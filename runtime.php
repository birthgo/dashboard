<?php
declare(strict_types=1);
require_once __DIR__.'/environment.php';
final class DashboardRuntime {
    private static ?array $values=null;
    private static ?PDO $portal=null;
    public static function config(): array {return self::$values??=DashboardEnvironment::load();}
    public static function privatePath(string $file): string {
        if(!in_array($file,['portal_users.sqlite','appointments.json','sessions'],true))throw new RuntimeException('Invalid private file');
        $dir=self::config()['DASHBOARD_PRIVATE_DIR']??'';
        $real=$dir!==''?realpath($dir):false;
        if(!$real||!is_dir($real))throw new RuntimeException('Private dashboard directory unavailable');
        $normal=static fn($p)=>strtolower(str_replace('\\','/',$p));
        foreach([dirname(__DIR__),$_SERVER['DOCUMENT_ROOT']??''] as $root){$root=$root!==''?realpath($root):false;if($root&&str_starts_with($normal($real).'/',rtrim($normal($root),'/').'/'))throw new RuntimeException('Private data must be outside webroot');}
        $path=$real.DIRECTORY_SEPARATOR.$file;
        if(is_link($path))throw new RuntimeException('Private file symlink not permitted');
        return $path;
    }
    public static function startSession(): void {
        $dir=self::privatePath('sessions');
        if(!is_dir($dir)||!is_writable($dir))throw new RuntimeException('Private session directory unavailable');
        if(session_status()!==PHP_SESSION_NONE)throw new RuntimeException('Unexpected existing session');
        session_save_path($dir);
        session_name('DENTAL_DASHBOARD');
        session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','path'=>'/']);
        if(!session_start())throw new RuntimeException('Session unavailable');
    }
    public static function portalDatabase(): PDO {
        if(self::$portal)return self::$portal;
        $path=self::privatePath('portal_users.sqlite');
        if(!is_file($path)||filesize($path)===0||!is_readable($path)||!is_writable($path)||!is_writable(dirname($path)))throw new RuntimeException('Existing private portal database required; no auto-seed');
        $connection=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
        // Verify the copied schema before any operation. Never CREATE/ALTER/reset accounts.
        $connection->query('SELECT id,username,password,doctor_code,display_name,must_change_pwd,created_at FROM users LIMIT 0');
        $connection->query('SELECT id,username,ip,success,attempted_at FROM login_attempts LIMIT 0');
        return self::$portal=$connection;
    }
    public static function readAppointments(string $path): array {
        $text=@file_get_contents($path);
        if($text===false)throw new RuntimeException('Private appointments unavailable');
        $value=json_decode($text,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($value)||!isset($value['appointments'])||!is_array($value['appointments']))throw new RuntimeException('Invalid appointments format');
        return $value;
    }
    public static function writeAppointments(array $value): void {
        $path=self::privatePath('appointments.json');
        $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        $temp=tempnam(dirname($path),'.appointments-');
        if($temp===false)throw new RuntimeException('Private output unavailable');
        try{if(!chmod($temp,0600)||file_put_contents($temp,$json)===false||!rename($temp,$path))throw new RuntimeException('Private output failed');}
        finally{if(is_file($temp))unlink($temp);}
    }
}
