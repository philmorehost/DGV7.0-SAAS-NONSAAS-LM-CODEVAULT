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
    
    // Check if VoveID is enabled for this vendor
    $voveid_enabled = false;
    $voveid_q = mysqli_query($connection_server, "SELECT option_value FROM sas_vendor_settings WHERE vendor_id='$vendor_id' AND option_name='voveid_enabled' LIMIT 1");
    if ($voveid_q && $r = mysqli_fetch_assoc($voveid_q)) {
        $voveid_enabled = (int)$r['option_value'] === 1;
    }
    
    echo json_encode([
        "status"      => "success",
        "kyc_status"  => (int)$user['kyc_status'],
        "kyc_name"    => $kyc_names[$user['kyc_status']] ?? "Unknown",
        "kyc_verified"=> ($user['kyc_status'] == 2) ? "Yes" : "No",
        "bvn_set"     => !empty($user['bvn']) ? "Yes" : "No",
        "nin_set"     => !empty($user['nin']) ? "Yes" : "No",
        "voveid_enabled" => $voveid_enabled ? "Yes" : "No",
        "voveid_status" => $user['voveid_status'] ?? 'unverified',
    ]);
    exit;
}

// Create VoveID session for mobile app
if ($action === 'voveid_session') {
    // Check if VoveID is enabled for this vendor
    $voveid_enabled = false;
    $voveid_q = mysqli_query($connection_server, "SELECT option_value FROM sas_vendor_settings WHERE vendor_id='$vendor_id' AND option_name='voveid_enabled' LIMIT 1");
    if ($voveid_q && $r = mysqli_fetch_assoc($voveid_q)) {
        $voveid_enabled = (int)$r['option_value'] === 1;
    }
    
    if (!$voveid_enabled) {
        echo json_encode(["status" => "failed", "desc" => "VoveID KYC is not enabled for this vendor"]);
        exit;
    }
    
    $user_id = (int)$user['id'];
    $refId = (string)$user['id'];
    
    // Check if user already has a VoveID ref_id stored
    $refId_q = mysqli_query($connection_server, "SELECT voveid_ref_id FROM sas_users WHERE id='$user_id' AND vendor_id='$vendor_id' LIMIT 1");
    if ($refId_q && $r = mysqli_fetch_assoc($refId_q)) {
        if (!empty($r['voveid_ref_id'])) {
            $refId = $r['voveid_ref_id'];
        }
    }
    
    // Include VoveID client
    require_once __DIR__ . "/../../func/voveid-client.php";
    
    // Create VoveID session
    $result = voveid_create_session($vendor_id, $user_id, $refId);
    
    if ($result['success'] && isset($result['data']['token'])) {
        $sessionToken = $result['data']['token'];
        $sessionId = $result['data']['sessionId'] ?? '';
        $flowId = $result['data']['flowId'] ?? '';
        
        // Store session in database
        $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
        $refIdEsc = mysqli_real_escape_string($connection_server, $refId);
        $sessionTokenEsc = mysqli_real_escape_string($connection_server, $sessionToken);
        $sessionIdEsc = mysqli_real_escape_string($connection_server, $sessionId);
        $flowIdEsc = mysqli_real_escape_string($connection_server, $flowId ?? '');
        
        mysqli_query($connection_server, "
            INSERT INTO sas_voveid_sessions (vendor_id, user_id, ref_id, session_token, session_id, flow_id, status, expires_at)
            VALUES ('$vendor_id', '$user_id', '$refIdEsc', '$sessionTokenEsc', '$sessionIdEsc', '$flowIdEsc', 'created', '$expiresAt')
            ON DUPLICATE KEY UPDATE 
                session_token='$sessionTokenEsc', 
                session_id='$sessionIdEsc', 
                flow_id='$flowIdEsc', 
                status='created', 
                expires_at='$expiresAt',
                updated_at=NOW()
        ");
        
        // Update user's voveid_ref_id if not set
        $refIdCheck = mysqli_query($connection_server, "SELECT voveid_ref_id FROM sas_users WHERE id='$user_id' AND vendor_id='$vendor_id' LIMIT 1");
        if ($refIdCheck && $r = mysqli_fetch_assoc($refIdCheck)) {
            if (empty($r['voveid_ref_id'])) {
                $refIdEsc = mysqli_real_escape_string($connection_server, $refId);
                mysqli_query($connection_server, "UPDATE sas_users SET voveid_ref_id='$refIdEsc' WHERE id='$user_id' AND vendor_id='$vendor_id'");
            }
        }
        
        echo json_encode([
            "status" => "success",
            "token" => $sessionToken,
            "session_id" => $sessionId,
            "ref_id" => $refId,
            "expires_at" => $expiresAt,
        ]);
    } else {
        echo json_encode([
            "status" => "failed",
            "desc" => $result['error'] ?? 'Failed to create VoveID session',
        ]);
    }
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
