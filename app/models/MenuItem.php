<?php
class MenuItem
{
    public static function customizationOptions(string $category): array
    {
        $category = function_exists('mb_strtolower')
            ? mb_strtolower(trim($category), 'UTF-8')
            : strtolower(trim($category));
        $notBeverage = preg_match('/\b(food|foods|pastry|pastries|bakery|baked goods|dessert|desserts|snack|snacks|meal|meals|sandwich|sandwiches|cake|cakes|bread|breads)\b/u', $category) === 1;
        $isBeverage = preg_match('/\bdrinks?\b|\bbeverages?\b|\bcoffee\b|\btea\b|\bespresso\b|\blatte\b|\bfrappe\b|\bfrappuccino\b|\bsmoothies?\b|\bmilk[ -]?shakes?\b|\bmilk[ -]?tea\b|\bmilktea\b|\bjuices?\b|\bcocoa\b/u', $category) === 1;
        $customizable = !$notBeverage && $isBeverage;

        return ['size' => $customizable, 'sugar' => $customizable];
    }

    public static function all(?string $category = null, bool $availableOnly = false, bool $archived = false): array
    {
        $sql = 'SELECT menu_items.*, categories.category_name AS category
            FROM menu_items JOIN categories USING (category_id) WHERE menu_items.archived_at IS ' . ($archived ? 'NOT ' : '') . 'NULL';
        $params = [];
        if ($category) {
            $sql .= ' AND categories.category_name = ?';
            $params[] = $category;
        }
        if ($availableOnly) {
            $sql .= ' AND available = 1';
        }
        $sql .= ' ORDER BY featured DESC, name ASC';
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    public static function featured(): array
    {
        return Database::connect()
            ->query('SELECT menu_items.*, categories.category_name AS category
                FROM menu_items JOIN categories USING (category_id)
                WHERE featured = 1 AND available = 1 AND menu_items.archived_at IS NULL ORDER BY id LIMIT 4')
            ->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT menu_items.*, categories.category_name AS category
            FROM menu_items JOIN categories USING (category_id) WHERE id = ? AND menu_items.archived_at IS NULL');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    public static function categories(): array
    {
        return Database::connect()
            ->query('SELECT category_id, category_name FROM categories ORDER BY category_name')
            ->fetchAll();
    }
    public static function categoryExists(int $categoryId): bool
    {
        $stmt = Database::connect()->prepare('SELECT 1 FROM categories WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        return (bool) $stmt->fetchColumn();
    }
    public static function save(array $data, ?int $id = null): int
    {
        if ($id) {
            $stmt = Database::connect()->prepare(
                'UPDATE menu_items SET name=?, description=?, price=?, category_id=?, image=?, available=?, featured=? WHERE id=?'
            );
            $stmt->execute([
                $data['name'], $data['description'], $data['price'], $data['category_id'],
                $data['image'], $data['available'], $data['featured'], $id
            ]);
            return $id;
        }
        $stmt = Database::connect()->prepare(
            'INSERT INTO menu_items (name, description, price, category_id, image, available, featured) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['description'], $data['price'], $data['category_id'],
            $data['image'], $data['available'], $data['featured']
        ]);
        return (int) Database::connect()->lastInsertId();
    }
    public static function toggle(int $id, string $field): void
    {
        if (!in_array($field, ['available', 'featured'], true)) {
            return;
        }
        Database::connect()->prepare("UPDATE menu_items SET {$field} = CASE WHEN {$field}=1 THEN 0 ELSE 1 END WHERE id = ?")
            ->execute([$id]);
    }
    public static function delete(int $id): void
    {
        Database::connect()->prepare('DELETE FROM menu_items WHERE id = ?')->execute([$id]);
    }
    public static function archiveMany(array $ids, bool $restore = false): int
    {
        $ids = array_values(array_unique(array_filter(array_map(static function ($value): int {
            $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
            return $id === false ? 0 : $id;
        }, $ids), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return 0;
        }
        $sql = $restore
            ? 'UPDATE menu_items SET archived_at = NULL WHERE archived_at IS NOT NULL AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            : 'UPDATE menu_items SET archived_at = CURRENT_TIMESTAMP WHERE archived_at IS NULL AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($ids);
        return $stmt->rowCount();
    }
    public static function count(): int
    {
        return (int) Database::connect()->query('SELECT COUNT(*) FROM menu_items WHERE archived_at IS NULL')->fetchColumn();
    }
}
