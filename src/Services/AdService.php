<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdRepository;
use RuntimeException;

final class AdService
{
    public function __construct(
        private readonly AdRepository $ads = new AdRepository(),
        private readonly StorageManager $storage = new StorageManager(),
        private readonly MediaCleanupService $cleanup = new MediaCleanupService(),
        private readonly VideoSourceService $videoSources = new VideoSourceService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $files
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    public function saveSlot(string $slotKey, array $input, array $files, ?array $existing = null): array
    {
        $definitions = ad_slot_definitions();

        if (!isset($definitions[$slotKey])) {
            throw new RuntimeException('Unknown ad slot.');
        }

        $existing ??= $this->ads->findBySlot($slotKey);
        $mode = trim((string) ($input['ad_type'] ?? 'placeholder'));
        $isActive = ($input['is_active'] ?? '0') === '1' ? 1 : 0;
        $title = trim((string) ($input['title'] ?? ''));
        $bodyText = trim((string) ($input['body_text'] ?? ''));
        $clickUrl = $this->normalizeOptionalUrl((string) ($input['click_url'] ?? ''));
        $videoUrl = $this->normalizeOptionalVideoUrl((string) ($input['video_url'] ?? ''));
        $vastTagUrl = $this->normalizeOptionalUrl((string) ($input['vast_tag_url'] ?? ''));
        $scriptCode = trim((string) ($input['script_code'] ?? ''));
        $removeImage = ($input['remove_image'] ?? '0') === '1';
        $removeVideo = ($input['remove_video'] ?? '0') === '1';
        $skipAfterSeconds = max(0, min(30, (int) ($input['skip_after_seconds'] ?? ($existing['skip_after_seconds'] ?? 5))));

        $allowedTypes = $definitions[$slotKey]['types'] ?? ['placeholder', 'image', 'script', 'text'];

        if (!is_array($allowedTypes) || !in_array($mode, $allowedTypes, true)) {
            throw new RuntimeException('Choose a valid ad type.');
        }

        $payload = [
            'ad_type' => $mode,
            'is_active' => $isActive,
            'title' => $title !== '' ? $title : null,
            'body_text' => $bodyText !== '' ? $bodyText : null,
            'click_url' => $clickUrl,
            'image_url' => $existing['image_url'] ?? null,
            'image_path' => $existing['image_path'] ?? null,
            'image_storage_provider' => $existing['image_storage_provider'] ?? null,
            'video_url' => $existing['video_url'] ?? null,
            'video_path' => $existing['video_path'] ?? null,
            'video_storage_provider' => $existing['video_storage_provider'] ?? null,
            'video_mime_type' => $existing['video_mime_type'] ?? null,
            'vast_tag_url' => $vastTagUrl,
            'skip_after_seconds' => $skipAfterSeconds,
            'script_code' => $scriptCode !== '' ? $scriptCode : null,
        ];

        if ($removeImage || $mode === 'placeholder') {
            $this->cleanup->removePath(
                (string) ($existing['image_storage_provider'] ?? ''),
                isset($existing['image_path']) ? (string) $existing['image_path'] : null
            );
            $payload['image_url'] = null;
            $payload['image_path'] = null;
            $payload['image_storage_provider'] = null;
        }

        if ($removeVideo || $mode === 'placeholder') {
            $this->cleanup->removePath(
                (string) ($existing['video_storage_provider'] ?? ''),
                isset($existing['video_path']) ? (string) $existing['video_path'] : null
            );
            $payload['video_url'] = null;
            $payload['video_path'] = null;
            $payload['video_storage_provider'] = null;
            $payload['video_mime_type'] = null;
        }

        if (isset($files['image_file']) && is_array($files['image_file']) && uploaded_file_present($files['image_file'])) {
            $this->assertMimeType($files['image_file'], ['image/jpeg', 'image/png', 'image/webp'], 'ad image');
            $upload = $this->storage->driver()->storeUploadedFile($files['image_file'], 'ads');

            $this->cleanup->removePath(
                (string) ($existing['image_storage_provider'] ?? ''),
                isset($existing['image_path']) ? (string) $existing['image_path'] : null
            );

            $payload['image_url'] = $upload['url'];
            $payload['image_path'] = $upload['path'];
            $payload['image_storage_provider'] = $upload['disk'];
        }

        if (isset($files['video_file']) && is_array($files['video_file']) && uploaded_file_present($files['video_file'])) {
            $this->assertMimeType($files['video_file'], ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 'video/webm'], 'ad video');
            $upload = $this->storage->driver()->storeUploadedFile($files['video_file'], 'ad-videos');

            $this->cleanup->removePath(
                (string) ($existing['video_storage_provider'] ?? ''),
                isset($existing['video_path']) ? (string) $existing['video_path'] : null
            );

            $payload['video_url'] = $upload['url'];
            $payload['video_path'] = $upload['path'];
            $payload['video_storage_provider'] = $upload['disk'];
            $payload['video_mime_type'] = $upload['mime_type'] ?? 'video/mp4';
        }

        if ($videoUrl !== null) {
            $this->cleanup->removePath(
                (string) ($existing['video_storage_provider'] ?? ''),
                isset($existing['video_path']) ? (string) $existing['video_path'] : null
            );
            $payload['video_url'] = $videoUrl;
            $payload['video_path'] = null;
            $payload['video_storage_provider'] = 'external';
            $payload['video_mime_type'] = $this->videoMimeTypeFromUrl($videoUrl);
        }

        if ($mode === 'placeholder') {
            $payload['is_active'] = 0;
            $payload['title'] = null;
            $payload['body_text'] = null;
            $payload['click_url'] = null;
            $payload['video_url'] = null;
            $payload['video_path'] = null;
            $payload['video_storage_provider'] = null;
            $payload['video_mime_type'] = null;
            $payload['vast_tag_url'] = null;
            $payload['skip_after_seconds'] = 5;
            $payload['script_code'] = null;
        }

        if ($mode === 'image' && $isActive === 1) {
            if (trim((string) ($payload['image_url'] ?? '')) === '' && trim((string) ($payload['image_path'] ?? '')) === '') {
                throw new RuntimeException('Upload an image before activating this ad slot.');
            }
        }

        if ($mode === 'script' && $isActive === 1 && $scriptCode === '') {
            throw new RuntimeException('Paste the script code before activating this ad slot.');
        }

        if ($mode === 'text' && $isActive === 1 && $title === '' && $bodyText === '') {
            throw new RuntimeException('Add a title or text before activating this ad slot.');
        }

        if ($mode === 'video' && $isActive === 1) {
            if (
                trim((string) ($payload['video_url'] ?? '')) === ''
                && trim((string) ($payload['video_path'] ?? '')) === ''
            ) {
                throw new RuntimeException('Upload a video file or paste a direct video URL before activating this pre-roll slot.');
            }
        }

        if ($mode === 'vast' && $isActive === 1 && trim((string) ($payload['vast_tag_url'] ?? '')) === '') {
            throw new RuntimeException('Paste a VAST tag URL before activating this pre-roll slot.');
        }

        $this->ads->upsert($slotKey, $payload);

        return $this->ads->findBySlot($slotKey) ?? $payload;
    }

    private function normalizeOptionalUrl(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $validated = filter_var($trimmed, FILTER_VALIDATE_URL);

        if (!is_string($validated) || $validated === '') {
            throw new RuntimeException('Enter a valid URL for the ad click target.');
        }

        $scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Ad links must start with http:// or https://.');
        }

        return $validated;
    }

    private function normalizeOptionalVideoUrl(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $resolved = $this->videoSources->resolve($trimmed);

        if ((string) ($resolved['source_type'] ?? '') !== 'external_file') {
            throw new RuntimeException('Pre-roll video URLs must point directly to a public .mp4, .m4v, .webm, or .mov file.');
        }

        return (string) ($resolved['video_url'] ?? '');
    }

    private function videoMimeTypeFromUrl(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            str_ends_with($path, '.webm') => 'video/webm',
            str_ends_with($path, '.mov') => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    /**
     * @param array<string, mixed> $file
     * @param array<int, string> $allowedMimes
     */
    private function assertMimeType(array $file, array $allowedMimes, string $label): void
    {
        if (!isset($file['tmp_name']) || $file['tmp_name'] === '') {
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException(sprintf('Invalid %s file type. Detected: %s. Allowed: %s', $label, $mime, implode(', ', $allowedMimes)));
        }
    }
}
