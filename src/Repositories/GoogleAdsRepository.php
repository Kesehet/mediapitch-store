<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Core\SecretBox;
use PDO;

final class GoogleAdsRepository
{
    public function connection(): array
    {
        $stmt=Database::connection()->query("SELECT setting_key,setting_value,encrypted FROM settings WHERE setting_key LIKE 'google_ads.%'");
        $values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $key=substr((string)$row['setting_key'],11);
            $raw=(string)($row['setting_value']??'');
            $values[$key]=!empty($row['encrypted'])&&$raw!==''?SecretBox::decrypt($raw):$raw;
        }
        return [
            'connected'=>!empty($values['refresh_token']),
            'refresh_token'=>(string)($values['refresh_token']??''),
            'customer_id'=>(string)($values['customer_id']??''),
            'login_customer_id'=>(string)($values['login_customer_id']??''),
            'account_name'=>(string)($values['account_name']??''),
            'last_verified'=>(string)($values['last_verified']??''),
            'last_error'=>(string)($values['last_error']??''),
        ];
    }

    public function saveRefreshToken(string $refreshToken): void
    {
        $this->put('google_ads.refresh_token',SecretBox::encrypt($refreshToken),true);
        $this->put('google_ads.last_error','',false);
    }

    public function selectCustomer(string $customerId,string $name='',string $loginCustomerId=''): void
    {
        $customerId=preg_replace('/\D/','',$customerId)??'';
        $loginCustomerId=preg_replace('/\D/','',$loginCustomerId)??'';
        if($customerId==='')throw new \InvalidArgumentException('Choose a valid Google Ads customer.');
        $this->put('google_ads.customer_id',$customerId,false);
        $this->put('google_ads.login_customer_id',$loginCustomerId,false);
        $this->put('google_ads.account_name',substr(trim($name),0,255),false);
    }

    public function saveVerification(array $tracking): void
    {
        foreach([
            'google_tag_id'=>(string)($tracking['google_tag_id']??''),
            'google_ads_affiliate_label'=>(string)($tracking['affiliate_label']??''),
            'google_ads_product_view_label'=>(string)($tracking['product_view_label']??''),
            'google_ads_search_label'=>(string)($tracking['search_label']??''),
        ] as $key=>$value){if($value!=='')$this->put('site.'.$key,$value,false);}
        $this->put('google_ads.last_verified',gmdate('Y-m-d H:i:s'),false);
        $this->put('google_ads.last_error','',false);
    }

    public function setError(?string $message): void
    {
        $this->put('google_ads.last_error',substr((string)$message,0,1500),false);
    }

    public function disconnect(): void
    {
        foreach(['refresh_token','customer_id','login_customer_id','account_name','last_verified','last_error'] as $key)$this->delete('google_ads.'.$key);
    }

    private function put(string $key,string $value,bool $encrypted): void
    {
        $stmt=Database::connection()->prepare('INSERT INTO settings (setting_key,setting_value,encrypted) VALUES (:k,:v,:e) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),encrypted=VALUES(encrypted)');
        $stmt->execute(['k'=>$key,'v'=>$value,'e'=>$encrypted?1:0]);
    }

    private function delete(string $key): void
    {
        $stmt=Database::connection()->prepare('DELETE FROM settings WHERE setting_key=:k');
        $stmt->execute(['k'=>$key]);
    }
}
