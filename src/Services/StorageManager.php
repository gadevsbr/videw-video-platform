<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use App\Storage\LocalStorageDriver;
use App\Storage\StorageDriverInterface;
use App\Storage\WasabiStorageDriver;
use App\Storage\WasabiS3Client;

final class StorageManager
{
    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository()
    ) {
    }

    public function driverName(): string
    {
        $driver = $this->settings->get('upload_driver', (string) config('storage.default_driver', 'local')) ?: 'local';
        return in_array($driver, ['local', 'wasabi'], true) ? $driver : 'local';
    }

    public function driver(): StorageDriverInterface
    {
        return $this->driverForDisk($this->driverName());
    }

    public function driverForDisk(string $disk): StorageDriverInterface
    {
        return $disk === 'wasabi'
            ? new WasabiStorageDriver(
                (string) $this->settings->get('wasabi_endpoint', (string) config('storage.wasabi_endpoint', '')),
                (string) $this->settings->get('wasabi_region', (string) config('storage.wasabi_region', '')),
                (string) $this->settings->get('wasabi_bucket', (string) config('storage.wasabi_bucket', '')),
                (string) $this->settings->get('wasabi_access_key', (string) config('storage.wasabi_access_key', '')),
                (string) $this->settings->get('wasabi_secret_key', (string) config('storage.wasabi_secret_key', '')),
                (string) $this->settings->get('wasabi_public_base_url', (string) config('storage.wasabi_public_base_url', '')),
                (string) $this->settings->get('wasabi_path_prefix', (string) config('storage.wasabi_path_prefix', 'videw')),
                $this->mbToBytes((string) $this->settings->get('wasabi_multipart_threshold_mb', (string) config('storage.wasabi_multipart_threshold_mb', '64')), 64),
                $this->mbToBytes((string) $this->settings->get('wasabi_multipart_part_size_mb', (string) config('storage.wasabi_multipart_part_size_mb', '16')), 16)
            )
            : new LocalStorageDriver(
                (string) config('storage.local_root', ROOT_PATH . '/storage/uploads'),
                local_storage_public_base_url((string) config('storage.local_public_base_url', ''))
            );
    }

    public function wasabiClient(): WasabiS3Client
    {
        return new WasabiS3Client(
            (string) $this->settings->get('wasabi_endpoint', (string) config('storage.wasabi_endpoint', '')),
            (string) $this->settings->get('wasabi_region', (string) config('storage.wasabi_region', '')),
            (string) $this->settings->get('wasabi_bucket', (string) config('storage.wasabi_bucket', '')),
            (string) $this->settings->get('wasabi_access_key', (string) config('storage.wasabi_access_key', '')),
            (string) $this->settings->get('wasabi_secret_key', (string) config('storage.wasabi_secret_key', '')),
            (string) $this->settings->get('wasabi_public_base_url', (string) config('storage.wasabi_public_base_url', '')),
            (string) $this->settings->get('wasabi_path_prefix', (string) config('storage.wasabi_path_prefix', 'videw'))
        );
    }

    private function mbToBytes(string $value, int $fallbackMb): int
    {
        $number = (int) $value;

        if ($number <= 0) {
            $number = $fallbackMb;
        }

        return $number * 1024 * 1024;
    }
}
