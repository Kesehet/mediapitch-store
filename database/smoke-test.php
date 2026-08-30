<?php

declare(strict_types=1);

use MediaPitch\Core\Database;
use MediaPitch\Repositories\AiSettingsRepository;
use MediaPitch\Repositories\SettingsRepository;

require dirname(__DIR__).'/src/bootstrap.php';

$checks=[];
$failures=0;
$warns=0;
$check=static function(string $label,bool $ok,string $detail='',bool $warningOnly=false)use(&$checks,&$failures,&$warns):void{
    $status=$ok?'PASS':($warningOnly?'WARN':'FAIL');
    if(!$ok){if($warningOnly)$warns++;else$failures++;}
    $checks[]=[$status,$label,$detail];
};

$check('PHP >= 8.2',version_compare(PHP_VERSION,'8.2.0','>='),PHP_VERSION);
$check('cURL extension',extension_loaded('curl'),'Required by Amazon Creators API and preferred for AI web research.');
$check('PDO extension',extension_loaded('pdo'));
$check('PDO MySQL extension',extension_loaded('pdo_mysql'));
$check('OpenSSL extension',extension_loaded('openssl'));
$check('Fileinfo extension',extension_loaded('fileinfo'));
$check('GD extension',extension_loaded('gd'),'Image optimization/thumbnails will gracefully fall back without GD.',true);

$appEnv=(string)env('APP_ENV','');
$appUrl=(string)env('APP_URL','');
$appKey=(string)env('APP_KEY','');
$check('APP_ENV set',$appEnv!=='',$appEnv ?: 'missing');
$check('APP_DEBUG disabled in production',$appEnv!=='production'||!(bool)env('APP_DEBUG',false),(bool)env('APP_DEBUG',false)?'APP_DEBUG=true':'APP_DEBUG=false');
$check('APP_URL configured',$appUrl!=='', $appUrl ?: 'missing');
$check('APP_KEY length',strlen($appKey)>=32,'length='.strlen($appKey));
$check('Secure session cookie in production',$appEnv!=='production'||(bool)env('SESSION_SECURE_COOKIE',true),(bool)env('SESSION_SECURE_COOKIE',true)?'enabled':'disabled');

try{
    $db=Database::connection();
    $db->query('SELECT 1');
    $check('Database connection',true);

    $requiredTables=[
        'users','categories','brands','products','content','content_products','product_specifications',
        'specification_definitions','settings','affiliate_clicks','redirects','media','search_queries',
        'admin_audit_log','password_reset_tokens','tags','content_tags','schema_migrations','ai_jobs','ai_research_sources',
    ];
    foreach($requiredTables as $table){
        try{$db->query('SELECT 1 FROM `'.$table.'` LIMIT 1');$check('Table '.$table,true);}catch(Throwable $e){$check('Table '.$table,false,substr($e->getMessage(),0,160));}
    }

    $requiredColumns=[
        'users'=>['last_login_at','failed_login_count','last_failed_login_at'],
        'media'=>['thumbnail_path','optimized'],
        'brands'=>['active'],
        'specification_definitions'=>['active'],
        'products'=>['asin','api_marketplace','last_synced_at','affiliate_url','source'],
        'ai_jobs'=>['trigger_mode','stage','content_id','model','started_at','completed_at'],
    ];
    foreach($requiredColumns as $table=>$columns){
        foreach($columns as $column){
            try{
                $stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
                $stmt->execute(['table'=>$table,'column'=>$column]);
                $check('Column '.$table.'.'.$column,(int)$stmt->fetchColumn()===1);
            }catch(Throwable $e){$check('Column '.$table.'.'.$column,false,substr($e->getMessage(),0,160));}
        }
    }

    try{$migrationCount=(int)$db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();$check('Migration tracking',true,$migrationCount.' migration(s) recorded');}catch(Throwable $e){$check('Migration tracking',false,substr($e->getMessage(),0,160));}
    try{$userCount=(int)$db->query('SELECT COUNT(*) FROM users WHERE active=1')->fetchColumn();$check('Active admin/user account exists',$userCount>0,$userCount.' active user(s)');}catch(Throwable $e){$check('Active admin/user account exists',false,substr($e->getMessage(),0,160));}

    try{
        $ai=(new AiSettingsRepository())->get();
        $aiConfigured=trim((string)($ai['ollama_url']??''))!==''&&trim((string)($ai['model']??''))!==''&&!empty($ai['api_key_configured']);
        $check('AI remote configuration present',$aiConfigured,$aiConfigured?'Ollama URL, model and API key configured.':'Configure Ollama URL, model and API key in Admin > AI Content Settings.',true);
    }catch(Throwable $e){$check('AI settings readable',false,substr($e->getMessage(),0,160));}

    try{
        $amazon=(new SettingsRepository())->amazon();
        $amazonConfigured=!empty($amazon['enabled'])&&trim((string)($amazon['marketplace']??''))!==''&&trim((string)($amazon['partner_tag']??''))!==''&&trim((string)($amazon['credential_id']??''))!==''&&trim((string)($amazon['credential_secret']??''))!=='';
        $check('Amazon Creators API profile configured',$amazonConfigured,$amazonConfigured?'Active marketplace profile has credentials and Partner Tag.':'Expected until Creators API credentials are added in Admin > Amazon Creators API.',true);
    }catch(Throwable $e){$check('Amazon settings readable',false,substr($e->getMessage(),0,160));}
}catch(Throwable $e){
    $check('Database connection',false,substr($e->getMessage(),0,200));
}

$uploadRoot=dirname(__DIR__).'/public/uploads';
if(!is_dir($uploadRoot))@mkdir($uploadRoot,0755,true);
$check('Upload directory writable',is_dir($uploadRoot)&&is_writable($uploadRoot),$uploadRoot);

foreach($checks as [$status,$label,$detail]){
    $suffix=$detail!==''?' - '.$detail:'';
    fwrite(STDOUT,sprintf("%-4s %s%s\n",$status,$label,$suffix));
}
fwrite(STDOUT,"\nSummary: ".count($checks)." checks, {$failures} failure(s), {$warns} warning(s).\n");
exit($failures>0?1:0);
