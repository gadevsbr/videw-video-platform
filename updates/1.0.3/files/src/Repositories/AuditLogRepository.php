<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;
use Throwable;

final class AuditLogRepository
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function record(?int $actorUserId, string $action, string $targetType, ?int $targetId, string $summary, array $metadata = []): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return;
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, summary, metadata_json, created_at)
                 VALUES (:actor_user_id, :action, :target_type, :target_id, :summary, :metadata_json, NOW())'
            );
            $statement->execute([
                'actor_user_id' => $actorUserId,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'summary' => $summary,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
            ]);
        } catch (Throwable) {
            return;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        return $this->paginate([], 1, $limit)['items'];
    }

    /**
     * @param array<string, string> $filters
     * @return array{items:array<int, array<string, mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 1,
            ];
        }

        [$where, $params] = $this->buildWhere($filters);
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        try {
            $countStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM audit_logs
                 LEFT JOIN users ON users.id = audit_logs.actor_user_id
                 WHERE ' . $where
            );
            foreach ($params as $key => $value) {
                $countStatement->bindValue(':' . $key, $value);
            }
            $countStatement->execute();
            $total = (int) $countStatement->fetchColumn();

            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            $statement = $pdo->prepare(
                'SELECT
                    audit_logs.id,
                    audit_logs.action,
                    audit_logs.target_type,
                    audit_logs.target_id,
                    audit_logs.summary,
                    audit_logs.metadata_json,
                    audit_logs.created_at,
                    users.display_name AS actor_name,
                    users.email AS actor_email
                 FROM audit_logs
                 LEFT JOIN users ON users.id = audit_logs.actor_user_id
                 WHERE ' . $where . '
                 ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
                 LIMIT :offset, :limit'
            );
            foreach ($params as $key => $value) {
                $statement->bindValue(':' . $key, $value);
            }
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->execute();

            return [
                'items' => $statement->fetchAll() ?: [],
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
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function filtered(array $filters = [], int $limit = 2000): array
    {
        $result = $this->paginate($filters, 1, $limit);

        return $result['items'];
    }

    /**
     * @param array<int, int> $targetIds
     * @param array<int, string> $actions
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function recentForTargets(string $targetType, array $targetIds, int $limitPerTarget = 3, array $actions = []): array
    {
        $pdo = Database::connection();
        $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds), static fn (int $id): bool => $id > 0)));
        $limitPerTarget = max(1, min(20, $limitPerTarget));

        if (!$pdo instanceof PDO || $targetIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['target_type' => $targetType];

        foreach ($targetIds as $index => $targetId) {
            $key = 'target_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $targetId;
        }

        $actionSql = '';

        if ($actions !== []) {
            $actionPlaceholders = [];

            foreach (array_values($actions) as $index => $action) {
                $key = 'action_' . $index;
                $actionPlaceholders[] = ':' . $key;
                $params[$key] = $action;
            }

            $actionSql = ' AND audit_logs.action IN (' . implode(', ', $actionPlaceholders) . ')';
        }

        try {
            $statement = $pdo->prepare(
                'SELECT
                    audit_logs.id,
                    audit_logs.action,
                    audit_logs.target_type,
                    audit_logs.target_id,
                    audit_logs.summary,
                    audit_logs.metadata_json,
                    audit_logs.created_at,
                    users.display_name AS actor_name,
                    users.email AS actor_email
                 FROM audit_logs
                 LEFT JOIN users ON users.id = audit_logs.actor_user_id
                 WHERE audit_logs.target_type = :target_type
                   AND audit_logs.target_id IN (' . implode(', ', $placeholders) . ')' . $actionSql . '
                 ORDER BY audit_logs.created_at DESC, audit_logs.id DESC'
            );

            foreach ($params as $key => $value) {
                if (str_starts_with($key, 'target_id_')) {
                    $statement->bindValue(':' . $key, (int) $value, PDO::PARAM_INT);
                    continue;
                }

                $statement->bindValue(':' . $key, $value);
            }

            $statement->execute();
            $rows = $statement->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }

        $grouped = [];

        foreach ($rows as $row) {
            $targetId = (int) ($row['target_id'] ?? 0);

            if ($targetId <= 0) {
                continue;
            }

            if (!isset($grouped[$targetId])) {
                $grouped[$targetId] = [];
            }

            if (count($grouped[$targetId]) >= $limitPerTarget) {
                continue;
            }

            $grouped[$targetId][] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<string, string> $filters
     * @return array{0:string,1:array<string, string>}
     */
    private function buildWhere(array $filters): array
    {
        $action = trim((string) ($filters['action'] ?? ''));
        $targetType = trim((string) ($filters['target_type'] ?? ''));
        $actor = trim((string) ($filters['actor'] ?? ''));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        $conditions = ['1 = 1'];
        $params = [];

        if ($action !== '') {
            $conditions[] = 'audit_logs.action = :action';
            $params['action'] = $action;
        }

        if ($targetType !== '') {
            $conditions[] = 'audit_logs.target_type = :target_type';
            $params['target_type'] = $targetType;
        }

        if ($actor !== '') {
            $conditions[] = '(users.display_name LIKE :actor OR users.email LIKE :actor)';
            $params['actor'] = '%' . $actor . '%';
        }

        if ($from !== '') {
            $conditions[] = 'DATE(audit_logs.created_at) >= :from_date';
            $params['from_date'] = $from;
        }

        if ($to !== '') {
            $conditions[] = 'DATE(audit_logs.created_at) <= :to_date';
            $params['to_date'] = $to;
        }

        return [implode(' AND ', $conditions), $params];
    }
}
