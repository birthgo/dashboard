#!/usr/bin/env php
<?php
/**
 * export_dental.php
 * ดึงเฉพาะยอดรวมผู้ป่วยนัดทันตกรรม ไม่มีชื่อคนไข้
 * ปลอดภัยสำหรับ push ขึ้น GitHub Public
 */

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    die("ERROR: ไม่พบ .env file ที่ {$envFile}\n");
}

$env = parse_ini_file($envFile);
$DB_HOST = $env['DB_HOST'] ?? '172.16.16.155';
$DB_PORT = $env['DB_PORT'] ?? '3306';
$DB_NAME = $env['DB_NAME'] ?? 'lkhos';
$DB_USER = $env['DB_USER'] ?? '';
$DB_PASS = $env['DB_PASS'] ?? '';

$GIT_REPO_PATH = $env['GIT_REPO_PATH'] ?? '/var/www/html/dental-dashboard';
$PUSH_BRANCH   = $env['GIT_BRANCH'] ?? 'main';

// ─── Database Connection ────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 10,
        ]
    );
} catch (PDOException $e) {
    die("ERROR: DB connection failed: " . $e->getMessage() . "\n");
}

$today = date('Y-m-d');

// ─── Query เฉพาะยอดรวม ────────────────────────────────────────────────────
$sql = "
    SELECT COUNT(*) AS total
    FROM lkhos.oapp
    WHERE nextdate = :today
      AND spclty = '11'
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':today' => $today]);
    $row = $stmt->fetch();
} catch (PDOException $e) {
    die("ERROR: Query failed: " . $e->getMessage() . "\n");
}

$total = (int)$row['total'];

// ─── Build JSON (ไม่มีชื่อคนไข้) ────────────────────────────────────────────
$output = [
    'date'         => $today,
    'generated_at' => date('Y-m-d H:i:s'),
    'total_count'  => $total,
];

$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ─── Write JSON ──────────────────────────────────────────────────────────────
$dataDir  = "{$GIT_REPO_PATH}/data";
$jsonFile = "{$dataDir}/dental_today.json";

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

file_put_contents($jsonFile, $json);
echo "[" . date('H:i:s') . "] เขียน JSON สำเร็จ: {$jsonFile} (ยอดรวม {$total} ราย)\n";

// ─── Git Push ────────────────────────────────────────────────────────────────
$commands = [
    "cd {$GIT_REPO_PATH}",
    "git add data/dental_today.json",
    "git diff --cached --quiet || git commit -m 'auto: update dental {$today} " . date('H:i') . "'",
    "git push origin {$PUSH_BRANCH} 2>&1",
];

$cmd = implode(' && ', $commands);
$result = shell_exec($cmd);
echo "[" . date('H:i:s') . "] Git push: " . trim($result ?? 'no output') . "\n";
echo "[" . date('H:i:s') . "] เสร็จสิ้น\n";
