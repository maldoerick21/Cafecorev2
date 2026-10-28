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
    public static function allStaff(bool $archived = false): array
    {
        $stmt = Database::connect()->prepare("SELECT id, name, email, created_at, archived_at FROM users WHERE role = 'staff' AND archived_at IS " . ($archived ? 'NOT ' : '') . "NULL ORDER BY name, id");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public static function archiveStaff(array $ids, bool $restore = false): int
    {
        $ids = array_values(array_unique(array_filter(array_map(static function ($value): int {
            $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
            return $id === false ? 0 : $id;
        }, $ids), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return 0;
        }
        $sql = $restore
            ? "UPDATE users SET archived_at = NULL WHERE role = 'staff' AND archived_at IS NOT NULL AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')'
            : "UPDATE users SET archived_at = CURRENT_TIMESTAMP WHERE role = 'staff' AND archived_at IS NULL AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')';
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($ids);
        return $stmt->rowCount();
    }
    public static function createStaff(string $name, string $email, string $password): int
    {
        $stmt = Database::connect()->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'staff')"
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        return (int) Database::connect()->lastInsertId();
    }
    public static function deleteStaff(int $id): string
    {
        if ($id < 1) {
            return 'not_found';
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $staff = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'staff' FOR UPDATE");
            $staff->execute([$id]);
            if (!$staff->fetchColumn()) {
                $pdo->commit();
                return 'not_found';
            }

            $orders = $pdo->prepare('SELECT 1 FROM orders WHERE user_id = ? LIMIT 1');
            $orders->execute([$id]);
            if ($orders->fetchColumn()) {
                $pdo->rollBack();
                return 'has_orders';
            }

            $delete = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
            $delete->execute([$id]);
            $pdo->commit();
            return $delete->rowCount() === 1 ? 'deleted' : 'not_found';
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof PDOException && $error->getCode() === '23000') {
                return 'has_orders';
            }
            throw $error;
        }
    }
    public static function count(): int
    {
        return (int) Database::connect()->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn();
    }

    public static function createPasswordReset(int $userId, string $otpHash, string $expiresAt): void
    {
        $db = Database::connect();
        $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
        $stmt = $db->prepare('INSERT INTO password_reset_tokens (user_id, otp_hash, expires_at, attempts) VALUES (?, ?, ?, 0)');
        $stmt->execute([$userId, $otpHash, $expiresAt]);
    }

    public static function getPasswordReset(int $userId): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM password_reset_tokens WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function incrementPasswordResetAttempts(int $id): void
    {
        Database::connect()->prepare('UPDATE password_reset_tokens SET attempts = attempts + 1 WHERE id = ?')->execute([$id]);
    }

    public static function updatePassword(int $userId, string $password): void
    {
        $stmt = Database::connect()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        Database::connect()->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
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
