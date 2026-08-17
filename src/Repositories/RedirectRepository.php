<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use PDO;

final class RedirectRepository
{
    public function all(): array
    {
        return Database::connection()->query('SELECT * FROM redirects ORDER BY updated_at DESC,id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(?int $id): ?array
    {
        if(!$id)return null;
        $stmt=Database::connection()->prepare('SELECT * FROM redirects WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    public function resolve(string $path): ?array
    {
        $stmt=Database::connection()->prepare('SELECT to_url,status_code FROM redirects WHERE from_path=:path AND active=1 LIMIT 1');
        $stmt->execute(['path'=>$this->normalizePath($path)]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    public function save(array $data,?int $id=null): int
    {
        $from=$this->normalizePath((string)($data['from_path']??''));
        $to=trim((string)($data['to_url']??''));
        $status=(int)($data['status_code']??301);
        if($from==='/' || $to==='') throw new InvalidArgumentException('A non-root source path and destination are required.');
        if(!in_array($status,[301,302,307,308],true)) $status=301;
        if(!str_starts_with($to,'/') && !filter_var($to,FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Destination must be a site path or valid absolute URL.');
        if($to===$from) throw new InvalidArgumentException('Source and destination cannot be the same.');
        $params=['from_path'=>$from,'to_url'=>$to,'status_code'=>$status,'active'=>!empty($data['active'])?1:0];
        if($id){
            $params['id']=$id;
            $stmt=Database::connection()->prepare('UPDATE redirects SET from_path=:from_path,to_url=:to_url,status_code=:status_code,active=:active WHERE id=:id');
            $stmt->execute($params);
            return $id;
        }
        $stmt=Database::connection()->prepare('INSERT INTO redirects (from_path,to_url,status_code,active) VALUES (:from_path,:to_url,:status_code,:active) ON DUPLICATE KEY UPDATE to_url=VALUES(to_url),status_code=VALUES(status_code),active=VALUES(active)');
        $stmt->execute($params);
        return (int)Database::connection()->lastInsertId();
    }

    public function upsert(string $fromPath,string $toUrl,int $status=301): void
    {
        $from=$this->normalizePath($fromPath);
        if($from==='/' || $from===$toUrl)return;
        $stmt=Database::connection()->prepare('INSERT INTO redirects (from_path,to_url,status_code,active) VALUES (:from,:to,:status,1) ON DUPLICATE KEY UPDATE to_url=VALUES(to_url),status_code=VALUES(status_code),active=1');
        $stmt->execute(['from'=>$from,'to'=>$toUrl,'status'=>in_array($status,[301,302,307,308],true)?$status:301]);
    }

    private function normalizePath(string $path): string
    {
        $path=parse_url(trim($path),PHP_URL_PATH)?:'/';
        return '/'.trim($path,'/');
    }
}
