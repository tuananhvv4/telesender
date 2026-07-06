<?php

declare(strict_types=1);

$scheduleTypeLabels = [
    'interval_minutes' => 'Mỗi X phút',
    'interval_hours' => 'Mỗi X giờ',
    'daily_times' => 'Mỗi ngày theo giờ',
    'weekly_times' => 'Theo ngày trong tuần',
    'advanced' => 'Nâng cao',
];
$scheduleRecords = [];

foreach ($schedules as $schedule) {
    $scheduleRecords[(int) $schedule['id']] = [
        'id' => (int) $schedule['id'],
        'telegram_account_id' => (int) $schedule['telegram_account_id'],
        'telegram_group_id' => (int) $schedule['telegram_group_id'],
        'message_template_id' => (int) $schedule['message_template_id'],
        'status' => (string) $schedule['status'],
        'form_state' => $scheduleFormStates[(int) $schedule['id']] ?? $defaultFormScheduleState,
    ];
}
?>
<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Lịch gửi</h1>
        <div class="inline-actions">
            <button class="button primary" type="button" id="open_schedule_create">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Tạo lịch gửi
            </button>
        </div>
    </div>

    <div data-live-region="schedules-shell">
    <script type="application/json" data-schedule-records><?= json_encode($scheduleRecords, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Danh sách lịch gửi</h2>
                <p class="panel-copy">Lọc theo tài khoản, mẫu tin, trạng thái hoặc từ khóa để quản lý danh sách lịch gửi lớn dễ hơn.</p>
            </div>

            <form class="toolbar-form" method="get" action="<?= e(url('/schedules')) ?>">
                <?php if ((int) request()->query('per_page', 0) > 0): ?>
                    <input type="hidden" name="per_page" value="<?= e((string) request()->query('per_page')) ?>">
                <?php endif; ?>
                <div class="toolbar-search schedule-toolbar-search">
                    <select class="select" name="telegram_account_id">
                        <option value="">Tất cả tài khoản</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= e((string) $account['id']) ?>" <?= (int) ($selectedAccountId ?? 0) === (int) $account['id'] ? 'selected' : '' ?>>
                                <?= e($account['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="select" name="message_template_id">
                        <option value="">Tất cả mẫu tin</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= e((string) $template['id']) ?>" <?= (int) ($selectedTemplateId ?? 0) === (int) $template['id'] ? 'selected' : '' ?>>
                                <?= e($template['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="select" name="status">
                        <option value="">Mọi trạng thái</option>
                        <option value="active" <?= ($selectedStatus ?? '') === 'active' ? 'selected' : '' ?>>Đang chạy</option>
                        <option value="paused" <?= ($selectedStatus ?? '') === 'paused' ? 'selected' : '' ?>>Tạm dừng</option>
                    </select>

                    <input class="input" type="text" name="q" value="<?= e($searchQuery ?? '') ?>" placeholder="Tìm theo mẫu tin, tài khoản, nhóm, topic, cron, timezone...">
                    <button class="button secondary" type="submit">Lọc</button>
                    <?php if (($searchQuery ?? '') !== '' || (int) ($selectedAccountId ?? 0) > 0 || (int) ($selectedTemplateId ?? 0) > 0 || ($selectedStatus ?? '') !== ''): ?>
                        <a class="button secondary" href="<?= e(url('/schedules')) ?>">Xóa lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="panel-body table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Mẫu tin</th>
                        <th>Tài khoản / Nhóm</th>
                        <th>Lịch chạy</th>
                        <th>Lần chạy tới</th>
                        <th>Trạng thái</th>
                        <th>An toàn</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($schedules as $schedule): ?>
                    <?php
                    $analysis = $scheduleAnalyses[(int) $schedule['id']] ?? ['risk' => 'safe', 'message' => '', 'runs_per_day' => 0, 'min_gap_minutes' => null];
                    $manualGuard = $scheduleManualGuards[(int) $schedule['id']] ?? null;
                    $summary = $scheduleSummaries[(int) $schedule['id']] ?? ('Cron tùy chỉnh: ' . (string) $schedule['cron_expression']);
                    $queueNotice = is_string($schedule['last_error'] ?? null) && str_starts_with((string) $schedule['last_error'], 'Queue:');
                    $riskBadgeClass = match ($analysis['risk']) {
                        'safe' => 'success',
                        'medium' => 'info',
                        'high' => 'warning',
                        default => 'danger',
                    };
                    $riskLabel = match ($analysis['risk']) {
                        'safe' => 'An toàn',
                        'medium' => 'Cần lưu ý',
                        'high' => 'Khá dày',
                        default => 'Quá dày',
                    };
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($schedule['template_name']) ?></strong>
                            <div style="margin-top:8px;">
                                <span class="badge info"><?= e($scheduleTypeLabels[(string) ($schedule['schedule_type'] ?? 'advanced')] ?? 'Nâng cao') ?></span>
                            </div>
                            <?php if (!empty($schedule['last_error'])): ?>
                                <div class="small" style="color:<?= $queueNotice ? '#0f766e' : '#b91c1c' ?>;"><?= e($schedule['last_error']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?= e($schedule['account_name']) ?></div>
                            <div class="small muted">
                                <?= e($schedule['group_title']) ?>
                                <?php if (!empty($schedule['topic_title'])): ?>
                                    · Topic: <?= e($schedule['topic_title']) ?>
                                <?php elseif (!empty($schedule['topic_id'])): ?>
                                    · Topic ID: <?= e((string) $schedule['topic_id']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div><?= e($summary) ?></div>
                            <div class="small muted">Múi giờ: <?= e($schedule['timezone']) ?></div>
                            <div class="small muted"><?= e((string) $analysis['runs_per_day']) ?> lần/ngày · <?= e($analysis['min_gap_minutes'] !== null ? (string) $analysis['min_gap_minutes'] . ' phút/lần' : 'không xác định') ?></div>
                        </td>
                        <td>
                            <div><?= e(fmt_datetime($schedule['next_run_at'])) ?></div>
                            <div class="small muted">Lần chạy gần nhất: <?= e(fmt_datetime($schedule['last_run_at'])) ?></div>
                        </td>
                        <td>
                            <span class="badge <?= $schedule['status'] === 'active' ? 'success' : 'warning' ?>"><?= e($schedule['status'] === 'active' ? 'Đang chạy' : 'Tạm dừng') ?></span>
                            <?php if ($queueNotice): ?>
                                <div style="margin-top:8px;">
                                    <span class="badge info">Đang xếp hàng</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e($riskBadgeClass) ?>"><?= e($riskLabel) ?></span>
                            <div class="small muted" style="margin-top: 6px;"><?= e($analysis['message']) ?></div>
                        </td>
                        <td>
                            <div class="inline-actions">
                                <button class="button secondary" type="button" data-schedule-edit="<?= e((string) $schedule['id']) ?>">Sửa</button>
                                <form
                                    method="post"
                                    action="<?= e(url('/schedules/send-now')) ?>"
                                    data-ajax-form
                                    data-ajax-refresh="schedules-shell"
                                    data-ajax-risk-confirm="1"
                                    data-ajax-confirm-title="Xác nhận gửi ngay"
                                    data-ajax-confirm-text="Vẫn gửi ngay"
                                    data-send-now-form
                                    <?= $manualGuard !== null ? 'data-risk-message="' . e((string) ($manualGuard['reason'] ?? 'Tài khoản đang trong vùng rủi ro an toàn.')) . '"' : '' ?>
                                >
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <input type="hidden" name="force_send" value="0" data-force-send-input>
                                    <button class="button secondary" type="submit">Gửi ngay</button>
                                </form>
                                <form method="post" action="<?= e(url('/schedules/toggle')) ?>" data-ajax-form data-ajax-refresh="schedules-shell">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <button class="button accent" type="submit"><?= $schedule['status'] === 'active' ? 'Tạm dừng' : 'Tiếp tục' ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/schedules/delete')) ?>" data-ajax-form data-ajax-refresh="schedules-shell">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <button class="button danger" type="submit">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($schedules === []): ?>
                    <tr>
                        <td colspan="7" class="muted">
                            <?= (($searchQuery ?? '') !== '' || (int) ($selectedAccountId ?? 0) > 0 || (int) ($selectedTemplateId ?? 0) > 0 || ($selectedStatus ?? '') !== '')
                                ? 'Không có lịch gửi nào khớp với bộ lọc hiện tại.'
                                : 'Chưa có lịch gửi nào.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-body" style="padding-top: 0;">
            <?php $perPageOptions = [10, 15, 20, 30, 50]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>

    <?php if (!empty($accountScheduleAnalyses)): ?>
        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Đánh giá tổng theo tài khoản</h2>
            </div>
            <div class="panel-body">
                <div class="grid grid-auto">
                    <?php foreach ($accountScheduleAnalyses as $accountAnalysis): ?>
                        <?php
                        $accountRiskBadgeClass = match ($accountAnalysis['risk'] ?? 'safe') {
                            'safe' => 'success',
                            'medium' => 'warning',
                            default => 'danger',
                        };
                        $accountRiskLabel = match ($accountAnalysis['risk'] ?? 'safe') {
                            'safe' => 'Ổn định',
                            'medium' => 'Có thể phải xếp hàng',
                            default => 'Nguy cơ dời lịch',
                        };
                        ?>
                        <article class="card stat-card account-health-card">
                            <div class="inline-actions" style="justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <strong><?= e((string) ($accountAnalysis['account_name'] ?? '')) ?></strong>
                                    <div class="small muted">
                                        <?= e((string) ($accountAnalysis['active_schedule_count'] ?? 0)) ?> lịch đang chạy
                                        <?php if ((int) ($accountAnalysis['paused_schedule_count'] ?? 0) > 0): ?>
                                            · <?= e((string) $accountAnalysis['paused_schedule_count']) ?> lịch tạm dừng
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge <?= e($accountRiskBadgeClass) ?>"><?= e($accountRiskLabel) ?></span>
                            </div>

                            <div class="grid grid-2 account-health-grid">
                                <div class="hint-box">
                                    <div class="small muted">24h tới</div>
                                    <strong><?= e((string) ($accountAnalysis['runs_per_day'] ?? 0)) ?> lần chạy</strong>
                                </div>
                                <div class="hint-box">
                                    <div class="small muted">Khoảng cách ngắn nhất</div>
                                    <strong><?= e(($accountAnalysis['min_gap_minutes'] ?? null) !== null ? (string) $accountAnalysis['min_gap_minutes'] . ' phút' : '-') ?></strong>
                                </div>
                                <div class="hint-box">
                                    <div class="small muted">Đỉnh tải 1 giờ</div>
                                    <strong><?= e((string) ($accountAnalysis['max_runs_per_hour'] ?? 0)) ?> lần/giờ</strong>
                                </div>
                                <div class="hint-box">
                                    <div class="small muted">Cặp mốc quá sát</div>
                                    <strong><?= e((string) ($accountAnalysis['conflict_pairs'] ?? 0)) ?></strong>
                                </div>
                            </div>

                            <?php if (!empty($accountAnalysis['message'])): ?>
                                <div class="small muted"><?= e((string) $accountAnalysis['message']) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
</section>

<template id="schedule_editor_template">
    <div class="schedule-builder-layout">
        <section class="card schedule-builder-main">
            <div class="field" style="margin: 16px 0 18px;">
                <label for="schedule_modal_preset">Lịch cài sẵn</label>
                <select class="select" id="schedule_modal_preset" data-schedule-preset>
                    <option value="">Chọn nhanh mẫu lịch gửi hoặc tự tuỳ chỉnh ở dưới</option>
                    <?php foreach ($schedulePresets as $preset): ?>
                        <option value="<?= e($preset['key']) ?>"><?= e($preset['name']) ?> · <?= e($preset['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="chip-row" style="margin-bottom: 18px;">
                <?php foreach ($schedulePresets as $preset): ?>
                    <button class="chip" type="button" data-schedule-chip="<?= e($preset['key']) ?>"><?= e($preset['name']) ?></button>
                <?php endforeach; ?>
            </div>

            <form class="form-grid" method="post" action="<?= e(url('/schedules')) ?>" data-schedule-form>
                <?= csrf_field() ?>
                <div class="form-feedback" data-form-feedback hidden></div>

                <section class="builder-block">
                    <div class="builder-block-head">
                        <div>
                            <h3 class="builder-block-title">Thông tin nền</h3>
                        </div>
                    </div>
                    <div class="schedule-core-grid">
                        <div class="field">
                            <label for="schedule_modal_account">Tài khoản Telegram</label>
                            <select class="select" id="schedule_modal_account" name="telegram_account_id" required data-schedule-account>
                                <option value="">Chọn tài khoản</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= e((string) $account['id']) ?>"><?= e($account['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="schedule_modal_group">Nhóm Telegram</label>
                            <select class="select" id="schedule_modal_group" name="telegram_group_id" required data-schedule-group>
                                <option value="">Chọn tài khoản trước</option>
                                <?php foreach ($groups as $group): ?>
                                    <?php
                                    $groupLabel = (string) $group['title'];
                                    if (!empty($group['topic_title'])) {
                                        $groupLabel .= ' · Topic: ' . (string) $group['topic_title'];
                                    } elseif (!empty($group['topic_id'])) {
                                        $groupLabel .= ' · Topic ID: ' . (string) $group['topic_id'];
                                    }
                                    $groupLabel .= ' (' . (string) $group['account_name'] . ')';
                                    ?>
                                    <option
                                        value="<?= e((string) $group['id']) ?>"
                                        data-group-account-id="<?= e((string) $group['telegram_account_id']) ?>"
                                        data-group-label="<?= e($groupLabel) ?>"
                                    >
                                        <?= e($groupLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field schedule-field-span-2">
                            <label for="schedule_modal_template">Mẫu tin nhắn</label>
                            <select class="select" id="schedule_modal_template" name="message_template_id" required data-schedule-template>
                                <option value="">Chọn mẫu tin nhắn</option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?= e((string) $template['id']) ?>"><?= e($template['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="schedule_modal_timezone">Múi giờ</label>
                            <input class="input" id="schedule_modal_timezone" type="text" name="timezone" value="<?= e($defaultTimezone) ?>" required data-schedule-timezone>
                        </div>

                        <div class="field">
                            <label for="schedule_modal_type">Kiểu lịch</label>
                            <select class="select" id="schedule_modal_type" name="schedule_type" required data-schedule-type>
                                <?php foreach ($scheduleModes as $mode): ?>
                                    <option value="<?= e($mode['value']) ?>"><?= e($mode['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="builder-block">
                    <div class="builder-block-head">
                        <div>
                            <h3 class="builder-block-title">Cấu hình lịch</h3>
                        </div>
                    </div>

                    <div class="field" data-schedule-section="interval_minutes">
                        <label for="schedule_modal_interval_minutes">Mỗi X phút</label>
                        <div class="schedule-inline-grid">
                            <input class="input" id="schedule_modal_interval_minutes" type="number" name="interval_minutes" min="5" max="59" value="15" data-schedule-interval-minutes>
                            <div class="inline-hint">phút / lần</div>
                        </div>
                    </div>

                    <div class="field" data-schedule-section="interval_hours">
                        <label for="schedule_modal_interval_hours">Mỗi X giờ</label>
                        <div class="schedule-inline-grid schedule-inline-grid-wide">
                            <input class="input" id="schedule_modal_interval_hours" type="number" name="interval_hours" min="1" max="23" value="4" placeholder="Ví dụ: 4" data-schedule-interval-hours>
                            <select class="select" id="schedule_modal_interval_hour_minute" name="interval_hour_minute" data-schedule-interval-hour-minute>
                                <?php for ($minute = 0; $minute < 60; $minute++): ?>
                                    <?php $formattedMinute = str_pad((string) $minute, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= e($formattedMinute) ?>" <?= $formattedMinute === '00' ? 'selected' : '' ?>>Phút <?= e($formattedMinute) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field" data-schedule-section="daily_times">
                        <div class="schedule-section-head">
                            <label>Những giờ chạy mỗi ngày</label>
                            <button class="button secondary schedule-add-button" type="button" data-add-daily-time>Thêm mốc giờ</button>
                        </div>
                        <div class="stack schedule-time-list" data-daily-times-list></div>
                    </div>

                    <div class="field" data-schedule-section="weekly_times">
                        <label>Ngày chạy trong tuần</label>
                        <div class="chip-row schedule-weekday-row" style="margin-bottom: 12px;">
                            <?php foreach ([
                                '1' => 'Thứ 2',
                                '2' => 'Thứ 3',
                                '3' => 'Thứ 4',
                                '4' => 'Thứ 5',
                                '5' => 'Thứ 6',
                                '6' => 'Thứ 7',
                                '0' => 'Chủ nhật',
                            ] as $weekdayValue => $weekdayLabel): ?>
                                <label class="chip schedule-weekday-chip" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" name="weekly_days[]" value="<?= e($weekdayValue) ?>" data-weekly-day>
                                    <span><?= e($weekdayLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="schedule-section-head">
                            <label>Những giờ chạy ở các ngày đã chọn</label>
                            <button class="button secondary schedule-add-button" type="button" data-add-weekly-time>Thêm mốc giờ</button>
                        </div>
                        <div class="stack schedule-time-list" data-weekly-times-list></div>
                    </div>

                    <div class="field" data-schedule-section="advanced">
                        <label for="schedule_modal_cron">Cron nâng cao</label>
                        <textarea class="textarea mono" id="schedule_modal_cron" name="cron_expression" rows="4" placeholder="Ví dụ: 0 8 * * *&#10;hoặc&#10;30 8 * * 1,2,3,4,5 | 0 20 * * *" data-schedule-cron></textarea>
                    </div>
                </section>

                <div class="actions">
                    <button class="button primary" type="submit" data-schedule-submit data-loading-text="Đang lưu...">Lưu lịch gửi</button>
                    <button class="button secondary" type="button" data-crud-modal-close>Hủy</button>
                </div>
            </form>
        </section>

        <section class="card schedule-builder-side">
            <h2 class="section-title">Xem trước lịch chạy</h2>
            <div class="list">
                <div class="list-item">
                    <strong>Cron thực thi</strong>
                    <div class="inline-actions" style="margin-top: 8px;">
                        <div class="small mono" style="flex:1;" data-schedule-preview-cron>-</div>
                        <button class="button secondary" type="button" data-copy-preview-cron>Sao chép cron</button>
                    </div>
                </div>
                <div class="list-item">
                    <strong>Mô tả</strong>
                    <div class="small muted" data-schedule-preview-summary>-</div>
                </div>
                <div class="list-item">
                    <strong>5 lần chạy tiếp theo</strong>
                    <div class="small muted" data-schedule-preview-runs>-</div>
                </div>
                <div class="list-item">
                    <strong>Mật độ an toàn</strong>
                    <div class="inline-actions" style="margin-top: 8px;">
                        <span class="badge info" data-schedule-preview-risk-badge>Đang tính</span>
                    </div>
                    <div class="small muted" style="margin-top: 8px; display:none;" data-schedule-preview-risk-message></div>
                </div>
            </div>
        </section>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
(function () {
    const schedulePresets = <?= json_encode($schedulePresets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const defaultScheduleState = <?= json_encode($defaultFormScheduleState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const previewUrl = <?= json_encode(url('/schedules/preview'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const createUrl = <?= json_encode(url('/schedules'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const updateUrl = <?= json_encode(url('/schedules/update'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const createButton = document.getElementById('open_schedule_create');
    const editorTemplate = document.getElementById('schedule_editor_template');
    let scheduleRecords = window.TeleSenderApp?.readJsonScript('[data-schedule-records]', {}) || {};

    if (!editorTemplate || !window.TeleSenderCrudModal || !window.TeleSenderApp) {
        return;
    }

    function createTimeRow(name, value = '') {
        const row = document.createElement('div');
        row.className = 'schedule-time-row';
        row.innerHTML = `
            <input class="input schedule-time-input" type="time" name="${name}" value="${value}">
            <button class="button secondary schedule-time-delete" type="button" data-remove-time>Xóa</button>
        `;
        return row;
    }

    function openScheduleModal(mode, scheduleId = null) {
        const fragment = editorTemplate.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.appendChild(fragment);

        const form = wrapper.querySelector('[data-schedule-form]');
        const presetSelect = wrapper.querySelector('[data-schedule-preset]');
        const typeInput = wrapper.querySelector('[data-schedule-type]');
        const timezoneInput = wrapper.querySelector('[data-schedule-timezone]');
        const cronInput = wrapper.querySelector('[data-schedule-cron]');
        const accountInput = wrapper.querySelector('[data-schedule-account]');
        const groupInput = wrapper.querySelector('[data-schedule-group]');
        const templateInput = wrapper.querySelector('[data-schedule-template]');
        const submitButton = wrapper.querySelector('[data-schedule-submit]');
        const dailyTimesList = wrapper.querySelector('[data-daily-times-list]');
        const weeklyTimesList = wrapper.querySelector('[data-weekly-times-list]');
        const previewCron = wrapper.querySelector('[data-schedule-preview-cron]');
        const previewSummary = wrapper.querySelector('[data-schedule-preview-summary]');
        const previewRuns = wrapper.querySelector('[data-schedule-preview-runs]');
        const previewRiskBadge = wrapper.querySelector('[data-schedule-preview-risk-badge]');
        const previewRiskMessage = wrapper.querySelector('[data-schedule-preview-risk-message]');
        const copyPreviewCronButton = wrapper.querySelector('[data-copy-preview-cron]');
        let previewTimer = null;

        if (
            !form || !presetSelect || !typeInput || !timezoneInput || !cronInput || !accountInput || !groupInput
            || !templateInput || !submitButton || !dailyTimesList || !weeklyTimesList || !previewCron
            || !previewSummary || !previewRuns || !previewRiskBadge || !previewRiskMessage || !copyPreviewCronButton
        ) {
            return;
        }

        const allGroupOptions = Array.from(groupInput.querySelectorAll('option[data-group-account-id]')).map((option) => ({
            value: option.value,
            accountId: option.getAttribute('data-group-account-id') || '',
            label: option.getAttribute('data-group-label') || option.textContent.trim(),
        }));

        function toggleScheduleSections() {
            const activeType = typeInput.value;
            wrapper.querySelectorAll('[data-schedule-section]').forEach((section) => {
                section.style.display = section.getAttribute('data-schedule-section') === activeType ? '' : 'none';
            });
        }

        function syncGroupOptions(preferredGroupId = null) {
            const selectedAccountId = String(accountInput.value || '');
            const fallbackGroupId = preferredGroupId === null ? String(groupInput.value || '') : String(preferredGroupId || '');
            const matchingGroups = selectedAccountId === ''
                ? []
                : allGroupOptions.filter((option) => option.accountId === selectedAccountId);

            groupInput.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';

            if (selectedAccountId === '') {
                placeholderOption.textContent = 'Chọn tài khoản trước';
                groupInput.disabled = true;
            } else if (matchingGroups.length === 0) {
                placeholderOption.textContent = 'Tài khoản này chưa có nhóm đã lưu';
                groupInput.disabled = false;
            } else {
                placeholderOption.textContent = 'Chọn nhóm';
                groupInput.disabled = false;
            }

            groupInput.appendChild(placeholderOption);

            matchingGroups.forEach((option) => {
                const element = document.createElement('option');
                element.value = option.value;
                element.textContent = option.label;
                groupInput.appendChild(element);
            });

            const nextGroupId = matchingGroups.some((option) => option.value === fallbackGroupId) ? fallbackGroupId : '';
            groupInput.value = nextGroupId;
        }

        function bindRemoveTimeButtons(scope = wrapper) {
            scope.querySelectorAll('[data-remove-time]').forEach((button) => {
                if (button.dataset.bound === '1') {
                    return;
                }

                button.dataset.bound = '1';
                button.addEventListener('click', () => {
                    const row = button.closest('.schedule-time-row');
                    row?.remove();
                    triggerPreview();
                });
            });
        }

        function addTimeRow(container, name, value = '') {
            const row = createTimeRow(name, value);
            container.appendChild(row);
            bindRemoveTimeButtons(row);
        }

        function applySchedulePreset(key) {
            const preset = schedulePresets.find((item) => item.key === key);
            if (!preset) {
                return;
            }

            timezoneInput.value = preset.timezone || 'Asia/Ho_Chi_Minh';

            if (preset.schedule_type) {
                typeInput.value = preset.schedule_type;
                toggleScheduleSections();
            } else {
                typeInput.value = 'advanced';
                toggleScheduleSections();
            }

            cronInput.value = preset.cron_expression || '';

            const config = preset.schedule_config || {};
            const intervalMinutesInput = wrapper.querySelector('[data-schedule-interval-minutes]');
            const intervalHoursInput = wrapper.querySelector('[data-schedule-interval-hours]');
            const intervalHourMinuteInput = wrapper.querySelector('[data-schedule-interval-hour-minute]');

            if (preset.schedule_type === 'interval_minutes' && intervalMinutesInput) {
                intervalMinutesInput.value = config.interval_minutes || 15;
            }

            if (preset.schedule_type === 'interval_hours' && intervalHoursInput && intervalHourMinuteInput) {
                intervalHoursInput.value = config.interval_hours || 4;
                intervalHourMinuteInput.value = String(config.minute ?? 0).padStart(2, '0');
            }

            if (preset.schedule_type === 'daily_times') {
                dailyTimesList.innerHTML = '';
                (config.times || ['08:00']).forEach((time) => addTimeRow(dailyTimesList, 'daily_times[]', time));
            }

            if (preset.schedule_type === 'weekly_times') {
                wrapper.querySelectorAll('input[name="weekly_days[]"]').forEach((checkbox) => {
                    checkbox.checked = (config.days || []).includes(checkbox.value);
                });

                weeklyTimesList.innerHTML = '';
                (config.times || ['09:00']).forEach((time) => addTimeRow(weeklyTimesList, 'weekly_times[]', time));
            }

            triggerPreview();
        }

        async function updateSchedulePreview() {
            const query = new URLSearchParams(new FormData(form));

            try {
                const payload = await window.TeleSenderApp.fetchJson(`${previewUrl}?${query.toString()}`);

                previewCron.textContent = payload.cron_expression;
                previewSummary.textContent = payload.summary;
                previewRuns.textContent = Array.isArray(payload.next_runs) ? payload.next_runs.join(' · ') : '-';

                const risk = payload.risk || {};
                const riskLabel = {
                    safe: 'An toàn',
                    medium: 'Cần lưu ý',
                    high: 'Khá dày',
                    blocked: 'Quá dày',
                }[risk.risk] || 'Đang tính';
                const riskClass = {
                    safe: 'success',
                    medium: 'info',
                    high: 'warning',
                    blocked: 'danger',
                }[risk.risk] || 'info';

                previewRiskBadge.className = `badge ${riskClass}`;
                previewRiskBadge.textContent = riskLabel;
                previewRiskMessage.textContent = risk.message || '';
                previewRiskMessage.style.display = risk.message ? '' : 'none';
            } catch (error) {
                previewCron.textContent = '-';
                previewSummary.textContent = error.message || 'Không preview được lịch này.';
                previewRuns.textContent = '-';
                previewRiskBadge.className = 'badge warning';
                previewRiskBadge.textContent = 'Chưa hợp lệ';
                previewRiskMessage.textContent = error.message || 'Vui lòng kiểm tra lại cấu hình lịch.';
                previewRiskMessage.style.display = '';
            }
        }

        function triggerPreview() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(updateSchedulePreview, 180);
        }

        function applyFormState(state) {
            const formState = state || defaultScheduleState;
            const builder = formState.builder || {};
            const intervalMinutesInput = wrapper.querySelector('[data-schedule-interval-minutes]');
            const intervalHoursInput = wrapper.querySelector('[data-schedule-interval-hours]');
            const intervalHourMinuteInput = wrapper.querySelector('[data-schedule-interval-hour-minute]');

            typeInput.value = formState.schedule_type || 'daily_times';
            timezoneInput.value = formState.timezone || 'Asia/Ho_Chi_Minh';
            cronInput.value = formState.cron_expression || '';

            if (intervalMinutesInput) {
                intervalMinutesInput.value = builder.interval_minutes || 15;
            }

            if (intervalHoursInput) {
                intervalHoursInput.value = builder.interval_hours || 4;
            }

            if (intervalHourMinuteInput) {
                intervalHourMinuteInput.value = builder.interval_hour_minute || '00';
            }

            dailyTimesList.innerHTML = '';
            (builder.daily_times || ['08:00']).forEach((time) => addTimeRow(dailyTimesList, 'daily_times[]', time));

            wrapper.querySelectorAll('input[name="weekly_days[]"]').forEach((checkbox) => {
                checkbox.checked = (builder.weekly_days || []).includes(checkbox.value);
            });

            weeklyTimesList.innerHTML = '';
            (builder.weekly_times || ['09:00']).forEach((time) => addTimeRow(weeklyTimesList, 'weekly_times[]', time));

            toggleScheduleSections();
        }

        const record = mode === 'edit' ? (scheduleRecords[String(scheduleId)] || null) : null;

        if (mode === 'edit' && !record) {
            window.TeleSenderApp.showFlash('error', 'Không tìm thấy schedule để chỉnh sửa.');
            return;
        }

        form.action = mode === 'edit' ? updateUrl : createUrl;
        submitButton.textContent = mode === 'edit' ? 'Cập nhật lịch gửi' : 'Tạo lịch gửi';
        applyFormState(record ? record.form_state : defaultScheduleState);
        accountInput.value = record ? String(record.telegram_account_id || '') : '';
        syncGroupOptions(record ? String(record.telegram_group_id || '') : '');
        templateInput.value = record ? String(record.message_template_id || '') : '';

        if (mode === 'edit' && record) {
            const idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'id';
            idField.value = String(record.id || '');
            form.prepend(idField);
        }

        wrapper.querySelector('[data-add-daily-time]')?.addEventListener('click', () => {
            addTimeRow(dailyTimesList, 'daily_times[]', '08:00');
            triggerPreview();
        });

        wrapper.querySelector('[data-add-weekly-time]')?.addEventListener('click', () => {
            addTimeRow(weeklyTimesList, 'weekly_times[]', '09:00');
            triggerPreview();
        });

        typeInput.addEventListener('change', () => {
            toggleScheduleSections();
            triggerPreview();
        });

        accountInput.addEventListener('change', () => {
            syncGroupOptions();
        });

        presetSelect.addEventListener('change', (event) => {
            applySchedulePreset(event.target.value);
        });

        wrapper.querySelectorAll('[data-schedule-chip]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-schedule-chip');
                presetSelect.value = key;
                applySchedulePreset(key);
            });
        });

        form.addEventListener('input', triggerPreview);
        form.addEventListener('change', triggerPreview);

        bindRemoveTimeButtons();
        toggleScheduleSections();
        triggerPreview();

        copyPreviewCronButton.addEventListener('click', async () => {
            const cron = previewCron.textContent?.trim();
            if (!cron || cron === '-') {
                return;
            }

            try {
                await navigator.clipboard.writeText(cron);
                copyPreviewCronButton.textContent = 'Đã sao chép';
                setTimeout(() => {
                    copyPreviewCronButton.textContent = 'Sao chép cron';
                }, 1200);
            } catch (error) {
                copyPreviewCronButton.textContent = 'Lỗi sao chép';
                setTimeout(() => {
                    copyPreviewCronButton.textContent = 'Sao chép cron';
                }, 1200);
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            await window.TeleSenderApp.submitAjaxForm(form, {
                closeCrudModalOnSuccess: true,
                refreshRegionsOnSuccess: ['[data-live-region="schedules-shell"]'],
            });
        });

        window.TeleSenderCrudModal.open({
            title: mode === 'edit' ? 'Cập nhật lịch gửi' : 'Tạo lịch gửi mới',
            description: 'Giữ nguyên schedule builder và preview cron realtime, chỉ chuyển form vào modal để gọn UI hơn.',
            size: 'full',
            content: wrapper,
            onClose() {
                if (previewTimer !== null) {
                    clearTimeout(previewTimer);
                }
            },
        });
    }

    createButton?.addEventListener('click', () => {
        openScheduleModal('create');
    });

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-schedule-edit]') : null;

        if (!button) {
            return;
        }

        openScheduleModal('edit', button.getAttribute('data-schedule-edit'));
    });

    document.addEventListener('app:regions:refreshed', () => {
        scheduleRecords = window.TeleSenderApp.readJsonScript('[data-schedule-records]', {});
    });
})();
});
</script>
