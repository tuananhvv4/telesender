<?php

declare(strict_types=1);

$sharedEmojiSource = $sharedEmojiSource ?? [];
$sharedCustomEmojis = $sharedCustomEmojis ?? [];
$importRowsState = is_array($importRowsState ?? null) ? array_values($importRowsState) : [];
$importShouldOpen = (bool) ($importShouldOpen ?? false);
$isSuperAdmin = user_is_super_admin();
$emojiRecords = [];

foreach ($customEmojis as $emoji) {
    $emojiRecords[(int) $emoji['id']] = [
        'id' => (int) $emoji['id'],
        'name' => (string) $emoji['name'],
        'slug' => (string) $emoji['slug'],
        'emoji_identifier' => (string) $emoji['emoji_identifier'],
        'fallback_emoji' => (string) $emoji['fallback_emoji'],
        'keywords' => (string) ($emoji['keywords'] ?? ''),
        'notes' => (string) ($emoji['notes'] ?? ''),
        'is_active' => (int) ($emoji['is_active'] ?? 1),
    ];
}
?>
<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Telegram Premium Emoji</h1>
            <h5 class="page-subtitle">Chỉ áp dụng cho tài khoản Telegram có Premium.</h5>
        </div>
        <div class="inline-actions">
            <button class="button primary" type="button" id="open_custom_emoji_create">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Thêm emoji
            </button>
            <button class="button secondary" type="button" data-emoji-import-open>
                <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
                Import nhanh
            </button>
        </div>
    </div>

    <div data-live-region="custom-emojis-shell">
    <script type="application/json" data-emoji-records><?= json_encode($emojiRecords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
    <section class="card">
        <?php if ($isSuperAdmin): ?>
            <h2 class="section-title">Thư viện dùng chung cho admin con</h2>
            <p class="section-copy">
                Các emoji đang bật trong thư viện riêng của bạn sẽ tự xuất hiện dưới dạng tham chiếu đọc-only cho admin con.
                Họ dùng được ngay trong template, nhưng không thể sửa trực tiếp emoji của super admin.
            </p>
            <div class="inline-actions">
                <span class="badge info">Đang chia sẻ <?= e((string) ($sharedEmojiSource['source_count'] ?? 0)) ?> emoji</span>
                <span class="small muted">Nếu admin con tạo emoji riêng trùng slug, emoji riêng của họ sẽ được ưu tiên khi dùng token.</span>
            </div>
        <?php else: ?>
            <h2 class="section-title">Emoji dùng chung từ super admin</h2>
            <?php if (!empty($sharedEmojiSource['source_available'])): ?>
                <p class="section-copy">
                    Bạn có thể dùng trực tiếp <?= e((string) ($sharedEmojiSource['source_count'] ?? 0)) ?> emoji read-only từ
                    <?= e((string) ($sharedEmojiSource['source_user_name'] ?? 'Super admin')) ?>.
                    Nếu cần tuỳ biến riêng, bạn vẫn có thể thêm emoji của mình và hệ thống sẽ ưu tiên emoji riêng khi trùng slug.
                </p>
            <?php else: ?>
                <p class="section-copy">Hiện chưa có thư viện emoji dùng chung nào từ super admin.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="panel listing-panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Thư viện riêng của tôi</h2>
                <p class="panel-copy">Emoji trong thư viện này được phép sửa, bật/tắt hoặc xoá như bình thường.</p>
            </div>
        </div>
        <div class="panel-body emoji-library-grid listing-body">
            <?php foreach ($customEmojis as $emoji): ?>
                <?php
                $noteSummary = trim((string) ($emoji['notes'] ?? ''));
                ?>
                <article class="emoji-library-card">
                    <div class="emoji-library-head">
                        <div class="emoji-library-title">
                            <span class="emoji-library-symbol custom-emoji-preview">
                                <span class="custom-emoji-fallback"><?= e($emoji['fallback_emoji']) ?></span>
                                <img
                                    class="custom-emoji-image"
                                    src="<?= e(url('/custom-emojis/preview?id=' . (int) $emoji['id'])) ?>"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    onload="this.parentElement.classList.add('is-loaded')"
                                    onerror="this.remove()"
                                >
                            </span>
                            <div class="emoji-library-title-copy">
                                <strong><?= e($emoji['name']) ?></strong>
                            </div>
                        </div>
                        <span class="badge <?= (int) $emoji['is_active'] === 1 ? 'success' : 'warning' ?>"><?= (int) $emoji['is_active'] === 1 ? 'Đang bật' : 'Tạm tắt' ?></span>
                    </div>

                    <div class="emoji-library-meta">
                        <span class="emoji-meta-chip">Đang dùng: <?= e((string) ($emoji['usage_count'] ?? 0)) ?></span>
                    </div>

                    <?php if ($noteSummary !== ''): ?>
                        <div class="emoji-library-note small muted" title="<?= e($noteSummary) ?>"><?= e(mb_strimwidth($noteSummary, 0, 88, '...')) ?></div>
                    <?php endif; ?>

                    <div class="inline-actions emoji-library-actions">
                        <button class="button secondary sm" type="button" data-emoji-edit="<?= e((string) $emoji['id']) ?>">Sửa</button>
                        <form method="post" action="<?= e(url('/custom-emojis/delete')) ?>" data-ajax-form data-ajax-refresh="custom-emojis-shell">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $emoji['id']) ?>">
                            <button class="button danger sm" type="submit">Xóa</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if ($customEmojis === []): ?>
                <div class="muted">Chưa có custom emoji riêng nào. Bạn có thể thêm tay hoặc bấm Import nhanh để nhập nhiều dòng.</div>
            <?php endif; ?>
            <?php $perPageOptions = [9, 18, 27, 36, 54]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>

    <?php if (!$isSuperAdmin): ?>
        <section class="panel listing-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Thư viện dùng chung</h2>
                </div>
                <?php if (!empty($sharedEmojiSource['source_available'])): ?>
                    <span class="badge info"><?= e((string) ($sharedEmojiSource['source_count'] ?? 0)) ?> emoji</span>
                <?php endif; ?>
            </div>
            <div class="panel-body emoji-library-grid listing-body">
                <?php foreach ($sharedCustomEmojis as $emoji): ?>
                    <?php
                    $noteSummary = trim((string) ($emoji['notes'] ?? ''));
                    $isOverridden = (int) ($emoji['is_overridden'] ?? 0) === 1;
                    ?>
                    <article class="emoji-library-card">
                        <div class="emoji-library-head">
                            <div class="emoji-library-title">
                                <span class="emoji-library-symbol custom-emoji-preview">
                                    <span class="custom-emoji-fallback"><?= e($emoji['fallback_emoji']) ?></span>
                                    <img
                                        class="custom-emoji-image"
                                        src="<?= e(url('/custom-emojis/preview?id=' . (int) $emoji['id'])) ?>"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        onload="this.parentElement.classList.add('is-loaded')"
                                        onerror="this.remove()"
                                    >
                                </span>
                                <div class="emoji-library-title-copy">
                                    <strong><?= e($emoji['name']) ?></strong>
                                </div>
                            </div>
                            <span class="badge info">Dùng chung</span>
                        </div>

                        <div class="emoji-library-meta">
                            <span class="emoji-meta-chip">Nguồn: <?= e((string) ($emoji['source_user_name'] ?? 'Super admin')) ?></span>
                            <?php if ($isOverridden): ?>
                                <span class="emoji-meta-chip emoji-meta-chip-warning">Đang bị emoji riêng ghi đè</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($noteSummary !== ''): ?>
                            <div class="emoji-library-note small muted" title="<?= e($noteSummary) ?>"><?= e(mb_strimwidth($noteSummary, 0, 88, '...')) ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

                <?php if ($sharedCustomEmojis === []): ?>
                    <div class="muted">
                        <?= !empty($sharedEmojiSource['source_available'])
                            ? 'Super admin chưa có emoji dùng chung nào đang bật.'
                            : 'Hiện chưa có thư viện dùng chung từ super admin.' ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
</section>

<template id="custom_emoji_form_template">
    <form class="form-grid emoji-compact-form" method="post" action="<?= e(url('/custom-emojis')) ?>">
        <?= csrf_field() ?>
        <div class="form-feedback" data-form-feedback hidden></div>

        <div class="emoji-form-core">
            <div class="field emoji-form-name-field">
                <label for="emoji_modal_name">Tên gợi nhớ</label>
                <input class="input" id="emoji_modal_name" type="text" name="name" placeholder="Ví dụ: Fire Cat" required data-emoji-field="name">
            </div>

            <div class="field emoji-form-id-field">
                <label for="emoji_modal_identifier">Emoji ID</label>
                <input class="input mono" id="emoji_modal_identifier" type="text" name="emoji_identifier" placeholder="Lấy ID từ @emojiid_get_bot" required data-emoji-field="emoji_identifier">
            </div>

            <div class="field">
                <label for="emoji_modal_fallback">Icon dự phòng</label>
                <input class="input emoji-fallback-input" id="emoji_modal_fallback" type="text" name="fallback_emoji" placeholder="🔥" required data-emoji-field="fallback_emoji">
            </div>
        </div>

        <label class="checkbox-row">
            <input type="checkbox" name="is_active" value="1" checked data-emoji-field="is_active">
            <span>Kích hoạt emoji này để dùng trong bộ chọn</span>
        </label>

        <details class="emoji-form-advanced">
            <summary>
                <span>Thông tin nâng cao</span>
                <small>Slug, từ khóa và ghi chú</small>
            </summary>
            <div class="emoji-form-advanced-body">
                <div class="emoji-form-advanced-grid">
                    <div class="field">
                        <label for="emoji_modal_slug">Slug nội bộ</label>
                        <input class="input mono" id="emoji_modal_slug" type="text" name="slug" placeholder="Ví dụ: fire-cat" required data-emoji-field="slug" data-touched="0">
                    </div>

                    <div class="field">
                        <label for="emoji_modal_keywords">Từ khóa tìm kiếm</label>
                        <input class="input" id="emoji_modal_keywords" type="text" name="keywords" placeholder="fire, cat, promo" data-emoji-field="keywords">
                    </div>
                </div>

                <div class="field">
                    <label for="emoji_modal_notes">Ghi chú</label>
                    <textarea class="textarea" id="emoji_modal_notes" name="notes" rows="2" placeholder="Mô tả ngắn để dễ nhận biết." data-emoji-field="notes"></textarea>
                </div>
            </div>
        </details>

        <div class="actions">
            <button class="button primary" type="submit" data-loading-text="Đang lưu...">Lưu emoji</button>
            <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
        </div>
    </form>
</template>

<div class="app-modal emoji-import-modal" id="emoji_import_modal" hidden aria-hidden="true">
    <div class="app-modal-backdrop" data-emoji-import-close></div>
    <div class="app-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="emoji_import_title">
        <div class="app-modal-head">
            <div>
                <h2 class="app-modal-title" id="emoji_import_title">Import nhanh custom emoji</h2>
                <div class="small muted">Slug được tự tạo theo tên.</div>
            </div>
            <button class="app-modal-dismiss" type="button" data-emoji-import-close aria-label="Đóng popup">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <form method="post" action="<?= e(url('/custom-emojis/import-bulk')) ?>" id="emoji_import_form">
            <?= csrf_field() ?>
            <div class="app-modal-body emoji-import-body">
                <div class="form-feedback" data-form-feedback hidden></div>

                <div class="table-wrap emoji-import-table-wrap">
                    <table class="emoji-import-table">
                        <colgroup>
                            <col class="emoji-import-col-index">
                            <col>
                            <col class="emoji-import-col-id">
                            <col class="emoji-import-col-fallback">
                            <col class="emoji-import-col-check">
                            <col class="emoji-import-col-action">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Tên gợi nhớ</th>
                            <th>Emoji ID</th>
                            <th>Icon dự phòng</th>
                            <th>Bật</th>
                            <th>Xóa</th>
                        </tr>
                        </thead>
                        <tbody id="emoji_import_rows"></tbody>
                    </table>
                </div>

                <div class="emoji-import-toolbar">
                    <button class="button secondary" id="emoji_import_add_row" type="button">Thêm dòng</button>
                </div>
            </div>

            <div class="app-modal-actions">
                <button class="button secondary" type="button" data-emoji-import-close>Đóng</button>
                <button class="button primary" type="submit" data-loading-text="Đang import...">Import vào hệ thống</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const createButton = document.getElementById('open_custom_emoji_create');
    const formTemplate = document.getElementById('custom_emoji_form_template');
    const importModal = document.getElementById('emoji_import_modal');
    const importForm = document.getElementById('emoji_import_form');
    const importRowsBody = document.getElementById('emoji_import_rows');
    const importAddRowButton = document.getElementById('emoji_import_add_row');
    const createUrl = <?= json_encode(url('/custom-emojis'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const updateUrl = <?= json_encode(url('/custom-emojis/update'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const initialImportRows = <?= json_encode($importRowsState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const importShouldOpen = <?= $importShouldOpen ? 'true' : 'false' ?>;
    let emojiRecords = window.TeleSenderApp?.readJsonScript('[data-emoji-records]', {}) || {};
    let importRowCounter = 0;

    function slugifyEmojiName(value) {
        return String(value)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9._-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function wireEmojiSlugAuto(form) {
        const nameInput = form.querySelector('[data-emoji-field="name"]');
        const slugInput = form.querySelector('[data-emoji-field="slug"]');

        if (!nameInput || !slugInput) {
            return;
        }

        nameInput.addEventListener('input', () => {
            if (slugInput.dataset.touched === '1') {
                return;
            }

            slugInput.value = slugifyEmojiName(nameInput.value);
        });

        slugInput.addEventListener('input', () => {
            slugInput.dataset.touched = slugInput.value.trim() === '' ? '0' : '1';
        });
    }

    function openEmojiModal(mode, emojiId = null) {
        if (!formTemplate || !window.TeleSenderCrudModal || !window.TeleSenderApp) {
            return;
        }

        const fragment = formTemplate.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const form = wrapper.querySelector('form');
        const nameInput = wrapper.querySelector('[data-emoji-field="name"]');
        const slugInput = wrapper.querySelector('[data-emoji-field="slug"]');
        const identifierInput = wrapper.querySelector('[data-emoji-field="emoji_identifier"]');
        const fallbackInput = wrapper.querySelector('[data-emoji-field="fallback_emoji"]');
        const keywordsInput = wrapper.querySelector('[data-emoji-field="keywords"]');
        const notesInput = wrapper.querySelector('[data-emoji-field="notes"]');
        const activeInput = wrapper.querySelector('[data-emoji-field="is_active"]');

        if (!form || !nameInput || !slugInput || !identifierInput || !fallbackInput || !keywordsInput || !notesInput || !activeInput) {
            return;
        }

        if (mode === 'edit') {
            const record = emojiRecords[String(emojiId)] || null;

            if (!record) {
                window.TeleSenderApp.showFlash('error', 'Không tìm thấy emoji để chỉnh sửa.');
                return;
            }

            form.action = updateUrl;
            nameInput.value = record.name || '';
            slugInput.value = record.slug || '';
            slugInput.dataset.touched = record.slug ? '1' : '0';
            identifierInput.value = record.emoji_identifier || '';
            fallbackInput.value = record.fallback_emoji || '🔥';
            keywordsInput.value = record.keywords || '';
            notesInput.value = record.notes || '';
            activeInput.checked = Number(record.is_active || 0) === 1;

            const idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'id';
            idField.value = String(record.id || '');
            form.prepend(idField);
        } else {
            form.action = createUrl;
            fallbackInput.value = '🔥';
        }

        wireEmojiSlugAuto(form);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="custom-emojis-shell"]'],
            });
        });

        window.TeleSenderCrudModal.open({
            title: mode === 'edit' ? 'Cập nhật emoji riêng' : 'Thêm emoji riêng mới',
            description: mode === 'edit'
                ? 'Cập nhật thông tin hiển thị và trạng thái emoji.'
                : 'Thêm emoji để sử dụng trong bộ chọn của mẫu tin nhắn.',
            size: 'md',
            content: wrapper,
        });
    }

    function normalizeImportRow(row = {}) {
        return {
            name: String(row.name || ''),
            slug: String(row.slug || ''),
            emoji_identifier: String(row.emoji_identifier || ''),
            fallback_emoji: String(row.fallback_emoji || '🔥'),
            keywords: String(row.keywords || ''),
            notes: String(row.notes || ''),
            is_active: row.is_active === 0 || row.is_active === '0' || row.is_active === false ? 0 : 1,
        };
    }

    function buildImportRow(row = {}) {
        const item = normalizeImportRow(row);
        const index = importRowCounter++;

        return `
            <tr data-import-row="${index}">
                <td class="emoji-import-index" data-label="Dòng" data-row-number></td>
                <td data-label="Tên gợi nhớ">
                    <input class="input" type="text" name="rows[${index}][name]" value="${escapeHtml(item.name)}" placeholder="Fire Cat" required data-import-name>
                    <input type="hidden" name="rows[${index}][slug]" value="${escapeHtml(item.slug)}" data-import-slug>
                    <input type="hidden" name="rows[${index}][keywords]" value="${escapeHtml(item.keywords)}">
                    <input type="hidden" name="rows[${index}][notes]" value="${escapeHtml(item.notes)}">
                </td>
                <td data-label="Emoji ID"><input class="input mono" type="text" name="rows[${index}][emoji_identifier]" value="${escapeHtml(item.emoji_identifier)}" placeholder="5318779098686826724" required></td>
                <td data-label="Icon dự phòng"><input class="input emoji-import-fallback" type="text" name="rows[${index}][fallback_emoji]" value="${escapeHtml(item.fallback_emoji)}" placeholder="🔥" required data-import-fallback></td>
                <td class="emoji-import-check-cell" data-label="Bật">
                    <input type="checkbox" name="rows[${index}][is_active]" value="1" ${item.is_active ? 'checked' : ''} data-import-active>
                </td>
                <td class="emoji-import-action-cell" data-label="Xóa">
                    <button class="button danger sm" type="button" data-import-remove-row>Xóa</button>
                </td>
            </tr>
        `;
    }

    function renumberImportRows() {
        if (!importRowsBody) {
            return;
        }

        Array.from(importRowsBody.querySelectorAll('tr')).forEach((row, index) => {
            const numberCell = row.querySelector('[data-row-number]');

            if (numberCell) {
                numberCell.textContent = String(index + 1);
            }
        });
    }

    function addImportRow(row = {}) {
        if (!importRowsBody) {
            return;
        }

        importRowsBody.insertAdjacentHTML('beforeend', buildImportRow(row));
        renumberImportRows();
    }

    function resetImportRow(row) {
        row.querySelectorAll('input:not([type="checkbox"]), textarea').forEach((field) => {
            field.value = '';
        });

        const activeField = row.querySelector('[data-import-active]');
        if (activeField) {
            activeField.checked = true;
        }

        const fallbackField = row.querySelector('[data-import-fallback]');
        if (fallbackField) {
            fallbackField.value = '🔥';
        }
    }

    function ensureImportRows() {
        if (!importRowsBody) {
            return;
        }

        if (!importRowsBody.querySelector('tr')) {
            addImportRow();
            return;
        }

        renumberImportRows();
    }

    function openImportModal() {
        if (!importModal) {
            return;
        }

        ensureImportRows();
        importModal.hidden = false;
        importModal.setAttribute('aria-hidden', 'false');
        window.TeleSenderModalState?.lock();
    }

    function closeImportModal() {
        if (!importModal) {
            return;
        }

        importModal.hidden = true;
        importModal.setAttribute('aria-hidden', 'true');
        window.TeleSenderModalState?.unlock();
    }

    createButton?.addEventListener('click', () => {
        openEmojiModal('create');
    });

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const editButton = target ? target.closest('[data-emoji-edit]') : null;

        if (editButton) {
            openEmojiModal('edit', editButton.getAttribute('data-emoji-edit'));
        }
    });

    document.querySelectorAll('[data-emoji-import-open]').forEach((button) => {
        button.addEventListener('click', openImportModal);
    });

    document.querySelectorAll('[data-emoji-import-close]').forEach((button) => {
        button.addEventListener('click', closeImportModal);
    });

    importAddRowButton?.addEventListener('click', () => {
        addImportRow();
    });

    importRowsBody?.addEventListener('click', (event) => {
        const target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        const removeButton = target.closest('[data-import-remove-row]');
        if (!removeButton) {
            return;
        }

        const row = removeButton.closest('tr');
        if (!row || !importRowsBody) {
            return;
        }

        const rows = importRowsBody.querySelectorAll('tr');

        if (rows.length > 1) {
            row.remove();
        } else {
            resetImportRow(row);
        }

        renumberImportRows();
    });

    importRowsBody?.addEventListener('input', (event) => {
        const target = event.target;
        if (!target || typeof target.matches !== 'function') {
            return;
        }

        if (target.matches('[data-import-name]')) {
            const row = target.closest('tr');
            const slugField = row ? row.querySelector('[data-import-slug]') : null;

            if (slugField) {
                slugField.value = slugifyEmojiName(target.value);
            }
        }
    });

    importForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        await window.TeleSenderApp.submitAjaxForm(importForm, {
            closeOnSuccess() {
                closeImportModal();
            },
            refreshRegionsOnSuccess: ['[data-live-region="custom-emojis-shell"]'],
        });
    });

    document.addEventListener('app:regions:refreshed', () => {
        emojiRecords = window.TeleSenderApp.readJsonScript('[data-emoji-records]', {});
    });

    if (Array.isArray(initialImportRows) && initialImportRows.length > 0) {
        initialImportRows.forEach((row) => addImportRow(row));
    } else {
        ensureImportRows();
    }

    if (importShouldOpen) {
        openImportModal();
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && importModal && !importModal.hidden) {
            closeImportModal();
        }
    });
})();
});
</script>
