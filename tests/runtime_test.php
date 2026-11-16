<?php
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/runtime.php';
function expect($value,$label){if(!$value)throw new RuntimeException($label);}
$root=sys_get_temp_dir().'/dashboard-fixture-'.bin2hex(random_bytes(8));
if(!mkdir($root,0700))throw new RuntimeException('Fixture temp unavailable');
$property=new ReflectionProperty(DashboardRuntime::class,'values');$property->setValue(null,['DASHBOARD_PRIVATE_DIR'=>$root]);
try {
    $rejected=false;try{DashboardRuntime::portalDatabase();}catch(Throwable $e){$rejected=true;}
    expect($rejected&&!file_exists($root.'/portal_users.sqlite'),'missing database must not be created/seeded');
    $db=new PDO('sqlite:'.$root.'/portal_users.sqlite');
    $db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,password TEXT,doctor_code TEXT,display_name TEXT,must_change_pwd INTEGER,created_at TEXT); CREATE TABLE login_attempts(id INTEGER PRIMARY KEY,username TEXT,ip TEXT,success INT,attempted_at TEXT DEFAULT (datetime('now','localtime')))");
    $hash=password_hash('fixture-secret',PASSWORD_BCRYPT);
    $s=$db->prepare("INSERT INTO users VALUES(1,'fixture',?,'DOC_A','Mock',0,'2026-01-01')");$s->execute([$hash]);
    $before=file_get_contents($root.'/portal_users.sqlite');
    $existing=DashboardRuntime::portalDatabase();
    expect($existing->query('SELECT password FROM users')->fetchColumn()===$hash,'existing hash preserved');
    expect($existing->query('SELECT must_change_pwd FROM users')->fetchColumn()==0,'existing flag preserved');
    expect(file_get_contents($root.'/portal_users.sqlite')===$before,'opening cannot change database bytes');
    $day=date('Y-m-d');
    DashboardRuntime::writeAppointments(['generated_at'=>'fixture','appointments'=>['DOC_A'=>[$day=>[['hn'=>'MOCK_A']]],'DOC_B'=>[$day=>[['hn'=>'MOCK_B']]]]]);
    expect(DashboardRuntime::readAppointments($root.'/appointments.json')['appointments']['DOC_A'][$day][0]['hn']==='MOCK_A','private JSON roundtrip');
    // Existing auth and authenticated API execute against fixtures only, never real accounts.
    require dirname(__DIR__).'/portal/auth.php';
    $_SERVER['REMOTE_ADDR']='fixture';
    expect(authenticate('fixture','wrong')===null,'wrong password rejected');
    expect(authenticate('fixture','fixture-secret')['doctor_code']==='DOC_A','copied hash authenticates');
    $db->exec("UPDATE login_attempts SET attempted_at=datetime('now','localtime','-2 minutes') WHERE success=0");
    $db->exec("UPDATE login_attempts SET attempted_at=datetime('now','localtime','-1 minute') WHERE success=1");
    for($i=0;$i<5;$i++)expect(authenticate('fixture','wrong')===null,'failed attempt recorded');
    $locked=false;try{authenticate('fixture','fixture-secret');}catch(LockedOutException $e){$locked=true;}
    expect($locked,'existing rate-limit policy retained');
    $_SESSION=['portal_user'=>['username'=>'fixture','doctor_code'=>'DOC_A','must_change_pwd'=>0]];
    $_GET=['date'=>$day];$_SERVER['SCRIPT_NAME']='/portal/api/appointments.php';
    ob_start();require dirname(__DIR__).'/portal/api/appointments.php';$json=ob_get_clean();
    $response=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    expect($response['doctor_code']==='DOC_A'&&count($response['appointments'])===1&&$response['appointments'][0]['hn']==='MOCK_A','API can only return current doctor data');
    expect(!str_contains($json,'MOCK_B'),'other doctor never returned');
    file_put_contents($root.'/appointments.json','broken');
    $rejected=false;try{DashboardRuntime::readAppointments($root.'/appointments.json');}catch(Throwable $e){$rejected=true;}
    expect($rejected,'corrupt JSON cannot become empty data');
    echo "PASS missing SQLite fail-closed, old hash/flags/bytes retained, authentication/rate-limit, private atomic JSON and doctor isolation\n";
} finally {
    $existing=null;$db=null;$s=null;(new ReflectionProperty(DashboardRuntime::class,'portal'))->setValue(null,null);
    foreach(glob($root.'/*') as $file)if(is_file($file))unlink($file);
    rmdir($root);
}
