<span class="badge info">TeleSender</span>
<h1 class="auth-heading"><?= e((string) ($registerHeading ?? 'Tạo người dùng mới')) ?></h1>

<?php if ($error = flash('error')): ?>
    <div class="flash error"><?= e($error) ?></div>
<?php endif; ?>

<form class="form-grid" method="post" action="<?= e(url('/register')) ?>">
    <?= csrf_field() ?>
    <div class="field">
        <label for="name">Họ tên</label>
        <input class="input" id="name" type="text" name="name" value="<?= e((string) old('name')) ?>" required>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input class="input" id="email" type="email" name="email" value="<?= e((string) old('email')) ?>" required>
    </div>
    <div class="field">
        <label for="password">Mật khẩu</label>
        <input class="input" id="password" type="password" name="password" required>
    </div>
    <div class="field">
        <label for="password_confirmation">Xác nhận mật khẩu</label>
        <input class="input" id="password_confirmation" type="password" name="password_confirmation" required>
    </div>
    <button class="button primary" type="submit">Tạo tài khoản</button>
</form>

<p class="small muted">Đã có tài khoản? <a href="<?= e(url('/login')) ?>"><strong>Đăng nhập</strong></a></p>
