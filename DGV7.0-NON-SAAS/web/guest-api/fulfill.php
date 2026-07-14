<?php
/**
 * Guest order fulfillment — called ONLY from guest-webhook.php after PayHub payment has
 * been independently verified. Mirrors web/func/{airtime,data,cable,electric,exam,betting}.php's
 * gateway-calling sequence exactly (same reset-vars -> include gateway file -> branch on
 * $api_response pattern) but with chargeUser()/bc_atomic_debit_user() removed entirely —
 * the guest already paid via PayHub before this ever runs, there is no wallet to debit.
 *
 * On gateway failure there is no wallet to refund into (unlike the authenticated flow's
 * chargeUser("credit", ...) refund) — the order is marked GUEST_STATUS_FAILED and the
 * vendor is emailed so the PayHub payment can be refunded manually. This is a deliberate,
 * documented gap (see Phase 1 plan note), not an oversight.
 *
 * Requires guest-bootstrap.php to already be included by the caller.
 */

/**
 * Emails the guest a simple HTML receipt after successful delivery — only when they typed a
 * real email at checkout (extra_data.guest_email; the synthesized guest+ref@host placeholder
 * used for PayHub's initialize call is never stored there). Subject deliberately avoids the
 * words Transaction/Purchase/Fulfillment so sendVendorEmail()'s vendor-side "transaction
 * emails disabled" toggle doesn't suppress it — this is the guest's only durable server-sent
 * copy of their purchase.
 */
function guest_send_receipt_email($order, $delivery_desc, $service_outputs = []) {
    $extra = json_decode($order['extra_data'] ?? '{}', true) ?: [];
    $to = $extra['guest_email'] ?? '';
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;

    $rows = [
        "Reference" => $order['reference'],
        "Service" => ucwords(str_replace(['-', '_'], ' ', $order['service_type'])),
        "Recipient" => $order['identity'],
        "Amount Paid" => "NGN " . number_format((float)$order['discounted_amount'], 2),
        "Status" => "Successful",
        "Details" => $delivery_desc,
    ];
    if (!empty($service_outputs['token'])) $rows["Token"] = $service_outputs['token'];
    if (!empty($service_outputs['token_unit'])) $rows["Units"] = $service_outputs['token_unit'];

    $body = "<div style='font-family:sans-serif;max-width:520px;margin:auto'>"
          . "<h2 style='color:#0D6EFD'>Payment Receipt</h2>"
          . "<p>Thank you for your payment. Here is your receipt:</p>"
          . "<table style='width:100%;border-collapse:collapse'>";
    foreach ($rows as $label => $value) {
        $body .= "<tr><td style='padding:8px;border-bottom:1px solid #eee;color:#64748B'>" . htmlspecialchars($label) . "</td>"
               . "<td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;text-align:right'>" . htmlspecialchars((string)$value) . "</td></tr>";
    }
    $body .= "</table><p style='color:#94A3B8;font-size:12px;margin-top:16px'>Keep this email as proof of payment.</p></div>";

    sendVendorEmail($to, "Your payment receipt — " . $order['reference'], $body);
}

/**
 * Independently verifies a still-pending order against PayHub and, if genuinely paid, atomically
 * claims + fulfills it. This is what guest-webhook.php calls when PayHub's server-to-server
 * webhook arrives — but a guest checkout should not be a single point of failure on PayHub's
 * webhook actually being registered/reachable for this merchant. status.php calls this exact
 * same function on every poll too, so the app's own "confirming your payment" screen drives
 * completion by itself if the webhook never shows up. guest_claim_order_for_payment() is an
 * atomic compare-and-swap, so it's safe for the webhook and several concurrent polls to all
 * call this for the same reference — only one ever wins the claim and fulfills.
 */
function guest_attempt_paid_fulfillment($reference) {
    $order = guest_get_order($reference);
    if (!$order || (int)$order['status'] !== GUEST_STATUS_PENDING_PAYMENT) {
        return;
    }

    $vendor_id = $order['vendor_id'];
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
    if (!$verified_tx) return;

    $verified_amount = (float)($verified_tx['amount'] ?? 0);
    if ($verified_amount <= 0 || abs($verified_amount - (float)$order['discounted_amount']) > 0.5) {
        bc_log_security_event('SECURITY', 'guest_status_verify', $reference, "Amount mismatch: quoted {$order['discounted_amount']}, paid $verified_amount");
        return;
    }

    if (!guest_claim_order_for_payment($reference)) return;
    guest_update_order($reference, 'payment_reference', $verified_tx['reference'] ?? $reference);

    $order = guest_get_order($reference);
    guest_fulfill_order($order);
}

function guest_fulfill_order($order) {
    global $connection_server;

    $vendor_id = $order['vendor_id'];
    $service = $order['service_type'];
    $identity = $order['identity'];
    $reference = $order['reference'];
    $extra = json_decode($order['extra_data'] ?? '{}', true) ?: [];
    $api_id = $order['api_id'];
    $product_id = $order['product_id'];
    $api_base_url = $order['api_website'];

    $type_prefix_map = [
        'airtime' => 'airtime',
        'data' => $extra['data_type'] ?? 'data',
        'cable' => 'cable',
        'electric' => 'electric',
        'exam' => 'exam',
        'betting' => 'betting',
    ];
    $type_prefix = $type_prefix_map[$service] ?? $service;

    // Real provider gateway files (func/api-gateway/*.php) are included directly and read
    // plain variables out of the including scope — $isp/$epp, $product_name (always set as
    // an isp/epp alias in the original web/func/*.php scope), $phone_no/$iuc_no/$meter_number/
    // $customer_id, $quantity, $type, $amount, $api_detail. These must be rebuilt here since
    // sas_guest_orders only stores api_id/product_id, not the full sas_apis row.
    // $reference and $get_logged_user_details are also read directly by several gateway files
    // (e.g. every electric-*.php provider writes an sas_electric_purchaseds audit row keyed off
    // $get_logged_user_details["vendor_id"/"username"] and $reference; dd-data-clubkonnect-com.php
    // builds its RequestID from $reference) — without these, those inserts/requests silently use
    // blank values instead of erroring, corrupting audit data or breaking provider idempotency keys.
    $api_detail = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_apis WHERE id='" . (int)$api_id . "' LIMIT 1"));
    $vars = [
        'api_detail' => $api_detail,
        'amount' => $order['amount'],
        'discounted_amount' => $order['discounted_amount'],
        'reference' => $reference,
        'get_logged_user_details' => ['vendor_id' => $vendor_id, 'username' => 'guest:' . $identity],
    ];

    switch ($service) {
        case 'airtime':
            $vars += ['isp' => $extra['network'], 'product_name' => $extra['network'], 'phone_no' => $identity];
            break;
        case 'data':
            $vars += ['isp' => $extra['network'], 'product_name' => $extra['network'], 'phone_no' => $identity, 'quantity' => $extra['quantity'], 'type' => $extra['data_type']];
            break;
        case 'cable':
            $vars += ['isp' => $extra['type'], 'product_name' => $extra['type'], 'iuc_no' => $identity, 'quantity' => $extra['package']];
            break;
        case 'electric':
            $vars += ['epp' => $extra['provider'], 'product_name' => $extra['provider'], 'meter_number' => $identity, 'type' => $extra['meter_type']];
            break;
        case 'exam':
            $vars += ['epp' => $extra['type'], 'product_name' => $extra['type'], 'quantity' => $extra['quantity']];
            break;
        case 'betting':
            $vars += ['epp' => $extra['provider'], 'product_name' => $extra['provider'], 'customer_id' => $identity];
            break;
    }

    $gw_name = guest_gateway_filename('purchase', $type_prefix, $api_base_url);
    $res = guest_run_gateway('purchase', $gw_name, $vars);
    $api_response = $res['api_response'] ? strtolower($res['api_response']) : $res['api_response'];

    if ($api_response === 'successful') {
        guest_update_order($reference, 'api_reference', $res['api_response_reference']);
        guest_update_order($reference, 'description', $res['api_response_description']);
        guest_update_order($reference, 'status', (string)GUEST_STATUS_SUCCESS);
        guest_mark_fulfilled($reference);
        guest_record_abuse_attempt($vendor_id, $service, $identity, $reference);

        $service_outputs = [];
        if ($service === 'electric') {
            $service_outputs = [
                'meter_number' => $res['api_response_meter_number'],
                'token' => $res['api_response_token'],
                'token_unit' => $res['api_response_token_unit'],
            ];
        } elseif ($service === 'betting') {
            $service_outputs = [
                'gateway_customer_id' => $res['api_response_customer_id'],
                'token' => $res['api_response_token'],
            ];
        }
        if ($service_outputs) guest_merge_extra_data($reference, $service_outputs);

        guest_send_receipt_email($order, $res['api_response_description'], $service_outputs);

        return ['status' => 'success', 'desc' => $res['api_response_description']];
    }

    if ($api_response === 'pending') {
        guest_update_order($reference, 'api_reference', $res['api_response_reference']);
        guest_update_order($reference, 'description', $res['api_response_description']);
        guest_update_order($reference, 'status', (string)GUEST_STATUS_GATEWAY_PENDING);
        guest_record_abuse_attempt($vendor_id, $service, $identity, $reference);
        return ['status' => 'pending', 'desc' => $res['api_response_description']];
    }

    // Failed: payment was already captured by PayHub — there is no wallet to auto-refund into.
    // Flag loudly for manual PayHub refund + support follow-up rather than silently losing the money.
    guest_update_order($reference, 'api_reference', $res['api_response_reference'] ?? '');
    guest_update_order($reference, 'description', $res['api_response_description'] ?? 'Gateway failed');
    guest_update_order($reference, 'status', (string)GUEST_STATUS_FAILED);
    bc_log_security_event('GUEST_FULFILLMENT_FAILED_AFTER_PAYMENT', $service, $reference, "Paid order failed at gateway — manual PayHub refund required. Identity: $identity");

    $vendor = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='" . (int)$vendor_id . "' LIMIT 1"));
    if ($vendor && !empty($vendor['email'])) {
        $subject = "ACTION REQUIRED: Guest order $reference failed after payment";
        $body = "A Guest Mode order failed at the delivery gateway AFTER the customer's PayHub payment was captured.<br><br>"
              . "Reference: $reference<br>Service: $service<br>Identity: $identity<br>Amount: " . $order['discounted_amount'] . "<br><br>"
              . "Please issue a manual refund via PayHub for this reference.";
        sendVendorEmail($vendor['email'], $subject, $body);
    }

    return ['status' => 'failed', 'desc' => 'Transaction Failed'];
}
