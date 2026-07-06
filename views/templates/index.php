<?php

declare(strict_types=1);

$templateRecords = [];

foreach ($templates as $template) {
    $templateRecords[(int) $template['id']] = [
        'id' => (int) $template['id'],
        'name' => (string) $template['name'],
        'label_id' => isset($template['label_id']) && $template['label_id'] !== null ? (int) $template['label_id'] : null,
        'body' => (string) $template['body'],
        'parse_mode' => (string) ($template['parse_mode'] ?? 'HTML'),
        'is_active' => (int) ($template['is_active'] ?? 1),
    ];
}
?>
<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Mẫu tin nhắn</h1>
        <div class="inline-actions">
            <button class="button primary" type="button" id="open_template_create">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Tạo mẫu mới
            </button>
        </div>
    </div>

    <section class="panel template-library-panel" data-live-region="templates-panel">
        <script type="application/json" data-template-records><?= json_encode($templateRecords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Danh sách mẫu tin nhắn</h2>
            </div>
            <form class="toolbar-form" method="get" action="<?= e(url('/templates')) ?>">
                <?php if ((int) request()->query('per_page', 0) > 0): ?>
                    <input type="hidden" name="per_page" value="<?= e((string) request()->query('per_page')) ?>">
                <?php endif; ?>
                <div class="toolbar-search">
                    <input class="input" type="text" name="q" value="<?= e($searchQuery ?? '') ?>" placeholder="Tìm theo tên mẫu, nội dung, nhãn, chế độ parse...">
                    <button class="button secondary" type="submit">Lọc</button>
                    <?php if (($searchQuery ?? '') !== ''): ?>
                        <a class="button secondary" href="<?= e(url('/templates')) ?>">Xóa lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="panel-body list">
            <?php foreach ($templates as $template): ?>
                <article class="list-item template-library-item">
                    <div class="template-library-head">
                        <div>
                            <strong><?= e($template['name']) ?></strong>
                            <div class="small muted mono"><?= e($template['parse_mode']) ?></div>
                        </div>
                        <div class="inline-actions">
                            <?php if (!empty($template['label_name'])): ?>
                                <span class="badge"><?= e($template['label_name']) ?></span>
                            <?php endif; ?>
                            <span class="badge <?= (int) $template['is_active'] === 1 ? 'success' : 'warning' ?>"><?= (int) $template['is_active'] === 1 ? 'Đang bật' : 'Tạm tắt' ?></span>
                        </div>
                    </div>
                    <p class="template-library-preview"><?= nl2br(e(mb_substr($templatePreviewBodies[(int) $template['id']] ?? $template['body'], 0, 240))) ?></p>
                    <div class="inline-actions">
                        <button class="button secondary" type="button" data-template-edit="<?= e((string) $template['id']) ?>">Sửa</button>
                        <form method="post" action="<?= e(url('/templates/delete')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $template['id']) ?>">
                            <button class="button danger" type="submit">Xóa</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($templates === []): ?>
                <div class="muted">Chưa có mẫu tin nhắn nào.</div>
            <?php endif; ?>
            <?php $perPageOptions = [10, 15, 20, 30, 50]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>
</section>

<template id="template_editor_template">
    <div class="grid grid-2 template-workspace">
        <section class="card template-editor-card">
            <section class="builder-block template-preset-block">
                <div class="builder-block-head">
                    <div>
                        <h3 class="builder-block-title">Preset nhanh</h3>
                    </div>
                </div>
                <div class="field">
                    <label for="template_modal_preset">Mẫu cài sẵn</label>
                    <select class="select" id="template_modal_preset" data-template-preset>
                        <option value="">Chọn mẫu cài sẵn để tự động điền form</option>
                        <?php foreach ($templatePresets as $preset): ?>
                            <option value="<?= e($preset['key']) ?>"><?= e($preset['name']) ?> · <?= e($preset['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="chip-row">
                    <?php foreach (array_slice($templatePresets, 0, 5) as $preset): ?>
                        <button class="chip" type="button" data-template-chip="<?= e($preset['key']) ?>"><?= e($preset['name']) ?></button>
                    <?php endforeach; ?>
                </div>
            </section>

            <form class="form-grid" method="post" action="<?= e(url('/templates')) ?>" data-template-form>
                <?= csrf_field() ?>
                <div class="form-feedback" data-form-feedback hidden></div>

                <div class="template-form-meta">
                    <div class="field">
                        <label for="template_modal_name">Tên mẫu tin nhắn</label>
                        <input class="input" id="template_modal_name" type="text" name="name" value="" required data-template-name>
                    </div>
                    <div class="field">
                        <label for="template_modal_label_id">Nhãn</label>
                        <select class="select" id="template_modal_label_id" name="label_id" data-template-label>
                            <option value="">Không gắn nhãn</option>
                            <?php foreach ($labels as $label): ?>
                                <option value="<?= e((string) $label['id']) ?>"><?= e($label['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field template-parse-field">
                        <label for="template_modal_parse_mode">Chế độ parse</label>
                        <select class="select" id="template_modal_parse_mode" name="parse_mode" data-template-parse-mode>
                            <?php foreach (['HTML', 'Markdown', 'TEXT'] as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= $mode === 'HTML' ? 'selected' : '' ?>><?= e($mode) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="small muted">Dùng <span class="mono">HTML</span> để gửi custom emoji Premium.</div>
                    </div>
                </div>

                <div class="field template-editor-field">
                    <label for="template_modal_body">Nội dung</label>
                    <textarea class="textarea template-editor-textarea" id="template_modal_body" name="body" required data-template-body></textarea>
                </div>

                <section class="builder-block template-emoji-studio">
                    <div class="builder-block-head">
                        <div>
                            <h3 class="builder-block-title">Chọn nhanh Premium Emoji</h3>
                        </div>
                        <a class="button secondary" href="<?= e(url('/custom-emojis')) ?>">Quản lý thư viện</a>
                    </div>

                    <?php if ($customEmojis !== []): ?>
                        <div class="field">
                            <label for="template_modal_emoji_search">Tìm Premium Emoji</label>
                            <input class="input" id="template_modal_emoji_search" type="text" placeholder="Tìm theo tên, slug hoặc từ khóa..." data-emoji-picker-search>
                        </div>

                        <div class="chip-row">
                            <button class="chip active" type="button" data-emoji-filter="all">Tất cả</button>
                            <button class="chip" type="button" data-emoji-filter="favorites">Yêu thích</button>
                            <button class="chip" type="button" data-emoji-filter="recent">Gần đây</button>
                        </div>

                        <div class="template-emoji-grid" data-template-emoji-grid></div>
                    <?php else: ?>
                        <div class="small muted">Chưa có emoji tùy chỉnh trong thư viện.</div>
                    <?php endif; ?>

                    <div class="field" hidden data-template-used-tokens-field>
                        <label>Emoji đang dùng</label>
                        <div class="chip-row" data-template-used-tokens></div>
                    </div>
                </section>
                <label class="checkbox-row">
                    <input type="checkbox" name="is_active" value="1" checked data-template-active>
                    <span>Cho phép sử dụng mẫu tin nhắn này</span>
                </label>
                <div class="actions">
                    <button class="button primary" type="submit" data-template-submit data-loading-text="Đang lưu...">Lưu mẫu</button>
                    <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
                </div>
            </form>
        </section>

        <section class="card template-preview-card">
            <div class="builder-block-head">
                <div>
                    <h2 class="section-title">Xem trước khi gửi</h2>
                </div>
            </div>

            <div class="list template-preview-list">
                <div class="list-item" hidden data-template-preview-issues-item>
                    <strong>Lưu ý</strong>
                    <div class="stack" data-template-preview-issues></div>
                </div>
                <div class="list-item">
                    <strong>Xem trước nội dung</strong>
                    <div class="template-preview-surface" data-template-preview-surface>Nhập nội dung hoặc chèn emoji tùy chỉnh để xem trước.</div>
                </div>
                <div class="list-item">
                    <strong>HTML gửi lên Telegram</strong>
                    <pre class="template-preview-code mono" data-template-preview-compiled>-</pre>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const templatePresets = <?= json_encode($templatePresets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const labelOptions = <?= json_encode(array_map(static fn ($label) => ['id' => $label['id'], 'slug' => $label['slug']], $labels), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const customEmojiLibrary = <?= json_encode(array_map(static fn ($emoji) => [
        'id' => $emoji['id'],
        'name' => $emoji['name'],
        'slug' => $emoji['slug'],
        'emoji_identifier' => $emoji['emoji_identifier'],
        'fallback_emoji' => $emoji['fallback_emoji'],
        'keywords' => $emoji['keywords'] ?? '',
        'notes' => $emoji['notes'] ?? '',
        'library_scope' => $emoji['library_scope'] ?? 'owned',
        'scope_label' => $emoji['scope_label'] ?? 'Riêng',
        'source_user_name' => $emoji['source_user_name'] ?? '',
        'token' => '{{ce:' . $emoji['slug'] . '}}',
    ], $customEmojis), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const previewUrl = <?= json_encode(url('/templates/preview'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const createUrl = <?= json_encode(url('/templates'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const updateUrl = <?= json_encode(url('/templates/update'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const createButton = document.getElementById('open_template_create');
    const editorTemplate = document.getElementById('template_editor_template');
    let templateRecords = window.TeleSenderApp?.readJsonScript('[data-template-records]', {}) || {};

    if (!editorTemplate || !window.TeleSenderCrudModal || !window.TeleSenderApp) {
        return;
    }

    const favoriteStorageKey = 'tele_sender_custom_emoji_favorites';
    const recentStorageKey = 'tele_sender_custom_emoji_recent';

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function readStoredList(key) {
        try {
            const parsed = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(parsed) ? parsed.filter((item) => typeof item === 'string') : [];
        } catch (error) {
            return [];
        }
    }

    function writeStoredList(key, values) {
        localStorage.setItem(key, JSON.stringify(values.slice(0, 24)));
    }

    function getFavorites() {
        return readStoredList(favoriteStorageKey);
    }

    function getRecents() {
        return readStoredList(recentStorageKey);
    }

    function openTemplateModal(mode, templateId = null) {
        const fragment = editorTemplate.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const form = wrapper.querySelector('[data-template-form]');
        const nameInput = wrapper.querySelector('[data-template-name]');
        const bodyInput = wrapper.querySelector('[data-template-body]');
        const parseModeInput = wrapper.querySelector('[data-template-parse-mode]');
        const labelInput = wrapper.querySelector('[data-template-label]');
        const activeInput = wrapper.querySelector('[data-template-active]');
        const submitButton = wrapper.querySelector('[data-template-submit]');
        const templatePresetSelect = wrapper.querySelector('[data-template-preset]');
        const emojiPickerSearch = wrapper.querySelector('[data-emoji-picker-search]');
        const emojiGrid = wrapper.querySelector('[data-template-emoji-grid]');
        const usedTokensWrap = wrapper.querySelector('[data-template-used-tokens]');
        const usedTokensField = wrapper.querySelector('[data-template-used-tokens-field]');
        const previewIssuesWrap = wrapper.querySelector('[data-template-preview-issues]');
        const previewIssuesItem = wrapper.querySelector('[data-template-preview-issues-item]');
        const previewSurface = wrapper.querySelector('[data-template-preview-surface]');
        const previewCompiled = wrapper.querySelector('[data-template-preview-compiled]');
        const emojiFilterButtons = wrapper.querySelectorAll('[data-emoji-filter]');
        const templateChipButtons = wrapper.querySelectorAll('[data-template-chip]');
        let templatePreviewTimer = null;
        let activeEmojiFilter = 'all';

        if (
            !form || !nameInput || !bodyInput || !parseModeInput || !labelInput || !activeInput || !submitButton
            || !usedTokensWrap || !usedTokensField || !previewIssuesWrap || !previewIssuesItem || !previewSurface || !previewCompiled
        ) {
            return;
        }

        if (mode === 'edit') {
            const record = templateRecords[String(templateId)] || null;

            if (!record) {
                window.TeleSenderApp.showFlash('error', 'Không tìm thấy template để chỉnh sửa.');
                return;
            }

            form.action = updateUrl;
            nameInput.value = record.name || '';
            bodyInput.value = record.body || '';
            parseModeInput.value = record.parse_mode || 'HTML';
            labelInput.value = record.label_id !== null && record.label_id !== undefined ? String(record.label_id) : '';
            activeInput.checked = Number(record.is_active || 0) === 1;
            submitButton.textContent = 'Cập nhật mẫu';

            const idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'id';
            idField.value = String(record.id || '');
            form.prepend(idField);
        } else {
            form.action = createUrl;
            nameInput.value = '';
            bodyInput.value = '';
            parseModeInput.value = 'HTML';
            labelInput.value = '';
            activeInput.checked = true;
            submitButton.textContent = 'Tạo mẫu';
        }

        function toggleFavorite(slug) {
            const favorites = getFavorites();
            const index = favorites.indexOf(slug);

            if (index >= 0) {
                favorites.splice(index, 1);
            } else {
                favorites.unshift(slug);
            }

            writeStoredList(favoriteStorageKey, favorites);
            syncEmojiTileState();
            renderEmojiGrid();
        }

        function pushRecent(slug) {
            const recents = getRecents().filter((item) => item !== slug);
            recents.unshift(slug);
            writeStoredList(recentStorageKey, recents);
        }

        function syncEmojiTileState() {
            const favorites = getFavorites();

            wrapper.querySelectorAll('[data-toggle-favorite]').forEach((star) => {
                const slug = star.getAttribute('data-toggle-favorite');
                const active = favorites.includes(slug);
                star.textContent = active ? '★' : '☆';
                star.classList.toggle('active', active);
            });

            emojiFilterButtons.forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-emoji-filter') === activeEmojiFilter);
            });
        }

        function filteredEmojiLibrary() {
            const search = (emojiPickerSearch?.value || '').trim().toLowerCase();
            const favorites = getFavorites();
            const recents = getRecents();

            return customEmojiLibrary.filter((emoji) => {
                const haystack = [emoji.name, emoji.slug, emoji.keywords, emoji.notes, emoji.scope_label, emoji.source_user_name].join(' ').toLowerCase();
                const matchesSearch = search === '' || haystack.includes(search);

                if (!matchesSearch) {
                    return false;
                }

                if (activeEmojiFilter === 'favorites') {
                    return favorites.includes(String(emoji.slug));
                }

                if (activeEmojiFilter === 'recent') {
                    return recents.includes(String(emoji.slug));
                }

                return true;
            }).sort((left, right) => {
                const recentsOrder = getRecents();
                const leftRecentIndex = recentsOrder.indexOf(String(left.slug));
                const rightRecentIndex = recentsOrder.indexOf(String(right.slug));

                if (activeEmojiFilter === 'recent') {
                    return (leftRecentIndex === -1 ? 999 : leftRecentIndex) - (rightRecentIndex === -1 ? 999 : rightRecentIndex);
                }

                return String(left.name).localeCompare(String(right.name));
            });
        }

        function insertTokenAtCursor(token) {
            const start = bodyInput.selectionStart ?? bodyInput.value.length;
            const end = bodyInput.selectionEnd ?? bodyInput.value.length;
            const before = bodyInput.value.slice(0, start);
            const after = bodyInput.value.slice(end);
            bodyInput.value = before + token + after;
            const nextCursor = start + token.length;
            bodyInput.focus();
            bodyInput.setSelectionRange(nextCursor, nextCursor);
        }

        function bindEmojiGridEvents() {
            wrapper.querySelectorAll('.template-emoji-tile').forEach((button) => {
                button.addEventListener('click', (event) => {
                    const favoriteButton = event.target.closest('[data-toggle-favorite]');
                    if (favoriteButton) {
                        event.preventDefault();
                        event.stopPropagation();
                        toggleFavorite(favoriteButton.getAttribute('data-toggle-favorite'));
                        return;
                    }

                    const token = button.getAttribute('data-emoji-token');
                    const slug = button.getAttribute('data-emoji-slug');

                    if (token) {
                        insertTokenAtCursor(token);
                        if (slug) {
                            pushRecent(slug);
                        }
                        syncEmojiTileState();
                        renderEmojiGrid();
                        renderTemplatePreview();
                    }
                });
            });
        }

        function renderEmojiGrid() {
            if (!emojiGrid) {
                return;
            }

            const items = filteredEmojiLibrary();

            if (items.length === 0) {
                emojiGrid.innerHTML = '<div class="muted small">Không có custom emoji nào khớp với bộ lọc hiện tại.</div>';
                return;
            }

            emojiGrid.innerHTML = items.map((emoji) => `
                <button
                    class="template-emoji-tile"
                    type="button"
                    data-emoji-slug="${escapeHtml(String(emoji.slug))}"
                    data-emoji-token="${escapeHtml(String(emoji.token))}"
                >
                    <span class="template-emoji-star ${getFavorites().includes(String(emoji.slug)) ? 'active' : ''}" data-toggle-favorite="${escapeHtml(String(emoji.slug))}" title="Đánh dấu yêu thích">${getFavorites().includes(String(emoji.slug)) ? '★' : '☆'}</span>
                    <div class="template-emoji-top">
                        <span class="template-emoji-symbol">${escapeHtml(String(emoji.fallback_emoji))}</span>
                        <span class="template-emoji-name">${escapeHtml(String(emoji.name))}</span>
                    </div>
                    <span class="template-emoji-origin">${escapeHtml(String(emoji.scope_label || 'Riêng'))}</span>
                    <span class="template-emoji-token mono">${escapeHtml(String(emoji.token))}</span>
                </button>
            `).join('');

            bindEmojiGridEvents();
        }

        async function renderTemplatePreview() {
            const formData = new URLSearchParams();
            formData.set('_token', csrfToken);
            formData.set('body', bodyInput.value);
            formData.set('parse_mode', parseModeInput.value || 'HTML');

            try {
                const payload = await window.TeleSenderApp.fetchJson(previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: formData.toString(),
                });

                previewSurface.innerHTML = escapeHtml(payload.fallback_preview || '')
                    .replace(/\{\{ce:([a-z0-9._-]+)\}\}/ig, '<span class="template-token-missing">Token thiếu: {{$1}}</span>')
                    .replace(/\n/g, '<br>');
                previewCompiled.textContent = payload.compiled_html || '-';

                const issues = Array.isArray(payload.issues) ? payload.issues : [];
                previewIssuesItem.hidden = issues.length === 0;
                previewIssuesWrap.innerHTML = issues.length > 0
                    ? issues.map((issue) => `<div class="badge warning">${escapeHtml(issue)}</div>`).join('')
                    : '';

                const used = Array.isArray(payload.used_emojis) ? payload.used_emojis : [];
                usedTokensField.hidden = used.length === 0;
                usedTokensWrap.innerHTML = used.length > 0
                    ? used.map((emoji) => `<span class="chip">${escapeHtml(String(emoji.fallback_emoji || ''))} ${escapeHtml(String(emoji.name || ''))} <span class="mono">${escapeHtml('{{ce:' + String(emoji.slug || '') + '}}')}</span></span>`).join('')
                    : '';
            } catch (error) {
                previewIssuesItem.hidden = false;
                previewIssuesWrap.innerHTML = `<div class="badge danger">${escapeHtml(error.message || 'Preview thất bại.')}</div>`;
            }
        }

        function scheduleTemplatePreview() {
            if (templatePreviewTimer !== null) {
                clearTimeout(templatePreviewTimer);
            }

            templatePreviewTimer = setTimeout(() => {
                renderTemplatePreview();
            }, 180);
        }

        function applyTemplatePreset(key) {
            const preset = templatePresets.find((item) => item.key === key);
            if (!preset) {
                return;
            }

            nameInput.value = preset.name;
            bodyInput.value = preset.body;
            parseModeInput.value = preset.parse_mode;

            const labelMatch = labelOptions.find((item) => item.slug === preset.label_slug);
            labelInput.value = labelMatch ? String(labelMatch.id) : '';
            scheduleTemplatePreview();
        }

        templatePresetSelect?.addEventListener('change', (event) => {
            applyTemplatePreset(event.target.value);
        });

        templateChipButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-template-chip');

                if (templatePresetSelect) {
                    templatePresetSelect.value = key;
                }

                applyTemplatePreset(key);
            });
        });

        bodyInput.addEventListener('input', scheduleTemplatePreview);
        parseModeInput.addEventListener('change', scheduleTemplatePreview);
        emojiPickerSearch?.addEventListener('input', renderEmojiGrid);

        emojiFilterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                activeEmojiFilter = button.getAttribute('data-emoji-filter') || 'all';
                syncEmojiTileState();
                renderEmojiGrid();
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="templates-panel"]'],
            });
        });

        syncEmojiTileState();
        renderEmojiGrid();
        renderTemplatePreview();

        window.TeleSenderCrudModal.open({
            title: mode === 'edit' ? 'Cập nhật mẫu tin nhắn' : 'Tạo mẫu tin nhắn mới',
            description: 'Giữ nguyên editor, preset, emoji picker và preview realtime trong modal để thao tác tập trung hơn.',
            size: 'full',
            content: wrapper,
            onClose() {
                if (templatePreviewTimer !== null) {
                    clearTimeout(templatePreviewTimer);
                }
            },
        });
    }

    createButton?.addEventListener('click', () => {
        openTemplateModal('create');
    });

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-template-edit]') : null;

        if (!button) {
            return;
        }

        openTemplateModal('edit', button.getAttribute('data-template-edit'));
    });

    document.addEventListener('app:regions:refreshed', () => {
        templateRecords = window.TeleSenderApp.readJsonScript('[data-template-records]', {});
    });
})();
});
</script>
