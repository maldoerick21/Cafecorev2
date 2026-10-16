<?php /** @var array $items */ ?>
<section>
    <div class="section-head">
        <h1>Menu management</h1>
        <a class="btn btn-primary" href="<?= url('admin/menu/create') ?>"><i class="fa-solid fa-plus"></i> Add item</a>
    </div>
    <?php
        $menuCategories = [];
        foreach ($items as $menuItem) {
            $categoryName = trim((string) ($menuItem['category'] ?? ''));
            if ($categoryName !== '' && !in_array($categoryName, $menuCategories, true)) {
                $menuCategories[] = $categoryName;
            }
        }
        sort($menuCategories, SORT_NATURAL | SORT_FLAG_CASE);
    ?>
    <nav class="menu-category-filters" aria-label="Filter menu by category">
        <button type="button" class="menu-category-filter is-active" data-category-filter="all" aria-pressed="true">All</button>
        <?php foreach ($menuCategories as $categoryName): ?>
            <button type="button" class="menu-category-filter" data-category-filter="<?= e($categoryName) ?>" aria-pressed="false"><?= e($categoryName) ?></button>
        <?php endforeach; ?>
    </nav>
    <div class="menu-admin-list">
        <?php foreach ($items as $item): ?>
            <article class="edit-item-row" data-menu-category="<?= e(trim((string) ($item['category'] ?? ''))) ?>">
                <img src="<?= e($item['image']) ?>" alt="">
                <div class="grow">
                    <strong><?= e($item['name']) ?></strong>
                    <span><?= e($item['category']) ?> - <?= money((float) $item['price']) ?></span>
                </div>
                <form method="post" action="<?= url('admin/menu/toggle') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="field" value="available">
                    <label class="switch">
                        <input type="checkbox" <?= $item['available'] ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span></span>
                    </label>
                </form>
                <a class="icon-btn" href="<?= url('admin/menu/edit?id=' . (int) $item['id']) ?>"><i class="fa-solid fa-pen"></i></a>
                <form method="post" action="<?= url('admin/menu/delete') ?>" onsubmit="return confirm('Remove this item?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <button class="ghost" type="submit"><i class="fa-solid fa-trash"></i></button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<script>
(() => {
    const filters = document.querySelectorAll('[data-category-filter]');
    const rows = document.querySelectorAll('.menu-admin-list [data-menu-category]');
    if (!filters.length || !rows.length) return;

    filters.forEach((button) => {
        button.addEventListener('click', () => {
            const selected = button.dataset.categoryFilter;
            filters.forEach((filter) => {
                const active = filter === button;
                filter.classList.toggle('is-active', active);
                filter.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            rows.forEach((row) => {
                row.hidden = selected !== 'all' && row.dataset.menuCategory !== selected;
            });
        });
    });
})();
</script>
