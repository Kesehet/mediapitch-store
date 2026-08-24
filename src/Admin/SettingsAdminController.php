<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Amazon\AmazonBulkRefresh;
use MediaPitch\Amazon\AmazonProductImporter;
use MediaPitch\Amazon\CreatorsApiClient;
use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Repositories\SettingsRepository;
use Throwable;

final class SettingsAdminController
{
    public function __construct(private readonly SettingsRepository $repo){}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/settings'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::isAdministrator()){http_response_code(403);echo 'Administrator access required.';return true;}

        if($path==='/admin/settings/site'&&$method==='GET'){
            View::render('admin/settings-site',['pageTitle'=>'Website Settings','adminUser'=>Auth::user(),'settings'=>$this->repo->site(),'success'=>$this->flash('success'),'error'=>$this->flash('error')],'admin/layout');return true;
        }
        if($path==='/admin/settings/site/save'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $this->repo->saveSite($_POST);
                $conversionLabels=0;
                foreach(['google_ads_affiliate_label','google_ads_product_view_label','google_ads_search_label'] as $key)if(trim((string)($_POST[$key]??''))!=='')$conversionLabels++;
                Audit::record('settings.site.update','settings',null,'Updated website settings',[
                    'site_name'=>(string)($_POST['name']??''),
                    'tagline'=>(string)($_POST['tagline']??''),
                    'google_tag_configured'=>trim((string)($_POST['google_tag_id']??''))!=='',
                    'google_ads_conversion_labels'=>$conversionLabels,
                    'affiliate_disclosure_configured'=>trim((string)($_POST['affiliate_disclosure']??''))!=='',
                    'home_categories'=>!empty($_POST['home_categories']),
                    'home_products'=>!empty($_POST['home_products']),
                    'home_guides'=>!empty($_POST['home_guides']),
                    'home_comparisons'=>!empty($_POST['home_comparisons']),
                    'home_articles'=>!empty($_POST['home_articles']),
                ]);
                $this->setFlash('success','Website settings saved.');
            }
            catch(Throwable $e){$this->setFlash('error','Website settings could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/settings/site');
        }

        if($path==='/admin/settings/amazon'&&$method==='GET'){
            $profiles=$this->repo->amazonProfiles();
            $isNew=!empty($_GET['new']);$requested=trim((string)($_GET['marketplace']??''));
            $settings=$isNew
                ? ['enabled'=>true,'marketplace'=>'','partner_tag'=>'','credential_id'=>'','credential_secret'=>'','credential_version'=>'3.2','last_success'=>'','last_error'=>'']
                : ($requested!==''?$this->repo->amazon($requested):$this->repo->amazon());
            View::render('admin/settings-amazon',['pageTitle'=>'Amazon Creators API','adminUser'=>Auth::user(),'settings'=>$settings,'profiles'=>$profiles,'activeMarketplace'=>$this->repo->activeAmazonMarketplace(),'isNewProfile'=>$isNew,'success'=>$this->flash('success'),'error'=>$this->flash('error')],'admin/layout');return true;
        }

        if($path==='/admin/settings/amazon/save'&&$method==='POST'){
            $this->requireCsrf();
            try{$this->repo->saveAmazon($_POST);$marketplace=$this->repo->activeAmazonMarketplace();Audit::record('settings.amazon.profile_save','settings',null,'Saved Amazon marketplace profile',['enabled'=>!empty($_POST['enabled']),'marketplace'=>$marketplace,'partner_tag'=>(string)($_POST['partner_tag']??''),'credential_version'=>(string)($_POST['credential_version']??''),'credential_secret'=>'[redacted]','credential_id'=>!empty($_POST['credential_id'])?'[configured]':'[unchanged]']);$this->setFlash('success','Amazon marketplace profile saved securely and made active.');}
            catch(Throwable $e){$this->setFlash('error','Settings could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/settings/amazon');
        }

        if($path==='/admin/settings/amazon/activate'&&$method==='POST'){
            $this->requireCsrf();$marketplace=(string)($_POST['marketplace']??'');
            try{$this->repo->setActiveAmazonMarketplace($marketplace);Audit::record('settings.amazon.profile_activate','settings',null,'Activated Amazon marketplace profile',['marketplace'=>$this->repo->activeAmazonMarketplace()]);$this->setFlash('success','Active Amazon marketplace changed.');}
            catch(Throwable $e){$this->setFlash('error','Marketplace could not be activated: '.$e->getMessage());}
            $this->redirect('/admin/settings/amazon');
        }

        if($path==='/admin/settings/amazon/delete-profile'&&$method==='POST'){
            $this->requireCsrf();$marketplace=(string)($_POST['marketplace']??'');
            try{$this->repo->deleteAmazonProfile($marketplace);Audit::record('settings.amazon.profile_delete','settings',null,'Deleted Amazon marketplace profile',['marketplace'=>$marketplace]);$this->setFlash('success','Amazon marketplace profile deleted.');}
            catch(Throwable $e){$this->setFlash('error','Marketplace profile could not be deleted: '.$e->getMessage());}
            $this->redirect('/admin/settings/amazon');
        }

        if($path==='/admin/settings/amazon/test'&&$method==='POST'){
            $this->requireCsrf();$settings=$this->repo->amazon();
            try{$result=(new CreatorsApiClient())->testCredentials($settings);$stamp=gmdate('Y-m-d H:i:s');$this->repo->setAmazonStatus($stamp,null,(string)$settings['marketplace']);Audit::record('amazon.connection.test','amazon',null,'Amazon authentication test succeeded',['marketplace'=>$settings['marketplace'],'expires_in'=>(int)$result['expires_in']]);$this->setFlash('success','Amazon authentication succeeded for '.$settings['marketplace'].'. Token lifetime: '.(int)$result['expires_in'].' seconds.');}
            catch(Throwable $e){$this->repo->setAmazonStatus(null,substr($e->getMessage(),0,1000),(string)($settings['marketplace']??''));Audit::record('amazon.connection.test_failed','amazon',null,'Amazon authentication test failed',['marketplace'=>$settings['marketplace']??'']);$this->setFlash('error','Amazon connection test failed: '.$e->getMessage());}
            $this->redirect('/admin/settings/amazon');
        }

        if($path==='/admin/settings/amazon/refresh'&&$method==='POST'){
            $this->requireCsrf();$settings=$this->repo->amazon();
            try{$result=(new AmazonBulkRefresh())->refresh($settings,50,true);$this->repo->setAmazonStatus(gmdate('Y-m-d H:i:s'),empty($result['errors'])?null:'Some products failed to refresh.',(string)$settings['marketplace']);Audit::record('amazon.products.bulk_refresh','amazon',null,'Bulk-refreshed stale Amazon products',['marketplace'=>$settings['marketplace'],'selected'=>(int)$result['selected'],'refreshed'=>(int)$result['refreshed'],'missing_count'=>count($result['missing']),'error_count'=>count($result['errors'])]);$message='Amazon refresh for '.$settings['marketplace'].' complete: '.(int)$result['refreshed'].' refreshed from '.(int)$result['selected'].' stale product(s).';if($result['missing'])$message.=' '.count($result['missing']).' ASIN(s) were not returned.';if($result['errors'])$message.=' '.count($result['errors']).' failed after retries.';$this->setFlash($result['errors']?'error':'success',$message);}
            catch(Throwable $e){$this->repo->setAmazonStatus(null,substr($e->getMessage(),0,1000),(string)($settings['marketplace']??''));Audit::record('amazon.products.bulk_refresh_failed','amazon',null,'Bulk Amazon refresh failed',['marketplace'=>$settings['marketplace']??'']);$this->setFlash('error','Amazon bulk refresh failed: '.$e->getMessage());}
            $this->redirect('/admin/settings/amazon');
        }

        if($path==='/admin/settings/amazon/import'&&$method==='GET'){
            $raw=$_SESSION['_amazon_search_results']??[];$previews=[];$importer=new AmazonProductImporter();if(is_array($raw))foreach($raw as $item)if(is_array($item))$previews[]=$importer->normalizeForPreview($item);
            View::render('admin/settings-amazon-import',['pageTitle'=>'Import from Amazon','adminUser'=>Auth::user(),'settings'=>$this->repo->amazon(),'categories'=>(new AdminRepository())->categoryOptions(),'query'=>(string)($_SESSION['_amazon_search_query']??''),'results'=>$previews,'success'=>$this->flash('success'),'error'=>$this->flash('error')],'admin/layout');return true;
        }

        if($path==='/admin/settings/amazon/search'&&$method==='POST'){
            $this->requireCsrf();$query=trim((string)($_POST['q']??''));$settings=$this->repo->amazon();
            try{$items=(new CreatorsApiClient())->searchItems($settings,$query,10);$_SESSION['_amazon_search_results']=$items;$_SESSION['_amazon_search_query']=$query;$this->repo->setAmazonStatus(gmdate('Y-m-d H:i:s'),null,(string)$settings['marketplace']);$this->setFlash('success',count($items).' Amazon result(s) loaded from '.$settings['marketplace'].'.');}
            catch(Throwable $e){unset($_SESSION['_amazon_search_results']);$_SESSION['_amazon_search_query']=$query;$this->repo->setAmazonStatus(null,substr($e->getMessage(),0,1000),(string)($settings['marketplace']??''));$this->setFlash('error','Amazon search failed: '.$e->getMessage());}
            $this->redirect('/admin/settings/amazon/import');
        }

        if($path==='/admin/settings/amazon/import-item'&&$method==='POST'){
            $this->requireCsrf();$asin=strtoupper(trim((string)($_POST['asin']??'')));$categoryId=!empty($_POST['category_id'])?(int)$_POST['category_id']:null;$settings=$this->repo->amazon();
            try{$items=(new CreatorsApiClient())->getItems($settings,[$asin]);if(!$items)throw new \RuntimeException('Amazon did not return that ASIN.');$id=(new AmazonProductImporter())->import($items[0],$settings,$categoryId);$this->repo->setAmazonStatus(gmdate('Y-m-d H:i:s'),null,(string)$settings['marketplace']);Audit::record('amazon.product.import','product',$id,'Imported/refreshed Amazon product',['asin'=>$asin,'category_id'=>$categoryId,'marketplace'=>$settings['marketplace']]);$this->setFlash('success','Amazon product imported/refreshed from '.$settings['marketplace'].'. Review editorial fields before activating it.');$this->redirect('/admin/products/'.$id.'/edit');}
            catch(Throwable $e){$this->repo->setAmazonStatus(null,substr($e->getMessage(),0,1000),(string)($settings['marketplace']??''));Audit::record('amazon.product.import_failed','product',null,'Amazon product import failed',['asin'=>$asin,'category_id'=>$categoryId,'marketplace'=>$settings['marketplace']??'']);$this->setFlash('error','Amazon import failed: '.$e->getMessage());$this->redirect('/admin/settings/amazon/import');}
        }
        return false;
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$value=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($value)?$value:null;}
}
