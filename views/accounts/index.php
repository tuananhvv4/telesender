<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Tài khoản Telegram</h1>
        <div class="inline-actions">
            <button class="button primary" type="button" id="open_account_create">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Tạo tài khoản
            </button>
        </div>
    </div>

    <section class="panel" data-live-region="accounts-panel">
        <div class="panel-header">
            <h2 class="panel-title">Danh sách tài khoản</h2>
        </div>
        <div class="panel-body table-wrap">
            <table>
	                <thead>
	                    <tr>
	                        <th>Tài khoản</th>
	                        <th>Số điện thoại</th>
	                        <th>Trạng thái</th>
	                        <th>Nhóm</th>
	                        <th>Lịch gửi</th>
	                        <th>Hành động</th>
	                    </tr>
	                </thead>
	                <tbody>
	                <?php foreach ($accounts as $account): ?>
                        <?php
                        $status = (string) $account['session_status'];
                        $isActive = (int) ($account['is_active'] ?? 1) === 1;
                        $badgeClass = match ($status) {
                            'active' => 'success',
                            'password_required' => 'warning',
                            'code_sent' => 'info',
                            default => 'warning',
                        };
                        $statusLabel = match ($status) {
                            'active' => 'Đã kết nối',
                            'password_required' => 'Cần mật khẩu 2FA',
                            'code_sent' => 'Đã gửi OTP',
                            'draft' => 'Chưa bắt đầu',
                            default => ucfirst(str_replace('_', ' ', $status)),
                        };
                        ?>
	                    <tr>
	                        <td>
	                            <strong><?= e($account['name']) ?></strong>
	                            <div class="small muted"><?= e($account['tg_username'] ?: $account['session_name']) ?></div>
	                        </td>
	                        <td class="mono"><?= e($account['phone_number']) ?></td>
	                        <td>
	                            <span class="badge <?= e($badgeClass) ?>">
	                                <?= e($statusLabel) ?>
	                            </span>
	                        </td>
	                        <td><?= e((string) $account['groups_count']) ?></td>
	                        <td><?= e((string) $account['schedules_count']) ?></td>
	                        <td>
	                            <div class="status-block">
                                    <form method="post" action="<?= e(url('/accounts/toggle-active')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                        <button class="button <?= $isActive ? 'danger' : 'accent' ?>" type="submit">
                                            <?= $isActive ? 'Tạm dừng tài khoản' : 'Bật lại tài khoản' ?>
                                        </button>
                                    </form>
                                    <?php if ($status === 'active'): ?>
                                        <div class="status-card success">
                                            <div class="status-title"><?= $isActive ? 'Đăng nhập thành công và sẵn sàng hoạt động' : 'Tài khoản đang được tạm dừng' ?></div>
                                            <div class="small muted">Kết nối gần nhất: <?= e(fmt_datetime($account['last_connected_at'])) ?></div>
                                            <?php if (!$isActive): ?>
                                                <div class="small muted">Tài khoản đang tạm dừng, các hoạt động sẽ bị bỏ qua.</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($status === 'code_sent'): ?>
                                        <div class="status-card info">
                                            <div class="status-title">Nhập mã OTP</div>
                                            <form class="status-form" method="post" action="<?= e(url('/accounts/verify-code')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                                <input class="input" type="text" name="code" placeholder="Nhập mã OTP" required>
                                                <button class="button accent" type="submit">Xác thực mã</button>
                                            </form>
                                            <form method="post" action="<?= e(url('/accounts/send-code')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                                <button class="button secondary" type="submit">Gửi lại OTP</button>
                                            </form>
                                        </div>
                                    <?php elseif ($status === 'password_required'): ?>
                                        <div class="status-card warning">
                                            <div class="status-title">Cần mật khẩu 2FA</div>
                                            <form class="status-form" method="post" action="<?= e(url('/accounts/verify-password')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                                <input class="input" type="password" name="password" placeholder="Nhập mật khẩu 2FA" required>
                                                <button class="button secondary" type="submit">Xác thực 2FA</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="status-card">
                                            <div class="status-title">Bắt đầu kết nối</div>
                                            <form method="post" action="<?= e(url('/accounts/send-code')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                                <button class="button secondary" type="submit">Gửi OTP</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
	                            </div>
	                        </td>
	                    </tr>
	                <?php endforeach; ?>
                <?php if ($accounts === []): ?>
                    <tr><td colspan="6" class="muted">Chưa có tài khoản nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-body" style="padding-top: 0;">
            <?php $perPageOptions = [10, 15, 20, 30, 50, 100]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>
</section>

<template id="account_form_template">
    <form class="form-grid" method="post" action="<?= e(url('/accounts')) ?>">
        <?= csrf_field() ?>
        <div class="form-feedback" data-form-feedback hidden></div>
        <div class="field">
            <label for="account_modal_name">Tên hiển thị</label>
            <input class="input" id="account_modal_name" type="text" name="name" placeholder="Ví dụ: Tài khoản Sale #2" required>
        </div>
        <div class="field">
            <label for="account_modal_phone">Số điện thoại Telegram</label>
            <input class="input" id="account_modal_phone" type="text" name="phone_number" placeholder="+8490xxxxxxx" required>
        </div>
        <div class="actions">
            <button class="button primary" type="submit" data-loading-text="Đang tạo...">Tạo tài khoản</button>
            <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
        </div>
    </form>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const createButton = document.getElementById('open_account_create');
    const template = document.getElementById('account_form_template');

    if (!createButton || !template || !window.TeleSenderCrudModal || !window.TeleSenderApp) {
        return;
    }

    createButton.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const form = wrapper.querySelector('form');

        if (!form) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="accounts-panel"]'],
            });
        });

        window.TeleSenderCrudModal.open({
            title: 'Tạo tài khoản Telegram',
            description: 'Tạo trước tên hiển thị và số điện thoại, sau đó bạn có thể xác thực OTP hoặc 2FA ngay trong danh sách.',
            size: 'md',
            content: wrapper,
        });
    });
})();
});
</script>
