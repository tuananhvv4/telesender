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
        $executed = $this->executedVersions();
        $applied = [];

        foreach ($this->loadMigrations() as $migration) {
            if (isset($executed[$migration->version])) {
                continue;
            }

            if ($targetVersion !== null && strcmp($migration->version, $targetVersion) > 0) {
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
        $row = $this->pdo->query('SELECT version FROM migrations ORDER BY version DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        return $row['version'] ?? null;
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

    private function executedVersions(): array
    {
        $rows = $this->pdo->query('SELECT version FROM migrations')->fetchAll(PDO::FETCH_ASSOC);
        $versions = [];

        foreach ($rows as $row) {
            $versions[$row['version']] = true;
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

        return $migrations;
    }
}
