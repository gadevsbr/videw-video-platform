<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class PasswordResetRepository
{
    public function create(int $userId, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. Password reset requires MySQL to be online.');
        }

        try {
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id OR expires_at < NOW()')
                ->execute(['user_id' => $userId]);

            $statement = $pdo->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used_at, created_at)
                 VALUES (:user_id, :token_hash, :expires_at, NULL, NOW())'
            );
            $statement->execute([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not create the reset token. ' . $exception->getMessage());
        }
    }

    public function findActive(string $tokenHash): ?array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            $statement = $pdo->prepare(
                'SELECT id, user_id, token_hash, expires_at, used_at, created_at
                 FROM password_reset_tokens
                 WHERE token_hash = :token_hash
                   AND used_at IS NULL
                   AND expires_at >= NOW()
                 LIMIT 1'
            );
            $statement->execute(['token_hash' => $tokenHash]);
            $row = $statement->fetch();

            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function consume(int $id): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database unavailable. Password reset requires MySQL to be online.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $id]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not consume the reset token. ' . $exception->getMessage());
        }
    }

    public function invalidateForUser(int $userId): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return;
        }

        try {
            $statement = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id OR expires_at < NOW()');
            $statement->execute(['user_id' => $userId]);
        } catch (Throwable) {
            return;
        }
    }
}
