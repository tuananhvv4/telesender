<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Telegram Premium Emoji</h1>
        <h5 class="page-subtitle">Chỉ áp dụng cho tài khoản Telegram có Premium.</h5>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h2 class="section-title"><?= $editEmoji ? 'Cập nhật emoji' : 'Thêm emoji mới' ?></h2>

            <form class="form-grid" method="post" action="<?= e(url($editEmoji ? '/custom-emojis/update' : '/custom-emojis')) ?>">
                <?= csrf_field() ?>
                <?php if ($editEmoji): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editEmoji['id']) ?>">
                <?php endif; ?>

                <div class="field">
                    <label for="emoji_name">Tên gợi nhớ</label>
                    <input class="input" id="emoji_name" type="text" name="name" value="<?= e($editEmoji['name'] ?? '') ?>" placeholder="Ví dụ: Fire Cat" required>
                </div>

                <div class="field">
                    <label for="emoji_slug">Slug chèn vào mẫu tin nhắn</label>
                    <input class="input mono" id="emoji_slug" type="text" name="slug" value="<?= e($editEmoji['slug'] ?? '') ?>" placeholder="Ví dụ: fire-cat" required>
                    <div class="small muted">Token dùng trong mẫu: <span class="mono">{{ce:slug}}</span></div>
                </div>

                <div class="grid grid-2">
                    <div class="field">
                        <label for="emoji_identifier">Emoji ID</label>
                        <small>Lấy Id của emoji từ bot @emojiid_get_bot</small>
                        <input class="input mono" id="emoji_identifier" type="text" name="emoji_identifier" value="<?= e($editEmoji['emoji_identifier'] ?? '') ?>" placeholder="5318779098686826724" required>
                    </div>
                    <div class="field">
                        <label for="fallback_emoji">Icon dự phòng</label>
                        <small>Icon này sẽ hiển thị khi emoji không khả dụng (Hết premium hoặc lỗi)</small>
                        <input class="input" id="fallback_emoji" type="text" name="fallback_emoji" value="<?= e($editEmoji['fallback_emoji'] ?? '🔥') ?>" placeholder="Có thể lấy từ getemoji.com hoặc điện thoại" required>
                    </div>
                </div>

                <div class="field">
                    <label for="keywords">Từ khóa tìm kiếm</label>
                    <input class="input" id="keywords" type="text" name="keywords" value="<?= e($editEmoji['keywords'] ?? '') ?>" placeholder="fire, cat, promo">
                </div>

                <div class="field">
                    <label for="emoji_notes">Ghi chú</label>
                    <textarea class="textarea" id="emoji_notes" name="notes" rows="4" placeholder="Mô tả để nhận biết nhanh emoji này dùng trong trường hợp nào."><?= e($editEmoji['notes'] ?? '') ?></textarea>
                </div>

                <label class="checkbox-row">
                    <input type="checkbox" name="is_active" value="1" <?= !isset($editEmoji['is_active']) || (int) $editEmoji['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Kích hoạt emoji này để dùng trong bộ chọn</span>
                </label>

                <div class="actions">
                    <button class="button primary" type="submit"><?= $editEmoji ? 'Cập nhật emoji' : 'Thêm vào thư viện' ?></button>
                    <?php if ($editEmoji): ?>
                        <a class="button secondary" href="<?= e(url('/custom-emojis')) ?>">Thêm mới</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Thư viện hiện tại</h2>
            </div>
            <div class="panel-body emoji-library-grid">
                <?php foreach ($customEmojis as $emoji): ?>
                    <?php
                    $token = '{{ce:' . $emoji['slug'] . '}}';
                    $emojiIdentifier = (string) $emoji['emoji_identifier'];
                    $shortIdentifier = strlen($emojiIdentifier) > 14
                        ? substr($emojiIdentifier, 0, 8) . '...' . substr($emojiIdentifier, -4)
                        : $emojiIdentifier;
                    $keywordSummary = trim((string) ($emoji['keywords'] ?? ''));
                    $noteSummary = trim((string) ($emoji['notes'] ?? ''));
                    ?>
                    <article class="emoji-library-card">
                        <div class="emoji-library-head">
                            <div class="emoji-library-title">
                                <span class="emoji-library-symbol"><?= e($emoji['fallback_emoji']) ?></span>
                                <div class="emoji-library-title-copy">
                                    <strong><?= e($emoji['name']) ?></strong>
                                    <div class="small muted mono"><?= e($token) ?></div>
                                </div>
                            </div>
                            <span class="badge <?= (int) $emoji['is_active'] === 1 ? 'success' : 'warning' ?>"><?= (int) $emoji['is_active'] === 1 ? 'Đang bật' : 'Tạm tắt' ?></span>
                        </div>

                        <div class="emoji-library-meta">
                            <span class="emoji-meta-chip mono" title="<?= e($emojiIdentifier) ?>">ID: <?= e($shortIdentifier) ?></span>
                            <span class="emoji-meta-chip">Đang dùng: <?= e((string) ($emoji['usage_count'] ?? 0)) ?></span>
                            <?php if ($keywordSummary !== ''): ?>
                                <span class="emoji-meta-chip" title="<?= e($keywordSummary) ?>">#<?= e(mb_strimwidth($keywordSummary, 0, 26, '...')) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($noteSummary !== ''): ?>
                            <div class="emoji-library-note small muted" title="<?= e($noteSummary) ?>"><?= e(mb_strimwidth($noteSummary, 0, 88, '...')) ?></div>
                        <?php endif; ?>

                        <div class="inline-actions emoji-library-actions">
                            <button class="button secondary sm" type="button" data-copy-token="<?= e($token) ?>">Chép token</button>
                            <a class="button secondary sm" href="<?= e(url('/custom-emojis?edit=' . $emoji['id'])) ?>">Sửa</a>
                            <form method="post" action="<?= e(url('/custom-emojis/delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $emoji['id']) ?>">
                                <button class="button danger sm" type="submit">Xóa</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if ($customEmojis === []): ?>
                    <div class="muted">Chưa có custom emoji nào. Hãy thêm một vài emoji để bắt đầu chèn nhanh trong mẫu tin nhắn.</div>
                <?php endif; ?>
                <?php $perPageOptions = [9, 18, 27, 36, 54]; ?>
                <?php require base_path('views/partials/pagination.php'); ?>
            </div>
        </section>
    </div>
</section>
<script>
const customEmojiNameInput = document.getElementById('emoji_name');
const customEmojiSlugInput = document.getElementById('emoji_slug');

function slugifyEmojiName(value) {
    return String(value)
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9._-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

customEmojiNameInput?.addEventListener('input', () => {
    if (!customEmojiSlugInput || customEmojiSlugInput.dataset.touched === '1') {
        return;
    }

    customEmojiSlugInput.value = slugifyEmojiName(customEmojiNameInput.value);
});

customEmojiSlugInput?.addEventListener('input', () => {
    customEmojiSlugInput.dataset.touched = customEmojiSlugInput.value.trim() === '' ? '0' : '1';
});

document.querySelectorAll('[data-copy-token]').forEach((button) => {
    button.addEventListener('click', async () => {
        const token = button.getAttribute('data-copy-token') || '';

        try {
            await navigator.clipboard.writeText(token);
            button.textContent = 'Đã sao chép';
            setTimeout(() => {
                button.textContent = 'Chép token';
            }, 1200);
        } catch (error) {
            button.textContent = token;
        }
    });
});
</script>
