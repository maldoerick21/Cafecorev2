<?php

class OrderController extends Controller
{
    public function history(): void
    {
        $user = require_auth();
        $this->view('history/index', [
            'title' => 'History',
            'orders' => Order::forUser($user['id']),
            'cartCount' => Cart::count(),
        ]);
    }
}
