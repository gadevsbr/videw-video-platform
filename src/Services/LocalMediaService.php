<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use App\Repositories\VideoRepository;
use RuntimeException;

final class LocalMediaService
{
    public function __construct(
        private readonly VideoRepository $videos = new VideoRepository(),
        private readonly StorageManager $storage = new StorageManager(),
        private readonly SettingsRepository $settings = new SettingsRepository()
    ) {
    }

    public function stream(int $videoId, string $asset): never
    {
        $video = $this->videos->findById($videoId);

        if (!$video) {
            $this->abort(404, 'Media not found.');
        }

        if ($asset === 'video') {
            if (!can_watch_video($video)) {
                $this->abort(403, 'Premium access required.');
            }

            $storageProvider = (string) ($video['storage_provider'] ?? '');

            if ($storageProvider === 'local' && !empty($video['file_path'])) {
                $this->streamFile((string) $video['file_path'], (string) ($video['mime_type'] ?? 'video/mp4'), true, video_requires_premium($video));
            }

            if ($storageProvider === 'wasabi' && !empty($video['file_path'])) {
                $this->redirectToWasabi((string) $video['file_path'], video_requires_premium($video));
            }

            $this->abort(404, 'Media not found.');
        }

        if ($asset === 'poster') {
            if ((string) ($video['poster_storage_provider'] ?? '') !== 'local' || empty($video['poster_path'])) {
                $this->abort(404, 'Media not found.');
            }

            $this->streamFile((string) $video['poster_path'], '', false, false);
        }

        $this->abort(404, 'Media not found.');
    }

    private function redirectToWasabi(string $objectPath, bool $privateCache): never
    {
        try {
            $ttl = (int) ($this->settings->get('wasabi_signed_url_ttl_seconds', (string) config('storage.wasabi_signed_url_ttl_seconds', '900')) ?? '900');
            $ttl = max(60, min(604800, $ttl));
            $signedUrl = $this->storage->wasabiClient()->presignGetObject($objectPath, $ttl);
        } catch (RuntimeException $exception) {
            $this->abort(500, $exception->getMessage());
        }

        header('Cache-Control: ' . ($privateCache ? 'private, no-store, max-age=0' : 'public, max-age=300'));
        header('Location: ' . $signedUrl, true, 302);
        exit;
    }

    private function abort(int $statusCode, string $message): never
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        exit;
    }

    private function streamFile(string $relativePath, string $mimeType, bool $supportsRanges, bool $privateCache): never
    {
        $root = rtrim((string) config('storage.local_root', ROOT_PATH . '/storage/uploads'), '/\\');
        $normalizedPath = str_replace('\\', '/', ltrim($relativePath, '/\\'));
        $absolutePath = $root . '/' . $normalizedPath;
        $resolvedRoot = realpath($root);
        $resolvedPath = realpath($absolutePath);

        if (!is_string($resolvedRoot) || !is_string($resolvedPath) || !str_starts_with(str_replace('\\', '/', $resolvedPath), str_replace('\\', '/', $resolvedRoot)) || !is_file($resolvedPath)) {
            $this->abort(404, 'Media file not found.');
        }

        $size = (int) filesize($resolvedPath);
        $mime = $mimeType !== '' ? $mimeType : $this->detectMimeType($resolvedPath);
        $start = 0;
        $end = max(0, $size - 1);
        $statusCode = 200;

        if ($supportsRanges) {
            header('Accept-Ranges: bytes');

            if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $matches) === 1) {
                $rangeStart = $matches[1] !== '' ? (int) $matches[1] : 0;
                $rangeEnd = $matches[2] !== '' ? (int) $matches[2] : $end;

                if ($rangeStart > $rangeEnd || $rangeEnd >= $size) {
                    header('Content-Range: bytes */' . $size);
                    $this->abort(416, 'Requested range not satisfiable.');
                }

                $start = $rangeStart;
                $end = $rangeEnd;
                $statusCode = 206;
            }
        }

        $length = ($end - $start) + 1;
        http_response_code($statusCode);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $length);
        header('Content-Disposition: inline; filename="' . basename($resolvedPath) . '"');
        header('Cache-Control: ' . ($privateCache ? 'private, no-store, max-age=0' : 'public, max-age=86400'));

        if ($statusCode === 206) {
            header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $handle = fopen($resolvedPath, 'rb');

        if (!is_resource($handle)) {
            $this->abort(500, 'Could not open the media file.');
        }

        fseek($handle, $start);
        $remaining = $length;

        while (!feof($handle) && $remaining > 0) {
            $chunkSize = min(8192, $remaining);
            $buffer = fread($handle, $chunkSize);

            if ($buffer === false) {
                break;
            }

            echo $buffer;
            flush();
            $remaining -= strlen($buffer);
        }

        fclose($handle);
        exit;
    }

    private function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo !== false) {
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return 'application/octet-stream';
    }
}
