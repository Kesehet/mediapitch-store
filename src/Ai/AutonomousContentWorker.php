<?php

declare(strict_types=1);

namespace MediaPitch\Ai;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Database;
use MediaPitch\Repositories\ContentRepository;
use MediaPitch\Repositories\SettingsRepository;
use PDO;
use RuntimeException;
use Throwable;

final class AutonomousContentWorker
{
    private SettingsRepository $settingsRepo;
    private AiJobRepository $jobs;

    public function __construct(?SettingsRepository $settingsRepo=null,?AiJobRepository $jobs=null)
    {
        $this->settingsRepo=$settingsRepo??new SettingsRepository();
        $this->jobs=$jobs??new AiJobRepository();
    }

    public function runOnce(): array
    {
        $settings=$this->settingsRepo->ai();
        if(empty($settings['enabled']))return ['status'=>'disabled'];

        if(!$this->jobs->hasOpenJob() && !empty($settings['auto_discover']) && $this->jobs->completedToday()<(int)$settings['max_drafts_per_day']){
            $this->discoverAndQueue($settings);
        }

        $job=$this->jobs->claimNext();
        if(!$job)return ['status'=>'idle'];
        try{
            $result=$this->process($job,$settings);
            return ['status'=>'completed','job_id'=>(int)$job['id'],'content_id'=>$result['content_id']];
        }catch(Throwable $e){
            $this->jobs->fail((int)$job['id'],$e);
            try{Audit::record('ai.content.failed','ai_job',(int)$job['id'],'Autonomous AI content job failed',['error'=>substr($e->getMessage(),0,1000)]);}catch(Throwable){}
            return ['status'=>'failed','job_id'=>(int)$job['id'],'error'=>$e->getMessage()];
        }
    }

    private function discoverAndQueue(array $settings): void
    {
        $client=$this->client($settings);
        $context=$this->cmsContext();
        $schema=['type'=>'object','properties'=>[
            'topic'=>['type'=>'string'],
            'content_type'=>['type'=>'string','enum'=>['blog','buying_guide']],
            'reason'=>['type'=>'string'],
        ],'required'=>['topic','content_type','reason']];
        $result=$client->json(
            'You are the commissioning editor for MediaPitch Store. Pick exactly one useful, non-duplicate editorial opportunity. Prefer topics supported by products/categories already in the CMS. Do not invent breaking news. Return JSON only.',
            "Existing CMS snapshot:\n".$context."\n\nChoose one topic worth researching and drafting now.",
            $schema
        );
        $topic=trim((string)($result['topic']??''));if($topic==='')return;
        $type=(string)($result['content_type']??'blog');
        if($type==='buying_guide' && empty($settings['allow_guides']))$type='blog';
        if($type==='blog' && empty($settings['allow_blog']))return;
        $this->jobs->queue($topic,$type,['discovery_reason'=>(string)($result['reason']??'')]);
    }

    private function process(array $job,array $settings): array
    {
        $id=(int)$job['id'];$topic=(string)$job['topic'];$type=(string)$job['content_type'];
        if($type==='buying_guide'&&empty($settings['allow_guides']))throw new RuntimeException('Buying-guide generation is disabled.');
        if($type==='blog'&&empty($settings['allow_blog']))throw new RuntimeException('Blog generation is disabled.');

        $client=$this->client($settings);
        $this->jobs->setStage($id,'planning_research');
        $queryCount=match((string)$settings['research_depth']){'quick'=>3,'standard'=>5,default=>8};
        $planSchema=['type'=>'object','properties'=>[
            'search_queries'=>['type'=>'array','items'=>['type'=>'string'],'minItems'=>$queryCount,'maxItems'=>$queryCount],
            'research_notes'=>['type'=>'string'],
        ],'required'=>['search_queries','research_notes']];
        $plan=$client->json(
            'You are a meticulous web researcher. Produce diverse search queries that verify facts, surface primary/authoritative sources where possible, and cover buyer intent, alternatives, limitations, and current context. Return JSON only.',
            "Topic: $topic\nContent type: $type\nCreate exactly $queryCount web research queries.",
            $planSchema
        );

        $researcher=new WebResearcher();$seen=[];
        $this->jobs->setStage($id,'researching',['queries'=>$plan['search_queries']??[]]);
        foreach(array_slice((array)($plan['search_queries']??[]),0,$queryCount) as $query){
            $query=trim((string)$query);if($query==='')continue;
            try{$results=$researcher->search($query,4);}catch(Throwable){continue;}
            foreach($results as $result){
                $url=(string)$result['url'];if(isset($seen[$url]))continue;$seen[$url]=true;
                try{$text=$researcher->read($url,10000);}catch(Throwable){continue;}
                if(strlen($text)<250)continue;
                $this->jobs->addSource($id,$query,$url,(string)$result['title'],$text);
                if(count($seen)>=24)break 2;
            }
        }
        $sources=$this->jobs->sources($id);
        if(count($sources)<2)throw new RuntimeException('Research did not return enough readable sources to create a defensible draft.');

        $this->jobs->setStage($id,'writing',['source_count'=>count($sources)]);
        $sourceText='';
        foreach($sources as $index=>$source){
            $sourceText.="\nSOURCE ".($index+1).": ".($source['title']??'')."\nURL: ".($source['url']??'')."\n".substr((string)($source['excerpt']??''),0,7000)."\n";
            if(strlen($sourceText)>70000)break;
        }
        $cms=$this->cmsContext();
        $draftSchema=['type'=>'object','properties'=>[
            'title'=>['type'=>'string'],
            'slug'=>['type'=>'string'],
            'excerpt'=>['type'=>'string'],
            'body'=>['type'=>'string'],
            'seo_title'=>['type'=>'string'],
            'meta_description'=>['type'=>'string'],
            'tags'=>['type'=>'string'],
            'category_id'=>['type'=>['integer','null']],
        ],'required'=>['title','slug','excerpt','body','seo_title','meta_description','tags','category_id']];
        $draft=$client->json(
            'You are a senior MediaPitch Store editor. Write an original, useful, evidence-led article from the supplied research. Never fabricate facts, prices, product specifications, quotations, or sources. When uncertain, omit the claim. Use clean HTML for the body (h2/h3/p/ul/ol/strong/a only). Link factual claims to relevant supplied source URLs where useful. Optimize naturally for SEO and answer-engine readability, but never keyword-stuff. Product shortcodes may be used only when the CMS snapshot explicitly shows that product ID. Return JSON only.',
            "TOPIC: $topic\nTYPE: $type\n\nCMS SNAPSHOT:\n$cms\n\nRESEARCH:\n$sourceText\n\nCreate the finished editorial draft. It MUST remain a draft; do not include publication instructions.",
            $draftSchema
        );
        $title=trim((string)($draft['title']??''));$body=trim((string)($draft['body']??''));
        if($title===''||strlen(strip_tags($body))<600)throw new RuntimeException('Ollama produced an incomplete article draft.');
        $slug=$this->uniqueSlug((string)($draft['slug']??$title));
        $authorId=$this->authorId((int)($settings['author_id']??0));
        $categoryId=!empty($draft['category_id'])?(int)$draft['category_id']:null;
        if($categoryId&&!$this->categoryExists($categoryId))$categoryId=null;

        $this->jobs->setStage($id,'saving_draft');
        $contentId=(new ContentRepository())->savePost([
            'category_id'=>$categoryId,
            'title'=>$title,
            'slug'=>$slug,
            'excerpt'=>(string)($draft['excerpt']??''),
            'body'=>$body,
            'seo_title'=>(string)($draft['seo_title']??''),
            'meta_description'=>(string)($draft['meta_description']??''),
            'canonical_url'=>'',
            'robots_index'=>0,
            'status'=>'draft',
            'published_at'=>'',
            'tags'=>(string)($draft['tags']??''),
        ],$authorId,null,$type);

        $job['content_id']=$contentId;$job['model']=$client->model();
        $this->jobs->complete($id,$contentId,$client->model());
        Audit::record('ai.content.draft_created','content',$contentId,'AI created an editorial draft for human review',['ai_job_id'=>$id,'topic'=>$topic,'content_type'=>$type,'source_count'=>count($sources),'model'=>$client->model(),'status'=>'draft']);

        $this->jobs->setStage($id,'notifying');
        try{(new NotificationMailer())->sendDraftReady($settings,$draft,$job,$sources);}catch(Throwable $mailError){
            Audit::record('ai.content.notification_failed','content',$contentId,'AI draft created but notification email failed',['ai_job_id'=>$id,'error'=>substr($mailError->getMessage(),0,1000)]);
        }
        return ['content_id'=>$contentId,'draft'=>$draft];
    }

    private function client(array $settings): OllamaClient
    {
        $url=trim((string)($settings['ollama_url']??''));$model=trim((string)($settings['model']??''));
        if($url===''||$model==='')throw new RuntimeException('Ollama URL and model must be configured.');
        return new OllamaClient($url,$model);
    }

    private function cmsContext(): string
    {
        $db=Database::connection();$lines=[];
        $categories=$db->query('SELECT id,name,slug FROM categories WHERE active=1 ORDER BY name LIMIT 80')->fetchAll(PDO::FETCH_ASSOC);
        $lines[]='CATEGORIES: '.json_encode($categories,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $products=$db->query("SELECT p.id,p.title,p.display_title,p.price,p.currency,p.best_for_label,c.name AS category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 ORDER BY p.updated_at DESC LIMIT 80")->fetchAll(PDO::FETCH_ASSOC);
        $lines[]='PRODUCTS: '.json_encode($products,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $content=$db->query("SELECT id,type,title,slug,status FROM content ORDER BY updated_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        $lines[]='EXISTING CONTENT: '.json_encode($content,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return implode("\n",$lines);
    }

    private function authorId(int $configured): int
    {
        if($configured>0){$stmt=Database::connection()->prepare('SELECT id FROM users WHERE id=:id AND active=1 LIMIT 1');$stmt->execute(['id'=>$configured]);if($stmt->fetchColumn())return $configured;}
        $id=(int)(Database::connection()->query("SELECT id FROM users WHERE active=1 ORDER BY CASE role WHEN 'administrator' THEN 1 WHEN 'editor' THEN 2 ELSE 3 END,id LIMIT 1")->fetchColumn()?:0);
        if(!$id)throw new RuntimeException('No active CMS user is available to own AI drafts.');return $id;
    }

    private function categoryExists(int $id): bool
    {
        $stmt=Database::connection()->prepare('SELECT 1 FROM categories WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);return (bool)$stmt->fetchColumn();
    }

    private function uniqueSlug(string $value): string
    {
        $slug=strtolower(trim($value));if(function_exists('iconv')){$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$slug);if(is_string($ascii))$slug=$ascii;}
        $slug=trim(preg_replace('/[^a-z0-9]+/','-',$slug)??'','-');if($slug==='')$slug='ai-draft-'.gmdate('Ymd-His');
        $base=substr($slug,0,180);$candidate=$base;$i=2;$stmt=Database::connection()->prepare('SELECT 1 FROM content WHERE slug=:slug LIMIT 1');
        while(true){$stmt->execute(['slug'=>$candidate]);if(!$stmt->fetchColumn())return $candidate;$candidate=substr($base,0,170).'-'.$i++;}
    }
}
