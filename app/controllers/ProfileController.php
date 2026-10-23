<?php

class ProfileController extends Controller
{
    public function index(): void
    {
        $user = $this->currentUser();
        $fresh = User::find($user['id']);
        $this->view('profile/index', [
            'title' => 'Profile',
            'profile' => $fresh,
            'cartCount' => Cart::count(),
        ], in_array($user['role'], ['admin', 'staff'], true) ? 'admin' : 'main');
    }

    public function update(): void
    {
        $user = $this->currentUser();
        verify_csrf();
        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
        $address = trim(is_string($_POST['address'] ?? null) ? $_POST['address'] : '');
        $nameLength = preg_match_all('/./us', $name);
        if ($name === '' || $nameLength === false || $nameLength > 150) {
            flash('error', 'Enter a name no longer than 150 characters.');
            redirect('profile');
        }
        if (strlen($phone) > 40 || strlen($address) > 65535) {
            flash('error', 'Phone number or address is too long.');
            redirect('profile');
        }
        User::update($user['id'], compact('name', 'phone', 'address'));
        $updatedUser = User::find($user['id']);
        if (!$updatedUser) {
            unset($_SESSION['user']);
            flash('error', 'Your account is no longer available. Please sign in again.');
            redirect('login');
        }
        $_SESSION['user'] = User::toSession($updatedUser);
        flash('success', 'Profile saved.');
        redirect('profile');
    }

    private function currentUser(): array
    {
        $user = require_auth();
        $fresh = User::find((int) ($user['id'] ?? 0));
        if (!$fresh) {
            unset($_SESSION['user']);
            flash('error', 'Your account is no longer available. Please sign in again.');
            redirect('login');
        }
        $_SESSION['user'] = User::toSession($fresh);
        return $_SESSION['user'];
    }
}
