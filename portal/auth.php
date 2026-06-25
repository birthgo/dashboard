<?php
/**
 * portal/auth.php
 * - username = doctor_code (เช่น 0675)
 * - password เริ่มต้น = doctor_code เหมือนกัน (เปลี่ยนได้ภายหลัง)
 * - users ถูก seed อัตโนมัติเมื่อเปิดระบบครั้งแรก
 */

date_default_timezone_set('Asia/Bangkok');
session_start();

// โหลด .env จาก root
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $_ENV[$k] = $v;
    }
}

// ─── รายชื่อทันตแพทย์ทั้งหมด ──────────────────────────
// username = doctor_code, password เริ่มต้น = doctor_code
// แต่ละหมอ: username (สำหรับ login เท่านั้น) => [doctor_code (ใช้ map กับ a.doctor ใน oapp), display_name]
const DOCTORS = [
    '13300' => ['doctor_code' => '0675', 'name' => 'ทพ.เจษฎา ไกรลาส'],
    '15821' => ['doctor_code' => '0237', 'name' => 'ทพญ.ธนภรณ์ ลิมปอารยะกุล'],
    '12541' => ['doctor_code' => '0739', 'name' => 'ทพ.ฐิติพงศ์ คำห้าง'],
    '21336' => ['doctor_code' => '0768', 'name' => 'ทพญ.ฐิตาพร แจ่มจันทร์เกษม'],
    '20196' => ['doctor_code' => '0772', 'name' => 'ทพญ.อาทิตยา คล้ำบู่'],
    '13804' => ['doctor_code' => '0797', 'name' => 'ทพญ.ภัทริน เถี่ยนมิตรภาพ'],
    '0730'  => ['doctor_code' => '0730', 'name' => 'นส.ชนนิกานต์ วิมาลา'],
    '0731'  => ['doctor_code' => '0731', 'name' => 'นส.พจนรรถ จงสัมฤทธิ์ผลดี'],
    '0749'  => ['doctor_code' => '0749', 'name' => 'นส.ศุภาพิชญ์ แสงอินทร์'],
    '0778'  => ['doctor_code' => '0778', 'name' => 'นส.อติกานต์ แก้วเณร'],
];

function getPortalDB(): PDO {
    $dbPath = dirname(__DIR__) . '/data/portal_users.sqlite';
    $isNew  = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5); // รอ lock ปลดสูงสุด 5 วินาทีก่อน throw
    $pdo->exec("PRAGMA journal_mode = WAL");  // ลด 'database is locked' จาก concurrent requests

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            username        TEXT UNIQUE NOT NULL,
            password        TEXT NOT NULL,
            doctor_code     TEXT NOT NULL,
            display_name    TEXT NOT NULL,
            must_change_pwd INTEGER NOT NULL DEFAULT 1,
            created_at      TEXT DEFAULT (datetime('now','localtime'))
        )
    ");

    // เผื่อ DB เก่าที่สร้างไว้ก่อนมีคอลัมน์นี้
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('must_change_pwd', $cols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_pwd INTEGER NOT NULL DEFAULT 1");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            username     TEXT NOT NULL,
            ip           TEXT NOT NULL,
            success      INTEGER NOT NULL,
            attempted_at TEXT DEFAULT (datetime('now','localtime'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attempts_lookup ON login_attempts(username, ip, attempted_at)");

    // Seed users เฉพาะตอนสร้าง DB ใหม่ครั้งแรกเท่านั้น (กัน lock จากการ INSERT OR IGNORE ทุก request)
    if ($isNew) {
        $insert = $pdo->prepare(
            'INSERT OR IGNORE INTO users (username, password, doctor_code, display_name, must_change_pwd)
             VALUES (:u, :p, :doc, :name, 1)'
        );
        foreach (DOCTORS as $username => $info) {
            $insert->execute([
                ':u'    => $username,
                ':p'    => password_hash($username, PASSWORD_BCRYPT), // รหัสผ่านเริ่มต้น = username
                ':doc'  => $info['doctor_code'],
                ':name' => $info['name'],
            ]);
        }
    }

    return $pdo;
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
    if (empty($_SESSION['portal_user'])) {
        header('Location: login.php');
        exit;
    }
    $user = currentUser();
    // บังคับเปลี่ยน password ครั้งแรก ก่อนเข้าใช้งานหน้าอื่น
    $currentFile = basename($_SERVER['SCRIPT_NAME']);
    if (!empty($user['must_change_pwd']) && $currentFile !== 'change_password.php') {
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
    $stmt = $pdo->prepare('SELECT must_change_pwd FROM users WHERE username = ?');
    $stmt->execute([$_SESSION['portal_user']['username']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row !== false) {
        $_SESSION['portal_user']['must_change_pwd'] = (int)$row['must_change_pwd'];
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
