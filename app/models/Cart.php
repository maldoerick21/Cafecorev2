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
    public static function makeKey(int $id, string $size = 'Medium', int $sugar = 100): string
    {
        $size = in_array($size, ['Small', 'Medium', 'Large'], true) ? $size : 'Medium';
        $sugar = in_array($sugar, [0, 25, 50, 75, 100], true) ? $sugar : 100;
        return $id . '|' . $size . '|' . $sugar;
    }
    public static function add(int $id, int $qty = 1, string $size = 'Medium', int $sugar = 100): void
    {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $key = self::makeKey($id, $size, $sugar);
        $_SESSION['cart'][$key] = ($_SESSION['cart'][$key] ?? 0) + max(1, min(99, $qty));
    }
    public static function set(string $key, int $qty): void
    {
        if ($qty < 1) unset($_SESSION['cart'][$key]);
        else if (isset($_SESSION['cart'][$key])) $_SESSION['cart'][$key] = min(99, $qty);
    }
    public static function remove(string $key): void { unset($_SESSION['cart'][$key]); }
    public static function clear(): void { $_SESSION['cart'] = []; }
    public static function detailed(): array
    {
        $lines = []; $subtotal = 0;
        foreach (self::items() as $key => $qty) {
            $parts = explode('|', (string) $key);
            $id = (int) ($parts[0] ?? $key);
            $size = $parts[1] ?? 'Medium';
            $sugar = isset($parts[2]) ? (int) $parts[2] : 100;
            if (!in_array($size, ['Small', 'Medium', 'Large'], true)) $size = 'Medium';
            if (!in_array($sugar, [0,25,50,75,100], true)) $sugar = 100;
            $item = MenuItem::find($id);
            if (!$item) continue;
            $extra = ['Small' => 0, 'Medium' => 15, 'Large' => 25][$size];
            $price = (float) $item['price'] + $extra;
            $line = $price * $qty; $subtotal += $line;
            $lines[] = ['key' => (string)$key, 'item' => $item, 'qty' => $qty, 'size' => $size, 'sugar' => $sugar, 'price' => $price, 'line' => $line];
        }
        // Over-the-counter pickup has no service fee.
        $service = 0;
        return ['lines' => $lines, 'subtotal' => $subtotal, 'service' => $service, 'total' => $subtotal + $service];
    }
}
