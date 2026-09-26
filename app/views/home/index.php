<?php 
/** @var array $featured */
/** @var array $categories */
/** @var array $items */ 
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Today’s pour</p>
        <h1>A quieter cup, made to linger.</h1>
        <p>Four house signatures on rotation. Add them to your tray and pick up at the counter.</p>
    </div>
    <div class="hero-stack">
        <?php foreach (array_slice($featured, 0, 3) as $i => $item): ?>
            <article class="hero-card" style="--i: <?= $i ?>">
                <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
                <div>
                    <strong><?= e($item['name']) ?></strong>
                    <span><?= money((float) $item['price']) ?></span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<section class="featured">
    <div class="section-head">
        <h2>Featured</h2>
        <div class="pills">
            <button class="pill is-active" data-filter="all">All</button>
            <?php foreach ($categories as $cat): ?>
                <button class="pill" data-filter="<?= e($cat) ?>"><?= e($cat) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="item-grid">
        <?php foreach ($items as $item): ?>
            <article class="item-card" data-category="<?= e($item['category']) ?>">
                <div class="item-photo">
                    <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
                    <?php if ($item['featured']): ?><span class="chip">Featured</span><?php endif; ?>
                </div>
                <div class="item-body">
                    <h3><?= e($item['name']) ?></h3>
                    <p><?= e($item['description']) ?></p>
                    <div class="item-meta">
                        <strong><?= money((float) $item['price']) ?></strong>
                        <form method="post" action="<?= url('cart/add') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button class="icon-btn" type="submit" title="Add to tray"><i class="fa-solid fa-plus"></i></button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>