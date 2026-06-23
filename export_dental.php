#!/usr/bin/env php
<?php
/**
 * export_dental.php
 * ดึงข้อมูลผู้ป่วยนัดแผนกทันตกรรมจาก HosXP และสร้าง JSON
 *
 * รันโดย cron บน Slave 2 (172.16.16.155)
 * Cron: ทุก 5 นาที ช่วง 07:00-08:00 และครั้งเดียว 07:00 สำหรับรายชื่อนัด
 */

// ─── Config จาก .env ───────────────────────────────────────────────────────
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

// GitHub config
$GIT_REPO_PATH = $env['GIT_REPO_PATH'] ?? '/opt/dental-dashboard';
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

// ─── Query ─────────────────────────────────────────────────────────────────
$today = date('Y-m-d');

/**
 * ดึงรายชื่อผู้ป่วยนัดทันตกรรมวันนี้
 * - clinic = '1D' คือรหัสทันตกรรมของ HosXP (ปรับตามโรงพยาบาล)
 * - JOIN กับ patient เพื่อดึงชื่อ-นามสกุล
 * - JOIN กับ doctor เพื่อดึงชื่อทันตแพทย์
 */
$sql = "
    SELECT
        o.hn,
        CONCAT(
            CASE pt.pname
                WHEN '1' THEN 'นาย '
                WHEN '2' THEN 'นาง '
                WHEN '3' THEN 'น.ส. '
                WHEN '4' THEN 'นาง '
                WHEN '6' THEN 'ด.ช. '
                WHEN '7' THEN 'ด.ญ. '
                ELSE ''
            END,
            pt.fname, ' ', pt.lname
        ) AS fullname,
        o.nexttime,
        o.doctor,
        d.name AS doctor_name,
        (SELECT COUNT(*) FROM lkhos.ovst
         WHERE hn = o.hn AND vstdate = o.nextdate) AS visit_count
    FROM lkhos.oapp o
    LEFT JOIN lkhos.patient pt ON pt.hn = o.hn
    LEFT JOIN lkhos.doctor d   ON d.code = o.doctor
    WHERE o.nextdate = :today
      AND o.spclty = '11'
    ORDER BY o.nexttime ASC, o.hn ASC
";

/* spclty = '11' คือรหัสแผนกทันตกรรม รพ.เลาขวัญ (ยืนยันจากไฟล์ dentalappointmentlist.php) */

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':today' => $today]);
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    die("ERROR: Query failed: " . $e->getMessage() . "\n");
}

// ─── Count query (จำนวนรวม อัพเดททุก 5 นาที) ───────────────────────────────
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM lkhos.oapp
    WHERE nextdate = :today
      AND spclty = '11'
";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute([':today' => $today]);
$countRow = $stmtCount->fetch();

// ─── Build JSON ─────────────────────────────────────────────────────────────
$output = [
    'date'         => $today,
    'generated_at' => date('Y-m-d H:i:s'),
    'total_count'  => (int)$countRow['total'],
    'patients'     => $patients,
];

$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ─── Write JSON file ─────────────────────────────────────────────────────────
$dataDir  = "{$GIT_REPO_PATH}/data";
$jsonFile = "{$dataDir}/dental_today.json";

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

file_put_contents($jsonFile, $json);
echo "[" . date('H:i:s') . "] เขียน JSON สำเร็จ: {$jsonFile} ({$countRow['total']} ราย)\n";

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
