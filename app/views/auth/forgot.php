<section class="auth-card">
    <h1>FORGOT PASSWORD</h1>
    <form method="post" action="<?= url('forgot') ?>" class="stack">
        <?= csrf_field() ?>
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <button class="btn btn-primary" type="submit">Send reset</button>
    </form>
    <p class="auth-links">
        <a href="<?= url('login') ?>">Back to login</a>
    </p>
</section>
