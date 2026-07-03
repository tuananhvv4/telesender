<?php

declare(strict_types=1);

$settingsMap = $settingsMap ?? [];
$supportName = trim((string) ($settingsMap['support_contact_name'] ?? ''));
$supportValue = trim((string) ($settingsMap['support_contact_value'] ?? ''));
$supportExtra = trim((string) ($settingsMap['support_contact_extra'] ?? ''));
$supportHref = support_contact_href($supportValue);
$footerText = trim((string) ($settingsMap['footer_text'] ?? ''));
?>
<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Cấu hình hệ thống</h1>
            <p class="page-subtitle">Thiết lập nội dung màn hình hết hạn, thông tin liên hệ và footer chung hiển thị trên toàn hệ thống.</p>
        </div>
    </div>

    <div class="admin-shell">
        <div class="admin-main">
            <section class="card">
                <h2 class="section-title">Nội dung màn hình hết hạn</h2>
                <form class="form-grid" method="post" action="<?= e(url('/admin/settings')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="expired_notice_title">Tiêu đề thông báo</label>
                        <input class="input" id="expired_notice_title" type="text" name="expired_notice_title" value="<?= e((string) ($settingsMap['expired_notice_title'] ?? '')) ?>" required>
                    </div>

                    <div class="field">
                        <label for="expired_notice_message">Nội dung thông báo</label>
                        <textarea class="textarea" id="expired_notice_message" name="expired_notice_message" rows="5" required><?= e((string) ($settingsMap['expired_notice_message'] ?? '')) ?></textarea>
                    </div>

                    <div class="admin-form-grid">
                        <div class="field">
                            <label for="support_contact_name">Tên liên hệ</label>
                            <input class="input" id="support_contact_name" type="text" name="support_contact_name" value="<?= e((string) ($settingsMap['support_contact_name'] ?? '')) ?>" placeholder="Ví dụ: Zalo hỗ trợ">
                        </div>

                        <div class="field">
                            <label for="support_contact_value">Thông tin liên hệ chính</label>
                            <input class="input" id="support_contact_value" type="text" name="support_contact_value" value="<?= e((string) ($settingsMap['support_contact_value'] ?? '')) ?>" placeholder="Số điện thoại, username, email hoặc link">
                        </div>
                    </div>

                    <div class="field">
                        <label for="support_contact_extra">Ghi chú thêm</label>
                        <textarea class="textarea" id="support_contact_extra" name="support_contact_extra" rows="3" placeholder="Ví dụ: Hỗ trợ trong giờ hành chính, phản hồi trong 15 phút..."><?= e((string) ($settingsMap['support_contact_extra'] ?? '')) ?></textarea>
                    </div>

                    <div class="field">
                        <label for="footer_text">Nội dung footer chung</label>
                        <textarea class="textarea" id="footer_text" name="footer_text" rows="3" placeholder="Ví dụ: Hỗ trợ gia hạn qua Zalo, phản hồi từ 08:00 - 22:00 mỗi ngày..."><?= e((string) ($settingsMap['footer_text'] ?? '')) ?></textarea>
                        <div class="small muted">Hiển thị ở cuối toàn bộ trang trong hệ thống, phù hợp để đặt thông tin liên hệ ngắn hoặc lưu ý hỗ trợ.</div>
                    </div>

                    <div class="actions">
                        <button class="button primary" type="submit">Lưu cấu hình</button>
                    </div>
                </form>
            </section>
        </div>

        <aside class="admin-side">
            <section class="card admin-side-card">
                <h2 class="section-title">Xem trước màn hình hết hạn</h2>
                <div class="expired-preview">
                    <span class="badge danger">Đã hết hạn</span>
                    <h3 class="expired-preview-title"><?= e((string) ($settingsMap['expired_notice_title'] ?? '')) ?></h3>
                    <div class="expired-preview-copy"><?= nl2br(e((string) ($settingsMap['expired_notice_message'] ?? ''))) ?></div>

                    <?php if ($supportName !== '' || $supportValue !== '' || $supportExtra !== ''): ?>
                        <div class="list-item">
                            <strong><?= e($supportName !== '' ? $supportName : 'Liên hệ') ?></strong>
                            <?php if ($supportValue !== ''): ?>
                                <div><?= e($supportValue) ?></div>
                            <?php endif; ?>
                            <?php if ($supportExtra !== ''): ?>
                                <div class="small muted"><?= nl2br(e($supportExtra)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card admin-side-card">
                <h2 class="section-title">Xem trước footer</h2>
                <div class="footer-preview-card">
                    <div class="footer-preview-brand">TeleSender</div>

                    <?php if ($footerText !== ''): ?>
                        <div class="footer-preview-copy"><?= nl2br(e($footerText)) ?></div>
                    <?php endif; ?>

                    <?php if ($supportName !== '' || $supportValue !== '' || $supportExtra !== ''): ?>
                        <div class="footer-preview-contact">
                            <strong><?= e($supportName !== '' ? $supportName : 'Liên hệ hỗ trợ') ?>:</strong>
                            <?php if ($supportValue !== ''): ?>
                                <?php if ($supportHref !== null): ?>
                                    <a href="<?= e($supportHref) ?>" target="_blank" rel="noreferrer"><?= e($supportValue) ?></a>
                                <?php else: ?>
                                    <span><?= e($supportValue) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($supportExtra !== ''): ?>
                                <span><?= e($supportExtra) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</section>
