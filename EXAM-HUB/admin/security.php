<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_bruteforce'])) {
        set_setting('bruteforce_username_max_fails', (int)$_POST['bruteforce_username_max_fails']);
        set_setting('bruteforce_ip_max_fails', (int)$_POST['bruteforce_ip_max_fails']);
        set_setting('bruteforce_lockout_minutes', (int)$_POST['bruteforce_lockout_minutes']);
        $success = "Security settings updated successfully.";
    } elseif (isset($_POST['ip_action'])) {
        $ip = $_POST['ip'];
        $action = $_POST['action']; // whitelist, blacklist, delete
        
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM ip_rules WHERE ip_address = ?")->execute([$ip]);
        } else {
            $pdo->prepare("INSERT INTO ip_rules (ip_address, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = ?")
                ->execute([$ip, $action, $action]);
        }
        $success = "IP rule updated.";
    }
}

// Fetch logs
$logs = $pdo->query("SELECT l.*, r.status as rule_status FROM login_logs l LEFT JOIN ip_rules r ON l.ip_address = r.ip_address ORDER BY l.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Fetch settings
$user_fails = get_setting('bruteforce_username_max_fails', 5);
$ip_fails = get_setting('bruteforce_ip_max_fails', 5);
$lockout_mins = get_setting('bruteforce_lockout_minutes', 60);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Management - EXAM-HUB Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <!-- Sidebar (Omitted for brevity in mockup, normally included via a require) -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-8">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-900 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h1 class="text-xl font-bold text-gray-800">Anti-BruteForce & Security</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8">
            <?php if (isset($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Brute Force Settings -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Brute Force Protection Settings</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="update_bruteforce" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Failures by Account (Username)</label>
                            <input type="number" name="bruteforce_username_max_fails" value="<?= htmlspecialchars($user_fails) ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Failures per IP Address</label>
                            <input type="number" name="bruteforce_ip_max_fails" value="<?= htmlspecialchars($ip_fails) ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Brute Force Protection Period / Lockout Duration (Minutes)</label>
                            <input type="number" name="bruteforce_lockout_minutes" value="<?= htmlspecialchars($lockout_mins) ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700">Save Settings</button>
                    </form>
                </div>
                
                <!-- Country Blocking Mockup -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Country Level Protection</h2>
                    <p class="text-sm text-gray-500 mb-4">Search and apply rules to specific countries. (Requires MaxMind GeoIP Database Integration)</p>
                    <div class="flex gap-2 mb-4">
                        <input type="text" placeholder="Search country name..." class="flex-1 px-3 py-2 border rounded-lg bg-gray-50">
                        <button class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium">Filter</button>
                    </div>
                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <tr class="bg-gray-50 border-b"><th class="px-4 py-2">Country</th><th class="px-4 py-2">Status</th></tr>
                            <tr class="border-b"><td class="px-4 py-2">Nigeria</td><td class="px-4 py-2 text-green-600 font-medium">Whitelisted</td></tr>
                            <tr class="border-b"><td class="px-4 py-2">Russia</td><td class="px-4 py-2 text-red-600 font-medium">Blacklisted</td></tr>
                            <tr><td class="px-4 py-2">United States</td><td class="px-4 py-2 text-gray-500">Not Specified</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Login Logs & IP Management -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-800">Login History & IP Monitoring</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-sm">
                                <th class="py-3 px-6 font-medium">IP Address</th>
                                <th class="py-3 px-6 font-medium">Username</th>
                                <th class="py-3 px-6 font-medium">Status</th>
                                <th class="py-3 px-6 font-medium">Date</th>
                                <th class="py-3 px-6 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <?php foreach($logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-6 text-gray-900 font-medium flex items-center gap-2">
                                    <?= htmlspecialchars($log['ip_address']) ?>
                                    <?php if($log['rule_status'] === 'whitelisted'): ?>
                                        <!-- King Icon for Whitelisted IPs -->
                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 014 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4a2 2 0 114 0v.5a.5.5 0 001 0V4a3 3 0 10-6 0v7.268a2 2 0 000 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 000-3.464V4a3 3 0 10-6 0v.5a.5.5 0 001 0V4z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                                    <?php elseif($log['rule_status'] === 'blacklisted'): ?>
                                        <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">Blocked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6 text-gray-600"><?= htmlspecialchars($log['username']) ?></td>
                                <td class="py-3 px-6">
                                    <?php if($log['status'] === 'success'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Success</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6 text-gray-500"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td class="py-3 px-6">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="ip_action" value="1">
                                        <input type="hidden" name="ip" value="<?= htmlspecialchars($log['ip_address']) ?>">
                                        <select name="action" onchange="this.form.submit()" class="text-xs border rounded p-1 bg-white">
                                            <option value="">Manage IP</option>
                                            <option value="whitelist">Whitelist</option>
                                            <option value="blacklist">Blacklist</option>
                                            <option value="delete">Remove Rule</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
