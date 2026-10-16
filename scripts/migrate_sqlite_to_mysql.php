<?php
/**
 * One-time CLI migration of existing CaféCore SQLite data into MySQL.
 * Import cafecore.sql into MySQL first, then run:
 *   php scripts/migrate_sqlite_to_mysql.php
 * Set DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD as needed.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this migration from the command line only.\n");
}

$root = dirname(__DIR__);
$sqliteFile = $root . '/storage/cafecore.sqlite';
if (!is_file($sqliteFile)) {
    exit("SQLite source database was not found: {$sqliteFile}\n");
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'cafecorev2';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

try {
    $source = new PDO('sqlite:' . $sqliteFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $source->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $target = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $tables = ['users', 'categories', 'menu_items', 'orders', 'order_items', 'password_reset_tokens'];
    foreach ($tables as $table) {
        $target->query("SELECT 1 FROM `{$table}` LIMIT 0");
        if ((int) $target->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() > 0) {
            throw new RuntimeException("MySQL table '{$table}' is not empty. Back up the database and migrate into an empty cafecorev2 database to avoid duplicate or mixed data.");
        }
    }

    $target->beginTransaction();
    $counts = [];
    foreach ($tables as $table) {
        $rows = $source->query("SELECT * FROM `{$table}`")->fetchAll();
        if (!$rows) {
            $counts[$table] = 0;
            continue;
        }
        $columns = array_keys($rows[0]);
        $quotedColumns = implode(', ', array_map(static fn($col) => '`' . str_replace('`', '``', $col) . '`', $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insert = $target->prepare("INSERT INTO `{$table}` ({$quotedColumns}) VALUES ({$placeholders})");
        foreach ($rows as $row) {
            $insert->execute(array_values($row));
        }
        $counts[$table] = count($rows);
    }

    // Keep AUTO_INCREMENT counters above the imported IDs.
    foreach ($tables as $table) {
        $target->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
    }
    $target->commit();
    echo "SQLite to MySQL migration completed successfully.\n";
    foreach ($counts as $table => $count) {
        echo sprintf("  %-24s %d rows\n", $table, $count);
    }
    echo "Keep storage/cafecore.sqlite as a backup until you verify the application.\n";
} catch (Throwable $error) {
    if (isset($target) && $target instanceof PDO && $target->inTransaction()) {
        $target->rollBack();
    }
    fwrite(STDERR, "Migration failed: " . $error->getMessage() . "\n");
    exit(1);
}
