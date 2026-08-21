<?php

declare(strict_types=1);

use App\Core\Application;
use App\Services\TelegramAccountLockService;
use App\Services\TelegramInboxService;
use App\Services\TelegramInboxSyncService;
use App\Services\TelegramMessageNormalizer;
use App\Services\TelegramService;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$app = Application::boot(base_path());
$db = $app->db();
$suffix = bin2hex(random_bytes(5));
$userId = null;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $now = gmdate('Y-m-d H:i:s');
    $userId = $db->insert('users', [
        'name' => 'Inbox smoke test',
        'email' => 'inbox-' . $suffix . '@example.test',
        'password_hash' => password_hash($suffix, PASSWORD_DEFAULT),
        'role' => 'admin',
        'status' => 'active',
        'subscription_expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        'max_telegram_accounts' => null,
        'max_schedule_jobs' => null,
        'can_override_safety_limits' => 0,
        'internal_note' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $accountId = $db->insert('telegram_accounts', [
        'user_id' => $userId,
        'name' => 'Inbox account ' . $suffix,
        'phone_number' => '+84000000001',
        'session_name' => 'inbox_' . $suffix,
        'session_status' => 'active',
        'is_active' => 1,
        'safety_mode' => 'safe',
        'meta_json' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $groupId = $db->insert('telegram_groups', [
        'user_id' => $userId,
        'telegram_account_id' => $accountId,
        'title' => 'Expired admin lock test',
        'peer_identifier' => '-100000000099',
        'topic_id' => null,
        'topic_title' => null,
        'notes' => null,
        'is_active' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $templateId = $db->insert('message_templates', [
        'user_id' => $userId,
        'label_id' => null,
        'name' => 'Expired admin lock test',
        'body' => 'Test',
        'parse_mode' => 'HTML',
        'is_active' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $scheduleId = $db->insert('schedule_jobs', [
        'user_id' => $userId,
        'telegram_account_id' => $accountId,
        'telegram_group_id' => $groupId,
        'message_template_id' => $templateId,
        'timezone' => 'UTC',
        'cron_expression' => '* * * * *',
        'schedule_type' => 'advanced',
        'schedule_config_json' => null,
        'next_run_at' => gmdate('Y-m-d H:i:s', time() + 60),
        'last_run_at' => null,
        'last_error' => null,
        'status' => 'active',
        'dispatch_locked_until' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $db->insert('schedule_job_groups', [
        'schedule_job_id' => $scheduleId,
        'telegram_group_id' => $groupId,
        'sort_order' => 0,
        'created_at' => $now,
    ]);
    $db->update('users', [
        'subscription_expires_at' => gmdate('Y-m-d H:i:s', time() - 60),
    ], 'id = :id', ['id' => $userId]);

    $expiredAdminLocks = new TelegramAccountLockService($db);
    $expiredAdminToken = $expiredAdminLocks->acquireInbox($accountId, 60, 180);
    $assert($expiredAdminToken !== null, 'An expired admin schedule must not block super-admin inbox sync.');
    $expiredAdminLocks->release($accountId, (string) $expiredAdminToken);

    $peerIdentifier = new ReflectionMethod(TelegramService::class, 'inboxPeerIdentifier');
    $telegramService = new TelegramService();
    $assert($peerIdentifier->invoke($telegramService, ['_' => 'peerUser', 'user_id' => 101]) === '101', 'User peer IDs must have a direct fallback.');
    $assert($peerIdentifier->invoke($telegramService, ['_' => 'peerChat', 'chat_id' => 202]) === '-202', 'Group peer IDs must have a direct fallback.');
    $assert($peerIdentifier->invoke($telegramService, ['_' => 'peerChannel', 'channel_id' => 303]) === '-100303', 'Channel peer IDs must have a direct fallback.');
    $assert($peerIdentifier->invoke($telegramService, ['_' => 'inputPeerChannel', 'channel_id' => 404]) === '-100404', 'Input peer constructors must be supported.');
    $assert($peerIdentifier->invoke($telegramService, '-100505') === '-100505', 'Numeric peer identifiers must pass through unchanged.');

    $telegram = new class extends TelegramService {
        public array $historyOffsets = [];

        public function getDialogsPage(array $account, int $limit = 100): array
        {
            return [
                'dialogs' => [
                    [
                        'peer' => ['_' => 'peerUser', 'user_id' => 101],
                        'top_message' => 10,
                        'unread_count' => 2,
                        '_peer_identifier' => '101',
                    ],
                    [
                        'peer' => ['_' => 'peerChat', 'chat_id' => 202],
                        'top_message' => 20,
                        'unread_count' => 0,
                        '_peer_identifier' => '-202',
                    ],
                    [
                        'peer' => ['_' => 'peerChannel', 'channel_id' => 303],
                        'top_message' => 30,
                        'unread_count' => 0,
                        '_peer_identifier' => '-100303',
                    ],
                ],
                'messages' => [[
                    '_' => 'message',
                    'id' => 10,
                    'peer_id' => ['_' => 'peerUser', 'user_id' => 101],
                    'from_id' => ['_' => 'peerUser', 'user_id' => 101],
                    'date' => time() - 10,
                    'message' => 'Tin nhắn mới nhất',
                ]],
                'users' => [[
                    '_' => 'user',
                    'id' => 101,
                    'first_name' => 'Nguyen',
                    'last_name' => 'Van A',
                    'username' => 'nguyenvana',
                ]],
                'chats' => [
                    ['_' => 'chat', 'id' => 202, 'title' => 'Nhom thu nghiem'],
                    ['_' => 'channel', 'id' => 303, 'title' => 'Kenh thu nghiem', 'broadcast' => true],
                ],
            ];
        }

        public function getHistoryPage(array $account, string $peer, int $offsetId = 0, int $limit = 40): array
        {
            $this->historyOffsets[] = $offsetId;
            $ids = $offsetId > 0 ? [8] : [10, 9];
            $messages = [];
            foreach ($ids as $id) {
                $messages[] = [
                    '_' => 'message',
                    'id' => $id,
                    'peer_id' => ['_' => 'peerUser', 'user_id' => 101],
                    'from_id' => ['_' => 'peerUser', 'user_id' => 101],
                    'date' => time() - (20 - $id),
                    'message' => 'Tin nhắn #' . $id,
                    'edit_date' => $id === 9 ? time() - 5 : null,
                    'reply_to' => $id === 9 ? ['reply_to_msg_id' => 10, 'quote_text' => 'Trích dẫn'] : null,
                ];
            }

            return [
                'messages' => $messages,
                'users' => [[
                    '_' => 'user',
                    'id' => 101,
                    'first_name' => 'Nguyen',
                    'last_name' => 'Van A',
                    'username' => 'nguyenvana',
                ]],
                'chats' => [],
            ];
        }
    };

    $inbox = new TelegramInboxService($db);
    $sync = new TelegramInboxSyncService(
        $db,
        $telegram,
        new TelegramMessageNormalizer(),
        new TelegramAccountLockService($db)
    );

    $dialogsJob = $inbox->enqueueAccountSync($accountId);
    $dialogsRun = $sync->runJob($dialogsJob);
    $assert($dialogsRun['completed'] === 1, 'Dialog refresh job must complete.');
    $dialogs = $inbox->dialogs($accountId);
    $assert(count($dialogs['items']) === 3, 'Private, group and channel dialogs must be cached.');
    $dialogTypes = array_column($dialogs['items'], 'peer_type');
    $assert(in_array('private', $dialogTypes, true), 'Private dialogs must be normalized.');
    $assert(in_array('group', $dialogTypes, true), 'Group dialogs must be normalized.');
    $assert(in_array('channel', $dialogTypes, true), 'Channel dialogs must be normalized.');
    $privateDialog = array_values(array_filter(
        $dialogs['items'],
        static fn (array $dialog): bool => $dialog['peer_type'] === 'private'
    ))[0];
    $dialogId = (int) $privateDialog['id'];

    $historyJob = $inbox->enqueueDialogSync($dialogId);
    $historyRun = $sync->runJob($historyJob);
    $assert($historyRun['completed'] === 1, 'History refresh job must complete.');
    $messages = $inbox->messages($dialogId);
    $assert(count($messages['items']) === 2, 'Two messages must be cached.');
    $assert((int) $messages['items'][0]['telegram_message_id'] === 9, 'Messages must be returned oldest first.');
    $assert((int) $messages['items'][0]['reply_to_message_id'] === 10, 'Reply metadata must be stored.');
    $assert($messages['items'][0]['edited_at'] !== null, 'Edited timestamp must be stored.');

    $backfillJob = $inbox->enqueueOlder($dialogId, 9);
    $assert($backfillJob !== null, 'Incomplete history must create a backfill job.');
    $backfillRun = $sync->runJob($backfillJob);
    $assert($backfillRun['completed'] === 1, 'History backfill job must complete.');
    $olderMessages = $inbox->messages($dialogId, 9);
    $assert(count($olderMessages['items']) === 1, 'Older history must resume from the durable cursor.');
    $assert((int) $olderMessages['items'][0]['telegram_message_id'] === 8, 'Backfill must cache the older message.');
    $assert($olderMessages['history_complete'] === true, 'Short final page must mark history complete.');
    $assert(in_array(9, $telegram->historyOffsets, true), 'Backfill must call Telegram with the requested offset.');

    $resumableJob = $inbox->enqueueAccountSync($accountId);
    $db->query(
        'UPDATE telegram_inbox_sync_jobs
         SET status = \'running\', locked_until = :expired, lock_token = :token
         WHERE job_key = :job_key',
        [
            'expired' => gmdate('Y-m-d H:i:s', time() - 5),
            'token' => 'expired-' . $suffix,
            'job_key' => 'dialogs:' . $accountId,
        ]
    );
    $resumedRun = $sync->runJob($resumableJob);
    $assert($resumedRun['completed'] === 1, 'An expired running job must resume on the next cron run.');

    $source = file_get_contents(dirname(__DIR__) . '/app/Services/TelegramService.php');
    foreach (['messages->readHistory', 'channels->readHistory', 'messages->readMessageContents'] as $forbiddenCall) {
        $assert(!str_contains((string) $source, $forbiddenCall), 'Inbox code must not mark Telegram messages as read.');
    }

    echo "Telegram inbox smoke test passed.\n";
} finally {
    if ($userId !== null) {
        $db->query('DELETE FROM users WHERE id = :id', ['id' => $userId]);
    }
}
