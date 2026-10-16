<?php /** @var string $content */ ?>
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
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="admin-body">
    <aside class="admin-sidebar">
        <a class="brand brand-compact" href="<?= url('admin') ?>">
            <i class="fa-solid fa-mug-hot"></i>
        </a>
        <button class="admin-nav-toggle" type="button" aria-expanded="false" aria-controls="admin-nav-panel" aria-label="Open admin navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="admin-nav-panel" id="admin-nav-panel">
            <nav>
                <a href="<?= url('admin') ?>" class="<?= ($title ?? '') === 'Dashboard' ? 'is-active' : '' ?>" title="Dashboard"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
                <a href="<?= url('admin/menu') ?>" class="<?= in_array($title ?? '', ['Menu management', 'Edit item', 'New item'], true) ? 'is-active' : '' ?>" title="Menu"><i class="fa-solid fa-utensils"></i><span>Menu</span></a>
            </nav>
            <a class="sidebar-logout" href="<?= url('logout') ?>" title="Log out"><i class="fa-solid fa-right-from-bracket"></i><span>Log out</span></a>
        </div>
    </aside>
    <div class="admin-frame">
        <header class="admin-top">
            <strong><?= APP_NAME ?></strong>
            <?php $currentUser = auth() ?? []; ?>
            <div class="admin-account" aria-label="Signed in account">
                <span class="admin-account-role"><?= e(ucfirst((string) ($currentUser['role'] ?? 'admin'))) ?></span>
                <span class="admin-account-separator">|</span>
                <span class="admin-account-name"><?= e($currentUser['name'] ?? 'Admin') ?></span>
            </div>
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
    <script src="<?= asset('js/flash.js') ?>"></script>
    <script>
        const navToggle = document.querySelector('.admin-nav-toggle');
        const navPanel = document.getElementById('admin-nav-panel');
        navToggle?.addEventListener('click', () => {
            const expanded = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', String(!expanded));
            navToggle.setAttribute('aria-label', expanded ? 'Open admin navigation' : 'Close admin navigation');
            navToggle.innerHTML = `<i class="fa-solid fa-${expanded ? 'bars' : 'xmark'}"></i>`;
            navPanel?.classList.toggle('is-open', !expanded);
        });
        navPanel?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                navToggle?.setAttribute('aria-expanded', 'false');
                navToggle?.setAttribute('aria-label', 'Open admin navigation');
                navToggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
                navPanel.classList.remove('is-open');
            });
        });
    </script>
</body>
</html>