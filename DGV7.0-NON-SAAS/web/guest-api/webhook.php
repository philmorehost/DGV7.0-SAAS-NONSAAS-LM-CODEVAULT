<?php
/**
 * Guest PayHub payment webhook — this is the ONLY place a guest order gets marked paid
 * and fulfilled. Mirrors the fixed users-payhub.php pattern exactly: the raw webhook body
 * is untrusted input, so before crediting/fulfilling anything we independently re-verify
 * the reference against PayHub's own transaction-verify API and act only on that response.
 */
include_once(__DIR__ . "/guest-bootstrap.php");
include_once(__DIR__ . "/fulfill.php");

function guest_webhook_log($msg) {
    // Intentionally silent in production; flip to error_log($msg) locally when debugging.
}

$body = file_get_contents("php://input");
$catch = json_decode($body, true);
if (!$catch) {
    guest_webhook_log("Guest webhook: invalid payload");
    http_response_code(400);
    exit("Invalid payload");
}

$event = $catch['event'] ?? 'charge.success';
$data = $catch['data'] ?? $catch;
$reference = $data['reference'] ?? '';

if (empty($reference)) {
    bc_log_security_event('SECURITY', 'guest_webhook', guest_client_ip(), 'Missing reference');
    http_response_code(400);
    exit("Missing reference");
}

if (!($event == 'charge.success' || ($catch['status'] ?? '') == 'success' || ($catch['status'] ?? '') == 'successful')) {
    http_response_code(200);
    exit("Event ignored");
}

$order = guest_get_order($reference);
if (!$order) {
    bc_log_security_event('SECURITY', 'guest_webhook', guest_client_ip(), "Unknown guest order reference: $reference");
    http_response_code(404);
    exit("Unknown order");
}

// Idempotency: only a pending_payment order should ever be advanced by this webhook.
if ((int)$order['status'] !== GUEST_STATUS_PENDING_PAYMENT) {
    http_response_code(200);
    exit("ALREADY_PROCESSED");
}

$vendor_id = $order['vendor_id'];

// Security Fix (mirrors users-payhub.php): never trust the raw webhook body — independently
// re-verify the reference against PayHub's own transaction-verify API before doing anything.
$verify_res = makePayhubRequest("GET", "api/transaction/verify/" . urlencode($reference), "", $vendor_id, false);
$v_data = json_decode($verify_res, true);
$verified_tx = null;
if (($v_data['status'] ?? "") == "success") {
    $tx_raw = json_decode($v_data['json_result'], true);
    $tx_data = $tx_raw['data'] ?? $tx_raw;
    $tx_status = strtolower($tx_data['status'] ?? "");
    if ($tx_status == "success" || $tx_status == "successful" || ($tx_raw['status'] ?? false) === true) {
        $verified_tx = $tx_data;
    }
}

if (!$verified_tx) {
    bc_log_security_event('SECURITY', 'guest_webhook', $reference, 'Could not independently verify via PayHub API. Refusing to fulfill.');
    http_response_code(400);
    exit("Unverified transaction");
}

// Amount sanity check: the verified PayHub amount should match what we quoted the guest.
// A missing/zero amount in PayHub's own verify response is itself suspicious for a
// "successful" transaction — fail closed rather than skipping the check.
$verified_amount = (float)($verified_tx['amount'] ?? 0);
if ($verified_amount <= 0 || abs($verified_amount - (float)$order['discounted_amount']) > 0.5) {
    bc_log_security_event('SECURITY', 'guest_webhook', $reference, "Amount mismatch: quoted {$order['discounted_amount']}, paid $verified_amount");
    http_response_code(400);
    exit("Amount mismatch");
}

// Atomic claim: only the request that actually flips PENDING_PAYMENT -> PAID proceeds to
// fulfill. A concurrent/retried webhook delivery for the same reference loses this race
// and is treated as already-processed rather than fulfilling (and paying out) twice.
if (!guest_claim_order_for_payment($reference)) {
    http_response_code(200);
    exit("ALREADY_PROCESSED");
}
guest_update_order($reference, 'payment_reference', $verified_tx['reference'] ?? $reference);

$order = guest_get_order($reference);
$result = guest_fulfill_order($order);

http_response_code(200);
echo json_encode(["status" => $result['status'], "desc" => $result['desc'], "reference" => $reference]);
