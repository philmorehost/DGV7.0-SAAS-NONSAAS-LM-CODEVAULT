<?php
/**
 * bc-gateway.php - shared helpers for upstream API gateway responses.
 *
 * A purchase is debited BEFORE its upstream gateway is called. If the gateway cannot
 * classify the upstream reply, `$api_response` stayed NULL, every status branch in the
 * purchase handler missed, NO refund branch ran and the customer was left charged for a
 * purchase that was neither delivered nor reversed. The helpers here close that hole:
 *
 *  - bc_gateway_json_decode()   decode tolerantly - a remote host with display_errors on
 *                               wraps its JSON in deprecation/notice text, which made
 *                               json_decode() fail and hid a perfectly good status.
 *  - bc_gateway_settle_purchase() force any unresolved outcome to "failed" so the
 *                               caller's refund branch always runs.
 *  - bc_gateway_log_raw_response() keep the unparseable body for diagnosis.
 *
 * Both editions (SAAS / NON-SAAS) ship an identical copy of this file.
 */

if (!function_exists('bc_gateway_json_decode')) {
    /**
     * Decode a gateway response body, tolerating noise printed around or ahead of the
     * JSON (PHP notices/deprecations, BOM, stray HTML) by the remote installation.
     *
     * @return array decoded payload, or an empty array when nothing decodable was found
     */
    function bc_gateway_json_decode($raw)
    {
        $raw = (string)$raw;

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array();
    }
}

if (!function_exists('bc_gateway_log_raw_response')) {
    /**
     * Append (a bounded copy of) a raw gateway response to logs/gateway-raw.log.
     */
    function bc_gateway_log_raw_response($raw, $context = '')
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return;
        }
        if (strlen($raw) > 1000) {
            $raw = substr($raw, 0, 1000) . '...[truncated]';
        }
        $line = "[" . date('Y-m-d H:i:s') . "] " . ($context !== '' ? $context . " " : "") . $raw . "\n";
        @file_put_contents(__DIR__ . "/../logs/gateway-raw.log", $line, FILE_APPEND);
    }
}

if (!function_exists('bc_gateway_settle_purchase')) {
    /**
     * Guarantee a purchase attempt ends in one of the three states the purchase
     * handlers understand: "successful", "pending" or "failed".
     *
     * An unrecognised/unparseable upstream reply is settled as "failed" (never left
     * NULL), so the handler's refund branch runs instead of silently keeping the money.
     * A reply the gateway already classified is never overridden.
     *
     * @return bool true when the outcome had to be forced to "failed"
     */
    function bc_gateway_settle_purchase(&$api_response, &$api_response_text, &$api_response_description, &$api_response_status, $label = '', $raw_response = '')
    {
        if (in_array($api_response, array("successful", "pending", "failed"), true)) {
            return false;
        }

        $previous = is_null($api_response) ? 'NULL' : (string)$api_response;

        $api_response = "failed";
        $api_response_text = "";
        if (trim((string)$api_response_description) === '') {
            $api_response_description = ($label !== '' ? $label : "Transaction Failed | no valid response from the upstream gateway");
        }
        $api_response_status = 3;

        bc_gateway_log_raw_response($raw_response, "UNSETTLED(" . $previous . ")");

        return true;
    }
}
