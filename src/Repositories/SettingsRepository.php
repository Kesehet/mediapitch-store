<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use MediaPitch\Core\SecretBox;
use PDO;

final class SettingsRepository
{
    private const DEFAULT_GOOGLE_TAG_ID = 'AW-16657488326';

    public function site(): array
    {
        $stmt=Database::connection()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key LIKE 'site.%' AND encrypted=0");
        $values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$values[substr((string)$row['setting_key'],5)]=(string)($row['setting_value']??'');}
        return [
            'name'=>$values['name']??'MediaPitch Store','tagline'=>$values['tagline']??'Independent buying guides, comparisons and product discovery.',
            'affiliate_disclosure'=>$values['affiliate_disclosure']??'As an Amazon Associate, MediaPitch may earn from qualifying purchases. Product availability and prices can change on Amazon.',
            'google_tag_id'=>$values['google_tag_id']??self::DEFAULT_GOOGLE_TAG_ID,
            'home_categories'=>($values['home_categories']??'1')==='1','home_guides'=>($values['home_guides']??'1')==='1','home_comparisons'=>($values['home_comparisons']??'1')==='1','home_products'=>($values['home_products']??'1')==='1','home_articles'=>($values['home_articles']??'1')==='1',
        ];
    }

    public function saveSite(array $data): void
    {
        $name=trim((string)($data['name']??''));if($name==='')$name='MediaPitch Store';
        $googleTagId=strtoupper(trim((string)($data['google_tag_id']??'')));
        if($googleTagId!==''&&!preg_match('/^(?:AW-\d+|G-[A-Z0-9]+|GT-[A-Z0-9]+|DC-\d+)$/',$googleTagId)){
            throw new InvalidArgumentException('Google tag ID is invalid. Use an ID such as AW-123456789, G-XXXXXXXXXX or GT-XXXXXXXXXX.');
        }
        $values=['name'=>substr($name,0,150),'tagline'=>substr(trim((string)($data['tagline']??'')),0,500),'affiliate_disclosure'=>substr(trim((string)($data['affiliate_disclosure']??'')),0,1500),'google_tag_id'=>$googleTagId,'home_categories'=>!empty($data['home_categories'])?'1':'0','home_guides'=>!empty($data['home_guides'])?'1':'0','home_comparisons'=>!empty($data['home_comparisons'])?'1':'0','home_products'=>!empty($data['home_products'])?'1':'0','home_articles'=>!empty($data['home_articles'])?'1':'0'];
        foreach($values as $key=>$value)$this->put('site.'.$key,$value,false);
    }

    public function amazon(?string $marketplace=null): array
    {
        $profiles=$this->amazonProfilesMap();
        $marketplace=$marketplace!==null&&trim($marketplace)!==''?$this->normalizeMarketplace($marketplace):$this->resolveActiveAmazonMarketplace($profiles);
        return $profiles[$marketplace]??$this->emptyAmazonProfile($marketplace?:'www.amazon.in');
    }

    /** @return array<int,array<string,mixed>> */
    public function amazonProfiles(): array
    {
        $map=$this->amazonProfilesMap();$profiles=array_values($map);$active=$this->resolveActiveAmazonMarketplace($map);
        usort($profiles,static function(array $a,array $b)use($active):int{if(($a['marketplace']??'')===$active)return -1;if(($b['marketplace']??'')===$active)return 1;return strcmp((string)($a['marketplace']??''),(string)($b['marketplace']??''));});
        foreach($profiles as &$profile){$profile['active_profile']=($profile['marketplace']??'')===$active;$profile['credentials_configured']=trim((string)($profile['credential_id']??''))!==''&&trim((string)($profile['credential_secret']??''))!=='';}unset($profile);
        return $profiles;
    }

    public function activeAmazonMarketplace(): string{return $this->resolveActiveAmazonMarketplace($this->amazonProfilesMap());}

    public function saveAmazon(array $data): void
    {
        $marketplace=$this->normalizeMarketplace((string)($data['marketplace']??''));if($marketplace==='')throw new InvalidArgumentException('Amazon marketplace is required.');
        $profiles=$this->amazonProfilesMap();$existing=$profiles[$marketplace]??$this->emptyAmazonProfile($marketplace);
        $secret=trim((string)($data['credential_secret']??''));if($secret==='')$secret=(string)($existing['credential_secret']??'');
        $id=trim((string)($data['credential_id']??''));if($id==='')$id=(string)($existing['credential_id']??'');
        $version=trim((string)($data['credential_version']??($existing['credential_version']??'3.2')));if(!in_array($version,['2.1','2.2','2.3','3.1','3.2','3.3'],true))throw new InvalidArgumentException('Unsupported Creators API credential version.');
        $profiles[$marketplace]=['enabled'=>!empty($data['enabled']),'marketplace'=>$marketplace,'partner_tag'=>substr(trim((string)($data['partner_tag']??'')),0,255),'credential_id'=>$id,'credential_secret'=>$secret,'credential_version'=>$version,'last_success'=>(string)($existing['last_success']??''),'last_error'=>(string)($existing['last_error']??'')];
        $this->saveAmazonProfilesMap($profiles);$this->put('amazon.active_marketplace',$marketplace,false);$this->mirrorLegacyAmazon($profiles[$marketplace]);
    }

    public function setActiveAmazonMarketplace(string $marketplace): void
    {
        $marketplace=$this->normalizeMarketplace($marketplace);$profiles=$this->amazonProfilesMap();
        if($marketplace===''||!isset($profiles[$marketplace]))throw new InvalidArgumentException('Amazon marketplace profile not found.');
        $this->put('amazon.active_marketplace',$marketplace,false);$this->mirrorLegacyAmazon($profiles[$marketplace]);
    }

    public function deleteAmazonProfile(string $marketplace): void
    {
        $marketplace=$this->normalizeMarketplace($marketplace);$profiles=$this->amazonProfilesMap();if(!isset($profiles[$marketplace]))return;
        if(count($profiles)<=1)throw new InvalidArgumentException('Keep at least one Amazon marketplace profile. Disable it instead if necessary.');
        $wasActive=$marketplace===$this->resolveActiveAmazonMarketplace($profiles);unset($profiles[$marketplace]);$this->saveAmazonProfilesMap($profiles);
        $active=$wasActive?(string)array_key_first($profiles):$this->resolveActiveAmazonMarketplace($profiles);$this->put('amazon.active_marketplace',$active,false);$this->mirrorLegacyAmazon($profiles[$active]);
    }

    public function setAmazonStatus(?string $success,?string $error,?string $marketplace=null): void
    {
        $profiles=$this->amazonProfilesMap();$marketplace=$marketplace!==null&&trim($marketplace)!==''?$this->normalizeMarketplace($marketplace):$this->resolveActiveAmazonMarketplace($profiles);
        if($marketplace!==''&&isset($profiles[$marketplace])){if($success!==null)$profiles[$marketplace]['last_success']=$success;$profiles[$marketplace]['last_error']=$error??'';$this->saveAmazonProfilesMap($profiles);if($marketplace===$this->resolveActiveAmazonMarketplace($profiles))$this->mirrorLegacyAmazon($profiles[$marketplace]);return;}
        if($success!==null)$this->put('amazon.last_success',$success,false);$this->put('amazon.last_error',$error??'',false);
    }

    /** @return array<string,array<string,mixed>> */
    private function amazonProfilesMap(): array
    {
        $values=$this->amazonValues();$profiles=[];
        if(!empty($values['profiles'])){$decoded=json_decode((string)$values['profiles'],true);if(is_array($decoded))foreach($decoded as $key=>$profile){if(!is_array($profile))continue;$marketplace=$this->normalizeMarketplace((string)($profile['marketplace']??$key));if($marketplace!=='')$profiles[$marketplace]=$this->normalizeProfile($profile,$marketplace);}}
        $legacyMarketplace=$this->normalizeMarketplace((string)($values['marketplace']??''));
        if($legacyMarketplace!==''&&!isset($profiles[$legacyMarketplace])){$hasLegacy=!empty($values['partner_tag'])||!empty($values['credential_id'])||!empty($values['credential_secret'])||isset($values['enabled']);if($hasLegacy)$profiles[$legacyMarketplace]=$this->normalizeProfile(['enabled'=>($values['enabled']??'0')==='1','marketplace'=>$legacyMarketplace,'partner_tag'=>$values['partner_tag']??'','credential_id'=>$values['credential_id']??'','credential_secret'=>$values['credential_secret']??'','credential_version'=>$values['credential_version']??'3.2','last_success'=>$values['last_success']??'','last_error'=>$values['last_error']??''],$legacyMarketplace);}
        if(!$profiles)$profiles['www.amazon.in']=$this->emptyAmazonProfile('www.amazon.in');return $profiles;
    }

    /** @return array<string,string> */
    private function amazonValues(): array
    {
        $stmt=Database::connection()->query("SELECT setting_key,setting_value,encrypted FROM settings WHERE setting_key LIKE 'amazon.%'");$values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$key=substr((string)$row['setting_key'],7);$raw=(string)($row['setting_value']??'');$values[$key]=!empty($row['encrypted'])&&$raw!==''?SecretBox::decrypt($raw):$raw;}return $values;
    }

    /** @param array<string,array<string,mixed>> $profiles */
    private function saveAmazonProfilesMap(array $profiles): void{$json=json_encode($profiles,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$this->put('amazon.profiles',SecretBox::encrypt($json),true);}

    /** @param array<string,mixed> $profile */
    private function mirrorLegacyAmazon(array $profile): void
    {
        foreach(['enabled'=>!empty($profile['enabled'])?'1':'0','marketplace'=>(string)$profile['marketplace'],'partner_tag'=>(string)($profile['partner_tag']??''),'credential_version'=>(string)($profile['credential_version']??'3.2'),'last_success'=>(string)($profile['last_success']??''),'last_error'=>(string)($profile['last_error']??'')] as $key=>$value)$this->put('amazon.'.$key,$value,false);
        $this->put('amazon.credential_id',SecretBox::encrypt((string)($profile['credential_id']??'')),true);$this->put('amazon.credential_secret',SecretBox::encrypt((string)($profile['credential_secret']??'')),true);
    }

    /** @param array<string,mixed> $profile @return array<string,mixed> */
    private function normalizeProfile(array $profile,string $marketplace): array
    {
        $version=(string)($profile['credential_version']??'3.2');
        return ['enabled'=>filter_var($profile['enabled']??false,FILTER_VALIDATE_BOOL),'marketplace'=>$marketplace,'partner_tag'=>(string)($profile['partner_tag']??''),'credential_id'=>(string)($profile['credential_id']??''),'credential_secret'=>(string)($profile['credential_secret']??''),'credential_version'=>in_array($version,['2.1','2.2','2.3','3.1','3.2','3.3'],true)?$version:'3.2','last_success'=>(string)($profile['last_success']??''),'last_error'=>(string)($profile['last_error']??'')];
    }

    /** @return array<string,mixed> */
    private function emptyAmazonProfile(string $marketplace): array{return ['enabled'=>false,'marketplace'=>$marketplace,'partner_tag'=>'','credential_id'=>'','credential_secret'=>'','credential_version'=>'3.2','last_success'=>'','last_error'=>''];}

    /** @param array<string,array<string,mixed>> $profiles */
    private function resolveActiveAmazonMarketplace(array $profiles): string
    {
        $values=$this->amazonValues();$active=$this->normalizeMarketplace((string)($values['active_marketplace']??$values['marketplace']??''));if($active!==''&&isset($profiles[$active]))return $active;return (string)(array_key_first($profiles)??'www.amazon.in');
    }

    private function normalizeMarketplace(string $marketplace): string
    {
        $marketplace=strtolower(trim($marketplace));if($marketplace==='')return '';
        if(str_contains($marketplace,'://'))$marketplace=(string)(parse_url($marketplace,PHP_URL_HOST)?:'');$marketplace=trim(explode('/',$marketplace,2)[0]);$marketplace=preg_replace('/:\d+$/','',$marketplace)??'';
        if($marketplace===''||strlen($marketplace)>100||!preg_match('/^[a-z0-9.-]+$/',$marketplace))throw new InvalidArgumentException('Amazon marketplace must be a valid hostname, for example www.amazon.in.');return $marketplace;
    }

    private function put(string $key,string $value,bool $encrypted): void
    {
        $stmt=Database::connection()->prepare('INSERT INTO settings (setting_key,setting_value,encrypted) VALUES (:k,:v,:e) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),encrypted=VALUES(encrypted)');$stmt->execute(['k'=>$key,'v'=>$value,'e'=>$encrypted?1:0]);
    }
}
