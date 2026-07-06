<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\MessageLabel;
use App\Models\MessageTemplate;
use App\Services\CustomEmojiService;
use App\Services\PresetService;

class MessageTemplateController extends Controller
{
    public function __construct(
        private readonly MessageTemplate $templates = new MessageTemplate(),
        private readonly MessageLabel $labels = new MessageLabel(),
        private readonly CustomEmojiService $customEmojiService = new CustomEmojiService()
    ) {
    }

    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $searchQuery = trim((string) $request->query('q', ''));
        $perPage = pagination_per_page(15, [10, 15, 20, 30, 50]);
        $result = $this->templates->paginateForUser($userId, (int) $request->query('page', 1), $perPage, $searchQuery);
        $templates = $result['items'];

        $this->render('templates/index', [
            'title' => 'Message Templates',
            'templates' => $templates,
            'labels' => $this->labels->allByUser($userId, 'name ASC'),
            'templatePresets' => (new PresetService(app()->db()))->templatePresets(),
            'customEmojis' => $this->customEmojiService->pickerLibrary($userId),
            'templatePreviewBodies' => $this->previewBodies($templates, $userId),
            'pagination' => $result['pagination'],
            'searchQuery' => $searchQuery,
        ]);
    }

    public function store(Request $request): void
    {
        $name = trim((string) $request->input('name'));
        $body = trim((string) $request->input('body'));

        if ($name === '' || $body === '') {
            $this->redirectWith('/templates', error: 'Template name và nội dung tin nhắn là bắt buộc.');
        }

        $parseMode = trim((string) $request->input('parse_mode', 'HTML'));

        try {
            $this->customEmojiService->ensureTemplateIsValid($body, $parseMode, (int) auth()->id());
        } catch (\Throwable $exception) {
            $this->redirectWith('/templates', error: $exception->getMessage());
        }

        $this->templates->create([
            'user_id' => (int) auth()->id(),
            'label_id' => $request->input('label_id') ? (int) $request->input('label_id') : null,
            'name' => $name,
            'body' => $body,
            'parse_mode' => $parseMode,
            'is_active' => $request->input('is_active') ? 1 : 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/templates', success: 'Đã tạo message template.');
    }

    public function update(Request $request): void
    {
        $template = $this->templates->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($template === null) {
            abort404();
        }

        $name = trim((string) $request->input('name'));
        $body = trim((string) $request->input('body'));

        if ($name === '' || $body === '') {
            $this->redirectWith('/templates', error: 'Template name và nội dung là bắt buộc.');
        }

        $parseMode = trim((string) $request->input('parse_mode', 'HTML'));

        try {
            $this->customEmojiService->ensureTemplateIsValid($body, $parseMode, (int) auth()->id());
        } catch (\Throwable $exception) {
            $this->redirectWith('/templates', error: $exception->getMessage());
        }

        $this->templates->updateById((int) $template['id'], [
            'label_id' => $request->input('label_id') ? (int) $request->input('label_id') : null,
            'name' => $name,
            'body' => $body,
            'parse_mode' => $parseMode,
            'is_active' => $request->input('is_active') ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/templates', success: 'Đã cập nhật message template.');
    }

    public function delete(Request $request): void
    {
        $template = $this->templates->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($template === null) {
            abort404();
        }

        $this->templates->deleteById((int) $template['id']);
        $this->redirectWith('/templates', success: 'Đã xóa message template.');
    }

    public function preview(Request $request): void
    {
        $body = (string) $request->input('body', '');
        $parseMode = trim((string) $request->input('parse_mode', 'HTML'));
        $analysis = $this->customEmojiService->analyzeTemplate($body, $parseMode, (int) auth()->id());

        Response::json([
            'ok' => true,
            'issues' => $analysis['issues'],
            'compiled_html' => $analysis['compiled_html'],
            'fallback_preview' => $analysis['fallback_preview'],
            'used_emojis' => $analysis['used_emojis'],
            'requires_html' => $analysis['requires_html'],
        ]);
    }

    private function previewBodies(array $templates, int $userId): array
    {
        $previews = [];

        foreach ($templates as $template) {
            $previews[(int) $template['id']] = $this->customEmojiService->replaceTokensWithFallback(
                (string) ($template['body'] ?? ''),
                $userId
            );
        }

        return $previews;
    }
}
