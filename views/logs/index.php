<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Dispatch Logs</h1>
            <p class="page-subtitle">Lịch sử gửi tin theo từng account, group, template và label. Đây là nơi chính để audit, truy lỗi và kiểm tra nội dung đã được đẩy đi.</p>
        </div>
        <span class="badge info">Audit Trail</span>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2 class="panel-title">Lịch sử gửi tin</h2>
        </div>
        <div class="panel-body table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Template / Label</th>
                        <th>Account / Group</th>
                        <th>Sent At</th>
                        <th>Preview</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><span class="badge <?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($log['status']) ?></span></td>
                        <td>
                            <strong><?= e($log['template_name'] ?? 'N/A') ?></strong>
                            <div class="small muted"><?= e($log['label_name'] ?? 'No label') ?></div>
                        </td>
                        <td>
                            <div><?= e($log['account_name'] ?? 'N/A') ?></div>
                            <div class="small muted"><?= e($log['group_title'] ?? 'N/A') ?></div>
                            <div class="small muted">Topic đích: <?= e($log['target_topic_label'] ?? 'General') ?></div>
                        </td>
                        <td>
                            <div><?= e(fmt_datetime($log['sent_at'])) ?></div>
                            <div class="small mono"><?= e($log['request_id']) ?></div>
                        </td>
                        <td><?= nl2br(e($log['message_preview'])) ?></td>
                        <td>
                            <?php if (!empty($log['error_message'])): ?>
                                <div class="small" style="color:#b91c1c;"><?= e($log['error_message']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($log['actual_topic_label'])): ?>
                                <div class="small" style="margin-bottom:6px;color:<?= !empty($log['topic_mismatch']) ? '#b91c1c' : '#0f766e' ?>;">
                                    Vào topic thực tế: <?= e($log['actual_topic_label']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($log['response_payload'])): ?>
                                <div class="small mono"><?= e(mb_substr($log['response_payload'], 0, 240)) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($logs === []): ?>
                    <tr><td colspan="6" class="muted">Chưa có log nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
