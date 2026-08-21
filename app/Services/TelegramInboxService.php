<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;

class TelegramInboxService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function admins(): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, email
             FROM users
             WHERE role = \'admin\'
             ORDER BY name ASC, id ASC'
        );
    }

    public function accountsForAdmin(int $userId): array
    {
        $this->adminOrFail($userId);

        return $this->db->fetchAll(
            'SELECT id, user_id, name, phone_number, session_status, is_active, tg_username, last_connected_at
             FROM telegram_accounts
             WHERE user_id = :user_id
               AND session_status = \'active\'
             ORDER BY is_active DESC, name ASC, id ASC',
            ['user_id' => $userId]
        );
    }

    public function dialogs(int $accountId, string $query = ''): array
    {
        $account = $this->accountOrFail($accountId);
        $bindings = ['account_id' => $accountId];
        $searchSql = '';
        $query = trim($query);
        if ($query !== '') {
            $bindings['title_search'] = '%' . $query . '%';
            $bindings['username_search'] = '%' . $query . '%';
            $bindings['message_search'] = '%' . $query . '%';
            $searchSql = ' AND (title LIKE :title_search OR username LIKE :username_search OR last_message_text LIKE :message_search)';
        }

        $items = $this->db->fetchAll(
            'SELECT *
             FROM telegram_inbox_dialogs
             WHERE telegram_account_id = :account_id' . $searchSql . '
             ORDER BY last_message_at IS NULL ASC, last_message_at DESC, id DESC
             LIMIT 200',
            $bindings
        );

        return ['account' => $account, 'items' => $items, 'sync' => $this->syncStatus($accountId, null)];
    }

    public function messages(int $dialogId, ?int $beforeMessageId = null, int $limit = 40): array
    {
        $dialog = $this->dialogOrFail($dialogId);
        $limit = max(1, min(100, $limit));
        $bindings = ['dialog_id' => $dialogId];
        $beforeSql = '';
        if ($beforeMessageId !== null && $beforeMessageId > 0) {
            $bindings['before_message_id'] = $beforeMessageId;
            $beforeSql = ' AND m.telegram_message_id < :before_message_id';
        }

        $items = $this->db->fetchAll(
            'SELECT m.*,
                    reply.sender_name AS reply_sender_name,
                    reply.message_text AS reply_message_text
             FROM telegram_inbox_messages m
             LEFT JOIN telegram_inbox_messages reply
               ON reply.telegram_inbox_dialog_id = m.telegram_inbox_dialog_id
              AND reply.telegram_message_id = m.reply_to_message_id
             WHERE m.telegram_inbox_dialog_id = :dialog_id' . $beforeSql . '
             ORDER BY m.telegram_message_id DESC
             LIMIT ' . $limit,
            $bindings
        );
        $items = array_reverse($items);
        $oldest = $items !== [] ? (int) $items[0]['telegram_message_id'] : null;

        return [
            'dialog' => $dialog,
            'items' => $items,
            'oldest_message_id' => $oldest,
            'history_complete' => (bool) ($dialog['history_complete'] ?? false),
            'has_more_cached' => $oldest !== null && $this->hasOlderCached($dialogId, $oldest),
            'sync' => $this->syncStatus((int) $dialog['telegram_account_id'], $dialogId),
        ];
    }

    public function enqueueAccountSync(int $accountId): void
    {
        $account = $this->accountOrFail($accountId);
        $this->upsertJob(
            'dialogs:' . $accountId,
            (int) $account['user_id'],
            $accountId,
            null,
            'dialogs_refresh',
            50,
            null
        );
    }

    public function enqueueDialogSync(int $dialogId): void
    {
        $dialog = $this->dialogOrFail($dialogId);
        $this->db->update('telegram_inbox_dialogs', [
            'last_opened_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $dialogId]);

        $this->upsertJob(
            'history-refresh:' . $dialogId,
            (int) $dialog['user_id'],
            (int) $dialog['telegram_account_id'],
            $dialogId,
            'history_refresh',
            100,
            null
        );
    }

    public function enqueueOlder(int $dialogId, int $beforeMessageId): void
    {
        $dialog = $this->dialogOrFail($dialogId);
        if ((bool) ($dialog['history_complete'] ?? false)) {
            return;
        }

        $beforeMessageId = max(0, $beforeMessageId);
        $this->upsertJob(
            'history-backfill:' . $dialogId . ':' . $beforeMessageId,
            (int) $dialog['user_id'],
            (int) $dialog['telegram_account_id'],
            $dialogId,
            'history_backfill',
            80,
            ['before_message_id' => $beforeMessageId]
        );
    }

    public function mediaMessageOrFail(int $messageId): array
    {
        $row = $this->db->fetch(
            'SELECT m.*, d.peer_identifier, d.peer_type, d.title AS dialog_title,
                    ta.session_name, ta.session_status, ta.name AS account_name,
                    ta.phone_number, ta.tg_username, ta.user_id AS account_user_id,
                    u.role AS owner_role
             FROM telegram_inbox_messages m
             INNER JOIN telegram_inbox_dialogs d ON d.id = m.telegram_inbox_dialog_id
             INNER JOIN telegram_accounts ta ON ta.id = m.telegram_account_id
             INNER JOIN users u ON u.id = ta.user_id
             WHERE m.id = :id
               AND u.role = \'admin\'
             LIMIT 1',
            ['id' => $messageId]
        );

        if ($row === null || empty($row['media_source_json'])) {
            throw new HttpException(404, 'Media không tồn tại hoặc chưa sẵn sàng.');
        }

        return $row;
    }

    public function accountOrFail(int $accountId): array
    {
        $account = $this->db->fetch(
            'SELECT ta.*, u.name AS owner_name, u.email AS owner_email, u.role AS owner_role
             FROM telegram_accounts ta
             INNER JOIN users u ON u.id = ta.user_id
             WHERE ta.id = :id
               AND u.role = \'admin\'
             LIMIT 1',
            ['id' => $accountId]
        );

        if ($account === null) {
            throw new HttpException(404, 'Không tìm thấy Telegram account của admin con.');
        }

        return $account;
    }

    public function dialogOrFail(int $dialogId): array
    {
        $dialog = $this->db->fetch(
            'SELECT d.*, ta.session_status, ta.name AS account_name, u.role AS owner_role
             FROM telegram_inbox_dialogs d
             INNER JOIN telegram_accounts ta ON ta.id = d.telegram_account_id
             INNER JOIN users u ON u.id = ta.user_id
             WHERE d.id = :id
               AND u.role = \'admin\'
             LIMIT 1',
            ['id' => $dialogId]
        );

        if ($dialog === null) {
            throw new HttpException(404, 'Không tìm thấy hội thoại.');
        }

        return $dialog;
    }

    private function adminOrFail(int $userId): array
    {
        $admin = $this->db->fetch(
            'SELECT id, name, email FROM users WHERE id = :id AND role = \'admin\' LIMIT 1',
            ['id' => $userId]
        );
        if ($admin === null) {
            throw new HttpException(404, 'Không tìm thấy admin con.');
        }
        return $admin;
    }

    private function upsertJob(
        string $jobKey,
        int $userId,
        int $accountId,
        ?int $dialogId,
        string $type,
        int $priority,
        ?array $cursor
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->query(
            'INSERT INTO telegram_inbox_sync_jobs (
                job_key, user_id, telegram_account_id, telegram_inbox_dialog_id,
                job_type, priority, status, cursor_json, attempts, next_attempt_at,
                locked_until, lock_token, last_error_code, last_error_message,
                started_at, completed_at, created_at, updated_at
             ) VALUES (
                :job_key, :user_id, :account_id, :dialog_id,
                :job_type, :priority, \'pending\', :cursor_json, 0, NULL,
                NULL, NULL, NULL, NULL, NULL, NULL, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                priority = GREATEST(priority, VALUES(priority)),
                status = IF(status = \'running\' AND locked_until >= UTC_TIMESTAMP(), status, \'pending\'),
                cursor_json = COALESCE(VALUES(cursor_json), cursor_json),
                next_attempt_at = NULL,
                completed_at = NULL,
                last_error_code = NULL,
                last_error_message = NULL,
                updated_at = VALUES(updated_at)',
            [
                'job_key' => $jobKey,
                'user_id' => $userId,
                'account_id' => $accountId,
                'dialog_id' => $dialogId,
                'job_type' => $type,
                'priority' => $priority,
                'cursor_json' => $cursor !== null ? json_encode($cursor, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function syncStatus(int $accountId, ?int $dialogId): array
    {
        $bindings = ['account_id' => $accountId];
        $dialogSql = '';
        if ($dialogId !== null) {
            $bindings['dialog_id'] = $dialogId;
            $dialogSql = ' AND telegram_inbox_dialog_id = :dialog_id';
        } else {
            $dialogSql = ' AND telegram_inbox_dialog_id IS NULL';
        }

        $job = $this->db->fetch(
            'SELECT status, next_attempt_at, locked_until, last_error_code, last_error_message, updated_at
             FROM telegram_inbox_sync_jobs
             WHERE telegram_account_id = :account_id' . $dialogSql . '
             ORDER BY CASE status
                        WHEN \'running\' THEN 0
                        WHEN \'pending\' THEN 1
                        WHEN \'retry\' THEN 2
                        WHEN \'failed\' THEN 3
                        ELSE 4
                      END,
                      updated_at DESC, id DESC
             LIMIT 1',
            $bindings
        );

        return $job ?? ['status' => 'not_synced'];
    }

    private function hasOlderCached(int $dialogId, int $oldestMessageId): bool
    {
        return $this->db->fetch(
            'SELECT id FROM telegram_inbox_messages
             WHERE telegram_inbox_dialog_id = :dialog_id
               AND telegram_message_id < :message_id
             LIMIT 1',
            ['dialog_id' => $dialogId, 'message_id' => $oldestMessageId]
        ) !== null;
    }
}
