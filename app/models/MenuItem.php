<?php
class MenuItem
{
    public static function all(?string $category = null, bool $availableOnly = false): array
    {
        $sql = 'SELECT menu_items.*, categories.category_name AS category
            FROM menu_items JOIN categories USING (category_id) WHERE 1=1';
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
                WHERE featured = 1 AND available = 1 ORDER BY id LIMIT 4')
            ->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT menu_items.*, categories.category_name AS category
            FROM menu_items JOIN categories USING (category_id) WHERE id = ?');
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
    public static function count(): int
    {
        return (int) Database::connect()->query('SELECT COUNT(*) FROM menu_items')->fetchColumn();
    }
}