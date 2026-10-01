<?php
/**
 * Confirm the provider's parameter rename and work out the complete fix.
 *
 * Finding so far: APIAirtimeNetworkV2.asp?ClientID=<CK...> returns real data, while the same call
 * with UserID= returns {"status":"AUTHENTICATION_FAILED_1"}. Supplying BOTH breaks it again, so the
 * fix is to REPLACE UserID with ClientID, not to add it.
 *
 * Note the documentation still says UserID, so the docs are behind the implementation.
 *
 * SAFETY: every purchase-shaped call here is unfillable on purpose - Amount=1 is below the
 * documented minimum of 50, and MobileNumber=1 is not a phone number - so no order can be created
 * whatever the provider does with them. What we read is whether it gets PAST authentication
 * (MINIMUM_50 / INVALID_RECIPIENT) or stops at MISSING_CREDENTIALS / INVALID_CREDENTIALS.
 *
 * Usage: php probe_nellobytes_clientid_fix.php --user=CK... --key=... [--order=6720970260]
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$opts = array('user' => '', 'key' => '', 'order' => '6720970260', 'callback' => 'https://v6.datagifting.com.ng/web/api/clubkonnect-callback.php');
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--user=') === 0) $opts['user'] = substr($a, 7);
    elseif (strpos($a, '--key=') === 0) $opts['key'] = substr($a, 6);
    elseif (strpos($a, '--order=') === 0) $opts['order'] = substr($a, 8);
    elseif (strpos($a, '--callback=') === 0) $opts['callback'] = substr($a, 11);
}
if ($opts['user'] === '' || $opts['key'] === '') {
    fwrite(STDERR, "usage: php probe_nellobytes_clientid_fix.php --user=CK... --key=... [--order=<OrderID>]\n");
    exit(2);
}

function fetch($url, $secret)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    $body = curl_exec($ch);
    curl_close($ch);
    if ($secret !== '' && is_string($body)) $body = str_replace($secret, '<<KEY-REDACTED>>', $body);
    return trim((string)$body);
}

$base = 'https://www.nellobytesystems.com/';
$cred = 'ClientID=' . urlencode($opts['user']) . '&APIKey=' . urlencode($opts['key']);
$cb = '&CallBackURL=' . urlencode($opts['callback']);

function check($label, $body)
{
    printf("--- %s\n    %s\n\n", $label, substr($body, 0, 500));
}

// 1. Read-only: does the credential now return the real details of a completed order?
check('APIQueryV1.asp (ClientID+APIKey) for OrderID ' . $opts['order'],
    fetch($base . 'APIQueryV1.asp?' . $cred . '&OrderID=' . urlencode($opts['order']), $opts['key']));

// 2. Is CallBackURL now REQUIRED? Same call with and without it, both impossible to fill.
check('APIAirtimeV1, ClientID, NO CallBackURL, Amount=1',
    fetch($base . 'APIAirtimeV1.asp?' . $cred . '&MobileNetwork=02&Amount=1&MobileNumber=08119871010&RequestID=FIXTEST1', $opts['key']));

check('APIAirtimeV1, ClientID, WITH CallBackURL, Amount=1  (expect MINIMUM_50 = auth passed)',
    fetch($base . 'APIAirtimeV1.asp?' . $cred . $cb . '&MobileNetwork=02&Amount=1&MobileNumber=08119871010&RequestID=FIXTEST2', $opts['key']));

check('APIAirtimeV1, ClientID, WITH CallBackURL, MobileNumber=1  (expect INVALID_RECIPIENT = auth passed)',
    fetch($base . 'APIAirtimeV1.asp?' . $cred . $cb . '&MobileNetwork=02&Amount=100&MobileNumber=1&RequestID=FIXTEST3', $opts['key']));

// 3. Same question for the data endpoint.
check('APIDatabundleV1, ClientID, WITH CallBackURL, MobileNumber=1',
    fetch($base . 'APIDatabundleV1.asp?' . $cred . $cb . '&MobileNetwork=01&DataPlan=1000&MobileNumber=1&RequestID=FIXTEST4', $opts['key']));

check('APIDatabundlePlansV2, ClientID',
    fetch($base . 'APIDatabundlePlansV2.asp?ClientID=' . urlencode($opts['user']), $opts['key']));

echo "EXPECTED READING:\n";
echo "  order query returns an order JSON ......... credential is valid again with ClientID\n";
echo "  MINIMUM_50 / INVALID_RECIPIENT ............ authentication passed (parameters accepted)\n";
echo "  MISSING_CREDENTIALS without CallBackURL ... CallBackURL is now a required parameter\n";
echo "  INVALID_CREDENTIALS / AUTHENTICATION_* .... still not authenticating\n";
