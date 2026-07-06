<?php

declare(strict_types=1);

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
            <h1 class="page-title">Quản lý hạn sử dụng</h1>
            <p class="page-subtitle">Theo dõi ngày hết hạn, gia hạn thủ công hoặc trừ ngày dùng của từng admin con. Mọi thay đổi đều được lưu lịch sử để đối soát.</p>
        </div>
    </div>

    <section class="panel" data-live-region="admin-subscriptions-panel">
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
                        <button class="button secondary sm" type="button" data-subscription-edit="<?= e((string) $admin['id']) ?>">Chỉnh hạn</button>
                        <a class="button secondary sm" href="<?= e(url('/admin/users')) ?>">Mở trang admin</a>
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
</section>

<template id="subscription_modal_template">
    <div class="stack">
        <div class="admin-detail-grid">
            <div class="admin-mini-stat">
                <span>Ngày còn lại</span>
                <strong data-subscription-remaining>-</strong>
            </div>
            <div class="admin-mini-stat">
                <span>Hết hạn lúc</span>
                <strong data-subscription-expires>-</strong>
            </div>
        </div>

        <form class="form-grid" method="post" action="<?= e(url('/admin/subscriptions/adjust')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="">
            <div class="form-feedback" data-form-feedback hidden></div>

            <div class="field">
                <label for="subscription_direction_modal">Thao tác</label>
                <select class="select" id="subscription_direction_modal" name="direction">
                    <option value="add">Cộng thêm ngày</option>
                    <option value="subtract">Trừ bớt ngày</option>
                </select>
            </div>

            <div class="field">
                <label for="subscription_days_modal">Số ngày</label>
                <input class="input" id="subscription_days_modal" type="number" name="days" min="1" value="30" required>
            </div>

            <div class="field">
                <label for="subscription_note_modal">Ghi chú</label>
                <textarea class="textarea" id="subscription_note_modal" name="note" rows="3" placeholder="Ví dụ: Gia hạn thủ công theo chuyển khoản tháng 7"></textarea>
            </div>

            <div class="hint-box" data-subscription-unlimited-hint hidden>
                <strong>User legacy đang ở trạng thái không giới hạn.</strong>
                <div class="small muted">Bạn chỉ nên cộng ngày để chuyển user này sang cơ chế có hạn dùng. Không thể trừ ngày trực tiếp khi chưa có mốc hết hạn.</div>
            </div>

            <div class="actions">
                <button class="button primary" type="submit" data-loading-text="Đang lưu...">Lưu điều chỉnh</button>
                <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
            </div>
        </form>

        <div class="list">
            <div class="list-item">
                <strong>Lịch sử điều chỉnh</strong>
                <div class="admin-compact-list" data-subscription-history style="margin-top: 12px;"></div>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const template = document.getElementById('subscription_modal_template');
    const detailsBaseUrl = <?= json_encode(url('/admin/subscriptions/details'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    if (!template || !window.TeleSenderCrudModal || !window.TeleSenderApp) {
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

    function renderHistory(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            return '<div class="muted">Chưa có lịch sử điều chỉnh nào.</div>';
        }

        return rows.map((row) => {
            return `
                <div class="admin-compact-row">
                    <div>
                        <strong>${escapeHtml(row.delta_label || '')}</strong>
                        <div class="small muted">${escapeHtml(row.actor_name || '')} · ${escapeHtml(row.created_at_label || '')}</div>
                        ${row.note ? `<div class="small muted">${escapeHtml(row.note)}</div>` : ''}
                    </div>
                    <div class="small muted admin-compact-side">
                        <div>Từ: ${escapeHtml(row.previous_expires_at_label || '')}</div>
                        <div>Đến: ${escapeHtml(row.new_expires_at_label || '')}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function openSubscriptionModal(userId) {
        let payload = null;

        try {
            payload = await window.TeleSenderApp.fetchJson(`${detailsBaseUrl}?user_id=${encodeURIComponent(String(userId || ''))}`);
        } catch (error) {
            window.TeleSenderApp.showFlash('error', error.message || 'Không tải được thông tin hạn dùng.');
            return;
        }

        const user = payload.user || {};
        const adjustments = Array.isArray(payload.adjustments) ? payload.adjustments : [];
        const fragment = template.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const form = wrapper.querySelector('form');
        const userIdField = wrapper.querySelector('input[name="user_id"]');
        const remainingTarget = wrapper.querySelector('[data-subscription-remaining]');
        const expiresTarget = wrapper.querySelector('[data-subscription-expires]');
        const unlimitedHint = wrapper.querySelector('[data-subscription-unlimited-hint]');
        const historyTarget = wrapper.querySelector('[data-subscription-history]');

        if (!form || !userIdField || !remainingTarget || !expiresTarget || !unlimitedHint || !historyTarget) {
            return;
        }

        userIdField.value = String(user.id || '');
        remainingTarget.textContent = user.remaining_label || '-';
        expiresTarget.textContent = user.expires_at_label || '-';
        unlimitedHint.hidden = user.subscription_state !== 'unlimited';
        historyTarget.innerHTML = renderHistory(adjustments);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="admin-subscriptions-panel"]'],
            });
        });

        window.TeleSenderCrudModal.open({
            title: 'Điều chỉnh hạn dùng',
            description: `${user.name || 'Admin'} · ${user.email || ''}`,
            size: 'lg',
            content: wrapper,
        });
    }

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-subscription-edit]') : null;

        if (!button) {
            return;
        }

        openSubscriptionModal(button.getAttribute('data-subscription-edit'));
    });
})();
});
</script>
