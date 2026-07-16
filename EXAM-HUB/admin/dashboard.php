<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

$pdo = get_db_connection();

// Basic Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$revenue = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type='purchase' AND status='completed'")->fetchColumn() ?: 0;
$pending_transfers = $pdo->query("SELECT COUNT(*) FROM transactions WHERE payment_method='transfer' AND status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EXAM-HUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-8">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-900 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h1 class="text-xl font-bold text-gray-800">Dashboard Overview</h1>
            </div>
            <div class="text-sm text-gray-500">Logged in as <?= htmlspecialchars($_SESSION['email']) ?></div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-sm font-medium mb-1">Total Users</div>
                    <div class="text-3xl font-bold text-gray-900"><?= number_format($total_users) ?></div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-sm font-medium mb-1">Total Orders</div>
                    <div class="text-3xl font-bold text-gray-900"><?= number_format($total_orders) ?></div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-sm font-medium mb-1">Revenue</div>
                    <div class="text-3xl font-bold text-blue-600">₦<?= number_format($revenue, 2) ?></div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-sm font-medium mb-1">Pending Transfers</div>
                    <div class="text-3xl font-bold text-red-500"><?= number_format($pending_transfers) ?></div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800">Recent Transactions</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-sm">
                                <th class="py-3 px-6 font-medium">Ref</th>
                                <th class="py-3 px-6 font-medium">Type</th>
                                <th class="py-3 px-6 font-medium">Amount</th>
                                <th class="py-3 px-6 font-medium">Status</th>
                                <th class="py-3 px-6 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <?php
                            $recent = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                            if(count($recent) > 0):
                                foreach($recent as $t):
                            ?>
                            <tr>
                                <td class="py-3 px-6 text-gray-900 font-medium"><?= htmlspecialchars($t['reference']) ?></td>
                                <td class="py-3 px-6 text-gray-600 capitalize"><?= htmlspecialchars($t['type']) ?></td>
                                <td class="py-3 px-6 text-gray-900">₦<?= number_format($t['amount'], 2) ?></td>
                                <td class="py-3 px-6">
                                    <?php
                                    $c = $t['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($t['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                    ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $c ?>"><?= ucfirst($t['status']) ?></span>
                                </td>
                                <td class="py-3 px-6 text-gray-500"><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></td>
                            </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <tr><td colspan="5" class="py-8 text-center text-gray-500">No transactions found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
