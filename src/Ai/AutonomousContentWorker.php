<?php

declare(strict_types=1);

namespace MediaPitch\Ai;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Database;
use MediaPitch\Repositories\AiSettingsRepository;
use MediaPitch\Repositories\ContentRepository;
use PDO;
use RuntimeException;
use Throwable;

final class AutonomousContentWorker
{
    public function __construct(private readonly ?AiSettingsRepository $settingsRepo=null,private readonly ?AiJobRepository $jobRepo=null){}

    private function settings(): AiSettingsRepository{return $this->settingsRepo??new AiSettingsRepository();}
    private function jobs(): AiJobRepository{return $this->jobRepo??new AiJobRepository();}

    public function runOnce(string $triggerMode='automatic'): array
    {
        $settings=$this->settings()->get();
        if(empty($settings['enabled']))return ['status'=>'disabled'];
        $jobs=$this->jobs();$triggerMode=$triggerMode==='manual'?'manual':'automatic';

        if($triggerMode==='automatic'){
            if($jobs->automaticRunStartedToday('Asia/Kolkata'))return ['status'=>'daily_limit'];
            if(!$jobs->hasOpenAutomaticJob()){
                if(empty($settings['auto_discover']))return ['status'=>'idle'];
                $this->discoverAndQueue($settings);
            }
        }

        $job=$jobs->claimNext($triggerMode);if(!$job)return ['status'=>'idle'];
        try{$contentId=$this->process($job,$settings);return ['status'=>'completed','job_id'=>(int)$job['id'],'content_id'=>$contentId];}
        catch(Throwable $e){$jobs->fail((int)$job['id'],$e);try{Audit::record('ai.content.failed','ai_job',(int)$job['id'],'Autonomous AI content job failed',['error'=>substr($e->getMessage(),0,1000),'trigger_mode'=>$triggerMode]);}catch(Throwable){}return ['status'=>'failed','job_id'=>(int)$job['id'],'error'=>$e->getMessage()];}
    }

    private function discoverAndQueue(array $settings): void
    {
        $schema=['type'=>'object','properties'=>['topic'=>['type'=>'string'],'content_type'=>['type'=>'string','enum'=>['blog','buying_guide']],'reason'=>['type'=>'string']],'required'=>['topic','content_type','reason']];
        $result=$this->client($settings)->json('You are the commissioning editor for MediaPitch Store. Pick one useful, non-duplicate editorial opportunity supported by the CMS catalogue. Do not invent breaking news. Return JSON only.',"CMS snapshot:\n".$this->cmsContext(),$schema);
        $topic=trim((string)($result['topic']??''));if($topic==='')return;
        $type=(string)($result['content_type']??'blog');
        if($type==='buying_guide'&&empty($settings['allow_guides']))$type='blog';
        if($type==='blog'&&empty($settings['allow_blog']))return;
        $this->jobs()->queue($topic,$type,['discovery_reason'=>(string)($result['reason']??'')],'automatic');
    }

    private function process(array $job,array $settings): int
    {
        $jobs=$this->jobs();$id=(int)$job['id'];$topic=(string)$job['topic'];$type=(string)$job['content_type'];
        if($type==='buying_guide'&&empty($settings['allow_guides']))throw new RuntimeException('Buying-guide generation is disabled.');
        if($type==='blog'&&empty($settings['allow_blog']))throw new RuntimeException('Blog generation is disabled.');
        $client=$this->client($settings);$count=match((string)$settings['research_depth']){'quick'=>3,'standard'=>5,default=>8};
        $jobs->setStage($id,'planning_research');
        $plan=$client->json('You are a meticulous web researcher. Produce diverse queries covering authoritative facts, buyer intent, alternatives, limitations and current context. Return JSON only.',"Topic: $topic\nCreate exactly $count web research queries.",['type'=>'object','properties'=>['search_queries'=>['type'=>'array','items'=>['type'=>'string'],'minItems'=>$count,'maxItems'=>$count]],'required'=>['search_queries']]);
        $researcher=new WebResearcher();$seen=[];$jobs->setStage($id,'researching',['queries'=>$plan['search_queries']??[]]);
        foreach(array_slice((array)($plan['search_queries']??[]),0,$count) as $query){
            $query=trim((string)$query);if($query==='')continue;
            try{$results=$researcher->search($query,4);}catch(Throwable){continue;}
            foreach($results as $result){$url=(string)$result['url'];if(isset($seen[$url]))continue;$seen[$url]=true;try{$text=$researcher->read($url,10000);}catch(Throwable){continue;}if(strlen($text)<250)continue;$jobs->addSource($id,$query,$url,(string)$result['title'],$text);if(count($seen)>=24)break 2;}
        }
        $sources=$jobs->sources($id);if(count($sources)<2)throw new RuntimeException('Research did not return enough readable sources to create a defensible draft.');
        $research='';foreach($sources as $n=>$source){$research.="\nSOURCE ".($n+1).": ".($source['title']??'')."\nURL: ".($source['url']??'')."\n".substr((string)($source['excerpt']??''),0,7000)."\n";if(strlen($research)>70000)break;}
        $jobs->setStage($id,'writing',['source_count'=>count($sources)]);
        $schema=['type'=>'object','properties'=>['title'=>['type'=>'string'],'slug'=>['type'=>'string'],'excerpt'=>['type'=>'string'],'body'=>['type'=>'string'],'seo_title'=>['type'=>'string'],'meta_description'=>['type'=>'string'],'tags'=>['type'=>'string'],'category_id'=>['type'=>['integer','null']]],'required'=>['title','slug','excerpt','body','seo_title','meta_description','tags','category_id']];
        $draft=$client->json('You are a senior MediaPitch Store editor. Write an original evidence-led article using only supported facts from the supplied research and CMS snapshot. Never fabricate prices, specifications, quotes or sources. Use clean HTML (h2,h3,p,ul,ol,strong,a). Link claims to supplied sources where useful. Optimize naturally for SEO/AEO. Product shortcodes may only use explicit CMS product IDs. Return JSON only.',"TOPIC: $topic\nTYPE: $type\n\nCMS:\n".$this->cmsContext()."\n\nRESEARCH:\n$research",$schema);
        $title=trim((string)($draft['title']??''));$body=trim((string)($draft['body']??''));if($title===''||strlen(strip_tags($body))<600)throw new RuntimeException('Ollama produced an incomplete draft.');
        $categoryId=!empty($draft['category_id'])?(int)$draft['category_id']:null;if($categoryId&&!$this->categoryExists($categoryId))$categoryId=null;
        $jobs->setStage($id,'saving_draft');
        $contentId=(new ContentRepository())->savePost(['category_id'=>$categoryId,'title'=>$title,'slug'=>$this->uniqueSlug((string)($draft['slug']??$title)),'excerpt'=>(string)($draft['excerpt']??''),'body'=>$body,'seo_title'=>(string)($draft['seo_title']??''),'meta_description'=>(string)($draft['meta_description']??''),'canonical_url'=>'','robots_index'=>0,'status'=>'draft','published_at'=>'','tags'=>(string)($draft['tags']??'')],$this->authorId((int)($settings['author_id']??0)),null,$type);
        $job['content_id']=$contentId;$job['model']=$client->model();$jobs->complete($id,$contentId,$client->model());
        Audit::record('ai.content.draft_created','content',$contentId,'AI created an editorial draft for human review',['ai_job_id'=>$id,'topic'=>$topic,'content_type'=>$type,'source_count'=>count($sources),'model'=>$client->model(),'status'=>'draft','trigger_mode'=>$job['trigger_mode']??'automatic']);
        try{(new NotificationMailer())->sendDraftReady($settings,$draft,$job,$sources);}catch(Throwable $e){Audit::record('ai.content.notification_failed','content',$contentId,'AI draft created but notification email failed',['ai_job_id'=>$id,'error'=>substr($e->getMessage(),0,1000)]);}
        return $contentId;
    }

    private function client(array $settings): OllamaClient
    {
        $url=trim((string)($settings['ollama_url']??''));$model=trim((string)($settings['model']??''));$apiKey=trim((string)($settings['api_key']??''));
        if($url===''||$model===''||$apiKey==='')throw new RuntimeException('Remote Ollama URL, model and API key must be configured.');
        return new OllamaClient($url,$model,$apiKey);
    }

    private function cmsContext(): string
    {
        $db=Database::connection();$categories=$db->query('SELECT id,name,slug FROM categories WHERE active=1 ORDER BY name LIMIT 80')->fetchAll(PDO::FETCH_ASSOC);$products=$db->query('SELECT p.id,p.title,p.display_title,p.price,p.currency,p.best_for_label,c.name AS category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 ORDER BY p.updated_at DESC LIMIT 80')->fetchAll(PDO::FETCH_ASSOC);$content=$db->query('SELECT id,type,title,slug,status FROM content ORDER BY updated_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);return 'CATEGORIES: '.json_encode($categories,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\nPRODUCTS: ".json_encode($products,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\nEXISTING CONTENT: ".json_encode($content,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }

    private function authorId(int $configured): int
    {
        $db=Database::connection();if($configured>0){$stmt=$db->prepare('SELECT id FROM users WHERE id=:id AND active=1 LIMIT 1');$stmt->execute(['id'=>$configured]);if($stmt->fetchColumn())return $configured;}$id=(int)($db->query("SELECT id FROM users WHERE active=1 ORDER BY CASE role WHEN 'administrator' THEN 1 WHEN 'editor' THEN 2 ELSE 3 END,id LIMIT 1")->fetchColumn()?:0);if(!$id)throw new RuntimeException('No active CMS user is available to own AI drafts.');return $id;
    }
    private function categoryExists(int $id): bool{$stmt=Database::connection()->prepare('SELECT 1 FROM categories WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);return (bool)$stmt->fetchColumn();}
    private function uniqueSlug(string $value): string{$slug=strtolower(trim($value));if(function_exists('iconv')){$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$slug);if(is_string($ascii))$slug=$ascii;}$slug=trim(preg_replace('/[^a-z0-9]+/','-',$slug)??'','-');if($slug==='')$slug='ai-draft-'.gmdate('Ymd-His');$base=substr($slug,0,180);$candidate=$base;$i=2;$stmt=Database::connection()->prepare('SELECT 1 FROM content WHERE slug=:slug LIMIT 1');while(true){$stmt->execute(['slug'=>$candidate]);if(!$stmt->fetchColumn())return $candidate;$candidate=substr($base,0,170).'-'.$i++;}}
}
