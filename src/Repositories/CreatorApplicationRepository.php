<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;
use RuntimeException;
use Throwable;

final class CreatorApplicationRepository
{
    public function latestForUser(int $userId): ?array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            $statement = $pdo->prepare(
                'SELECT creator_applications.*, users.display_name AS user_display_name, users.email AS user_email
                 FROM creator_applications
                 INNER JOIN users ON users.id = creator_applications.user_id
                 WHERE creator_applications.user_id = :user_id
                 ORDER BY creator_applications.created_at DESC, creator_applications.id DESC
                 LIMIT 1'
            );
            $statement->execute(['user_id' => $userId]);
            $row = $statement->fetch();

            return $row ? $this->normalize($row) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createOrRefreshPending(int $userId, array $payload): int
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Creator applications are temporarily unavailable.');
        }

        $requestedDisplayName = trim((string) ($payload['requested_display_name'] ?? ''));
        $requestedSlug = trim((string) ($payload['requested_slug'] ?? ''));
        $requestedBio = trim((string) ($payload['requested_bio'] ?? ''));

        if ($requestedDisplayName === '' || $requestedSlug === '') {
            throw new RuntimeException('Channel name and channel link are required.');
        }

        $existing = $this->latestForUser($userId);

        try {
            if ($existing && (string) ($existing['status'] ?? '') === 'pending') {
                $statement = $pdo->prepare(
                    'UPDATE creator_applications
                     SET requested_display_name = :requested_display_name,
                         requested_slug = :requested_slug,
                         requested_bio = :requested_bio,
                         updated_at = NOW()
                     WHERE id = :id
                     LIMIT 1'
                );
                $statement->execute([
                    'id' => $existing['id'],
                    'requested_display_name' => $requestedDisplayName,
                    'requested_slug' => $requestedSlug,
                    'requested_bio' => $requestedBio !== '' ? $requestedBio : null,
                ]);

                return (int) $existing['id'];
            }

            $statement = $pdo->prepare(
                'INSERT INTO creator_applications (
                    user_id, requested_display_name, requested_slug, requested_bio, status, review_notes, reviewed_at, created_at, updated_at
                 ) VALUES (
                    :user_id, :requested_display_name, :requested_slug, :requested_bio, \'pending\', NULL, NULL, NOW(), NOW()
                 )'
            );
            $statement->execute([
                'user_id' => $userId,
                'requested_display_name' => $requestedDisplayName,
                'requested_slug' => $requestedSlug,
                'requested_bio' => $requestedBio !== '' ? $requestedBio : null,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not save the creator application. ' . $exception->getMessage());
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array{items:array<int, array<string, mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function paginate(string $status = 'pending', int $page = 1, int $perPage = 12): array
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

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'pending';

        try {
            $countStatement = $pdo->prepare('SELECT COUNT(*) FROM creator_applications WHERE status = :status');
            $countStatement->execute(['status' => $status]);
            $total = (int) $countStatement->fetchColumn();

            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            $statement = $pdo->prepare(
                'SELECT creator_applications.*, users.display_name AS user_display_name, users.email AS user_email
                 FROM creator_applications
                 INNER JOIN users ON users.id = creator_applications.user_id
                 WHERE creator_applications.status = :status
                 ORDER BY creator_applications.created_at DESC, creator_applications.id DESC
                 LIMIT :offset, :limit'
            );
            $statement->bindValue(':status', $status);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->execute();

            return [
                'items' => array_map([$this, 'normalize'], $statement->fetchAll() ?: []),
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

    public function findById(int $id): ?array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            $statement = $pdo->prepare(
                'SELECT creator_applications.*, users.display_name AS user_display_name, users.email AS user_email
                 FROM creator_applications
                 INNER JOIN users ON users.id = creator_applications.user_id
                 WHERE creator_applications.id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();

            return $row ? $this->normalize($row) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function updateStatus(int $id, string $status, ?string $reviewNotes = null): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Creator review is temporarily unavailable.');
        }

        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
            throw new RuntimeException('Invalid creator application status.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE creator_applications
                 SET status = :status,
                     review_notes = :review_notes,
                     reviewed_at = CASE WHEN :status = \'pending\' THEN NULL ELSE NOW() END,
                     updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'status' => $status,
                'review_notes' => $reviewNotes !== null && trim($reviewNotes) !== '' ? trim($reviewNotes) : null,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not update the creator application. ' . $exception->getMessage());
        }
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
            ];
        }

        try {
            $statement = $pdo->query(
                'SELECT status, COUNT(*) AS total
                 FROM creator_applications
                 GROUP BY status'
            );
            $rows = $statement->fetchAll() ?: [];
        } catch (Throwable) {
            $rows = [];
        }

        $stats = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');

            if (array_key_exists($status, $stats)) {
                $stats[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'requested_display_name' => (string) ($row['requested_display_name'] ?? ''),
            'requested_slug' => (string) ($row['requested_slug'] ?? ''),
            'requested_bio' => trim((string) ($row['requested_bio'] ?? '')),
            'status' => (string) ($row['status'] ?? 'pending'),
            'review_notes' => trim((string) ($row['review_notes'] ?? '')),
            'reviewed_at' => !empty($row['reviewed_at']) ? (string) $row['reviewed_at'] : null,
            'created_at' => !empty($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => !empty($row['updated_at']) ? (string) $row['updated_at'] : null,
            'created_label' => format_datetime(!empty($row['created_at']) ? (string) $row['created_at'] : null),
            'reviewed_label' => format_datetime(!empty($row['reviewed_at']) ? (string) $row['reviewed_at'] : null, 'Not reviewed'),
            'user_display_name' => (string) ($row['user_display_name'] ?? 'Member'),
            'user_email' => (string) ($row['user_email'] ?? ''),
        ];
    }
}
