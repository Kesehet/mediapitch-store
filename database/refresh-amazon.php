<?php

declare(strict_types=1);

use MediaPitch\Amazon\AmazonBulkRefresh;
use MediaPitch\Repositories\SettingsRepository;

require dirname(__DIR__).'/src/bootstrap.php';

try {
    $settings=(new SettingsRepository())->amazon();
    if(empty($settings['enabled'])){
        fwrite(STDOUT,"Amazon Creators API integration is disabled; nothing to refresh.\n");
        exit(0);
    }
    if(trim((string)$settings['credential_id'])==='' || trim((string)$settings['credential_secret'])==='' || trim((string)$settings['partner_tag'])===''){
        fwrite(STDERR,"Amazon Creators API settings are incomplete.\n");
        exit(1);
    }

    $limit=max(1,min(500,(int)($argv[1]??100)));
    $remaining=$limit;
    $totals=['selected'=>0,'refreshed'=>0,'missing'=>0,'errors'=>0];
    $refresh=new AmazonBulkRefresh();

    while($remaining>0){
        $batch=min(100,$remaining);
        $result=$refresh->refresh($settings,$batch,true);
        $selected=(int)($result['selected']??0);
        $totals['selected']+=$selected;
        $totals['refreshed']+=(int)($result['refreshed']??0);
        $totals['missing']+=count($result['missing']??[]);
        $totals['errors']+=count($result['errors']??[]);
        if($selected===0)break;
        $remaining-=$selected;
        if($selected<$batch)break;
    }

    fwrite(STDOUT,sprintf(
        "Amazon refresh complete: selected=%d refreshed=%d missing=%d errors=%d\n",
        $totals['selected'],$totals['refreshed'],$totals['missing'],$totals['errors']
    ));
    exit($totals['errors']>0?2:0);
}catch(Throwable $e){
    fwrite(STDERR,'Amazon refresh failed: '.$e->getMessage()."\n");
    exit(1);
}
