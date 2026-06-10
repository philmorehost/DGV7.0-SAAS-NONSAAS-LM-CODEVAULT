<?php session_start();
    include("../func/bc-admin-config.php");
	
	$kyc_verification_array = array("bvn", "nin", "liveliness_video", "liveliness_picture", "govt_id", "proof_of_address");
    $payment_gateway_array = array("monnify", "flutterwave", "paystack", "payvessel", "payhub", "plisio");
	$payment_gateway_webhook_array = array("monnify" => $web_http_host . "/users-monnify.php", "flutterwave" => $web_http_host . "/users-flutterwave.php", "paystack" => $web_http_host . "/users-paystack.php", "payvessel" => $web_http_host . "/users-payvessel.php", "payhub" => $web_http_host . "/users-payhub.php", "plisio" => $web_http_host . "/users-plisio.php");
	
	if(isset($_POST["update-kyc-details"])){
        $force_kyc = isset($_POST["force_kyc"]) ? 1 : 0;
        $vendor_id = $get_logged_admin_details["id"];
        mysqli_query($connection_server, "UPDATE sas_vendors SET force_kyc='$force_kyc' WHERE id='$vendor_id'");

        $verification_names = $_POST["verification-name"] ?? [];
        
        if(count($verification_names) > 0){
            foreach($verification_names as $name){
                $each_verification_name = mysqli_real_escape_string($connection_server, trim(strip_tags($name)));
                $each_verification_status = isset($_POST["verification-status-".$each_verification_name]) ? "1" : "2";
                
                if(in_array($each_verification_name, $kyc_verification_array)){
                    $get_kyc_verification_details = mysqli_query($connection_server, "SELECT * FROM sas_kyc_verifications WHERE vendor_id='$vendor_id' && verification_name='$each_verification_name'");
                    if(mysqli_num_rows($get_kyc_verification_details) > 0){
                        mysqli_query($connection_server, "UPDATE sas_kyc_verifications SET status='$each_verification_status' WHERE vendor_id='$vendor_id' && verification_name='$each_verification_name'");
                    }else{
                        mysqli_query($connection_server, "INSERT INTO sas_kyc_verifications (vendor_id, verification_name, status) VALUES ('$vendor_id', '$each_verification_name', '$each_verification_status')");
                    }
                }
            }
            $_SESSION["product_purchase_response"] = "KYC Verification Information Updated Successfully";
        }else{
            $_SESSION["product_purchase_response"] = "Verification Field Not Available";
        }
        header("Location: ".$_SERVER["REQUEST_URI"]);
        exit();
    }

	
    if(isset($_POST["update-withdrawal-settings"])){
        $withdrawal_fee = mysqli_real_escape_string($connection_server, (float)$_POST["withdrawal_fee"]);
        $crypto_swap_fee = mysqli_real_escape_string($connection_server, (float)$_POST["crypto_swap_fee"]);
        $approve_withdrawal = isset($_POST["approve_withdrawal"]) ? 1 : 0;
        $payout_provider = mysqli_real_escape_string($connection_server, trim($_POST["payout_provider"]));

        mysqli_query($connection_server, "UPDATE sas_vendors SET withdrawal_fee='$withdrawal_fee', crypto_swap_fee='$crypto_swap_fee', approve_withdrawal='$approve_withdrawal', payout_provider='$payout_provider' WHERE id='".$get_logged_admin_details["id"]."'");
        $_SESSION["product_purchase_response"] = "Settings Updated Successfully";
        header("Location: ".$_SERVER["REQUEST_URI"]);
        exit();
    }

    // STANDALONE: Payout activation fee removed
    // STANDALONE: Plisio activation fee removed


    if(isset($_POST["update-withdrawal-gateway-details"])){
        $gateway_name = $_POST["gateway-name"];
        $public_key = $_POST["public-key"];
        $secret_key = $_POST["secret-key"];
        $encrypt_key = $_POST["encrypt-key"];
        $withdrawal_gateway_array = array("payhub", "paystack");

        if(count($gateway_name) > 0){
            foreach($gateway_name as $index => $name){
                $each_gateway_name = mysqli_real_escape_string($connection_server, trim(strip_tags($gateway_name[$index])));
                $each_public_key = mysqli_real_escape_string($connection_server, trim(strip_tags($public_key[$index])));
                $each_secret_key = mysqli_real_escape_string($connection_server, trim(strip_tags($secret_key[$index])));
                $each_encrypt_key = mysqli_real_escape_string($connection_server, trim(strip_tags($encrypt_key[$index])));
                $each_gateway_status = isset($_POST["withdrawal-status-".$each_gateway_name]) ? "1" : "2";

                // Safety: Only update if secret key is provided, or if just changing status
                if(in_array($each_gateway_name, $withdrawal_gateway_array)){
                    if (!empty($each_secret_key)) {
                        mysqli_query($connection_server, "INSERT INTO sas_bank_transfer_gateways (vendor_id, gateway_name, public_key, secret_key, encrypt_key, transfer_fee, status)
                            VALUES ('".$get_logged_admin_details["id"]."', '$each_gateway_name', '$each_public_key', '$each_secret_key', '$each_encrypt_key', '0', '$each_gateway_status')
                            ON DUPLICATE KEY UPDATE public_key='$each_public_key', secret_key='$each_secret_key', encrypt_key='$each_encrypt_key', status='$each_gateway_status'");
                    } else {
                        // Only update status if secret is empty (to avoid overwriting with blank)
                        mysqli_query($connection_server, "UPDATE sas_bank_transfer_gateways SET status='$each_gateway_status' WHERE vendor_id='".$get_logged_admin_details["id"]."' AND gateway_name='$each_gateway_name'");
                    }
                }
            }
            $_SESSION["product_purchase_response"] = "Withdrawal Gateway Information Updated Successfully";
        }
        header("Location: ".$_SERVER["REQUEST_URI"]);
        exit();
    }

    if(isset($_POST["update-gateway-details"])){
        $gateway_name = $_POST["gateway-name"];
        $public_key = $_POST["public-key"];
        $secret_key = $_POST["secret-key"];
        $encrypt_key = $_POST["encrypt-key"];
        $payment_percent = $_POST["payment-percent"];
        $gateway_array_list = $payment_gateway_array;

        if((count($gateway_name) > 0) && (count($public_key) > 0) && (count($secret_key) > 0) && (count($public_key) == count($secret_key))){
            foreach($gateway_name as $index => $name){
                $each_gateway_name = mysqli_real_escape_string($connection_server, trim(strip_tags($gateway_name[$index])));

                // STANDALONE: plisio_activated check removed — always allow update

                $each_public_key = mysqli_real_escape_string($connection_server, trim(strip_tags($public_key[$index])));
                $each_secret_key = mysqli_real_escape_string($connection_server, trim(strip_tags($secret_key[$index])));
                $each_encrypt_key = mysqli_real_escape_string($connection_server, trim(strip_tags($encrypt_key[$index])));
                $each_payment_percent = mysqli_real_escape_string($connection_server, trim(strip_tags($payment_percent[$index])));
                
                //$each_gateway_status_unrefined = mysqli_real_escape_string($connection_server, preg_replace("/[^0-9]+/","",trim(strip_tags( $_POST["gateway-status-".$each_gateway_name] ))));
                if(isset($_POST["gateway-status-".$each_gateway_name])){
                    $each_gateway_status = "1";
                }else{
                	$each_gateway_status = "2";
                }

                if(in_array($each_gateway_name, $gateway_array_list)){
                    $get_payment_gateway_details = mysqli_query($connection_server, "SELECT * FROM sas_payment_gateways WHERE vendor_id='".$get_logged_admin_details["id"]."' && gateway_name='$each_gateway_name'");
                    if(mysqli_num_rows($get_payment_gateway_details) == 1){
                        mysqli_query($connection_server, "UPDATE sas_payment_gateways SET public_key='$each_public_key', secret_key='$each_secret_key', encrypt_key='$each_encrypt_key', percentage='$each_payment_percent', status='$each_gateway_status' WHERE vendor_id='".$get_logged_admin_details["id"]."' && gateway_name='$each_gateway_name'");
                        //Payment Gateway Information Updated Successfully
                        $json_response_array = array("desc" => "Payment Gateway Information Updated Successfully");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                        if(mysqli_num_rows($get_payment_gateway_details) == 0){
                            mysqli_query($connection_server, "INSERT INTO sas_payment_gateways (vendor_id, gateway_name, public_key, secret_key, encrypt_key, percentage, status) VALUES ('".$get_logged_admin_details["id"]."', '$each_gateway_name', '$each_public_key', '$each_secret_key', '$each_encrypt_key', '$each_payment_percent', '$each_gateway_status')");
                            //Payment Gateway Information Created Successfully
                            $json_response_array = array("desc" => "Payment Gateway Information Created Successfully");
                            $json_response_encode = json_encode($json_response_array,true);
                        }else{
                            if(mysqli_num_rows($get_payment_gateway_details) > 1){
                                //Duplicated Details, Contact Admin
                                $json_response_array = array("desc" => "Duplicated Details, Contact Admin");
                                $json_response_encode = json_encode($json_response_array,true);
                            }
                        }
                    }
                }else{
                    //cannot show the error once
                }
            }
        }else{
            if((count($gateway_name) < 1)){
                //Gateway Field Not Available
                $json_response_array = array("desc" => "Gateway Field Not Available");
                $json_response_encode = json_encode($json_response_array,true);
            }else{
                if((count($public_key) < 1)){
                    //Public Key Field Not Available
                    $json_response_array = array("desc" => "Public Key Field Not Available");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if((count($secret_key) < 1)){
                        //Secret Key Field Not Available
                        $json_response_array = array("desc" => "Secret Key Field Not Available");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                    	if((count($encrypt_key) < 1)){
                    		//Encrypt Key Field Not Available
                    		$json_response_array = array("desc" => "Encrypt Key Field Not Available");
                    		$json_response_encode = json_encode($json_response_array,true);
                    	}else{
                    		if((count($payment_percent) < 1)){
                    			//Payment Percentage Field Not Available
                    			$json_response_array = array("desc" => "Payment Percentage Field Not Available");
                	    		$json_response_encode = json_encode($json_response_array,true);
                    		}else{
                    			if((count($public_key) !== count($secret_key)) || (count($secret_key) !== count($encrypt_key)) || (count($encrypt_key) !== count($payment_percent))){
                        			//Incomplete Field
                            		$json_response_array = array("desc" => "Incomplete Field");
                           		 	$json_response_encode = json_encode($json_response_array,true);
                    			}
                    		}
                    	}
                    }
                }
            }
        }

        $json_response_decode = json_decode($json_response_encode,true);
        $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
        header("Location: ".$_SERVER["REQUEST_URI"]);
    }

?>
<!DOCTYPE html>
<head>
    <title>Payment Gateway | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="description" content="<?php echo substr($get_all_super_admin_site_details["site_desc"], 0, 160); ?>" />
    <meta http-equiv="Content-Type" content="text/html; " />
    <meta name="theme-color" content="black" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <meta name="author" content="Philmore Codes">
    <meta name="dc.creator" content="Philmore Codes">
    
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
      <h1>PAYMENT GATEWAYS</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Payment Gateways</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row g-4 justify-content-center">
        <div class="col-lg-10">

            <!-- Finance & Withdrawal Settings Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-primary">Finance & Withdrawal Settings</h5>
                    <i class="bi bi-bank text-muted fs-4"></i>
                </div>
                <div class="card-body p-4">
                    <?php // STANDALONE: Payout lock screen removed — always show withdrawal settings ?>
                        <form method="post">
                            <div class="row g-4 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Payout API Provider</label>
                                    <select name="payout_provider" class="form-select form-control-lg bg-light border-0">
                                        <option value="" <?php echo empty($get_logged_admin_details['payout_provider']) ? 'selected' : ''; ?>>-- Select Provider --</option>
                                        <option value="payhub" <?php echo ($get_logged_admin_details['payout_provider'] == 'payhub') ? 'selected' : ''; ?>>PayHub (Automated)</option>
                                        <option value="paystack" <?php echo ($get_logged_admin_details['payout_provider'] == 'paystack') ? 'selected' : ''; ?>>Paystack (Automated)</option>
                                    </select>
                                    <small class="text-muted text-xs">Choose the provider for automated bank transfers.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Withdrawal Fee (NGN)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">₦</span>
                                        <input name="withdrawal_fee" type="number" step="0.01" value="<?php echo $get_logged_admin_details['withdrawal_fee']; ?>" class="form-control form-control-lg bg-light border-0" placeholder="0.00" />
                                    </div>
                                    <small class="text-muted text-xs">Charge applied to every bank withdrawal.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Crypto Swap Fee (%)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">%</span>
                                        <input name="crypto_swap_fee" type="number" step="0.01" value="<?php echo $get_logged_admin_details['crypto_swap_fee']; ?>" class="form-control form-control-lg bg-light border-0" placeholder="0.00" />
                                    </div>
                                    <small class="text-muted text-xs">Charge for swapping Crypto to NGN.</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-4 bg-light d-flex align-items-center justify-content-between">
                                        <div>
                                            <h6 class="fw-bold mb-1">Manual Approval</h6>
                                            <p class="small text-muted mb-0">Review and approve all withdrawals manually.</p>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input fs-3" type="checkbox" role="switch" name="approve_withdrawal" value="1" <?php echo ($get_logged_admin_details['approve_withdrawal'] == 1) ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <button name="update-withdrawal-settings" type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm">
                                        <i class="bi bi-check-circle me-2"></i> Save Withdrawal Settings
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr class="my-5 opacity-25">

                        <h6 class="fw-bold text-dark mb-4"><i class="bi bi-key me-2 text-primary"></i>Withdrawal Gateway API Credentials</h6>
                        <p class="small text-muted mb-4">Set independent credentials for your withdrawal provider. This isolates your payout API from your user funding gateways to prevent "Invalid Key" errors.</p>

                        <form method="post">
                            <div class="row g-4">
                                <?php
                                $withdrawal_gateways = ["payhub", "paystack"];
                                foreach($withdrawal_gateways as $wg_name):
                                    $q_wg = mysqli_query($connection_server, "SELECT * FROM sas_bank_transfer_gateways WHERE vendor_id='".$get_logged_admin_details["id"]."' && gateway_name='$wg_name'");
                                    $wg_data = mysqli_fetch_assoc($q_wg);
                                    $wg_active = ($wg_data && $wg_data['status'] == 1);
                                ?>
                                <div class="col-md-6">
                                    <div class="p-4 border rounded-4 bg-light shadow-none">
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <h6 class="fw-bold mb-0 text-uppercase"><?php echo $wg_name; ?> PAYOUT API</h6>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input fs-5" type="checkbox" role="switch" name="withdrawal-status-<?php echo $wg_name; ?>" value="1" <?php echo $wg_active ? 'checked' : ''; ?>>
                                                <input name="gateway-name[]" value="<?php echo $wg_name; ?>" hidden />
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-muted">PUBLIC KEY</label>
                                            <input name="public-key[]" type="text" value="<?php echo $wg_data['public_key'] ?? ''; ?>" class="form-control rounded-3" placeholder="Enter Public Key" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-muted">SECRET KEY</label>
                                            <input name="secret-key[]" type="password" value="<?php echo $wg_data['secret_key'] ?? ''; ?>" class="form-control rounded-3" placeholder="Enter Secret Key" />
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label small fw-bold text-muted"><?php echo ($wg_name == 'paystack') ? 'NOT REQUIRED' : 'ENCRYPT KEY / APP ID'; ?></label>
                                            <input name="encrypt-key[]" type="text" value="<?php echo $wg_data['encrypt_key'] ?? ''; ?>" class="form-control rounded-3" placeholder="Optional" />
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4">
                                <button name="update-withdrawal-gateway-details" type="submit" class="btn btn-dark rounded-pill px-5 fw-bold shadow-sm">
                                    Update Withdrawal Keys
                                </button>
                            </div>
                        </form>
                </div>
            </div>


            <!-- Vendor KYC Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-primary">Vendor KYC Enforcement</h5>
                    <i class="bi bi-shield-check text-muted fs-4"></i>
                </div>
                <div class="card-body p-4">
                    <form method="post">
                        <div class="row g-4 mb-4">
                            <div class="col-md-12">
                                <div class="p-3 border border-primary rounded-4 bg-primary bg-opacity-10 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark-primary">Global KYC Enforcement</h6>
                                        <p class="small text-dark-primary mb-0" style="opacity: 0.8;">Force users to verify identity before accessing key services.</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input fs-3" type="checkbox" role="switch" name="force_kyc" value="1" <?php echo (($get_logged_admin_details['force_kyc'] ?? 0) == 1) ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>

                            <?php foreach($kyc_verification_array as $verification_name):
                                $get_verification_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_kyc_verifications WHERE vendor_id='".$get_logged_admin_details["id"]."' && verification_name='$verification_name'"));
                                $is_active = ($get_verification_details && $get_verification_details["status"] == 1);

                                $kyc_names_map = [
                                    'govt_id' => 'Government ID Card',
                                    'bvn' => 'BVN Number',
                                    'nin' => 'NIN Number',
                                    'proof_of_address' => 'Proof of Address Document'
                                ];
                                $friendly_name = isset($kyc_names_map[$verification_name]) ? $kyc_names_map[$verification_name] : ucwords(str_replace("_", " ", $verification_name));
                            ?>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-4 bg-light d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?php echo $friendly_name; ?></h6>
                                        <p class="small text-muted mb-0"><?php echo $is_active ? 'Forced validation required' : 'Validation currently optional'; ?></p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input fs-4" type="checkbox" role="switch" name="verification-status-<?php echo $verification_name; ?>" value="1" <?php echo $is_active ? 'checked' : ''; ?>>
                                        <input name="verification-name[]" value="<?php echo $verification_name; ?>" hidden />
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button name="update-kyc-details" type="submit" class="btn btn-primary px-5 rounded-pill fw-bold">Update KYC Rules</button>
                    </form>
                </div>
            </div>


            <!-- Payment Gateways Grid -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-primary">Integrated Payment Gateways</h5>
                    <div class="badge bg-primary bg-opacity-10 text-dark-primary rounded-pill px-3">Auto-Funding</div>
                </div>
                <div class="card-body p-4">
                    <form method="post">
                        <div class="row g-4">
                            <?php foreach($payment_gateway_array as $gateway_name):
                                $get_gateway_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_payment_gateways WHERE vendor_id='".$get_logged_admin_details["id"]."' && gateway_name='$gateway_name'"));
                                $is_enabled = ($get_gateway_details && $get_gateway_details["status"] == 1);
                                $is_plisio = ($gateway_name == 'plisio');
                                $is_activated = ($is_plisio) ? ($get_logged_admin_details['plisio_activated'] == 1) : true;
                            ?>
                            <div class="col-md-6">
                                <div class="card border rounded-4 h-100 mb-0 shadow-none">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-3 p-2 me-3 shadow-sm">
                                                    <i class="bi bi-wallet2 text-primary fs-5"></i>
                                                </div>
                                                <h6 class="fw-bold mb-0"><?php echo strtoupper($gateway_name); ?></h6>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input fs-5" type="checkbox" role="switch" name="gateway-status-<?php echo $gateway_name; ?>" value="1" <?php echo $is_enabled ? 'checked' : ''; ?>>
                                                <input name="gateway-name[]" value="<?php echo $gateway_name; ?>" hidden />
                                            </div>
                                        </div>

                                        <?php // STANDALONE: Plisio lock screen removed — always show key input fields ?>
                                            <div class="mb-3" <?php if($gateway_name == 'plisio') echo 'style="display:none;"'; ?>>
                                                <label class="form-label small fw-bold text-muted">PUBLIC KEY</label>
                                                <input name="public-key[]" type="text" value="<?php echo $get_gateway_details["public_key"]; ?>" class="form-control rounded-3" placeholder="<?php echo ($gateway_name == 'plisio') ? 'Not Required' : 'Enter Public Key'; ?>" />
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-muted">SECRET KEY</label>
                                                <input name="secret-key[]" type="password" value="<?php echo $get_gateway_details["secret_key"]; ?>" class="form-control rounded-3" placeholder="Enter Secret Key" />
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-6" <?php if($gateway_name == 'plisio') echo 'style="display:none;"'; ?>>
                                                    <label class="form-label small fw-bold text-muted"><?php echo ($gateway_name === 'monnify') ? 'CONTRACT CODE' : 'ENCRYPT KEY'; ?></label>
                                                    <input name="encrypt-key[]" type="text" value="<?php echo $get_gateway_details["encrypt_key"]; ?>" class="form-control rounded-3" placeholder="<?php echo ($gateway_name === 'monnify') ? 'Enter Contract Code' : ($gateway_name == 'plisio' ? 'Not Required' : 'Optional'); ?>" />
                                                </div>
                                                <div class="<?php echo ($gateway_name == 'plisio') ? 'col-12' : 'col-6'; ?>">
                                                    <label class="form-label small fw-bold text-muted">FEE (%)</label>
                                                    <input name="payment-percent[]" type="number" step="0.001" value="<?php echo $get_gateway_details["percentage"]; ?>" class="form-control rounded-3" placeholder="0.000" />
                                                </div>
                                            </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-muted">WEBHOOK URL</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" value="<?php echo $payment_gateway_webhook_array[$gateway_name]; ?>" class="form-control bg-light border-0" readonly />
                                                <button class="btn btn-outline-secondary border-0" type="button" onclick="navigator.clipboard.writeText('<?php echo $payment_gateway_webhook_array[$gateway_name]; ?>')"><i class="bi bi-clipboard"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-5 text-center">
                            <button name="update-gateway-details" type="submit" class="btn btn-primary btn-lg px-5 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-save2 me-2"></i>Save All Gateway Configurations
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
    // STANDALONE: initiatePayoutActivation and initiatePlisioActivation removed
    </script>
</body>
</html>