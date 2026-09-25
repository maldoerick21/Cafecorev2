<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Admin') ?> · <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/f43b637ad4.js" crossorigin="anonymous" rel="stylesheet"></script>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="admin-body">
    <aside class="admin-sidebar">
        <a class="brand brand-compact" href="<?= url('admin') ?>">
            <i class="fa-solid fa-mug-hot"></i>
        </a>
        <nav>
            <a href="<?= url('admin') ?>" class="<?= ($title ?? '') === 'Dashboard' ? 'is-active' : '' ?>" title="Dashboard"><i class="fa-solid fa-chart-pie"></i></a>
            <a href="<?= url('admin/menu') ?>" class="<?= in_array($title ?? '', ['Menu management', 'Edit item', 'New item'], true) ? 'is-active' : '' ?>" title="Menu"><i class="fa-solid fa-utensils"></i></a>
            <a href="<?= url('home') ?>" title="Storefront"><i class="fa-solid fa-store"></i></a>
        </nav>
        <a class="sidebar-logout" href="<?= url('logout') ?>" title="Log out"><i class="fa-solid fa-right-from-bracket"></i></a>
    </aside>
    <div class="admin-frame">
        <header class="admin-top">
            <strong><?= APP_NAME ?></strong>
            <span><?= e(auth()['name'] ?? 'Admin') ?></span>
        </header>
        <main class="admin-page">
            <?php if ($msg = flash('error')): ?>
                <div class="toast toast-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('success')): ?>
                <div class="toast toast-success"><i class="fa-solid fa-circle-check"></i> <?= e($msg) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</body>
</html>