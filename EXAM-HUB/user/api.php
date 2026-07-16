<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$pdo = get_db_connection();
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_api'])) {
    $domain = $_POST['domain_name'] ?? '';
    
    if (empty($domain)) {
        $error = "Domain name is required.";
    } elseif (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid domain name (e.g. example.com).";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM api_access WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            $error = "You have already requested API access.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO api_access (user_id, domain_name) VALUES (?, ?)");
            $stmt->execute([$user_id, $domain]);
            $success = "API Access requested successfully. Please wait for admin approval.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM api_access WHERE user_id = ?");
$stmt->execute([$user_id]);
$api_access = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="glass p-4 sm:p-8 rounded-3xl shadow-xl">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-8 border-b pb-4">API Access</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!$api_access): ?>
            <div class="bg-blue-50 p-4 sm:p-6 rounded-xl border border-blue-100 mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-2">Request Developer API Key</h2>
                <p class="text-gray-700 mb-4">Integrate EXAM-HUB directly into your own application or website. You must provide the exact domain where the API will be used. Admin will manually review your domain before approving.</p>
                
                <form method="POST" class="flex flex-col sm:flex-row gap-4">
                    <input type="hidden" name="request_api" value="1">
                    <input type="text" name="domain_name" placeholder="e.g. yourwebsite.com" required
                           class="flex-1 w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-6 sm:px-8 rounded-xl hover:bg-blue-700 transition shadow-lg w-full sm:w-auto">
                        Submit Request
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-white p-4 sm:p-6 rounded-xl border border-gray-200 mb-8 shadow-sm">
                <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Your API Details</h2>
                    <?php if ($api_access['status'] === 'pending'): ?>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs sm:text-sm font-bold rounded-full">Pending Approval</span>
                    <?php elseif ($api_access['status'] === 'approved'): ?>
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs sm:text-sm font-bold rounded-full">Active</span>
                    <?php else: ?>
                        <span class="px-3 py-1 bg-red-100 text-red-800 text-xs sm:text-sm font-bold rounded-full">Rejected</span>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 font-semibold mb-1">Registered Domain</p>
                        <p class="font-medium text-gray-900 break-all"><?= htmlspecialchars($api_access['domain_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-semibold mb-1">Assigned Discount</p>
                        <?php if ($api_access['status'] === 'approved'): ?>
                            <p class="font-medium text-gray-900">
                                <?= $api_access['discount_type'] === 'percentage' ? floatval($api_access['discount_value']) . '%' : '₦' . number_format($api_access['discount_value'], 2) ?> off
                            </p>
                        <?php else: ?>
                            <p class="text-gray-400">N/A</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($api_access['status'] === 'approved'): ?>
                    <div class="mt-8">
                        <p class="text-sm text-gray-500 font-semibold mb-1">Your API Key (Keep Secret)</p>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="<?= htmlspecialchars($api_access['api_key']) ?>" class="w-full px-3 sm:px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg font-mono text-sm sm:text-base text-gray-600">
                        </div>
                        <p class="text-sm text-red-600 mt-2">Do not share this key with anyone. It has access to your wallet balance.</p>
                    </div>
                    
                    <div class="mt-8 flex justify-center">
                        <a href="/api-docs" class="w-full sm:w-auto text-center bg-slate-900 text-white font-bold py-3 px-6 sm:px-8 rounded-xl hover:bg-slate-800 transition shadow-lg">
                            View API Documentation
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
