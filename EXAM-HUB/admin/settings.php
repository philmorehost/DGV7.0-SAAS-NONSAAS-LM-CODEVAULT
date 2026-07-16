<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

$pdo = get_db_connection();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'site_title', 'meta_keywords', 'meta_description', 'site_name',
        'google_client_id', 'google_client_secret',
        'vtpass_username', 'vtpass_password', 'clubkonnect_userid', 'clubkonnect_apikey',
        'naijaresultpins_token', 'payhub_public_key', 'payhub_secret_key', 'bank_name', 'bank_account_name', 'bank_account_number',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_enc',
        'analytics_code', 'contact_email', 'whatsapp_number', 'social_facebook', 'social_twitter', 'social_instagram'
    ];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            set_setting($f, $_POST[$f]);
        }
    }
    
    // Handle File Uploads
    $upload_dir = __DIR__ . '/../assets/uploads/';
    $allowed_types = ['image/png', 'image/jpeg', 'image/webp'];
    
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        if (in_array($_FILES['site_logo']['type'], $allowed_types)) {
            $ext = pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_dir . $filename)) {
                set_setting('site_logo', '/assets/uploads/' . $filename);
            }
        }
    }
    
    if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
        if (in_array($_FILES['site_favicon']['type'], $allowed_types)) {
            $ext = pathinfo($_FILES['site_favicon']['name'], PATHINFO_EXTENSION);
            $filename = 'favicon_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['site_favicon']['tmp_name'], $upload_dir . $filename)) {
                set_setting('site_favicon', '/assets/uploads/' . $filename);
            }
        }
    }

    $success = "Settings updated successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Settings - EXAM-HUB Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-8">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-900 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h1 class="text-xl font-bold text-gray-800">Global Settings</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8">
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="max-w-4xl space-y-8 pb-10">
                
                <!-- SEO & Branding -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">SEO & Branding</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Site Title</label>
                            <input type="text" name="site_title" value="<?= htmlspecialchars(get_setting('site_title') ?: 'EXAM-HUB | Buy WAEC, NECO & JAMB Result Checker PINs Instantly') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Keywords</label>
                            <input type="text" name="meta_keywords" value="<?= htmlspecialchars(get_setting('meta_keywords') ?: 'buy waec scratch card online, neco result checker pin, nabteb pins, buy jamb pin, cheap exam pins nigeria, instant result checker') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label>
                            <textarea name="meta_description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?= htmlspecialchars(get_setting('meta_description') ?: 'Fast, reliable, and secure platform to purchase WAEC, NECO, NABTEB, and JAMB result checker PINs instantly in Nigeria.') ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Logo (PNG, JPG, WEBP)</label>
                            <input type="file" name="site_logo" accept="image/png, image/jpeg, image/webp" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <?php if ($logo = get_setting('site_logo')): ?>
                                <div class="mt-2 text-sm text-gray-500">Current: <img src="<?= $logo ?>" class="h-8 inline-block ml-2 border"></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Favicon (PNG, JPG, WEBP)</label>
                            <input type="file" name="site_favicon" accept="image/png, image/jpeg, image/webp" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <?php if ($fav = get_setting('site_favicon')): ?>
                                <div class="mt-2 text-sm text-gray-500">Current: <img src="<?= $fav ?>" class="h-8 inline-block ml-2 border"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Analytics & Advanced SEO -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Analytics & Schema Data (Advanced SEO)</h2>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Analytics Tracking Code (Google Analytics, Meta Pixel)</label>
                            <textarea name="analytics_code" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg font-mono text-sm" placeholder="<script>...</script>"><?= htmlspecialchars(get_setting('analytics_code')) ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">This code will be injected right before the closing &lt;/head&gt; tag on all frontend pages.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email (For SEO Schema)</label>
                                <input type="email" name="contact_email" value="<?= htmlspecialchars(get_setting('contact_email')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="support@domain.com">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number (e.g. +234...)</label>
                                <input type="text" name="whatsapp_number" value="<?= htmlspecialchars(get_setting('whatsapp_number')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="+2348000000000">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Facebook URL</label>
                                <input type="url" name="social_facebook" value="<?= htmlspecialchars(get_setting('social_facebook')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="https://facebook.com/yourpage">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Twitter/X URL</label>
                                <input type="url" name="social_twitter" value="<?= htmlspecialchars(get_setting('social_twitter')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="https://twitter.com/yourhandle">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Instagram URL</label>
                                <input type="url" name="social_instagram" value="<?= htmlspecialchars(get_setting('social_instagram')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="https://instagram.com/yourhandle">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Authentication Settings -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b">Authentication Settings (Google)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Google Client ID</label>
                            <input type="text" name="google_client_id" value="<?= htmlspecialchars(get_setting('google_client_id')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Google Client Secret</label>
                            <input type="password" name="google_client_secret" value="<?= htmlspecialchars(get_setting('google_client_secret')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-500 bg-blue-50 p-3 rounded border border-blue-100">
                                <strong>Note:</strong> Set your Google OAuth Authorized Redirect URI to: <code class="bg-white px-2 py-1 rounded text-blue-700 font-mono"><?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" ?>/google_auth.php</code>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- API Providers (Existing) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- VTPass -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">VTPass API Configuration</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username (Email)</label>
                                <input type="text" name="vtpass_username" value="<?= htmlspecialchars(get_setting('vtpass_username')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input type="password" name="vtpass_password" value="<?= htmlspecialchars(get_setting('vtpass_password')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>

                    <!-- ClubKonnect -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">ClubKonnect API Configuration</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">User ID</label>
                                <input type="text" name="clubkonnect_userid" value="<?= htmlspecialchars(get_setting('clubkonnect_userid')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                                <input type="password" name="clubkonnect_apikey" value="<?= htmlspecialchars(get_setting('clubkonnect_apikey')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NaijaResultPins & Payhub -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">NaijaResultPins API Configuration</h2>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bearer Token</label>
                            <input type="password" name="naijaresultpins_token" value="<?= htmlspecialchars(get_setting('naijaresultpins_token')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Payhub Payment Gateway</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Public Key</label>
                                <input type="text" name="payhub_public_key" value="<?= htmlspecialchars(get_setting('payhub_public_key')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="pk_live_...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
                                <input type="password" name="payhub_secret_key" value="<?= htmlspecialchars(get_setting('payhub_secret_key')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="sk_live_...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manual Bank Details -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Manual Bank Transfer Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" value="<?= htmlspecialchars(get_setting('bank_name')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="e.g. Guarantee Trust Bank">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                            <input type="text" name="bank_account_name" value="<?= htmlspecialchars(get_setting('bank_account_name')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="e.g. Exam Hub Ltd">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                            <input type="text" name="bank_account_number" value="<?= htmlspecialchars(get_setting('bank_account_number')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="0123456789">
                        </div>
                    </div>
                </div>

                <!-- SMTP Settings -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">SMTP Email Settings</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                            <input type="text" name="smtp_host" value="<?= htmlspecialchars(get_setting('smtp_host')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="smtp.gmail.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                            <input type="text" name="smtp_port" value="<?= htmlspecialchars(get_setting('smtp_port')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="465 or 587">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                            <input type="text" name="smtp_user" value="<?= htmlspecialchars(get_setting('smtp_user')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                            <input type="password" name="smtp_pass" value="<?= htmlspecialchars(get_setting('smtp_pass')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                            <select name="smtp_enc" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="tls" <?= get_setting('smtp_enc') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= get_setting('smtp_enc') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= get_setting('smtp_enc') === 'none' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Sync Engine -->
                <div class="bg-blue-50 p-6 rounded-xl shadow-sm border border-blue-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-2">Marketing Emails (Cron Job)</h2>
                    <p class="text-sm text-gray-500 mb-4">Set up a daily cron job on your server (cPanel or VPS) to automatically send promotional marketing emails to inactive users. Point it to this script:</p>
                    <div class="bg-gray-100 p-4 rounded-lg font-mono text-sm text-gray-800 flex justify-between items-center break-all">
                        php <?= htmlspecialchars(realpath(__DIR__ . '/../cron/marketing_emails.php')) ?>
                        <button type="button" class="ml-4 text-blue-600 hover:text-blue-800" onclick="navigator.clipboard.writeText('php <?= htmlspecialchars(addslashes(realpath(__DIR__ . '/../cron/marketing_emails.php'))) ?>'); alert('Copied to clipboard!')">Copy</button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white px-6 py-4 rounded-xl font-bold hover:bg-blue-700 shadow-lg transition">Save All Settings</button>
            </form>
        </div>
    </main>
</body>
</html>
