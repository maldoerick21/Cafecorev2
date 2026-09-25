<?php
class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
    public static function create(array $data): int
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO users (name, email, password, role, phone, address) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'] ?? 'customer',
            $data['phone'] ?? null,
            $data['address'] ?? null,
        ]);
        return (int) Database::connect()->lastInsertId();
    }
    public static function update(int $id, array $data): void
    {
        $stmt = Database::connect()->prepare(
            'UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?'
        );
        $stmt->execute([$data['name'], $data['phone'], $data['address'], $id]);
    }
    public static function count(): int
    {
        return (int) Database::connect()->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn();
    }
    public static function toSession(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'phone' => $user['phone'] ?? '',
            'address' => $user['address'] ?? '',
        ];
    }
}