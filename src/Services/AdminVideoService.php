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
     * @return array{success:bool,message:string,video_id?:int}
     */
    public function publish(array $input, array $files): array
    {
        try {
            $payload = $this->buildPayload($input, $files);
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
     * @return array{success:bool,message:string,video_id?:int}
     */
    public function update(int $videoId, array $input, array $files): array
    {
        $existing = $this->videos->findById($videoId);

        if (!$existing) {
            return ['success' => false, 'message' => 'Video not found.'];
        }

        try {
            $payload = $this->buildPayload($input, $files, $existing, $videoId);
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
     * @return array<string, mixed>
     */
    private function buildPayload(array $input, array $files, ?array $existing = null, ?int $videoId = null): array
    {
        $title = trim($input['title'] ?? (string) ($existing['title'] ?? ''));
        $synopsis = trim($input['synopsis'] ?? (string) ($existing['synopsis'] ?? ''));
        $creatorName = trim($input['creator_name'] ?? (string) ($existing['creator_name'] ?? ''));
        $category = trim($input['category'] ?? (string) ($existing['category'] ?? ''));
        $accessLevel = normalize_access_level(trim($input['access_level'] ?? (string) ($existing['access_level'] ?? 'free')));
        $durationMinutes = (int) ($input['duration_minutes'] ?? (int) ($existing['duration_minutes'] ?? 0));
        $existingSourceType = (string) ($existing['source_type'] ?? 'upload');
        $sourceMode = trim($input['source_mode'] ?? ($existingSourceType === 'upload' ? 'file' : 'url'));
        $externalUrl = trim($input['external_url'] ?? '');
        $posterSourceMode = trim($input['poster_source_mode'] ?? (($existing && !empty($existing['poster_path'])) ? 'upload' : (!empty($existing['stored_poster_url']) ? 'url' : 'upload')));
        $posterExternalUrl = trim($input['poster_external_url'] ?? '');
        $isFeatured = ($input['is_featured'] ?? (string) ($existing['is_featured'] ?? '0')) === '1' ? 1 : 0;
        $moderationStatus = trim($input['moderation_status'] ?? (string) ($existing['moderation_status'] ?? 'draft'));
        $moderationNotes = trim($input['moderation_notes'] ?? (string) ($existing['moderation_notes'] ?? ''));
        $removePoster = ($input['remove_poster'] ?? '') === '1';

        if ($title === '' || $synopsis === '' || $creatorName === '' || $category === '') {
            throw new RuntimeException('Title, creator, category, and description are required.');
        }

        if (!in_array($accessLevel, ['free', 'premium'], true)) {
            throw new RuntimeException('Invalid access level.');
        }

        if (!in_array($moderationStatus, ['draft', 'approved', 'flagged'], true)) {
            throw new RuntimeException('Invalid moderation status.');
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
            'creator_name' => $creatorName,
            'category' => $category,
            'access_level' => $accessLevel,
            'duration_minutes' => $durationMinutes,
            'poster_tone' => (int) ($existing['poster_tone'] ?? random_int(0, 3)),
            'poster_url' => $existing['stored_poster_url'] ?? null,
            'poster_path' => $existing['poster_path'] ?? null,
            'poster_storage_provider' => $existing['poster_storage_provider'] ?? null,
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
