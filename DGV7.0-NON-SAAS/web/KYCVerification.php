<?php session_start();
include("../func/bc-config.php");

$username = $get_logged_user_details['username'];
$vid = $get_logged_user_details['vendor_id'];

// Get Vendor Specific KYC Settings
$kyc_settings = [];
$q_kyc = mysqli_query($connection_server, "SELECT verification_name, status FROM sas_kyc_verifications WHERE vendor_id='$vid'");
while($r = mysqli_fetch_assoc($q_kyc)) $kyc_settings[$r['verification_name']] = (int)$r['status'];

$is_kyc_enabled = isKYCEnforced($vid);

// Handle Submissions
if (isset($_POST['submit_bvn_nin'])) {
    $type = ($_POST['type'] == 'nin') ? 'nin' : 'bvn'; // Whitelist to prevent SQL injection
    $value = mysqli_real_escape_string($connection_server, trim($_POST['value']));

    if (strlen($value) < 10) {
        $_SESSION['product_purchase_response'] = "Error: Invalid $type format.";
    } else {
        mysqli_query($connection_server, "UPDATE sas_users SET $type='$value' WHERE id='".$get_logged_user_details['id']."'");
        $_SESSION['product_purchase_response'] = "Success: ".strtoupper($type)." updated successfully.";
    }
    header("Location: KYCVerification.php");
    exit();
}

if (isset($_POST['submit_media'])) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/kyc/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $user_id = $get_logged_user_details['id'];
    $updates = [];
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

    foreach (['govt_id' => 'govt_id_card', 'selfie' => 'kyc_face_image'] as $input_name => $db_col) {
        if (!empty($_FILES[$input_name]['name'])) {
            $ext = strtolower(pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = "kyc_" . $user_id . "_" . $input_name . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $upload_dir . $filename)) {
                    $updates[] = "$db_col = '$filename'";
                }
            }
        }
    }

    if (!empty($updates)) {
        $updates[] = "kyc_status = 1"; // Set to Pending
        $sql = "UPDATE sas_users SET " . implode(", ", $updates) . " WHERE id='$user_id'";
        mysqli_query($connection_server, $sql);
        $_SESSION['product_purchase_response'] = "Identity documents uploaded and submitted for review.";
    } else {
        $_SESSION['product_purchase_response'] = "Error: No valid documents selected.";
    }

    header("Location: KYCVerification.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>KYC Verification | <?php echo $get_all_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        .kyc-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: 0.3s; }
        .kyc-card:hover { transform: translateY(-5px); }
        .status-badge { font-size: 0.75rem; padding: 5px 12px; border-radius: 50px; font-weight: 700; }
    </style>
</head>
<body class="bg-light">
    <?php include("../func/bc-header.php"); ?>

    <div class="pagetitle">
      <h1>IDENTITY VERIFICATION (KYC)</h1>
      <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li><li class="breadcrumb-item active">KYC</li></ol></nav>
    </div>

    <section class="section">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card kyc-card p-4 text-center">
                    <div class="mb-3">
                        <?php if($get_logged_user_details['kyc_status'] == 2): ?>
                            <i class="bi bi-patch-check-fill text-success display-1"></i>
                            <h4 class="fw-bold mt-2">Fully Verified</h4>
                            <p class="text-muted small">Your identity has been confirmed. You have unrestricted access to all services.</p>
                        <?php elseif($get_logged_user_details['kyc_status'] == 1): ?>
                            <i class="bi bi-clock-history text-warning display-1"></i>
                            <h4 class="fw-bold mt-2">Under Review</h4>
                            <p class="text-muted small">Your documents are being processed by our compliance team.</p>
                        <?php else: ?>
                            <i class="bi bi-shield-lock text-primary display-1"></i>
                            <h4 class="fw-bold mt-2">Unverified</h4>
                            <p class="text-muted small">Please complete the required steps below to secure your account.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <?php if(!$is_kyc_enabled): ?>
                    <div class="alert alert-info border-0 rounded-4 shadow-sm p-4">
                        <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i>KYC is Optional</h6>
                        <p class="mb-0 small">The administrator has not enforced mandatory KYC. You can continue using services, but we recommend verifying for enhanced security.</p>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <!-- BVN/NIN Section -->
                    <?php if(($kyc_settings['bvn'] ?? 0) == 1 || ($kyc_settings['nin'] ?? 0) == 1): ?>
                    <div class="col-md-6">
                        <div class="card kyc-card h-100">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-3"><i class="bi bi-fingerprint me-2 text-primary"></i>Basic Verification</h6>
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Select ID Type</label>
                                        <select name="type" class="form-select rounded-3 shadow-sm">
                                            <?php if(($kyc_settings['bvn'] ?? 0) == 1): ?>
                                                <option value="bvn" <?php echo !empty($get_logged_user_details['bvn']) ? 'selected' : ''; ?>>Bank Verification Number (BVN)</option>
                                            <?php endif; ?>
                                            <?php if(($kyc_settings['nin'] ?? 0) == 1): ?>
                                                <option value="nin" <?php echo !empty($get_logged_user_details['nin']) ? 'selected' : ''; ?>>National Identity Number (NIN)</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" name="value" class="form-control rounded-3 shadow-sm" placeholder="Enter 11-digit number" value="<?php echo $get_logged_user_details['bvn'] ?: $get_logged_user_details['nin']; ?>" required>
                                    </div>
                                    <button name="submit_bvn_nin" type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">Update ID</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- AI Interview Section -->
                    <div class="col-md-12">
                        <div class="card kyc-card" style="background: linear-gradient(135deg, #1e1b4b, #312e81); color: white;">
                            <div class="card-body p-4">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="fw-bold mb-2"><i class="bi bi-robot me-2"></i>Titanium AI Interview</h5>
                                        <p class="small text-white-50">Short on time? Complete your KYC by simply talking to our AI Compliance Officer. No forms required.</p>
                                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#aiKycModal">
                                            Start AI Interview
                                        </button>
                                    </div>
                                    <div class="col-md-4 text-center d-none d-md-block">
                                        <i class="bi bi-mic-fill display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- AI KYC Modal -->
    <div class="modal fade" id="aiKycModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold">AI Compliance Interview</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="aiChatLog" class="mb-3 p-3 bg-light rounded-3" style="height: 300px; overflow-y: auto;">
                        <p class="small mb-2"><b>AI:</b> Hello! I'm here to help you complete your KYC. What is your full name as it appears on your ID?</p>
                    </div>
                    <div class="input-group">
                        <input type="text" id="aiKycInput" class="form-control rounded-start-pill border-2" placeholder="Type or speak...">
                        <button class="btn btn-primary rounded-end-pill px-4" onclick="sendToAiKyc()">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let kycHistory = [{role: 'assistant', content: "Hello! I'm here to help you complete your KYC. What is your full name as it appears on your ID?"}];
        
        async function sendToAiKyc() {
            const input = document.getElementById('aiKycInput');
            const log = document.getElementById('aiChatLog');
            const msg = input.value.trim();
            if(!msg) return;

            log.innerHTML += `<p class='small mb-2'><b>You:</b> ${msg}</p>`;
            kycHistory.push({role: 'user', content: msg});
            input.value = '';
            log.scrollTop = log.scrollHeight;

            const response = await fetch('/api/app-backend/ai-kyc-interview.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message: msg, history: kycHistory})
            });
            const data = await response.json();
            
            if(data.success) {
                log.innerHTML += `<p class='small mb-2'><b>AI:</b> ${data.response}</p>`;
                kycHistory.push({role: 'assistant', content: data.response});
                if(data.completed) {
                    input.disabled = true;
                    setTimeout(() => window.location.reload(), 3000);
                }
            }
            log.scrollTop = log.scrollHeight;
        }
    </script>

    <?php include("../func/bc-footer.php"); ?>
</body>
</html>
