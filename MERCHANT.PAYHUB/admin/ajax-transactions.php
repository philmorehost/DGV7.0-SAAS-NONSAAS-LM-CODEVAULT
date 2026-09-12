<?php
// php-version/admin/ajax-transactions.php
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// Debugging: ensure the response is always JSON
header('Content-Type: application/json');

$db = Database::connect();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$merchant_id = $_GET['merchant_id'] ?? '';
$mismatch = $_GET['mismatch'] ?? '';
$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

$query = "SELECT t.*, u.business_name 
          FROM transactions t 
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (t.reference LIKE ? OR t.customer_email LIKE ? OR u.business_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status)) {
    $query .= " AND t.status = ?";
    $params[] = $status;
}

if (!empty($merchant_id)) {
    $query .= " AND t.user_id = ?";
    $params[] = $merchant_id;
}

// Integrity review: transactions that were credited at a different amount than requested,
// or blocked outright. failure_reason is written only by the integrity guards
// (amount_mismatch / mode_mismatch) and by the expiry cron (expired), so this is the
// filter for "something was wrong here" as opposed to "nothing was ever paid".
if ($mismatch === '1') {
    $query .= " AND ((t.gateway_amount IS NOT NULL AND t.gateway_amount <> t.amount) OR t.failure_reason IS NOT NULL)";
}

$query .= " ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    $countQuery = "SELECT COUNT(*) as total FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";
    $countParams = [];
    if (!empty($search)) {
        $countQuery .= " AND (t.reference LIKE ? OR t.customer_email LIKE ? OR u.business_name LIKE ?)";
        $searchTerm = "%$search%";
        $countParams[] = $searchTerm; $countParams[] = $searchTerm; $countParams[] = $searchTerm;
    }
    if (!empty($status)) {
        $countQuery .= " AND t.status = ?";
        $countParams[] = $status;
    }
    if (!empty($merchant_id)) {
        $countQuery .= " AND t.user_id = ?";
        $countParams[] = $merchant_id;
    }
    if ($mismatch === '1') {
        $countQuery .= " AND ((t.gateway_amount IS NOT NULL AND t.gateway_amount <> t.amount) OR t.failure_reason IS NOT NULL)";
    }
    $cStmt = $db->prepare($countQuery);
    $cStmt->execute($countParams);
    $totalRecords = $cStmt->fetch()['total'] ?? 0;

    echo json_encode([
        'status' => true,
        'data' => $transactions,
        'pagination' => [
            'total' => (int)$totalRecords,
            'per_page' => $limit,
            'current_page' => ($offset / $limit) + 1,
            'total_pages' => ceil($totalRecords / $limit)
        ]
    ]);
} catch (\Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
