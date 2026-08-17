<?php

declare(strict_types=1);

use MediaPitch\Core\Database;

require dirname(__DIR__).'/src/bootstrap.php';

$db=Database::connection();
$requiredTables=[
    'users','categories','brands','products','specification_definitions','product_specifications',
    'content','content_products','affiliate_clicks','settings','redirects','media',
    'search_queries','admin_audit_log','password_resets','tags','content_tags','schema_migrations',
];
$requiredColumns=[
    'users'=>['last_login_at','failed_login_count','last_failed_login_at'],
    'media'=>['thumbnail_path','optimized'],
    'brands'=>['active'],
    'specification_definitions'=>['active'],
];
$failures=[];

foreach($requiredTables as $table){
    $stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute(['table'=>$table]);
    if((int)$stmt->fetchColumn()!==1)$failures[]='missing table `'.$table.'`';
}
foreach($requiredColumns as $table=>$columns){
    foreach($columns as $column){
        $stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
        $stmt->execute(['table'=>$table,'column'=>$column]);
        if((int)$stmt->fetchColumn()!==1)$failures[]='missing column `'.$table.'`.`'.$column.'`';
    }
}

if($failures){
    fwrite(STDERR,"Database schema verification failed:\n");
    foreach($failures as $failure)fwrite(STDERR,' - '.$failure."\n");
    exit(1);
}

fwrite(STDOUT,'Database schema verified: '.count($requiredTables).' required tables and critical columns are present.'."\n");
