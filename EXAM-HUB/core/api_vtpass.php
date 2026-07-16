<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function vtpass_request($endpoint, $post_data = null) {
    $username = get_setting('vtpass_username');
    $password = get_setting('vtpass_password');
    $url = "https://vtpass.com/api/" . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($post_data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$password")
        ]);
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode("$username:$password")
        ]);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("VTPass API cURL Error: $error");
    }
    
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        throw new Exception("VTPass API returned invalid JSON (HTTP $http_code): " . htmlspecialchars(substr($response, 0, 100)));
    }
    
    return $decoded;
}

function vtpass_get_packages() {
    $packages = [];
    $services = ['waec', 'jamb'];
    
    foreach ($services as $service) {
        $res = vtpass_request("service-variations?serviceID={$service}");
        if (isset($res['content']['variations'])) {
            foreach ($res['content']['variations'] as $v) {
                $packages[] = [
                    'provider_product_id' => $service . '|' . $v['variation_code'],
                    'name' => $v['name'] . ' (' . $res['content']['ServiceName'] . ')',
                    'original_price' => $v['variation_amount']
                ];
            }
        }
    }
    return $packages;
}

function vtpass_purchase($provider_product_id, $amount, $phone) {
    list($serviceID, $variation_code) = explode('|', $provider_product_id);
    
    $requestId = date('YmdHi') . uniqid();
    
    $payload = [
        'request_id' => $requestId,
        'serviceID' => $serviceID,
        'variation_code' => $variation_code,
        'amount' => $amount,
        'phone' => $phone ?: '08000000000'
    ];
    
    $res = vtpass_request("pay", $payload);
    
    if (isset($res['code']) && $res['code'] === '000') {
        $pins = [];
        if (isset($res['cards']) && is_array($res['cards'])) {
            foreach ($res['cards'] as $card) {
                $pins[] = [
                    'pin' => $card['Pin'] ?? $card['pin'],
                    'serial_no' => $card['Serial'] ?? $card['serial'] ?? 'N/A'
                ];
            }
        } elseif (isset($res['purchased_code'])) {
            // Sometimes it returns just purchased_code string
            $pins[] = [
                'pin' => $res['purchased_code'],
                'serial_no' => 'N/A'
            ];
        }
        
        return [
            'status' => true,
            'pins' => $pins,
            'message' => 'Success'
        ];
    }
    
    return [
        'status' => false,
        'message' => $res['response_description'] ?? 'Transaction failed on VTPass.'
    ];
}
