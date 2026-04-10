<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use Throwable;

final class DatabaseVersionService
{
    /**
     * @return array{
     *   db_connected:bool,
     *   tracking_ready:bool,
     *   code_version:string,
     *   db_version:string,
     *   migration_count:int,
     *   latest_migration:?array<string,mixed>,
     *   upgrade_required:bool,
     *   pending_versions:array<int,string>,
     *   available_upgrade_versions:array<int,string>,
     *   message:string
     * }
     */
    public function status(): array
    {
        $codeVersion = $this->codeVersion();
        $status = [
            'db_connected' => false,
            'tracking_ready' => false,
            'code_version' => $codeVersion,
            'db_version' => '',
            'migration_count' => 0,
            'latest_migration' => null,
            'upgrade_required' => false,
            'pending_versions' => [],
            'available_upgrade_versions' => $this->availableUpgradeVersions(),
            'message' => 'Database unavailable.',
        ];

        $pdo = Database::connection();

        if (!$pdo instanceof PDO) {
            return $status;
        }

        $status['db_connected'] = true;

        if (!$this->tableExists($pdo, 'schema_migrations')) {
            $versionLabel = $codeVersion !== '' ? $codeVersion : '<version>';
            $status['message'] = 'Version tracking table missing. Apply the upgrade SQL from updates/' . $versionLabel . '/sql for this install.';
            return $status;
        }

        $status['tracking_ready'] = true;

        try {
            $rows = $pdo->query(
                'SELECT id, version, filename, notes, applied_at
                 FROM schema_migrations
                 ORDER BY applied_at DESC, id DESC'
            )->fetchAll() ?: [];
        } catch (Throwable) {
            $status['message'] = 'Could not read schema migration history.';
            return $status;
        }

        if ($rows === []) {
            $status['message'] = 'Version tracking is enabled, but no migration entries were recorded yet.';
            return $status;
        }

        $status['migration_count'] = count($rows);
        $status['latest_migration'] = $rows[0];
        $status['db_version'] = $this->highestVersion(array_map(
            static fn (array $row): string => (string) ($row['version'] ?? ''),
            $rows
        ));

        $pendingVersions = $this->pendingVersions($status['db_version'], $codeVersion, $status['available_upgrade_versions']);
        $status['pending_versions'] = $pendingVersions;
        $status['upgrade_required'] = $pendingVersions !== [];
        $status['message'] = $status['upgrade_required']
            ? 'Database version is behind the app version. Apply the pending upgrade SQL files.'
            : 'Database schema tracking matches the current app version.';

        return $status;
    }

    /**
     * @return array<int, string>
     */
    private function availableUpgradeVersions(): array
    {
        $directories = glob(ROOT_PATH . '/updates/*', GLOB_ONLYDIR) ?: [];
        $versions = [];

        foreach ($directories as $directory) {
            $version = basename($directory);

            if (preg_match('/^\d+\.\d+\.\d+$/', $version) === 1 && is_dir($directory . '/sql')) {
                $versions[] = $version;
            }
        }

        usort($versions, 'version_compare');

        return array_values(array_unique($versions));
    }

    /**
     * @param array<int, string> $available
     * @return array<int, string>
     */
    private function pendingVersions(string $dbVersion, string $codeVersion, array $available): array
    {
        if ($codeVersion === '') {
            return [];
        }

        return array_values(array_filter(
            $available,
            static function (string $version) use ($dbVersion, $codeVersion): bool {
                if (version_compare($version, $codeVersion, '>')) {
                    return false;
                }

                if ($dbVersion === '') {
                    return true;
                }

                return version_compare($version, $dbVersion, '>');
            }
        ));
    }

    /**
     * @param array<int, string> $versions
     */
    private function highestVersion(array $versions): string
    {
        $normalized = array_values(array_filter(array_map(
            fn (string $version): string => $this->extractVersion($version),
            $versions
        )));

        if ($normalized === []) {
            return '';
        }

        usort($normalized, 'version_compare');

        return (string) end($normalized);
    }

    private function codeVersion(): string
    {
        $configured = $this->extractVersion((string) config('updates.current_version', ''));

        if ($configured !== '') {
            return $configured;
        }

        $packagePath = ROOT_PATH . '/package.json';

        if (is_file($packagePath)) {
            $contents = @file_get_contents($packagePath);
            $decoded = is_string($contents) ? json_decode($contents, true) : null;

            if (is_array($decoded) && !empty($decoded['version']) && is_string($decoded['version'])) {
                return $this->extractVersion((string) $decoded['version']);
            }
        }

        return '';
    }

    private function extractVersion(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/v?(\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?)/', $value, $matches) === 1) {
            return (string) ($matches[1] ?? '');
        }

        return '';
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :table_name'
            );
            $statement->execute(['table_name' => $table]);
            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
