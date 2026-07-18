<?php
// php-version/includes/functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Security Headers
$isCheckoutEmbed = (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'checkout.php') !== false) && (isset($_GET['embed']) && $_GET['embed'] === '1');
if (!$isCheckoutEmbed) {
    header("X-Frame-Options: SAMEORIGIN");
}
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Define base path
define('BASE_PATH', dirname(__DIR__) . '/');
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '/';
$base_dir = str_replace(basename($script_name), '', $script_name);
// Ensure we get the root of the php-version directory by stripping subfolders
$base_dir = preg_replace('#/(admin|merchant|api)/.*$#', '/', $base_dir);
// Remove double slashes and ensure trailing slash
$base_dir = preg_replace('#/+#', '/', $base_dir);
if (substr($base_dir, -1) !== '/') $base_dir .= '/';

define('BASE_URL', $protocol . "://" . $host . $base_dir);

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/db.php';

// Include Composer Autoloader for PHPMailer
if (file_exists(BASE_PATH . 'vendor/autoload.php')) {
    require_once BASE_PATH . 'vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Lightweight auto-migration for critical table stability
function ensure_critical_tables() {
    if (!isInstalled()) return;

    // Quick version check to avoid redundant DB calls on every request
    $version = '1.2.1';
    if (getConfig('sys_db_version') === $version) return;

    try {
        $db = Database::connect();
        $essential_tables = [
            'config' => "CREATE TABLE IF NOT EXISTS config (`key` VARCHAR(100) PRIMARY KEY, `value` TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'api_logs' => "CREATE TABLE IF NOT EXISTS api_logs (id INT AUTO_INCREMENT PRIMARY KEY, endpoint VARCHAR(255), method VARCHAR(10), payload TEXT, response TEXT, status_code INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'users' => "CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, business_name VARCHAR(255), role ENUM('admin', 'merchant') DEFAULT 'merchant', wallet_balance DECIMAL(15, 2) DEFAULT 0.00, is_kyc_verified TINYINT DEFAULT 0, is_suspended TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'virtual_accounts' => "CREATE TABLE IF NOT EXISTS virtual_accounts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, bank_name VARCHAR(255), account_number VARCHAR(50), account_name VARCHAR(255), customer_email VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'transactions' => "CREATE TABLE IF NOT EXISTS transactions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, reference VARCHAR(100) UNIQUE, amount DECIMAL(15,2), status VARCHAR(20) DEFAULT 'pending', customer_email VARCHAR(255), payment_method VARCHAR(50) DEFAULT 'card', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'ledger' => "CREATE TABLE IF NOT EXISTS ledger (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, amount DECIMAL(15, 2), type ENUM('credit', 'debit'), category VARCHAR(50), description TEXT, balance_after DECIMAL(15, 2), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'disputes' => "CREATE TABLE IF NOT EXISTS disputes (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, transaction_id INT, reason TEXT, status ENUM('open', 'won', 'lost') DEFAULT 'open', evidence_path VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'customers' => "CREATE TABLE IF NOT EXISTS customers (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, full_name VARCHAR(255), email VARCHAR(255), phone VARCHAR(50), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `merchant_customer` (`user_id`, `email`)) ENGINE=InnoDB",
            'transaction_timeline' => "CREATE TABLE IF NOT EXISTS transaction_timeline (id INT AUTO_INCREMENT PRIMARY KEY, transaction_id INT, event_type VARCHAR(50), description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'payouts' => "CREATE TABLE IF NOT EXISTS payouts (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, amount DECIMAL(15,2), status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending', reference VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'subscriptions' => "CREATE TABLE IF NOT EXISTS subscriptions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, customer_email VARCHAR(255), plan_name VARCHAR(100), amount DECIMAL(15,2), status VARCHAR(20), next_billing_date DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'tickets' => "CREATE TABLE IF NOT EXISTS tickets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, guest_email VARCHAR(255) NULL, is_registered TINYINT DEFAULT 1, subject VARCHAR(255), message TEXT, status ENUM('open', 'processing', 'resolved', 'closed') DEFAULT 'open', priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'ticket_messages' => "CREATE TABLE IF NOT EXISTS ticket_messages (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT, user_id INT NULL, message TEXT, is_admin TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'blog_posts' => "CREATE TABLE IF NOT EXISTS blog_posts (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), content TEXT, author VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'webhook_logs' => "CREATE TABLE IF NOT EXISTS webhook_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, event_type VARCHAR(100), payload TEXT, response_code INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'staff_roles' => "CREATE TABLE IF NOT EXISTS staff_roles (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, role VARCHAR(50), permissions TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
            'otp_codes' => "CREATE TABLE IF NOT EXISTS otp_codes (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL, otp_code VARCHAR(6) NOT NULL, purpose ENUM('registration','login') NOT NULL, expires_at DATETIME NOT NULL, used TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_email_purpose (email, purpose)) ENGINE=InnoDB"
        ];
        foreach ($essential_tables as $sql) { $db->exec($sql); }

        // Ensure critical columns exist
        $cols = [
            'users' => [
                'is_deleted' => "TINYINT DEFAULT 0",
                'settlement_bank' => "VARCHAR(100)",
                'settlement_account_number' => "VARCHAR(50)",
                'settlement_account_name' => "VARCHAR(255)",
                'settlement_bank_code' => "VARCHAR(10)",
                'parent_id' => "INT DEFAULT NULL",
                'fee_percentage' => "DECIMAL(5, 2) DEFAULT NULL",
                'fee_flat' => "DECIMAL(15, 2) DEFAULT NULL",
                'is_suspended' => "TINYINT DEFAULT 0",
                'registration_number' => "VARCHAR(100)",
                'bn_number' => "VARCHAR(100)",
                'tin' => "VARCHAR(100)",
                'business_type' => "VARCHAR(50)",
                'id_card_path' => "VARCHAR(255)",
                'utility_bill_path' => "VARCHAR(255)",
                'liveliness_path' => "VARCHAR(255)",
                'cac_cert_path' => "VARCHAR(255)",
                'cac_form_path' => "VARCHAR(255)",
                'memart_path' => "VARCHAR(255)",
                'bn_cert_path' => "VARCHAR(255)",
                'bn_form_path' => "VARCHAR(255)",
                'ngo_form_path' => "VARCHAR(255)",
                'ngo_constitution_path' => "VARCHAR(255)",
                'gov_auth_letter_path' => "VARCHAR(255)",
                'gov_gazette_path' => "VARCHAR(255)",
                'business_address_proof_path' => "VARCHAR(255)",
                'is_test_mode' => "TINYINT DEFAULT 1",
                'webhook_url' => "VARCHAR(255)",
                'has_payout_consent' => "TINYINT DEFAULT 0",
                'payout_consent_date' => "TIMESTAMP NULL",
                'payout_method' => "VARCHAR(20) DEFAULT 'manual'",
                'payout_method_status' => "VARCHAR(20) DEFAULT 'active'",
                'pending_payout_method' => "VARCHAR(20) DEFAULT NULL",
                'kyc_notes' => "TEXT",
                'country' => "VARCHAR(100) DEFAULT 'Nigeria'",
                'id_type' => "VARCHAR(100)",
                'id_expiry_date' => "DATE",
                'bvn' => "VARCHAR(20)",
                'residential_address' => "TEXT",
                'rc_number' => "VARCHAR(100)",
                'two_factor_secret' => "VARCHAR(255)",
                'two_factor_enabled' => "TINYINT DEFAULT 0",
                'security_pin' => "VARCHAR(255)",
                'phone_number' => "VARCHAR(50)",
                'settlement_currency' => "VARCHAR(10) DEFAULT 'NGN'"
            ],
            'transactions' => [
                'customer_email' => "VARCHAR(255)",
                'customer_name' => "VARCHAR(255)",
                'payment_method' => "VARCHAR(50) DEFAULT 'card'",
                'is_test' => "TINYINT DEFAULT 0",
                'fee_amount' => "DECIMAL(15, 2) DEFAULT 0.00",
                'settled_amount' => "DECIMAL(15, 2) DEFAULT 0.00",
                'currency' => "VARCHAR(10) DEFAULT 'NGN'",
                'gateway_reference' => "VARCHAR(100)",
                'invoice_id' => "INT DEFAULT NULL",
                'metadata' => "TEXT"
            ],
            'invoices' => [
                'reference' => "VARCHAR(100)",
                'customer_name' => "VARCHAR(255)",
                'description' => "TEXT",
                'status' => "ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending'",
                'is_test_mode' => "TINYINT DEFAULT 0"
            ],
            'virtual_accounts' => [
                'customer_email' => "VARCHAR(255)",
                'account_name' => "VARCHAR(255)",
                'metadata' => "TEXT"
            ],
            'payouts' => [
                'status_details' => "TEXT",
                'gateway_reference' => "VARCHAR(100)",
                'fee_amount' => "DECIMAL(15, 2) DEFAULT 0.00",
                'net_amount' => "DECIMAL(15, 2) DEFAULT 0.00"
            ]
        ];
        foreach ($cols as $table => $columns) {
            foreach ($columns as $col => $def) {
                try {
                    $cCheck = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
                    if (!$cCheck) {
                        $db->exec("ALTER TABLE `$table` ADD `$col` $def");
                    }
                } catch (\Throwable $e) {}
            }
        }

        // Ensure support system columns and fix foreign key constraints
        // 1. Identify and Drop foreign keys on ticket_messages that point to users
        try {
            // Drop ALL foreign keys on ticket_messages to be sure
            $fks = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'ticket_messages' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND TABLE_SCHEMA = DATABASE()")->fetchAll();
            foreach ($fks as $fk) {
                $db->exec("ALTER TABLE ticket_messages DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME']);
            }
        } catch (\Throwable $e) {
            error_log("FK Drop Error: " . $e->getMessage());
        }

        // 2. Ensure tickets and ticket_messages tables have correct schema
        try {
            // Aggressive schema fix
            $db->exec("ALTER TABLE tickets MODIFY user_id INT NULL");
            $db->exec("ALTER TABLE ticket_messages MODIFY user_id INT NULL");

            $colsToAdd = [
                'tickets' => [
                    'guest_email' => "VARCHAR(255) NULL AFTER user_id",
                    'is_registered' => "TINYINT DEFAULT 1 AFTER guest_email",
                    'priority' => "ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium' AFTER status"
                ],
                'ticket_messages' => [
                    'is_admin' => "TINYINT DEFAULT 0 AFTER message"
                ]
            ];

            foreach ($colsToAdd as $table => $columns) {
                foreach ($columns as $col => $def) {
                    try {
                        $cCheck = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
                        if (!$cCheck) {
                            $db->exec("ALTER TABLE `$table` ADD `$col` $def");
                        }
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {
            error_log("Support Schema Fix Error: " . $e->getMessage());
        }

        // Set version flag to skip future checks until next code update
        $stmt = $db->prepare("INSERT INTO config (`key`, `value`) VALUES ('sys_db_version', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute([$version]);

    } catch (\Throwable $e) {
        error_log("Critical Table Migration Error: " . $e->getMessage());
    }
}
ensure_critical_tables();

function isInstalled() {
    return file_exists(__DIR__ . '/config.php');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($path) {
    header("Location: $path");
    exit;
}

function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

function generateApiKey($prefix) {
    return $prefix . bin2hex(random_bytes(16));
}

function getAuthUser() {
    if (!isLoggedIn()) return null;
    try {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (\Throwable $e) {
        return null;
    }
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Robustly get input for API requests, merging GET, POST, and JSON body.
 */
function get_api_input() {
    $input = [];
    // 1. Get query params
    $input = array_merge($input, $_GET);
    // 2. Get POST params
    $input = array_merge($input, $_POST);
    // 3. Get JSON body
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
    }
    return $input;
}

function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getConfig($key, $default = '') {
    try {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT `value` FROM config WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function get_stats($userId, $is_test = 0) {
    $db = Database::connect();
    $stmt = $db->prepare("
        SELECT 
            SUM(amount) as total_volume,
            COUNT(*) as transaction_count,
            (SELECT wallet_balance FROM users WHERE id = ?) as balance
        FROM transactions 
        WHERE user_id = ? AND status = 'success' AND is_test = ?
    ");
    $stmt->execute([$userId, $userId, $is_test]);
    $stats = $stmt->fetch();

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM transactions WHERE user_id = ? AND is_test = ?");
    $stmt->execute([$userId, $is_test]);
    $total = $stmt->fetch();

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM transactions WHERE user_id = ? AND status = 'success' AND is_test = ?");
    $stmt->execute([$userId, $is_test]);
    $success = $stmt->fetch();

    $successRate = $total['count'] > 0 ? number_format(($success['count'] / $total['count']) * 100, 1) : '100';

    return [
        'total_volume' => $stats['total_volume'] ?? 0,
        'transaction_count' => $stats['transaction_count'] ?? 0,
        'balance' => $stats['balance'] ?? 0,
        'success_rate' => $successRate . '%'
    ];
}

/**
 * Paystack Integration Helpers
 */
function paystack_call($endpoint, $method = 'GET', $data = [], $is_test = null) {
    if ($is_test === null) {
        $user = getAuthUser();
        $is_test = $user ? ($user['is_test_mode'] == 1) : false;
    }

    $secret_key = $is_test ? getConfig('paystack_test_secret_key') : getConfig('paystack_secret_key');
    if (!$secret_key) $secret_key = getConfig('paystack_secret_key'); // Fallback to live key if test not set

    if (!$secret_key) return ['status' => false, 'message' => 'Paystack not configured'];

    $url = "https://api.paystack.co/" . $endpoint;
    $headers = [
        "Authorization: Bearer " . $secret_key,
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) return ['status' => false, 'message' => 'CURL error'];

    // Log the API call
    try {
        $db = Database::connect();
        $stmt = $db->prepare("INSERT INTO api_logs (endpoint, method, payload, response, status_code) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$endpoint, $method, json_encode($data), $response, (int)$http_code]);
    } catch (\Throwable $t) {}

    $result = json_decode($response, true);
    return $result;
}

/**
 * Ensures a customer has a virtual account and is registered locally.
 */
function ensure_virtual_account($userId, $email, $customerData = [], $is_test = null, $metadata = '') {
    $log_file = BASE_PATH . 'va_debug.log';
    $log = function($msg) use ($log_file) {
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
    };

    try {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $merchant = $stmt->fetch();
        if (!$merchant) {
            $log("ERROR: Merchant not found for userId: $userId");
            return ['status' => false, 'message' => 'Merchant not found'];
        }

        if ($is_test === null) {
            $is_test = ($merchant['is_test_mode'] == 1);
        }
        $log("VA request for merchant userId=$userId, email=$email, is_test=" . ($is_test ? 'YES' : 'NO'));

        $stmt = $db->prepare("SELECT * FROM virtual_accounts WHERE user_id = ? AND customer_email = ?");
        $stmt->execute([$userId, $email]);
        $existing = $stmt->fetch();

        if ($existing && !empty($existing['account_number']) && $existing['account_number'] !== '0000000000') {
            $log("Existing VA found: " . $existing['account_number']);
            return ['status' => true, 'message' => 'Account exists', 'data' => $existing];
        }

        $fullName = $customerData['full_name'] ?? 'Customer';
        $phone = $customerData['phone'] ?? '';
        $log("Creating Paystack customer: name=$fullName, phone=$phone, email=$email");

        // Insert or update customer in local database so they show in Customer Directory
        $stmt = $db->prepare("INSERT INTO customers (user_id, full_name, email, phone) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), phone = VALUES(phone)");
        $stmt->execute([$userId, $fullName, $email, $phone]);

        // Paystack Customer logic
        $paystackCustomer = paystack_call('customer', 'POST', [
            'email' => $email,
            'first_name' => explode(' ', $fullName)[0],
            'last_name' => explode(' ', $fullName)[1] ?? 'Merchant',
            'phone' => $phone,
            'metadata' => ['merchant_id' => $userId]
        ], $is_test);

        $log("Paystack customer response: " . json_encode($paystackCustomer));

        if (!$paystackCustomer || !$paystackCustomer['status']) {
            $log("ERROR: Paystack Customer creation failed: " . ($paystackCustomer['message'] ?? 'Unknown'));
            return ['status' => false, 'message' => 'Paystack Customer Error: ' . ($paystackCustomer['message'] ?? 'Unknown error')];
        }
        $customerCode = $paystackCustomer['data']['customer_code'];
        $log("Customer code obtained: $customerCode");

        // Paystack Gotcha: If customer already existed on Paystack, calling POST customer returns the customer
        // but does NOT update their phone number if it was missing. We must explicitly update it.
        $paystackPhone = $paystackCustomer['data']['phone'] ?? '';
        if (empty($paystackPhone) && !empty($phone)) {
            $log("Customer phone is empty on Paystack. Updating customer profile for $customerCode with phone $phone");
            $updateRes = paystack_call('customer/' . $customerCode, 'PUT', [
                'phone' => $phone,
                'first_name' => explode(' ', $fullName)[0],
                'last_name' => explode(' ', $fullName)[1] ?? 'Merchant'
            ], $is_test);
            $log("Paystack customer update response: " . json_encode($updateRes));
        }

        $log("Requesting dedicated virtual account for customer: $customerCode");
        
        // Fetch available providers first to solve the "Please choose a provider" error
        $preferred_bank = null;
        $providersRes = paystack_call('dedicated_account/available_providers', 'GET', [], $is_test);
        $log("Available providers response: " . json_encode($providersRes));
        if ($providersRes && $providersRes['status'] && !empty($providersRes['data'])) {
            $preferred_bank = $providersRes['data'][0]['provider_slug'] ?? null;
            $log("Selected preferred bank provider: " . $preferred_bank);
        }

        $dvaParams = ['customer' => $customerCode];
        if ($preferred_bank) {
            $dvaParams['preferred_bank'] = $preferred_bank;
        }

        $dvaRes = paystack_call('dedicated_account', 'POST', $dvaParams, $is_test);
        $log("Paystack DVA response: " . json_encode($dvaRes));

        if ($dvaRes && $dvaRes['status']) {
            $acc = $dvaRes['data'];
            $bank = $acc['bank']['name'] ?? 'Virtual Bank';
            $number = $acc['account_number'] ?? '';
            $accName = $acc['account_name'] ?? $merchant['business_name'];

            if (!empty($number)) {
                $stmt = $db->prepare("INSERT INTO virtual_accounts (user_id, bank_name, account_number, account_name, customer_email, metadata) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE account_number = VALUES(account_number), metadata = VALUES(metadata)");
                $stmt->execute([$userId, $bank, $number, $accName, $email, $metadata]);
                $log("SUCCESS: VA created - Bank=$bank, Number=$number, Name=$accName");
                return ['status' => true, 'message' => 'VA generated', 'data' => ['account_number' => $number, 'bank_name' => $bank, 'account_name' => $accName]];
            }
            $log("ERROR: DVA response was successful but account_number is empty");
        } else {
            $log("ERROR: DVA creation failed. Response: " . json_encode($dvaRes));
        }
        return ['status' => false, 'message' => 'Failed to generate VA: ' . ($dvaRes['message'] ?? 'Unknown error')];
    } catch (\Throwable $e) {
        if (isset($log)) $log("EXCEPTION: " . $e->getMessage());
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

function log_transaction_event($transactionId, $type, $desc) {
    try {
        $db = Database::connect();
        $stmt = $db->prepare("INSERT INTO transaction_timeline (transaction_id, event_type, description) VALUES (?, ?, ?)");
        return $stmt->execute([$transactionId, $type, $desc]);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Processes a payout via Paystack Transfer API.
 * Can use merchant's default settlement details OR custom provided bank details for customer withdrawals.
 */
function paystack_payout($userId, $amount, $reason = "Merchant Payout", $customBank = []) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT settlement_bank, settlement_bank_code, settlement_account_number, settlement_account_name, is_test_mode FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();

    if (!$u) return ['status' => false, 'message' => 'Merchant not found'];

    $is_test = ($u['is_test_mode'] == 1);

    // Determine target account details
    $targetBankCode = $customBank['bank_code'] ?? $u['settlement_bank_code'];
    $targetAccountNumber = $customBank['account_number'] ?? $u['settlement_account_number'];
    $targetAccountName = $customBank['account_name'] ?? ($customBank['bank_name'] ?? ($u['settlement_account_name'] ?: $reason));

    if (empty($targetBankCode) || empty($targetAccountNumber)) {
        return ['status' => false, 'message' => 'Target bank details incomplete'];
    }

    // 1. Create Transfer Recipient
    $recipient = paystack_call('transferrecipient', 'POST', [
        'type' => 'nuban',
        'name' => $targetAccountName,
        'account_number' => $targetAccountNumber,
        'bank_code' => $targetBankCode,
        'currency' => 'NGN'
    ], $is_test);

    if (!$recipient || !$recipient['status']) {
        return ['status' => false, 'message' => 'Failed to create recipient: ' . ($recipient['message'] ?? 'Unknown error')];
    }

    $recipient_code = $recipient['data']['recipient_code'];

    // 2. Initiate Transfer
    $transfer = paystack_call('transfer', 'POST', [
        'source' => 'balance',
        'amount' => (int)($amount * 100),
        'recipient' => $recipient_code,
        'reason' => $reason
    ], $is_test);

    // Detailed Logging
    file_put_contents(BASE_PATH . 'payout_debug.log', "[" . date('Y-m-d H:i:s') . "] Payout for user $userId: " . json_encode($transfer) . PHP_EOL, FILE_APPEND);

    return $transfer;
}

/**
 * Calculates the payout fee based on tiered structure and stamp duty rules.
 */
function calculate_payout_fee($amount) {
    // 1. Determine the Base Transfer Fee
    $tier1_max = (float)getConfig('payout_tier1_max', '5000');
    $tier1_fee = (float)getConfig('payout_tier1_fee', '10');

    $tier2_max = (float)getConfig('payout_tier2_max', '50000');
    $tier2_fee = (float)getConfig('payout_tier2_fee', '25');

    $tier3_fee = (float)getConfig('payout_tier3_fee', '50');

    if ($amount <= $tier1_max) {
        $transferFee = $tier1_fee;
    } elseif ($amount <= $tier2_max) {
        $transferFee = $tier2_fee;
    } else {
        $transferFee = $tier3_fee;
    }

    // 2. Add Government Stamp Duty (Tax Act 2025)
    $stamp_threshold = (float)getConfig('stamp_duty_threshold', '10000');
    $stamp_fee = (float)getConfig('stamp_duty_fee', '50');
    $stampDuty = ($amount >= $stamp_threshold) ? $stamp_fee : 0;

    // 3. Add PayHub's "Markup" for profit
    $payhubMargin = (float)getConfig('payout_markup', '0');

    return $transferFee + $stampDuty + $payhubMargin;
}

function calculate_fees($amount, $is_international = false, $userId = null) {
    $percent = null;
    $flat = null;

    if ($userId) {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT fee_percentage, fee_flat FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $percent = $user['fee_percentage'];
            $flat = $user['fee_flat'];
        }
    }

    if ($is_international) {
        if ($percent === null) $percent = (float)getConfig('international_fee_percent', '3.9');
        if ($flat === null) $flat = (float)getConfig('international_fee_flat', '100');
        $fee = ($amount * ($percent / 100)) + $flat;
        return $fee;
    } else {
        if ($percent === null) $percent = (float)getConfig('transaction_fee_percent', '1.5');
        if ($flat === null) {
            $flat = ($amount < 2500) ? 0 : (float)getConfig('transaction_fee_flat', '100');
        }
        $cap = (float)getConfig('transaction_fee_cap', '2000');

        $fee = ($amount * ($percent / 100)) + $flat;
        if ($fee > $cap) $fee = $cap;
        return $fee;
    }
}

/**
 * Triggers the merchant's webhook for a specific transaction.
 */
function trigger_merchant_webhook($transactionId) {
    try {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT t.*, u.webhook_url FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
        $stmt->execute([$transactionId]);
        $tx = $stmt->fetch();

        if ($tx && !empty($tx['webhook_url']) && $tx['status'] === 'success') {
            $payload = [
                'event' => 'charge.success',
                'data' => [
                    'id' => $tx['gateway_reference'] ?? $tx['id'],
                    'reference' => $tx['reference'],
                    'amount' => $tx['amount'] * 100,
                    'status' => 'success',
                    'currency' => $tx['currency'],
                    'customer' => ['email' => $tx['customer_email']],
                    'metadata' => json_decode($tx['metadata'] ?? '[]', true),
                    'channel' => $tx['payment_method'],
                    'paid_at' => $tx['created_at']
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tx['webhook_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Log Webhook Forwarding
            try {
                $stmtLog = $db->prepare("INSERT INTO webhook_logs (user_id, event_type, payload, response_code) VALUES (?, ?, ?, ?)");
                $stmtLog->execute([$tx['user_id'], 'charge.success', json_encode($payload), (int)$code]);
            } catch (\Throwable $t) {}
        }
    } catch (\Throwable $e) {}
}

function log_ledger_entry($userId, $amount, $type, $category, $desc, $is_test = false) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $current = (float)$stmt->fetch()['wallet_balance'];

    if ($is_test) {
        $stmt = $db->prepare("INSERT INTO ledger (user_id, amount, type, category, description, balance_after) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $amount, $type, $category, "[TEST] " . $desc, $current]);
    }

    $newBalance = ($type === 'credit') ? ($current + $amount) : ($current - $amount);
    $db->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
    $stmt = $db->prepare("INSERT INTO ledger (user_id, amount, type, category, description, balance_after) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $amount, $type, $category, $desc, $newBalance]);
}

/**
 * Resizes and optimizes an image to reduce file size while maintaining readability.
 * Targets roughly < 50KB as requested.
 */
function resize_and_optimize_image($source_path, $target_path, $max_width = 1000, $quality = 50) {
    if (!extension_loaded('gd')) {
        return copy($source_path, $target_path);
    }

    $info = getimagesize($source_path);
    if (!$info) return copy($source_path, $target_path);

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($source_path); break;
        case 'image/png': $image = imagecreatefrompng($source_path); break;
        case 'image/webp': $image = imagecreatefromwebp($source_path); break;
        default: return copy($source_path, $target_path);
    }

    $width = $info[0];
    $height = $info[1];

    if ($width > $max_width) {
        $ratio = $max_width / $width;
        $new_width = $max_width;
        $new_height = (int)($height * $ratio);
        $new_image = imagecreatetruecolor($new_width, $new_height);

        // Handle transparency for PNG/WebP
        if ($mime == 'image/png' || $mime == 'image/webp') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
        }

        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $new_image;
    }

    // Save as JPEG to optimize size
    $res = imagejpeg($image, $target_path, $quality);
    imagedestroy($image);
    return $res;
}

/**
 * Generates a 6-digit OTP, stores it in the database, and sends it via email.
 * Returns true on success, false on failure.
 */
function generate_and_send_otp($email, $purpose) {
    try {
        $db = Database::connect();
        // Invalidate any previous unused OTPs for this email/purpose
        $db->prepare("UPDATE otp_codes SET used = 1 WHERE email = ? AND purpose = ? AND used = 0")
           ->execute([$email, $purpose]);

        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("INSERT INTO otp_codes (email, otp_code, purpose, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->execute([$email, $otp, $purpose]);

        $site_name = getConfig('site_name', 'Payhub');
        $subject = "Your {$site_name} verification code";
        $label   = $purpose === 'registration' ? 'complete your registration' : 'log in to your account';
        $body    = "
            <h2 style='color:#1e293b;margin:0 0 12px'>Your verification code</h2>
            <p style='color:#475569;margin:0 0 24px'>Use the code below to {$label}. It expires in 10 minutes.</p>
            <div style='font-size:36px;font-weight:700;letter-spacing:10px;color:#4f46e5;background:#eef2ff;padding:20px 30px;border-radius:12px;text-align:center;margin:0 0 24px'>{$otp}</div>
            <p style='color:#94a3b8;font-size:13px;margin:0'>If you did not request this code, you can safely ignore this email.</p>";

        return sendEmail($email, $subject, $body);
    } catch (\Throwable $e) {
        error_log("generate_and_send_otp error [{$purpose}] for {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifies an OTP for the given email and purpose.
 * Returns true if valid, false otherwise. Marks OTP as used on success.
 */
function verify_otp($email, $otp, $purpose) {
    try {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT id FROM otp_codes
             WHERE email = ? AND otp_code = ? AND purpose = ? AND used = 0 AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$email, $otp, $purpose]);
        $row = $stmt->fetch();
        if ($row) {
            $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$row['id']]);
            return true;
        }
        return false;
    } catch (\Throwable $e) {
        return false;
    }
}

function sendEmail($to, $subject, $body) {
    $smtp_host = getConfig('smtp_host');
    $smtp_port = getConfig('smtp_port');
    $smtp_user = getConfig('smtp_user');
    $smtp_pass = getConfig('smtp_pass');
    $smtp_from = getConfig('smtp_from');
    $site_name = getConfig('site_name', 'Payhub');
    $logo = getConfig('site_logo');

    // Development Fallback: Log email instead of sending if email_mock_mode is enabled
    if (getConfig('email_mock_mode') === '1') {
        error_log("sendEmail (MOCKED) to {$to}: [{$subject}] " . strip_tags($body));
        return true;
    }

    // Use smtp_user as From address when smtp_from is not configured
    if (empty($smtp_from)) {
        $smtp_from = $smtp_user ?: ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }

    $logo_html = '';
    if ($logo) {
        $logo_url = BASE_URL . 'uploads/' . $logo;
        $logo_html = "<img src='$logo_url' alt='$site_name' style='height: 40px; margin-bottom: 20px;'>";
    }

    $modern_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f7fa; color: #334155;'>
        <div style='max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 24px; overflow: hidden; shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;'>
            <div style='padding: 40px;'>
                $logo_html
                <div style='font-size: 16px; line-height: 1.6;'>
                    $body
                </div>
                <div style='margin-top: 40px; padding-top: 24px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-align: center;'>
                    &copy; " . date('Y') . " $site_name. All rights reserved.
                </div>
            </div>
        </div>
    </body>
    </html>";

    if (!$smtp_host || !$smtp_user) {
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: $site_name <$smtp_from>\r\n";
        $result = mail($to, $subject, $modern_body, $headers);
        if (!$result) {
            $last_error = error_get_last();
            error_log("sendEmail: PHP mail() failed sending to {$to}. Error: " . ($last_error['message'] ?? 'Unknown error'));
        }
        return $result;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        if ((int)$smtp_port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port       = $smtp_port;
        $mail->setFrom($smtp_from, $site_name);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $modern_body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("sendEmail: PHPMailer error sending to {$to}: " . $e->getMessage());
        return false;
    }
}
