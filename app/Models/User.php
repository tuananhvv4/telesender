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
        'subscription_expires_at',
        'max_telegram_accounts',
        'max_schedule_jobs',
        'internal_note',
        'created_at',
        'updated_at',
    ];

    public function findByEmail(string $email): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM users WHERE email = :email LIMIT 1',
            ['email' => $email]
        );
    }

    public function paginateAdmins(int $page = 1, int $perPage = 20, string $query = ''): array
    {
        return $this->paginateAdminsWithOrder($page, $perPage, $query, 'u.created_at DESC, u.id DESC');
    }

    public function paginateAdminsBySubscription(int $page = 1, int $perPage = 20, string $query = ''): array
    {
        return $this->paginateAdminsWithOrder(
            $page,
            $perPage,
            $query,
            'u.subscription_expires_at IS NULL ASC, u.subscription_expires_at ASC, u.id DESC'
        );
    }

    private function paginateAdminsWithOrder(int $page, int $perPage, string $query, string $orderBy): array
    {
        $bindings = ['role' => 'admin'];
        $searchSql = '';
        $query = trim($query);

        if ($query !== '') {
            $bindings['search'] = '%' . $query . '%';
            $searchSql = ' AND (
                u.name LIKE :search
                OR u.email LIKE :search
                OR u.status LIKE :search
            )';
        }

        return $this->paginateQuery(
            'SELECT COUNT(*) AS aggregate
             FROM users u
             WHERE u.role = :role' . $searchSql,
            'SELECT u.*,
                    (SELECT COUNT(*) FROM telegram_accounts ta WHERE ta.user_id = u.id) AS telegram_accounts_total,
                    (SELECT COUNT(*) FROM telegram_accounts ta WHERE ta.user_id = u.id AND ta.is_active = 1) AS telegram_accounts_active,
                    (SELECT COUNT(*) FROM telegram_groups tg WHERE tg.user_id = u.id) AS groups_total,
                    (SELECT COUNT(*) FROM telegram_groups tg WHERE tg.user_id = u.id AND tg.is_active = 1) AS groups_active,
                    (SELECT COUNT(*) FROM schedule_jobs sj WHERE sj.user_id = u.id) AS schedules_total,
                    (SELECT COUNT(*) FROM schedule_jobs sj WHERE sj.user_id = u.id AND sj.status = \'active\') AS schedules_active,
                    (SELECT COUNT(*) FROM schedule_jobs sj WHERE sj.user_id = u.id AND sj.status = \'paused\') AS schedules_paused,
                    (SELECT COUNT(*) FROM message_templates mt WHERE mt.user_id = u.id) AS templates_total,
                    (SELECT COUNT(*) FROM dispatch_logs dl WHERE dl.user_id = u.id) AS logs_total,
                    (SELECT MAX(dl.sent_at) FROM dispatch_logs dl WHERE dl.user_id = u.id) AS last_dispatch_at
             FROM users u
             WHERE u.role = :role' . $searchSql . '
             ORDER BY ' . $orderBy,
            $bindings,
            $page,
            $perPage
        );
    }

    public function findAdminWithStats(int $userId): ?array
    {
        return $this->db()->fetch(
            'SELECT u.*,
                    (SELECT COUNT(*) FROM telegram_accounts ta WHERE ta.user_id = u.id) AS telegram_accounts_total,
                    (SELECT COUNT(*) FROM telegram_accounts ta WHERE ta.user_id = u.id AND ta.is_active = 1) AS telegram_accounts_active,
                    (SELECT COUNT(*) FROM telegram_groups tg WHERE tg.user_id = u.id) AS groups_total,
                    (SELECT COUNT(*) FROM telegram_groups tg WHERE tg.user_id = u.id AND tg.is_active = 1) AS groups_active,
                    (SELECT COUNT(*) FROM schedule_jobs sj WHERE sj.user_id = u.id) AS schedules_total,
                    (SELECT COUNT(*) FROM schedule_jobs sj WHERE sj.user_id = u.id AND sj.status = \'active\') AS schedules_active,
                    (SELECT COUNT(*) FROM schedule_jobs sj WHERE sj.user_id = u.id AND sj.status = \'paused\') AS schedules_paused,
                    (SELECT COUNT(*) FROM message_templates mt WHERE mt.user_id = u.id) AS templates_total,
                    (SELECT COUNT(*) FROM dispatch_logs dl WHERE dl.user_id = u.id) AS logs_total,
                    (SELECT COUNT(*) FROM dispatch_logs dl WHERE dl.user_id = u.id AND dl.status = \'success\' AND dl.sent_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)) AS logs_success_recent,
                    (SELECT COUNT(*) FROM dispatch_logs dl WHERE dl.user_id = u.id AND dl.status = \'error\' AND dl.sent_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)) AS logs_error_recent,
                    (SELECT MAX(dl.sent_at) FROM dispatch_logs dl WHERE dl.user_id = u.id) AS last_dispatch_at
             FROM users u
             WHERE u.id = :id
               AND u.role = :role
             LIMIT 1',
            [
                'id' => $userId,
                'role' => 'admin',
            ]
        );
    }
}
