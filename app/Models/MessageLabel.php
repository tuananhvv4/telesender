<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class MessageLabel extends Model
{
    protected string $table = 'message_labels';
    protected array $fillable = [
        'user_id',
        'name',
        'slug',
        'color',
        'created_at',
        'updated_at',
    ];

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM message_labels
             WHERE user_id = :user_id',
            'SELECT *
             FROM message_labels
             WHERE user_id = :user_id
             ORDER BY id DESC',
            ['user_id' => $userId],
            $page,
            $perPage
        );
    }
}
