<?php
// Test RFID tap functionality
$url = 'http://localhost/animates/api/rfid_tap.php';
$data = json_encode(['rfid_tag' => '1BL89OOX']); // This is the custom_uid for RFID card ID 33

$opts = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json' . "\r\n",
        'content' => $data
    ]
];

$context = stream_context_create($opts);
$response = file_get_contents($url, false, $context);

echo "Testing RFID Tap for: 1BL89OOX\n";
echo "===============================\n\n";
echo "Response:\n";
echo $response . "\n\n";

// Decode and show the response
$result = json_decode($response, true);
if ($result) {
    echo "Decoded Response:\n";
    echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    echo "Message: " . $result['message'] . "\n";
    
    if (isset($result['data'])) {
        echo "Booking ID: " . ($result['data']['booking_id'] ?? 'NULL') . "\n";
        echo "Booking Updated: " . ($result['data']['booking_updated'] ? 'Yes' : 'No') . "\n";
        echo "Status Changed To: " . ($result['data']['status_changed_to'] ?? 'NULL') . "\n";
        echo "New Tap Count: " . $result['data']['new_tap_count'] . "\n";
    }
}
?>
