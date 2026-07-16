<?php session_start();
    include("../func/bc-admin-config.php");
	
	if(isset($_POST["update-identity-provider"])){
        $allowed_providers = ["monnify", "dojah", "qoreid", "smileid", "localhost"];
        $identity_provider_gateways = ["dojah", "qoreid", "smileid"];
        $vendor_id = $get_logged_admin_details["id"];

        $selected_provider = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["identity_provider"] ?? "monnify")));
        if (!in_array($selected_provider, $allowed_providers)) $selected_provider = "monnify";
        
        $identity_api_id = (int)($_POST["identity_api_id"] ?? 0);
        mysqli_query($connection_server, "UPDATE sas_vendors SET identity_provider='$selected_provider', identity_api_id='$identity_api_id' WHERE id='$vendor_id'");

        // Save API keys for identity verification providers
        foreach ($identity_provider_gateways as $gw_name) {
            $pub  = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["idp_public_".$gw_name] ?? "")));
            $sec  = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["idp_secret_".$gw_name] ?? "")));
            $check = mysqli_query($connection_server, "SELECT gateway_name FROM sas_payment_gateways WHERE vendor_id='$vendor_id' AND gateway_name='$gw_name' LIMIT 1");
            if ($check && mysqli_num_rows($check) > 0) {
                if (!empty($pub) || !empty($sec)) {
                    $upd_parts = [];
                    if (!empty($pub)) $upd_parts[] = "public_key='$pub'";
                    if (!empty($sec)) $upd_parts[] = "secret_key='$sec'";
                    mysqli_query($connection_server, "UPDATE sas_payment_gateways SET ".implode(",", $upd_parts)." WHERE vendor_id='$vendor_id' AND gateway_name='$gw_name'");
                }
            } else {
                mysqli_query($connection_server, "INSERT INTO sas_payment_gateways (vendor_id, gateway_name, public_key, secret_key, encrypt_key, percentage, status) VALUES ('$vendor_id', '$gw_name', '$pub', '$sec', '', '0', '2')");
            }
        }
        $_SESSION["product_purchase_response"] = "Identity Verification Provider Updated Successfully";
        header("Location: ".$_SERVER["REQUEST_URI"]);
        exit();
    }



    if (isset($_POST["update-nin-card-pricing"])) {
        $nin_fee      = (float)$_POST["nin_card_fee"];
        $nin_fee_agent = (float)$_POST["nin_card_fee_agent"];
        $nin_fee_api  = (float)$_POST["nin_card_fee_api"];
        $vid = $get_logged_admin_details['id'];
        mysqli_query($connection_server, "UPDATE sas_vendors SET nin_card_fee='$nin_fee', nin_card_fee_agent='$nin_fee_agent', nin_card_fee_api='$nin_fee_api' WHERE id='$vid'");
        $_SESSION["product_purchase_response"] = "NIN Card pricing updated successfully.";
        header("Location: IdentityAPI.php");
        exit();
    }



    if (isset($_POST["update-bvn-verify-pricing"])) {
        $bvn_fee       = (float)$_POST["bvn_verify_fee"];
        $bvn_fee_agent = (float)$_POST["bvn_verify_fee_agent"];
        $bvn_fee_api   = (float)$_POST["bvn_verify_fee_api"];
        $vid = $get_logged_admin_details['id'];
        mysqli_query($connection_server, "UPDATE sas_vendors SET bvn_verify_fee='$bvn_fee', bvn_verify_fee_agent='$bvn_fee_agent', bvn_verify_fee_api='$bvn_fee_api' WHERE id='$vid'");
        $_SESSION["product_purchase_response"] = "BVN Verification pricing updated successfully.";
        header("Location: IdentityAPI.php");
        exit();
    }

?>
<!DOCTYPE html>
<head>
    <title>Identity Services & API | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    
    <!-- Vendor CSS Files -->
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/remixicon/remixicon.css" rel="stylesheet">

    <!-- Template Main CSS File -->
    <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-admin-header.php"); ?>    
	<div class="pagetitle">
      <h1>IDENTITY SERVICES MANAGER</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
          <li class="breadcrumb-item">API Manager</li>
          <li class="breadcrumb-item active">Identity Services</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row g-4 justify-content-center">
        <div class="col-lg-10">

            <!-- Identity Verification Provider Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-primary">Identity Verification Provider</h5>
                    <i class="bi bi-person-badge text-muted fs-4"></i>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info border-0 rounded-4 small mb-4">
                        Select which identity verification provider to use for BVN/NIN verification. Enter API keys for the providers you want to use. Name matching is always applied.
                    </div>
                    <form method="post">
                        <?php
                        $current_idp = getIdentityProvider($get_logged_admin_details["id"]);
                        $idp_providers = [
                            "monnify" => "Monnify (uses your Monnify gateway keys)",
                            "dojah"   => "Dojah",
                            "qoreid"  => "QoreID (VerifyMe)",
                            "smileid" => "Smile Identity",
                            "localhost" => "Localhost (Local Vendor API)"
                        ];
                        $idp_gateways = ["dojah", "qoreid", "smileid"];
                        $idp_key_labels = [
                            "dojah"  => ["public_key" => "App ID", "secret_key" => "Private Key"],
                            "qoreid" => ["public_key" => "Client ID", "secret_key" => "Client Secret"],
                            "smileid"=> ["public_key" => "Partner ID", "secret_key" => "API Key"],
                        ];
                        ?>
                        <div class="mb-4">
                            <label class="form-label fw-bold small">Active Provider</label>
                            <select name="identity_provider" class="form-select" id="idp-select">
                                <?php foreach ($idp_providers as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($current_idp === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php foreach ($idp_gateways as $gw): ?>
                        <?php
                            $gw_row = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT * FROM sas_payment_gateways WHERE vendor_id='".$get_logged_admin_details["id"]."' AND gateway_name='$gw' LIMIT 1"));
                            $gw_pub = $gw_row['public_key'] ?? '';
                            $gw_sec = $gw_row['secret_key'] ?? '';
                            $pub_label = $idp_key_labels[$gw]['public_key'];
                            $sec_label = $idp_key_labels[$gw]['secret_key'];
                            $gw_display = ($gw === 'smileid') ? 'Smile Identity' : ucwords($gw);
                        ?>
                        <div class="p-3 border rounded-4 mb-3 idp-keys-block" id="idp-keys-<?php echo $gw; ?>" style="display:<?php echo ($current_idp === $gw) ? '' : 'none'; ?>">
                            <h6 class="fw-bold mb-3"><?php echo $gw_display; ?> API Keys</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold"><?php echo $pub_label; ?></label>
                                    <input type="text" name="idp_public_<?php echo $gw; ?>" class="form-control" value="<?php echo htmlspecialchars($gw_pub); ?>" placeholder="Enter <?php echo $pub_label; ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold"><?php echo $sec_label; ?></label>
                                    <input type="password" name="idp_secret_<?php echo $gw; ?>" class="form-control" value="<?php echo htmlspecialchars($gw_sec); ?>" placeholder="Enter <?php echo $sec_label; ?>" />
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="p-3 border rounded-4 mb-3 idp-keys-block" id="idp-keys-localhost" style="display:<?php echo ($current_idp === 'localhost') ? '' : 'none'; ?>">
                            <h6 class="fw-bold mb-3">Local Marketplace API</h6>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Select Installed Identity API</label>
                                    <select name="identity_api_id" class="form-select">
                                        <option value="0">-- Select API --</option>
                                        <?php 
                                        $current_api_id = getIdentityApiId($get_logged_admin_details["id"]);
                                        $q_apis = mysqli_query($connection_server, "SELECT * FROM sas_apis WHERE vendor_id='".$get_logged_admin_details["id"]."' AND api_type='identity-verification'");
                                        while($api = mysqli_fetch_assoc($q_apis)): ?>
                                            <option value="<?php echo $api['id']; ?>" <?php echo ($current_api_id == $api['id']) ? 'selected' : ''; ?>>
                                                <?php echo strtoupper($api['api_base_url']); ?> (Status: <?php echo ($api['status'] == 1 ? 'Active' : 'Inactive'); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="form-text mt-2 small">These are Identity Verification APIs you have installed from the MarketPlace. Ensure the selected API is "Active".</div>
                                </div>
                            </div>
                        </div>

                        <button name="update-identity-provider" type="submit" class="btn btn-primary px-5 rounded-pill fw-bold">Save Provider Settings</button>
                    </form>
                    <script>
                    (function(){
                        var sel = document.getElementById('idp-select');
                        var blocks = document.querySelectorAll('.idp-keys-block');
                        function toggleBlocks(){
                            blocks.forEach(function(b){
                                b.style.display = b.id === 'idp-keys-'+sel.value ? '' : 'none';
                            });
                        }
                        sel.addEventListener('change', toggleBlocks);
                    })();
                    </script>
                </div>
            </div>

            <!-- NIN Card Service Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-success">NIN Card Service</h5>
                    <i class="bi bi-person-badge text-muted fs-4"></i>
                </div>
                <div class="card-body p-4">

                        <div class="alert alert-success border-0 rounded-3 mb-4 d-flex align-items-center gap-3">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                            <div>
                                <strong>NIN Card Service is Active.</strong>
                                Your users can now generate Digital NIN Slips via <a href="../web/NINCard.php">NINCard.php</a>.
                                Set pricing below and ensure your <strong>Identity Provider</strong> (Dojah or QoreID) is configured above.
                            </div>
                        </div>

                        <form method="post">
                            <div class="row g-4 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Fee — User (Level 1)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">₦</span>
                                        <input name="nin_card_fee" type="number" step="any" min="0"
                                               value="<?php echo $get_logged_admin_details['nin_card_fee']; ?>"
                                               class="form-control form-control-lg bg-light border-0" placeholder="300.00">
                                    </div>
                                    <small class="text-muted text-xs">Price charged to regular users.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Fee — Agent (Level 2)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">₦</span>
                                        <input name="nin_card_fee_agent" type="number" step="any" min="0"
                                               value="<?php echo $get_logged_admin_details['nin_card_fee_agent']; ?>"
                                               class="form-control form-control-lg bg-light border-0" placeholder="250.00">
                                    </div>
                                    <small class="text-muted text-xs">Price charged to agent-level users.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Fee — API (Level 3)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">₦</span>
                                        <input name="nin_card_fee_api" type="number" step="any" min="0"
                                               value="<?php echo $get_logged_admin_details['nin_card_fee_api']; ?>"
                                               class="form-control form-control-lg bg-light border-0" placeholder="200.00">
                                    </div>
                                    <small class="text-muted text-xs">Price charged to API-level users.</small>
                                </div>
                            </div>
                            <div class="mt-4 d-flex gap-3">
                                <button name="update-nin-card-pricing" type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="bi bi-save2 me-2"></i>Save NIN Card Pricing
                                </button>
                                <a href="NINCard.php" class="btn btn-outline-secondary rounded-pill px-4">
                                    <i class="bi bi-clock-history me-1"></i> View History
                                </a>
                            </div>
                        </form>

                </div>
            </div>

            <!-- BVN Verification Card -->
            <div id="bvn-verify" class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-fingerprint me-2"></i>BVN Verification Service</h5>
                    <i class="bi bi-bank text-muted fs-4"></i>
                </div>
                <div class="card-body p-4">

                        <div class="alert alert-success border-0 rounded-3 mb-4 d-flex align-items-center gap-3">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                            <div><strong>BVN Verification is Active!</strong> Your users can verify BVN numbers through the Print Hub.</div>
                        </div>
                        <form method="post">
                            <h6 class="fw-bold mb-3">Set Verification Fee by Account Level</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Smart (User) Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₦</span>
                                        <input name="bvn_verify_fee" type="number" step="any" min="0"
                                               value="<?php echo $get_logged_admin_details['bvn_verify_fee']; ?>"
                                               class="form-control" required>
                                    </div>
                                    <small class="text-muted text-xs">Price charged to regular users.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Agent Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₦</span>
                                        <input name="bvn_verify_fee_agent" type="number" step="any" min="0"
                                               value="<?php echo $get_logged_admin_details['bvn_verify_fee_agent']; ?>"
                                               class="form-control" required>
                                    </div>
                                    <small class="text-muted text-xs">Price charged to agent-level users.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">API Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₦</span>
                                        <input name="bvn_verify_fee_api" type="number" step="any" min="0"
                                               value="<?php echo $get_logged_admin_details['bvn_verify_fee_api']; ?>"
                                               class="form-control" required>
                                    </div>
                                    <small class="text-muted text-xs">Price charged to API-level users.</small>
                                </div>
                            </div>
                            <div class="mt-4 d-flex gap-3">
                                <button name="update-bvn-verify-pricing" type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm">
                                    <i class="bi bi-save2 me-2"></i>Save BVN Pricing
                                </button>
                                <a href="BVNVerification.php" class="btn btn-outline-secondary rounded-pill px-4">
                                    <i class="bi bi-clock-history me-1"></i> View History
                                </a>
                            </div>
                        </form>

                </div>
            </div>

        </div>
      </div>
    </section>

    <?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>
