<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$files=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src'));
foreach($iterator as $file){
    if(!$file->isFile()||$file->getExtension()!=='php')continue;
    $files[]=$file->getPathname();
}

$failures=[];
foreach($files as $file){
    $source=(string)file_get_contents($file);
    if($source==='')continue;

    if(!preg_match_all('/->prepare\(\s*([\'\"])(.*?)\1\s*\)/s',$source,$matches,PREG_SET_ORDER))continue;
    foreach($matches as $match){
        $sql=$match[2];
        preg_match_all('/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/',$sql,$params);
        $names=$params[1]??[];
        if(!$names)continue;
        $counts=array_count_values($names);
        foreach($counts as $name=>$count){
            if($count>1){
                $failures[]=str_replace($root.'/','',$file).": duplicate named placeholder :{$name} appears {$count} times in one prepared statement";
            }
        }
    }
}

if($failures){
    fwrite(STDERR,"Duplicate PDO named placeholders found:\n - ".implode("\n - ",$failures)."\n");
    exit(1);
}

fwrite(STDOUT,"PASS no duplicate PDO named placeholders found in src/ prepared SQL.\n");
