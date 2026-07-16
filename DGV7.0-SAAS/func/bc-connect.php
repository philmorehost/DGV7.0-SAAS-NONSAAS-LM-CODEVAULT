<?php
    // ─── PHP 8.1+ Compatibility Fix ──────────────────────────────────────────────
    // PHP 8.1+ enables STRICT exception mode for MySQLi by default.
    // DGV6.90 legacy code expects mysqli_query to return false on failure instead of crashing.
    mysqli_report(MYSQLI_REPORT_OFF);

	date_default_timezone_set('Africa/Lagos');

    // ─── Zero-Trust License Lock Interceptor (Kill Switch) ───────────────────────
    $lock_file = __DIR__ . '/.license.lock';
    if (file_exists($lock_file)) {
        $lock_data = json_decode(@file_get_contents($lock_file), true);
        if ($lock_data && isset($lock_data['suspended_at'])) {
            $reason = htmlspecialchars($lock_data['reason'] ?? 'License revoked by administrator.');
            header("HTTP/1.1 503 Service Temporarily Unavailable");
            echo '<!DOCTYPE html><html><head><title>System Suspended</title><meta name="viewport" content="width=device-width, initial-scale=1"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet"><style>body{background:#0b0f19;color:#f3f4f6;font-family:\'Outfit\',sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;text-align:center;box-sizing:border-box}.card{background:#111827;border:1px solid #dc3545;border-radius:12px;padding:40px;max-width:500px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.5)}.icon{font-size:48px;color:#dc3545;margin-bottom:20px}h1{font-size:24px;margin:0 0 10px 0;font-weight:600}p{color:#9ca3af;font-size:15px;line-height:1.6;margin:0 0 20px 0}</style></head><body><div class="card"><div class="icon">⚠️</div><h1>System Suspended</h1><p>' . $reason . '</p><p>Please contact the software provider to resolve this issue.</p></div></body></html>';
            exit;
        }
    }
	
	// Installer check: Redirect to the new installer if not yet installed
	if (!file_exists(__DIR__ . "/db-json.php")) {
	    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
	    if (strpos($current_uri, '/install/') === false) {
	        header("Location: /install/");
	        exit;
	    }
	}

	include_once(__DIR__ . "/db-dtl.php");
	include_once(__DIR__ . "/bc-mailer.php");
	include_once(__DIR__ . "/email-design.php");
	include_once(__DIR__ . "/bc-levelup.php");

    $connection = null;
    $connection_server = null;

    try {
	    $connection_server = mysqli_connect($mySqlServer, $mySqlUser, $mySqlPass, $mySqlDBName);
        if ($connection_server) {
            mysqli_set_charset($connection_server, "utf8mb4");
        }
        $connection = $connection_server;
    } catch (mysqli_sql_exception $e) {
        // Log DB connection failure without exposing credentials
        error_log('[DGV-DB] Connection failed: ' . $e->getMessage());
        $connection_server = null;
        $connection = null;
    }

	if (file_exists(__DIR__ . "/db-json.php")) {
	    bc_verify_integrity();
	}


    // Now include functions that may depend on $connection_server
    include_once(__DIR__ . "/bc-func.php");
    include_once(__DIR__ . "/bc-bulk-queue.php");

    // License Verification & Grace Period Logic
    if ($connection_server) {
        $license_status = getSuperAdminOption('license_status', 'valid');
        $license_invalid_since = getSuperAdminOption('license_invalid_since', '');

        if ($license_status === 'invalid' && !empty($license_invalid_since)) {
            $elapsed = time() - (int)$license_invalid_since;
            $grace_period = 172800; // 48 hours

            if ($elapsed > $grace_period) {
                // If it is a super admin request, redirect to AccountSettings.php
                $current_uri = $_SERVER['REQUEST_URI'] ?? '';
                $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
                
                $is_spadmin = (strpos($current_uri, '/bc-spadmin/') !== false || strpos($current_script, '/bc-spadmin/') !== false);
                
                if ($is_spadmin) {
                    $is_exempt = (
                        strpos($current_script, 'AccountSettings.php') !== false ||
                        strpos($current_uri, 'AccountSettings.php') !== false ||
                        isset($_GET['refresh_license']) ||
                        isset($_POST['update-license'])
                    );
                    if (!$is_exempt) {
                        header("Location: /bc-spadmin/AccountSettings.php?license_expired=1");
                        exit;
                    }
                } else {
                    // For vendors/customers, show a blocked screen
                    header("HTTP/1.1 503 Service Temporarily Unavailable");
                    echo '<!DOCTYPE html><html><head><title>System License Required</title><meta name="viewport" content="width=device-width, initial-scale=1"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet"><style>body{background:#0b0f19;color:#f3f4f6;font-family:\'Outfit\',sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;text-align:center;box-sizing:border-box}.card{background:#111827;border:1px solid #dc3545;border-radius:12px;padding:40px;max-width:500px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.5)}.icon{font-size:48px;color:#dc3545;margin-bottom:20px}h1{font-size:24px;margin:0 0 10px 0;font-weight:600}p{color:#9ca3af;font-size:15px;line-height:1.6;margin:0 0 20px 0}</style></head><body><div class="card"><div class="icon">⚠️</div><h1>Activation Pending</h1><p>This software license has expired or is invalid. Please contact the platform administrator to reactivate this system.</p></div></body></html>';
                    exit;
                }
            } else {
                // Within grace period: calculate hours remaining and set global warning message for super admins
                $remaining_seconds = $grace_period - $elapsed;
                $remaining_hours = ceil($remaining_seconds / 3600);
                $GLOBALS['license_warning_msg'] = "⚠️ <strong>License Verification Alert:</strong> Your platform license is invalid. The system will lock and disable all operations in <strong>" . $remaining_hours . " hours</strong> unless a valid license key is updated in <a href='/bc-spadmin/AccountSettings.php#tab-license' class='alert-link fw-bold text-decoration-underline'>Account Settings</a>. You can manage or verify your domain activation on the <a href='https://manager.pmhserver.name.ng' target='_blank' class='alert-link fw-bold text-decoration-underline'>Licensing API Server</a>.";
            }
        }
    }

    // Define the web host
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $protocol = "https://";
    } else {
        $protocol = "http://";
    }
    $web_http_host = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
	
	$get_requested_website_domain_url = $_SERVER["HTTP_HOST"] ?? 'localhost';
    if (substr($get_requested_website_domain_url, 0, 4) === "www.") {
        $non_www = substr($get_requested_website_domain_url, 4);
		if(isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on")){
			header("Location: https://" . $non_www . $_SERVER["REQUEST_URI"]);
		}else{
			header("Location: http://" . $non_www . $_SERVER["REQUEST_URI"]);
		}
        exit();
	}