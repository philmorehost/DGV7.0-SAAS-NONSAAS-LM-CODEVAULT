<?php
/**
 * Live validation of the nellobytesystems.com credential (the provider behind the files named
 * "*-clubkonnect-com.php").
 *
 * SAFETY: the two purchase-shaped calls below are deliberately MALFORMED so they cannot buy
 * anything. One is below the documented minimum amount, the other has an invalid mobile number.
 * Either way an order can never be created - but if authentication SUCCEEDS the provider answers
 * with MINIMUM_50 / INVALID_RECIPIENT instead of INVALID_CREDENTIALS, which is exactly the
 * distinction we need. Nothing here can spend money.
 *
 * The credentials are never printed - only the provider's own response, with the key redacted.
 *
 * Usage:
 *   php probe_nellobytes_validate.php [--db=dgv7_probe] [--user=CK...] [--key=...] [--order=<OrderID>]
 * With no --user/--key the stored credential is used and the provider is asked about it directly.
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$opts = array('db' => 'dgv7_probe', 'user' => '', 'key' => '', 'order' => '6720970260');
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--db=') === 0) $opts['db'] = substr($a, 5);
    elseif (strpos($a, '--user=') === 0) $opts['user'] = substr($a, 7);
    elseif (strpos($a, '--key=') === 0) $opts['key'] = substr($a, 6);
    elseif (strpos($a, '--order=') === 0) $opts['order'] = substr($a, 8);
}

$stored_user = $stored_key = '';
$conn = @new mysqli('127.0.0.1', 'root', '', $opts['db'], 3306);
if (!$conn->connect_errno) {
    $res = $conn->query("SELECT api_key FROM sas_apis WHERE api_base_url='clubkonnect.com' AND vendor_id=1 AND api_type='airtime' LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
        $p = array_values(array_filter(explode(':', trim((string)$row['api_key']))));
        $stored_user = $p[0] ?? '';
        $stored_key = $p[1] ?? '';
    }
    $conn->close();
}

$user = $opts['user'] !== '' ? $opts['user'] : $stored_user;
$key = $opts['key'] !== '' ? $opts['key'] : $stored_key;

if ($user === '' || $key === '') {
    fwrite(STDERR, "no credential available (pass --user/--key or use a database that has one)\n");
    exit(2);
}

echo "UserID used : length " . strlen($user) . "\n";
echo "APIKey used : length " . strlen($key) . " (never printed)\n";
if ($stored_key !== '' && $opts['key'] !== '') {
    $same = hash_equals($stored_key, $opts['key']);
    if ($same) {
        echo "stored vs supplied APIKey : IDENTICAL\n";
    } else {
        $diff = null;
        $n = min(strlen($stored_key), strlen($key));
        for ($i = 0; $i < $n; $i++) {
            if ($stored_key[$i] !== $key[$i]) { $diff = $i + 1; break; }
        }
        echo "stored vs supplied APIKey : DIFFERENT (stored len " . strlen($stored_key)
            . ", supplied len " . strlen($key)
            . ($diff !== null ? ", first difference at character $diff" : ", one is a prefix of the other") . ")\n";
    }
    if ($stored_user !== '' && $opts['user'] !== '') {
        echo "stored vs supplied UserID   : " . (hash_equals($stored_user, $opts['user']) ? 'IDENTICAL' : 'DIFFERENT') . "\n";
    }
}
echo "\n";

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
    $meta = array('http' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'errno' => curl_errno($ch), 'error' => curl_error($ch));
    curl_close($ch);
    if ($secret !== '' && is_string($body)) $body = str_replace($secret, '<<APIKEY-REDACTED>>', $body);
    return array('body' => $body, 'meta' => $meta);
}

function show($label, $url, $secret)
{
    $r = fetch($url, $secret);
    printf("--- %s\n", $label);
    printf("    http=%s curl_errno=%s %s\n", $r['meta']['http'], $r['meta']['errno'], $r['meta']['error']);
    printf("    %s\n\n", trim(substr((string)$r['body'], 0, 400)));
}

$base = 'https://www.nellobytesystems.com/';
$cred = 'UserID=' . urlencode($user) . '&APIKey=' . urlencode($key);

// 1 & 2: read-only, cannot buy anything.
show('APIAirtimeNetworkV2.asp (network list, UserID only)',
    $base . 'APIAirtimeNetworkV2.asp?UserID=' . urlencode($user), $key);
show('APIQueryV1.asp for OrderID ' . $opts['order'] . ' (a real, completed order on this account)',
    $base . 'APIQueryV1.asp?' . $cred . '&OrderID=' . urlencode($opts['order']), $key);

// 3 & 4: purchase-shaped but IMPOSSIBLE to fulfil, so they can never create an order.
show('APIAirtimeV1.asp with Amount=1 (below the documented minimum of 50) - cannot create an order',
    $base . 'APIAirtimeV1.asp?' . $cred . '&MobileNetwork=02&Amount=1&MobileNumber=08119871010&RequestID=VALIDTEST1', $key);
show('APIAirtimeV1.asp with MobileNumber=1 (not a phone number) - cannot create an order',
    $base . 'APIAirtimeV1.asp?' . $cred . '&MobileNetwork=02&Amount=100&MobileNumber=1&RequestID=VALIDTEST2', $key);

echo "READ THE RESULTS LIKE THIS:\n";
echo "  INVALID_CREDENTIALS / AUTHENTICATION_FAILED*  -> the provider is refusing this UserID+APIKey.\n";
echo "  MINIMUM_50 or INVALID_RECIPIENT               -> authentication PASSED (it got as far as\n";
echo "                                                   checking amount/number), so the credential is\n";
echo "                                                   fine and the failure is elsewhere.\n";
