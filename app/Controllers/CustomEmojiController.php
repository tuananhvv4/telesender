<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\CustomEmoji;
use App\Models\MessageTemplate;
use App\Services\SharedCustomEmojiService;
use RuntimeException;

class CustomEmojiController extends Controller
{
    public function __construct(
        private readonly CustomEmoji $customEmojis = new CustomEmoji(),
        private readonly MessageTemplate $templates = new MessageTemplate(),
        private readonly SharedCustomEmojiService $sharedEmojis = new SharedCustomEmojiService()
    ) {
    }

    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $result = $this->customEmojis->paginateForUser(
            $userId,
            (int) $request->query('page', 1),
            pagination_per_page(18, [9, 18, 27, 36, 54])
        );

        $this->render('custom-emojis/index', [
            'title' => 'Custom Emoji',
            'customEmojis' => $result['items'],
            'pagination' => $result['pagination'],
            'sharedEmojiSource' => $this->sharedEmojis->sourceSummaryForUser($userId),
            'sharedCustomEmojis' => $this->sharedEmojis->sharedActiveForUser($userId),
            'importRowsState' => flash('custom_emoji_import_rows') ?? [],
            'importShouldOpen' => (bool) (flash('custom_emoji_import_open') ?? false),
        ]);
    }

    public function bulkImport(Request $request): void
    {
        $rawRows = $request->input('rows', []);
        $userId = (int) auth()->id();

        if (!is_array($rawRows)) {
            if (!request()->expectsJson()) {
                Session::flash('custom_emoji_import_open', true);
            }
            $this->redirectWith('/custom-emojis', error: 'Dữ liệu import không hợp lệ.');
        }

        $normalizedRows = [];
        foreach ($rawRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalizedRows[] = $this->normalizedPayload($row);
        }

        $existingSlugs = $this->ownedSlugSet($userId);
        $batchSlugs = [];
        $payloads = [];

        foreach ($normalizedRows as $index => $row) {
            if ($this->isPayloadBlank($row)) {
                continue;
            }

            try {
                $payload = $this->validatePayload($row, $index + 1);
            } catch (RuntimeException $exception) {
                $this->flashImportState($normalizedRows);
                $this->redirectWith('/custom-emojis', error: $exception->getMessage());
            }

            $slug = strtolower((string) $payload['slug']);

            if (isset($existingSlugs[$slug])) {
                $this->flashImportState($normalizedRows);
                $this->redirectWith('/custom-emojis', error: 'Dòng ' . ($index + 1) . ': Slug custom emoji đã tồn tại trong thư viện riêng của bạn.');
            }

            if (isset($batchSlugs[$slug])) {
                $this->flashImportState($normalizedRows);
                $this->redirectWith(
                    '/custom-emojis',
                    error: 'Dòng ' . ($index + 1) . ': Slug bị trùng với dòng ' . $batchSlugs[$slug] . ' trong cùng lần import.'
                );
            }

            $batchSlugs[$slug] = $index + 1;
            $payloads[] = $payload;
        }

        if ($payloads === []) {
            $this->flashImportState($normalizedRows);
            $this->redirectWith('/custom-emojis', error: 'Bạn cần nhập ít nhất 1 dòng hợp lệ để import.');
        }

        app()->db()->transaction(function () use ($payloads, $userId): void {
            $timestamp = gmdate('Y-m-d H:i:s');

            foreach ($payloads as $payload) {
                $this->customEmojis->create(array_merge($payload, [
                    'user_id' => $userId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]));
            }
        });

        $this->redirectWith('/custom-emojis', success: 'Đã import ' . count($payloads) . ' custom emoji vào hệ thống.');
    }

    public function store(Request $request): void
    {
        try {
            $payload = $this->validatePayload($this->normalizedPayload($request->all()));
        } catch (RuntimeException $exception) {
            $this->redirectWith('/custom-emojis', error: $exception->getMessage());
        }

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

        try {
            $payload = $this->validatePayload($this->normalizedPayload($request->all()));
        } catch (RuntimeException $exception) {
            $this->redirectWith('/custom-emojis', error: $exception->getMessage());
        }

        $userId = (int) auth()->id();
        $existing = $this->customEmojis->findBySlugForUser($payload['slug'], $userId);

        if ($existing !== null && (int) $existing['id'] !== (int) $emoji['id']) {
            $this->redirectWith('/custom-emojis', error: 'Slug custom emoji đã tồn tại. Hãy chọn slug khác.');
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

    private function normalizedPayload(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = $this->normalizeSlug((string) ($input['slug'] ?? $name));
        $emojiIdentifier = preg_replace('/\s+/', '', trim((string) ($input['emoji_identifier'] ?? ''))) ?? '';
        $fallbackEmoji = trim((string) ($input['fallback_emoji'] ?? ''));
        $keywords = trim((string) ($input['keywords'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        return [
            'name' => $name,
            'slug' => $slug,
            'emoji_identifier' => $emojiIdentifier,
            'fallback_emoji' => $fallbackEmoji,
            'keywords' => $keywords !== '' ? $keywords : null,
            'notes' => $notes !== '' ? $notes : null,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];
    }

    private function validatePayload(array $payload, ?int $rowNumber = null): array
    {
        $prefix = $rowNumber !== null ? 'Dòng ' . $rowNumber . ': ' : '';
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));
        $emojiIdentifier = trim((string) ($payload['emoji_identifier'] ?? ''));
        $fallbackEmoji = trim((string) ($payload['fallback_emoji'] ?? ''));

        if ($name === '' || $slug === '' || $emojiIdentifier === '' || $fallbackEmoji === '') {
            throw new RuntimeException($prefix . 'Tên, slug, emoji ID và fallback emoji là bắt buộc.');
        }

        if (!preg_match('/^[0-9]{5,40}$/', $emojiIdentifier)) {
            throw new RuntimeException($prefix . 'Emoji ID không hợp lệ. Hãy nhập đúng dãy số Telegram cung cấp.');
        }

        if (!preg_match('/^[a-z0-9._-]+$/', $slug)) {
            throw new RuntimeException($prefix . 'Slug chỉ được gồm chữ thường, số, dấu gạch ngang, gạch dưới hoặc dấu chấm.');
        }

        return $payload;
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function isPayloadBlank(array $payload): bool
    {
        return trim((string) ($payload['name'] ?? '')) === ''
            && trim((string) ($payload['slug'] ?? '')) === ''
            && trim((string) ($payload['emoji_identifier'] ?? '')) === ''
            && trim((string) ($payload['fallback_emoji'] ?? '')) === ''
            && trim((string) ($payload['keywords'] ?? '')) === ''
            && trim((string) ($payload['notes'] ?? '')) === '';
    }

    /**
     * @return array<string, bool>
     */
    private function ownedSlugSet(int $userId): array
    {
        $set = [];

        foreach ($this->customEmojis->allByUser($userId, 'id DESC') as $emoji) {
            $slug = strtolower((string) ($emoji['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $set[$slug] = true;
        }

        return $set;
    }

    private function flashImportState(array $rows): void
    {
        if (request()->expectsJson()) {
            return;
        }

        Session::flash('custom_emoji_import_rows', $rows);
        Session::flash('custom_emoji_import_open', true);
    }
}
