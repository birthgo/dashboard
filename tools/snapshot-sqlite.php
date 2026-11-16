<?php
// Run by the owner on .52 against the existing SQLite. Never changes the source.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(count($argv)!==3){fwrite(STDERR,"Usage: php tools/snapshot-sqlite.php SOURCE DESTINATION\n");exit(1);}
[$script,$source,$destination]=$argv;
if(!is_file($source)||file_exists($destination)||!is_dir(dirname($destination))){fwrite(STDERR,"Source missing, destination exists or destination directory unavailable; no overwrite\n");exit(1);}
$parent=strtolower(str_replace('\\','/',realpath(dirname($destination))));
foreach(['/var/www/html','C:/xampp/htdocs',$_SERVER['DOCUMENT_ROOT']??''] as $root){$root=$root!==''?realpath($root):false;if($root&&str_starts_with($parent.'/',rtrim(strtolower(str_replace('\\','/',$root)),'/').'/')){fwrite(STDERR,"Snapshot destination must be outside webroot\n");exit(1);}}
$src=null;$dst=null;$oldMask=umask(0077);
try {
    if(class_exists('SQLite3')){
        $src=new SQLite3($source,SQLITE3_OPEN_READONLY);
        $src->busyTimeout(10000);
        $dst=new SQLite3($destination,SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
        if(!$src->backup($dst)||$dst->querySingle('PRAGMA quick_check')!=='ok')throw new RuntimeException('Snapshot failed');
    }else{
        $uri=str_replace(['%','?','#'],['%25','%3F','%23'],str_replace('\\','/',$source));
        $src=new PDO('sqlite:file:'.$uri.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $src->exec('PRAGMA busy_timeout=10000');
        if(version_compare($src->query('SELECT sqlite_version()')->fetchColumn(),'3.27.0','<'))throw new RuntimeException('Need SQLite3 backup extension or PDO SQLite 3.27+');
        $copy=$src->prepare('VACUUM INTO ?');$copy->execute([$destination]);$copy=null;
        $dst=new PDO('sqlite:'.$destination,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if($dst->query('PRAGMA quick_check')->fetchColumn()!=='ok')throw new RuntimeException('Snapshot integrity failed');
    }
    if(!chmod($destination,0600))throw new RuntimeException('Snapshot permissions failed');
    echo "Consistent SQLite snapshot created, including committed WAL data; source unchanged\n";
}catch(Throwable $e){fwrite(STDERR,'Snapshot failed type='.get_class($e)."; do not deploy this destination\n");exit(1);}
finally{if($dst instanceof SQLite3)$dst->close();if($src instanceof SQLite3)$src->close();$src=null;$dst=null;umask($oldMask);}
