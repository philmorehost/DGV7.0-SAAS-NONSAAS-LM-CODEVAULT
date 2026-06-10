<?php session_start();
    include("../func/bc-admin-config.php");

    // ── Helper: generate a Google OAuth2 JWT for FCM v1 API ───────────────
    function _fcm_base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    function generateFcmAccessToken($service_account_json) {
        $sa = json_decode($service_account_json, true);
        if (empty($sa['client_email']) || empty($sa['private_key'])) {
            return null;
        }

        $now     = time();
        $header  = _fcm_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = _fcm_base64url(json_encode([
            'iss'   => $sa['client_email'],
            'sub'   => $sa['client_email'],
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ]));

        $signing_input = "$header.$payload";
        $private_key   = openssl_pkey_get_private($sa['private_key']);
        if (!$private_key) return null;

        openssl_sign($signing_input, $signature, $private_key, 'SHA256');
        $jwt = $signing_input . '.' . _fcm_base64url($signature);

        // Exchange JWT for access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        return $data['access_token'] ?? null;
    }

    function sendFcmTopicMessage($project_id, $access_token, $topic, $data_payload) {
        $body = json_encode([
            'message' => [
                'topic' => $topic,
                'data'  => $data_payload,
            ],
        ]);
        $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer $access_token",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp      = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $http_code, 'body' => json_decode($resp, true)];
    }

    // ── Load saved FCM settings ────────────────────────────────────────────
    $vendor_id  = (int) $get_logged_admin_details["id"];
    $stmt = $connection_server->prepare("SELECT * FROM sas_fcm_settings WHERE vendor_id=? LIMIT 1");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $fcm_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // ── Handle Save FCM Settings ───────────────────────────────────────────
    if (isset($_POST["save-fcm-settings"])) {
        $project_id          = trim(strip_tags($_POST["project_id"] ?? ""));
        $service_account_raw = trim($_POST["service_account_json"] ?? "");

        if (empty($project_id) || empty($service_account_raw)) {
            $_SESSION["product_purchase_response"] = "Project ID and Service Account JSON are required";
        } elseif (!json_decode($service_account_raw)) {
            $_SESSION["product_purchase_response"] = "Service Account JSON is not valid JSON";
        } else {
            $stmt = $connection_server->prepare(
                "INSERT INTO sas_fcm_settings (vendor_id, project_id, service_account_json)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE project_id=VALUES(project_id), service_account_json=VALUES(service_account_json)"
            );
            $stmt->bind_param("iss", $vendor_id, $project_id, $service_account_raw);
            $stmt->execute();
            $stmt->close();
            $_SESSION["product_purchase_response"] = "FCM settings saved successfully";
        }
        header("Location: " . $_SERVER["REQUEST_URI"]);
        exit;
    }

    // ── Handle Broadcast Update Notification ──────────────────────────────
    if (isset($_POST["broadcast-update"])) {
        if (empty($fcm_row)) {
            $_SESSION["product_purchase_response"] = "Error: FCM settings not configured. Save your Firebase project ID and Service Account JSON first.";
        } else {
            $access_token = generateFcmAccessToken($fcm_row["service_account_json"]);
            if (!$access_token) {
                $_SESSION["product_purchase_response"] = "Error: Could not obtain FCM access token. Check your Service Account JSON.";
            } else {
                $result = sendFcmTopicMessage(
                    $fcm_row["project_id"],
                    $access_token,
                    "app_updates",
                    ["type" => "app_update"]
                );
                if ($result["code"] === 200) {
                    $_SESSION["product_purchase_response"] = "Update notification broadcast to all app users successfully!";
                } else {
                    $err = ($result["body"]["error"]["message"] ?? null) ?? "Unknown error";
                    $_SESSION["product_purchase_response"] = "FCM Error ({$result["code"]}): $err";
                }
            }
        }
        header("Location: " . $_SERVER["REQUEST_URI"]);
        exit;
    }

    // Reload saved row after any saves
    $stmt = $connection_server->prepare("SELECT * FROM sas_fcm_settings WHERE vendor_id=? LIMIT 1");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $fcm_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Registered device count for this vendor
    $stmt = $connection_server->prepare(
        "SELECT COUNT(*) AS cnt FROM sas_device_tokens WHERE vendor_id=?"
    );
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $device_count_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $device_count = (int) ($device_count_row["cnt"] ?? 0);
?>
<!DOCTYPE html>
<head>
    <title>App Update Broadcast | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta http-equiv="Content-Type" content="text/html; " />
    <meta name="theme-color" content="black" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-admin-header.php"); ?>

    <div class="pagetitle">
        <h1>App Update Broadcast</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active">App Update Broadcast</li>
            </ol>
        </nav>
    </div>

    <section class="section">
      <div class="row">

        <!-- Broadcast card -->
        <div class="col-12 col-md-6">
          <div class="card info-card px-4 py-4">
            <h5 class="card-title">📣 Broadcast Update Notification</h5>
            <p class="text-muted">
              Sends a push notification to all users who have the DataGifting app installed.
              Their app will immediately check for a new APK version and prompt them to update.
            </p>
            <p>
              <strong>Registered devices:</strong>
              <span class="badge bg-primary fs-6"><?php echo $device_count; ?></span>
            </p>
            <div class="alert alert-info">
              <strong>Before broadcasting:</strong><br/>
              1. Upload your new APK to cPanel at <code>/public_html/apk/datagifting-X.X.X.apk</code><br/>
              2. Update the version in <code>web/api/app-update.php</code><br/>
              3. Click Broadcast below — users will be notified instantly.
            </div>
            <?php if (!$fcm_row): ?>
            <div class="alert alert-warning">
                ⚠️ FCM is not yet configured. Fill in your Firebase settings below first.
            </div>
            <?php endif; ?>
            <form method="post" action="">
                <button name="broadcast-update" type="submit"
                    class="btn btn-success col-12"
                    <?php echo (!$fcm_row ? 'disabled' : ''); ?>
                    onclick="return confirm('Send update notification to all <?php echo $device_count; ?> registered devices?')">
                    🚀 Broadcast Update Notification
                </button>
            </form>
          </div>
        </div>

        <!-- FCM settings card -->
        <div class="col-12 col-md-6">
          <div class="card info-card px-4 py-4">
            <h5 class="card-title">⚙️ Firebase (FCM) Settings</h5>
            <p class="text-muted">
              Required for sending push notifications. Get these from your
              <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a>:
              <br/>• <strong>Project ID</strong>: Firebase project settings → General → Project ID
              <br/>• <strong>Service Account JSON</strong>: Project settings → Service accounts → Generate new private key
            </p>
            <form method="post" action="">
                <div class="mb-3">
                    <label class="form-label">Firebase Project ID</label>
                    <input type="text" name="project_id" class="form-control"
                        placeholder="e.g. my-app-12345"
                        value="<?php echo htmlspecialchars($fcm_row["project_id"] ?? ""); ?>" required/>
                </div>
                <div class="mb-3">
                    <label class="form-label">Service Account JSON</label>
                    <textarea name="service_account_json" class="form-control" rows="8"
                        placeholder='Paste the contents of the service account JSON key file here...'
                        required><?php echo htmlspecialchars($fcm_row["service_account_json"] ?? ""); ?></textarea>
                </div>
                <button name="save-fcm-settings" type="submit" class="btn btn-primary col-12">
                    💾 Save FCM Settings
                </button>
            </form>
          </div>
        </div>

      </div><!-- /row -->
    </section>

    <?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>
