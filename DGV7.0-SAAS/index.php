<?php
// Single source of truth for vendor data
include("func/bc-connect.php");

// Initialize vendor details
$vendor_account_details = null;
$error_message = null;

if ($connection_server) {
    $host = strtolower(trim(explode(':', $_SERVER["HTTP_HOST"])[0] ?? ''));
    $cacheKey = 'vendor_details_' . md5($host);

    if (function_exists('bc_cache_get')) {
        $vendor_account_details = bc_cache_get($cacheKey, 300);
    }

    if (!$vendor_account_details) {
        $stmt = mysqli_prepare($connection_server, "SELECT * FROM sas_vendors WHERE website_url = ? AND status = 1 LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $host);

            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($result)) {
                    $vendor_account_details = $row;
                    if (function_exists('bc_cache_set')) {
                        bc_cache_set($cacheKey, $vendor_account_details);
                    }
                } else {
                    $error_message = "No vendor found for this host.";
                }
            } else {
                $error_message = "Failed to execute vendor query.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Failed to prepare vendor query.";
        }
    }
} else {
    $error_message = "Failed to connect to the database.";
}

// Default CSS template
$css_style_template_location = "index-bc-style-template-1.php";

// If a vendor is found, check for a custom style template
if ($vendor_account_details) {
    $stmt_template = mysqli_prepare($connection_server, "SELECT template_name FROM sas_vendor_style_templates WHERE vendor_id = ?");
    if ($stmt_template) {
        mysqli_stmt_bind_param($stmt_template, "i", $vendor_account_details["id"]);
        
        if (mysqli_stmt_execute($stmt_template)) {
            $result_template = mysqli_stmt_get_result($stmt_template);
            if ($get_vendor_style_template = mysqli_fetch_assoc($result_template)) {
                $style_template_name = explode(".", trim($get_vendor_style_template["template_name"]))[0];
                if (!empty($style_template_name)) {
                    $style_template_location = "index-" . $style_template_name . ".php";
                    if (file_exists($style_template_location)) {
                        $css_style_template_location = $style_template_location;
                    }
                }
            }
        }
        
        mysqli_stmt_close($stmt_template);
    }
}

// Pass both vendor data and any error message to the template
include(__DIR__ . "/" . $css_style_template_location);
?>