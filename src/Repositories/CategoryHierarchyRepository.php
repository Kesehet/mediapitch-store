<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class CategoryHierarchyRepository
{
    public function ancestors(int $categoryId,int $maxDepth=12): array
    {
        $db=Database::connection();
        $stmt=$db->prepare('SELECT id,parent_id,name,slug FROM categories WHERE id=:id LIMIT 1');
        $chain=[];$seen=[];$current=$categoryId;
        while($current>0 && count($chain)<$maxDepth && !isset($seen[$current])){
            $seen[$current]=true;
            $stmt->execute(['id'=>$current]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row)break;
            array_unshift($chain,$row);
            $current=(int)($row['parent_id']??0);
        }
        return $chain;
    }
}
