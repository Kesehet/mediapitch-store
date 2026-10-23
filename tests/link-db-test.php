<?php
declare(strict_types=1);

require dirname(__DIR__).'/src/bootstrap.php';

use MediaPitch\Amazon\AmazonProductImporter;
use MediaPitch\Core\Database;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Repositories\CatalogRepository;
use MediaPitch\Services\ProductAuthoring;
use MediaPitch\Services\ProductLinks;
use MediaPitch\Services\ProductOverrides;

function verify(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException($message);
    echo 'PASS: '.$message.PHP_EOL;
}

$db=Database::connection();
$db->exec("INSERT INTO users (name,email,password_hash,role) VALUES ('Test editor','ci-links@example.invalid','not-a-real-password','administrator')");
$userId=(int)$db->lastInsertId();
$admin=new AdminRepository();
$authoring=new ProductAuthoring();
$importer=new AmazonProductImporter();

$asin='B0A1B2C3D4';
$customLink='https://offers.example.com/product?tag=custom';
$original='https://www.amazon.in/dp/'.$asin;
$input=[
    'title'=>'Protected test product','slug'=>'protected-test-product','asin'=>$asin,
    'source'=>'manual','amazon_url'=>$original,'affiliate_url'=>$customLink,
    'active'=>1,'amazon_override_present'=>1,'amazon_override'=>['affiliate_url'=>1],
];
$id=$admin->saveProduct($authoring->prepare($input),null);
ProductAuthoring::persistPendingOverrides($id);
$first=$admin->product($id);
verify(($first['affiliate_url']??'')===$customLink,'CMS stores the affiliate link');
verify((new ProductOverrides())->forProduct($first)['affiliate_url'],'CMS stores the checkbox protection (previous regression)');

$item=['asin'=>$asin,'detailPageURL'=>'https://www.amazon.in/dp/'.$asin.'?tag=api','itemInfo'=>['title'=>['displayValue'=>'Updated title']]];
$importer->import($item,['marketplace'=>'www.amazon.in']);
$after=$admin->product($id);
verify(($after['affiliate_url']??'')===$customLink,'Amazon refresh preserves the protected affiliate link');
verify(str_contains((string)$after['amazon_url'],'tag=api'),'Amazon URL may be refreshed separately');

$secondAsin='B0D1E2F3G4';
$legacyUrl='https://offers.example.com/legacy';
$legacyId=$admin->saveProduct([
    'title'=>'Legacy editorial link','slug'=>'legacy-editorial-link','asin'=>$secondAsin,
    'source'=>'manual','amazon_url'=>'https://www.amazon.in/dp/'.$secondAsin,
    'affiliate_url'=>$legacyUrl,'active'=>1,
],null);
$second=['asin'=>$secondAsin,'detailPageURL'=>'https://www.amazon.in/dp/'.$secondAsin.'?tag=api','itemInfo'=>['title'=>['displayValue'=>'Updated legacy']]];
$importer->import($second,['marketplace'=>'www.amazon.in']);
verify(($admin->product($legacyId)['affiliate_url']??'')===$legacyUrl,'Legacy hand-edited affiliate link survives without prior override flags');
$second['detailPageURL']='';
$importer->import($second,['marketplace'=>'www.amazon.in']);
verify(ProductLinks::destination($admin->product($legacyId))===$legacyUrl,'API response without URL does not erase a saved destination');

$fallbackId=$admin->saveProduct([
    'title'=>'Fallback product','slug'=>'fallback-product','source'=>'manual',
    'amazon_url'=>'https://www.amazon.in/dp/B0F1G2H3J4','affiliate_url'=>'','active'=>1
],null);
$fallback=$admin->product($fallbackId);
verify(ProductLinks::destination($fallback)===$fallback['amazon_url'],'Public product uses the Amazon URL fallback');
$listed=array_filter($admin->products(),static fn($p)=>(int)$p['id']===$fallbackId);
verify(count($listed)===1 && ProductLinks::destination(reset($listed))===$fallback['amazon_url'],'CMS list and public page agree on the link');

$body='<p>Review <a href="https://example.org/details?ref=editor">external</a> and <a href="/product/fallback-product">internal</a>.</p>';
$guideId=$admin->saveGuide([
    'title'=>'Test linked guide','slug'=>'test-linked-guide','body'=>$body,
    'status'=>'published','published_at'=>gmdate('Y-m-d H:i:s',time()-7200),
    'product_id'=>[$fallbackId],'product_title'=>['Fallback product'],'rank_position'=>[1],
    'cta_text'=>['Check price'],
],$userId);
$fromAdmin=$admin->guide($guideId);
verify(str_contains((string)$fromAdmin['body'],'href="/product/fallback-product"'),'CMS saves guide hyperlinks verbatim');
$public=(new CatalogRepository())->buyingGuideBySlug('test-linked-guide');
verify(is_array($public) && count($public['products'])===1,'Published guide fetch contains ranked product');
verify(ProductLinks::destination($public['products'][0])===$fallback['amazon_url'],'Public guide query includes Amazon URL fallback');
verify(str_contains(\MediaPitch\Core\Html::sanitize($public['body']),'href="https://example.org/details?ref=editor"'),'Published HTML retains saved editorial hyperlinks');

echo "All database link integration tests passed.".PHP_EOL;
