<?php

declare(strict_types=1);

$settings = $settings ?? [];
$user = $user ?? [];
$supportName = trim((string) ($settings['support_contact_name'] ?? ''));
$supportValue = trim((string) ($settings['support_contact_value'] ?? ''));
$supportExtra = trim((string) ($settings['support_contact_extra'] ?? ''));
$supportHref = support_contact_href($supportValue);
?>
<section class="stack expired-screen">
    <div>
        <span class="badge danger">Truy cập tạm khóa</span>
        <h1 class="auth-heading" style="margin-top: 16px;"><?= e((string) ($settings['expired_notice_title'] ?? 'Gói sử dụng đã hết hạn')) ?></h1>
        <p class="auth-copy"><?= nl2br(e((string) ($settings['expired_notice_message'] ?? ''))) ?></p>
    </div>

    <div class="list">
        <div class="list-item">
            <strong>Tài khoản</strong>
            <div style="margin-top: 8px;"><?= e((string) ($user['name'] ?? '')) ?> · <?= e((string) ($user['email'] ?? '')) ?></div>
            <?php if (!empty($user['subscription_expires_at'])): ?>
                <div class="small muted" style="margin-top: 6px;">Hết hạn từ <?= e(fmt_datetime((string) $user['subscription_expires_at'])) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($supportName !== '' || $supportValue !== '' || $supportExtra !== ''): ?>
            <div class="list-item">
                <strong><?= e($supportName !== '' ? $supportName : 'Liên hệ hỗ trợ') ?></strong>
                <?php if ($supportValue !== ''): ?>
                    <div style="margin-top: 8px;">
                        <?php if ($supportHref !== null): ?>
                            <a href="<?= e($supportHref) ?>" target="_blank" rel="noreferrer"><?= e($supportValue) ?></a>
                        <?php else: ?>
                            <?= e($supportValue) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($supportExtra !== ''): ?>
                    <div class="small muted" style="margin-top: 6px;"><?= nl2br(e($supportExtra)) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="button primary" type="submit">Đăng xuất</button>
    </form>
</section>
