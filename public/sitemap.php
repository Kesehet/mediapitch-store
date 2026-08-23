<?php

declare(strict_types=1);

use MediaPitch\Core\Database;
use MediaPitch\Services\ContentVisibility;

require dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base=rtrim(url(),'/');
$urls=[
    ['loc'=>$base.'/','lastmod'=>null],
    ['loc'=>$base.'/blog','lastmod'=>null],
    ['loc'=>$base.'/comparisons','lastmod'=>null],
];

try{
    $db=Database::connection();

    foreach($db->query("SELECT slug,updated_at FROM categories WHERE active=1 ORDER BY updated_at DESC")->fetchAll() as $row){
        $urls[]=['loc'=>$base.'/category/'.$row['slug'],'lastmod'=>$row['updated_at']??null];
    }
    foreach($db->query("SELECT slug,updated_at FROM brands WHERE active=1 ORDER BY updated_at DESC")->fetchAll() as $row){
        $urls[]=['loc'=>$base.'/brand/'.$row['slug'],'lastmod'=>$row['updated_at']??null];
    }
    foreach($db->query("SELECT slug,updated_at FROM products WHERE active=1 ORDER BY updated_at DESC")->fetchAll() as $row){
        $urls[]=['loc'=>$base.'/product/'.$row['slug'],'lastmod'=>$row['updated_at']??null];
    }
    $visibility=ContentVisibility::sql('');
    foreach($db->query("SELECT type,slug,updated_at FROM content WHERE $visibility AND robots_index=1 ORDER BY updated_at DESC")->fetchAll() as $row){
        $prefix=match($row['type']){
            'buying_guide'=>'guide',
            'comparison'=>'compare',
            'blog'=>'blog',
            'review'=>'review',
            default=>null,
        };
        if($prefix)$urls[]=['loc'=>$base.'/'.$prefix.'/'.$row['slug'],'lastmod'=>$row['updated_at']??null];
    }
}catch(Throwable $e){
    if((bool)env('APP_DEBUG',false))error_log('Sitemap database error: '.$e->getMessage());
}

$xmlEscape=static fn(string $value):string=>htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach($urls as $item){
    echo "  <url>\n    <loc>".$xmlEscape($item['loc'])."</loc>\n";
    if(!empty($item['lastmod']))echo "    <lastmod>".$xmlEscape(gmdate('c',strtotime((string)$item['lastmod'])))."</lastmod>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
