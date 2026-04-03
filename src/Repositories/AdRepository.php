<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;
use RuntimeException;
use Throwable;

final class AdRepository
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $cachedBySlot = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allBySlot(): array
    {
        if (is_array($this->cachedBySlot)) {
            return $this->cachedBySlot;
        }

        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return [];
        }

        try {
            $statement = $pdo->query(
                'SELECT id, slot_key, ad_type, is_active, title, body_text, click_url, image_url,
                        image_path, image_storage_provider, video_url, video_path, video_storage_provider,
                        video_mime_type, vast_tag_url, skip_after_seconds, script_code, created_at, updated_at
                 FROM ads
                 ORDER BY slot_key ASC'
            );

            $rows = $statement->fetchAll() ?: [];
            $normalized = [];

            foreach ($rows as $row) {
                $ad = $this->normalizeAd($row);
                $normalized[(string) $ad['slot_key']] = $ad;
            }

            $this->cachedBySlot = $normalized;

            return $normalized;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlot(string $slotKey): ?array
    {
        $all = $this->allBySlot();

        return $all[$slotKey] ?? null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsert(string $slotKey, array $payload): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. Ads require MySQL to be online.');
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO ads (
                    slot_key, ad_type, is_active, title, body_text, click_url, image_url,
                    image_path, image_storage_provider, video_url, video_path, video_storage_provider,
                    video_mime_type, vast_tag_url, skip_after_seconds, script_code, created_at, updated_at
                 ) VALUES (
                    :slot_key, :ad_type, :is_active, :title, :body_text, :click_url, :image_url,
                    :image_path, :image_storage_provider, :video_url, :video_path, :video_storage_provider,
                    :video_mime_type, :vast_tag_url, :skip_after_seconds, :script_code, NOW(), NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    ad_type = VALUES(ad_type),
                    is_active = VALUES(is_active),
                    title = VALUES(title),
                    body_text = VALUES(body_text),
                    click_url = VALUES(click_url),
                    image_url = VALUES(image_url),
                    image_path = VALUES(image_path),
                    image_storage_provider = VALUES(image_storage_provider),
                    video_url = VALUES(video_url),
                    video_path = VALUES(video_path),
                    video_storage_provider = VALUES(video_storage_provider),
                    video_mime_type = VALUES(video_mime_type),
                    vast_tag_url = VALUES(vast_tag_url),
                    skip_after_seconds = VALUES(skip_after_seconds),
                    script_code = VALUES(script_code),
                    updated_at = NOW()'
            );

            $statement->execute([
                'slot_key' => $slotKey,
                'ad_type' => $payload['ad_type'] ?? 'placeholder',
                'is_active' => !empty($payload['is_active']) ? 1 : 0,
                'title' => $payload['title'] ?? null,
                'body_text' => $payload['body_text'] ?? null,
                'click_url' => $payload['click_url'] ?? null,
                'image_url' => $payload['image_url'] ?? null,
                'image_path' => $payload['image_path'] ?? null,
                'image_storage_provider' => $payload['image_storage_provider'] ?? null,
                'video_url' => $payload['video_url'] ?? null,
                'video_path' => $payload['video_path'] ?? null,
                'video_storage_provider' => $payload['video_storage_provider'] ?? null,
                'video_mime_type' => $payload['video_mime_type'] ?? null,
                'vast_tag_url' => $payload['vast_tag_url'] ?? null,
                'skip_after_seconds' => $payload['skip_after_seconds'] ?? 5,
                'script_code' => $payload['script_code'] ?? null,
            ]);

            $this->cachedBySlot = null;
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not save the ad slot. ' . $exception->getMessage());
        }
    }

    /**
     * @return array{slots:int,active:int,configured:int}
     */
    public function stats(): array
    {
        $all = $this->allBySlot();
        $configured = array_filter($all, static fn (array $ad): bool => (string) ($ad['ad_type'] ?? 'placeholder') !== 'placeholder');
        $active = array_filter($all, static fn (array $ad): bool => !empty($ad['is_active']) && (string) ($ad['ad_type'] ?? 'placeholder') !== 'placeholder');

        return [
            'slots' => count(ad_slot_definitions()),
            'active' => count($active),
            'configured' => count($configured),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeAd(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'slot_key' => (string) ($row['slot_key'] ?? ''),
            'ad_type' => (string) ($row['ad_type'] ?? 'placeholder'),
            'is_active' => (int) ($row['is_active'] ?? 0),
            'title' => trim((string) ($row['title'] ?? '')),
            'body_text' => trim((string) ($row['body_text'] ?? '')),
            'click_url' => trim((string) ($row['click_url'] ?? '')),
            'image_url' => trim((string) ($row['image_url'] ?? '')),
            'image_path' => trim((string) ($row['image_path'] ?? '')),
            'image_storage_provider' => trim((string) ($row['image_storage_provider'] ?? '')),
            'video_url' => trim((string) ($row['video_url'] ?? '')),
            'video_path' => trim((string) ($row['video_path'] ?? '')),
            'video_storage_provider' => trim((string) ($row['video_storage_provider'] ?? '')),
            'video_mime_type' => trim((string) ($row['video_mime_type'] ?? '')),
            'vast_tag_url' => trim((string) ($row['vast_tag_url'] ?? '')),
            'skip_after_seconds' => max(0, (int) ($row['skip_after_seconds'] ?? 5)),
            'script_code' => (string) ($row['script_code'] ?? ''),
            'created_at' => !empty($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => !empty($row['updated_at']) ? (string) $row['updated_at'] : null,
        ];
    }
}
