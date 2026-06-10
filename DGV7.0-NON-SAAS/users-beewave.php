<?php session_start();
include(__DIR__ . "/func/bc-connect.php");

$catch_incoming_request = json_decode(file_get_contents("php://input"), true);

//Select Vendor Table
$vendor_id = resolveVendorID();
$select_vendor_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='$vendor_id' AND status=1 LIMIT 1"));
if ($select_vendor_table) {
	$beewave_keys = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT * FROM sas_payment_gateways WHERE vendor_id='" . $select_vendor_table["id"] . "' && gateway_name='beewave'"));
	
	$customer_name = "";
	$customer_phone_number = $catch_incoming_request["data"]["customer"]["phone"];
	$customer_email = $catch_incoming_request["data"]["customer"]["email"];
	$amount_paid = floatval($catch_incoming_request['data']['amount_paid']);
	$amount_deposited = floatval($catch_incoming_request['data']['settlement_amount']);
	$transaction_id = $catch_incoming_request["data"]["transaction_ref"];
	$virtual_accountno = $catch_incoming_request["data"]["destination"]["account_number"];
	$virtual_bankcode = $catch_incoming_request["data"]["destination"]["bank_code"];
	$virtual_bankname = $catch_incoming_request["data"]["destination"]["bank_name"];
	$virtual_accounttype = $catch_incoming_request["data"]["destination"]["account_type"];
	$payment_method = "BANK TRANSFER";
	$exp_customer_detail = array_filter(explode("-", trim($customer_email)));
	$customer_username = $exp_customer_detail[1];
	$customer_mail = $exp_customer_detail[2];
	$vendor_id = trim($select_vendor_table["id"]);

	if ($virtual_accounttype == "static") {
		$check_if_banks_exists = mysqli_query($connection_server, "SELECT * FROM sas_user_banks WHERE vendor_id='$vendor_id' && username='$customer_username' && account_number='$virtual_accountno' && bank_code='$virtual_bankcode'");

		if (mysqli_num_rows($check_if_banks_exists) == 1) {
			$get_payment_details = mysqli_fetch_array($check_if_banks_exists);

			$beewave_verify_transaction = json_decode(confirmPaymentDeposited("GET", "https://merchant.beewave.ng/api/v1/collection/verify?access_key=" . $beewave_keys["public_key"] . "&&transaction_ref=" . $transaction_id, ["Accept: application/json", "Content-Type: application/json"], ""), true);

			$user_id = $get_payment_details["username"];
			$reference = substr(str_shuffle("12345678901234567890"), 0, 15);
			$check_vendor_user_exists = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vendor_id' && username='$user_id'");
			if (mysqli_num_rows($check_vendor_user_exists) == 1) {
				$get_logged_user_details = mysqli_fetch_array($check_vendor_user_exists);
				$_SESSION["user_session"] = $get_logged_user_details["username"];

				$select_transaction_history = mysqli_query($connection_server, "SELECT * FROM sas_transactions WHERE (reference='$transaction_id' OR api_reference='$transaction_id')");

				if (($catch_incoming_request['data']['settlement_amount'] == $beewave_verify_transaction['data']['settlement_amount']) && ($beewave_verify_transaction['status'] == true && $beewave_verify_transaction['data']['status'] == "success") && ($catch_incoming_request['status'] == true && $catch_incoming_request['data']['status'] == "success")) {
					if (mysqli_num_rows($select_transaction_history) == 0) {
						chargeUser("credit", $_SESSION["user_session"], "Wallet Credit", $reference, $transaction_id, $amount_paid, $amount_deposited, "Beewave Wallet Credit - " . str_replace("_", " ", $payment_method), strtoupper("WEB"), $_SERVER["HTTP_HOST"], "1");
						unset($_SESSION["user_session"]);
					}
				}
			}


		}
	}

	if ($virtual_accounttype == "dynamic") {
		
		$beewave_verify_transaction = json_decode(confirmPaymentDeposited("GET", "https://merchant.beewave.ng/api/v1/collection/verify?access_key=" . $beewave_keys["public_key"] . "&transaction_ref=" . $transaction_id, ["Accept: application/json", "Content-Type: application/json"], ""), true);
		
		$user_id = $customer_username;
		$reference = substr(str_shuffle("12345678901234567890"), 0, 15);
		$check_vendor_user_exists = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vendor_id' && username='$user_id'");
		if (mysqli_num_rows($check_vendor_user_exists) == 1) {
			$get_logged_user_details = mysqli_fetch_array($check_vendor_user_exists);
			$_SESSION["user_session"] = $get_logged_user_details["username"];

			$select_transaction_history = mysqli_query($connection_server, "SELECT * FROM sas_transactions WHERE (reference='$transaction_id' OR api_reference='$transaction_id')");
		
			if (($catch_incoming_request['data']['settlement_amount'] == $beewave_verify_transaction['data']['settlement_amount']) && ($beewave_verify_transaction['status'] == true && $beewave_verify_transaction['data']['status'] == "success") && ($catch_incoming_request['status'] == true && $catch_incoming_request['data']['status'] == "success")) {
				if (mysqli_num_rows($select_transaction_history) == 0) {
					chargeUser("credit", $_SESSION["user_session"], "Wallet Credit", $reference, $transaction_id, $amount_paid, $amount_deposited, "Beewave Wallet Credit - " . str_replace("_", " ", $payment_method) ." (".$virtual_bankname." (DYNAMIC ACCOUNT) - ".$virtual_accountno.")", strtoupper("WEB"), $_SERVER["HTTP_HOST"], "1");
					unset($_SESSION["user_session"]);
				}
			}
		}
	}

}

function confirmPaymentDeposited($method, $url, $header, $json)
{
	$apiwalletBalance = curl_init($url);
	$apiwalletBalanceUrl = $url;
	curl_setopt($apiwalletBalance, CURLOPT_URL, $apiwalletBalanceUrl);
	curl_setopt($apiwalletBalance, CURLOPT_RETURNTRANSFER, true);
	if ($method == "POST") {
		curl_setopt($apiwalletBalance, CURLOPT_POST, true);
	}

	if ($method == "GET") {
		curl_setopt($apiwalletBalance, CURLOPT_HTTPGET, true);
	}

	if ($header == true) {
		curl_setopt($apiwalletBalance, CURLOPT_HTTPHEADER, $header);
	}
	if ($json == true) {
		curl_setopt($apiwalletBalance, CURLOPT_POSTFIELDS, $json);
	}
	curl_setopt($apiwalletBalance, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($apiwalletBalance, CURLOPT_SSL_VERIFYPEER, false);

	$GetAPIBalanceJSON = curl_exec($apiwalletBalance);
	return $GetAPIBalanceJSON;
}
?>