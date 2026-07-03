<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Mẫu tin nhắn</h1>
    </div>

    <div class="grid grid-2 template-workspace">
        <section class="card template-editor-card">
            <h2 class="section-title"><?= $editTemplate ? 'Cập nhật mẫu tin nhắn' : 'Tạo mẫu tin nhắn mới' ?></h2>

            <section class="builder-block template-preset-block">
                <div class="builder-block-head">
                    <div>
                        <h3 class="builder-block-title">Preset nhanh</h3>
                    </div>
                </div>
                <div class="field">
                    <label for="template_preset">Mẫu cài sẵn</label>
                    <select class="select" id="template_preset">
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

            <form class="form-grid" method="post" action="<?= e(url($editTemplate ? '/templates/update' : '/templates')) ?>">
                <?= csrf_field() ?>
                <?php if ($editTemplate): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editTemplate['id']) ?>">
                <?php endif; ?>
                <div class="template-form-meta">
                    <div class="field">
                        <label for="name">Tên mẫu tin nhắn</label>
                        <input class="input" id="name" type="text" name="name" value="<?= e($editTemplate['name'] ?? '') ?>" required>
                    </div>
                    <div class="field">
                        <label for="label_id">Nhãn</label>
                        <select class="select" id="label_id" name="label_id">
                            <option value="">Không gắn nhãn</option>
                            <?php foreach ($labels as $label): ?>
                                <option value="<?= e((string) $label['id']) ?>" <?= (string) ($editTemplate['label_id'] ?? '') === (string) $label['id'] ? 'selected' : '' ?>>
                                    <?= e($label['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field template-parse-field">
                        <label for="parse_mode">Chế độ parse</label>
                        <select class="select" id="parse_mode" name="parse_mode">
                            <?php foreach (['HTML', 'Markdown', 'TEXT'] as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= ($editTemplate['parse_mode'] ?? 'HTML') === $mode ? 'selected' : '' ?>><?= e($mode) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="small muted">Dùng <span class="mono">HTML</span> để gửi custom emoji Premium.</div>
                    </div>
                </div>

                <div class="field template-editor-field">
                    <label for="body">Nội dung</label>
                    <textarea class="textarea template-editor-textarea" id="body" name="body" required><?= e($editTemplate['body'] ?? '') ?></textarea>
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
                            <label for="emoji_picker_search">Tìm Premium Emoji</label>
                            <input class="input" id="emoji_picker_search" type="text" placeholder="Tìm theo tên, slug hoặc từ khóa...">
                        </div>

                        <div class="chip-row">
                            <button class="chip active" id="emoji_filter_all" type="button" data-emoji-filter="all">Tất cả</button>
                            <button class="chip" id="emoji_filter_favorites" type="button" data-emoji-filter="favorites">Yêu thích</button>
                            <button class="chip" id="emoji_filter_recent" type="button" data-emoji-filter="recent">Gần đây</button>
                        </div>

                        <div class="template-emoji-grid" id="template_emoji_grid">
                            <?php foreach ($customEmojis as $emoji): ?>
                                <button
                                    class="template-emoji-tile"
                                    type="button"
                                    data-emoji-slug="<?= e((string) $emoji['slug']) ?>"
                                    data-emoji-name="<?= e((string) $emoji['name']) ?>"
                                    data-emoji-keywords="<?= e((string) ($emoji['keywords'] ?? '')) ?>"
                                    data-emoji-token="<?= e('{{ce:' . $emoji['slug'] . '}}') ?>"
                                >
                                    <span class="template-emoji-star" data-toggle-favorite="<?= e((string) $emoji['slug']) ?>" title="Đánh dấu yêu thích">☆</span>
                                    <div class="template-emoji-top">
                                        <span class="template-emoji-symbol"><?= e((string) $emoji['fallback_emoji']) ?></span>
                                        <span class="template-emoji-name"><?= e((string) $emoji['name']) ?></span>
                                    </div>
                                    <span class="template-emoji-token mono"><?= e('{{ce:' . $emoji['slug'] . '}}') ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="small muted">Chưa có emoji tùy chỉnh trong thư viện.</div>
                    <?php endif; ?>

                    <div class="field" id="template_used_tokens_field" hidden>
                        <label>Emoji đang dùng</label>
                        <div class="chip-row" id="template_used_tokens"></div>
                    </div>
                </section>
                <label class="checkbox-row">
                    <input type="checkbox" name="is_active" value="1" <?= !isset($editTemplate['is_active']) || (int) $editTemplate['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Cho phép sử dụng mẫu tin nhắn này</span>
                </label>
                <div class="actions">
                    <button class="button primary" type="submit"><?= $editTemplate ? 'Cập nhật' : 'Tạo mẫu' ?></button>
                    <?php if ($editTemplate): ?>
                        <a class="button secondary" href="<?= e(url('/templates')) ?>">Tạo mới</a>
                    <?php endif; ?>
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
                <div class="list-item" id="template_preview_issues_item" hidden>
                    <strong>Lưu ý</strong>
                    <div class="stack" id="template_preview_issues"></div>
                </div>
                <div class="list-item">
                    <strong>Xem trước nội dung</strong>
                    <div class="template-preview-surface" id="template_preview_surface">Nhập nội dung hoặc chèn emoji tùy chỉnh để xem trước.</div>
                </div>
                <div class="list-item">
                    <strong>HTML gửi lên Telegram</strong>
                    <pre class="template-preview-code mono" id="template_preview_compiled">-</pre>
                </div>
            </div>
        </section>
    </div>

    <section class="panel template-library-panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Danh sách mẫu tin nhắn</h2>
            </div>
            <form class="toolbar-form" method="get" action="<?= e(url('/templates')) ?>">
                <?php if ($editTemplate): ?>
                    <input type="hidden" name="edit" value="<?= e((string) $editTemplate['id']) ?>">
                <?php endif; ?>
                <?php if ((int) request()->query('per_page', 0) > 0): ?>
                    <input type="hidden" name="per_page" value="<?= e((string) request()->query('per_page')) ?>">
                <?php endif; ?>
                <div class="toolbar-search">
                    <input class="input" type="text" name="q" value="<?= e($searchQuery ?? '') ?>" placeholder="Tìm theo tên mẫu, nội dung, nhãn, chế độ parse...">
                    <button class="button secondary" type="submit">Lọc</button>
                    <?php if (($searchQuery ?? '') !== ''): ?>
                        <?php $resetUrl = $editTemplate ? url('/templates?edit=' . $editTemplate['id']) : url('/templates'); ?>
                        <a class="button secondary" href="<?= e($resetUrl) ?>">Xóa lọc</a>
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
                        <a class="button secondary" href="<?= e(url('/templates?edit=' . $template['id'])) ?>">Sửa</a>
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
<script>
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
    'token' => '{{ce:' . $emoji['slug'] . '}}',
], $customEmojis), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

const templatePresetSelect = document.getElementById('template_preset');
const templateNameInput = document.getElementById('name');
const templateBodyInput = document.getElementById('body');
const templateParseModeInput = document.getElementById('parse_mode');
const templateLabelInput = document.getElementById('label_id');
const emojiPickerSearch = document.getElementById('emoji_picker_search');
const emojiGrid = document.getElementById('template_emoji_grid');
const usedTokensWrap = document.getElementById('template_used_tokens');
const usedTokensField = document.getElementById('template_used_tokens_field');
const previewIssuesWrap = document.getElementById('template_preview_issues');
const previewIssuesItem = document.getElementById('template_preview_issues_item');
const previewSurface = document.getElementById('template_preview_surface');
const previewCompiled = document.getElementById('template_preview_compiled');
let templatePreviewTimer = null;

const favoriteStorageKey = 'tele_sender_custom_emoji_favorites';
const recentStorageKey = 'tele_sender_custom_emoji_recent';
let activeEmojiFilter = 'all';

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

    document.querySelectorAll('[data-toggle-favorite]').forEach((star) => {
        const slug = star.getAttribute('data-toggle-favorite');
        const active = favorites.includes(slug);
        star.textContent = active ? '★' : '☆';
        star.classList.toggle('active', active);
    });

    document.querySelectorAll('[data-emoji-filter]').forEach((button) => {
        button.classList.toggle('active', button.getAttribute('data-emoji-filter') === activeEmojiFilter);
    });
}

function filteredEmojiLibrary() {
    const search = (emojiPickerSearch?.value || '').trim().toLowerCase();
    const favorites = getFavorites();
    const recents = getRecents();

    return customEmojiLibrary.filter((emoji) => {
        const haystack = [emoji.name, emoji.slug, emoji.keywords, emoji.notes].join(' ').toLowerCase();
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
            <span class="template-emoji-token mono">${escapeHtml(String(emoji.token))}</span>
        </button>
    `).join('');

    bindEmojiGridEvents();
}

function insertTokenAtCursor(token) {
    if (!templateBodyInput) {
        return;
    }

    const start = templateBodyInput.selectionStart ?? templateBodyInput.value.length;
    const end = templateBodyInput.selectionEnd ?? templateBodyInput.value.length;
    const before = templateBodyInput.value.slice(0, start);
    const after = templateBodyInput.value.slice(end);
    templateBodyInput.value = before + token + after;
    const nextCursor = start + token.length;
    templateBodyInput.focus();
    templateBodyInput.setSelectionRange(nextCursor, nextCursor);
}

function bindEmojiGridEvents() {
    document.querySelectorAll('.template-emoji-tile').forEach((button) => {
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
                renderTemplatePreview();
            }
        });
    });
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function renderTemplatePreview() {
    if (!templateBodyInput || !previewSurface || !previewCompiled || !previewIssuesWrap || !usedTokensWrap || !usedTokensField || !previewIssuesItem) {
        return;
    }

    const formData = new URLSearchParams();
    formData.set('_token', csrfToken);
    formData.set('body', templateBodyInput.value);
    formData.set('parse_mode', templateParseModeInput?.value || 'HTML');

    try {
        const response = await fetch('<?= e(url('/templates/preview')) ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData.toString(),
        });

        const payload = await response.json();

        if (!payload.ok) {
            throw new Error(payload.message || 'Không thể xem trước mẫu tin nhắn.');
        }

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

    templateNameInput.value = preset.name;
    templateBodyInput.value = preset.body;
    templateParseModeInput.value = preset.parse_mode;

    const labelMatch = labelOptions.find((item) => item.slug === preset.label_slug);
    templateLabelInput.value = labelMatch ? String(labelMatch.id) : '';
    scheduleTemplatePreview();
}

templatePresetSelect?.addEventListener('change', (event) => {
    applyTemplatePreset(event.target.value);
});

document.querySelectorAll('[data-template-chip]').forEach((button) => {
    button.addEventListener('click', () => {
        const key = button.getAttribute('data-template-chip');
        templatePresetSelect.value = key;
        applyTemplatePreset(key);
    });
});

templateBodyInput?.addEventListener('input', scheduleTemplatePreview);
templateParseModeInput?.addEventListener('change', scheduleTemplatePreview);
emojiPickerSearch?.addEventListener('input', renderEmojiGrid);

document.querySelectorAll('[data-emoji-filter]').forEach((button) => {
    button.addEventListener('click', () => {
        activeEmojiFilter = button.getAttribute('data-emoji-filter') || 'all';
        syncEmojiTileState();
        renderEmojiGrid();
    });
});

syncEmojiTileState();
bindEmojiGridEvents();
renderEmojiGrid();
renderTemplatePreview();
</script>
