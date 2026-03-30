<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class VideoSourceService
{
    /**
     * @return array<string, string|null>
     */
    public function resolve(string $url): array
    {
        $normalized = trim($url);

        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Enter a valid external URL.');
        }

        $this->assertAllowedExternalUrl($normalized);

        if ($this->isDirectVideoUrl($normalized)) {
            return [
                'source_type' => 'external_file',
                'storage_provider' => 'external',
                'original_source_url' => $normalized,
                'video_url' => $normalized,
                'embed_url' => null,
                'mime_type' => $this->mimeTypeFromUrl($normalized),
            ];
        }

        $embedUrl = $this->detectEmbedUrl($normalized);

        if ($embedUrl !== null) {
            return [
                'source_type' => 'embed',
                'storage_provider' => 'external',
                'original_source_url' => $normalized,
                'video_url' => null,
                'embed_url' => $embedUrl,
                'mime_type' => null,
            ];
        }

        throw new RuntimeException('Supported external URLs: direct .mp4/.webm/.m3u8 files or videos from YouTube, Vimeo, and Dailymotion.');
    }

    public function resolvePosterUrl(string $url): string
    {
        $normalized = trim($url);

        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Enter a valid poster URL.');
        }

        $this->assertAllowedExternalUrl($normalized);

        return $normalized;
    }

    private function isDirectVideoUrl(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return (bool) preg_match('/\.(mp4|m4v|webm|mov|m3u8)$/i', $path);
    }

    private function mimeTypeFromUrl(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            str_ends_with($path, '.webm') => 'video/webm',
            str_ends_with($path, '.m3u8') => 'application/x-mpegURL',
            str_ends_with($path, '.mov') => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    private function detectEmbedUrl(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($host, 'youtube.com') || $host === 'youtu.be') {
            $videoId = '';

            if ($host === 'youtu.be') {
                $videoId = trim($path, '/');
            } elseif (isset($query['v'])) {
                $videoId = (string) $query['v'];
            } elseif (preg_match('#/(embed|shorts)/([^/?]+)#', $path, $matches)) {
                $videoId = $matches[2];
            }

            return $videoId !== '' ? 'https://www.youtube.com/embed/' . rawurlencode($videoId) : null;
        }

        if (str_contains($host, 'vimeo.com')) {
            if (preg_match('#/(?:video/)?([0-9]+)#', $path, $matches)) {
                return 'https://player.vimeo.com/video/' . rawurlencode($matches[1]);
            }
        }

        if (str_contains($host, 'dailymotion.com') || $host === 'dai.ly') {
            if ($host === 'dai.ly' && trim($path, '/') !== '') {
                return 'https://www.dailymotion.com/embed/video/' . rawurlencode(trim($path, '/'));
            }

            if (preg_match('#/video/([^_/?]+)#', $path, $matches)) {
                return 'https://www.dailymotion.com/embed/video/' . rawurlencode($matches[1]);
            }
        }

        return null;
    }

    private function assertAllowedExternalUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $user = trim((string) parse_url($url, PHP_URL_USER));
        $pass = trim((string) parse_url($url, PHP_URL_PASS));

        if ($scheme !== 'https') {
            throw new RuntimeException('External video URLs must use HTTPS.');
        }

        if ($host === '' || $user !== '' || $pass !== '') {
            throw new RuntimeException('Enter a valid public external URL.');
        }

        if ($host === 'localhost' || !str_contains($host, '.') || preg_match('/\.(local|internal|test|home|lan)$/', $host) === 1) {
            throw new RuntimeException('Local or internal URLs are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Private or reserved IP ranges are not allowed.');
        }
    }
}
