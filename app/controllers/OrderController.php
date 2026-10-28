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
        $completed = !$staffLayout && ($_GET['completed'] ?? '') === '1';
        $dateFilters = history_date_filter();
        $this->view('history/index', [
            'title' => $staffLayout ? 'Order history' : 'History',
            'orders' => Order::forUser((int) $user['id'], $dateFilters['date'], $dateFilters['date'], false, $completed ? 'completed' : null, !$staffLayout && !$completed),
            'cartCount' => Cart::count(),
            'staffLayout' => $staffLayout,
            'dateFilters' => $dateFilters,
            'completed' => $completed,
        ], $staffLayout ? 'admin' : 'main');
    }

    public function archiveCustomerOrders(): void
    {
        $user = require_auth();
        if (($user['role'] ?? '') !== 'customer') {
            flash('error', 'Customer order archiving is only available in customer accounts.');
            redirect('history');
        }
        verify_csrf();
        $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        $restore = ($_POST['operation'] ?? '') === 'restore';
        $changed = Order::archiveForUser((int) $user['id'], $ids, $restore);
        flash('success', $changed . ' order' . ($changed === 1 ? '' : 's') . ($restore ? ' restored.' : ' archived.'));
        $date = is_string($_POST['date'] ?? null) ? $_POST['date'] : '';
        $query = array_filter([
            'date' => $date,
            'archived' => $restore ? null : '1',
        ], static fn ($value) => $value !== null && $value !== '');
        redirect('history?' . http_build_query($query));
    }

    public function statuses(): void
    {
        $user = require_auth();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->json(['orders' => Order::statusesForUser((int) $user['id'])]);
    }
}
