<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use RuntimeException;
use Throwable;

final class MediaAccessService
{
    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository(),
        private readonly StorageManager $storage = new StorageManager()
    ) {
    }

    /**
     * @param array<string, mixed> $video
     * @return array<string, mixed>
     */
    public function decorateVideo(array $video): array
    {
        $video['resolved_poster_url'] = $video['poster_url'] ?? null;
        $video['resolved_listing_poster_url'] = $video['listing_poster_url'] ?? ($video['poster_url'] ?? null);
        $video['resolved_video_url'] = $video['video_url'] ?? null;
        $shouldProtectWasabiVideo = ($video['storage_provider'] ?? '') === 'wasabi'
            && !empty($video['file_path'])
            && !empty($video['id'])
            && (video_requires_premium($video) || $this->usesSignedUrls());

        if (($video['poster_storage_provider'] ?? '') === 'local' && !empty($video['poster_path']) && !empty($video['id'])) {
            $posterUrl = base_url('media.php?video=' . urlencode((string) $video['id']) . '&asset=poster');
            $video['resolved_poster_url'] = $posterUrl;
            $video['resolved_listing_poster_url'] = $posterUrl;
        }

        if (($video['storage_provider'] ?? '') === 'local' && !empty($video['file_path']) && !empty($video['id'])) {
            $video['resolved_video_url'] = base_url('media.php?video=' . urlencode((string) $video['id']) . '&asset=video');
        }

        if ($shouldProtectWasabiVideo) {
            $video['resolved_video_url'] = base_url('media.php?video=' . urlencode((string) $video['id']) . '&asset=video');
        }

        if (($video['storage_provider'] ?? '') !== 'wasabi' || !$this->usesSignedUrls()) {
            return $video;
        }

        try {
            $client = $this->storage->wasabiClient();
            $ttl = $this->signedUrlTtlSeconds();

            if (!empty($video['poster_path'])) {
                $signedPosterUrl = $client->presignGetObject((string) $video['poster_path'], $ttl);
                $video['resolved_poster_url'] = $signedPosterUrl;
                $video['resolved_listing_poster_url'] = $signedPosterUrl;
            }

            if (!empty($video['file_path'])) {
                if (!$shouldProtectWasabiVideo) {
                    $video['resolved_video_url'] = $client->presignGetObject((string) $video['file_path'], $ttl);
                }
            }
        } catch (RuntimeException | Throwable) {
            return $video;
        }

        return $video;
    }

    /**
     * @param array<int, array<string, mixed>> $videos
     * @return array<int, array<string, mixed>>
     */
    public function decorateVideos(array $videos): array
    {
        return array_map(fn (array $video): array => $this->decorateVideo($video), $videos);
    }

    public function usesSignedUrls(): bool
    {
        return ($this->settings->get('wasabi_private_bucket', (string) config('storage.wasabi_private_bucket', '0')) ?? '0') === '1';
    }

    public function signedUrlTtlSeconds(): int
    {
        $ttl = (int) ($this->settings->get('wasabi_signed_url_ttl_seconds', (string) config('storage.wasabi_signed_url_ttl_seconds', '900')) ?? 900);
        return max(60, min(604800, $ttl));
    }
}
