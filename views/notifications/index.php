<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Thông báo</h1>
            <p class="page-subtitle">Cảnh báo Telegram, circuit breaker và các sự kiện an toàn cần chú ý.</p>
        </div>
        <form method="post" action="<?= e(url('/notifications/read-all')) ?>" data-ajax-form>
            <?= csrf_field() ?>
            <button class="button secondary" type="submit">Đánh dấu tất cả đã đọc</button>
        </form>
    </div>

    <section class="card">
        <div class="list">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $severity = (string) ($notification['severity'] ?? 'warning');
                $badge = $severity === 'critical' ? 'danger' : ($severity === 'info' ? 'info' : 'warning');
                $isUnread = empty($notification['read_at']);
                ?>
                <div class="list-item" style="<?= $isUnread ? 'border-color:rgba(245,158,11,.55);' : '' ?>">
                    <div class="builder-block-head">
                        <div>
                            <span class="badge <?= e($badge) ?>"><?= e($severity === 'critical' ? 'Khẩn cấp' : 'Cảnh báo') ?></span>
                            <?php if ($isUnread): ?><span class="badge info">Chưa đọc</span><?php endif; ?>
                            <strong style="margin-left:8px;"><?= e((string) $notification['title']) ?></strong>
                        </div>
                        <span class="small muted"><?= e(fmt_datetime((string) $notification['created_at'])) ?></span>
                    </div>
                    <div style="margin-top:8px;"><?= e((string) $notification['message']) ?></div>
                    <?php if (!empty($notification['account_name'])): ?>
                        <div class="small muted" style="margin-top:6px;">Account: <?= e((string) $notification['account_name']) ?></div>
                    <?php endif; ?>
                    <?php if ($isUnread): ?>
                        <form method="post" action="<?= e(url('/notifications/read')) ?>" data-ajax-form style="margin-top:10px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="notification_id" value="<?= e((string) $notification['id']) ?>">
                            <button class="button secondary sm" type="submit">Đánh dấu đã đọc</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($notifications === []): ?><div class="muted">Chưa có thông báo.</div><?php endif; ?>
        </div>
    </section>

    <?php $perPageOptions = [10, 15, 20, 30, 50]; ?>
    <?php require base_path('views/partials/pagination.php'); ?>
</section>
