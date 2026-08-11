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

    <section class="panel listing-panel" data-live-region="accounts-panel">
        <div class="panel-header">
            <h2 class="panel-title">Danh sách tài khoản</h2>
        </div>
        <div class="panel-body table-wrap listing-body">
            <table class="data-table responsive-data-table">
	                <thead>
	                    <tr>
	                        <th>Tài khoản</th>
	                        <th>Số điện thoại</th>
	                        <th>Trạng thái</th>
	                        <th>Nhóm</th>
	                        <th>Lịch gửi</th>
	                        <th>Chế độ gửi tin</th>
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
                            'draft' => 'Chưa kết nối',
                            default => ucfirst(str_replace('_', ' ', $status)),
                        };
                        $safety = (array) ($account['safety'] ?? []);
                        $safetyMode = (string) ($safety['mode'] ?? 'safe');
                        $safetyBadge = match ($safetyMode) {
                            'risk_accepted' => 'danger',
                            'elevated' => 'warning',
                            default => 'success',
                        };
                        ?>
	                    <tr>
	                        <td data-label="Tài khoản">
	                            <strong><?= e($account['name']) ?></strong>
	                            <div class="small muted"><?= e($account['tg_username'] ?: $account['session_name']) ?></div>
	                        </td>
	                        <td class="mono" data-label="Số điện thoại"><?= e($account['phone_number']) ?></td>
	                        <td data-label="Trạng thái">
	                            <span class="badge <?= e($badgeClass) ?>">
	                                <?= e($statusLabel) ?>
	                            </span>
	                        </td>
	                        <td data-label="Nhóm"><?= e((string) $account['groups_count']) ?></td>
	                        <td data-label="Lịch gửi"><?= e((string) $account['schedules_count']) ?></td>
	                        <td data-label="Chế độ gửi tin" style="min-width:260px;">
                                <div class="status-block">
                                    <div>
                                        <span class="badge <?= e($safetyBadge) ?>"><?= e((string) ($safety['mode_label'] ?? 'An toàn')) ?></span>
                                        <?php if (!empty($safety['circuit_breaker_active'])): ?>
                                            <span class="badge danger">Circuit breaker</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small muted">
                                        1 giờ: <?= e((string) ($safety['hourly_count'] ?? 0)) ?><?= $safety['hourly_limit'] !== null ? ' / ' . e((string) $safety['hourly_limit']) : ' / Không giới hạn' ?>
                                        · 24 giờ: <?= e((string) ($safety['daily_count'] ?? 0)) ?><?= $safety['daily_limit'] !== null ? ' / ' . e((string) $safety['daily_limit']) : ' / Không giới hạn' ?>
                                    </div>
                                    <div class="small muted">Khoảng cách tối thiểu: <?= e((string) ($safety['min_gap_minutes'] ?? 8)) ?> phút</div>
                                    <?php if (!empty($safety['circuit_breaker_active'])): ?>
                                        <div class="small" style="color:#ef4444;">
                                            <?= e((string) ($safety['circuit_breaker_reason'] ?? 'Telegram đang giới hạn account.')) ?>
                                            Thử lại: <?= e(fmt_datetime((string) ($safety['circuit_breaker_until'] ?? ''))) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($canOverrideSafety)): ?>
                                        <button
                                            class="button secondary sm"
                                            type="button"
                                            data-safety-mode-edit
                                            data-account-id="<?= e((string) $account['id']) ?>"
                                            data-account-name="<?= e((string) $account['name']) ?>"
                                            data-current-mode="<?= e($safetyMode) ?>"
                                            data-hourly-count="<?= e((string) ($safety['hourly_count'] ?? 0)) ?>"
                                            data-daily-count="<?= e((string) ($safety['daily_count'] ?? 0)) ?>"
                                            data-mode-policies="<?= e((string) json_encode($safety['mode_policies'] ?? [], JSON_UNESCAPED_UNICODE)) ?>"
                                        >
                                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                                            Điều chỉnh chế độ
                                        </button>
                                    <?php else: ?>
                                        <div class="small muted">Liên hệ super admin để được cấp quyền thay đổi chế độ.</div>
                                    <?php endif; ?>
                                </div>
	                        </td>
	                        <td data-label="Hành động">
	                            <div class="status-block">
                                    <?php if ($status === 'active'): ?>
                                        <form method="post" action="<?= e(url('/accounts/toggle-active')) ?>" data-ajax-form data-ajax-refresh="accounts-panel">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                            <button class="button <?= $isActive ? 'danger' : 'accent' ?>" type="submit">
                                                <?= $isActive ? 'Tạm dừng tài khoản' : 'Bật lại tài khoản' ?>
                                            </button>
                                        </form>
                                        <div class="status-card success">
                                            <div class="status-title"><?= $isActive ? 'Đăng nhập thành công và sẵn sàng hoạt động' : 'Tài khoản đang được tạm dừng' ?></div>
                                            <div class="small muted">Thời gian: <?= e(fmt_datetime($account['last_connected_at'])) ?></div>
                                            <?php if (!$isActive): ?>
                                                <div class="small muted">Tài khoản đang tạm dừng, các hoạt động sẽ bị bỏ qua.</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($status === 'code_sent'): ?>
                                        <div class="status-card info">
                                            <div class="status-title">Nhập mã OTP</div>
                                            <form class="status-form" method="post" action="<?= e(url('/accounts/verify-code')) ?>" data-ajax-form data-ajax-refresh="accounts-panel">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                                <div class="form-feedback" data-form-feedback hidden></div>
                                                <input class="input" type="text" name="code" placeholder="Nhập mã OTP" required>
                                                <button class="button accent" type="submit">Xác thực mã</button>
                                            </form>
                                            <form method="post" action="<?= e(url('/accounts/send-code')) ?>" data-ajax-form data-ajax-refresh="accounts-panel">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                                <button class="button secondary" type="submit">Gửi lại OTP</button>
                                            </form>
                                        </div>
                                    <?php elseif ($status === 'password_required'): ?>
                                        <div class="status-card warning">
                                            <div class="status-title">Cần mật khẩu 2FA</div>
                                            <form class="status-form" method="post" action="<?= e(url('/accounts/verify-password')) ?>" data-ajax-form data-ajax-refresh="accounts-panel">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                                <div class="form-feedback" data-form-feedback hidden></div>
                                                <input class="input" type="password" name="password" placeholder="Nhập mật khẩu 2FA" required>
                                                <button class="button secondary" type="submit">Xác thực 2FA</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="status-card">
                                            <div class="status-title">Bắt đầu kết nối</div>
                                            <form method="post" action="<?= e(url('/accounts/send-code')) ?>" data-ajax-form data-ajax-refresh="accounts-panel">
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
                    <tr class="responsive-table-empty"><td colspan="7" class="muted">Chưa có tài khoản nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-body" style="padding-top: 0;">
            <?php $perPageOptions = [10, 15, 20, 30, 50, 100]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>

    <?php if (($safetyEvents ?? []) !== []): ?>
        <section class="card">
            <h2 class="section-title">Lịch sử thay đổi an toàn gần đây</h2>
            <div class="list">
                <?php foreach ($safetyEvents as $event): ?>
                    <div class="list-item">
                        <div class="builder-block-head">
                            <strong><?= e((string) $event['account_name']) ?> · <?= e(safety_event_label((string) $event['event_type'])) ?></strong>
                            <span class="small muted"><?= e(fmt_datetime((string) $event['created_at'])) ?></span>
                        </div>
                        <div><?= e(safety_mode_label($event['previous_mode'] ?? null)) ?> → <?= e(safety_mode_label($event['new_mode'] ?? null)) ?></div>
                        <div class="small muted">Thực hiện: <?= e((string) ($event['actor_name'] ?? 'Hệ thống')) ?></div>
                        <?php if (!empty($event['reason'])): ?><div class="small"><?= e((string) $event['reason']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
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
            <label for="account_modal_phone">Số điện thoại đăng nhập Telegram</label>
            <div class="phone-input-group">
                <select class="select" name="country_code" aria-label="Mã quốc gia" required>
                    <option value="+84" selected>VN +84</option>
                    <option value="+1">US/CA +1</option>
                    <option value="+86">CN +86</option>
                    <option value="+852">HK +852</option>
                    <option value="+853">MO +853</option>
                    <option value="+886">TW +886</option>
                    <option value="+65">SG +65</option>
                    <option value="+66">TH +66</option>
                    <option value="+60">MY +60</option>
                    <option value="+62">ID +62</option>
                    <option value="+63">PH +63</option>
                    <option value="+856">LA +856</option>
                    <option value="+855">KH +855</option>
                    <option value="+95">MM +95</option>
                    <option value="+81">JP +81</option>
                    <option value="+82">KR +82</option>
                    <option value="+91">IN +91</option>
                    <option value="+61">AU +61</option>
                    <option value="+64">NZ +64</option>
                    <option value="+44">UK +44</option>
                    <option value="+33">FR +33</option>
                    <option value="+49">DE +49</option>
                    <option value="+7">RU +7</option>
                    <option value="+971">AE +971</option>
                </select>
                <input
                    class="input"
                    id="account_modal_phone"
                    type="tel"
                    name="phone_number"
                    inputmode="numeric"
                    autocomplete="tel-national"
                    placeholder="987654321"
                    aria-describedby="account_modal_phone_hint"
                    required
                >
            </div>
            <!-- <div class="small muted" id="account_modal_phone_hint">Có thể nhập 0987654321 hoặc 987654321. Hệ thống sẽ tự bỏ số 0 ở đầu.</div> -->
        </div>
        <div class="actions">
            <button class="button primary" type="submit" data-loading-text="Đang tạo...">Tạo tài khoản</button>
            <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
        </div>
    </form>
</template>

<template id="account_safety_mode_template">
    <form class="form-grid" method="post" action="<?= e(url('/accounts/safety-mode')) ?>" data-safety-mode-form>
        <?= csrf_field() ?>
        <input type="hidden" name="account_id" value="">
        <input type="hidden" name="queue_action" value="recalculate_from_now">
        <div class="form-feedback" data-form-feedback hidden></div>

        <div class="list-item">
            <div class="builder-block-head">
                <div>
                    <strong data-safety-account-name>-</strong>
                    <div class="small muted">Chính sách được áp dụng riêng cho Telegram account này.</div>
                </div>
                <span class="badge info" data-safety-current-mode>An toàn</span>
            </div>
            <div class="grid grid-3" style="margin-top:12px;">
                <div class="hint-box"><div class="small muted">Đã dùng trong 1 giờ</div><strong data-safety-hourly>-</strong></div>
                <div class="hint-box"><div class="small muted">Đã dùng trong 24 giờ</div><strong data-safety-daily>-</strong></div>
                <div class="hint-box"><div class="small muted">Khoảng cách gửi tối thiểu giữa các tin nhắn</div><strong><span data-safety-gap>-</span> phút</strong></div>
            </div>
        </div>

        <div class="field">
            <label for="account_safety_mode">Chế độ gửi</label>
            <select class="select" id="account_safety_mode" name="safety_mode" required>
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
            <label for="account_safety_reason">Lý do thay đổi</label>
            <textarea class="textarea" id="account_safety_reason" name="reason" rows="3" placeholder="Không bắt buộc, nhưng nên ghi để dễ tra cứu."></textarea>
        </div>
        <div class="list-item" data-safety-risk-warning hidden>
            <strong style="color:#ef4444;">Cảnh báo khi chấp nhận rủi ro</strong>
            <div class="small muted" style="margin-top:6px;">Hệ thống sẽ không chặn theo giới hạn lượt gửi nội bộ. Telegram vẫn có thể giới hạn hoặc khóa tài khoản, nên cân nhắc kỹ khi sử dụng.</div>
            <label class="checkbox-row" style="margin-top:10px;">
                <input type="checkbox" name="acknowledged" value="1" data-safety-acknowledgement>
                <span>Tôi đã hiểu và chấp nhận rủi ro này.</span>
            </label>
        </div>
        <div class="actions">
            <button class="button primary" type="submit" data-loading-text="Đang áp dụng...">Áp dụng chế độ</button>
            <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
        </div>
    </form>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const createButton = document.getElementById('open_account_create');
    const template = document.getElementById('account_form_template');
    const safetyTemplate = document.getElementById('account_safety_mode_template');

    if (!window.TeleSenderCrudModal || !window.TeleSenderApp) {
        return;
    }

    createButton?.addEventListener('click', () => {
        if (!template) {
            return;
        }
        const fragment = template.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const form = wrapper.querySelector('form');

        if (!form) {
            return;
        }

        const countryCodeInput = form.querySelector('[name="country_code"]');
        const phoneInput = form.querySelector('[name="phone_number"]');
        const phoneError = 'Số điện thoại chỉ được chứa chữ số, ví dụ: 0987654321 hoặc 987654321.';

        const validatePhone = (normalizeInput = false) => {
            if (!countryCodeInput || !phoneInput) {
                return true;
            }

            const normalizedPhone = phoneInput.value.replace(/\s+/g, '').replace(/^0/, '');

            if (normalizeInput && normalizedPhone !== '') {
                phoneInput.value = normalizedPhone;
            }

            const fullPhone = `${countryCodeInput.value}${normalizedPhone}`;
            const isValid = normalizedPhone === '' || (
                /^[1-9]\d+$/.test(normalizedPhone)
                && /^\+[1-9]\d{7,14}$/.test(fullPhone)
            );
            phoneInput.setCustomValidity(isValid ? '' : phoneError);

            return isValid;
        };

        countryCodeInput?.addEventListener('change', () => validatePhone());
        phoneInput?.addEventListener('input', () => validatePhone());
        phoneInput?.addEventListener('blur', () => validatePhone(true));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!validatePhone(true) || !form.reportValidity()) {
                return;
            }

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="accounts-panel"]'],
            });
        });

        window.TeleSenderCrudModal.open({
            title: 'Tạo tài khoản Telegram',
            description: 'Vui lòng điền chính xác thông tin tài khoản của bạn.',
            size: 'md',
            content: wrapper,
        });
    });

    function safetyModeLabel(mode) {
        if (mode === 'risk_accepted') return 'Chấp nhận rủi ro';
        if (mode === 'elevated') return 'Mở rộng giới hạn';
        return 'An toàn';
    }

    function openSafetyModeModal(button) {
        if (!safetyTemplate) {
            return;
        }

        const fragment = safetyTemplate.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);
        const form = wrapper.querySelector('[data-safety-mode-form]');
        const modeInput = form?.querySelector('[name="safety_mode"]');
        const riskWarning = form?.querySelector('[data-safety-risk-warning]');
        const acknowledgement = form?.querySelector('[data-safety-acknowledgement]');

        if (!form || !(modeInput instanceof HTMLSelectElement) || !riskWarning || !(acknowledgement instanceof HTMLInputElement)) {
            return;
        }

        form.querySelector('[name="account_id"]').value = button.getAttribute('data-account-id') || '';
        form.querySelector('[data-safety-account-name]').textContent = button.getAttribute('data-account-name') || 'Telegram account';
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
        const modeBadge = form.querySelector('[data-safety-current-mode]');

        const updatePolicyPreview = () => {
            const selectedMode = modeInput.value || 'safe';
            const selectedPolicy = modePolicies[selectedMode] || {};
            const hourlyLimit = selectedPolicy.hourly_limit === null ? 'Không giới hạn' : String(selectedPolicy.hourly_limit ?? '-');
            const dailyLimit = selectedPolicy.daily_limit === null ? 'Không giới hạn' : String(selectedPolicy.daily_limit ?? '-');
            form.querySelector('[data-safety-hourly]').textContent = `${hourlyCount} / ${hourlyLimit}`;
            form.querySelector('[data-safety-daily]').textContent = `${dailyCount} / ${dailyLimit}`;
            form.querySelector('[data-safety-gap]').textContent = String(selectedPolicy.min_gap_minutes ?? '-');
            modeBadge.textContent = safetyModeLabel(selectedMode);
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

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="accounts-panel"]'],
            });
        });

        window.TeleSenderCrudModal.open({
            title: 'Điều chỉnh chế độ gửi',
            description: 'Kiểm tra kỹ giới hạn và cách xử lý hàng đợi trước khi áp dụng.',
            size: 'lg',
            content: wrapper,
        });
    }

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-safety-mode-edit]') : null;
        if (button instanceof HTMLButtonElement) {
            openSafetyModeModal(button);
        }
    });
})();
});
</script>
