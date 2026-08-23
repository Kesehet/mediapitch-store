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
            return nl2br(htmlspecialchars(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
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
                    if($href!==''&&!preg_match('#^(https?://|//|/|#|mailto:|tel:)#i',$href))$child->removeAttribute('href');
                    if(strtolower($child->getAttribute('target'))==='_blank')$child->setAttribute('rel','noopener noreferrer');
                }
                self::cleanNode($child);
            }
        }
    }
}
