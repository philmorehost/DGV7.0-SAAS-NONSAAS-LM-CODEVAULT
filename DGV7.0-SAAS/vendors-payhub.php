<?php
/**
 * PayHub Webhook Handler (Vendors/Platform)
 * Location: /vendors-payhub.php
 */

function logPayhubVendor($msg) {
}

session_start();
include_once(__DIR__ . "/func/bc-connect.php");

$body = file_get_contents("php://input");
logPayhubVendor("Incoming Vendor Webhook: " . $body);

$catch = json_decode($body, true);
if (!$catch) {
    logPayhubVendor("Invalid payload");
    http_response_code(400);
    exit("Invalid payload");
}

// Support both v2 (event object) and v1 (flat or direct data)
$event = $catch['event'] ?? 'charge.success';
$data = $catch['data'] ?? $catch;
$reference = $data['reference'] ?? '';

if ($event == 'charge.success' || ($catch['status'] ?? '') == 'success' || ($catch['status'] ?? '') == 'successful') {
    logPayhubVendor("Processing success event for reference: $reference");

    // For vendor payments, they are paying the platform, so we use Super Admin keys (vid=0)
    $payhub_keys = getGatewayDetails('payhub', 0);

    if (!$payhub_keys) {
        logPayhubVendor("CRITICAL: PayHub keys not found for Super Admin. Aborting.");
        http_response_code(404);
        exit;
    }

    // Process payment and credit vendor wallet
    // metadata is expected to contain "target" => "vendor" and "vendor_id"
    $meta = [];
    if (!empty($data['metadata'])) {
        $meta = is_array($data['metadata']) ? $data['metadata'] : json_decode($data['metadata'], true);
    }

    $vid = (int)($meta['vendor_id'] ?? 0);
    $result_ref = processPayhubSuccess($vid, $reference, $data, $payhub_keys);

    if ($result_ref) {
        logPayhubVendor("Successfully processed $reference. Local Ref: $result_ref");
        http_response_code(200);
        echo "Success";
    } else {
        logPayhubVendor("Failed to process $reference");
        http_response_code(500);
        echo "Processing failed";
    }
} else {
    logPayhubVendor("Ignored event type: $event");
    http_response_code(200);
    echo "Event ignored";
}
?>
