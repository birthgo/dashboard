<?php
/**
 * export_appointments.php
 * Cron: 0 6 * * *
 * ดึงข้อมูลนัดทันตกรรม วันนี้ + 30 วันข้างหน้า แล้วบันทึกเป็น JSON
 */

date_default_timezone_set('Asia/Bangkok');

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    error_log('[export_appointments] ERROR: .env not found');
    exit(1);
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = array_map('trim', explode('=', $line, 2));
    $_ENV[$key] = $val;
}

$host   = $_ENV['DB_HOST']   ?? '172.16.16.155';
$port   = $_ENV['DB_PORT']   ?? '3306';
$dbname = $_ENV['DB_NAME']   ?? 'lkhos';
$user   = $_ENV['DB_USER']   ?? 'adminwork';
$pass   = $_ENV['DB_PASS']   ?? '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log('[export_appointments] DB Error: ' . $e->getMessage());
    exit(1);
}

$today   = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+30 days'));

$sql = "
    SELECT
        a.oapp_id,
        a.hn,
        a.nextdate,
        a.nexttime,
        a.doctor,
        a.note,
        CONCAT(
            CASE p.pname
                WHEN '1' THEN 'นาย'
                WHEN '2' THEN 'นาง'
                WHEN '3' THEN 'น.ส.'
                WHEN '4' THEN 'นาง'
                WHEN '6' THEN 'ด.ช.'
                WHEN '7' THEN 'ด.ญ.'
                ELSE ''
            END,
            p.fname, ' ', p.lname
        ) AS patient_name,
        p.hometel,
        d.name AS doctor_name
    FROM lkhos.oapp a
    LEFT JOIN lkhos.patient p ON p.hn = a.hn
    LEFT JOIN lkhos.doctor d  ON d.code = a.doctor
    WHERE a.spclty = '11'
      AND a.nextdate BETWEEN :today AND :enddate
    ORDER BY a.nextdate ASC, a.nexttime ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':today' => $today, ':enddate' => $endDate]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// จัดกลุ่มตาม doctor แล้วตาม date
$byDoctor = [];
foreach ($rows as $row) {
    $doc  = $row['doctor'];
    $date = $row['nextdate'];
    $byDoctor[$doc][$date][] = [
        'oapp_id'      => $row['oapp_id'],
        'hn'           => $row['hn'],
        'patient_name' => $row['patient_name'],
        'hometel'      => $row['hometel'],
        'nexttime'     => substr($row['nexttime'], 0, 5), // HH:MM
        'note'         => $row['note'],
        'doctor_name'  => $row['doctor_name'],
    ];
}

$output = [
    'generated_at' => date('Y-m-d H:i:s'),
    'date_range'   => ['from' => $today, 'to' => $endDate],
    'appointments' => $byDoctor,
];

$jsonPath = __DIR__ . '/data/appointments.json';
if (file_put_contents($jsonPath, json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
    echo '[' . date('Y-m-d H:i:s') . '] OK — ' . count($rows) . " appointments saved\n";
} else {
    error_log('[export_appointments] ERROR: cannot write ' . $jsonPath);
    exit(1);
}
