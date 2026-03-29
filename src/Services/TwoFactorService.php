<?php

declare(strict_types=1);

namespace App\Services;

final class TwoFactorService
{
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes(max(10, $bytes)));
    }

    public function otpauthUri(string $issuer, string $accountLabel, string $secret): string
    {
        $label = rawurlencode($issuer . ':' . $accountLabel);
        $issuer = rawurlencode($issuer);

        return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . $issuer . '&algorithm=SHA1&digits=6&period=30';
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if ($normalizedCode === '' || strlen($normalizedCode) !== 6) {
            return false;
        }

        $counter = (int) floor(time() / 30);

        for ($offset = -abs($window); $offset <= abs($window); $offset++) {
            if (hash_equals($this->totpCode($secret, $counter + $offset), $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];

        for ($index = 0; $index < max(4, $count); $index++) {
            $chunk = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = substr($chunk, 0, 4) . '-' . substr($chunk, 4, 4);
        }

        return $codes;
    }

    /**
     * @param array<int, string> $codes
     * @return array<int, string>
     */
    public function hashBackupCodes(array $codes): array
    {
        return array_map(
            static fn (string $code): string => hash('sha256', strtoupper(str_replace('-', '', trim($code)))),
            $codes
        );
    }

    /**
     * @param array<int, string> $hashedCodes
     * @return array{matched:bool,remaining_codes:array<int, string>}
     */
    public function consumeBackupCode(string $code, array $hashedCodes): array
    {
        $normalizedCode = strtoupper(str_replace(['-', ' '], '', trim($code)));
        $matched = false;
        $remaining = [];

        foreach ($hashedCodes as $hashedCode) {
            if (!$matched && hash_equals((string) $hashedCode, hash('sha256', $normalizedCode))) {
                $matched = true;
                continue;
            }

            $remaining[] = (string) $hashedCode;
        }

        return [
            'matched' => $matched,
            'remaining_codes' => $remaining,
        ];
    }

    private function totpCode(string $secret, int $counter): string
    {
        $binarySecret = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $truncated, 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($binary) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }

            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($secret) as $character) {
            $position = strpos($alphabet, $character);

            if ($position === false) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
