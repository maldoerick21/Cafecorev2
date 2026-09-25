<?php
class Cart
{
    public static function items(): array
    {
        return $_SESSION['cart'] ?? [];
    }
    public static function count(): int
    {
        return array_sum(self::items());
    }
    public static function add(int $id, int $qty = 1): void
    {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + max(1, $qty);
    }
    public static function set(int $id, int $qty): void
    {
        if ($qty < 1) {
            unset($_SESSION['cart'][$id]);
            return;
        }
        $_SESSION['cart'][$id] = $qty;
    }
    public static function remove(int $id): void
    {
        unset($_SESSION['cart'][$id]);
    }
    public static function clear(): void
    {
        $_SESSION['cart'] = [];
    }
    public static function detailed(): array
    {
        $lines = [];
        $subtotal = 0;
        foreach (self::items() as $id => $qty) {
            $item = MenuItem::find((int) $id);
            if (!$item) {
                continue;
            }
            $line = $item['price'] * $qty;
            $subtotal += $line;
            $lines[] = [
                'item' => $item,
                'qty' => $qty,
                'line' => $line,
            ];
        }
        $service = $subtotal > 0 ? 15 : 0;
        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'service' => $service,
            'total' => $subtotal + $service,
        ];
    }
}