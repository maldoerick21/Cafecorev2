<?php /** @var array $items */ ?>
<section>
    <div class="section-head">
        <h1>Menu management</h1>
        <a class="btn btn-primary" href="<?= url('admin/menu/create') ?>"><i class="fa-solid fa-plus"></i> Add item</a>
    </div>
    <div class="menu-admin-list">
        <?php foreach ($items as $item): ?>
            <article class="edit-item-row">
                <img src="<?= e($item['image']) ?>" alt="">
                <div class="grow">
                    <strong><?= e($item['name']) ?></strong>
                    <span><?= e($item['category']) ?> · <?= money((float) $item['price']) ?></span>
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