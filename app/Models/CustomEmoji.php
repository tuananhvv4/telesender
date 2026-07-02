<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class CustomEmoji extends Model
{
    protected string $table = 'custom_emojis';
    protected array $fillable = [
        'user_id',
        'name',
        'slug',
        'emoji_identifier',
        'fallback_emoji',
        'keywords',
        'notes',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function listForUser(int $userId): array
    {
        return $this->db()->fetchAll(
            'SELECT ce.*,
                    (
                        SELECT COUNT(*)
                        FROM message_templates mt
                        WHERE mt.user_id = ce.user_id
                          AND mt.body LIKE CONCAT(\'%\', \'{{ce:\', ce.slug, \'}}\', \'%\')
                    ) AS usage_count
             FROM custom_emojis ce
             WHERE ce.user_id = :user_id
             ORDER BY is_active DESC, name ASC, id DESC',
            ['user_id' => $userId]
        );
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 18): array
    {
        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM custom_emojis
             WHERE user_id = :user_id',
            'SELECT ce.*,
                    (
                        SELECT COUNT(*)
                        FROM message_templates mt
                        WHERE mt.user_id = ce.user_id
                          AND mt.body LIKE CONCAT(\'%\', \'{{ce:\', ce.slug, \'}}\', \'%\')
                    ) AS usage_count
             FROM custom_emojis ce
             WHERE ce.user_id = :user_id
             ORDER BY is_active DESC, name ASC, id DESC',
            ['user_id' => $userId],
            $page,
            $perPage
        );
    }

    public function activeForUser(int $userId): array
    {
        return $this->db()->fetchAll(
            'SELECT *
             FROM custom_emojis
             WHERE user_id = :user_id
               AND is_active = 1
             ORDER BY name ASC, id DESC',
            ['user_id' => $userId]
        );
    }

    public function findBySlugForUser(string $slug, int $userId): ?array
    {
        return $this->db()->fetch(
            'SELECT *
             FROM custom_emojis
             WHERE user_id = :user_id
               AND slug = :slug
             LIMIT 1',
            [
                'user_id' => $userId,
                'slug' => $slug,
            ]
        );
    }
}
