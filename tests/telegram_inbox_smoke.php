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

    $inbox->enqueueAccountSync($accountId);
    $dialogsRun = $sync->run();
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

    $inbox->enqueueDialogSync($dialogId);
    $historyRun = $sync->run();
    $assert($historyRun['completed'] === 1, 'History refresh job must complete.');
    $messages = $inbox->messages($dialogId);
    $assert(count($messages['items']) === 2, 'Two messages must be cached.');
    $assert((int) $messages['items'][0]['telegram_message_id'] === 9, 'Messages must be returned oldest first.');
    $assert((int) $messages['items'][0]['reply_to_message_id'] === 10, 'Reply metadata must be stored.');
    $assert($messages['items'][0]['edited_at'] !== null, 'Edited timestamp must be stored.');

    $inbox->enqueueOlder($dialogId, 9);
    $backfillRun = $sync->run();
    $assert($backfillRun['completed'] === 1, 'History backfill job must complete.');
    $olderMessages = $inbox->messages($dialogId, 9);
    $assert(count($olderMessages['items']) === 1, 'Older history must resume from the durable cursor.');
    $assert((int) $olderMessages['items'][0]['telegram_message_id'] === 8, 'Backfill must cache the older message.');
    $assert($olderMessages['history_complete'] === true, 'Short final page must mark history complete.');
    $assert(in_array(9, $telegram->historyOffsets, true), 'Backfill must call Telegram with the requested offset.');

    $inbox->enqueueAccountSync($accountId);
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
    $resumedRun = $sync->run();
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
