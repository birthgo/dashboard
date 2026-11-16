// Loopback-only fixtures. No real ENV, account, patient, DB server or GitHub requests.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict');
const {spawn,spawnSync}=require('node:child_process');
const php=process.env.PHP_BINARY||'C:/xampp/php/php.exe';
const dir=fs.mkdtempSync(path.join(process.env.DASHBOARD_TEST_TMP||os.tmpdir(),'dashboard-http-'));
const project=path.resolve(__dirname,'..').replaceAll('\\','/');
let server;
const setup=`<?php
$root=$argv[1];mkdir($root.'/sessions',0700);
$db=new PDO('sqlite:'.$root.'/portal_users.sqlite');
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,password TEXT,doctor_code TEXT,display_name TEXT,must_change_pwd INTEGER,created_at TEXT); CREATE TABLE login_attempts(id INTEGER PRIMARY KEY,username TEXT,ip TEXT,success INT,attempted_at TEXT)');
$insert=$db->prepare('INSERT INTO users VALUES(?,?,?,?,?,?,?)');
foreach([['alpha','DOC_A',0],['beta','DOC_B',0],['forced','DOC_C',1]] as $i=>$user)$insert->execute([$i+1,$user[0],password_hash('fixture-password',PASSWORD_BCRYPT),$user[1],'Mock',$user[2],'2026-01-01']);
date_default_timezone_set('Asia/Bangkok');$day=date('Y-m-d');
file_put_contents($root.'/appointments.json',json_encode(['appointments'=>['DOC_A'=>[$day=>[['hn'=>'MOCK_A']]],'DOC_B'=>[$day=>[['hn'=>'MOCK_B']]]]]));
echo $day;
`;
const router=`<?php
$project=${JSON.stringify(project)};
require $project.'/runtime.php';
(new ReflectionProperty(DashboardRuntime::class,'values'))->setValue(null,['DASHBOARD_PRIVATE_DIR'=>__DIR__]);
$route=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(!in_array($route,['/portal/login.php','/portal/index.php','/portal/logout.php','/portal/change_password.php','/portal/api/appointments.php'],true)){http_response_code(404);exit;}
$_SERVER['SCRIPT_NAME']=$route;require $project.$route;
`;
(async()=>{
  fs.writeFileSync(path.join(dir,'setup.php'),setup);fs.writeFileSync(path.join(dir,'router.php'),router);
  const fixture=spawnSync(php,[path.join(dir,'setup.php'),dir],{encoding:'utf8',windowsHide:true});assert.equal(fixture.status,0,fixture.stderr);const day=fixture.stdout;
  const port=await new Promise(resolve=>{const socket=net.createServer();socket.listen(0,'127.0.0.1',()=>{const p=socket.address().port;socket.close(()=>resolve(p));});});
  server=spawn(php,['-S','127.0.0.1:'+port,'-t',project,path.join(dir,'router.php')],{cwd:dir,windowsHide:true,stdio:'ignore'});
  const base='http://127.0.0.1:'+port;
  let ready=false;for(let i=0;i<60;i++){try{await fetch(base+'/not-found');ready=true;break;}catch{}await new Promise(r=>setTimeout(r,80));}assert(ready,'Fixture server startup');
  const api=()=>base+'/portal/api/appointments.php?date='+day+'&doctor_code=DOC_B';
  let response=await fetch(api(),{redirect:'manual'});assert.equal(response.status,401);assert.equal((await response.json()).error,'unauthorized');
  async function login(username){
    const landing=await fetch(base+'/portal/login.php');assert.equal(landing.status,200);
    const cookie=landing.headers.get('set-cookie').split(';')[0];assert(cookie.startsWith('DENTAL_DASHBOARD='));
    const result=await fetch(base+'/portal/login.php',{method:'POST',redirect:'manual',headers:{Cookie:cookie,'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({username,password:'fixture-password'})});
    assert.equal(result.status,302);return {cookie,location:result.headers.get('location')};
  }
  const alpha=await login('alpha');assert.equal(alpha.location,'index.php');
  response=await fetch(api(),{headers:{Cookie:alpha.cookie}});assert.equal(response.status,200);let json=await response.json();assert.equal(json.doctor_code,'DOC_A');assert.deepEqual(json.appointments,[{hn:'MOCK_A'}]);
  const beta=await login('beta');response=await fetch(api(),{headers:{Cookie:beta.cookie}});json=await response.json();assert.equal(json.doctor_code,'DOC_B');assert.deepEqual(json.appointments,[{hn:'MOCK_B'}]);
  const forced=await login('forced');assert.equal(forced.location,'change_password.php');response=await fetch(api(),{headers:{Cookie:forced.cookie}});assert.equal(response.status,403);
  await fetch(base+'/portal/logout.php',{headers:{Cookie:alpha.cookie},redirect:'manual'});response=await fetch(api(),{headers:{Cookie:alpha.cookie}});assert.equal(response.status,401);
  console.log('PASS real HTTP login/cookies/private sessions, forced password change, doctor isolation, forged doctor filter ignored and logout denial');
})().catch(e=>{process.exitCode=1;console.error(e);}).finally(async()=>{
  if(server){server.kill();await new Promise(resolve=>{if(server.exitCode!==null)return resolve();server.once('exit',resolve);});}
  // Only this freshly created fixture directory, containing synthetic accounts, is removed.
  fs.rmSync(dir,{recursive:true,force:true});
});
