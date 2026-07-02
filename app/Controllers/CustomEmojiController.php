<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\CustomEmoji;
use App\Models\MessageTemplate;

class CustomEmojiController extends Controller
{
    public function __construct(
        private readonly CustomEmoji $customEmojis = new CustomEmoji(),
        private readonly MessageTemplate $templates = new MessageTemplate()
    ) {
    }

    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $editEmoji = null;
        $editId = (int) $request->query('edit', 0);
        $result = $this->customEmojis->paginateForUser($userId, (int) $request->query('page', 1), pagination_per_page(18, [9, 18, 27, 36, 54]));

        if ($editId > 0) {
            $editEmoji = $this->customEmojis->findForUser($editId, $userId);
        }

        $this->render('custom-emojis/index', [
            'title' => 'Custom Emoji',
            'customEmojis' => $result['items'],
            'editEmoji' => $editEmoji,
            'pagination' => $result['pagination'],
        ]);
    }

    public function store(Request $request): void
    {
        $payload = $this->validatedData($request);
        $userId = (int) auth()->id();

        if ($this->customEmojis->findBySlugForUser($payload['slug'], $userId) !== null) {
            $this->redirectWith('/custom-emojis', error: 'Slug custom emoji đã tồn tại. Hãy chọn slug khác.');
        }

        $this->customEmojis->create(array_merge($payload, [
            'user_id' => $userId,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]));

        $this->redirectWith('/custom-emojis', success: 'Đã thêm custom emoji vào thư viện.');
    }

    public function update(Request $request): void
    {
        $emoji = $this->customEmojis->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($emoji === null) {
            abort404();
        }

        $payload = $this->validatedData($request);
        $userId = (int) auth()->id();
        $existing = $this->customEmojis->findBySlugForUser($payload['slug'], $userId);

        if ($existing !== null && (int) $existing['id'] !== (int) $emoji['id']) {
            $this->redirectWith('/custom-emojis?edit=' . $emoji['id'], error: 'Slug custom emoji đã tồn tại. Hãy chọn slug khác.');
        }

        $this->customEmojis->updateById((int) $emoji['id'], array_merge($payload, [
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]));

        if ((string) $emoji['slug'] !== $payload['slug']) {
            $this->templates->replaceCustomEmojiToken($userId, (string) $emoji['slug'], $payload['slug']);
        }

        $message = (string) $emoji['slug'] !== $payload['slug']
            ? 'Đã cập nhật custom emoji và đồng bộ token mới vào các template đang dùng.'
            : 'Đã cập nhật custom emoji.';

        $this->redirectWith('/custom-emojis', success: $message);
    }

    public function delete(Request $request): void
    {
        $emoji = $this->customEmojis->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($emoji === null) {
            abort404();
        }

        $usageCount = $this->templates->countUsingCustomEmojiToken((int) auth()->id(), (string) $emoji['slug']);

        if ($usageCount > 0) {
            $this->redirectWith(
                '/custom-emojis',
                error: 'Không thể xóa emoji này vì đang có ' . $usageCount . ' template sử dụng token {{ce:' . $emoji['slug'] . '}}.'
            );
        }

        $this->customEmojis->deleteById((int) $emoji['id']);
        $this->redirectWith('/custom-emojis', success: 'Đã xóa custom emoji khỏi thư viện.');
    }

    private function validatedData(Request $request): array
    {
        $name = trim((string) $request->input('name'));
        $slug = $this->normalizeSlug((string) $request->input('slug', $name));
        $emojiIdentifier = preg_replace('/\s+/', '', trim((string) $request->input('emoji_identifier')));
        $fallbackEmoji = trim((string) $request->input('fallback_emoji'));
        $keywords = trim((string) $request->input('keywords'));
        $notes = trim((string) $request->input('notes'));

        if ($name === '' || $slug === '' || $emojiIdentifier === '' || $fallbackEmoji === '') {
            $this->redirectWith('/custom-emojis', error: 'Tên, slug, emoji ID và fallback emoji là bắt buộc.');
        }

        if (!preg_match('/^[0-9]{5,40}$/', $emojiIdentifier)) {
            $this->redirectWith('/custom-emojis', error: 'Emoji ID không hợp lệ. Hãy nhập đúng dãy số Telegram cung cấp.');
        }

        if (!preg_match('/^[a-z0-9._-]+$/', $slug)) {
            $this->redirectWith('/custom-emojis', error: 'Slug chỉ được gồm chữ thường, số, dấu gạch ngang, gạch dưới hoặc dấu chấm.');
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'emoji_identifier' => $emojiIdentifier,
            'fallback_emoji' => $fallbackEmoji,
            'keywords' => $keywords !== '' ? $keywords : null,
            'notes' => $notes !== '' ? $notes : null,
            'is_active' => $request->input('is_active') ? 1 : 0,
        ];
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
