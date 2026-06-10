<?php
	$sms_service_provider_alter_code = array("mtn" => "mtn", "airtel" => "airtel", "glo" => "glo", "9mobile" => "9mobile");
	if(in_array($product_name, array_keys($sms_service_provider_alter_code))){
	 if($product_name == "mtn"){
	$web_sms_size_array = array("standard_sms" => "sms", "flash_sms" => "flash");
	}else{
	if($product_name == "airtel"){
	$web_sms_size_array = array("standard_sms" => "sms", "flash_sms" => "flash");
			}else{
	if($product_name == "glo"){
	$web_sms_size_array = array("standard_sms" => "sms", "flash_sms" => "flash");
	}else{
	if($product_name == "9mobile"){
	$web_sms_size_array = array("standard_sms" => "sms", "flash_sms" => "flash");
					}
	}
	}
	}

	if(in_array($sms_type, array_keys($web_sms_size_array))){
			$explode_philmoresms_apikey = array_filter(explode(":",trim($api_detail["api_key"])));
	$curl_url = "https://smsc.philmoresms.com/smsAPI?sendsms&apikey=".$explode_philmoresms_apikey[0]."&apitoken=".$explode_philmoresms_apikey[1]."&type=".$web_sms_size_array[$sms_type]."&from=".$sender_id."&to=".$phone_no."&text=".$text_message."&scheduledate=".$schedule_date."&route=0";
	$curl_request = curl_init($curl_url);
	curl_setopt($curl_request, CURLOPT_HTTPGET, true);
   curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($curl_request, CURLOPT_TIMEOUT, 30);
 curl_setopt($curl_request, CURLOPT_CONNECTTIMEOUT, 10);
   curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, false);
   curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, false);
   $curl_result = curl_exec($curl_request);
   $curl_json_result = json_decode($curl_result, true);


			if(curl_errno($curl_request)){
				$api_response = "failed";
				$api_response_text = 1;
				$api_response_description = "";
				$api_response_status = 3;
			}
			
	if(in_array($curl_json_result["status"],array("success"))){
	$api_response = "successful";
	$api_response_reference = $curl_json_result["group_id"];
	$api_response_text = $curl_json_result["status"];
	$api_response_description = "Transaction Successful";
	$api_response_status = 1;
	}

	if(in_array($curl_json_result["status"],array("queued"))){
		$api_response = "pending";
		$api_response_reference = $curl_json_result["group_id"];
		$api_response_text = $curl_json_result["status"];
		$api_response_description = "Transaction Pending";
		$api_response_status = 2;
	}

	if(in_array($curl_json_result["status"],array("error"))){
	$api_response = "failed";
	$api_response_text = $curl_json_result["status"];
	$api_response_description = "Transaction Failed";
	$api_response_status = 3;
	}
	}else{
	//sms size not available
	$api_response = "failed";
	$api_response_text = "";
	$api_response_description = "";
	$api_response_status = 3;
	}
 }else{
	//Service not available
	$api_response = "failed";
	$api_response_text = "";
	$api_response_description = "Service not available";
	$api_response_status = 3;
 }
curl_close($curl_request);
?>