<div class="stack">
    <span class="badge danger">HTTP <?= e((string) $exception->status()) ?></span>
    <h1 class="auth-heading"><?= e($exception->getMessage()) ?></h1>
    <a class="button primary" href="<?= e(url('/')) ?>">Quay về tổng quan</a>
</div>
