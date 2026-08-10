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

    <section class="panel template-library-panel listing-panel" data-live-region="templates-panel">
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
        <div class="panel-body list listing-body listing-card-grid template-listing-grid">
            <?php foreach ($templates as $template): ?>
                <article class="list-item template-library-item entity-list-item">
                    <div class="template-library-head">
                        <div>
                            <strong><?= e($template['name']) ?></strong>
                            <div class="small muted mono"><?= e($template['parse_mode']) ?></div>
                        </div>
                        <div class="inline-actions">
                            <a
                                class="badge info template-usage-badge"
                                href="<?= e(url('/schedules?message_template_id=' . (int) $template['id'])) ?>"
                                title="Xem các lịch gửi đang sử dụng mẫu này"
                            >
                                <i class="fa-regular fa-paper-plane" aria-hidden="true"></i>
                                <?= e((string) ((int) ($template['schedules_count'] ?? 0))) ?> lịch gửi
                            </a>
                            <?php if (!empty($template['label_name'])): ?>
                                <span class="badge"><?= e($template['label_name']) ?></span>
                            <?php endif; ?>
                            <span class="badge <?= (int) $template['is_active'] === 1 ? 'success' : 'warning' ?>"><?= (int) $template['is_active'] === 1 ? 'Đang bật' : 'Tạm tắt' ?></span>
                        </div>
                    </div>
                    <div class="template-library-preview" data-template-listing-preview="<?= e((string) $template['id']) ?>">
                        <?= nl2br(e(mb_substr(strip_tags($templatePreviewBodies[(int) $template['id']] ?? $template['body']), 0, 240))) ?>
                    </div>
                    <div class="inline-actions">
                        <button class="button secondary" type="button" data-template-edit="<?= e((string) $template['id']) ?>">Sửa</button>
                        <form method="post" action="<?= e(url('/templates/delete')) ?>" data-ajax-form data-ajax-refresh="templates-panel">
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
                    <h3 class="builder-block-title">Mẫu soạn nhanh</h3>
                    </div>
                </div>
                <div class="field">
                    <label for="template_modal_preset">Chọn nội dung mẫu</label>
                    <select class="select" id="template_modal_preset" data-template-preset>
                        <option value="">Chọn mẫu cài sẵn hoặc tự thiết lập ở phần dưới</option>
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
                        <div class="small muted">Chọn HTML khi nội dung có Premium Emoji.</div>
                    </div>
                </div>

                <div class="field template-editor-field">
                    <label for="template_modal_body">Nội dung</label>
                    <div class="template-rich-editor" data-template-rich-editor>
                        <div class="template-editor-toolbar" role="toolbar" aria-label="Định dạng nội dung">
                            <button class="template-editor-tool" type="button" data-editor-command="bold" aria-label="In đậm" title="In đậm"><strong>B</strong></button>
                            <button class="template-editor-tool" type="button" data-editor-command="italic" aria-label="In nghiêng" title="In nghiêng"><em>I</em></button>
                            <button class="template-editor-tool" type="button" data-editor-command="underline" aria-label="Gạch chân" title="Gạch chân"><u>U</u></button>
                            <button class="template-editor-tool" type="button" data-editor-command="strikeThrough" aria-label="Gạch ngang" title="Gạch ngang"><s>S</s></button>
                            <button class="template-editor-tool" type="button" data-editor-command="inlineCode" aria-label="Mã nội tuyến" title="Mã nội tuyến"><i class="fa-solid fa-code" aria-hidden="true"></i></button>
                            <button class="template-editor-tool" type="button" data-editor-command="spoiler" aria-label="Nội dung ẩn" title="Nội dung ẩn (spoiler)"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i></button>
                            <span class="template-editor-toolbar-separator" aria-hidden="true"></span>
                            <button class="template-editor-tool template-editor-tool-wide" type="button" data-editor-block="blockquote" aria-label="Trích dẫn" title="Trích dẫn"><i class="fa-solid fa-quote-left" aria-hidden="true"></i><span>Trích dẫn</span></button>
                            <button class="template-editor-tool" type="button" data-editor-block="pre" aria-label="Khối mã" title="Khối mã"><i class="fa-solid fa-file-code" aria-hidden="true"></i></button>
                            <button class="template-editor-tool" type="button" data-editor-action="link" aria-label="Chèn liên kết" title="Chèn liên kết"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
                            <span class="template-editor-toolbar-separator" aria-hidden="true"></span>
                            <button class="template-editor-tool" type="button" data-editor-action="unorderedList" aria-label="Danh sách dấu chấm" title="Danh sách dấu chấm"><i class="fa-solid fa-list-ul" aria-hidden="true"></i></button>
                            <button class="template-editor-tool" type="button" data-editor-action="orderedList" aria-label="Danh sách đánh số" title="Danh sách đánh số"><i class="fa-solid fa-list-ol" aria-hidden="true"></i></button>
                            <button class="template-editor-tool" type="button" data-editor-action="indent" aria-label="Tăng thụt lề" title="Tăng thụt lề"><i class="fa-solid fa-indent" aria-hidden="true"></i></button>
                            <button class="template-editor-tool" type="button" data-editor-action="outdent" aria-label="Giảm thụt lề" title="Giảm thụt lề"><i class="fa-solid fa-outdent" aria-hidden="true"></i></button>
                            <span class="template-editor-toolbar-separator" aria-hidden="true"></span>
                            <button class="template-editor-tool template-editor-tool-wide" type="button" data-editor-command="removeFormat" aria-label="Xóa định dạng" title="Xóa định dạng"><i class="fa-solid fa-eraser" aria-hidden="true"></i><span>Xóa định dạng</span></button>
                            <span class="template-editor-toolbar-separator" aria-hidden="true"></span>
                            <button
                                class="template-editor-tool template-editor-tool-wide template-editor-emoji-toggle"
                                type="button"
                                aria-label="Chọn Premium Emoji"
                                aria-expanded="false"
                                data-editor-emoji-toggle
                            >
                                <i class="fa-regular fa-face-smile" aria-hidden="true"></i>
                                <span>Premium Emoji</span>
                            </button>
                        </div>
                        <div
                            class="template-editor-surface"
                            id="template_modal_body"
                            contenteditable="true"
                            role="textbox"
                            aria-multiline="true"
                            data-placeholder="Nhập nội dung tin nhắn..."
                            data-template-editor-surface
                        ></div>

                        <div class="template-emoji-popover" hidden data-template-emoji-popover>
                            <div class="template-emoji-popover-head">
                                <div>
                                    <strong>Premium Emoji</strong>
                                    <span>Chèn icon tại vị trí con trỏ</span>
                                </div>
                                <div class="inline-actions">
                                    <a class="template-emoji-manage-link" href="<?= e(url('/custom-emojis')) ?>">Quản lý</a>
                                    <button class="template-emoji-popover-close" type="button" aria-label="Đóng bộ chọn emoji" data-editor-emoji-close>
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if ($customEmojis !== []): ?>
                                <input class="input template-emoji-search" type="text" placeholder="Tìm theo tên hoặc từ khóa..." aria-label="Tìm Premium Emoji" data-emoji-picker-search>
                                <div class="chip-row template-emoji-filters">
                                    <button class="chip active" type="button" data-emoji-filter="all">Tất cả</button>
                                    <button class="chip" type="button" data-emoji-filter="recent">Gần đây</button>
                                </div>
                                <div class="template-emoji-grid" data-template-emoji-grid></div>
                            <?php else: ?>
                                <div class="template-emoji-empty muted small">Chưa có emoji tùy chỉnh trong thư viện.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <textarea name="body" hidden data-template-body></textarea>

                    <div class="field" hidden data-template-used-tokens-field>
                        <label>Emoji đang dùng</label>
                        <div class="chip-row" data-template-used-tokens></div>
                    </div>
                </div>
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

        <aside class="template-preview-rail">
            <section class="card template-preview-card">
                <div class="template-preview-head">
                    <div>
                        <h2 class="section-title">Bản xem trước</h2>
                        <p class="section-copy">Nội dung và Premium Emoji sẽ hiển thị gần giống khi gửi trên Telegram.</p>
                    </div>
                </div>

                <div class="template-preview-issues" hidden data-template-preview-issues-item>
                    <div class="stack" data-template-preview-issues></div>
                </div>

                <div class="template-preview-surface" data-template-preview-surface>Nhập nội dung để xem trước tin nhắn.</div>
            </section>
        </aside>
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
        'preview_url' => url('/custom-emojis/preview?id=' . (int) $emoji['id']),
        'token' => '{{ce:' . $emoji['slug'] . '}}',
    ], $customEmojis), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const customEmojiMap = new Map(customEmojiLibrary.map((emoji) => [String(emoji.slug).toLowerCase(), emoji]));
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

    function emojiVisualMarkup(emoji, extraClass = '') {
        const fallback = escapeHtml(String(emoji?.fallback_emoji || ''));
        const previewUrl = escapeHtml(String(emoji?.preview_url || ''));
        const className = ['custom-emoji-preview', extraClass].filter(Boolean).join(' ');
        const image = previewUrl !== ''
            ? `<img class="custom-emoji-image" src="${previewUrl}" alt="" loading="lazy" decoding="async" data-custom-emoji-image>`
            : '';

        return `<span class="${className}"><span class="custom-emoji-fallback">${fallback}</span>${image}</span>`;
    }

    function bindCustomEmojiPreviews(scope) {
        scope.querySelectorAll('[data-custom-emoji-image]').forEach((image) => {
            if (image.dataset.previewBound === '1') {
                return;
            }

            image.dataset.previewBound = '1';
            const container = image.closest('.custom-emoji-preview');

            const showImage = () => container?.classList.add('is-loaded');
            const removeImage = () => image.remove();

            image.addEventListener('load', showImage, { once: true });
            image.addEventListener('error', removeImage, { once: true });

            if (image.complete) {
                if (image.naturalWidth > 0) {
                    showImage();
                } else {
                    removeImage();
                }
            }
        });
    }

    function bindSpoilerPreviews(scope) {
        scope.querySelectorAll('tg-spoiler').forEach((spoiler) => {
            if (spoiler.dataset.spoilerBound === '1') {
                return;
            }

            spoiler.dataset.spoilerBound = '1';
            spoiler.setAttribute('role', 'button');
            spoiler.setAttribute('tabindex', '0');
            spoiler.setAttribute('aria-label', 'Hiện hoặc ẩn nội dung spoiler');

            const toggle = () => spoiler.classList.toggle('is-revealed');
            spoiler.addEventListener('click', toggle);
            spoiler.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });
        });
    }

    function renderSafeTelegramPreview(value, emojiMap) {
        const tokenized = String(value || '').replace(/\{\{ce:([a-z0-9._-]+)\}\}/ig, (token, slug) => (
            `<span data-preview-emoji="${String(slug).toLowerCase()}"></span>`
        ));
        const source = document.createElement('template');
        const output = document.createElement('div');
        const allowedTags = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'INS', 'S', 'STRIKE', 'DEL', 'CODE', 'PRE', 'BLOCKQUOTE', 'TG-SPOILER', 'A', 'BR']);
        source.innerHTML = tokenized;

        function cleanNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                return document.createTextNode(node.textContent || '');
            }

            if (!(node instanceof HTMLElement)) {
                return document.createDocumentFragment();
            }

            const emojiSlug = node.getAttribute('data-preview-emoji');

            if (node.tagName === 'SPAN' && emojiSlug) {
                const emoji = emojiMap.get(emojiSlug);
                const holder = document.createElement('template');

                if (emoji) {
                    holder.innerHTML = emojiVisualMarkup(emoji, 'template-inline-emoji');
                    return holder.content.firstElementChild || document.createTextNode('');
                }

                const missing = document.createElement('span');
                missing.className = 'template-token-missing';
                missing.textContent = `Token thiếu: {{ce:${emojiSlug}}}`;
                return missing;
            }

            if (!allowedTags.has(node.tagName)) {
                const fragment = document.createDocumentFragment();
                node.childNodes.forEach((child) => fragment.appendChild(cleanNode(child)));
                return fragment;
            }

            const clean = document.createElement(node.tagName.toLowerCase());

            if (node.tagName === 'A') {
                const href = (node.getAttribute('href') || '').trim();

                if (/^(https?:|tg:)/i.test(href)) {
                    clean.setAttribute('href', href);
                    clean.setAttribute('target', '_blank');
                    clean.setAttribute('rel', 'noreferrer');
                }
            }

            node.childNodes.forEach((child) => clean.appendChild(cleanNode(child)));
            return clean;
        }

        source.content.childNodes.forEach((node) => output.appendChild(cleanNode(node)));

        return output.innerHTML.replace(/\n/g, '<br>');
    }

    function renderTemplateContent(value, emojiMap, parseMode = 'HTML') {
        if (String(parseMode || '').toUpperCase() === 'HTML') {
            return renderSafeTelegramPreview(value, emojiMap);
        }

        return escapeHtml(String(value || ''))
            .replace(/\{\{ce:([a-z0-9._-]+)\}\}/ig, (token, slug) => {
                const emoji = emojiMap.get(String(slug).toLowerCase());

                if (!emoji) {
                    return `<span class="template-token-missing">${escapeHtml(token)}</span>`;
                }

                return emojiVisualMarkup(emoji, 'template-inline-emoji');
            })
            .replace(/\n/g, '<br>');
    }

    function renderTemplateListingPreviews() {
        document.querySelectorAll('[data-template-listing-preview]').forEach((preview) => {
            const templateId = preview.getAttribute('data-template-listing-preview') || '';
            const record = templateRecords[String(templateId)];

            if (!record) {
                return;
            }

            preview.innerHTML = renderTemplateContent(record.body || '', customEmojiMap, record.parse_mode || 'HTML');
            bindCustomEmojiPreviews(preview);
            bindSpoilerPreviews(preview);
        });
    }

    function renderEditorContent(value, emojiMap, parseMode) {
        if (String(parseMode || '').toUpperCase() !== 'HTML') {
            return escapeHtml(String(value || ''))
                .replace(/\{\{ce:([a-z0-9._-]+)\}\}/ig, (token, slug) => {
                    const emoji = emojiMap.get(String(slug).toLowerCase());
                    return emoji
                        ? `<span class="template-editor-emoji" data-editor-emoji-token="${escapeHtml(token)}" contenteditable="false">${emojiVisualMarkup(emoji, 'template-inline-emoji')}</span>`
                        : escapeHtml(token);
                })
                .replace(/\n/g, '<br>');
        }

        const tokenized = String(value || '').replace(/\{\{ce:([a-z0-9._-]+)\}\}/ig, (token, slug) => (
            `<span data-editor-emoji="${String(slug).toLowerCase()}" data-editor-token="${escapeHtml(token)}"></span>`
        ));
        const source = document.createElement('template');
        const output = document.createElement('div');
        const allowedTags = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'INS', 'S', 'STRIKE', 'DEL', 'CODE', 'PRE', 'BLOCKQUOTE', 'TG-SPOILER', 'A', 'BR']);
        source.innerHTML = tokenized;

        function cleanNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                return document.createTextNode(node.textContent || '');
            }

            if (!(node instanceof HTMLElement)) {
                return document.createDocumentFragment();
            }

            const emojiSlug = node.getAttribute('data-editor-emoji');

            if (node.tagName === 'SPAN' && emojiSlug) {
                const emoji = emojiMap.get(emojiSlug);

                if (!emoji) {
                    return document.createTextNode(node.getAttribute('data-editor-token') || '');
                }

                const holder = document.createElement('span');
                holder.className = 'template-editor-emoji';
                holder.contentEditable = 'false';
                holder.dataset.editorEmojiToken = node.getAttribute('data-editor-token') || emoji.token;
                holder.innerHTML = emojiVisualMarkup(emoji, 'template-inline-emoji');
                return holder;
            }

            if (!allowedTags.has(node.tagName)) {
                const fragment = document.createDocumentFragment();
                node.childNodes.forEach((child) => fragment.appendChild(cleanNode(child)));
                return fragment;
            }

            const clean = document.createElement(node.tagName.toLowerCase());

            if (node.tagName === 'A') {
                const href = (node.getAttribute('href') || '').trim();
                if (/^(https?:|tg:)/i.test(href)) {
                    clean.setAttribute('href', href);
                }
            }

            node.childNodes.forEach((child) => clean.appendChild(cleanNode(child)));
            return clean;
        }

        source.content.childNodes.forEach((node) => output.appendChild(cleanNode(node)));
        return output.innerHTML.replace(/\n/g, '<br>');
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
        const richEditor = wrapper.querySelector('[data-template-rich-editor]');
        const editorSurface = wrapper.querySelector('[data-template-editor-surface]');
        const editorCommandButtons = wrapper.querySelectorAll('[data-editor-command]');
        const editorBlockButtons = wrapper.querySelectorAll('[data-editor-block]');
        const editorActionButtons = wrapper.querySelectorAll('[data-editor-action]');
        const toolbarFormatStates = [
            { button: wrapper.querySelector('[data-editor-command="bold"]'), selector: 'b, strong' },
            { button: wrapper.querySelector('[data-editor-command="italic"]'), selector: 'i, em' },
            { button: wrapper.querySelector('[data-editor-command="underline"]'), selector: 'u, ins' },
            { button: wrapper.querySelector('[data-editor-command="strikeThrough"]'), selector: 's, strike, del' },
            { button: wrapper.querySelector('[data-editor-command="inlineCode"]'), selector: 'code' },
            { button: wrapper.querySelector('[data-editor-command="spoiler"]'), selector: 'tg-spoiler' },
            { button: wrapper.querySelector('[data-editor-block="blockquote"]'), selector: 'blockquote' },
            { button: wrapper.querySelector('[data-editor-block="pre"]'), selector: 'pre' },
            { button: wrapper.querySelector('[data-editor-action="link"]'), selector: 'a' },
            { button: wrapper.querySelector('[data-editor-action="unorderedList"]'), selector: 'ul' },
            { button: wrapper.querySelector('[data-editor-action="orderedList"]'), selector: 'ol' },
        ];
        const parseModeInput = wrapper.querySelector('[data-template-parse-mode]');
        const labelInput = wrapper.querySelector('[data-template-label]');
        const activeInput = wrapper.querySelector('[data-template-active]');
        const submitButton = wrapper.querySelector('[data-template-submit]');
        const templatePresetSelect = wrapper.querySelector('[data-template-preset]');
        const emojiPickerToggle = wrapper.querySelector('[data-editor-emoji-toggle]');
        const emojiPickerPopover = wrapper.querySelector('[data-template-emoji-popover]');
        const emojiPickerClose = wrapper.querySelector('[data-editor-emoji-close]');
        const emojiPickerSearch = wrapper.querySelector('[data-emoji-picker-search]');
        const emojiGrid = wrapper.querySelector('[data-template-emoji-grid]');
        const usedTokensWrap = wrapper.querySelector('[data-template-used-tokens]');
        const usedTokensField = wrapper.querySelector('[data-template-used-tokens-field]');
        const previewIssuesWrap = wrapper.querySelector('[data-template-preview-issues]');
        const previewIssuesItem = wrapper.querySelector('[data-template-preview-issues-item]');
        const previewSurface = wrapper.querySelector('[data-template-preview-surface]');
        const emojiFilterButtons = wrapper.querySelectorAll('[data-emoji-filter]');
        const templateChipButtons = wrapper.querySelectorAll('[data-template-chip]');
        let templatePreviewTimer = null;
        let activeEmojiFilter = 'all';
        let lastEditorRange = null;
        let emojiInsertionMarker = null;
        const emojiPickerMobileQuery = window.matchMedia('(max-width: 760px)');
        const emojiMap = customEmojiMap;

        if (
            !form || !nameInput || !bodyInput || !richEditor || !editorSurface || !emojiPickerToggle || !emojiPickerPopover
            || !parseModeInput || !labelInput || !activeInput || !submitButton
            || !usedTokensWrap || !usedTokensField || !previewIssuesWrap || !previewIssuesItem || !previewSurface
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

        setEditorContent(bodyInput.value);

        function pushRecent(slug) {
            const recents = getRecents().filter((item) => item !== slug);
            recents.unshift(slug);
            writeStoredList(recentStorageKey, recents);
        }

        function setEmojiPickerOpen(open) {
            if (open && !emojiInsertionMarker?.isConnected) {
                placeEmojiInsertionMarker();
            }

            emojiPickerPopover.hidden = !open;
            emojiPickerToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            emojiPickerToggle.classList.toggle('active', open);
            richEditor.classList.toggle('emoji-picker-open', open);

            if (open) {
                positionEmojiPicker();
                renderEmojiGrid();
                requestAnimationFrame(() => emojiPickerSearch?.focus());
            } else {
                removeEmojiInsertionMarker();

                if (emojiPickerPopover.parentElement !== richEditor) {
                    richEditor.appendChild(emojiPickerPopover);
                }
            }
        }

        function positionEmojiPicker() {
            if (emojiPickerPopover.hidden) {
                return;
            }

            const parent = emojiPickerMobileQuery.matches ? document.body : richEditor;
            if (emojiPickerPopover.parentElement !== parent) {
                parent.appendChild(emojiPickerPopover);
            }
        }

        function syncEmojiTileState() {
            emojiFilterButtons.forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-emoji-filter') === activeEmojiFilter);
            });
        }

        function filteredEmojiLibrary() {
            const search = (emojiPickerSearch?.value || '').trim().toLowerCase();
            const recents = getRecents();

            return customEmojiLibrary.filter((emoji) => {
                const haystack = [emoji.name, emoji.slug, emoji.keywords, emoji.notes, emoji.scope_label, emoji.source_user_name].join(' ').toLowerCase();
                const matchesSearch = search === '' || haystack.includes(search);

                if (!matchesSearch) {
                    return false;
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

        function setEditorContent(value) {
            editorSurface.innerHTML = renderEditorContent(value, emojiMap, parseModeInput.value);
            bindCustomEmojiPreviews(editorSurface);
            lastEditorRange = null;
            syncEditorToolbarState(null);
        }

        function syncEditorToolbarState(range) {
            const selectedTextNodes = [];

            if (range && editorSurface.contains(range.commonAncestorContainer)) {
                const walker = document.createTreeWalker(editorSurface, NodeFilter.SHOW_TEXT);
                let currentNode = walker.nextNode();

                while (currentNode) {
                    if ((currentNode.textContent || '').trim() !== '') {
                        try {
                            if (range.intersectsNode(currentNode)) {
                                selectedTextNodes.push(currentNode);
                            }
                        } catch (error) {
                        }
                    }

                    currentNode = walker.nextNode();
                }
            }

            toolbarFormatStates.forEach(({ button, selector }) => {
                if (!button) {
                    return;
                }

                let matches = 0;
                let total = selectedTextNodes.length;

                if (range && total === 0 && editorSurface.contains(range.commonAncestorContainer)) {
                    const startElement = range.startContainer instanceof HTMLElement
                        ? range.startContainer
                        : range.startContainer.parentElement;
                    total = 1;
                    matches = startElement?.closest(selector) && editorSurface.contains(startElement.closest(selector)) ? 1 : 0;
                } else if (total > 0) {
                    matches = selectedTextNodes.filter((node) => {
                        const formattedParent = node.parentElement?.closest(selector);
                        return formattedParent && editorSurface.contains(formattedParent);
                    }).length;
                }

                const state = matches === 0
                    ? 'off'
                    : matches === total
                        ? 'active'
                        : 'mixed';

                button.classList.toggle('active', state === 'active');
                button.classList.toggle('mixed', state === 'mixed');
                button.setAttribute('aria-pressed', state === 'mixed' ? 'mixed' : state === 'active' ? 'true' : 'false');
            });
        }

        function serializeEditorNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                return escapeHtml((node.textContent || '').replaceAll('\u200B', ''));
            }

            if (!(node instanceof HTMLElement)) {
                return '';
            }

            if (node.hasAttribute('data-editor-emoji-token')) {
                return node.getAttribute('data-editor-emoji-token') || '';
            }

            if (node.hasAttribute('data-editor-emoji-marker')) {
                return '';
            }

            if (node.tagName === 'BR') {
                return '<br>';
            }

            const children = Array.from(node.childNodes).map(serializeEditorNode).join('');

            if (node.tagName === 'LI') {
                const list = node.parentElement;
                let depth = 0;
                let ancestor = list?.parentElement || null;

                while (ancestor && ancestor !== editorSurface) {
                    if (ancestor.tagName === 'LI') {
                        depth += 1;
                    }
                    ancestor = ancestor.parentElement;
                }

                const marker = list?.tagName === 'OL'
                    ? `${Array.from(list.children).indexOf(node) + 1}. `
                    : '• ';

                return `${'\u2003'.repeat(depth)}${marker}${children}<br>`;
            }

            if (node.tagName === 'UL' || node.tagName === 'OL') {
                return children;
            }

            const tagMap = {
                B: 'b', STRONG: 'b', I: 'i', EM: 'i', U: 'u', INS: 'u',
                S: 's', STRIKE: 's', DEL: 's', CODE: 'code', PRE: 'pre', BLOCKQUOTE: 'blockquote',
                'TG-SPOILER': 'tg-spoiler',
            };
            const tag = tagMap[node.tagName];

            if (tag) {
                return `<${tag}>${children}</${tag}>`;
            }

            if (node.tagName === 'A') {
                const href = (node.getAttribute('href') || '').trim();
                return /^(https?:|tg:)/i.test(href)
                    ? `<a href="${escapeHtml(href)}">${children}</a>`
                    : children;
            }

            if (node.tagName === 'DIV' || node.tagName === 'P') {
                return `${children}<br>`;
            }

            return children;
        }

        function syncBodyFromEditor() {
            let value = Array.from(editorSurface.childNodes).map(serializeEditorNode).join('')
                .replace(/<(b|i|u|s|code|pre|blockquote|tg-spoiler)>\s*<\/\1>/gi, '')
                .replace(/(?:<br>){2,}$/g, '<br>')
                .replace(/<br>$/g, '');

            if ((parseModeInput.value || '').toUpperCase() !== 'HTML') {
                const plain = document.createElement('div');
                plain.innerHTML = value.replace(/<br>/g, '\n');
                value = plain.textContent || '';
            }

            bodyInput.value = value;
            return value;
        }

        function rememberEditorSelection() {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) {
                syncEditorToolbarState(null);
                return;
            }

            const range = selection.getRangeAt(0);
            if (editorSurface.contains(range.commonAncestorContainer)) {
                lastEditorRange = range.cloneRange();
                syncEditorToolbarState(range);
            } else if (!richEditor.contains(document.activeElement)) {
                syncEditorToolbarState(null);
            }
        }

        function focusEditorRange() {
            editorSurface.focus();
            const selection = window.getSelection();
            if (!selection) {
                return null;
            }

            selection.removeAllRanges();
            const range = lastEditorRange && editorSurface.contains(lastEditorRange.commonAncestorContainer)
                ? lastEditorRange
                : document.createRange();

            if (!lastEditorRange || !editorSurface.contains(range.commonAncestorContainer)) {
                range.selectNodeContents(editorSurface);
                range.collapse(false);
            }

            selection.addRange(range);
            return range;
        }

        function removeEmojiInsertionMarker() {
            if (!emojiInsertionMarker?.isConnected) {
                emojiInsertionMarker = null;
                return;
            }

            const range = document.createRange();
            range.setStartBefore(emojiInsertionMarker);
            range.collapse(true);
            emojiInsertionMarker.remove();
            lastEditorRange = range.cloneRange();
            emojiInsertionMarker = null;
        }

        function placeEmojiInsertionMarker() {
            removeEmojiInsertionMarker();
            const selection = window.getSelection();
            let range = selection && selection.rangeCount > 0 ? selection.getRangeAt(0).cloneRange() : null;

            if (!range || !editorSurface.contains(range.commonAncestorContainer)) {
                range = lastEditorRange && editorSurface.contains(lastEditorRange.commonAncestorContainer)
                    ? lastEditorRange.cloneRange()
                    : document.createRange();

                if (!lastEditorRange || !editorSurface.contains(range.commonAncestorContainer)) {
                    range.selectNodeContents(editorSurface);
                    range.collapse(false);
                }
            }

            range.collapse(false);
            emojiInsertionMarker = document.createElement('span');
            emojiInsertionMarker.dataset.editorEmojiMarker = '1';
            emojiInsertionMarker.setAttribute('aria-hidden', 'true');
            range.insertNode(emojiInsertionMarker);
        }

        function insertEmojiAtCursor(emoji) {
            let range = null;

            if (emojiInsertionMarker?.isConnected) {
                range = document.createRange();
                range.setStartBefore(emojiInsertionMarker);
                range.collapse(true);
                emojiInsertionMarker.remove();
                emojiInsertionMarker = null;
            } else {
                range = focusEditorRange();
            }

            if (!range) {
                return;
            }

            range.deleteContents();
            const holder = document.createElement('span');
            holder.className = 'template-editor-emoji';
            holder.contentEditable = 'false';
            holder.dataset.editorEmojiToken = emoji.token;
            holder.innerHTML = emojiVisualMarkup(emoji, 'template-inline-emoji');
            const spacer = document.createTextNode('\u200B');
            range.insertNode(spacer);
            range.insertNode(holder);
            range.setStartAfter(spacer);
            range.collapse(true);

            editorSurface.focus();
            const selection = window.getSelection();
            selection?.removeAllRanges();
            selection?.addRange(range);
            lastEditorRange = range.cloneRange();
            bindCustomEmojiPreviews(holder);
            parseModeInput.value = 'HTML';
            syncBodyFromEditor();
        }

        function applyEditorCommand(command) {
            const range = focusEditorRange();
            if (!range) {
                return;
            }

            const tagMap = {
                bold: 'b',
                italic: 'i',
                underline: 'u',
                strikeThrough: 's',
                inlineCode: 'code',
                spoiler: 'tg-spoiler',
            };
            const tagName = tagMap[command];

            if (range.collapsed) {
                if (['bold', 'italic', 'underline', 'strikeThrough', 'removeFormat'].includes(command)) {
                    document.execCommand(command, false);
                } else if (tagName) {
                    const formatted = document.createElement(tagName);
                    const marker = document.createTextNode('\u200B');
                    formatted.appendChild(marker);
                    range.insertNode(formatted);
                    range.setStart(marker, marker.length);
                    range.collapse(true);

                    const selection = window.getSelection();
                    selection?.removeAllRanges();
                    selection?.addRange(range);
                    lastEditorRange = range.cloneRange();
                }

                rememberEditorSelection();
                return;
            }

            if (command === 'removeFormat') {
                const formattedBlocks = Array.from(editorSurface.querySelectorAll('blockquote, pre'))
                    .filter((block) => range.intersectsNode(block));
                const formattedLists = Array.from(editorSurface.querySelectorAll('ul, ol'))
                    .filter((list) => range.intersectsNode(list));
                const formattedInline = Array.from(editorSurface.querySelectorAll('a, code, tg-spoiler'))
                    .filter((element) => range.intersectsNode(element));
                const protectedEmojis = Array.from(editorSurface.querySelectorAll('[data-editor-emoji-token]'))
                    .filter((emoji) => range.intersectsNode(emoji))
                    .map((emoji, index) => {
                        const marker = document.createTextNode(`\uE000${index}\uE001`);
                        const clone = emoji.cloneNode(true);
                        emoji.replaceWith(marker);

                        return { marker, clone };
                    });

                document.execCommand('removeFormat', false);
                document.execCommand('unlink', false);

                protectedEmojis.forEach(({ marker, clone }) => {
                    if (marker.isConnected) {
                        marker.replaceWith(clone);
                    }
                });

                // Block formatting wraps the selection, so convert affected blocks to normal paragraphs.
                formattedBlocks.forEach((block) => {
                    if (!block.isConnected) {
                        return;
                    }

                    const paragraph = document.createElement('div');
                    paragraph.append(...block.childNodes);
                    block.replaceWith(paragraph);
                });

                formattedLists.forEach((list) => {
                    if (!list.isConnected) {
                        return;
                    }

                    const paragraphs = document.createDocumentFragment();
                    Array.from(list.children).forEach((item) => {
                        const paragraph = document.createElement('div');
                        paragraph.append(...item.childNodes);
                        paragraphs.appendChild(paragraph);
                    });
                    list.replaceWith(paragraphs);
                });

                formattedInline.forEach((element) => {
                    if (element.isConnected) {
                        element.replaceWith(...element.childNodes);
                    }
                });

                bindCustomEmojiPreviews(editorSurface);
            } else {
                if (!tagName) {
                    return;
                }

                const startElement = range.startContainer instanceof HTMLElement
                    ? range.startContainer
                    : range.startContainer.parentElement;
                const existingFormat = startElement?.closest(tagName);

                if (existingFormat && editorSurface.contains(existingFormat) && existingFormat.contains(range.endContainer)) {
                    const firstChild = existingFormat.firstChild;
                    const lastChild = existingFormat.lastChild;
                    existingFormat.replaceWith(...existingFormat.childNodes);

                    if (firstChild && lastChild) {
                        range.setStartBefore(firstChild);
                        range.setEndAfter(lastChild);
                    }
                } else {
                    const formatted = document.createElement(tagName);
                    formatted.appendChild(range.extractContents());
                    range.insertNode(formatted);
                    range.selectNodeContents(formatted);
                }
            }

            const selection = window.getSelection();
            selection?.removeAllRanges();
            selection?.addRange(range);
            lastEditorRange = range.cloneRange();
            syncEditorToolbarState(range);
        }

        function applyEditorBlock(tagName) {
            const range = focusEditorRange();
            if (!range) {
                return;
            }

            if (range.collapsed) {
                document.execCommand('formatBlock', false, tagName);
                rememberEditorSelection();
                return;
            }

            const block = document.createElement(tagName);
            block.appendChild(range.extractContents());
            range.insertNode(block);
            range.selectNodeContents(block);

            const selection = window.getSelection();
            selection?.removeAllRanges();
            selection?.addRange(range);
            lastEditorRange = range.cloneRange();
            syncEditorToolbarState(range);
        }

        function applyEditorLink() {
            const range = focusEditorRange();
            if (!range) {
                return;
            }

            const startElement = range.startContainer instanceof HTMLElement
                ? range.startContainer
                : range.startContainer.parentElement;
            const existingLink = startElement?.closest('a');
            const enteredUrl = window.prompt('Nhập liên kết (https://... hoặc tg://...):', existingLink?.getAttribute('href') || 'https://');

            if (enteredUrl === null) {
                return;
            }

            const url = enteredUrl.trim();

            if (url === '') {
                document.execCommand('unlink', false);
                rememberEditorSelection();
                return;
            }

            if (!/^(https?:|tg:)/i.test(url)) {
                window.alert('Liên kết phải bắt đầu bằng http://, https:// hoặc tg://.');
                return;
            }

            if (range.collapsed) {
                const link = document.createElement('a');
                link.href = url;
                link.textContent = url;
                range.insertNode(link);
                range.setStartAfter(link);
                range.collapse(true);

                const selection = window.getSelection();
                selection?.removeAllRanges();
                selection?.addRange(range);
                lastEditorRange = range.cloneRange();
                return;
            }

            document.execCommand('createLink', false, url);
            rememberEditorSelection();
        }

        function applyEditorList(type) {
            focusEditorRange();
            document.execCommand(type === 'ordered' ? 'insertOrderedList' : 'insertUnorderedList', false);
            rememberEditorSelection();
        }

        function applyEditorIndent(direction) {
            const range = focusEditorRange();
            if (!range) {
                return;
            }

            const startElement = range.startContainer instanceof HTMLElement
                ? range.startContainer
                : range.startContainer.parentElement;

            if (startElement?.closest('li')) {
                document.execCommand(direction, false);
                rememberEditorSelection();
                return;
            }

            const blockTags = new Set(['DIV', 'P', 'BLOCKQUOTE', 'PRE']);
            const lines = [];
            let inlineLine = [];

            const flushInlineLine = () => {
                if (inlineLine.length > 0) {
                    lines.push({ container: editorSurface, nodes: inlineLine });
                    inlineLine = [];
                }
            };

            Array.from(editorSurface.childNodes).forEach((node) => {
                if (node instanceof HTMLElement && node.tagName === 'BR') {
                    flushInlineLine();
                    return;
                }

                if (node instanceof HTMLElement && blockTags.has(node.tagName)) {
                    flushInlineLine();
                    lines.push({ container: node, nodes: [node] });
                    return;
                }

                inlineLine.push(node);
            });
            flushInlineLine();

            const selectedLines = lines.filter(({ nodes }) => nodes.some((node) => {
                try {
                    return range.intersectsNode(node);
                } catch (error) {
                    return false;
                }
            }));

            selectedLines.forEach(({ container, nodes }) => {
                const firstNode = container === editorSurface ? nodes[0] : container.firstChild;

                if (direction === 'indent') {
                    container.insertBefore(document.createTextNode('\u2003'), firstNode || null);
                    return;
                }

                const leadingNode = firstNode?.nodeType === Node.TEXT_NODE
                    ? firstNode
                    : firstNode?.firstChild;

                if (leadingNode?.nodeType === Node.TEXT_NODE && leadingNode.textContent?.startsWith('\u2003')) {
                    leadingNode.textContent = leadingNode.textContent.slice(1);
                    if (leadingNode.textContent === '') {
                        leadingNode.remove();
                    }
                }
            });

            if (selectedLines.length === 0 && range.collapsed && direction === 'indent') {
                document.execCommand('insertText', false, '\u2003');
            }

            rememberEditorSelection();
        }

        function bindEmojiGridEvents() {
            emojiGrid?.querySelectorAll('.template-emoji-tile').forEach((button) => {
                button.addEventListener('click', () => {
                    const slug = button.getAttribute('data-emoji-slug');
                    const emoji = emojiMap.get(String(slug || '').toLowerCase());

                    if (emoji) {
                        insertEmojiAtCursor(emoji);
                        if (slug) {
                            pushRecent(slug);
                        }
                        syncEmojiTileState();
                        renderEmojiGrid();
                        setEmojiPickerOpen(false);
                        scheduleTemplatePreview();
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
                >
                    ${emojiVisualMarkup(emoji, 'template-emoji-symbol')}
                    <span class="template-emoji-name">${escapeHtml(String(emoji.name))}</span>
                </button>
            `).join('');

            bindEmojiGridEvents();
            bindCustomEmojiPreviews(emojiGrid);
        }

        async function renderTemplatePreview() {
            syncBodyFromEditor();
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

                previewSurface.innerHTML = renderTemplateContent(
                    bodyInput.value,
                    emojiMap,
                    parseModeInput.value || 'HTML'
                );
                const issues = Array.isArray(payload.issues) ? payload.issues : [];
                previewIssuesItem.hidden = issues.length === 0;
                previewIssuesWrap.innerHTML = issues.length > 0
                    ? issues.map((issue) => `<div class="badge warning">${escapeHtml(issue)}</div>`).join('')
                    : '';

                const used = Array.isArray(payload.used_emojis) ? payload.used_emojis : [];
                usedTokensField.hidden = used.length === 0;
                usedTokensWrap.innerHTML = used.length > 0
                    ? used.map((emoji) => {
                        const libraryEmoji = emojiMap.get(String(emoji.slug || '').toLowerCase()) || emoji;
                        return `<span class="chip template-used-emoji-chip">${emojiVisualMarkup(libraryEmoji, 'template-used-emoji')} <span>${escapeHtml(String(emoji.name || ''))}</span></span>`;
                    }).join('')
                    : '';

                bindCustomEmojiPreviews(wrapper);
                bindSpoilerPreviews(previewSurface);
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
            setEditorContent(bodyInput.value);

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

        editorSurface.addEventListener('input', () => {
            syncBodyFromEditor();
            queueMicrotask(rememberEditorSelection);
            requestAnimationFrame(rememberEditorSelection);
            scheduleTemplatePreview();
        });
        editorSurface.addEventListener('keyup', rememberEditorSelection);
        editorSurface.addEventListener('mouseup', rememberEditorSelection);
        editorSurface.addEventListener('focus', rememberEditorSelection);
        editorSurface.addEventListener('paste', (event) => {
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') || '');
        });
        document.addEventListener('selectionchange', rememberEditorSelection);

        editorCommandButtons.forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                applyEditorCommand(button.getAttribute('data-editor-command') || '');
                parseModeInput.value = 'HTML';
                syncBodyFromEditor();
                scheduleTemplatePreview();
            });
        });

        editorBlockButtons.forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                applyEditorBlock(button.getAttribute('data-editor-block') || 'blockquote');
                parseModeInput.value = 'HTML';
                syncBodyFromEditor();
                scheduleTemplatePreview();
            });
        });

        editorActionButtons.forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                const action = button.getAttribute('data-editor-action') || '';

                if (action === 'link') {
                    applyEditorLink();
                } else if (action === 'unorderedList') {
                    applyEditorList('unordered');
                } else if (action === 'orderedList') {
                    applyEditorList('ordered');
                } else if (action === 'indent' || action === 'outdent') {
                    applyEditorIndent(action);
                }

                parseModeInput.value = 'HTML';
                syncBodyFromEditor();
                scheduleTemplatePreview();
            });
        });

        emojiPickerToggle.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            rememberEditorSelection();
            placeEmojiInsertionMarker();
        });
        emojiPickerToggle.addEventListener('click', () => {
            setEmojiPickerOpen(emojiPickerPopover.hidden);
        });
        emojiPickerClose?.addEventListener('click', () => setEmojiPickerOpen(false));

        function handleEmojiPickerOutsideClick(event) {
            const target = event.target;

            if (
                emojiPickerPopover.hidden
                || !(target instanceof Node)
                || emojiPickerPopover.contains(target)
                || emojiPickerToggle.contains(target)
            ) {
                return;
            }

            setEmojiPickerOpen(false);
        }

        function handleEmojiPickerEscape(event) {
            if (event.key === 'Escape' && !emojiPickerPopover.hidden) {
                setEmojiPickerOpen(false);
                editorSurface.focus();
            }
        }

        document.addEventListener('pointerdown', handleEmojiPickerOutsideClick);
        document.addEventListener('keydown', handleEmojiPickerEscape);
        emojiPickerMobileQuery.addEventListener('change', positionEmojiPicker);

        parseModeInput.addEventListener('change', () => {
            syncBodyFromEditor();
            setEditorContent(bodyInput.value);
            scheduleTemplatePreview();
        });
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
            syncBodyFromEditor();

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
            description: 'Soạn nội dung, chèn Premium Emoji và kiểm tra bản xem trước trước khi lưu.',
            size: 'full',
            content: wrapper,
            onClose() {
                if (templatePreviewTimer !== null) {
                    clearTimeout(templatePreviewTimer);
                }
                document.removeEventListener('selectionchange', rememberEditorSelection);
                document.removeEventListener('pointerdown', handleEmojiPickerOutsideClick);
                document.removeEventListener('keydown', handleEmojiPickerEscape);
                emojiPickerMobileQuery.removeEventListener('change', positionEmojiPicker);
                emojiPickerPopover.remove();
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
        renderTemplateListingPreviews();
    });

    renderTemplateListingPreviews();
})();
});
</script>
