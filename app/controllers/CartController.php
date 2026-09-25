<?php
class CartController extends Controller
{
    public function index(): void
    {
        require_auth();
        $this->view('checkout/index', [
            'title' => 'Checkout',
            'cart' => Cart::detailed(),
            'cartCount' => Cart::count(),
        ]);
    }
    public function add(): void
    {
        require_auth();
        verify_csrf();
        $id = (int) ($_POST['item_id'] ?? 0);
        $item = MenuItem::find($id);
        if (!$item || !$item['available']) {
            flash('error', 'That item is not available.');
            redirect('home');
        }
        Cart::add($id, (int) ($_POST['qty'] ?? 1));
        flash('success', $item['name'] . ' added to your tray.');
        redirect($_POST['redirect'] ?? 'home');
    }
    public function update(): void
    {
        require_auth();
        verify_csrf();
        Cart::set((int) ($_POST['item_id'] ?? 0), (int) ($_POST['qty'] ?? 1));
        redirect('checkout');
    }
    public function remove(): void
    {
        require_auth();
        verify_csrf();
        Cart::remove((int) ($_POST['item_id'] ?? 0));
        redirect('checkout');
    }
    public function place(): void
    {
        $user = require_auth();
        verify_csrf();
        $detail = Cart::detailed();
        if (!$detail['lines']) {
            flash('error', 'Your tray is empty.');
            redirect('checkout');
        }
        $catalog = [];
        foreach ($detail['lines'] as $line) {
            $catalog[$line['item']['id']] = $line['item'];
        }
        try {
            $orderId = Order::create($user['id'], Cart::items(), $catalog);
            Cart::clear();
            flash('success', 'Order #' . $orderId . ' is in the kitchen.');
            redirect('history');
        } catch (Throwable $e) {
            flash('error', 'Could not place the order. Try again.');
            redirect('checkout');
        }
    }
}