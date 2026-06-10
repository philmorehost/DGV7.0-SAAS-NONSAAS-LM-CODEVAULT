<?php
/**
 * web/api/app-update.php
 *
 * Returns the latest Android APK version info so the app can prompt the user
 * to update when a newer version is available.
 *
 * The admin uploads the APK to the cPanel file manager (e.g. /apk/datagifting.apk)
 * and updates the version info below.
 *
 * Response (when an update is available):
 *   {
 *     "status":       "update_available",
 *     "version_code": 2,
 *     "version_name": "1.1.0",
 *     "apk_url":      "https://yourdomain.com/apk/datagifting-1.1.0.apk",
 *     "changelog":    "Bug fixes and performance improvements."
 *   }
 *
 * Response (when the app is up to date):
 *   { "status": "up_to_date" }
 */

header("Content-Type: application/json");

// ── Update configuration ──────────────────────────────────────────────────────
// Edit these values each time you upload a new APK to cPanel.

/**
 * Increment this integer every time you publish a new APK.
 * It must be strictly greater than the versionCode in app/build.gradle
 * for the update prompt to appear.
 */
$latest_version_code = 1;

/** Human-readable version string shown to the user. */
$latest_version_name = "1.0.0";

/**
 * Direct HTTPS URL to the APK in cPanel.
 * Upload the APK via cPanel File Manager → public_html/apk/
 * and set the URL accordingly.
 */
$apk_filename = "datagifting-{$latest_version_name}.apk";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apk_url = "https://{$host}/apk/{$apk_filename}";

/** Optional – shown in the update dialog. */
$changelog = "";

// ── Logic ──────────────────────────────────────────────────────────────────────
// Only return update_available when the APK file actually exists on disk.
$apk_path = __DIR__ . "/../../apk/{$apk_filename}";

if (file_exists($apk_path)) {
    echo json_encode([
        "status"       => "update_available",
        "version_code" => $latest_version_code,
        "version_name" => $latest_version_name,
        "apk_url"      => $apk_url,
        "changelog"    => $changelog,
    ]);
} else {
    // APK not uploaded yet – treat as up to date so the app is not prompted.
    echo json_encode(["status" => "up_to_date"]);
}
