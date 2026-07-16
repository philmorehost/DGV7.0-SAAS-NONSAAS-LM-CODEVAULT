<?php
/**
 * Standalone hdkdata.com SME Data API probe.
 *
 * NOT part of the live platform request path — does not touch any database,
 * wallet, or session. It sends the exact same HTTP request that
 * DGV7.0-SAAS/func/api-gateway/sme-data-hdkdata-com.php sends during a real
 * purchase, so you can verify your API key / plan codes / response shape
 * directly.
 *
 * WARNING: hdkdata's /api/data/ endpoint has no visible sandbox/test mode.
 * Running this against a real phone number will very likely trigger a REAL
 * data top-up and deduct REAL balance from your hdkdata account. Use a plan
 * size and phone number you're comfortable actually spending on, or confirm
 * with hdkdata support whether a test mode exists for your account.
 *
 * Usage:
 *   php probe_hdkdata_sme.php <api_base_url> <api_key> <phone_no> [network] [plan_size]
 *
 * Examples:
 *   php probe_hdkdata_sme.php hdkdata.com sk_live_xxx 08012345678 mtn 1gb
 *   php probe_hdkdata_sme.php hdkdata.com sk_live_xxx 08012345678 glo 3gb
 *
 * network:   mtn | airtel | glo | 9mobile   (default: mtn)
 * plan_size: one of the keys below for the chosen network (default: 1gb)
 *   mtn:     1gb, 2gb, 3gb, 5gb
 *   glo:     1gb, 3gb, 5gb
 *   airtel:  (none supported by this gateway's SME lookup — will report "plan not available")
 *   9mobile: (none supported by this gateway's SME lookup — will report "plan not available")
 */

if (php_sapi_name() !== 'cli') {
    die("Run this from the command line: php probe_hdkdata_sme.php <api_base_url> <api_key> <phone_no> [network] [plan_size]\n");
}

$api_base_url = $argv[1] ?? null;
$api_key      = $argv[2] ?? null;
$phone_no     = $argv[3] ?? null;
$network      = $argv[4] ?? 'mtn';
$plan_size    = $argv[5] ?? '1gb';

if (!$api_base_url || !$api_key || !$phone_no) {
    fwrite(STDERR, "Usage: php probe_hdkdata_sme.php <api_base_url> <api_key> <phone_no> [network=mtn] [plan_size=1gb]\n");
    exit(1);
}

// Exact same net_id + plan-code tables as func/api-gateway/sme-data-hdkdata-com.php
$net_id_map = ["mtn" => "1", "airtel" => "4", "glo" => "2", "9mobile" => "3"];
$plan_code_map = [
    "mtn"     => ["1gb" => "328", "2gb" => "8", "3gb" => "44", "5gb" => "279"],
    "airtel"  => [],
    "glo"     => ["1gb" => "342", "3gb" => "343", "5gb" => "344"],
    "9mobile" => [],
];

if (!isset($net_id_map[$network])) {
    fwrite(STDERR, "Unknown network '$network'. Must be one of: " . implode(', ', array_keys($net_id_map)) . "\n");
    exit(1);
}

$net_id = $net_id_map[$network];
$available_plans = $plan_code_map[$network];

echo "==================================================================\n";
echo " hdkdata SME Data probe\n";
echo "==================================================================\n";
echo " Base URL   : https://{$api_base_url}/api/data/\n";
echo " Network    : {$network} (net_id={$net_id})\n";
echo " Plan size  : {$plan_size}\n";
echo " Phone      : {$phone_no}\n";
echo "------------------------------------------------------------------\n";

if (!array_key_exists($plan_size, $available_plans)) {
    echo " RESULT: 'Plan size not available' — this exact failure is what the live gateway\n";
    echo " would also return for this network/plan combo BEFORE ever calling hdkdata.\n";
    echo " Available plan sizes for '{$network}': " . (empty($available_plans) ? "(none mapped)" : implode(', ', array_keys($available_plans))) . "\n";
    exit(0);
}

$plan_code = $available_plans[$plan_size];
echo " Resolved plan code: {$plan_code}\n";
echo "------------------------------------------------------------------\n";
echo " Sending live request now...\n\n";

$curl_url = "https://" . $api_base_url . "/api/data/";
$curl_request = curl_init($curl_url);
curl_setopt($curl_request, CURLOPT_POST, true);
curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_request, CURLOPT_TIMEOUT, 30);
curl_setopt($curl_request, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl_request, CURLOPT_HTTPHEADER, [
    "Authorization: Token " . $api_key,
    "Content-Type: application/json",
]);
$payload = [
    "network" => $net_id,
    "plan" => $plan_code,
    "mobile_number" => $phone_no,
    "Ported_number" => true,
];
curl_setopt($curl_request, CURLOPT_POSTFIELDS, json_encode($payload, true));

echo " Request payload: " . json_encode($payload) . "\n\n";

$curl_result = curl_exec($curl_request);
$http_code = curl_getinfo($curl_request, CURLINFO_HTTP_CODE);
$curl_errno = curl_errno($curl_request);
$curl_error = curl_error($curl_request);
curl_close($curl_request);

if ($curl_errno) {
    echo " CURL ERROR ({$curl_errno}): {$curl_error}\n";
    echo " -> API key/base URL/network reachability issue, not a data-plan issue.\n";
    exit(1);
}

echo " HTTP status: {$http_code}\n";
echo " Raw response body:\n{$curl_result}\n\n";

$decoded = json_decode($curl_result, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo " NOTE: response body is not valid JSON (" . json_last_error_msg() . ").\n";
    echo " -> hdkdata may have returned an HTML error page (bad auth, wrong URL, rate limit, etc.)\n";
    exit(1);
}

echo " Decoded 'Status' field: " . var_export($decoded['Status'] ?? null, true) . "\n";
echo " Decoded 'id' field    : " . var_export($decoded['id'] ?? null, true) . "\n";

if (in_array($decoded['Status'] ?? null, ['successful', 'pending'], true)) {
    echo "\n RESULT: hdkdata accepted the request ({$decoded['Status']}). API key and plan code are valid.\n";
} else {
    echo "\n RESULT: hdkdata did NOT report success/pending. Inspect the raw response above for the\n";
    echo " actual reason (invalid plan code, insufficient hdkdata wallet balance, bad phone format, etc.)\n";
}
