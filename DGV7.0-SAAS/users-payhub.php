<?php
/**
 * PayHub Webhook Handler
 * Location: /users-payhub.php
 */

function logPayhub($msg) {
}

session_start();
include_once(__DIR__ . "/func/bc-connect.php");

$body = file_get_contents("php://input");
logPayhub("Incoming Webhook: " . $body);

$catch = json_decode($body, true);
if (!$catch) {
    logPayhub("Invalid payload");
    http_response_code(400);
    exit("Invalid payload");
}

// Support both v2 (event object) and v1 (flat or direct data)
$event = $catch['event'] ?? 'charge.success';
$data = $catch['data'] ?? $catch;
$reference = $data['reference'] ?? '';

if ($event == 'charge.success' || ($catch['status'] ?? '') == 'success' || ($catch['status'] ?? '') == 'successful') {
    logPayhub("Processing success event for reference: $reference");

    // We need to determine the vendor_id to get the correct keys for signature verification if applicable
    // But processPayhubSuccess handles context resolution internally.

    // 1. Context Resolution from metadata
    $meta = [];
    if (!empty($data['metadata'])) {
        $meta = is_array($data['metadata']) ? $data['metadata'] : json_decode($data['metadata'], true);
    }

    $vid = (int)($meta['vendor_id'] ?? 0);
    $target = $meta['target'] ?? 'user';

    // If it's a vendor funding, they pay the platform, so we use Super Admin keys (vid=0)
    // If it's a user funding, they pay their vendor, so we use vendor keys
    $lookup_vid = ($target == 'vendor') ? 0 : $vid;
    $payhub_keys = getGatewayDetails('payhub', $lookup_vid);

    if (!$payhub_keys) {
        logPayhub("CRITICAL: PayHub keys not found for VID $lookup_vid. Aborting.");
        http_response_code(404);
        exit;
    }

    // 2. Process payment and credit wallet
    $username = $meta['username'] ?? '';
    $result_ref = processPayhubSuccess($vid, $reference, $data, $payhub_keys, $username);

    if ($result_ref) {
        logPayhub("Successfully processed $reference. Local Ref: $result_ref");
        http_response_code(200);
        echo "Success";
    } else {
        logPayhub("Failed to process $reference");
        http_response_code(500);
        echo "Processing failed";
    }
} else {
    logPayhub("Ignored event type: $event");
    http_response_code(200);
    echo "Event ignored";
}
?>
