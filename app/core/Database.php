<?php
class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'cafecorev2';
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        self::$pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        self::ensurePasswordResetTable();
        self::ensureOrderFulfillmentColumns();
        self::ensurePaymentsTable();
        self::ensureArchiveColumns();
        return self::$pdo;
    }

    private static function ensureArchiveColumns(): void
    {
        foreach (['orders', 'menu_items', 'users'] as $table) {
            $columns = self::$pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('archived_at', $columns, true)) {
                self::$pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN archived_at DATETIME NULL');
            }
        }
    }

    private static function ensureOrderFulfillmentColumns(): void
    {
        $columns = self::$pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('fulfillment_type', $columns, true)) {
            self::$pdo->exec("ALTER TABLE orders ADD COLUMN fulfillment_type VARCHAR(20) NOT NULL DEFAULT 'pickup'");
        }
        if (!in_array('delivery_address', $columns, true)) {
            self::$pdo->exec('ALTER TABLE orders ADD COLUMN delivery_address TEXT NULL');
        }
    }

    private static function ensurePasswordResetTable(): void
    {
        self::$pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private static function ensurePaymentsTable(): void
    {
        self::$pdo->exec("CREATE TABLE IF NOT EXISTS payments (
            payment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL UNIQUE,
            payment_date DATETIME NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
            CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$pdo->exec("INSERT INTO payments (order_id, amount, payment_method, payment_status)
            SELECT o.id, o.total, 'Cash on Delivery', 'pending'
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE p.payment_id IS NULL");
        self::$pdo->exec("UPDATE payments SET payment_method = 'Cash on Delivery' WHERE LOWER(payment_method) = 'counter'");
    }
}
