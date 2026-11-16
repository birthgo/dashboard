<?php
// Internal include/CLI helper. Direct HTTP access must not start a session.
if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
/**
 * portal/auth.php
 * - Existing doctor accounts and mappings come from the private SQLite snapshot.
 * - Existing hashes, force-change flags and lockout history are preserved.
 * - migration requires an existing private SQLite database; never seeds accounts.
 */

require_once dirname(__DIR__) . '/runtime.php';
date_default_timezone_set('Asia/Bangkok');
// Portal keeps its own existing authentication; it does not share an SSO session.
if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function(Throwable $e): void {
        error_log('Dashboard portal unavailable; type='.get_class($e));
        http_response_code(503);
        header('Cache-Control: no-store');
        if(str_contains($_SERVER['SCRIPT_NAME']??'','/api/')){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error'=>'Portal ยังไม่พร้อมใช้งาน กรุณาแจ้งผู้ดูแล'],JSON_UNESCAPED_UNICODE);
        }else echo 'Portal ยังไม่พร้อมใช้งาน กรุณาแจ้งผู้ดูแล';
    });
    try { DashboardRuntime::startSession(); }
    catch (Throwable $e) { http_response_code(503); exit('Portal ยังไม่พร้อมใช้งาน กรุณาแจ้งผู้ดูแล'); }
}
function getPortalDB(): PDO {
    return DashboardRuntime::portalDatabase();
}
// ─── Rate limiting ──────────────────────────────────────────
// ล็อก username+IP ชั่วคราวถ้า login ผิดเกิน MAX_ATTEMPTS ครั้ง ภายใน WINDOW_MINUTES
const MAX_ATTEMPTS   = 5;
const WINDOW_MINUTES  = 15;
const LOCKOUT_MINUTES = 15;

function clientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * คืนจำนวนนาทีที่เหลือถ้ายังถูกล็อกอยู่ (0 = ไม่ถูกล็อก)
 * นับเฉพาะ failed attempts ที่เกิด "หลัง" ครั้งล่าสุดที่ login สำเร็จ (ถ้ามี)
 * เพื่อไม่ให้ผิดมือ 2-3 ครั้งก่อนหน้าที่จบด้วยการ login สำเร็จไปแล้ว มาสะสมรวมกับรอบใหม่
 * คำนวณเวลาทั้งหมดฝั่ง SQLite เพื่อเลี่ยงปัญหา timezone ไม่ตรงกับ PHP DateTime
 */
function getLockoutMinutesRemaining(string $username): int {
    $pdo = getPortalDB();
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS failed_count,
            CAST(
                ROUND(
                    (julianday(MAX(attempted_at), '+' || :lockout || ' minutes')
                     - julianday('now', 'localtime')) * 24 * 60
                ) AS INTEGER
            ) AS minutes_remaining
        FROM login_attempts
        WHERE username = :u
          AND ip = :ip
          AND success = 0
          AND attempted_at >= datetime('now', 'localtime', :window)
          AND attempted_at > COALESCE(
              (SELECT MAX(attempted_at) FROM login_attempts
               WHERE username = :u AND ip = :ip AND success = 1),
              '0000-01-01'
          )
    ");
    $stmt->execute([
        ':u'       => $username,
        ':ip'      => clientIp(),
        ':window'  => '-' . WINDOW_MINUTES . ' minutes',
        ':lockout' => LOCKOUT_MINUTES,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ((int)$row['failed_count'] < MAX_ATTEMPTS) {
        return 0;
    }

    $remaining = (int)$row['minutes_remaining'];
    return $remaining > 0 ? $remaining : 0;
}

function recordLoginAttempt(string $username, bool $success): void {
    $pdo = getPortalDB();
    $pdo->prepare('INSERT INTO login_attempts (username, ip, success) VALUES (:u, :ip, :s)')
        ->execute([':u' => $username, ':ip' => clientIp(), ':s' => $success ? 1 : 0]);
}

/**
 * คืนค่า:
 *  - array ของ user ถ้า login สำเร็จ
 *  - null ถ้า user/password ผิด
 *  - throw LockedOutException ถ้าถูกล็อกจาก rate limit
 */
function authenticate(string $username, string $password): ?array {
    $lockMinutes = getLockoutMinutesRemaining($username);
    if ($lockMinutes > 0) {
        throw new LockedOutException($lockMinutes);
    }

    $pdo  = getPortalDB();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        recordLoginAttempt($username, true);
        return $user;
    }

    recordLoginAttempt($username, false);
    return null;
}

class LockedOutException extends \Exception {
    public int $minutesRemaining;
    public function __construct(int $minutesRemaining) {
        $this->minutesRemaining = $minutesRemaining;
        parent::__construct("Locked out for {$minutesRemaining} more minute(s)");
    }
}

function requireLogin(): void {
    $api = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
    $user = currentUser();
    if (!$user) {
        if ($api) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error'=>'unauthorized']);
            exit;
        }
        header('Location: login.php');
        exit;
    }
    // บังคับเปลี่ยน password ครั้งแรก ก่อนเข้าใช้งานหน้าอื่น
    $currentFile = basename($_SERVER['SCRIPT_NAME']);
    if (!empty($user['must_change_pwd']) && $currentFile !== 'change_password.php') {
        if ($api) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error'=>'password_change_required']);
            exit;
        }
        header('Location: change_password.php');
        exit;
    }
}

function getUserPasswordHash(string $username): ?string {
    $pdo  = getPortalDB();
    $stmt = $pdo->prepare('SELECT password FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['password'] : null;
}

function updateUserPassword(string $username, string $newPassword): void {
    $pdo  = getPortalDB();
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password = ?, must_change_pwd = 0 WHERE username = ?')
        ->execute([$hash, $username]);
}

function currentUser(): ?array {
    if (empty($_SESSION['portal_user'])) {
        return null;
    }
    // sync must_change_pwd ล่าสุดจาก DB เข้า session กัน flag ค้างหลังเปลี่ยน password
    $pdo  = getPortalDB();
    $stmt = $pdo->prepare('SELECT id,username,doctor_code,display_name,must_change_pwd FROM users WHERE username = ?');
    $stmt->execute([$_SESSION['portal_user']['username']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row !== false) {
        $row['must_change_pwd'] = (int)$row['must_change_pwd'];
        $_SESSION['portal_user'] = $row;
    } else {
        unset($_SESSION['portal_user']);
        return null;
    }
    return $_SESSION['portal_user'];
}

/* ---------- CLI ----------
   php portal/auth.php list
   php portal/auth.php passwd <doctor_code> <new_password>
   php portal/auth.php reset  <doctor_code>          ← reset กลับเป็น doctor_code (บังคับเปลี่ยนใหม่อีกครั้ง)
   php portal/auth.php unlock <doctor_code>          ← ปลดล็อก rate-limit ของ username นี้ทุก IP
---------------------------------------------------- */
if (PHP_SAPI === 'cli' && isset($argv[1])) {
    $pdo = getPortalDB();

    if ($argv[1] === 'list') {
        echo str_pad('username', 8) . '  ' . str_pad('doctor_code', 12) . '  ' . str_pad('must_change', 12) . '  display_name' . "\n";
        echo str_repeat('-', 75) . "\n";
        foreach ($pdo->query('SELECT username, doctor_code, display_name, must_change_pwd FROM users ORDER BY username') as $r) {
            $flag = $r['must_change_pwd'] ? 'YES' : 'no';
            echo str_pad($r['username'], 8) . '  ' . str_pad($r['doctor_code'], 12) . '  ' . str_pad($flag, 12) . '  ' . $r['display_name'] . "\n";
        }

    } elseif ($argv[1] === 'passwd' && isset($argv[2], $argv[3])) {
        $hash = password_hash($argv[3], PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password = ?, must_change_pwd = 0 WHERE username = ?')
            ->execute([$hash, $argv[2]]);
        echo "Password updated for '{$argv[2]}'.\n";

    } elseif ($argv[1] === 'reset' && isset($argv[2])) {
        $username = $argv[2];
        $hash = password_hash($username, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password = ?, must_change_pwd = 1 WHERE username = ?')
            ->execute([$hash, $username]);
        echo "Password reset to default (= username) for '{$username}'. ผู้ใช้จะถูกบังคับเปลี่ยน password ตอน login ครั้งถัดไป.\n";

    } elseif ($argv[1] === 'unlock' && isset($argv[2])) {
        $code = $argv[2];
        $pdo->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([$code]);
        echo "Unlocked '{$code}' — ลบประวัติ login attempts ทั้งหมดของ username นี้แล้ว.\n";

    } else {
        echo "Usage:\n";
        echo "  php portal/auth.php list\n";
        echo "  php portal/auth.php passwd <doctor_code> <new_password>\n";
        echo "  php portal/auth.php reset  <doctor_code>\n";
        echo "  php portal/auth.php unlock <doctor_code>\n";
    }
    exit;
}
