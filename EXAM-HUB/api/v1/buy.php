<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/functions.php';

$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized: Missing or invalid Bearer token.']);
    exit;
}

$api_key = $matches[1];

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT a.*, u.wallet_balance FROM api_access a JOIN users u ON a.user_id = u.id WHERE a.api_key = ? AND a.status = 'approved'");
$stmt->execute([$api_key]);
$api_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$api_user) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized: Invalid API key or access suspended.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$card_id = $input['card_id'] ?? null;
$quantity = (int)($input['quantity'] ?? 1);
$reference = $input['reference'] ?? ('API_' . time() . '_' . rand(1000,9999));

if (!$card_id || $quantity < 1 || $quantity > 100) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Bad Request: Missing card_id or invalid quantity (1-100).']);
    exit;
}

// Fetch Product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
$stmt->execute([$card_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'Not Found: Card ID does not exist or is out of stock.']);
    exit;
}

// Check Reference uniqueness
$stmt = $pdo->prepare("SELECT id FROM orders WHERE reference = ?");
$stmt->execute([$reference]);
if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Bad Request: Reference ID already exists.']);
    exit;
}

// Calculate price
$unit_price = (float)$product['selling_price'];
$discounted_unit = $unit_price;

if ($api_user['discount_type'] === 'percentage') {
    $discounted_unit -= ($unit_price * ($api_user['discount_value'] / 100));
} else {
    $discounted_unit -= $api_user['discount_value'];
}
if ($discounted_unit < 0) $discounted_unit = 0;

$total_charged = $discounted_unit * $quantity;
$user_id = $api_user['user_id'];
$wallet = (float)$api_user['wallet_balance'];

if ($wallet < $total_charged) {
    http_response_code(402);
    echo json_encode(['status' => false, 'message' => 'Payment Required: Insufficient wallet balance.']);
    exit;
}

// Proceed with Purchase (Wallet Deduct & Process)
require_once __DIR__ . '/../../core/api_vtpass.php';
require_once __DIR__ . '/../../core/api_clubkonnect.php';
require_once __DIR__ . '/../../core/api_naijaresultpins.php';

$pdo->beginTransaction();
try {
    // Deduct Wallet
    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
        ->execute([$total_charged, $user_id]);
        
    // Record Transaction
    $pdo->prepare("INSERT INTO transactions (user_id, reference, type, amount, status, payment_method) VALUES (?, ?, 'purchase', ?, 'completed', 'api_wallet')")
        ->execute([$user_id, $reference, $total_charged]);
        
    // Create Order
    $pdo->prepare("INSERT INTO orders (user_id, reference, card_type_id, quantity, amount, status, phone) VALUES (?, ?, ?, ?, ?, 'pending', ?)")
        ->execute([$user_id, $reference, $card_id, $quantity, $total_charged, 'API_PURCHASE']);
    
    $order_id = $pdo->lastInsertId();
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Internal Server Error: Database failure.']);
    exit;
}

// Process Provider API
$provider = $product['active_provider'];
$provider_product_id = $product[$provider . '_id']; // Dynamically get vtpass_id, clubkonnect_id, etc.

$all_pins = [];
$has_error = false;
$error_msg = '';

if (!$provider_product_id) {
    $has_error = true;
    $error_msg = "Provider ID is not configured for $provider.";
} else {
    if ($provider === 'naijaresultpins') {
        $result = naijaresultpins_purchase($provider_product_id, $quantity);
        if ($result['status']) {
            $all_pins = array_merge($all_pins, $result['pins']);
        } else {
            $has_error = true;
            $error_msg = $result['message'];
        }
    } else {
        // VTPass and ClubKonnect process 1 PIN per request, loop if quantity > 1
        for ($i = 0; $i < $quantity; $i++) {
            if ($provider === 'vtpass') {
                $result = vtpass_purchase($provider_product_id, $product['original_price'], '08000000000');
            } elseif ($provider === 'clubkonnect') {
                $result = clubkonnect_purchase($provider_product_id, '08000000000');
            } else {
                $result = ['status' => false, 'message' => 'Unknown provider'];
            }
        
        if ($result['status']) {
            $all_pins = array_merge($all_pins, $result['pins']);
        } else {
            $has_error = true;
            $error_msg = $result['message'];
            break; 
        }
    }
}

if (!$has_error && count($all_pins) > 0) {
    $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$order_id]);
    
    foreach ($all_pins as $card) {
        $pdo->prepare("INSERT INTO order_pins (order_id, pin, serial_no) VALUES (?, ?, ?)")
            ->execute([$order_id, $card['pin'], $card['serial_no']]);
    }
    
    echo json_encode([
        'status' => true,
        'message' => 'Purchase successful',
        'reference' => $reference,
        'total_charged' => $total_charged,
        'pins' => $all_pins
    ]);
} else {
    $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order_id]);
    
    // In a robust system, we would auto-refund the wallet here if partial/full failure.
    // For this implementation, we will auto-refund the exact charged amount since it failed.
    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
        ->execute([$total_charged, $user_id]);
        
    $pdo->prepare("INSERT INTO transactions (user_id, reference, type, amount, status, payment_method) VALUES (?, ?, 'purchase', ?, 'failed', 'api_refund')")
        ->execute([$user_id, $reference . '_REFUND', $total_charged]);
        
    http_response_code(422);
    echo json_encode([
        'status' => false,
        'message' => 'Provider Error: ' . ($error_msg ?: 'Failed to generate PINs. Amount refunded to wallet.')
    ]);
}
