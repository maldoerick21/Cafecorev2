<?php

class OrderController extends Controller
{
    public function history(): void
    {
        $user = require_auth();
        $staffLayout = in_array($user['role'] ?? '', ['admin', 'staff'], true);
        if ($staffLayout) {
            $user = require_staff();
        }
        $this->view('history/index', [
            'title' => $staffLayout ? 'Order history' : 'History',
            'orders' => Order::forUser($user['id']),
            'cartCount' => Cart::count(),
            'staffLayout' => $staffLayout,
        ], $staffLayout ? 'admin' : 'main');
    }
}
