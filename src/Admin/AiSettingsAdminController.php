<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Ai\AiJobRepository;
use MediaPitch\Ai\OllamaClient;
use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\Database;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AiSettingsRepository;
use PDO;
use Throwable;

final class AiSettingsAdminController
{
    public function __construct(private readonly AiSettingsRepository $settings,private readonly AiJobRepository $jobs){}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/settings/ai'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::isAdministrator()){http_response_code(403);echo 'Administrator access required.';return true;}

        if($path==='/admin/settings/ai'&&$method==='GET'){
            $users=Database::connection()->query("SELECT id,name,email,role FROM users WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            View::render('admin/settings-ai',['pageTitle'=>'AI Content Settings','adminUser'=>Auth::user(),'settings'=>$this->settings->get(),'jobs'=>$this->jobs->recent(25),'users'=>$users,'success'=>$this->flash('success'),'error'=>$this->flash('error')],'admin/layout');return true;
        }
        if($path==='/admin/settings/ai/save'&&$method==='POST'){
            $this->requireCsrf();
            try{$this->settings->save($_POST);Audit::record('settings.ai.update','settings',null,'Updated autonomous AI content settings',['enabled'=>!empty($_POST['enabled']),'model'=>(string)($_POST['model']??''),'auto_discover'=>!empty($_POST['auto_discover']),'research_depth'=>(string)($_POST['research_depth']??''),'max_drafts_per_day'=>(int)($_POST['max_drafts_per_day']??0)]);$this->setFlash('success','AI content settings saved.');}
            catch(Throwable $e){$this->setFlash('error','AI settings could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/settings/ai');
        }
        if($path==='/admin/settings/ai/test'&&$method==='POST'){
            $this->requireCsrf();
            try{$settings=$this->settings->get();$result=(new OllamaClient((string)$settings['ollama_url'],(string)$settings['model']))->test();$models=$result['models']??[];$found=in_array((string)$settings['model'],$models,true);$this->setFlash('success','Ollama connection succeeded. '.count($models).' model(s) available.'.($found?' Configured model found.':' Configured model was not listed; pull it before running jobs.'));Audit::record('ai.ollama.test','settings',null,'Tested Ollama connection',['model'=>$settings['model'],'configured_model_found'=>$found]);}
            catch(Throwable $e){$this->setFlash('error','Ollama connection failed: '.$e->getMessage());}
            $this->redirect('/admin/settings/ai');
        }
        if($path==='/admin/settings/ai/queue'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $settings=$this->settings->get();if(empty($settings['enabled']))throw new \RuntimeException('Enable autonomous AI content before queueing a job.');
                $topic=trim((string)($_POST['topic']??''));if($topic==='')throw new \InvalidArgumentException('Enter a topic to queue.');
                $type=(string)($_POST['content_type']??'blog');if(!in_array($type,['blog','buying_guide'],true))$type='blog';
                $id=$this->jobs->queue($topic,$type,['queued_by'=>(int)(Auth::user()['id']??0)]);Audit::record('ai.content.queued','ai_job',$id,'Queued AI content draft',['topic'=>$topic,'content_type'=>$type]);$this->setFlash('success','AI draft job #'.$id.' queued. The CLI worker will pick it up.');
            }catch(Throwable $e){$this->setFlash('error','Could not queue AI job: '.$e->getMessage());}
            $this->redirect('/admin/settings/ai');
        }
        return false;
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$value=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($value)?$value:null;}
}
