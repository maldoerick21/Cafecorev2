<?php
/**
 * Adds a starter CaféCore menu and downloads each item's photo from Unsplash.
 * Run from the project root: php scripts/seed_cafe_menu.php
 * Safe to rerun: existing items are updated instead of duplicated.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$pdo = Database::connect();
$imageDir = APP_ROOT . '/resources/menu-images';
if (!is_dir($imageDir) && !mkdir($imageDir, 0755, true) && !is_dir($imageDir)) {
    throw new RuntimeException('Could not create resources/menu-images.');
}

$menu = [
    ['Classic Latte','Smooth espresso balanced with steamed milk.','Coffee',125,'photo-1461023058943-07fcbe16d735',1],
    ['Iced Spanish Latte','Chilled espresso with creamy sweetened milk.','Coffee',145,'photo-1517701604599-bb29b565090c',1],
    ['Caramel Macchiato','Espresso, vanilla milk and caramel drizzle.','Coffee',155,'photo-1485808191679-5f86510681a2',1],
    ['Americano','Bold espresso topped with hot water or served iced.','Coffee',95,'photo-1514432324607-a09d9b4aefdd',0],
    ['Matcha Latte','Earthy Japanese matcha whisked with milk.','Non-Coffee',145,'photo-1515823064-d6e0c04616a7',1],
    ['Chocolate Frappe','Blended chocolate drink topped with whipped cream.','Non-Coffee',155,'photo-1572490122747-3968b75cc699',0],
    ['Strawberry Milk','Sweet strawberry milk served cold.','Non-Coffee',120,'photo-1553787499-6f9133860278',0],
    ['Classic Milk Tea','Black tea with creamy milk and chewy pearls.','Tea',120,'photo-1558857563-b371033873b8',0],
    ['Butter Croissant','Flaky, buttery pastry baked until golden.','Pastries',85,'photo-1555507036-ab1f4038808a',0],
    ['Chocolate Muffin','Soft chocolate muffin with rich chocolate chips.','Pastries',75,'photo-1607958996333-41aef7caefaa',0],
    ['Blueberry Cheesecake','Creamy cheesecake with a blueberry topping.','Desserts',145,'photo-1533134242443-d4fd215305ad',0],
    ['Chocolate Brownie','Fudgy chocolate brownie with a crisp top.','Desserts',90,'photo-1606313564200-e75d5e30476c',0],
    ['Classic Cheeseburger','Juicy beef patty, cheese, lettuce and house sauce.','Meals',185,'photo-1568901346375-23c9450c58cd',1],
    ['Crispy Chicken Sandwich','Crispy chicken fillet with fresh lettuce and sauce.','Meals',175,'photo-1606755962773-d324e0a13086',0],
    ['Pepperoni Pizza','Cheesy pizza topped with pepperoni and herbs.','Meals',220,'photo-1579751626657-72bc17010498',0],
    ['Creamy Carbonara','Creamy pasta with parmesan and smoky bacon.','Pasta',195,'photo-1555949264-9e9f3b5c0b5b',0],
    ['Chicken Pesto Pasta','Pasta tossed in basil pesto with grilled chicken.','Pasta',205,'photo-1473093295043-cdd812d0e601',0],
    ['French Fries','Golden, crispy fries with a pinch of salt.','Snacks',85,'photo-1573080496219-bb080dd4f877',0],
    ['Chicken Wings','Crispy chicken wings tossed in a savory glaze.','Snacks',165,'photo-1527477396000-e27163b481c2',0],
    ['Mango Graham Cup','Creamy mango dessert layered with graham crumbs.','Desserts',110,'photo-1559622214-f8a9850965bb',0],
];

// Replace generic placeholders with valid, specific Unsplash photo IDs.
$photoOverrides = [
    'Blueberry Cheesecake' => 'photo-1533134242443-d4fd215305ad',
    'Pepperoni Pizza' => 'photo-1579751626657-72bc17010498',
    'Creamy Carbonara' => 'photo-1555949264-9e9f3b5c0b5b',
];

$categoryIds = [];
$categoryInsert = $pdo->prepare('INSERT INTO categories (category_name) VALUES (?) ON DUPLICATE KEY UPDATE category_name = VALUES(category_name)');
$categoryFind = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
foreach (array_unique(array_column($menu, 2)) as $categoryName) {
    $categoryInsert->execute([$categoryName]);
    $categoryFind->execute([$categoryName]);
    $categoryIds[$categoryName] = (int)$categoryFind->fetchColumn();
}

$findItem = $pdo->prepare('SELECT id FROM menu_items WHERE name = ? LIMIT 1');
$insertItem = $pdo->prepare('INSERT INTO menu_items (name, description, price, category_id, image, available, featured) VALUES (?, ?, ?, ?, ?, 1, ?)');
$updateItem = $pdo->prepare('UPDATE menu_items SET description=?, price=?, category_id=?, image=?, available=1, featured=? WHERE id=?');
$downloaded = 0;
foreach ($menu as [$name, $description, $category, $price, $photoId, $featured]) {
    $photoId = $photoOverrides[$name] ?? $photoId;
    $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name)), '-'));
    $filename = $slug . '.jpg';
    $localFile = $imageDir . '/' . $filename;
    $photoUrl = 'https://images.unsplash.com/' . $photoId . '?auto=format&fit=crop&w=1000&q=82';
    if (!is_file($localFile) || filesize($localFile) < 5000) {
        $context = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => 1, 'user_agent' => 'CafeCore menu image downloader']]);
        $image = @file_get_contents($photoUrl, false, $context);
        if ($image !== false && strlen($image) > 5000 && str_starts_with($image, "\xFF\xD8")) {
            file_put_contents($localFile, $image);
            $downloaded++;
        } else {
            echo "WARNING: Could not download photo for {$name}. Check internet access and photo ID: {$photoUrl}\n";
        }
    }
    $imagePath = url('resources/menu-images/' . $filename);
    $findItem->execute([$name]);
    $existingId = $findItem->fetchColumn();
    if ($existingId) {
        $updateItem->execute([$description, $price, $categoryIds[$category], $imagePath, $featured, (int)$existingId]);
    } else {
        $insertItem->execute([$name, $description, $price, $categoryIds[$category], $imagePath, $featured]);
    }
    echo ($existingId ? 'Updated' : 'Added') . ": {$name} — ₱" . number_format($price, 2) . "\n";
}
echo "\nMenu setup complete. New photos downloaded: {$downloaded}.\n";
