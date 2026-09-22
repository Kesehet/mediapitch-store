<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\RedirectRepository;
use MediaPitch\Services\RedirectHealthChecker;
use Throwable;

final class RedirectAdminController
{
    public function __construct(private readonly RedirectRepository $repo){}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/redirects'))return false;
        if(!Auth::check()){$this->redirect('/admin/login');}
        if(!Auth::isAdministrator()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/redirects'&&$method==='GET'){
            $editId=isset($_GET['edit'])?(int)$_GET['edit']:null;
            View::render('admin/redirects',[
                'pageTitle'=>'Redirects','adminUser'=>Auth::user(),'redirects'=>$this->repo->all(),'editRedirect'=>$this->repo->find($editId),
                'healthResults'=>$this->healthResults(),
                'success'=>$this->flash('success'),'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }
        if($path==='/admin/redirects/save'&&$method==='POST'){
            $this->requireCsrf();
            try{$this->repo->save($_POST,!empty($_POST['id'])?(int)$_POST['id']:null);$this->setFlash('success','Redirect saved.');}
            catch(Throwable $e){$this->setFlash('error','Redirect could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/redirects');
        }

        if($path==='/admin/redirects/check'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $redirects=array_values(array_filter($this->repo->all(),static fn(array $row):bool=>!empty($row['active'])));
                $results=(new RedirectHealthChecker())->checkMany($redirects);
                $disabled=0;$healthy=0;$warnings=0;
                foreach($results as $id=>$result){
                    if(($result['state']??'')==='broken'){
                        $this->repo->disable((int)$id);
                        $results[$id]['disabled']=true;
                        $disabled++;
                    }elseif(($result['state']??'')==='healthy')$healthy++;
                    else $warnings++;
                }
                $_SESSION['_redirect_health_results']=$results;
                Audit::record('redirect.health_check','redirect',null,'Checked active redirects',[
                    'checked'=>count($results),'healthy'=>$healthy,'disabled_broken'=>$disabled,'warnings'=>$warnings,
                ]);
                $this->setFlash('success','Redirect check complete: '.count($results).' checked, '.$healthy.' healthy, '.$disabled.' disabled for 404/410, '.$warnings.' warnings.');
            }catch(Throwable $e){
                $this->setFlash('error','Redirect check failed: '.$e->getMessage());
            }
            $this->redirect('/admin/redirects');
        }

        if($method==='POST'&&preg_match('#^/admin/redirects/(\d+)/check$#',$path,$m)){
            $this->requireCsrf();
            $id=(int)$m[1];
            try{
                $redirect=$this->repo->find($id);
                if(!$redirect)throw new \RuntimeException('Redirect not found.');
                $result=(new RedirectHealthChecker())->check($redirect);
                if(($result['state']??'')==='broken'){
                    $this->repo->disable($id);
                    $result['disabled']=true;
                    $this->setFlash('success','Target returned '.(string)($result['http_status']??'404/410').'; redirect disabled.');
                }elseif(($result['state']??'')==='healthy'){
                    $this->setFlash('success','Redirect target is healthy (HTTP '.(string)$result['http_status'].').');
                }else{
                    $message=$result['error']??('HTTP '.(string)($result['http_status']??'unknown'));
                    $this->setFlash('error','Redirect was not disabled because the check was inconclusive: '.$message);
                }
                $_SESSION['_redirect_health_results']=[$id=>$result];
                Audit::record('redirect.health_check','redirect',$id,'Checked redirect target',$result);
            }catch(Throwable $e){
                $this->setFlash('error','Redirect check failed: '.$e->getMessage());
            }
            $this->redirect('/admin/redirects');
        }

        if($method==='POST'&&preg_match('#^/admin/redirects/(\d+)/delete$#',$path,$m)){
            $this->requireCsrf();
            $id=(int)$m[1];
            try{
                $deleted=$this->repo->delete($id);
                if(!$deleted)throw new \RuntimeException('Redirect not found.');
                Audit::record('redirect.delete','redirect',$id,'Deleted redirect',[
                    'from_path'=>$deleted['from_path']??'','to_url'=>$deleted['to_url']??'','active'=>!empty($deleted['active']),
                ]);
                $this->setFlash('success','Redirect deleted.');
            }catch(Throwable $e){
                $this->setFlash('error','Redirect could not be deleted: '.$e->getMessage());
            }
            $this->redirect('/admin/redirects');
        }
        return false;
    }

    private function healthResults(): array
    {
        $results=$_SESSION['_redirect_health_results']??[];
        unset($_SESSION['_redirect_health_results']);
        return is_array($results)?$results:[];
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
