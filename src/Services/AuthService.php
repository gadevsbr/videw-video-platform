<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Security\RateLimiter;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class AuthService
{
    private const LOGIN_RATE_LIMIT_WINDOW = 600;
    private const LOGIN_RATE_LIMIT_MAX_IP = 25;
    private const LOGIN_RATE_LIMIT_MAX_CREDENTIAL = 8;
    private const RESET_RATE_LIMIT_WINDOW = 900;
    private const RESET_RATE_LIMIT_MAX_IP = 6;
    private const RESET_RATE_LIMIT_MAX_EMAIL = 3;
    private const MFA_RATE_LIMIT_WINDOW = 600;
    private const MFA_RATE_LIMIT_MAX_IP = 10;
    private const MFA_RATE_LIMIT_MAX_USER = 6;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly PasswordResetRepository $passwordResets = new PasswordResetRepository(),
        private readonly TwoFactorService $twoFactor = new TwoFactorService(),
        private readonly RateLimiter $rateLimiter = new RateLimiter()
    ) {
    }

    /**
     * @return array{success:bool,message:string,requires_mfa?:bool}
     */
    public function attemptLogin(string $email, string $password): array
    {
        $email = trim($email);
        $ipKey = $this->loginIpKey();
        $credentialKey = $this->loginCredentialKey($email);

        if ($this->rateLimiter->tooManyAttempts($ipKey, self::LOGIN_RATE_LIMIT_MAX_IP, self::LOGIN_RATE_LIMIT_WINDOW)
            || $this->rateLimiter->tooManyAttempts($credentialKey, self::LOGIN_RATE_LIMIT_MAX_CREDENTIAL, self::LOGIN_RATE_LIMIT_WINDOW)) {
            return [
                'success' => false,
                'message' => $this->throttleMessage(
                    $this->retryAfterSeconds([$ipKey, $credentialKey], self::LOGIN_RATE_LIMIT_WINDOW)
                ),
            ];
        }

        if (!Database::connection()) {
            return [
                'success' => false,
                'message' => 'Database unavailable. Import the MySQL schema before using sign in.',
            ];
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $this->rateLimiter->hit($ipKey, self::LOGIN_RATE_LIMIT_WINDOW);
            $this->rateLimiter->hit($credentialKey, self::LOGIN_RATE_LIMIT_WINDOW);

            return [
                'success' => false,
                'message' => 'Invalid email or password.',
            ];
        }

        if (($user['status'] ?? 'active') !== 'active') {
            return [
                'success' => false,
                'message' => 'This account is suspended.',
            ];
        }

        if ((int) ($user['mfa_enabled'] ?? 0) === 1) {
            $this->clearLoginRateLimits($email);
            $this->setPendingMfaUser($user);

            return [
                'success' => true,
                'message' => 'Enter your 2FA code to continue.',
                'requires_mfa' => true,
            ];
        }

        $this->clearLoginRateLimits($email);
        $this->storeUser($user);
        $this->users->touchLastLogin((int) ($user['id'] ?? 0));

        return [
            'success' => true,
            'message' => 'Signed in successfully.',
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{success:bool,message:string}
     */
    public function registerMember(array $input): array
    {
        $displayName = trim($input['display_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $passwordConfirmation = $input['password_confirmation'] ?? '';
        $birthDate = $input['birth_date'] ?? '';
        $acceptedTerms = ($input['adult_terms'] ?? '') === '1';

        if ($displayName === '' || $email === '' || $password === '' || $birthDate === '') {
            return ['success' => false, 'message' => 'Fill in all required fields.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Enter a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }

        if ($password !== $passwordConfirmation) {
            return ['success' => false, 'message' => 'Password confirmation does not match.'];
        }

        if (!$acceptedTerms) {
            return ['success' => false, 'message' => 'You must confirm you are 18+ and accept the adult policy.'];
        }

        try {
            $birthDateObject = new DateTimeImmutable($birthDate);
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Invalid birth date.'];
        }

        if (years_between($birthDateObject) < 18) {
            return ['success' => false, 'message' => 'Registration is available only to users 18 or older.'];
        }

        if ($this->users->findByEmail($email)) {
            return ['success' => false, 'message' => 'An account with this email already exists.'];
        }

        $role = $this->users->hasAdmin() ? 'member' : 'admin';

        try {
            $user = $this->users->createMember([
                'display_name' => $displayName,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'status' => 'active',
                'account_tier' => 'free',
                'birth_date' => $birthDateObject->format('Y-m-d'),
                'adult_confirmed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        $this->storeUser($user);

        return [
            'success' => true,
            'message' => $role === 'admin'
                ? 'Account created. This first account has admin access.'
                : 'Account created successfully.',
        ];
    }

    /**
     * @return array{success:bool,message:string,reset_url?:string}
     */
    public function requestPasswordReset(string $email): array
    {
        $email = trim($email);
        $ipKey = $this->passwordResetIpKey();
        $emailKey = $this->passwordResetEmailKey($email);

        if ($this->rateLimiter->tooManyAttempts($ipKey, self::RESET_RATE_LIMIT_MAX_IP, self::RESET_RATE_LIMIT_WINDOW)
            || $this->rateLimiter->tooManyAttempts($emailKey, self::RESET_RATE_LIMIT_MAX_EMAIL, self::RESET_RATE_LIMIT_WINDOW)) {
            return [
                'success' => false,
                'message' => $this->throttleMessage(
                    $this->retryAfterSeconds([$ipKey, $emailKey], self::RESET_RATE_LIMIT_WINDOW)
                ),
            ];
        }

        $user = $this->users->findByEmail($email);
        $this->rateLimiter->hit($ipKey, self::RESET_RATE_LIMIT_WINDOW);
        $this->rateLimiter->hit($emailKey, self::RESET_RATE_LIMIT_WINDOW);

        if (!$user) {
            return [
                'success' => true,
                'message' => 'If the account exists, a reset link has been generated.',
            ];
        }

        try {
            $token = bin2hex(random_bytes(32));
            $this->passwordResets->create(
                (int) $user['id'],
                hash('sha256', $token),
                (new DateTimeImmutable('+1 hour'))
            );
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $response = [
            'success' => true,
            'message' => 'If the account exists, a reset link has been generated.',
        ];

        if ((bool) config('security.expose_reset_links', false)) {
            $response['message'] = 'Reset link generated for development mode.';
            $response['reset_url'] = base_url('reset-password.php?token=' . urlencode($token));
        }

        return $response;
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function resetPassword(string $token, string $password, string $passwordConfirmation): array
    {
        $token = trim($token);

        if ($token === '') {
            return ['success' => false, 'message' => 'Invalid reset token.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }

        if ($password !== $passwordConfirmation) {
            return ['success' => false, 'message' => 'Password confirmation does not match.'];
        }

        $reset = $this->passwordResets->findActive(hash('sha256', $token));

        if (!$reset) {
            return ['success' => false, 'message' => 'Reset link expired or already used.'];
        }

        try {
            $this->users->updatePassword((int) $reset['user_id'], password_hash($password, PASSWORD_DEFAULT));
            $this->passwordResets->consume((int) $reset['id']);
            $this->passwordResets->invalidateForUser((int) $reset['user_id']);
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Password updated. You can sign in now.',
        ];
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function completePendingMfa(string $code): array
    {
        $user = $this->pendingMfaUser();

        if (!$user) {
            return ['success' => false, 'message' => 'Your MFA session expired. Sign in again.'];
        }

        $ipKey = $this->mfaIpKey();
        $userKey = $this->mfaUserKey((int) ($user['id'] ?? 0));

        if ($this->rateLimiter->tooManyAttempts($ipKey, self::MFA_RATE_LIMIT_MAX_IP, self::MFA_RATE_LIMIT_WINDOW)
            || $this->rateLimiter->tooManyAttempts($userKey, self::MFA_RATE_LIMIT_MAX_USER, self::MFA_RATE_LIMIT_WINDOW)) {
            return [
                'success' => false,
                'message' => $this->throttleMessage(
                    $this->retryAfterSeconds([$ipKey, $userKey], self::MFA_RATE_LIMIT_WINDOW)
                ),
            ];
        }

        $verification = $this->verifyMfaCode($user, $code, true);

        if (!$verification['valid']) {
            $this->rateLimiter->hit($ipKey, self::MFA_RATE_LIMIT_WINDOW);
            $this->rateLimiter->hit($userKey, self::MFA_RATE_LIMIT_WINDOW);
            return ['success' => false, 'message' => 'Invalid 2FA code.'];
        }

        try {
            if ($verification['backup_used']) {
                $this->users->updateMfaBackupCodes((int) $user['id'], $verification['remaining_codes']);
            }
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        $this->storeUser($this->users->findById((int) $user['id']) ?? $user);
        $this->users->touchLastLogin((int) ($user['id'] ?? 0));
        $this->clearPendingMfaUser();
        $this->rateLimiter->clear($ipKey);
        $this->rateLimiter->clear($userKey);

        return [
            'success' => true,
            'message' => $verification['backup_used']
                ? 'Backup code accepted. You are now signed in.'
                : '2FA verified. You are now signed in.',
        ];
    }

    /**
     * @return array{secret:string,otpauth_uri:string}|null
     */
    public function currentMfaSetup(int $userId): ?array
    {
        if ((int) ($_SESSION['mfa_setup_user_id'] ?? 0) !== $userId) {
            return null;
        }

        $secret = trim((string) ($_SESSION['mfa_setup_secret'] ?? ''));

        if ($secret === '') {
            return null;
        }

        $user = $this->users->findById($userId);

        if (!$user) {
            return null;
        }

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->twoFactor->otpauthUri(
                (string) config('app.name'),
                (string) ($user['email'] ?? 'account'),
                $secret
            ),
        ];
    }

    /**
     * @return array{success:bool,message:string,setup?:array{secret:string,otpauth_uri:string}}
     */
    public function startMfaSetup(int $userId): array
    {
        $user = $this->users->findById($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'Account not found.'];
        }

        if ((int) ($user['mfa_enabled'] ?? 0) === 1) {
            return ['success' => false, 'message' => '2FA is already enabled.'];
        }

        $secret = $this->twoFactor->generateSecret();
        $_SESSION['mfa_setup_user_id'] = $userId;
        $_SESSION['mfa_setup_secret'] = $secret;

        return [
            'success' => true,
            'message' => '2FA setup started. Add the key to your authenticator app and confirm with a code.',
            'setup' => [
                'secret' => $secret,
                'otpauth_uri' => $this->twoFactor->otpauthUri((string) config('app.name'), (string) ($user['email'] ?? 'account'), $secret),
            ],
        ];
    }

    /**
     * @return array{success:bool,message:string,backup_codes?:array<int, string>}
     */
    public function enableMfaFromPendingSetup(int $userId, string $code): array
    {
        $setup = $this->currentMfaSetup($userId);

        if (!$setup) {
            return ['success' => false, 'message' => 'Start the 2FA setup first.'];
        }

        if (!$this->twoFactor->verifyCode($setup['secret'], $code)) {
            return ['success' => false, 'message' => 'Invalid authenticator code.'];
        }

        $backupCodes = $this->twoFactor->generateBackupCodes();

        try {
            $this->users->saveMfa($userId, $setup['secret'], $this->twoFactor->hashBackupCodes($backupCodes));
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        $this->clearPendingMfaSetup();
        $freshUser = $this->users->findById($userId);

        if ($freshUser && current_user() && (int) (current_user()['id'] ?? 0) === $userId) {
            $this->storeUser($freshUser);
        }

        return [
            'success' => true,
            'message' => '2FA enabled. Save the backup codes now.',
            'backup_codes' => $backupCodes,
        ];
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function disableMfa(int $userId, string $code): array
    {
        $user = $this->users->findById($userId);

        if (!$user || (int) ($user['mfa_enabled'] ?? 0) !== 1) {
            return ['success' => false, 'message' => '2FA is not enabled for this account.'];
        }

        $verification = $this->verifyMfaCode($user, $code, true);

        if (!$verification['valid']) {
            return ['success' => false, 'message' => 'Invalid 2FA code.'];
        }

        try {
            $this->users->clearMfa($userId);
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        $this->clearPendingMfaSetup();
        $freshUser = $this->users->findById($userId);

        if ($freshUser && current_user() && (int) (current_user()['id'] ?? 0) === $userId) {
            $this->storeUser($freshUser);
        }

        return [
            'success' => true,
            'message' => '2FA disabled.',
        ];
    }

    public function logout(): void
    {
        $_SESSION = [];
        $this->rotateSession();
        $this->clearPendingMfaUser();
        $this->clearPendingMfaSetup();
    }

    private function pendingMfaUser(): ?array
    {
        $userId = (int) ($_SESSION['pending_mfa_user_id'] ?? 0);
        $startedAt = (int) ($_SESSION['pending_mfa_started_at'] ?? 0);

        if ($userId <= 0 || $startedAt <= 0 || ($startedAt + 900) < time()) {
            $this->clearPendingMfaUser();

            return null;
        }

        $user = $this->users->findById($userId);

        if (!$user || (string) ($user['status'] ?? 'active') !== 'active') {
            $this->clearPendingMfaUser();

            return null;
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function setPendingMfaUser(array $user): void
    {
        $this->rotateSession();
        $_SESSION['pending_mfa_user_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['pending_mfa_started_at'] = time();
    }

    private function clearPendingMfaUser(): void
    {
        unset($_SESSION['pending_mfa_user_id'], $_SESSION['pending_mfa_started_at']);
    }

    private function clearPendingMfaSetup(): void
    {
        unset($_SESSION['mfa_setup_user_id'], $_SESSION['mfa_setup_secret']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{valid:bool,backup_used:bool,remaining_codes:array<int, string>}
     */
    private function verifyMfaCode(array $user, string $code, bool $consumeBackupCodes): array
    {
        $secret = trim((string) ($user['mfa_secret'] ?? ''));

        if ($secret !== '' && $this->twoFactor->verifyCode($secret, $code)) {
            return [
                'valid' => true,
                'backup_used' => false,
                'remaining_codes' => $user['mfa_backup_codes'] ?? [],
            ];
        }

        if (!$consumeBackupCodes) {
            return [
                'valid' => false,
                'backup_used' => false,
                'remaining_codes' => $user['mfa_backup_codes'] ?? [],
            ];
        }

        $backupCheck = $this->twoFactor->consumeBackupCode($code, $user['mfa_backup_codes'] ?? []);

        return [
            'valid' => $backupCheck['matched'],
            'backup_used' => $backupCheck['matched'],
            'remaining_codes' => $backupCheck['remaining_codes'],
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function storeUser(array $user): void
    {
        $this->rotateSession();
        $_SESSION['auth_user'] = [
            'id' => $user['id'] ?? null,
            'display_name' => $user['display_name'] ?? 'Account',
            'email' => $user['email'] ?? null,
            'role' => $user['role'] ?? 'member',
            'status' => $user['status'] ?? 'active',
            'account_tier' => $user['account_tier'] ?? 'free',
            'stripe_customer_id' => $user['stripe_customer_id'] ?? null,
            'stripe_subscription_id' => $user['stripe_subscription_id'] ?? null,
            'stripe_subscription_status' => $user['stripe_subscription_status'] ?? null,
            'mfa_enabled' => (int) ($user['mfa_enabled'] ?? 0),
        ];
    }

    private function rotateSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private function clearLoginRateLimits(string $email): void
    {
        $this->rateLimiter->clear($this->loginIpKey());
        $this->rateLimiter->clear($this->loginCredentialKey($email));
    }

    private function loginIpKey(): string
    {
        return 'login:ip:' . client_ip_address();
    }

    private function loginCredentialKey(string $email): string
    {
        return 'login:credential:' . hash('sha256', strtolower(trim($email)) . '|' . client_ip_address());
    }

    private function passwordResetIpKey(): string
    {
        return 'password-reset:ip:' . client_ip_address();
    }

    private function passwordResetEmailKey(string $email): string
    {
        return 'password-reset:email:' . hash('sha256', strtolower(trim($email)));
    }

    private function mfaIpKey(): string
    {
        return 'mfa:ip:' . client_ip_address();
    }

    private function mfaUserKey(int $userId): string
    {
        return 'mfa:user:' . $userId . '|ip:' . client_ip_address();
    }

    /**
     * @param array<int, string> $keys
     */
    private function retryAfterSeconds(array $keys, int $windowSeconds): int
    {
        $retryAfter = 0;

        foreach ($keys as $key) {
            $retryAfter = max($retryAfter, $this->rateLimiter->availableIn($key, $windowSeconds));
        }

        return max(60, $retryAfter);
    }

    private function throttleMessage(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return 'Too many attempts. Try again in about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
    }
}
