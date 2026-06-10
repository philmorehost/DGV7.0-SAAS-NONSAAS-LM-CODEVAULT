<?php
$url = "https://api.sendchamp.com/api/v1/whatsapp/message/send";
$payload = [
    "sender" => "2348086697100", // Based on customer_mobile_number in their test, or we'll let it fail and see the error
    "recipient" => "2348086697100",
    "type" => "text",
    "message" => "Test from Antigravity"
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer sendchamp_live_$2a$10$TCyh7uTsvoG9BixQ9Y9z7uqygSPA6IQxGs4oHWyUz2AZtK6JP.wne",
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "cURL Error: $err\n";
echo "Response: $response\n";
