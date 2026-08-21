<?php

declare(strict_types=1);

namespace App\Services;

class TelegramMessageNormalizer
{
    public function dialogs(array $response): array
    {
        $users = $this->userMap((array) ($response['users'] ?? []));
        $chats = $this->chatMap((array) ($response['chats'] ?? []));
        $messages = [];

        foreach ((array) ($response['messages'] ?? []) as $message) {
            if (!is_array($message) || !isset($message['id'])) {
                continue;
            }

            $key = $this->peerKey((array) ($message['peer_id'] ?? [])) . ':' . (int) $message['id'];
            $messages[$key] = $message;
        }

        $result = [];
        foreach ((array) ($response['dialogs'] ?? []) as $dialog) {
            if (!is_array($dialog)) {
                continue;
            }

            $peer = $dialog['peer'] ?? $dialog['peer_id'] ?? [];
            $peerIdentifier = trim((string) ($dialog['_peer_identifier'] ?? ''));
            $peerKey = $this->peerKey($peer);
            if ($peerKey === '') {
                $peerKey = $this->peerKeyFromIdentifier($peerIdentifier);
            }
            $peerType = $this->peerType($peer, $chats);
            if ($peerType === 'unknown') {
                $peerType = $this->peerTypeFromIdentifier($peerIdentifier, $chats);
            }
            if ($peerKey === '' || !in_array($peerType, ['private', 'group', 'supergroup', 'channel'], true)) {
                continue;
            }

            $topMessageId = isset($dialog['top_message']) ? (int) $dialog['top_message'] : null;
            $topMessage = $topMessageId !== null ? ($messages[$peerKey . ':' . $topMessageId] ?? null) : null;
            [$title, $username] = $this->dialogIdentity($peer, $users, $chats, $peerIdentifier);

            $result[] = [
                'peer_key' => $peerKey,
                'peer_identifier' => $peerIdentifier,
                'peer_type' => $peerType,
                'is_forum' => $this->isForumPeer($peerKey, $chats) ? 1 : 0,
                'is_bot' => $this->isBotPeer($peerKey, $users) ? 1 : 0,
                'title' => $title,
                'username' => $username,
                'top_message_id' => $topMessageId,
                'last_message_text' => $topMessage !== null ? $this->messagePreview($topMessage) : null,
                'last_message_at' => $topMessage !== null ? $this->telegramDate($topMessage['date'] ?? null) : null,
                'unread_count' => max(0, (int) ($dialog['unread_count'] ?? 0)),
            ];
        }

        return $result;
    }

    public function messages(array $response, array $account): array
    {
        $users = $this->userMap((array) ($response['users'] ?? []));
        $chats = $this->chatMap((array) ($response['chats'] ?? []));
        $result = [];

        foreach ((array) ($response['messages'] ?? []) as $message) {
            if (!is_array($message) || !isset($message['id'])) {
                continue;
            }

            $rawType = (string) ($message['_'] ?? 'message');
            if ($rawType === 'messageEmpty') {
                continue;
            }

            $outgoing = (bool) ($message['out'] ?? false);
            [$senderKey, $senderName] = $this->sender($message, $users, $chats, $account, $outgoing);
            $reply = is_array($message['reply_to'] ?? null) ? $message['reply_to'] : [];
            $media = $this->media((array) ($message['_media_bot_api'] ?? []));
            $text = trim((string) ($message['message'] ?? ''));

            if ($text === '' && $rawType === 'messageService') {
                $text = 'Sự kiện Telegram';
            }

            $result[] = [
                'telegram_message_id' => (int) $message['id'],
                'sender_peer_key' => $senderKey,
                'sender_name' => $senderName,
                'is_outgoing' => $outgoing ? 1 : 0,
                'message_text' => $text !== '' ? $text : null,
                'reply_to_message_id' => isset($reply['reply_to_msg_id']) ? (int) $reply['reply_to_msg_id'] : null,
                'reply_to_top_id' => isset($reply['reply_to_top_id']) ? (int) $reply['reply_to_top_id'] : null,
                'topic_id' => isset($reply['reply_to_top_id']) ? (int) $reply['reply_to_top_id'] : null,
                'reply_quote_text' => isset($reply['quote_text']) ? trim((string) $reply['quote_text']) : null,
                'grouped_id' => isset($message['grouped_id']) ? (string) $message['grouped_id'] : null,
                'media_type' => $media['type'],
                'media_file_name' => $media['file_name'],
                'media_mime_type' => $media['mime_type'],
                'media_size' => $media['size'],
                'media_source_json' => $media['source_json'],
                'telegram_created_at' => $this->telegramDate($message['date'] ?? null) ?? gmdate('Y-m-d H:i:s'),
                'edited_at' => $this->telegramDate($message['edit_date'] ?? null),
                'raw_type' => $rawType,
            ];
        }

        return $result;
    }

    private function userMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['id'])) {
                $map[(string) $row['id']] = $row;
            }
        }
        return $map;
    }

    private function chatMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['id'])) {
                $map[(string) $row['id']] = $row;
            }
        }
        return $map;
    }

    private function peerKey(mixed $peer): string
    {
        if (is_int($peer) || is_string($peer)) {
            return $this->peerKeyFromIdentifier(trim((string) $peer));
        }
        if (!is_array($peer)) {
            return '';
        }

        $constructor = strtolower((string) ($peer['_'] ?? ''));
        if (str_contains($constructor, 'user')) {
            return 'user:' . (string) ($peer['user_id'] ?? $peer['id'] ?? '');
        }
        if (str_contains($constructor, 'channel')) {
            return 'channel:' . (string) ($peer['channel_id'] ?? $peer['id'] ?? '');
        }
        if (str_contains($constructor, 'chat')) {
            return 'chat:' . (string) ($peer['chat_id'] ?? $peer['id'] ?? '');
        }

        return '';
    }

    private function peerType(mixed $peer, array $chats): string
    {
        if (is_int($peer) || is_string($peer)) {
            return $this->peerTypeFromIdentifier(trim((string) $peer), $chats);
        }
        if (!is_array($peer)) {
            return 'unknown';
        }

        $constructor = strtolower((string) ($peer['_'] ?? ''));
        if (str_contains($constructor, 'user')) {
            return 'private';
        }
        if (str_contains($constructor, 'chat') && !str_contains($constructor, 'channel')) {
            return 'group';
        }
        if (!str_contains($constructor, 'channel')) {
            return 'unknown';
        }

        $chat = $chats[(string) ($peer['channel_id'] ?? $peer['id'] ?? '')] ?? [];
        return (bool) ($chat['broadcast'] ?? false) ? 'channel' : 'supergroup';
    }

    private function isForumPeer(string $peerKey, array $chats): bool
    {
        if (!str_starts_with($peerKey, 'channel:')) {
            return false;
        }

        return (bool) ($chats[substr($peerKey, 8)]['forum'] ?? false);
    }

    private function isBotPeer(string $peerKey, array $users): bool
    {
        if (!str_starts_with($peerKey, 'user:')) {
            return false;
        }

        return (bool) ($users[substr($peerKey, 5)]['bot'] ?? false);
    }

    private function dialogIdentity(mixed $peer, array $users, array $chats, string $peerIdentifier): array
    {
        $peerKey = $this->peerKey($peer);
        if ($peerKey === '') {
            $peerKey = $this->peerKeyFromIdentifier($peerIdentifier);
        }

        if (str_starts_with($peerKey, 'user:')) {
            $user = $users[substr($peerKey, 5)] ?? [];
            $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            $username = trim((string) ($user['username'] ?? ''));
            return [$name !== '' ? $name : ($username !== '' ? '@' . $username : 'Telegram user'), $username ?: null];
        }

        $id = str_contains($peerKey, ':') ? substr($peerKey, strpos($peerKey, ':') + 1) : '';
        $chat = $chats[$id] ?? [];
        $title = trim((string) ($chat['title'] ?? ''));
        $username = trim((string) ($chat['username'] ?? ''));
        return [$title !== '' ? $title : 'Telegram chat', $username ?: null];
    }

    private function peerKeyFromIdentifier(string $identifier): string
    {
        if ($identifier === '' || $identifier === '0') {
            return '';
        }
        if (str_starts_with($identifier, '-100')) {
            return 'channel:' . substr($identifier, 4);
        }
        if (str_starts_with($identifier, '-')) {
            return 'chat:' . ltrim($identifier, '-');
        }

        return 'user:' . $identifier;
    }

    private function peerTypeFromIdentifier(string $identifier, array $chats): string
    {
        if ($identifier === '' || $identifier === '0') {
            return 'unknown';
        }
        if (str_starts_with($identifier, '-100')) {
            $chat = $chats[substr($identifier, 4)] ?? [];
            return (bool) ($chat['broadcast'] ?? false) ? 'channel' : 'supergroup';
        }

        return str_starts_with($identifier, '-') ? 'group' : 'private';
    }

    private function sender(array $message, array $users, array $chats, array $account, bool $outgoing): array
    {
        if ($outgoing) {
            $username = trim((string) ($account['tg_username'] ?? ''));
            return ['self:' . (int) $account['id'], $username !== '' ? '@' . $username : (string) $account['name']];
        }

        $from = $message['from_id'] ?? $message['sender_id'] ?? [];
        $key = $this->peerKey($from);
        if ($key === '' && isset($message['peer_id'])) {
            $candidateKey = $this->peerKey($message['peer_id']);
            if (str_starts_with($candidateKey, 'user:')) {
                $key = $candidateKey;
            }
        }

        if (str_starts_with($key, 'user:')) {
            $user = $users[substr($key, 5)] ?? [];
            $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            $username = trim((string) ($user['username'] ?? ''));
            return [$key, $name !== '' ? $name : ($username !== '' ? '@' . $username : 'Telegram user')];
        }

        $id = str_contains($key, ':') ? substr($key, strpos($key, ':') + 1) : '';
        $chat = $chats[$id] ?? [];
        $name = trim((string) ($message['post_author'] ?? $chat['title'] ?? ''));
        return [$key, $name !== '' ? $name : 'Telegram'];
    }

    private function messagePreview(array $message): string
    {
        $text = trim((string) ($message['message'] ?? ''));
        if ($text !== '') {
            return mb_substr($text, 0, 180);
        }

        $media = (array) ($message['_media_bot_api'] ?? []);
        foreach (['photo', 'video', 'voice', 'audio', 'document', 'animation', 'video_note', 'sticker'] as $type) {
            if (isset($media[$type])) {
                return '[' . $type . ']';
            }
        }

        return (string) (($message['_'] ?? '') === 'messageService' ? '[Sự kiện Telegram]' : '');
    }

    private function media(array $botApi): array
    {
        $empty = ['type' => null, 'file_name' => null, 'mime_type' => null, 'size' => null, 'source_json' => null];
        if ($botApi === []) {
            return $empty;
        }

        foreach (['video', 'voice', 'audio', 'document', 'animation', 'video_note', 'sticker'] as $type) {
            if (!is_array($botApi[$type] ?? null)) {
                continue;
            }
            $file = $botApi[$type];
            return [
                'type' => $type,
                'file_name' => isset($file['file_name']) ? (string) $file['file_name'] : null,
                'mime_type' => isset($file['mime_type']) ? (string) $file['mime_type'] : null,
                'size' => isset($file['file_size']) ? (int) $file['file_size'] : null,
                'source_json' => !empty($file['file_id']) ? json_encode(['file_id' => (string) $file['file_id']]) : null,
            ];
        }

        if (is_array($botApi['photo'] ?? null) && $botApi['photo'] !== []) {
            $photo = end($botApi['photo']);
            if (is_array($photo)) {
                return [
                    'type' => 'photo',
                    'file_name' => null,
                    'mime_type' => 'image/jpeg',
                    'size' => isset($photo['file_size']) ? (int) $photo['file_size'] : null,
                    'source_json' => !empty($photo['file_id']) ? json_encode(['file_id' => (string) $photo['file_id']]) : null,
                ];
            }
        }

        return $empty;
    }

    private function telegramDate(mixed $timestamp): ?string
    {
        return is_numeric($timestamp) && (int) $timestamp > 0 ? gmdate('Y-m-d H:i:s', (int) $timestamp) : null;
    }
}
