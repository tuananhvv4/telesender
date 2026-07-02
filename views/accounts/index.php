<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Tài khoản Telegram</h1>
    </div>

    <section class="card">
        <h2 class="section-title">Thêm tài khoản mới</h2>
        <form class="form-grid" method="post" action="<?= e(url('/accounts')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Tên hiển thị</label>
                <input class="input" id="name" type="text" name="name" placeholder="Ví dụ: Tài khoản Sale #2" required>
            </div>
            <div class="field">
                <label for="phone_number">Số điện thoại Telegram</label>
                <input class="input" id="phone_number" type="text" name="phone_number" placeholder="+8490xxxxxxx" required>
            </div>
            <button class="button primary" type="submit">Tạo tài khoản</button>
        </form>
    </section>

    <section class="panel">
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
                                    <?php if ($status === 'active'): ?>
                                        <div class="status-card success">
                                            <div class="status-title">Đăng nhập thành công</div>
                                            <div class="small muted">Kết nối gần nhất: <?= e(fmt_datetime($account['last_connected_at'])) ?></div>
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
