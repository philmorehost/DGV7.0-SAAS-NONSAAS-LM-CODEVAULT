<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';
session_start();

if (!isset($_GET['ref'])) {
    die('Invalid reference.');
}

$reference = $_GET['ref'];
$pdo = get_db_connection();

// Ensure transaction exists and is pending
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE reference = ? AND type = 'purchase' AND payment_method = 'payhub'");
$stmt->execute([$reference]);
$txn = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$txn) {
    die('Transaction not found or invalid.');
}

if ($txn['status'] === 'completed') {
    // Already processed
    header("Location: /process_order.php?ref=" . urlencode($reference));
    exit;
}

$secret_key = get_setting('payhub_secret_key');

// Verify Transaction via Payhub API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://merchant.payhub.com.ng/api/transaction/verify/" . rawurlencode($reference));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $secret_key,
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    die("cURL Error: " . htmlspecialchars($err));
}

$result = json_decode($response, true);

if ($http_code === 200 && isset($result['status']) && $result['status'] === true) {
    // Transaction successful
    $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE reference = ?")->execute([$reference]);
    
    // Redirect to process the order and fulfill PINs
    header("Location: /process_order.php?ref=" . urlencode($reference));
    exit;
} else {
    // Transaction failed
    $pdo->prepare("UPDATE transactions SET status = 'failed' WHERE reference = ?")->execute([$reference]);
    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE reference = ?")->execute([$reference]);
    
    $error_msg = isset($result['message']) ? $result['message'] : 'Payment failed verification.';
    echo "<h1>Payment Failed</h1>";
    echo "<p>" . htmlspecialchars($error_msg) . "</p>";
    echo "<a href='/catalog.php'>Return to Catalog</a>";
    exit;
}
