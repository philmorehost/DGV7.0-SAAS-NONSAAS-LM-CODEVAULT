<?php
/**
 * Cron: Background Requery Processor (multi-tenant)
 *
 * Re-queries every vendor's pending (status=2) transactions so "stuck" purchases
 * (e.g. a network timeout mid top-up) get resolved to success/failed and users are
 * charged or refunded automatically. Runs server-side ONCE for ALL vendors — unlike
 * the legacy HTTP `automated-cron-requery.php`, which only processed whichever vendor
 * the request host resolved to (so it had to be configured per-vendor).
 *
 * Schedule: every 1 minute. Overlap-protected via flock; bounded runtime so a stuck
 * run can't block the next invocation forever.
 */

define('WEB_ROOT', realpath(__DIR__ . "/../web"));
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . "/..");
$_SESSION = array(); // No HTTP session in CLI; reused includes touch $_SESSION defensively.

include(__DIR__ . "/../func/bc-connect.php");

if (!$connection_server) {
    fwrite(STDERR, "DB connection failed.\n");
    exit(1);
}

// ─── Overlap protection ──────────────────────────────────────────────────────
$lock_file = __DIR__ . "/../logs/requery_queue.lock";
$lock_handle = fopen($lock_file, 'c');
if (!$lock_handle || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
    echo "Another requery run is already in progress. Exiting.\n";
    exit(0);
}

$start_time = microtime(true);
$time_budget_seconds = 50;
$per_vendor_limit = 50;
$total_processed = 0;

// All active vendors — each tenant's own pending transactions are processed in turn.
$vendors = mysqli_query($connection_server, "SELECT id FROM sas_vendors WHERE status=1 ORDER BY id");
if (!$vendors) {
    fwrite(STDERR, "Failed to read vendor list: " . mysqli_error($connection_server) . "\n");
    flock($lock_handle, LOCK_UN);
    fclose($lock_handle);
    exit(1);
}

while ((microtime(true) - $start_time) < $time_budget_seconds && ($vendor = mysqli_fetch_assoc($vendors))) {
    $vendor_id = (int)$vendor['id'];
    if ($vendor_id <= 0) continue;

    // Pin the vendor context so resolveVendorID()/chargeOtherUser() inside the included
    // requery logic hit THIS vendor — same mechanism process_bulk_queue.php uses.
    $GLOBALS['vendor_id'] = $vendor_id;
    resolveVendorID(true);

    $pending = mysqli_query($connection_server, "SELECT * FROM sas_transactions WHERE vendor_id='$vendor_id' AND status='2' ORDER BY id LIMIT $per_vendor_limit");
    if (!$pending || mysqli_num_rows($pending) == 0) continue;

    while ($tx = mysqli_fetch_assoc($pending)) {
        if ((microtime(true) - $start_time) >= $time_budget_seconds) break 2;

        // Reconstruct the exact context web/func/requery-transaction.php expects.
        $_SESSION["user_session"] = $tx["username"];
        $get_logged_user_details = mysqli_fetch_assoc(mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vendor_id' AND username='" . mysqli_real_escape_string($connection_server, $tx['username']) . "' LIMIT 1"));
        $GLOBALS['get_logged_user_details'] = $get_logged_user_details;

        if (!$get_logged_user_details || $get_logged_user_details["status"] != "1") continue;

        $purchase_method = "cron_job";
        $action_function = 2;
        $cron_job_requery_reference = $tx["reference"];
        $json_response_encode = null;

        include(WEB_ROOT . "/func/requery-transaction.php");
        $total_processed++;
    }
}

echo "Processed $total_processed pending transaction(s) in " . round(microtime(true) - $start_time, 2) . "s.\n";

flock($lock_handle, LOCK_UN);
fclose($lock_handle);
