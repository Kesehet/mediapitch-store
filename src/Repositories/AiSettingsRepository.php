<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use MediaPitch\Core\SecretBox;
use PDO;

final class AiSettingsRepository
{
    public function get(): array
    {
        $stmt=Database::connection()->query("SELECT setting_key,setting_value,encrypted FROM settings WHERE setting_key LIKE 'ai.%'");
        $values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $key=substr((string)$row['setting_key'],3);
            $raw=(string)($row['setting_value']??'');
            $values[$key]=!empty($row['encrypted'])&&$raw!==''?SecretBox::decrypt($raw):$raw;
        }
        return [
            'enabled'=>($values['enabled']??'0')==='1',
            'ollama_url'=>$values['ollama_url']??'',
            'model'=>$values['model']??'qwen3:30b',
            'api_key'=>$values['api_key']??'',
            'api_key_configured'=>trim((string)($values['api_key']??''))!=='',
            'auto_discover'=>($values['auto_discover']??'1')==='1',
            'research_depth'=>in_array(($values['research_depth']??'thorough'),['quick','standard','thorough'],true)?$values['research_depth']:'thorough',
            'allow_blog'=>($values['allow_blog']??'1')==='1',
            'allow_guides'=>($values['allow_guides']??'1')==='1',
            'author_id'=>max(0,(int)($values['author_id']??0)),
            'notification_emails'=>$values['notification_emails']??'',
            'notification_from'=>$values['notification_from']??'',
        ];
    }

    public function save(array $data): void
    {
        $existing=$this->get();
        $url=rtrim(trim((string)($data['ollama_url']??'')),'/');
        if($url===''||!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('#^https?://#i',$url))throw new InvalidArgumentException('A remote Ollama HTTP(S) URL is required.');
        $host=strtolower((string)(parse_url($url,PHP_URL_HOST)?:''));
        if(in_array($host,['localhost','127.0.0.1','::1'],true))throw new InvalidArgumentException('Local Ollama endpoints are not supported. Configure the remote Ollama service URL.');
        $model=trim((string)($data['model']??''));if($model==='')throw new InvalidArgumentException('Ollama model is required.');
        $apiKey=trim((string)($data['api_key']??''));if($apiKey==='')$apiKey=(string)($existing['api_key']??'');
        if($apiKey==='')throw new InvalidArgumentException('Ollama API key is required.');
        $depth=(string)($data['research_depth']??'thorough');if(!in_array($depth,['quick','standard','thorough'],true))$depth='thorough';
        $emails=array_values(array_unique(array_filter(array_map('trim',preg_split('/[,;\n]+/',(string)($data['notification_emails']??''))?:[]))));
        foreach($emails as $email)if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid notification email: '.$email);
        $from=trim((string)($data['notification_from']??''));if($from!==''&&!filter_var($from,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Notification From email is invalid.');
        $values=[
            'enabled'=>!empty($data['enabled'])?'1':'0',
            'ollama_url'=>substr($url,0,500),
            'model'=>substr($model,0,150),
            'auto_discover'=>!empty($data['auto_discover'])?'1':'0',
            'research_depth'=>$depth,
            'allow_blog'=>!empty($data['allow_blog'])?'1':'0',
            'allow_guides'=>!empty($data['allow_guides'])?'1':'0',
            'author_id'=>(string)max(0,(int)($data['author_id']??0)),
            'notification_emails'=>implode("\n",$emails),
            'notification_from'=>$from,
        ];
        foreach($values as $key=>$value)$this->put('ai.'.$key,$value,false);
        $this->put('ai.api_key',SecretBox::encrypt($apiKey),true);
    }

    private function put(string $key,string $value,bool $encrypted): void
    {
        $stmt=Database::connection()->prepare('INSERT INTO settings (setting_key,setting_value,encrypted) VALUES (:k,:v,:e) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),encrypted=VALUES(encrypted)');
        $stmt->execute(['k'=>$key,'v'=>$value,'e'=>$encrypted?1:0]);
    }
}
