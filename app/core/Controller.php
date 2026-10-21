<?php
class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data);
        $viewFile = APP_ROOT . '/app/views/' . $view . '.html';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo 'View not found: ' . e($view);
            return;
        }
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        $layoutFile = APP_ROOT . '/app/views/layouts/' . $layout . '.html';
        require $layoutFile;
    }
    protected function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}