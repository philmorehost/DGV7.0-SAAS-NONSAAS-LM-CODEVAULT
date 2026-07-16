<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function clubkonnect_request($endpoint, $params = []) {
    $userid = get_setting('clubkonnect_userid');
    $apikey = get_setting('clubkonnect_apikey');
    
    $params['UserID'] = $userid;
    $params['APIKey'] = $apikey;
    
    $url = "https://www.nellobytesystems.com/" . $endpoint . "?" . http_build_query($params);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("ClubKonnect API cURL Error: $error");
    }
    
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        throw new Exception("ClubKonnect API returned invalid JSON: " . htmlspecialchars(substr($response, 0, 100)));
    }
    
    return $decoded;
}

function clubkonnect_get_packages() {
    $packages = [];
    
    // WAEC
    $waec = clubkonnect_request("APIWAECPackagesV2.asp");
    if (isset($waec['EXAM_TYPE']) && is_array($waec['EXAM_TYPE'])) {
        foreach ($waec['EXAM_TYPE'] as $details) {
            $code = $details['PRODUCT_CODE'];
            $packages[] = [
                'provider_product_id' => 'waec|' . $code,
                'name' => $details['PRODUCT_DESCRIPTION'] ?? $code,
                'original_price' => $details['PRODUCT_AMOUNT'] ?? 0
            ];
        }
    } else {
        throw new Exception("ClubKonnect WAEC Error: " . json_encode($waec));
    }
    
    // JAMB
    $jamb = clubkonnect_request("APIJAMBPackagesV2.asp");
    if (isset($jamb['EXAM_TYPE']) && is_array($jamb['EXAM_TYPE'])) {
        foreach ($jamb['EXAM_TYPE'] as $details) {
            $code = $details['PRODUCT_CODE'];
            $packages[] = [
                'provider_product_id' => 'jamb|' . $code,
                'name' => $details['PRODUCT_DESCRIPTION'] ?? $code,
                'original_price' => $details['PRODUCT_AMOUNT'] ?? 0
            ];
        }
    } else {
        throw new Exception("ClubKonnect JAMB Error: " . json_encode($jamb));
    }
    
    return $packages;
}

function clubkonnect_purchase($provider_product_id, $phone) {
    list($type, $exam_code) = explode('|', $provider_product_id);
    
    $endpoint = $type === 'waec' ? 'APIWAECV1.asp' : 'APIJAMBV1.asp';
    
    $params = [
        'ExamType' => $exam_code,
        'PhoneNo' => $phone,
        'RequestID' => date('YmdHi') . uniqid()
    ];
    
    $res = clubkonnect_request($endpoint, $params);
    
    if (isset($res['statuscode']) && $res['statuscode'] === '200') {
        $pins = [];
        // Clubkonnect returns card details as string "Serial No:XXX, pin: YYY"
        if (isset($res['carddetails'])) {
            $parts = explode(',', $res['carddetails']);
            $serial = 'N/A';
            $pin = 'N/A';
            foreach ($parts as $part) {
                if (stripos($part, 'Serial No') !== false) {
                    $serial = trim(explode(':', $part)[1] ?? 'N/A');
                } elseif (stripos($part, 'pin') !== false) {
                    $pin = trim(explode(':', $part)[1] ?? 'N/A');
                }
            }
            $pins[] = [
                'serial_no' => $serial,
                'pin' => $pin
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
        'message' => $res['remark'] ?? 'Transaction failed on ClubKonnect.'
    ];
}
