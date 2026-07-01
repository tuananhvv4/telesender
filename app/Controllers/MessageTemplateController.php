<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\MessageLabel;
use App\Models\MessageTemplate;
use App\Services\PresetService;

class MessageTemplateController extends Controller
{
    public function __construct(
        private readonly MessageTemplate $templates = new MessageTemplate(),
        private readonly MessageLabel $labels = new MessageLabel()
    ) {
    }

    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $editTemplate = null;
        $editId = (int) $request->query('edit', 0);

        if ($editId > 0) {
            $editTemplate = $this->templates->findForUser($editId, $userId);
        }

        $this->render('templates/index', [
            'title' => 'Message Templates',
            'templates' => $this->templates->listForUser($userId),
            'labels' => $this->labels->allByUser($userId, 'name ASC'),
            'editTemplate' => $editTemplate,
            'templatePresets' => (new PresetService(app()->db()))->templatePresets(),
        ]);
    }

    public function store(Request $request): void
    {
        $name = trim((string) $request->input('name'));
        $body = trim((string) $request->input('body'));

        if ($name === '' || $body === '') {
            $this->redirectWith('/templates', error: 'Template name và nội dung tin nhắn là bắt buộc.');
        }

        $this->templates->create([
            'user_id' => (int) auth()->id(),
            'label_id' => $request->input('label_id') ? (int) $request->input('label_id') : null,
            'name' => $name,
            'body' => $body,
            'parse_mode' => trim((string) $request->input('parse_mode', 'HTML')),
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
            $this->redirectWith('/templates?edit=' . $template['id'], error: 'Template name và nội dung là bắt buộc.');
        }

        $this->templates->updateById((int) $template['id'], [
            'label_id' => $request->input('label_id') ? (int) $request->input('label_id') : null,
            'name' => $name,
            'body' => $body,
            'parse_mode' => trim((string) $request->input('parse_mode', 'HTML')),
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
}
