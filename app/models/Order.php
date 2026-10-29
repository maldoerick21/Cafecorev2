<?php
class Order
{
    public static function create(int $userId, array $cart, array $catalog, string $paymentMethod, string $fulfillmentType = 'pickup', ?string $deliveryAddress = null): int
    {
        $paymentMethod = self::normalizePaymentMethod($paymentMethod);
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
                $options = [];
                if ($line['has_size']) $options[] = $line['size'];
                if ($line['has_sugar']) $options[] = $line['sugar'] . '% sugar';
                $displayName = $item['name'] . ($options ? ' (' . implode(', ', $options) . ')' : '');
                $lines[] = [$item, $qty, $price, $displayName];
            }
            if (!$lines) throw new RuntimeException('Cart is empty.');
            // Delivery is charged at 2.5% of the item subtotal; pickup is free.
            if ($fulfillmentType === 'delivery') $total += round($total * 0.025, 2);
            $stmt = $pdo->prepare('INSERT INTO orders (user_id, total, status, fulfillment_type, delivery_address) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $total, 'preparing', $fulfillmentType, $fulfillmentType === 'delivery' ? $deliveryAddress : null]);
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
        $paymentMethod = self::normalizePaymentMethod($paymentMethod);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid customer email address.');
        }
        if (!in_array($paymentMethod, ['Cash on Delivery', 'cash', 'card', 'e_wallet'], true)) {
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

            $productStmt = $pdo->prepare('SELECT mi.id, mi.name, mi.price, mi.available, c.category_name
                FROM menu_items mi
                INNER JOIN categories c ON c.category_id = mi.category_id
                WHERE mi.id = ? FOR UPDATE');
            $quantities = [];
            foreach ($requestedItems as $requestedItem) {
                if (!is_array($requestedItem)) {
                    throw new InvalidArgumentException('Choose a product and a quantity from 1 to 99.');
                }
                $itemValue = $requestedItem['item_id'] ?? null;
                $qtyValue = $requestedItem['qty'] ?? null;
                $sizeValue = $requestedItem['size'] ?? 'Medium';
                $sugarValue = $requestedItem['sugar'] ?? 100;
                if (!is_scalar($itemValue) || !is_scalar($qtyValue)) {
                    throw new InvalidArgumentException('Choose a product and a quantity from 1 to 99.');
                }
                $itemId = filter_var($itemValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $qty = filter_var($qtyValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
                if (!$itemId || !$qty) {
                    throw new InvalidArgumentException('Choose a product and a quantity from 1 to 99.');
                }

                $productStmt->execute([$itemId]);
                $product = $productStmt->fetch();
                if (!$product || !(int) $product['available']) {
                    throw new InvalidArgumentException('One of the selected products is unavailable. Refresh and try again.');
                }
                $customization = MenuItem::customizationOptions((string) $product['category_name']);
                if ($customization['size']) {
                    if (!is_string($sizeValue) || !in_array($sizeValue, ['Small', 'Medium', 'Large'], true)) {
                        throw new InvalidArgumentException('Choose a valid size for each selected drink.');
                    }
                    $size = $sizeValue;
                } else {
                    $size = 'Standard';
                }
                if ($customization['sugar']) {
                    $sugar = is_scalar($sugarValue) ? filter_var($sugarValue, FILTER_VALIDATE_INT) : false;
                    if (!in_array($sugar, [0, 25, 50, 75, 100], true)) {
                        throw new InvalidArgumentException('Choose a valid sugar level for each selected drink.');
                    }
                } else {
                    $sugar = -1;
                }
                $key = $itemId . '|' . $size . '|' . $sugar;
                $quantities[$key] = ($quantities[$key] ?? 0) + $qty;
                if ($quantities[$key] > 99) {
                    throw new InvalidArgumentException('A product quantity cannot exceed 99.');
                }
            }
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
                $customization = MenuItem::customizationOptions((string) $product['category_name']);
                $sizeExtra = $customization['size'] ? ['Small' => 0, 'Medium' => 15, 'Large' => 25][$size] : 0;
                $product['price'] = (float) $product['price'] + $sizeExtra;
                $options = [];
                if ($customization['size']) $options[] = $size;
                if ($customization['sugar']) $options[] = $sugar . '% sugar';
                $product['display_name'] = $product['name'] . ($options ? ' (' . implode(', ', $options) . ')' : '');
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

    public static function forStaff(string $status = '', string $search = '', bool $todayOnly = false, bool $archived = false, ?string $date = null): array
    {
        $allowedStatuses = ['preparing', 'completed', 'cancelled'];
        $sql = "SELECT o.id, o.user_id, o.total, o.status, o.fulfillment_type, o.delivery_address, o.created_at,
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
        // Staff order queue contains only active orders. Completed and paid
        // orders are moved out of this queue by updateStaffOrder().
        $conditions = ['o.archived_at IS ' . ($archived ? 'NOT ' : '') . 'NULL'];
        $params = [];
        if (in_array($status, $allowedStatuses, true)) {
            $conditions[] = 'o.status = ?';
            $params[] = $status;
        }
        if ($todayOnly) {
            $conditions[] = 'o.created_at >= CURDATE() AND o.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
        }
        if ($archived && $date !== null) {
            $conditions[] = 'o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $date;
            $params[] = $date;
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
                COALESCE(SUM(status = 'cancelled'), 0) AS cancelled
            FROM orders WHERE archived_at IS NULL")->fetch();
    }

    public static function updateStaffStatus(int $orderId, string $status): bool
    {
        if ($orderId < 1 || !in_array($status, ['preparing', 'completed', 'cancelled'], true)) {
            return false;
        }
        $pdo = Database::connect();
        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $orderId]);
        self::archiveStaffOrderIfSettled($pdo, $orderId);
        $check = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
        $check->execute([$orderId]);
        return $check->fetchColumn() === $status;
    }

    public static function updateStaffPayment(int $orderId, string $paymentStatus, string $paymentMethod): bool
    {
        $paymentMethod = self::normalizePaymentMethod($paymentMethod);
        if (
            $orderId < 1
            || !in_array($paymentStatus, ['pending', 'paid', 'refunded'], true)
            || $paymentMethod !== 'Cash on Delivery'
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
        self::archiveStaffOrderIfSettled($pdo, $orderId);
        $check = $pdo->prepare('SELECT 1 FROM payments WHERE order_id = ?');
        $check->execute([$orderId]);
        return (bool) $check->fetchColumn();
    }

    private static function archiveStaffOrderIfSettled(PDO $pdo, int $orderId): void
    {
        $stmt = $pdo->prepare("UPDATE orders o
            INNER JOIN payments p ON p.order_id = o.id
            SET o.archived_at = COALESCE(o.archived_at, CURRENT_TIMESTAMP)
            WHERE o.id = ? AND o.status = 'completed' AND p.payment_status = 'paid'");
        $stmt->execute([$orderId]);
    }

    public static function updateStaffOrder(int $orderId, string $status, string $paymentStatus, string $paymentMethod): bool
    {
        $paymentMethod = self::normalizePaymentMethod($paymentMethod);
        if (
            $orderId < 1
            || !in_array($status, ['preparing', 'completed', 'cancelled'], true)
            || !in_array($paymentStatus, ['pending', 'paid', 'refunded'], true)
            || $paymentMethod !== 'Cash on Delivery'
        ) {
            return false;
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $orderStmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $orderStmt->execute([$status, $orderId]);
            $paymentStmt = $pdo->prepare("UPDATE payments
                SET payment_status = ?, payment_method = ?,
                    payment_date = CASE
                        WHEN ? = 'paid' THEN COALESCE(payment_date, CURRENT_TIMESTAMP)
                        WHEN ? = 'pending' THEN NULL
                        ELSE payment_date
                    END
                WHERE order_id = ?");
            $paymentStmt->execute([$paymentStatus, $paymentMethod, $paymentStatus, $paymentStatus, $orderId]);
            $check = $pdo->prepare('SELECT (SELECT COUNT(*) FROM orders WHERE id = ?) + (SELECT COUNT(*) FROM payments WHERE order_id = ?)');
            $check->execute([$orderId, $orderId]);
            if ((int) $check->fetchColumn() !== 2) {
                $pdo->rollBack();
                return false;
            }
            if ($status === 'completed' && $paymentStatus === 'paid') {
                $archiveStmt = $pdo->prepare("UPDATE orders SET archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP) WHERE id = ?");
                $archiveStmt->execute([$orderId]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
    public static function forUser(int $userId, ?string $from = null, ?string $to = null, bool $archived = false, ?string $status = null, bool $excludeCompleted = false): array
    {
        $sql = 'SELECT * FROM orders WHERE user_id = ?';
        $params = [$userId];
        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        } else {
            $sql .= ' AND archived_at IS ' . ($archived ? 'NOT ' : '') . 'NULL';
            if ($excludeCompleted) {
                $sql .= " AND status <> 'completed'";
            }
        }
        if ($from !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND created_at < ?';
            $params[] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';
        $orders = Database::connect()->prepare($sql);
        $orders->execute($params);
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

    public static function archiveForUser(int $userId, array $ids, bool $restore = false): int
    {
        $ids = array_values(array_unique(array_filter(array_map(static function ($value): int {
            $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
            return $id === false ? 0 : $id;
        }, $ids), static fn (int $id): bool => $id > 0)));
        if ($userId < 1 || !$ids) {
            return 0;
        }
        $sql = $restore
            ? 'UPDATE orders SET archived_at = NULL WHERE user_id = ? AND archived_at IS NOT NULL AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            : "UPDATE orders SET archived_at = CURRENT_TIMESTAMP WHERE user_id = ? AND archived_at IS NULL AND status IN ('completed', 'cancelled', 'refunded') AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')';
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute(array_merge([$userId], $ids));
        return $stmt->rowCount();
    }

    public static function statusesForUser(int $userId): array
    {
        $stmt = Database::connect()->prepare('SELECT id, status FROM orders WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function allRecent(?int $limit = null): array
    {
        $sql = 'SELECT o.*, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC, o.id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $stmt = Database::connect()->prepare($sql);
            $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }
        return Database::connect()->query($sql)->fetchAll();
    }

    public static function reportDateDefaults(): array
    {
        $defaults = Database::connect()->query("SELECT
                DATE_FORMAT(CURDATE(), '%Y-01-01') AS from_date,
                DATE_FORMAT(CURDATE(), '%Y-%m-%d') AS to_date")
            ->fetch();
        return [
            'from' => (string) $defaults['from_date'],
            'to' => (string) $defaults['to_date'],
        ];
    }

    public static function reportYearsWithSales(): array
    {
        $years = Database::connect()->query("SELECT DISTINCT YEAR(o.created_at) AS sales_year
                FROM orders o
                INNER JOIN payments p ON p.order_id = o.id
                WHERE o.status = 'completed' AND p.payment_status = 'paid'
                ORDER BY sales_year DESC")
            ->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $years);
    }

    private static function normalizePaymentMethod(string $paymentMethod): string
    {
        return in_array(strtolower(trim($paymentMethod)), ['counter', 'cash on delivery'], true)
            ? 'Cash on Delivery'
            : $paymentMethod;
    }

    public static function allForAdmin(?string $from = null, ?string $to = null, ?bool $archived = false): array
    {
        $sql = "SELECT o.id, o.total, o.status, o.fulfillment_type, o.delivery_address, o.created_at,
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
            WHERE 1 = 1";
        $params = [];
        if ($archived !== null) {
            $sql .= ' AND o.archived_at IS ' . ($archived ? 'NOT ' : '') . 'NULL';
        }
        if ($from !== null) {
            $sql .= ' AND o.created_at >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND o.created_at < ?';
            $params[] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
        }
        $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT 500';
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function report(array $filters): array
    {
        [$whereSql, $params] = self::reportScope($filters);
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT o.id, o.total, o.status, o.created_at,
                    u.name AS customer, u.email,
                    p.payment_method, p.payment_status, p.payment_date, p.amount AS payment_amount
                FROM orders o
                INNER JOIN users u ON u.id = o.user_id
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE {$whereSql}
                ORDER BY o.created_at DESC, o.id DESC");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Fetch item rows individually so MySQL's GROUP_CONCAT length limit cannot
        // silently truncate an order's item list in the report or Excel export.
        $itemsStmt = $pdo->prepare("SELECT oi.order_id, oi.qty, oi.name
                FROM order_items oi
                INNER JOIN orders o ON o.id = oi.order_id
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE {$whereSql}
                ORDER BY o.created_at DESC, o.id DESC, oi.id");
        $itemsStmt->execute($params);
        $itemsByOrder = [];
        foreach ($itemsStmt->fetchAll() as $item) {
            $itemsByOrder[(int) $item['order_id']][] = (int) $item['qty'] . ' × ' . (string) $item['name'];
        }
        foreach ($orders as &$order) {
            $order['items_summary'] = implode(', ', $itemsByOrder[(int) $order['id']] ?? []);
        }
        unset($order);

        $summary = [
            'total_orders' => count($orders),
            'completed_orders' => 0,
            'order_value' => 0.0,
            'collected_amount' => 0.0,
        ];
        $daily = [];
        foreach ($orders as $order) {
            $total = (float) $order['total'];
            $summary['order_value'] += $total;
            $summary['completed_orders']++;
            $summary['collected_amount'] += (float) $order['payment_amount'];

            $createdAt = new DateTimeImmutable((string) $order['created_at']);
            $date = match ($filters['period'] ?? 'daily') {
                'weekly' => $createdAt->modify('monday this week')->format('Y-m-d'),
                'monthly' => $createdAt->format('Y-m-01'),
                'yearly' => $createdAt->format('Y-01-01'),
                default => $createdAt->format('Y-m-d'),
            };
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'orders' => 0,
                    'completed_orders' => 0,
                    'order_value' => 0.0,
                    'collected_amount' => 0.0,
                ];
            }
            $daily[$date]['orders']++;
            $daily[$date]['completed_orders']++;
            $daily[$date]['order_value'] += $total;
            $daily[$date]['collected_amount'] += (float) $order['payment_amount'];
        }
        ksort($daily);

        return [
            'orders' => $orders,
            'summary' => $summary,
            'daily' => array_values($daily),
        ];
    }

    public static function reportCategorySales(array $filters): array
    {
        [$whereSql, $params] = self::reportScope($filters);
        $stmt = Database::connect()->prepare("SELECT
                COALESCE(c.category_name, 'Uncategorized') AS category_name,
                oi.name AS item_name,
                COALESCE(SUM(oi.qty), 0) AS units_sold,
                COALESCE(SUM(oi.qty * oi.price), 0) AS order_value
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            LEFT JOIN payments p ON p.order_id = o.id
            LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
            LEFT JOIN categories c ON c.category_id = mi.category_id
            WHERE {$whereSql}
            GROUP BY COALESCE(c.category_name, 'Uncategorized'), oi.name
            ORDER BY category_name, item_name");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function reportScope(array $filters): array
    {
        $from = (string) ($filters['from'] ?? date('Y-01-01'));
        $toExclusive = (new DateTimeImmutable((string) ($filters['to'] ?? date('Y-m-d'))))
            ->modify('+1 day')
            ->format('Y-m-d');
        $conditions = [
            'o.created_at >= ?',
            'o.created_at < ?',
            "o.status = 'completed'",
            "p.payment_status = 'paid'",
        ];
        $params = [$from, $toExclusive];

        return [implode(' AND ', $conditions), $params];
    }

    public static function stats(): array
    {
        $pdo = Database::connect();
        $revenue = (float) $pdo->query('SELECT COALESCE(SUM(total),0) FROM orders')->fetchColumn();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $today = (float) $pdo->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at) = CURDATE()')->fetchColumn();

        $databaseDate = new DateTimeImmutable((string) $pdo->query('SELECT CURDATE()')->fetchColumn());
        $dailyRows = $pdo->query("SELECT DATE(created_at) AS period_key, COALESCE(SUM(total), 0) AS earnings
            FROM orders
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
              AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            GROUP BY DATE(created_at)")->fetchAll();
        $dailyMap = [];
        foreach ($dailyRows as $row) {
            $dailyMap[$row['period_key']] = (float) $row['earnings'];
        }
        $dailyEarnings = [];
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $day = $databaseDate->modify('-' . $daysAgo . ' days');
            $key = $day->format('Y-m-d');
            $dailyEarnings[] = ['label' => $day->format('M j'), 'earnings' => $dailyMap[$key] ?? 0.0];
        }

        $weeklyRows = $pdo->query("SELECT DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS week_start,
                COALESCE(SUM(total), 0) AS earnings
            FROM orders
            WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
              AND created_at < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)
            GROUP BY week_start")->fetchAll();
        $weeklyMap = [];
        foreach ($weeklyRows as $row) {
            $weeklyMap[$row['week_start']] = (float) $row['earnings'];
        }
        $weeklyEarnings = [];
        $monthStart = $databaseDate->modify('first day of this month');
        $monthEnd = $databaseDate->modify('last day of this month');
        $weeklyCaption = 'Revenue by week for ' . $databaseDate->format('F Y');
        $weekStart = $monthStart->modify('monday this week');
        $weekNumber = 1;
        while ($weekStart <= $monthEnd) {
            $key = $weekStart->format('Y-m-d');
            $weeklyEarnings[] = ['label' => 'Week ' . $weekNumber, 'earnings' => $weeklyMap[$key] ?? 0.0];
            $weekStart = $weekStart->modify('+1 week');
            $weekNumber++;
        }

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

        $yearlyRows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y') AS period_key,
                COALESCE(SUM(total), 0) AS earnings
            FROM orders
            WHERE created_at >= MAKEDATE(YEAR(CURDATE()) - 4, 1)
              AND created_at < DATE_ADD(MAKEDATE(YEAR(CURDATE()), 1), INTERVAL 1 YEAR)
            GROUP BY YEAR(created_at)")->fetchAll();
        $yearlyMap = [];
        foreach ($yearlyRows as $row) {
            $yearlyMap[$row['period_key']] = (float) $row['earnings'];
        }
        $yearlyEarnings = [];
        $currentYear = (int) $databaseDate->format('Y');
        for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
            $key = (string) $year;
            $yearlyEarnings[] = ['label' => $key, 'earnings' => $yearlyMap[$key] ?? 0.0];
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

        $earningsViews = [
            'daily' => $dailyEarnings,
            'weekly' => $weeklyEarnings,
            'monthly' => array_map(static fn(array $row): array => ['label' => $row['month'], 'earnings' => (float) $row['earnings']], $monthlyEarnings),
            'yearly' => $yearlyEarnings,
        ];

        return compact('revenue', 'count', 'today', 'statusMap', 'monthlyEarnings', 'yearlyEarnings', 'dailyEarnings', 'weeklyEarnings', 'weeklyCaption', 'earningsViews', 'categorySales');
    }
}
