<?php
class StaffController extends Controller
{
    public function orders(): void
    {
        require_staff();
        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
        if (!in_array($status, ['', 'preparing', 'completed', 'cancelled'], true)) {
            $status = '';
        }
        $search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
        $todayOnly = ($_GET['today'] ?? '') === '1' && $status === 'completed';
        $archived = ($_GET['archived'] ?? '') === '1';
        $dateFilter = '';
        $dateValue = $_GET['date'] ?? null;
        if ($archived && is_string($dateValue) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if ($parsedDate && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) && $parsedDate->format('Y-m-d') === $dateValue) {
                $dateFilter = $dateValue;
            }
        }
        $this->view('staff/orders', [
            'title' => 'Staff orders',
            'orders' => Order::forStaff($status, $search, $todayOnly, $archived, $dateFilter !== '' ? $dateFilter : null),
            'summary' => Order::staffSummary(),
            'statusFilter' => $status,
            'search' => $search,
            'todayOnly' => $todayOnly,
            'archived' => $archived,
            'dateFilter' => $dateFilter,
        ], 'admin');
    }

    public function createOrder(): void
    {
        require_staff();
        $this->view('staff/create-order', [
            'title' => 'Create customer order',
            'items' => MenuItem::all(null, true),
        ], 'admin');
    }

    public function createOrderPost(): void
    {
        require_staff();
        verify_csrf();

        try {
            $orderId = Order::createForCustomer(
                $this->postedString('customer_email'),
                is_array($_POST['items'] ?? null) ? $_POST['items'] : [],
                $this->postedString('payment_method')
            );
        } catch (InvalidArgumentException $error) {
            flash('error', $error->getMessage());
            redirect('staff/orders/create');
        } catch (Throwable $error) {
            error_log('CaféCore staff order creation failed: ' . $error->getMessage());
            flash('error', 'The order could not be created. Please try again.');
            redirect('staff/orders/create');
        }

        flash('success', 'Order #' . $orderId . ' created.');
        redirect('staff/orders');
    }

    public function updateStatus(): void
    {
        require_staff();
        verify_csrf();
        $orderId = $this->postedInt('order_id');
        $status = $this->postedString('status');
        if (!Order::updateStaffStatus($orderId, $status)) {
            flash('error', 'The order was not found or the status is invalid.');
            redirect($this->statusReturnUrl());
        }
        flash('success', 'Order status updated.');
        redirect($this->statusReturnUrl());
    }

    public function updatePayment(): void
    {
        require_staff();
        verify_csrf();
        $orderId = $this->postedInt('order_id');
        $paymentStatus = $this->postedString('payment_status');
        $paymentMethod = $this->postedString('payment_method');
        if (!Order::updateStaffPayment($orderId, $paymentStatus, $paymentMethod)) {
            flash('error', 'The payment was not found or its details are invalid.');
            redirect($this->ordersUrl());
        }
        flash('success', 'Payment updated.');
        redirect($this->ordersUrl());
    }

    public function updateOrder(): void
    {
        require_staff();
        verify_csrf();
        $orderId = $this->postedInt('order_id');
        if (!Order::updateStaffOrder(
            $orderId,
            $this->postedString('status'),
            $this->postedString('payment_status'),
            $this->postedString('payment_method')
        )) {
            flash('error', 'The order was not found or its details are invalid.');
            redirect($this->statusReturnUrl());
        }
        flash('success', 'Order updated.');
        redirect($this->statusReturnUrl());
    }

    private function ordersUrl(): string
    {
        $status = $this->postedString('return_status');
        if (!in_array($status, ['', 'preparing', 'completed', 'cancelled'], true)) {
            $status = '';
        }
        $search = trim($this->postedString('return_search'));
        $todayOnly = $status === 'completed' && $this->postedString('return_today') === '1';
        $query = array_filter(['status' => $status, 'today' => $todayOnly ? '1' : '', 'q' => $search], static fn (string $value): bool => $value !== '');
        return 'staff/orders' . ($query ? '?' . http_build_query($query) : '');
    }

    private function statusReturnUrl(): string
    {
        $returnTo = $this->postedString('return_to');
        return in_array($returnTo, ['history', 'admin/history'], true)
            ? $returnTo
            : $this->ordersUrl();
    }

    private function postedString(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private function postedInt(string $key): int
    {
        $value = $_POST[$key] ?? null;
        return is_scalar($value) && filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : 0;
    }
}
