<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

final class LocalStorageDriver implements StorageDriverInterface
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $publicBaseUrl
    ) {
    }

    public function storeUploadedFile(array $file, string $directory): array
    {
        if (!uploaded_file_present($file)) {
            throw new RuntimeException('Invalid or missing upload.');
        }

        $originalName = (string) ($file['name'] ?? 'file.bin');
        $mimeType = $this->detectMimeType((string) $file['tmp_name'], (string) ($file['type'] ?? 'application/octet-stream'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeName = slugify(pathinfo($originalName, PATHINFO_FILENAME));
        $extensionSuffix = $extension !== '' ? '.' . $extension : '';
        $relativePath = trim($directory, '/') . '/' . date('Y/m') . '/' . uniqid($safeName . '-', true) . $extensionSuffix;
        $absolutePath = rtrim($this->rootPath, '/\\') . '/' . $relativePath;
        $targetDirectory = dirname($absolutePath);

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Could not create the local upload directory.');
        }

        $tmpPath = (string) $file['tmp_name'];
        $moved = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $absolutePath)
            : rename($tmpPath, $absolutePath);

        if (!$moved && !copy($tmpPath, $absolutePath)) {
            throw new RuntimeException('Could not store the file locally.');
        }

        return [
            'disk' => 'local',
            'path' => str_replace('\\', '/', $relativePath),
            'url' => rtrim($this->publicBaseUrl, '/') . '/' . str_replace('\\', '/', $relativePath),
            'mime_type' => $mimeType,
            'size' => (int) filesize($absolutePath),
        ];
    }

    public function delete(string $path): void
    {
        $normalized = str_replace(['\\', '//'], '/', trim($path));

        if ($normalized === '') {
            return;
        }

        $absolutePath = rtrim($this->rootPath, '/\\') . '/' . ltrim($normalized, '/');

        if (!is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
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
