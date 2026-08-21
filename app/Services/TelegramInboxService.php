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

    public function dialogs(int $accountId, string $query = '', string $type = 'all'): array
    {
        $account = $this->accountOrFail($accountId);
        $bindings = ['account_id' => $accountId];
        $searchSql = '';
        $typeSql = match ($type) {
            'group' => " AND peer_type IN ('group', 'supergroup', 'channel')",
            'private' => " AND peer_type = 'private' AND is_bot = 0",
            'bot' => ' AND is_bot = 1',
            default => '',
        };
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
             WHERE telegram_account_id = :account_id' . $typeSql . $searchSql . '
             ORDER BY last_message_at IS NULL ASC, last_message_at DESC, id DESC
             LIMIT 200',
            $bindings
        );

        return ['account' => $account, 'items' => $items, 'sync' => $this->syncStatus($accountId, null)];
    }

    public function topics(int $dialogId): array
    {
        $dialog = $this->dialogOrFail($dialogId);
        $items = (bool) ($dialog['is_forum'] ?? false)
            ? $this->db->fetchAll(
                'SELECT * FROM telegram_inbox_topics
                 WHERE telegram_inbox_dialog_id = :dialog_id
                 ORDER BY topic_id = 1 DESC, title ASC, topic_id ASC',
                ['dialog_id' => $dialogId]
            )
            : [];

        return [
            'dialog' => $dialog,
            'items' => $items,
            'sync' => $this->syncStatus((int) $dialog['telegram_account_id'], $dialogId),
        ];
    }

    public function messages(
        int $dialogId,
        ?int $beforeMessageId = null,
        int $limit = 40,
        ?int $topicId = null
    ): array
    {
        $dialog = $this->dialogOrFail($dialogId);
        $topic = $topicId !== null && $topicId > 0 ? $this->topicOrFail($dialogId, $topicId) : null;
        $limit = max(1, min(100, $limit));
        $bindings = ['dialog_id' => $dialogId];
        $beforeSql = '';
        $topicSql = '';
        if ($topic !== null) {
            $bindings['topic_id'] = (int) $topic['topic_id'];
            $topicSql = ' AND m.topic_id = :topic_id';
        }
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
             WHERE m.telegram_inbox_dialog_id = :dialog_id' . $topicSql . $beforeSql . '
             ORDER BY m.telegram_message_id DESC
             LIMIT ' . $limit,
            $bindings
        );
        $items = array_reverse($items);
        $oldest = $items !== [] ? (int) $items[0]['telegram_message_id'] : null;

        return [
            'dialog' => $dialog,
            'topic' => $topic,
            'items' => $items,
            'oldest_message_id' => $oldest,
            'history_complete' => (bool) (($topic ?? $dialog)['history_complete'] ?? false),
            'has_more_cached' => $oldest !== null && $this->hasOlderCached(
                $dialogId,
                $oldest,
                $topic !== null ? (int) $topic['topic_id'] : null
            ),
            'sync' => $this->syncStatus((int) $dialog['telegram_account_id'], $dialogId),
        ];
    }

    public function enqueueAccountSync(int $accountId): string
    {
        $account = $this->accountOrFail($accountId);
        $jobKey = 'dialogs:' . $accountId;
        $this->upsertJob(
            $jobKey,
            (int) $account['user_id'],
            $accountId,
            null,
            'dialogs_refresh',
            50,
            null
        );

        return $jobKey;
    }

    public function enqueueDialogSync(int $dialogId, ?int $topicId = null): string
    {
        $dialog = $this->dialogOrFail($dialogId);
        $topicId = $topicId !== null && $topicId > 0
            ? (int) $this->topicOrFail($dialogId, $topicId)['topic_id']
            : null;
        $this->db->update('telegram_inbox_dialogs', [
            'last_opened_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $dialogId]);

        $jobKey = 'history-refresh:' . $dialogId . ($topicId !== null ? ':topic:' . $topicId : '');
        $this->upsertJob(
            $jobKey,
            (int) $dialog['user_id'],
            (int) $dialog['telegram_account_id'],
            $dialogId,
            'history_refresh',
            100,
            $topicId !== null ? ['topic_id' => $topicId] : null
        );

        return $jobKey;
    }

    public function enqueueOlder(int $dialogId, int $beforeMessageId, ?int $topicId = null): ?string
    {
        $dialog = $this->dialogOrFail($dialogId);
        $topic = $topicId !== null && $topicId > 0 ? $this->topicOrFail($dialogId, $topicId) : null;
        if ((bool) (($topic ?? $dialog)['history_complete'] ?? false)) {
            return null;
        }

        $beforeMessageId = max(0, $beforeMessageId);
        $jobKey = 'history-backfill:' . $dialogId
            . ($topic !== null ? ':topic:' . (int) $topic['topic_id'] : '')
            . ':' . $beforeMessageId;
        $this->upsertJob(
            $jobKey,
            (int) $dialog['user_id'],
            (int) $dialog['telegram_account_id'],
            $dialogId,
            'history_backfill',
            80,
            array_filter([
                'before_message_id' => $beforeMessageId,
                'topic_id' => $topic !== null ? (int) $topic['topic_id'] : null,
            ], static fn (mixed $value): bool => $value !== null)
        );

        return $jobKey;
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

    public function syncJobStatus(string $jobKey): array
    {
        return $this->db->fetch(
            'SELECT status, attempts, next_attempt_at, locked_until, last_error_code,
                    last_error_message, completed_at, updated_at
             FROM telegram_inbox_sync_jobs
             WHERE job_key = :job_key
             LIMIT 1',
            ['job_key' => $jobKey]
        ) ?? ['status' => 'not_found'];
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

    private function topicOrFail(int $dialogId, int $topicId): array
    {
        $topic = $this->db->fetch(
            'SELECT * FROM telegram_inbox_topics
             WHERE telegram_inbox_dialog_id = :dialog_id AND topic_id = :topic_id
             LIMIT 1',
            ['dialog_id' => $dialogId, 'topic_id' => $topicId]
        );
        if ($topic === null) {
            throw new HttpException(404, 'Không tìm thấy topic của nhóm.');
        }

        return $topic;
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

    private function hasOlderCached(int $dialogId, int $oldestMessageId, ?int $topicId = null): bool
    {
        $bindings = ['dialog_id' => $dialogId, 'message_id' => $oldestMessageId];
        $topicSql = '';
        if ($topicId !== null) {
            $bindings['topic_id'] = $topicId;
            $topicSql = ' AND topic_id = :topic_id';
        }

        return $this->db->fetch(
            'SELECT id FROM telegram_inbox_messages
             WHERE telegram_inbox_dialog_id = :dialog_id
               AND telegram_message_id < :message_id' . $topicSql . '
             LIMIT 1',
            $bindings
        ) !== null;
    }
}
