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
}
