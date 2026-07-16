<?php
$data_service_provider_alter_code = array("mtn" => "mtn", "airtel" => "airtel", "glo" => "glo", "9mobile" => "9mobile");
if (in_array($product_name, array_keys($data_service_provider_alter_code))) {
  if ($product_name == "mtn") {
    $net_id = "1";
    $web_data_size_array = array("1gb" => "328", "2gb" => "8", "3gb" => "44", "5gb" => "279");
  } else {
    if ($product_name == "airtel") {
      $net_id = "4";
      $web_data_size_array = array();
    } else {
      if ($product_name == "glo") {
        $net_id = "2";
        $web_data_size_array = array("1gb" => "342", "3gb" => "343", "5gb" => "344");
      } else {
        if ($product_name == "9mobile") {
          $net_id = "3";
          $web_data_size_array = array();
        }
      }
    }
  }
  if (in_array($quantity, array_keys($web_data_size_array))) {
    $clean_base_url = preg_replace('#^https?://#', '', trim($api_detail["api_base_url"]));
        $clean_base_url = rtrim($clean_base_url, "/");
        $curl_url = "https://" . $clean_base_url . "/api/data/";
    $curl_request = curl_init($curl_url);
    curl_setopt($curl_request, CURLOPT_POST, true);
    curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_request, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl_request, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, false);
    $clean_api_key = trim(str_ireplace("Token ", "", $api_detail["api_key"]));
    $curl_http_headers = array(
      "Authorization: Token " . $clean_api_key,
      "Content-Type: application/json",
      "Accept: application/json",
      "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    );
    curl_setopt($curl_request, CURLOPT_HTTPHEADER, $curl_http_headers);
    $curl_postfields_data = json_encode(array("network" => $net_id, "plan" => $web_data_size_array[$quantity], "mobile_number" => $phone_no, "Ported_number" => true));
    curl_setopt($curl_request, CURLOPT_POSTFIELDS, $curl_postfields_data);
    $curl_result = curl_exec($curl_request);
    error_log("HDK DATA API RAW RESPONSE: " . $curl_result);
    $curl_json_result = json_decode($curl_result, true);


    if (curl_errno($curl_request)) {
      $api_response = "failed";
      $api_response_text = 1;
      $api_response_description = "";
      $api_response_status = 3;
    }

    if (in_array($curl_json_result["Status"], array("successful"))) {
      $api_response = "successful";
      $api_response_reference = $curl_json_result["id"];
      $api_response_text = $curl_json_result["Status"];
      $api_response_description = "Transaction Successful | " . strtoupper(str_replace(["_", "-"], " ", $quantity)) . " credited to 234" . substr($phone_no, "1", "11");
      $api_response_status = 1;
    }

    if (in_array($curl_json_result["Status"], array("pending"))) {
      $api_response = "pending";
      $api_response_reference = $curl_json_result["id"];
      $api_response_text = $curl_json_result["Status"];
      $api_response_description = "Transaction Pending | " . strtoupper(str_replace(["_", "-"], " ", $quantity)) . " credited to 234" . substr($phone_no, "1", "11");
      $api_response_status = 2;
    }

    if (!in_array($curl_json_result["Status"], array("successful", "pending"))) {
      $api_response = "failed";
      $api_response_text = $curl_json_result["Status"];
      $api_response_description = "Transaction Failed | " . strtoupper(str_replace(["_", "-"], " ", $quantity)) . " credited to 234" . substr($phone_no, "1", "11") . " failed. RAW: " . $curl_result;
      $api_response_status = 3;
    }
  } else {
    //Data size not available
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
if (isset($curl_request)) {
    curl_close($curl_request);
}
?>