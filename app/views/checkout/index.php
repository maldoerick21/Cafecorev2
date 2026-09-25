<section class="checkout-layout">
    <div>
        <h1>Check out</h1>
        <?php if (!$cart['lines']): ?>
            <div class="empty">
                <i class="fa-solid fa-mug-saucer"></i>
                <p>Your tray is empty.</p>
                <a class="btn btn-primary" href="<?= url('home') ?>">Browse the menu</a>
            </div>
        <?php else: ?>
            <ul class="checkout-list">
                <?php foreach ($cart['lines'] as $line): ?>
                    <li class="checkout-row">
                        <img src="<?= e($line['item']['image']) ?>" alt="">
                        <div class="grow">
                            <strong><?= e($line['item']['name']) ?></strong>
                            <span><?= money((float) $line['item']['price']) ?></span>
                        </div>
                        <form method="post" action="<?= url('cart/update') ?>" class="qty-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= (int) $line['item']['id'] ?>">
                            <button name="qty" value="<?= $line['qty'] - 1 ?>" type="submit">−</button>
                            <span><?= (int) $line['qty'] ?></span>
                            <button name="qty" value="<?= $line['qty'] + 1 ?>" type="submit">+</button>
                        </form>
                        <strong class="line-total"><?= money((float) $line['line']) ?></strong>
                        <form method="post" action="<?= url('cart/remove') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= (int) $line['item']['id'] ?>">
                            <button class="ghost" type="submit"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <aside class="summary-card">
        <h2>Summary</h2>
        <dl>
            <div><dt>Subtotal</dt><dd><?= money($cart['subtotal']) ?></dd></div>
            <div><dt>Service</dt><dd><?= money($cart['service']) ?></dd></div>
            <div class="total"><dt>Total</dt><dd><?= money($cart['total']) ?></dd></div>
        </dl>
        <form method="post" action="<?= url('checkout') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-block" type="submit" <?= $cart['lines'] ? '' : 'disabled' ?>>
                Place order
            </button>
        </form>
    </aside>
</section>