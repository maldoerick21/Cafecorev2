<?php
class AuthController extends Controller
{
    public function login(): void
    {
        if (auth()) {
            $role = auth()['role'] ?? '';
            redirect($role === 'admin' ? 'admin' : ($role === 'staff' ? 'staff/orders' : 'home'));
        }
        $this->view('auth/login', ['title' => 'Login'], 'auth');
    }
    public function loginPost(): void
    {
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $_SESSION['_old'] = ['email' => $email];
        $user = User::findByEmail($email);
        if (!$user || !empty($user['archived_at']) || !password_verify($password, $user['password'])) {
            flash('error', 'Those credentials do not match our records.');
            redirect('login');
        }
        $_SESSION['user'] = User::toSession($user);
        unset($_SESSION['_old']);
        flash('success', 'Welcome back, ' . $user['name'] . '.');
        $destination = $user['role'] === 'admin'
            ? 'admin'
            : ($user['role'] === 'staff' ? 'staff/orders' : ($_SESSION['_after_login'] ?? 'home'));
        unset($_SESSION['_after_login']);
        redirect($destination);
    }
    public function signup(): void
    {
        if (auth()) {
            redirect('home');
        }
        $this->view('auth/signup', ['title' => 'Sign up'], 'auth');
    }
    public function signupPost(): void
    {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $_SESSION['_old'] = compact('name', 'email');
        if ($name === '' || $email === '' || $password === '') {
            flash('error', 'Please complete every field.');
            redirect('signup');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
            redirect('signup');
        }
        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            redirect('signup');
        }
        if ($password !== $confirm) {
            flash('error', 'Passwords do not match.');
            redirect('signup');
        }
        if (User::findByEmail($email)) {
            flash('error', 'That email is already registered.');
            redirect('signup');
        }
        User::create(compact('name', 'email', 'password'));
        unset($_SESSION['_old']);
        flash('success', 'Account created. Please log in with your new account.');
        redirect('login');
    }
    public function forgot(): void
    {
        $this->view('auth/forgot', [
            'title' => 'Forgot password',
            'resetEmail' => $_SESSION['password_reset_email'] ?? '',
            'otpSent' => !empty($_SESSION['password_reset_user_id']),
        ], 'auth');
    }

    public function forgotPost(): void
    {
        verify_csrf();
        $action = $_POST['action'] ?? 'send_otp';

        if ($action === 'send_otp') {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Please enter a valid email address.');
                redirect('forgot');
            }

            $user = User::findByEmail($email);
            if (!$user) {
                flash('error', 'No account was found with that email address.');
                redirect('forgot');
            }

            // Limit OTP requests to one per 60 seconds for this browser session.
            $lastSent = (int) ($_SESSION['password_reset_last_sent'] ?? 0);
            if (time() - $lastSent < 60) {
                flash('error', 'Please wait a minute before requesting another OTP.');
                redirect('forgot');
            }

            $otp = (string) random_int(100000, 999999);
            $expiresAt = date('Y-m-d H:i:s', time() + 600);
            User::createPasswordReset((int) $user['id'], password_hash($otp, PASSWORD_DEFAULT), $expiresAt);
            try {
                Mailer::sendPasswordOtp($user['email'], $otp);
            } catch (Throwable $error) {

                $reset = User::getPasswordReset((int) $user['id']);
                if ($reset) {
                    Database::connect()->prepare('DELETE FROM password_reset_tokens WHERE id = ?')->execute([$reset['id']]);
                }
                error_log('CaféCore password reset email failed: ' . $error->getMessage());
                flash('error', 'We could not send the email. Check the SMTP settings and try again.');
                redirect('forgot');
            }

            $_SESSION['password_reset_user_id'] = (int) $user['id'];
            $_SESSION['password_reset_email'] = $user['email'];
            $_SESSION['password_reset_last_sent'] = time();
            flash('success', 'An OTP has been sent to your email. It expires in 10 minutes.');
            redirect('forgot');
        }

        if ($action === 'reset_password') {
            $email = trim($_POST['email'] ?? '');
            $otp = trim($_POST['otp'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['password_confirm'] ?? '';
            $userId = (int) ($_SESSION['password_reset_user_id'] ?? 0);
            $sessionEmail = (string) ($_SESSION['password_reset_email'] ?? '');
            $user = $userId > 0 ? User::find($userId) : null;
            $reset = $user ? User::getPasswordReset($userId) : null;

            if (!$user || $email === '' || strcasecmp($email, $sessionEmail) !== 0 || strcasecmp($user['email'], $sessionEmail) !== 0) {
                flash('error', 'Please request a new OTP using your account email.');
                unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_email']);
                redirect('forgot');
            }
            if (!preg_match('/^\d{6}$/', $otp)) {
                flash('error', 'Enter the 6-digit OTP from your email.');
                redirect('forgot');
            }
            if (strlen($password) < 8) {
                flash('error', 'Your new password must be at least 8 characters.');
                redirect('forgot');
            }
            if ($password !== $confirm) {
                flash('error', 'The passwords do not match.');
                redirect('forgot');
            }
            if (!$reset || strtotime($reset['expires_at']) < time() || (int) $reset['attempts'] >= 5) {
                flash('error', 'Your OTP has expired or reached its attempt limit. Request a new one.');
                unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_email']);
                redirect('forgot');
            }
            if (!password_verify($otp, $reset['otp_hash'])) {
                User::incrementPasswordResetAttempts((int) $reset['id']);
                flash('error', 'That OTP is incorrect. Please try again.');
                redirect('forgot');
            }

            User::updatePassword($userId, $password);
            unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_email'], $_SESSION['password_reset_last_sent']);
            flash('success', 'Your password has been reset. You can now log in with your new password.');
            redirect('login');
        }

        flash('error', 'Invalid password reset request.');
        redirect('forgot');
    }
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('success', 'You have been signed out.');
        redirect('home');
    }
}
