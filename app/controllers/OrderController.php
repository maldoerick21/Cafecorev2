<?php

class OrderController extends Controller
{
    public function history(): void
    {
        $user = require_auth();
        $this->view('history/index', [
            'title' => 'History',
            'orders' => Order::forUser($user['id']),
            'processingOrderId' => (int) ($_GET['processing'] ?? 0),
            'cartCount' => Cart::count(),
        ]);
    }
    public function complete(): void
    {
        $user = require_auth();
        verify_csrf();
        header('Content-Type: application/json');
        echo json_encode([
            'completed' => Order::completeAfterProcessing((int) ($_POST['order_id'] ?? 0), (int) $user['id']),
        ]);
    }
}
