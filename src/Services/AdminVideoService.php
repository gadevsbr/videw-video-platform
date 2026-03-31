<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\VideoRepository;
use DateTimeImmutable;
use RuntimeException;

final class AdminVideoService
{
    public function __construct(
        private readonly VideoRepository $videos = new VideoRepository(),
        private readonly StorageManager $storage = new StorageManager(),
        private readonly VideoSourceService $sources = new VideoSourceService(),
        private readonly MediaCleanupService $cleanup = new MediaCleanupService()
    ) {
    }

    /**
     * @param array<string, string> $input
     * @param array<string, array<string, mixed>> $files
     * @param array<string, mixed> $options
     * @return array{success:bool,message:string,video_id?:int}
     */
    public function publish(array $input, array $files, array $options = []): array
    {
        try {
            $payload = $this->buildPayload($input, $files, null, null, $options);
            $videoId = $this->videos->create($payload);
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Video created successfully.',
            'video_id' => $videoId,
        ];
    }

    /**
     * @param array<string, string> $input
     * @param array<string, array<string, mixed>> $files
     * @param array<string, mixed> $options
     * @return array{success:bool,message:string,video_id?:int}
     */
    public function update(int $videoId, array $input, array $files, array $options = []): array
    {
        $existing = $this->videos->findById($videoId);

        if (!$existing) {
            return ['success' => false, 'message' => 'Video not found.'];
        }

        try {
            $payload = $this->buildPayload($input, $files, $existing, $videoId, $options);
            $this->videos->update($videoId, $payload);
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Video updated successfully.',
            'video_id' => $videoId,
        ];
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function delete(int $videoId): array
    {
        $existing = $this->videos->findById($videoId);

        if (!$existing) {
            return ['success' => false, 'message' => 'Video not found.'];
        }

        try {
            $this->cleanup->removeVideoAssets($existing, true, true);
            $this->videos->softDelete($videoId);
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Video deleted and stored assets cleaned up.',
        ];
    }

    /**
     * @param array<int, int> $videoIds
     */
    public function bulkDelete(array $videoIds): int
    {
        $count = 0;

        foreach (array_unique(array_filter(array_map('intval', $videoIds), static fn (int $id): bool => $id > 0)) as $videoId) {
            $result = $this->delete($videoId);

            if ($result['success']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, string> $input
     * @param array<string, array<string, mixed>> $files
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildPayload(array $input, array $files, ?array $existing = null, ?int $videoId = null, array $options = []): array
    {
        $this->assertUploadStatus($files['video_file'] ?? null, 'video');
        $this->assertUploadStatus($files['poster_file'] ?? null, 'poster');

        $title = trim($input['title'] ?? (string) ($existing['title'] ?? ''));
        $synopsis = trim($input['synopsis'] ?? (string) ($existing['synopsis'] ?? ''));
        $forcedCreatorName = trim((string) ($options['creator_name'] ?? ''));
        $allowCreatorNameInput = ($options['allow_creator_name_input'] ?? true) !== false;
        $creatorName = $allowCreatorNameInput
            ? trim($input['creator_name'] ?? $forcedCreatorName ?: (string) ($existing['creator_name'] ?? ''))
            : ($forcedCreatorName !== '' ? $forcedCreatorName : trim((string) ($existing['creator_name'] ?? '')));
        $category = trim($input['category'] ?? (string) ($existing['category'] ?? ''));
        $accessLevel = normalize_access_level(trim($input['access_level'] ?? (string) ($existing['access_level'] ?? 'free')));
        $durationMinutes = (int) ($input['duration_minutes'] ?? (int) ($existing['duration_minutes'] ?? 0));
        $existingSourceType = (string) ($existing['source_type'] ?? 'upload');
        $sourceMode = trim($input['source_mode'] ?? ($existingSourceType === 'upload' ? 'file' : 'url'));
        $externalUrl = trim($input['external_url'] ?? '');
        $posterSourceMode = trim($input['poster_source_mode'] ?? (($existing && !empty($existing['poster_path'])) ? 'upload' : (!empty($existing['stored_poster_url']) ? 'url' : 'upload')));
        $posterExternalUrl = trim($input['poster_external_url'] ?? '');
        $posterFocusX = normalize_poster_focus($input['poster_focus_x'] ?? ($existing['poster_focus_x'] ?? 50));
        $posterFocusY = normalize_poster_focus($input['poster_focus_y'] ?? ($existing['poster_focus_y'] ?? 50));
        $allowFeatured = ($options['allow_featured'] ?? true) !== false;
        $isFeatured = $allowFeatured
            ? (($input['is_featured'] ?? (string) ($existing['is_featured'] ?? '0')) === '1' ? 1 : 0)
            : (int) ($existing['is_featured'] ?? 0);
        $allowedModerationStatuses = $options['allowed_moderation_statuses'] ?? ['draft', 'approved', 'flagged'];
        $allowedModerationStatuses = is_array($allowedModerationStatuses)
            ? array_values(array_filter($allowedModerationStatuses, static fn (mixed $status): bool => in_array((string) $status, ['draft', 'approved', 'flagged'], true)))
            : ['draft', 'approved', 'flagged'];
        $defaultModerationStatus = (string) ($options['default_moderation_status'] ?? ($existing['moderation_status'] ?? 'draft'));
        if (!in_array($defaultModerationStatus, ['draft', 'approved', 'flagged'], true)) {
            $defaultModerationStatus = 'draft';
        }
        $moderationStatus = trim($input['moderation_status'] ?? $defaultModerationStatus);
        $moderationNotes = trim($input['moderation_notes'] ?? (string) ($existing['moderation_notes'] ?? ''));
        $removePoster = ($input['remove_poster'] ?? '') === '1';

        if ($title === '' || $synopsis === '' || $creatorName === '' || $category === '') {
            throw new RuntimeException('Title, creator, category, and description are required.');
        }

        if (!in_array($accessLevel, ['free', 'premium'], true)) {
            throw new RuntimeException('Invalid access level.');
        }

        if (!in_array($moderationStatus, $allowedModerationStatuses, true)) {
            $moderationStatus = $defaultModerationStatus;
        }

        if ($durationMinutes < 0) {
            throw new RuntimeException('Duration must be zero or higher.');
        }

        $allowedSourceModes = $existing ? ['', 'file', 'url'] : ['file', 'url'];

        if (!in_array($sourceMode, $allowedSourceModes, true)) {
            throw new RuntimeException($existing ? 'Choose how you want to replace the video.' : 'Choose how you want to add the video.');
        }

        if (!in_array($posterSourceMode, ['', 'upload', 'url'], true)) {
            throw new RuntimeException('Choose how you want to add the poster.');
        }

        $payload = [
            'slug' => $this->videos->generateUniqueSlug($title, $videoId),
            'title' => $title,
            'synopsis' => $synopsis,
            'creator_user_id' => !empty($options['creator_user_id'])
                ? (int) $options['creator_user_id']
                : ($existing['creator_user_id'] ?? null),
            'creator_name' => $creatorName,
            'category' => $category,
            'access_level' => $accessLevel,
            'duration_minutes' => $durationMinutes,
            'poster_tone' => (int) ($existing['poster_tone'] ?? random_int(0, 3)),
            'poster_url' => $existing['stored_poster_url'] ?? null,
            'poster_path' => $existing['poster_path'] ?? null,
            'poster_storage_provider' => $existing['poster_storage_provider'] ?? null,
            'poster_focus_x' => $posterFocusX,
            'poster_focus_y' => $posterFocusY,
            'video_url' => $existing['video_url'] ?? null,
            'file_path' => $existing['file_path'] ?? null,
            'trailer_url' => $existing['trailer_url'] ?? null,
            'embed_url' => $existing['embed_url'] ?? null,
            'mime_type' => $existing['mime_type'] ?? null,
            'original_source_url' => $existing['original_source_url'] ?? null,
            'source_type' => $existing['source_type'] ?? 'upload',
            'storage_provider' => $existing['storage_provider'] ?? $this->storage->driverName(),
            'is_featured' => $isFeatured,
            'moderation_status' => $moderationStatus,
            'moderation_notes' => $moderationNotes !== '' ? $moderationNotes : null,
            'published_at' => $this->resolvePublishedAt($moderationStatus, $existing),
        ];

        if ($sourceMode === 'url') {
            if ($externalUrl !== '') {
                $resolved = $this->sources->resolve($externalUrl);
                $payload = array_merge($payload, $resolved);
                $payload['file_path'] = null;
                $payload['trailer_url'] = null;

                if ($existing && (string) ($existing['source_type'] ?? '') === 'upload' && !empty($existing['file_path'])) {
                    $this->cleanup->removePath((string) ($existing['storage_provider'] ?? ''), (string) $existing['file_path']);
                }
            } elseif ($existing === null) {
                throw new RuntimeException('Enter an external URL or switch to file upload.');
            }
        } elseif ($sourceMode === 'file') {
            if (isset($files['video_file']) && uploaded_file_present($files['video_file'])) {
                $upload = $this->storage->driver()->storeUploadedFile($files['video_file'], 'videos');
                $payload['source_type'] = 'upload';
                $payload['storage_provider'] = $upload['disk'];
                $payload['video_url'] = $upload['url'];
                $payload['file_path'] = $upload['path'];
                $payload['mime_type'] = $upload['mime_type'];
                $payload['embed_url'] = null;
                $payload['original_source_url'] = null;

                if ($existing && (string) ($existing['source_type'] ?? '') === 'upload' && !empty($existing['file_path'])) {
                    $this->cleanup->removePath((string) ($existing['storage_provider'] ?? ''), (string) $existing['file_path']);
                }
            } elseif ($existing === null) {
                throw new RuntimeException('Upload a video file or switch to external URL mode.');
            }
        }

        if ($removePoster && $existing && (!empty($existing['poster_path']) || !empty($existing['stored_poster_url']))) {
            if (!empty($existing['poster_path'])) {
                $this->cleanup->removePath(
                    (string) ($existing['poster_storage_provider'] ?? $existing['storage_provider'] ?? ''),
                    (string) $existing['poster_path']
                );
            }

            $payload['poster_url'] = null;
            $payload['poster_path'] = null;
            $payload['poster_storage_provider'] = null;
        }

        if ($posterSourceMode === 'url' && $posterExternalUrl !== '') {
            $resolvedPosterUrl = $this->sources->resolvePosterUrl($posterExternalUrl);

            if ($existing && !empty($existing['poster_path'])) {
                $this->cleanup->removePath(
                    (string) ($existing['poster_storage_provider'] ?? $existing['storage_provider'] ?? ''),
                    (string) $existing['poster_path']
                );
            }

            $payload['poster_url'] = $resolvedPosterUrl;
            $payload['poster_path'] = null;
            $payload['poster_storage_provider'] = 'external';
        } elseif ($posterSourceMode === 'upload' && isset($files['poster_file']) && uploaded_file_present($files['poster_file'])) {
            $posterUpload = $this->storage->driver()->storeUploadedFile($files['poster_file'], 'posters');

            if ($existing && !empty($existing['poster_path'])) {
                $this->cleanup->removePath(
                    (string) ($existing['poster_storage_provider'] ?? $existing['storage_provider'] ?? ''),
                    (string) $existing['poster_path']
                );
            }

            $payload['poster_url'] = $posterUpload['url'];
            $payload['poster_path'] = $posterUpload['path'];
            $payload['poster_storage_provider'] = $posterUpload['disk'];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed>|null $file
     */
    private function assertUploadStatus(?array $file, string $label): void
    {
        if (!is_array($file) || !isset($file['error'])) {
            return;
        }

        $error = (int) $file['error'];

        if ($error === UPLOAD_ERR_OK || $error === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $prefix = ucfirst($label) . ' upload failed';

        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $prefix . '. The file is larger than the server limit.',
            UPLOAD_ERR_PARTIAL => $prefix . '. The upload was interrupted before it finished.',
            UPLOAD_ERR_NO_TMP_DIR => $prefix . '. The server is missing a temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => $prefix . '. The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => $prefix . '. A server extension blocked the upload.',
            default => $prefix . '. Try again.',
        };

        throw new RuntimeException($message);
    }

    /**
     * @param array<string, mixed>|null $existing
     */
    private function resolvePublishedAt(string $moderationStatus, ?array $existing): ?string
    {
        if ($moderationStatus !== 'approved') {
            return $existing['published_at'] ?? null;
        }

        if (!empty($existing['published_at'])) {
            return (string) $existing['published_at'];
        }

        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
