<?php

declare(strict_types=1);

namespace MediaPitch\Core;

use Throwable;

final class Audit
{
    public static function record(string $action,string $entityType,?int $entityId=null,?string $summary=null,array $metadata=[]): void
    {
        try{
            $user=Auth::user();
            $stmt=Database::connection()->prepare(
                'INSERT INTO admin_audit_log (user_id,action,entity_type,entity_id,summary,metadata_json)
                 VALUES (:user_id,:action,:entity_type,:entity_id,:summary,:metadata_json)'
            );
            $stmt->execute([
                'user_id'=>!empty($user['id'])?(int)$user['id']:null,
                'action'=>substr(trim($action),0,100),
                'entity_type'=>substr(trim($entityType),0,100),
                'entity_id'=>$entityId,
                'summary'=>$summary!==null?substr($summary,0,500):null,
                'metadata_json'=>$metadata?json_encode(self::sanitize($metadata),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,
            ]);
        }catch(Throwable $e){
            if((bool)env('APP_DEBUG',false))error_log('Audit log write failed: '.$e->getMessage());
        }
    }

    private static function sanitize(array $metadata): array
    {
        $blocked=['password','password_hash','current_password','new_password','new_password_confirmation','credential_secret','secret','token','access_token'];
        foreach($metadata as $key=>$value){
            if(in_array(strtolower((string)$key),$blocked,true)){
                $metadata[$key]='[redacted]';
            }elseif(is_array($value)){
                $metadata[$key]=self::sanitize($value);
            }elseif(is_string($value) && strlen($value)>1000){
                $metadata[$key]=substr($value,0,1000).'…';
            }
        }
        return $metadata;
    }
}
