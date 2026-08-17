<?php

declare(strict_types=1);

namespace MediaPitch\Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layout'): void
    {
        $views = dirname(__DIR__, 2) . '/views';
        $viewFile = $views . '/' . ltrim($view, '/') . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found.';
            return;
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = $views . '/' . $layout . '.php';
        require $layoutFile;
    }
}
