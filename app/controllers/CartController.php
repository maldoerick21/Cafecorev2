<?php
class CartController extends Controller
{
    public function index(): void
    {
        $user = require_auth();
        $this->view('checkout/index', [
            'title' => 'Checkout',
            'cart' => Cart::detailed(),
            'deliveryAddress' => $user['address'] ?? '',
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
        $customization = MenuItem::customizationOptions((string) $item['category']);
        $requestedSize = is_string($_POST['size'] ?? null) ? $_POST['size'] : '';
        $size = !$customization['size'] ? 'Standard' : (in_array($requestedSize, ['Small', 'Medium', 'Large'], true) ? $requestedSize : 'Medium');
        $sugarValue = $_POST['sugar'] ?? 100;
        $requestedSugar = is_scalar($sugarValue) ? filter_var($sugarValue, FILTER_VALIDATE_INT) : false;
        $sugar = !$customization['sugar'] ? -1 : (in_array($requestedSugar, [0, 25, 50, 75, 100], true) ? $requestedSugar : 100);
        Cart::add($id, (int) ($_POST['qty'] ?? 1), $size, $sugar);
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
            flash('error', 'Cash on delivery is the only available payment method.');
            redirect('checkout');
        }
        $fulfillmentType = (string) ($_POST['fulfillment_type'] ?? '');
        if ($fulfillmentType !== 'delivery') {
            flash('error', 'Delivery is the only available order type.');
            redirect('checkout');
        }
        $deliveryAddress = trim(is_string($_POST['delivery_address'] ?? null) ? $_POST['delivery_address'] : '');
        if ($fulfillmentType === 'delivery' && $deliveryAddress === '') {
            flash('error', 'Enter a delivery address or choose pickup.');
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
            $orderId = Order::create($user['id'], Cart::items(), $catalog, $paymentMethod, $fulfillmentType, $deliveryAddress);
            Cart::clear();
            $message = 'Order #' . $orderId . ' placed. Pay cash on delivery.';
            flash('success', $message);
            redirect('history');
        } catch (Throwable $e) {
            flash('error', 'Could not place the order. Try again.');
            redirect('checkout');
        }
    }
}
