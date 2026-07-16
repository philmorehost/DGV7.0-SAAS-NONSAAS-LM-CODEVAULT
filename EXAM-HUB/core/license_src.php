<?php
if (!defined('LICENSE_KEY')) {
    die('<b>Security Error:</b> License key is missing. Please reinstall the software.');
}

// Ensure database connection is available
$pdo = get_db_connection();

// Check last license verification time to avoid pinging API on every page load
$last_check = get_setting('license_last_check', 0);
$current_time = time();

// Verify license every 24 hours (86400 seconds)
if (($current_time - (int)$last_check) > 86400) {
    $domain = $_SERVER['HTTP_HOST'];
    $key = LICENSE_KEY;
    
    // Developer bypass for testing
    if ($key === 'DEV') {
        set_setting('license_last_check', $current_time);
        return true;
    }
    
    $ch = curl_init('https://manager.pmhserver.name.ng/api.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['key' => $key, 'domain' => $domain]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] == 1) {
            // License is valid
            set_setting('license_last_check', $current_time);
        } else {
            // License is invalid
            die('<b>License Error:</b> Your license key is invalid or suspended for this domain. Please contact support.');
        }
    } else {
        // If the license server is down, we don't block the site immediately, 
        // we'll just check again on the next page load. 
        // In a strict system, we would block it, but that causes downtime if the API is offline.
    }
}
