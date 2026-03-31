<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class VideoRepository
{
    private const SELECT_COLUMNS = 'videos.id, videos.slug, videos.title, videos.synopsis, videos.creator_user_id, videos.creator_name,
        videos.category, videos.access_level, videos.duration_minutes, videos.poster_tone, videos.poster_url, videos.poster_path,
        videos.poster_storage_provider, videos.poster_focus_x, videos.poster_focus_y, videos.video_url, videos.file_path, videos.trailer_url, videos.embed_url, videos.mime_type,
        videos.original_source_url, videos.source_type, videos.storage_provider, videos.is_featured, videos.moderation_status,
        videos.moderation_notes, videos.published_at, videos.deleted_at, videos.created_at, videos.updated_at';

    private bool $usingFallback = false;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $cachedVideos = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPublished(): array
    {
        if (is_array($this->cachedVideos)) {
            return $this->cachedVideos;
        }

        $pdo = Database::connection();

        if ($pdo instanceof PDO) {
            try {
                $statement = $pdo->query(
                    'SELECT ' . self::SELECT_COLUMNS . '
                     FROM videos
                     WHERE moderation_status = \'approved\' AND deleted_at IS NULL
                     ORDER BY published_at DESC, id DESC'
                );

                $rows = $statement->fetchAll();
                $this->cachedVideos = array_map([$this, 'normalizeVideo'], $rows ?: []);

                return $this->cachedVideos;
            } catch (Throwable) {
                $this->usingFallback = true;
            }
        } else {
            $this->usingFallback = true;
        }

        $videos = require ROOT_PATH . '/src/Demo/demo-data.php';
        $this->cachedVideos = array_map([$this, 'normalizeVideo'], $videos);

        return $this->cachedVideos;
    }

    /**
     * @param array<string, string|int> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(array $filters = []): array
    {
        return $this->paginateForAdmin($filters, 1, 500)['items'];
    }

    /**
     * @param array<string, string|int> $filters
     * @return array{items:array<int, array<string, mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function paginateForAdmin(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            $items = $this->listPublished();

            return [
                'items' => $items,
                'total' => count($items),
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
            ];
        }

        [$where, $params] = $this->buildAdminWhere($filters);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        try {
            $countStatement = $pdo->prepare('SELECT COUNT(*) FROM videos WHERE ' . $where);
            $countStatement->execute($params);
            $total = (int) $countStatement->fetchColumn();

            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            $statement = $pdo->prepare(
                'SELECT ' . self::SELECT_COLUMNS . '
                 FROM videos
                 WHERE ' . $where . '
                 ORDER BY created_at DESC, id DESC
                 LIMIT :offset, :limit'
            );

            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $statement->bindValue(':' . $key, $value, PDO::PARAM_INT);
                } else {
                    $statement->bindValue(':' . $key, $value);
                }
            }

            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->execute();

            return [
                'items' => array_map([$this, 'normalizeVideo'], $statement->fetchAll() ?: []),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ];
        } catch (Throwable) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
            ];
        }
    }

    /**
     * @return array{items:array<int, array<string, mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function paginateForCreator(int $creatorUserId, array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $filters['creator_user_id'] = $creatorUserId;

        return $this->paginateForAdmin($filters, $page, $perPage);
    }

    /**
     * @return array{items:array<int, array<string, mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function paginatePublishedByCreator(int $creatorUserId, int $page = 1, int $perPage = 12): array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            $items = array_values(array_filter(
                $this->listPublished(),
                static fn (array $video): bool => (int) ($video['creator_user_id'] ?? 0) === $creatorUserId
            ));

            return [
                'items' => array_slice($items, 0, $perPage),
                'total' => count($items),
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
            ];
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        try {
            $countStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM videos
                 WHERE creator_user_id = :creator_user_id
                   AND moderation_status = \'approved\'
                   AND deleted_at IS NULL'
            );
            $countStatement->execute(['creator_user_id' => $creatorUserId]);
            $total = (int) $countStatement->fetchColumn();

            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            $statement = $pdo->prepare(
                'SELECT ' . self::SELECT_COLUMNS . '
                 FROM videos
                 WHERE creator_user_id = :creator_user_id
                   AND moderation_status = \'approved\'
                   AND deleted_at IS NULL
                 ORDER BY published_at DESC, id DESC
                 LIMIT :offset, :limit'
            );
            $statement->bindValue(':creator_user_id', $creatorUserId, PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->execute();

            return [
                'items' => array_map([$this, 'normalizeVideo'], $statement->fetchAll() ?: []),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ];
        } catch (Throwable) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    public function categories(): array
    {
        $categories = array_column($this->listPublished(), 'category');
        $categories = array_values(array_unique(array_filter($categories)));
        sort($categories);

        return $categories;
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $videos = $this->listPublished();
        $creatorKeys = [];

        foreach ($videos as $video) {
            $creatorKey = (int) ($video['creator_user_id'] ?? 0) > 0
                ? 'id:' . (int) $video['creator_user_id']
                : 'name:' . (string) ($video['creator_name'] ?? '');
            $creatorKeys[$creatorKey] = true;
        }

        $premium = array_filter($videos, static fn (array $video): bool => $video['access_level'] !== 'free');
        $featured = array_filter($videos, static fn (array $video): bool => (int) $video['is_featured'] === 1);

        return [
            'videos' => count($videos),
            'creators' => count($creatorKeys),
            'premium' => count($premium),
            'featured' => count($featured),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function adminStats(): array
    {
        $videos = $this->listForAdmin();
        $draft = array_filter($videos, static fn (array $video): bool => (string) ($video['moderation_status'] ?? '') === 'draft');
        $approved = array_filter($videos, static fn (array $video): bool => (string) ($video['moderation_status'] ?? '') === 'approved');
        $flagged = array_filter($videos, static fn (array $video): bool => (string) ($video['moderation_status'] ?? '') === 'flagged');
        $featured = array_filter($videos, static fn (array $video): bool => (int) ($video['is_featured'] ?? 0) === 1);

        return [
            'total' => count($videos),
            'draft' => count($draft),
            'approved' => count($approved),
            'flagged' => count($flagged),
            'featured' => count($featured),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function creatorStats(int $creatorUserId): array
    {
        $videos = $this->paginateForCreator($creatorUserId, [], 1, 500)['items'];
        $published = array_filter($videos, static fn (array $video): bool => (string) ($video['moderation_status'] ?? '') === 'approved');
        $draft = array_filter($videos, static fn (array $video): bool => (string) ($video['moderation_status'] ?? '') === 'draft');
        $flagged = array_filter($videos, static fn (array $video): bool => (string) ($video['moderation_status'] ?? '') === 'flagged');
        $premium = array_filter($videos, static fn (array $video): bool => video_requires_premium($video));

        return [
            'total' => count($videos),
            'published' => count($published),
            'draft' => count($draft),
            'flagged' => count($flagged),
            'premium' => count($premium),
        ];
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->listPublished() as $video) {
            if ((string) ($video['slug'] ?? '') === $slug) {
                return $video;
            }
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            foreach ($this->listPublished() as $video) {
                if ((int) ($video['id'] ?? 0) === $id) {
                    return $video;
                }
            }

            return null;
        }

        try {
            $statement = $pdo->prepare(
                'SELECT ' . self::SELECT_COLUMNS . '
                 FROM videos
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $id]);
            $video = $statement->fetch();

            return $video ? $this->normalizeVideo($video) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function findOwnedById(int $id, int $creatorUserId): ?array
    {
        $video = $this->findById($id);

        if (!$video) {
            return null;
        }

        if ((int) ($video['creator_user_id'] ?? 0) !== $creatorUserId) {
            return null;
        }

        return $video;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findManyByIds(array $ids): array
    {
        $pdo = Database::connection();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if (!$pdo instanceof PDO || $ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $statement = $pdo->prepare(
                'SELECT ' . self::SELECT_COLUMNS . '
                 FROM videos
                 WHERE id IN (' . $placeholders . ')'
            );
            $statement->execute($ids);

            return array_map([$this, 'normalizeVideo'], $statement->fetchAll() ?: []);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function related(string $slug, string $category, int $limit = 3): array
    {
        $related = array_values(array_filter(
            $this->listPublished(),
            static fn (array $video): bool => $video['slug'] !== $slug && $video['category'] === $category
        ));

        return array_slice($related, 0, $limit);
    }

    public function usingFallback(): bool
    {
        return $this->usingFallback;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): int
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. Video publishing requires MySQL to be online.');
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO videos (
                    slug, title, synopsis, creator_user_id, creator_name, category, access_level, duration_minutes, poster_tone,
                    poster_url, poster_path, poster_storage_provider, poster_focus_x, poster_focus_y, video_url, file_path, trailer_url, embed_url,
                    mime_type, original_source_url, source_type, storage_provider, is_featured, moderation_status,
                    moderation_notes, published_at, deleted_at, created_at, updated_at
                ) VALUES (
                    :slug, :title, :synopsis, :creator_user_id, :creator_name, :category, :access_level, :duration_minutes, :poster_tone,
                    :poster_url, :poster_path, :poster_storage_provider, :poster_focus_x, :poster_focus_y, :video_url, :file_path, :trailer_url, :embed_url,
                    :mime_type, :original_source_url, :source_type, :storage_provider, :is_featured, :moderation_status,
                    :moderation_notes, :published_at, NULL, NOW(), NOW()
                )'
            );
            $statement->execute([
                'slug' => $payload['slug'],
                'title' => $payload['title'],
                'synopsis' => $payload['synopsis'],
                'creator_user_id' => $payload['creator_user_id'] ?? null,
                'creator_name' => $payload['creator_name'],
                'category' => $payload['category'],
                'access_level' => $payload['access_level'],
                'duration_minutes' => $payload['duration_minutes'],
                'poster_tone' => $payload['poster_tone'],
                'poster_url' => $payload['poster_url'],
                'poster_path' => $payload['poster_path'],
                'poster_storage_provider' => $payload['poster_storage_provider'],
                'poster_focus_x' => $payload['poster_focus_x'],
                'poster_focus_y' => $payload['poster_focus_y'],
                'video_url' => $payload['video_url'],
                'file_path' => $payload['file_path'],
                'trailer_url' => $payload['trailer_url'],
                'embed_url' => $payload['embed_url'],
                'mime_type' => $payload['mime_type'],
                'original_source_url' => $payload['original_source_url'],
                'source_type' => $payload['source_type'],
                'storage_provider' => $payload['storage_provider'],
                'is_featured' => $payload['is_featured'],
                'moderation_status' => $payload['moderation_status'],
                'moderation_notes' => $payload['moderation_notes'] ?? null,
                'published_at' => $payload['published_at'],
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('The videos table is missing or outdated. Import db/schema.sql or rerun install.php. ' . $exception->getMessage());
        }

        $this->cachedVideos = null;

        return (int) $pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(int $id, array $payload): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. Video editing requires MySQL to be online.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE videos
                 SET slug = :slug,
                     title = :title,
                     synopsis = :synopsis,
                     creator_user_id = :creator_user_id,
                     creator_name = :creator_name,
                     category = :category,
                     access_level = :access_level,
                     duration_minutes = :duration_minutes,
                     poster_tone = :poster_tone,
                     poster_url = :poster_url,
                     poster_path = :poster_path,
                     poster_storage_provider = :poster_storage_provider,
                     poster_focus_x = :poster_focus_x,
                     poster_focus_y = :poster_focus_y,
                     video_url = :video_url,
                     file_path = :file_path,
                     trailer_url = :trailer_url,
                     embed_url = :embed_url,
                     mime_type = :mime_type,
                     original_source_url = :original_source_url,
                     source_type = :source_type,
                     storage_provider = :storage_provider,
                     is_featured = :is_featured,
                     moderation_status = :moderation_status,
                     moderation_notes = :moderation_notes,
                     published_at = :published_at,
                     updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'slug' => $payload['slug'],
                'title' => $payload['title'],
                'synopsis' => $payload['synopsis'],
                'creator_user_id' => $payload['creator_user_id'] ?? null,
                'creator_name' => $payload['creator_name'],
                'category' => $payload['category'],
                'access_level' => $payload['access_level'],
                'duration_minutes' => $payload['duration_minutes'],
                'poster_tone' => $payload['poster_tone'],
                'poster_url' => $payload['poster_url'],
                'poster_path' => $payload['poster_path'],
                'poster_storage_provider' => $payload['poster_storage_provider'],
                'poster_focus_x' => $payload['poster_focus_x'],
                'poster_focus_y' => $payload['poster_focus_y'],
                'video_url' => $payload['video_url'],
                'file_path' => $payload['file_path'],
                'trailer_url' => $payload['trailer_url'],
                'embed_url' => $payload['embed_url'],
                'mime_type' => $payload['mime_type'],
                'original_source_url' => $payload['original_source_url'],
                'source_type' => $payload['source_type'],
                'storage_provider' => $payload['storage_provider'],
                'is_featured' => $payload['is_featured'],
                'moderation_status' => $payload['moderation_status'],
                'moderation_notes' => $payload['moderation_notes'] ?? null,
                'published_at' => $payload['published_at'],
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not update the video. ' . $exception->getMessage());
        }

        $this->cachedVideos = null;
    }

    public function updateModeration(int $id, string $status, ?string $notes = null): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. Moderation requires MySQL to be online.');
        }

        if (!in_array($status, ['draft', 'approved', 'flagged'], true)) {
            throw new RuntimeException('Invalid moderation status.');
        }

        $publishedAt = $status === 'approved' ? (new DateTimeImmutable())->format('Y-m-d H:i:s') : null;

        try {
            $statement = $pdo->prepare(
                'UPDATE videos
                 SET moderation_status = :status,
                     moderation_notes = :notes,
                     published_at = CASE WHEN :published_at IS NULL THEN published_at ELSE :published_at END,
                     updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'status' => $status,
                'notes' => $notes,
                'published_at' => $publishedAt,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not update moderation. ' . $exception->getMessage());
        }

        $this->cachedVideos = null;
    }

    /**
     * @param array<int, int> $ids
     */
    public function bulkUpdateModeration(array $ids, string $status, ?string $notes = null): int
    {
        $count = 0;

        foreach (array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)) as $id) {
            $this->updateModeration($id, $status, $notes);
            $count++;
        }

        return $count;
    }

    public function setFeatured(int $id, bool $isFeatured): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. Featured state requires MySQL to be online.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE videos
                 SET is_featured = :is_featured, updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'is_featured' => $isFeatured ? 1 : 0,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not update featured state. ' . $exception->getMessage());
        }

        $this->cachedVideos = null;
    }

    /**
     * @param array<int, int> $ids
     */
    public function bulkSetFeatured(array $ids, bool $isFeatured): int
    {
        $count = 0;

        foreach (array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)) as $id) {
            $this->setFeatured($id, $isFeatured);
            $count++;
        }

        return $count;
    }

    public function softDelete(int $id): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. Deleting videos requires MySQL to be online.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE videos
                 SET deleted_at = NOW(), updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $id]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not delete the video. ' . $exception->getMessage());
        }

        $this->cachedVideos = null;
    }

    public function syncCreatorIdentity(int $creatorUserId, string $creatorName): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return;
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE videos
                 SET creator_name = :creator_name, updated_at = NOW()
                 WHERE creator_user_id = :creator_user_id'
            );
            $statement->execute([
                'creator_name' => $creatorName,
                'creator_user_id' => $creatorUserId,
            ]);
        } catch (Throwable) {
            return;
        }

        $this->cachedVideos = null;
    }

    public function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = slugify($title);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $pdo = Database::connection();

        if ($pdo instanceof PDO) {
            try {
                $sql = 'SELECT COUNT(*) FROM videos WHERE slug = :slug';
                $params = ['slug' => $slug];

                if ($ignoreId !== null) {
                    $sql .= ' AND id <> :ignore_id';
                    $params['ignore_id'] = $ignoreId;
                }

                $statement = $pdo->prepare($sql);
                $statement->execute($params);

                return (int) $statement->fetchColumn() > 0;
            } catch (Throwable) {
                return false;
            }
        }

        foreach ($this->listPublished() as $video) {
            if ((string) ($video['slug'] ?? '') === $slug && (int) ($video['id'] ?? 0) !== (int) $ignoreId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string|int> $filters
     * @return array{0:string,1:array<string, string|int>}
     */
    private function buildAdminWhere(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $sourceType = trim((string) ($filters['source_type'] ?? ''));
        $storageProvider = trim((string) ($filters['storage_provider'] ?? ''));
        $creatorUserId = (int) ($filters['creator_user_id'] ?? 0);

        $conditions = ['deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $conditions[] = '(title LIKE :search OR creator_name LIKE :search OR category LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if (in_array($status, ['draft', 'approved', 'flagged'], true)) {
            $conditions[] = 'moderation_status = :status';
            $params['status'] = $status;
        }

        if (in_array($sourceType, ['upload', 'external_file', 'embed'], true)) {
            $conditions[] = 'source_type = :source_type';
            $params['source_type'] = $sourceType;
        }

        if (in_array($storageProvider, ['local', 'wasabi', 'external'], true)) {
            $conditions[] = 'storage_provider = :storage_provider';
            $params['storage_provider'] = $storageProvider;
        }

        if ($creatorUserId > 0) {
            $conditions[] = 'creator_user_id = :creator_user_id';
            $params['creator_user_id'] = $creatorUserId;
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, mixed> $video
     * @return array<string, mixed>
     */
    private function normalizeVideo(array $video): array
    {
        $tone = (int) ($video['poster_tone'] ?? 0);
        $posterUrl = $video['poster_url'] ?? null;
        $detailPosterUrl = $posterUrl ?: poster_data_url((string) $video['title'], (string) $video['category'], $tone);
        $listingPosterUrl = $posterUrl ?: poster_listing_data_url((string) $video['title'], (string) $video['category'], $tone);

        return [
            'id' => (int) ($video['id'] ?? 0),
            'slug' => (string) ($video['slug'] ?? ''),
            'title' => (string) ($video['title'] ?? ''),
            'synopsis' => (string) ($video['synopsis'] ?? ''),
            'creator_user_id' => !empty($video['creator_user_id']) ? (int) $video['creator_user_id'] : null,
            'creator_name' => (string) ($video['creator_name'] ?? ''),
            'category' => (string) ($video['category'] ?? ''),
            'access_level' => normalize_access_level((string) ($video['access_level'] ?? 'free')),
            'access_label' => access_label((string) ($video['access_level'] ?? 'free')),
            'duration_minutes' => (int) ($video['duration_minutes'] ?? 0),
            'duration_label' => duration_label((int) ($video['duration_minutes'] ?? 0)),
            'poster_tone' => $tone,
            'stored_poster_url' => !empty($video['poster_url']) ? (string) $video['poster_url'] : null,
            'poster_url' => $detailPosterUrl,
            'listing_poster_url' => $listingPosterUrl,
            'poster_path' => !empty($video['poster_path']) ? (string) $video['poster_path'] : null,
            'poster_focus_x' => normalize_poster_focus($video['poster_focus_x'] ?? 50),
            'poster_focus_y' => normalize_poster_focus($video['poster_focus_y'] ?? 50),
            'poster_object_position' => poster_object_position($video),
            'poster_storage_provider' => !empty($video['poster_storage_provider'])
                ? (string) $video['poster_storage_provider']
                : (!empty($video['poster_path']) ? (string) ($video['storage_provider'] ?? 'local') : null),
            'video_url' => !empty($video['video_url']) ? (string) $video['video_url'] : null,
            'file_path' => !empty($video['file_path']) ? (string) $video['file_path'] : null,
            'trailer_url' => !empty($video['trailer_url']) ? (string) $video['trailer_url'] : null,
            'embed_url' => !empty($video['embed_url']) ? (string) $video['embed_url'] : null,
            'mime_type' => !empty($video['mime_type']) ? (string) $video['mime_type'] : null,
            'original_source_url' => !empty($video['original_source_url']) ? (string) $video['original_source_url'] : null,
            'source_type' => (string) ($video['source_type'] ?? 'upload'),
            'storage_provider' => (string) ($video['storage_provider'] ?? 'local'),
            'is_featured' => (int) ($video['is_featured'] ?? 0),
            'moderation_status' => (string) ($video['moderation_status'] ?? 'draft'),
            'moderation_label' => moderation_label((string) ($video['moderation_status'] ?? 'draft')),
            'moderation_notes' => !empty($video['moderation_notes']) ? (string) $video['moderation_notes'] : '',
            'published_at' => !empty($video['published_at']) ? (string) $video['published_at'] : null,
            'published_label' => format_datetime(!empty($video['published_at']) ? (string) $video['published_at'] : null),
            'deleted_at' => !empty($video['deleted_at']) ? (string) $video['deleted_at'] : null,
            'created_at' => !empty($video['created_at']) ? (string) $video['created_at'] : null,
            'updated_at' => !empty($video['updated_at']) ? (string) $video['updated_at'] : null,
        ];
    }
}
