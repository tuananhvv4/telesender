<?php

declare(strict_types=1);

$labelRecords = [];

foreach ($labels as $label) {
    $labelRecords[(int) $label['id']] = [
        'id' => (int) $label['id'],
        'name' => (string) $label['name'],
        'slug' => (string) $label['slug'],
        'color' => (string) ($label['color'] ?? '#111827'),
    ];
}
?>
<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Nhãn tin nhắn</h1>
        <div class="inline-actions">
            <button class="button primary" type="button" id="open_label_create">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Tạo nhãn mới
            </button>
        </div>
    </div>

    <section class="panel" data-live-region="labels-panel">
        <script type="application/json" data-label-records><?= json_encode($labelRecords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
        <div class="panel-header">
            <h2 class="panel-title">Danh sách nhãn</h2>
        </div>
        <div class="panel-body list">
            <?php foreach ($labels as $label): ?>
                <article class="list-item">
                    <div class="inline-actions">
                        <span class="badge" style="background: <?= e($label['color']) ?>20; color: <?= e($label['color']) ?>;">
                            <?= e($label['name']) ?>
                        </span>
                        <span class="small mono"><?= e($label['slug']) ?></span>
                    </div>
                    <div class="inline-actions">
                        <button class="button secondary" type="button" data-label-edit="<?= e((string) $label['id']) ?>">Sửa</button>
                        <form method="post" action="<?= e(url('/labels/delete')) ?>" data-ajax-form data-ajax-refresh="labels-panel">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $label['id']) ?>">
                            <button class="button danger" type="submit">Xóa</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($labels === []): ?>
                <div class="muted">Chưa có nhãn nào.</div>
            <?php endif; ?>
            <?php $perPageOptions = [10, 15, 20, 30, 50, 100]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>
</section>

<template id="label_form_template">
    <form class="form-grid" method="post" action="<?= e(url('/labels')) ?>" data-loading-text="Đang lưu...">
        <?= csrf_field() ?>
        <div class="form-feedback" data-form-feedback hidden></div>
        <div class="field">
            <label for="label_modal_name">Tên nhãn</label>
            <input class="input" id="label_modal_name" type="text" name="name" required>
        </div>
        <div class="field">
            <label for="label_modal_slug">Slug</label>
            <input class="input mono" id="label_modal_slug" type="text" name="slug" placeholder="promo-morning" required>
        </div>
        <div class="field">
            <label for="label_modal_color">Màu hiển thị</label>
            <input class="input mono" id="label_modal_color" type="text" name="color" placeholder="#0f766e">
        </div>
        <div class="actions">
            <button class="button primary" type="submit" data-loading-text="Đang lưu...">Lưu nhãn</button>
            <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
        </div>
    </form>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const template = document.getElementById('label_form_template');
    const createButton = document.getElementById('open_label_create');
    const createUrl = <?= json_encode(url('/labels'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const updateUrl = <?= json_encode(url('/labels/update'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    let labelRecords = window.TeleSenderApp?.readJsonScript('[data-label-records]', {}) || {};

    if (!template || !window.TeleSenderCrudModal || !window.TeleSenderApp) {
        return;
    }

    function buildForm(mode, labelId) {
        const fragment = template.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const form = wrapper.querySelector('form');
        const nameInput = wrapper.querySelector('#label_modal_name');
        const slugInput = wrapper.querySelector('#label_modal_slug');
        const colorInput = wrapper.querySelector('#label_modal_color');

        if (!form || !nameInput || !slugInput || !colorInput) {
            return null;
        }

        if (mode === 'edit') {
            const record = labelRecords[String(labelId)] || null;

            if (!record) {
                window.TeleSenderApp.showFlash('error', 'Không tìm thấy dữ liệu nhãn để chỉnh sửa.');
                return null;
            }

            form.action = updateUrl;
            nameInput.value = record.name || '';
            slugInput.value = record.slug || '';
            colorInput.value = record.color || '#111827';

            const idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'id';
            idField.value = String(record.id || '');
            form.prepend(idField);
        } else {
            form.action = createUrl;
            colorInput.value = '#111827';
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const payload = await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="labels-panel"]'],
            });

            if (!payload) {
                return;
            }
        });

        return wrapper;
    }

    function openLabelModal(mode, labelId = null) {
        const formWrap = buildForm(mode, labelId);

        if (!formWrap) {
            return;
        }

        window.TeleSenderCrudModal.open({
            title: mode === 'edit' ? 'Cập nhật nhãn' : 'Tạo nhãn mới',
            description: mode === 'edit'
                ? 'Chỉnh lại tên, slug hoặc màu hiển thị của nhãn.'
                : 'Tạo nhanh một nhãn để phân loại template và dữ liệu gửi.',
            size: 'md',
            content: formWrap,
        });
    }

    if (createButton) {
        createButton.addEventListener('click', () => {
            openLabelModal('create');
        });
    }

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-label-edit]') : null;

        if (!button) {
            return;
        }

        openLabelModal('edit', button.getAttribute('data-label-edit'));
    });

    document.addEventListener('app:regions:refreshed', () => {
        labelRecords = window.TeleSenderApp.readJsonScript('[data-label-records]', {});
    });
})();
});
</script>
