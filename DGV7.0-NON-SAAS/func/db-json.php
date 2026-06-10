<?php
/**
 * DGV6.90 — Secure Database Credentials
 * Backward compatible with PHP 7.4+
 */

// Use a plain function for all versions to avoid parser syntax errors with readonly keywords
function _bc_db_config_safe(): array {
    return [
        'server'  => getenv('DB_HOST') ?: 'localhost',
        'user'    => getenv('DB_USER') ?: 'v7pmh_vtuserver',
        'pass'    => getenv('DB_PASS') ?: 'v7pmh_vtuserver',
        'dbname'  => getenv('DB_NAME') ?: 'v7pmh_vtuserver',
        'app_env' => getenv('APP_ENV') ?: 'production',
    ]; 
}

$db_json_decode = _bc_db_config_safe();

// Legacy variables kept for strict backward compatibility
$db_json_dtls   = $db_json_decode;
$db_json_encode = json_encode($db_json_decode, JSON_THROW_ON_ERROR);