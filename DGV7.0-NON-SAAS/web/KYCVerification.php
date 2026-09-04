<?php session_start();
include("../func/bc-config.php");

$username = $get_logged_user_details['username'];
$vid = $get_logged_user_details['vendor_id'];

// Get Vendor Specific KYC Settings
$kyc_settings = [];
$q_kyc = mysqli_query($connection_server, "SELECT verification_name, status FROM sas_kyc_verifications WHERE vendor_id='$vid'");
while($r = mysqli_fetch_assoc($q_kyc)) $kyc_settings[$r['verification_name']] = (int)$r['status'];

$is_kyc_enabled = isKYCEnforced($vid);

// Check if VoveID is enabled for this vendor
$voveid_enabled = false;
$voveid_public_key = '';
$voveid_env = 'production';
$voveid_q = mysqli_query($connection_server, "SELECT option_name, option_value FROM sas_vendor_settings WHERE vendor_id='$vid' AND option_name IN ('voveid_enabled', 'voveid_public_key', 'voveid_environment')");
while($r = mysqli_fetch_assoc($voveid_q)) {
    if ($r['option_name'] === 'voveid_enabled') $voveid_enabled = (int)$r['option_value'] === 1;
    if ($r['option_name'] === 'voveid_public_key') $voveid_public_key = $r['option_value'];
    if ($r['option_name'] === 'voveid_environment') $voveid_env = $r['option_value'];
}

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
    <?php if ($voveid_enabled && !empty($voveid_public_key)): ?>
    <!-- VoveID Web SDK -->
    <script src="https://cdn.voveid.com/web-sdk/voveid-web-sdk.min.js"></script>
    <?php endif; ?>
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
                    <!-- VoveID Verification Section -->
                    <?php if ($voveid_enabled && !empty($voveid_public_key)): ?>
                    <div class="col-12">
                        <div class="card kyc-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <div class="card-body p-4">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="fw-bold mb-2"><i class="bi bi-shield-check me-2"></i>VoveID Identity Verification</h5>
                                        <p class="small text-white-50 mb-3">Complete your KYC in minutes with VoveID's AI-powered verification. Secure, fast, and compliant.</p>
                                        <button type="button" id="btnStartVoveID" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" onclick="startVoveIDVerification()">
                                            <i class="bi bi-shield-lock me-2"></i>Start VoveID Verification
                                        </button>
                                        <span id="voveidStatus" class="ms-3 small text-white-50"></span>
                                    </div>
                                    <div class="col-md-4 text-center d-none d-md-block">
                                        <i class="bi bi-shield-lock-fill display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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

    <?php if ($voveid_enabled && !empty($voveid_public_key)): ?>
    <script>
        // VoveID Web SDK Integration
        let voveidInitialized = false;
        
        // Initialize VoveID SDK
        async function initVoveID() {
            if (typeof Vove === 'undefined') {
                document.getElementById('voveidStatus').textContent = 'Loading VoveID SDK...';
                // Wait for SDK to load
                let attempts = 0;
                while (typeof Vove === 'undefined' && attempts < 20) {
                    await new Promise(r => setTimeout(r, 100));
                    attempts++;
                }
            }
            
            if (typeof Vove !== 'undefined') {
                try {
                    await new Promise((resolve, reject) => {
                        Vove.initialize('<?php echo addslashes($voveid_public_key); ?>', '<?php echo $voveid_env; ?>', (result) => {
                            if (result === 'success' || result === true) {
                                voveidInitialized = true;
                                document.getElementById('voveidStatus').textContent = 'Ready';
                                resolve();
                            } else {
                                reject(new Error('VoveID initialization failed'));
                            }
                        });
                    });
                } catch (e) {
                    console.error('VoveID init error:', e);
                    document.getElementById('voveidStatus').textContent = 'SDK init failed';
                }
            } else {
                document.getElementById('voveidStatus').textContent = 'SDK not loaded';
            }
        }
        
        // Start VoveID Verification
        async function startVoveIDVerification() {
            const btn = document.getElementById('btnStartVoveID');
            const statusEl = document.getElementById('voveidStatus');
            
            if (!voveidInitialized) {
                statusEl.textContent = 'Initializing...';
                await initVoveID();
            }
            
            if (!voveidInitialized) {
                statusEl.textContent = 'VoveID SDK not available';
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating session...';
            statusEl.textContent = 'Creating verification session...';
            
            try {
                // Create VoveID session via our backend
                const response = await fetch('/api/voveid-session.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to create session');
                }
                
                const sessionToken = data.token;
                statusEl.textContent = 'Starting verification...';
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Launching...';
                
                // Start VoveID verification
                const config = {
                    showUI: true,
                    exitAfterEachStep: false,
                    maxAttemptsActionCallback: () => {
                        statusEl.textContent = 'Max attempts reached. Contact support.';
                    }
                };
                
                Vove.start(sessionToken, config, (payload) => {
                    if (!payload) return;
                    
                    console.log('VoveID payload:', payload);
                    
                    switch (payload.result) {
                        case 'success':
                        case 'SUCCESS':
                            statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Verification successful!</span>';
                            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Verified';
                            btn.classList.remove('btn-light');
                            btn.classList.add('btn-success');
                            setTimeout(() => window.location.reload(), 3000);
                            break;
                        case 'pending':
                        case 'PENDING':
                            statusEl.textContent = 'Verification pending review...';
                            break;
                        case 'in_progress':
                        case 'IN_PROGRESS':
                            const nextStep = payload.nextStep?.name || 'processing';
                            statusEl.textContent = 'Step completed: ' + nextStep + '. Continuing...';
                            break;
                        case 'canceled':
                        case 'CANCELED':
                            statusEl.textContent = 'Verification canceled';
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-shield-lock me-2"></i>Start VoveID Verification';
                            break;
                        case 'max_attempts':
                        case 'MAX_ATTEMPTS_REACHED':
                            statusEl.textContent = 'Max attempts reached. Contact support.';
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-shield-lock me-2"></i>Start VoveID Verification';
                            break;
                    }
                    
                    if (payload.nextStep && payload.nextStep.name) {
                        console.log('Next step:', payload.nextStep.name);
                    }
                });
                
            } catch (e) {
                console.error('VoveID error:', e);
                statusEl.textContent = 'Error: ' + e.message;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-shield-lock me-2"></i>Start VoveID Verification';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initVoveID);
    </script>
    <?php endif; ?>
