<?php
class Order
{
    public static function create(int $userId, array $cart, array $catalog, string $paymentMethod): int
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $total = 0; $lines = [];
            foreach ($cart as $key => $qty) {
                $line = $catalog[$key] ?? null;
                if (!$line || $qty < 1) continue;
                $item = $line['item'];
                $price = (float) $line['price'];
                $total += $price * $qty;
                $displayName = $item['name'] . ' (' . $line['size'] . ', ' . $line['sugar'] . '% sugar)';
                $lines[] = [$item, $qty, $price, $displayName];
            }
            if (!$lines) throw new RuntimeException('Cart is empty.');
            // Orders paid and picked up at the counter have no service fee.
            $stmt = $pdo->prepare('INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $total, 'preparing']);
            $orderId = (int) $pdo->lastInsertId();
            $lineStmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, name, qty, price) VALUES (?, ?, ?, ?, ?)');
            foreach ($lines as [$item, $qty, $price, $displayName]) {
                $lineStmt->execute([$orderId, $item['id'], $displayName, $qty, $price]);
            }
            $paymentStmt = $pdo->prepare('INSERT INTO payments (order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?)');
            $paymentStmt->execute([$orderId, $total, $paymentMethod, 'pending']);
            $pdo->commit(); return $orderId;
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function createForCustomer(string $email, array $requestedItems, string $paymentMethod): int
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid customer email address.');
        }
        if (!in_array($paymentMethod, ['counter', 'cash', 'card', 'e_wallet'], true)) {
            throw new InvalidArgumentException('Select a valid payment method.');
        }
        if (!$requestedItems) {
            throw new InvalidArgumentException('Add at least one product to the order.');
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $customerStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'customer' LIMIT 1");
            $customerStmt->execute([$email]);
            $userId = $customerStmt->fetchColumn();
            if (!$userId) {
                throw new InvalidArgumentException('No customer account was found for that email.');
            }

            $quantities = [];
            foreach ($requestedItems as $requestedItem) {
                if (!is_array($requestedItem)) {
                    throw new InvalidArgumentException('Choose a product and a quantity from 1 to 99.');
                }
                $itemValue = $requestedItem['item_id'] ?? null;
                $qtyValue = $requestedItem['qty'] ?? null;
                $sizeValue = $requestedItem['size'] ?? 'Medium';
                $sugarValue = $requestedItem['sugar'] ?? 100;
                if (!is_scalar($itemValue) || !is_scalar($qtyValue) || !is_string($sizeValue) || !is_scalar($sugarValue)) {
                    throw new InvalidArgumentException('Choose a product and a quantity from 1 to 99.');
                }
                $itemId = filter_var($itemValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $qty = filter_var($qtyValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
                $size = $sizeValue;
                $sugar = filter_var($sugarValue, FILTER_VALIDATE_INT);
                if (!$itemId || !$qty || !in_array($size, ['Small', 'Medium', 'Large'], true) || !in_array($sugar, [0, 25, 50, 75, 100], true)) {
                    throw new InvalidArgumentException('Choose a product and a quantity from 1 to 99.');
                }
                $key = $itemId . '|' . $size . '|' . $sugar;
                $quantities[$key] = ($quantities[$key] ?? 0) + $qty;
                if ($quantities[$key] > 99) {
                    throw new InvalidArgumentException('A product quantity cannot exceed 99.');
                }
            }

            $productStmt = $pdo->prepare('SELECT id, name, price, available FROM menu_items WHERE id = ? FOR UPDATE');
            $products = [];
            $total = 0.0;
            foreach ($quantities as $key => $qty) {
                [$itemId, $size, $sugar] = explode('|', $key);
                $productStmt->execute([$itemId]);
                $product = $productStmt->fetch();
                if (!$product || !(int) $product['available']) {
                    throw new InvalidArgumentException('One of the selected products is unavailable. Refresh and try again.');
                }
                $product['qty'] = $qty;
                $product['price'] = (float) $product['price'] + ['Small' => 0, 'Medium' => 15, 'Large' => 25][$size];
                $product['display_name'] = $product['name'] . ' (' . $size . ', ' . $sugar . '% sugar)';
                $products[] = $product;
                $total += $product['price'] * $qty;
            }

            $orderStmt = $pdo->prepare('INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)');
            $orderStmt->execute([(int) $userId, $total, 'preparing']);
            $orderId = (int) $pdo->lastInsertId();
            $lineStmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, name, qty, price) VALUES (?, ?, ?, ?, ?)');
            foreach ($products as $product) {
                $lineStmt->execute([$orderId, $product['id'], $product['display_name'], $product['qty'], $product['price']]);
            }
            $paymentStmt = $pdo->prepare('INSERT INTO payments (order_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?)');
            $paymentStmt->execute([$orderId, $total, $paymentMethod, 'pending']);
            $pdo->commit();
            return $orderId;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function forStaff(string $status = '', string $search = ''): array
    {
        $allowedStatuses = ['preparing', 'completed', 'cancelled'];
        $sql = "SELECT o.id, o.user_id, o.total, o.status, o.created_at,
                    u.name AS customer, u.email,
                    p.payment_method, p.payment_status, p.payment_date,
                    COALESCE(order_lines.items_summary, '') AS items_summary
                FROM orders o
                INNER JOIN users u ON u.id = o.user_id
                LEFT JOIN payments p ON p.order_id = o.id
                LEFT JOIN (
                    SELECT order_id, GROUP_CONCAT(CONCAT(qty, ' × ', name) ORDER BY id SEPARATOR ', ') AS items_summary
                    FROM order_items GROUP BY order_id
                ) order_lines ON order_lines.order_id = o.id";
        $conditions = [];
        $params = [];
        if (in_array($status, $allowedStatuses, true)) {
            $conditions[] = 'o.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $conditions[] = '(u.name LIKE ? OR u.email LIKE ? OR CAST(o.id AS CHAR) = ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = $search;
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY o.created_at DESC LIMIT 100';
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function staffSummary(): array
    {
        return Database::connect()->query("SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'preparing'), 0) AS preparing,
                COALESCE(SUM(status = 'completed' AND DATE(created_at) = CURDATE()), 0) AS completed_today,
                (SELECT COUNT(*) FROM payments WHERE payment_status = 'pending') AS unpaid
            FROM orders")->fetch();
    }

    public static function updateStaffStatus(int $orderId, string $status): bool
    {
        if ($orderId < 1 || !in_array($status, ['preparing', 'completed', 'cancelled'], true)) {
            return false;
        }
        $pdo = Database::connect();
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $orderId]);
        $check = $pdo->prepare('SELECT 1 FROM orders WHERE id = ?');
        $check->execute([$orderId]);
        return (bool) $check->fetchColumn();
    }

    public static function updateStaffPayment(int $orderId, string $paymentStatus, string $paymentMethod): bool
    {
        if (
            $orderId < 1
            || !in_array($paymentStatus, ['pending', 'paid', 'refunded'], true)
            || !in_array($paymentMethod, ['counter', 'cash', 'card', 'e_wallet'], true)
        ) {
            return false;
        }
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE payments
            SET payment_status = ?, payment_method = ?,
                payment_date = CASE
                    WHEN ? = 'paid' THEN COALESCE(payment_date, CURRENT_TIMESTAMP)
                    WHEN ? = 'pending' THEN NULL
                    ELSE payment_date
                END
            WHERE order_id = ?");
        $stmt->execute([$paymentStatus, $paymentMethod, $paymentStatus, $paymentStatus, $orderId]);
        $check = $pdo->prepare('SELECT 1 FROM payments WHERE order_id = ?');
        $check->execute([$orderId]);
        return (bool) $check->fetchColumn();
    }
    public static function forUser(int $userId): array
    {
        $orders = Database::connect()
            ->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
        $orders->execute([$userId]);
        $rows = $orders->fetchAll();
        $itemStmt = Database::connect()->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $paymentStmt = Database::connect()->prepare('SELECT payment_method, payment_status, payment_date FROM payments WHERE order_id = ?');
        foreach ($rows as &$row) {
            $itemStmt->execute([$row['id']]);
            $row['items'] = $itemStmt->fetchAll();
            $paymentStmt->execute([$row['id']]);
            $row['payment'] = $paymentStmt->fetch() ?: null;
        }
        return $rows;
    }
    public static function allRecent(?int $limit = null): array
    {
        $sql = 'SELECT o.*, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $stmt = Database::connect()->prepare($sql);
            $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }
        return Database::connect()->query($sql)->fetchAll();
    }

    public static function allForAdmin(): array
    {
        return Database::connect()->query("SELECT o.id, o.total, o.status, o.created_at,
                u.name AS customer, u.email,
                p.payment_method, p.payment_status, p.payment_date,
                COALESCE(order_lines.items_summary, '') AS items_summary
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN payments p ON p.order_id = o.id
            LEFT JOIN (
                SELECT order_id, GROUP_CONCAT(CONCAT(qty, ' × ', name) ORDER BY id SEPARATOR ', ') AS items_summary
                FROM order_items GROUP BY order_id
            ) order_lines ON order_lines.order_id = o.id
            ORDER BY o.created_at DESC
            LIMIT 500")->fetchAll();
    }

    public static function stats(): array
    {
        $pdo = Database::connect();
        $revenue = (float) $pdo->query('SELECT COALESCE(SUM(total),0) FROM orders')->fetchColumn();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $today = (float) $pdo->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at) = CURDATE()')->fetchColumn();

        $byStatus = $pdo->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status')->fetchAll();
        $statusMap = ['preparing' => 0, 'completed' => 0, 'cancelled' => 0];
        foreach ($byStatus as $row) {
            $statusMap[$row['status']] = (int) $row['n'];
        }

        // Return all twelve calendar months, including months with no earnings.
        $monthlyRows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
                COALESCE(SUM(total), 0) AS earnings
            FROM orders
            WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
              AND created_at < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')")->fetchAll();
        $monthlyMap = [];
        foreach ($monthlyRows as $row) {
            $monthlyMap[$row['month_key']] = (float) $row['earnings'];
        }
        $monthlyEarnings = [];
        $monthStart = new DateTime('first day of this month');
        $monthStart->modify('-11 months');
        for ($i = 0; $i < 12; $i++) {
            $key = $monthStart->format('Y-m');
            $monthlyEarnings[] = [
                'month' => $monthStart->format('M Y'),
                'earnings' => $monthlyMap[$key] ?? 0.0,
            ];
            $monthStart->modify('+1 month');
        }

        // Category is determined by the menu item's category, not the order status.
        $categorySales = $pdo->query("SELECT c.category_name AS name, COALESCE(SUM(oi.qty), 0) AS sold
            FROM order_items oi
            INNER JOIN menu_items mi ON mi.id = oi.menu_item_id
            INNER JOIN categories c ON c.category_id = mi.category_id
            GROUP BY c.category_id, c.category_name
            HAVING SUM(oi.qty) > 0
            ORDER BY sold DESC, c.category_name ASC")->fetchAll();
        foreach ($categorySales as &$row) {
            $row['sold'] = (int) $row['sold'];
        }
        unset($row);

        return compact('revenue', 'count', 'today', 'statusMap', 'monthlyEarnings', 'categorySales');
    }
}
