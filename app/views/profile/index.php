<?php /** @var array $profile */ ?>
<section class="profile-wrap">
    <a class="btn btn-danger logout-float" href="<?= url('logout') ?>"><i class="fa-solid fa-right-from-bracket"></i> Log out</a>
    <div class="profile-card">
        <div class="avatar">
            <i class="fa-solid fa-user"></i>
        </div>
        <h1><?= e($profile['name']) ?></h1>
        <p class="muted"><?= e($profile['email']) ?></p>
        <form method="post" action="<?= url('profile') ?>" class="stack">
            <?= csrf_field() ?>
            <label>
                Username
                <input type="text" name="name" value="<?= e($profile['name']) ?>" required>
            </label>
            <label>
                Phone number
                <input type="text" name="phone" value="<?= e($profile['phone'] ?? '') ?>">
            </label>
            <label>
                Home address
                <input type="text" name="address" value="<?= e($profile['address'] ?? '') ?>">
            </label>
            <button class="btn btn-primary" type="submit">Save changes</button>
        </form>
    </div>
</section>
