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
            self::seed();
        } else {
            self::migrateCategories();
        }
        return self::$pdo;
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
    private static function seed(): void
    {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $users = self::$pdo->prepare('INSERT INTO users (name, email, password, role, phone, address) VALUES (?, ?, ?, ?, ?, ?)');
        $users->execute(['Maya Reyes', 'customer@cafecore.test', $hash, 'customer', '0917 555 0142', '12 Espresso Lane, Manila']);
        $users->execute(['CaféCore Admin', 'admin@cafecore.test', $hash, 'admin', '0917 555 0100', 'CaféCore HQ']);
        $items = [
            ['House Espresso', 'Double shot with a cocoa finish and lingering crema.', 95, 'Coffee', 'https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?w=800&q=80', 1, 1],
            ['Vanilla Cloud Latte', 'Silky steamed milk, house espresso, Madagascar vanilla.', 145, 'Coffee', 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=800&q=80', 1, 1],
            ['Burnt Honey Cappuccino', 'Honey foam, espresso, toasted oat crumble.', 155, 'Coffee', 'https://images.unsplash.com/photo-1572442388796-11668a67e53d?w=800&q=80', 1, 1],
            ['Cold Brew Tonic', '18-hour cold brew poured over citrus tonic.', 165, 'Coffee', 'https://images.unsplash.com/photo-1517701550927-30cf4ba1dba5?w=800&q=80', 1, 0],
            ['Matcha Velvet', 'Stone-ground matcha with oat milk and a white chocolate dust.', 175, 'Tea', 'https://images.unsplash.com/photo-1515823662972-da6a2e4d3002?w=800&q=80', 1, 1],
            ['Butter Croissant', 'Layered, shatter-crisp, baked twice daily.', 95, 'Pastry', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=800&q=80', 1, 1],
            ['Dark Cocoa Brownie', '70% chocolate, walnut crunch, sea salt.', 120, 'Pastry', 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=800&q=80', 1, 0],
            ['Tomato Basil Toast', 'Sourdough, slow tomatoes, basil oil, burrata.', 210, 'Brunch', 'https://images.unsplash.com/photo-1482049016688-2d3e1b311543?w=800&q=80', 1, 0],
            ['Seasonal Berry Bowl', 'Greek yogurt, local berries, pistachio granola.', 190, 'Brunch', 'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=800&q=80', 1, 0],
            ['Earl Grey Steam', 'Bergamot tea, milk foam, lavender honey.', 135, 'Tea', 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=800&q=80', 1, 0],
        ];
        $category = self::$pdo->prepare('INSERT OR IGNORE INTO categories (category_name) VALUES (?)');
        $categoryId = self::$pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
        $item = self::$pdo->prepare('INSERT INTO menu_items (name, description, price, category_id, image, available, featured) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($items as $row) {
            $category->execute([$row[3]]);
            $categoryId->execute([$row[3]]);
            $row[3] = (int) $categoryId->fetchColumn();
            $item->execute($row);
        }
        $customerId = 1;
        self::$pdo->prepare('INSERT INTO orders (user_id, total, status, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$customerId, 335, 'completed', '2026-09-18 09:14:00']);
        self::$pdo->prepare('INSERT INTO orders (user_id, total, status, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$customerId, 240, 'completed', '2026-09-21 16:42:00']);
        $oi = self::$pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, name, qty, price) VALUES (?, ?, ?, ?, ?)');
        $oi->execute([1, 2, 'Vanilla Cloud Latte', 1, 145]);
        $oi->execute([1, 6, 'Butter Croissant', 2, 95]);
        $oi->execute([2, 3, 'Burnt Honey Cappuccino', 1, 155]);
        $oi->execute([2, 7, 'Dark Cocoa Brownie', 1, 120]);
    }
}