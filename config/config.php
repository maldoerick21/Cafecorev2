<?php
define('APP_NAME', 'CaféCore');
define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_PATH', APP_ROOT . '/public');
define('STORAGE_PATH', APP_ROOT . '/storage');
// MySQL connection settings can be overridden with DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD environment variables.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
    $scriptDir = '';
}
define('BASE_URL', rtrim($scriptDir, '/'));
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}
function asset(string $path): string
{
    return url('public/' . ltrim($path, '/'));
}
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid session token.');
    }
}
function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}
function flash(string $key, ?string $value = null)
{
    if ($value === null) {
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }
    $_SESSION['_flash'][$key] = $value;
}
function auth(): ?array
{
    return $_SESSION['user'] ?? null;
}
function is_admin(): bool
{
    return (auth()['role'] ?? '') === 'admin';
}
function require_auth(): array
{
    $user = auth();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('login');
    }
    return $user;
}
function require_admin(): array
{
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        flash('error', 'Admin access only.');
        redirect('home');
    }
    return $user;
}
function money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}