<?php
// CodeVault PDO Database Connection Provider
// Supports both MySQL and SQLite fallback

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$config_file = dirname(__DIR__) . '/config.php';

if (!file_exists($config_file)) {
    // If config does not exist, redirect to installer if not already there
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header('Location: ' . (file_exists('install.php') ? 'install.php' : '../install.php'));
        exit;
    }
} else {
    require_once $config_file;
}

// Establish DB connection using credentials from config.php or default sqlite
try {
    if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        // Fallback to SQLite
        $sqlite_path = defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : dirname(__DIR__) . '/marketplace.db';
        $db = new PDO("sqlite:" . $sqlite_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        die("Database connection failed: " . $e->getMessage() . ". Please rerun <a href='install.php'>install.php</a>.");
    }
}

// Helper: Get system settings easily
function get_setting($key, $default = '') {
    global $db;
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetch();
        return $res ? $res['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Helper: Set dynamic setting
function set_setting($key, $value) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (Exception $e) {
        // Fallback for SQLite which doesn't support ON DUPLICATE KEY UPDATE
        try {
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (`key`, value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }
}

// Helper: Get site name
function get_platform_name() {
    return get_setting('site_name', 'CodeVault');
}

// Helper: Format price using dynamic platform currency
function format_price($amount) {
    $currency = get_setting('currency', '$');
    return $currency . number_get_formatted($amount);
}

function format_currency($amount, $currency = null) {
    if ($currency === null) {
        $currency = get_setting('currency', '$');
    }
    return $currency . number_get_formatted($amount);
}

function number_get_formatted($val) {
    return number_format((float)$val, 2, '.', ',');
}

// Clean SEF URL Helper
function url($page, $id = null) {
    // Detect base folder path automatically to support subdirectory installations
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_dir = dirname($script_name);
    // Replace backslashes with forward slashes for Windows local development
    $base_dir = str_replace('\\', '/', $base_dir);
    $base_path = ($base_dir === '/' || $base_dir === '\\') ? '' : $base_dir;
    
    // Graceful fallback if clean URLs are disabled or if Apache mod_rewrite is missing
    $clean_urls_enabled = true;
    if (get_setting('clean_urls', '1') === '0') {
        $clean_urls_enabled = false;
    } elseif (function_exists('apache_get_modules') && !in_array('mod_rewrite', apache_get_modules())) {
        $clean_urls_enabled = false;
    }
    
    if (!$clean_urls_enabled) {
        return $base_path . '/index.php?page=' . $page . ($id !== null ? '&id=' . $id : '');
    }
    
    $mappings = [
        'marketplace' => 'marketplace',
        'product' => 'product',
        'dashboard' => 'dashboard',
        'blog' => 'blog',
        'forums' => 'forums',
        'tutorials' => 'tutorials',
        'affiliate' => 'affiliate',
        'seller_profile' => 'seller',
        'flash_sale' => 'flash-sale',
        'free_files' => 'free-files',
        'collections' => 'collections',
        'policies' => 'policies',
        'help_center' => 'help',
    ];
    
    if (isset($mappings[$page])) {
        $path = $mappings[$page];
        if ($id !== null) {
            $path .= '/' . $id;
        }
        return $base_path . '/' . $path;
    }
    
    return $base_path . '/index.php?page=' . $page . ($id !== null ? '&id=' . $id : '');
}

// Authentication Helpers
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_logged_in_user() {
    global $db;
    if (!is_logged_in()) return null;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function has_role($role) {
    $user = get_logged_in_user();
    return $user && $user['role'] === $role;
}

function is_admin() {
    return has_role('admin');
}

function is_seller() {
    return has_role('seller');
}

// Auto-migration block to support new features (MySQL and SQLite compatible)
try {
    $is_sqlite = !(defined('DB_TYPE') && DB_TYPE === 'mysql');
    
    // Add columns to products table
    $columns_products = [
        'is_featured' => "TINYINT DEFAULT 0",
        'tags' => "TEXT DEFAULT NULL",
        'views_count' => "INT DEFAULT 0",
        'slug' => "VARCHAR(255) DEFAULT NULL",
        'status' => "VARCHAR(50) DEFAULT 'approved'",
        'discount_price' => "DECIMAL(10,2) DEFAULT NULL",
        'sale_ends_at' => "TIMESTAMP NULL DEFAULT NULL",
        'version' => "VARCHAR(50) DEFAULT '1.0.0'",
        'licensing_enabled' => "TINYINT DEFAULT 0",
        'license_manager_url' => "TEXT DEFAULT NULL",
        'license_manager_secret' => "TEXT DEFAULT NULL",
        'extended_price' => "DECIMAL(10,2) DEFAULT NULL"
    ];
    foreach ($columns_products as $col => $def) {
        try {
            $db->exec("ALTER TABLE products ADD COLUMN $col $def");
        } catch (Exception $e) {
            // Column already exists or table doesn't exist yet
        }
    }

    // Add columns to users table
    $columns_users = [
        'bio' => "TEXT DEFAULT NULL",
        'avatar_url' => "TEXT DEFAULT NULL",
        'reset_otp' => "VARCHAR(10) DEFAULT NULL",
        'reset_otp_expires_at' => "TIMESTAMP NULL DEFAULT NULL"
    ];
    foreach ($columns_users as $col => $def) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN $col $def");
        } catch (Exception $e) {
            // Column already exists
        }
    }

    // Add columns to categories table
    try {
        $db->exec("ALTER TABLE categories ADD COLUMN icon VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {
        // Column already exists
    }

    // Add columns to purchases table
    $columns_purchases = [
        'license_key' => "VARCHAR(255) DEFAULT NULL",
        'license_type' => "VARCHAR(50) DEFAULT 'standard'"
    ];
    foreach ($columns_purchases as $col => $def) {
        try {
            $db->exec("ALTER TABLE purchases ADD COLUMN $col $def");
        } catch (Exception $e) {
            // Column already exists
        }
    }

    // Create Collections table
    $pk_def = $is_sqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";
    
    $db->exec("CREATE TABLE IF NOT EXISTS collections (
        id $pk_def,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS collection_items (
        id $pk_def,
        collection_id INT NOT NULL,
        product_id INT NOT NULL,
        UNIQUE(collection_id, product_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS coupon_codes (
        id $pk_def,
        code VARCHAR(50) UNIQUE NOT NULL,
        type VARCHAR(20) DEFAULT 'percentage',
        value DECIMAL(10,2) NOT NULL,
        expiry_date DATE DEFAULT NULL,
        max_uses INT DEFAULT NULL,
        uses_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS follows (
        id $pk_def,
        follower_id INT NOT NULL,
        followed_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(follower_id, followed_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id $pk_def,
        user_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        status VARCHAR(50) DEFAULT 'open',
        priority VARCHAR(50) DEFAULT 'normal',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id $pk_def,
        ticket_id INT NOT NULL,
        sender_id INT NOT NULL,
        sender_name VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
} catch (Exception $e) {
    // Database has not been initialized yet or migration error
}

// Auto-release matured escrow balances database-agnostically
function clear_pending_balances($db) {
    if (!$db) return;
    try {
        $now_func = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? "NOW()" : "datetime('now')";
        $stmt = $db->query("SELECT id, user_id, amount FROM transactions WHERE status = 'pending_clearance' AND available_at <= $now_func");
        if ($stmt) {
            $matured = $stmt->fetchAll();
            if (!empty($matured)) {
                $db->beginTransaction();
                foreach ($matured as $tx) {
                    $tx_id = intval($tx['id']);
                    $uid = intval($tx['user_id']);
                    $amt = floatval($tx['amount']);
                    
                    // Deduct from pending_balance and add to withdrawable balance database-agnostically
                    $new_pending_stmt = $db->prepare("SELECT pending_balance FROM wallets WHERE user_id = ?");
                    $new_pending_stmt->execute([$uid]);
                    $current_pending = floatval($new_pending_stmt->fetchColumn());
                    $next_pending = max(0.0, $current_pending - $amt);
                    
                    $db->prepare("UPDATE wallets SET balance = balance + ?, pending_balance = ? WHERE user_id = ?")
                       ->execute([$amt, $next_pending, $uid]);
                    
                    // Mark transaction as completed
                    $db->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?")
                       ->execute([$tx_id]);
                }
                $db->commit();
            }
        }
    } catch (Exception $e) {
        if (isset($db) && method_exists($db, 'inTransaction') && $db->inTransaction()) {
            $db->rollBack();
        }
    }
}

if (isset($db)) {
    clear_pending_balances($db);
}

function generate_product_license($product, $buyer_email, $license_type = 'standard') {
    if (empty($product['licensing_enabled']) || intval($product['licensing_enabled']) !== 1) {
        return null;
    }
    
    $global_url = get_setting('global_lm_url');
    $global_secret = get_setting('global_lm_secret');
    
    if (empty($global_url) || empty($global_secret)) {
        error_log("CodeVault Licensing: Global URL or Secret is not configured.");
        return null;
    }
    
    $api_url = rtrim($global_url, '/') . '/api-create-license.php';
    $api_secret = $global_secret;
    
    // Prepare curl request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'api_secret' => $api_secret,
        'customer_email' => $buyer_email,
        'domain' => 'N/A',
        'license_type' => $license_type
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("CodeVault Licensing: curl error calling API {$api_url}: {$curl_error}");
        return null;
    }
    
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success'] && isset($data['license_key'])) {
        return $data['license_key'];
    }
    
    error_log("CodeVault Licensing: API response failed: " . json_encode($data));
    return null;
}

/**
 * Automatically sends an email alert of a newly approved product to all registered users.
 */
function send_product_email_ads($db, $product_id) {
    $p_stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $p_stmt->execute([$product_id]);
    $p = $p_stmt->fetch();
    if (!$p || $p['status'] !== 'approved') {
        return false;
    }

    $setting_key = 'email_sent_prod_' . $product_id;
    if (get_setting($setting_key, '0') === '1') {
        return false; 
    }
    set_setting($setting_key, '1');

    $site_name = get_platform_name();
    
    // Construct base URL
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || ($_SERVER['SERVER_PORT'] == 443);
    $protocol = $is_https ? 'https' : 'http';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_dir = dirname($script_name);
    $base_dir = str_replace('\\', '/', $base_dir);
    $base_path = ($base_dir === '/' || $base_dir === '\\') ? '' : $base_dir;
    $site_url = $protocol . '://' . $host . $base_path;
    
    $product_url = $site_url . '/index.php?page=product&id=' . $p['id'];
    $thumb_url = (strpos($p['thumbnail'], 'http') === 0) ? $p['thumbnail'] : ($site_url . '/' . $p['thumbnail']);
    
    $u_stmt = $db->query("SELECT email, name FROM users");
    $users = $u_stmt->fetchAll();
    
    $subject = "🔥 New Product Launch: " . $p['title'] . " - " . $site_name;
    
    $price_display = !empty($p['discount_price']) ? ('$' . number_format($p['discount_price'], 2) . ' <span style="text-decoration: line-through; color: #a0aec0; font-size: 14px;">$' . number_format($p['price'], 2) . '</span>') : ('$' . number_format($p['price'], 2));
    
    foreach ($users as $u) {
        $to = $u['email'];
        $name = htmlspecialchars($u['name']);
        
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . htmlspecialchars($p['title']) . '</title>
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f7fafc; margin: 0; padding: 40px 0; -webkit-font-smoothing: antialiased;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                <tr>
                    <td align="center" style="background-color: #1a202c; padding: 24px 0; color: #ffffff;">
                        <h1 style="margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">' . htmlspecialchars($site_name) . '</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 40px 32px;">
                        <p style="margin-top: 0; margin-bottom: 24px; font-size: 16px; color: #4a5568; line-height: 1.6;">Hello ' . $name . ',</p>
                        <p style="margin-bottom: 32px; font-size: 16px; color: #4a5568; line-height: 1.6;">We are thrilled to present our newest product arrival, now live and available for instant download on ' . htmlspecialchars($site_name) . '!</p>
                        
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; margin-bottom: 32px;">
                            <tr>
                                <td>
                                    <img src="' . htmlspecialchars($thumb_url) . '" alt="' . htmlspecialchars($p['title']) . '" width="100%" style="display: block; border-bottom: 1px solid #e2e8f0;" />
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 24px;">
                                    <h2 style="margin-top: 0; margin-bottom: 12px; font-size: 20px; font-weight: 800; color: #1a202c; line-height: 1.3;">' . htmlspecialchars($p['title']) . '</h2>
                                    <p style="margin-bottom: 16px; font-size: 14px; color: #718096; line-height: 1.5;">Category: <strong>' . htmlspecialchars($p['category']) . '</strong></p>
                                    <p style="margin-bottom: 24px; font-size: 14px; color: #4a5568; line-height: 1.6;">' . htmlspecialchars(substr(strip_tags($p['description']), 0, 180)) . '...</p>
                                    
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td style="font-size: 24px; font-weight: 800; color: #5cb85c;">
                                                ' . $price_display . '
                                            </td>
                                            <td align="right">
                                                <a href="' . htmlspecialchars($product_url) . '" style="background-color: #5cb85c; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; display: inline-block;">View Script</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        
                        <p style="margin-bottom: 0; font-size: 14px; color: #a0aec0; line-height: 1.6;">You received this email because you are registered as a member on ' . htmlspecialchars($site_name) . '. If you wish to unsubscribe, please update your dashboard preferences.</p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="background-color: #edf2f7; padding: 24px 32px; color: #718096; font-size: 12px; border-top: 1px solid #e2e8f0;">
                        &copy; ' . date('Y') . ' ' . htmlspecialchars($site_name) . '. All rights reserved.
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
        
        send_custom_email($to, $subject, $message);
    }
    return true;
}

/**
 * Socket-based lightweight SMTP client
 */
function send_email_via_smtp($to, $subject, $body, $config) {
    $host = $config['host'];
    $port = intval($config['port']);
    $secure = strtolower($config['secure']);
    $user = $config['user'];
    $pass = $config['pass'];
    $from_email = $config['from_email'];
    $from_name = $config['from_name'];

    $socket_host = $host;
    if ($secure === 'ssl') {
        $socket_host = 'ssl://' . $host;
    }

    $socket = @fsockopen($socket_host, $port, $errno, $errstr, 10);
    if (!$socket) {
        error_log("SMTP Connection Error: $errstr ($errno) to $socket_host:$port");
        return false;
    }

    $read = function($socket, $expected_response) {
        $server_response = '';
        while (substr($server_response, 3, 1) !== ' ') {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $server_response .= $line;
        }
        if (intval(substr($server_response, 0, 3)) !== $expected_response) {
            error_log("SMTP Server Error: Expected $expected_response, got: $server_response");
            return false;
        }
        return true;
    };

    $write = function($socket, $cmd) {
        fputs($socket, $cmd . "\r\n");
    };

    if (!$read($socket, 220)) { fclose($socket); return false; }

    $helo_host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $write($socket, "EHLO " . $helo_host);
    if (!$read($socket, 250)) {
        $write($socket, "HELO " . $helo_host);
        if (!$read($socket, 250)) { fclose($socket); return false; }
    }

    if ($secure === 'tls') {
        $write($socket, "STARTTLS");
        if (!$read($socket, 220)) { fclose($socket); return false; }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log("SMTP TLS initialization failed");
            fclose($socket);
            return false;
        }
        $write($socket, "EHLO " . $helo_host);
        if (!$read($socket, 250)) { fclose($socket); return false; }
    }

    if (!empty($user) && !empty($pass)) {
        $write($socket, "AUTH LOGIN");
        if (!$read($socket, 334)) { fclose($socket); return false; }
        $write($socket, base64_encode($user));
        if (!$read($socket, 334)) { fclose($socket); return false; }
        $write($socket, base64_encode($pass));
        if (!$read($socket, 235)) { fclose($socket); return false; }
    }

    $write($socket, "MAIL FROM: <$from_email>");
    if (!$read($socket, 250)) { fclose($socket); return false; }

    $write($socket, "RCPT TO: <$to>");
    if (!$read($socket, 250)) { fclose($socket); return false; }

    $write($socket, "DATA");
    if (!$read($socket, 354)) { fclose($socket); return false; }

    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from_email>",
        "To: <$to>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "Date: " . date('r'),
        "Content-Transfer-Encoding: 8bit"
    ];

    $write($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
    if (!$read($socket, 250)) { fclose($socket); return false; }

    $write($socket, "QUIT");
    fclose($socket);
    return true;
}

/**
 * Send custom email using SMTP if enabled, otherwise fallback to php mail()
 */
function send_custom_email($to_email, $subject, $body) {
    $smtp_enabled = get_setting('smtp_enabled', '0');
    $smtp_host = get_setting('smtp_host', '');
    $smtp_port = get_setting('smtp_port', '25');
    $smtp_secure = get_setting('smtp_secure', 'none'); // 'ssl', 'tls', 'none'
    $smtp_user = get_setting('smtp_user', '');
    $smtp_pass = get_setting('smtp_pass', '');
    $default_from_email = 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $smtp_from_email = get_setting('smtp_from_email', $default_from_email);
    if (empty($smtp_from_email)) {
        $smtp_from_email = $default_from_email;
    }
    $smtp_from_name = get_setting('smtp_from_name', get_platform_name());
    if (empty($smtp_from_name)) {
        $smtp_from_name = get_platform_name();
    }

    if ($smtp_enabled === '1' && !empty($smtp_host)) {
        $config = [
            'host' => $smtp_host,
            'port' => $smtp_port,
            'secure' => $smtp_secure,
            'user' => $smtp_user,
            'pass' => $smtp_pass,
            'from_email' => $smtp_from_email,
            'from_name' => $smtp_from_name
        ];
        $success = send_email_via_smtp($to_email, $subject, $body, $config);
        if ($success) {
            return true;
        }
    }

    // Fallback to PHP native mail
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $smtp_from_name . " <" . $smtp_from_email . ">" . "\r\n";
    return @mail($to_email, $subject, $body, $headers);
}

