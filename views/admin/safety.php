<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Giám sát an toàn</h1>
            <p class="page-subtitle">Theo dõi mức sử dụng, circuit breaker và chế độ gửi của mọi Telegram account.</p>
        </div>
    </div>

    <section class="panel listing-panel">
        <div class="panel-header">
            <form class="toolbar-form" method="get" action="<?= e(url('/admin/safety')) ?>">
                <div class="toolbar-search">
                    <input class="input" type="text" name="q" value="<?= e($searchQuery ?? '') ?>" placeholder="Tìm account, admin hoặc email...">
                </div>
                <button class="button secondary" type="submit">Lọc</button>
            </form>
        </div>
        <div class="panel-body table-wrap listing-body">
            <table class="data-table responsive-data-table">
                <thead>
                <tr>
                    <th>Account</th>
                    <th>Chế độ</th>
                    <th>Mức sử dụng</th>
                    <th>Circuit breaker</th>
                    <th>Điều chỉnh</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <?php
                    $safety = (array) ($account['safety'] ?? []);
                    $mode = (string) ($safety['mode'] ?? 'safe');
                    $badge = match ($mode) {
                        'risk_accepted' => 'danger',
                        'elevated' => 'warning',
                        default => 'success',
                    };
                    ?>
                    <tr>
                        <td data-label="Account">
                            <strong><?= e((string) $account['name']) ?></strong>
                            <div class="small muted"><?= e((string) $account['owner_name']) ?> · <?= e((string) $account['owner_email']) ?></div>
                            <div class="small muted"><?= e((string) ($account['schedules_count'] ?? 0)) ?> lịch active</div>
                        </td>
                        <td data-label="Chế độ"><span class="badge <?= e($badge) ?>"><?= e((string) ($safety['mode_label'] ?? 'An toàn')) ?></span></td>
                        <td data-label="Mức sử dụng">
                            <div>1 giờ: <?= e((string) ($safety['hourly_count'] ?? 0)) ?> / <?= $safety['hourly_limit'] === null ? 'Không giới hạn' : e((string) $safety['hourly_limit']) ?></div>
                            <div>24 giờ: <?= e((string) ($safety['daily_count'] ?? 0)) ?> / <?= $safety['daily_limit'] === null ? 'Không giới hạn' : e((string) $safety['daily_limit']) ?></div>
                            <div class="small muted">Gap <?= e((string) ($safety['min_gap_minutes'] ?? 8)) ?> phút</div>
                        </td>
                        <td data-label="Circuit breaker">
                            <?php if (!empty($safety['circuit_breaker_active'])): ?>
                                <span class="badge danger">Đang mở</span>
                                <div class="small muted"><?= e(fmt_datetime((string) ($safety['circuit_breaker_until'] ?? ''))) ?></div>
                                <div class="small"><?= e((string) ($safety['circuit_breaker_reason'] ?? '')) ?></div>
                            <?php else: ?>
                                <span class="badge success">Đóng</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Điều chỉnh">
                            <button
                                class="button secondary sm"
                                type="button"
                                data-admin-safety-mode-edit
                                data-account-id="<?= e((string) $account['id']) ?>"
                                data-account-name="<?= e((string) $account['name']) ?>"
                                data-owner-name="<?= e((string) $account['owner_name']) ?>"
                                data-current-mode="<?= e($mode) ?>"
                                data-hourly-count="<?= e((string) ($safety['hourly_count'] ?? 0)) ?>"
                                data-daily-count="<?= e((string) ($safety['daily_count'] ?? 0)) ?>"
                                data-mode-policies="<?= e((string) json_encode($safety['mode_policies'] ?? [], JSON_UNESCAPED_UNICODE)) ?>"
                            >
                                <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                                Điều chỉnh chế độ
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($accounts === []): ?>
                    <tr class="responsive-table-empty"><td colspan="5" class="muted">Không có Telegram account phù hợp.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-body" style="padding-top:0;">
            <?php $perPageOptions = [10, 15, 20, 30, 50, 100]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>

    <section class="card">
        <h2 class="section-title">Audit gần đây</h2>
        <div class="list">
            <?php foreach ($events as $event): ?>
                <div class="list-item">
                    <div class="builder-block-head">
                        <strong><?= e((string) $event['account_name']) ?> · <?= e(safety_event_label((string) $event['event_type'])) ?></strong>
                        <span class="small muted"><?= e(fmt_datetime((string) $event['created_at'])) ?></span>
                    </div>
                    <div class="small muted">
                        Chủ sở hữu: <?= e((string) $event['owner_name']) ?> · Thực hiện: <?= e((string) ($event['actor_name'] ?? 'Hệ thống')) ?>
                    </div>
                    <div><?= e(safety_mode_label($event['previous_mode'] ?? null)) ?> → <?= e(safety_mode_label($event['new_mode'] ?? null)) ?></div>
                    <?php if (!empty($event['reason'])): ?><div class="small"><?= e((string) $event['reason']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($events === []): ?><div class="muted">Chưa có audit event.</div><?php endif; ?>
        </div>
    </section>

    <section class="card">
        <h2 class="section-title">Audit cấp quyền gần đây</h2>
        <div class="list">
            <?php foreach (($permissionEvents ?? []) as $event): ?>
                <div class="list-item">
                    <div class="builder-block-head">
                        <strong><?= e((string) $event['target_name']) ?> · <?= (int) $event['new_allowed'] === 1 ? 'Đã cấp quyền' : 'Đã thu hồi quyền' ?></strong>
                        <span class="small muted"><?= e(fmt_datetime((string) $event['created_at'])) ?></span>
                    </div>
                    <div class="small muted"><?= e((string) $event['target_email']) ?> · Thực hiện: <?= e((string) ($event['actor_name'] ?? 'Hệ thống')) ?></div>
                    <?php if (!empty($event['reason'])): ?><div class="small"><?= e((string) $event['reason']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (($permissionEvents ?? []) === []): ?><div class="muted">Chưa có thay đổi quyền.</div><?php endif; ?>
        </div>
    </section>
</section>

<template id="admin_safety_mode_template">
    <form class="form-grid" method="post" action="<?= e(url('/admin/safety/mode')) ?>" data-ajax-form data-ajax-close-crud-modal="1">
        <?= csrf_field() ?>
        <input type="hidden" name="account_id" value="">
        <input type="hidden" name="queue_action" value="recalculate_from_now">
        <div class="form-feedback" data-form-feedback hidden></div>

        <div class="list-item">
            <div class="builder-block-head">
                <div>
                    <strong data-admin-safety-account-name>-</strong>
                    <div class="small muted">Chủ sở hữu: <span data-admin-safety-owner>-</span></div>
                </div>
                <span class="badge info" data-admin-safety-current-mode>An toàn</span>
            </div>
            <div class="grid grid-3" style="margin-top:12px;">
                <div class="hint-box"><div class="small muted">Đã dùng trong 1 giờ</div><strong data-admin-safety-hourly>-</strong></div>
                <div class="hint-box"><div class="small muted">Đã dùng trong 24 giờ</div><strong data-admin-safety-daily>-</strong></div>
                <div class="hint-box"><div class="small muted">Khoảng cách gửi giữa các tin nhắn</div><strong><span data-admin-safety-gap>-</span> phút</strong></div>
            </div>
        </div>

        <div class="field">
            <label for="admin_safety_mode">Chế độ gửi</label>
            <select class="select" id="admin_safety_mode" name="safety_mode" required>
                <option value="safe">An toàn</option>
                <option value="elevated">Mở rộng giới hạn</option>
                <option value="risk_accepted">Chấp nhận rủi ro</option>
            </select>
        </div>
        <div class="list-item">
            <strong>Lịch cũ bị dời sẽ được bỏ qua</strong>
            <div class="small muted" style="margin-top:6px;">Không gửi bù lịch cũ. Hệ thống tính lần chạy hợp lệ tiếp theo từ hiện tại và các lịch tương lai tiếp tục chạy bình thường.</div>
        </div>
        <div class="field">
            <label for="admin_safety_reason">Lý do thay đổi (Tuỳ chọn)</label>
            <textarea class="textarea" id="admin_safety_reason" name="reason" rows="3" placeholder="Có thể ghi lý do để đối chiếu."></textarea>
        </div>
        <div class="list-item" data-admin-safety-risk-warning hidden>
            <strong style="color:#ef4444;">Tài khoản sẽ hoạt hoạt động vượt mức giới hạn an toàn</strong>
            <div class="small muted" style="margin-top:6px;">Chế độ không tự hết hạn. Telegram cooldown, breaker và khóa dispatch vẫn là guard bắt buộc.</div>
            <label class="checkbox-row" style="margin-top:10px;">
                <input type="checkbox" data-admin-safety-acknowledgement>
                <span>Tôi xác nhận áp dụng chế độ chấp nhận rủi ro cho tài khoản này.</span>
            </label>
        </div>
        <div class="actions">
            <button class="button primary" type="submit" data-loading-text="Đang cập nhật...">Áp dụng chế độ</button>
            <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
        </div>
    </form>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const template = document.getElementById('admin_safety_mode_template');
    if (!template || !window.TeleSenderCrudModal) return;

    function modeLabel(mode) {
        if (mode === 'risk_accepted') return 'Chấp nhận rủi ro';
        if (mode === 'elevated') return 'Mở rộng giới hạn';
        return 'An toàn';
    }

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-admin-safety-mode-edit]') : null;
        if (!(button instanceof HTMLButtonElement)) return;

        const fragment = template.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);
        const form = wrapper.querySelector('form');
        const modeInput = form?.querySelector('[name="safety_mode"]');
        const riskWarning = form?.querySelector('[data-admin-safety-risk-warning]');
        const acknowledgement = form?.querySelector('[data-admin-safety-acknowledgement]');
        if (!form || !(modeInput instanceof HTMLSelectElement) || !riskWarning || !(acknowledgement instanceof HTMLInputElement)) return;

        form.querySelector('[name="account_id"]').value = button.getAttribute('data-account-id') || '';
        form.querySelector('[data-admin-safety-account-name]').textContent = button.getAttribute('data-account-name') || 'Telegram account';
        form.querySelector('[data-admin-safety-owner]').textContent = button.getAttribute('data-owner-name') || '-';
        const hourlyCount = button.getAttribute('data-hourly-count') || '0';
        const dailyCount = button.getAttribute('data-daily-count') || '0';
        let modePolicies = {};
        try {
            modePolicies = JSON.parse(button.getAttribute('data-mode-policies') || '{}');
        } catch (error) {
            modePolicies = {};
        }
        const currentMode = button.getAttribute('data-current-mode') || 'safe';
        modeInput.value = currentMode;
        const modeBadge = form.querySelector('[data-admin-safety-current-mode]');

        const updatePolicyPreview = () => {
            const selectedMode = modeInput.value || 'safe';
            const selectedPolicy = modePolicies[selectedMode] || {};
            const hourlyLimit = selectedPolicy.hourly_limit === null ? 'Không giới hạn' : String(selectedPolicy.hourly_limit ?? '-');
            const dailyLimit = selectedPolicy.daily_limit === null ? 'Không giới hạn' : String(selectedPolicy.daily_limit ?? '-');
            form.querySelector('[data-admin-safety-hourly]').textContent = `${hourlyCount} / ${hourlyLimit}`;
            form.querySelector('[data-admin-safety-daily]').textContent = `${dailyCount} / ${dailyLimit}`;
            form.querySelector('[data-admin-safety-gap]').textContent = String(selectedPolicy.min_gap_minutes ?? '-');
            modeBadge.textContent = modeLabel(selectedMode);
            modeBadge.className = `badge ${selectedMode === 'risk_accepted' ? 'danger' : (selectedMode === 'elevated' ? 'warning' : 'success')}`;
        };

        const updateConditionalFields = () => {
            const isRisk = modeInput.value === 'risk_accepted';
            riskWarning.hidden = !isRisk;
            riskWarning.style.display = isRisk ? '' : 'none';
            acknowledgement.required = isRisk;
            if (!isRisk) acknowledgement.checked = false;
            updatePolicyPreview();
        };
        modeInput.addEventListener('change', updateConditionalFields);
        updateConditionalFields();

        window.TeleSenderCrudModal.open({
            title: 'Điều chỉnh chế độ account',
            description: 'Thay đổi này có hiệu lực từ lần scheduler đánh giá tiếp theo.',
            size: 'lg',
            content: wrapper,
        });
    });
})();
});
</script>
