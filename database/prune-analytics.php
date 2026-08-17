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
    $stmt=$db->prepare("DELETE FROM {$table} WHERE created_at < (UTC_TIMESTAMP() - INTERVAL :days DAY)");
    $stmt->bindValue(':days',$days,PDO::PARAM_INT);
    $stmt->execute();
    echo sprintf("%s: removed %d row(s) older than %d days.\n",$table,$stmt->rowCount(),$days);
}
