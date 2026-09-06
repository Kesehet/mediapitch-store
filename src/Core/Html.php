<?php

declare(strict_types=1);

namespace MediaPitch\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

final class Html
{
    private const ALLOWED_TAGS = ['p','br','strong','b','em','i','u','s','h2','h3','h4','h5','ul','ol','li','blockquote','a','code','pre','hr','table','thead','tbody','tr','th','td'];
    private const GLOBAL_ATTRIBUTES = ['class'];
    private const TAG_ATTRIBUTES = [
        'a'=>['href','title','target','rel'],
        'th'=>['scope','colspan','rowspan'],
        'td'=>['colspan','rowspan'],
    ];

    public static function sanitize(?string $html): string
    {
        $html=trim((string)$html);
        if($html==='')return '';
        if(!class_exists(DOMDocument::class)){
            return self::sanitizeWithoutDom($html);
        }

        $dom=new DOMDocument('1.0','UTF-8');
        $previous=libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="mp-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root=$dom->getElementById('mp-root');
        if(!$root)return '';
        self::cleanNode($root);

        $out='';
        foreach(iterator_to_array($root->childNodes) as $child){
            $out.=$dom->saveHTML($child);
        }
        return $out;
    }

    /**
     * Safe fallback for shared hosts where ext-dom is unavailable.
     * Keep editorial formatting and hyperlinks instead of flattening everything
     * to plain text, while rebuilding opening tags from an attribute allow-list.
     */
    private static function sanitizeWithoutDom(string $html): string
    {
        // Remove script/style blocks completely before allowing editorial tags.
        $html=(string)preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is','',$html);
        $allowed='<'.implode('><',self::ALLOWED_TAGS).'>';
        $html=strip_tags($html,$allowed);

        return (string)preg_replace_callback('/<([a-z0-9]+)\b([^>]*)>/i',static function(array $m): string {
            $tag=strtolower($m[1]);
            if(!in_array($tag,self::ALLOWED_TAGS,true))return '';
            $raw=(string)($m[2]??'');

            // Void-ish editorial tags do not need attributes here.
            if(in_array($tag,['br','hr'],true))return '<'.$tag.'>';

            $attrs=[];
            if(in_array('class',self::GLOBAL_ATTRIBUTES,true)){
                $class=self::extractAttribute($raw,'class');
                if($class!==null&&preg_match('/^[A-Za-z0-9_\-\s]{1,200}$/',$class))$attrs['class']=$class;
            }

            if($tag==='a'){
                $href=self::extractAttribute($raw,'href');
                if($href!==null&&self::isSafeHref($href))$attrs['href']=$href;
                $title=self::extractAttribute($raw,'title');
                if($title!==null&&$title!=='')$attrs['title']=$title;
                $target=strtolower((string)(self::extractAttribute($raw,'target')??''));
                if($target==='_blank'){
                    $attrs['target']='_blank';
                    $attrs['rel']='noopener noreferrer';
                }
            }elseif($tag==='th'){
                foreach(['scope','colspan','rowspan'] as $name){
                    $value=self::extractAttribute($raw,$name);
                    if($value!==null&&preg_match('/^[A-Za-z0-9_-]{1,20}$/',$value))$attrs[$name]=$value;
                }
            }elseif($tag==='td'){
                foreach(['colspan','rowspan'] as $name){
                    $value=self::extractAttribute($raw,$name);
                    if($value!==null&&preg_match('/^\d{1,3}$/',$value))$attrs[$name]=$value;
                }
            }

            $out='<'.$tag;
            foreach($attrs as $name=>$value)$out.=' '.$name.'="'.htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';
            return $out.'>';
        },$html);
    }

    private static function extractAttribute(string $raw,string $name): ?string
    {
        $quoted='/\b'.preg_quote($name,'/').'\s*=\s*(["\'])(.*?)\1/is';
        if(preg_match($quoted,$raw,$m))return trim(html_entity_decode((string)$m[2],ENT_QUOTES|ENT_HTML5,'UTF-8'));
        $unquoted='/\b'.preg_quote($name,'/').'\s*=\s*([^\s>]+)/i';
        if(preg_match($unquoted,$raw,$m))return trim(html_entity_decode((string)$m[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));
        return null;
    }

    private static function isSafeHref(string $href): bool
    {
        $href=trim($href);
        return $href!==''&&preg_match('#^(https?://|//|/|#|mailto:|tel:)#i',$href)===1;
    }

    private static function cleanNode(DOMNode $node): void
    {
        foreach(iterator_to_array($node->childNodes) as $child){
            if($child instanceof DOMElement){
                $tag=strtolower($child->tagName);
                if(!in_array($tag,self::ALLOWED_TAGS,true)){
                    while($child->firstChild)$node->insertBefore($child->firstChild,$child);
                    $node->removeChild($child);
                    continue;
                }

                $allowed=array_merge(self::GLOBAL_ATTRIBUTES,self::TAG_ATTRIBUTES[$tag]??[]);
                foreach(iterator_to_array($child->attributes) as $attribute){
                    $name=strtolower($attribute->name);
                    if(str_starts_with($name,'on')||!in_array($name,$allowed,true)){
                        $child->removeAttribute($attribute->name);
                    }
                }
                if($tag==='a'){
                    $href=trim($child->getAttribute('href'));
                    // Keep normal web, root-relative, fragment, email and phone links.
                    // Reject script/data/file style schemes even if pasted from rich editors.
                    if($href!==''&&!self::isSafeHref($href))$child->removeAttribute('href');
                    if(strtolower($child->getAttribute('target'))==='_blank')$child->setAttribute('rel','noopener noreferrer');
                }
                self::cleanNode($child);
            }
        }
    }
}
