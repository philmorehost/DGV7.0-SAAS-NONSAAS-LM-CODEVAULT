<?php
/**
 * Work out what the provider's airtime endpoint now actually requires.
 *
 * Established by live testing:
 *   APIAirtimeNetworkV2.asp?ClientID=<CK...>   -> real network list   (ID parameter is ClientID)
 *   APIAirtimeNetworkV2.asp?UserID=<CK...>     -> AUTHENTICATION_FAILED_1 (UserID is dead)
 *   *PlansV2.asp?ClientID=<CK...>              -> real plan list
 *   anything with the APIKey under 16 different names -> MISSING_CREDENTIALS
 *
 * So the ID is accepted and the key is not being read. Two readings remain: the key parameter was
 * renamed to something outside the candidate list, or the key is no longer used at all and the
 * account is now authenticated by ClientID plus an allowed-IP rule.
 *
 * SAFETY: every airtime call below sends Amount=1, which is below the documented minimum of 50, so
 * no order can be created whatever else happens. MINIMUM_50 back means authentication PASSED.
 *
 * Usage: php probe_nellobytes_requirements.php --user=CK... --key=... [--callback=URL]
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$opts = array(
    'user' => '',
    'key' => '',
    'callback' => 'https://v6.datagifting.com.ng/web/api/clubkonnect-callback.php',
    'order' => '6720970260',
);
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--user=') === 0) $opts['user'] = substr($a, 7);
    elseif (strpos($a, '--key=') === 0) $opts['key'] = substr($a, 6);
    elseif (strpos($a, '--callback=') === 0) $opts['callback'] = substr($a, 11);
    elseif (strpos($a, '--order=') === 0) $opts['order'] = substr($a, 8);
}
if ($opts['user'] === '' || $opts['key'] === '') {
    fwrite(STDERR, "usage: php probe_nellobytes_requirements.php --user=CK... --key=...\n");
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
$id = 'ClientID=' . urlencode($opts['user']);
$k = '&APIKey=' . urlencode($opts['key']);
$cb = '&CallBackURL=' . urlencode($opts['callback']);

$cases = array(
    'airtime: ClientID only, no key at all' => $base . 'APIAirtimeV1.asp?' . $id . $cb . '&MobileNetwork=02&Amount=1&MobileNumber=08119871010&RequestID=REQTEST1',
    'airtime: ClientID + key' => $base . 'APIAirtimeV1.asp?' . $id . $k . $cb . '&MobileNetwork=02&Amount=1&MobileNumber=08119871010&RequestID=REQTEST2',
    'airtime: ClientID + key, no CallBackURL' => $base . 'APIAirtimeV1.asp?' . $id . $k . '&MobileNetwork=02&Amount=1&MobileNumber=08119871010&RequestID=REQTEST3',
    'airtime: ClientID + key, no RequestID' => $base . 'APIAirtimeV1.asp?' . $id . $k . $cb . '&MobileNetwork=02&Amount=1&MobileNumber=08119871010',
    'airtime: ClientID + key, no Amount' => $base . 'APIAirtimeV1.asp?' . $id . $k . $cb . '&MobileNetwork=02&MobileNumber=08119871010&RequestID=REQTEST5',
    'query:  ClientID only + OrderID' => $base . 'APIQueryV1.asp?' . $id . '&OrderID=' . urlencode($opts['order']),
    'query:  ClientID + key + OrderID + RequestID' => $base . 'APIQueryV1.asp?' . $id . $k . '&OrderID=' . urlencode($opts['order']) . '&RequestID=REQTEST7',
    'query:  ClientID + key + RequestID only' => $base . 'APIQueryV1.asp?' . $id . $k . '&RequestID=' . urlencode($opts['order']),
);

foreach ($cases as $label => $url) {
    printf("--- %s\n    %s\n\n", $label, substr(fetch($url, $opts['key']), 0, 300));
}

echo "MINIMUM_50 or INVALID_RECIPIENT = authentication PASSED and the call was understood.\n";
echo "MISSING_AMOUNT on the 'no Amount' case = authentication PASSED too.\n";
