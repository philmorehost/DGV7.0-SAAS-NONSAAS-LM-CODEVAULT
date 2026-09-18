<?php session_start();
	include_once("../../func/bc-connect.php");

// Helpers for this handler. They are declared here, before use: an unconditional top-level function is
// hoisted, but one inside an if() block is only defined when execution reaches it.
if (!function_exists('bc_log_beewave')) {
	function bc_log_beewave($msg) {
		$dir = __DIR__ . '/../../logs';
		if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
		@file_put_contents($dir . '/beewave_webhook.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
	}
}
if (!function_exists('bc_beewave_verify_lookup')) {
	function bc_beewave_verify_lookup($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/json", "Content-Type: application/json"));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		$res = curl_exec($ch);
		curl_close($ch);
		return json_decode((string)$res, true);
	}
}

	$body = file_get_contents("php://input");
	$catch_incoming_request = json_decode($body, true);

    if (!$catch_incoming_request) {
        http_response_code(400);
        die("Invalid payload");
    }

    $event = $catch_incoming_request["event"] ?? "";
    $event_data = $catch_incoming_request["data"] ?? [];
    $transaction_ref = $event_data["reference"] ?? "";

    if ($event !== "transaction.success") {
        exit("Ignored event: " . $event);
    }

	// Robust Vendor Identification
	$vendor_id = resolveVendorID();
	$select_vendor_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='$vendor_id' AND status=1 LIMIT 1"));

    if (!$select_vendor_table) {
        if (!empty($transaction_ref)) {
            $ref_esc = mysqli_real_escape_string($connection_server, $transaction_ref);
            $q = mysqli_query($connection_server, "SELECT vendor_id FROM sas_user_payment_checkouts WHERE reference='$ref_esc' LIMIT 1");
            if ($r = mysqli_fetch_assoc($q)) {
                $vendor_id = $r['vendor_id'];
                $select_vendor_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='$vendor_id' LIMIT 1"));
            }
        }
    }

	if($select_vendor_table && $select_vendor_table["status"] == 1){
        $vendor_id = $select_vendor_table["id"];
        $GLOBALS['vendor_id'] = $vendor_id;
		$beewave_keys = mysqli_fetch_assoc(mysqli_query($connection_server,"SELECT * FROM sas_payment_gateways WHERE vendor_id='$vendor_id' && gateway_name='beewave'"));

        // ── Verify the callback before it may credit anything ──────────────────────────────
        // This URL is public: anyone can POST {"event":"transaction.success","data":{...}} at it. The
        // "signature verification would go here" placeholder meant a forged POST credited ANY amount to
        // ANY pending reference - free money for whoever found the endpoint.
        //
        // 1) The reference must be one of OUR funding attempts. That row is also where the amount the
        //    customer was asked to pay comes from, and the credit is capped by it below, so a forged or
        //    replayed body can never inflate the amount.
        $ref_esc_exp = mysqli_real_escape_string($connection_server, (string)$transaction_ref);
        $expected_amount = 0.0;
        $q_exp = mysqli_query($connection_server, "SELECT amount FROM sas_transactions WHERE vendor_id='$vendor_id' AND (reference='$ref_esc_exp' OR api_reference='$ref_esc_exp') ORDER BY id DESC LIMIT 1");
        if ($q_exp && ($r_exp = mysqli_fetch_assoc($q_exp))) {
            $expected_amount = (float)$r_exp['amount'];
        }

        // 2) Beewave itself must confirm the transaction, using the merchant's access key.
        $beewave_access_key = trim((string)($beewave_keys['public_key'] ?? ''));
        $beewave_verified_data = null;
        if ($beewave_access_key !== '' && (string)$transaction_ref !== '') {
            $beewave_verify = bc_beewave_verify_lookup("https://merchant.beewave.ng/api/v1/collection/verify?access_key=" . rawurlencode($beewave_access_key) . "&transaction_ref=" . rawurlencode((string)$transaction_ref));
            if (is_array($beewave_verify) && !empty($beewave_verify['status']) && (($beewave_verify['data']['status'] ?? '') === 'success')) {
                $beewave_verified_data = is_array($beewave_verify['data'] ?? null) ? $beewave_verify['data'] : array();
            }
        }

        // 3) If this vendor has a webhook secret configured, the caller must present it too.
        $configured_secret = trim((string)($beewave_keys['webhook_secret'] ?? ''));
        if ($configured_secret !== '') {
            $provided_secret = $_GET['secret'] ?? ($_SERVER['HTTP_X_BEEWAVE_SIGNATURE'] ?? '');
            if (empty($provided_secret) || !hash_equals($configured_secret, (string)$provided_secret)) {
                bc_log_beewave("REJECTED secret mismatch vendor=$vendor_id ref=$transaction_ref");
                http_response_code(401);
                die("Invalid signature");
            }
        }

        if ($beewave_verified_data === null) {
            // Not confirmed by the gateway. 502 so Beewave retries, and logged for reconciliation.
            bc_log_beewave("UNVERIFIED vendor=$vendor_id ref=$transaction_ref body_amount=" . (float)($event_data['amount'] ?? 0));
            http_response_code(502);
            die("Could not verify transaction with Beewave");
        }
        if ($expected_amount <= 0) {
            bc_log_beewave("NO MATCHING PENDING ROW vendor=$vendor_id ref=$transaction_ref");
            http_response_code(200);
            die("Ignored: unknown reference");
        }

        // Prefer the gateway's own figure, but never more than the customer was asked to fund.
        $settled_amount = (float)($beewave_verified_data['amount'] ?? $beewave_verified_data['amount_paid'] ?? $beewave_verified_data['settlement_amount'] ?? 0);
        $amount_paid = $settled_amount > 0 ? min($settled_amount, $expected_amount) : $expected_amount;

        // Implement Charges correctly
        $charge_percent = (float)($beewave_keys['percentage'] ?? 0);
        $amount_deposited = $amount_paid * (1 - ($charge_percent / 100));

        $payment_method = $event_data["channel"] ?? "UNKNOWN";

        // Find user
        $username = "";
        $q_checkout = mysqli_query($connection_server, "SELECT username FROM sas_user_payment_checkouts WHERE vendor_id='$vendor_id' && reference='".mysqli_real_escape_string($connection_server, $transaction_ref)."' LIMIT 1");
        if ($r_checkout = mysqli_fetch_assoc($q_checkout)) {
            $username = $r_checkout['username'];
        }

        if (!empty($username)) {
            $check_tx = mysqli_query($connection_server, "SELECT id FROM sas_transactions WHERE vendor_id='$vendor_id' AND (api_reference='$transaction_ref' OR reference='$transaction_ref') AND status=1 LIMIT 1");
            if (mysqli_num_rows($check_tx) == 0) {
                $new_ref = substr(str_shuffle("12345678901234567890"), 0, 15);
                $desc = "Beewave Wallet Credit - ".str_replace("_"," ",$payment_method);
                chargeOtherUser($username, "credit", "Beewave", "Wallet Credit", $new_ref, $transaction_ref, $amount_paid, $amount_deposited, $desc, "APP", $_SERVER['HTTP_HOST'], 1);

                // Update checkout status
                mysqli_query($connection_server, "UPDATE sas_user_payment_checkouts SET status=2 WHERE vendor_id='$vendor_id' AND reference='".mysqli_real_escape_string($connection_server, $transaction_ref)."'");

                echo "SUCCESS";
            } else {
                echo "ALREADY_PROCESSED";
            }
        }
	}
?>