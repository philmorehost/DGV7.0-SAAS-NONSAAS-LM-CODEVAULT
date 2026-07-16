<?php
// php-version/api/transaction/initialize.php
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$headers = getallheaders();
$auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$auth || strpos($auth, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$sk = str_replace('Bearer ', '', $auth);
$db = Database::connect();
$stmt = $db->prepare("SELECT id FROM users WHERE secret_key = ? OR test_secret_key = ?");
$stmt->execute([$sk, $sk]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Invalid Secret Key']);
    exit;
}

$input = get_api_input();

$email = sanitize($input['email'] ?? '');
$amount = (float)($input['amount'] ?? 0);
$name = sanitize($input['name'] ?? '');
$phone = sanitize($input['phone'] ?? '');
$metadata = $input['metadata'] ?? '';
if (is_array($metadata)) $metadata = json_encode($metadata);

// Special case for VA generation only (amount 0)
if (!$email || ($amount <= 0 && empty($phone))) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing email or invalid amount']);
    exit;
}

$ref = 'PH_' . bin2hex(random_bytes(8));

// Determine if it's test mode based on the Secret Key used
$is_test = (strpos($sk, 'sk_test_') === 0);

// Only create a transaction if amount is greater than 0
if ($amount > 0) {
    // Create transaction in pending state
    $stmt = $db->prepare("INSERT INTO transactions (user_id, reference, amount, customer_email, customer_name, status, is_test, metadata) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
    $stmt->execute([$user['id'], $ref, $amount, $email, $name, $is_test ? 1 : 0, $metadata]);
    $txId = $db->lastInsertId();

    log_transaction_event($txId, 'initiated', "Transaction initiated via API");
}

// Attempt to automate Virtual Account generation if requested or possible
$va_data = null;
$va_error = null;
if (!empty($name) && !empty($phone)) {
    $res = ensure_virtual_account($user['id'], $email, [
        'full_name' => $name,
        'phone' => $phone
    ], $is_test, $metadata);
    if ($res['status']) {
        $va_data = $res['data'];
    } else {
        $va_error = $res['message'] ?? 'Unknown VA error';
    }
}

if ($amount > 0) {
    $checkoutUrl = BASE_URL . "checkout.php?ref=$ref&amount=$amount&email=" . urlencode($email);

    $responseData = [
        'status' => true,
        'message' => 'Transaction initialized',
        'data' => [
            'authorization_url' => $checkoutUrl,
            'access_code' => $ref,
            'reference' => $ref
        ]
    ];
} else {
    // If amount is 0, it's just a VA generation request
    $responseData = [
        'status' => true,
        'message' => 'Virtual Account request processed',
        'data' => []
    ];
}

if ($va_data) {
    $responseData['data']['virtual_account'] = $va_data;
}

if ($va_error && !$va_data) {
    $responseData['data']['va_error'] = $va_error;
}

echo json_encode($responseData);
