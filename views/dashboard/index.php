<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Tổng quan</h1>
    </div>

    <?php if (!empty($isSuperAdmin)): ?>
        <div class="grid grid-4">
            <div class="card stat-card">
                <span class="stat-label">Tổng admin con</span>
                <strong class="stat-value"><?= e((string) ($stats['admins_total'] ?? 0)) ?></strong>
            </div>
            <div class="card stat-card">
                <span class="stat-label">Admin đang hoạt động</span>
                <strong class="stat-value"><?= e((string) ($stats['admins_active'] ?? 0)) ?></strong>
            </div>
            <div class="card stat-card">
                <span class="stat-label">Admin hết hạn</span>
                <strong class="stat-value"><?= e((string) ($stats['admins_expired'] ?? 0)) ?></strong>
                <span class="small muted"><?= e((string) ($stats['admins_inactive'] ?? 0)) ?> admin đang bị khóa thủ công</span>
            </div>
            <div class="card stat-card">
                <span class="stat-label">Telegram account</span>
                <strong class="stat-value"><?= e((string) ($stats['accounts'] ?? 0)) ?></strong>
            </div>
            <div class="card stat-card">
                <span class="stat-label">Nhóm Telegram</span>
                <strong class="stat-value"><?= e((string) ($stats['groups'] ?? 0)) ?></strong>
            </div>
            <div class="card stat-card">
                <span class="stat-label">Mẫu tin nhắn</span>
                <strong class="stat-value"><?= e((string) ($stats['templates'] ?? 0)) ?></strong>
            </div>
            <div class="card stat-card">
                <span class="stat-label">Schedule</span>
                <strong class="stat-value"><?= e((string) ($stats['schedules'] ?? 0)) ?></strong>
            </div>
        </div>

        <div class="grid grid-2">
            <section class="card dashboard-system-card">
                <div class="dashboard-card-head">
                    <h2 class="section-title">Cron hệ thống</h2>
                </div>
                <div class="dashboard-kv-list">
                    <div class="dashboard-kv-item">
                        <span class="dashboard-kv-label">Endpoint</span>
                        <div class="dashboard-code mono"><?= e((string) ($systemEndpoints['cron'] ?? '')) ?></div>
                    </div>
                </div>
            </section>

            <section class="card dashboard-system-card">
                <div class="dashboard-card-head">
                    <h2 class="section-title">Migrate hệ thống</h2>
                </div>
                <div class="dashboard-kv-list">
                    <div class="dashboard-kv-item">
                        <span class="dashboard-kv-label">Endpoint base</span>
                        <div class="dashboard-code mono"><?= e((string) ($systemEndpoints['migrate_base'] ?? '')) ?></div>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid grid-2">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Danh sách migration</h2>
                    <span class="small muted"><?= e((string) count((array) ($migrationReport['items'] ?? []))) ?> migration</span>
                </div>
                <div class="panel-body dashboard-migration-list">
                    <?php foreach (($migrationReport['items'] ?? []) as $migration): ?>
                        <article class="dashboard-migration-item">
                            <div class="dashboard-migration-head">
                                <div class="inline-actions">
                                    <span class="badge info mono">v<?= e((string) $migration['version']) ?></span>
                                    <strong><?= e((string) $migration['name']) ?></strong>
                                </div>
                                <div class="inline-actions">
                                    <?php if (!empty($migration['legacy_versions'])): ?>
                                        <span class="badge mono">Legacy: <?= e(implode(', ', array_map('strval', (array) $migration['legacy_versions']))) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="dashboard-migration-meta">
                                <span class="badge <?= !empty($migration['executed']) ? 'success' : 'warning' ?>">
                                    <?= !empty($migration['executed']) ? 'Đã chạy' : 'Chưa chạy' ?>
                                </span>
                            </div>
                            <div class="small muted dashboard-migration-time">
                                <?= !empty($migration['executed_at']) ? 'Thời điểm chạy: ' . e(fmt_datetime((string) $migration['executed_at'])) : 'Chưa được thực thi trên database hiện tại.' ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($migrationReport['items'])): ?>
                        <div class="muted">Không tìm thấy migration nào.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Nhật ký gần đây của super admin</h2>
                </div>
                <div class="panel-body dashboard-compact-list">
                    <?php foreach (($recentLogs ?? []) as $log): ?>
                        <?php $statusLabel = $log['status'] === 'success' ? 'Thành công' : 'Thất bại'; ?>
                        <article class="dashboard-compact-item">
                            <div class="dashboard-compact-head">
                                <div class="inline-actions">
                                    <span class="badge <?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($statusLabel) ?></span>
                                    <strong><?= e($log['template_name'] ?? 'Không xác định') ?></strong>
                                </div>
                                <span class="small muted"><?= e(fmt_datetime($log['sent_at'])) ?></span>
                            </div>
                            <div class="small muted">
                                <?= e($log['group_title'] ?? 'Nhóm không xác định') ?>
                                <?php if (!empty($log['label_name'])): ?>
                                    · <?= e($log['label_name']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($log['error_message'])): ?>
                                <div class="small dashboard-inline-error"><?= e($log['error_message']) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($recentLogs)): ?>
                        <div class="muted">Chưa có log nào được ghi nhận.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php else: ?>
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
            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Lịch chạy gần nhất</h2>
                </div>
                <div class="panel-body list">
                    <?php foreach (array_slice($nextSchedules, 0, 6) as $schedule): ?>
                        <article class="list-item">
                            <strong><?= e($schedule['template_name']) ?></strong>
                            <div class="small muted"><?= e($schedule['group_title']) ?> · <?= e($schedule['account_name']) ?></div>
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
    <?php endif; ?>
</section>
