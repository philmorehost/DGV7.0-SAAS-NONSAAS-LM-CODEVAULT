<?php session_start();
	include(__DIR__."/func/bc-connect.php");
	
	$catch_incoming_request = json_decode(file_get_contents("php://input"),true);
	//Select Vendor Table
    $vendor_id = resolveVendorID();
	$select_vendor_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='$vendor_id' AND status=1 LIMIT 1"));
	if($select_vendor_table){
		$flutterwave_keys = mysqli_fetch_assoc(mysqli_query($connection_server,"SELECT * FROM sas_payment_gateways WHERE vendor_id='".$select_vendor_table["id"]."' && gateway_name='flutterwave'"));
		
		$flutterwave_verify_transaction = json_decode(confirmPaymentDeposited("GET","https://api.flutterwave.com/v3/transactions/".$catch_incoming_request["data"]["id"]."/verify",["Authorization: Bearer ".$flutterwave_keys["secret_key"]],""),true);
		
		$customer_name = $catch_incoming_request["data"]["customer"]["name"];
		$customer_phone_number = $catch_incoming_request["data"]["customer"]["phone_number"];
		$customer_email = $catch_incoming_request["data"]["customer"]["email"];
		$amount_paid = $catch_incoming_request["data"]["charged_amount"];
		$amount_deposited = ($catch_incoming_request["data"]["charged_amount"]-$catch_incoming_request["data"]["app_fee"]);
		$transaction_id = $catch_incoming_request["data"]["tx_ref"];
		$payment_method = $catch_incoming_request["data"]["payment_type"];
		$vendor_id = trim($select_vendor_table["id"]);
		$check_if_pre_payment_exists = mysqli_query($connection_server, "SELECT * FROM sas_user_payment_checkouts WHERE vendor_id='$vendor_id' && reference='$transaction_id'");

		if(mysqli_num_rows($check_if_pre_payment_exists) == 1){
			$get_payment_details = mysqli_fetch_array($check_if_pre_payment_exists);
			$user_id = $get_payment_details["username"];
			$reference = substr(str_shuffle("12345678901234567890"), 0, 15);
			$check_vendor_user_exists = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vendor_id' && username='$user_id'");
			if(mysqli_num_rows($check_vendor_user_exists) == 1){
				$get_logged_user_details = mysqli_fetch_array($check_vendor_user_exists);
				$_SESSION["user_session"] = $get_logged_user_details["username"];
			
				$select_transaction_history = mysqli_query($connection_server,"SELECT * FROM sas_transactions WHERE (api_reference='$transaction_id')");
			
				if(($flutterwave_verify_transaction["status"] == "success") && ($catch_incoming_request["data"]["status"] == "successful")){
					if(mysqli_num_rows($select_transaction_history) == 0){
						chargeUser("credit", $_SESSION["user_session"], "Wallet Credit", $reference, $transaction_id, $amount_paid, $amount_deposited, "Flutterwave Wallet Credit - ".str_replace("_"," ",$payment_method), strtoupper("WEB"), $_SERVER["HTTP_HOST"], "1");
						unset($_SESSION["user_session"]);
					}
				}
			}
		}
	}

	function confirmPaymentDeposited($method,$url,$header,$json){
		$apiwalletBalance = curl_init($url);
		$apiwalletBalanceUrl = $url;
		curl_setopt($apiwalletBalance,CURLOPT_URL,$apiwalletBalanceUrl);
		curl_setopt($apiwalletBalance,CURLOPT_RETURNTRANSFER,true);
		if($method == "POST"){
			curl_setopt($apiwalletBalance,CURLOPT_POST,true);
		}
		
		if($method == "GET"){
		curl_setopt($apiwalletBalance,CURLOPT_HTTPGET,true);
		}
		
		if($header == true){
			curl_setopt($apiwalletBalance,CURLOPT_HTTPHEADER,$header);
		}
		if($json == true){
			curl_setopt($apiwalletBalance,CURLOPT_POSTFIELDS,$json);
		}
		curl_setopt($apiwalletBalance, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($apiwalletBalance, CURLOPT_SSL_VERIFYPEER, false);
		
		$GetAPIBalanceJSON = curl_exec($apiwalletBalance);
		return $GetAPIBalanceJSON;
	}
?>