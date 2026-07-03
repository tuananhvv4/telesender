<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Migration;
use PDO;
use RuntimeException;

class MigrationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function migrate(?string $targetVersion = null): array
    {
        $this->ensureTable();
        $migrations = $this->loadMigrations();
        $targetVersion = $this->normalizeTargetVersion($targetVersion, $migrations);
        $executed = $this->executedVersions($migrations);
        $applied = [];

        foreach ($migrations as $migration) {
            if (isset($executed[$migration->version])) {
                continue;
            }

            if ($targetVersion !== null && $this->compareVersions($migration->version, $targetVersion) > 0) {
                continue;
            }

            try {
                $migration->up($this->pdo);
                $this->recordMigration($migration->version, $migration->name);
                $applied[] = $migration->version;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw new RuntimeException(
                    sprintf('Migration %s thất bại: %s', $migration->version, $exception->getMessage()),
                    0,
                    $exception
                );
            }
        }

        return [
            'target_version' => $targetVersion,
            'applied_versions' => $applied,
            'current_version' => $this->currentVersion(),
        ];
    }

    public function currentVersion(): ?string
    {
        $this->ensureTable();
        $migrations = $this->loadMigrations();
        $executed = $this->executedVersions($migrations);
        $currentVersion = null;

        foreach ($migrations as $migration) {
            if (isset($executed[$migration->version])) {
                $currentVersion = $migration->version;
            }
        }

        return $currentVersion;
    }

    public function report(): array
    {
        $this->ensureTable();
        $migrations = $this->loadMigrations();
        $executedRows = $this->pdo->query('SELECT version, name, executed_at FROM migrations ORDER BY executed_at ASC')
            ->fetchAll(PDO::FETCH_ASSOC);

        $executedByVersion = [];

        foreach ($executedRows as $row) {
            $version = (string) ($row['version'] ?? '');

            if ($version === '') {
                continue;
            }

            $executedByVersion[$version] = [
                'name' => (string) ($row['name'] ?? ''),
                'executed_at' => (string) ($row['executed_at'] ?? ''),
            ];
        }

        $items = [];

        foreach ($migrations as $migration) {
            $execution = $executedByVersion[$migration->version] ?? null;

            if ($execution === null && $migration->legacyVersions !== []) {
                foreach ($migration->legacyVersions as $legacyVersion) {
                    $legacyExecution = $executedByVersion[(string) $legacyVersion] ?? null;

                    if ($legacyExecution !== null) {
                        $execution = $legacyExecution;
                        break;
                    }
                }
            }

            $items[] = [
                'version' => $migration->version,
                'name' => $migration->name,
                'legacy_versions' => $migration->legacyVersions,
                'executed' => $execution !== null,
                'executed_at' => $execution['executed_at'] ?? null,
            ];
        }

        return [
            'current_version' => $this->currentVersion(),
            'items' => $items,
        ];
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                version VARCHAR(32) PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                executed_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @param array<int, Migration> $migrations
     * @return array<string, bool>
     */
    private function executedVersions(array $migrations): array
    {
        $rows = $this->pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_ASSOC);
        $versions = [];
        $aliases = $this->versionAliases($migrations);

        foreach ($rows as $row) {
            $rawVersion = (string) ($row['version'] ?? '');

            if ($rawVersion === '') {
                continue;
            }

            $versions[$rawVersion] = true;

            if (isset($aliases[$rawVersion])) {
                $versions[$aliases[$rawVersion]] = true;
            }
        }

        return $versions;
    }

    private function recordMigration(string $version, string $name): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO migrations (version, name, executed_at) VALUES (:version, :name, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'version' => $version,
            'name' => $name,
        ]);
    }

    /**
     * @return array<int, Migration>
     */
    private function loadMigrations(): array
    {
        $files = glob(base_path('database/migrations/*.php')) ?: [];
        sort($files);

        $migrations = [];

        foreach ($files as $file) {
            $migration = require $file;

            if (!$migration instanceof Migration) {
                throw new RuntimeException('Migration file không trả về App\Core\Migration: ' . $file);
            }

            $migrations[] = $migration;
        }

        usort(
            $migrations,
            fn (Migration $left, Migration $right): int => $this->compareVersions($left->version, $right->version)
        );

        return $migrations;
    }

    /**
     * @param array<int, Migration> $migrations
     * @return array<string, string>
     */
    private function versionAliases(array $migrations): array
    {
        $aliases = [];

        foreach ($migrations as $migration) {
            $aliases[$migration->version] = $migration->version;

            foreach ($migration->legacyVersions as $legacyVersion) {
                $aliases[(string) $legacyVersion] = $migration->version;
            }
        }

        return $aliases;
    }

    /**
     * @param array<int, Migration> $migrations
     */
    private function normalizeTargetVersion(?string $targetVersion, array $migrations): ?string
    {
        if ($targetVersion === null || $targetVersion === '') {
            return null;
        }

        $aliases = $this->versionAliases($migrations);

        return $aliases[$targetVersion] ?? $targetVersion;
    }

    private function compareVersions(string $left, string $right): int
    {
        if (ctype_digit($left) && ctype_digit($right)) {
            return (int) $left <=> (int) $right;
        }

        return strcmp($left, $right);
    }
}
