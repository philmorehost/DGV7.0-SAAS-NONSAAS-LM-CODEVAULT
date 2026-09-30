<?php
    // ─── JSON purity for API / app clients ───────────────────────────────────────
    // The mobile apps parse every /web/api/* response as JSON. Every other API surface in
    // this codebase already guards against stray output: web/guest-api/guest-bootstrap.php
    // and func/bc-php-compat.php (pulled in by bc-config.php for web pages) both set
    // display_errors off, and api/app-backend/* starts with error_reporting(0). /web/api/*
    // had no guard at all, so a single notice, warning or deprecation printed anywhere in
    // this include chain lands in front of the payload, the body stops being valid JSON,
    // and the app reports it to the user as "check your connection" - a network-sounding
    // message for a server-side defect that is visible only in the error log.
    $bc_connect_is_api_client = (
        stripos($_SERVER['REQUEST_URI'] ?? '', '/web/api/') !== false ||
        stripos($_SERVER['SCRIPT_NAME'] ?? '', '/web/api/') !== false ||
        isset($_SERVER['HTTP_X_APP_SOURCE'])
    );
    if ($bc_connect_is_api_client) {
        // Errors are still raised and logged; only the on-screen output is suppressed.
        @ini_set('display_errors', '0');
        @ini_set('html_errors', '0');
        @ini_set('log_errors', '1');
    }

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
            $reason = $lock_data['reason'] ?? 'License revoked by administrator.';
            if ($bc_connect_is_api_client) {
                // A machine client cannot read an HTML block page: answer with parseable
                // JSON and a stable code so the failure is diagnosable rather than being
                // shown to the user as a broken connection. The status is only set while
                // the headers are still ours - an already-started response used to keep
                // its 200 silently while carrying the block page, which is precisely what
                // makes such an answer look like an unparseable success to the client.
                if (!headers_sent()) {
                    header("HTTP/1.1 503 Service Temporarily Unavailable");
                    header("Content-Type: application/json");
                }
                echo json_encode([
                    "status" => "failed",
                    "code" => "LICENSE_SUSPENDED",
                    "desc" => "This platform is temporarily suspended. " . trim((string)$reason),
                ]);
                exit;
            }
            $reason = htmlspecialchars($reason);
            header("HTTP/1.1 503 Service Temporarily Unavailable");
            echo '<!DOCTYPE html><html><head><title>System Suspended</title><meta name="viewport" content="width=device-width, initial-scale=1"><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet"><style>body{background:#0b0f19;color:#f3f4f6;font-family:\'Outfit\',sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;text-align:center;box-sizing:border-box}.card{background:#111827;border:1px solid #dc3545;border-radius:12px;padding:40px;max-width:500px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.5)}.icon{font-size:48px;color:#dc3545;margin-bottom:20px}h1{font-size:24px;margin:0 0 10px 0;font-weight:600}p{color:#9ca3af;font-size:15px;line-height:1.6;margin:0 0 20px 0}</style></head><body><div class="card"><div class="icon">⚠️</div><h1>System Suspended</h1><p>' . $reason . '</p><p>Please contact the software provider to resolve this issue.</p></div></body></html>';
            exit;
        }
    }

	include_once(__DIR__ . "/db-dtl.php");
	include_once(__DIR__ . "/bc-mailer.php");
	include_once(__DIR__ . "/email-design.php");

    $connection = null;
    $connection_server = null;

    try {
	    $connection_server = @mysqli_connect($mySqlServer, $mySqlUser, $mySqlPass, $mySqlDBName);
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


    // Now include functions that may depend on $connection_server
    include_once(__DIR__ . "/bc-func.php");
    include_once(__DIR__ . "/bc-gateway.php");
    include_once(__DIR__ . "/bc-remote-vendor.php");
    include_once(__DIR__ . "/bc-bulk-queue.php");
    include_once(__DIR__ . "/bc-mail-queue.php");
    include_once(__DIR__ . "/bc-demo-mode.php");
    include_once(__DIR__ . "/voveid-client.php");

    // Connect Platform Validation & Integrity Engine
    include_once(__DIR__ . "/bc-integrity.php");
    if ($connection_server) {
        bc_verify_integrity();
    }

    // Define the web host.
    // $_SERVER['HTTPS'] on its own is NOT a reliable signal: it is absent when TLS terminates upstream
    // (Cloudflare / nginx proxy) and some stacks report "1" or "Off" instead of "on". Trusting it blindly
    // produced http:// links - and http:// redirects - on a site actually served over https, which is how
    // an admin or vendor session could silently land on plain http. Every signal the request carries is
    // checked instead; the worst case of a spoofed forwarding header is a link that says https.
    $bc_request_is_https = (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos((string)$_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false)
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        || (!empty($_SERVER['HTTP_CF_VISITOR']) && stripos((string)$_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
        || (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower((string)$_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );
    $protocol = $bc_request_is_https ? "https://" : "http://";
    $web_http_host = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    // The Host header is attacker-controlled (a plain "curl -H 'Host: ...'" is enough) and it reaches
    // SQL, URLs and the tenant lookup. These two helpers give every caller the same sanitised value:
    // bc_safe_host() keeps only a host[:port] shape, bc_safe_host_sql() escapes it for a query.
    if (!function_exists('bc_safe_host')) {
        function bc_safe_host() {
            $host = (string)($_SERVER['HTTP_HOST'] ?? '');
            $host = preg_replace('/[^A-Za-z0-9\.\-:]/', '', $host);
            return substr($host, 0, 255);
        }
    }
    if (!function_exists('bc_safe_host_sql')) {
        function bc_safe_host_sql($db) {
            $host = bc_safe_host();
            return $db ? mysqli_real_escape_string($db, $host) : $host;
        }
    }
	
	$get_requested_website_domain_url = $_SERVER["HTTP_HOST"] ?? 'localhost';
    if (substr($get_requested_website_domain_url, 0, 4) === "www.") {
        $non_www = substr($get_requested_website_domain_url, 4);
		// Keep the scheme the visitor already arrived with ($protocol above) instead of re-testing HTTPS,
		// which could bounce an https visitor to http.
		header("Location: " . $protocol . $non_www . $_SERVER["REQUEST_URI"]);
        exit();
	}
