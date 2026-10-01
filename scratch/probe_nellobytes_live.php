<?php
/**
 * Ask the Airtime/Data provider (nellobytesystems.com, which is what the files named
 * "*-clubkonnect-com.php" actually call) whether our credentials still work.
 *
 * READ-ONLY. It calls only:
 *   - APIAirtimeNetworkV2.asp  (network list, needs UserID only)   -> is the UserID recognised?
 *   - APIQueryV1.asp           (query an OLD order by OrderID)      -> is the APIKey accepted?
 * It never places an order, so no money can move. The credentials are read from the local database
 * and are NEVER printed - only the provider's own response is shown, with the key redacted.
 *
 * Usage: php probe_nellobytes_live.php [--db=dgv7_probe] [--order=<OrderID>]
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$opts = array('db' => 'dgv7_probe', 'order' => '6720970260');
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--db=') === 0) $opts['db'] = substr($a, 5);
    elseif (strpos($a, '--order=') === 0) $opts['order'] = substr($a, 8);
}

$conn = @new mysqli('127.0.0.1', 'root', '', $opts['db'], 3306);
if ($conn->connect_errno) {
    fwrite(STDERR, "cannot reach the local database '{$opts['db']}'\n");
    exit(2);
}

$sql = "SELECT vendor_id, api_key FROM sas_apis WHERE api_base_url='clubkonnect.com' AND api_type='airtime' AND api_key LIKE '%:%' LIMIT 1";
$row = $conn->query($sql)->fetch_assoc();
if (!$row) {
    fwrite(STDERR, "no clubkonnect airtime credential found in sas_apis\n");
    exit(2);
}
$parts = array_values(array_filter(explode(':', trim($row['api_key']))));
$user_id = $parts[0] ?? '';
$api_key = $parts[1] ?? '';
$conn->close();

echo "credential source : sas_apis vendor_id={$row['vendor_id']}, split on ':'\n";
echo "UserID length     : " . strlen($user_id) . "\n";
echo "APIKey length     : " . strlen($api_key) . " (value never printed)\n\n";
function redact($text, $secret)
{
    if ($secret !== '' && is_string($text)) {
        $text = str_replace($secret, '<<APIKEY-REDACTED>>', $text);
    }
    return $text;
}

function get($url, $secret)
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
    $info = array(
        'http' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'errno' => curl_errno($ch),
        'error' => curl_error($ch),
    );
    curl_close($ch);
    return array('body' => $body, 'info' => $info);
}

// 1. Is the UserID recognised at all? (documented as UserID-only, read-only)
echo "=== APIAirtimeNetworkV2.asp (network list, UserID only) ===\n";
$r = get('https://www.nellobytesystems.com/APIAirtimeNetworkV2.asp?UserID=' . urlencode($user_id), $api_key);
printf("http=%s curl_errno=%s %s\n", $r['info']['http'], $r['info']['errno'], $r['info']['error']);
$txt = redact((string)$r['body'], $api_key);
echo "body: " . substr($txt, 0, 600) . "\n\n";

// 2. Does the APIKey authenticate, and what does the provider say about a real past order?
echo "=== APIQueryV1.asp (query OrderID {$opts['order']} - an order we know completed) ===\n";
$r = get('https://www.nellobytesystems.com/APIQueryV1.asp?UserID=' . urlencode($user_id)
    . '&APIKey=' . urlencode($api_key) . '&OrderID=' . urlencode($opts['order']), $api_key);
printf("http=%s curl_errno=%s %s\n", $r['info']['http'], $r['info']['errno'], $r['info']['error']);
$txt = redact((string)$r['body'], $api_key);
echo "body: " . substr($txt, 0, 900) . "\n";

// 3. Which api ROW is actually broken? Each product (airtime, dd-data, betting...) has its OWN
//    row in sas_apis with its own UserID:APIKey. Querying a real past order with each credential
//    separates "this account is rejected" from "this one product's credential is wrong".
echo "\n=== per-credential query of OrderID {$opts['order']} (a DD-data order that completed) ===\n";
$conn = @new mysqli('127.0.0.1', 'root', '', $opts['db'], 3306);
if (!$conn->connect_errno) {
    $rows = array();
    $res = $conn->query("SELECT id, api_type, api_key FROM sas_apis WHERE api_base_url='clubkonnect.com' AND vendor_id=1 ORDER BY api_type, id");
    while ($r2 = $res->fetch_assoc()) { $rows[] = $r2; }

    $airtime_uid = null;
    foreach ($rows as $r2) {
        if ($r2['api_type'] === 'airtime') {
            $p = array_values(array_filter(explode(':', trim((string)$r2['api_key']))));
            $airtime_uid = $p[0] ?? null;
        }
    }

    foreach ($rows as $r2) {
        $p = array_values(array_filter(explode(':', trim((string)$r2['api_key']))));
        $uid = $p[0] ?? '';
        $kid = $p[1] ?? '';
        if ($uid === '') {
            printf("  id=%-4s %-8s -> credential is EMPTY\n", $r2['id'], $r2['api_type']);
            continue;
        }
        $same = ($airtime_uid !== null && $uid === $airtime_uid) ? 'same UserID' : 'DIFFERENT UserID';
        $resp = get('https://www.nellobytesystems.com/APIQueryV1.asp?UserID=' . urlencode($uid)
            . '&APIKey=' . urlencode($kid) . '&OrderID=' . urlencode($opts['order']), $kid);
        printf("  id=%-4s %-8s UserID len=%-3s (%s) order query -> %s\n",
            $r2['id'], $r2['api_type'], strlen($uid), $same,
            trim(substr(redact((string)$resp['body'], $kid), 0, 160)));
    }
    $conn->close();
} else {
    echo "  (could not reopen the database)\n";
}

echo "\nCAVEAT: this call is made from THIS workstation, not from the production server. If the account\n";
echo "is restricted by IP, a perfectly valid credential is rejected from here too - so a rejection\n";
echo "above must be re-tested from the server before it is read as a credential problem.\n";
