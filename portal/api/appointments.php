<?php
/**
 * portal/api/appointments.php
 * GET ?date=YYYY-MM-DD
 * ส่งกลับ JSON รายการนัดของ doctor ที่ login อยู่ สำหรับวันที่ระบุ
 */

require_once dirname(__DIR__) . '/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$user       = currentUser();
$doctorCode = $user['doctor_code'];

$date = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date']);
    exit;
}

// อนุญาตเฉพาะวันนี้ และ 30 วันข้างหน้า
$today   = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+30 days'));
if ($date < $today || $date > $maxDate) {
    echo json_encode(['error' => 'Date out of range']);
    exit;
}

$jsonPath = dirname(dirname(__DIR__)) . '/data/appointments.json';

if (!file_exists($jsonPath)) {
    echo json_encode([
        'generated_at'  => null,
        'doctor_code'   => $doctorCode,
        'date'          => $date,
        'appointments'  => [],
        'message'       => 'ยังไม่มีข้อมูลนัด กรุณารอ cron 06:00 น.',
    ]);
    exit;
}

$data = json_decode(file_get_contents($jsonPath), true);

$appointments = $data['appointments'][$doctorCode][$date] ?? [];

echo json_encode([
    'generated_at' => $data['generated_at'] ?? null,
    'doctor_code'  => $doctorCode,
    'date'         => $date,
    'appointments' => $appointments,
], JSON_UNESCAPED_UNICODE);
