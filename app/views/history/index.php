<?php /** @var array $orders */ ?>
<?php /** @var int $processingOrderId */ ?>
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
                    <?php if ((int) $processingOrderId === (int) $order['id'] && $order['status'] === 'preparing'): ?>
                        <div class="order-processing" data-order-id="<?= (int) $order['id'] ?>" aria-live="polite">
                            <span class="processing-label">Preparing your order</span> · <span class="processing-timer"><span class="processing-countdown">7</span>s</span>
                            <div class="order-progress"><span></span></div>
                        </div>
                    <?php endif; ?>
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
<?php if ($processingOrderId): ?>
    <script>
        const processing = document.querySelector('.order-processing');
        if (processing) {
            const countdown = processing.querySelector('.processing-countdown');
            const progress = processing.querySelector('.order-progress span');
            const status = processing.closest('.history-order').querySelector('.status');
            const orderId = processing.dataset.orderId;
            const csrfToken = <?= json_encode(csrf_token()) ?>;
            let remaining = 7;
            requestAnimationFrame(() => { progress.style.width = '100%'; });
            const timer = setInterval(() => {
                remaining -= 1;
                countdown.textContent = remaining;
                if (remaining <= 0) {
                    clearInterval(timer);
                    fetch('<?= e(url('orders/complete')) ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ order_id: orderId, csrf_token: csrfToken })
                    }).then((response) => response.json()).then((result) => {
                        if (result.completed) {
                            status.textContent = 'completed';
                            status.classList.remove('status-preparing');
                            status.classList.add('status-completed');
                            processing.querySelector('.processing-label').textContent = 'Order completed';
                            processing.querySelector('.processing-timer').hidden = true;
                        }
                    }).catch(() => {
                        processing.querySelector('.processing-label').textContent = 'Status update failed. Refresh to check.';
                    });
                }
            }, 1000);
        }
    </script>
<?php endif; ?>