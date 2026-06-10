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
