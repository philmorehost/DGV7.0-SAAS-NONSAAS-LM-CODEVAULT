<?php
// php-version/verify.php
require_once 'includes/functions.php';

$ref = sanitize($_GET['reference'] ?? '');

if (!$ref) redirect('index.php');

$db = Database::connect();
$stmt = $db->prepare("SELECT * FROM transactions WHERE reference = ?");
$stmt->execute([$ref]);
$tx = $stmt->fetch();

$status = 'pending';
$amount = 0;

// Detect if it's an invoice payment or standard transaction
$is_invoice = (strpos($ref, 'INV_') === 0);
$is_test_mode = false;

if ($tx) {
    $is_test_mode = (bool)$tx['is_test'];
} elseif ($is_invoice) {
    // For invoices, we determine test mode from the invoice's merchant
    $parts = explode('_', $ref);
    if (count($parts) >= 2) {
        $inv_ref = $parts[1];
        $stmt = $db->prepare("SELECT u.is_test_mode FROM invoices i JOIN users u ON i.user_id = u.id WHERE i.reference = ?");
        $stmt->execute([$inv_ref]);
        $m = $stmt->fetch();
        if ($m) $is_test_mode = (bool)$m['is_test_mode'];
    }
}

// For sandbox-simulated transactions that are already 'success' in our DB,
// trust the local record and skip the real Paystack network call.
if ($tx && $tx['status'] === 'success' && (bool)$tx['is_test']) {
    $status = 'success';
    $amount = (float)$tx['amount'];
} else {
    // Call Paystack to verify
    $res = paystack_call("transaction/verify/" . $ref, 'GET', [], $is_test_mode);

    if ($res && $res['status'] && $res['data']['status'] === 'success') {
        $status = 'success';
        $amount = $res['data']['amount'] / 100;

        // If transaction doesn't exist (e.g. direct invoice payment), create it
        if (!$tx) {
            $db->beginTransaction();
            try {
                $merchant_id = null;
                $invoice_id = null;
                $customer_email = $res['data']['customer']['email'];
                $customer_name = trim(($res['data']['customer']['first_name'] ?? '') . ' ' . ($res['data']['customer']['last_name'] ?? ''));

                if ($is_invoice) {
                    $parts = explode('_', $ref);
                    $inv_ref = $parts[1];
                    $stmt = $db->prepare("SELECT id, user_id FROM invoices WHERE reference = ?");
                    $stmt->execute([$inv_ref]);
                    $inv_data = $stmt->fetch();
                    if ($inv_data) {
                        $invoice_id = $inv_data['id'];
                        $merchant_id = $inv_data['user_id'];
                    }
                }

                if ($merchant_id) {
                    $stmt = $db->prepare("INSERT INTO transactions (user_id, reference, amount, customer_email, customer_name, status, is_test, invoice_id) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
                    $stmt->execute([$merchant_id, $ref, $amount, $customer_email, $customer_name, $is_test_mode ? 1 : 0, $invoice_id]);
                    $tx_id = $db->lastInsertId();

                    // Fetch the new tx to proceed with standard logic
                    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
                    $stmt->execute([$tx_id]);
                    $tx = $stmt->fetch();
                    $db->commit();
                } else {
                    $db->rollBack();
                }
            } catch (Exception $e) {
                $db->rollBack();
            }
        }

        if ($tx && $tx['status'] === 'pending') {
            $status = 'success';
            $amount = $res['data']['amount'] / 100;

            if ($tx['status'] === 'pending') {
                $db->beginTransaction();
                try {
                    // Update transaction
                    $stmt = $db->prepare("UPDATE transactions SET status = 'success', gateway_reference = ? WHERE id = ?");
                    $stmt->execute([$res['data']['id'], $tx['id']]);

                    // Calculate fees
                    $is_intl = ($res['data']['currency'] !== 'NGN');
                    $fee = calculate_fees($amount, $is_intl, $tx['user_id']);
                    $settled = $amount - $fee;

                    $stmt = $db->prepare("UPDATE transactions SET fee_amount = ?, settled_amount = ? WHERE id = ?");
                    $stmt->execute([$fee, $settled, $tx['id']]);

                    // Update or Create Customer record
                    $stmt = $db->prepare("SELECT id FROM customers WHERE user_id = ? AND email = ?");
                    $stmt->execute([$tx['user_id'], $tx['customer_email']]);
                    if (!$stmt->fetch()) {
                        $stmt = $db->prepare("INSERT INTO customers (user_id, full_name, email) VALUES (?, ?, ?)");
                        $stmt->execute([$tx['user_id'], $tx['customer_name'] ?: 'Guest Customer', $tx['customer_email']]);
                    }

                    // Handle Invoice Payment
                    $inv_id = $tx['invoice_id'] ?: ($res['data']['metadata']['invoice_id'] ?? null);
                    if ($inv_id) {
                        $db->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$inv_id]);
                        if (!$tx['invoice_id']) {
                            $db->prepare("UPDATE transactions SET invoice_id = ? WHERE id = ?")->execute([$inv_id, $tx['id']]);
                        }
                    }

                    // Log ledger and update user balance (prevent real crediting for test mode)
                    $is_test_tx = (bool)$tx['is_test'] || ($res['data']['domain'] === 'test');
                    log_ledger_entry($tx['user_id'], $settled, 'credit', 'payment', "Payment verified for Ref: $ref", $is_test_tx);

                    log_transaction_event($tx['id'], 'verified', "Payment verified via direct lookup.");

                    // Trigger Webhook if configured
                    $stmt = $db->prepare("SELECT webhook_url FROM users WHERE id = ?");
                    $stmt->execute([$tx['user_id']]);
                    $webhook_url = $stmt->fetchColumn();

                    if ($webhook_url) {
                        $payload = [
                            'event' => 'charge.success',
                            'data' => [
                                'id' => $tx['id'],
                                'reference' => $tx['reference'],
                                'amount' => $tx['amount'],
                                'status' => 'success',
                                'customer' => [
                                    'email' => $tx['customer_email'],
                                    'name' => $tx['customer_name']
                                ],
                                'metadata' => $res['data']['metadata'] ?? []
                            ]
                        ];
                        $ch = curl_init($webhook_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                        curl_exec($ch);
                        curl_close($ch);
                    }

                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                }
            }
        }
    } else {
        if ($res && isset($res['data']['status']) && $res['data']['status'] === 'failed') {
            $status = 'failed';
        }
    }
}

include 'includes/header.php';
?>
<div class="pt-32 pb-20 bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden text-center p-12">
        <?php if ($status === 'success'): ?>
            <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="lucide-check-circle-2 w-10 h-10"></i>
            </div>
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Payment Successful!</h2>
            <p class="text-slate-500 mb-8">Thank you for your payment. Your transaction reference is <strong><?php echo $ref; ?></strong>.</p>
        <?php elseif ($status === 'failed'): ?>
            <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="lucide-x-circle w-10 h-10"></i>
            </div>
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Payment Failed</h2>
            <p class="text-slate-500 mb-8">We could not process your payment. Please try again or contact support.</p>
        <?php else: ?>
            <div class="w-20 h-20 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-6 animate-pulse">
                <i class="lucide-refresh-ccw w-10 h-10"></i>
            </div>
            <h2 class="text-3xl font-bold text-slate-900 mb-2">Verifying Payment...</h2>
            <p class="text-slate-500 mb-8">Please wait while we confirm your transaction with the bank.</p>
            <script>setTimeout(() => window.location.reload(), 3000);</script>
        <?php endif; ?>

        <a href="index.php" class="inline-block bg-slate-900 text-white px-8 py-3 rounded-xl font-bold hover:bg-slate-800 transition-all">Return Home</a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
