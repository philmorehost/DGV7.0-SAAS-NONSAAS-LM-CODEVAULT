<?php
// php-version/webhook-paystack.php
require_once 'includes/functions.php';

$input = file_get_contents("php://input");
$event = json_decode($input, true);

// Start Logging for Debugging
$log_payload = [
    'time' => date('Y-m-d H:i:s'),
    'event' => $event['event'] ?? 'unknown',
    'full_input' => $event
];
file_put_contents('webhook_debug.log', json_encode($log_payload) . PHP_EOL, FILE_APPEND);

if (!$event) {
    http_response_code(400);
    die('No input');
}

// Verify Paystack IP Address
$paystack_ips = ['52.31.139.75', '52.49.173.169', '52.214.14.220'];
$request_ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($request_ip, $paystack_ips) && getConfig('webhook_ip_check', '0') === '1') {
    file_put_contents('webhook_debug.log', "IP Verification Failed: " . $request_ip . PHP_EOL, FILE_APPEND);
    http_response_code(403);
    die('Unauthorized IP address');
}

// Verify Paystack Signature.
// The key that signed is recorded, not merely the fact that a signature was
// valid: signing with the TEST secret must not be able to speak for a LIVE
// transaction. An empty secret would make the HMAC computable by anyone, so it
// is rejected explicitly rather than signed against ''. hash_equals() keeps the
// comparison constant-time.
$paystack_secret = getConfig('paystack_secret_key');
$paystack_test_secret = getConfig('paystack_test_secret_key');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

$verified_with = null;
if ($signature !== '' && $paystack_secret !== ''
        && hash_equals(hash_hmac('sha512', $input, $paystack_secret), $signature)) {
    $verified_with = 'live';
} elseif ($signature !== '' && $paystack_test_secret !== ''
        && hash_equals(hash_hmac('sha512', $input, $paystack_test_secret), $signature)) {
    $verified_with = 'test';
}

if ($verified_with === null) {
    file_put_contents('webhook_debug.log', "Signature Verification Failed" . PHP_EOL, FILE_APPEND);
    http_response_code(401);
    die('Invalid signature');
}

// If one key is configured for both live and test, the signature cannot tell us
// which mode the event belongs to. In that case mode agreement cannot be
// enforced and the legacy fallback to the payload's `domain` field is kept, so a
// sandbox install sharing a single key keeps working.
$mode_ambiguous = ($paystack_secret !== '' && $paystack_secret === $paystack_test_secret);

$db = Database::connect();

if ($event['event'] === 'charge.success') {
    $data = $event['data'];
    $ref = $data['reference'];
    $amount = $data['amount'] / 100;
    $currency = $data['currency'];

    // Find transaction by reference OR gateway reference
    $stmt = $db->prepare("SELECT * FROM transactions WHERE reference = ? OR gateway_reference = ?");
    $stmt->execute([$ref, $data['id']]);
    $tx = $stmt->fetch();

    // If no transaction found, check if it's a payment to a dedicated virtual account
    if (!$tx) {
        $acc_number = null;
        // Check standard structures for dedicated account payments
        if (isset($data['dedicated_combined_account']['account_number'])) {
            $acc_number = $data['dedicated_combined_account']['account_number'];
        } elseif (isset($data['customer']['dedicated_account'])) {
            $acc_number = $data['customer']['dedicated_account'];
        } elseif (isset($data['authorization']['receiver_bank_account_number'])) {
            $acc_number = $data['authorization']['receiver_bank_account_number'];
        } elseif (isset($data['receiver_bank_account_number'])) {
            $acc_number = $data['receiver_bank_account_number'];
        } elseif (isset($data['authorization']['account_number'])) {
            $acc_number = $data['authorization']['account_number'];
        }

        // Check metadata if Paystack didn't explicitly label it as dedicated_account
        if (!$acc_number && isset($data['metadata']['receiver_account_number'])) {
            $acc_number = $data['metadata']['receiver_account_number'];
        }

        if ($acc_number) {
            // Clean account number (Paystack sometimes sends it with leading zeros or slightly different)
            $clean_acc = ltrim($acc_number, '0');
            $stmt = $db->prepare("SELECT * FROM virtual_accounts WHERE account_number = ? OR account_number = ? OR account_number = ?");
            $stmt->execute([$acc_number, str_pad($clean_acc, 10, '0', STR_PAD_LEFT), $clean_acc]);
            $va = $stmt->fetch();

            if ($va) {
                // Which Paystack key signed this payload is authoritative. The
                // payload's own `domain` field is data chosen by whoever signed,
                // so it must not decide whether this row credits real money -
                // except when a single key serves both modes, where it is the
                // only signal available.
                $is_test_va = $mode_ambiguous ? ($data['domain'] === 'test') : ($verified_with === 'test');

                // Recover metadata if missing or lacks custom fields
                $va_meta = json_decode($va['metadata'] ?? '[]', true);
                if (!is_array($va_meta)) {
                    $va_meta = [];
                }
                $rtx_meta = $data['metadata'] ?? [];
                if (!is_array($rtx_meta)) {
                    $rtx_meta = [];
                }
                // Merge, prioritizing rtx_meta for gateway fields but keeping VA's custom fields
                $recovered_metadata = array_merge($va_meta, $rtx_meta);
                if (is_array($recovered_metadata)) $recovered_metadata = json_encode($recovered_metadata);

                // Create a pending transaction for this VA payment
                // Using INSERT IGNORE in case webhook is retried quickly
                $stmt = $db->prepare("INSERT IGNORE INTO transactions (user_id, reference, amount, status, customer_email, payment_method, is_test, metadata) VALUES (?, ?, ?, 'pending', ?, 'bank_transfer', ?, ?)");
                $stmt->execute([$va['user_id'], $ref, $amount, $va['customer_email'], $is_test_va ? 1 : 0, $recovered_metadata]);

                $stmt = $db->prepare("SELECT * FROM transactions WHERE reference = ?");
                $stmt->execute([$ref]);
                $tx = $stmt->fetch();

                file_put_contents('webhook_debug.log', "Matched Virtual Account: $acc_number for user " . $va['user_id'] . " (Test: ".($is_test_va?'Yes':'No').")" . PHP_EOL, FILE_APPEND);
            } else {
                file_put_contents('webhook_debug.log', "Dedicated Account payment but NO MATCH in DB: $acc_number" . PHP_EOL, FILE_APPEND);
            }
        }
    }

    if ($tx && $tx['status'] !== 'success') {
        /*
         * SECURITY: the amount the customer actually settled must equal the
         * amount recorded against this reference. checkout.php renders that
         * amount into the page that drives Paystack Inline, so a customer can
         * rewrite it in the browser - or bypass the page entirely by calling
         * Paystack's API with the same reference - and pay a token sum while the
         * stored record still holds the larger figure.
         *
         * Without this guard the transaction was marked successful and the
         * merchant's webhook announced the unverified amount, so the merchant
         * credited a wallet that was never funded.
         */
        if (!amounts_match($tx['amount'], $amount)) {
            $mismatch = flag_amount_mismatch($tx, $tx['amount'], $amount, 'paystack webhook');
            file_put_contents('webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] " . $mismatch . PHP_EOL, FILE_APPEND);
            // Acknowledge so Paystack stops retrying a payload we will never accept.
            http_response_code(200);
            echo "Amount mismatch - transaction not fulfilled";
            exit;
        }

        /*
         * SECURITY: the signature proves which Paystack key signed this payload;
         * the transaction's own flag was set at initialize time from the secret
         * key the merchant used. When they disagree the event cannot be trusted
         * for this transaction - a test-key signature must never be able to speak
         * for a live one, which would let a leaked test secret credit a real
         * wallet.
         */
        $tx_is_test = ((int)$tx['is_test'] === 1);
        if (!$mode_ambiguous && (($verified_with === 'test') !== $tx_is_test)) {
            $msg = "BLOCKED signature/transaction mode mismatch for Ref: $ref"
                 . " (payload signed with the {$verified_with} key but the transaction has is_test=" . (int)$tx['is_test'] . ")";
            log_transaction_event($tx['id'], 'mode_mismatch', $msg);
            error_log('[PayHub] ' . $msg);
            file_put_contents('webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
            http_response_code(200);
            echo "Signature/transaction mode mismatch - transaction not fulfilled";
            exit;
        }

        $db->beginTransaction();
        try {
            // Atomically claim this transaction before moving any money. A
            // concurrent worker - a Paystack retry, or the merchant polling
            // api/transaction/verify.php - that already fulfilled it makes this
            // affect 0 rows, so the merchant wallet is never credited twice.
            if (!claim_transaction_for_fulfilment($tx['id'])) {
                $db->rollBack();
                file_put_contents('webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Tx ID " . $tx['id'] . " already fulfilled by another worker - not crediting again." . PHP_EOL, FILE_APPEND);
                http_response_code(200);
                echo "Already processed";
                exit;
            }

            // Detailed Logging of fulfillment start
            file_put_contents('webhook_debug.log', "[" . date('Y-m-d H:i:s') . "] Fulfilling Tx ID: " . $tx['id'] . " for Amount: " . $amount . PHP_EOL, FILE_APPEND);

            // Update transaction
            $stmt = $db->prepare("UPDATE transactions SET currency = ?, gateway_reference = ? WHERE id = ?");
            $stmt->execute([$currency, $data['id'], $tx['id']]);

            // Persist the gateway-confirmed figure so every later integrity check
            // (and the merchant webhook) works from evidence, not from the request.
            record_gateway_amount($tx['id'], $amount);

            // Calculate fees
            $is_intl = ($currency !== 'NGN');
            $fee = calculate_fees($amount, $is_intl, $tx['user_id']);
            $settled = $amount - $fee;

            $stmt = $db->prepare("UPDATE transactions SET fee_amount = ?, settled_amount = ?, payment_method = ? WHERE id = ?");
            $stmt->execute([$fee, $settled, $data['channel'] ?? $tx['payment_method'], $tx['id']]);

            // Log ledger and update user balance (prevent real crediting for test mode)
            // The signing key is the authority on test vs live - never the payload's own domain field.
            $is_test_tx = $mode_ambiguous
                ? ((bool)$tx['is_test'] || ($data['domain'] === 'test'))
                : $tx_is_test;
            log_ledger_entry($tx['user_id'], $settled, 'credit', 'payment', "Payment received for Ref: $ref", $is_test_tx);

            log_transaction_event($tx['id'], 'payment_completed', 'Payment successfully processed and confirmed via Webhook');

            $db->commit();
            $fulfillment_success = true;

        } catch (Exception $e) {
            $db->rollBack();
            $fulfillment_success = false;
            file_put_contents('webhook_debug.log', "Transaction Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        if ($fulfillment_success) {
            // Notify Merchant & Forward Webhook (Outside Transaction to prevent timeouts/locks)
            $stmt = $db->prepare("SELECT email, business_name, webhook_url, secret_key, test_secret_key FROM users WHERE id = ?");
            $stmt->execute([$tx['user_id']]);
            $m = $stmt->fetch();
 
            if ($m) {
                sendEmail($m['email'], "New Payment Received", "<h2>Payment Confirmed</h2><p>You have received a payment of <strong>".formatCurrency($amount)."</strong>.</p><p>Reference: $ref</p>");
 
                // Forward Webhook to Merchant Site
                if (!empty($m['webhook_url'])) {
                    // Recover metadata for payload - merge what we have in DB with what came in
                    $db_meta = json_decode($tx['metadata'] ?? '[]', true);
                    $final_metadata = array_merge($db_meta, $data['metadata'] ?? []);
 
                    $payload = [
                        'event' => 'charge.success',
                        'data' => array_merge($data, [
                            'metadata' => $final_metadata
                        ])
                    ];
 
                    $webhook_secret = ($tx['is_test'] == 1) ? ($m['test_secret_key'] ?? '') : ($m['secret_key'] ?? '');
                    if (empty($webhook_secret)) {
                        $webhook_secret = getConfig('payhub_webhook_secret', 'secret');
                    }
                    $signature = hash_hmac('sha512', json_encode($payload), $webhook_secret);
 
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $m['webhook_url']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'X-Payhub-Signature: ' . $signature
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    // Log Webhook Forwarding
                    try {
                        $stmtLog = $db->prepare("INSERT INTO webhook_logs (user_id, event_type, payload, response_code) VALUES (?, ?, ?, ?)");
                        $stmtLog->execute([$tx['user_id'], 'charge.success', json_encode($payload), (int)$code]);
                    } catch (\Throwable $t) {}
                }
            }
        }
    }
} elseif ($event['event'] === 'refund.processed') {
    $data = $event['data'];
    $ref = $data['transaction_reference'];

    $stmt = $db->prepare("SELECT id FROM transactions WHERE gateway_reference = ? OR reference = ?");
    $stmt->execute([$data['transaction_id'], $ref]);
    $tx = $stmt->fetch();

    if ($tx) {
        log_transaction_event($tx['id'], 'refund_completed', "Refund of " . formatCurrency($data['amount']/100) . " has been completed by Paystack.");
    }
} elseif ($event['event'] === 'dispute.create') {
    $data = $event['data'];
    $stmt = $db->prepare("SELECT id, user_id FROM transactions WHERE gateway_reference = ?");
    $stmt->execute([$data['transaction_id']]);
    $tx = $stmt->fetch();

    if ($tx) {
        $stmt = $db->prepare("INSERT INTO disputes (user_id, transaction_id, reason, status) VALUES (?, ?, ?, 'open')");
        $stmt->execute([$tx['user_id'], $tx['id'], $data['reason'] ?? 'Chargeback initiated']);
        log_transaction_event($tx['id'], 'dispute_opened', "A chargeback dispute has been opened for this transaction.");
    }
}

http_response_code(200);
echo "Webhook processed";
