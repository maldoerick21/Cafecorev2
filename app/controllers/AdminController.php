<?php
class AdminController extends Controller
{
    public function dashboard(): void
    {
        require_admin();
        $this->view('admin/dashboard', [
            'title' => 'Dashboard',
            'stats' => Order::stats(),
            'customers' => User::count(),
            'menuCount' => MenuItem::count(),
            'recent' => Order::allRecent(),
        ], 'admin');
    }
    public function menu(): void
    {
        require_admin();
        $this->view('admin/menu', [
            'title' => 'Menu management',
            'items' => MenuItem::all(),
        ], 'admin');
    }
    public function edit(): void
    {
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);
        $item = $id ? MenuItem::find($id) : null;
        $this->view('admin/edit', [
            'title' => $item ? 'Edit item' : 'New item',
            'item' => $item,
        ], 'admin');
    }
    public function save(): void
    {
        require_admin();
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price' => (float) ($_POST['price'] ?? 0),
            'category' => trim($_POST['category'] ?? 'Coffee'),
            'image' => trim($_POST['image'] ?? ''),
            'available' => isset($_POST['available']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
        ];
        if ($data['name'] === '' || $data['price'] <= 0) {
            flash('error', 'Name and a valid price are required.');
            redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
        }
        MenuItem::save($data, $id);
        flash('success', 'Menu item saved.');
        redirect('admin/menu');
    }
    public function toggle(): void
    {
        require_admin();
        verify_csrf();
        MenuItem::toggle((int) ($_POST['id'] ?? 0), $_POST['field'] ?? 'available');
        redirect('admin/menu');
    }
    public function delete(): void
    {
        require_admin();
        verify_csrf();
        MenuItem::delete((int) ($_POST['id'] ?? 0));
        flash('success', 'Item removed from the menu.');
        redirect('admin/menu');
    }
}