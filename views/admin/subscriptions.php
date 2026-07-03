<?php

declare(strict_types=1);

$admins = $admins ?? [];
$focusUser = $focusUser ?? null;
$adjustmentLogs = $adjustmentLogs ?? [];
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
            <h1 class="page-title">Quản lý hạn sử dụng</h1>
            <p class="page-subtitle">Theo dõi ngày hết hạn, gia hạn thủ công hoặc trừ ngày dùng của từng admin con. Mọi thay đổi đều được lưu lịch sử để đối soát.</p>
        </div>
    </div>

    <div class="admin-shell">
        <div class="admin-main">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Danh sách hạn dùng</h2>
                    <form class="toolbar-form" method="get" action="<?= e(url('/admin/subscriptions')) ?>">
                        <?php if ((int) request()->query('per_page', 0) > 0): ?>
                            <input type="hidden" name="per_page" value="<?= e((string) request()->query('per_page')) ?>">
                        <?php endif; ?>
                        <div class="toolbar-search">
                            <input class="input" type="text" name="q" value="<?= e((string) $searchQuery) ?>" placeholder="Tìm theo tên, email hoặc trạng thái...">
                            <button class="button secondary" type="submit">Lọc</button>
                            <?php if ($searchQuery !== ''): ?>
                                <a class="button secondary" href="<?= e(url('/admin/subscriptions')) ?>">Xóa lọc</a>
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
                                    <span>Hết hạn lúc</span>
                                    <strong><?= !empty($admin['subscription_expires_at']) ? e(fmt_datetime((string) $admin['subscription_expires_at'])) : 'Không giới hạn' ?></strong>
                                </div>
                                <div class="admin-mini-stat">
                                    <span>Schedule</span>
                                    <strong><?= e((string) ($admin['schedules_total'] ?? 0)) ?></strong>
                                </div>
                                <div class="admin-mini-stat">
                                    <span>Log</span>
                                    <strong><?= e((string) ($admin['logs_total'] ?? 0)) ?></strong>
                                </div>
                            </div>

                            <div class="inline-actions">
                                <a class="button secondary sm" href="<?= e(url('/admin/subscriptions?user=' . $admin['id'])) ?>">Chỉnh hạn</a>
                                <a class="button secondary sm" href="<?= e(url('/admin/users?view=' . $admin['id'])) ?>">Xem hồ sơ</a>
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
            <?php if ($focusUser !== null): ?>
                <section class="card admin-side-card">
                    <div class="builder-block-head">
                        <div>
                            <h2 class="section-title">Điều chỉnh hạn dùng</h2>
                            <p class="section-copy"><?= e((string) $focusUser['name']) ?> · <?= e((string) $focusUser['email']) ?></p>
                        </div>
                        <span class="badge <?= e($subscriptionBadgeClass((string) ($focusUser['subscription_state'] ?? 'inactive'))) ?>">
                            <?= e((string) ($focusUser['subscription_label'] ?? '-')) ?>
                        </span>
                    </div>

                    <div class="admin-detail-grid">
                        <div class="admin-mini-stat">
                            <span>Ngày còn lại</span>
                            <strong><?= e($remainingLabel($focusUser)) ?></strong>
                        </div>
                        <div class="admin-mini-stat">
                            <span>Hết hạn lúc</span>
                            <strong><?= !empty($focusUser['subscription_expires_at']) ? e(fmt_datetime((string) $focusUser['subscription_expires_at'])) : 'Không giới hạn' ?></strong>
                        </div>
                    </div>

                    <form class="form-grid" method="post" action="<?= e(url('/admin/subscriptions/adjust')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= e((string) $focusUser['id']) ?>">

                        <div class="field">
                            <label for="subscription_direction">Thao tác</label>
                            <select class="select" id="subscription_direction" name="direction">
                                <option value="add">Cộng thêm ngày</option>
                                <option value="subtract">Trừ bớt ngày</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="subscription_days">Số ngày</label>
                            <input class="input" id="subscription_days" type="number" name="days" min="1" value="30" required>
                        </div>

                        <div class="field">
                            <label for="subscription_note">Ghi chú</label>
                            <textarea class="textarea" id="subscription_note" name="note" rows="3" placeholder="Ví dụ: Gia hạn thủ công theo chuyển khoản tháng 7"></textarea>
                        </div>

                        <?php if (($focusUser['subscription_state'] ?? '') === 'unlimited'): ?>
                            <div class="hint-box">
                                <strong>User legacy đang ở trạng thái không giới hạn.</strong>
                                <div class="small muted">Bạn chỉ nên cộng ngày để chuyển user này sang cơ chế có hạn dùng. Không thể trừ ngày trực tiếp khi chưa có mốc hết hạn.</div>
                            </div>
                        <?php endif; ?>

                        <div class="actions">
                            <button class="button primary" type="submit">Lưu điều chỉnh</button>
                        </div>
                    </form>

                    <div class="list">
                        <div class="list-item">
                            <strong>Lịch sử điều chỉnh</strong>
                            <div class="admin-compact-list" style="margin-top: 12px;">
                                <?php foreach ($adjustmentLogs as $adjustment): ?>
                                    <div class="admin-compact-row">
                                        <div>
                                            <strong><?= ((int) ($adjustment['delta_days'] ?? 0) > 0 ? '+' : '') . e((string) $adjustment['delta_days']) ?> ngày</strong>
                                            <div class="small muted">
                                                <?= e((string) ($adjustment['actor_name'] ?? 'Super admin')) ?> · <?= e(fmt_datetime((string) ($adjustment['created_at'] ?? ''))) ?>
                                            </div>
                                            <?php if (!empty($adjustment['note'])): ?>
                                                <div class="small muted"><?= e((string) $adjustment['note']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small muted admin-compact-side">
                                            <div>Từ: <?= e(fmt_datetime((string) ($adjustment['previous_expires_at'] ?? ''))) ?></div>
                                            <div>Đến: <?= e(fmt_datetime((string) ($adjustment['new_expires_at'] ?? ''))) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($adjustmentLogs === []): ?>
                                    <div class="muted">Chưa có lịch sử điều chỉnh nào.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <section class="card admin-side-card">
                    <h2 class="section-title">Chọn admin để chỉnh hạn</h2>
                    <p class="section-copy">Bấm “Chỉnh hạn” ở danh sách bên trái để cộng/trừ ngày sử dụng, ghi chú và xem toàn bộ lịch sử thay đổi.</p>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</section>
