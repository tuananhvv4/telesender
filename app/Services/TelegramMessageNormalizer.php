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

            $peer = (array) ($dialog['peer'] ?? []);
            $peerKey = $this->peerKey($peer);
            $peerType = $this->peerType($peer, $chats);
            if ($peerKey === '' || !in_array($peerType, ['private', 'group', 'supergroup', 'channel'], true)) {
                continue;
            }

            $topMessageId = isset($dialog['top_message']) ? (int) $dialog['top_message'] : null;
            $topMessage = $topMessageId !== null ? ($messages[$peerKey . ':' . $topMessageId] ?? null) : null;
            [$title, $username] = $this->dialogIdentity($peer, $users, $chats);

            $result[] = [
                'peer_key' => $peerKey,
                'peer_identifier' => trim((string) ($dialog['_peer_identifier'] ?? '')),
                'peer_type' => $peerType,
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

    private function peerKey(array $peer): string
    {
        return match ((string) ($peer['_'] ?? '')) {
            'peerUser' => 'user:' . (string) ($peer['user_id'] ?? ''),
            'peerChat' => 'chat:' . (string) ($peer['chat_id'] ?? ''),
            'peerChannel' => 'channel:' . (string) ($peer['channel_id'] ?? ''),
            default => '',
        };
    }

    private function peerType(array $peer, array $chats): string
    {
        $constructor = (string) ($peer['_'] ?? '');
        if ($constructor === 'peerUser') {
            return 'private';
        }
        if ($constructor === 'peerChat') {
            return 'group';
        }
        if ($constructor !== 'peerChannel') {
            return 'unknown';
        }

        $chat = $chats[(string) ($peer['channel_id'] ?? '')] ?? [];
        return (bool) ($chat['broadcast'] ?? false) ? 'channel' : 'supergroup';
    }

    private function dialogIdentity(array $peer, array $users, array $chats): array
    {
        if (($peer['_'] ?? null) === 'peerUser') {
            $user = $users[(string) ($peer['user_id'] ?? '')] ?? [];
            $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            $username = trim((string) ($user['username'] ?? ''));
            return [$name !== '' ? $name : ($username !== '' ? '@' . $username : 'Telegram user'), $username ?: null];
        }

        $id = (string) ($peer['chat_id'] ?? $peer['channel_id'] ?? '');
        $chat = $chats[$id] ?? [];
        $title = trim((string) ($chat['title'] ?? ''));
        $username = trim((string) ($chat['username'] ?? ''));
        return [$title !== '' ? $title : 'Telegram chat', $username ?: null];
    }

    private function sender(array $message, array $users, array $chats, array $account, bool $outgoing): array
    {
        if ($outgoing) {
            $username = trim((string) ($account['tg_username'] ?? ''));
            return ['self:' . (int) $account['id'], $username !== '' ? '@' . $username : (string) $account['name']];
        }

        $from = (array) ($message['from_id'] ?? []);
        $key = $this->peerKey($from);
        if (($from['_'] ?? null) === 'peerUser') {
            $user = $users[(string) ($from['user_id'] ?? '')] ?? [];
            $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            $username = trim((string) ($user['username'] ?? ''));
            return [$key, $name !== '' ? $name : ($username !== '' ? '@' . $username : 'Telegram user')];
        }

        $id = (string) ($from['chat_id'] ?? $from['channel_id'] ?? '');
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
