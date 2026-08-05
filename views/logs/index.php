<?php

declare(strict_types=1);

$logPreviewRecords = [];

foreach ($logs as $log) {
    $logPreviewRecords[(int) $log['id']] = [
        'body' => (string) ($log['rendered_message_body'] ?? $log['message_preview'] ?? ''),
        'parse_mode' => (string) ($log['message_parse_mode'] ?? 'HTML'),
    ];
}
?>
<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Nhật ký gửi tin</h1>
    </div>

    <section class="panel listing-panel">
        <script type="application/json" data-log-preview-records><?= json_encode($logPreviewRecords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
        <div class="panel-header">
            <h2 class="panel-title">Lịch sử gửi tin</h2>
            <form class="toolbar-form" method="get" action="<?= e(url('/logs')) ?>">
                <?php if ((int) request()->query('per_page', 0) > 0): ?>
                    <input type="hidden" name="per_page" value="<?= e((string) request()->query('per_page')) ?>">
                <?php endif; ?>
                <div class="toolbar-search">
                    <input class="input" type="text" name="q" value="<?= e($searchQuery ?? '') ?>" placeholder="Tìm theo mẫu tin, tài khoản, nhóm, request id, lỗi...">
                    <button class="button secondary" type="submit">Lọc</button>
                    <?php if (($searchQuery ?? '') !== ''): ?>
                        <a class="button secondary" href="<?= e(url('/logs')) ?>">Xóa lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="panel-body logs-feed listing-body">
            <?php foreach ($logs as $log): ?>
                <?php
                $statusLabel = $log['status'] === 'success' ? 'Thành công' : 'Thất bại';
                $messagePreview = trim((string) ($log['message_preview'] ?? ''));
                $templateName = trim((string) ($log['template_name'] ?? ''));
                ?>
                <article class="log-card">
                    <div class="log-card-head">
                        <div class="inline-actions">
                            <span class="badge <?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($statusLabel) ?></span>
                            <span class="log-meta-pill"><?= e(fmt_datetime($log['sent_at'])) ?></span>
                            <!-- <span class="log-meta-pill mono"><?= e($log['request_id']) ?></span> -->
                        </div>
                        <?php if (!empty($log['label_name'])): ?>
                            <span class="badge info"><?= e($log['label_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($log['error_message'])): ?>
                        <div class="log-alert danger"><?= e($log['error_message']) ?></div>
                    <?php endif; ?>

                    <div class="log-card-grid">
                        <section class="log-card-section log-card-main">
                            <div class="log-section-label">Mẫu tin</div>
                            <h3 class="log-title"><?= e($templateName !== '' ? $templateName : 'Mẫu đã xóa') ?></h3>
                            <div class="log-preview" data-log-message-preview="<?= e((string) $log['id']) ?>">
                                <?= nl2br(e($messagePreview !== '' ? strip_tags($messagePreview) : 'Không có nội dung xem trước.')) ?>
                            </div>
                        </section>

                        <section class="log-card-section log-card-target">
                            <div class="log-section-label">Đích gửi</div>
                            <div class="log-kv">
                                <span class="log-kv-label">Tài khoản</span>
                                <strong><?= e($log['account_name'] ?? 'Không xác định') ?></strong>
                            </div>
                            <div class="log-kv">
                                <span class="log-kv-label">Nhóm</span>
                                <span><?= e($log['group_title'] ?? 'Không xác định') ?></span>
                            </div>
                            <div class="log-kv">
                                <span class="log-kv-label">Topic đích</span>
                                <span><?= e($log['target_topic_label'] ?? 'Topic chung') ?></span>
                            </div>
                            <?php if (!empty($log['actual_topic_label'])): ?>
                                <div class="log-kv">
                                <span class="log-kv-label">Topic thực tế</span>
                                <span class="<?= !empty($log['topic_mismatch']) ? 'log-mismatch' : 'log-match' ?>">
                                    <?= e($log['actual_topic_label']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        </section>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($logs === []): ?>
                <div class="muted">Chưa có log nào.</div>
            <?php endif; ?>
        </div>
        <div class="panel-body" style="padding-top: 0;">
            <?php $perPageOptions = [20, 50, 100, 200]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const logPreviewRecords = window.TeleSenderApp?.readJsonScript('[data-log-preview-records]', {}) || {};
    const customEmojiLibrary = <?= json_encode(array_map(static fn ($emoji) => [
        'id' => $emoji['id'],
        'name' => $emoji['name'],
        'slug' => $emoji['slug'],
        'fallback_emoji' => $emoji['fallback_emoji'],
        'preview_url' => url('/custom-emojis/preview?id=' . (int) $emoji['id']),
    ], $customEmojis), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const emojiMap = new Map(customEmojiLibrary.map((emoji) => [String(emoji.slug).toLowerCase(), emoji]));

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function emojiVisualMarkup(emoji) {
        const fallback = escapeHtml(String(emoji?.fallback_emoji || ''));
        const previewUrl = escapeHtml(String(emoji?.preview_url || ''));
        const image = previewUrl !== ''
            ? `<img class="custom-emoji-image" src="${previewUrl}" alt="" loading="lazy" decoding="async" data-custom-emoji-image>`
            : '';

        return `<span class="custom-emoji-preview template-inline-emoji"><span class="custom-emoji-fallback">${fallback}</span>${image}</span>`;
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
                image.naturalWidth > 0 ? showImage() : removeImage();
            }
        });
    }

    function renderSafeHtml(value) {
        const tokenized = String(value || '').replace(/\{\{ce:([a-z0-9._-]+)\}\}/ig, (token, slug) => (
            `<span data-log-emoji="${String(slug).toLowerCase()}"></span>`
        ));
        const source = document.createElement('template');
        const output = document.createElement('div');
        const allowedTags = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'INS', 'S', 'STRIKE', 'DEL', 'CODE', 'PRE', 'BLOCKQUOTE', 'A', 'BR']);
        source.innerHTML = tokenized;

        function cleanNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                return document.createTextNode(node.textContent || '');
            }

            if (!(node instanceof HTMLElement)) {
                return document.createDocumentFragment();
            }

            const emojiSlug = node.getAttribute('data-log-emoji');

            if (node.tagName === 'SPAN' && emojiSlug) {
                const emoji = emojiMap.get(emojiSlug);
                const holder = document.createElement('template');

                if (emoji) {
                    holder.innerHTML = emojiVisualMarkup(emoji);
                    return holder.content.firstElementChild || document.createTextNode('');
                }

                return document.createTextNode(`{{ce:${emojiSlug}}}`);
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

    document.querySelectorAll('[data-log-message-preview]').forEach((preview) => {
        const logId = preview.getAttribute('data-log-message-preview') || '';
        const record = logPreviewRecords[String(logId)];

        if (!record) {
            return;
        }

        preview.innerHTML = String(record.parse_mode || '').toUpperCase() === 'HTML'
            ? renderSafeHtml(record.body || '')
            : escapeHtml(record.body || '').replace(/\n/g, '<br>');
        bindCustomEmojiPreviews(preview);
    });
});
</script>
