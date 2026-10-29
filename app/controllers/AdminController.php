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
            'recent' => Order::allRecent(5),
        ], 'admin');
    }
    public function history(): void
    {
        require_admin();
        $dateFilters = history_date_filter();
        $this->view('admin/history', [
            'title' => 'Order history',
            'orders' => Order::allForAdmin($dateFilters['date'], $dateFilters['date'], null),
            'dateFilters' => $dateFilters,
        ], 'admin');
    }
    public function staff(): void
    {
        require_admin();
        $archived = ($_GET['archived'] ?? '') === '1';
        $this->view('admin/staff', [
            'title' => 'Staff accounts',
            'staffMembers' => User::allStaff($archived),
            'archived' => $archived,
        ], 'admin');
    }
    public function archiveStaff(): void
    {
        require_admin();
        verify_csrf();
        $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        $restore = ($_POST['operation'] ?? '') === 'restore';
        $changed = User::archiveStaff($ids, $restore);
        flash('success', $changed . ' staff account' . ($changed === 1 ? '' : 's') . ($restore ? ' restored.' : ' archived.'));
        redirect('admin/staff' . ($restore ? '' : '?archived=1'));
    }
    public function createStaff(): void
    {
        require_admin();
        verify_csrf();

        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $passwordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

        $nameLength = preg_match_all('/./us', $name);
        if ($name === '' || $nameLength === false || $nameLength > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            flash('error', 'Enter a name and a valid email address within the allowed length.');
            redirect('admin/staff');
        }
        if (strlen($password) < 8) {
            flash('error', 'Staff passwords must be at least 8 characters.');
            redirect('admin/staff');
        }
        if ($password !== $passwordConfirm) {
            flash('error', 'The passwords do not match.');
            redirect('admin/staff');
        }
        if (User::findByEmail($email)) {
            flash('error', 'An account already exists with that email address.');
            redirect('admin/staff');
        }

        try {
            User::createStaff($name, $email, $password);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                flash('error', 'An account already exists with that email address.');
                redirect('admin/staff');
            }
            throw $error;
        }
        flash('success', 'Staff account created. They can now sign in.');
        redirect('admin/staff');
    }
    public function deleteStaff(): void
    {
        require_admin();
        verify_csrf();
        $id = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            flash('error', 'Select a valid staff account.');
            redirect('admin/staff');
        }

        $result = User::deleteStaff((int) $id);
        if ($result === 'has_orders') {
            flash('error', 'This account has order history and cannot be deleted without removing those records.');
            redirect('admin/staff');
        }
        if ($result !== 'deleted') {
            flash('error', 'The staff account was not found.');
            redirect('admin/staff');
        }
        flash('success', 'Staff account deleted.');
        redirect('admin/staff');
    }
    public function menu(): void
    {
        require_staff();
        $archived = ($_GET['archived'] ?? '') === '1';
        $this->view('admin/menu', [
            'title' => 'Menu management',
            'items' => MenuItem::all(null, false, $archived),
            'archived' => $archived,
        ], 'admin');
    }
    public function archiveMenu(): void
    {
        require_staff();
        verify_csrf();
        $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        $restore = ($_POST['operation'] ?? '') === 'restore';
        $changed = MenuItem::archiveMany($ids, $restore);
        flash('success', $changed . ' menu item' . ($changed === 1 ? '' : 's') . ($restore ? ' restored.' : ' archived.'));
        redirect('admin/menu' . ($restore ? '' : '?archived=1'));
    }
    public function edit(): void
    {
        require_staff();
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
        require_staff();
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price' => (float) ($_POST['price'] ?? 0),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'image' => '',
            'available' => isset($_POST['available']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
        ];
        if ($data['name'] === '' || $data['price'] <= 0 || !MenuItem::categoryExists($data['category_id'])) {
            flash('error', 'Name, a valid price, and a category are required.');
            redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
        }
        $upload = $_FILES['image_file'] ?? null;
        $previousImage = '';
        if ($id) {
            $existingItem = MenuItem::find($id);
            $previousImage = (string) ($existingItem['image'] ?? '');
            $data['image'] = $previousImage;
        }

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

            // Store menu images in resources/menu-images using the menu item's name.
            $uploadDir = APP_ROOT . '/resources/menu-images';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                flash('error', 'The image could not be saved.');
                redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
            }

            $baseName = trim($data['name']);
            if (function_exists('iconv')) {
                $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $baseName);
                if ($converted !== false) $baseName = $converted;
            }
            $baseName = strtolower($baseName);
            $baseName = preg_replace('/[^a-z0-9]+/', '-', $baseName);
            $baseName = trim((string) $baseName, '-');
            if ($baseName === '') $baseName = 'menu-item';
            $filename = $baseName . '.' . $extensions[$mime];
            $destination = $uploadDir . '/' . $filename;

            // Remove older extensions for this same item name so only the new image remains.
            foreach (['jpg', 'png', 'gif', 'webp'] as $extension) {
                $oldVariant = $uploadDir . '/' . $baseName . '.' . $extension;
                if ($oldVariant !== $destination && is_file($oldVariant)) {
                    @unlink($oldVariant);
                }
            }

            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                flash('error', 'The image could not be saved.');
                redirect($id ? 'admin/menu/edit?id=' . $id : 'admin/menu/create');
            }
            $data['image'] = url('resources/menu-images/' . $filename);
        }
        MenuItem::save($data, $id);

        // When replacing an uploaded image, delete the old managed file after the DB save.
        if ($previousImage !== '' && $previousImage !== $data['image']) {
            $oldPath = parse_url($previousImage, PHP_URL_PATH) ?: '';
            $managedPrefix = rtrim(BASE_URL, '/') . '/resources/menu-images/';
            $relativePrefix = '/resources/menu-images/';
            $oldFilename = '';
            if (str_starts_with($oldPath, $managedPrefix)) {
                $oldFilename = substr($oldPath, strlen($managedPrefix));
            } elseif (str_starts_with($oldPath, $relativePrefix)) {
                $oldFilename = substr($oldPath, strlen($relativePrefix));
            }
            if ($oldFilename !== '' && basename($oldFilename) === $oldFilename) {
                $oldFile = APP_ROOT . '/resources/menu-images/' . $oldFilename;
                if (is_file($oldFile)) @unlink($oldFile);
            }
        }

        flash('success', 'Menu item saved.');
        redirect('admin/menu');
    }
    public function toggle(): void
    {
        require_staff();
        verify_csrf();
        MenuItem::toggle((int) ($_POST['id'] ?? 0), $_POST['field'] ?? 'available');
        redirect('admin/menu');
    }
    public function delete(): void
    {
        require_staff();
        verify_csrf();
        MenuItem::delete((int) ($_POST['id'] ?? 0));
        flash('success', 'Item removed from the menu.');
        redirect('admin/menu');
    }
}
