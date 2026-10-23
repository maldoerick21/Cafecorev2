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
        Cart::add($id, (int) ($_POST['qty'] ?? 1), (string) ($_POST['size'] ?? 'Medium'), (int) ($_POST['sugar'] ?? 100));
        flash('success', $item['name'] . ' added to your tray.');
        redirect($_POST['redirect'] ?? 'home');
    }
    public function update(): void
    {
        require_auth();
        verify_csrf();
        Cart::set((string) ($_POST['cart_key'] ?? ''), (int) ($_POST['qty'] ?? 1));
        redirect('checkout');
    }
    public function remove(): void
    {
        require_auth();
        verify_csrf();
        Cart::remove((string) ($_POST['cart_key'] ?? ''));
        redirect('checkout');
    }
    public function place(): void
    {
        $user = require_auth();
        verify_csrf();

        $paymentMethod = (string) ($_POST['payment_method'] ?? '');
        if ($paymentMethod !== 'counter') {
            flash('error', 'Please select Pay at the counter.');
            redirect('checkout');
        }
        $detail = Cart::detailed();
        if (!$detail['lines']) {
            flash('error', 'Your tray is empty.');
            redirect('checkout');
        }
        $catalog = [];
        foreach ($detail['lines'] as $line) {
            $catalog[$line['key']] = $line;
        }
        try {
            $orderId = Order::create($user['id'], Cart::items(), $catalog, $paymentMethod);
            Cart::clear();
            flash('success', 'Order #' . $orderId . ' placed. Pay at the counter when you pick it up.');
            redirect('history');
        } catch (Throwable $e) {
            flash('error', 'Could not place the order. Try again.');
            redirect('checkout');
        }
    }
}
