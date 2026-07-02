<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Tổng quan</h1>
    </div>

    <div class="grid grid-4">
        <div class="card stat-card">
            <span class="stat-label">Tài khoản Telegram</span>
            <strong class="stat-value"><?= e((string) $stats['accounts']) ?></strong>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Nhóm Telegram</span>
            <strong class="stat-value"><?= e((string) $stats['groups']) ?></strong>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Nhãn tin nhắn</span>
            <strong class="stat-value"><?= e((string) $stats['labels']) ?></strong>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Mẫu tin nhắn</span>
            <strong class="stat-value"><?= e((string) $stats['templates']) ?></strong>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Lịch gửi</span>
            <strong class="stat-value"><?= e((string) $stats['schedules']) ?></strong>
        </div>
    </div>

    <div class="grid grid-2">
        <section class="card hero">
            <h2 class="section-title">Endpoint cron</h2>
            <div class="endpoint mono"><?= e(url('/cron/run?token=YOUR_CRON_TOKEN')) ?></div>
        </section>
        <section class="card hero">
            <h2 class="section-title">Endpoint migrate</h2>
            <div class="endpoint mono"><?= e(url('/system/migrate?token=YOUR_MIGRATE_TOKEN&version=6')) ?></div>
        </section>
    </div>

    <div class="grid grid-2">
        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Lịch chạy gần nhất</h2>
            </div>
            <div class="panel-body list">
                <?php foreach (array_slice($nextSchedules, 0, 6) as $schedule): ?>
                    <article class="list-item">
                        <strong><?= e($schedule['template_name']) ?></strong>
                        <div class="small muted"><?= e($schedule['group_title']) ?> · <?= e($schedule['account_name']) ?></div>
                        <div class="small mono"><?= e($schedule['cron_expression']) ?> · <?= e($schedule['timezone']) ?></div>
                        <div class="small">Lần chạy tới: <strong><?= e(fmt_datetime($schedule['next_run_at'])) ?></strong></div>
                    </article>
                <?php endforeach; ?>
                <?php if ($nextSchedules === []): ?>
                    <div class="muted">Chưa có lịch gửi nào. Hãy tạo tài khoản, nhóm, mẫu tin nhắn rồi thêm lịch gửi.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Nhật ký gửi tin</h2>
            </div>
            <div class="panel-body list">
                <?php foreach ($recentLogs as $log): ?>
                    <?php $statusLabel = $log['status'] === 'success' ? 'Thành công' : 'Thất bại'; ?>
                    <article class="list-item">
                        <div class="inline-actions">
                            <span class="badge <?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($statusLabel) ?></span>
                            <?php if (!empty($log['label_name'])): ?>
                                <span class="badge"><?= e($log['label_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <strong><?= e($log['template_name'] ?? 'Không xác định') ?></strong>
                        <div class="small muted"><?= e($log['group_title'] ?? 'Nhóm không xác định') ?> · <?= e(fmt_datetime($log['sent_at'])) ?></div>
                        <?php if (!empty($log['error_message'])): ?>
                            <div class="small" style="color:#b91c1c;"><?= e($log['error_message']) ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if ($recentLogs === []): ?>
                    <div class="muted">Chưa có log nào được ghi nhận.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>
