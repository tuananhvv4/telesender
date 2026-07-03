<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Nhật ký gửi tin</h1>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Lịch sử gửi tin</h2>
            <form class="toolbar-form" method="get" action="<?= e(url('/logs')) ?>">
                <?php if ((int) request()->query('per_page', 0) > 0): ?>
                    <input type="hidden" name="per_page" value="<?= e((string) request()->query('per_page')) ?>">
                <?php endif; ?>
                <div class="toolbar-search">
                    <input class="input" type="text" name="q" value="<?= e($searchQuery ?? '') ?>" placeholder="Tìm theo mẫu tin, tài khoản, nhóm, request id, lỗi...">
                    <button class="button secondary" type="submit">Lọc</button>
                    <?php if (($searchQuery ?? '') !== ''): ?>
                        <a class="button secondary" href="<?= e(url('/logs')) ?>">Xóa lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="panel-body logs-feed">
            <?php foreach ($logs as $log): ?>
                <?php
                $statusLabel = $log['status'] === 'success' ? 'Thành công' : 'Thất bại';
                $messagePreview = trim((string) ($log['message_preview'] ?? ''));
                ?>
                <article class="log-card">
                    <div class="log-card-head">
                        <div class="inline-actions">
                            <span class="badge <?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($statusLabel) ?></span>
                            <span class="log-meta-pill"><?= e(fmt_datetime($log['sent_at'])) ?></span>
                            <span class="log-meta-pill mono"><?= e($log['request_id']) ?></span>
                        </div>
                        <?php if (!empty($log['label_name'])): ?>
                            <span class="badge info"><?= e($log['label_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($log['error_message'])): ?>
                        <div class="log-alert danger"><?= e($log['error_message']) ?></div>
                    <?php endif; ?>

                    <div class="log-card-grid">
                        <section class="log-card-section log-card-main">
                            <div class="log-section-label">Mẫu tin</div>
                            <h3 class="log-title"><?= e($log['template_name'] ?? 'Không xác định') ?></h3>
                            <div class="log-preview">
                                <?= nl2br(e($messagePreview !== '' ? $messagePreview : 'Không có nội dung xem trước.')) ?>
                            </div>
                        </section>

                        <section class="log-card-section log-card-target">
                            <div class="log-section-label">Đích gửi</div>
                            <div class="log-kv">
                                <span class="log-kv-label">Tài khoản</span>
                                <strong><?= e($log['account_name'] ?? 'Không xác định') ?></strong>
                            </div>
                            <div class="log-kv">
                                <span class="log-kv-label">Nhóm</span>
                                <span><?= e($log['group_title'] ?? 'Không xác định') ?></span>
                            </div>
                            <div class="log-kv">
                                <span class="log-kv-label">Topic đích</span>
                                <span><?= e($log['target_topic_label'] ?? 'Topic chung') ?></span>
                            </div>
                            <?php if (!empty($log['actual_topic_label'])): ?>
                                <div class="log-kv">
                                <span class="log-kv-label">Topic thực tế</span>
                                <span class="<?= !empty($log['topic_mismatch']) ? 'log-mismatch' : 'log-match' ?>">
                                    <?= e($log['actual_topic_label']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        </section>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($logs === []): ?>
                <div class="muted">Chưa có log nào.</div>
            <?php endif; ?>
        </div>
        <div class="panel-body" style="padding-top: 0;">
            <?php $perPageOptions = [20, 50, 100, 200]; ?>
            <?php require base_path('views/partials/pagination.php'); ?>
        </div>
    </section>
</section>
