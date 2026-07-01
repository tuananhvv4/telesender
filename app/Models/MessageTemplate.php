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
}
