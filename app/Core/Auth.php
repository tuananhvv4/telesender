<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\UserAccessService;

class Auth
{
    public function __construct(private readonly Database $db)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        return $this->attemptDetailed($email, $password)['ok'];
    }

    public function attemptDetailed(string $email, string $password): array
    {
        $user = $this->db->fetch(
            'SELECT * FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return [
                'ok' => false,
                'reason' => 'invalid',
                'user' => null,
            ];
        }

        if ((string) ($user['status'] ?? 'inactive') !== 'active') {
            return [
                'ok' => false,
                'reason' => 'inactive',
                'user' => $user,
            ];
        }

        Session::regenerate();
        Session::put('auth_user_id', (int) $user['id']);

        return [
            'ok' => true,
            'reason' => null,
            'user' => $user,
        ];
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

    public function access(): UserAccessService
    {
        return new UserAccessService($this->db);
    }

    public function logout(): void
    {
        Session::forget('auth_user_id');
        Session::destroy();
    }
}
