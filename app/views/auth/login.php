<section class="auth-card">
    <h1>LOGIN</h1>
    <form method="post" action="<?= url('login') ?>" class="stack">
        <?= csrf_field() ?>
        <label>
            Email
            <input type="email" name="email" placeholder="Email" required>
        </label>
        <label>
            Password
            <input id="password" type="password" name="password" placeholder="Password" required>
            
        </label>
        <div class="show-forgot">
            <label><input type="checkbox" id="show-hide" onclick="togglePassword()"> Show Password</label>
            <script>
                function togglePassword() {

                    let password = document.getElementById("password");
                    let checkbox = document.getElementById("show-hide");

                    if (checkbox.checked) {
                        password.type = "text";
                    } else {
                        password.type = "password";
                    }
                }
            </script>
            <a href="<?= url('forgot') ?>">Forgot password?</a>
        </div>
        <button class="btn btn-primary" type="submit">Login</button>
    </form>
    <p class="auth-links">
        <a href="<?= url('signup') ?>">Create an account</a>
       
    </p>
</section>
