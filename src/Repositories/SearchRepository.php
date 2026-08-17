<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class SearchRepository
{
    public function search(string $query,int $page=1,int $perPage=12): array
    {
        $db=Database::connection();
        $page=max(1,$page);
        $perPage=max(4,min(48,$perPage));
        $offset=($page-1)*$perPage;
        $like='%'.$query.'%';

        $countProducts=$db->prepare(
            'SELECT COUNT(*)
             FROM products p
             LEFT JOIN brands b ON b.id=p.brand_id
             LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.active=1 AND (p.title LIKE :q1 OR p.display_title LIKE :q2 OR p.short_description LIKE :q3 OR b.name LIKE :q4 OR c.name LIKE :q5)'
        );
        foreach([':q1',':q2',':q3',':q4',':q5'] as $key)$countProducts->bindValue($key,$like);
        $countProducts->execute();
        $productTotal=(int)$countProducts->fetchColumn();

        $products=$db->prepare(
            'SELECT p.id,p.title,p.display_title,p.slug,p.main_image_url,p.price,p.currency,p.custom_score,p.best_for_label,b.name AS brand_name,c.name AS category_name
             FROM products p
             LEFT JOIN brands b ON b.id=p.brand_id
             LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.active=1 AND (p.title LIKE :q1 OR p.display_title LIKE :q2 OR p.short_description LIKE :q3 OR b.name LIKE :q4 OR c.name LIKE :q5)
             ORDER BY p.custom_score DESC,p.updated_at DESC LIMIT :limit OFFSET :offset'
        );
        foreach([':q1',':q2',':q3',':q4',':q5'] as $key)$products->bindValue($key,$like);
        $products->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $products->bindValue(':offset',$offset,PDO::PARAM_INT);
        $products->execute();

        $categories=$db->prepare(
            'SELECT id,name,slug,description,image_url FROM categories
             WHERE active=1 AND (name LIKE :q1 OR description LIKE :q2)
             ORDER BY sort_order,name LIMIT 12'
        );
        $categories->bindValue(':q1',$like);$categories->bindValue(':q2',$like);$categories->execute();

        $content=$db->prepare(
            "SELECT id,type,title,slug,excerpt,featured_image_url,published_at
             FROM content
             WHERE type IN ('buying_guide','comparison','blog','review')
               AND status IN ('published','scheduled')
               AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP()
               AND (title LIKE :q1 OR excerpt LIKE :q2 OR body LIKE :q3)
             ORDER BY published_at DESC LIMIT 24"
        );
        $content->bindValue(':q1',$like);$content->bindValue(':q2',$like);$content->bindValue(':q3',$like);$content->execute();
        $contentRows=$content->fetchAll(PDO::FETCH_ASSOC);

        $grouped=['guides'=>[],'comparisons'=>[],'articles'=>[],'reviews'=>[]];
        foreach($contentRows as $row){
            match($row['type']){
                'buying_guide'=>$grouped['guides'][]=$row,
                'comparison'=>$grouped['comparisons'][]=$row,
                'blog'=>$grouped['articles'][]=$row,
                'review'=>$grouped['reviews'][]=$row,
                default=>null,
            };
        }

        return [
            'products'=>$products->fetchAll(PDO::FETCH_ASSOC),
            'categories'=>$categories->fetchAll(PDO::FETCH_ASSOC),
            ...$grouped,
            'pagination'=>[
                'page'=>$page,
                'per_page'=>$perPage,
                'product_total'=>$productTotal,
                'pages'=>max(1,(int)ceil($productTotal/$perPage)),
            ],
        ];
    }

    public function suggestions(string $query,int $limit=8): array
    {
        $query=trim($query);
        if(mb_strlen($query)<2)return [];
        $limit=max(3,min(12,$limit));
        $like='%'.$query.'%';
        $db=Database::connection();
        $stmt=$db->prepare(
            "SELECT label,url,type FROM (
                SELECT COALESCE(display_title,title) AS label,CONCAT('/product/',slug) AS url,'Product' AS type,updated_at AS sort_date
                FROM products WHERE active=1 AND (title LIKE :p1 OR display_title LIKE :p2)
                UNION ALL
                SELECT name AS label,CONCAT('/category/',slug) AS url,'Category' AS type,updated_at AS sort_date
                FROM categories WHERE active=1 AND name LIKE :c1
                UNION ALL
                SELECT title AS label,
                    CASE type WHEN 'buying_guide' THEN CONCAT('/guide/',slug) WHEN 'comparison' THEN CONCAT('/compare/',slug) WHEN 'review' THEN CONCAT('/review/',slug) ELSE CONCAT('/blog/',slug) END AS url,
                    CASE type WHEN 'buying_guide' THEN 'Buying guide' WHEN 'comparison' THEN 'Comparison' WHEN 'review' THEN 'Review' ELSE 'Article' END AS type,
                    updated_at AS sort_date
                FROM content
                WHERE type IN ('buying_guide','comparison','review','blog') AND status IN ('published','scheduled') AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP() AND title LIKE :t1
             ) suggestions
             ORDER BY sort_date DESC LIMIT :limit"
        );
        $stmt->bindValue(':p1',$like);$stmt->bindValue(':p2',$like);$stmt->bindValue(':c1',$like);$stmt->bindValue(':t1',$like);$stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
