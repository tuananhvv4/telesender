<span class="badge info">TeleSender</span>
<h1 class="auth-heading">Đăng nhập hệ thống</h1>
<p class="auth-copy">Quản lý nhiều tài khoản Telegram cá nhân, nhiều nhóm và lịch gửi cố định qua endpoint cron.</p>

<?php if ($success = flash('success')): ?>
    <div class="flash success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error = flash('error')): ?>
    <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<form class="form-grid" method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input class="input" id="email" type="email" name="email" value="<?= e((string) old('email')) ?>" required>
    </div>
    <div class="field">
        <label for="password">Mật khẩu</label>
        <input class="input" id="password" type="password" name="password" required>
    </div>
    <button class="button primary" type="submit">Đăng nhập</button>
</form>

<?php if (config('app.allow_registration', true)): ?>
    <p class="small muted">Chưa có tài khoản? <a href="<?= e(url('/register')) ?>"><strong>Đăng ký</strong></a></p>
<?php endif; ?>
