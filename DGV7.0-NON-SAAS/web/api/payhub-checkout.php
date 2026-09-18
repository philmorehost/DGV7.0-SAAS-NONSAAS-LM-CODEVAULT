<?php
/**
 * Server-side Payhub checkout initializer for the Android app.
 * The app opens this URL in a WebView; it initializes a Payhub session
 * and redirects directly to the Payhub-hosted checkout page.
 */
header("Content-Type: text/html; charset=UTF-8");
include_once("../../func/bc-connect.php");

$reference = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["reference"] ?? '')));
$amount    = (float)($_GET["amount"] ?? 0);
$email     = htmlspecialchars(trim($_GET["email"] ?? ''), ENT_QUOTES);
$name      = htmlspecialchars(trim($_GET["name"]  ?? ''), ENT_QUOTES);

if (empty($reference) || $amount <= 0) {
    echo '<p style="color:red;font-family:sans-serif;padding:20px;">Invalid checkout parameters.</p>';
    exit;
}

$vendor_id = resolveVendorID();

// This endpoint is opened straight from the mobile app's WebView without an api_key, so the
// reference is the only handle available. Require it to be a pending funding row for THIS vendor and
// take the amount from that row: previously any caller could initialize a PayHub checkout for an
// arbitrary amount by simply crafting "?reference=..&amount=.." (and the app-visible amount was
// trusted over the recorded one).
$q_payhub_pending = mysqli_query($connection_server, "SELECT amount FROM sas_transactions WHERE vendor_id='$vendor_id' AND reference='$reference' AND status='2' ORDER BY id DESC LIMIT 1");
$payhub_pending = $q_payhub_pending ? mysqli_fetch_assoc($q_payhub_pending) : null;
if (!$payhub_pending || (float)$payhub_pending['amount'] <= 0) {
    echo '<p style="color:red;font-family:sans-serif;padding:20px;">Invalid or already-completed funding reference.</p>';
    exit;
}
$amount = (float)$payhub_pending['amount'];

$callback_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . $_SERVER['HTTP_HOST'] . '/web/payhub-success.php';

$res_json = makePayhubRequest("POST", "api/transaction/initialize", [
    "email"        => $email,
    "amount"       => $amount,
    "name"         => $name,
    "reference"    => $reference,
    "callback_url" => $callback_url,
    "metadata"     => json_encode([
        "vendor_id" => $vendor_id,
        "reference" => $reference,
        "source"    => "android-app"
    ])
], $vendor_id, false);

$res = json_decode($res_json, true);
$inner = isset($res['json_result']) ? json_decode($res['json_result'], true) : $res;
$url = $inner['data']['authorization_url']
    ?? ($inner['authorization_url']
    ?? ($inner['data']['checkout_url']
    ?? ($inner['checkout_url'] ?? '')));

if (!empty($url)) {
    header("Location: " . $url);
    exit;
}

$err = htmlspecialchars($res['message'] ?? 'Initialization failed', ENT_QUOTES);
echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:sans-serif;padding:20px;text-align:center;">
<p style="color:red;">Could not initialize PayHub checkout: ' . $err . '</p>
<p><a href="javascript:history.back()">Go Back</a></p>
</body></html>';
mysqli_close($connection_server);
