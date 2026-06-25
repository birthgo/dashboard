<?php
/**
 * debug_export.php — ไฟล์ทดสอบชั่วคราว ใช้หาสาเหตุที่ export_appointments.php เขียนไฟล์ไม่ได้
 * รันด้วย: sudo -u apache php debug_export.php
 * ลบไฟล์นี้ทิ้งหลังจากหาสาเหตุได้แล้ว
 */

date_default_timezone_set('Asia/Bangkok');

$envFile = '/var/www/html/dental-dashboard/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = array_map('trim', explode('=', $line, 2));
    $_ENV[$key] = $val;
}

$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'], $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$today   = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+30 days'));

$sql = "
    SELECT
        a.oapp_id, a.hn, a.nextdate, a.nexttime, a.doctor, a.note,
        CONCAT(
            CASE p.pname
                WHEN '1' THEN 'นาย' WHEN '2' THEN 'นาง' WHEN '3' THEN 'น.ส.'
                WHEN '4' THEN 'นาง' WHEN '6' THEN 'ด.ช.' WHEN '7' THEN 'ด.ญ.'
                ELSE ''
            END,
            p.fname, ' ', p.lname
        ) AS patient_name,
        p.hometel, d.name AS doctor_name
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

echo "=== Step 1: Query ===\n";
echo "Rows fetched: " . count($rows) . "\n\n";

// เช็คทุกแถวว่า encoding เป็น valid UTF-8 ไหม ก่อน json_encode
echo "=== Step 2: ตรวจ encoding รายแถว ===\n";
$badRows = [];
foreach ($rows as $i => $row) {
    foreach ($row as $field => $value) {
        if ($value !== null && !mb_check_encoding((string)$value, 'UTF-8')) {
            $badRows[] = "Row $i, field '$field': INVALID UTF-8 -> " . bin2hex((string)$value);
        }
    }
}
if (empty($badRows)) {
    echo "ทุกแถว encoding ปกติ (valid UTF-8 หมด)\n\n";
} else {
    echo "พบแถวที่ encoding ผิดปกติ " . count($badRows) . " จุด:\n";
    foreach (array_slice($badRows, 0, 10) as $bad) {
        echo "  - $bad\n";
    }
    echo "\n";
}

echo "=== Step 3: จัดกลุ่มข้อมูล ===\n";
$byDoctor = [];
foreach ($rows as $row) {
    $doc  = $row['doctor'];
    $date = $row['nextdate'];
    $byDoctor[$doc][$date][] = [
        'oapp_id'      => $row['oapp_id'],
        'hn'           => $row['hn'],
        'patient_name' => $row['patient_name'],
        'hometel'      => $row['hometel'],
        'nexttime'     => substr($row['nexttime'], 0, 5),
        'note'         => $row['note'],
        'doctor_name'  => $row['doctor_name'],
    ];
}
echo "จำนวนหมอที่มีนัด: " . count($byDoctor) . "\n\n";

$output = [
    'generated_at' => date('Y-m-d H:i:s'),
    'date_range'   => ['from' => $today, 'to' => $endDate],
    'appointments' => $byDoctor,
];

echo "=== Step 4: json_encode ===\n";
$json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo "Result type: " . gettype($json) . "\n";
echo "json_last_error(): " . json_last_error() . "\n";
echo "json_last_error_msg(): " . json_last_error_msg() . "\n";

if ($json === false) {
    echo "\n*** JSON_ENCODE ล้มเหลว! นี่คือสาเหตุของปัญหา ***\n";
    echo "ลองหาแถวที่มีปัญหาทีละแถว...\n";
    foreach ($byDoctor as $doc => $dates) {
        $test = json_encode([$doc => $dates], JSON_UNESCAPED_UNICODE);
        if ($test === false) {
            echo "พบปัญหาที่ doctor code: $doc (error: " . json_last_error_msg() . ")\n";
        }
    }
} else {
    echo "JSON length: " . strlen($json) . " bytes\n\n";

    echo "=== Step 5: ทดสอบเขียนไฟล์จริง ===\n";
    $jsonPath = '/var/www/html/dental-dashboard/data/appointments.json';
    $writeResult = file_put_contents($jsonPath, $json);
    echo "file_put_contents result: ";
    var_dump($writeResult);

    if ($writeResult === false) {
        $err = error_get_last();
        echo "PHP last error: " . json_encode($err) . "\n";
    } else {
        echo "เขียนไฟล์สำเร็จ! ขนาด $writeResult bytes\n";
    }
}
