<?php
/**
 * Guest order status polling — GET ?reference=<ref>
 * Mirrors the {ref, status, desc, response_desc} contract of the authenticated API's
 * verify-funding.php so both mobile clients can share response-parsing code, plus the
 * service-specific extra fields (token/meter_number/customer_name) from extra_data.
 */
include_once(__DIR__ . "/guest-bootstrap.php");

$vendor = guest_resolve_vendor();
$vendor_id = $vendor['id'];
guest_security_gate($vendor_id, "guest_status_poll", 60, 60);

$input = array_merge($_GET, $_POST);
$reference = trim(strip_tags($input['reference'] ?? ''));
if (empty($reference)) {
    guest_fail("Reference required");
}

$order = guest_get_order($reference, $vendor_id);
if (!$order) {
    guest_fail("Order not found", 404);
}

$status_map = [
    GUEST_STATUS_PENDING_PAYMENT => 'pending_payment',
    GUEST_STATUS_PAID            => 'processing',
    GUEST_STATUS_SUCCESS         => 'success',
    GUEST_STATUS_GATEWAY_PENDING => 'pending',
    GUEST_STATUS_FAILED          => 'failed',
];
$status_label = $status_map[(int)$order['status']] ?? 'unknown';
$extra = json_decode($order['extra_data'] ?? '{}', true) ?: [];

guest_json([
    "ref" => $order['reference'],
    "status" => $status_label,
    "service" => $order['service_type'],
    "amount" => $order['discounted_amount'],
    "desc" => $order['description'],
    "response_desc" => $order['description'],
    "meter_number" => $extra['meter_number'] ?? null,
    "token" => $extra['token'] ?? null,
    "token_unit" => $extra['token_unit'] ?? null,
    "customer_id" => $extra['customer_id'] ?? null,
]);
