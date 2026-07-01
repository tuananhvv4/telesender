<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Bộ điều khiển cho hệ thống gửi tin tự động vào nhóm Telegram bằng tài khoản cá nhân, hỗ trợ multi-user, multi-account và log lịch sử theo từng template/label.</p>
        </div>
        <span class="badge info">MVC + PHP + MySQL</span>
    </div>

    <div class="grid grid-4">
        <div class="card stat-card">
            <span class="stat-label">Telegram Accounts</span>
            <strong class="stat-value"><?= e((string) $stats['accounts']) ?></strong>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Telegram Groups</span>
            <strong class="stat-value"><?= e((string) $stats['groups']) ?></strong>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Message Labels</span>
            <strong class="stat-value"><?= e((string) $stats['labels']) ?></strong>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Message Templates</span>
            <strong class="stat-value"><?= e((string) $stats['templates']) ?></strong>
        </div>
        <div class="card stat-card">
            <span class="stat-label">Schedules</span>
            <strong class="stat-value"><?= e((string) $stats['schedules']) ?></strong>
        </div>
    </div>

    <div class="grid grid-2">
        <section class="card hero">
            <h2 class="section-title">Cron Endpoint</h2>
            <p class="section-copy">Đặt cron ngoài hệ thống bắn vào endpoint này mỗi phút để app tự dò các lịch tới hạn và gửi tin.</p>
            <div class="endpoint mono"><?= e(url('/cron/run?token=YOUR_CRON_TOKEN')) ?></div>
        </section>
        <section class="card hero">
            <h2 class="section-title">Migration Endpoint</h2>
            <p class="section-copy">Sau khi deploy bản mới, chỉ cần gọi endpoint với version mục tiêu để áp dụng migration theo thứ tự.</p>
            <div class="endpoint mono"><?= e(url('/system/migrate?token=YOUR_MIGRATE_TOKEN&version=5')) ?></div>
        </section>
    </div>

    <div class="grid grid-2">
        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Lịch chạy gần nhất</h2>
                <p class="panel-copy">Các schedule đang được quản lý trong tài khoản hiện tại.</p>
            </div>
            <div class="panel-body list">
                <?php foreach (array_slice($nextSchedules, 0, 6) as $schedule): ?>
                    <article class="list-item">
                        <strong><?= e($schedule['template_name']) ?></strong>
                        <div class="small muted"><?= e($schedule['group_title']) ?> · <?= e($schedule['account_name']) ?></div>
                        <div class="small mono"><?= e($schedule['cron_expression']) ?> · <?= e($schedule['timezone']) ?></div>
                        <div class="small">Next run: <strong><?= e(fmt_datetime($schedule['next_run_at'])) ?></strong></div>
                    </article>
                <?php endforeach; ?>
                <?php if ($nextSchedules === []): ?>
                    <div class="muted">Chưa có schedule nào. Hãy tạo account, group, template rồi thêm lịch gửi.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Dispatch Logs</h2>
                <p class="panel-copy">Kết quả gửi gần đây theo từng schedule.</p>
            </div>
            <div class="panel-body list">
                <?php foreach ($recentLogs as $log): ?>
                    <article class="list-item">
                        <div class="inline-actions">
                            <span class="badge <?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($log['status']) ?></span>
                            <?php if (!empty($log['label_name'])): ?>
                                <span class="badge"><?= e($log['label_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <strong><?= e($log['template_name'] ?? 'N/A') ?></strong>
                        <div class="small muted"><?= e($log['group_title'] ?? 'Unknown group') ?> · <?= e(fmt_datetime($log['sent_at'])) ?></div>
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
