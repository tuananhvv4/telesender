<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    public function __construct(private readonly Database $db)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->db->fetch(
            'SELECT * FROM users WHERE email = :email AND status = :status LIMIT 1',
            ['email' => $email, 'status' => 'active']
        );

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        Session::regenerate();
        Session::put('auth_user_id', (int) $user['id']);
        return true;
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function id(): ?int
    {
        $id = Session::get('auth_user_id');
        return $id === null ? null : (int) $id;
    }

    public function user(): ?array
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        return $this->db->fetch('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function logout(): void
    {
        Session::forget('auth_user_id');
        Session::destroy();
    }
}
