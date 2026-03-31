<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;
use Throwable;

final class VideoAnalyticsRepository
{
    public function recordView(int $videoId, int $creatorUserId, ?int $viewerUserId, string $sessionKey): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO || $videoId <= 0 || $creatorUserId <= 0 || trim($sessionKey) === '') {
            return;
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO video_views (video_id, creator_user_id, viewer_user_id, session_key, viewed_on, created_at)
                 VALUES (:video_id, :creator_user_id, :viewer_user_id, :session_key, CURDATE(), NOW())
                 ON DUPLICATE KEY UPDATE created_at = created_at'
            );
            $statement->execute([
                'video_id' => $videoId,
                'creator_user_id' => $creatorUserId,
                'viewer_user_id' => $viewerUserId,
                'session_key' => $sessionKey,
            ]);
        } catch (Throwable) {
            return;
        }
    }

    /**
     * @return array<string, int>
     */
    public function overview(int $creatorUserId): array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return [
                'views_total' => 0,
                'views_7d' => 0,
                'views_30d' => 0,
                'unique_viewers_30d' => 0,
            ];
        }

        try {
            $statement = $pdo->prepare(
                'SELECT
                    COUNT(*) AS views_total,
                    SUM(CASE WHEN viewed_on >= CURDATE() - INTERVAL 6 DAY THEN 1 ELSE 0 END) AS views_7d,
                    SUM(CASE WHEN viewed_on >= CURDATE() - INTERVAL 29 DAY THEN 1 ELSE 0 END) AS views_30d,
                    COUNT(DISTINCT CASE WHEN viewed_on >= CURDATE() - INTERVAL 29 DAY THEN session_key ELSE NULL END) AS unique_viewers_30d
                 FROM video_views
                 WHERE creator_user_id = :creator_user_id'
            );
            $statement->execute(['creator_user_id' => $creatorUserId]);
            $row = $statement->fetch() ?: [];
        } catch (Throwable) {
            $row = [];
        }

        return [
            'views_total' => (int) ($row['views_total'] ?? 0),
            'views_7d' => (int) ($row['views_7d'] ?? 0),
            'views_30d' => (int) ($row['views_30d'] ?? 0),
            'unique_viewers_30d' => (int) ($row['unique_viewers_30d'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{date:string,label:string,views:int}>
     */
    public function dailySeries(int $creatorUserId, int $days = 14): array
    {
        $pdo = Database::connection();
        $days = max(1, min(90, $days));
        $end = new DateTimeImmutable('today');
        $start = $end->sub(new DateInterval('P' . ($days - 1) . 'D'));

        $series = [];
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->add(new DateInterval('P1D')));

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $series[$key] = [
                'date' => $key,
                'label' => $date->format('M j'),
                'views' => 0,
            ];
        }

        if (!$pdo instanceof PDO) {
            return array_values($series);
        }

        try {
            $statement = $pdo->prepare(
                'SELECT viewed_on, COUNT(*) AS total
                 FROM video_views
                 WHERE creator_user_id = :creator_user_id
                   AND viewed_on BETWEEN :start_date AND :end_date
                 GROUP BY viewed_on
                 ORDER BY viewed_on ASC'
            );
            $statement->execute([
                'creator_user_id' => $creatorUserId,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ]);

            foreach ($statement->fetchAll() ?: [] as $row) {
                $key = (string) ($row['viewed_on'] ?? '');

                if (isset($series[$key])) {
                    $series[$key]['views'] = (int) ($row['total'] ?? 0);
                }
            }
        } catch (Throwable) {
            return array_values($series);
        }

        return array_values($series);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function topVideos(int $creatorUserId, int $limit = 12): array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return [];
        }

        $limit = max(1, min(50, $limit));

        try {
            $statement = $pdo->prepare(
                'SELECT
                    videos.id,
                    videos.slug,
                    videos.title,
                    videos.access_level,
                    videos.moderation_status,
                    videos.published_at,
                    COUNT(video_views.id) AS total_views,
                    SUM(CASE WHEN video_views.viewed_on >= CURDATE() - INTERVAL 29 DAY THEN 1 ELSE 0 END) AS views_30d,
                    MAX(video_views.created_at) AS last_view_at
                 FROM videos
                 LEFT JOIN video_views ON video_views.video_id = videos.id
                 WHERE videos.creator_user_id = :creator_user_id
                   AND videos.deleted_at IS NULL
                 GROUP BY videos.id, videos.slug, videos.title, videos.access_level, videos.moderation_status, videos.published_at
                 ORDER BY total_views DESC, views_30d DESC, videos.created_at DESC
                 LIMIT :limit'
            );
            $statement->bindValue(':creator_user_id', $creatorUserId, PDO::PARAM_INT);
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }

        return array_map(static function (array $row): array {
            $publishedAt = !empty($row['published_at']) ? (string) $row['published_at'] : null;
            $lastViewAt = !empty($row['last_view_at']) ? (string) $row['last_view_at'] : null;

            return [
                'id' => (int) ($row['id'] ?? 0),
                'slug' => (string) ($row['slug'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'access_level' => normalize_access_level((string) ($row['access_level'] ?? 'free')),
                'access_label' => access_label((string) ($row['access_level'] ?? 'free')),
                'moderation_status' => (string) ($row['moderation_status'] ?? 'draft'),
                'moderation_label' => moderation_label((string) ($row['moderation_status'] ?? 'draft')),
                'published_at' => $publishedAt,
                'published_label' => format_datetime($publishedAt),
                'total_views' => (int) ($row['total_views'] ?? 0),
                'views_30d' => (int) ($row['views_30d'] ?? 0),
                'last_view_at' => $lastViewAt,
                'last_view_label' => format_datetime($lastViewAt),
            ];
        }, $rows);
    }
}
