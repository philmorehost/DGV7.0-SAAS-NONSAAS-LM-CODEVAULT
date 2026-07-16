<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/api_vtpass.php';
require_once __DIR__ . '/../core/api_clubkonnect.php';
require_once __DIR__ . '/../core/api_naijaresultpins.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /admin/login.php");
    exit;
}

$provider = $_GET['provider'] ?? 'vtpass';
$packages = [];
$error = '';
$loading = true;

try {
    if ($provider === 'vtpass') {
        $packages = vtpass_get_packages();
    } elseif ($provider === 'clubkonnect') {
        $packages = clubkonnect_get_packages();
    } elseif ($provider === 'naijaresultpins') {
        $packages = naijaresultpins_get_packages();
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$loading = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Provider Reference - EXAM-HUB Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-8">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-900 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h1 class="text-xl font-bold text-gray-800">API Documentation</h1>
            </div>
            <a href="/admin/products.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Back to Products</a>
        </header>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="border-b border-gray-100 p-4 bg-gray-50 flex gap-4">
                <a href="?provider=vtpass" class="px-4 py-2 rounded-lg font-bold <?= $provider === 'vtpass' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-200' ?>">VTPass</a>
                <a href="?provider=clubkonnect" class="px-4 py-2 rounded-lg font-bold <?= $provider === 'clubkonnect' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-200' ?>">ClubKonnect</a>
                <a href="?provider=naijaresultpins" class="px-4 py-2 rounded-lg font-bold <?= $provider === 'naijaresultpins' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-200' ?>">NaijaResultPins</a>
            </div>
            
            <div class="p-6">
                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-lg mb-4"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if (empty($packages)): ?>
                    <p class="text-gray-500">No products found or could not connect to API. Please ensure your API keys are correct in Global Settings.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-500 text-sm">
                                    <th class="py-3 px-4 font-bold">Product Name</th>
                                    <th class="py-3 px-4 font-bold">API Product ID (Copy this)</th>
                                    <th class="py-3 px-4 font-bold">Provider Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $pkg): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                        <td class="py-3 px-4"><?= htmlspecialchars($pkg['name']) ?></td>
                                        <td class="py-3 px-4">
                                            <code class="bg-blue-50 text-blue-700 px-2 py-1 rounded border border-blue-200 font-mono text-sm"><?= htmlspecialchars($pkg['provider_product_id']) ?></code>
                                        </td>
                                        <td class="py-3 px-4">₦<?= number_format($pkg['original_price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </main>
</body>
</html>
