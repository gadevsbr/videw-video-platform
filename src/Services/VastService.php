<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;

final class VastService
{
    private const MAX_WRAPPER_DEPTH = 3;
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return array<string, mixed>|null
     */
    public function resolveTag(string $tagUrl, int $fallbackSkipAfterSeconds = 5): ?array
    {
        $normalizedUrl = $this->normalizePublicHttpsUrl($tagUrl);

        return $this->resolveDocument($normalizedUrl, max(0, $fallbackSkipAfterSeconds), 0, [
            'impression' => [],
            'start' => [],
            'firstQuartile' => [],
            'midpoint' => [],
            'thirdQuartile' => [],
            'complete' => [],
            'skip' => [],
            'clickTracking' => [],
        ]);
    }

    /**
     * @param array<string, array<int, string>> $carryTracking
     * @return array<string, mixed>|null
     */
    private function resolveDocument(string $url, int $fallbackSkipAfterSeconds, int $depth, array $carryTracking): ?array
    {
        if ($depth > self::MAX_WRAPPER_DEPTH) {
            return null;
        }

        $xml = $this->loadXml($url);

        if ($xml === null) {
            return null;
        }

        $wrapper = $this->firstNode($xml->xpath('//Wrapper'));

        if ($wrapper instanceof SimpleXMLElement) {
            $mergedTracking = $this->mergeTracking($carryTracking, $this->extractTracking($wrapper));
            $vastTagUri = $this->nodeText($wrapper->VASTAdTagURI ?? null);

            if ($vastTagUri === '') {
                return null;
            }

            $nextUrl = $this->normalizePublicHttpsUrl($vastTagUri);

            return $this->resolveDocument($nextUrl, $fallbackSkipAfterSeconds, $depth + 1, $mergedTracking);
        }

        $inline = $this->firstNode($xml->xpath('//InLine'));

        if (!$inline instanceof SimpleXMLElement) {
            return null;
        }

        $linear = $this->firstNode($inline->xpath('.//Linear'));

        if (!$linear instanceof SimpleXMLElement) {
            return null;
        }

        $media = $this->chooseMediaFile($linear);

        if ($media === null) {
            return null;
        }

        $tracking = $this->mergeTracking($carryTracking, $this->extractTracking($inline));
        $clickThrough = $this->nodeText($linear->xpath('.//VideoClicks/ClickThrough')[0] ?? null);
        $skipOffset = $this->parseSkipOffset((string) ($linear['skipoffset'] ?? ''), $fallbackSkipAfterSeconds);

        return [
            'tag_url' => $url,
            'title' => $this->nodeText($inline->AdTitle ?? null),
            'body_text' => $this->nodeText($inline->Description ?? null),
            'media_url' => $media['url'],
            'mime_type' => $media['mime_type'],
            'duration' => $this->parseDurationToSeconds($this->nodeText($linear->Duration ?? null)),
            'skip_after_seconds' => $skipOffset,
            'click_url' => $clickThrough,
            'tracking' => $tracking,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function chooseMediaFile(SimpleXMLElement $linear): ?array
    {
        $mediaFiles = $linear->xpath('.//MediaFiles/MediaFile');

        if (!is_array($mediaFiles) || $mediaFiles === []) {
            return null;
        }

        $candidates = [];

        foreach ($mediaFiles as $mediaFile) {
            if (!$mediaFile instanceof SimpleXMLElement) {
                continue;
            }

            $url = $this->nodeText($mediaFile);

            if ($url === '') {
                continue;
            }

            try {
                $normalizedUrl = $this->normalizePublicHttpsUrl($url);
            } catch (RuntimeException) {
                continue;
            }

            $delivery = strtolower(trim((string) ($mediaFile['delivery'] ?? '')));
            $mimeType = strtolower(trim((string) ($mediaFile['type'] ?? 'video/mp4')));
            $bitrate = (int) ($mediaFile['bitrate'] ?? 0);
            $width = (int) ($mediaFile['width'] ?? 0);
            $height = (int) ($mediaFile['height'] ?? 0);
            $isProgressive = $delivery === '' || $delivery === 'progressive';
            $mimeScore = match ($mimeType) {
                'video/mp4' => 3,
                'video/webm' => 2,
                default => 1,
            };

            $candidates[] = [
                'url' => $normalizedUrl,
                'mime_type' => $mimeType !== '' ? $mimeType : 'video/mp4',
                'score' => ($isProgressive ? 1000 : 0) + ($mimeScore * 100) + min($bitrate, 999) + min($width + $height, 2000),
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => ($right['score'] ?? 0) <=> ($left['score'] ?? 0)
        );

        return [
            'url' => (string) $candidates[0]['url'],
            'mime_type' => (string) $candidates[0]['mime_type'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function extractTracking(SimpleXMLElement $node): array
    {
        $tracking = [
            'impression' => $this->nodeTexts($node->xpath('.//Impression')),
            'start' => $this->trackingTexts($node, 'start'),
            'firstQuartile' => $this->trackingTexts($node, 'firstQuartile'),
            'midpoint' => $this->trackingTexts($node, 'midpoint'),
            'thirdQuartile' => $this->trackingTexts($node, 'thirdQuartile'),
            'complete' => $this->trackingTexts($node, 'complete'),
            'skip' => $this->trackingTexts($node, 'skip'),
            'clickTracking' => $this->nodeTexts($node->xpath('.//VideoClicks/ClickTracking')),
        ];

        return array_map(
            static fn (array $items): array => array_values(array_unique(array_filter($items, static fn (string $item): bool => $item !== ''))),
            $tracking
        );
    }

    /**
     * @param array<string, array<int, string>> $carry
     * @param array<string, array<int, string>> $current
     * @return array<string, array<int, string>>
     */
    private function mergeTracking(array $carry, array $current): array
    {
        $keys = array_unique(array_merge(array_keys($carry), array_keys($current)));
        $merged = [];

        foreach ($keys as $key) {
            $merged[$key] = array_values(array_unique(array_merge($carry[$key] ?? [], $current[$key] ?? [])));
        }

        return $merged;
    }

    /**
     * @return array<int, string>
     */
    private function trackingTexts(SimpleXMLElement $node, string $event): array
    {
        return $this->nodeTexts($node->xpath('.//Tracking[@event="' . $event . '"]'));
    }

    /**
     * @param mixed $value
     */
    private function nodeText(mixed $value): string
    {
        if ($value instanceof SimpleXMLElement) {
            return trim((string) $value);
        }

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param mixed $nodes
     * @return array<int, string>
     */
    private function nodeTexts(mixed $nodes): array
    {
        if (!is_array($nodes)) {
            return [];
        }

        $values = [];

        foreach ($nodes as $node) {
            $text = $this->nodeText($node);

            if ($text !== '') {
                $values[] = $text;
            }
        }

        return $values;
    }

    /**
     * @param mixed $nodes
     */
    private function firstNode(mixed $nodes): ?SimpleXMLElement
    {
        return is_array($nodes) && $nodes !== [] && $nodes[0] instanceof SimpleXMLElement
            ? $nodes[0]
            : null;
    }

    private function parseDurationToSeconds(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^(?:(\d+):)?(\d{2}):(\d{2})(?:\.\d+)?$/', $value, $matches)) {
            return null;
        }

        $hours = isset($matches[1]) ? (int) $matches[1] : 0;
        $minutes = (int) $matches[2];
        $seconds = (int) $matches[3];

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    private function parseSkipOffset(string $value, int $fallback): int
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $fallback;
        }

        if (str_ends_with($trimmed, '%')) {
            return $fallback;
        }

        $seconds = $this->parseDurationToSeconds($trimmed);

        if ($seconds === null) {
            return $fallback;
        }

        return max(0, $seconds);
    }

    private function normalizePublicHttpsUrl(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '' || !filter_var($trimmed, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid URL.');
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($trimmed, PHP_URL_HOST));

        if ($scheme !== 'https' || $host === '') {
            throw new RuntimeException('Only public HTTPS URLs are supported.');
        }

        if ($host === 'localhost' || !str_contains($host, '.') || preg_match('/\.(local|internal|test|home|lan)$/', $host) === 1) {
            throw new RuntimeException('Local or internal URLs are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Private or reserved IP ranges are not allowed.');
        }

        return $trimmed;
    }

    private function loadXml(string $url): ?SimpleXMLElement
    {
        $cacheKey = sha1($url);
        $cachePath = ROOT_PATH . '/storage/runtime/vast-' . $cacheKey . '.xml';
        $xmlPayload = null;

        if (is_file($cachePath) && (time() - (int) filemtime($cachePath)) < self::CACHE_TTL_SECONDS) {
            $cached = file_get_contents($cachePath);

            if (is_string($cached) && $cached !== '') {
                $xmlPayload = $cached;
            }
        }

        if (!is_string($xmlPayload) || $xmlPayload === '') {
            $xmlPayload = $this->fetchRemoteXml($url);

            if ($xmlPayload === null || $xmlPayload === '') {
                return null;
            }

            @file_put_contents($cachePath, $xmlPayload);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlPayload, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml instanceof SimpleXMLElement ? $xml : null;
    }

    private function fetchRemoteXml(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);

            if ($handle !== false) {
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_USERAGENT => 'VIDEW/1.1 VAST Resolver',
                    CURLOPT_HTTPHEADER => ['Accept: application/xml,text/xml,*/*'],
                ]);
                $response = curl_exec($handle);
                $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                curl_close($handle);

                if (is_string($response) && $response !== '' && $status >= 200 && $status < 400) {
                    return $response;
                }
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/xml,text/xml,*/*\r\nUser-Agent: VIDEW/1.1 VAST Resolver\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);

        return is_string($response) && $response !== '' ? $response : null;
    }
}
