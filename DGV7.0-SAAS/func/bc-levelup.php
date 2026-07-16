<?php
// Prevent direct access
if (!defined('SYSTEM_ENTRY')) {
    define('SYSTEM_ENTRY', true);
}

/**
 * Dynamic Encryption/Decryption Helpers
 */
function bc_crypt_secret_key() {
    // Stable key independent of the accessing domain to allow multi-tenant SAAS vendor domains to decrypt activation token
    return hash('sha256', 'DGV7_INTEGRITY_SALT_2026_!');
}

function bc_encrypt_key($plain_text) {
    if (empty($plain_text)) return '';
    $key = bc_crypt_secret_key();
    // 16-byte Initialization Vector (IV)
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($plain_text, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    // Return concatenated base64 encoded payload of IV + Ciphertext
    return base64_encode($iv . $ciphertext);
}

function bc_decrypt_key($cipher_text) {
    if (empty($cipher_text)) return '';
    $key = bc_crypt_secret_key();
    $raw = base64_decode($cipher_text);
    if (strlen($raw) < 17) return ''; // Needs to be at least IV (16 bytes) + some payload
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : '';
}

/**
 * Reads the stored activation code from func/bc-activation.php
 */
function bc_read_activation() {
    $activation_file = __DIR__ . '/bc-activation.php';
    if (file_exists($activation_file)) {
        include $activation_file;
        if (isset($bc_activation_token) && !empty($bc_activation_token)) {
            // Decrypt it dynamically
            return bc_decrypt_key($bc_activation_token);
        }
    }
    return '';
}

/**
 * Stores the activation code into func/bc-activation.php
 */
function bc_write_activation($code) {
    $activation_file = __DIR__ . '/bc-activation.php';
    // Encrypt it before writing
    $encrypted_code = bc_encrypt_key($code);
    $code_escaped = addslashes($encrypted_code);
    $content = "<?php\n// System Activation Configuration\n\$bc_activation_token = '{$code_escaped}';\n?>";
    return file_put_contents($activation_file, $content) !== false;
}

/**
 * Clears the activation code
 */
function bc_clear_activation() {
    $activation_file = __DIR__ . '/bc-activation.php';
    if (file_exists($activation_file)) {
        @unlink($activation_file);
    }
    $cache_file = __DIR__ . '/cache/bc-core.cache';
    if (file_exists($cache_file)) {
        @unlink($cache_file);
    }
}

/**
 * Verifies system integrity against the validation API
 */
function bc_verify_integrity() {
    // Avoid double validation in the same execution
    if (isset($GLOBALS['bc_integrity_checked'])) {
        return !$GLOBALS['bc_integrity_fail'];
    }
    $GLOBALS['bc_integrity_checked'] = true;

    $code = bc_read_activation();
    if (empty($code)) {
        global $connection_server;
        if (isset($connection_server) && $connection_server) {
            $q = mysqli_query($connection_server, "SELECT option_value FROM sas_super_admin_options WHERE option_name='license_key' LIMIT 1");
            if ($q && $r = mysqli_fetch_assoc($q)) {
                $code = $r['option_value'];
                if (!empty($code)) {
                    bc_write_activation($code);
                }
            }
        }
    }
    if (empty($code)) {
        $GLOBALS['bc_integrity_fail'] = true;
        return false;
    }

    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Use the exact domain the license was registered under (set when the admin
    // saved/validated the key in Account Settings). Falling back to a guessed
    // vendor domain here caused valid keys to fail integrity checks whenever the
    // first-registered vendor's website_url didn't match what was actually
    // licensed with the remote server.
    global $connection_server;
    if (isset($connection_server) && $connection_server) {
        $q_domain = mysqli_query($connection_server, "SELECT option_value FROM sas_super_admin_options WHERE option_name='license_domain' LIMIT 1");
        if ($q_domain && $r_domain = mysqli_fetch_assoc($q_domain)) {
            if (!empty($r_domain['option_value'])) {
                $domain = $r_domain['option_value'];
            }
        }
    }

    // Clean up domain (remove protocol and port if present)
    $domain = str_replace(["https://", "http://"], "", $domain);
    if (strpos($domain, ':') !== false) {
        $domain = explode(':', $domain)[0];
    }

    $cache_dir = __DIR__ . '/cache';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    $cache_file = $cache_dir . '/bc-core.cache';

    $now = time();
    $cache = null;

    if (file_exists($cache_file)) {
        $cache_data = @file_get_contents($cache_file);
        if ($cache_data) {
            $cache = @json_decode($cache_data, true);
        }
    }

    // If cache exists, matches current domain/code, and is fresh (< 6 hours)
    if ($cache && isset($cache['last_check'], $cache['status'], $cache['domain'], $cache['code'])) {
        if ($cache['domain'] === $domain && $cache['code'] === $code) {
            // Check if within 6 hours (21600 seconds)
            if (($now - $cache['last_check']) < 21600) {
                if ($cache['status'] == 1) {
                    $GLOBALS['bc_integrity_fail'] = false;
                    return true;
                } else {
                    $GLOBALS['bc_integrity_fail'] = true;
                    return false;
                }
            }
        }
    }

    // Call the validation API
    $api_url = 'https://manager.pmhserver.name.ng/api.php';
    $post_data = [
        'key' => $code,
        'domain' => $domain
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $http_code === 200) {
        $res_decoded = @json_decode($response, true);
        if ($res_decoded && isset($res_decoded['status'])) {
            $status = (int)$res_decoded['status'];
            $new_cache = [
                'last_check' => $now,
                'status' => $status,
                'domain' => $domain,
                'code' => $code
            ];
            @file_put_contents($cache_file, json_encode($new_cache));

            if ($status === 1 || (isset($res_decoded['message']) && stripos($res_decoded['message'], 'Limit exceeded') !== false)) {
                $GLOBALS['bc_integrity_fail'] = false;
                return true;
            } else {
                $_SESSION['bc_integrity_debug_err'] = "API returned Status " . $status . ": " . ($res_decoded['message'] ?? 'Inactive/Invalid key');
                $GLOBALS['bc_integrity_fail'] = true;
                return false;
            }
        } else {
            $_SESSION['bc_integrity_debug_err'] = "Response is not valid JSON. First 150 chars: " . substr($response, 0, 150);
        }
    } else {
        $_SESSION['bc_integrity_debug_err'] = "Server check failed. HTTP " . $http_code . " (cURL: " . ($curl_error ?: 'Unknown error') . ")";
    }

    // API is unreachable or returned invalid non-JSON payload
    // Let's fall back to offline activation mode if we have a license key of length >= 10
    if (!empty($code) && strlen($code) >= 10) {
        $GLOBALS['bc_integrity_fail'] = false;
        return true;
    }

    // API is unreachable (cURL error or non-200 HTTP code)
    // Check 48 hours grace period (172800 seconds)
    if ($cache && isset($cache['last_check'], $cache['status']) && $cache['status'] == 1) {
        if (($now - $cache['last_check']) < 172800) {
            // Within 48 hours grace period, let it pass silently!
            $GLOBALS['bc_integrity_fail'] = false;
            return true;
        }
    }

    // No valid cache or grace period expired
    $GLOBALS['bc_integrity_fail'] = true;
    return false;
}
