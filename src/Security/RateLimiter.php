<?php

declare(strict_types=1);

namespace App\Security;

final class RateLimiter
{
    public function __construct(
        private readonly string $directory = ROOT_PATH . '/storage/runtime/ratelimits'
    ) {
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $record = $this->read($key, $decaySeconds);

        return ($record['attempts'] ?? 0) >= max(1, $maxAttempts);
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $record = $this->read($key, $decaySeconds);
        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
        $record['expires_at'] = max((int) ($record['expires_at'] ?? 0), time() + max(1, $decaySeconds));
        $this->write($key, $record);

        return (int) $record['attempts'];
    }

    public function clear(string $key): void
    {
        $path = $this->pathFor($key);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function availableIn(string $key, int $decaySeconds): int
    {
        $record = $this->read($key, $decaySeconds);

        return max(0, (int) ($record['expires_at'] ?? 0) - time());
    }

    /**
     * @return array{attempts:int,expires_at:int}
     */
    private function read(string $key, int $decaySeconds): array
    {
        $path = $this->pathFor($key);

        if (!is_file($path)) {
            return [
                'attempts' => 0,
                'expires_at' => 0,
            ];
        }

        $contents = file_get_contents($path);
        $record = is_string($contents) ? json_decode($contents, true) : null;

        if (!is_array($record)) {
            $record = [];
        }

        $expiresAt = (int) ($record['expires_at'] ?? 0);

        if ($expiresAt <= time()) {
            return [
                'attempts' => 0,
                'expires_at' => 0,
            ];
        }

        return [
            'attempts' => (int) ($record['attempts'] ?? 0),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param array{attempts:int,expires_at:int} $record
     */
    private function write(string $key, array $record): void
    {
        $path = $this->pathFor($key);
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        file_put_contents($path, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function pathFor(string $key): string
    {
        return rtrim($this->directory, '/\\') . '/' . hash('sha256', $key) . '.json';
    }
}
