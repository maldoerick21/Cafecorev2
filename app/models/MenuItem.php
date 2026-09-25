<?php
class MenuItem
{
    public static function all(?string $category = null, bool $availableOnly = false): array
    {
        $sql = 'SELECT * FROM menu_items WHERE 1=1';
        $params = [];
        if ($category) {
            $sql .= ' AND category = ?';
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
            ->query('SELECT * FROM menu_items WHERE featured = 1 AND available = 1 ORDER BY id LIMIT 4')
            ->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM menu_items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    public static function categories(): array
    {
        return Database::connect()
            ->query('SELECT DISTINCT category FROM menu_items ORDER BY category')
            ->fetchAll(PDO::FETCH_COLUMN);
    }
    public static function save(array $data, ?int $id = null): int
    {
        if ($id) {
            $stmt = Database::connect()->prepare(
                'UPDATE menu_items SET name=?, description=?, price=?, category=?, image=?, available=?, featured=? WHERE id=?'
            );
            $stmt->execute([
                $data['name'], $data['description'], $data['price'], $data['category'],
                $data['image'], $data['available'], $data['featured'], $id
            ]);
            return $id;
        }
        $stmt = Database::connect()->prepare(
            'INSERT INTO menu_items (name, description, price, category, image, available, featured) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['description'], $data['price'], $data['category'],
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