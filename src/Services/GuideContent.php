<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use DOMDocument;
use DOMElement;
use MediaPitch\Core\Html;

final class GuideContent
{
    public function render(?string $body): array
    {
        $html=Html::sanitize($body);
        if($html==='' || !class_exists(DOMDocument::class))return ['html'=>$html,'toc'=>[]];

        $dom=new DOMDocument('1.0','UTF-8');
        $previous=libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="guide-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();libxml_use_internal_errors($previous);
        $root=$dom->getElementById('guide-root');
        if(!$root)return ['html'=>$html,'toc'=>[]];

        $toc=[];$used=[];
        foreach(iterator_to_array($root->getElementsByTagName('*')) as $node){
            if(!$node instanceof DOMElement || !in_array(strtolower($node->tagName),['h2','h3'],true))continue;
            $label=trim($node->textContent);
            if($label==='')continue;
            $base=$this->slug($label)?:'section';$id=$base;$n=2;
            while(isset($used[$id]))$id=$base.'-'.$n++;
            $used[$id]=true;$node->setAttribute('id',$id);
            $toc[]=['id'=>$id,'label'=>$label,'level'=>strtolower($node->tagName)==='h3'?3:2];
        }

        $out='';foreach(iterator_to_array($root->childNodes) as $child)$out.=$dom->saveHTML($child);
        return ['html'=>$out,'toc'=>$toc];
    }

    private function slug(string $value): string
    {
        if(function_exists('iconv')){$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);if(is_string($ascii)&&$ascii!=='')$value=$ascii;}
        return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($value))??'','-');
    }
}
