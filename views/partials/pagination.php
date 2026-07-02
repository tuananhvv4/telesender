<?php

declare(strict_types=1);

$pagination = $pagination ?? null;

if (!is_array($pagination)) {
    return;
}

$page = (int) ($pagination['page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$series = pagination_series($pagination);
$perPage = (int) ($pagination['per_page'] ?? 20);
$perPageOptions = $perPageOptions ?? [10, 15, 20, 30, 50, 100];
$currentQuery = request()->queryParams();
?>
<div class="pagination-shell">
    <div class="pagination-meta">
        Hiển thị <?= e((string) ($pagination['from'] ?? 0)) ?>-<?= e((string) ($pagination['to'] ?? 0)) ?>
        / <?= e((string) ($pagination['total'] ?? 0)) ?> mục
    </div>

    <div class="pagination-controls">
        <form class="pagination-inline-form" method="get" action="<?= e(url(request()->path())) ?>">
            <?php foreach ($currentQuery as $key => $value): ?>
                <?php if (in_array($key, ['page', 'per_page'], true)): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
            <?php endforeach; ?>
            <label class="pagination-label" for="pagination_per_page">Mỗi trang</label>
            <select class="select pagination-select" id="pagination_per_page" name="per_page" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $option): ?>
                    <option value="<?= e((string) $option) ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($totalPages > 1): ?>
            <form class="pagination-inline-form" method="get" action="<?= e(url(request()->path())) ?>">
                <?php foreach ($currentQuery as $key => $value): ?>
                    <?php if ($key === 'page'): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                <?php endforeach; ?>
                <label class="pagination-label" for="pagination_page_jump">Tới trang</label>
                <input class="input pagination-jump-input" id="pagination_page_jump" type="number" name="page" min="1" max="<?= e((string) $totalPages) ?>" value="<?= e((string) $page) ?>">
                <button class="button secondary pagination-jump-button" type="submit">Đi</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Phân trang">
            <a class="pagination-link <?= !empty($pagination['has_prev']) ? '' : 'disabled' ?>" href="<?= e(!empty($pagination['has_prev']) ? pagination_url((int) $pagination['prev_page']) : '#') ?>">Trước</a>
            <?php foreach ($series as $item): ?>
                <?php if ($item === '...'): ?>
                    <span class="pagination-gap">...</span>
                <?php else: ?>
                    <?php $itemPage = (int) $item; ?>
                    <a class="pagination-link <?= $itemPage === $page ? 'active' : '' ?>" href="<?= e(pagination_url($itemPage)) ?>"><?= e((string) $itemPage) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a class="pagination-link <?= !empty($pagination['has_next']) ? '' : 'disabled' ?>" href="<?= e(!empty($pagination['has_next']) ? pagination_url((int) $pagination['next_page']) : '#') ?>">Sau</a>
        </nav>
    <?php endif; ?>
</div>
