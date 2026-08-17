<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AnalyticsRepository;

final class AnalyticsAdminController
{
    public function __construct(private readonly AnalyticsRepository $repo) {}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/analytics')) return false;
        if(!Auth::check()) $this->redirect('/admin/login');
        if(!Auth::isAdministrator()) { http_response_code(403); exit('Forbidden'); }
        if($method!=='GET') return false;

        [$from,$to]=$this->range();
        if($path==='/admin/analytics/export'){
            $this->export($from,$to);
        }
        if($path==='/admin/analytics'){
            View::render('admin/analytics',[
                'pageTitle'=>'Affiliate Analytics',
                'adminUser'=>Auth::user(),
                'from'=>$from,
                'to'=>$to,
                'report'=>$this->repo->report($from,$to),
            ],'admin/layout');
            return true;
        }
        return false;
    }

    private function range(): array
    {
        $to=(string)($_GET['to']??gmdate('Y-m-d'));
        $from=(string)($_GET['from']??gmdate('Y-m-d',strtotime('-29 days')));
        foreach([&$from,&$to] as &$value){
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('UTC'));
            if(!$date || $date->format('Y-m-d')!==$value) $value=gmdate('Y-m-d');
        }
        unset($value);
        if($from>$to) [$from,$to]=[$to,$from];
        return [$from,$to];
    }

    private function export(string $from,string $to): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="affiliate-clicks-'.$from.'-to-'.$to.'.csv"');
        $out=fopen('php://output','wb');
        fputcsv($out,['clicked_at','product','content','content_type','rank_position','cta_location','campaign','referring_url','user_agent']);
        foreach($this->repo->exportRows($from,$to) as $row) fputcsv($out,$row);
        fclose($out);
        exit;
    }

    private function redirect(string $path): never { header('Location: '.url($path)); exit; }
}
