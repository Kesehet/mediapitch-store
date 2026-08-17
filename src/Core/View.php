<?php

declare(strict_types=1);

namespace MediaPitch\Core;

use MediaPitch\Repositories\SettingsRepository;
use Throwable;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layout'): void
    {
        if($layout==='layout' && !array_key_exists('siteSettings',$data)){
            try{
                $data['siteSettings']=(new SettingsRepository())->site();
            }catch(Throwable){
                $data['siteSettings']=[
                    'name'=>'MediaPitch Store',
                    'tagline'=>'Independent buying guides, comparisons and product discovery.',
                    'affiliate_disclosure'=>'As an Amazon Associate, MediaPitch may earn from qualifying purchases. Product availability and prices can change on Amazon.',
                    'home_categories'=>true,'home_guides'=>true,'home_comparisons'=>true,'home_products'=>true,'home_articles'=>true,
                ];
            }
        }

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
