#!/usr/bin/env php
<?php
date_default_timezone_set('Asia/Bangkok');
/**
 * export_dental.php
 * ดึงเฉพาะยอดรวมผู้ป่วยนัดทันตกรรม ไม่มีชื่อคนไข้
 * ปลอดภัยสำหรับ push ขึ้น GitHub Public
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/environment.php';
$env=DashboardEnvironment::load();
$DB_HOST=$env['DB_HOST']; $DB_PORT=$env['DB_PORT']; $DB_NAME=$env['DB_NAME'];
$DB_USER=$env['DB_USER']; $DB_PASS=$env['DB_PASS'];
$GIT_REPO_PATH=$env['GIT_REPO_PATH']??'';
$PUSH_BRANCH=$env['GIT_BRANCH']??'main';
if (!is_dir($GIT_REPO_PATH) || !preg_match('~^[A-Za-z0-9][A-Za-z0-9/_-]*$~D',$PUSH_BRANCH)) { fwrite(STDERR,"Invalid Git output configuration\n"); exit(1); }
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
    die("ERROR: DB connection failed: " . 'check DB configuration' . "\n");
}

$today = date('Y-m-d');

// ─── Query เฉพาะยอดรวม ────────────────────────────────────────────────────
$sql = "
    SELECT COUNT(DISTINCT dental.vn) AS total
    FROM (
        SELECT v.vn
        FROM lkhos.ovst v
        INNER JOIN lkhos.kskdepartment k ON k.depcode = v.main_dep
        INNER JOIN lkhos.spclty s ON s.spclty = k.spclty
        WHERE v.vstdate = :department_date
          AND s.spclty = '11'
          AND s.name IS NOT NULL

        UNION

        SELECT d.vn
        FROM lkhos.dtmain d
        INNER JOIN lkhos.ovst v ON v.vn = d.vn
        WHERE v.vstdate = :dental_date
    ) AS dental
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':department_date' => $today, ':dental_date' => $today]);
    $row = $stmt->fetch();
} catch (PDOException $e) {
    die("ERROR: Query failed: " . 'check DB configuration' . "\n");
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
    'cd ' . escapeshellarg($GIT_REPO_PATH),
    "git add data/dental_today.json",
    "git diff --cached --quiet || git commit -m 'auto: update dental {$today} " . date('H:i') . "'",
    'git push origin ' . escapeshellarg($PUSH_BRANCH) . ' 2>&1',
];

$cmd = implode(' && ', $commands);
$result = shell_exec($cmd);
echo "Git command finished; output suppressed to avoid credential leakage\n";
echo "[" . date('H:i:s') . "] เสร็จสิ้น\n";
