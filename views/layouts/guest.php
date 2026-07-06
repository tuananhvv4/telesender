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
    <div class="toast-stack" id="app_toast_stack" aria-live="polite" aria-atomic="true">
        <?php if ($success = flash('success')): ?>
            <div class="toast success" role="status">
                <div class="toast-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
                <div class="toast-content">
                    <strong class="toast-title">Thành công</strong>
                    <div class="toast-message"><?= e($success) ?></div>
                </div>
                <button class="toast-dismiss" type="button" data-toast-close aria-label="Đóng thông báo">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <div class="toast-progress" aria-hidden="true">
                    <div class="toast-progress-bar" data-toast-progress-bar></div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($error = flash('error')): ?>
            <div class="toast error" role="alert">
                <div class="toast-icon" aria-hidden="true"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div class="toast-content">
                    <strong class="toast-title">Có lỗi xảy ra</strong>
                    <div class="toast-message"><?= e($error) ?></div>
                </div>
                <button class="toast-dismiss" type="button" data-toast-close aria-label="Đóng thông báo">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <div class="toast-progress" aria-hidden="true">
                    <div class="toast-progress-bar" data-toast-progress-bar></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

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
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const toastStack = document.getElementById('app_toast_stack');

        if (!toastStack) {
            return;
        }

        function removeToastElement(element) {
            if (!(element instanceof HTMLElement) || !element.parentNode) {
                return;
            }

            element.classList.add('is-leaving');
            window.setTimeout(() => {
                if (element.parentNode) {
                    element.remove();
                }
            }, 220);
        }

        function bindToastElement(element) {
            if (!(element instanceof HTMLElement) || element.dataset.toastBound === '1') {
                return;
            }

            element.dataset.toastBound = '1';

            let dismissTimer = null;
            const duration = Number(element.getAttribute('data-toast-duration') || (element.classList.contains('error') ? 5600 : 4200));
            let remaining = duration;
            let startedAt = 0;
            const progressBar = element.querySelector('[data-toast-progress-bar]');

            function syncProgress(ratio, withTransition = false, transitionDuration = 0) {
                if (!(progressBar instanceof HTMLElement)) {
                    return;
                }

                progressBar.style.transition = withTransition ? `transform ${transitionDuration}ms linear` : 'none';
                progressBar.style.transform = `scaleX(${Math.max(0, Math.min(1, ratio))})`;
            }

            function pauseTimer() {
                window.clearTimeout(dismissTimer);

                if (startedAt > 0) {
                    remaining = Math.max(0, remaining - (Date.now() - startedAt));
                    startedAt = 0;
                }

                syncProgress(duration > 0 ? remaining / duration : 0, false);
            }

            const startTimer = () => {
                window.clearTimeout(dismissTimer);
                startedAt = Date.now();

                syncProgress(duration > 0 ? remaining / duration : 0, false);

                if (progressBar instanceof HTMLElement) {
                    progressBar.getBoundingClientRect();
                }

                syncProgress(0, true, remaining);

                dismissTimer = window.setTimeout(() => {
                    removeToastElement(element);
                }, remaining);
            };

            element.querySelectorAll('[data-toast-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    pauseTimer();
                    removeToastElement(element);
                });
            });

            element.addEventListener('mouseenter', () => {
                pauseTimer();
            });

            element.addEventListener('mouseleave', () => {
                if (remaining > 0) {
                    startTimer();
                }
            });

            element.addEventListener('focusin', () => {
                pauseTimer();
            });

            element.addEventListener('focusout', () => {
                if (!element.contains(document.activeElement) && remaining > 0) {
                    startTimer();
                }
            });

            startTimer();
        }

        toastStack.querySelectorAll('.toast').forEach((element) => {
            bindToastElement(element);
        });
    });
    </script>
    <script src="<?= e(asset('theme.js')) ?>" defer></script>
</body>
</html>
