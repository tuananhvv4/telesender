<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class MessageTemplate extends Model
{
    protected string $table = 'message_templates';
    protected array $fillable = [
        'user_id',
        'label_id',
        'name',
        'body',
        'parse_mode',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function listForUser(int $userId): array
    {
        return $this->db()->fetchAll(
            'SELECT mt.*, ml.name AS label_name, ml.color AS label_color
             FROM message_templates mt
             LEFT JOIN message_labels ml ON ml.id = mt.label_id
             WHERE mt.user_id = :user_id
             ORDER BY mt.id DESC',
            ['user_id' => $userId]
        );
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 15, string $query = ''): array
    {
        $bindings = ['user_id' => $userId];
        $searchSql = '';
        $query = trim($query);

        if ($query !== '') {
            $bindings['search'] = '%' . $query . '%';
            $searchSql = ' AND (
                mt.name LIKE :search
                OR mt.body LIKE :search
                OR mt.parse_mode LIKE :search
                OR ml.name LIKE :search
                OR ml.slug LIKE :search
            )';
        }

        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM message_templates mt
             LEFT JOIN message_labels ml ON ml.id = mt.label_id
             WHERE mt.user_id = :user_id' . $searchSql,
            'SELECT mt.*, ml.name AS label_name, ml.color AS label_color
             FROM message_templates mt
             LEFT JOIN message_labels ml ON ml.id = mt.label_id
             WHERE mt.user_id = :user_id' . $searchSql . '
             ORDER BY mt.id DESC',
            $bindings,
            $page,
            $perPage
        );
    }

    public function countUsingCustomEmojiToken(int $userId, string $slug): int
    {
        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS aggregate
             FROM message_templates
             WHERE user_id = :user_id
               AND body LIKE :token',
            [
                'user_id' => $userId,
                'token' => '%' . '{{ce:' . $slug . '}}' . '%',
            ]
        );

        return (int) ($row['aggregate'] ?? 0);
    }

    public function replaceCustomEmojiToken(int $userId, string $oldSlug, string $newSlug): bool
    {
        return $this->db()->execute(
            'UPDATE message_templates
             SET body = REPLACE(body, :old_token, :new_token),
                 updated_at = :updated_at
             WHERE user_id = :user_id
               AND body LIKE :search_token',
            [
                'old_token' => '{{ce:' . $oldSlug . '}}',
                'new_token' => '{{ce:' . $newSlug . '}}',
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'user_id' => $userId,
                'search_token' => '%' . '{{ce:' . $oldSlug . '}}' . '%',
            ]
        );
    }
}
