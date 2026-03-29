<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Repositories\VideoRepository;
use App\Services\MediaAccessService;

header('Content-Type: application/json; charset=UTF-8');

$repository = new VideoRepository();
$mediaAccess = new MediaAccessService();
$videos = $mediaAccess->decorateVideos($repository->listPublished());

$query = mb_strtolower(trim((string) ($_GET['query'] ?? '')));
$category = trim((string) ($_GET['category'] ?? ''));
$accessLevel = trim((string) ($_GET['access'] ?? ''));

$filtered = array_values(array_filter($videos, static function (array $video) use ($query, $category, $accessLevel): bool {
    $matchesQuery = $query === ''
        || str_contains(mb_strtolower((string) $video['title']), $query)
        || str_contains(mb_strtolower((string) $video['creator_name']), $query);

    $matchesCategory = $category === '' || $category === 'all' || $video['category'] === $category;
    $matchesAccess = $accessLevel === '' || $accessLevel === 'all' || $video['access_level'] === $accessLevel;

    return $matchesQuery && $matchesCategory && $matchesAccess;
}));

echo json_encode([
    'videos' => array_map(static fn (array $video): array => public_catalog_video_payload($video), $filtered),
    'fallback' => $repository->usingFallback(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
