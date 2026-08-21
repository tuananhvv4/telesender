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
    data-topics-url="<?= e(url('/admin/inbox/topics')) ?>"
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
        <div class="inbox-readonly-badge">
            <i class="fa-solid fa-lock"></i>
            <span><strong>Chỉ đọc</strong><small>Không gửi hoặc đánh dấu đã đọc</small></span>
        </div>
    </div>

    <div class="inbox-shell">
        <aside class="inbox-dialog-pane" id="inbox_dialog_pane">
            <div class="inbox-pane-head">
                <div class="inbox-dialog-titlebar">
                    <div>
                        <strong>Hội thoại</strong>
                        <span class="small muted" id="inbox_dialog_count">0 cuộc trò chuyện</span>
                    </div>
                    <button class="inbox-icon-button" id="inbox_refresh_dialogs" type="button" disabled aria-label="Làm mới hội thoại" title="Làm mới hội thoại">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
                <label class="inbox-dialog-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="inbox_search" type="search" placeholder="Tìm kiếm" disabled>
                </label>
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
                <button class="inbox-icon-button inbox-conversation-refresh" id="inbox_refresh_messages" type="button" disabled aria-label="Làm mới tin nhắn" title="Làm mới tin nhắn">
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </header>

            <label class="inbox-topic-field" id="inbox_topic_field" hidden>
                <span class="inbox-topic-icon"><i class="fa-solid fa-hashtag"></i></span>
                <span class="inbox-topic-copy">
                    <small>Topic đang xem</small>
                    <select id="inbox_topic_select">
                        <option value="">Tất cả topic</option>
                    </select>
                </span>
                <i class="fa-solid fa-chevron-down"></i>
            </label>

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

            <div class="inbox-readonly-composer">
                <i class="fa-solid fa-lock"></i>
                <span>Chế độ chỉ đọc</span>
                <small>Tin nhắn Telegram không bị đánh dấu đã đọc</small>
            </div>
        </section>
    </div>
</section>

<script src="<?= e(asset('admin-inbox.js')) ?>" defer></script>
