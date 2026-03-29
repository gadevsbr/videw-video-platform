<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

final class MediaCleanupService
{
    public function __construct(
        private readonly StorageManager $storage = new StorageManager()
    ) {
    }

    public function removePath(?string $disk, ?string $path): void
    {
        $normalizedDisk = in_array($disk, ['local', 'wasabi'], true) ? $disk : null;
        $normalizedPath = trim((string) $path);

        if ($normalizedDisk === null || $normalizedPath === '') {
            return;
        }

        try {
            $this->storage->driverForDisk($normalizedDisk)->delete($normalizedPath);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not remove a stored media file. ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $video
     */
    public function removeVideoAssets(array $video, bool $removeVideo = true, bool $removePoster = true): void
    {
        if ($removeVideo && (string) ($video['source_type'] ?? '') === 'upload') {
            $this->removePath(
                (string) ($video['storage_provider'] ?? ''),
                isset($video['file_path']) ? (string) $video['file_path'] : null
            );
        }

        if ($removePoster) {
            $this->removePath(
                (string) ($video['poster_storage_provider'] ?? $video['storage_provider'] ?? ''),
                isset($video['poster_path']) ? (string) $video['poster_path'] : null
            );
        }
    }
}
