<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class GitHubReleaseService
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @return array<string, mixed>
     */
    public function updateStatus(string $repository, ?string $installedVersion = null): array
    {
        $repository = $this->normalizeRepository($repository);
        $installedVersion = $this->resolveInstalledVersion($installedVersion);

        $status = [
            'repository' => $repository,
            'installed_version' => $installedVersion,
            'latest_version' => '',
            'release_name' => '',
            'release_url' => '',
            'published_at' => null,
            'checked_at' => null,
            'summary' => '',
            'status' => 'disabled',
            'message' => 'Add a GitHub repository in settings to enable release checks.',
            'is_update_available' => false,
            'error' => '',
        ];

        if ($repository === '') {
            return $status;
        }

        try {
            $release = $this->latestRelease($repository);
        } catch (RuntimeException $exception) {
            $status['status'] = 'error';
            $status['message'] = 'Could not fetch the latest GitHub release.';
            $status['error'] = $exception->getMessage();
            return $status;
        }

        $latestVersion = $this->extractVersion((string) ($release['tag_name'] ?? $release['name'] ?? ''));

        $status['latest_version'] = $latestVersion;
        $status['release_name'] = trim((string) ($release['name'] ?? $release['tag_name'] ?? ''));
        $status['release_url'] = trim((string) ($release['html_url'] ?? ''));
        $status['published_at'] = (string) ($release['published_at'] ?? '') ?: null;
        $status['checked_at'] = (string) ($release['checked_at'] ?? '') ?: null;
        $status['summary'] = $this->releaseSummary((string) ($release['body'] ?? ''));

        if ($installedVersion === '') {
            $status['status'] = 'installed_version_unknown';
            $status['message'] = 'Latest release found. Set the installed version to compare automatically.';
            return $status;
        }

        if ($latestVersion === '') {
            $status['status'] = 'comparison_unknown';
            $status['message'] = 'Latest release found, but its version string could not be parsed.';
            return $status;
        }

        if ($this->versionsMatch($installedVersion, $latestVersion)) {
            $status['status'] = 'up_to_date';
            $status['message'] = 'This installation matches the latest GitHub release.';
            return $status;
        }

        if ($this->versionGreaterThan($latestVersion, $installedVersion)) {
            $status['status'] = 'update_available';
            $status['message'] = 'A newer GitHub release is available for this installation.';
            $status['is_update_available'] = true;
            return $status;
        }

        $status['status'] = 'ahead_or_custom';
        $status['message'] = 'This installation is ahead of, or different from, the latest tagged GitHub release.';

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function latestRelease(string $repository): array
    {
        $cached = $this->readCache($repository);

        if ($cached !== null) {
            return $cached;
        }

        $release = $this->requestLatestRelease($repository);
        $release['checked_at'] = gmdate('c');
        $this->writeCache($repository, $release);

        return $release;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCache(string $repository): ?array
    {
        $cachePath = $this->cachePath($repository);

        if (!is_file($cachePath)) {
            return null;
        }

        $contents = @file_get_contents($cachePath);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (!is_array($decoded)) {
            return null;
        }

        $checkedAt = strtotime((string) ($decoded['checked_at'] ?? ''));

        if ($checkedAt === false || (time() - $checkedAt) > self::CACHE_TTL_SECONDS) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $release
     */
    private function writeCache(string $repository, array $release): void
    {
        $cacheDirectory = dirname($this->cachePath($repository));

        if (!is_dir($cacheDirectory)) {
            @mkdir($cacheDirectory, 0775, true);
        }

        @file_put_contents(
            $this->cachePath($repository),
            json_encode($release, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private function cachePath(string $repository): string
    {
        $fileName = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($repository)) ?: 'github-release';

        return ROOT_PATH . '/storage/cache/' . $fileName . '.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function requestLatestRelease(string $repository): array
    {
        $url = 'https://api.github.com/repos/' . rawurlencode($this->repositoryOwner($repository))
            . '/' . rawurlencode($this->repositoryName($repository))
            . '/releases/latest';

        if (function_exists('curl_init')) {
            return $this->requestWithCurl($url);
        }

        return $this->requestWithStreams($url);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestWithCurl(string $url): array
    {
        $curl = curl_init($url);

        if (!$curl) {
            throw new RuntimeException('Could not initialize the GitHub request.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->requestHeaders(),
            CURLOPT_TIMEOUT => 20,
        ]);

        $caFile = $this->resolveCaFile();

        if ($caFile !== null) {
            curl_setopt($curl, CURLOPT_CAINFO, $caFile);
        }

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || $response === '') {
            throw new RuntimeException($curlError !== '' ? $curlError : 'Empty response from GitHub.');
        }

        return $this->decodeReleaseResponse($response, $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestWithStreams(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $this->requestHeaders()) . "\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => array_filter([
                'verify_peer' => true,
                'verify_peer_name' => true,
                'cafile' => $this->resolveCaFile(),
            ], static fn (mixed $value): bool => $value !== null),
        ]);

        $response = @file_get_contents($url, false, $context);
        $statusCode = 0;

        foreach ((array) ($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $header, $matches) === 1) {
                $statusCode = (int) $matches[1];
                break;
            }
        }

        if (!is_string($response) || $response === '') {
            throw new RuntimeException('Empty response from GitHub.');
        }

        return $this->decodeReleaseResponse($response, $statusCode);
    }

    /**
     * @return array<int, string>
     */
    private function requestHeaders(): array
    {
        return [
            'Accept: application/vnd.github+json',
            'User-Agent: VIDEW Release Check',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeReleaseResponse(string $response, int $statusCode): array
    {
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid response from GitHub.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = trim((string) ($decoded['message'] ?? 'GitHub release request failed.'));
            throw new RuntimeException($message !== '' ? $message : 'GitHub release request failed.');
        }

        return $decoded;
    }

    private function normalizeRepository(string $repository): string
    {
        $repository = trim($repository);

        if ($repository === '') {
            return '';
        }

        $repository = preg_replace('~^https?://github\.com/~i', '', $repository) ?? $repository;
        $repository = preg_replace('~\.git$~i', '', $repository) ?? $repository;
        $repository = trim($repository, '/');

        if (preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $repository) !== 1) {
            return '';
        }

        return $repository;
    }

    private function repositoryOwner(string $repository): string
    {
        return explode('/', $repository, 2)[0] ?? '';
    }

    private function repositoryName(string $repository): string
    {
        return explode('/', $repository, 2)[1] ?? '';
    }

    private function resolveInstalledVersion(?string $configuredVersion): string
    {
        $configuredVersion = $this->extractVersion((string) $configuredVersion);

        if ($configuredVersion !== '') {
            return $configuredVersion;
        }

        $changelogPath = ROOT_PATH . '/CHANGELOG.md';

        if (is_file($changelogPath)) {
            $contents = @file_get_contents($changelogPath);

            if (is_string($contents) && preg_match('/^## \[([^\]]+)\]/m', $contents, $matches) === 1) {
                return $this->extractVersion((string) ($matches[1] ?? ''));
            }
        }

        $packagePath = ROOT_PATH . '/package.json';

        if (is_file($packagePath)) {
            $contents = @file_get_contents($packagePath);
            $decoded = is_string($contents) ? json_decode($contents, true) : null;

            if (is_array($decoded)) {
                return $this->extractVersion((string) ($decoded['version'] ?? ''));
            }
        }

        return '';
    }

    private function extractVersion(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/v?(\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?)/', $value, $matches) === 1) {
            return (string) ($matches[1] ?? '');
        }

        return ltrim($value, "vV \t\n\r\0\x0B");
    }

    private function versionsMatch(string $left, string $right): bool
    {
        $left = $this->extractVersion($left);
        $right = $this->extractVersion($right);

        if ($left === '' || $right === '') {
            return false;
        }

        return version_compare($left, $right, '==');
    }

    private function versionGreaterThan(string $left, string $right): bool
    {
        $left = $this->extractVersion($left);
        $right = $this->extractVersion($right);

        if ($left === '' || $right === '') {
            return false;
        }

        return version_compare($left, $right, '>');
    }

    private function releaseSummary(string $body): string
    {
        foreach (preg_split('/\R+/', $body) ?: [] as $line) {
            $line = trim((string) $line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $line = trim((string) preg_replace('/^[-*]\s+/', '', $line));

            if ($line !== '') {
                return substr($line, 0, 220);
            }
        }

        return '';
    }

    private function resolveCaFile(): ?string
    {
        $configured = trim((string) ini_get('curl.cainfo'));

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $configured = trim((string) ini_get('openssl.cafile'));

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $phpRoot = dirname(dirname((string) PHP_BINARY));
        $wampRoot = dirname($phpRoot);
        $candidates = [
            dirname((string) PHP_BINARY) . '/extras/ssl/cacert.pem',
            $phpRoot . '/php8.2.29/extras/ssl/cacert.pem',
        ];

        foreach (glob($phpRoot . '/php*/extras/ssl/cacert.pem') ?: [] as $path) {
            $candidates[] = (string) $path;
        }

        foreach (glob($wampRoot . '/apps/phpmyadmin*/vendor/composer/ca-bundle/res/cacert.pem') ?: [] as $path) {
            $candidates[] = (string) $path;
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
