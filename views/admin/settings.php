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
            <p class="page-subtitle">Thiết lập nội dung chung và chính sách an toàn gửi Telegram trên toàn hệ thống.</p>
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

                    <div class="list-item">
                        <div class="builder-block-head">
                            <div>
                                <strong>An toàn gửi Telegram</strong>
                                <div class="small muted">Daily limit là cửa sổ trượt 24 giờ. Các giá trị có hiệu lực từ cron request tiếp theo.</div>
                            </div>
                        </div>

                        <div class="admin-form-grid" style="margin-top:16px;">
                            <div class="field">
                                <label for="safety_safe_hourly_limit">Safe - lượt/giờ</label>
                                <input class="input" id="safety_safe_hourly_limit" type="number" min="1" name="safety_safe_hourly_limit" value="<?= e((string) ($settingsMap['safety_safe_hourly_limit'] ?? '8')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_safe_daily_limit">Safe - lượt/24 giờ</label>
                                <input class="input" id="safety_safe_daily_limit" type="number" min="1" name="safety_safe_daily_limit" value="<?= e((string) ($settingsMap['safety_safe_daily_limit'] ?? '40')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_safe_min_gap_minutes">Safe - khoảng cách (phút)</label>
                                <input class="input" id="safety_safe_min_gap_minutes" type="number" min="1" name="safety_safe_min_gap_minutes" value="<?= e((string) ($settingsMap['safety_safe_min_gap_minutes'] ?? '8')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_elevated_hourly_limit">Elevated - lượt/giờ</label>
                                <input class="input" id="safety_elevated_hourly_limit" type="number" min="1" name="safety_elevated_hourly_limit" value="<?= e((string) ($settingsMap['safety_elevated_hourly_limit'] ?? '10')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_elevated_daily_limit">Elevated - lượt/24 giờ</label>
                                <input class="input" id="safety_elevated_daily_limit" type="number" min="1" name="safety_elevated_daily_limit" value="<?= e((string) ($settingsMap['safety_elevated_daily_limit'] ?? '80')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_elevated_min_gap_minutes">Elevated - khoảng cách (phút)</label>
                                <input class="input" id="safety_elevated_min_gap_minutes" type="number" min="1" name="safety_elevated_min_gap_minutes" value="<?= e((string) ($settingsMap['safety_elevated_min_gap_minutes'] ?? '5')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_risk_min_gap_minutes">Risk - khoảng cách tối thiểu</label>
                                <input class="input" id="safety_risk_min_gap_minutes" type="number" min="1" name="safety_risk_min_gap_minutes" value="<?= e((string) ($settingsMap['safety_risk_min_gap_minutes'] ?? '1')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_circuit_breaker_error_count">Lỗi không phân loại để mở breaker</label>
                                <input class="input" id="safety_circuit_breaker_error_count" type="number" min="1" name="safety_circuit_breaker_error_count" value="<?= e((string) ($settingsMap['safety_circuit_breaker_error_count'] ?? '3')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_circuit_breaker_window_minutes">Cửa sổ đếm lỗi dự phòng (phút)</label>
                                <input class="input" id="safety_circuit_breaker_window_minutes" type="number" min="1" name="safety_circuit_breaker_window_minutes" value="<?= e((string) ($settingsMap['safety_circuit_breaker_window_minutes'] ?? '15')) ?>" required>
                            </div>
                            <div class="field">
                                <label for="safety_circuit_breaker_cooldown_minutes">Cooldown mặc định (phút)</label>
                                <input class="input" id="safety_circuit_breaker_cooldown_minutes" type="number" min="1" name="safety_circuit_breaker_cooldown_minutes" value="<?= e((string) ($settingsMap['safety_circuit_breaker_cooldown_minutes'] ?? '180')) ?>" required>
                            </div>
                        </div>

                        <label class="chip" style="margin-top:12px;display:inline-flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="safety_admin_self_override_enabled" value="1" <?= (string) ($settingsMap['safety_admin_self_override_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                            Cho phép admin đã được cấp quyền tự bật/tắt chế độ elevated hoặc risk accepted
                        </label>
                        <div class="small muted" style="margin-top:8px;">Tín hiệu spam/rate limit rõ ràng từ Telegram sẽ mở breaker ngay; ngưỡng lỗi chỉ dùng làm cơ chế dự phòng. Audit và thông báo được lưu 30 ngày.</div>
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
