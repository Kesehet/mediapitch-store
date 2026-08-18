<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class ContentRepository
{
    public function adminPosts(string $type = 'blog'): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id,c.title,c.slug,c.status,c.published_at,c.updated_at,cat.name AS category_name,u.name AS author_name
             FROM content c
             LEFT JOIN categories cat ON cat.id=c.category_id
             LEFT JOIN users u ON u.id=c.author_id
             WHERE c.type=:type ORDER BY c.updated_at DESC'
        );
        $stmt->execute(['type'=>$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminPost(?int $id, string $type = 'blog'): ?array
    {
        if (!$id) return null;
        $stmt = Database::connection()->prepare('SELECT * FROM content WHERE id=:id AND type=:type LIMIT 1');
        $stmt->execute(['id'=>$id,'type'=>$type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)return null;
        if($type==='blog')$row['tags']=implode(', ',array_column($this->tagsForContent($id),'name'));
        return $row;
    }

    public function savePost(array $data, int $authorId, ?int $id = null, string $type = 'blog'): int
    {
        $db = Database::connection();
        $status = in_array(($data['status'] ?? 'draft'), ['draft','scheduled','published'], true) ? (string)$data['status'] : 'draft';
        $publishedAt = !empty($data['published_at']) ? date('Y-m-d H:i:s', strtotime((string)$data['published_at'])) : null;
        if ($status === 'published' && !$publishedAt) $publishedAt = gmdate('Y-m-d H:i:s');
        if ($status === 'scheduled' && !$publishedAt) $status = 'draft';

        $params = [
            'category_id'=>!empty($data['category_id']) ? (int)$data['category_id'] : null,
            'author_id'=>$authorId,
            'title'=>trim((string)($data['title'] ?? '')),
            'slug'=>trim((string)($data['slug'] ?? '')),
            'excerpt'=>trim((string)($data['excerpt'] ?? '')) ?: null,
            'body'=>trim((string)($data['body'] ?? '')) ?: null,
            'featured_image_url'=>trim((string)($data['featured_image_url'] ?? '')) ?: null,
            'seo_title'=>trim((string)($data['seo_title'] ?? '')) ?: null,
            'meta_description'=>trim((string)($data['meta_description'] ?? '')) ?: null,
            'canonical_url'=>trim((string)($data['canonical_url'] ?? '')) ?: null,
            'robots_index'=>!empty($data['robots_index']) ? 1 : 0,
            'status'=>$status,
            'published_at'=>$publishedAt,
        ];

        $db->beginTransaction();
        try{
            if ($id) {
                $params['id']=$id;$params['type']=$type;
                $stmt=$db->prepare(
                    'UPDATE content SET category_id=:category_id,author_id=:author_id,title=:title,slug=:slug,excerpt=:excerpt,body=:body,
                     featured_image_url=:featured_image_url,seo_title=:seo_title,meta_description=:meta_description,canonical_url=:canonical_url,
                     robots_index=:robots_index,status=:status,published_at=:published_at WHERE id=:id AND type=:type'
                );
                $stmt->execute($params);
            }else{
                $params['type']=$type;
                $stmt=$db->prepare(
                    'INSERT INTO content (type,category_id,author_id,title,slug,excerpt,body,featured_image_url,seo_title,meta_description,canonical_url,robots_index,status,published_at)
                     VALUES (:type,:category_id,:author_id,:title,:slug,:excerpt,:body,:featured_image_url,:seo_title,:meta_description,:canonical_url,:robots_index,:status,:published_at)'
                );
                $stmt->execute($params);$id=(int)$db->lastInsertId();
            }
            if($type==='blog')$this->syncTags((int)$id,(string)($data['tags']??''));
            $db->commit();
            return (int)$id;
        }catch(\Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }

    public function publishedPosts(int $limit = 20, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.id,c.title,c.slug,c.excerpt,c.body,c.featured_image_url,c.published_at,c.seo_title,c.meta_description,
                    cat.name AS category_name,cat.slug AS category_slug,u.name AS author_name
             FROM content c
             LEFT JOIN categories cat ON cat.id=c.category_id
             LEFT JOIN users u ON u.id=c.author_id
             WHERE c.type='blog' AND c.status IN ('published','scheduled') AND c.robots_index=1
               AND c.published_at IS NOT NULL AND c.published_at<=UTC_TIMESTAMP()
             ORDER BY c.published_at DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function publishedPostBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.*,cat.name AS category_name,cat.slug AS category_slug,u.name AS author_name
             FROM content c
             LEFT JOIN categories cat ON cat.id=c.category_id
             LEFT JOIN users u ON u.id=c.author_id
             WHERE c.slug=:slug AND c.type='blog' AND c.status IN ('published','scheduled')
               AND c.published_at IS NOT NULL AND c.published_at<=UTC_TIMESTAMP() LIMIT 1"
        );
        $stmt->execute(['slug'=>$slug]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)return null;
        $row['tags']=$this->tagsForContent((int)$row['id']);
        return $row;
    }

    private function syncTags(int $contentId,string $input): void
    {
        $db=Database::connection();
        $names=array_values(array_unique(array_filter(array_map(static fn($v)=>trim($v),preg_split('/[,\n]+/',$input)?:[]))));
        $names=array_slice($names,0,20);
        $db->prepare('DELETE FROM content_tags WHERE content_id=:id')->execute(['id'=>$contentId]);
        if(!$names)return;
        $find=$db->prepare('SELECT id FROM tags WHERE slug=:slug LIMIT 1');
        $insertTag=$db->prepare('INSERT INTO tags (name,slug) VALUES (:name,:slug)');
        $link=$db->prepare('INSERT IGNORE INTO content_tags (content_id,tag_id) VALUES (:content_id,:tag_id)');
        foreach($names as $name){
            $name=function_exists('mb_substr')?mb_substr($name,0,100):substr($name,0,100);
            $slug=$this->tagSlug($name);if($slug==='')continue;
            $find->execute(['slug'=>$slug]);$tagId=(int)($find->fetchColumn()?:0);
            if(!$tagId){$insertTag->execute(['name'=>$name,'slug'=>$slug]);$tagId=(int)$db->lastInsertId();}
            $link->execute(['content_id'=>$contentId,'tag_id'=>$tagId]);
        }
    }

    private function tagsForContent(int $contentId): array
    {
        try{
            $stmt=Database::connection()->prepare('SELECT t.id,t.name,t.slug FROM tags t JOIN content_tags ct ON ct.tag_id=t.id WHERE ct.content_id=:id ORDER BY t.name');
            $stmt->execute(['id'=>$contentId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch(\Throwable){return [];}
    }

    private function tagSlug(string $value): string
    {
        if(function_exists('iconv')){$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);if(is_string($ascii)&&$ascii!=='')$value=$ascii;}
        return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($value))??'','-');
    }
}
