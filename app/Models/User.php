<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'status',
        'created_at',
        'updated_at',
    ];
}
