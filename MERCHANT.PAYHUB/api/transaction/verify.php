<?php
// php-version/api/transaction/verify.php
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

// Extract reference from URL (assuming rewrite or simple query)
$ref = $_GET['reference'] ?? '';

if (!$ref) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing reference']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM transactions WHERE reference = ? AND user_id = ?");
$stmt->execute([$ref, $user['id']]);
$tx = $stmt->fetch();

if (!$tx) {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'Transaction not found']);
    exit;
}

// If local status is not success, check the gateway directly (Real-time reconciliation)
if ($tx['status'] !== 'success') {
    $is_test = (bool)$tx['is_test'];
    $response = paystack_call("transaction/verify/" . urlencode($ref), 'GET', [], $is_test);

    if ($response && $response['status'] && $response['data']['status'] === 'success') {
        $data = $response['data'];
        $amount = $data['amount'] / 100;
        $fee = calculate_fees($amount, ($data['currency'] !== 'NGN'), $user['id']);
        $settled = $amount - $fee;

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE transactions SET status = 'success', fee_amount = ?, settled_amount = ?, gateway_reference = ? WHERE id = ?");
            $stmt->execute([$fee, $settled, $data['id'], $tx['id']]);

            log_ledger_entry($user['id'], $settled, 'credit', 'payment', "Real-time Verified Payment: $ref", $is_test);
            log_transaction_event($tx['id'], 'verified', 'Payment verified and fulfilled via real-time API check');

            $db->commit();
            trigger_merchant_webhook($tx['id']);

            // Refresh local tx data
            $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
            $stmt->execute([$tx['id']]);
            $tx = $stmt->fetch();
        } catch (Exception $e) {
            $db->rollBack();
        }
    }
}

echo json_encode([
    'status' => true,
    'message' => 'Transaction retrieved',
    'data' => [
        'id' => $tx['id'],
        'status' => $tx['status'],
        'reference' => $tx['reference'],
        'amount' => $tx['amount'] * 100,
        'customer' => [
            'email' => $tx['customer_email']
        ],
        'gateway_response' => $tx['status'] === 'success' ? 'Successful' : ($response['data']['status'] ?? 'Pending'),
        'created_at' => $tx['created_at'],
        'metadata' => json_decode($tx['metadata'] ?? '[]', true)
    ]
]);
