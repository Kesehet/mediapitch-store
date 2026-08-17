<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\Database;
use MediaPitch\Core\View;
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
            View::render('admin/product-tools',['pageTitle'=>'Product Tools','adminUser'=>Auth::user(),'success'=>$this->flash('success'),'error'=>$this->flash('error')],'admin/layout');
            return true;
        }

        if($path==='/admin/product-tools/export.csv'&&$method==='GET'){
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="mediapitch-products-'.gmdate('Ymd-His').'.csv"');
            $out=fopen('php://output','wb');
            fputcsv($out,ProductCsv::HEADERS);
            foreach($this->csv->exportRows() as $row){$line=[];foreach(ProductCsv::HEADERS as $header)$line[]=$row[$header]??'';fputcsv($out,$line);}
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
            }catch(Throwable $e){$this->setFlash('error','CSV import failed: '.$e->getMessage());}
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
            }catch(Throwable $e){$this->setFlash('error','Bulk action failed: '.$e->getMessage());}
            $this->redirect('/admin/products');
        }

        return false;
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
