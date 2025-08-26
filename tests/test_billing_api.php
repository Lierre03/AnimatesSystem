<?php
// Test script for billing API
echo "Testing Billing API...\n";

// Test 1: Check if API endpoint is accessible with a real RFID tag
$url = 'http://localhost/animates/api/billing.php?rfid=5TKVETUH';
echo "Testing URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Response Code: $httpCode\n";
echo "Response: $response\n";

if (curl_error($ch)) {
    echo "cURL Error: " . curl_error($ch) . "\n";
}

curl_close($ch);

echo "\nTest completed.\n";
?>
