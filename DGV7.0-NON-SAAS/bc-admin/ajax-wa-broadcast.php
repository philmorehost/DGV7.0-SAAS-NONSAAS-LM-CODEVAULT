<?php session_start();
include("../func/bc-admin-config.php");
include("../func/bc-whatsapp.php");
require_once("../func/bc-giftcard-func.php"); // For Live USD to NGN rate

header('Content-Type: application/json');

$vid = $get_logged_admin_details['id'];
if (!$vid) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$phones = $input['phones'] ?? [];
$message = $input['message'] ?? '';

if (empty($phones) || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Missing recipients or message body']);
    exit();
}

$count = count($phones);
if ($count > 200) {
    echo json_encode(['success' => false, 'error' => 'Maximum 200 recipients allowed per batch.']);
    exit();
}

// STANDALONE: Campaigns are free and unlimited for the admin
$total_cost = 0;
$ref = "WA_BRD_" . time() . "_" . rand(100, 999);

// 5. Dispatch Messages
$sent = 0;
$failed = 0;
foreach ($phones as $phone) {
    if (sendWhatsAppAlert($phone, $message)) {
        $sent++;
    } else {
        $failed++;
    }
    usleep(100000); // 100ms delay
}

echo json_encode([
    'success' => true,
    'sent' => $sent,
    'failed' => $failed,
    'cost' => number_format($total_cost, 2)
]);
