<?php
// api/transaction/simulate.php  — Sandbox-only: instantly marks a test
// transaction as successful without touching the real Paystack API.
// Security: only works if is_test = 1. Live transactions are rejected.
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$db = Database::connect();

$ref = $_GET['reference'] ?? '';
if (!$ref) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing reference']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM transactions WHERE reference = ?");
$stmt->execute([$ref]);
$tx = $stmt->fetch();

if (!$tx) {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'Transaction not found']);
    exit;
}

if (!(bool)$tx['is_test']) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'This endpoint cannot be used for live transactions.']);
    exit;
}

$user_id = $tx['user_id'];

if ($tx['status'] === 'success') {
    echo json_encode(['status' => true, 'message' => 'Already successful', 'data' => ['reference' => $ref, 'status' => 'success']]);
    exit;
}

$db->beginTransaction();
try {
    $amount = (float)$tx['amount'];
    $fee = calculate_fees($amount, false, $user_id);
    $settled = $amount - $fee;

    $stmt = $db->prepare("UPDATE transactions SET status = 'success', fee_amount = ?, settled_amount = ?, gateway_reference = ? WHERE id = ?");
    $stmt->execute([$fee, $settled, 'SIMULATED_' . strtoupper(bin2hex(random_bytes(4))), $tx['id']]);

    log_ledger_entry($user_id, $settled, 'credit', 'payment', "Sandbox simulated payment: $ref", true);
    log_transaction_event($tx['id'], 'simulated', 'Payment marked successful via sandbox simulate endpoint');

    $db->commit();

    // Fire merchant webhook so their verify endpoint gets notified
    trigger_merchant_webhook($tx['id']);

    echo json_encode([
        'status'  => true,
        'message' => 'Transaction simulated as successful',
        'data'    => [
            'reference' => $ref,
            'status'    => 'success',
            'amount'    => $tx['amount'] * 100,
        ]
    ]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Simulation failed: ' . $e->getMessage()]);
}
