<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Telegram Accounts</h1>
            <p class="page-subtitle">Mỗi account là một tài khoản Telegram cá nhân riêng. Sau khi thêm, dùng OTP và 2FA để liên kết phiên đăng nhập thật cho việc gửi tin vào group.</p>
        </div>
        <span class="badge info">Multi Account</span>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h2 class="section-title">Thêm account mới</h2>
            <p class="section-copy">Dùng số điện thoại của tài khoản phụ đã tham gia nhóm Telegram.</p>
            <form class="form-grid" method="post" action="<?= e(url('/accounts')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="name">Tên hiển thị</label>
                    <input class="input" id="name" type="text" name="name" placeholder="Ví dụ: Account Sales #2" required>
                </div>
                <div class="field">
                    <label for="phone_number">Số điện thoại Telegram</label>
                    <input class="input" id="phone_number" type="text" name="phone_number" placeholder="+8490xxxxxxx" required>
                </div>
                <button class="button primary" type="submit">Tạo account</button>
            </form>
        </section>

        <section class="card">
            <h2 class="section-title">Quy trình kết nối</h2>
            <div class="list">
                <div class="list-item">1. Tạo account ở form bên trái.</div>
                <div class="list-item">2. Bấm <strong>Gửi OTP</strong> để Telegram gửi mã xác thực.</div>
                <div class="list-item">3. Nhập OTP vào form <strong>Xác thực mã</strong>.</div>
                <div class="list-item">4. Nếu account bật 2FA, nhập thêm mật khẩu Telegram.</div>
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Danh sách account</h2>
            <p class="panel-copy">Theo dõi trạng thái từng phiên Telegram và thao tác xác thực ngay trên web.</p>
        </div>
        <div class="panel-body table-wrap">
            <table>
	                <thead>
	                    <tr>
	                        <th>Account</th>
	                        <th>Số điện thoại</th>
	                        <th>Trạng thái</th>
	                        <th>Groups</th>
	                        <th>Schedules</th>
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
                                            <p class="status-copy">Tài khoản này đã sẵn sàng để gắn group và chạy schedule.</p>
                                            <div class="small muted">Kết nối gần nhất: <?= e(fmt_datetime($account['last_connected_at'])) ?></div>
                                        </div>
                                    <?php elseif ($status === 'code_sent'): ?>
                                        <div class="status-card info">
                                            <div class="status-title">Nhập mã OTP</div>
                                            <p class="status-copy">Telegram đã gửi mã xác thực. Nhập mã vừa nhận được để hoàn tất bước đăng nhập.</p>
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
                                            <p class="status-copy">Tài khoản này đã qua bước OTP và đang chờ mật khẩu bảo mật 2 lớp của Telegram.</p>
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
                                            <p class="status-copy">Gửi OTP để Telegram bắt đầu quá trình xác thực cho account này.</p>
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
                    <tr><td colspan="6" class="muted">Chưa có account nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
