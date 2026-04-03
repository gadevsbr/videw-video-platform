<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\StorageManager;

$slotKey = trim((string) ($_GET['slot'] ?? ''));
$asset = trim((string) ($_GET['asset'] ?? 'image'));
$asset = in_array($asset, ['image', 'video'], true) ? $asset : 'image';

$ad = current_ad_slots()[$slotKey] ?? null;

if (!is_array($ad) || !ad_has_renderable_content($ad) || (int) ($ad['is_active'] ?? 0) !== 1) {
    ad_media_abort(404, 'Ad asset not found.');
}

$path = $asset === 'video'
    ? trim((string) ($ad['video_path'] ?? ''))
    : trim((string) ($ad['image_path'] ?? ''));
$url = $asset === 'video'
    ? trim((string) ($ad['video_url'] ?? ''))
    : trim((string) ($ad['image_url'] ?? ''));
$provider = $asset === 'video'
    ? trim((string) ($ad['video_storage_provider'] ?? ''))
    : trim((string) ($ad['image_storage_provider'] ?? ''));
$mimeType = $asset === 'video'
    ? trim((string) ($ad['video_mime_type'] ?? 'video/mp4'))
    : '';

if ($provider === 'local' && $path !== '') {
    ad_media_stream_local($path, $mimeType, $asset === 'video');
}

if ($provider === 'wasabi' && $path !== '') {
    try {
        $storage = new StorageManager();
        $ttl = max(60, min(604800, (int) config('storage.wasabi_signed_url_ttl_seconds', 900)));
        $signedUrl = $storage->wasabiClient()->presignGetObject($path, $ttl);
        header('Cache-Control: public, max-age=300');
        header('Location: ' . $signedUrl, true, 302);
        exit;
    } catch (Throwable) {
        if ($url !== '') {
            header('Location: ' . $url, true, 302);
            exit;
        }

        ad_media_abort(404, 'Ad asset not found.');
    }
}

if ($url !== '') {
    header('Cache-Control: public, max-age=300');
    header('Location: ' . $url, true, 302);
    exit;
}

ad_media_abort(404, 'Ad asset not found.');

function ad_media_abort(int $statusCode, string $message): never
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function ad_media_stream_local(string $relativePath, string $mimeType, bool $supportsRanges): never
{
    $root = rtrim((string) config('storage.local_root', ROOT_PATH . '/storage/uploads'), '/\\');
    $normalizedPath = str_replace('\\', '/', ltrim($relativePath, '/\\'));
    $absolutePath = $root . '/' . $normalizedPath;
    $resolvedRoot = realpath($root);
    $resolvedPath = realpath($absolutePath);

    if (
        !is_string($resolvedRoot)
        || !is_string($resolvedPath)
        || !str_starts_with(str_replace('\\', '/', $resolvedPath), str_replace('\\', '/', $resolvedRoot))
        || !is_file($resolvedPath)
    ) {
        ad_media_abort(404, 'Ad asset not found.');
    }

    $size = (int) filesize($resolvedPath);
    $mime = $mimeType !== '' ? $mimeType : ad_media_detect_mime($resolvedPath);
    $start = 0;
    $end = max(0, $size - 1);
    $statusCode = 200;

    if ($supportsRanges) {
        header('Accept-Ranges: bytes');

        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $matches) === 1) {
            $rangeStart = $matches[1] !== '' ? (int) $matches[1] : 0;
            $rangeEnd = $matches[2] !== '' ? (int) $matches[2] : $end;

            if ($rangeStart > $rangeEnd || $rangeEnd >= $size) {
                header('Content-Range: bytes */' . $size);
                ad_media_abort(416, 'Requested range not satisfiable.');
            }

            $start = $rangeStart;
            $end = $rangeEnd;
            $statusCode = 206;
        }
    }

    $length = ($end - $start) + 1;
    http_response_code($statusCode);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $length);
    header('Content-Disposition: inline; filename="' . basename($resolvedPath) . '"');
    header('Cache-Control: public, max-age=86400');

    if ($statusCode === 206) {
        header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $handle = fopen($resolvedPath, 'rb');

    if (!is_resource($handle)) {
        ad_media_abort(500, 'Could not open the ad asset.');
    }

    fseek($handle, $start);
    $remaining = $length;

    while (!feof($handle) && $remaining > 0) {
        $chunkSize = min(8192, $remaining);
        $buffer = fread($handle, $chunkSize);

        if ($buffer === false) {
            break;
        }

        echo $buffer;
        flush();
        $remaining -= strlen($buffer);
    }

    fclose($handle);
    exit;
}

function ad_media_detect_mime(string $path): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo !== false) {
        $detected = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (is_string($detected) && $detected !== '') {
            return $detected;
        }
    }

    return 'application/octet-stream';
}
