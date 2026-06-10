<?php
$sms_service_provider_alter_code = array("mtn" => "mtn", "airtel" => "airtel", "glo" => "glo", "9mobile" => "9mobile");
if (in_array($product_name, array_keys($sms_service_provider_alter_code))) {
 if ($product_name == "mtn") {
  $web_sms_size_array = array("standard_sms" => "standard_sms", "flash_sms" => "flash_sms", "in_app_otp" => "in_app_otp");
 } else {
  if ($product_name == "airtel") {
   $web_sms_size_array = array("standard_sms" => "standard_sms", "flash_sms" => "flash_sms", "in_app_otp" => "in_app_otp");
  } else {
   if ($product_name == "glo") {
    $web_sms_size_array = array("standard_sms" => "standard_sms", "flash_sms" => "flash_sms", "in_app_otp" => "in_app_otp");
   } else {
    if ($product_name == "9mobile") {
     $web_sms_size_array = array("standard_sms" => "standard_sms", "flash_sms" => "flash_sms", "in_app_otp" => "in_app_otp");
    }
   }
  }
 }

 if (!empty($sms_type)) {
  $curl_url = "https://" . $api_detail["api_base_url"] . "/web/api/sms.php";
  $curl_request = curl_init($curl_url);
  curl_setopt($curl_request, CURLOPT_POST, true);
  curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($curl_request, CURLOPT_TIMEOUT, 30);
 curl_setopt($curl_request, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, false);
  $curl_http_headers = array(
   "Content-Type: application/json",
  );
  curl_setopt($curl_request, CURLOPT_HTTPHEADER, $curl_http_headers);

  if (curl_errno($curl_request)) {
   $api_response = "failed";
   $api_response_text = 1;
   $api_response_description = "";
   $api_response_status = 3;
  }

  if (in_array($sms_type, array("standard_sms", "flash_sms"))) {
   $curl_postfields_data = json_encode(array("api_key" => $api_detail["api_key"], "network" => $product_name, "sender_id" => $sender_id, "phone_number" => $phone_no, "type" => $sms_type, "message" => $text_message, "date" => $schedule_date), true);
  }

  if (in_array($sms_type, array("otp"))) {
   $otp_type_array_2 = array("numeric" => "numeric", "alphanumeric" => "alphanumeric");
   if (in_array($otp_type, array_keys($otp_type_array_2))) {
    $otp_type_text = $otp_type_array_2[$otp_type];
   } else {
    $otp_type_text = "";
   }
   $curl_postfields_data = json_encode(array("api_key" => $api_detail["api_key"], "network" => $product_name, "phone_number" => array_filter(explode(",", $phone_no))[0], "type" => $sms_type, "otp_type" => $otp_type_text, "pin_attempts" => $pin_attempts, "expires" => $expiration_time, "pin_length" => $pin_length), true);
  }

  curl_setopt($curl_request, CURLOPT_POSTFIELDS, $curl_postfields_data);
  $curl_result = curl_exec($curl_request);
  $curl_json_result = json_decode($curl_result, true);


  if (in_array($sms_type, array("standard_sms", "flash_sms"))) {
   if (in_array($curl_json_result["status"], array("success"))) {
    $api_response = "successful";
    $api_response_reference = $curl_json_result["ref"];
    $api_response_text = "";
    $api_response_description = "Transaction Successful";
    $api_response_status = 1;
   }

   if (in_array($curl_json_result["status"], array("pending"))) {
    $api_response = "pending";
    $api_response_reference = $curl_json_result["ref"];
    $api_response_text = "";
    $api_response_description = "Transaction Pending";
    $api_response_status = 2;
   }

   if (in_array($curl_json_result["status"], array("failed"))) {
    $api_response = "failed";
    $api_response_text = "";
    $api_response_description = "Transaction Failed";
    $api_response_status = 3;
   }
  }

  if (in_array($sms_type, array("otp"))) {
   if (in_array($curl_json_result["status"], array("success"))) {
    $api_response = "successful";
    $api_response_reference = $curl_json_result["ref"];
    $api_response_text = $curl_json_result["otp"];
    $api_response_description = "Transaction Successful";
    $api_response_status = 1;
   }

   if (in_array($curl_json_result["status"], array("pending"))) {
    $api_response = "pending";
    $api_response_reference = $curl_json_result["ref"];
    $api_response_text = "";
    $api_response_description = "Transaction Pending";
    $api_response_status = 2;
   }

   if (in_array($curl_json_result["status"], array("failed"))) {
    $api_response = "failed";
    $api_response_text = "";
    $api_response_description = "Transaction Failed";
    $api_response_status = 3;
   }
  }
 } else {
  //Sms size not available
  $api_response = "failed";
  $api_response_text = "";
  $api_response_description = "";
  $api_response_status = 3;
 }
} else {
 //Service not available
 $api_response = "failed";
 $api_response_text = "";
 $api_response_description = "Service not available";
 $api_response_status = 3;
}
curl_close($curl_request);
?>