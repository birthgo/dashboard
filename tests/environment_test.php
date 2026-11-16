<?php
require dirname(__DIR__).'/environment.php';
$base="APP_ENV=production\nDB_HOST=fixture\nDB_NAME=fixture\nDB_USER=fixture\nDB_PASS='literal$#value'\nDASHBOARD_PRIVATE_DIR=/var/lib/dashboard\n";
$v=DashboardEnvironment::parse($base);
if($v['DB_PASS']!=='literal$#value'||$v['DB_PORT']!==3306)throw new RuntimeException('Parser failed');
foreach([$base."DB_PORT=0\n",$base."DB_USER=duplicate\n",str_replace('DB_PASS=\'literal$#value\'','DB_PASS=CHANGE_ME',$base)] as $bad){$failed=false;try{DashboardEnvironment::parse($bad);}catch(Throwable $e){$failed=true;}if(!$failed)throw new RuntimeException('Invalid env accepted');}
$failed=false;try{DashboardEnvironment::load(dirname(__DIR__).'/.env');}catch(Throwable $e){$failed=true;}if(!$failed)throw new RuntimeException('Webroot path accepted');
echo "PASS external ENV literal password, required keys, ports, duplicates and no webroot fallback\n";
