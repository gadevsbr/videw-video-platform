<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;
use RuntimeException;
use Throwable;

final class UserRepository
{
    public function hasAdmin(): bool
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $statement = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        } catch (Throwable) {
            return false;
        }

        return (int) $statement->fetchColumn() > 0;
    }

    public function findById(int $id): ?array
    {
        return $this->findOneBy('id', (string) $id);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy('email', $email);
    }

    public function findByStripeCustomerId(string $stripeCustomerId): ?array
    {
        return $this->findOneBy('stripe_customer_id', $stripeCustomerId);
    }

    public function findByStripeSubscriptionId(string $stripeSubscriptionId): ?array
    {
        return $this->findOneBy('stripe_subscription_id', $stripeSubscriptionId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(string $search = ''): array
    {
        return $this->paginateAll($search, 1, 500)['items'];
    }

    /**
     * @return array{items:array<int, array<string, mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public function paginateAll(string $search = '', int $page = 1, int $perPage = 12): array
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
        $params = [];
        $where = '';

        if ($search !== '') {
            $where = 'WHERE display_name LIKE :search OR email LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        try {
            $countStatement = $pdo->prepare('SELECT COUNT(*) FROM users ' . $where);
            $countStatement->execute($params);
            $total = (int) $countStatement->fetchColumn();

            $totalPages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            $statement = $pdo->prepare(
                'SELECT id, display_name, email, role, status, account_tier, stripe_subscription_status, birth_date, adult_confirmed_at, last_login_at, mfa_enabled, created_at
                 FROM users
                 ' . $where . '
                 ORDER BY created_at DESC, id DESC
                 LIMIT :offset, :limit'
            );

            foreach ($params as $key => $value) {
                $statement->bindValue(':' . $key, $value);
            }

            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
            $statement->execute();

            return [
                'items' => array_map([$this, 'normalizeUser'], $statement->fetchAll() ?: []),
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
     * @return array<string, int>
     */
    public function stats(): array
    {
        $users = $this->listAll();
        $admins = array_filter($users, static fn (array $user): bool => (string) ($user['role'] ?? '') === 'admin');
        $creators = array_filter($users, static fn (array $user): bool => (string) ($user['role'] ?? '') === 'creator');
        $suspended = array_filter($users, static fn (array $user): bool => (string) ($user['status'] ?? 'active') === 'suspended');
        $mfaEnabled = array_filter($users, static fn (array $user): bool => (int) ($user['mfa_enabled'] ?? 0) === 1);
        $premium = array_filter($users, static fn (array $user): bool => (string) ($user['account_tier'] ?? 'free') === 'premium');

        return [
            'users' => count($users),
            'admins' => count($admins),
            'creators' => count($creators),
            'suspended' => count($suspended),
            'mfa_enabled' => count($mfaEnabled),
            'premium' => count($premium),
        ];
    }

    public function activeAdminCount(): int
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return 0;
        }

        try {
            $statement = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");

            return (int) $statement->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    public function createMember(array $payload): array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Sign-up is temporarily unavailable. Please try again later.');
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO users (
                    display_name, email, password_hash, role, status, birth_date, adult_confirmed_at,
                    account_tier, stripe_customer_id, stripe_subscription_id, stripe_subscription_price_id,
                    stripe_subscription_status, stripe_current_period_end, mfa_secret, mfa_enabled,
                    mfa_backup_codes_json, created_at
                 ) VALUES (
                    :display_name, :email, :password_hash, :role, :status, :birth_date, :adult_confirmed_at,
                    :account_tier, NULL, NULL, NULL,
                    NULL, NULL, NULL, 0,
                    NULL, NOW()
                 )'
            );
            $statement->execute([
                'display_name' => $payload['display_name'],
                'email' => $payload['email'],
                'password_hash' => $payload['password_hash'],
                'role' => $payload['role'] ?? 'member',
                'status' => $payload['status'] ?? 'active',
                'account_tier' => $payload['account_tier'] ?? 'free',
                'birth_date' => $payload['birth_date'],
                'adult_confirmed_at' => $payload['adult_confirmed_at'],
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Sign-up is temporarily unavailable. Please try again later.');
        }

        return $this->findByEmail((string) $payload['email']) ?? [];
    }

    public function updateAdminFields(int $id, string $role, string $status): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('User management is temporarily unavailable.');
        }

        if (!in_array($role, ['member', 'creator', 'admin'], true)) {
            throw new RuntimeException('Invalid role.');
        }

        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new RuntimeException('Invalid user status.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE users
                 SET role = :role, status = :status, updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'role' => $role,
                'status' => $status,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not update the user. ' . $exception->getMessage());
        }
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Password reset is temporarily unavailable.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE users
                 SET password_hash = :password_hash, updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'password_hash' => $passwordHash,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not update the password. ' . $exception->getMessage());
        }
    }

    /**
     * @param array<int, string> $backupCodeHashes
     */
    public function saveMfa(int $id, string $secret, array $backupCodeHashes): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Two-step verification is temporarily unavailable.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE users
                 SET mfa_secret = :mfa_secret,
                     mfa_enabled = 1,
                     mfa_backup_codes_json = :mfa_backup_codes_json,
                     updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'mfa_secret' => $secret,
                'mfa_backup_codes_json' => json_encode(array_values($backupCodeHashes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not enable MFA. ' . $exception->getMessage());
        }
    }

    /**
     * @param array<int, string> $backupCodeHashes
     */
    public function updateMfaBackupCodes(int $id, array $backupCodeHashes): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Two-step verification is temporarily unavailable.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE users
                 SET mfa_backup_codes_json = :mfa_backup_codes_json,
                     updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'mfa_backup_codes_json' => json_encode(array_values($backupCodeHashes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not update MFA backup codes. ' . $exception->getMessage());
        }
    }

    public function clearMfa(int $id): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Two-step verification is temporarily unavailable.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE users
                 SET mfa_secret = NULL,
                     mfa_enabled = 0,
                     mfa_backup_codes_json = NULL,
                     updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $id]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not disable MFA. ' . $exception->getMessage());
        }
    }

    public function touchLastLogin(int $id): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return;
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE users
                 SET last_login_at = NOW(), updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $id]);
        } catch (Throwable) {
            return;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function syncStripeBilling(int $id, array $payload): void
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Membership updates are temporarily unavailable.');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE users
                 SET account_tier = :account_tier,
                     stripe_customer_id = :stripe_customer_id,
                     stripe_subscription_id = :stripe_subscription_id,
                     stripe_subscription_price_id = :stripe_subscription_price_id,
                     stripe_subscription_status = :stripe_subscription_status,
                     stripe_current_period_end = :stripe_current_period_end,
                     updated_at = NOW()
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $id,
                'account_tier' => $payload['account_tier'] ?? 'free',
                'stripe_customer_id' => $payload['stripe_customer_id'] ?? null,
                'stripe_subscription_id' => $payload['stripe_subscription_id'] ?? null,
                'stripe_subscription_price_id' => $payload['stripe_subscription_price_id'] ?? null,
                'stripe_subscription_status' => $payload['stripe_subscription_status'] ?? null,
                'stripe_current_period_end' => $payload['stripe_current_period_end'] ?? null,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not sync billing status. ' . $exception->getMessage());
        }
    }

    private function findOneBy(string $column, string $value): ?array
    {
        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return null;
        }

        if (!in_array($column, ['id', 'email', 'stripe_customer_id', 'stripe_subscription_id'], true)) {
            return null;
        }

        try {
            $statement = $pdo->prepare(
                'SELECT
                    id, display_name, email, password_hash, role, status, birth_date, adult_confirmed_at,
                    account_tier, stripe_customer_id, stripe_subscription_id, stripe_subscription_price_id,
                    stripe_subscription_status, stripe_current_period_end, last_login_at, mfa_secret, mfa_enabled,
                    mfa_backup_codes_json, created_at
                 FROM users
                 WHERE ' . $column . ' = :value
                 LIMIT 1'
            );
            $statement->execute(['value' => $value]);
            $user = $statement->fetch();

            return $user ? $this->normalizeUser($user) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function normalizeUser(array $user): array
    {
        $backupCodes = [];

        if (isset($user['mfa_backup_codes_json']) && is_string($user['mfa_backup_codes_json']) && $user['mfa_backup_codes_json'] !== '') {
            $decoded = json_decode($user['mfa_backup_codes_json'], true);

            if (is_array($decoded)) {
                $backupCodes = array_values(array_filter($decoded, 'is_string'));
            }
        }

        $user['mfa_enabled'] = (int) ($user['mfa_enabled'] ?? 0);
        $user['mfa_secret'] = isset($user['mfa_secret']) && $user['mfa_secret'] !== '' ? (string) $user['mfa_secret'] : null;
        $user['mfa_backup_codes'] = $backupCodes;
        $user['account_tier'] = (string) ($user['account_tier'] ?? 'free');
        $user['stripe_customer_id'] = isset($user['stripe_customer_id']) && $user['stripe_customer_id'] !== '' ? (string) $user['stripe_customer_id'] : null;
        $user['stripe_subscription_id'] = isset($user['stripe_subscription_id']) && $user['stripe_subscription_id'] !== '' ? (string) $user['stripe_subscription_id'] : null;
        $user['stripe_subscription_price_id'] = isset($user['stripe_subscription_price_id']) && $user['stripe_subscription_price_id'] !== '' ? (string) $user['stripe_subscription_price_id'] : null;
        $user['stripe_subscription_status'] = isset($user['stripe_subscription_status']) && $user['stripe_subscription_status'] !== '' ? (string) $user['stripe_subscription_status'] : null;
        $user['stripe_current_period_end'] = isset($user['stripe_current_period_end']) && $user['stripe_current_period_end'] !== '' ? (string) $user['stripe_current_period_end'] : null;

        return $user;
    }
}
