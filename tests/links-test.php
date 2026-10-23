<?php
declare(strict_types=1);

// The public guide template normally uses a database-backed related-content widget.
// Stub only that unrelated dependency: link resolution and rendering remain real.
namespace MediaPitch\Repositories {
    final class RelatedContentRepository {
        public function forContent(int $contentId, ?int $categoryId = null): array { return []; }
    }
}

namespace {
    require dirname(__DIR__).'/src/bootstrap.php';

    function check(bool $ok, string $message): void {
        if(!$ok)throw new \RuntimeException($message);
        echo "PASS: ".$message.PHP_EOL;
    }
    function renderFixture(string $file, array $vars): string {
        extract($vars, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__).'/'.$file;
        return (string)ob_get_clean();
    }

    $link='https://www.amazon.in/dp/B0TEST1234?tag=mediapitch-21';
    $affiliate='https://example.com/item?campaign=media';
    $links=\MediaPitch\Services\ProductLinks::class;
    check($links::destination(['affiliate_url'=>$affiliate,'amazon_url'=>$link])===$affiliate,'Affiliate URL takes precedence');
    check($links::destination(['affiliate_url'=>'','amazon_url'=>$link])===$link,'Amazon URL is a visible fallback');
    check($links::destination(['affiliate_url'=>'javascript:alert(1)','amazon_url'=>$link])===$link,'Unsafe affiliate URLs fall back safely');
    check($links::destination(['affiliate_url'=>'data:text/html,bad','amazon_url'=>''])===null,'Unsafe links are not emitted');
    check($links::destination(['affiliate_url'=>'','amazon_url'=>''])===null,'Missing destination remains missing');

    $rich='<h2>Useful links</h2><p>Read <a href="https://example.com/a?x=1&amp;y=2" target="_blank">external</a>, <a href="/guide/example">internal</a>, <a href="#part">section</a> and <a href="mailto:editor@example.com">contact</a>.</p>';
    $clean=\MediaPitch\Core\Html::sanitize($rich);
    check(str_contains($clean,'href="https://example.com/a?x=1&amp;y=2"'),'Sanitizer retains external links with query strings');
    check(str_contains($clean,'href="/guide/example"') && str_contains($clean,'href="#part"'),'Sanitizer retains internal and anchor links');
    check(str_contains($clean,'href="mailto:editor@example.com"'),'Sanitizer retains contact links');
    check(str_contains($clean,'noopener noreferrer'),'External new-tab links get safe rel attributes');
    check(!str_contains(\MediaPitch\Core\Html::sanitize('<a href="javascript:alert(1)">bad</a>'),'href='),'Executable protocols are removed');

    $rendered=(new \MediaPitch\Services\GuideContent())->render($rich);
    check(str_contains($rendered['html'],'href="/guide/example"'),'Published guide body retains hyperlinks');
    check(count($rendered['toc'])===(!class_exists(\DOMDocument::class)?0:1),'Guide TOC builds without losing body links');
    check(str_contains(editorial_html($rich),'href="https://example.com/a?x=1&amp;y=2"'),'Published blog body retains hyperlinks');

    $product=[
        'id'=>42,'title'=>'Sample test device','display_title'=>'','slug'=>'sample-test-device',
        'source'=>'manual','price'=>null,'currency'=>'INR','previous_price'=>null,'custom_score'=>null,
        'main_image_url'=>null,'features_json'=>null,'pros_json'=>null,'cons_json'=>null,'gallery_json'=>null,
        'short_description'=>'','full_description'=>'<p><a href="/blog/example">Read review</a></p>',
        'brand_id'=>null,'brand_name'=>null,'category_name'=>null,'category_slug'=>null,
        'best_for_label'=>null,'asin'=>null,'amazon_url'=>$link,'affiliate_url'=>null
    ];
    $public=renderFixture('views/product.php',['product'=>$product,'relatedProducts'=>[],'relatedGuides'=>[]]);
    check(str_contains($public,'href="/go/42?from=product"'),'Public product page shows purchase link using Amazon fallback');
    check(str_contains($public,'href="/blog/example"'),'Public product description keeps embedded editorial links');
    $product['amazon_url']=null;
    $public=renderFixture('views/product.php',['product'=>$product,'relatedProducts'=>[],'relatedGuides'=>[]]);
    check(!str_contains($public,'href="/go/42?from=product"'),'Product page does not emit broken /go links');

    $product['amazon_url']=$link;
    $guide=['id'=>17,'title'=>'Test guide','slug'=>'test-guide','excerpt'=>'','body'=>$rich,'products'=>[
        array_merge($product,['rank_position'=>1,'guide_score'=>null,'guide_best_for'=>null,'recommendation'=>'','cta_text'=>'Check price','active'=>1])
    ]];
    $public=renderFixture('views/guide.php',['guide'=>$guide]);
    check(str_contains($public,'href="/go/42?content=17&amp;rank=1&amp;from=guide"') || str_contains($public,'href="/go/42?content=17&rank=1&from=guide"'),'Buying guide ranks show purchase CTA with fallback');
    check(str_contains($public,'href="/guide/example"'),'Published guide template retains editorial links');

    $admin=renderFixture('views/admin/products.php',['products'=>[
        array_merge($product,['brand_name'=>'','category_name'=>'Test','active'=>1,'updated_at'=>'2026-10-23']),
        array_merge($product,['id'=>43,'amazon_url'=>null,'affiliate_url'=>null,'active'=>0])
    ]]);
    check(str_contains($admin,'Purchase link') && str_contains($admin,'Open link'),'CMS products list shows real purchase links');
    check(str_contains($admin,'Amazon fallback') && str_contains($admin,'Missing'),'CMS distinguishes fallback from missing links');

    $bootstrap=file_get_contents(dirname(__DIR__).'/src/bootstrap.php');
    check(str_contains($bootstrap,"script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net"),'CMS editor CDN is allowed by script CSP');
    check(str_contains($bootstrap,"https://fonts.googleapis.com https://cdn.jsdelivr.net"),'CMS editor stylesheet CDN is allowed by style CSP');

    echo "All link regression checks passed.".PHP_EOL;
}
