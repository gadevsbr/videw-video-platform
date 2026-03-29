<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Services\LocalMediaService;

$videoId = (int) ($_GET['video'] ?? 0);
$asset = trim((string) ($_GET['asset'] ?? 'video'));

$service = new LocalMediaService();
$service->stream($videoId, $asset);
