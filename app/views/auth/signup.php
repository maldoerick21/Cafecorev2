<section class="auth-card">
    <h1>SIGN UP</h1>
    <form method="post" action="<?= url('signup') ?>" class="stack">
        <?= csrf_field() ?>
        <label>
            Username
            <input type="text" name="name" value="<?= old('name') ?>" required>
        </label>
        <label>
            Email
            <input type="email" name="email" value="<?= old('email') ?>" required>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <label>
            Confirm password
            <input type="password" name="password_confirm" required>
        </label>
        <button class="btn btn-primary" type="submit">Sign up</button>
    </form>
    <p class="auth-links">
        <a href="<?= url('login') ?>">Already have an account?</a>
    </p>
</section>