<?php

class ProfileController extends Controller
{
    public function index(): void
    {
        $user = require_auth();
        $fresh = User::find($user['id']);
        $this->view('profile/index', [
            'title' => 'Profile',
            'profile' => $fresh,
            'cartCount' => Cart::count(),
        ]);
    }

    public function update(): void
    {
        $user = require_auth();
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if ($name === '') {
            flash('error', 'Name cannot be empty.');
            redirect('profile');
        }
        User::update($user['id'], compact('name', 'phone', 'address'));
        $_SESSION['user'] = User::toSession(User::find($user['id']));
        flash('success', 'Profile saved.');
        redirect('profile');
    }
}
