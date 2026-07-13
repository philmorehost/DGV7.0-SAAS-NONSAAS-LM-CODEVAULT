<?php
// Two-level mapping: product_name (exam type) → quantity (sub-type) → NaijaResultPins card_type_id.
// card_type_ids confirmed live against GET https://www.naijaresultpins.com/api/v1:
// 1=WAEC Scratch Card, 2=NECO TOKEN, 3=NABTEB Scratch Card, 4=WAEC Verification Pin,
// 11=NECO e-Verification PIN. (NBAIS and EXAMINIFY card types also exist on the live account but
// have no matching product in this app yet, so they're intentionally left unmapped.)
// JAMB IDs (8/9/10) are not currently returned by the live product list for this account and are
// unverified — left as-is rather than guessed at.
$nrp_card_type_map = array(
    "waec"   => array("result_checker" => "1", "verification_pin" => "4"),
    "neco"   => array("result_checker" => "2", "verification_pin" => "11"),
    "nabteb" => array("result_checker" => "3"),
    "jamb"   => array(
        "utme_without_mock" => "8",
        "utme_with_mock"    => "9",
        "direct_entry"      => "10",
    ),
);
if (array_key_exists($product_name, $nrp_card_type_map) && array_key_exists($quantity, $nrp_card_type_map[$product_name])) {
    $card_type_id = $nrp_card_type_map[$product_name][$quantity];
    $curl_url = "https://www." . $api_detail["api_base_url"] . "/api/v1/exam-card/buy";
    $curl_request = curl_init($curl_url);
    curl_setopt($curl_request, CURLOPT_POST, true);
    curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_request, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl_request, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, false);
    $curl_http_headers = array("Authorization: Bearer " . $api_detail["api_key"], "Content-Type: application/json");
    curl_setopt($curl_request, CURLOPT_HTTPHEADER, $curl_http_headers);
    // NaijaResultPins "quantity" is the number of cards to buy — always 1 per transaction on this platform.
    // $quantity here is the exam sub-type (e.g. "result_checker") used for the card_type_id lookup above.
    $curl_postfields_data = json_encode(array("card_type_id" => $card_type_id, "quantity" => "1"));
    curl_setopt($curl_request, CURLOPT_POSTFIELDS, $curl_postfields_data);
    $curl_result = curl_exec($curl_request);
    $curl_json_result = json_decode($curl_result, true);

    if (curl_errno($curl_request)) {
        $api_response = "failed";
        $api_response_text = curl_error($curl_request);
        $api_response_description = "Curl Error";
        $api_response_status = 3;
    } else {
        if (isset($curl_json_result["status"]) && $curl_json_result["status"] === true && isset($curl_json_result["code"]) && $curl_json_result["code"] == "000") {
            $api_response = "successful";
            $api_response_reference = $curl_json_result["reference"];
            $api_response_text = $curl_json_result["message"];
            $cards = array();
            foreach ($curl_json_result["cards"] as $card_item) {
                $cards[] = "PIN: " . $card_item["pin"] . " | Serial: " . $card_item["serial_no"];
            }
            $api_response_description = "Transaction Successful | " . implode(" | ", $cards);
            $api_response_status = 1;
        } else {
            $api_response = "failed";
            $api_response_text = isset($curl_json_result["message"]) ? $curl_json_result["message"] : "Unknown Error";
            $api_response_description = "Transaction Failed";
            $api_response_status = 3;
        }
    }
    curl_close($curl_request);
} else {
    //Service or sub-type not available
    $api_response = "failed";
    $api_response_text = "";
    $api_response_description = "Service not available";
    $api_response_status = 3;
}
?>