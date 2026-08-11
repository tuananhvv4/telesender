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
        'telegram_group_ids' => array_values(array_map('intval', (array) ($schedule['telegram_group_ids'] ?? [$schedule['telegram_group_id']]))),
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
    <section class="panel listing-panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Danh sách lịch gửi</h2>
                <p class="panel-copy">Lọc theo tài khoản, mẫu tin, trạng thái hoặc từ khóa để quản lý danh sách lịch gửi lớn dễ hơn.</p>
            </div>

            <form class="toolbar-form" method="get" action="<?= e(url('/schedules')) ?>" data-schedule-filter-form>
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
                        <a class="button secondary" href="<?= e(url('/schedules')) ?>" data-schedule-clear-filters>Xóa lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="panel-body table-wrap listing-body">
            <table class="data-table responsive-data-table">
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
                <tbody data-schedule-rows>
                <?php foreach ($schedules as $schedule): ?>
                    <?php
                    $analysis = $scheduleAnalyses[(int) $schedule['id']] ?? ['risk' => 'safe', 'message' => '', 'runs_per_day' => 0, 'min_gap_minutes' => null];
                    $manualGuard = $scheduleManualGuards[(int) $schedule['id']] ?? null;
                    $manualGuardCanBypass = $manualGuard !== null && !empty($manualGuard['bypass_allowed']);
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
                    <tr data-schedule-row="<?= e((string) $schedule['id']) ?>">
                        <td data-label="Mẫu tin">
                            <strong><?= e($schedule['template_name']) ?></strong>
                            <div style="margin-top:8px;">
                                <span class="badge info"><?= e($scheduleTypeLabels[(string) ($schedule['schedule_type'] ?? 'advanced')] ?? 'Nâng cao') ?></span>
                            </div>
                            <?php if (!empty($schedule['last_error'])): ?>
                                <div class="small" style="color:<?= $queueNotice ? '#0f766e' : '#b91c1c' ?>;" data-schedule-error><?= e(dispatch_error_message((string) $schedule['last_error'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Tài khoản / Nhóm">
                            <div><?= e($schedule['account_name']) ?></div>
                            <div class="schedule-target-list">
                                <?php foreach ((array) ($schedule['target_groups'] ?? []) as $targetGroup): ?>
                                    <div class="small muted schedule-target-item">
                                        <?= e((string) $targetGroup['title']) ?>
                                        <?php if (!empty($targetGroup['topic_title'])): ?>
                                            · Topic: <?= e((string) $targetGroup['topic_title']) ?>
                                        <?php elseif (!empty($targetGroup['topic_id'])): ?>
                                            · Topic ID: <?= e((string) $targetGroup['topic_id']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ((int) ($schedule['group_count'] ?? 1) > 1): ?>
                                <span class="badge info schedule-target-count"><?= e((string) $schedule['group_count']) ?> nhóm</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Lịch chạy">
                            <div><?= e($summary) ?></div>
                            <div class="small muted">Múi giờ: <?= e($schedule['timezone']) ?></div>
                            <div class="small muted"><?= e((string) $analysis['runs_per_day']) ?> lần/ngày · <?= e($analysis['min_gap_minutes'] !== null ? (string) $analysis['min_gap_minutes'] . ' phút/lần' : 'không xác định') ?></div>
                        </td>
                        <td data-label="Lần chạy tới">
                            <div data-schedule-next-run><?= e(fmt_datetime($schedule['next_run_at'])) ?></div>
                            <div class="small muted">Lần chạy gần nhất: <?= e(fmt_datetime($schedule['last_run_at'])) ?></div>
                        </td>
                        <td data-label="Trạng thái" data-schedule-status-cell>
                            <span class="badge <?= $schedule['status'] === 'active' ? 'success' : 'warning' ?>" data-schedule-status-badge><?= e($schedule['status'] === 'active' ? 'Đang chạy' : 'Tạm dừng') ?></span>
                            <?php if ($queueNotice): ?>
                                <div style="margin-top:8px;" data-schedule-queue-notice>
                                    <span class="badge info">Đang xếp hàng</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="An toàn">
                            <span class="badge <?= e($riskBadgeClass) ?>"><?= e($riskLabel) ?></span>
                            <div class="small muted" style="margin-top: 6px;"><?= e($analysis['message']) ?></div>
                        </td>
                        <td data-label="Hành động">
                            <div class="inline-actions">
                                <button class="button secondary" type="button" data-schedule-edit="<?= e((string) $schedule['id']) ?>">Sửa</button>
                                <form
                                    method="post"
                                    action="<?= e(url('/schedules/send-now')) ?>"
                                    <?= $manualGuardCanBypass ? 'data-ajax-risk-confirm="1"' : '' ?>
                                    data-ajax-confirm-title="Xác nhận gửi ngay"
                                    data-ajax-confirm-text="Vẫn gửi ngay"
                                    data-send-now-form
                                    data-schedule-send-now-form
                                    <?= $manualGuardCanBypass ? 'data-risk-message="' . e((string) ($manualGuard['reason'] ?? 'Tài khoản đang trong vùng rủi ro an toàn.')) . '"' : '' ?>
                                >
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <input type="hidden" name="force_send" value="0" data-force-send-input>
                                    <button class="button secondary" type="submit" data-loading-text="Đang gửi..." <?= $manualGuard !== null && !$manualGuardCanBypass ? 'disabled title="' . e((string) ($manualGuard['reason'] ?? 'Đang bị guard bắt buộc chặn.')) . '"' : '' ?>>
                                        <?= $manualGuard !== null && !$manualGuardCanBypass ? 'Đang bị chặn' : 'Gửi ngay' ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= e(url('/schedules/toggle')) ?>" data-schedule-toggle-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <button class="button accent" type="submit" data-schedule-toggle-button data-loading-text="Đang cập nhật..."><?= $schedule['status'] === 'active' ? 'Tạm dừng' : 'Tiếp tục' ?></button>
                                </form>
                                <form method="post" action="<?= e(url('/schedules/delete')) ?>" data-schedule-delete-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <button class="button danger" type="submit" data-loading-text="Đang xóa...">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($schedules === []): ?>
                    <tr class="responsive-table-empty">
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
        <section class="panel" data-schedule-account-analysis>
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
                                    <div style="margin-top:6px;"><span class="badge <?= ($accountAnalysis['safety_mode'] ?? 'safe') === 'risk_accepted' ? 'danger' : (($accountAnalysis['safety_mode'] ?? 'safe') === 'elevated' ? 'warning' : 'success') ?>"><?= e((string) ($accountAnalysis['safety_mode_label'] ?? 'An toàn')) ?></span></div>
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
                                    <div class="small muted">Giới hạn: <?= ($accountAnalysis['hourly_limit'] ?? null) === null ? 'Không giới hạn' : e((string) $accountAnalysis['hourly_limit']) ?></div>
                                </div>
                                <div class="hint-box">
                                    <div class="small muted">Cặp mốc quá sát</div>
                                    <strong><?= e((string) ($accountAnalysis['conflict_pairs'] ?? 0)) ?></strong>
                                </div>
                            </div>

                            <?php if (!empty($accountAnalysis['message'])): ?>
                                <div class="small muted"><?= e((string) $accountAnalysis['message']) ?></div>
                            <?php endif; ?>
                            <div class="small muted">Giới hạn 24 giờ: <?= ($accountAnalysis['daily_limit'] ?? null) === null ? 'Không giới hạn' : e((string) $accountAnalysis['daily_limit']) ?> · Gap policy: <?= e((string) ($accountAnalysis['policy_min_gap_minutes'] ?? 8)) ?> phút</div>
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
                            <label>Nhóm Telegram</label>
                            <div class="schedule-group-picker" data-schedule-group>
                                <div class="schedule-group-picker-head" data-schedule-group-head hidden>
                                    <span class="small muted" data-schedule-group-summary>Chọn tài khoản trước</span>
                                    <div class="inline-actions">
                                        <button class="button secondary sm" type="button" data-schedule-select-all-groups>Chọn tất cả</button>
                                        <button class="button secondary sm" type="button" data-schedule-clear-groups>Bỏ chọn</button>
                                    </div>
                                </div>
                                <div class="schedule-group-options" data-schedule-group-options>
                                    <?php foreach ($groups as $group): ?>
                                        <?php
                                        $groupMeta = 'Peer: ' . (string) $group['peer_identifier'];
                                        if (!empty($group['topic_title'])) {
                                            $groupMeta = 'Topic: ' . (string) $group['topic_title'];
                                        } elseif (!empty($group['topic_id'])) {
                                            $groupMeta = 'Topic ID: ' . (string) $group['topic_id'];
                                        }
                                        ?>
                                        <label class="schedule-group-option" data-group-account-id="<?= e((string) $group['telegram_account_id']) ?>" hidden>
                                            <input type="checkbox" name="telegram_group_ids[]" value="<?= e((string) $group['id']) ?>">
                                            <span>
                                                <strong><?= e((string) $group['title']) ?></strong>
                                                <small><?= e($groupMeta) ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="schedule-group-empty" data-schedule-group-empty>Chọn tài khoản trước để hiển thị nhóm.</div>
                                </div>
                            </div>
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
                            <input class="input" id="schedule_modal_interval_minutes" type="number" name="interval_minutes" min="1" max="59" value="15" data-schedule-interval-minutes>
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

    async function fetchScheduleDocument(url = window.location.href) {
        const response = await fetch(url, {
            headers: {
                'Accept': 'text/html,application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
            },
            cache: 'no-store',
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Không thể đồng bộ lại dữ liệu lịch gửi.');
        }

        return new DOMParser().parseFromString(await response.text(), 'text/html');
    }

    function readScheduleRecordsFromDocument(nextDocument) {
        const source = nextDocument.querySelector('[data-schedule-records]');

        if (!source) {
            return {};
        }

        try {
            return JSON.parse(source.textContent || '{}');
        } catch (error) {
            return {};
        }
    }

    function replaceScheduleSupportRegions(nextDocument) {
        const currentPagination = document.querySelector('[data-live-region="schedules-shell"] .listing-pagination');
        const nextPagination = nextDocument.querySelector('[data-live-region="schedules-shell"] .listing-pagination');

        if (currentPagination && nextPagination) {
            currentPagination.replaceWith(document.importNode(nextPagination, true));
        }

        const currentAnalysis = document.querySelector('[data-schedule-account-analysis]');
        const nextAnalysis = nextDocument.querySelector('[data-schedule-account-analysis]');

        if (currentAnalysis && nextAnalysis) {
            currentAnalysis.replaceWith(document.importNode(nextAnalysis, true));
        } else if (currentAnalysis && !nextAnalysis) {
            currentAnalysis.remove();
        } else if (!currentAnalysis && nextAnalysis) {
            document.querySelector('[data-live-region="schedules-shell"]')?.appendChild(document.importNode(nextAnalysis, true));
        }
    }

    async function syncScheduleRows(changedScheduleId = '') {
        const nextDocument = await fetchScheduleDocument(window.location.href);
        const currentBody = document.querySelector('[data-schedule-rows]');
        const nextBody = nextDocument.querySelector('[data-schedule-rows]');

        if (!currentBody || !nextBody) {
            throw new Error('Không tìm thấy danh sách lịch gửi để đồng bộ.');
        }

        const desiredRows = Array.from(nextBody.querySelectorAll('[data-schedule-row]'));
        const desiredIds = new Set(desiredRows.map((row) => String(row.getAttribute('data-schedule-row') || '')));

        currentBody.querySelectorAll('[data-schedule-row]').forEach((row) => {
            const rowId = String(row.getAttribute('data-schedule-row') || '');

            if (!desiredIds.has(rowId)) {
                row.remove();
            }
        });

        currentBody.querySelectorAll('tr:not([data-schedule-row])').forEach((row) => row.remove());

        for (const sourceRow of desiredRows) {
            const rowId = String(sourceRow.getAttribute('data-schedule-row') || '');
            let currentRow = currentBody.querySelector(`[data-schedule-row="${CSS.escape(rowId)}"]`);

            if (!currentRow || rowId === String(changedScheduleId || '')) {
                const freshRow = document.importNode(sourceRow, true);

                if (currentRow) {
                    currentRow.replaceWith(freshRow);
                }

                currentRow = freshRow;
            }

            currentBody.appendChild(currentRow);
        }

        if (desiredRows.length === 0) {
            const emptyRow = nextBody.querySelector('tr');

            if (emptyRow) {
                currentBody.appendChild(document.importNode(emptyRow, true));
            }
        }

        scheduleRecords = readScheduleRecordsFromDocument(nextDocument);
        replaceScheduleSupportRegions(nextDocument);
    }

    async function navigateScheduleList(url, pushState = true) {
        const shell = document.querySelector('[data-live-region="schedules-shell"]');

        if (!shell) {
            window.location.href = url;
            return;
        }

        shell.setAttribute('aria-busy', 'true');

        try {
            await window.TeleSenderApp.refreshRegions(['[data-live-region="schedules-shell"]'], { url });

            if (pushState) {
                window.history.pushState({}, '', url);
            }
        } catch (error) {
            window.location.href = url;
        } finally {
            document.querySelector('[data-live-region="schedules-shell"]')?.removeAttribute('aria-busy');
        }
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
        const groupHead = wrapper.querySelector('[data-schedule-group-head]');
        const groupSummary = wrapper.querySelector('[data-schedule-group-summary]');
        const groupEmpty = wrapper.querySelector('[data-schedule-group-empty]');
        const selectAllGroupsButton = wrapper.querySelector('[data-schedule-select-all-groups]');
        const clearGroupsButton = wrapper.querySelector('[data-schedule-clear-groups]');
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
            || !groupHead || !groupSummary || !groupEmpty || !selectAllGroupsButton || !clearGroupsButton
            || !templateInput || !submitButton || !dailyTimesList || !weeklyTimesList || !previewCron
            || !previewSummary || !previewRuns || !previewRiskBadge || !previewRiskMessage || !copyPreviewCronButton
        ) {
            return;
        }

        const allGroupOptions = Array.from(groupInput.querySelectorAll('[data-group-account-id]')).map((element) => ({
            element,
            input: element.querySelector('input[type="checkbox"]'),
            accountId: element.getAttribute('data-group-account-id') || '',
        }));

        function toggleScheduleSections() {
            const activeType = typeInput.value;
            wrapper.querySelectorAll('[data-schedule-section]').forEach((section) => {
                section.style.display = section.getAttribute('data-schedule-section') === activeType ? '' : 'none';
            });
        }

        function selectedGroupIds() {
            return allGroupOptions
                .filter((option) => option.input?.checked && !option.element.hidden)
                .map((option) => String(option.input.value));
        }

        function updateGroupSummary() {
            const selectedAccountId = String(accountInput.value || '');
            const matchingGroups = selectedAccountId === ''
                ? []
                : allGroupOptions.filter((option) => option.accountId === selectedAccountId);
            const selectedCount = selectedGroupIds().length;

            if (selectedAccountId === '') {
                groupSummary.textContent = 'Chọn tài khoản trước';
            } else if (matchingGroups.length === 0) {
                groupSummary.textContent = 'Tài khoản này chưa có nhóm đã lưu';
            } else if (selectedCount === 0) {
                groupSummary.textContent = `Có ${matchingGroups.length} nhóm. Chọn ít nhất một nhóm.`;
            } else {
                groupSummary.textContent = `Đã chọn ${selectedCount}/${matchingGroups.length} nhóm cho lịch này.`;
            }

            groupEmpty.hidden = matchingGroups.length > 0;
            groupHead.hidden = matchingGroups.length === 0;
            groupEmpty.textContent = selectedAccountId === ''
                ? 'Chọn tài khoản trước để hiển thị nhóm.'
                : 'Tài khoản này chưa có nhóm đã lưu.';
            selectAllGroupsButton.disabled = matchingGroups.length === 0;
            clearGroupsButton.disabled = selectedCount === 0;
        }

        function syncGroupOptions(preferredGroupIds = null) {
            const selectedAccountId = String(accountInput.value || '');
            const preferredIds = preferredGroupIds === null
                ? selectedGroupIds()
                : (Array.isArray(preferredGroupIds) ? preferredGroupIds : [preferredGroupIds]).map(String);
            const preferredSet = new Set(preferredIds);

            allGroupOptions.forEach((option) => {
                const matchesAccount = selectedAccountId !== '' && option.accountId === selectedAccountId;
                option.element.hidden = !matchesAccount;

                if (option.input) {
                    option.input.checked = matchesAccount && preferredSet.has(String(option.input.value));
                }
            });

            updateGroupSummary();
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
        syncGroupOptions(record ? (record.telegram_group_ids || [record.telegram_group_id]) : []);
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
            syncGroupOptions([]);
            triggerPreview();
        });

        groupInput.addEventListener('change', () => {
            updateGroupSummary();
            triggerPreview();
        });

        selectAllGroupsButton.addEventListener('click', () => {
            allGroupOptions.forEach((option) => {
                if (!option.element.hidden && option.input) {
                    option.input.checked = true;
                }
            });
            updateGroupSummary();
            triggerPreview();
        });

        clearGroupsButton.addEventListener('click', () => {
            allGroupOptions.forEach((option) => {
                if (option.input) {
                    option.input.checked = false;
                }
            });
            updateGroupSummary();
            triggerPreview();
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

            if (selectedGroupIds().length === 0) {
                window.TeleSenderApp.showFlash('error', 'Hãy chọn ít nhất một nhóm Telegram cho lịch này.');
                groupInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            await window.TeleSenderApp.submitAjaxForm(form, {
                reloadOnSuccess: false,
                async onSuccess(payload) {
                    window.TeleSenderCrudModal.close();

                    try {
                        await syncScheduleRows(String(payload.schedule_id || scheduleId || ''));
                    } catch (error) {
                        window.TeleSenderApp.showFlash('error', error.message || 'Đã lưu lịch nhưng chưa đồng bộ được danh sách hiển thị.');
                    }
                },
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

    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement
            ? event.target.closest('[data-schedule-toggle-form]')
            : null;

        if (!form) {
            return;
        }

        event.preventDefault();

        const scheduleId = String(form.querySelector('input[name="id"]')?.value || '');
        const row = document.querySelector(`[data-schedule-row="${CSS.escape(scheduleId)}"]`);

        await window.TeleSenderApp.submitAjaxForm(form, {
            reloadOnSuccess: false,
            onSuccess(payload) {
                const schedule = payload.schedule || {};

                if (!row || String(schedule.id || '') !== scheduleId) {
                    return;
                }

                const status = String(schedule.status || '');
                const statusBadge = row.querySelector('[data-schedule-status-badge]');
                const toggleButton = row.querySelector('[data-schedule-toggle-button]');
                const nextRun = row.querySelector('[data-schedule-next-run]');

                if (statusBadge) {
                    statusBadge.className = `badge ${status === 'active' ? 'success' : 'warning'}`;
                    statusBadge.textContent = schedule.status_label || (status === 'active' ? 'Đang chạy' : 'Tạm dừng');
                }

                if (toggleButton) {
                    const nextActionLabel = status === 'active' ? 'Tạm dừng' : 'Tiếp tục';
                    toggleButton.textContent = nextActionLabel;
                    toggleButton.dataset.originalLabel = nextActionLabel;
                }

                if (nextRun && schedule.next_run_at_label) {
                    nextRun.textContent = schedule.next_run_at_label;
                }

                if (schedule.queue_cleared) {
                    row.querySelector('[data-schedule-error]')?.remove();
                    row.querySelector('[data-schedule-queue-notice]')?.remove();
                }

                if (scheduleRecords[scheduleId]) {
                    scheduleRecords[scheduleId].status = status;
                }
            },
        });
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement
            ? event.target.closest('[data-schedule-delete-form]')
            : null;

        if (!form) {
            return;
        }

        event.preventDefault();

        const scheduleId = String(form.querySelector('input[name="id"]')?.value || '');
        const row = document.querySelector(`[data-schedule-row="${CSS.escape(scheduleId)}"]`);

        await window.TeleSenderApp.submitAjaxForm(form, {
            reloadOnSuccess: false,
            async onSuccess() {
                row?.remove();

                try {
                    await syncScheduleRows(scheduleId);
                } catch (error) {
                    window.TeleSenderApp.showFlash('error', error.message || 'Đã xóa lịch nhưng chưa đồng bộ được danh sách hiển thị.');
                }
            },
        });
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement
            ? event.target.closest('[data-schedule-send-now-form]')
            : null;

        if (!form) {
            return;
        }

        event.preventDefault();

        const forceInput = form.querySelector('[data-force-send-input]');
        const riskMessage = form.getAttribute('data-risk-message') || '';

        if (forceInput instanceof HTMLInputElement) {
            forceInput.value = '0';
        }

        if (form.getAttribute('data-ajax-risk-confirm') === '1' && riskMessage !== '') {
            const confirmed = await window.TeleSenderApp.requestModal('confirm', {
                title: form.getAttribute('data-ajax-confirm-title') || 'Xác nhận gửi ngay',
                message: riskMessage + '\n\nNếu tiếp tục, hệ thống sẽ ép gửi ngay và chỉ bỏ qua giới hạn nội bộ hoặc giãn cách mềm ở lần bấm này.',
                confirmText: form.getAttribute('data-ajax-confirm-text') || 'Vẫn gửi ngay',
                cancelText: 'Hủy',
                confirmClass: 'danger',
            });

            if (!confirmed) {
                return;
            }

            if (forceInput instanceof HTMLInputElement) {
                forceInput.value = '1';
            }
        }

        const scheduleId = String(form.querySelector('input[name="id"]')?.value || '');
        const syncChangedRow = async () => {
            try {
                await syncScheduleRows(scheduleId);
            } catch (error) {
                window.TeleSenderApp.showFlash('error', error.message || 'Đã gửi xong nhưng chưa đồng bộ được dòng hiển thị.');
            }
        };

        await window.TeleSenderApp.submitAjaxForm(form, {
            reloadOnSuccess: false,
            onSuccess: syncChangedRow,
            async onError(payload) {
                await syncChangedRow();
                window.TeleSenderApp.showFlash('error', payload.message || 'Không thể gửi lịch này ngay lúc này.');
            },
        });
    });

    function scheduleUrlFromForm(form) {
        const targetUrl = new URL(form.action || window.location.href, window.location.href);

        for (const [key, value] of new FormData(form).entries()) {
            const normalizedValue = String(value || '').trim();

            if (normalizedValue === '') {
                targetUrl.searchParams.delete(key);
            } else {
                targetUrl.searchParams.set(key, normalizedValue);
            }
        }

        return targetUrl.toString();
    }

    document.addEventListener('submit', (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;

        if (!form || (!form.matches('[data-schedule-filter-form]') && !form.closest('[data-live-region="schedules-shell"] .listing-pagination'))) {
            return;
        }

        event.preventDefault();
        void navigateScheduleList(scheduleUrlFromForm(form));
    });

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element
            ? event.target.closest('[data-live-region="schedules-shell"] .pagination-link, [data-schedule-clear-filters]')
            : null;

        if (!link || link.classList.contains('disabled') || link.getAttribute('href') === '#') {
            return;
        }

        event.preventDefault();
        void navigateScheduleList(link.href);
    });

    document.addEventListener('change', (event) => {
        const select = event.target instanceof HTMLSelectElement ? event.target : null;

        if (!select || !select.matches('[data-live-region="schedules-shell"] .pagination-select')) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (select.form) {
            void navigateScheduleList(scheduleUrlFromForm(select.form));
        }
    }, true);

    window.addEventListener('popstate', () => {
        void navigateScheduleList(window.location.href, false);
    });

    document.addEventListener('app:regions:refreshed', () => {
        scheduleRecords = window.TeleSenderApp.readJsonScript('[data-schedule-records]', {});
    });
})();
});
</script>
