<?php
/**
 * Does the provider now want the credential under a different parameter name?
 *
 * The partner panel calls it "client ID" while the published documentation says "UserID", and the
 * live API already returns a status code (AUTHENTICATION_FAILED_1) that the documentation does not
 * list. A silent parameter rename would break every call at once, exactly as observed.
 *
 * These calls are read-only: the network list needs no key, and the purchase-shaped call is
 * deliberately below the documented minimum amount so it cannot create an order. The credentials are
 * never printed.
 *
 * Usage: php probe_nellobytes_param_variants.php --user=CK... --key=...
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$opts = array('user' => '', 'key' => '');
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--user=') === 0) $opts['user'] = substr($a, 7);
    elseif (strpos($a, '--key=') === 0) $opts['key'] = substr($a, 6);
}
if ($opts['user'] === '' || $opts['key'] === '') {
    fwrite(STDERR, "usage: php probe_nellobytes_param_variants.php --user=CK... --key=...\n");
    exit(2);
}
$user = $opts['user'];
$key = $opts['key'];

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

// A. network list, trying every plausible spelling of the ID parameter (no key needed per docs).
echo "=== APIAirtimeNetworkV2.asp - ID parameter spellings ===\n";
foreach (array('UserID', 'ClientID', 'ClientId', 'userid', 'clientid', 'UserId') as $param) {
    $r = fetch($base . 'APIAirtimeNetworkV2.asp?' . $param . '=' . urlencode($user), $key);
    printf("  %-10s -> %s\n", $param, substr($r, 0, 140));
}

// B. the same, with BOTH spellings supplied at once - covers a provider that reads either.
echo "\n=== both spellings supplied together ===\n";
$both = fetch($base . 'APIAirtimeNetworkV2.asp?UserID=' . urlencode($user) . '&ClientID=' . urlencode($user), $key);
echo "  " . substr($both, 0, 140) . "\n";

// C. purchase-shaped, but Amount=1 (below minimum) so no order can be created. Only here do we
//    learn whether authentication passes, because the amount check sits behind it.
echo "\n=== APIAirtimeV1.asp, Amount=1 (cannot create an order) - key parameter spellings ===\n";
foreach (array('UserID' => 'APIKey', 'ClientID' => 'APIKey', 'UserID' => 'ApiKey', 'UserID' => 'apikey') as $idp => $keyp) {
    $url = $base . 'APIAirtimeV1.asp?' . $idp . '=' . urlencode($user) . '&' . $keyp . '=' . urlencode($key)
        . '&MobileNetwork=02&Amount=1&MobileNumber=08119871010&RequestID=PARAMTEST1';
    $r = fetch($url, $key);
    printf("  %-10s + %-8s -> %s\n", $idp, $keyp, substr($r, 0, 140));
}

echo "\nAnything other than INVALID_CREDENTIALS / AUTHENTICATION_FAILED* means the parameters were\n";
echo "accepted and the provider moved on to validating the amount - i.e. authentication succeeded.\n";
