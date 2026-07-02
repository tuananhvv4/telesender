<section class="stack">
    <div class="topbar">
        <h1 class="page-title">Nhãn tin nhắn</h1>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h2 class="section-title"><?= $editLabel ? 'Cập nhật nhãn' : 'Tạo nhãn mới' ?></h2>
            <form class="form-grid" method="post" action="<?= e(url($editLabel ? '/labels/update' : '/labels')) ?>">
                <?= csrf_field() ?>
                <?php if ($editLabel): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editLabel['id']) ?>">
                <?php endif; ?>
                <div class="field">
                    <label for="name">Tên nhãn</label>
                    <input class="input" id="name" type="text" name="name" value="<?= e($editLabel['name'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label for="slug">Slug</label>
                    <input class="input mono" id="slug" type="text" name="slug" value="<?= e($editLabel['slug'] ?? '') ?>" placeholder="promo-morning" required>
                </div>
                <div class="field">
                    <label for="color">Màu hiển thị</label>
                    <input class="input mono" id="color" type="text" name="color" value="<?= e($editLabel['color'] ?? '#111827') ?>" placeholder="#0f766e">
                </div>
                <div class="actions">
                    <button class="button primary" type="submit"><?= $editLabel ? 'Cập nhật' : 'Tạo nhãn' ?></button>
                    <?php if ($editLabel): ?>
                        <a class="button secondary" href="<?= e(url('/labels')) ?>">Tạo mới</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Danh sách nhãn</h2>
            </div>
            <div class="panel-body list">
                <?php foreach ($labels as $label): ?>
                    <article class="list-item">
                        <div class="inline-actions">
                            <span class="badge" style="background: <?= e($label['color']) ?>20; color: <?= e($label['color']) ?>;">
                                <?= e($label['name']) ?>
                            </span>
                            <span class="small mono"><?= e($label['slug']) ?></span>
                        </div>
                        <div class="inline-actions">
                            <a class="button secondary" href="<?= e(url('/labels?edit=' . $label['id'])) ?>">Sửa</a>
                            <form method="post" action="<?= e(url('/labels/delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $label['id']) ?>">
                                <button class="button danger" type="submit">Xóa</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if ($labels === []): ?>
                    <div class="muted">Chưa có nhãn nào.</div>
                <?php endif; ?>
                <?php $perPageOptions = [10, 15, 20, 30, 50, 100]; ?>
                <?php require base_path('views/partials/pagination.php'); ?>
            </div>
        </section>
    </div>
</section>
