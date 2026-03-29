<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

final class WasabiStorageDriver implements StorageDriverInterface
{
    private readonly WasabiS3Client $client;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $region,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $publicBaseUrl,
        private readonly string $pathPrefix,
        private readonly int $multipartThresholdBytes = 67108864,
        private readonly int $multipartPartSizeBytes = 16777216
    ) {
        $this->client = new WasabiS3Client(
            $this->endpoint,
            $this->region,
            $this->bucket,
            $this->accessKey,
            $this->secretKey,
            $this->publicBaseUrl,
            $this->pathPrefix
        );
    }

    public function storeUploadedFile(array $file, string $directory): array
    {
        if (!uploaded_file_present($file)) {
            throw new RuntimeException('Invalid or missing upload.');
        }

        if (
            $this->bucket === ''
            || $this->accessKey === ''
            || $this->secretKey === ''
            || $this->endpoint === ''
            || $this->region === ''
        ) {
            throw new RuntimeException('Fill in the Wasabi endpoint, region, bucket, access key, and secret key in the admin panel.');
        }

        $originalName = (string) ($file['name'] ?? 'file.bin');
        $mimeType = $this->detectMimeType((string) $file['tmp_name'], (string) ($file['type'] ?? 'application/octet-stream'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeName = slugify(pathinfo($originalName, PATHINFO_FILENAME));
        $extensionSuffix = $extension !== '' ? '.' . $extension : '';
        $relativePath = trim($directory, '/') . '/' . date('Y/m') . '/' . uniqid($safeName . '-', true) . $extensionSuffix;
        $objectKey = $this->client->qualifyObjectKey($relativePath);
        $fileSize = (int) filesize((string) $file['tmp_name']);

        if ($fileSize >= max(5 * 1024 * 1024, $this->multipartThresholdBytes)) {
            $this->client->uploadFileMultipart($objectKey, (string) $file['tmp_name'], $mimeType, $this->multipartPartSizeBytes);
        } else {
            $this->client->uploadFile($objectKey, (string) $file['tmp_name'], $mimeType);
        }

        return [
            'disk' => 'wasabi',
            'path' => $objectKey,
            'url' => $this->client->publicObjectUrl($objectKey),
            'mime_type' => $mimeType,
            'size' => $fileSize,
        ];
    }

    public function delete(string $path): void
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '') {
            return;
        }

        $this->client->deleteObject($normalizedPath);
    }

    private function detectMimeType(string $tmpPath, string $fallback): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo !== false) {
            $detected = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return $fallback !== '' ? $fallback : 'application/octet-stream';
    }
}
