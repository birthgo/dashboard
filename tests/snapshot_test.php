<?php
if(PHP_SAPI!=='cli'){exit;}
$root=sys_get_temp_dir().'/dashboard-snapshot-'.bin2hex(random_bytes(8));mkdir($root,0700);
$source=$root.'/source.sqlite';$target=$root.'/copy.sqlite';$db=null;$copy=null;
try {
    $db=new PDO('sqlite:'.$source,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=WAL');$db->exec('PRAGMA wal_autocheckpoint=0');
    $db->exec('CREATE TABLE fixture(value TEXT)');$db->exec("INSERT INTO fixture VALUES('committed-wal-value')");
    if(!is_file($source.'-wal')||filesize($source.'-wal')===0)throw new RuntimeException('WAL fixture required');
    $process=proc_open([PHP_BINARY,dirname(__DIR__).'/tools/snapshot-sqlite.php',$source,$target],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Snapshot subprocess failed: '.$error);
    $copy=new PDO('sqlite:'.$target);
    if($copy->query('SELECT value FROM fixture')->fetchColumn()!=='committed-wal-value'||$db->query('SELECT COUNT(*) FROM fixture')->fetchColumn()!=1)throw new RuntimeException('WAL copy/source mismatch');
    echo "PASS consistent snapshot includes committed WAL while source remains open and unchanged\n";
}finally{$copy=null;$db=null;foreach(glob($root.'/*') as $file)if(is_file($file))unlink($file);rmdir($root);}
