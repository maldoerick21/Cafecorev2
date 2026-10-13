<?php
class App
{
    private array $routes = [
        'GET' => [
            '' => ['AuthController', 'login'],
            'login' => ['AuthController', 'login'],
            'signup' => ['AuthController', 'signup'],
            'forgot' => ['AuthController', 'forgot'],
            'logout' => ['AuthController', 'logout'],
            'home' => ['HomeController', 'index'],
            'checkout' => ['CartController', 'index'],
            'history' => ['OrderController', 'history'],
            'profile' => ['ProfileController', 'index'],
            'admin' => ['AdminController', 'dashboard'],
            'admin/menu' => ['AdminController', 'menu'],
            'admin/menu/create' => ['AdminController', 'edit'],
            'admin/menu/edit' => ['AdminController', 'edit'],
        ],
        'POST' => [
            'login' => ['AuthController', 'loginPost'],
            'signup' => ['AuthController', 'signupPost'],
            'forgot' => ['AuthController', 'forgotPost'],
            'cart/add' => ['CartController', 'add'],
            'cart/update' => ['CartController', 'update'],
            'cart/remove' => ['CartController', 'remove'],
            'checkout' => ['CartController', 'place'],
            'orders/complete' => ['OrderController', 'complete'],
            'profile' => ['ProfileController', 'update'],
            'admin/menu/save' => ['AdminController', 'save'],
            'admin/menu/toggle' => ['AdminController', 'toggle'],
            'admin/menu/delete' => ['AdminController', 'delete'],
        ],
    ];
    public function __construct()
    {
        Database::connect();
        $this->dispatch();
    }
    private function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = preg_replace('#^' . preg_quote(BASE_URL, '#') . '#', '', $uri);
        $uri = trim($uri, '/');
        if (str_starts_with($uri, 'public/')) {
            return;
        }
        $handler = $this->routes[$method][$uri] ?? null;
        if (!$handler) {
            http_response_code(404);
            $this->notFound();
            return;
        }
        [$class, $action] = $handler;
        $controller = new $class();
        $controller->$action();
    }
    private function notFound(): void
    {
        $title = 'Not found';
        $content = '<section class="auth-card"><h1>Page not found</h1><p>That route does not exist.</p><a class="btn btn-primary" href="' . url('home') . '">Back home</a></section>';
        require APP_ROOT . '/app/views/layouts/auth.php';
    }
}