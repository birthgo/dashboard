<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/runtime.php';
try {
    $c=DashboardRuntime::config();echo "ENV: readable and valid\n";
    $db=new PDO('mysql:host='.$c['DB_HOST'].';port='.$c['DB_PORT'].';dbname='.$c['DB_NAME'].';charset=utf8mb4',$c['DB_USER'],$c['DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
    foreach(['oapp','patient','doctor','ovst','kskdepartment','spclty','dtmain'] as $table){$db->query('SELECT 1 FROM '.$table.' LIMIT 0');echo 'SELECT '.$table.": OK\n";}
    $sqlite=DashboardRuntime::portalDatabase();
    if($sqlite->query('PRAGMA quick_check')->fetchColumn()!=='ok')throw new RuntimeException('SQLite integrity failed');
    echo "PORTAL_SQLITE: existing schema and integrity OK; no seed performed\n";
    DashboardRuntime::readAppointments(DashboardRuntime::privatePath('appointments.json'));
    echo "PRIVATE_APPOINTMENTS: valid JSON (patient values hidden)\n";
    if(!is_writable(DashboardRuntime::privatePath('sessions')))throw new RuntimeException('Session directory unavailable');
    echo "SESSION_DIR: writable\n";
    echo "No exporter, push, cron installation, password change or patient output performed\n";
}catch(Throwable $e){$code=$e instanceof PDOException?(int)($e->errorInfo[1]??0):0;fwrite(STDERR,'CHECK_FAILED type='.get_class($e).' driver='.$code.PHP_EOL);exit(1);}
