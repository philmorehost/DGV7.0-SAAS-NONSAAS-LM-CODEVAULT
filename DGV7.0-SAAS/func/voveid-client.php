<?php
/**
 * VoveID PHP Client for DGV7.0
 * Integrates with VoveID API for KYC/ID Verification
 * 
 * API Docs: https://docs.voveid.com/docs
 * Base URLs: https://api.voveid.com or https://api.voveid.net
 */

class VoveIDClient {
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $connectTimeout;
    
    // VoveID Step IDs
    public const STEP_ID_DOCUMENT = 'ID_DOCUMENT';
    public const STEP_DRIVING_LICENSE = 'DRIVING_LICENSE';
    public const STEP_CAR_REGISTRATION = 'CAR_REGISTRATION_DOCUMENT';
    public const STEP_ADDRESS_PROOF = 'ADDRESS_PROOF';
    public const STEP_LIVENESS = 'LIVENESS';
    
    // Verification Statuses
    public const STATUS_SUCCESS = 'successful';
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';
    
    public function __construct(string $apiKey, string $environment = 'production', int $timeout = 10, int $connectTimeout = 5) {
        $this->apiKey = $apiKey;
        $this->baseUrl = ($environment === 'sandbox') ? 'https://api.voveid.net' : 'https://api.voveid.com';
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }
    
    /**
     * Create a verification session
     * 
     * @param string $refId User ID in your system (mandatory)
     * @param string|null $flowId Custom verification flow ID
     * @param bool $forceCreation Force new session even if active exists
     * @param array|null $user Optional user data (firstName, lastName, gender, dateOfBirth)
     * @return array Response with success, token, sessionId
     */
    public function createSession(string $refId, ?string $flowId = null, bool $forceCreation = false, ?array $user = null): array {
        $payload = [
            'refId' => $refId,
            'forceCreation' => $forceCreation,
        ];
        
        if ($flowId) {
            $payload['flowId'] = $flowId;
        }
        
        if ($user) {
            $payload['user'] = $user;
        }
        
        return $this->request('POST', '/v2/sessions', $payload);
    }
    
    /**
     * Get user verification session details
     * 
     * @param string $refId User ID in your system
     * @return array User verification session data
     */
    public function getUserVerification(string $refId): array {
        return $this->request('GET', "/v2/users/{$refId}");
    }
    
    /**
     * Get user verification documents
     * 
     * @param string $refId User ID in your system
     * @return array User verification documents
     */
    public function getUserDocuments(string $refId): array {
        return $this->request('GET', "/v2/users/{$refId}/documents");
    }
    
    /**
     * Get user verification selfie
     * 
     * @param string $refId User ID in your system
     * @return array User selfie data
     */
    public function getUserSelfie(string $refId): array {
        return $this->request('GET', "/v2/users/{$refId}/selfie");
    }
    
    /**
     * Make HTTP request to VoveID API
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request payload
     * @return array Response
     */
    private function request(string $method, string $endpoint, ?array $data = null): array {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return [
                'success' => false,
                'error' => 'cURL error: ' . $curlError,
                'http_code' => 0,
            ];
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response: ' . json_last_error_msg(),
                'raw_response' => $response,
                'http_code' => $httpCode,
            ];
        }
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $decoded,
        ];
    }
    
    /**
     * Verify webhook signature (if VoveID provides signature verification)
     * 
     * @param string $payload Raw webhook payload
     * @param string $signature Signature header
     * @return bool
     */
    public function verifyWebhook(string $payload, string $signature): bool {
        // VoveID webhook verification would go here
        // Implementation depends on VoveID's webhook signing method
        return true;
    }
    
    /**
     * Parse webhook payload
     * 
     * @param string $payload Raw webhook payload
     * @return array Parsed webhook data
     */
    public function parseWebhook(string $payload): array {
        $data = json_decode($payload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid webhook JSON',
            ];
        }
        
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}

/**
 * Helper function to get VoveID client instance
 * 
 * @param int $vendor_id Vendor ID
 * @return VoveIDClient|null
 */
function voveid_get_client(int $vendor_id): ?VoveIDClient {
    global $connection_server;
    
    if (!$connection_server) return null;
    
    // Get VoveID settings from vendor settings
    $q = mysqli_query($connection_server, "
        SELECT option_value FROM sas_vendor_settings 
        WHERE vendor_id='$vendor_id' AND option_name IN ('voveid_api_key', 'voveid_environment', 'voveid_flow_id')
    ");
    
    $settings = [];
    while ($r = mysqli_fetch_assoc($q)) {
        $settings[$r['option_name']] = $r['option_value'];
    }
    
    if (empty($settings['voveid_api_key'])) {
        return null;
    }
    
    $environment = $settings['voveid_environment'] ?? 'production';
    $apiKey = $settings['voveid_api_key'];
    
    return new VoveIDClient($apiKey, $environment);
}

/**
 * Create VoveID session for a user
 * 
 * @param int $vendor_id Vendor ID
 * @param int $user_id User ID
 * @param string $refId User reference ID (can be user_id or UUID)
 * @return array Response with session token
 */
function voveid_create_session(int $vendor_id, int $user_id, string $refId): array {
    $client = voveid_get_client($vendor_id);
    
    if (!$client) {
        return ['success' => false, 'error' => 'VoveID not configured for this vendor'];
    }
    
    // Get user details for the session
    global $connection_server;
    $user_q = mysqli_query($connection_server, "SELECT firstname, lastname, email, date_of_birth, gender FROM sas_users WHERE id='$user_id' AND vendor_id='$vendor_id' LIMIT 1");
    $user = mysqli_fetch_assoc($user_q);
    
    $userData = null;
    if ($user) {
        $userData = [
            'firstName' => $user['firstname'] ?? '',
            'lastName' => $user['lastname'] ?? '',
            'email' => $user['email'] ?? '',
            'dateOfBirth' => $user['date_of_birth'] ?? '',
            'gender' => $user['gender'] ?? '',
        ];
    }
    
    // Get flow ID from settings
    $flowId = null;
    $q = mysqli_query($connection_server, "SELECT option_value FROM sas_vendor_settings WHERE vendor_id='$vendor_id' AND option_name='voveid_flow_id' LIMIT 1");
    if ($q && $r = mysqli_fetch_assoc($q)) {
        $flowId = $r['option_value'] ?: null;
    }
    
    return $client->createSession($refId, $flowId, false, $userData);
}

/**
 * Get VoveID verification status for a user
 * 
 * @param int $vendor_id Vendor ID
 * @param string $refId User reference ID
 * @return array Verification status
 */
function voveid_get_verification_status(int $vendor_id, string $refId): array {
    $client = voveid_get_client($vendor_id);
    
    if (!$client) {
        return ['success' => false, 'error' => 'VoveID not configured'];
    }
    
    return $client->getUserVerification($refId);
}

/**
 * Process VoveID webhook
 * 
 * @param array $payload Webhook payload
 * @return array Processing result
 */
function voveid_process_webhook(array $payload): array {
    global $connection_server;
    
    $refId = $payload['refId'] ?? ($payload['userId'] ?? '');
    $status = $payload['status'] ?? '';
    $sessionId = $payload['sessionId'] ?? '';
    
    if (empty($refId)) {
        return ['success' => false, 'error' => 'Missing refId in webhook'];
    }
    
    // Find user by refId (could be user_id or UUID stored in voveid_ref_id column)
    global $connection_server;
    $user_q = mysqli_query($connection_server, "SELECT id, vendor_id FROM sas_users WHERE (id='$refId' OR voveid_ref_id='$refId') LIMIT 1");
    $user = mysqli_fetch_assoc($user_q);
    
    if (!$user) {
        return ['success' => false, 'error' => 'User not found for refId: ' . $refId];
    }
    
    $vendor_id = (int)$user['vendor_id'];
    $user_id = (int)$user['id'];
    
    // Map VoveID status to our KYC status
    $kyc_status_map = [
        'successful' => 2,    // Verified
        'pending' => 1,       // Under Review
        'in_progress' => 1,   // Under Review
        'failed' => 3,        // Rejected
        'canceled' => 0,      // Unverified
    ];
    
    $new_kyc_status = $kyc_status_map[$status] ?? 0;
    
    // Update user KYC status
    mysqli_query($connection_server, "UPDATE sas_users SET kyc_status='$new_kyc_status', voveid_session_id='$sessionId', voveid_last_webhook=NOW() WHERE id='$user_id'");
    
    // If verified, fetch and store detailed verification data
    if ($new_kyc_status == 2) {
        $client = voveid_get_client($vendor_id);
        if ($client) {
            $verification = $client->getUserVerification($refId);
            if ($verification['success'] && isset($verification['data'])) {
                $data = json_encode($verification['data']);
                mysqli_query($connection_server, "UPDATE sas_users SET voveid_verification_data='$data' WHERE id='$user_id'");
            }
        }
    }
    
    // Log webhook
    mysqli_query($connection_server, "INSERT INTO sas_voveid_webhooks (vendor_id, user_id, ref_id, session_id, status, payload, created_at) VALUES ('$vendor_id', '$user_id', '" . mysqli_real_escape_string($connection_server, $refId) . "', '" . mysqli_real_escape_string($connection_server, $sessionId) . "', '" . mysqli_real_escape_string($connection_server, $status) . "', '" . mysqli_real_escape_string($connection_server, json_encode($payload)) . "', NOW())");
    
    return ['success' => true, 'kyc_status' => $new_kyc_status];
}