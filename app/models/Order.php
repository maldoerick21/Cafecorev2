<?php
class Order
{
    public static function create(int $userId, array $cart, array $catalog): int
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
            $pdo->commit(); return $orderId;
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
    public static function completeAfterProcessing(int $orderId, int $userId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE orders SET status = 'completed'
            WHERE id = ? AND user_id = ? AND status = 'preparing'
            AND TIMESTAMPDIFF(SECOND, created_at, CURRENT_TIMESTAMP) >= 7");
        $stmt->execute([$orderId, $userId]);
        if ($stmt->rowCount() > 0) {
            return true;
        }
        $check = $pdo->prepare("SELECT status = 'completed' FROM orders WHERE id = ? AND user_id = ?");
        $check->execute([$orderId, $userId]);
        return (bool) $check->fetchColumn();
    }
    public static function forUser(int $userId): array
    {
        $orders = Database::connect()
            ->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
        $orders->execute([$userId]);
        $rows = $orders->fetchAll();
        $itemStmt = Database::connect()->prepare('SELECT * FROM order_items WHERE order_id = ?');
        foreach ($rows as &$row) {
            $itemStmt->execute([$row['id']]);
            $row['items'] = $itemStmt->fetchAll();
        }
        return $rows;
    }
    public static function allRecent(int $limit = 8): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT o.*, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
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