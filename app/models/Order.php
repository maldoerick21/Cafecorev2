<?php
class Order
{
    public static function create(int $userId, array $cart, array $catalog): int
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $total = 0;
            $lines = [];
            foreach ($cart as $itemId => $qty) {
                $item = $catalog[$itemId] ?? null;
                if (!$item || $qty < 1) {
                    continue;
                }
                $lineTotal = $item['price'] * $qty;
                $total += $lineTotal;
                $lines[] = [$item, $qty];
            }
            if (!$lines) {
                throw new RuntimeException('Cart is empty.');
            }
            $stmt = $pdo->prepare('INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $total, 'preparing']);
            $orderId = (int) $pdo->lastInsertId();
            $lineStmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, name, qty, price) VALUES (?, ?, ?, ?, ?)');
            foreach ($lines as [$item, $qty]) {
                $lineStmt->execute([$orderId, $item['id'], $item['name'], $qty, $item['price']]);
            }
            $pdo->commit();
            return $orderId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    public static function completeAfterProcessing(int $orderId, int $userId): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE orders SET status = 'completed'
            WHERE id = ? AND user_id = ? AND status = 'preparing'
            AND (julianday('now') - julianday(created_at)) * 86400 >= 7");
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
            ->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY datetime(created_at) DESC');
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
            'SELECT o.*, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id ORDER BY datetime(o.created_at) DESC LIMIT ?'
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
        $today = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE date(created_at) = date('now')")->fetchColumn();
        $byStatus = $pdo->query('SELECT status, COUNT(*) as n FROM orders GROUP BY status')->fetchAll();
        $statusMap = ['preparing' => 0, 'completed' => 0, 'cancelled' => 0];
        foreach ($byStatus as $row) {
            $statusMap[$row['status']] = (int) $row['n'];
        }
        $top = $pdo->query(
            'SELECT name, SUM(qty) as sold FROM order_items GROUP BY name ORDER BY sold DESC LIMIT 5'
        )->fetchAll();
        return compact('revenue', 'count', 'today', 'statusMap', 'top');
    }
}