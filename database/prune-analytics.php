<?php

declare(strict_types=1);

use MediaPitch\Core\Database;

require dirname(__DIR__).'/src/bootstrap.php';

$analyticsDays=max(30,(int)env('ANALYTICS_RETENTION_DAYS',395));
$auditDays=max(90,(int)env('AUDIT_RETENTION_DAYS',730));
$db=Database::connection();

$targets=[
    ['table'=>'affiliate_clicks','days'=>$analyticsDays],
    ['table'=>'search_queries','days'=>$analyticsDays],
    ['table'=>'admin_audit_log','days'=>$auditDays],
];

foreach($targets as $target){
    $table=$target['table'];$days=(int)$target['days'];
    $cutoff=gmdate('Y-m-d H:i:s',time()-($days*86400));
    $stmt=$db->prepare("DELETE FROM {$table} WHERE created_at < :cutoff");
    $stmt->execute(['cutoff'=>$cutoff]);
    echo sprintf("%s: removed %d row(s) older than %d days.\n",$table,$stmt->rowCount(),$days);
}
