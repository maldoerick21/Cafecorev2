<?php
/** @var int $cartCount */
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? APP_NAME) ?> · <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/f43b637ad4.js" crossorigin="anonymous" rel="stylesheet"></script>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
</head>
<body class="app-body">
    <header class="navbar">
        <a class="brand" href="<?= url('home') ?>">
            <i class="fa-solid fa-mug-hot"></i>
            <span><?= APP_NAME ?></span>
        </a>
        <nav class="nav-actions">
            <a href="<?= url('home') ?>" class="<?= ($title ?? '') === 'Home' ? 'is-active' : '' ?>" title="Home"><i class="fa-solid fa-house"></i></a>
            <a href="<?= url('history') ?>" class="<?= ($title ?? '') === 'History' ? 'is-active' : '' ?>" title="History"><i class="fa-solid fa-clock-rotate-left"></i></a>
            <a href="<?= url('checkout') ?>" class="nav-cart <?= ($title ?? '') === 'Checkout' ? 'is-active' : '' ?>" title="Checkout">
                <i class="fa-solid fa-bag-shopping"></i>
                <?php if (($cartCount ?? 0) > 0): ?><span class="badge"><?= (int) $cartCount ?></span><?php endif; ?>
            </a>
        </nav>
        <?php $currentUser = auth() ?? []; ?>
        <a class="account-chip" href="<?= url('profile') ?>" title="Profile">
            <i class="fa-solid fa-user"></i>
            <span class="account-details">
                <span class="account-role"><?= e(ucfirst((string) ($currentUser['role'] ?? 'customer'))) ?></span>
                <span class="account-separator">|</span>
                <span class="account-name"><?= e($currentUser['name'] ?? 'Customer') ?></span>
            </span>
        </a>
    </header>
    <main class="page">
        <?php if ($msg = flash('error')): ?>
            <div class="toast toast-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="toast toast-success"><i class="fa-solid fa-circle-check"></i> <?= e($msg) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>