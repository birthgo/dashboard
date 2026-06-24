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
const DOCTORS = [
    '0675' => 'ทพ.เจษฎา ไกรลาส',
    '0237' => 'ทพญ.ธนภรณ์ ลิมปอารยะกุล',
    '0739' => 'ทพ.ฐิติพงศ์ คำห้าง',
    '0768' => 'ทพญ.ฐิตาพร แจ่มจันทร์เกษม',
    '0772' => 'ทพญ.อาทิตยา คล้ำบู่',
    '0797' => 'ทพญ.ภัทริน เถี่ยนมิตรภาพ',
    '0730' => 'นส.ชนนิกานต์ วิมาลา',
    '0731' => 'นส.พจนรรถ จงสัมฤทธิ์ผลดี',
    '0749' => 'นส.ศุภาพิชญ์ แสงอินทร์',
    '0778' => 'นส.อติกานต์ แก้วเณร',
];

function getPortalDB(): PDO {
    $dbPath = dirname(__DIR__) . '/data/portal_users.sqlite';
    $isNew  = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            username     TEXT UNIQUE NOT NULL,
            password     TEXT NOT NULL,
            doctor_code  TEXT NOT NULL,
            display_name TEXT NOT NULL,
            created_at   TEXT DEFAULT (datetime('now','localtime'))
        )
    ");

    // Seed users ครั้งแรก (หรือเพิ่มเฉพาะที่ยังไม่มี)
    $insert = $pdo->prepare(
        'INSERT OR IGNORE INTO users (username, password, doctor_code, display_name)
         VALUES (:u, :p, :doc, :name)'
    );
    foreach (DOCTORS as $code => $name) {
        $insert->execute([
            ':u'    => $code,
            ':p'    => password_hash($code, PASSWORD_BCRYPT), // รหัสผ่านเริ่มต้น = doctor_code
            ':doc'  => $code,
            ':name' => $name,
        ]);
    }

    return $pdo;
}

function authenticate(string $username, string $password): ?array {
    $pdo  = getPortalDB();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return null;
}

function requireLogin(): void {
    if (empty($_SESSION['portal_user'])) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): ?array {
    return $_SESSION['portal_user'] ?? null;
}

/* ---------- CLI ----------
   php portal/auth.php list
   php portal/auth.php passwd <doctor_code> <new_password>
   php portal/auth.php reset  <doctor_code>          ← reset กลับเป็น doctor_code
---------------------------------------------------- */
if (PHP_SAPI === 'cli' && isset($argv[1])) {
    $pdo = getPortalDB();

    if ($argv[1] === 'list') {
        echo str_pad('username', 8) . '  ' . str_pad('doctor_code', 12) . '  display_name' . "\n";
        echo str_repeat('-', 60) . "\n";
        foreach ($pdo->query('SELECT username, doctor_code, display_name FROM users ORDER BY username') as $r) {
            echo str_pad($r['username'], 8) . '  ' . str_pad($r['doctor_code'], 12) . '  ' . $r['display_name'] . "\n";
        }

    } elseif ($argv[1] === 'passwd' && isset($argv[2], $argv[3])) {
        $hash = password_hash($argv[3], PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password = ? WHERE username = ?')
            ->execute([$hash, $argv[2]]);
        echo "Password updated for '{$argv[2]}'.\n";

    } elseif ($argv[1] === 'reset' && isset($argv[2])) {
        $code = $argv[2];
        $hash = password_hash($code, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password = ? WHERE username = ?')
            ->execute([$hash, $code]);
        echo "Password reset to doctor_code for '{$code}'.\n";

    } else {
        echo "Usage:\n";
        echo "  php portal/auth.php list\n";
        echo "  php portal/auth.php passwd <doctor_code> <new_password>\n";
        echo "  php portal/auth.php reset  <doctor_code>\n";
    }
    exit;
}
