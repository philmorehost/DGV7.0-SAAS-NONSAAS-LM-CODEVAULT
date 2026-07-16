<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function payhub_generate_virtual_account($user_id) {
    $pdo = get_db_connection();
    
    // Check if user already has a virtual account
    $stmt = $pdo->prepare("SELECT firstname, lastname, email, phone, virtual_bank_name, virtual_account_number, virtual_account_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return false;
    if (!empty($user['virtual_account_number'])) {
        return [
            'bank_name' => $user['virtual_bank_name'],
            'account_number' => $user['virtual_account_number'],
            'account_name' => $user['virtual_account_name']
        ];
    }
    
    // Fetch virtual account using Payhub API
    $secret_key = get_setting('payhub_secret_key');
    if (empty($secret_key)) return false;
    
    // IMPORTANT: Do NOT proceed without a real phone number
    $phone = !empty($user['phone']) ? $user['phone'] : '';
    if (empty($phone)) {
        $log_msg = "[" . date('Y-m-d H:i:s') . "] VA Generation SKIPPED for User ID: {$user_id}\n";
        $log_msg .= "Reason: User has no phone number in database. Please update profile first.\n";
        $log_msg .= str_repeat("-", 50) . "\n";
        file_put_contents(__DIR__ . '/../payhub_debug.log', $log_msg, FILE_APPEND);
        return false;
    }

    $payload = [
        'email' => $user['email'],
        'amount' => 0,
        'name' => trim($user['firstname'] . ' ' . $user['lastname']),
        'phone' => $phone
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://merchant.payhub.com.ng/api/transaction/initialize");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secret_key,
        "Content-Type: application/json",
        "Accept: application/json"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // DEBUG LOGGING
    $log_msg = "[" . date('Y-m-d H:i:s') . "] VA Generation Attempt for User ID: {$user_id}\n";
    $log_msg .= "Secret Key (first 15 chars): " . substr($secret_key, 0, 15) . "...\n";
    $log_msg .= "Payload: " . json_encode($payload) . "\n";
    $log_msg .= "HTTP Code: {$http_code}\n";
    if ($curl_error) $log_msg .= "cURL Error: {$curl_error}\n";
    $log_msg .= "Response: {$response}\n";
    $log_msg .= str_repeat("-", 50) . "\n";
    file_put_contents(__DIR__ . '/../payhub_debug.log', $log_msg, FILE_APPEND);
    
    $result = json_decode($response, true);
    
    if ($http_code == 200 && isset($result['status']) && $result['status'] === true && !empty($result['data']['virtual_account'])) {
        $account_data = $result['data']['virtual_account'];
        
        if (isset($account_data['account_number'])) {
            $bank_name = $account_data['bank_name'] ?? 'Payhub Virtual Bank';
            $account_number = $account_data['account_number'];
            $account_name = $account_data['account_name'] ?? trim($user['firstname'] . ' ' . $user['lastname']);
            
            $update = $pdo->prepare("UPDATE users SET virtual_bank_name = ?, virtual_account_number = ?, virtual_account_name = ? WHERE id = ?");
            $update->execute([$bank_name, $account_number, $account_name, $user_id]);
            
            return [
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'account_name' => $account_name
            ];
        }
    }
    
    return false;
}
