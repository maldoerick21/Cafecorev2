<?php /** @var array $item */ ?>
<section class="product-detail-wrap">
    <a class="back-link" href="<?= url('home') ?>"><i class="fa-solid fa-arrow-left"></i> Back to menu</a>
    <article class="product-detail-card">
        <div class="product-detail-photo">
            <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
            <?php if ($item['featured']): ?><span class="chip">Featured</span><?php endif; ?>
        </div>
        <div class="product-detail-info">
            <p class="eyebrow"><?= e($item['category']) ?></p>
            <h1><?= e($item['name']) ?></h1>
            <p class="product-description"><?= e($item['description'] ?: 'A CaféCore favorite, freshly prepared just for you.') ?></p>
            <div class="detail-price" id="detail-price" data-base-price="<?= (float) $item['price'] ?>"><?= money((float) $item['price'] + 15) ?></div>
            <form method="post" action="<?= url('cart/add') ?>" class="customize-form" id="customize-form">
                <?= csrf_field() ?>
                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="redirect" value="menu/item?id=<?= (int) $item['id'] ?>">
                <fieldset>
                    <legend>Choose your size</legend>
                    <div class="choice-grid size-choices">
                        <label class="choice-card"><input type="radio" name="size" value="Small" data-extra="0"><span><strong>Small</strong><small>12 oz · +₱0</small></span></label>
                        <label class="choice-card"><input type="radio" name="size" value="Medium" data-extra="15" checked><span><strong>Medium</strong><small>16 oz · +₱15</small></span></label>
                        <label class="choice-card"><input type="radio" name="size" value="Large" data-extra="25"><span><strong>Large</strong><small>22 oz · +₱25</small></span></label>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>Sugar level</legend>
                    <div class="choice-grid sugar-choices">
                        <?php foreach ([0,25,50,75,100] as $sugar): ?>
                            <label class="sugar-choice"><input type="radio" name="sugar" value="<?= $sugar ?>" <?= $sugar === 100 ? 'checked' : '' ?>><span><?= $sugar ?>%</span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <button class="btn btn-primary btn-block add-customized" type="submit"><i class="fa-solid fa-bag-shopping"></i> Add to tray</button>
            </form>
        </div>
    </article>
</section>
<script>
(() => {
    const form = document.getElementById('customize-form');
    const price = document.getElementById('detail-price');
    const base = Number(price.dataset.basePrice || 0);
    const format = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(value);
    const update = () => {
        const selected = form.querySelector('input[name="size"]:checked');
        price.textContent = format(base + Number(selected?.dataset.extra || 0));
    };
    form.querySelectorAll('input').forEach(input => input.addEventListener('change', update));
    update();
})();
</script>
