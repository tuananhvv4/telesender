<?php

declare(strict_types=1);

$systemSummary = $systemSummary ?? [];
$admins = $admins ?? [];
$viewUser = $viewUser ?? null;
$recentLogs = $recentLogs ?? [];
$recentAdjustments = $recentAdjustments ?? [];
$searchQuery = $searchQuery ?? '';

$statusBadgeClass = static fn (string $status): string => $status === 'active' ? 'success' : 'warning';
$subscriptionBadgeClass = static fn (string $state): string => match ($state) {
    'expired' => 'danger',
    'inactive' => 'warning',
    'unlimited', 'super_admin' => 'info',
    default => 'success',
};
$remainingLabel = static function (array $user): string {
    if (($user['subscription_state'] ?? '') === 'unlimited') {
        return 'Không giới hạn';
    }

    $days = $user['days_remaining'] ?? null;

    if ($days === null) {
        return '-';
    }

    if ((int) $days <= 0) {
        return 'Đã hết hạn';
    }

    return (int) $days . ' ngày';
};
?>
<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Quản lý admin con</h1>
            <p class="page-subtitle">Super admin có thể tạo admin mới, khóa/mở khóa thủ công và theo dõi nhanh toàn bộ dữ liệu vận hành của từng admin.</p>
        </div>
    </div>

    <section class="grid grid-4">
        <article class="card stat-card">
            <span class="stat-label">Tổng admin con</span>
            <strong class="stat-value"><?= e((string) ($systemSummary['admins_total'] ?? 0)) ?></strong>
            <span class="small muted"><?= e((string) ($systemSummary['admins_active'] ?? 0)) ?> đang hoạt động</span>
        </article>
        <article class="card stat-card">
            <span class="stat-label">Admin đã khóa</span>
            <strong class="stat-value"><?= e((string) ($systemSummary['admins_inactive'] ?? 0)) ?></strong>
            <span class="small muted"><?= e((string) ($systemSummary['admins_expired'] ?? 0)) ?> đã hết hạn</span>
        </article>
        <article class="card stat-card">
            <span class="stat-label">Telegram account</span>
            <strong class="stat-value"><?= e((string) ($systemSummary['telegram_accounts_total'] ?? 0)) ?></strong>
            <span class="small muted"><?= e((string) ($systemSummary['telegram_accounts_active'] ?? 0)) ?> account đang bật</span>
        </article>
        <article class="card stat-card">
            <span class="stat-label">Schedule</span>
            <strong class="stat-value"><?= e((string) ($systemSummary['schedules_total'] ?? 0)) ?></strong>
            <span class="small muted"><?= e((string) ($systemSummary['schedules_active'] ?? 0)) ?> schedule đang chạy</span>
        </article>
        <article class="card stat-card">
            <span class="stat-label">Nhóm Telegram</span>
            <strong class="stat-value"><?= e((string) ($systemSummary['groups_total'] ?? 0)) ?></strong>
            <span class="small muted">Tổng số đích gửi đã cấu hình</span>
        </article>
        <article class="card stat-card">
            <span class="stat-label">Mẫu tin / Log 24h</span>
            <strong class="stat-value"><?= e((string) ($systemSummary['templates_total'] ?? 0)) ?></strong>
            <span class="small muted"><?= e((string) ($systemSummary['logs_last_24h'] ?? 0)) ?> log trong 24 giờ gần nhất</span>
        </article>
    </section>

    <div class="admin-shell">
        <div class="admin-main">
            <section class="card">
                <div class="builder-block-head">
                    <div>
                        <h2 class="section-title">Tạo admin con mới</h2>
                        <p class="section-copy">Tạo thủ công admin mới với mật khẩu tạm và số ngày sử dụng ban đầu.</p>
                    </div>
                    <span class="badge info">Đăng ký công khai đang tắt</span>
                </div>

                <form class="form-grid admin-form-grid" method="post" action="<?= e(url('/admin/users')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="admin_name">Tên hiển thị</label>
                        <input class="input" id="admin_name" type="text" name="name" placeholder="Ví dụ: Đại lý miền Nam" required>
                    </div>
                    <div class="field">
                        <label for="admin_email">Email đăng nhập</label>
                        <input class="input" id="admin_email" type="email" name="email" placeholder="admin@example.com" required>
                    </div>
                    <div class="field">
                        <label for="admin_password">Mật khẩu tạm</label>
                        <input class="input" id="admin_password" type="password" name="password" placeholder="Tối thiểu nên tự đặt chuỗi mạnh" required>
                    </div>
                    <div class="field">
                        <label for="admin_initial_days">Số ngày sử dụng ban đầu</label>
                        <input class="input" id="admin_initial_days" type="number" name="initial_days" min="1" value="30" required>
                    </div>
                    <div class="field">
                        <label for="admin_max_telegram_accounts">Giới hạn account Telegram</label>
                        <input class="input" id="admin_max_telegram_accounts" type="number" name="max_telegram_accounts" min="0" placeholder="Bỏ trống = không giới hạn">
                    </div>
                    <div class="field">
                        <label for="admin_max_schedule_jobs">Giới hạn schedule</label>
                        <input class="input" id="admin_max_schedule_jobs" type="number" name="max_schedule_jobs" min="0" placeholder="Bỏ trống = không giới hạn">
                    </div>
                    <div class="field admin-form-span-2">
                        <label for="admin_internal_note">Ghi chú nội bộ</label>
                        <textarea class="textarea" id="admin_internal_note" name="internal_note" rows="3" placeholder="Chỉ super admin nhìn thấy ghi chú này. Ví dụ: đại lý cũ, thanh toán thủ công, cần follow riêng..."></textarea>
                    </div>
                    <label class="checkbox-row admin-form-span-2">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>Kích hoạt admin này ngay sau khi tạo</span>
                    </label>
                    <div class="actions admin-form-span-2">
                        <button class="button primary" type="submit">Tạo admin con</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Danh sách admin con</h2>
                    <form class="toolbar-form" method="get" action="<?= e(url('/admin/users')) ?>">
                        <?php if ((int) request()->query('per_page', 0) > 0): ?>
                            <input type="hidden" name="per_page" value="<?= e((string) request()->query('per_page')) ?>">
                        <?php endif; ?>
                        <div class="toolbar-search">
                            <input class="input" type="text" name="q" value="<?= e((string) $searchQuery) ?>" placeholder="Tìm theo tên, email hoặc trạng thái...">
                            <button class="button secondary" type="submit">Lọc</button>
                            <?php if ($searchQuery !== ''): ?>
                                <a class="button secondary" href="<?= e(url('/admin/users')) ?>">Xóa lọc</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="panel-body admin-user-list">
                    <?php foreach ($admins as $admin): ?>
                        <article class="admin-user-card">
                            <div class="admin-user-head">
                                <div class="admin-user-title">
                                    <strong><?= e((string) $admin['name']) ?></strong>
                                    <div class="small muted"><?= e((string) $admin['email']) ?></div>
                                    <?php if (!empty($admin['internal_note'])): ?>
                                        <div class="small muted"><?= e(mb_strimwidth((string) $admin['internal_note'], 0, 120, '...')) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="inline-actions">
                                    <span class="badge <?= e($statusBadgeClass((string) ($admin['status'] ?? 'inactive'))) ?>">
                                        <?= (string) ($admin['status'] ?? 'inactive') === 'active' ? 'Đang bật' : 'Đã khóa' ?>
                                    </span>
                                    <span class="badge <?= e($subscriptionBadgeClass((string) ($admin['subscription_state'] ?? 'inactive'))) ?>">
                                        <?= e((string) ($admin['subscription_label'] ?? '-')) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="admin-user-stats">
                                <div class="admin-mini-stat">
                                    <span>Ngày còn lại</span>
                                    <strong><?= e($remainingLabel($admin)) ?></strong>
                                </div>
                                <div class="admin-mini-stat">
                                    <span>Account</span>
                                    <strong><?= e((string) ($admin['telegram_accounts_total'] ?? 0)) ?> / <?= e((string) ($admin['account_limit_label'] ?? 'Không giới hạn')) ?></strong>
                                </div>
                                <div class="admin-mini-stat">
                                    <span>Schedule</span>
                                    <strong><?= e((string) ($admin['schedules_total'] ?? 0)) ?> / <?= e((string) ($admin['schedule_limit_label'] ?? 'Không giới hạn')) ?></strong>
                                </div>
                                <div class="admin-mini-stat">
                                    <span>Log gửi</span>
                                    <strong><?= e((string) ($admin['logs_total'] ?? 0)) ?></strong>
                                </div>
                            </div>

                            <div class="inline-actions">
                                <a class="button secondary sm" href="<?= e(url('/admin/users?view=' . $admin['id'])) ?>">Xem chi tiết</a>
                                <a class="button secondary sm" href="<?= e(url('/admin/subscriptions?user=' . $admin['id'])) ?>">Gia hạn</a>
                                <form method="post" action="<?= e(url('/admin/users/status')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= e((string) $admin['id']) ?>">
                                    <button class="button <?= (string) ($admin['status'] ?? 'inactive') === 'active' ? 'danger' : 'accent' ?> sm" type="submit">
                                        <?= (string) ($admin['status'] ?? 'inactive') === 'active' ? 'Khóa admin' : 'Mở khóa' ?>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <?php if ($admins === []): ?>
                        <div class="muted">Chưa có admin con nào.</div>
                    <?php endif; ?>
                </div>

                <div class="panel-body" style="padding-top: 0;">
                    <?php $perPageOptions = [10, 15, 20, 30, 50, 100]; ?>
                    <?php require base_path('views/partials/pagination.php'); ?>
                </div>
            </section>
        </div>

        <aside class="admin-side">
            <?php if ($viewUser !== null): ?>
                <section class="card admin-side-card">
                    <div class="builder-block-head">
                        <div>
                            <h2 class="section-title">Chi tiết admin</h2>
                            <p class="section-copy"><?= e((string) $viewUser['name']) ?> · <?= e((string) $viewUser['email']) ?></p>
                        </div>
                        <div class="inline-actions">
                            <span class="badge <?= e($statusBadgeClass((string) ($viewUser['status'] ?? 'inactive'))) ?>">
                                <?= (string) ($viewUser['status'] ?? 'inactive') === 'active' ? 'Đang bật' : 'Đã khóa' ?>
                            </span>
                            <span class="badge <?= e($subscriptionBadgeClass((string) ($viewUser['subscription_state'] ?? 'inactive'))) ?>">
                                <?= e((string) ($viewUser['subscription_label'] ?? '-')) ?>
                            </span>
                        </div>
                    </div>

                    <div class="admin-detail-grid">
                        <div class="admin-mini-stat">
                            <span>Ngày còn lại</span>
                            <strong><?= e($remainingLabel($viewUser)) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Ngày tạo</span>
                            <strong><?= e(fmt_datetime((string) ($viewUser['created_at'] ?? ''))) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Account active</span>
                            <strong><?= e((string) ($viewUser['telegram_accounts_active'] ?? 0)) ?> / <?= e((string) ($viewUser['telegram_accounts_total'] ?? 0)) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Quota account</span>
                            <strong><?= e((string) ($viewUser['telegram_accounts_total'] ?? 0)) ?> / <?= e((string) ($viewUser['account_limit_label'] ?? 'Không giới hạn')) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Group active</span>
                            <strong><?= e((string) ($viewUser['groups_active'] ?? 0)) ?> / <?= e((string) ($viewUser['groups_total'] ?? 0)) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Schedule active</span>
                            <strong><?= e((string) ($viewUser['schedules_active'] ?? 0)) ?> / <?= e((string) ($viewUser['schedules_total'] ?? 0)) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Quota schedule</span>
                            <strong><?= e((string) ($viewUser['schedules_total'] ?? 0)) ?> / <?= e((string) ($viewUser['schedule_limit_label'] ?? 'Không giới hạn')) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Template</span>
                            <strong><?= e((string) ($viewUser['templates_total'] ?? 0)) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Log thành công 7 ngày</span>
                            <strong><?= e((string) ($viewUser['logs_success_recent'] ?? 0)) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Log lỗi 7 ngày</span>
                            <strong><?= e((string) ($viewUser['logs_error_recent'] ?? 0)) ?></strong>
                        </div>
                    </div>

                    <div class="list">
                        <div class="list-item">
                            <div class="builder-block-head">
                                <strong>Quản lý nội bộ</strong>
                            </div>
                            <form class="form-grid" method="post" action="<?= e(url('/admin/users/limits')) ?>" style="padding-top: 12px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= e((string) $viewUser['id']) ?>">
                                <div class="field">
                                    <label for="view_max_telegram_accounts">Tối đa account Telegram</label>
                                    <input class="input" id="view_max_telegram_accounts" type="number" name="max_telegram_accounts" min="0" value="<?= e(isset($viewUser['account_limit']) && $viewUser['account_limit'] !== null ? (string) $viewUser['account_limit'] : '') ?>" placeholder="Bỏ trống = không giới hạn">
                                </div>
                                <div class="field">
                                    <label for="view_max_schedule_jobs">Tối đa schedule</label>
                                    <input class="input" id="view_max_schedule_jobs" type="number" name="max_schedule_jobs" min="0" value="<?= e(isset($viewUser['schedule_limit']) && $viewUser['schedule_limit'] !== null ? (string) $viewUser['schedule_limit'] : '') ?>" placeholder="Bỏ trống = không giới hạn">
                                </div>
                                <div class="field">
                                    <label for="view_internal_note">Ghi chú nội bộ</label>
                                    <textarea class="textarea" id="view_internal_note" name="internal_note" rows="4" placeholder="Chỉ super admin nhìn thấy ghi chú này."><?= e((string) ($viewUser['internal_note'] ?? '')) ?></textarea>
                                </div>
                                <div class="small muted">Nếu để trống thì admin này sẽ không bị giới hạn số lượng account hoặc schedule.</div>
                                <div class="actions">
                                    <button class="button primary sm" type="submit">Lưu thông tin nội bộ</button>
                                </div>
                            </form>
                        </div>
                        <?php if (!empty($viewUser['internal_note'])): ?>
                            <div class="list-item">
                                <strong>Ghi chú hiện tại</strong>
                                <div class="small muted" style="margin-top: 8px; white-space: pre-line;"><?= e((string) $viewUser['internal_note']) ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="list-item">
                            <strong>Lần gửi gần nhất</strong>
                            <div class="small muted"><?= e(fmt_datetime((string) ($viewUser['last_dispatch_at'] ?? ''))) ?></div>
                        </div>
                        <div class="list-item">
                            <div class="builder-block-head">
                                <strong>Lịch sử chỉnh hạn gần đây</strong>
                                <a class="button secondary sm" href="<?= e(url('/admin/subscriptions?user=' . $viewUser['id'])) ?>">Mở trang hạn dùng</a>
                            </div>
                            <div class="admin-compact-list">
                                <?php foreach ($recentAdjustments as $adjustment): ?>
                                    <div class="admin-compact-row">
                                        <div>
                                            <strong><?= ((int) ($adjustment['delta_days'] ?? 0) > 0 ? '+' : '') . e((string) $adjustment['delta_days']) ?> ngày</strong>
                                            <div class="small muted"><?= e((string) ($adjustment['actor_name'] ?? 'Super admin')) ?> · <?= e(fmt_datetime((string) ($adjustment['created_at'] ?? ''))) ?></div>
                                        </div>
                                        <div class="small muted admin-compact-side">
                                            <?= e(fmt_datetime((string) ($adjustment['new_expires_at'] ?? ''))) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($recentAdjustments === []): ?>
                                    <div class="muted">Chưa có lịch sử điều chỉnh hạn dùng.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="list-item">
                            <strong>Log gửi gần đây</strong>
                            <div class="admin-compact-list" style="margin-top: 12px;">
                                <?php foreach ($recentLogs as $log): ?>
                                    <div class="admin-compact-row">
                                        <div>
                                            <strong><?= e((string) ($log['template_name'] ?? 'Không rõ mẫu')) ?></strong>
                                            <div class="small muted"><?= e((string) ($log['group_title'] ?? 'Không rõ nhóm')) ?> · <?= e(fmt_datetime((string) ($log['sent_at'] ?? ''))) ?></div>
                                        </div>
                                        <span class="badge <?= (string) ($log['status'] ?? '') === 'success' ? 'success' : 'danger' ?>">
                                            <?= (string) ($log['status'] ?? '') === 'success' ? 'Thành công' : 'Lỗi' ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($recentLogs === []): ?>
                                    <div class="muted">Chưa có log gửi nào.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <section class="card admin-side-card">
                    <h2 class="section-title">Chi tiết admin</h2>
                    <p class="section-copy">Chọn một admin ở danh sách bên trái để xem nhanh số account, group, schedule, template, log gửi và lịch sử gia hạn.</p>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</section>
