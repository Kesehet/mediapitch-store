<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class AnalyticsRepository
{
    public function report(string $from,string $to): array
    {
        $db=Database::connection();
        $params=['from'=>$from.' 00:00:00','to'=>$to.' 23:59:59'];

        $total=$db->prepare('SELECT COUNT(*) FROM affiliate_clicks WHERE clicked_at BETWEEN :from AND :to');
        $total->execute($params);

        $daily=$db->prepare('SELECT DATE(clicked_at) AS day,COUNT(*) AS clicks FROM affiliate_clicks WHERE clicked_at BETWEEN :from AND :to GROUP BY DATE(clicked_at) ORDER BY day');
        $daily->execute($params);

        $products=$db->prepare('SELECT p.id,COALESCE(p.display_title,p.title) AS title,p.slug,COUNT(*) AS clicks FROM affiliate_clicks ac JOIN products p ON p.id=ac.product_id WHERE ac.clicked_at BETWEEN :from AND :to GROUP BY p.id,p.display_title,p.title,p.slug ORDER BY clicks DESC LIMIT 25');
        $products->execute($params);

        $content=$db->prepare('SELECT c.id,c.type,c.title,c.slug,COUNT(*) AS clicks FROM affiliate_clicks ac JOIN content c ON c.id=ac.content_id WHERE ac.clicked_at BETWEEN :from AND :to GROUP BY c.id,c.type,c.title,c.slug ORDER BY clicks DESC LIMIT 25');
        $content->execute($params);

        $cta=$db->prepare("SELECT COALESCE(NULLIF(cta_location,''),'unknown') AS label,COUNT(*) AS clicks FROM affiliate_clicks WHERE clicked_at BETWEEN :from AND :to GROUP BY label ORDER BY clicks DESC LIMIT 25");
        $cta->execute($params);

        $campaigns=$db->prepare("SELECT COALESCE(NULLIF(campaign,''),'none') AS label,COUNT(*) AS clicks FROM affiliate_clicks WHERE clicked_at BETWEEN :from AND :to GROUP BY label ORDER BY clicks DESC LIMIT 25");
        $campaigns->execute($params);

        $ranks=$db->prepare("SELECT COALESCE(CAST(rank_position AS CHAR),'not ranked') AS label,COUNT(*) AS clicks FROM affiliate_clicks WHERE clicked_at BETWEEN :from AND :to GROUP BY rank_position ORDER BY clicks DESC LIMIT 25");
        $ranks->execute($params);

        return [
            'total'=>(int)$total->fetchColumn(),
            'daily'=>$daily->fetchAll(PDO::FETCH_ASSOC),
            'products'=>$products->fetchAll(PDO::FETCH_ASSOC),
            'content'=>$content->fetchAll(PDO::FETCH_ASSOC),
            'cta'=>$cta->fetchAll(PDO::FETCH_ASSOC),
            'campaigns'=>$campaigns->fetchAll(PDO::FETCH_ASSOC),
            'ranks'=>$ranks->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function exportRows(string $from,string $to): array
    {
        $stmt=Database::connection()->prepare(
            'SELECT ac.clicked_at,COALESCE(p.display_title,p.title) AS product,c.title AS content,c.type AS content_type,
                    ac.rank_position,ac.cta_location,ac.campaign,ac.referring_url,ac.user_agent
             FROM affiliate_clicks ac JOIN products p ON p.id=ac.product_id LEFT JOIN content c ON c.id=ac.content_id
             WHERE ac.clicked_at BETWEEN :from AND :to ORDER BY ac.clicked_at DESC'
        );
        $stmt->execute(['from'=>$from.' 00:00:00','to'=>$to.' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
