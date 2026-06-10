<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../func/bc-connect.php");

header('Content-Type: application/json');

// Determine context
$is_admin = isset($_SESSION['admin_session']);
$is_spadmin = isset($_SESSION['spadmin_session']);
$is_user = isset($_SESSION['user_session']);

if (isset($_POST['action']) && $_POST['action'] == 'create_checkout') {
    $username = mysqli_real_escape_string($connection_server, $_POST['username']);
    $reference = mysqli_real_escape_string($connection_server, $_POST['reference']);
    $amount = (float)$_POST['amount'];
    $vid = (int)$_POST['vendor_id'];
    $is_vendor = (isset($_POST['is_vendor']) && $_POST['is_vendor'] == '1');
    $target = $_POST['target'] ?? '';

    if (empty($username) || empty($reference) || $amount <= 0 || $vid <= 0) {
        if (ob_get_length()) ob_clean();
        echo json_encode(array('status' => 'error', 'message' => 'Invalid parameters'));
        exit;
    }

    // 1. Log in checkouts
    $check = mysqli_query($connection_server, "SELECT id FROM sas_user_payment_checkouts WHERE reference='$reference' AND vendor_id='$vid'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($connection_server, "INSERT INTO sas_user_payment_checkouts (vendor_id, username, reference, status) VALUES ('$vid', '$username', '$reference', '1')");
    }

    // 2. Log in transactions as pending (status 2)
    if ($is_vendor) {
        $check_trans = mysqli_query($connection_server, "SELECT id FROM sas_vendor_transactions WHERE reference='$reference' AND vendor_id='$vid'");
        if (mysqli_num_rows($check_trans) == 0) {
             $v_q = mysqli_query($connection_server, "SELECT balance FROM sas_vendors WHERE id='$vid' LIMIT 1");
             $v_r = mysqli_fetch_assoc($v_q);
             $bal = $v_r['balance'] ?? 0;

             $p_uid = ($target == 'plisio_activation') ? 'plisio_activation' : (($target == 'payout_activation') ? 'payout_activation' : 'wallet_funding');
             $type_alt = ($target == 'plisio_activation' || $target == 'payout_activation') ? 'Service Activation' : 'Wallet Funding';
             $desc = ($target == 'plisio_activation') ? 'Plisio Crypto Gateway Activation Fee' : (($target == 'payout_activation') ? 'Withdrawal Module Activation Fee' : 'Wallet funding via ATM/Transfer');

             mysqli_query($connection_server, "INSERT INTO sas_vendor_transactions (vendor_id, product_unique_id, type_alternative, reference, amount, discounted_amount, balance_before, balance_after, description, api_website, status) VALUES ('$vid', '$p_uid', '$type_alt', '$reference', '$amount', '$amount', '$bal', '$bal', '$desc', '".$_SERVER['HTTP_HOST']."', '2')");
        }
    } else {
        $check_trans = mysqli_query($connection_server, "SELECT id FROM sas_transactions WHERE reference='$reference' AND vendor_id='$vid'");
        if (mysqli_num_rows($check_trans) == 0) {
             $user_q = mysqli_query($connection_server, "SELECT balance FROM sas_users WHERE vendor_id='$vid' AND username='$username' LIMIT 1");
             $user_r = mysqli_fetch_assoc($user_q);
             $bal = $user_r['balance'] ?? 0;
             mysqli_query($connection_server, "INSERT INTO sas_transactions (vendor_id, product_unique_id, type_alternative, reference, username, amount, discounted_amount, balance_before, balance_after, description, mode, status) VALUES ('$vid', 'wallet_funding', 'Wallet Funding', '$reference', '$username', '$amount', '$amount', '$bal', '$bal', 'Wallet funding via ATM/Transfer', 'WEB', '2')");
        }
    }

    if (ob_get_length()) ob_clean();
    echo json_encode(array('status' => 'success'));
    exit;
}

if (!$is_admin && !$is_user && !$is_spadmin) {
    if (ob_get_length()) ob_clean();
    echo json_encode(array('status' => 'error', 'message' => 'Session expired'));
    exit;
}

// Fetch details based on session
$vendor_id = resolveVendorID();
if ($is_admin) {
    $admin_email = $_SESSION['admin_session'];
    $get_logged_admin_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='$vendor_id' && email='$admin_email' LIMIT 1"));
    $vendor_id = $get_logged_admin_details['id'] ?? 0;
} else if ($is_spadmin) {
    $vendor_id = null; // SPAdmin can see all vendors
} else {
    $session_user = mysqli_real_escape_string($connection_server, $_SESSION["user_session"] ?? "");
    $get_logged_user_details = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vendor_id' && username='$session_user' LIMIT 1"));
    $user_id = $get_logged_user_details['id'] ?? 0;
    $vendor_id = $get_logged_user_details['vendor_id'] ?? 0;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action == 'gateway_redirect') {
        $gateway = $_GET['gateway'] ?? '';
        $reference = mysqli_real_escape_string($connection_server, $_GET['reference'] ?? '');

        // Find transaction
        $q = mysqli_query($connection_server, "SELECT * FROM sas_transactions WHERE reference='$reference' LIMIT 1");
        $is_vendor_funding = false;
        if (!$q || mysqli_num_rows($q) == 0) {
            $q = mysqli_query($connection_server, "SELECT * FROM sas_vendor_transactions WHERE reference='$reference' LIMIT 1");
            $is_vendor_funding = true;
        }

        $tx = mysqli_fetch_assoc($q);
        if (!$tx) die("Transaction not found");

        $vid = (int)$tx['vendor_id'];
        $amount = (float)$tx['amount'];
        $email = ""; $phone = ""; $name = "";

        if (!$is_vendor_funding) {
            $u_q = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vid' AND username='".mysqli_real_escape_string($connection_server, $tx['username'])."' LIMIT 1");
            $u = mysqli_fetch_assoc($u_q);
            $email = $u['email'] ?? '';
            $phone = $u['phone_number'] ?? '';
            $name = ($u['firstname'] ?? '') . " " . ($u['lastname'] ?? '');
        } else {
            $v_q = mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='$vid' LIMIT 1");
            $v = mysqli_fetch_assoc($v_q);
            $email = $v['email'] ?? '';
            $phone = $v['phone_number'] ?? '';
            $name = ($v['firstname'] ?? '') . " " . ($v['lastname'] ?? '');
        }

        if ($gateway == 'payhub') {
            $callback_url = $is_vendor_funding ? $web_http_host . "/bc-admin/payhub-success.php" : $web_http_host . "/web/payhub-success.php";
            $res_json = makePayhubRequest("POST", "api/transaction/initialize", [
                "email" => $email,
                "amount" => $amount,
                "name" => $name,
                "phone" => $phone,
                "reference" => $reference,
                "callback_url" => $callback_url,
                "metadata" => json_encode([
                    "vendor_id" => $vid,
                    "username" => $tx['username'] ?? '',
                    "target" => $is_vendor_funding ? "vendor" : "user",
                    "reference" => $reference,
                    "product_unique_id" => $tx['product_unique_id'] ?? ''
                ])
            ], $vid, $is_vendor_funding);

            $res = json_decode($res_json, true);
            if (($res['status'] ?? '') == 'success') {
                $inner = json_decode($res['json_result'], true);
                // Support both nested and flat structures for the authorization_url
                $url = $inner['data']['authorization_url'] ?? ($inner['authorization_url'] ?? ($inner['data']['checkout_url'] ?? ($inner['checkout_url'] ?? '')));
                if (!empty($url)) {
                    if (ob_get_length()) ob_clean();
                    echo json_encode(['status' => 'success', 'checkout_url' => $url]);
                    exit;
                }
            }
            if (ob_get_length()) ob_clean();
            echo json_encode(['status' => 'error', 'message' => $res['message'] ?? 'Initialization failed']);
            exit;
        }
        die("Invalid Gateway");
    }

    if ($action == 'get_transaction_details') {
        try {
            $ref = $_GET['reference'] ?? '';
            $admin_param = isset($_GET['admin']) && $_GET['admin'] == '1';

            if ($is_spadmin) {
                $tables = array('sas_transactions', 'sas_vendor_transactions', 'sas_submitted_payments', 'sas_fund_transfer_requests', 'sas_super_admin_submitted_payments', 'sas_vendor_paid_bills');
            } else if ($is_admin) {
                $tables = array('sas_transactions', 'sas_vendor_transactions', 'sas_submitted_payments', 'sas_fund_transfer_requests');
            } else {
                $tables = array('sas_transactions', 'sas_submitted_payments', 'sas_fund_transfer_requests');
            }

            $row = null;
            $found_table = '';

            foreach ($tables as $table) {
                $stmt = false;
                if ($is_spadmin) {
                    $stmt = mysqli_prepare($connection_server, "SELECT * FROM $table WHERE reference = ?");
                    if ($stmt) mysqli_stmt_bind_param($stmt, "s", $ref);
                } else if ($is_admin) {
                    $stmt = mysqli_prepare($connection_server, "SELECT * FROM $table WHERE vendor_id = ? AND reference = ?");
                    if ($stmt) mysqli_stmt_bind_param($stmt, "is", $vendor_id, $ref);
                } else {
                    $stmt = mysqli_prepare($connection_server, "SELECT * FROM $table WHERE vendor_id = ? AND username = ? AND reference = ?");
                    if ($stmt) mysqli_stmt_bind_param($stmt, "iss", $vendor_id, $get_logged_user_details['username'], $ref);
                }

                if ($stmt) {
                    mysqli_stmt_execute($stmt);
                    $res = mysqli_stmt_get_result($stmt);
                    if ($row = mysqli_fetch_assoc($res)) {
                        $found_table = $table;
                        break;
                    }
                }
            }

            if ($row) {
                if (!empty($row["api_id"]) && !empty($row["product_id"])) {
                    $vid_esc = (int)($row['vendor_id'] ?? 0);
                    $prod_id_esc = (int)($row["product_id"]);
                    $api_id_esc = (int)($row["api_id"]);
                    $stmt_prod = mysqli_prepare($connection_server, "SELECT * FROM sas_products WHERE vendor_id = ? AND id = ? LIMIT 1");
                    if ($stmt_prod) {
                        mysqli_stmt_bind_param($stmt_prod, "ii", $vid_esc, $prod_id_esc);
                        mysqli_stmt_execute($stmt_prod);
                        $get_prod = mysqli_fetch_array(mysqli_stmt_get_result($stmt_prod));
                    } else {
                        $get_prod = false;
                    }
                    $stmt_api = mysqli_prepare($connection_server, "SELECT * FROM sas_apis WHERE vendor_id = ? AND id = ? LIMIT 1");
                    if ($stmt_api) {
                        mysqli_stmt_bind_param($stmt_api, "ii", $vid_esc, $api_id_esc);
                        mysqli_stmt_execute($stmt_api);
                        $get_api = mysqli_fetch_array(mysqli_stmt_get_result($stmt_api));
                    } else {
                        $get_api = false;
                    }
                    $type = ucwords(($get_prod["product_name"] ?? '') . " " . str_replace(array("-", "_"), " ", ($get_api["api_type"] ?? '')));
                } else {
                    $type = ucwords($row["type_alternative"] ?? $row["description"] ?? 'Transaction');
                }

                $details = array(
                    'Type' => $type,
                    'Reference' => $row['reference'] ?? 'N/A',
                    'Username' => isset($row['username']) ? $row['username'] : (isset($row['recipient_username']) ? 'To: '.$row['recipient_username'] : 'N/A'),
                    'Description' => isset($row['description']) ? $row['description'] : 'N/A',
                    'Amount' => '₦' . number_format($row['amount'] ?? 0, 2),
                    'Amount Paid' => '₦' . number_format($row['discounted_amount'] ?? 0, 2),
                    'Balance Before' => isset($row['balance_before']) ? '₦' . number_format($row['balance_before'], 2) : 'N/A',
                    'Balance After' => isset($row['balance_after']) ? '₦' . number_format($row['balance_after'], 2) : 'N/A',
                    'Mode' => isset($row['mode']) ? $row['mode'] : 'N/A',
                    'Status' => tranStatus($row['status'] ?? 0),
                    'Date' => formDate($row['date'] ?? ''),
                    'API Website' => isset($row['api_website']) ? $row['api_website'] : 'N/A'
                );

                if (!$admin_param) {
                    unset($details['Status']);
                    unset($details['API Website']);
                }

                if ($admin_param && $is_admin) {
                    $is_payment_order_flag = ($found_table == 'sas_submitted_payments');
                    $actions = adminTransactionActionButton($row["api_id"] ?? null, $row["product_id"] ?? null, $row["reference"] ?? '', $row["status"] ?? 0, $type, $is_payment_order_flag, $row["description"] ?? '');
                } else {
                    $actions = transactionActionButton($row["api_id"] ?? null, $row["product_id"] ?? null, $row["reference"] ?? '', $row["status"] ?? 0, $type, $row["description"] ?? '');
                }

                $json = json_encode(array('status' => 'success', 'data' => $details, 'actions' => (string)$actions), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($json === false) {
                    $json = json_encode(array('status' => 'error', 'message' => 'Could not encode transaction details.'));
                }
                if (ob_get_length()) ob_clean();
                echo $json;
            } else {
                if (ob_get_length()) ob_clean();
                echo json_encode(array('status' => 'error', 'message' => 'Transaction not found'));
            }
        } catch (Throwable $e) {
            if (ob_get_length()) ob_clean();
            echo json_encode(array('status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()));
        }
        exit;
    }
}

if (ob_get_length()) ob_clean();
echo json_encode(array('status' => 'error', 'message' => 'Invalid request'));
