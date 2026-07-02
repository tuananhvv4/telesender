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

    protected function paginateQuery(
        string $countSql,
        string $dataSql,
        array $bindings,
        int $page = 1,
        int $perPage = 20
    ): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $countRow = $this->db()->fetch($countSql, $bindings);
        $total = (int) ($countRow['aggregate'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));

        if ($total > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        $offset = max(0, ($page - 1) * $perPage);
        $items = $total === 0
            ? []
            : $this->db()->fetchAll($dataSql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $bindings);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total === 0 ? 0 : ($offset + 1),
                'to' => $total === 0 ? 0 : min($total, $offset + count($items)),
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $totalPages ? $page + 1 : null,
            ],
        ];
    }
}
