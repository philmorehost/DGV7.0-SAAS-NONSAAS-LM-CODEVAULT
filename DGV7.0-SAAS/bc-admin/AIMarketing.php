<?php session_start();
include("../func/bc-admin-config.php");
include_once("../func/bc-ai-engine.php");

$title = "AI Marketing Studio";
$vendor_id = $get_logged_admin_details['id'];
$ai_engine = ai_engine();
$assigned_model_raw = $get_logged_admin_details['ai_model_assigned'] ?: getSuperAdminOption('ai_default_model', '');
$assigned_model = $ai_engine->isModelCompatible($assigned_model_raw) ? $assigned_model_raw : $ai_engine->getDefaultModel();

// Business Name Fallback Logic
$site_q = mysqli_query($connection_server, "SELECT site_title FROM sas_site_details WHERE vendor_id='$vendor_id' LIMIT 1");
$site_data = mysqli_fetch_assoc($site_q);
$biz_name = $get_logged_admin_details['company_name'] ?? ($site_data['site_title'] ?? 'Our VTU Platform');

$current_bg = $get_logged_admin_details['ai_marketing_bg'] ?? 'midnight';

if (isset($_POST['set-bg'])) {
    $new_bg = bc_sanitize($_POST['bg_name'] ?? 'midnight');
    mysqli_query($connection_server, "UPDATE sas_vendors SET ai_marketing_bg='$new_bg' WHERE id='$vendor_id'");
    header("Location: AIMarketing.php"); exit();
    exit;
}

if (isset($_POST['generate-ad'])) {
    $service = bc_sanitize($_POST['service'] ?? 'Airtime');
    $tone    = bc_sanitize($_POST['tone'] ?? 'Professional');
    $target  = bc_sanitize($_POST['target'] ?? 'Customers');
    $platform = bc_sanitize($_POST['platform'] ?? 'Push Notification');

    $token_bal = (int)($get_logged_admin_details['ai_token_balance'] ?? 0);
    $per_tx_cost = (int)($get_logged_admin_details['ai_per_tx_cost'] ?? 2);

    if ($token_bal < $per_tx_cost) {
        $generated_copy = "❌ Insufficient AI Tokens! Please top up your AI balance in settings.";
    } else {
        $prompt = "You are a professional VTU Marketing Strategist.
               Generate a high-converting $platform ad copy for a business called '$biz_name'.
               Service being promoted: $service. 
               Tone: $tone. 
               Target Audience: $target.
               Include attractive emojis, a catchy headline, and a clear Call to Action (CTA) pointing to our website: {$get_logged_admin_details['website_url']}.
               The design vibe for this ad is '$current_bg'.
               Keep it extremely engaging and concise for mobile readers.";
    
    $start_time = microtime(true);
    $ai = ai_engine();
    $result = $ai->chat($assigned_model, $prompt, ['temperature' => 0.85]);
    $duration = round((microtime(true) - $start_time) * 1000);

    if ($result['status'] === 'success') {
        $generated_copy = $result['response'];
        $tokens = strlen($prompt . $generated_copy) / 4; 
        $esc_res = mysqli_real_escape_string($connection_server, $generated_copy);
        mysqli_query($connection_server, "INSERT INTO sas_ai_transactions (vendor_id, username, prompt, response, tokens_burned, status, duration_ms) VALUES ('$vendor_id', 'admin_{$get_logged_admin_details['email']}', 'Marketing Ad ($platform): $service', '$esc_res', '$tokens', 'success', '$duration')");

        // Debit the vendor
        mysqli_query($connection_server, "UPDATE sas_vendors SET ai_token_balance = ai_token_balance - $per_tx_cost WHERE id='$vendor_id'");
        // Refresh local details for balance display
        $get_logged_admin_details['ai_token_balance'] -= $per_tx_cost;
    } else {
        $generated_copy = '❌ AI Error: ' . ($result['message'] ?? 'Unable to connect to AI engine.');
    }
    }
}

if (isset($_POST['send-campaign'])) {
    $subject = trim($_POST["subject"] ?? '');
    $body = trim($_POST["body"] ?? ''); 
    $mailto = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["mailto"] ?? ''))));

    $external_emails = [];
    if (!empty($_POST['paste_emails'])) {
        $pasted = preg_split('/[\s,]+/', $_POST['paste_emails'], -1, PREG_SPLIT_NO_EMPTY);
        foreach ($pasted as $email) {
            if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) $external_emails[] = trim($email);
        }
    }
    
    $external_emails = array_unique($external_emails);

    if (!empty($subject) && !empty($body)) {
        $success_count = 0;
        
        // Internal targets
        if (!empty($mailto) && $mailto !== 'none') {
            $res = sendVendorEmailSpecific($mailto, $subject, $body);
            if ($res == "success") $success_count++;
        }

        // External targets
        if (!empty($external_emails)) {
            foreach ($external_emails as $ext_email) {
                sendVendorEmail($ext_email, $subject, $body);
            }
            $success_count += count($external_emails);
        }

        if ($success_count > 0) {
            $_SESSION["product_purchase_response"] = "Campaign Dispatch Successful! (Targets reached: $success_count)";
        } else {
            $_SESSION["product_purchase_response"] = "Error: No targets selected or dispatch failed.";
        }
    } else {
        $_SESSION["product_purchase_response"] = "Error: Subject and Marketing Content are required.";
    }
    header("Location: AIMarketing.php");
    exit;
}

$bg_templates = [
    'midnight' => ['name' => 'Midnight', 'css' => 'linear-gradient(135deg, #0f172a, #1e293b)'],
    'solar'    => ['name' => 'Solar', 'css' => 'linear-gradient(135deg, #f97316, #ef4444)'],
    'emerald'  => ['name' => 'Emerald', 'css' => 'linear-gradient(135deg, #065f46, #064e3b)'],
    'royal'    => ['name' => 'Royal', 'css' => 'linear-gradient(135deg, #6d28d9, #4c1d95)'],
    'neon'     => ['name' => 'Neon', 'css' => '#000', 'border' => '2px solid #3b82f6'],
    'glass'    => ['name' => 'Glass', 'css' => 'rgba(255,255,255,0.1)', 'blur' => 'backdrop-filter: blur(10px); color: #000;']
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $title; ?></title>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
    <style>
        .studio-header { background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 2rem; padding: 3rem; color: white; margin-bottom: 2rem; position: relative; overflow: hidden; }
        .studio-header::after { content: '✨'; position: absolute; right: 5%; top: 50%; transform: translateY(-50%); font-size: 5rem; opacity: 0.2; }
        .fintech-card { border: none; border-radius: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.05); background: #fff; transition: 0.3s; }
        .fintech-card:hover { transform: translateY(-5px); }
        .gen-box { background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 1.5rem; padding: 2rem; white-space: pre-wrap; position: relative; }
        .flyer-preview { width: 100%; max-width: 350px; aspect-ratio: 9/16; border-radius: 2.5rem; background: <?php echo $bg_templates[$current_bg]['css']; ?>; padding: 2rem; display: flex; flex-direction: column; justify-content: center; text-align: center; color: white; position: relative; border: 8px solid #1e293b; <?php echo $bg_templates[$current_bg]['blur'] ?? ''; ?> }
        .flyer-overlay { background: rgba(255,255,255,0.1); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.2); border-radius: 1.5rem; padding: 1.5rem; height: 80%; display: flex; flex-direction: column; justify-content: space-between; }
        .nav-pills-studio .nav-link { border-radius: 2rem; padding: 0.8rem 2rem; font-weight: 700; color: #64748b; }
        .nav-pills-studio .nav-link.active { background: #6366f1 !important; color: white !important; }
    </style>
</head>
<body>
<?php include("../func/bc-admin-header.php"); ?>
<div class="pagetitle"><h1>AI Marketing Studio</h1></div>

<section class="section">
    <div class="studio-header shadow">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="fw-bold mb-0">Marketing Intelligence Hub</h2>
            <div class="bg-white bg-opacity-20 rounded-pill px-4 py-2 small fw-bold">
                <i class="bi bi-coin me-1"></i> <?php echo number_format($get_logged_admin_details['ai_token_balance'] ?? 0); ?> Tokens
            </div>
        </div>
        <p class="opacity-75 mb-0">Harness the power of generative AI to create viral campaigns for <strong><?php echo $biz_name; ?></strong></p>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card fintech-card mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Campaign Creator</h5>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Platform</label>
                            <select name="platform" class="form-select rounded-4 p-3 bg-light border-0">
                                <option>Push Notification</option>
                                <option>In-App Banner</option>
                                <option>Facebook Post</option>
                                <option>Twitter/X Ad</option>
                                <option>Instagram Reel Script</option>
                                <option>SMS Marketing</option>
                                <option>Email Campaign</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Service</label>
                            <select name="service" class="form-select rounded-4 p-3 bg-light border-0">
                                <option>Cheapest Data Bundles</option>
                                <option>Airtime to Cash</option>
                                <option>Electricity Tokens</option>
                                <option>Cable TV Subscriptions</option>
                                <option>Reseller Program</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Brand Tone</label>
                            <select name="tone" class="form-select rounded-4 p-3 bg-light border-0">
                                <option>Professional</option>
                                <option>Energetic</option>
                                <option>Funny/Witty</option>
                                <option>Urgent</option>
                            </select>
                        </div>
                        <button name="generate-ad" class="btn btn-primary w-100 rounded-pill py-3 fw-bold">Generate Magic 🪄</button>
                    </form>
                </div>
            </div>

            <div class="card fintech-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Flyer Backgrounds</h5>
                    <div class="row g-2">
                        <?php foreach($bg_templates as $key => $tpl): ?>
                        <div class="col-4 text-center">
                            <form method="post">
                                <input type="hidden" name="bg_name" value="<?php echo $key; ?>">
                                <div onclick="this.parentElement.submit()" class="rounded-4 mb-1" style="height:50px; background:<?php echo $tpl['css']; ?>; cursor:pointer; border:2px solid <?php echo $key==$current_bg?'#6366f1':'transparent'; ?>"></div>
                                <small class="fw-bold" style="font-size:10px;"><?php echo $tpl['name']; ?></small>
                                <input type="hidden" name="set-bg" value="1">
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <?php if(isset($generated_copy)): ?>
            <div class="card fintech-card mb-4 animate__animated animate__fadeIn">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="fw-bold">Ad Copy</h5>
                        <button class="btn btn-light btn-sm rounded-pill px-3" onclick="navigator.clipboard.writeText(document.getElementById('adText').value); alert('Copied!')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    
                    <form method="post" action="">
                        <div class="mb-4">
                            <textarea class="gen-box form-control shadow-none" id="adText" name="body" rows="8" style="width: 100%; resize: vertical;"><?php echo htmlspecialchars($generated_copy); ?></textarea>
                        </div>
                        
                        <div class="p-4 bg-light rounded-4 border border-dashed mb-3">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-send-fill me-2"></i>Campaign Dispatch</h6>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Email Subject</label>
                                <input type="text" name="subject" class="form-control rounded-3 py-2" placeholder="e.g. Exciting Offers Inside!" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">Registered Users</label>
                                    <select name="mailto" class="form-select rounded-3 py-2">
                                        <option value="none">-- Do not send to internal users --</option>
                                        <option value="all">All Users</option>
                                        <option value="api">API Users Only</option>
                                        <option value="smart">Smart Users Only</option>
                                        <option value="agent">Agent Users Only</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">External Emails (comma separated)</label>
                                    <textarea name="paste_emails" class="form-control rounded-3" rows="1" placeholder="user1@email.com, user2@email.com"></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" name="send-campaign" class="btn btn-success w-100 rounded-pill py-3 fw-bold mt-2 shadow-sm"><i class="bi bi-envelope-paper-fill me-2"></i> Launch Email Campaign</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card fintech-card p-5 d-flex align-items-center justify-content-center bg-light overflow-hidden">
                <div class="flyer-preview shadow-lg">
                    <div class="flyer-overlay">
                        <h4 class="fw-bold"><?php echo strtoupper($biz_name); ?></h4>
                        <div class="flex-grow-1 d-flex align-items-center justify-content-center overflow-hidden my-3">
                            <p class="small mb-0" style="font-size: <?php echo strlen($generated_copy) > 300 ? '0.75rem' : '0.9rem'; ?>; line-height: 1.4;">
                                <?php echo nl2br(htmlspecialchars(mb_strimwidth($generated_copy, 0, 600, "..."))); ?>
                            </p>
                        </div>
                        <div class="bg-white text-dark py-2 rounded-4 fw-bold small" style="word-break: break-all;"><?php echo $get_logged_admin_details['website_url']; ?></div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card fintech-card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-megaphone display-1 text-primary opacity-25"></i>
                    <h4 class="fw-bold mt-4">Viral Content starts here.</h4>
                    <p class="text-muted">Generate professional marketing assets in seconds.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>
