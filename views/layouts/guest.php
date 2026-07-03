<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config('app.name')) ?></title>
    <script>
    (function () {
        var root = document.documentElement;
        var storageKey = 'tele_sender_theme';
        var theme = 'light';

        try {
            var stored = localStorage.getItem(storageKey);
            if (stored === 'dark' || stored === 'light') {
                theme = stored;
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }
        } catch (error) {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }
        }

        root.setAttribute('data-theme', theme);
    })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/fontawesome/css/all.min.css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
</head>
<?php
$systemSettings = system_settings_map();
$footerText = trim((string) ($systemSettings['footer_text'] ?? ''));
$supportName = trim((string) ($systemSettings['support_contact_name'] ?? ''));
$supportValue = trim((string) ($systemSettings['support_contact_value'] ?? ''));
$supportExtra = trim((string) ($systemSettings['support_contact_extra'] ?? ''));
$supportHref = support_contact_href($supportValue);
$hasFooterMeta = $footerText !== '' || $supportName !== '' || $supportValue !== '' || $supportExtra !== '';
?>
<body>
    <main class="auth-shell">
        <div class="guest-toolbar">
            <button class="theme-toggle theme-toggle-guest" type="button" data-theme-toggle aria-label="Chuyển giao diện" title="Chuyển giao diện">
                <i class="fa-solid fa-moon" data-theme-icon aria-hidden="true"></i>
                <span data-theme-label>Đổi giao diện</span>
            </button>
        </div>

        <section class="card auth-card">
            <?= $content ?>
        </section>

        <footer class="app-footer app-footer-guest">
            <div class="app-footer-inner">

                <?php if ($hasFooterMeta): ?>
                    <div class="app-footer-meta">
                        <?php if ($footerText !== ''): ?>
                            <div class="app-footer-copy"><?= nl2br(e($footerText)) ?></div>
                        <?php endif; ?>

                        <?php if ($supportName !== '' || $supportValue !== '' || $supportExtra !== ''): ?>
                            <div class="app-footer-contact">
                                <strong><?= e($supportName !== '' ? $supportName : 'Liên hệ hỗ trợ') ?>:</strong>
                                <?php if ($supportValue !== ''): ?>
                                    <?php if ($supportHref !== null): ?>
                                        <a href="<?= e($supportHref) ?>" target="_blank" rel="noreferrer"><?= e($supportValue) ?></a>
                                    <?php else: ?>
                                        <span><?= e($supportValue) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($supportExtra !== ''): ?>
                                    <span class="app-footer-extra"><?= e($supportExtra) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </footer>
    </main>
    <script src="<?= e(asset('theme.js')) ?>" defer></script>
</body>
</html>
