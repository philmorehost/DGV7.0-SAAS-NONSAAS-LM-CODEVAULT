<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

// Read incoming JSON payload
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

$log_file = __DIR__ . '/payhub_webhook.log';
$log = function($msg) use ($log_file) {
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
};

$log("Webhook hit. Method: " . $_SERVER['REQUEST_METHOD']);
$log("Payload: " . $input);

if (!$payload) {
    $log("ERROR: Invalid JSON payload");
    http_response_code(400);
    die("Invalid JSON payload");
}

// Security: Verify signature if provided in header (Payhub typically uses payhub-signature)
$secret_key = get_setting('payhub_secret_key');
$signature = $_SERVER['HTTP_X_PAYHUB_SIGNATURE'] ?? $_SERVER['HTTP_PAYHUB_SIGNATURE'] ?? '';
$log("Signature received: " . $signature);
$log("Secret key configured (first 10 chars): " . substr($secret_key, 0, 10));

if (!empty($secret_key) && !empty($signature)) {
    $expected_signature = hash_hmac('sha512', $input, $secret_key);
    $log("Expected signature: " . $expected_signature);
    if ($expected_signature !== $signature) {
        $log("ERROR: Signature mismatch");
        http_response_code(401);
        die("Invalid signature");
    }
    $log("Signature verified successfully");
} else {
    $log("Signature verification skipped (secret or signature header missing)");
}

$pdo = get_db_connection();

// Process Virtual Account Deposit
// Check for standard event names like virtual_account.payment or charge.success
if (isset($payload['event']) && (strpos($payload['event'], 'payment') !== false || strpos($payload['event'], 'transfer') !== false || strpos($payload['event'], 'success') !== false)) {
    $data = $payload['data'] ?? $payload;
    
    // Extract required fields
    $account_number = $data['account_number'] ?? $data['virtual_account_number'] ?? null;
    
    // Paystack payload mapping gotcha: extract account number from inside sub-objects if not at top-level
    if (empty($account_number)) {
        if (isset($data['authorization']['receiver_bank_account_number'])) {
            $account_number = $data['authorization']['receiver_bank_account_number'];
        } elseif (isset($data['metadata']['receiver_account_number'])) {
            $account_number = $data['metadata']['receiver_account_number'];
        }
    }

    // Paystack amounts are in kobo. Convert to Naira.
    $amount = ($data['amount'] ?? 0) / 100;
    $reference = $data['reference'] ?? $data['tx_ref'] ?? 'VACC_'.time().rand(100,999);
    
    $log("Mapped fields: account_number=$account_number, amount=$amount, reference=$reference");

    if ($account_number && $amount > 0) {
        // Find user by virtual account number
        $stmt = $pdo->prepare("SELECT id, wallet_balance FROM users WHERE virtual_account_number = ? LIMIT 1");
        $stmt->execute([$account_number]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $log("Matched user ID: " . $user['id'] . ", current balance: " . $user['wallet_balance']);
            // Check if transaction was already processed
            $check = $pdo->prepare("SELECT id FROM transactions WHERE reference = ?");
            $check->execute([$reference]);
            if (!$check->fetch()) {
                // Determine actual amount
                $final_amount = $amount; 
                
                // Credit user wallet
                $pdo->beginTransaction();
                try {
                    $new_balance = $user['wallet_balance'] + $final_amount;
                    
                    // Update user balance
                    $update = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                    $update->execute([$new_balance, $user['id']]);
                    
                    // Record transaction
                    $insert = $pdo->prepare("INSERT INTO transactions (user_id, reference, type, amount, status, payment_method) VALUES (?, ?, 'deposit', ?, 'completed', 'payhub_transfer')");
                    $insert->execute([$user['id'], $reference, $final_amount]);
                    
                    $pdo->commit();
                    $log("SUCCESS: Wallet credited successfully with $final_amount");
                    http_response_code(200);
                    echo json_encode(["status" => true, "message" => "Wallet credited successfully"]);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $log("ERROR: Database exception: " . $e->getMessage());
                    http_response_code(500);
                    die("Database error");
                }
            } else {
                $log("INFO: Transaction $reference already processed previously");
                http_response_code(200);
                die("Transaction already processed");
            }
        } else {
            $log("ERROR: No user found in DB with virtual_account_number: $account_number");
        }
    } else {
        $log("ERROR: Missing account number or amount <= 0");
    }
} else {
    $log("INFO: Event type is not charge.success or related. Event: " . ($payload['event'] ?? 'none'));
}

// Acknowledge receipt for unknown events
$log("INFO: Request acknowledged and exited (default behavior)");
http_response_code(200);
echo json_encode(["status" => true, "message" => "Webhook received"]);
