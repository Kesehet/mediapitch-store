<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\Database;
use MediaPitch\Services\ProductOverrides;
use PDO;

final class ProductSyncAdminController
{
    public function handle(string $method,string $path): bool
    {
        if($method!=='GET' || !preg_match('#^/admin/products/(\d+)/sync-status$#',$path,$m))return false;
        if(!Auth::check()){http_response_code(401);$this->json(['error'=>'Authentication required.']);return true;}
        if(!Auth::canManageProducts()){http_response_code(403);$this->json(['error'=>'Forbidden.']);return true;}

        $stmt=Database::connection()->prepare('SELECT id,source,asin,api_marketplace,last_synced_at,manual_override_json FROM products WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>(int)$m[1]]);
        $product=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$product){http_response_code(404);$this->json(['error'=>'Product not found.']);return true;}

        $this->json([
            'id'=>(int)$product['id'],
            'source'=>(string)$product['source'],
            'asin'=>(string)($product['asin']??''),
            'marketplace'=>(string)($product['api_marketplace']??''),
            'last_synced_at'=>$product['last_synced_at']?:null,
            'overrides'=>(new ProductOverrides())->forProduct($product),
        ]);
        return true;
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
}
