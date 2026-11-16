<?php
/**
 * export_appointments.php
 * Cron: 0 6 * * *
 * ดึงข้อมูลนัดทันตกรรม วันนี้ + 30 วันข้างหน้า แล้วบันทึกเป็น JSON
 */

date_default_timezone_set('Asia/Bangkok');

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/runtime.php';
$env=DashboardRuntime::config();
$host=$env['DB_HOST']; $port=$env['DB_PORT']; $dbname=$env['DB_NAME'];
$user=$env['DB_USER']; $pass=$env['DB_PASS'];
/**
 * แปลง string จาก TIS-620 (encoding เดิมของ HosXP) เป็น UTF-8
 * ถ้าเป็น UTF-8 อยู่แล้ว (valid) จะคืนค่าเดิมไม่แปลงซ้ำ กัน double-encode
 * ถ้าแปลงไม่ได้เลย จะคืน string เปล่าเพื่อไม่ทำให้ json_encode ทั้งไฟล์พังไปด้วย
 */
function toUtf8(?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    // ถ้าเป็น UTF-8 ที่ถูกต้องอยู่แล้ว ไม่ต้องแปลง (กัน double-encode ถ้า column บางตัวเก็บ UTF-8 จริงๆ)
    if (mb_check_encoding($value, 'UTF-8')) {
        return $value;
    }
    $converted = @iconv('TIS-620', 'UTF-8', $value);
    if ($converted !== false) {
        return $converted;
    }
    // ลอง CP874 (Windows Thai) เป็นตัวสำรอง เผื่อบางคอลัมน์เก็บมาจากโปรแกรมฝั่ง Windows
    $converted = @iconv('CP874', 'UTF-8', $value);
    if ($converted !== false) {
        return $converted;
    }
    // แปลงไม่ได้เลยทั้งสองทาง — ตัด byte ที่ทำให้ invalid ทิ้งเพื่อไม่ให้ json_encode ทั้งไฟล์ล้ม
    return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log('[export_appointments] DB connection failed; type=' . get_class($e));
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
        'patient_name' => toUtf8($row['patient_name']),
        'hometel'      => $row['hometel'], // เบอร์โทรเป็นตัวเลข ไม่ต้องแปลง encoding
        'nexttime'     => substr($row['nexttime'], 0, 5), // HH:MM
        'note'         => toUtf8($row['note']),
        'doctor_name'  => toUtf8($row['doctor_name']),
    ];
}

$output = [
    'generated_at' => date('Y-m-d H:i:s'),
    'date_range'   => ['from' => $today, 'to' => $endDate],
    'appointments' => $byDoctor,
];

try {
    DashboardRuntime::writeAppointments($output);
    echo '[' . date('Y-m-d H:i:s') . '] OK appointments saved privately; count=' . count($rows) . PHP_EOL;
} catch (Throwable $e) {
    error_log('[export_appointments] private write failed; type=' . get_class($e));
    exit(1);
}
