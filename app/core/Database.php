<?php
class Database
{
    private static ?PDO $pdo = null;
    public static function connect(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        if (!is_dir(STORAGE_PATH)) {
            mkdir(STORAGE_PATH, 0777, true);
        }
        $fresh = !file_exists(DB_PATH);
        self::$pdo = new PDO('sqlite:' . DB_PATH);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        if ($fresh) {
            self::migrate();
            
        } else {
            self::migrateCategories();
        }
        self::ensurePasswordResetTable();
        return self::$pdo;
    }
    private static function ensurePasswordResetTable(): void
    {
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            otp_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');
    }

    private static function migrate(): void
    {
        $sql = <<<SQL
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'customer',
            phone TEXT,
            address TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE categories (
            category_id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_name TEXT NOT NULL UNIQUE
        );
        CREATE TABLE menu_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            price REAL NOT NULL,
            category_id INTEGER NOT NULL,
            image TEXT,
            available INTEGER NOT NULL DEFAULT 1,
            featured INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(category_id)
        );
        CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            total REAL NOT NULL,
            status TEXT NOT NULL DEFAULT 'preparing',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            menu_item_id INTEGER,
            name TEXT NOT NULL,
            qty INTEGER NOT NULL,
            price REAL NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        );
        SQL;
        self::$pdo->exec($sql);
    }
    private static function migrateCategories(): void
    {
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS categories (
            category_id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_name TEXT NOT NULL UNIQUE
        )');
        $columns = self::$pdo->query('PRAGMA table_info(menu_items)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (in_array('category_id', $columns, true)) {
            return;
        }

        self::$pdo->beginTransaction();
        try {
            self::$pdo->exec("INSERT OR IGNORE INTO categories (category_name)
                SELECT DISTINCT category FROM menu_items
                WHERE category IS NOT NULL AND TRIM(category) <> ''");
            self::$pdo->exec('CREATE TABLE menu_items_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                price REAL NOT NULL,
                category_id INTEGER NOT NULL,
                image TEXT,
                available INTEGER NOT NULL DEFAULT 1,
                featured INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(category_id)
            )');
            self::$pdo->exec("INSERT INTO menu_items_new (id, name, description, price, category_id, image, available, featured, created_at)
                SELECT menu_items.id, menu_items.name, menu_items.description, menu_items.price,
                    categories.category_id, menu_items.image, menu_items.available, menu_items.featured, menu_items.created_at
                FROM menu_items
                JOIN categories ON categories.category_name = menu_items.category");
            self::$pdo->exec('DROP TABLE menu_items');
            self::$pdo->exec('ALTER TABLE menu_items_new RENAME TO menu_items');
            self::$pdo->commit();
        } catch (Throwable $error) {
            self::$pdo->rollBack();
            throw $error;
        }
    }
}