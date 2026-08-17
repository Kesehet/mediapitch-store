<?php

declare(strict_types=1);

use MediaPitch\Admin\AdminController;
use MediaPitch\Admin\ComparisonAdminController;
use MediaPitch\Admin\MediaAdminController;
use MediaPitch\Admin\ReviewAdminController;
use MediaPitch\Admin\SettingsAdminController;
use MediaPitch\Core\Database;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Repositories\CatalogRepository;
use MediaPitch\Repositories\ComparisonRepository;
use MediaPitch\Repositories\ContentRepository;
use MediaPitch\Repositories\MediaRepository;
use MediaPitch\Repositories\ReviewRepository;
use MediaPitch\Repositories\SearchRepository;
use MediaPitch\Repositories\SettingsRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

$catalog = new CatalogRepository();
$contentRepo = new ContentRepository();
$comparisonRepo = new ComparisonRepository();
$reviewRepo = new ReviewRepository();
$searchRepo = new SearchRepository();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}

try {
    if (str_starts_with($path, '/admin/media')) {
        $mediaAdmin = new MediaAdminController(new MediaRepository());
        if ($mediaAdmin->handle($method, $path)) {
            exit;
        }
    }

    if (str_starts_with($path, '/admin/reviews')) {
        $reviewAdmin = new ReviewAdminController($reviewRepo, new AdminRepository());
        if ($reviewAdmin->handle($method, $path)) {
            exit;
        }
    }

    if (str_starts_with($path, '/admin/comparisons')) {
        $comparisonAdmin = new ComparisonAdminController($comparisonRepo, new AdminRepository());
        if ($comparisonAdmin->handle($method, $path)) {
            exit;
        }
    }

    if (str_starts_with($path, '/admin/settings')) {
        $settingsAdmin = new SettingsAdminController(new SettingsRepository());
        if ($settingsAdmin->handle($method, $path)) {
            exit;
        }
    }

    if (str_starts_with($path, '/admin')) {
        $admin = new AdminController(new AdminRepository());
        if ($admin->handle($method, $path)) {
            exit;
        }
    }

    if ($method === 'GET' && $path === '/api/search-suggestions') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=60');
        $query=trim((string)($_GET['q']??''));
        echo json_encode(['suggestions'=>$searchRepo->suggestions($query)],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        exit;
    }

    if ($method === 'GET' && $path === '/') {
        $categories = [];
        $products = [];
        $guides = [];
        $articles = [];
        $databaseAvailable = true;

        try {
            $categories = $catalog->featuredCategories();
            $products = $catalog->featuredProducts();
            $guides = $catalog->latestContent('buying_guide');
            $articles = $catalog->latestContent('blog');
        } catch (Throwable $databaseError) {
            $databaseAvailable = false;
            if ((bool) env('APP_DEBUG', false)) {
                error_log('MediaPitch Store homepage database error: ' . $databaseError->getMessage());
            }
        }

        View::render('home', [
            'pageTitle' => 'MediaPitch Store — Smart Product Discovery',
            'metaDescription' => 'Independent buying guides, product comparisons and recommendations from MediaPitch.',
            'categories' => $categories,
            'products' => $products,
            'guides' => $guides,
            'articles' => $articles,
            'databaseAvailable' => $databaseAvailable,
        ]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/category/([a-z0-9-]+)$#i', $path, $matches)) {
        $category=$catalog->categoryBySlug($matches[1]);
        if(!$category){
            http_response_code(404);
            View::render('404',['pageTitle'=>'Category not found','metaDescription'=>'']);
            exit;
        }
        View::render('category',[
            'pageTitle'=>($category['seo_title'] ?: $category['name']) . ' — MediaPitch Store',
            'metaDescription'=>(string)($category['meta_description'] ?: $category['description']),
            'canonicalUrl'=>url('category/' . $category['slug']),
            'category'=>$category,
        ]);
        exit;
    }

    if ($method === 'GET' && $path === '/blog') {
        View::render('blog-index', [
            'pageTitle'=>'MediaPitch Blog — Buying Advice & Product Guides',
            'metaDescription'=>'Buying advice, product explainers, how-to articles and shopping insights from MediaPitch.',
            'posts'=>$contentRepo->publishedPosts(),
        ]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/blog/([a-z0-9-]+)$#i', $path, $matches)) {
        $post=$contentRepo->publishedPostBySlug($matches[1]);
        if (!$post) {
            http_response_code(404);
            View::render('404',['pageTitle'=>'Article not found','metaDescription'=>'']);
            exit;
        }
        View::render('article',[
            'pageTitle'=>($post['seo_title'] ?: $post['title']) . ' — MediaPitch',
            'metaDescription'=>(string)($post['meta_description'] ?: $post['excerpt']),
            'canonicalUrl'=>$post['canonical_url'] ?: url('blog/' . $post['slug']),
            'robotsIndex'=>(bool)$post['robots_index'],
            'post'=>$post,
        ]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/compare/([a-z0-9-]+)$#i', $path, $matches)) {
        $comparison=$comparisonRepo->publishedBySlug($matches[1]);
        if(!$comparison){
            http_response_code(404);
            View::render('404',['pageTitle'=>'Comparison not found','metaDescription'=>'']);
            exit;
        }
        View::render('comparison',[
            'pageTitle'=>($comparison['seo_title'] ?: $comparison['title']) . ' — MediaPitch',
            'metaDescription'=>(string)($comparison['meta_description'] ?: $comparison['excerpt']),
            'canonicalUrl'=>$comparison['canonical_url'] ?: url('compare/' . $comparison['slug']),
            'robotsIndex'=>(bool)$comparison['robots_index'],
            'comparison'=>$comparison,
        ]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/review/([a-z0-9-]+)$#i', $path, $matches)) {
        $review=$reviewRepo->publishedBySlug($matches[1]);
        if(!$review){
            http_response_code(404);
            View::render('404',['pageTitle'=>'Review not found','metaDescription'=>'']);
            exit;
        }
        View::render('review',[
            'pageTitle'=>($review['seo_title'] ?: $review['title']) . ' — MediaPitch',
            'metaDescription'=>(string)($review['meta_description'] ?: $review['excerpt']),
            'canonicalUrl'=>$review['canonical_url'] ?: url('review/' . $review['slug']),
            'robotsIndex'=>(bool)$review['robots_index'],
            'review'=>$review,
        ]);
        exit;
    }

    if ($method === 'GET' && $path === '/search') {
        $query = trim((string) ($_GET['q'] ?? ''));
        $page=max(1,(int)($_GET['page']??1));
        View::render('search', [
            'pageTitle' => $query !== '' ? 'Search: ' . $query . ' — MediaPitch' : 'Search — MediaPitch',
            'metaDescription' => 'Search MediaPitch products, categories, buying guides, comparisons and articles.',
            'query' => $query,
            'results' => $query !== '' ? $searchRepo->search($query,$page) : ['products'=>[],'categories'=>[],'guides'=>[],'comparisons'=>[],'articles'=>[],'reviews'=>[],'pagination'=>['page'=>1,'pages'=>1,'product_total'=>0]],
        ]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/product/([a-z0-9-]+)$#i', $path, $matches)) {
        $product = $catalog->productBySlug($matches[1]);
        if (!$product) {
            http_response_code(404);
            View::render('404', ['pageTitle' => 'Product not found', 'metaDescription' => '']);
            exit;
        }

        View::render('product', [
            'pageTitle' => ($product['display_title'] ?: $product['title']) . ' — MediaPitch',
            'metaDescription' => (string) ($product['short_description'] ?? ''),
            'canonicalUrl'=>url('product/' . $product['slug']),
            'product' => $product,
        ]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/guide/([a-z0-9-]+)$#i', $path, $matches)) {
        $guide = $catalog->buyingGuideBySlug($matches[1]);
        if (!$guide) {
            http_response_code(404);
            View::render('404', ['pageTitle' => 'Guide not found', 'metaDescription' => '']);
            exit;
        }

        View::render('guide', [
            'pageTitle' => ($guide['seo_title'] ?: $guide['title']) . ' — MediaPitch',
            'metaDescription' => (string) ($guide['meta_description'] ?: $guide['excerpt']),
            'canonicalUrl'=>$guide['canonical_url'] ?: url('guide/' . $guide['slug']),
            'robotsIndex'=>(bool)$guide['robots_index'],
            'guide' => $guide,
        ]);
        exit;
    }

    if ($method === 'GET' && preg_match('#^/go/(\d+)$#', $path, $matches)) {
        $product = $catalog->productById((int) $matches[1]);
        if (!$product || empty($product['affiliate_url'])) {
            http_response_code(404);
            View::render('404', ['pageTitle' => 'Link unavailable', 'metaDescription' => '']);
            exit;
        }

        $contentId = isset($_GET['content']) ? (int) $_GET['content'] : null;
        $rank = isset($_GET['rank']) ? (int) $_GET['rank'] : null;
        $catalog->recordAffiliateClick(
            (int) $product['id'],
            $contentId ?: null,
            $rank ?: null,
            isset($_GET['from']) ? substr((string) $_GET['from'], 0, 100) : null,
            isset($_SERVER['HTTP_REFERER']) ? substr((string) $_SERVER['HTTP_REFERER'], 0, 2000) : null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 1000) : null,
            isset($_GET['campaign']) ? substr((string) $_GET['campaign'], 0, 255) : null
        );

        header('Location: ' . $product['affiliate_url'], true, 302);
        header('Referrer-Policy: no-referrer-when-downgrade');
        exit;
    }

    if ($method === 'GET' && $path === '/health') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            Database::connection()->query('SELECT 1');
            echo json_encode(['status' => 'ok', 'database' => 'ok'], JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            http_response_code(503);
            echo json_encode(['status' => 'degraded', 'database' => 'unavailable'], JSON_THROW_ON_ERROR);
        }
        exit;
    }

    http_response_code(404);
    View::render('404', ['pageTitle' => 'Page not found', 'metaDescription' => '']);
} catch (Throwable $e) {
    http_response_code(500);
    if ((bool) env('APP_DEBUG', false)) {
        echo '<pre>' . e($e->getMessage()) . "\n\n" . e($e->getTraceAsString()) . '</pre>';
    } else {
        View::render('500', ['pageTitle' => 'Something went wrong', 'metaDescription' => '']);
    }
}
