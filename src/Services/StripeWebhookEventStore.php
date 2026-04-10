<?php

declare(strict_types=1);

namespace App\Services;

final class StripeWebhookEventStore
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory !== null && trim($directory) !== ''
            ? rtrim($directory, '/\\')
            : ROOT_PATH . '/storage/runtime/stripe-webhooks';
    }

    /**
     * @return array{event_id:string,status:string,duplicate:bool,in_progress:bool,record:array<string,mixed>}
     */
    public function claim(string $eventId, string $type, ?string $eventCreatedAt = null): array
    {
        $eventId = $this->normalizeEventId($eventId);
        $path = $this->eventPath($eventId);
        $now = gmdate('c');

        $this->ensureDirectory();

        if (!is_file($path)) {
            $record = [
                'event_id' => $eventId,
                'type' => $type,
                'status' => 'processing',
                'first_seen_at' => $now,
                'event_created_at' => $eventCreatedAt,
                'updated_at' => $now,
                'duplicate_count' => 0,
                'failure_count' => 0,
                'retry_count' => 0,
            ];

            $handle = @fopen($path, 'x');

            if (is_resource($handle)) {
                fwrite($handle, $this->encode($record));
                fclose($handle);
                return [
                    'event_id' => $eventId,
                    'status' => 'processing',
                    'duplicate' => false,
                    'in_progress' => false,
                    'record' => $record,
                ];
            }
        }

        $record = $this->readRecord($path) ?? [
            'event_id' => $eventId,
            'type' => $type,
            'status' => 'processing',
            'first_seen_at' => $now,
            'updated_at' => $now,
            'duplicate_count' => 0,
            'failure_count' => 0,
            'retry_count' => 0,
        ];

        if ((string) ($record['status'] ?? '') === 'failed') {
            $record['status'] = 'processing';
            $record['updated_at'] = $now;
            $record['retry_count'] = (int) ($record['retry_count'] ?? 0) + 1;
            $record['type'] = $type;
            if ($eventCreatedAt !== null && $eventCreatedAt !== '') {
                $record['event_created_at'] = $eventCreatedAt;
            }
            unset($record['last_error'], $record['failed_at']);
            $this->writeRecord($path, $record);

            return [
                'event_id' => $eventId,
                'status' => 'processing',
                'duplicate' => false,
                'in_progress' => false,
                'record' => $record,
            ];
        }

        $record['duplicate_count'] = (int) ($record['duplicate_count'] ?? 0) + 1;
        $record['last_duplicate_at'] = $now;
        $record['updated_at'] = $now;
        $this->writeRecord($path, $record);

        $status = (string) ($record['status'] ?? 'processing');

        return [
            'event_id' => $eventId,
            'status' => $status,
            'duplicate' => true,
            'in_progress' => $status === 'processing',
            'record' => $record,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function markProcessed(string $eventId, array $metadata = []): void
    {
        $path = $this->eventPath($eventId);
        $record = $this->readRecord($path) ?? ['event_id' => $this->normalizeEventId($eventId)];

        $record['status'] = 'processed';
        $record['processed_at'] = gmdate('c');
        $record['updated_at'] = $record['processed_at'];
        $record['metadata'] = $metadata;
        unset($record['last_error']);

        $this->writeRecord($path, $record);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function markFailed(string $eventId, string $error, array $metadata = []): void
    {
        $path = $this->eventPath($eventId);
        $record = $this->readRecord($path) ?? ['event_id' => $this->normalizeEventId($eventId)];

        $record['status'] = 'failed';
        $record['failed_at'] = gmdate('c');
        $record['updated_at'] = $record['failed_at'];
        $record['failure_count'] = (int) ($record['failure_count'] ?? 0) + 1;
        $record['last_error'] = $error;
        $record['metadata'] = $metadata;

        $this->writeRecord($path, $record);
    }

    /**
     * @return array{processed:int,failed:int,processing:int,duplicates:int,total:int,latest:?array<string,mixed>}
     */
    public function summary(int $limit = 25): array
    {
        $summary = [
            'processed' => 0,
            'failed' => 0,
            'processing' => 0,
            'duplicates' => 0,
            'total' => 0,
            'latest' => null,
        ];

        if (!is_dir($this->directory)) {
            return $summary;
        }

        $files = $this->sortedEventFiles();

        foreach ($files as $index => $file) {
            $record = $this->readRecord($file);

            if (!is_array($record)) {
                continue;
            }

            $summary['total']++;
            $summary['duplicates'] += (int) ($record['duplicate_count'] ?? 0);

            $status = (string) ($record['status'] ?? '');

            if (isset($summary[$status])) {
                $summary[$status]++;
            }

            if ($summary['latest'] === null && $index < max(1, $limit)) {
                $summary['latest'] = $record;
            }
        }

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 10, ?string $status = null): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $records = [];

        foreach ($this->sortedEventFiles() as $file) {
            $record = $this->readRecord($file);

            if (!is_array($record)) {
                continue;
            }

            if ($status !== null && (string) ($record['status'] ?? '') !== $status) {
                continue;
            }

            $records[] = $record;

            if (count($records) >= max(1, $limit)) {
                break;
            }
        }

        return $records;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $eventId): ?array
    {
        return $this->readRecord($this->eventPath($eventId));
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    /**
     * @return array<int, string>
     */
    private function sortedEventFiles(): array
    {
        $files = glob($this->directory . '/*.json') ?: [];
        usort(
            $files,
            static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left)
        );

        return $files;
    }

    private function normalizeEventId(string $eventId): string
    {
        $eventId = trim($eventId);
        $normalized = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $eventId) ?? '';

        return $normalized !== '' ? $normalized : hash('sha256', $eventId !== '' ? $eventId : uniqid('stripe-webhook-', true));
    }

    private function eventPath(string $eventId): string
    {
        return $this->directory . '/' . $this->normalizeEventId($eventId) . '.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readRecord(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function writeRecord(string $path, array $record): void
    {
        @file_put_contents($path, $this->encode($record), LOCK_EX);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function encode(array $record): string
    {
        return json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }
}
