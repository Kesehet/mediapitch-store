<?php

declare(strict_types=1);

namespace MediaPitch\Ai;

use RuntimeException;

final class NotificationMailer
{
    public function sendDraftReady(array $settings,array $draft,array $job,array $sources): void
    {
        $recipients=array_values(array_filter(array_map('trim',preg_split('/[,;\n]+/',(string)($settings['notification_emails']??''))?:[]),static fn(string $v):bool=>(bool)filter_var($v,FILTER_VALIDATE_EMAIL)));
        if(!$recipients)return;
        $title=(string)($draft['title']??'AI draft');
        $reviewUrl=url('admin/blog/'.(int)($job['content_id']??0).'/edit');
        $sourceLines=[];
        foreach(array_slice($sources,0,20) as $source)$sourceLines[]='- '.((string)($source['title']??'Source')).' — '.((string)($source['url']??''));
        $body="A new AI-generated draft is ready for review.\n\n".
            $title."\n\n".
            'Type: '.((string)($job['content_type']??'blog'))."\n".
            'Model: '.((string)($job['model']??$settings['model']??''))."\n".
            'Sources researched: '.count($sources)."\n\n".
            'Summary:\n'.((string)($draft['excerpt']??''))."\n\n".
            "Research sources:\n".implode("\n",$sourceLines)."\n\n".
            "FULL ARTICLE\n\n".trim(strip_tags((string)($draft['body']??'')))."\n\n".
            'Review in CMS: '.$reviewUrl."\n";
        $from=trim((string)($settings['notification_from']??''));
        if($from!==''&&!filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('AI notification From email is invalid.');
        $headers=['Content-Type: text/plain; charset=UTF-8'];
        if($from!=='')$headers[]='From: MediaPitch Store <'.$from.'>';
        $subject='[MediaPitch] New AI draft ready: '.$title;
        foreach($recipients as $recipient){
            if(!@mail($recipient,$subject,$body,implode("\r\n",$headers)))throw new RuntimeException('Could not send AI draft notification email to '.$recipient.'.');
        }
    }
}
