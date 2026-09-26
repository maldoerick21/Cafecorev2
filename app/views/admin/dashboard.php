<?php 
/** @var array $stats */ 
/** @var array $customers */
/** @var array $recent */
?>
<section>
    <h1>Dashboard overview</h1>
    <div class="stat-grid">
        <article class="stat">
            <span>Today</span>
            <strong><?= money($stats['today']) ?></strong>
        </article>
        <article class="stat">
            <span>Revenue</span>
            <strong><?= money($stats['revenue']) ?></strong>
        </article>
        <article class="stat">
            <span>Orders</span>
            <strong><?= (int) $stats['count'] ?></strong>
        </article>
        <article class="stat">
            <span>Guests</span>
            <strong><?= (int) $customers ?></strong>
        </article>
    </div>
    <div class="dash-grid">
        <article class="panel">
            <h2>Sales mix</h2>
            <ul class="bar-list">
                <?php
                $max = max(array_column($stats['top'] ?: [['sold' => 1]], 'sold')) ?: 1;
                foreach ($stats['top'] as $row):
                    $pct = ((int) $row['sold'] / $max) * 100;
                ?>
                    <li>
                        <div class="bar-meta">
                            <span><?= e($row['name']) ?></span>
                            <span><?= (int) $row['sold'] ?></span>
                        </div>
                        <div class="bar"><i style="width: <?= $pct ?>%"></i></div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$stats['top']): ?><p class="muted">No sales yet.</p><?php endif; ?>
            </ul>
        </article>
        <article class="panel pie-panel">
            <h2>Status</h2>
            <?php
            $totalStatus = max(1, array_sum($stats['statusMap']));
            $prep = $stats['statusMap']['preparing'] / $totalStatus * 100;
            $done = $stats['statusMap']['completed'] / $totalStatus * 100;
            ?>
            <div class="pie" style="background: conic-gradient(var(--gold) 0 <?= $done ?>%, var(--ink) <?= $done ?>% <?= $done + $prep ?>%, #c9c1b4 <?= $done + $prep ?>% 100%);"></div>
            <ul class="legend">
                <li><i class="dot gold"></i> Completed (<?= (int) $stats['statusMap']['completed'] ?>)</li>
                <li><i class="dot ink"></i> Preparing (<?= (int) $stats['statusMap']['preparing'] ?>)</li>
                <li><i class="dot mute"></i> Cancelled (<?= (int) $stats['statusMap']['cancelled'] ?>)</li>
            </ul>
        </article>
    </div>
    <article class="panel">
        <h2>Recent orders</h2>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Guest</th><th>Total</th><th>Status</th><th>When</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td>#<?= (int) $row['id'] ?></td>
                    <td><?= e($row['customer']) ?></td>
                    <td><?= money((float) $row['total']) ?></td>
                    <td><em class="status status-<?= e($row['status']) ?>"><?= e($row['status']) ?></em></td>
                    <td><?= e(date('M j, g:ia', strtotime($row['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>