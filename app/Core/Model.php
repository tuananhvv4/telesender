<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected string $table;
    protected array $fillable = [];

    protected function db(): Database
    {
        return app()->db();
    }

    public function all(string $orderBy = 'id DESC'): array
    {
        return $this->db()->fetchAll("SELECT * FROM {$this->table} ORDER BY {$orderBy}");
    }

    public function allByUser(int $userId, string $orderBy = 'id DESC'): array
    {
        return $this->db()->fetchAll(
            "SELECT * FROM {$this->table} WHERE user_id = :user_id ORDER BY {$orderBy}",
            ['user_id' => $userId]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db()->fetch("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1", ['id' => $id]);
    }

    public function findForUser(int $id, int $userId): ?array
    {
        return $this->db()->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id AND user_id = :user_id LIMIT 1",
            ['id' => $id, 'user_id' => $userId]
        );
    }

    public function create(array $data): int
    {
        $filtered = array_intersect_key($data, array_flip($this->fillable));
        return $this->db()->insert($this->table, $filtered);
    }

    public function updateById(int $id, array $data): bool
    {
        $filtered = array_intersect_key($data, array_flip($this->fillable));
        return $this->db()->update($this->table, $filtered, 'id = :id', ['id' => $id]);
    }

    public function deleteById(int $id): bool
    {
        return $this->db()->execute("DELETE FROM {$this->table} WHERE id = :id", ['id' => $id]);
    }

    public function count(string $where = '1 = 1', array $bindings = []): int
    {
        $row = $this->db()->fetch("SELECT COUNT(*) AS aggregate FROM {$this->table} WHERE {$where}", $bindings);
        return (int) ($row['aggregate'] ?? 0);
    }
}
