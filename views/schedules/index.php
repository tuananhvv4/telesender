<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Schedules</h1>
            <p class="page-subtitle">Mỗi lịch gửi gắn chặt với account, group và message template. App sẽ tự tính `next_run_at` theo cron expression và gọi Telegram khi endpoint cron được bắn.</p>
        </div>
        <span class="badge info">Cron Driven</span>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h2 class="section-title"><?= $editSchedule ? 'Cập nhật schedule' : 'Tạo schedule mới' ?></h2>
            <div class="field" style="margin: 16px 0 18px;">
                <label for="schedule_preset">Preset schedule</label>
                <select class="select" id="schedule_preset">
                    <option value="">Chọn preset để điền nhanh cron</option>
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
            <form class="form-grid" method="post" action="<?= e(url($editSchedule ? '/schedules/update' : '/schedules')) ?>">
                <?= csrf_field() ?>
                <?php if ($editSchedule): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editSchedule['id']) ?>">
                <?php endif; ?>
                <div class="field">
                    <label for="telegram_account_id">Telegram account</label>
                    <select class="select" id="telegram_account_id" name="telegram_account_id" required>
                        <option value="">Chọn account</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= e((string) $account['id']) ?>" <?= (string) ($editSchedule['telegram_account_id'] ?? '') === (string) $account['id'] ? 'selected' : '' ?>>
                                <?= e($account['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="telegram_group_id">Telegram group</label>
                    <select class="select" id="telegram_group_id" name="telegram_group_id" required>
                        <option value="">Chọn group</option>
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
                <div class="field">
                    <label for="message_template_id">Message template</label>
                    <select class="select" id="message_template_id" name="message_template_id" required>
                        <option value="">Chọn template</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= e((string) $template['id']) ?>" <?= (string) ($editSchedule['message_template_id'] ?? '') === (string) $template['id'] ? 'selected' : '' ?>>
                                <?= e($template['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="timezone">Timezone</label>
                    <input class="input" id="timezone" type="text" name="timezone" value="<?= e($editSchedule['timezone'] ?? $defaultTimezone) ?>" required>
                </div>
                <div class="field">
                    <label for="cron_expression">Cron expression</label>
                    <input class="input mono" id="cron_expression" type="text" name="cron_expression" value="<?= e($editSchedule['cron_expression'] ?? '0 8 * * *') ?>" placeholder="0 8 * * *" required>
                </div>
                <p class="small muted">Ví dụ: `0 */2 * * *` là mỗi 2 giờ 1 lần, `0 8,12,20 * * *` là mỗi ngày lúc 08:00, 12:00, 20:00, `30 8,11,14,17 * * 1-5` là giờ hành chính nhiều mốc.</p>
                <div class="actions">
                    <button class="button primary" type="submit"><?= $editSchedule ? 'Cập nhật schedule' : 'Tạo schedule' ?></button>
                    <?php if ($editSchedule): ?>
                        <a class="button secondary" href="<?= e(url('/schedules')) ?>">Tạo mới</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 class="section-title">Chế độ gửi an toàn</h2>
            <div class="list">
                <div class="list-item">Tối đa <?= e((string) $safetyRules['account_limits']['max_success_per_hour']) ?> lần gửi thành công mỗi giờ cho một account.</div>
                <div class="list-item">Tối đa <?= e((string) $safetyRules['account_limits']['max_success_per_day']) ?> lần gửi thành công mỗi ngày cho một account.</div>
                <div class="list-item">Tự giãn cách tối thiểu <?= e((string) $safetyRules['account_limits']['min_minutes_between_sends']) ?> phút giữa 2 lần gửi của cùng account.</div>
                <div class="list-item">Nếu Telegram trả tín hiệu spam/rate limit, account sẽ tự cooldown khoảng <?= e((string) $safetyRules['account_limits']['spam_cooldown_minutes']) ?> phút.</div>
                <div class="list-item">Các lịch dày hơn <?= e((string) $safetyRules['schedule_limits']['block_runs_per_day']) ?> lần/ngày sẽ bị chặn khi lưu.</div>
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Danh sách schedules</h2>
        </div>
        <div class="panel-body table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Template</th>
                        <th>Account / Group</th>
                        <th>Cron</th>
                        <th>Next Run</th>
                        <th>Trạng thái</th>
                        <th>An toàn</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($schedules as $schedule): ?>
                    <?php
                    $analysis = $scheduleAnalyses[(int) $schedule['id']] ?? ['risk' => 'safe', 'message' => '', 'runs_per_day' => 0, 'min_gap_minutes' => null];
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
                            <?php if (!empty($schedule['last_error'])): ?>
                                <div class="small" style="color:#b91c1c;"><?= e($schedule['last_error']) ?></div>
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
                        <td class="mono">
                            <?= e($schedule['cron_expression']) ?>
                            <div class="small muted"><?= e($schedule['timezone']) ?></div>
                            <div class="small muted"><?= e((string) $analysis['runs_per_day']) ?> lần/ngày · <?= e($analysis['min_gap_minutes'] !== null ? (string) $analysis['min_gap_minutes'] . ' phút/lần' : 'không xác định') ?></div>
                        </td>
                        <td>
                            <div><?= e(fmt_datetime($schedule['next_run_at'])) ?></div>
                            <div class="small muted">Last: <?= e(fmt_datetime($schedule['last_run_at'])) ?></div>
                        </td>
                        <td>
                            <span class="badge <?= $schedule['status'] === 'active' ? 'success' : 'warning' ?>"><?= e($schedule['status']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= e($riskBadgeClass) ?>"><?= e($riskLabel) ?></span>
                            <div class="small muted" style="margin-top: 6px;"><?= e($analysis['message']) ?></div>
                        </td>
                        <td>
                            <div class="inline-actions">
                                <a class="button secondary" href="<?= e(url('/schedules?edit=' . $schedule['id'])) ?>">Sửa</a>
                                <form method="post" action="<?= e(url('/schedules/send-now')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <button class="button secondary" type="submit">Gửi ngay</button>
                                </form>
                                <form method="post" action="<?= e(url('/schedules/toggle')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $schedule['id']) ?>">
                                    <button class="button accent" type="submit"><?= $schedule['status'] === 'active' ? 'Pause' : 'Resume' ?></button>
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
                    <tr><td colspan="7" class="muted">Chưa có schedule nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
<script>
const schedulePresets = <?= json_encode($schedulePresets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const schedulePresetSelect = document.getElementById('schedule_preset');
const timezoneInput = document.getElementById('timezone');
const cronInput = document.getElementById('cron_expression');

function applySchedulePreset(key) {
    const preset = schedulePresets.find((item) => item.key === key);
    if (!preset) {
        return;
    }

    timezoneInput.value = preset.timezone;
    cronInput.value = preset.cron_expression;
}

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
</script>
