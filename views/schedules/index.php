<?php

declare(strict_types=1);

$formType = (string) ($formScheduleState['schedule_type'] ?? 'daily_times');
$formTimezone = (string) ($formScheduleState['timezone'] ?? $defaultTimezone);
$formCronExpression = (string) ($formScheduleState['cron_expression'] ?? '0 8 * * *');
$formBuilder = (array) ($formScheduleState['builder'] ?? []);
$intervalMinutes = (int) ($formBuilder['interval_minutes'] ?? 15);
$intervalHours = (int) ($formBuilder['interval_hours'] ?? 4);
$intervalHourMinute = (string) ($formBuilder['interval_hour_minute'] ?? '00');
$dailyTimes = (array) ($formBuilder['daily_times'] ?? ['08:00', '12:00', '20:00']);
$weeklyDays = array_map('strval', (array) ($formBuilder['weekly_days'] ?? ['1', '2', '3', '4', '5']));
$weeklyTimes = (array) ($formBuilder['weekly_times'] ?? ['09:00', '13:00', '17:00']);
$scheduleTypeLabels = [
    'interval_minutes' => 'Mỗi X phút',
    'interval_hours' => 'Mỗi X giờ',
    'daily_times' => 'Mỗi ngày theo giờ',
    'weekly_times' => 'Theo ngày trong tuần',
    'advanced' => 'Nâng cao',
];
$weekdayOptions = [
    '1' => 'Thứ 2',
    '2' => 'Thứ 3',
    '3' => 'Thứ 4',
    '4' => 'Thứ 5',
    '5' => 'Thứ 6',
    '6' => 'Thứ 7',
    '0' => 'Chủ nhật',
];
?>
<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Lịch gửi</h1>
    </div>

    <div class="schedule-builder-layout">
        <section class="card schedule-builder-main">
            <h2 class="section-title"><?= $editSchedule ? 'Cập nhật lịch gửi' : 'Tạo lịch gửi mới' ?></h2>
            <div class="field" style="margin: 16px 0 18px;">
                <label for="schedule_preset">Lịch cài sẵn</label>
                <select class="select" id="schedule_preset">
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

            <form class="form-grid" id="schedule_form" method="post" action="<?= e(url($editSchedule ? '/schedules/update' : '/schedules')) ?>">
                <?= csrf_field() ?>
                <?php if ($editSchedule): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editSchedule['id']) ?>">
                <?php endif; ?>

                <section class="builder-block">
                    <div class="builder-block-head">
                        <div>
                            <h3 class="builder-block-title">Thông tin nền</h3>
                        </div>
                    </div>
                    <div class="schedule-core-grid">
                        <div class="field">
                            <label for="telegram_account_id">Tài khoản Telegram</label>
                            <select class="select" id="telegram_account_id" name="telegram_account_id" required>
                                <option value="">Chọn tài khoản</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?= e((string) $account['id']) ?>" <?= (string) ($editSchedule['telegram_account_id'] ?? '') === (string) $account['id'] ? 'selected' : '' ?>>
                                        <?= e($account['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="telegram_group_id">Nhóm Telegram</label>
                            <select class="select" id="telegram_group_id" name="telegram_group_id" required>
                                <option value="">Chọn nhóm</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?= e((string) $group['id']) ?>" <?= (string) ($editSchedule['telegram_group_id'] ?? '') === (string) $group['id'] ? 'selected' : '' ?>>
                                        <?= e($group['title']) ?>
                                        <?php if (!empty($group['topic_title'])): ?>
                                            · Topic: <?= e($group['topic_title']) ?>
                                        <?php elseif (!empty($group['topic_id'])): ?>
                                            · Topic ID: <?= e((string) $group['topic_id']) ?>
                                        <?php endif; ?>
                                        (<?= e($group['account_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field schedule-field-span-2">
                            <label for="message_template_id">Mẫu tin nhắn</label>
                            <select class="select" id="message_template_id" name="message_template_id" required>
                                <option value="">Chọn mẫu tin nhắn</option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?= e((string) $template['id']) ?>" <?= (string) ($editSchedule['message_template_id'] ?? '') === (string) $template['id'] ? 'selected' : '' ?>>
                                        <?= e($template['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="timezone">Múi giờ</label>
                            <input class="input" id="timezone" type="text" name="timezone" value="<?= e($formTimezone) ?>" required>
                        </div>

                        <div class="field">
                            <label for="schedule_type">Kiểu lịch</label>
                            <select class="select" id="schedule_type" name="schedule_type" required>
                                <?php foreach ($scheduleModes as $mode): ?>
                                    <option value="<?= e($mode['value']) ?>" <?= $formType === $mode['value'] ? 'selected' : '' ?>>
                                        <?= e($mode['label']) ?>
                                    </option>
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
                        <label for="interval_minutes">Mỗi X phút</label>
                        <div class="schedule-inline-grid">
                            <input class="input" id="interval_minutes" type="number" name="interval_minutes" min="5" max="59" value="<?= e((string) $intervalMinutes) ?>">
                            <div class="inline-hint">phút / lần</div>
                        </div>
                    </div>

                    <div class="field" data-schedule-section="interval_hours">
                        <label for="interval_hours">Mỗi X giờ</label>
                        <div class="schedule-inline-grid schedule-inline-grid-wide">
                            <input class="input" id="interval_hours" type="number" name="interval_hours" min="1" max="23" value="<?= e((string) $intervalHours) ?>" placeholder="Ví dụ: 4">
                            <select class="select" id="interval_hour_minute" name="interval_hour_minute">
                                <?php for ($minute = 0; $minute < 60; $minute++): ?>
                                    <?php $formattedMinute = str_pad((string) $minute, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= e($formattedMinute) ?>" <?= $intervalHourMinute === $formattedMinute ? 'selected' : '' ?>>
                                        Phút <?= e($formattedMinute) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field" data-schedule-section="daily_times">
                        <div class="schedule-section-head">
                            <label>Những giờ chạy mỗi ngày</label>
                            <button class="button secondary schedule-add-button" type="button" id="add_daily_time">Thêm mốc giờ</button>
                        </div>
                        <div class="stack schedule-time-list" id="daily_times_list">
                            <?php foreach ($dailyTimes as $time): ?>
                                <div class="schedule-time-row">
                                    <input class="input schedule-time-input" type="time" name="daily_times[]" value="<?= e((string) $time) ?>">
                                    <button class="button secondary schedule-time-delete" type="button" data-remove-time>Xóa</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="field" data-schedule-section="weekly_times">
                        <label>Ngày chạy trong tuần</label>
                        <div class="chip-row schedule-weekday-row" style="margin-bottom: 12px;">
                            <?php foreach ($weekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                <label class="chip schedule-weekday-chip" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" name="weekly_days[]" value="<?= e($weekdayValue) ?>" <?= in_array($weekdayValue, $weeklyDays, true) ? 'checked' : '' ?>>
                                    <span><?= e($weekdayLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="schedule-section-head">
                            <label>Những giờ chạy ở các ngày đã chọn</label>
                            <button class="button secondary schedule-add-button" type="button" id="add_weekly_time">Thêm mốc giờ</button>
                        </div>
                        <div class="stack schedule-time-list" id="weekly_times_list">
                            <?php foreach ($weeklyTimes as $time): ?>
                                <div class="schedule-time-row">
                                    <input class="input schedule-time-input" type="time" name="weekly_times[]" value="<?= e((string) $time) ?>">
                                    <button class="button secondary schedule-time-delete" type="button" data-remove-time>Xóa</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="field" data-schedule-section="advanced">
                        <label for="cron_expression">Cron nâng cao</label>
                        <textarea class="textarea mono" id="cron_expression" name="cron_expression" rows="4" placeholder="Ví dụ: 0 8 * * *&#10;hoặc&#10;30 8 * * 1,2,3,4,5 | 0 20 * * *"><?= e($formCronExpression) ?></textarea>
                    </div>
                </section>

                <div class="actions">
                    <button class="button primary" type="submit"><?= $editSchedule ? 'Cập nhật lịch gửi' : 'Tạo lịch gửi' ?></button>
                    <?php if ($editSchedule): ?>
                        <a class="button secondary" href="<?= e(url('/schedules')) ?>">Tạo mới</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card schedule-builder-side">
            <h2 class="section-title">Xem trước lịch chạy</h2>
            <div class="list">
                <div class="list-item">
                    <strong>Cron thực thi</strong>
                    <div class="inline-actions" style="margin-top: 8px;">
                        <div class="small mono" id="schedule_preview_cron" style="flex:1;">-</div>
                        <button class="button secondary" id="copy_preview_cron" type="button">Sao chép cron</button>
                    </div>
                </div>
                <div class="list-item">
                    <strong>Mô tả</strong>
                    <div class="small muted" id="schedule_preview_summary">-</div>
                </div>
                <div class="list-item">
                    <strong>5 lần chạy tiếp theo</strong>
                    <div class="small muted" id="schedule_preview_runs">-</div>
                </div>
                <div class="list-item">
                    <strong>Mật độ an toàn</strong>
                    <div class="inline-actions" style="margin-top: 8px;">
                        <span class="badge info" id="schedule_preview_risk_badge">Đang tính</span>
                    </div>
                    <div class="small muted" id="schedule_preview_risk_message" style="margin-top: 8px; display:none;"></div>
                </div>
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Danh sách lịch gửi</h2>
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
                                <a class="button secondary" href="<?= e(url('/schedules?edit=' . $schedule['id'])) ?>">Sửa</a>
                                <form
                                    method="post"
                                    action="<?= e(url('/schedules/send-now')) ?>"
                                    data-send-now-form
                                    <?= $manualGuard !== null ? 'data-risk-message="' . e((string) ($manualGuard['reason'] ?? 'Tài khoản đang trong vùng rủi ro an toàn.')) . '"' : '' ?>
                                >
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <input type="hidden" name="force_send" value="0" data-force-send-input>
                                    <button class="button secondary" type="submit">Gửi ngay</button>
                                </form>
                                <form method="post" action="<?= e(url('/schedules/toggle')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <button class="button accent" type="submit"><?= $schedule['status'] === 'active' ? 'Tạm dừng' : 'Tiếp tục' ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/schedules/delete')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <button class="button danger" type="submit">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($schedules === []): ?>
                    <tr><td colspan="7" class="muted">Chưa có lịch gửi nào.</td></tr>
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
</section>

<script>
function requestAppModal(mode, options = {}) {
    return new Promise((resolve) => {
        const responseEvent = `app:modal:response:${Date.now()}:${Math.random().toString(16).slice(2)}`;
        const handleResponse = (event) => {
            document.removeEventListener(responseEvent, handleResponse);
            resolve(Boolean(event.detail?.confirmed));
        };

        document.addEventListener(responseEvent, handleResponse, { once: true });
        document.dispatchEvent(new CustomEvent('app:modal:open', {
            detail: {
                mode,
                options,
                responseEvent,
            },
        }));
    });
}

const schedulePresets = <?= json_encode($schedulePresets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const scheduleForm = document.getElementById('schedule_form');
const schedulePresetSelect = document.getElementById('schedule_preset');
const scheduleTypeInput = document.getElementById('schedule_type');
const timezoneInput = document.getElementById('timezone');
const cronInput = document.getElementById('cron_expression');
const dailyTimesList = document.getElementById('daily_times_list');
const weeklyTimesList = document.getElementById('weekly_times_list');
const previewCron = document.getElementById('schedule_preview_cron');
const previewSummary = document.getElementById('schedule_preview_summary');
const previewRuns = document.getElementById('schedule_preview_runs');
const previewRiskBadge = document.getElementById('schedule_preview_risk_badge');
const previewRiskMessage = document.getElementById('schedule_preview_risk_message');
const copyPreviewCronButton = document.getElementById('copy_preview_cron');
const previewUrl = '<?= e(url('/schedules/preview')) ?>';

function toggleScheduleSections() {
    const activeType = scheduleTypeInput.value;
    document.querySelectorAll('[data-schedule-section]').forEach((section) => {
        section.style.display = section.getAttribute('data-schedule-section') === activeType ? '' : 'none';
    });
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

function bindRemoveTimeButtons(scope = document) {
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
        scheduleTypeInput.value = preset.schedule_type;
        toggleScheduleSections();
    } else {
        scheduleTypeInput.value = 'advanced';
        toggleScheduleSections();
    }

    cronInput.value = preset.cron_expression || '';

    const config = preset.schedule_config || {};
    if (preset.schedule_type === 'interval_minutes') {
        document.getElementById('interval_minutes').value = config.interval_minutes || 15;
    }

    if (preset.schedule_type === 'interval_hours') {
        document.getElementById('interval_hours').value = config.interval_hours || 4;
        document.getElementById('interval_hour_minute').value = String(config.minute ?? 0).padStart(2, '0');
    }

    if (preset.schedule_type === 'daily_times') {
        dailyTimesList.innerHTML = '';
        (config.times || ['08:00']).forEach((time) => addTimeRow(dailyTimesList, 'daily_times[]', time));
    }

    if (preset.schedule_type === 'weekly_times') {
        document.querySelectorAll('input[name="weekly_days[]"]').forEach((checkbox) => {
            checkbox.checked = (config.days || []).includes(checkbox.value);
        });

        weeklyTimesList.innerHTML = '';
        (config.times || ['09:00']).forEach((time) => addTimeRow(weeklyTimesList, 'weekly_times[]', time));
    }

    triggerPreview();
}

let previewTimer = null;

async function updateSchedulePreview() {
    const query = new URLSearchParams(new FormData(scheduleForm));

    try {
        const response = await fetch(`${previewUrl}?${query.toString()}`, {
            headers: {
                'Accept': 'application/json',
            },
        });
        const payload = await response.json();

        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'Không preview được lịch này.');
        }

        previewCron.textContent = payload.cron_expression;
        previewSummary.textContent = payload.summary;
        previewRuns.textContent = payload.next_runs.join(' · ');

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

document.getElementById('add_daily_time')?.addEventListener('click', () => {
    addTimeRow(dailyTimesList, 'daily_times[]', '08:00');
    triggerPreview();
});

document.getElementById('add_weekly_time')?.addEventListener('click', () => {
    addTimeRow(weeklyTimesList, 'weekly_times[]', '09:00');
    triggerPreview();
});

scheduleTypeInput?.addEventListener('change', () => {
    toggleScheduleSections();
    triggerPreview();
});

schedulePresetSelect?.addEventListener('change', (event) => {
    applySchedulePreset(event.target.value);
});

document.querySelectorAll('[data-schedule-chip]').forEach((button) => {
    button.addEventListener('click', () => {
        const key = button.getAttribute('data-schedule-chip');
        schedulePresetSelect.value = key;
        applySchedulePreset(key);
    });
});

scheduleForm?.addEventListener('input', triggerPreview);
scheduleForm?.addEventListener('change', triggerPreview);

bindRemoveTimeButtons();
toggleScheduleSections();
triggerPreview();

copyPreviewCronButton?.addEventListener('click', async () => {
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

document.querySelectorAll('[data-send-now-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        const message = form.getAttribute('data-risk-message') || '';
        const forceInput = form.querySelector('[data-force-send-input]');

        if (!forceInput) {
            return;
        }

        if (message === '') {
            forceInput.value = '0';
            return;
        }

        event.preventDefault();

        const confirmed = await requestAppModal('confirm', {
            title: 'Xác nhận gửi ngay',
            message: message + '\n\nNếu tiếp tục, hệ thống sẽ ép gửi ngay và bỏ qua cooldown / giãn cách an toàn ở lần bấm này.',
            confirmText: 'Vẫn gửi ngay',
            cancelText: 'Hủy',
            confirmClass: 'danger',
        });

        if (!confirmed) {
            forceInput.value = '0';
            return;
        }

        forceInput.value = '1';
        form.submit();
    });
});
</script>
