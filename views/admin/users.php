<?php

declare(strict_types=1);

$systemSummary = $systemSummary ?? [];
$admins = $admins ?? [];
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
        <div class="inline-actions">
            <button class="button primary" type="button" id="open_admin_create">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Tạo admin con
            </button>
        </div>
    </div>

    <div data-live-region="admin-users-shell">
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
                        <button class="button secondary sm" type="button" data-admin-detail="<?= e((string) $admin['id']) ?>">Chi tiết / sửa</button>
                        <a class="button secondary sm" href="<?= e(url('/admin/subscriptions')) ?>">Quản lý hạn dùng</a>
                        <form method="post" action="<?= e(url('/admin/users/status')) ?>" data-ajax-form data-ajax-refresh="admin-users-shell">
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
</section>

<template id="admin_create_template">
    <form class="form-grid admin-form-grid" method="post" action="<?= e(url('/admin/users')) ?>">
        <?= csrf_field() ?>
        <div class="form-feedback" data-form-feedback hidden></div>
        <div class="field">
            <label for="admin_modal_name">Tên hiển thị</label>
            <input class="input" id="admin_modal_name" type="text" name="name" placeholder="Ví dụ: Đại lý miền Nam" required>
        </div>
        <div class="field">
            <label for="admin_modal_email">Email đăng nhập</label>
            <input class="input" id="admin_modal_email" type="email" name="email" placeholder="admin@example.com" required>
        </div>
        <div class="field">
            <label for="admin_modal_password">Mật khẩu tạm</label>
            <input class="input" id="admin_modal_password" type="password" name="password" placeholder="Tối thiểu nên tự đặt chuỗi mạnh" required>
        </div>
        <div class="field">
            <label for="admin_modal_initial_days">Số ngày sử dụng ban đầu</label>
            <input class="input" id="admin_modal_initial_days" type="number" name="initial_days" min="1" value="30" required>
        </div>
        <div class="field">
            <label for="admin_modal_max_accounts">Giới hạn account Telegram</label>
            <input class="input" id="admin_modal_max_accounts" type="number" name="max_telegram_accounts" min="0" placeholder="Bỏ trống = không giới hạn">
        </div>
        <div class="field">
            <label for="admin_modal_max_schedules">Giới hạn schedule</label>
            <input class="input" id="admin_modal_max_schedules" type="number" name="max_schedule_jobs" min="0" placeholder="Bỏ trống = không giới hạn">
        </div>
        <div class="field admin-form-span-2">
            <label for="admin_modal_note">Ghi chú nội bộ</label>
            <textarea class="textarea" id="admin_modal_note" name="internal_note" rows="3" placeholder="Chỉ super admin nhìn thấy ghi chú này."></textarea>
        </div>
        <label class="checkbox-row admin-form-span-2">
            <input type="checkbox" name="is_active" value="1" checked>
            <span>Kích hoạt admin này ngay sau khi tạo</span>
        </label>
        <div class="actions admin-form-span-2">
            <button class="button primary" type="submit" data-loading-text="Đang tạo...">Tạo admin con</button>
            <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
        </div>
    </form>
</template>

<template id="admin_detail_template">
    <div class="stack">
        <div class="inline-actions">
            <span class="badge" data-admin-status-badge></span>
            <span class="badge" data-admin-subscription-badge></span>
        </div>

        <div class="admin-detail-grid">
            <div class="admin-mini-stat">
                <span>Ngày còn lại</span>
                <strong data-admin-remaining>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Ngày tạo</span>
                <strong data-admin-created-at>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Account active</span>
                <strong data-admin-account-active>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Quota account</span>
                <strong data-admin-account-limit>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Group active</span>
                <strong data-admin-group-active>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Schedule active</span>
                <strong data-admin-schedule-active>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Quota schedule</span>
                <strong data-admin-schedule-limit>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Template</span>
                <strong data-admin-templates>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Log thành công 7 ngày</span>
                <strong data-admin-log-success>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Log lỗi 7 ngày</span>
                <strong data-admin-log-error>-</strong>
            </div>
        </div>

        <div class="list">
            <div class="list-item">
                <div class="builder-block-head">
                    <strong>Quản lý nội bộ</strong>
                </div>
                <form class="form-grid" method="post" action="<?= e(url('/admin/users/limits')) ?>" style="padding-top: 12px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="">
                    <div class="form-feedback" data-form-feedback hidden></div>
                    <div class="field">
                        <label for="admin_detail_max_accounts">Tối đa account Telegram</label>
                        <input class="input" id="admin_detail_max_accounts" type="number" name="max_telegram_accounts" min="0" placeholder="Bỏ trống = không giới hạn">
                    </div>
                    <div class="field">
                        <label for="admin_detail_max_schedules">Tối đa schedule</label>
                        <input class="input" id="admin_detail_max_schedules" type="number" name="max_schedule_jobs" min="0" placeholder="Bỏ trống = không giới hạn">
                    </div>
                    <div class="field">
                        <label for="admin_detail_note">Ghi chú nội bộ</label>
                        <textarea class="textarea" id="admin_detail_note" name="internal_note" rows="4" placeholder="Chỉ super admin nhìn thấy ghi chú này."></textarea>
                    </div>
                    <div class="small muted">Nếu để trống thì admin này sẽ không bị giới hạn số lượng account hoặc schedule.</div>
                    <div class="actions">
                        <button class="button primary sm" type="submit" data-loading-text="Đang lưu...">Lưu thông tin nội bộ</button>
                        <button class="button secondary sm" type="button" data-crud-modal-close>Đóng</button>
                    </div>
                </form>
            </div>

            <div class="list-item">
                <div class="builder-block-head">
                    <strong>Lịch sử chỉnh hạn gần đây</strong>
                    <a class="button secondary sm" href="<?= e(url('/admin/subscriptions')) ?>">Mở trang hạn dùng</a>
                </div>
                <div class="admin-compact-list" data-admin-adjustments style="margin-top: 12px;"></div>
            </div>

            <div class="list-item">
                <strong>Log gửi gần đây</strong>
                <div class="admin-compact-list" data-admin-logs style="margin-top: 12px;"></div>
            </div>

            <div class="list-item">
                <strong>Lần gửi gần nhất</strong>
                <div class="small muted" data-admin-last-dispatch>-</div>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const createButton = document.getElementById('open_admin_create');
    const createTemplate = document.getElementById('admin_create_template');
    const detailTemplate = document.getElementById('admin_detail_template');
    const detailsBaseUrl = <?= json_encode(url('/admin/users/details'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    if (!window.TeleSenderCrudModal || !window.TeleSenderApp) {
        return;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function badgeClassForStatus(status) {
        return status === 'active' ? 'success' : 'warning';
    }

    function badgeClassForSubscription(state) {
        if (state === 'expired') {
            return 'danger';
        }

        if (state === 'inactive') {
            return 'warning';
        }

        if (state === 'unlimited' || state === 'super_admin') {
            return 'info';
        }

        return 'success';
    }

    function renderAdjustments(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            return '<div class="muted">Chưa có lịch sử điều chỉnh hạn dùng.</div>';
        }

        return rows.map((row) => `
            <div class="admin-compact-row">
                <div>
                    <strong>${escapeHtml(row.delta_label || '')}</strong>
                    <div class="small muted">${escapeHtml(row.actor_name || '')} · ${escapeHtml(row.created_at_label || '')}</div>
                </div>
                <div class="small muted admin-compact-side">${escapeHtml(row.new_expires_at_label || '')}</div>
            </div>
        `).join('');
    }

    function renderLogs(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            return '<div class="muted">Chưa có log gửi gần đây.</div>';
        }

        return rows.map((row) => `
            <div class="admin-compact-row">
                <div>
                    <strong>${escapeHtml(row.template_name || '')}</strong>
                    <div class="small muted">${escapeHtml(row.group_title || '')} · ${escapeHtml(row.sent_at_label || '')}</div>
                </div>
                <span class="badge ${row.status === 'success' ? 'success' : 'danger'}">${row.status === 'success' ? 'Thành công' : 'Lỗi'}</span>
            </div>
        `).join('');
    }

    function openCreateModal() {
        if (!createTemplate) {
            return;
        }

        const fragment = createTemplate.content.cloneNode(true);
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
                refreshRegionsOnSuccess: ['[data-live-region="admin-users-shell"]'],
            });
        });

        window.TeleSenderCrudModal.open({
            title: 'Tạo admin con mới',
            description: 'Tạo thủ công admin mới với mật khẩu tạm và số ngày sử dụng ban đầu.',
            size: 'xl',
            content: wrapper,
        });
    }

    async function openDetailModal(userId) {
        if (!detailTemplate) {
            return;
        }

        let payload = null;

        try {
            payload = await window.TeleSenderApp.fetchJson(`${detailsBaseUrl}?user_id=${encodeURIComponent(String(userId || ''))}`);
        } catch (error) {
            window.TeleSenderApp.showFlash('error', error.message || 'Không tải được thông tin admin.');
            return;
        }

        const user = payload.user || {};
        const recentAdjustments = Array.isArray(payload.recent_adjustments) ? payload.recent_adjustments : [];
        const recentLogs = Array.isArray(payload.recent_logs) ? payload.recent_logs : [];
        const fragment = detailTemplate.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const statusBadge = wrapper.querySelector('[data-admin-status-badge]');
        const subscriptionBadge = wrapper.querySelector('[data-admin-subscription-badge]');
        const remainingTarget = wrapper.querySelector('[data-admin-remaining]');
        const createdAtTarget = wrapper.querySelector('[data-admin-created-at]');
        const accountActiveTarget = wrapper.querySelector('[data-admin-account-active]');
        const accountLimitTarget = wrapper.querySelector('[data-admin-account-limit]');
        const groupActiveTarget = wrapper.querySelector('[data-admin-group-active]');
        const scheduleActiveTarget = wrapper.querySelector('[data-admin-schedule-active]');
        const scheduleLimitTarget = wrapper.querySelector('[data-admin-schedule-limit]');
        const templatesTarget = wrapper.querySelector('[data-admin-templates]');
        const logSuccessTarget = wrapper.querySelector('[data-admin-log-success]');
        const logErrorTarget = wrapper.querySelector('[data-admin-log-error]');
        const lastDispatchTarget = wrapper.querySelector('[data-admin-last-dispatch]');
        const adjustmentsTarget = wrapper.querySelector('[data-admin-adjustments]');
        const logsTarget = wrapper.querySelector('[data-admin-logs]');
        const form = wrapper.querySelector('form');
        const userIdField = wrapper.querySelector('input[name="user_id"]');
        const maxAccountsField = wrapper.querySelector('input[name="max_telegram_accounts"]');
        const maxSchedulesField = wrapper.querySelector('input[name="max_schedule_jobs"]');
        const noteField = wrapper.querySelector('textarea[name="internal_note"]');

        if (
            !statusBadge || !subscriptionBadge || !remainingTarget || !createdAtTarget || !accountActiveTarget
            || !accountLimitTarget || !groupActiveTarget || !scheduleActiveTarget || !scheduleLimitTarget
            || !templatesTarget || !logSuccessTarget || !logErrorTarget || !lastDispatchTarget
            || !adjustmentsTarget || !logsTarget || !form || !userIdField || !maxAccountsField
            || !maxSchedulesField || !noteField
        ) {
            return;
        }

        statusBadge.className = `badge ${badgeClassForStatus(String(user.status || 'inactive'))}`;
        statusBadge.textContent = String(user.status || 'inactive') === 'active' ? 'Đang bật' : 'Đã khóa';
        subscriptionBadge.className = `badge ${badgeClassForSubscription(String(user.subscription_state || 'inactive'))}`;
        subscriptionBadge.textContent = user.subscription_label || '-';
        remainingTarget.textContent = user.remaining_label || '-';
        createdAtTarget.textContent = user.created_at_label || '-';
        accountActiveTarget.textContent = `${user.telegram_accounts_active || 0} / ${user.telegram_accounts_total || 0}`;
        accountLimitTarget.textContent = `${user.telegram_accounts_total || 0} / ${user.account_limit_label || 'Không giới hạn'}`;
        groupActiveTarget.textContent = `${user.groups_active || 0} / ${user.groups_total || 0}`;
        scheduleActiveTarget.textContent = `${user.schedules_active || 0} / ${user.schedules_total || 0}`;
        scheduleLimitTarget.textContent = `${user.schedules_total || 0} / ${user.schedule_limit_label || 'Không giới hạn'}`;
        templatesTarget.textContent = String(user.templates_total || 0);
        logSuccessTarget.textContent = String(user.logs_success_recent || 0);
        logErrorTarget.textContent = String(user.logs_error_recent || 0);
        lastDispatchTarget.textContent = user.last_dispatch_at_label || '-';
        adjustmentsTarget.innerHTML = renderAdjustments(recentAdjustments);
        logsTarget.innerHTML = renderLogs(recentLogs);
        userIdField.value = String(user.id || '');
        maxAccountsField.value = user.account_limit ?? '';
        maxSchedulesField.value = user.schedule_limit ?? '';
        noteField.value = user.internal_note || '';

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="admin-users-shell"]'],
            });
        });

        window.TeleSenderCrudModal.open({
            title: 'Chi tiết admin',
            description: `${user.name || 'Admin'} · ${user.email || ''}`,
            size: 'xl',
            content: wrapper,
        });
    }

    if (createButton) {
        createButton.addEventListener('click', openCreateModal);
    }

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-admin-detail]') : null;

        if (!button) {
            return;
        }

        openDetailModal(button.getAttribute('data-admin-detail'));
    });
})();
});
</script>
