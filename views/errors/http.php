<div class="stack">
    <span class="badge danger">HTTP <?= e((string) $exception->status()) ?></span>
    <h1 class="auth-heading"><?= e($exception->getMessage()) ?></h1>
    <p class="auth-copy">Hãy kiểm tra lại route, đăng nhập hoặc cấu hình hệ thống rồi thử lại.</p>
    <a class="button primary" href="<?= e(url('/')) ?>">Quay về dashboard</a>
</div>
