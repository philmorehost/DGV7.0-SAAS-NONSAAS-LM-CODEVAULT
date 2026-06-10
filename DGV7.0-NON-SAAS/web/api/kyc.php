<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With, X-App-Source");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once("../../func/bc-connect.php");

$vendor_id = resolveVendorID();
$select_vendor_table = mysqli_fetch_array(mysqli_query($connection_server,
    "SELECT * FROM sas_vendors WHERE id='$vendor_id' AND status=1 LIMIT 1"));
if (!$select_vendor_table) {
    echo json_encode(["status" => "failed", "desc" => "Vendor not found"]);
    exit;
}

$api_key = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST['api_key'] ?? '')));
if (empty($api_key)) {
    echo json_encode(["status" => "failed", "desc" => "Missing API key"]);
    exit;
}

$user_q = mysqli_query($connection_server,
    "SELECT * FROM sas_users WHERE vendor_id='$vendor_id' AND api_key='$api_key' AND status=1 LIMIT 1");
$user = mysqli_fetch_assoc($user_q);
if (!$user) {
    echo json_encode(["status" => "failed", "desc" => "Unauthorized"]);
    exit;
}

$action = trim(strip_tags($_POST['action'] ?? ''));

// Return current KYC status
if ($action === 'status') {
    $kyc_names = [0 => "Unverified", 1 => "Under Review", 2 => "Verified", 3 => "Rejected"];
    echo json_encode([
        "status"      => "success",
        "kyc_status"  => (int)$user['kyc_status'],
        "kyc_name"    => $kyc_names[$user['kyc_status']] ?? "Unknown",
        "kyc_verified"=> ($user['kyc_status'] == 2) ? "Yes" : "No",
        "bvn_set"     => !empty($user['bvn']) ? "Yes" : "No",
        "nin_set"     => !empty($user['nin']) ? "Yes" : "No",
    ]);
    exit;
}

// Submit BVN or NIN
if ($action === 'submit_bvn_nin') {
    $type  = ($_POST['type'] ?? '') === 'nin' ? 'nin' : 'bvn';
    $value = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST['value'] ?? '')));
    if (strlen($value) < 10) {
        echo json_encode(["status" => "failed", "desc" => "Invalid $type format"]);
        exit;
    }
    mysqli_query($connection_server,
        "UPDATE sas_users SET $type='$value' WHERE id='".(int)$user['id']."'");
    echo json_encode(["status" => "success", "desc" => strtoupper($type)." saved successfully"]);
    exit;
}

// Upload document / selfie (multipart)
if ($action === 'upload_document') {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/kyc/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $user_id = (int)$user['id'];
    $updates = [];
    $allowed = ['jpg', 'jpeg', 'png'];

    foreach (['govt_id' => 'govt_id_card', 'selfie' => 'kyc_face_image'] as $input_name => $db_col) {
        if (!empty($_FILES[$input_name]['name']) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;
            $filename = "kyc_{$user_id}_{$input_name}_" . time() . ".$ext";
            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $upload_dir . $filename)) {
                $fn_esc = mysqli_real_escape_string($connection_server, $filename);
                $updates[] = "$db_col='$fn_esc'";
            }
        }
    }

    if (!empty($updates)) {
        $updates[] = "kyc_status=1"; // Pending review
        mysqli_query($connection_server,
            "UPDATE sas_users SET " . implode(", ", $updates) . " WHERE id='$user_id'");
        echo json_encode(["status" => "success", "desc" => "Documents submitted for review"]);
    } else {
        echo json_encode(["status" => "failed", "desc" => "No valid documents received"]);
    }
    exit;
}

echo json_encode(["status" => "failed", "desc" => "Unknown action"]);
