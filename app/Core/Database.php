<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use PDO;
use PDOStatement;

class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement;
    }

    public function fetch(string $sql, array $bindings = []): ?array
    {
        $result = $this->query($sql, $bindings)->fetch();
        return $result === false ? null : $result;
    }

    public function fetchAll(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll();
    }

    public function execute(string $sql, array $bindings = []): bool
    {
        return $this->query($sql, $bindings) !== false;
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $bindings = []): bool
    {
        $sets = [];
        foreach ($data as $column => $value) {
            $sets[] = sprintf('%s = :set_%s', $column, $column);
            $bindings['set_' . $column] = $value;
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return $this->execute($sql, $bindings);
    }

    public function transaction(Closure $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }
}
