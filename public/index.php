<?php

declare(strict_types=1);

use MediaPitch\Admin\AdminController;
use MediaPitch\Core\Database;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Repositories\CatalogRepository;
use MediaPitch\Repositories\ContentRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

$catalog = new CatalogRepository();
$contentRepo = new ContentRepository();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}

try {
    if (str_starts_with($path, '/admin')) {
        $admin = new AdminController(new AdminRepository());
        if ($admin->handle($method, $path)) {
            exit;
        }
    }

    if ($method === 'GET' && $path === '/') {
        View::render('home', [
            'pageTitle' => 'MediaPitch Store — Smart Product Discovery',
            'metaDescription' => 'Independent buying guides, product comparisons and recommendations from MediaPitch.',
            'categories' => $catalog->featuredCategories(),
            'products' => $catalog->featuredProducts(),
            'guides' => $catalog->latestContent('buying_guide'),
            'articles' => $catalog->latestContent('blog'),
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

    if ($method === 'GET' && $path === '/search') {
        $query = trim((string) ($_GET['q'] ?? ''));
        View::render('search', [
            'pageTitle' => $query !== '' ? 'Search: ' . $query : 'Search',
            'metaDescription' => 'Search MediaPitch product recommendations.',
            'query' => $query,
            'results' => $query !== '' ? $catalog->search($query) : [],
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
