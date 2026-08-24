<?php

declare(strict_types=1);

namespace MediaPitch\Ai;

use DateTimeImmutable;
use DateTimeZone;
use MediaPitch\Core\Database;
use PDO;
use Throwable;

final class AiJobRepository
{
    public function queue(string $topic,string $contentType='blog',array $metadata=[],string $triggerMode='manual'): int
    {
        $contentType=$contentType==='buying_guide'?'buying_guide':'blog';
        $triggerMode=$triggerMode==='automatic'?'automatic':'manual';
        $stmt=Database::connection()->prepare('INSERT INTO ai_jobs (topic,content_type,trigger_mode,metadata_json) VALUES (:topic,:type,:trigger,:meta)');
        $stmt->execute(['topic'=>substr(trim($topic),0,500),'type'=>$contentType,'trigger'=>$triggerMode,'meta'=>$metadata?json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null]);
        return (int)Database::connection()->lastInsertId();
    }

    public function claimNext(string $triggerMode): ?array
    {
        $triggerMode=$triggerMode==='automatic'?'automatic':'manual';
        $db=Database::connection();$db->beginTransaction();
        try{
            $stmt=$db->prepare("SELECT * FROM ai_jobs WHERE status='queued' AND trigger_mode=:trigger ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
            $stmt->execute(['trigger'=>$triggerMode]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row){$db->commit();return null;}
            $update=$db->prepare("UPDATE ai_jobs SET status='running',attempts=attempts+1,started_at=COALESCE(started_at,UTC_TIMESTAMP()),stage='starting' WHERE id=:id AND status='queued'");
            $update->execute(['id'=>$row['id']]);
            if($update->rowCount()!==1){$db->rollBack();return null;}
            $db->commit();$row['status']='running';$row['attempts']=(int)$row['attempts']+1;return $row;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public function setStage(int $id,string $stage,array $metadata=[]): void
    {
        $stmt=Database::connection()->prepare('UPDATE ai_jobs SET stage=:stage,metadata_json=CASE WHEN :meta IS NULL THEN metadata_json ELSE :meta END WHERE id=:id');
        $json=$metadata?json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;$stmt->execute(['stage'=>substr($stage,0,80),'meta'=>$json,'id'=>$id]);
    }

    public function addSource(int $jobId,string $query,string $url,string $title,string $excerpt): void
    {
        $host=(string)(parse_url($url,PHP_URL_HOST)?:'');$stmt=Database::connection()->prepare('INSERT IGNORE INTO ai_research_sources (job_id,query_text,url,title,publisher,excerpt) VALUES (:job,:query,:url,:title,:publisher,:excerpt)');
        $stmt->execute(['job'=>$jobId,'query'=>substr($query,0,1000),'url'=>substr($url,0,2048),'title'=>substr($title,0,500),'publisher'=>substr($host,0,255),'excerpt'=>substr($excerpt,0,30000)]);
    }

    public function sources(int $jobId): array
    {
        $stmt=Database::connection()->prepare('SELECT query_text,url,title,publisher,excerpt,retrieved_at FROM ai_research_sources WHERE job_id=:id ORDER BY id');$stmt->execute(['id'=>$jobId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function complete(int $id,int $contentId,string $model): void
    {
        $stmt=Database::connection()->prepare("UPDATE ai_jobs SET status='completed',stage='ready_for_review',content_id=:content,model=:model,completed_at=UTC_TIMESTAMP(),error_message=NULL WHERE id=:id");$stmt->execute(['content'=>$contentId,'model'=>substr($model,0,150),'id'=>$id]);
    }

    public function fail(int $id,Throwable|string $error): void
    {
        $message=$error instanceof Throwable?$error->getMessage():$error;$stmt=Database::connection()->prepare("UPDATE ai_jobs SET status='failed',stage='failed',completed_at=UTC_TIMESTAMP(),error_message=:error WHERE id=:id");$stmt->execute(['error'=>substr($message,0,5000),'id'=>$id]);
    }

    public function automaticRunStartedToday(string $timezone='Asia/Kolkata'): bool
    {
        $tz=new DateTimeZone($timezone);$now=new DateTimeImmutable('now',$tz);$start=$now->setTime(0,0)->setTimezone(new DateTimeZone('UTC'));$end=$start->modify('+1 day');
        $stmt=Database::connection()->prepare("SELECT 1 FROM ai_jobs WHERE trigger_mode='automatic' AND started_at>=:start AND started_at<:end LIMIT 1");
        $stmt->execute(['start'=>$start->format('Y-m-d H:i:s'),'end'=>$end->format('Y-m-d H:i:s')]);return (bool)$stmt->fetchColumn();
    }

    public function hasOpenAutomaticJob(): bool{return (bool)Database::connection()->query("SELECT 1 FROM ai_jobs WHERE trigger_mode='automatic' AND status IN ('queued','running') LIMIT 1")->fetchColumn();}

    public function recent(int $limit=20): array
    {
        $limit=max(1,min(100,$limit));return Database::connection()->query("SELECT j.*,c.title AS content_title,c.slug AS content_slug FROM ai_jobs j LEFT JOIN content c ON c.id=j.content_id ORDER BY j.created_at DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    }
}
