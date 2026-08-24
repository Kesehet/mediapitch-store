<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use PDO;

final class AiSettingsRepository
{
    public function get(): array
    {
        $stmt=Database::connection()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key LIKE 'ai.%' AND encrypted=0");
        $values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$values[substr((string)$row['setting_key'],3)]=(string)($row['setting_value']??'');
        return [
            'enabled'=>($values['enabled']??'0')==='1',
            'ollama_url'=>$values['ollama_url']??'http://127.0.0.1:11434',
            'model'=>$values['model']??'qwen3:30b',
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
        $url=rtrim(trim((string)($data['ollama_url']??'')),'/');
        if($url===''||!preg_match('#^https?://[^\s]+$#i',$url))throw new InvalidArgumentException('Ollama URL must be a valid http:// or https:// URL.');
        $model=trim((string)($data['model']??''));if($model==='')throw new InvalidArgumentException('Ollama model is required.');
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
        foreach($values as $key=>$value)$this->put('ai.'.$key,$value);
    }

    private function put(string $key,string $value): void
    {
        $stmt=Database::connection()->prepare('INSERT INTO settings (setting_key,setting_value,encrypted) VALUES (:k,:v,0) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),encrypted=0');$stmt->execute(['k'=>$key,'v'=>$value]);
    }
}
