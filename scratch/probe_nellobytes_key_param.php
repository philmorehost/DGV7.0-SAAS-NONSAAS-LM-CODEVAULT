<?php
/**
 * Find the provider's new APIKEY parameter name.
 *
 * Established so far:
 *   APIAirtimeNetworkV2.asp?ClientID=<CK...>        -> real data  (so the ID parameter is ClientID)
 *   APIAirtimeNetworkV2.asp?UserID=<CK...>          -> AUTHENTICATION_FAILED_1 (the old name is dead)
 *   APIQueryV1.asp?ClientID=..&APIKey=..&OrderID=.. -> MISSING_CREDENTIALS ("missing required
 *                                                      parameters") - the ID was accepted, so the
 *                                                      KEY parameter is the one not being read.
 *
 * This only calls APIQueryV1.asp, which is read-only (it reports a past order and cannot place one).
 * The credential is never printed.
 *
 * Usage: php probe_nellobytes_key_param.php --user=CK... --key=... [--order=6720970260]
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$opts = array('user' => '', 'key' => '', 'order' => '6720970260');
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--user=') === 0) $opts['user'] = substr($a, 7);
    elseif (strpos($a, '--key=') === 0) $opts['key'] = substr($a, 6);
    elseif (strpos($a, '--order=') === 0) $opts['order'] = substr($a, 8);
}
if ($opts['user'] === '' || $opts['key'] === '') {
    fwrite(STDERR, "usage: php probe_nellobytes_key_param.php --user=CK... --key=... [--order=<OrderID>]\n");
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
$names = array(
    'APIKey', 'ApiKey', 'apikey', 'API_KEY', 'Api_Key',
    'ClientSecret', 'clientsecret', 'SecretKey', 'Secret', 'ApiSecret',
    'Key', 'AuthKey', 'AuthenticationKey', 'AccessKey', 'Password', 'ApiPassword',
);

echo "=== APIQueryV1.asp with ClientID + each candidate key parameter (read-only) ===\n";
$winners = array();
foreach ($names as $name) {
    $url = $base . 'APIQueryV1.asp?ClientID=' . urlencode($opts['user']) . '&' . $name . '=' . urlencode($opts['key'])
        . '&OrderID=' . urlencode($opts['order']);
    $r = fetch($url, $opts['key']);
    $short = substr($r, 0, 110);
    printf("  %-18s -> %s\n", $name, $short);
    if (stripos($r, 'MISSING_CREDENTIALS') === false && stripos($r, 'INVALID_CREDENTIALS') === false && stripos($r, 'AUTHENTICATION_FAILED') === false) {
        $winners[] = $name;
    }
}

echo "\n";
if ($winners) {
    echo "ACCEPTED KEY PARAMETER(S): " . implode(', ', $winners) . "\n";
} else {
    echo "None of the candidate names was accepted. The reply is MISSING_CREDENTIALS for all of them,\n";
    echo "which means an additional required parameter is still absent - test RequestID and\n";
    echo "CallBackURL alongside the established ClientID.\n";
}
