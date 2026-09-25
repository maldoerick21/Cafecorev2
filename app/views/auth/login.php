<section class="auth-card">
    <h1>LOGIN</h1>
    <form method="post" action="<?= url('login') ?>" class="stack">
        <?= csrf_field() ?>
        <label>
            Email
            <input type="email" name="email" value="<?= old('email', 'customer@cafecore.test') ?>" required>
        </label>
        <label>
            Password
            <input type="password" name="password" value="password" required>
        </label>
        <button class="btn btn-primary" type="submit">Login</button>
    </form>
    <p class="auth-links">
        <a href="<?= url('signup') ?>">Create an account</a>
        <a href="<?= url('forgot') ?>">Forgot password?</a>
    </p>
</section>
