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
$currentUser = auth()->user() ?? [];
$userName = (string) ($currentUser['name'] ?? '');
$userEmail = (string) ($currentUser['email'] ?? '');
$userInitial = $userName !== '' ? mb_strtoupper(mb_substr($userName, 0, 1)) : 'U';
$isSuperAdmin = user_is_super_admin($currentUser);
$daysRemaining = user_days_remaining($currentUser);
$systemSettings = system_settings_map();
$footerText = trim((string) ($systemSettings['footer_text'] ?? ''));
$supportName = trim((string) ($systemSettings['support_contact_name'] ?? ''));
$supportValue = trim((string) ($systemSettings['support_contact_value'] ?? ''));
$supportExtra = trim((string) ($systemSettings['support_contact_extra'] ?? ''));
$supportHref = support_contact_href($supportValue);
$hasFooterMeta = $footerText !== '' || $supportName !== '' || $supportValue !== '' || $supportExtra !== '';

if ($isSuperAdmin) {
    $subscriptionBadgeClass = 'info';
    $subscriptionBadgeText = user_subscription_label($currentUser);
} elseif ($daysRemaining === null) {
    $subscriptionBadgeClass = 'success';
    $subscriptionBadgeText = 'Không giới hạn sử dụng';
} elseif ($daysRemaining <= 0) {
    $subscriptionBadgeClass = 'danger';
    $subscriptionBadgeText = 'Đã hết hạn sử dụng';
} elseif ($daysRemaining < 5) {
    $subscriptionBadgeClass = 'danger';
    $subscriptionBadgeText = 'Còn ' . $daysRemaining . ' ngày sử dụng';
} else {
    $subscriptionBadgeClass = 'success';
    $subscriptionBadgeText = 'Còn ' . $daysRemaining . ' ngày sử dụng';
}
?>
<body class="app-layout">
    <div class="shell">
        <button class="sidebar-overlay" type="button" data-sidebar-close aria-label="Đóng menu"></button>

        <aside class="sidebar" id="app_sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-main">
                    <div class="brand-badge">TS</div>
                    <div class="brand-copy">
                        <h1><?= e(config('app.name')) ?></h1>
                    </div>
                </div>
                <div class="sidebar-brand-actions">
                    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Chuyển giao diện" title="Chuyển giao diện">
                        <i class="fa-solid fa-moon" data-theme-icon aria-hidden="true"></i>
                        <span class="sr-only">Chuyển giao diện</span>
                    </button>
                    <button class="sidebar-toggle sidebar-toggle-desktop" type="button" data-sidebar-toggle aria-label="Thu gọn menu">
                        <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="sidebar-section-label">Điều hướng</div>

            <nav class="nav">
                <a class="nav-link <?= is_active_path('/') ? 'active' : '' ?>" href="<?= e(url('/')) ?>" title="Tổng quan">
                    <span class="nav-icon" aria-hidden="true"><i class="fa-solid fa-gauge-high"></i></span>
                    <span class="nav-text">Tổng quan</span>
                </a>
                <a class="nav-link <?= is_active_path('/accounts') ? 'active' : '' ?>" href="<?= e(url('/accounts')) ?>" title="Tài khoản">
                    <span class="nav-icon" aria-hidden="true"><i class="fa-solid fa-user-group"></i></span>
                    <span class="nav-text">Tài khoản</span>
                </a>
                <a class="nav-link <?= is_active_path('/groups') ? 'active' : '' ?>" href="<?= e(url('/groups')) ?>" title="Nhóm">
                    <span class="nav-icon" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                    <span class="nav-text">Nhóm</span>
                </a>
                <a class="nav-link <?= is_active_path('/labels') ? 'active' : '' ?>" href="<?= e(url('/labels')) ?>" title="Nhãn">
                    <span class="nav-icon" aria-hidden="true"><i class="fa-solid fa-tags"></i></span>
                    <span class="nav-text">Nhãn</span>
                </a>
                <a class="nav-link <?= is_active_path('/custom-emojis') ? 'active' : '' ?>" href="<?= e(url('/custom-emojis')) ?>" title="Emoji tùy chỉnh">
                    <span class="nav-icon" aria-hidden="true"><i class="fa-regular fa-face-smile"></i></span>
                    <span class="nav-text">Emoji tùy chỉnh</span>
                </a>
                <a class="nav-link <?= is_active_path('/templates') ? 'active' : '' ?>" href="<?= e(url('/templates')) ?>" title="Mẫu tin nhắn">
                    <span class="nav-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                    <span class="nav-text">Mẫu tin nhắn</span>
                </a>
                <a class="nav-link <?= is_active_path('/schedules') ? 'active' : '' ?>" href="<?= e(url('/schedules')) ?>" title="Lịch gửi">
                    <span class="nav-icon" aria-hidden="true"><i class="fa-regular fa-calendar-days"></i></span>
                    <span class="nav-text">Lịch gửi</span>
                </a>
                <a class="nav-link <?= is_active_path('/logs') ? 'active' : '' ?>" href="<?= e(url('/logs')) ?>" title="Nhật ký">
                    <span class="nav-icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <span class="nav-text">Nhật ký</span>
                </a>
            </nav>

            <?php if ($isSuperAdmin): ?>
                <div class="sidebar-section-label">Quản trị</div>
                <nav class="nav nav-admin">
                    <a class="nav-link <?= is_active_path('/admin/users') ? 'active' : '' ?>" href="<?= e(url('/admin/users')) ?>" title="Admin con">
                        <span class="nav-icon" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                        <span class="nav-text">Admin con</span>
                    </a>
                    <a class="nav-link <?= is_active_path('/admin/subscriptions') ? 'active' : '' ?>" href="<?= e(url('/admin/subscriptions')) ?>" title="Hạn sử dụng">
                        <span class="nav-icon" aria-hidden="true"><i class="fa-regular fa-hourglass-half"></i></span>
                        <span class="nav-text">Hạn sử dụng</span>
                    </a>
                    <a class="nav-link <?= is_active_path('/admin/settings') ? 'active' : '' ?>" href="<?= e(url('/admin/settings')) ?>" title="Cấu hình hệ thống">
                        <span class="nav-icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Cấu hình hệ thống</span>
                    </a>
                </nav>
            <?php endif; ?>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar"><?= e($userInitial) ?></div>
                    <div class="sidebar-user-copy">
                        <strong><?= e($userName) ?></strong>
                        <div class="small muted"><?= e($userEmail) ?></div>
                        <div class="sidebar-user-meta">
                            <span class="badge <?= e($subscriptionBadgeClass) ?>"><?= e($subscriptionBadgeText) ?></span>
                        </div>
                    </div>
                </div>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="button secondary sidebar-logout" type="submit">
                        <span class="sidebar-logout-icon" aria-hidden="true"><i class="fa-solid fa-right-from-bracket"></i></span>
                        <span class="sidebar-logout-text">Đăng xuất</span>
                    </button>
                </form>
            </div>
        </aside>

        <main class="main">
            <header class="mobile-topbar">
                <div class="mobile-topbar-brand">
                    <div class="brand-badge small">TS</div>
                    <div class="mobile-topbar-copy">
                        <strong><?= e(config('app.name')) ?></strong>
                        <span>Bảng điều khiển</span>
                    </div>
                </div>
                <div class="mobile-topbar-actions">
                    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Chuyển giao diện" title="Chuyển giao diện">
                        <i class="fa-solid fa-moon" data-theme-icon aria-hidden="true"></i>
                        <span class="sr-only">Chuyển giao diện</span>
                    </button>
                    <button class="sidebar-toggle sidebar-toggle-mobile" type="button" data-sidebar-toggle aria-controls="app_sidebar" aria-expanded="false" aria-label="Mở menu">
                        <i class="fa-solid fa-bars" aria-hidden="true"></i>
                    </button>
                </div>
            </header>

            <div class="main-content">
                <div class="flash-stack" id="app_flash_stack" aria-live="polite" aria-atomic="true">
                    <?php if ($success = flash('success')): ?>
                        <div class="flash success"><?= e($success) ?></div>
                    <?php endif; ?>
                    <?php if ($error = flash('error')): ?>
                        <div class="flash error"><?= e($error) ?></div>
                    <?php endif; ?>
                </div>
                <?= $content ?>
            </div>

            <footer class="app-footer">
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
    </div>

    <div class="app-modal" id="app_modal" hidden aria-hidden="true">
        <div class="app-modal-backdrop" data-app-modal-close></div>
        <div class="app-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="app_modal_title">
            <div class="app-modal-head">
                <div>
                    <h2 class="app-modal-title" id="app_modal_title">Xác nhận</h2>
                </div>
                <button class="app-modal-dismiss" type="button" data-app-modal-close aria-label="Đóng popup">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="app-modal-body" id="app_modal_body"></div>
            <div class="app-modal-actions">
                <button class="button secondary" type="button" id="app_modal_cancel">Hủy</button>
                <button class="button danger" type="button" id="app_modal_confirm">Xác nhận</button>
            </div>
        </div>
    </div>

    <div class="crud-modal" id="crud_modal" hidden aria-hidden="true">
        <div class="crud-modal-backdrop" data-crud-modal-close></div>
        <div class="crud-modal-dialog" id="crud_modal_dialog" data-size="lg" role="dialog" aria-modal="true" aria-labelledby="crud_modal_title">
            <div class="crud-modal-head">
                <div class="crud-modal-copy">
                    <h2 class="crud-modal-title" id="crud_modal_title">Biểu mẫu</h2>
                    <p class="crud-modal-description" id="crud_modal_description" hidden></p>
                </div>
                <button class="crud-modal-dismiss" type="button" data-crud-modal-close aria-label="Đóng popup">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="crud-modal-body" id="crud_modal_body"></div>
        </div>
    </div>

    <script src="<?= e(asset('theme.js')) ?>" defer></script>
    <script>
    (function () {
        const body = document.body;
        const desktopBreakpoint = window.matchMedia('(min-width: 1081px)');
        const sidebarStateKey = 'tele_sender_sidebar_collapsed';
        const toggles = document.querySelectorAll('[data-sidebar-toggle]');
        const closers = document.querySelectorAll('[data-sidebar-close]');

        function isDesktop() {
            return desktopBreakpoint.matches;
        }

        function syncToggleState() {
            const expanded = body.classList.contains('sidebar-open');
            toggles.forEach((button) => {
                button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
        }

        function applyStoredDesktopState() {
            if (!isDesktop()) {
                body.classList.remove('sidebar-collapsed');
                return;
            }

            if (localStorage.getItem(sidebarStateKey) === '1') {
                body.classList.add('sidebar-collapsed');
            } else {
                body.classList.remove('sidebar-collapsed');
            }
        }

        function closeMobileSidebar() {
            body.classList.remove('sidebar-open');
            syncToggleState();
        }

        function openMobileSidebar() {
            body.classList.add('sidebar-open');
            syncToggleState();
        }

        function toggleSidebar() {
            if (isDesktop()) {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem(sidebarStateKey, body.classList.contains('sidebar-collapsed') ? '1' : '0');
                return;
            }

            if (body.classList.contains('sidebar-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        }

        toggles.forEach((button) => {
            button.addEventListener('click', toggleSidebar);
        });

        closers.forEach((button) => {
            button.addEventListener('click', closeMobileSidebar);
        });

        window.addEventListener('resize', () => {
            if (isDesktop()) {
                closeMobileSidebar();
                applyStoredDesktopState();
            } else {
                body.classList.remove('sidebar-collapsed');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMobileSidebar();
            }
        });

        applyStoredDesktopState();
        syncToggleState();
    })();

    (function () {
        const body = document.body;
        let openCount = 0;

        window.TeleSenderModalState = {
            lock() {
                openCount += 1;
                body.classList.add('modal-open');
            },
            unlock() {
                openCount = Math.max(0, openCount - 1);

                if (openCount === 0) {
                    body.classList.remove('modal-open');
                }
            },
        };
    })();

    (function () {
        const flashStack = document.getElementById('app_flash_stack');
        const pendingFlashStorageKey = 'tele_sender_pending_flash';

        function persistFlash(type, message) {
            if (!message) {
                return;
            }

            try {
                sessionStorage.setItem(pendingFlashStorageKey, JSON.stringify({
                    type: type === 'error' ? 'error' : 'success',
                    message: String(message),
                    createdAt: Date.now(),
                }));
            } catch (error) {
                // Ignore storage errors and continue without persisted flash.
            }
        }

        function consumePersistedFlash() {
            try {
                const raw = sessionStorage.getItem(pendingFlashStorageKey);

                if (!raw) {
                    return null;
                }

                sessionStorage.removeItem(pendingFlashStorageKey);

                const parsed = JSON.parse(raw);

                if (!parsed || typeof parsed.message !== 'string' || parsed.message.trim() === '') {
                    return null;
                }

                return {
                    type: parsed.type === 'error' ? 'error' : 'success',
                    message: parsed.message,
                };
            } catch (error) {
                try {
                    sessionStorage.removeItem(pendingFlashStorageKey);
                } catch (cleanupError) {
                    // Ignore cleanup errors.
                }

                return null;
            }
        }

        function removeFlashElement(element) {
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

        function bindFlashElement(element) {
            if (!(element instanceof HTMLElement) || element.dataset.flashBound === '1') {
                return;
            }

            element.dataset.flashBound = '1';

            window.setTimeout(() => {
                removeFlashElement(element);
            }, 4200);
        }

        function showFlash(type, message, options = {}) {
            if (!flashStack || !message) {
                return;
            }

            const element = document.createElement('div');
            element.className = `flash ${type === 'error' ? 'error' : 'success'}`;
            element.textContent = String(message);
            element.setAttribute('role', type === 'error' ? 'alert' : 'status');
            flashStack.prepend(element);
            bindFlashElement(element);

            if (options.persist === true) {
                persistFlash(type, message);
            }

            if (options.scrollToTop !== false) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        function reloadWithFlash(options = {}) {
            const type = options.type === 'error' ? 'error' : 'success';
            const message = options.message || '';
            const targetUrl = typeof options.url === 'string' && options.url !== '' ? options.url : '';

            if (message !== '') {
                persistFlash(type, message);
            }

            if (targetUrl !== '' && targetUrl !== window.location.href) {
                window.location.assign(targetUrl);
                return;
            }

            window.location.reload();
        }

        function readJsonScript(target, fallback = {}) {
            const element = typeof target === 'string'
                ? document.querySelector(target)
                : target;

            if (!(element instanceof HTMLScriptElement)) {
                return fallback;
            }

            try {
                const raw = element.textContent || '';
                return raw.trim() === '' ? fallback : JSON.parse(raw);
            } catch (error) {
                return fallback;
            }
        }

        function clearFormFeedback(form) {
            const feedback = form.querySelector('[data-form-feedback]');

            if (!feedback) {
                return;
            }

            feedback.hidden = true;
            feedback.textContent = '';
            feedback.className = 'form-feedback';
        }

        function setFormFeedback(form, type, message) {
            const feedback = form.querySelector('[data-form-feedback]');

            if (!feedback) {
                return;
            }

            feedback.hidden = !message;
            feedback.textContent = message || '';
            feedback.className = `form-feedback ${type === 'success' ? 'success' : 'error'}`;
        }

        function setSubmitting(form, isSubmitting) {
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                if (isSubmitting) {
                    if (!button.dataset.originalLabel) {
                        button.dataset.originalLabel = button.textContent;
                    }

                    button.disabled = true;

                    if (button.dataset.loadingText) {
                        button.textContent = button.dataset.loadingText;
                    }

                    return;
                }

                button.disabled = false;

                if (button.dataset.originalLabel) {
                    button.textContent = button.dataset.originalLabel;
                }
            });
        }

        async function fetchJson(url, options = {}) {
            const headers = new Headers(options.headers || {});

            if (!headers.has('Accept')) {
                headers.set('Accept', 'application/json');
            }

            headers.set('X-Requested-With', 'XMLHttpRequest');

            const response = await fetch(url, {
                ...options,
                headers,
            });
            const raw = await response.text();
            let payload = {};

            if (raw.trim() !== '') {
                try {
                    payload = JSON.parse(raw);
                } catch (error) {
                    throw {
                        ok: false,
                        message: 'Máy chủ trả về dữ liệu không hợp lệ.',
                        status: response.status || 500,
                    };
                }
            }

            if (!response.ok || payload.ok === false) {
                throw {
                    ...payload,
                    ok: false,
                    status: Number(payload.status || response.status || 500),
                    message: payload.message || 'Yêu cầu thất bại.',
                };
            }

            return payload;
        }

        async function fetchHtmlDocument(url = window.location.href) {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'text/html,application/xhtml+xml',
                },
                cache: 'no-store',
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Không thể tải lại dữ liệu trang hiện tại.');
            }

            const raw = await response.text();
            return new DOMParser().parseFromString(raw, 'text/html');
        }

        async function refreshRegions(selectors, options = {}) {
            const uniqueSelectors = Array.from(new Set(
                (Array.isArray(selectors) ? selectors : [selectors])
                    .map((selector) => String(selector || '').trim())
                    .filter(Boolean)
            ));

            if (uniqueSelectors.length === 0) {
                return;
            }

            const nextDocument = await fetchHtmlDocument(options.url || window.location.href);

            uniqueSelectors.forEach((selector) => {
                const currentElement = document.querySelector(selector);
                const nextElement = nextDocument.querySelector(selector);

                if (!currentElement || !nextElement) {
                    throw new Error(`Không tìm thấy vùng cần làm mới: ${selector}`);
                }

                currentElement.replaceWith(nextElement);
            });

            document.dispatchEvent(new CustomEvent('app:regions:refreshed', {
                detail: {
                    selectors: uniqueSelectors,
                },
            }));
        }

        async function submitAjaxForm(form, options = {}) {
            clearFormFeedback(form);
            setSubmitting(form, true);

            try {
                const payload = await fetchJson(form.action, {
                    method: String(form.method || 'POST').toUpperCase(),
                    body: new FormData(form),
                });

                if (typeof options.onSuccess === 'function') {
                    await options.onSuccess(payload, form);
                }

                if (typeof options.closeOnSuccess === 'function') {
                    await options.closeOnSuccess(payload, form);
                }

                if (options.closeCrudModalOnSuccess === true) {
                    window.TeleSenderCrudModal?.close();
                }

                if (Array.isArray(options.refreshRegionsOnSuccess) && options.refreshRegionsOnSuccess.length > 0) {
                    try {
                        await refreshRegions(options.refreshRegionsOnSuccess, {
                            url: options.refreshUrl || window.location.href,
                        });

                        if (payload.message && options.showSuccessFlash !== false) {
                            showFlash('success', payload.message);
                        }
                    } catch (refreshError) {
                        reloadWithFlash({
                            type: 'success',
                            message: options.successMessage || payload.message || '',
                            url: options.redirectUrl || payload.redirect || '',
                        });
                    }
                } else if (options.reloadOnSuccess === true || (options.reloadOnSuccess !== false && typeof options.onSuccess !== 'function')) {
                    reloadWithFlash({
                        type: 'success',
                        message: options.successMessage || payload.message || '',
                        url: options.redirectUrl || payload.redirect || '',
                    });
                } else if (payload.message && options.showSuccessFlash !== false) {
                    showFlash('success', payload.message);
                }

                return payload;
            } catch (error) {
                const payload = error && typeof error === 'object' ? error : {};
                const status = Number(payload.status || 0);
                const message = payload.message || 'Không thể xử lý yêu cầu này.';

                if (payload.redirect && [401, 403, 419].includes(status)) {
                    showFlash('error', message);
                    window.setTimeout(() => {
                        window.location.href = String(payload.redirect);
                    }, 500);
                    return null;
                }

                if (typeof options.onError === 'function') {
                    await options.onError(payload, form);
                } else {
                    setFormFeedback(form, 'error', message);
                }

                return null;
            } finally {
                setSubmitting(form, false);
            }
        }

        flashStack?.querySelectorAll('.flash').forEach((element) => {
            bindFlashElement(element);
        });

        const pendingFlash = consumePersistedFlash();

        if (pendingFlash) {
            showFlash(pendingFlash.type, pendingFlash.message);
        }

        window.TeleSenderApp = {
            clearFormFeedback,
            fetchJson,
            readJsonScript,
            refreshRegions,
            reloadWithFlash,
            setFormFeedback,
            showFlash,
            submitAjaxForm,
        };
    })();

    (function () {
        const body = document.body;
        const modalRoot = document.getElementById('app_modal');
        const modalTitle = document.getElementById('app_modal_title');
        const modalBody = document.getElementById('app_modal_body');
        const cancelButton = document.getElementById('app_modal_cancel');
        const confirmButton = document.getElementById('app_modal_confirm');
        const closeButtons = document.querySelectorAll('[data-app-modal-close]');
        let resolver = null;
        let responseEventName = null;
        let activeMode = 'confirm';

        if (!modalRoot || !modalTitle || !modalBody || !cancelButton || !confirmButton) {
            return;
        }

        function cleanup(result) {
            modalRoot.hidden = true;
            modalRoot.setAttribute('aria-hidden', 'true');
            window.TeleSenderModalState?.unlock();

            if (responseEventName) {
                document.dispatchEvent(new CustomEvent(responseEventName, {
                    detail: {
                        confirmed: result === true,
                        result: result,
                    },
                }));
                responseEventName = null;
            }

            if (resolver) {
                const resolve = resolver;
                resolver = null;
                resolve(result);
            }
        }

        function openModal(options, mode) {
            activeMode = mode;
            responseEventName = options.responseEvent || null;
            modalTitle.textContent = options.title || (mode === 'alert' ? 'Thông báo' : 'Xác nhận');
            modalBody.textContent = options.message || '';

            cancelButton.hidden = mode === 'alert';
            cancelButton.textContent = options.cancelText || 'Hủy';

            confirmButton.textContent = options.confirmText || (mode === 'alert' ? 'Đã hiểu' : 'Xác nhận');
            confirmButton.className = `button ${options.confirmClass || (mode === 'alert' ? 'primary' : 'danger')}`;

            modalRoot.hidden = false;
            modalRoot.setAttribute('aria-hidden', 'false');
            window.TeleSenderModalState?.lock();

            setTimeout(() => {
                confirmButton.focus();
            }, 10);

            return new Promise((resolve) => {
                resolver = resolve;
            });
        }

        document.addEventListener('app:modal:open', (event) => {
            const detail = event.detail || {};
            const options = detail.options || {};
            options.responseEvent = detail.responseEvent || null;
            openModal(options, detail.mode === 'alert' ? 'alert' : 'confirm');
        });

        cancelButton.addEventListener('click', () => {
            cleanup(false);
        });

        confirmButton.addEventListener('click', () => {
            cleanup(true);
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                cleanup(false);
            });
        });

        document.addEventListener('keydown', (event) => {
            if (modalRoot.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                cleanup(false);
            }

            if (event.key === 'Enter' && activeMode === 'alert') {
                cleanup(true);
            }
        });

    })();

    (function () {
        const body = document.body;
        const modalRoot = document.getElementById('crud_modal');
        const modalDialog = document.getElementById('crud_modal_dialog');
        const modalTitle = document.getElementById('crud_modal_title');
        const modalDescription = document.getElementById('crud_modal_description');
        const modalBody = document.getElementById('crud_modal_body');
        let activeOnClose = null;

        if (!modalRoot || !modalDialog || !modalTitle || !modalDescription || !modalBody) {
            return;
        }

        function closeModal() {
            modalRoot.hidden = true;
            modalRoot.setAttribute('aria-hidden', 'true');
            modalDialog.dataset.size = 'lg';
            modalBody.innerHTML = '';
            modalDescription.hidden = true;
            modalDescription.textContent = '';
            window.TeleSenderModalState?.unlock();

            if (typeof activeOnClose === 'function') {
                activeOnClose();
            }

            activeOnClose = null;
            document.dispatchEvent(new CustomEvent('app:crud-modal:closed'));
        }

        function openModal(options = {}) {
            modalTitle.textContent = options.title || 'Biểu mẫu';
            modalDescription.textContent = options.description || '';
            modalDescription.hidden = !options.description;
            modalDialog.dataset.size = options.size || 'lg';
            modalBody.innerHTML = '';

            if (options.content instanceof Node) {
                modalBody.appendChild(options.content);
            } else if (typeof options.content === 'string') {
                modalBody.innerHTML = options.content;
            }

            activeOnClose = typeof options.onClose === 'function' ? options.onClose : null;

            modalRoot.hidden = false;
            modalRoot.setAttribute('aria-hidden', 'false');
            window.TeleSenderModalState?.lock();

            window.setTimeout(() => {
                const focusTarget = modalBody.querySelector('[autofocus], input, textarea, select, button');

                if (focusTarget instanceof HTMLElement) {
                    focusTarget.focus();
                }
            }, 10);

            document.dispatchEvent(new CustomEvent('app:crud-modal:opened', {
                detail: {
                    body: modalBody,
                    dialog: modalDialog,
                },
            }));
        }

        modalRoot.addEventListener('click', (event) => {
            const target = event.target instanceof Element
                ? event.target.closest('[data-crud-modal-close]')
                : null;

            if (target) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!modalRoot.hidden && event.key === 'Escape') {
                closeModal();
            }
        });

        window.TeleSenderCrudModal = {
            open: openModal,
            close: closeModal,
            getBody() {
                return modalBody;
            },
            isOpen() {
                return modalRoot.hidden === false;
            },
        };
    })();
    </script>
</body>
</html>
