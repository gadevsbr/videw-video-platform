<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;
use RuntimeException;
use Throwable;

final class SettingsRepository
{
    /**
     * @return array<int, string>
     */
    public static function envManagedKeys(): array
    {
        return [
            'upload_driver',
            'wasabi_endpoint',
            'wasabi_region',
            'wasabi_bucket',
            'wasabi_access_key',
            'wasabi_secret_key',
            'wasabi_public_base_url',
            'wasabi_path_prefix',
            'wasabi_private_bucket',
            'wasabi_signed_url_ttl_seconds',
            'wasabi_multipart_threshold_mb',
            'wasabi_multipart_part_size_mb',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $settings = $this->defaults();
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return $settings;
        }

        try {
            $statement = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
            $rows = $statement->fetchAll();

            foreach ($rows as $row) {
                $key = (string) $row['setting_key'];

                if (in_array($key, self::envManagedKeys(), true)) {
                    continue;
                }

                $settings[$key] = (string) ($row['setting_value'] ?? '');
            }
        } catch (Throwable) {
            return $settings;
        }

        return $settings;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    /**
     * @param array<string, string> $settings
     */
    public function putMany(array $settings): void
    {
        $settings = array_filter(
            $settings,
            static fn (string $key): bool => !in_array($key, self::envManagedKeys(), true),
            ARRAY_FILTER_USE_KEY
        );

        if ($settings === []) {
            return;
        }

        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. The admin panel requires MySQL to be online.');
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO site_settings (setting_key, setting_value, updated_at)
                 VALUES (:setting_key, :setting_value, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
            );

            foreach ($settings as $key => $value) {
                $statement->execute([
                    'setting_key' => $key,
                    'setting_value' => $value,
                ]);
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('Settings table missing. Run db/upgrade-20260328-embed-wasabi.sql. ' . $exception->getMessage());
        }
    }

    public function dbReady(): bool
    {
        return Database::connection() instanceof PDO;
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'upload_driver' => (string) config('storage.default_driver', 'local'),
            'wasabi_endpoint' => (string) config('storage.wasabi_endpoint', 'https://s3.wasabisys.com'),
            'wasabi_region' => (string) config('storage.wasabi_region', 'us-east-1'),
            'wasabi_bucket' => (string) config('storage.wasabi_bucket', ''),
            'wasabi_access_key' => (string) config('storage.wasabi_access_key', ''),
            'wasabi_secret_key' => (string) config('storage.wasabi_secret_key', ''),
            'wasabi_public_base_url' => (string) config('storage.wasabi_public_base_url', ''),
            'wasabi_path_prefix' => (string) config('storage.wasabi_path_prefix', 'videw'),
            'wasabi_private_bucket' => (string) config('storage.wasabi_private_bucket', '0'),
            'wasabi_signed_url_ttl_seconds' => (string) config('storage.wasabi_signed_url_ttl_seconds', '900'),
            'wasabi_multipart_threshold_mb' => (string) config('storage.wasabi_multipart_threshold_mb', '64'),
            'wasabi_multipart_part_size_mb' => (string) config('storage.wasabi_multipart_part_size_mb', '16'),
        ];
    }
}
