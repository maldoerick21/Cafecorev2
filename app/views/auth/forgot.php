<section class="auth-card">
    <h1>RESET PASSWORD</h1>
    <p class="hint">We will email a 6-digit code to your registered email address.</p>

    <?php if (empty($otpSent)): ?>
        <form method="post" action="<?= url('forgot') ?>" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_otp">
            <label>
                Email
                <input type="email" name="email" value="<?= old('email') ?>" placeholder="you@example.com" autocomplete="email" required>
            </label>
            <button class="btn btn-primary" type="submit">Send OTP</button>
        </form>
    <?php else: ?>
        <p class="hint">OTP sent to <strong><?= e($resetEmail) ?></strong>. Check your inbox and spam folder.</p>
        <form method="post" action="<?= url('forgot') ?>" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <label>
                Email
                <input type="email" name="email" value="<?= e($resetEmail) ?>" autocomplete="email" required>
            </label>
            <label>
                OTP
                <input type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" required>
            </label>
            <label>
                New password
                <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
            </label>
            <label>
                Confirm password
                <input type="password" name="password_confirm" minlength="8" autocomplete="new-password" placeholder="Enter password again" required>
            </label>
            <button class="btn btn-primary" type="submit">Reset password</button>
        </form>
        <form method="post" action="<?= url('forgot') ?>" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_otp">
            <input type="hidden" name="email" value="<?= e($resetEmail) ?>">
            <button class="btn" type="submit">Send a new OTP</button>
        </form>
    <?php endif; ?>

    <p class="auth-links"><a href="<?= url('login') ?>">Back to login</a></p>
</section>
