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

$response = null; // gateway verification response (set below only when a live check runs)

// If local status is not success, check the gateway directly (Real-time reconciliation)
if ($tx['status'] !== 'success') {
    $is_test = (bool)$tx['is_test'];
    $response = paystack_call("transaction/verify/" . urlencode($ref), 'GET', [], $is_test);

    if ($response && $response['status'] && $response['data']['status'] === 'success') {
        $data = $response['data'];
        $amount = $data['amount'] / 100;

        // CRITICAL: compare the amount the gateway actually settled against the
        // amount recorded for this reference. `transactions.amount` is
        // merchant-supplied and, for hosted checkout, is rendered into the page
        // that drives Paystack Inline - so it can be rewritten in the browser (or
        // bypassed by calling Paystack directly) to pay a token sum while the
        // record still holds the larger figure.
        if (!amounts_match($tx['amount'], $amount)) {
            flag_amount_mismatch($tx, $tx['amount'], $amount, 'api/transaction/verify.php');
            http_response_code(400);
            echo json_encode([
                'status' => false,
                'message' => 'Payment amount mismatch. Transaction rejected.',
                'details' => [
                    'expected' => $tx['amount'],
                    'received' => $amount
                ]
            ]);
            exit;
        }

        $fee = calculate_fees($amount, ($data['currency'] !== 'NGN'), $user['id']);
        $settled = $amount - $fee;

        $db->beginTransaction();
        try {
            // Atomically claim this transaction before moving any money. A
            // concurrent worker - the Paystack webhook, or a second poll of this
            // endpoint - that already fulfilled it makes this affect 0 rows, so
            // the merchant wallet is never credited twice.
            if (!claim_transaction_for_fulfilment($tx['id'])) {
                $db->rollBack();
            } else {
                $stmt = $db->prepare("UPDATE transactions SET fee_amount = ?, settled_amount = ?, gateway_reference = ? WHERE id = ?");
                $stmt->execute([$fee, $settled, $data['id'], $tx['id']]);

                // Persist the gateway-confirmed figure so downstream consumers (the
                // merchant webhook, admin reporting) work from evidence.
                record_gateway_amount($tx['id'], $amount);

                log_ledger_entry($user['id'], $settled, 'credit', 'payment', "Real-time Verified Payment: $ref", $is_test);
                log_transaction_event($tx['id'], 'verified', 'Payment verified and fulfilled via real-time API check');

                $db->commit();
                trigger_merchant_webhook($tx['id']);
            }

            // Refresh local tx data - this also picks up a fulfilment that another
            // worker completed just before us, so `paid` stays accurate.
            $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
            $stmt->execute([$tx['id']]);
            $tx = $stmt->fetch();
        } catch (Exception $e) {
            $db->rollBack();
        }
    }
}

echo json_encode([
    'status' => true,                         // = "transaction retrieved" (NOT "paid")
    'message' => 'Transaction retrieved',
    'paid' => ($tx['status'] === 'success'),  // authoritative boolean: is this payment complete?
    'data' => [
        'id' => $tx['id'],
        'status' => $tx['status'],                          // real status: success | pending | failed
        'payment_status' => $tx['status'],                  // alias for clarity
        'paid' => ($tx['status'] === 'success'),            // boolean mirror of the above
        'reference' => $tx['reference'],
        'amount' => $tx['amount'] * 100,                    // amount in KOBO
        'currency' => 'NGN',
        // Lets an integrating script refuse to credit sandbox payments.
        'domain' => ((int)$tx['is_test'] === 1) ? 'test' : 'live',
        'customer' => [
            'email' => $tx['customer_email']
        ],
        'gateway_response' => $tx['status'] === 'success' ? 'Successful' : ($response['data']['status'] ?? 'Pending'),
        'created_at' => $tx['created_at'],
        'metadata' => json_decode($tx['metadata'] ?? '[]', true)
    ]
]);
