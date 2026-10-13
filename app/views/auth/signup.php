<section class="auth-card">
    <h1>SIGN UP</h1>
    <form method="post" action="<?= url('signup') ?>" class="stack">
        <?= csrf_field() ?>
        <label>
            Username
            <input type="text" name="name" placeholder="Username" required>
        </label>
        <label>
            Email
            <input type="email" name="email" placeholder="Email" required>
        </label>
        <label>
            Password
            <input id="password" type="password" name="password" placeholder="Password" required>
        </label>
        <label>
            Confirm password
            <input id="cpassword" type="password" name="password_confirm" placeholder="Confirm Password" required>
        </label>
        
        <div class="show-forgot">
            <label><input type="checkbox" id="show-hide" onclick="togglePassword()"> Show Password</label>
            <script>
                function togglePassword() {

                    let password = document.getElementById("password");
                    let cpassword = document.getElementById("cpassword");
                    let checkbox = document.getElementById("show-hide");

                    if (checkbox.checked) {
                        password.type = "text";
                        cpassword.type = "text";
                    } else {
                        password.type = "password";
                        cpassword.type = "password";
                    }
                }
            </script>
        </div>
        <button class="btn btn-primary" type="submit">Sign up</button>
    </form>
    <p class="auth-links">
        <a href="<?= url('login') ?>">Already have an account?</a>
    </p>
</section>