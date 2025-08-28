<?php
$url = 'http://192.168.100.18/animates/api/rfid_endpoint.php';
$payload = [
    'card_uid' => '73:77:f8:39',
    'custom_uid' => 'TVTPIV8O',
    'tap_count' => 2,
    'max_taps' => 5,
    'tap_number' => 2,
    'device_info' => 'ESP32-RFID-Scanner',
    'wifi_network' => 'HUAWEI-2.4G-x6Nj',
    'signal_strength' => -53,
    'validation_status' => 'approved',
    'readable_time' => '2025-08-28 12:28:47',
    'timestamp_value' => '2025-08-28 12:28:47',
    'rfid_scanner_status' => 'OK',
    'validation_time_ms' => 3000,
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
echo json_encode([
    'request' => $payload,
    'http_status' => $status,
    'curl_errno' => $errno,
    'curl_error' => $error,
    'response' => $response,
], JSON_PRETTY_PRINT);


