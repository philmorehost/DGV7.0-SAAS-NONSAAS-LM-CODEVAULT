<?php session_start();
include("../func/bc-admin-config.php");
include("../func/bc-whatsapp.php");
require_once("../func/bc-giftcard-func.php");

$vid = $get_logged_admin_details['id'];
$site_q = mysqli_query($connection_server, "SELECT site_title FROM sas_site_details WHERE vendor_id='$vid' LIMIT 1");
$site_data = mysqli_fetch_assoc($site_q);
$biz_name = $get_logged_admin_details['company_name'] ?? ($site_data['site_title'] ?? $_SERVER['HTTP_HOST']);

$wa_enabled = getVendorOption($vid, 'whatsapp_notifications', '0');
$is_configured = isWhatsAppGatewayOnline();

// --- Handle Save WhatsApp API Credentials ---
if (isset($_POST['save-whatsapp-api'])) {
    $wa_provider = mysqli_real_escape_string($connection_server, $_POST['whatsapp_provider']);
    $wa_custom_url = mysqli_real_escape_string($connection_server, $_POST['wa_custom_url'] ?? '');
    $wa_custom_token = mysqli_real_escape_string($connection_server, $_POST['wa_custom_token'] ?? '');
    
    $wa_token = mysqli_real_escape_string($connection_server, $_POST['wa_official_token'] ?? '');
    $wa_phone_id = mysqli_real_escape_string($connection_server, $_POST['wa_official_phone_id'] ?? '');
    $wa_biz_id = mysqli_real_escape_string($connection_server, $_POST['wa_official_biz_id'] ?? '');

    $wa_sendchamp_token = mysqli_real_escape_string($connection_server, $_POST['wa_sendchamp_token'] ?? '');
    $wa_sendchamp_sender = mysqli_real_escape_string($connection_server, $_POST['wa_sendchamp_sender'] ?? '');
    
    $opts = [
        'whatsapp_provider' => $wa_provider,
        'wa_custom_url' => $wa_custom_url,
        'wa_custom_token' => $wa_custom_token,
        'wa_official_token' => $wa_token,
        'wa_official_phone_id' => $wa_phone_id,
        'wa_official_biz_id' => $wa_biz_id,
        'wa_sendchamp_token' => $wa_sendchamp_token,
        'wa_sendchamp_sender' => $wa_sendchamp_sender
    ];
    
    foreach ($opts as $key => $val) {
        setVendorOption(1, $key, $val);
    }
    $_SESSION['success_msg'] = "WhatsApp API credentials saved successfully.";
    header("Location: WhatsAppManager.php"); exit();
}

// --- Handle Toggle WhatsApp Alerts ---
if (isset($_POST['toggle-wa'])) {
    setVendorOption($vid, 'whatsapp_notifications', $_POST['toggle-wa'] == '1' ? '1' : '0');
    header("Location: WhatsAppManager.php"); exit();
}

// --- Handle API Test ---
if (isset($_POST['test-whatsapp-api'])) {
    $test_phone = mysqli_real_escape_string($connection_server, $_POST['test_phone'] ?? '');
    $test_msg = $_POST['test_message'] ?? 'This is a test message from your API setup.';
    
    // Test the API directly here to capture the exact error message
    $provider = getSuperAdminOption('whatsapp_provider', 'official');
    if ($provider === 'sendchamp') {
        $api_token = getSuperAdminOption('wa_sendchamp_token', '');
        $sender = getSuperAdminOption('wa_sendchamp_sender', '');
        
        $phone = preg_replace('/[^0-9]/', '', $test_phone);
        if (strlen($phone) === 11 && $phone[0] === '0') $phone = '234' . substr($phone, 1);
        
        $url = "https://api.sendchamp.com/api/v1/whatsapp/message/send";
        $payload = [
            "sender" => $sender,
            "recipient" => $phone,
            "type" => "text",
            "message" => $test_msg
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $api_token",
                "Content-Type: application/json",
                "Accept: application/json"
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $_SESSION['success_msg'] = "Test message sent successfully to $test_phone!";
        } else {
            $_SESSION['error_msg'] = "API Error ($http_code): " . htmlspecialchars($response);
        }
    } else {
        if (sendWhatsAppAlert($test_phone, $test_msg)) {
            $_SESSION['success_msg'] = "Test message sent successfully to $test_phone!";
        } else {
            $_SESSION['error_msg'] = "Failed to send test message. Check your API credentials.";
        }
    }
    header("Location: WhatsAppManager.php"); exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>WhatsApp Marketing | Vendor Panel</title>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        .select-scroll { height: 250px; overflow-y: auto; background: #fff; border: 1px solid #dee2e6; border-radius: 0.5rem; padding: 10px; }
        .user-checkbox-item { padding: 8px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px; transition: 0.2s; }
        .user-checkbox-item:hover { background: #f8fafc; }
        .user-checkbox-item label { flex-grow: 1; cursor: pointer; margin: 0; font-size: 0.85rem; }
        .badge-wa { background: linear-gradient(135deg, #25d366 0%, #128c7e 100%); color: white; }
    </style>
</head>
<body>
<?php include("../func/bc-admin-header.php"); ?>
<div class="pagetitle d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">WhatsApp Official Notifications</h1>
        <p class="text-muted small">Send highly converted alerts via Official Cloud API</p>
    </div>
    <div class="text-end">
        <span class="badge bg-light text-dark shadow-sm px-3 py-2 border"><i class="bi bi-wallet2 text-success me-2"></i>Wallet: ₦<?php echo number_format($get_logged_admin_details['balance'], 2); ?></span>
    </div>
</div>

<?php if(isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if(isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-octagon me-2"></i><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<section class="section">
    <div class="row g-4">
        <!-- LEFT COLUMN: PRICING & TEMPLATE SUBMISSION -->
        <div class="col-lg-4">
            <!-- Pricing Panel (FREE under Standalone Mode) -->
            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-body p-4 bg-light rounded-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-fill-check text-success me-2"></i>Standalone Broadcast Mode</h6>
                    <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border-0">
                        <div>
                            <span class="d-block small text-muted">Broadcasting Cost</span>
                            <h5 class="fw-bold text-success mb-0">₦0.00 <span class="small fw-normal text-success">(FREE / UNLIMITED)</span></h5>
                        </div>
                        <i class="bi bi-broadcast fs-3 text-success opacity-50 animate__animated animate__pulse animate__infinite"></i>
                    </div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Official WhatsApp API broadcast features are completely free and unlocked on your standalone server.</p>
                </div>
            </div>

            <!-- WhatsApp API Setup -->
            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-gear-fill text-primary me-2"></i>API Setup</h6>
                    <?php if($is_configured): ?>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small">Configured</span>
                    <?php else: ?>
                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2 py-1 small">Not Configured</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4 pt-0">
                    <form method="post">
                        <?php $current_provider = getSuperAdminOption('whatsapp_provider', 'official'); ?>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark">WhatsApp Provider</label>
                            <select name="whatsapp_provider" id="wa-provider-select" class="form-select bg-light border-0" onchange="toggleWaProvider()">
                                <option value="official" <?php echo $current_provider === 'official' ? 'selected' : ''; ?>>Official Meta Cloud API (Recommended)</option>
                                <option value="sendchamp" <?php echo $current_provider === 'sendchamp' ? 'selected' : ''; ?>>Sendchamp API</option>
                                <option value="custom" <?php echo $current_provider === 'custom' ? 'selected' : ''; ?>>Custom Unofficial API (e.g. watext)</option>
                            </select>
                        </div>

                        <!-- Official Meta Cloud API Fields -->
                        <div id="wa-official-fields" style="display: <?php echo $current_provider === 'official' ? 'block' : 'none'; ?>;">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Permanent Access Token</label>
                                <input type="text" name="wa_official_token" class="form-control" value="<?php echo htmlspecialchars(getSuperAdminOption('wa_official_token', '')); ?>" placeholder="EAAI...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Phone Number ID</label>
                                <input type="text" name="wa_official_phone_id" class="form-control" value="<?php echo htmlspecialchars(getSuperAdminOption('wa_official_phone_id', '')); ?>" placeholder="1234567890">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">WhatsApp Business Account ID</label>
                                <input type="text" name="wa_official_biz_id" class="form-control" value="<?php echo htmlspecialchars(getSuperAdminOption('wa_official_biz_id', '')); ?>" placeholder="1234567890">
                            </div>
                        </div>

                        <!-- Custom Unofficial API Fields -->
                        <div id="wa-custom-fields" style="display: <?php echo $current_provider === 'custom' ? 'block' : 'none'; ?>;">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Custom API Base URL</label>
                                <input type="url" name="wa_custom_url" class="form-control" value="<?php echo htmlspecialchars(getSuperAdminOption('wa_custom_url', '')); ?>" placeholder="https://api.watext.com/send">
                                <small class="text-muted" style="font-size:11px;">The endpoint that accepts standard JSON POST payload (phone, message, token).</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Custom API Token (Optional)</label>
                                <input type="text" name="wa_custom_token" class="form-control" value="<?php echo htmlspecialchars(getSuperAdminOption('wa_custom_token', '')); ?>" placeholder="Enter API Token">
                            </div>
                        </div>

                        <!-- Sendchamp API Fields -->
                        <div id="wa-sendchamp-fields" style="display: <?php echo $current_provider === 'sendchamp' ? 'block' : 'none'; ?>;">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Sendchamp Access Key</label>
                                <input type="text" name="wa_sendchamp_token" class="form-control" value="<?php echo htmlspecialchars(getSuperAdminOption('wa_sendchamp_token', '')); ?>" placeholder="sendchamp_live_...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Sender Phone Number</label>
                                <input type="text" name="wa_sendchamp_sender" class="form-control" value="<?php echo htmlspecialchars(getSuperAdminOption('wa_sendchamp_sender', '')); ?>" placeholder="e.g. 2348100000000">
                                <small class="text-muted" style="font-size:11px;">Your approved WhatsApp number on Sendchamp in international format without +</small>
                            </div>
                        </div>

                        <button type="submit" name="save-whatsapp-api" class="btn btn-primary w-100 rounded-pill"><i class="bi bi-save me-2"></i>Save API Settings</button>
                    </form>
                </div>
            </div>

            <script>
                function toggleWaProvider() {
                    const provider = document.getElementById('wa-provider-select').value;
                    document.getElementById('wa-official-fields').style.display = 'none';
                    document.getElementById('wa-custom-fields').style.display = 'none';
                    document.getElementById('wa-sendchamp-fields').style.display = 'none';
                    
                    if (provider === 'official') {
                        document.getElementById('wa-official-fields').style.display = 'block';
                    } else if (provider === 'sendchamp') {
                        document.getElementById('wa-sendchamp-fields').style.display = 'block';
                    } else {
                        document.getElementById('wa-custom-fields').style.display = 'block';
                    }
                }
            </script>

            <!-- Test API Connection -->
            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check text-primary me-2"></i>Test API Connection</h6>
                </div>
                <div class="card-body p-4 pt-0">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Test Phone Number</label>
                            <input type="text" name="test_phone" class="form-control" placeholder="e.g. 2348100000000" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Test Message</label>
                            <textarea name="test_message" class="form-control" rows="2" required>Hello! This is a test message to confirm your WhatsApp API is working correctly.</textarea>
                        </div>
                        <button type="submit" name="test-whatsapp-api" class="btn btn-dark w-100 rounded-pill"><i class="bi bi-send me-2"></i>Send Test Message</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: BROADCAST ENGINE -->
        <div class="col-lg-8">
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-primary"></i>Broadcast Dispatcher</h6>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 small">Limit: 200/batch</span>
                </div>
                <div class="card-body p-4 pt-0">
                    <form id="broadcast-form" onsubmit="sendBroadcast(event)">
                        <!-- Checkbox User List -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-bold text-dark mb-0">1. Select Recipients</label>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="selectAll(false)">Clear</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" onclick="selectAllMax200()">Select Max 200</button>
                                </div>
                            </div>
                            <div class="select-scroll" id="checkbox-container">
                                <?php
                                $uq = mysqli_query($connection_server, "SELECT id, firstname, lastname, phone_number, username FROM sas_users WHERE vendor_id='$vid' OR vendor_id=".intval($vid)." ORDER BY id DESC LIMIT 500");
                                if (mysqli_num_rows($uq) == 0) {
                                    echo '<div class="text-center p-3 small text-muted">No users found.</div>';
                                }
                                $user_count = 0;
                                while ($u = mysqli_fetch_assoc($uq)) {
                                    $p = preg_replace('/[^0-9]/', '', $u['phone_number']);
                                    if (strlen($p) === 11 && $p[0] === '0') $p = '234' . substr($p, 1);
                                    
                                    $fn = htmlspecialchars(trim($u['firstname'] . ' ' . $u['lastname']));
                                    if ($p) {
                                        $user_count++;
                                        echo "<div class='user-checkbox-item'>
                                                <input class='form-check-input user-check' type='checkbox' value='$p' id='u_$user_count'>
                                                <label for='u_$user_count' class='text-truncate'>{$fn} (@".htmlspecialchars($u['username']).") <span class='text-muted ms-1'>+$p</span></label>
                                              </div>";
                                    }
                                }
                                ?>
                            </div>
                            <div class="form-text small mt-1 d-flex justify-content-between">
                                <span id="selected-count">0 selected</span>
                                <span>Total users: <?php echo $user_count; ?></span>
                            </div>
                        </div>
                        
                        <!-- Message Body -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark">2. Broadcast Message</label>
                            <textarea id="broadcast-message" class="form-control rounded-3 p-3 bg-light border-0" rows="4" placeholder="Type your broadcast message here..." required></textarea>
                            <div class="form-text small text-muted mt-1">This message will be sent exactly as typed to all selected users.</div>
                        </div>
                        

                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div id="broadcast-status" class="small fw-bold"></div>
                            <button type="submit" id="btn-broadcast" class="btn btn-dark rounded-pill px-5 fw-bold shadow-sm" <?php echo !$is_configured?'disabled':''; ?>>
                                <i class="bi bi-send-fill me-2"></i>Dispatch Campaign
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include("../func/bc-admin-footer.php"); ?>

<script>
    const ratePerMessage = 0;
    
    // Update counter on checkbox change
    document.getElementById('checkbox-container').addEventListener('change', updateCalculator);

    function updateCalculator() {
        const checkedBoxes = document.querySelectorAll('.user-check:checked');
        const count = checkedBoxes.length;
        document.getElementById('selected-count').innerText = `${count} selected`;
    }

    function selectAll(forceCheck = false) {
        const boxes = document.querySelectorAll('.user-check');
        boxes.forEach(box => box.checked = forceCheck);
        updateCalculator();
    }

    function selectAllMax200() {
        selectAll(false); // clear first
        const boxes = document.querySelectorAll('.user-check');
        let count = 0;
        boxes.forEach(box => {
            if (count < 200) {
                box.checked = true;
                count++;
            }
        });
        updateCalculator();
        if(count > 0) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `${count} users selected`, showConfirmButton: false, timer: 2000 });
        }
    }

    function sendBroadcast(e) {
        e.preventDefault();
        const checkedBoxes = Array.from(document.querySelectorAll('.user-check:checked'));
        const broadcastMsg = document.getElementById('broadcast-message').value;
        const status = document.getElementById('broadcast-status');
        const btn = document.getElementById('btn-broadcast');

        if (checkedBoxes.length === 0) {
            Swal.fire('Error', 'Please select at least one recipient.', 'error');
            return;
        }
        if (checkedBoxes.length > 200) {
            Swal.fire('Limit Reached', 'Maximum 200 recipients allowed per batch.', 'warning');
            return;
        }
        if (!broadcastMsg) {
            Swal.fire('Error', 'Please type a broadcast message.', 'error');
            return;
        }

        const phones = checkedBoxes.map(box => box.value);
        
        Swal.fire({
            title: 'Confirm Dispatch',
            text: `You are about to send a campaign to ${phones.length} users. This campaign is completely FREE under Standalone Mode.`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Dispatching...';
                status.innerHTML = '<span class="text-primary"><i class="bi bi-hourglass-split me-1"></i>Starting...</span>';

                fetch('ajax-wa-broadcast.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phones, message: broadcastMsg })
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Dispatch Campaign';
                    if (data.success) {
                        Swal.fire('Success', `Broadcast complete! ${data.sent} sent, ${data.failed} failed. Cost: ₦0.00 (FREE)`, 'success')
                        .then(() => window.location.reload());
                    } else {
                        Swal.fire('Failed', data.error || 'Broadcast failed', 'error');
                        status.innerHTML = `<span class="text-danger">❌ Error</span>`;
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Dispatch Campaign';
                    status.innerHTML = `<span class="text-danger">❌ Connection error: ${err.message}</span>`;
                });

            }
        });
    }
</script>
</body>
</html>
