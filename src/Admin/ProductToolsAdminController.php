<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\Database;
use MediaPitch\Core\View;
use MediaPitch\Services\ProductBackfillService;
use MediaPitch\Services\ProductCsv;
use PDO;
use Throwable;

final class ProductToolsAdminController
{
    public function __construct(private readonly ProductCsv $csv) {}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/product-tools'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::canManageProducts()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/product-tools'&&$method==='GET'){
            $backfill=new ProductBackfillService();
            $history=[];
            try{
                $history=Database::connection()->query(
                    "SELECT l.product_id,l.field_name,l.source_type,l.confidence,l.created_at,COALESCE(p.display_title,p.title) AS product_title
                     FROM product_enrichment_log l
                     JOIN products p ON p.id=l.product_id
                     WHERE l.status='applied'
                     ORDER BY l.created_at DESC,l.id DESC LIMIT 30"
                )->fetchAll(PDO::FETCH_ASSOC);
            }catch(Throwable){}
            View::render('admin/product-tools',[
                'pageTitle'=>'Product Tools',
                'adminUser'=>Auth::user(),
                'success'=>$this->flash('success'),
                'error'=>$this->flash('error'),
                'backfillStats'=>$backfill->stats(),
                'backfillCandidates'=>$backfill->candidates(100),
                'backfillHistory'=>$history,
            ],'admin/layout');
            return true;
        }

        if($path==='/admin/product-tools/export.csv'&&$method==='GET'){
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="mediapitch-products-'.gmdate('Ymd-His').'.csv"');
            $out=fopen('php://output','wb');
            fputcsv($out,ProductCsv::HEADERS);
            foreach($this->csv->exportRows()as$row){$line=[];foreach(ProductCsv::HEADERS as$header)$line[]=$row[$header]??'';fputcsv($out,$line);}
            fclose($out);exit;
        }

        if($path==='/admin/product-tools/import'&&$method==='POST'){
            $this->requireCsrf();
            $file=$_FILES['csv']??[];
            try{
                if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new \RuntimeException('Choose a CSV file to import.');
                if((int)($file['size']??0)>5*1024*1024)throw new \RuntimeException('CSV must be 5 MB or smaller.');
                $result=$this->csv->importFile((string)$file['tmp_name']);
                Audit::record('products.csv.import','product',null,'Imported products from CSV',['created'=>$result['created'],'updated'=>$result['updated'],'error_count'=>count($result['errors'])]);
                $message=$result['created'].' created, '.$result['updated'].' updated.';
                if($result['errors'])$message.=' '.count($result['errors']).' row(s) skipped: '.implode(' | ',array_slice($result['errors'],0,5));
                $this->setFlash($result['errors']?'error':'success',$message);
            }catch(Throwable$e){$this->setFlash('error','CSV import failed: '.$e->getMessage());}
            $this->redirect('/admin/product-tools');
        }

        if(in_array($path,['/admin/product-tools/backfill-next','/admin/product-tools/backfill-selected'],true)&&$method==='POST'){
            $this->requireCsrf();
            try{
                $useAi=!empty($_POST['use_ai']);
                $limit=max(1,min(10,(int)($_POST['limit']??3)));
                $ids=[];
                if($path==='/admin/product-tools/backfill-selected'){
                    $ids=array_values(array_unique(array_filter(array_map('intval',is_array($_POST['product_ids']??null)?$_POST['product_ids']:[]))));
                    if(!$ids)throw new \InvalidArgumentException('Select at least one product to backfill.');
                }
                $result=(new ProductBackfillService())->backfillBatch($ids,$limit,$useAi);
                Audit::record('products.backfill','product',null,'Ran product metadata backfill',[
                    'processed'=>$result['processed'],'updated'=>$result['updated'],'fields'=>$result['fields'],'failed'=>$result['failed'],'use_ai'=>$useAi,
                ]);
                $message=$result['processed'].' processed; '.$result['updated'].' product(s) updated with '.$result['fields'].' field(s).';
                if($result['failed'])$message.=' '.$result['failed'].' failed.';
                $details=[];
                foreach($result['results'] as $row){
                    $status=(string)($row['status']??'');
                    if($status==='failed')$details[]='#'.(int)$row['product_id'].' failed: '.(string)($row['error']??'Unknown error');
                    elseif($status==='no_change')$details[]='#'.(int)$row['product_id'].' no change: '.(string)($row['diagnostic']??'No new data found.');
                }
                if($details)$message.=' '.implode(' | ',array_slice($details,0,3));
                $this->setFlash(($result['failed']||($result['updated']===0&&$details))?'error':'success',$message);
            }catch(Throwable$e){
                $this->setFlash('error','Product backfill failed: '.$e->getMessage());
            }
            $this->redirect('/admin/product-tools');
        }

        if($path==='/admin/product-tools/bulk'&&$method==='POST'){
            $this->requireCsrf();
            $ids=array_values(array_unique(array_filter(array_map('intval',is_array($_POST['product_ids']??null)?$_POST['product_ids']:[]))));
            $action=(string)($_POST['bulk_action']??'');
            if(!$ids||!in_array($action,['archive','restore'],true)){$this->setFlash('error','Choose products and a valid bulk action.');$this->redirect('/admin/products');}
            try{
                $placeholders=implode(',',array_fill(0,count($ids),'?'));
                $stmt=Database::connection()->prepare('UPDATE products SET active=? WHERE id IN ('.$placeholders.')');
                $stmt->execute(array_merge([$action==='restore'?1:0],$ids));
                Audit::record('products.bulk_'.$action,'product',null,ucfirst($action).'d products in bulk',['product_ids'=>$ids,'count'=>count($ids)]);
                $this->setFlash('success',count($ids).' product(s) '.($action==='restore'?'restored':'archived').'.');
            }catch(Throwable$e){$this->setFlash('error','Bulk action failed: '.$e->getMessage());}
            $this->redirect('/admin/products');
        }

        return false;
    }

    private function requireCsrf():void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string$path):never{header('Location: '.url($path));exit;}
    private function setFlash(string$key,string$value):void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string$key):?string{$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
