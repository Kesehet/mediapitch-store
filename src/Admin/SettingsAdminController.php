<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Amazon\AmazonProductImporter;
use MediaPitch\Amazon\CreatorsApiClient;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Repositories\SettingsRepository;
use Throwable;

final class SettingsAdminController
{
    public function __construct(private readonly SettingsRepository $repo)
    {
    }

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/settings'))return false;
        if(!Auth::check()){$this->redirect('/admin/login');}
        if(!Auth::isAdministrator()){http_response_code(403);echo 'Administrator access required.';return true;}

        if($path==='/admin/settings/site'&&$method==='GET'){
            View::render('admin/settings-site',[
                'pageTitle'=>'Website Settings','adminUser'=>Auth::user(),'settings'=>$this->repo->site(),
                'success'=>$this->flash('success'),'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }
        if($path==='/admin/settings/site/save'&&$method==='POST'){
            $this->requireCsrf();
            try{$this->repo->saveSite($_POST);$this->setFlash('success','Website settings saved.');}
            catch(Throwable $e){$this->setFlash('error','Website settings could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/settings/site');
        }

        if($path==='/admin/settings/amazon'&&$method==='GET'){
            View::render('admin/settings-amazon',[
                'pageTitle'=>'Amazon Creators API','adminUser'=>Auth::user(),'settings'=>$this->repo->amazon(),
                'success'=>$this->flash('success'),'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }

        if($path==='/admin/settings/amazon/save'&&$method==='POST'){
            $this->requireCsrf();
            try{$this->repo->saveAmazon($_POST);$this->setFlash('success','Amazon settings saved securely.');}
            catch(Throwable $e){$this->setFlash('error','Settings could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/settings/amazon');
        }

        if($path==='/admin/settings/amazon/test'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $settings=$this->repo->amazon();
                $result=(new CreatorsApiClient())->testCredentials($settings);
                $stamp=gmdate('Y-m-d H:i:s');
                $this->repo->setAmazonStatus($stamp,null);
                $this->setFlash('success','Amazon authentication succeeded. Token lifetime reported: '.(int)$result['expires_in'].' seconds.');
            }catch(Throwable $e){
                $this->repo->setAmazonStatus(null,substr($e->getMessage(),0,1000));
                $this->setFlash('error','Amazon connection test failed: '.$e->getMessage());
            }
            $this->redirect('/admin/settings/amazon');
        }

        if($path==='/admin/settings/amazon/import'&&$method==='GET'){
            $raw=$_SESSION['_amazon_search_results']??[];
            $previews=[];$importer=new AmazonProductImporter();
            if(is_array($raw))foreach($raw as $item){if(is_array($item))$previews[]=$importer->normalizeForPreview($item);}
            View::render('admin/settings-amazon-import',[
                'pageTitle'=>'Import from Amazon','adminUser'=>Auth::user(),'settings'=>$this->repo->amazon(),
                'categories'=>(new AdminRepository())->categoryOptions(),'query'=>(string)($_SESSION['_amazon_search_query']??''),
                'results'=>$previews,'success'=>$this->flash('success'),'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }

        if($path==='/admin/settings/amazon/search'&&$method==='POST'){
            $this->requireCsrf();
            $query=trim((string)($_POST['q']??''));
            try{
                $settings=$this->repo->amazon();
                $items=(new CreatorsApiClient())->searchItems($settings,$query,10);
                $_SESSION['_amazon_search_results']=$items;
                $_SESSION['_amazon_search_query']=$query;
                $this->repo->setAmazonStatus(gmdate('Y-m-d H:i:s'),null);
                $this->setFlash('success',count($items).' Amazon result(s) loaded.');
            }catch(Throwable $e){
                unset($_SESSION['_amazon_search_results']);
                $_SESSION['_amazon_search_query']=$query;
                $this->repo->setAmazonStatus(null,substr($e->getMessage(),0,1000));
                $this->setFlash('error','Amazon search failed: '.$e->getMessage());
            }
            $this->redirect('/admin/settings/amazon/import');
        }

        if($path==='/admin/settings/amazon/import-item'&&$method==='POST'){
            $this->requireCsrf();
            $asin=strtoupper(trim((string)($_POST['asin']??'')));
            $categoryId=!empty($_POST['category_id'])?(int)$_POST['category_id']:null;
            try{
                $settings=$this->repo->amazon();
                $items=(new CreatorsApiClient())->getItems($settings,[$asin]);
                if(!$items)throw new \RuntimeException('Amazon did not return that ASIN.');
                $id=(new AmazonProductImporter())->import($items[0],$settings,$categoryId);
                $this->repo->setAmazonStatus(gmdate('Y-m-d H:i:s'),null);
                $this->setFlash('success','Amazon product imported/refreshed. Review the editorial fields before activating it.');
                $this->redirect('/admin/products/'.$id.'/edit');
            }catch(Throwable $e){
                $this->repo->setAmazonStatus(null,substr($e->getMessage(),0,1000));
                $this->setFlash('error','Amazon import failed: '.$e->getMessage());
                $this->redirect('/admin/settings/amazon/import');
            }
        }

        return false;
    }

    private function requireCsrf(): void
    {
        if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){
            http_response_code(419);exit('Invalid or expired form token.');
        }
    }
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$value=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($value)?$value:null;}
}
