<?php

declare(strict_types=1);

namespace MediaPitch\Api;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Database;
use MediaPitch\Repositories\AdminRepository;
use PDO;
use Throwable;

final class AdminApiController
{
    public function __construct(private readonly AdminRepository $repo) {}

    public function handle(string $method, string $path): bool
    {
        if (!str_starts_with($path, '/api/v1/')) return false;

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex, nofollow');

        if (!$this->authorized($method)) {
            $this->json(['ok'=>false,'error'=>'unauthorized'], 401);
        }

        try {
            if ($method === 'GET' && $path === '/api/v1/status') {
                $this->json([
                    'ok'=>true,
                    'service'=>'MediaPitch CMS Admin API',
                    'version'=>'v1',
                    'database'=>'ok',
                    'get_writes_enabled'=>(bool)env('CMS_API_ALLOW_GET_WRITES', false),
                    'resources'=>['products','categories','brands','guides'],
                ]);
            }

            if ($method === 'GET' && $path === '/api/v1/products') {
                $this->json(['ok'=>true,'data'=>$this->filterRows($this->repo->products())]);
            }
            if ($method === 'GET' && preg_match('#^/api/v1/products/(\d+)$#', $path, $m)) {
                $row=$this->repo->product((int)$m[1]);
                if (!$row) $this->json(['ok'=>false,'error'=>'not_found'],404);
                $this->json(['ok'=>true,'data'=>$this->filterRow($row)]);
            }
            if ($method === 'GET' && $path === '/api/v1/categories') {
                $this->json(['ok'=>true,'data'=>$this->repo->categories()]);
            }
            if ($method === 'GET' && $path === '/api/v1/brands') {
                $this->json(['ok'=>true,'data'=>$this->repo->brands()]);
            }
            if ($method === 'GET' && $path === '/api/v1/guides') {
                $this->json(['ok'=>true,'data'=>$this->repo->guides()]);
            }
            if ($method === 'GET' && preg_match('#^/api/v1/guides/(\d+)$#', $path, $m)) {
                $row=$this->repo->guide((int)$m[1]);
                if (!$row) $this->json(['ok'=>false,'error'=>'not_found'],404);
                $this->json(['ok'=>true,'data'=>$row]);
            }

            if ($path === '/api/v1/command' && in_array($method,['GET','POST'],true)) {
                if ($method === 'GET' && !(bool)env('CMS_API_ALLOW_GET_WRITES', false)) {
                    $this->json(['ok'=>false,'error'=>'get_writes_disabled'],405);
                }
                $payload=$method==='GET' ? $this->getCommandPayload() : $this->jsonBody();
                $this->json($this->executeCommand($payload, $method));
            }

            $this->json(['ok'=>false,'error'=>'not_found'],404);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok'=>false,'error'=>'validation_error','message'=>$e->getMessage()],422);
        } catch (Throwable $e) {
            if ((bool)env('APP_DEBUG',false)) {
                $this->json(['ok'=>false,'error'=>'server_error','message'=>$e->getMessage()],500);
            }
            $this->json(['ok'=>false,'error'=>'server_error'],500);
        }
    }

    private function executeCommand(array $payload, string $method): array
    {
        $op=trim((string)($payload['op']??''));
        $requestId=trim((string)($payload['request_id']??''));
        $data=is_array($payload['data']??null)?$payload['data']:[];

        if ($op==='') throw new \InvalidArgumentException('op is required.');
        if ($method==='GET' && $requestId==='') throw new \InvalidArgumentException('request_id is required for GET write commands.');

        if ($requestId!=='') {
            if (!preg_match('/^[A-Za-z0-9._:-]{8,120}$/',$requestId)) {
                throw new \InvalidArgumentException('request_id must be 8-120 safe characters.');
            }
            $existing=$this->idempotencyGet($requestId);
            if ($existing!==null) return $existing+['replayed'=>true];
        }

        $result=match($op){
            'product.save'=>$this->saveProduct($data),
            'product.archive'=>$this->setProductActive($data,false),
            'product.restore'=>$this->setProductActive($data,true),
            'category.save'=>$this->saveCategory($data),
            'category.archive'=>$this->setCategoryActive($data,false),
            'category.restore'=>$this->setCategoryActive($data,true),
            'brand.save'=>$this->saveBrand($data),
            'guide.save'=>$this->saveGuide($data),
            default=>throw new \InvalidArgumentException('Unsupported op.'),
        };

        $response=['ok'=>true,'op'=>$op,'result'=>$result];
        if ($requestId!=='') $this->idempotencyPut($requestId,$response);
        return $response;
    }

    private function saveProduct(array $data): array
    {
        $id=!empty($data['id'])?(int)$data['id']:null;
        if (trim((string)($data['title']??''))==='') throw new \InvalidArgumentException('title is required.');
        if (trim((string)($data['slug']??''))==='') $data['slug']=$this->slug((string)$data['title']);
        if (!array_key_exists('active',$data)) $data['active']=0;
        $saved=$this->repo->saveProduct($data,$id);
        Audit::record('api.product.save','product',$saved,'Saved product via CMS API',['request_source'=>'api']);
        return ['id'=>$saved,'product'=>$this->filterRow($this->repo->product($saved)??[])];
    }

    private function setProductActive(array $data,bool $active): array
    {
        $id=(int)($data['id']??0); if($id<1) throw new \InvalidArgumentException('id is required.');
        $stmt=Database::connection()->prepare('UPDATE products SET active=:active WHERE id=:id');
        $stmt->execute(['active'=>$active?1:0,'id'=>$id]);
        Audit::record($active?'api.product.restore':'api.product.archive','product',$id,$active?'Restored product via CMS API':'Archived product via CMS API');
        return ['id'=>$id,'active'=>$active];
    }

    private function saveCategory(array $data): array
    {
        $id=!empty($data['id'])?(int)$data['id']:null;
        if (trim((string)($data['name']??''))==='') throw new \InvalidArgumentException('name is required.');
        if (trim((string)($data['slug']??''))==='') $data['slug']=$this->slug((string)$data['name']);
        if (!array_key_exists('active',$data)) $data['active']=1;
        $saved=$this->repo->saveCategory($data,$id);
        Audit::record('api.category.save','category',$saved,'Saved category via CMS API');
        return ['id'=>$saved,'category'=>$this->repo->category($saved)];
    }

    private function setCategoryActive(array $data,bool $active): array
    {
        $id=(int)($data['id']??0); if($id<1) throw new \InvalidArgumentException('id is required.');
        $this->repo->setCategoryActive($id,$active);
        Audit::record($active?'api.category.restore':'api.category.archive','category',$id,$active?'Restored category via CMS API':'Archived category via CMS API');
        return ['id'=>$id,'active'=>$active];
    }

    private function saveBrand(array $data): array
    {
        $id=!empty($data['id'])?(int)$data['id']:null;
        if (trim((string)($data['name']??''))==='') throw new \InvalidArgumentException('name is required.');
        if (trim((string)($data['slug']??''))==='') $data['slug']=$this->slug((string)$data['name']);
        $saved=$this->repo->saveBrand($data,$id);
        Audit::record('api.brand.save','brand',$saved,'Saved brand via CMS API');
        return ['id'=>$saved,'brand'=>$this->repo->brand($saved)];
    }

    private function saveGuide(array $data): array
    {
        $id=!empty($data['id'])?(int)$data['id']:null;
        if (trim((string)($data['title']??''))==='') throw new \InvalidArgumentException('title is required.');
        if (trim((string)($data['slug']??''))==='') $data['slug']=$this->slug((string)$data['title']);
        $authorId=(int)($data['author_id']??$this->apiAuthorId());
        if($authorId<1) throw new \InvalidArgumentException('No API author is available.');
        $saved=$this->repo->saveGuide($data,$authorId,$id);
        Audit::record('api.guide.save','content',$saved,'Saved buying guide via CMS API');
        return ['id'=>$saved,'guide'=>$this->repo->guide($saved)];
    }

    private function apiAuthorId(): int
    {
        $configured=(int)env('CMS_API_AUTHOR_ID',0);
        if($configured>0) return $configured;
        try{return (int)Database::connection()->query("SELECT id FROM users WHERE active=1 AND role IN ('administrator','editor') ORDER BY id LIMIT 1")->fetchColumn();}
        catch(Throwable){return 0;}
    }

    private function authorized(string $method): bool
    {
        $configured=(string)env('CMS_API_KEY','');
        if(strlen($configured)<24) return false;

        $header='';
        if(isset($_SERVER['HTTP_AUTHORIZATION'])) $header=trim((string)$_SERVER['HTTP_AUTHORIZATION']);
        if($header==='' && function_exists('getallheaders')){
            $headers=getallheaders();
            $header=trim((string)($headers['Authorization']??$headers['authorization']??''));
        }
        if(str_starts_with($header,'Bearer ')){
            $candidate=substr($header,7);
            if(hash_equals($configured,$candidate)) return true;
        }
        $xKey=trim((string)($_SERVER['HTTP_X_API_KEY']??''));
        if($xKey!=='' && hash_equals($configured,$xKey)) return true;

        if($method==='GET' && (bool)env('CMS_API_ALLOW_QUERY_KEY',false)){
            $candidate=trim((string)($_GET['api_key']??''));
            if($candidate!=='' && hash_equals($configured,$candidate)) return true;
        }
        return false;
    }

    private function jsonBody(): array
    {
        $raw=(string)file_get_contents('php://input');
        if($raw==='') return [];
        $decoded=json_decode($raw,true);
        if(!is_array($decoded)) throw new \InvalidArgumentException('JSON body is required.');
        return $decoded;
    }

    private function getCommandPayload(): array
    {
        $op=trim((string)($_GET['op']??''));
        $requestId=trim((string)($_GET['request_id']??''));
        $dataRaw=(string)($_GET['data']??'');
        $data=[];
        if($dataRaw!==''){
            $decoded=$this->base64UrlDecode($dataRaw);
            $json=json_decode($decoded,true);
            if(!is_array($json)) throw new \InvalidArgumentException('data must be base64url-encoded JSON.');
            $data=$json;
        }
        return ['op'=>$op,'request_id'=>$requestId,'data'=>$data];
    }

    private function idempotencyGet(string $requestId): ?array
    {
        try{
            $stmt=Database::connection()->prepare('SELECT setting_value FROM settings WHERE setting_key=:k LIMIT 1');
            $stmt->execute(['k'=>'api.idempotency.'.hash('sha256',$requestId)]);
            $raw=$stmt->fetchColumn();
            if(!$raw) return null;
            $decoded=json_decode((string)$raw,true);
            return is_array($decoded)?$decoded:null;
        }catch(Throwable){return null;}
    }

    private function idempotencyPut(string $requestId,array $response): void
    {
        try{
            $key='api.idempotency.'.hash('sha256',$requestId);
            $stmt=Database::connection()->prepare(
                'INSERT INTO settings (setting_key,setting_value,encrypted) VALUES (:k,:v,0) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),encrypted=0'
            );
            $stmt->execute(['k'=>$key,'v'=>json_encode($response,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        }catch(Throwable){/* idempotency persistence is best-effort for POST; GET still requires a request id */}
    }

    private function filterRows(array $rows): array {return array_map(fn(array $r)=>$this->filterRow($r),$rows);}
    private function filterRow(array $row): array
    {
        foreach(['password_hash','credential_secret','access_token'] as $blocked) unset($row[$blocked]);
        return $row;
    }

    private function slug(string $value): string
    {
        $value=strtolower(trim($value));
        $value=preg_replace('/[^a-z0-9]+/','-',$value)??'';
        return trim($value,'-') ?: 'item-'.time();
    }

    private function base64UrlDecode(string $value): string
    {
        $value=strtr($value,'-_','+/');
        $pad=strlen($value)%4; if($pad) $value.=str_repeat('=',4-$pad);
        $decoded=base64_decode($value,true);
        if($decoded===false) throw new \InvalidArgumentException('Invalid base64url data.');
        return $decoded;
    }

    private function json(array $payload,int $status=200): never
    {
        http_response_code($status);
        echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        exit;
    }
}
