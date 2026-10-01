<?php
/**
 * Is there a newer (V2) purchase/query endpoint that matches the working V2 read endpoints?
 *
 * APIDatabundlePlansV2.asp and APIAirtimeNetworkV2.asp both answer to ClientID, while the V1
 * purchase and query endpoints only ever say MISSING_CREDENTIALS once the ID is accepted. If a V2
 * purchase/query endpoint exists, the migration is to that, not a parameter guess.
 *
 * SAFETY: Amount=1 (below the documented minimum of 50), so no call here can create an order.
 *
 * Usage: php probe_nellobytes_endpoints.php --user=CK... --key=...
 */

error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE ^ E_DEPRECATED);

$opts = array('user' => '', 'key' => '');
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--user=') === 0) $opts['user'] = substr($a, 7);
    elseif (strpos($a, '--key=') === 0) $opts['key'] = substr($a, 6);
}
if ($opts['user'] === '' || $opts['key'] === '') {
    fwrite(STDERR, "usage: php probe_nellobytes_endpoints.php --user=CK... --key=...\n");
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
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($secret !== '' && is_string($body)) $body = str_replace($secret, '<<KEY-REDACTED>>', $body);
    return array('http' => $http, 'body' => trim((string)$body));
}

$base = 'https://www.nellobytesystems.com/';
$id = 'ClientID=' . urlencode($opts['user']);
$k = '&APIKey=' . urlencode($opts['key']);
$common = '&MobileNetwork=02&Amount=1&MobileNumber=08119871010&RequestID=EPTEST1';

$endpoints = array(
    'APIAirtimeV1.asp',
    'APIAirtimeV2.asp',
    'APIAirtime.asp',
    'APIRechargeV1.asp',
    'APIAirtimeV1.asp (id only)',
);

foreach ($endpoints as $ep) {
    $use = (strpos($ep, '(id only)') !== false) ? $id : $id . $k;
    $name = str_replace(' (id only)', '', $ep);
    $r = fetch($base . $name . '?' . $use . $common, $opts['key']);
    printf("  %-26s http=%-4s %s\n", $name, $r['http'], substr($r['body'], 0, 150));
}

echo "\n=== query endpoints ===\n";
foreach (array('APIQueryV1.asp', 'APIQueryV2.asp', 'APIQuery.asp') as $ep) {
    $r = fetch($base . $ep . '?' . $id . $k . '&OrderID=6720970260', $opts['key']);
    printf("  %-26s http=%-4s %s\n", $ep, $r['http'], substr($r['body'], 0, 150));
}

echo "\nA 404 means the endpoint does not exist. A JSON reply means it does - compare the status word.\n";
