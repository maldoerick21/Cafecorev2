<?php
class AuthController extends Controller
{
    public function login(): void
    {
        if (auth()) {
            redirect(auth()['role'] === 'admin' ? 'admin' : 'home');
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
        if (!$user || !password_verify($password, $user['password'])) {
            flash('error', 'Those credentials do not match our records.');
            redirect('login');
        }
        $_SESSION['user'] = User::toSession($user);
        unset($_SESSION['_old']);
        flash('success', 'Welcome back, ' . $user['name'] . '.');
        redirect($user['role'] === 'admin' ? 'admin' : 'home');
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
        if (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
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
        $id = User::create(compact('name', 'email', 'password'));
        $user = User::find($id);
        $_SESSION['user'] = User::toSession($user);
        unset($_SESSION['_old']);
        flash('success', 'Account created. Your table is ready.');
        redirect('home');
    }
    public function forgot(): void
    {
        $this->view('auth/forgot', ['title' => 'Forgot password'], 'auth');
    }
    public function forgotPost(): void
    {
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        flash('success', 'If an account exists for ' . $email . ', a reset link is on its way.');
        redirect('forgot');
    }
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('success', 'You have been signed out.');
        redirect('login');
    }
}