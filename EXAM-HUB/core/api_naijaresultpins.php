<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function naijaresultpins_request($endpoint, $post_data = null) {
    $token = get_setting('naijaresultpins_token');
    $url = "https://www.naijaresultpins.com/api/v1/" . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = [
        'Authorization: Bearer ' . $token,
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept: application/json'
    ];
    
    if ($post_data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        $headers[] = 'Content-Type: application/json';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("NaijaResultPins API cURL Error: $error");
    }
    
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        throw new Exception("NaijaResultPins API returned invalid JSON (HTTP $http_code): " . htmlspecialchars(substr($response, 0, 100)));
    }
    
    if (isset($decoded['status']) && $decoded['status'] === false) {
        throw new Exception("NaijaResultPins API Error: " . ($decoded['message'] ?? 'Unknown error'));
    }
    
    return $decoded;
}

function naijaresultpins_get_packages() {
    // NaijaResultPins API blocks automated cURL requests with a 403 Forbidden WAF.
    // We return their standard known product IDs here based on official pricing.
    return [
        [
            'provider_product_id' => '1',
            'name' => 'WAEC Scratch Card',
            'original_price' => 5140
        ],
        [
            'provider_product_id' => '2',
            'name' => 'NECO TOKEN',
            'original_price' => 2000
        ],
        [
            'provider_product_id' => '3',
            'name' => 'NABTEB Scratch Card',
            'original_price' => 820
        ],
        [
            'provider_product_id' => '4',
            'name' => 'WAEC Verification Pin',
            'original_price' => 5350
        ],
        [
            'provider_product_id' => '5',
            'name' => 'NBAIS Scratch Card',
            'original_price' => 1250
        ],
        [
            'provider_product_id' => '11',
            'name' => 'NECO e-Verification PIN',
            'original_price' => 5700
        ],
        [
            'provider_product_id' => '16',
            'name' => 'EXAMINIFY BIOMETRIC TOKEN',
            'original_price' => 480
        ]
    ];
}

function naijaresultpins_purchase($provider_product_id, $quantity = 1) {
    $payload = [
        'card_type_id' => $provider_product_id,
        'quantity' => $quantity
    ];
    
    $res = naijaresultpins_request("exam-card/buy", $payload);
    
    if (isset($res['status']) && $res['status'] === true) {
        $pins = [];
        if (isset($res['cards'])) {
            foreach ($res['cards'] as $card) {
                $pins[] = [
                    'pin' => $card['pin'],
                    'serial_no' => $card['serial_no'] ?? 'N/A'
                ];
            }
        }
        
        return [
            'status' => true,
            'pins' => $pins,
            'message' => 'Success'
        ];
    }
    
    return [
        'status' => false,
        'message' => $res['message'] ?? 'Transaction failed on NaijaResultPins.'
    ];
}
