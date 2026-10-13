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
            'categories' => MenuItem::categories(),
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
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'image' => trim($_POST['image'] ?? ''),
            'available' => isset($_POST['available']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
        ];
        if ($data['name'] === '' || $data['price'] <= 0 || !MenuItem::categoryExists($data['category_id'])) {
            flash('error', 'Name, a valid price, and a category are required.');
            redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
        }
        $upload = $_FILES['image_file'] ?? null;
        if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
                flash('error', 'Choose an image smaller than 5 MB.');
                redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                flash('error', 'Use a JPG, PNG, GIF, or WebP image.');
                redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
            }
            $uploadDir = PUBLIC_PATH . '/uploads/menu';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                flash('error', 'The image could not be saved.');
                redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            if (!move_uploaded_file($upload['tmp_name'], $uploadDir . '/' . $filename)) {
                flash('error', 'The image could not be saved.');
                redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
            }
            $data['image'] = url('public/uploads/menu/' . $filename);
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