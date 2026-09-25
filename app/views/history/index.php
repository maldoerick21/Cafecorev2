<section>
    <h1>History</h1>
    <?php if (!$orders): ?>
        <div class="empty">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <p>No orders yet.</p>
        </div>
    <?php else: ?>
        <div class="history-list">
            <?php foreach ($orders as $order): ?>
                <article class="history-order">
                    <header>
                        <div>
                            <strong>Order #<?= (int) $order['id'] ?></strong>
                            <span><?= e(date('M j, Y · g:ia', strtotime($order['created_at']))) ?></span>
                        </div>
                        <em class="status status-<?= e($order['status']) ?>"><?= e($order['status']) ?></em>
                    </header>
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="history-row">
                            <div class="thumb-fallback"><i class="fa-solid fa-mug-hot"></i></div>
                            <div class="grow">
                                <strong><?= e($item['name']) ?></strong>
                                <span>Qty <?= (int) $item['qty'] ?> · <?= money((float) $item['price']) ?></span>
                            </div>
                            <strong><?= money((float) $item['price'] * $item['qty']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <footer>Total <?= money((float) $order['total']) ?></footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>