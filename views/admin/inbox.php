<section class="page-header inbox-page-header">
    <div>
        <span class="eyebrow">Super admin</span>
        <h2>Hội thoại Telegram</h2>
        <p class="muted">Chế độ chỉ đọc từ các Telegram account đã đăng nhập trong hệ thống.</p>
    </div>
    <div class="inbox-sync-state" id="inbox_global_status">Chọn admin và account để bắt đầu.</div>
</section>

<section
    class="telegram-inbox"
    id="telegram_inbox_app"
    data-csrf-token="<?= e(csrf_token()) ?>"
    data-accounts-url="<?= e(url('/admin/inbox/accounts')) ?>"
    data-dialogs-url="<?= e(url('/admin/inbox/dialogs')) ?>"
    data-messages-url="<?= e(url('/admin/inbox/messages')) ?>"
    data-sync-account-url="<?= e(url('/admin/inbox/sync-account')) ?>"
    data-sync-dialog-url="<?= e(url('/admin/inbox/sync-dialog')) ?>"
    data-load-older-url="<?= e(url('/admin/inbox/load-older')) ?>"
    data-media-url="<?= e(url('/admin/inbox/media')) ?>"
>
    <div class="inbox-toolbar">
        <label>
            <span>Admin con</span>
            <select class="input" id="inbox_admin_select">
                <option value="">Chọn admin</option>
                <?php foreach ($admins as $admin): ?>
                    <option value="<?= e((string) $admin['id']) ?>">
                        <?= e((string) $admin['name']) ?> · <?= e((string) $admin['email']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Telegram account</span>
            <select class="input" id="inbox_account_select" disabled>
                <option value="">Chọn account</option>
            </select>
        </label>
        <label class="inbox-search-field">
            <span>Tìm hội thoại</span>
            <input class="input" id="inbox_search" type="search" placeholder="Tên, username hoặc nội dung gần nhất" disabled>
        </label>
    </div>

    <div class="inbox-shell">
        <aside class="inbox-dialog-pane" id="inbox_dialog_pane">
            <div class="inbox-pane-head">
                <div>
                    <strong>Hội thoại</strong>
                    <span class="small muted" id="inbox_dialog_count">0 cuộc trò chuyện</span>
                </div>
                <button class="button secondary sm" id="inbox_refresh_dialogs" type="button" disabled>
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>
            <div class="inbox-dialog-list" id="inbox_dialog_list">
                <div class="inbox-empty">Chưa chọn Telegram account.</div>
            </div>
        </aside>

        <section class="inbox-message-pane" id="inbox_message_pane">
            <header class="inbox-conversation-head">
                <button class="inbox-mobile-back" id="inbox_mobile_back" type="button" aria-label="Quay lại danh sách">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div class="inbox-avatar" id="inbox_conversation_avatar">TG</div>
                <div>
                    <strong id="inbox_conversation_title">Chọn một hội thoại</strong>
                    <span class="small muted" id="inbox_conversation_meta">Private chat, group hoặc channel</span>
                </div>
                <button class="button secondary sm inbox-conversation-refresh" id="inbox_refresh_messages" type="button" disabled>
                    <i class="fa-solid fa-rotate"></i> Làm mới
                </button>
            </header>

            <div class="inbox-message-scroll" id="inbox_message_scroll">
                <button class="button secondary sm inbox-load-older" id="inbox_load_older" type="button" hidden>
                    Tải tin nhắn cũ hơn
                </button>
                <div class="inbox-message-list" id="inbox_message_list">
                    <div class="inbox-empty inbox-empty-large">
                        <i class="fa-regular fa-comments"></i>
                        <strong>Hộp thư chỉ đọc</strong>
                        <span>Chọn một cuộc trò chuyện ở cột bên trái.</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</section>

<script src="<?= e(asset('admin-inbox.js')) ?>" defer></script>
