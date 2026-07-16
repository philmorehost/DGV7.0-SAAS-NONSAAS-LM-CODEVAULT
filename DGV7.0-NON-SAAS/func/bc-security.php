<?php
/**
 * bc-security.php — DGV6.90 AI Edition
 * Centralized Security Utilities
 *
 * Provides: CSRF tokens, rate limiting, input sanitization,
 * atomic wallet transaction helpers, and security audit logging.
 *
 * GOLDEN RULE: This file must be included ONCE via bc-connect.php
 * before any page logic runs.
 */

if (function_exists('bc_generate_csrf_token')) return; // Guard against double-include

// ─────────────────────────────────────────────────────────────
// 1. CSRF TOKEN SYSTEM
// ─────────────────────────────────────────────────────────────

/**
 * Generates (or returns existing) CSRF token for the current session.
 * Rotates every 2 hours for long-lived sessions.
 */
function bc_generate_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $now = time();
    $rotate_interval = 7200; // 2 hours

    if (
        empty($_SESSION['_csrf_token']) ||
        empty($_SESSION['_csrf_token_time']) ||
        ($now - $_SESSION['_csrf_token_time']) > $rotate_interval
    ) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token_time'] = $now;
    }

    return $_SESSION['_csrf_token'];
}

/**
 * Returns an HTML hidden input field with the CSRF token.
 * Usage in forms: <?php echo bc_csrf_field(); ?>
 */
function bc_csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(bc_generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validates CSRF token from POST. Dies with JSON error on failure.
 * Call at the top of any POST handler.
 *
 * @param bool $json_response If true, return JSON error. If false, die with plain text.
 */
function bc_validate_csrf(bool $json_response = false): void {
    $submitted = $_POST['_csrf_token'] ?? '';
    $expected  = $_SESSION['_csrf_token'] ?? '';

    if (empty($submitted) || empty($expected) || !hash_equals($expected, $submitted)) {
        if ($json_response) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh the page and try again.']);
            exit;
        }
        // For standard form submissions, redirect back with error
        $_SESSION['product_purchase_response'] = 'Security validation failed. Please try again.';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $referrer);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
// 2. RATE LIMITING
// ─────────────────────────────────────────────────────────────

/**
 * Checks if an action is rate-limited. Returns true if the request should be BLOCKED.
 *
 * @param string $action   A descriptive key (e.g. 'login', 'ai_request', 'otp_verify')
 * @param string $key      Unique identifier for the actor (IP address or username)
 * @param int    $max      Maximum allowed attempts in the window
 * @param int    $window   Time window in seconds
 */
function bc_is_rate_limited(string $action, string $key, int $max = 5, int $window = 60): bool {
    global $connection_server;
    if (!$connection_server) return false;

    $action_esc = mysqli_real_escape_string($connection_server, $action);
    $key_esc    = mysqli_real_escape_string($connection_server, $key);
    $now        = time();
    $window_start = $now - $window;

    // Count recent attempts
    $count_q = mysqli_query($connection_server,
        "SELECT COUNT(*) as cnt FROM sas_rate_limits
         WHERE action='$action_esc' AND rate_key='$key_esc' AND attempted_at > FROM_UNIXTIME($window_start)"
    );
    $row = $count_q ? mysqli_fetch_assoc($count_q) : null;
    $count = $row ? (int)$row['cnt'] : 0;

    if ($count >= $max) {
        bc_log_security_event('RATE_LIMIT_HIT', $action, $key, "Count: $count / $max in {$window}s");
        return true;
    }

    // Record this attempt
    mysqli_query($connection_server,
        "INSERT INTO sas_rate_limits (action, rate_key, attempted_at) VALUES ('$action_esc', '$key_esc', NOW())"
    );

    return false;
}

/**
 * Cleans up old rate limit records (call from cron or occasionally).
 */
function bc_cleanup_rate_limits(int $older_than_seconds = 3600): void {
    global $connection_server;
    if (!$connection_server) return;
    mysqli_query($connection_server,
        "DELETE FROM sas_rate_limits WHERE attempted_at < DATE_SUB(NOW(), INTERVAL $older_than_seconds SECOND)"
    );
}

// ─────────────────────────────────────────────────────────────
// 3. INPUT SANITIZATION
// ─────────────────────────────────────────────────────────────

/**
 * Sanitize a string for safe HTML output and basic injection prevention.
 * Use for general user-supplied data that will be displayed.
 */
function bc_sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitize a numeric value. Returns 0 if not numeric.
 * @param mixed $input
 */
function bc_sanitize_number($input, int $decimals = 2): float {
    $cleaned = preg_replace('/[^0-9.]/', '', (string)$input);
    return is_numeric($cleaned) ? round((float)$cleaned, $decimals) : 0.0;
}

/**
 * Sanitize a phone number to Nigerian 11-digit format.
 * Accepts: 08012345678, 2348012345678, +2348012345678
 */
function bc_sanitize_phone(string $phone): string {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (substr($cleaned, 0, 3) === '234' && strlen($cleaned) >= 13) {
        $cleaned = '0' . substr($cleaned, 3);
    }
    if (substr($cleaned, 0, 1) === '+') {
        $cleaned = '0' . substr($cleaned, 4);
    }
    return $cleaned;
}

/**
 * Sanitize a string for use as an SQL LIKE pattern (escapes %, _, \)
 */
function bc_sanitize_like(string $input): string {
    return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $input);
}

// ─────────────────────────────────────────────────────────────
// 4. SECURITY COOKIE HELPERS
// ─────────────────────────────────────────────────────────────

/**
 * Store a security answer as a server-side hash in the session
 * instead of raw value in a cookie.
 *
 * This replaces the legacy cookie comparison vulnerability.
 */
function bc_set_security_answer_session(string $answer): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['_security_answer_hash'] = password_hash(strtolower(trim($answer)), PASSWORD_DEFAULT);
}

/**
 * Verify the submitted security answer against the session hash.
 */
function bc_verify_security_answer(string $submitted_answer): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $hash = $_SESSION['_security_answer_hash'] ?? '';
    if (empty($hash)) return false;
    return password_verify(strtolower(trim($submitted_answer)), $hash);
}

// ─────────────────────────────────────────────────────────────
// 5. ATOMIC WALLET TRANSACTION HELPERS
// ─────────────────────────────────────────────────────────────

/**
 * Safely debit a USER wallet using a MySQL transaction with row-level locking.
 * This prevents race conditions when concurrent requests hit chargeUser().
 *
 * Returns: 'success', 'insufficient_balance', or 'failed'
 *
 * USAGE: Called internally by chargeUser() for debit operations.
 */
function bc_atomic_debit_user(int $vendor_id, string $username, float $amount, string $reference): string {
    global $connection_server;
    if (!$connection_server || $amount <= 0 || $vendor_id <= 0 || empty($username)) return 'failed';

    $esc_user   = mysqli_real_escape_string($connection_server, $username);
    $esc_ref    = mysqli_real_escape_string($connection_server, $reference);
    $safe_vid   = (int)$vendor_id;
    $safe_amt   = (float)$amount;

    // Begin MySQL transaction
    mysqli_begin_transaction($connection_server);

    try {
        // Lock the specific user row to prevent concurrent debits
        $lock_q = mysqli_query($connection_server,
            "SELECT id, balance, status FROM sas_users
             WHERE vendor_id='$safe_vid' AND username='$esc_user'
             LIMIT 1 FOR UPDATE"
        );

        if (!$lock_q) {
            throw new Exception('Lock query failed');
        }

        $user = mysqli_fetch_assoc($lock_q);

        if (!$user || $user['status'] != 1) {
            throw new Exception('User not found or inactive');
        }

        $current_balance = (float)$user['balance'];

        if ($current_balance < $safe_amt) {
            mysqli_rollback($connection_server);
            return 'insufficient_balance';
        }

        $new_balance = $current_balance - $safe_amt;
        $user_id     = (int)$user['id'];

        // Update balance atomically
        $update_q = mysqli_query($connection_server,
            "UPDATE sas_users SET balance='$new_balance'
             WHERE id='$user_id' AND balance='$current_balance'"
        );

        if (!$update_q || mysqli_affected_rows($connection_server) !== 1) {
            // Another concurrent request modified the balance — rollback
            throw new Exception('Concurrent modification detected');
        }

        mysqli_commit($connection_server);
        return 'success';

    } catch (Exception $e) {
        mysqli_rollback($connection_server);
        bc_log_security_event('ATOMIC_DEBIT_FAILED', 'wallet_debit', "$vendor_id:$username", $e->getMessage());
        return 'failed';
    }
}

/**
 * Safely debit a VENDOR wallet using a MySQL transaction with row-level locking.
 * Returns: 'success', 'insufficient_balance', or 'failed'
 */
function bc_atomic_debit_vendor(int $vendor_id, float $amount, string $reference): string {
    global $connection_server;
    if (!$connection_server || $amount <= 0 || $vendor_id <= 0) return 'failed';

    $safe_vid = (int)$vendor_id;
    $safe_amt = (float)$amount;
    $esc_ref  = mysqli_real_escape_string($connection_server, $reference);

    mysqli_begin_transaction($connection_server);

    try {
        $lock_q = mysqli_query($connection_server,
            "SELECT id, balance FROM sas_vendors WHERE id='$safe_vid' LIMIT 1 FOR UPDATE"
        );

        if (!$lock_q) throw new Exception('Vendor lock failed');
        $vendor = mysqli_fetch_assoc($lock_q);
        if (!$vendor) throw new Exception('Vendor not found');

        $current_balance = (float)$vendor['balance'];
        if ($current_balance < $safe_amt) {
            mysqli_rollback($connection_server);
            return 'insufficient_balance';
        }

        $new_balance = $current_balance - $safe_amt;

        $upd = mysqli_query($connection_server,
            "UPDATE sas_vendors SET balance='$new_balance'
             WHERE id='$safe_vid' AND balance='$current_balance'"
        );

        if (!$upd || mysqli_affected_rows($connection_server) !== 1) {
            throw new Exception('Vendor concurrent modification');
        }

        mysqli_commit($connection_server);
        return 'success';

    } catch (Exception $e) {
        mysqli_rollback($connection_server);
        bc_log_security_event('VENDOR_DEBIT_FAILED', 'wallet_debit', "vendor:$vendor_id", $e->getMessage());
        return 'failed';
    }
}

// ─────────────────────────────────────────────────────────────
// 6. SECURITY AUDIT LOG
// ─────────────────────────────────────────────────────────────

/**
 * Logs a security event to sas_ai_audit_log.
 * Non-blocking — silently fails if table isn't ready yet.
 *
 * @param string $event_type  e.g. 'RATE_LIMIT_HIT', 'CSRF_FAIL', 'ATOMIC_DEBIT_FAILED'
 * @param string $action      The specific action being performed
 * @param string $actor       The user/IP involved
 * @param string $detail      Additional context
 */
function bc_log_security_event(string $event_type, string $action, string $actor, string $detail = ''): void {
    global $connection_server;
    if (!$connection_server) return;

    $esc_type   = mysqli_real_escape_string($connection_server, substr($event_type, 0, 50));
    $esc_action = mysqli_real_escape_string($connection_server, substr($action, 0, 100));
    $esc_actor  = mysqli_real_escape_string($connection_server, substr($actor, 0, 100));
    $esc_detail = mysqli_real_escape_string($connection_server, substr($detail, 0, 500));
    $ip         = mysqli_real_escape_string($connection_server, $_SERVER['REMOTE_ADDR'] ?? 'cli');

    @mysqli_query($connection_server,
        "INSERT INTO sas_ai_audit_log (event_type, action, actor, detail, ip_address, created_at)
         VALUES ('$esc_type', '$esc_action', '$esc_actor', '$esc_detail', '$ip', NOW())"
    );
}

// ─────────────────────────────────────────────────────────────
// 7. AI PROMPT FIREWALL
// ─────────────────────────────────────────────────────────────

/**
 * Sanitizes and validates a user-submitted AI prompt.
 * Returns: sanitized prompt string, or FALSE if the prompt is malicious/invalid.
 *
 * @param string $raw_prompt
 * @param bool $strict_mode
 * @param array $context
 * @return string|false
 */
function bc_firewall_prompt($raw_prompt, $strict_mode = false, $context = []) {
    // Strip tags and dangerous chars
    $prompt = strip_tags(trim($raw_prompt));
    $prompt = preg_replace('/[\x00-\x1F\x7F]/', '', $prompt); // Remove control chars

    if (strlen($prompt) < 1 || strlen($prompt) > 2000) {
        return false;
    }

    // Blocked patterns — things that could be injection or misuse
    $blocked_patterns = [
        '/SELECT\s+.+\s+FROM/i',           // SQL SELECT
        '/INSERT\s+INTO/i',                 // SQL INSERT
        '/UPDATE\s+.+\s+SET/i',            // SQL UPDATE
        '/DELETE\s+FROM/i',                 // SQL DELETE
        '/DROP\s+TABLE/i',                  // SQL DROP
        '/<\?php/i',                        // PHP injection
        '/eval\s*\(/i',                     // eval()
        '/exec\s*\(/i',                     // exec()
        '/shell_exec/i',                    // shell commands
        '/transfer\s+money/i',              // Attempt to move funds via AI
        '/move\s+funds/i',
        '/withdraw\s+from/i',
        '/send\s+\d+\s+to\s+account/i',
    ];

    foreach ($blocked_patterns as $pattern) {
        if (preg_match($pattern, $prompt)) {
            bc_log_security_event('PROMPT_BLOCKED', 'ai_firewall', 'user', substr($prompt, 0, 100));
            return false;
        }
    }

    // Prepend the system context to keep AI on-topic
    $system_context = "You are a professional Nigerian VTU assistant. Help with airtime, data, electricity, cable TV, exam pins, and betting. "
                    . "IMPORTANT: You CANNOT execute transactions yourself. If the user wants to buy something, summarize their request clearly. "
                    . "For ALL networks (MTN, Airtel, Glo, 9mobile), ensure you extract the network name and data type correctly. "
                    . "If the user is vague about a data plan or makes a mistake, list all available bundles, prices, and durations from the 'Current Data Prices' context provided, and ask them to pick one. "
                    . "For non-MTN networks, pay extra attention to the plan names in the context to avoid failures. "
                    . "Once a valid plan is identified, ALWAYS ask: 'Shall I go ahead and process this for you? Type Yes to confirm.' "
                    . "Keep your responses concise and direct. "
                    . "NEVER claim that a transaction is complete or that you have sent money. ";

    // Inject Smart Context if available
    if (!empty($context)) {
        $ctx_parts = [];
        if (isset($context['page'])) $ctx_parts[] = "Current Page: " . $context['page'];
        if (isset($context['wallet_balance'])) $ctx_parts[] = "Balance: ₦" . number_format($context['wallet_balance'], 2);
        
        // Include Successful History for "Faster Processing"
        if (!empty($context['recent_history']) && is_array($context['recent_history'])) {
            $ctx_parts[] = "Frequent/Recent Purchases: " . implode(" | ", $context['recent_history']);
        }
        
        if (!empty($context['last_fail_reason'])) $ctx_parts[] = "Last Error: " . $context['last_fail_reason'];
        
        // Include Current Data Prices to avoid ₦0 estimates
        if (!empty($context['current_data_prices']) && is_array($context['current_data_prices'])) {
            $ctx_parts[] = "Current Data Prices: " . implode(" | ", $context['current_data_prices']);
        }

        if (!empty($ctx_parts)) {
            $system_context .= " [Context: " . implode(", ", $ctx_parts) . "] ";
        }
    }

    $system_context .= "\nUser request: ";

    return $system_context . $prompt;
}

// ─────────────────────────────────────────────────────────────
// 8. SECURITY SENTINEL: TRUST SCORING
// ─────────────────────────────────────────────────────────────

/**
 * Calculates a dynamic Trust Score (0-100) for a user.
 * 
 * Factors:
 * - Success Rate (Success / Total Tx)
 * - Account Age (Days since registration)
 * - Total Volume (Total successful Naira spent)
 * 
 * @param string $username
 * @return int 0-100
 */
function bc_calculate_user_trust_score(string $username): int {
    global $connection_server;
    if (!$connection_server) return 50;

    $user_esc = mysqli_real_escape_string($connection_server, $username);
    
    // 1. Fetch user data
    $u_q = mysqli_query($connection_server, "SELECT id, created_at, trust_score FROM sas_users WHERE username='$user_esc' LIMIT 1");
    $user = mysqli_fetch_assoc($u_q);
    if (!$user) return 0;

    // 2. Base score from existing (or default)
    $score = (int)$user['trust_score'];

    // 3. Success Rate
    $tx_q = mysqli_query($connection_server, "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) as success
        FROM sas_transactions WHERE username='$user_esc'");
    $stats = mysqli_fetch_assoc($tx_q);
    
    if ($stats && $stats['total'] > 5) {
        $rate = ($stats['success'] / $stats['total']) * 100;
        if ($rate > 90) $score += 10;
        elseif ($rate < 50) $score -= 20;
    }

    // 4. Account Age
    $age_days = (time() - strtotime($user['created_at'])) / 86400;
    if ($age_days > 365) $score += 15;
    elseif ($age_days > 90) $score += 5;
    elseif ($age_days < 7) $score -= 10;

    // 5. Constraints
    $score = max(0, min(100, $score));

    // 6. Persist
    mysqli_query($connection_server, "UPDATE sas_users SET trust_score='$score', last_trust_update=NOW() WHERE username='$user_esc'");

    return $score;
}

/**
 * Trigger a High-Alert email notification for the Super Admin.
 */
function bc_trigger_high_alert(string $event, string $detail): void {
    global $connection_server;

    // Get admin email
    $q = mysqli_query($connection_server, "SELECT option_value FROM sas_super_admin_options WHERE option_name='admin_email' LIMIT 1");
    $row = mysqli_fetch_assoc($q);
    $email = $row['option_value'] ?? '';

    if (empty($email)) return;

    $subject = "High Alert: $event";
    $body = "<b>Event:</b> $event<br><b>Detail:</b> $detail<br><b>Time:</b> " . date('H:i:s') . "<br><br>Please review in the admin panel.";

    sendSuperAdminEmail($email, $subject, $body);
}

/**
 * Checks for spending anomalies by comparing current amount to user history.
 * If anomalous, triggers a High-Alert notification.
 *
 * @param string $username
 * @param float  $amount
 * @return bool True if anomalous (High Risk)
 */
function bc_check_spending_anomaly(string $username, float $amount): bool {
    global $connection_server;
    if (!$connection_server || $amount <= 0) return false;

    $user_esc = mysqli_real_escape_string($connection_server, $username);

    // Get average spend of last 10 successful transactions
    $avg_q = mysqli_query($connection_server, "SELECT AVG(discounted_amount) as avg_amt FROM sas_transactions WHERE username='$user_esc' AND status=1 ORDER BY created_at DESC LIMIT 10");
    $avg_row = mysqli_fetch_assoc($avg_q);
    $avg_amt = (float)($avg_row['avg_amt'] ?? 0);

    // If this amount is > 10x their average AND > 2,000 Naira
    if ($avg_amt > 0 && $amount > ($avg_amt * 10) && $amount > 2000) {
        bc_trigger_high_alert("SPENDING_ANOMALY", "User $username just attempted a transaction of ₦" . number_format($amount, 2) . ". Their average spend is ₦" . number_format($avg_amt, 2) . ".");
        return true;
    }

    return false;
}


