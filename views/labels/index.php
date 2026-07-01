<section class="stack">
    <div class="topbar">
        <div>
            <h1 class="page-title">Message Labels</h1>
            <p class="page-subtitle">Nhóm các loại tin nhắn theo nhãn như `promo`, `reminder`, `report`, `sale`, giúp lọc log và tổ chức template rõ ràng hơn.</p>
        </div>
        <span class="badge info">Message Taxonomy</span>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h2 class="section-title"><?= $editLabel ? 'Cập nhật label' : 'Tạo label mới' ?></h2>
            <form class="form-grid" method="post" action="<?= e(url($editLabel ? '/labels/update' : '/labels')) ?>">
                <?= csrf_field() ?>
                <?php if ($editLabel): ?>
                    <input type="hidden" name="id" value="<?= e((string) $editLabel['id']) ?>">
                <?php endif; ?>
                <div class="field">
                    <label for="name">Tên label</label>
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
                    <button class="button primary" type="submit"><?= $editLabel ? 'Cập nhật' : 'Tạo label' ?></button>
                    <?php if ($editLabel): ?>
                        <a class="button secondary" href="<?= e(url('/labels')) ?>">Tạo mới</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Danh sách labels</h2>
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
                    <div class="muted">Chưa có label nào.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>
