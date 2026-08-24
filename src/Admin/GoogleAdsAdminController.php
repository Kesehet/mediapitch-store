<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\GoogleAds\GoogleAdsClient;
use MediaPitch\Repositories\GoogleAdsRepository;
use Throwable;

final class GoogleAdsAdminController
{
    public function __construct(private readonly GoogleAdsRepository $repo,private readonly GoogleAdsClient $client){}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/settings/google-ads'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::isAdministrator()){http_response_code(403);echo 'Administrator access required.';return true;}

        if($path==='/admin/settings/google-ads'&&$method==='GET'){
            $connection=$this->repo->connection();$accounts=[];
            if($connection['connected']){
                try{$accounts=$this->client->listAccessibleCustomers((string)$connection['refresh_token']);$this->repo->setError(null);}
                catch(Throwable $e){$this->repo->setError($e->getMessage());$connection['last_error']=$e->getMessage();}
            }
            $report=$_SESSION['_google_ads_report']??null;unset($_SESSION['_google_ads_report']);
            View::render('admin/settings-google-ads',['pageTitle'=>'Google Ads','adminUser'=>Auth::user(),'configured'=>$this->client->configured(),'connection'=>$connection,'accounts'=>$accounts,'report'=>is_array($report)?$report:null,'success'=>$this->flash('success'),'error'=>$this->flash('error')],'admin/layout');return true;
        }

        if($path==='/admin/settings/google-ads/connect'&&$method==='GET'){
            try{$state=bin2hex(random_bytes(24));$_SESSION['_google_ads_oauth_state']=$state;header('Location: '.$this->client->authorizationUrl($state));exit;}
            catch(Throwable $e){$this->setFlash('error','Google Ads connection could not start: '.$e->getMessage());$this->redirect('/admin/settings/google-ads');}
        }

        if($path==='/admin/settings/google-ads/callback'&&$method==='GET'){
            $expected=(string)($_SESSION['_google_ads_oauth_state']??'');unset($_SESSION['_google_ads_oauth_state']);
            $state=(string)($_GET['state']??'');$code=(string)($_GET['code']??'');$oauthError=(string)($_GET['error']??'');
            if($oauthError!==''){$this->setFlash('error','Google authorization was not completed: '.$oauthError);$this->redirect('/admin/settings/google-ads');}
            if($expected===''||$state===''||!hash_equals($expected,$state)){$this->setFlash('error','Google OAuth state check failed. Please connect again.');$this->redirect('/admin/settings/google-ads');}
            try{
                $refresh=$this->client->exchangeCode($code);$this->repo->saveRefreshToken($refresh);$accounts=$this->client->listAccessibleCustomers($refresh);
                if(count($accounts)===1)$this->repo->selectCustomer((string)$accounts[0]['id'],(string)$accounts[0]['name'],(string)($accounts[0]['login_customer_id']??''));
                Audit::record('google_ads.connect','settings',null,'Connected Google Ads account',['accessible_accounts'=>count($accounts)]);
                $this->setFlash('success','Google Ads connected. Choose the account to manage, then run Verify & repair.');
            }catch(Throwable $e){$this->repo->setError($e->getMessage());$this->setFlash('error','Google Ads connection failed: '.$e->getMessage());}
            $this->redirect('/admin/settings/google-ads');
        }

        if($path==='/admin/settings/google-ads/select'&&$method==='POST'){
            $this->requireCsrf();$id=preg_replace('/\D/','',(string)($_POST['customer_id']??''))??'';
            try{
                $connection=$this->repo->connection();if(!$connection['connected'])throw new \RuntimeException('Connect Google Ads first.');
                $accounts=$this->client->listAccessibleCustomers((string)$connection['refresh_token']);$selected=null;foreach($accounts as $account)if((string)$account['id']===$id){$selected=$account;break;}
                if(!$selected)throw new \RuntimeException('That Google Ads customer is not accessible with the connected account.');
                $login=(string)($selected['login_customer_id']??'');$this->repo->selectCustomer($id,(string)$selected['name'],$login);
                Audit::record('google_ads.customer_select','settings',null,'Selected Google Ads customer',['customer_id'=>$id,'login_customer_id'=>$login!==''?$login:null,'manager'=>!empty($selected['manager'])]);$this->setFlash('success','Google Ads account selected.');
            }catch(Throwable $e){$this->setFlash('error','Account selection failed: '.$e->getMessage());}
            $this->redirect('/admin/settings/google-ads');
        }

        if($path==='/admin/settings/google-ads/verify'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $connection=$this->repo->connection();if(!$connection['connected'])throw new \RuntimeException('Connect Google Ads first.');if((string)$connection['customer_id']==='')throw new \RuntimeException('Choose a Google Ads customer first.');
                $result=$this->client->reconcile((string)$connection['refresh_token'],(string)$connection['customer_id'],(string)$connection['login_customer_id']);
                $this->repo->saveVerification($result['tracking']??[]);$_SESSION['_google_ads_report']=$result['report']??[];
                $created=0;$conflicts=0;foreach(($result['report']??[]) as $item){if(($item['status']??'')==='created')$created++;if(($item['status']??'')==='conflict')$conflicts++;}
                Audit::record('google_ads.verify_repair','settings',null,'Verified Google Ads conversion tracking',['customer_id'=>$connection['customer_id'],'created'=>$created,'conflicts'=>$conflicts]);
                $this->setFlash($conflicts?'error':'success',$conflicts?'Verification completed, but '.$conflicts.' naming conflict(s) need review. No conflicting action was modified.':'Google Ads verified. '.$created.' missing conversion action(s) created and tracking labels synchronized.');
            }catch(Throwable $e){$this->repo->setError($e->getMessage());Audit::record('google_ads.verify_failed','settings',null,'Google Ads verification failed',['message'=>substr($e->getMessage(),0,500)]);$this->setFlash('error','Google Ads verification failed: '.$e->getMessage());}
            $this->redirect('/admin/settings/google-ads');
        }

        if($path==='/admin/settings/google-ads/disconnect'&&$method==='POST'){
            $this->requireCsrf();$this->repo->disconnect();unset($_SESSION['_google_ads_access_token']);Audit::record('google_ads.disconnect','settings',null,'Disconnected Google Ads integration');$this->setFlash('success','Google Ads disconnected. Existing Google Ads conversions and site tracking values were left untouched.');$this->redirect('/admin/settings/google-ads');
        }
        return false;
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$value=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($value)?$value:null;}
}
