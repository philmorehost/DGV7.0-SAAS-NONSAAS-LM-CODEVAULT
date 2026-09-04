<?php
/**
 * VoveID Webhook Handler
 * Receives verification status updates from VoveID and updates user KYC status
 */

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get raw payload
$payload = file_get_contents('php://input');
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty payload']);
    exit;
}

// Verify webhook signature if secret is configured
// (Implementation depends on VoveID's webhook signing method)

require_once __DIR__ . '/../../func/bc-connect.php';

if (!$connection_server) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Parse payload
$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Log webhook for debugging
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
file_put_contents($logDir . '/voveid_webhook.log', date('Y-m-d H:i:s') . ' - ' . $payload . "\n", FILE_APPEND);

// Process webhook using VoveID client
require_once __DIR__ . '/../../func/voveid-client.php';
$result = voveid_process_webhook($data);

if ($result['success']) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Webhook processed successfully']);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Webhook processing failed']);
}