<?php session_start();
include("../func/bc-config.php");

$select_user_vendor_status_message = mysqli_query($connection_server, "SELECT * FROM sas_vendor_status_messages WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "'");
if (mysqli_num_rows($select_user_vendor_status_message) == 1) {
	$get_user_vendor_status_message = mysqli_fetch_array($select_user_vendor_status_message);
	if (!isset($_SESSION["product_purchase_response"]) && isset($_SESSION["user_session"])) {
		$user_vendor_status_message_template_encoded_text_array = array("{username}" => $get_logged_user_details["username"]);
		foreach ($user_vendor_status_message_template_encoded_text_array as $array_key => $array_val) {
			$user_vendor_status_message_template_text = str_replace($array_key, $array_val, $get_user_vendor_status_message["message"]);
		}
		$_SESSION["product_purchase_response"] = str_replace("\n", "<br/>", $user_vendor_status_message_template_text);
	}
}
if (isset($_POST["upgrade-user"])) {
	$account_level_upgrade_array = array("smart" => 1, "agent" => 2);
	$purchase_method = "web";
	$purchase_method = strtoupper($purchase_method);
	$purchase_method_array = array("WEB");

	if (in_array($purchase_method, $purchase_method_array)) {
		if ($purchase_method === "WEB") {
			$upgrade_type = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["upgrade-type"]))));
		}

		if ($account_level_upgrade_array[$upgrade_type] == true) {
			if ($account_level_upgrade_array[$upgrade_type] > $get_logged_user_details["account_level"]) {
				$get_upgrade_price = mysqli_query($connection_server, "SELECT * FROM sas_user_upgrade_price WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && account_type='" . $account_level_upgrade_array[$upgrade_type] . "'");
				if (mysqli_num_rows($get_upgrade_price) == 1) {
					$upgrade_price = mysqli_fetch_array($get_upgrade_price);
					if (!empty($upgrade_price["price"]) && is_numeric($upgrade_price["price"]) && ($upgrade_price["price"] > 0)) {
						if (!empty(userBalance(1)) && is_numeric(userBalance(1)) && (userBalance(1) > 0)) {
							$amount = $upgrade_price["price"];
							$discounted_amount = $amount;
							$type_alternative = ucwords("Account Upgrade");
							$reference = substr(str_shuffle("12345678901234567890"), 0, 15);
							$description = ucwords(accountLevel($account_level_upgrade_array[$upgrade_type]) . " Upgrade charges");
							$status = 1;

							$debit_user = chargeUser("debit", accountLevel($account_level_upgrade_array[$upgrade_type]), $type_alternative, $reference, "", $amount, $discounted_amount, $description, $purchase_method, $_SERVER["HTTP_HOST"], $status);
							if ($debit_user === "success") {
								$user_logged_name = $get_logged_user_details["username"];
								$account_upgrade_id = $account_level_upgrade_array[$upgrade_type];
								$alter_user_details = alterUser($user_logged_name, "account_level", $account_upgrade_id);
								if ($alter_user_details == "success") {
									// Email Beginning
									$upgrade_template_encoded_text_array = array("{firstname}" => $get_logged_user_details["firstname"], "{lastname}" => $get_logged_user_details["lastname"], "{account_level}" => $upgrade_type . " level");
									$raw_upgrade_template_subject = getUserEmailTemplate('user-upgrade', 'subject');
									$raw_upgrade_template_body = getUserEmailTemplate('user-upgrade', 'body');
									foreach ($upgrade_template_encoded_text_array as $array_key => $array_val) {
										$raw_upgrade_template_subject = str_replace($array_key, $array_val, $raw_upgrade_template_subject);
										$raw_upgrade_template_body = str_replace($array_key, $array_val, $raw_upgrade_template_body);
									}
									sendVendorEmail($get_logged_user_details["email"], $raw_upgrade_template_subject, $raw_upgrade_template_body);
									// Email End

									//Referral Function
									if (!empty($get_logged_user_details["referral_id"])) {
										$check_user_referral_details = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && id='" . $get_logged_user_details["referral_id"] . "'");
										if (mysqli_num_rows($check_user_referral_details) == 1) {
											$get_referral_details = mysqli_fetch_array($check_user_referral_details);
											$select_referral_percentage_details = mysqli_query($connection_server, "SELECT * FROM sas_referral_percents WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && account_level='" . $account_level_upgrade_array[$upgrade_type] . "'");
											if (mysqli_num_rows($select_referral_percentage_details) == 1) {
												$reference_3 = substr(str_shuffle("12345678901234567890"), 0, 15);
												$get_referral_percentage = mysqli_fetch_array($select_referral_percentage_details);
												$referral_commission = ($discounted_amount * ($get_referral_percentage["percentage"] / 100));
												$discounted_referral_commission = $referral_commission;
												chargeOtherUser($get_referral_details["username"], "credit", accountLevel($account_level_upgrade_array[$upgrade_type]) . " Referral Commission", "Referral Commission", $reference_3, "", $referral_commission, $discounted_referral_commission, "Referral Upgrade Commission from " . accountLevel($get_logged_user_details["account_level"]) . " to " . accountLevel($account_level_upgrade_array[$upgrade_type]) . " for user: " . $get_logged_user_details["username"], $purchase_method, $_SERVER["HTTP_HOST"], "1");
												// Email Beginning
												$referral_template_encoded_text_array = array("{firstname}" => $get_referral_details["firstname"], "{lastname}" => $get_referral_details["lastname"], "{referral_commission}" => toDecimal($discounted_referral_commission, 2), "{referree}" => $get_logged_user_details["username"], "{account_level}" => $upgrade_type . " level");
												$raw_referral_template_subject = getUserEmailTemplate('user-referral-commission', 'subject');
												$raw_referral_template_body = getUserEmailTemplate('user-referral-commission', 'body');
												foreach ($referral_template_encoded_text_array as $array_key => $array_val) {
													$raw_referral_template_subject = str_replace($array_key, $array_val, $raw_referral_template_subject);
													$raw_referral_template_body = str_replace($array_key, $array_val, $raw_referral_template_body);
												}
												sendVendorEmail($get_referral_details["email"], $raw_referral_template_subject, $raw_referral_template_body);
												// Email End
											}
										}
									}

									//Account Upgraded Successfully
									$json_response_array = array("desc" => "Account Upgraded Successfully");
									$json_response_encode = json_encode($json_response_array, true);
								} else {
									$reference_2 = substr(str_shuffle("12345678901234567890"), 0, 15);
									chargeUser("credit", accountLevel($account_level_upgrade_array[$upgrade_type]), "Refund", $reference_2, "", $amount, $discounted_amount, "Refund for Ref:<i>'$reference'</i>", $purchase_method, $_SERVER["HTTP_HOST"], "1");
									//Upgrade Failed, Contact Admin
									$json_response_array = array("desc" => "Upgrade Failed, Contact Admin");
									$json_response_encode = json_encode($json_response_array, true);
								}
							} else {
								//Insufficient Fund
								$json_response_array = array("desc" => "Insufficient Fund");
								$json_response_encode = json_encode($json_response_array, true);
							}
						} else {
							//Balance is LOW
							$json_response_array = array("desc" => "Balance is LOW");
							$json_response_encode = json_encode($json_response_array, true);
						}
					} else {
						//Pricing Error, Contact Admin
						$json_response_array = array("desc" => "Pricing Error, Contact Admin");
						$json_response_encode = json_encode($json_response_array, true);
					}
				} else {
					//Error: Pricing Not Available, Contact Admin
					$json_response_array = array("desc" => "Error: Pricing Not Available, Contact Admin");
					$json_response_encode = json_encode($json_response_array, true);
				}
			} else {
				//Error: Account Cannot Be Downgraded, Contact Admin
				$json_response_array = array("desc" => "Error: Account Cannot Be Downgraded, Contact Admin");
				$json_response_encode = json_encode($json_response_array, true);
			}
		} else {
			//Invalid Upgrade Type
			$json_response_array = array("desc" => "Invalid Upgrade Type");
			$json_response_encode = json_encode($json_response_array, true);
		}
	}
	$json_response_decode = json_decode($json_response_encode, true);
	$_SESSION["product_purchase_response"] = $json_response_decode["desc"];
	header("Location: " . $_SERVER["REQUEST_URI"]);
}

$virtual_account_vaccount_err = "";

// 1. Mandatory API-based generation (Requires BVN/NIN)
$registered_virtual_bank_arr = array();
$virtual_bank_code_arr = array("232", "035", "50515", "120001", "100039", "110072", "566", "090110");
$user_banks = getUserVirtualBank();
if (is_array($user_banks)) {
    foreach ($user_banks as $bank_json) {
        $bank_json = json_decode($bank_json, true);
        array_push($registered_virtual_bank_arr, $bank_json["bank_code"]);
    }
}


if ((!empty($select_vendor_table["bank_code"]) && is_numeric($select_vendor_table["bank_code"]) && !empty($select_vendor_table["bvn"]) && is_numeric($select_vendor_table["bvn"]) && strlen($select_vendor_table["bvn"]) == 11) || (!empty($get_logged_user_details["bank_code"]) && is_numeric($get_logged_user_details["bank_code"]) && !empty($get_logged_user_details["nin"]) && is_numeric($get_logged_user_details["nin"]) && strlen($get_logged_user_details["nin"]) == 11)) {
	//Admin BVN/NIN
	if ((!empty($select_vendor_table["bvn"]) && is_numeric($select_vendor_table["bvn"]) && strlen($select_vendor_table["bvn"]) == 11) && (!empty($select_vendor_table["nin"]) && is_numeric($select_vendor_table["nin"]) && strlen($select_vendor_table["nin"]) == 11)) {
		$verification_type = 1;
		$select_vendor_table_bvn = $select_vendor_table["bvn"];
		$select_vendor_table_nin = $select_vendor_table["nin"];
	} else {
		if ((!empty($select_vendor_table["bvn"]) && is_numeric($select_vendor_table["bvn"]) && strlen($select_vendor_table["bvn"]) == 11)) {
			$verification_type = 1;
			$select_vendor_table_bvn = $select_vendor_table["bvn"];
		} else {
			if ((!empty($select_vendor_table["nin"]) && is_numeric($select_vendor_table["nin"]) && strlen($select_vendor_table["nin"]) == 11)) {
				$verification_type = 2;
				$select_vendor_table_nin = $select_vendor_table["nin"];
			}
		}
	}
	
	if ((getUserVirtualBank() == false) || (!empty(array_diff(array("232", "035", "50515", "120001"), $registered_virtual_bank_arr)))) {
		//Monnify
		$get_monnify_access_token = json_decode(getUserMonnifyAccessToken(), true);
		if ($get_monnify_access_token["status"] == "success") {

			//Check If Monnify Virtual Account Exists
			$user_monnify_account_reference = md5($_SERVER["HTTP_HOST"] . "-" . $get_logged_user_details["vendor_id"] . "-" . $get_logged_user_details["username"]);
			$get_monnify_reserve_account = json_decode(makeMonnifyRequest("get", $get_monnify_access_token["token"], "api/v2/bank-transfer/reserved-accounts/" . $user_monnify_account_reference, ""), true);
			if ($get_monnify_reserve_account["status"] == "success") {
				$monnify_reserve_account_response = json_decode($get_monnify_reserve_account["json_result"], true);
				foreach ($monnify_reserve_account_response["responseBody"]["accounts"] as $monnify_accounts_json) {
					if (in_array($monnify_accounts_json["bankCode"], array("232", "035", "50515", "058"))) {
						
						addUserVirtualBank($user_monnify_account_reference, $monnify_accounts_json["bankCode"], $monnify_accounts_json["bankName"], $monnify_accounts_json["accountNumber"], $monnify_reserve_account_response["responseBody"]["accountName"], $get_logged_user_details["vendor_id"], $get_logged_user_details["username"], 'monnify');
					}
				}
			} else {
				$select_monnify_gateway_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_payment_gateways WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && gateway_name='monnify' LIMIT 1"));
				$monnify_create_reserve_account_array = array("accountReference" => $user_monnify_account_reference, "accountName" => $get_logged_user_details["firstname"] . " " . $get_logged_user_details["lastname"] . " " . $get_logged_user_details["othername"], "currencyCode" => "NGN", "contractCode" => $select_monnify_gateway_details["encrypt_key"], "customerEmail" => $get_logged_user_details["email"], "getAllAvailableBanks" => false, "preferredBanks" => ["232", "035", "50515", "058"]);
				if (strlen($select_vendor_table_bvn) === 11) {
					$monnify_create_reserve_account_array["bvn"] = $select_vendor_table_bvn;
				}
				if (strlen($select_vendor_table_nin) === 11) {
					$monnify_create_reserve_account_array["nin"] = $select_vendor_table_nin;
				}
				makeMonnifyRequest("post", $get_monnify_access_token["token"], "api/v2/bank-transfer/reserved-accounts", $monnify_create_reserve_account_array);
			}
		}


		//Payvessel Admin/User BVN Virtual Account Generation
		if ((!empty($select_vendor_table["bvn"]) && is_numeric($select_vendor_table["bvn"]) && strlen($select_vendor_table["bvn"]) == 11) || (!empty($get_logged_user_details["bvn"]) && is_numeric($get_logged_user_details["bvn"]) && strlen($get_logged_user_details["bvn"]) == 11)) {
			$get_payvessel_access_token = json_decode(getUserPayvesselAccessToken(), true);

			if ($get_payvessel_access_token["status"] == "success") {
				$select_payvessel_gateway_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_payment_gateways WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && gateway_name='payvessel' LIMIT 1"));
				$user_payvessel_account_reference = str_replace([".", "-", ":"], "", $_SERVER["HTTP_HOST"]) . "-" . $get_logged_user_details["username"] . "-" . $get_logged_user_details["email"];
				$payvessel_create_reserve_account_array = array("email" => $user_payvessel_account_reference, "name" => trim($get_logged_user_details["firstname"] . " " . $get_logged_user_details["lastname"] . " " . $get_logged_user_details["othername"]), "phoneNumber" => $get_logged_user_details["phone_number"], "businessid" => $select_payvessel_gateway_details["encrypt_key"], "bankcode" => ["101", "120001"], "account_type" => "STATIC");
				if (strlen($select_vendor_table_bvn) === 11) {
					$payvessel_create_reserve_account_array["bvn"] = $select_vendor_table_bvn;
				}
				if (strlen($select_vendor_table_nin) === 11) {
					$payvessel_create_reserve_account_array["nin"] = $select_vendor_table_nin;
				}
				$get_payvessel_reserve_account = json_decode(makePayvesselRequest("post", $get_payvessel_access_token["token"], "api/external/request/customerReservedAccount/", $payvessel_create_reserve_account_array), true);

				if ($get_payvessel_reserve_account["status"] == "success") {
					$payvessel_reserve_account_response = json_decode($get_payvessel_reserve_account["json_result"], true);

					foreach ($payvessel_reserve_account_response["banks"] as $payvessel_accounts_json) {
						
						addUserVirtualBank($payvessel_accounts_json["trackingReference"], $payvessel_accounts_json["bankCode"], $payvessel_accounts_json["bankName"], $payvessel_accounts_json["accountNumber"], $payvessel_accounts_json["accountName"], $get_logged_user_details["vendor_id"], $get_logged_user_details["username"], 'payvessel');
						
					}
				}
			}
		}

		//Beewave Admin/User BVN Virtual Account Generation
		if ((!empty($select_vendor_table["nin"]) && is_numeric($select_vendor_table["nin"]) && strlen($select_vendor_table["nin"]) == 11) || (!empty($get_logged_user_details["nin"]) && is_numeric($get_logged_user_details["nin"]) && strlen($get_logged_user_details["nin"]) == 11) || (!empty($select_vendor_table["bvn"]) && is_numeric($select_vendor_table["bvn"]) && strlen($select_vendor_table["bvn"]) == 11) || (!empty($get_logged_user_details["bvn"]) && is_numeric($get_logged_user_details["bvn"]) && strlen($get_logged_user_details["bvn"]) == 11)) {
			$select_beewave_gateway_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_payment_gateways WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && gateway_name='beewave' LIMIT 1"));
			$user_beewave_account_reference = str_replace([".", "-", ":"], "", $_SERVER["HTTP_HOST"]) . "-" . $get_logged_user_details["username"] . "-" . $get_logged_user_details["email"];
			$beewave_create_reserve_account_array = array("email" => $user_beewave_account_reference, "name" => trim($get_logged_user_details["firstname"] . " " . $get_logged_user_details["lastname"] . " " . $get_logged_user_details["othername"]), "phone" => $get_logged_user_details["phone_number"], "access_key" => $select_beewave_gateway_details["public_key"], "bank_code" => ["110072"]);
			if (strlen($select_vendor_table_bvn) === 11) {
				$beewave_create_reserve_account_array["bvn"] = $select_vendor_table_bvn;
			}else{
				$beewave_create_reserve_account_array["bvn"] = $get_logged_user_details["bvn"];
			}
			if (strlen($select_vendor_table_nin) === 11) {
				$beewave_create_reserve_account_array["nin"] = $select_vendor_table_nin;
			}else{
				$beewave_create_reserve_account_array["nin"] = $get_logged_user_details["nin"];
			}
			$get_beewave_reserve_account = json_decode(makeBeewaveRequest("post", "s", "api/v1/bank-transfer/virtual-account-numbers", $beewave_create_reserve_account_array), true);
			
			if ($get_beewave_reserve_account["status"] == "success") {
				$beewave_reserve_account_response = json_decode($get_beewave_reserve_account["json_result"], true);

				foreach ($beewave_reserve_account_response["virtual_accounts"] as $beewave_accounts_json) {
					
					addUserVirtualBank($beewave_accounts_json["tracking_ref"], $beewave_accounts_json["bank_code"], $beewave_accounts_json["bank_name"], $beewave_accounts_json["account_number"], $beewave_accounts_json["account_name"], $get_logged_user_details["vendor_id"], $get_logged_user_details["username"], 'beewave');
				}
			}
		}

	}
}
?>
<!DOCTYPE html>

<head>
	<title>Dashboard | <?php echo $get_all_site_details["site_title"]; ?></title>
	<meta charset="UTF-8" />
	<meta name="description" content="<?php echo substr($get_all_site_details["site_desc"], 0, 160); ?>" />
	<meta http-equiv="Content-Type" content="text/html; " />
	<meta name="theme-color" content="black" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
	<link rel="stylesheet" href="/cssfile/bc-style.css">
	<meta name="author" content="Philmore Codes">
	<meta name="dc.creator" content="Philmore Codes">
	
	<!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet">

    <script src="https://merchant.beewave.ng/checkout.min.js"></script> 
  <!-- Vendor CSS Files -->
  <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../assets-2/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../assets-2/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../assets-2/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets-2/css/style.css" rel="stylesheet">
  <style>
    .info-card.card-blue { background-color: #eef7ff; }
    .info-card.card-red { background-color: #fceeed; }
    .info-card.card-green { background-color: #eefcef; }
    .info-card.card-yellow { background-color: #fff9e6; }
    .info-card {
        border-radius: 15px;
    }
    .shadow {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
    }
    .auto-funding-container {
        display: flex;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch; /* for smooth scrolling on iOS */
        padding-bottom: 15px; /* space for scrollbar */
    }
    .auto-funding-container .bank-card {
        flex: 0 0 85%; /* Adjust width as needed, using percentage for responsiveness */
        max-width: 400px; /* Max width for larger screens */
        min-width: 300px; /* Min width for smaller screens */
        margin-right: 15px;
        display: inline-block;
        vertical-align: top;
        white-space: normal; /* Reset white-space for content inside the card */
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .auto-funding-container .bank-card:last-child {
        margin-right: 0;
    }
  </style>

</head>

<body>
	<?php include("../func/bc-header.php"); ?>
	
	<div class="pagetitle">
      <h1>DASHBOARD</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Dashboard</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">

        <!-- Account Type Card -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card info-card card-blue shadow">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $get_logged_user_details["username"]; ?> <span>| Account Type</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="ps-3">
                            <h6><?php echo accountLevel($get_logged_user_details["account_level"]); ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- End Account Type Card -->

        <!-- Balance Card -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card info-card card-red shadow">
                <div class="card-body">
                    <h5 class="card-title">Balance <span>| Current</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div class="ps-3">
                            <h6>₦<?php echo toDecimal($get_logged_user_details["balance"], "2"); ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- End Balance Card -->

        <!-- Total Deposit Card -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card info-card card-green shadow">
                <div class="card-body">
                    <h5 class="card-title">Deposit <span>| Total</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="ps-3">
                            <h6>₦<?php
                                $all_user_credit_transaction = 0;
            							$get_all_user_credit_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_transactions WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && username='" . $get_logged_user_details["username"] . "' && (type_alternative LIKE '%credit%' OR type_alternative LIKE '%received%' OR type_alternative LIKE '%commission%')");
            							if (mysqli_num_rows($get_all_user_credit_transaction_details) >= 1) {
            								while ($transaction_record = mysqli_fetch_assoc($get_all_user_credit_transaction_details)) {
            									$all_user_credit_transaction += $transaction_record["discounted_amount"];
            								}
            							}
            							echo toDecimal($all_user_credit_transaction, 2);
            						?>
                        </h6>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- End Total Deposit Card -->

        <!-- Total Spent Card -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card info-card card-yellow shadow">
                <div class="card-body">
                    <h5 class="card-title">Spent <span>| Total</span></h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-cart-x"></i>
                        </div>
                        <div class="ps-3">
                            <h6>₦<?php
                                $all_user_debit_transaction = 0;
              							$get_all_user_debit_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_transactions WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && username='" . $get_logged_user_details["username"] . "' && (type_alternative NOT LIKE '%credit%' && type_alternative NOT LIKE '%refund%' && type_alternative NOT LIKE '%received%' && type_alternative NOT LIKE '%commission%' && status NOT LIKE '%3%')");
              							if (mysqli_num_rows($get_all_user_debit_transaction_details) >= 1) {
              								while ($transaction_record = mysqli_fetch_assoc($get_all_user_debit_transaction_details)) {
              									$all_user_debit_transaction += $transaction_record["discounted_amount"];
              								}
              							}
              							echo toDecimal($all_user_debit_transaction, 2);
              					  ?>
                        </h6>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- End Total Spent Card -->

    </div>
        
        <!-- Row -->
        <div class="row">
          <!-- Col -->
          <div class="col-6 col-lg-3">
            <div class="card info-card sales-card p-3 align-items-center">
              <a href="Fund.php" class=""><h5 class="card-title">Wallet Funding</h5></a>
              </div>
          </div>
          <!-- Col End -->
          
           <!-- Col -->
          <div class="col-6 col-lg-3">
            <div class="card info-card sales-card p-3 align-items-center">
              <a href="ShareFund.php" class=""><h5 class="card-title">Fund Transfer</h5></a>
              </div>
          </div>
          <!-- Col End -->
          
           <!-- Col -->
          <div class="col-6 col-lg-3">
            <div class="card info-card sales-card p-3 align-items-center">
              <a href="SubmitPayment.php" class=""><h5 class="card-title">Submit Payment</h5></a>
              </div>
          </div>
          <!-- Col End -->
          
           <!-- Col -->
          <div class="col-6 col-lg-3">
            <div class="card info-card sales-card p-3 align-items-center">
              <a href="Transactions.php" class=""><h5 class="card-title">Transactions</h5></a>
              </div>
          </div>
          <!-- Col End -->
        </div>  
          <!-- Row End -->
        
        <!-- Row -->
        <div class="row">
          <!-- Col -->
          <div class="col-6 col-lg-3">
            <div class="card info-card sales-card p-3 align-items-center">
              <a href="Data.php" class="">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-wifi"></i>  
                  </div>
                  <h5 class="card-title">Buy Data</h5>
                </a>
              </div>
          </div>
          <!-- Col End -->
          
           <!-- Col -->
          <div class="col-6 col-lg-3">
            <div class="card info-card sales-card p-3 align-items-center">
              <a href="Airtime.php" class="">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-telephone"></i>  
                  </div>
                  <h5 class="card-title">Buy Airtime</h5>
                </a>
              </div>
          </div>
          <!-- Col End -->
          
           <!-- Col -->
          <div class="col-6 col-lg-3">
            <div class="card info-card sales-card p-3 align-items-center">
              <a href="Cable.php" class="">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                  <i class="bi bi-tv"></i>  
                </div>
                <h5 class="card-title">Cable Tv</h5>
              </a>
              </div>
          </div>
          <!-- Col End -->
          
           <!-- Col -->
          <div class="col-6 col-lg-3">
            <div class="card info-card sales-card p-3 align-items-center">
              <a href="Electric.php" class="">
                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                  <i class="bi bi-lightbulb"></i>  
                </div>
                <h5 class="card-title">Electric</h5>
              </a>
              </div>
          </div>
          <!-- Col End -->
        </div>  
          <!-- Row End -->
          
         
        <!-- Row for Auto Funding -->
        <?php if(isServiceEnabled('virtual_bank_display')): ?>
        <div class="row">
            <div class="col-12">
                <div class="card info-card sales-card p-3">
                    <div class="card-body">
                        <h5 class="card-title">Auto Funding</h5>
                        <?php
                        $virtual_banks_html = "";
                        $banks_list = getUserVirtualBank();
                        if(is_array($banks_list)) {
                            foreach ($banks_list as $bank_accounts_json) {
                                $bank_accounts_json = json_decode($bank_accounts_json, true);
                                $bname_up = strtoupper($bank_accounts_json["bank_name"]);
                                if (in_array($bank_accounts_json["bank_code"], $virtual_bank_code_arr) || strpos($bname_up, "WEMA") !== false || strpos($bname_up, "GLOBUS") !== false || strpos($bname_up, "VFD") !== false || strpos($bname_up, "PROVIDUS") !== false || strpos($bname_up, "TITAN") !== false || strpos($bname_up, "STERLING") !== false) {
                                    $virtual_banks_html .=
                                        '<div class="bank-card bg-white p-2">
                                            <div style="" class="bg-success d-inline-block rounded-2 rounded-bottom-0 col-12 px-2 py-2 mt-0">
                                                <h5 class="text-white">' . strtoupper($bank_accounts_json["account_name"]) . '</h5>
                                            </div>
                                            <div style="" class="bg-light d-flex rounded-2 rounded-top-0 col-12 px-2 py-1 mt-0 justify-content-center justify-content-between">
                                                <div class="row">
                                                    <div style="user-select: auto;" class="d-inline-block text-success h5 mt-2">' . strtoupper($bank_accounts_json["bank_name"]) . '</div><br>
                                                    <div style="user-select: auto;" class="d-inline-block text-success h3 mt-1">' . $bank_accounts_json["account_number"] . ' <span onclick="copyText(`Account number copied successfully`,`' . $bank_accounts_json["account_number"] . '`);" class="p-1 card-icon rounded-circle"><i title="Copy Account Number" class="bi bi-copy h3 text-success" ></i></span></div>
                                                </div>
                                                <div class="row">
                                                    <div style="user-select: auto;" class="col-12 d-inline-block text-success h5 mt-2 text-end">Charges<br/><h2>#50</h2></div>
                                                </div>
                                            </div>
                                            <div style="user-select: auto;" class="col-12 d-inline-block text-success fw-bold text-end mt-1 text-decoration-underline"><a href="#autofunding" onclick="moreAutoBanks();">View More</a></div>
                                        </div>';
                                }
                            }
                        }

                        if (!empty($virtual_banks_html)) {
                            echo '<div class="auto-funding-container">' . $virtual_banks_html . '</div>';
                        }
                        ?><br>
                        <?php
                        echo $virtual_account_vaccount_err;
                        ?>
                        <script>
                            let autoBanks = `<?php
                                $all_banks_html_2 = "";
                                $banks_list_2 = getUserVirtualBank();
                                if(is_array($banks_list_2)) {
                                    foreach ($banks_list_2 as $bj) {
                                        $bj = json_decode($bj, true);
                                        $all_banks_html_2 .= "<div class='text-start mb-3 pb-2 border-bottom'>";
                                        $all_banks_html_2 .= "<div class='small text-muted'>".strtoupper($bj['bank_name'])."</div>";
                                        $all_banks_html_2 .= "<div class='fw-bold fs-5'>".$bj['account_number']."</div>";
                                        $all_banks_html_2 .= "<div class='small text-success'>".strtoupper($bj['account_name'])."</div>";
                                        $all_banks_html_2 .= "</div>";
                                    }
                                }
                                echo $all_banks_html_2 ?: "No virtual accounts generated yet.";
                            ?>`;
                            function moreAutoBanks(){
                                Swal.fire({
                                    title: 'All Virtual Accounts',
                                    html: '<div style="max-height: 400px; overflow-y: auto;">' + autoBanks + '</div>',
                                    icon: 'info',
                                    confirmButtonText: 'Close'
                                });
                            }
                        </script>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- End Row for Auto Funding -->
        <!-- End Row for Auto Funding -->

        <!-- Row for Upgrade and Refer -->
        <div class="row">
          <!-- Upgrade Account Card -->
          <div class="col-lg-6 mb-4">
            <div class="card info-card sales-card p-3 h-100">
                  <div class="card-body">
                    <h5 class="card-title">UPGRADE ACCOUNT</h5>
                    <form method="post" action="">
                			<select name="upgrade-type" class="form-select" required>
                			<option value="" default hidden selected>Choose Account Level</option>
                			<?php
                			if (!empty($get_logged_user_details["account_level"])) {
                				$account_level_upgrade_array = array(1 => "smart", 2 => "agent");
                				foreach ($account_level_upgrade_array as $index => $account_levels) {
                					if ($index > $get_logged_user_details["account_level"]) {
                						$get_upgrade_price = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_user_upgrade_price WHERE vendor_id='" . $get_logged_user_details["vendor_id"] . "' && account_type='" . $index . "' LIMIT 1"));
                						echo '<option value="' . $account_levels . '">' . accountLevel($index) . ' @ N' . toDecimal($get_upgrade_price["price"], 2) . '</option>';
                					}
                				}
                			}
                			?>
                			</select><br />
                			<button name="upgrade-user" type="submit" class="btn btn-success col-12">
                				Proceed
                			</button>
                		</form>
                  </div>
              </div>
          </div>
          <!-- End Upgrade Account Card -->
          
          <!-- Refer Card -->
          <div class="col-lg-6 mb-4">
            <div class="card info-card sales-card p-3 align-items-center justify-content-center h-100">
                <div class="card-body text-center">
                    <h5 class="card-title">Refer and Earn Big!</h5>
                    <p>Copy your referral link and share it to earn commissions.</p>
                    <button class="btn btn-primary rounded-pill mt-2" onclick="copyReferLink();" title="Click To Copy">
                        <i class="bi bi-link-45deg"></i> Copy Link
                    </button>
                </div>
            </div>
            <script>
                let ReferLink = '<?php echo $web_http_host . "/web/Register.php?referral=" . $get_logged_user_details["username"]; ?>';
                const copyReferLink = async () => {
                    try {
                        await navigator.clipboard.writeText(ReferLink);
                        alert('Referral link copied to clipboard!');
                    } catch (err) {
                        alert('Failed to copy link: ' + err);
                    }
                }
            </script>
          </div>
          <!-- End Refer Card -->
        </div>  
        <!-- End Row for Upgrade and Refer -->
          

        
  
      </div>
    </section>
	<?php
	?>
	<?php include("../func/short-trans.php"); ?>
	<?php include("../func/bc-footer.php"); ?>

</body>

</html>
