<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\MessageLabel;

class LabelController extends Controller
{
    public function __construct(private readonly MessageLabel $labels = new MessageLabel())
    {
    }

    public function index(Request $request): void
    {
        $userId = (int) auth()->id();
        $editLabel = null;
        $editId = (int) $request->query('edit', 0);
        $result = $this->labels->paginateForUser($userId, (int) $request->query('page', 1), pagination_per_page(20));

        if ($editId > 0) {
            $editLabel = $this->labels->findForUser($editId, $userId);
        }

        $this->render('labels/index', [
            'title' => 'Message Labels',
            'labels' => $result['items'],
            'editLabel' => $editLabel,
            'pagination' => $result['pagination'],
        ]);
    }

    public function store(Request $request): void
    {
        $name = trim((string) $request->input('name'));
        $slug = trim((string) $request->input('slug'));

        if ($name === '' || $slug === '') {
            $this->redirectWith('/labels', error: 'Tên và slug của label là bắt buộc.');
        }

        $this->labels->create([
            'user_id' => (int) auth()->id(),
            'name' => $name,
            'slug' => $slug,
            'color' => trim((string) $request->input('color', '#111827')),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/labels', success: 'Đã tạo label.');
    }

    public function update(Request $request): void
    {
        $label = $this->labels->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($label === null) {
            abort404();
        }

        $name = trim((string) $request->input('name'));
        $slug = trim((string) $request->input('slug'));

        if ($name === '' || $slug === '') {
            $this->redirectWith('/labels?edit=' . $label['id'], error: 'Tên và slug của label là bắt buộc.');
        }

        $this->labels->updateById((int) $label['id'], [
            'name' => $name,
            'slug' => $slug,
            'color' => trim((string) $request->input('color', '#111827')),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->redirectWith('/labels', success: 'Đã cập nhật label.');
    }

    public function delete(Request $request): void
    {
        $label = $this->labels->findForUser((int) $request->input('id'), (int) auth()->id());

        if ($label === null) {
            abort404();
        }

        $this->labels->deleteById((int) $label['id']);
        $this->redirectWith('/labels', success: 'Đã xóa label.');
    }
}
