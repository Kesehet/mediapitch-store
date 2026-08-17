<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Core\SecretBox;
use PDO;

final class SettingsRepository
{
    public function site(): array
    {
        $stmt=Database::connection()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key LIKE 'site.%' AND encrypted=0");
        $values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $values[substr((string)$row['setting_key'],5)]=(string)($row['setting_value']??'');
        }
        return [
            'name'=>$values['name']??'MediaPitch Store',
            'tagline'=>$values['tagline']??'Independent buying guides, comparisons and product discovery.',
            'affiliate_disclosure'=>$values['affiliate_disclosure']??'As an Amazon Associate, MediaPitch may earn from qualifying purchases. Product availability and prices can change on Amazon.',
            'home_categories'=>($values['home_categories']??'1')==='1',
            'home_guides'=>($values['home_guides']??'1')==='1',
            'home_comparisons'=>($values['home_comparisons']??'1')==='1',
            'home_products'=>($values['home_products']??'1')==='1',
            'home_articles'=>($values['home_articles']??'1')==='1',
        ];
    }

    public function saveSite(array $data): void
    {
        $name=trim((string)($data['name']??''));
        if($name==='') $name='MediaPitch Store';
        $values=[
            'name'=>substr($name,0,150),
            'tagline'=>substr(trim((string)($data['tagline']??'')),0,500),
            'affiliate_disclosure'=>substr(trim((string)($data['affiliate_disclosure']??'')),0,1500),
            'home_categories'=>!empty($data['home_categories'])?'1':'0',
            'home_guides'=>!empty($data['home_guides'])?'1':'0',
            'home_comparisons'=>!empty($data['home_comparisons'])?'1':'0',
            'home_products'=>!empty($data['home_products'])?'1':'0',
            'home_articles'=>!empty($data['home_articles'])?'1':'0',
        ];
        foreach($values as $key=>$value)$this->put('site.'.$key,$value,false);
    }

    public function amazon(): array
    {
        $stmt=Database::connection()->query("SELECT setting_key,setting_value,encrypted FROM settings WHERE setting_key LIKE 'amazon.%'");
        $values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $key=substr((string)$row['setting_key'],7);
            $values[$key]=!empty($row['encrypted'])?SecretBox::decrypt((string)$row['setting_value']):(string)($row['setting_value']??'');
        }
        return [
            'enabled'=>($values['enabled']??'0')==='1',
            'marketplace'=>$values['marketplace']??'www.amazon.in',
            'partner_tag'=>$values['partner_tag']??'',
            'credential_id'=>$values['credential_id']??'',
            'credential_secret'=>$values['credential_secret']??'',
            'credential_version'=>$values['credential_version']??'3.2',
            'last_success'=>$values['last_success']??'',
            'last_error'=>$values['last_error']??'',
        ];
    }

    public function saveAmazon(array $data): void
    {
        $existing=$this->amazon();
        $secret=trim((string)($data['credential_secret']??''));
        if($secret==='')$secret=(string)$existing['credential_secret'];
        $id=trim((string)($data['credential_id']??''));
        if($id==='')$id=(string)$existing['credential_id'];

        $values=[
            'enabled'=>!empty($data['enabled'])?'1':'0',
            'marketplace'=>trim((string)($data['marketplace']??'www.amazon.in')),
            'partner_tag'=>trim((string)($data['partner_tag']??'')),
            'credential_id'=>$id,
            'credential_secret'=>$secret,
            'credential_version'=>trim((string)($data['credential_version']??'3.2')),
        ];
        foreach($values as $key=>$value){
            $encrypted=in_array($key,['credential_id','credential_secret'],true);
            $this->put('amazon.'.$key,$encrypted?SecretBox::encrypt($value):$value,$encrypted);
        }
    }

    public function setAmazonStatus(?string $success,?string $error): void
    {
        if($success!==null)$this->put('amazon.last_success',$success,false);
        $this->put('amazon.last_error',$error??'',false);
    }

    private function put(string $key,string $value,bool $encrypted): void
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO settings (setting_key,setting_value,encrypted) VALUES (:k,:v,:e)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),encrypted=VALUES(encrypted)'
        );
        $stmt->execute(['k'=>$key,'v'=>$value,'e'=>$encrypted?1:0]);
    }
}
