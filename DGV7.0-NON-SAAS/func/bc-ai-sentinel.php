<?php
/**
 * DGV6.90 AI Edition — Support Sentinel
 * Monitors failed transactions and proactively resolves them.
 */

include_once(__DIR__ . "/bc-connect.php");
include_once(__DIR__ . "/bc-whatsapp.php");

class AiSupportSentinel {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Scans for failed transactions and prepares autonomous resolution
     */
    public function monitorFailedTransactions() {
        $q = mysqli_query($this->db, "SELECT * FROM sas_transactions 
            WHERE status=3 AND date >= NOW() - INTERVAL 10 MINUTE 
            AND ai_sentinel_processed=0");
        
        while ($row = mysqli_fetch_assoc($q)) {
            $this->processFailure($row);
        }
    }

    private function processFailure($row) {
        $ref = $row['reference'];
        $username = $row['username'];
        $amount = $row['amount'];
        $description = $row['description'];
        
        // 1. Verify if user was refunded
        $refund_q = mysqli_query($this->db, "SELECT * FROM sas_transactions 
            WHERE username='$username' AND type_alternative='refund' 
            AND description LIKE '%$ref%' LIMIT 1");
        $is_refunded = mysqli_num_rows($refund_q) > 0;

        // 2. Get User Phone
        $u_q = mysqli_query($this->db, "SELECT phone FROM sas_users WHERE username='$username' LIMIT 1");
        $user = mysqli_fetch_assoc($u_q);
        
        if ($user && !empty($user['phone'])) {
            $msg = "🤖 *AI Support Sentinel*\n\n"
                 . "I noticed your transaction *" . $description . "* failed.\n\n"
                 . ($is_refunded ? "✅ *Status:* Your wallet has been automatically refunded.\n" : "⚠️ *Status:* Refund is processing.\n")
                 . "\n🚀 *Auto-Retry?* I can retry this using a backup route for you. Reply 'RETRY' to proceed or log in to choose a different plan.\n\n"
                 . "_— Resolving issues before you even ask!_";
            
            sendWhatsAppAlert($user['phone'], $msg, 'support');
        }

        // Mark as processed
        mysqli_query($this->db, "UPDATE sas_transactions SET ai_sentinel_processed=1 WHERE id=".$row['id']);
    }

    /**
     * High-Value Failure Alert for Admin
     */
    public function alertAdmin($row) {
        if ($row['amount'] >= 5000) { // Configurable threshold
            $msg = "🚨 *High-Value Failure Alert*\n\n"
                 . "User: " . $row['username'] . "\n"
                 . "Amount: ₦" . number_format($row['amount'], 2) . "\n"
                 . "Service: " . $row['description'] . "\n"
                 . "Ref: " . $row['reference'] . "\n\n"
                 . "AI Sentinel is currently monitoring this user.";
            
            // Send to Admin
            $admin_q = mysqli_query($this->db, "SELECT phone_number FROM sas_vendors WHERE id=".$row['vendor_id']);
            $admin = mysqli_fetch_assoc($admin_q);
            if ($admin) sendWhatsAppAlert($admin['phone_number'], $msg, 'admin_alert');
        }
    }
}

/**
 * Procedural Hook for UI Evaluation (used in bc-admin controllers)
 */
function ai_sentinel_evaluate($username, $vendor_id, $api_type, $amount) {
    global $connection_server;

    // 1. Check spending anomaly
    require_once(__DIR__ . "/bc-security.php");
    if (bc_check_spending_anomaly($username, $amount)) {
        return 'BLOCK';
    }

    // 2. Check Trust Score
    $score = bc_calculate_user_trust_score($username);
    if ($score < 20) {
        return 'BLOCK';
    }

    if ($score < 40) {
        return 'FLAG_FOR_APPROVAL';
    }

    return 'ALLOW';
}
