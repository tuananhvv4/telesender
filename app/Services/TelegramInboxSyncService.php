<?php

declare(strict_types=1);

namespace App\Services;

use Amp\TimeoutException;
use App\Core\Database;
use danog\MadelineProto\RPCError\RateLimitError;
use danog\MadelineProto\RPCError\TimeoutError;
use Throwable;

class TelegramInboxSyncService
{
    public function __construct(
        private readonly Database $db,
        private readonly TelegramService $telegram,
        private readonly TelegramMessageNormalizer $normalizer,
        private readonly TelegramAccountLockService $locks
    ) {
    }

    public function run(): array
    {
        $this->seedDueAccountJobs();
        return $this->runLoop(null, max(1, (int) config('inbox.jobs_per_run', 8)), false);
    }

    public function runJob(string $jobKey): array
    {
        return $this->runLoop($jobKey, 1, true);
    }

    private function runLoop(?string $jobKey, int $limit, bool $manual): array
    {
        $started = microtime(true);
        $runtime = max(10, (int) config('inbox.cron_runtime_seconds', 40));
        $reserve = max(1, (int) config('inbox.cron_shutdown_reserve_seconds', 5));
        $result = [
            'processed' => 0,
            'completed' => 0,
            'rescheduled' => 0,
            'rate_limited' => 0,
            'busy_accounts' => 0,
            'deadline_reached' => false,
            'errors' => [],
        ];

        for ($index = 0; $index < $limit; $index++) {
            if ((microtime(true) - $started) >= ($runtime - $reserve)) {
                $result['deadline_reached'] = true;
                break;
            }

            $job = $this->claimJob($jobKey);
            if ($job === null) {
                break;
            }
            $result['processed']++;
            $accountToken = null;

            try {
                $account = $this->loadAccount((int) $job['telegram_account_id']);
                if ($account === null || (string) ($account['session_status'] ?? '') !== 'active') {
                    $this->failJob($job, 'session_inactive', 'Telegram session không còn active.');
                    $result['errors'][] = ['job_id' => (int) $job['id'], 'code' => 'session_inactive'];
                    continue;
                }

                $leaseSeconds = $manual
                    ? max(30, (int) config('inbox.manual_sync_lock_seconds', 60))
                    : max(30, (int) config('inbox.sync_lock_seconds', 60));
                $lookaheadSeconds = $manual
                    ? max(0, (int) config('inbox.manual_dispatch_lookahead_seconds', 0))
                    : max(0, (int) config('inbox.dispatch_lookahead_seconds', 180));
                $accountToken = $this->locks->acquireInbox(
                    (int) $account['id'],
                    $leaseSeconds,
                    $lookaheadSeconds
                );
                if ($accountToken === null) {
                    $this->rescheduleBusy($job);
                    $result['busy_accounts']++;
                    $result['rescheduled']++;
                    continue;
                }

                match ((string) $job['job_type']) {
                    'dialogs_refresh' => $this->syncDialogs($job, $account),
                    'history_refresh', 'history_backfill' => $this->syncHistory($job, $account),
                    default => $this->failJob($job, 'unknown_job_type', 'Loại sync job không hợp lệ.'),
                };
                $result['completed']++;
            } catch (RateLimitError $exception) {
                $this->retryJobAt($job, 'rate_limited', $exception->getMessage(), $exception->expires);
                $result['rate_limited']++;
                $result['rescheduled']++;
            } catch (TimeoutError|TimeoutException $exception) {
                $this->retryTransient($job, 'timeout', $exception->getMessage());
                $result['rescheduled']++;
            } catch (Throwable $exception) {
                $this->retryTransient($job, 'telegram_error', $exception->getMessage());
                $result['errors'][] = ['job_id' => (int) $job['id'], 'code' => 'telegram_error'];
                $result['rescheduled']++;
            } finally {
                if ($accountToken !== null) {
                    $this->locks->release((int) $job['telegram_account_id'], $accountToken);
                }
            }
        }

        return $result;
    }

    private function claimJob(?string $jobKey = null): ?array
    {
        return $this->db->transaction(function (Database $db) use ($jobKey): ?array {
            $bindings = [];
            $jobKeySql = '';
            if ($jobKey !== null) {
                $jobKeySql = ' AND job_key = :job_key';
                $bindings['job_key'] = $jobKey;
            }
            $job = $db->fetch(
                'SELECT *
                 FROM telegram_inbox_sync_jobs
                 WHERE status IN (\'pending\', \'retry\', \'running\')
                   AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP())
                   AND (locked_until IS NULL OR locked_until < UTC_TIMESTAMP())' . $jobKeySql . '
                 ORDER BY priority DESC, created_at ASC, id ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED',
                $bindings
            );
            if ($job === null) {
                return null;
            }

            $token = bin2hex(random_bytes(16));
            $lease = max(30, (int) config('inbox.sync_lock_seconds', 60));
            $until = gmdate('Y-m-d H:i:s', time() + $lease);
            $now = gmdate('Y-m-d H:i:s');
            $db->query(
                'UPDATE telegram_inbox_sync_jobs
                 SET status = \'running\', lock_token = :token, locked_until = :locked_until,
                     started_at = COALESCE(started_at, :started_at), updated_at = :updated_at
                 WHERE id = :id',
                [
                    'token' => $token,
                    'locked_until' => $until,
                    'started_at' => $now,
                    'updated_at' => $now,
                    'id' => (int) $job['id'],
                ]
            );
            $job['lock_token'] = $token;
            return $job;
        });
    }

    private function seedDueAccountJobs(): void
    {
        $freshSeconds = max(30, (int) config('inbox.fresh_dialog_seconds', 120));
        $freshAfter = gmdate('Y-m-d H:i:s', time() - $freshSeconds);
        $accounts = $this->db->fetchAll(
            'SELECT ta.id, job.status AS sync_status, job.completed_at, job.updated_at AS job_updated_at
             FROM telegram_accounts ta
             INNER JOIN users u ON u.id = ta.user_id
             LEFT JOIN telegram_inbox_sync_jobs job ON job.job_key = CONCAT(\'dialogs:\', ta.id)
             WHERE u.role = \'admin\'
               AND ta.session_status = \'active\'
             ORDER BY ta.id ASC
             LIMIT 200'
        );
        $inbox = new TelegramInboxService($this->db);

        foreach ($accounts as $account) {
            $status = (string) ($account['sync_status'] ?? '');
            if (in_array($status, ['pending', 'retry', 'running'], true)) {
                continue;
            }

            $freshness = (string) ($account['completed_at'] ?? $account['job_updated_at'] ?? '');
            if ($freshness !== '' && $freshness > $freshAfter) {
                continue;
            }

            $inbox->enqueueAccountSync((int) $account['id']);
        }
    }

    private function syncDialogs(array $job, array $account): void
    {
        $response = $this->telegram->getDialogsPage($account, (int) config('inbox.dialogs_page_size', 100));
        $dialogs = $this->normalizer->dialogs($response);
        if (($response['dialogs'] ?? []) !== [] && $dialogs === []) {
            $shapes = array_slice(array_map(static function (mixed $dialog): array {
                if (!is_array($dialog)) {
                    return ['dialog_type' => get_debug_type($dialog)];
                }
                $peer = $dialog['peer'] ?? $dialog['peer_id'] ?? null;
                return [
                    'dialog_constructor' => (string) ($dialog['_'] ?? ''),
                    'peer_type' => get_debug_type($peer),
                    'peer_constructor' => is_array($peer) ? (string) ($peer['_'] ?? '') : '',
                    'peer_keys' => is_array($peer) ? array_keys($peer) : [],
                    'resolved_id' => (string) ($dialog['_peer_identifier'] ?? ''),
                ];
            }, (array) $response['dialogs']), 0, 5);
            throw new \RuntimeException(
                'Telegram có trả về hội thoại nhưng chưa đọc được cấu trúc peer: '
                . json_encode($shapes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
        $now = gmdate('Y-m-d H:i:s');
        $emptyResult = ($response['dialogs'] ?? []) === [];
        $genericTitles = ['Telegram chat', 'Telegram user'];
        $repairPeers = [];
        foreach ($dialogs as $dialog) {
            if (in_array((string) $dialog['title'], $genericTitles, true)) {
                $repairPeers[] = (string) $dialog['peer_identifier'];
            }
        }
        $cachedGenericDialogs = $this->db->fetchAll(
            'SELECT peer_identifier
             FROM telegram_inbox_dialogs
             WHERE telegram_account_id = :account_id
               AND (title IN (\'Telegram chat\', \'Telegram user\') OR title = \'\')
             ORDER BY last_message_at DESC, id DESC
             LIMIT ' . max(1, (int) config('inbox.identity_lookup_limit', 120)),
            ['account_id' => (int) $account['id']]
        );
        foreach ($cachedGenericDialogs as $cachedDialog) {
            $repairPeers[] = (string) $cachedDialog['peer_identifier'];
        }
        $identities = $repairPeers !== []
            ? $this->telegram->resolveInboxPeerIdentities($account, $repairPeers)
            : [];
        foreach ($dialogs as &$dialog) {
            $identity = $identities[(string) $dialog['peer_identifier']] ?? null;
            if ($identity === null || !in_array((string) $dialog['title'], $genericTitles, true)) {
                continue;
            }
            $dialog['title'] = (string) $identity['title'];
            $dialog['username'] = $identity['username'];
            if (!empty($identity['peer_type'])) {
                $dialog['peer_type'] = (string) $identity['peer_type'];
            }
        }
        unset($dialog);

        $this->db->transaction(function (Database $db) use ($job, $account, $dialogs, $identities, $now, $emptyResult): void {
            foreach ($dialogs as $dialog) {
                if ($dialog['peer_identifier'] === '') {
                    continue;
                }
                $db->query(
                    'INSERT INTO telegram_inbox_dialogs (
                        user_id, telegram_account_id, peer_key, peer_identifier, peer_type, is_forum, is_bot,
                        title, username, top_message_id, last_message_text, last_message_at,
                        unread_count, history_complete, created_at, updated_at
                     ) VALUES (
                        :user_id, :account_id, :peer_key, :peer_identifier, :peer_type, :is_forum, :is_bot,
                        :title, :username, :top_message_id, :last_message_text, :last_message_at,
                        :unread_count, 0, :created_at, :updated_at
                     )
                     ON DUPLICATE KEY UPDATE
                        peer_identifier = VALUES(peer_identifier), peer_type = VALUES(peer_type),
                        is_forum = VALUES(is_forum),
                        is_bot = VALUES(is_bot),
                        title = VALUES(title), username = VALUES(username), top_message_id = VALUES(top_message_id),
                        last_message_text = VALUES(last_message_text), last_message_at = VALUES(last_message_at),
                        unread_count = VALUES(unread_count), updated_at = VALUES(updated_at)',
                    array_merge($dialog, [
                        'user_id' => (int) $account['user_id'],
                        'account_id' => (int) $account['id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
            foreach ($identities as $peerIdentifier => $identity) {
                $updates = [
                    'title' => (string) $identity['title'],
                    'username' => $identity['username'],
                    'is_bot' => (int) ($identity['is_bot'] ?? 0),
                    'updated_at' => $now,
                ];
                if (!empty($identity['peer_type'])) {
                    $updates['peer_type'] = (string) $identity['peer_type'];
                }
                $db->update(
                    'telegram_inbox_dialogs',
                    $updates,
                    'telegram_account_id = :account_id AND peer_identifier = :peer_identifier',
                    ['account_id' => (int) $account['id'], 'peer_identifier' => (string) $peerIdentifier]
                );
            }
            $this->completeJobWithDb(
                $db,
                $job,
                $now,
                $emptyResult ? 'empty_dialogs' : null,
                $emptyResult ? 'Telegram trả về 0 hội thoại cho account này.' : null
            );
        });
    }

    private function syncHistory(array $job, array $account): void
    {
        $dialog = $this->db->fetch(
            'SELECT * FROM telegram_inbox_dialogs WHERE id = :id AND telegram_account_id = :account_id LIMIT 1',
            ['id' => (int) $job['telegram_inbox_dialog_id'], 'account_id' => (int) $account['id']]
        );
        if ($dialog === null) {
            $this->failJob($job, 'dialog_missing', 'Hội thoại không còn tồn tại.');
            return;
        }

        $cursor = json_decode((string) ($job['cursor_json'] ?? ''), true);
        $cursor = is_array($cursor) ? $cursor : [];
        $topicId = max(0, (int) ($cursor['topic_id'] ?? 0));
        $topic = null;
        if ($topicId > 0) {
            $topic = $this->db->fetch(
                'SELECT * FROM telegram_inbox_topics
                 WHERE telegram_inbox_dialog_id = :dialog_id AND topic_id = :topic_id
                 LIMIT 1',
                ['dialog_id' => (int) $dialog['id'], 'topic_id' => $topicId]
            );
            if ($topic === null) {
                $this->failJob($job, 'topic_missing', 'Topic không còn tồn tại trong cache.');
                return;
            }
        }
        $offsetId = (string) $job['job_type'] === 'history_backfill'
            ? max(0, (int) ($cursor['before_message_id'] ?? $topic['oldest_message_id'] ?? $dialog['oldest_message_id'] ?? 0))
            : 0;
        $pageSize = max(1, (int) config('inbox.history_page_size', 40));
        $topics = [];
        if ((bool) ($dialog['is_forum'] ?? false) || (string) $dialog['peer_type'] === 'supergroup') {
            try {
                $topics = $this->telegram->getInboxForumTopics(
                    $account,
                    (string) $dialog['peer_identifier'],
                    max(1, (int) config('inbox.topics_page_size', 100))
                );
            } catch (RateLimitError|TimeoutError|TimeoutException $exception) {
                throw $exception;
            } catch (Throwable) {
                // Telegram omits the forum flag on some dialogs; non-forum supergroups simply have no topics.
            }
        }
        $response = $topicId > 0
            ? $this->telegram->getTopicHistoryPage(
                $account,
                (string) $dialog['peer_identifier'],
                $topicId,
                $offsetId,
                $pageSize
            )
            : $this->telegram->getHistoryPage(
                $account,
                (string) $dialog['peer_identifier'],
                $offsetId,
                $pageSize
            );
        $messages = $this->normalizer->messages($response, $account);
        if ($topicId > 0) {
            foreach ($messages as &$topicMessage) {
                $topicMessage['topic_id'] = $topicId;
            }
            unset($topicMessage);
        }
        $now = gmdate('Y-m-d H:i:s');
        $genericSenderNames = ['Telegram', 'Telegram user'];
        $senderKeys = [];
        foreach ($messages as $message) {
            if (
                in_array((string) ($message['sender_name'] ?? ''), $genericSenderNames, true)
                && !empty($message['sender_peer_key'])
                && !str_starts_with((string) $message['sender_peer_key'], 'self:')
            ) {
                $senderKeys[] = (string) $message['sender_peer_key'];
            }
        }
        $cachedGenericSenders = $this->db->fetchAll(
            'SELECT DISTINCT sender_peer_key
             FROM telegram_inbox_messages
             WHERE telegram_account_id = :account_id
               AND sender_peer_key IS NOT NULL
               AND sender_peer_key NOT LIKE \'self:%\'
               AND sender_name IN (\'Telegram\', \'Telegram user\')
             ORDER BY sender_peer_key ASC
             LIMIT ' . max(1, (int) config('inbox.identity_lookup_limit', 120)),
            ['account_id' => (int) $account['id']]
        );
        foreach ($cachedGenericSenders as $cachedSender) {
            $senderKeys[] = (string) $cachedSender['sender_peer_key'];
        }
        $senderPeerMap = [];
        foreach (array_values(array_unique($senderKeys)) as $senderKey) {
            $identifier = $this->peerIdentifierFromKey($senderKey);
            if ($identifier !== '') {
                $senderPeerMap[$identifier] = $senderKey;
            }
        }
        $senderIdentities = $senderPeerMap !== []
            ? $this->telegram->resolveInboxPeerIdentities(
                $account,
                array_map(static fn (int|string $identifier): string => (string) $identifier, array_keys($senderPeerMap))
            )
            : [];
        $senderNames = [];
        foreach ($senderIdentities as $identifier => $identity) {
            $senderKey = $senderPeerMap[(string) $identifier] ?? null;
            if ($senderKey !== null && !empty($identity['title'])) {
                $senderNames[$senderKey] = (string) $identity['title'];
            }
        }
        foreach ($messages as &$message) {
            $senderKey = (string) ($message['sender_peer_key'] ?? '');
            if (
                isset($senderNames[$senderKey])
                && in_array((string) ($message['sender_name'] ?? ''), $genericSenderNames, true)
            ) {
                $message['sender_name'] = $senderNames[$senderKey];
            }
        }
        unset($message);

        $this->db->transaction(function (Database $db) use (
            $job,
            $account,
            $dialog,
            $topic,
            $topicId,
            $topics,
            $messages,
            $senderNames,
            $pageSize,
            $now
        ): void {
            foreach ($topics as $forumTopic) {
                $db->query(
                    'INSERT INTO telegram_inbox_topics (
                        user_id, telegram_account_id, telegram_inbox_dialog_id, topic_id,
                        title, icon_color, icon_emoji_id, top_message_id, unread_count,
                        history_complete, created_at, updated_at
                     ) VALUES (
                        :user_id, :account_id, :dialog_id, :topic_id,
                        :title, :icon_color, :icon_emoji_id, :top_message_id, :unread_count,
                        0, :created_at, :updated_at
                     )
                     ON DUPLICATE KEY UPDATE
                        title = VALUES(title), icon_color = VALUES(icon_color),
                        icon_emoji_id = VALUES(icon_emoji_id), top_message_id = VALUES(top_message_id),
                        unread_count = VALUES(unread_count), updated_at = VALUES(updated_at)',
                    array_merge($forumTopic, [
                        'user_id' => (int) $dialog['user_id'],
                        'account_id' => (int) $dialog['telegram_account_id'],
                        'dialog_id' => (int) $dialog['id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }

            $ids = [];
            foreach ($messages as $message) {
                $ids[] = (int) $message['telegram_message_id'];
                $db->query(
                    'INSERT INTO telegram_inbox_messages (
                        user_id, telegram_account_id, telegram_inbox_dialog_id, telegram_message_id,
                        sender_peer_key, sender_name, is_outgoing, message_text, reply_to_message_id,
                        reply_to_top_id, topic_id, reply_quote_text, grouped_id, media_type, media_file_name,
                        media_mime_type, media_size, media_source_json, telegram_created_at,
                        edited_at, raw_type, created_at, updated_at
                     ) VALUES (
                        :user_id, :account_id, :dialog_id, :telegram_message_id,
                        :sender_peer_key, :sender_name, :is_outgoing, :message_text, :reply_to_message_id,
                        :reply_to_top_id, :topic_id, :reply_quote_text, :grouped_id, :media_type, :media_file_name,
                        :media_mime_type, :media_size, :media_source_json, :telegram_created_at,
                        :edited_at, :raw_type, :created_at, :updated_at
                     )
                     ON DUPLICATE KEY UPDATE
                        sender_peer_key = VALUES(sender_peer_key), sender_name = VALUES(sender_name),
                        is_outgoing = VALUES(is_outgoing), message_text = VALUES(message_text),
                        reply_to_message_id = VALUES(reply_to_message_id), reply_to_top_id = VALUES(reply_to_top_id),
                        topic_id = COALESCE(VALUES(topic_id), topic_id),
                        reply_quote_text = VALUES(reply_quote_text), grouped_id = VALUES(grouped_id),
                        media_type = VALUES(media_type), media_file_name = VALUES(media_file_name),
                        media_mime_type = VALUES(media_mime_type), media_size = VALUES(media_size),
                        media_source_json = COALESCE(VALUES(media_source_json), media_source_json),
                        telegram_created_at = VALUES(telegram_created_at), edited_at = VALUES(edited_at),
                        raw_type = VALUES(raw_type), updated_at = VALUES(updated_at)',
                    array_merge($message, [
                        'user_id' => (int) $dialog['user_id'],
                        'account_id' => (int) $dialog['telegram_account_id'],
                        'dialog_id' => (int) $dialog['id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }

            foreach ($topics as $forumTopic) {
                $db->query(
                    'UPDATE telegram_inbox_messages
                     SET topic_id = :topic_id, updated_at = :updated_at
                     WHERE telegram_inbox_dialog_id = :dialog_id
                       AND telegram_message_id = :topic_id
                       AND topic_id IS NULL',
                    [
                        'topic_id' => (int) $forumTopic['topic_id'],
                        'updated_at' => $now,
                        'dialog_id' => (int) $dialog['id'],
                    ]
                );
            }

            foreach ($senderNames as $senderKey => $senderName) {
                $db->query(
                    'UPDATE telegram_inbox_messages
                     SET sender_name = :sender_name, updated_at = :updated_at
                     WHERE telegram_account_id = :account_id
                       AND sender_peer_key = :sender_peer_key
                       AND sender_name IN (\'Telegram\', \'Telegram user\')',
                    [
                        'sender_name' => $senderName,
                        'updated_at' => $now,
                        'account_id' => (int) $account['id'],
                        'sender_peer_key' => (string) $senderKey,
                    ]
                );
            }

            $updates = [
                'last_synced_at' => $now,
                'updated_at' => $now,
            ];
            if ($topics !== []) {
                $updates['is_forum'] = 1;
            }
            if ($ids !== []) {
                $updates['oldest_message_id'] = $dialog['oldest_message_id'] === null
                    ? min($ids)
                    : min((int) $dialog['oldest_message_id'], min($ids));
                $updates['newest_message_id'] = $dialog['newest_message_id'] === null
                    ? max($ids)
                    : max((int) $dialog['newest_message_id'], max($ids));
            }
            if ($topicId === 0 && (string) $job['job_type'] === 'history_backfill' && count($messages) < $pageSize) {
                $updates['history_complete'] = 1;
            }
            $db->update('telegram_inbox_dialogs', $updates, 'id = :id', ['id' => (int) $dialog['id']]);

            if ($topicId > 0 && $topic !== null) {
                $topicUpdates = [
                    'last_synced_at' => $now,
                    'updated_at' => $now,
                ];
                if ($ids !== []) {
                    $topicUpdates['oldest_message_id'] = $topic['oldest_message_id'] === null
                        ? min($ids)
                        : min((int) $topic['oldest_message_id'], min($ids));
                    $topicUpdates['newest_message_id'] = $topic['newest_message_id'] === null
                        ? max($ids)
                        : max((int) $topic['newest_message_id'], max($ids));
                }
                if ((string) $job['job_type'] === 'history_backfill' && count($messages) < $pageSize) {
                    $topicUpdates['history_complete'] = 1;
                }
                $db->update(
                    'telegram_inbox_topics',
                    $topicUpdates,
                    'telegram_inbox_dialog_id = :dialog_id AND topic_id = :topic_id',
                    ['dialog_id' => (int) $dialog['id'], 'topic_id' => $topicId]
                );
            }
            $this->completeJobWithDb($db, $job, $now);
        });
    }

    private function peerIdentifierFromKey(string $peerKey): string
    {
        if (!str_contains($peerKey, ':')) {
            return '';
        }
        [$type, $id] = explode(':', $peerKey, 2);
        $id = ltrim(trim($id), '-');
        if ($id === '') {
            return '';
        }

        return match ($type) {
            'user' => $id,
            'chat' => '-' . $id,
            'channel' => '-100' . $id,
            default => '',
        };
    }

    private function loadAccount(int $accountId): ?array
    {
        return $this->db->fetch(
            'SELECT ta.* FROM telegram_accounts ta
             INNER JOIN users u ON u.id = ta.user_id
             WHERE ta.id = :id AND u.role = \'admin\' LIMIT 1',
            ['id' => $accountId]
        );
    }

    private function completeJobWithDb(
        Database $db,
        array $job,
        string $now,
        ?string $resultCode = null,
        ?string $resultMessage = null
    ): void
    {
        $db->query(
            'UPDATE telegram_inbox_sync_jobs
             SET status = \'completed\', locked_until = NULL, lock_token = NULL,
                 completed_at = :completed_at, last_error_code = :result_code,
                 last_error_message = :result_message, updated_at = :updated_at
             WHERE id = :id AND lock_token = :lock_token',
            [
                'completed_at' => $now,
                'result_code' => $resultCode,
                'result_message' => $resultMessage,
                'updated_at' => $now,
                'id' => (int) $job['id'],
                'lock_token' => (string) $job['lock_token'],
            ]
        );
    }

    private function rescheduleBusy(array $job): void
    {
        $this->retryJobAt($job, 'account_busy', 'Telegram account đang ưu tiên gửi tin.', time() + 30, false);
    }

    private function retryTransient(array $job, string $code, string $message): void
    {
        $attempts = (int) ($job['attempts'] ?? 0) + 1;
        $delays = [30, 120, 300, 900];
        $delay = $delays[min($attempts - 1, count($delays) - 1)];
        $this->retryJobAt($job, $code, $message, time() + $delay, true);
    }

    private function retryJobAt(array $job, string $code, string $message, int $timestamp, bool $incrementAttempts = true): void
    {
        $this->db->query(
            'UPDATE telegram_inbox_sync_jobs
             SET status = \'retry\', attempts = attempts + :attempt_increment,
                 next_attempt_at = :next_attempt_at, locked_until = NULL, lock_token = NULL,
                 last_error_code = :error_code, last_error_message = :error_message,
                 updated_at = :updated_at
             WHERE id = :id AND lock_token = :lock_token',
            [
                'attempt_increment' => $incrementAttempts ? 1 : 0,
                'next_attempt_at' => gmdate('Y-m-d H:i:s', max(time() + 1, $timestamp)),
                'error_code' => $code,
                'error_message' => mb_substr($message, 0, 1000),
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'id' => (int) $job['id'],
                'lock_token' => (string) $job['lock_token'],
            ]
        );
    }

    private function failJob(array $job, string $code, string $message): void
    {
        $this->db->query(
            'UPDATE telegram_inbox_sync_jobs
             SET status = \'failed\', locked_until = NULL, lock_token = NULL,
                 last_error_code = :error_code, last_error_message = :error_message,
                 completed_at = :completed_at, updated_at = :updated_at
             WHERE id = :id AND lock_token = :lock_token',
            [
                'error_code' => $code,
                'error_message' => mb_substr($message, 0, 1000),
                'completed_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'id' => (int) $job['id'],
                'lock_token' => (string) $job['lock_token'],
            ]
        );
    }
}
