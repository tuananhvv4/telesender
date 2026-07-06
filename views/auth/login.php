<span class="badge info">TeleSender</span>
<h1 class="auth-heading">Đăng nhập hệ thống</h1>

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

<?php if (!empty($showRegisterLink)): ?>
    <p class="small muted">Chưa có tài khoản? <a href="<?= e(url('/register')) ?>"><strong><?= e((string) ($registerLinkLabel ?? 'Đăng ký')) ?></strong></a></p>
<?php endif; ?>
