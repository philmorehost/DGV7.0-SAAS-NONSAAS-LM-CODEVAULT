<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/functions.php';

// Authenticate Request
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized: Missing or invalid Bearer token.']);
    exit;
}

$api_key = $matches[1];

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT * FROM api_access WHERE api_key = ? AND status = 'approved'");
$stmt->execute([$api_key]);
$api_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$api_user) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized: Invalid API key or access suspended.']);
    exit;
}

// Fetch Packages
$products = $pdo->query("SELECT id, name, selling_price, status FROM products WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($products as $p) {
    // Calculate discounted price
    $price = (float)$p['selling_price'];
    $discounted = $price;
    
    if ($api_user['discount_type'] === 'percentage') {
        $discounted -= ($price * ($api_user['discount_value'] / 100));
    } else {
        $discounted -= $api_user['discount_value'];
    }
    
    // Ensure discounted isn't negative
    if ($discounted < 0) $discounted = 0;
    
    $data[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'price' => $price,
        'discounted_price' => $discounted
    ];
}

echo json_encode([
    'status' => true,
    'data' => $data
]);
