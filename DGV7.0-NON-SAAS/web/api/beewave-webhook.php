<?php session_start();
	include_once("../../func/bc-connect.php");

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

        // Security Fix: verify this callback genuinely came from Beewave before crediting anything.
        // Beewave has no documented HMAC-header signing scheme available in this codebase, so the
        // supported mechanism is a shared secret configured as a query parameter on the webhook URL
        // itself (set in the Beewave dashboard as .../beewave-webhook.php?secret=XXXX via the admin
        // panel's generated Webhook URL) — this works regardless of what header scheme Beewave may
        // or may not send. If Beewave also sends a signature header, that's accepted too as an
        // alternative. Compared with hash_equals() to avoid timing attacks.
        $configured_secret = trim($beewave_keys['webhook_secret'] ?? '');
        if (empty($configured_secret)) {
            error_log("SECURITY: Beewave webhook secret not configured for vendor $vendor_id. Rejecting webhook.");
            http_response_code(401);
            die("Webhook not configured");
        }

        $provided_secret = $_GET['secret'] ?? ($_SERVER['HTTP_X_BEEWAVE_SIGNATURE'] ?? '');
        if (empty($provided_secret) || !hash_equals($configured_secret, $provided_secret)) {
            error_log("SECURITY: Beewave webhook signature mismatch for vendor $vendor_id, ref $transaction_ref.");
            http_response_code(401);
            die("Invalid signature");
        }

        $amount_paid = (float)($event_data["amount"] ?? 0);

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
                chargeOtherUser($username, "credit", "Beewave", "Wallet Credit", $new_ref, $transaction_ref, $amount_paid, $amount_deposited, $desc, "APP", $host, 1);

                // Update checkout status
                mysqli_query($connection_server, "UPDATE sas_user_payment_checkouts SET status=2 WHERE vendor_id='$vendor_id' AND reference='".mysqli_real_escape_string($connection_server, $transaction_ref)."'");

                echo "SUCCESS";
            } else {
                echo "ALREADY_PROCESSED";
            }
        }
	}
?>