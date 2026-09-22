<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$failures=[];

$checks=[
    'views/admin/blog-form.php'=>['name="tags"','name="seo_title"','name="meta_description"','name="canonical_url"','name="robots_index"'],
    'views/admin/guide-form.php'=>['name="tags"','name="seo_title"','name="meta_description"','name="canonical_url"','name="robots_index"'],
    'views/admin/review-form.php'=>['name="tags"','name="seo_title"','name="meta_description"','name="canonical_url"','name="robots_index"'],
    'views/admin/comparison-form.php'=>['name="tags"','name="seo_title"','name="meta_description"','name="canonical_url"','name="robots_index"'],
    'views/admin/product-form.php'=>['name="seo_title"','name="meta_description"','name="canonical_url"','name="robots_index"'],
    'views/admin/categories.php'=>['name="seo_title"','name="meta_description"','name="canonical_url"','name="robots_index"'],
    'views/admin/brands.php'=>['name="description"','name="seo_title"','name="meta_description"','name="canonical_url"','name="robots_index"'],
    'views/admin/layout.php'=>['admin-form-drafts.js'],
    'public/index.php'=>['/admin/form-drafts','/tag/'],
    'public/sitemap.php'=>['/tag/','robots_index=1'],
];

foreach($checks as $path=>$needles){
    $full=$root.'/'.$path;
    if(!is_file($full)){
        $failures[]=$path.': missing file';
        continue;
    }
    $source=(string)file_get_contents($full);
    foreach($needles as $needle){
        if(!str_contains($source,$needle))$failures[]=$path.': missing '.$needle;
    }
}

$migration=$root.'/database/migrations/016_seo_and_admin_form_drafts.sql';
if(!is_file($migration)){
    $failures[]='migration 016 missing';
}else{
    $sql=(string)file_get_contents($migration);
    foreach(['admin_form_drafts','ALTER TABLE products','ALTER TABLE categories','ALTER TABLE brands'] as $needle){
        if(!str_contains($sql,$needle))$failures[]='migration 016 missing '.$needle;
    }
}

if($failures){
    fwrite(STDERR,"Editorial SEO/draft hardening checks failed:\n - ".implode("\n - ",$failures)."\n");
    exit(1);
}

fwrite(STDOUT,"PASS editorial SEO fields, tag routing, sitemap coverage, and draft recovery wiring are present.\n");
