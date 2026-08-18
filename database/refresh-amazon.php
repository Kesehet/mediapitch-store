<?php

declare(strict_types=1);

use MediaPitch\Amazon\AmazonBulkRefresh;
use MediaPitch\Repositories\SettingsRepository;

require dirname(__DIR__).'/src/bootstrap.php';

try {
    $repository=new SettingsRepository();
    $profiles=$repository->amazonProfiles();
    $enabled=array_values(array_filter($profiles,static fn(array $profile):bool=>!empty($profile['enabled'])));

    if(!$enabled){
        fwrite(STDOUT,"Amazon Creators API integration is disabled for all marketplace profiles; nothing to refresh.\n");
        exit(0);
    }

    $configured=array_values(array_filter($enabled,static fn(array $profile):bool=>
        trim((string)($profile['credential_id']??''))!=='' &&
        trim((string)($profile['credential_secret']??''))!=='' &&
        trim((string)($profile['partner_tag']??''))!==''
    ));
    $allowLegacyUnscoped=count($configured)===1;

    $limitPerProfile=max(1,min(500,(int)($argv[1]??100)));
    $refresh=new AmazonBulkRefresh();
    $grand=['profiles'=>0,'selected'=>0,'refreshed'=>0,'missing'=>0,'errors'=>0];

    foreach($enabled as $settings){
        $marketplace=(string)($settings['marketplace']??'unknown');
        if(trim((string)($settings['credential_id']??''))==='' || trim((string)($settings['credential_secret']??''))==='' || trim((string)($settings['partner_tag']??''))===''){
            fwrite(STDERR,"{$marketplace}: profile is enabled but credentials/partner tag are incomplete; skipped.\n");
            $grand['errors']++;
            continue;
        }

        $settings['allow_legacy_unscoped']=$allowLegacyUnscoped;
        $remaining=$limitPerProfile;
        $totals=['selected'=>0,'refreshed'=>0,'missing'=>0,'errors'=>0];
        $grand['profiles']++;
        fwrite(STDOUT,"Refreshing {$marketplace}...\n");

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

        foreach(['selected','refreshed','missing','errors'] as $key)$grand[$key]+=$totals[$key];
        fwrite(STDOUT,sprintf(
            "%s complete: selected=%d refreshed=%d missing=%d errors=%d\n",
            $marketplace,$totals['selected'],$totals['refreshed'],$totals['missing'],$totals['errors']
        ));
    }

    if(count($configured)>1){
        fwrite(STDOUT,"Legacy Amazon products without api_marketplace were intentionally skipped because multiple marketplaces are enabled.\n");
    }
    fwrite(STDOUT,sprintf(
        "Amazon multi-market refresh complete: profiles=%d selected=%d refreshed=%d missing=%d errors=%d\n",
        $grand['profiles'],$grand['selected'],$grand['refreshed'],$grand['missing'],$grand['errors']
    ));
    exit($grand['errors']>0?2:0);
}catch(Throwable $e){
    fwrite(STDERR,'Amazon refresh failed: '.$e->getMessage()."\n");
    exit(1);
}
