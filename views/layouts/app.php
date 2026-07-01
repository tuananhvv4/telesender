<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-badge">TS</div>
                <div class="brand-copy">
                    <h1><?= e(config('app.name')) ?></h1>
                    <p>Telegram Scheduler</p>
                </div>
            </div>

            <nav class="nav">
                <a class="nav-link <?= is_active_path('/') ? 'active' : '' ?>" href="<?= e(url('/')) ?>">Dashboard</a>
                <a class="nav-link <?= is_active_path('/accounts') ? 'active' : '' ?>" href="<?= e(url('/accounts')) ?>">Accounts</a>
                <a class="nav-link <?= is_active_path('/groups') ? 'active' : '' ?>" href="<?= e(url('/groups')) ?>">Groups</a>
                <a class="nav-link <?= is_active_path('/labels') ? 'active' : '' ?>" href="<?= e(url('/labels')) ?>">Labels</a>
                <a class="nav-link <?= is_active_path('/templates') ? 'active' : '' ?>" href="<?= e(url('/templates')) ?>">Templates</a>
                <a class="nav-link <?= is_active_path('/schedules') ? 'active' : '' ?>" href="<?= e(url('/schedules')) ?>">Schedules</a>
                <a class="nav-link <?= is_active_path('/logs') ? 'active' : '' ?>" href="<?= e(url('/logs')) ?>">Logs</a>
            </nav>

            <div class="sidebar-footer">
                <div>
                    <strong><?= e(auth()->user()['name'] ?? '') ?></strong>
                    <div class="small muted"><?= e(auth()->user()['email'] ?? '') ?></div>
                </div>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="button secondary" type="submit">Đăng xuất</button>
                </form>
            </div>
        </aside>

        <main class="main">
            <?php if ($success = flash('success')): ?>
                <div class="flash success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error = flash('error')): ?>
                <div class="flash error"><?= e($error) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</body>
</html>
