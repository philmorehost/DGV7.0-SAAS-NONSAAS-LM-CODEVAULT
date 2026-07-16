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

// Fetch latest user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: /login');
    exit;
}

if (empty($user['phone'])) {
    header('Location: /user/complete_profile.php');
    exit;
}

// Fetch recent orders
$stmt = $pdo->prepare("SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.card_type_id = p.id WHERE o.user_id = ? ORDER BY o.created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900">Welcome back, <?= htmlspecialchars($user['firstname']) ?>!</h1>
            <p class="text-gray-500 mt-1">Manage your PINs, Wallet, and API access.</p>
        </div>
        <a href="/catalog" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:bg-blue-700 transition">Buy New PINs</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <div class="glass p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-gray-500 font-semibold mb-1">Wallet Balance</p>
                    <p class="text-3xl font-extrabold text-gray-900">₦<?= number_format($user['wallet_balance'], 2) ?></p>
                </div>
                <div class="h-12 w-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            
            <div class="mt-2 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-2">Fund Wallet via Transfer</p>
                <?php
                require_once __DIR__ . '/../core/api_payhub.php';
                $virtual_account = payhub_generate_virtual_account($user_id);
                if ($virtual_account && !empty($virtual_account['account_number'])):
                ?>
                <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs text-gray-500">Bank</span>
                        <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($virtual_account['bank_name']) ?></span>
                    </div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs text-gray-500">Account No.</span>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-blue-600 tracking-wider font-mono"><?= htmlspecialchars($virtual_account['account_number']) ?></span>
                            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($virtual_account['account_number']) ?>'); alert('Account number copied!');" class="text-gray-400 hover:text-blue-600" title="Copy">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-500">Name</span>
                        <span class="text-xs font-bold text-gray-700 truncate max-w-[150px]"><?= htmlspecialchars($virtual_account['account_name']) ?></span>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-yellow-50 rounded-lg p-3 border border-yellow-100 text-xs text-yellow-700 text-center">
                    <?php if (empty(get_setting('payhub_secret_key'))): ?>
                    Virtual accounts are currently unavailable.
                    <?php else: ?>
                    <p class="mb-2">Virtual account not found or generation delayed.</p>
                    <a href="/user/dashboard" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg font-bold shadow hover:bg-blue-700 transition">Force Generate Account</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="glass p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-semibold mb-1">Total Orders</p>
                <?php
                $order_count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                $order_count->execute([$user_id]);
                ?>
                <p class="text-3xl font-extrabold text-gray-900"><?= number_format($order_count->fetchColumn()) ?></p>
            </div>
            <div class="h-12 w-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
        </div>

        <a href="/user/api" class="glass p-6 rounded-2xl shadow-sm border border-blue-200 bg-blue-50 hover:bg-blue-100 transition flex items-center justify-between group cursor-pointer">
            <div>
                <p class="text-sm text-blue-700 font-semibold mb-1">Developer API</p>
                <p class="text-lg font-bold text-gray-900 group-hover:text-blue-600 transition">Manage Access &rarr;</p>
            </div>
            <div class="h-12 w-12 rounded-full bg-blue-200 text-blue-700 flex items-center justify-center group-hover:scale-110 transition">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
        <!-- Recent Orders -->
        <div class="glass rounded-2xl shadow-sm overflow-hidden border border-gray-100">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white">
                <h3 class="text-lg font-bold text-gray-900">Recent Orders</h3>
            </div>
            <div class="overflow-x-auto bg-white/50">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-sm">
                            <th class="py-3 px-6 font-medium">Product</th>
                            <th class="py-3 px-6 font-medium">Qty</th>
                            <th class="py-3 px-6 font-medium">Amount</th>
                            <th class="py-3 px-6 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <?php foreach($orders as $order): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-4 px-6 font-medium text-gray-900"><?= htmlspecialchars($order['product_name']) ?></td>
                            <td class="py-4 px-6 text-gray-600"><?= $order['quantity'] ?></td>
                            <td class="py-4 px-6 text-gray-600">₦<?= number_format($order['amount'], 2) ?></td>
                            <td class="py-4 px-6">
                                <?php if($order['status'] === 'completed'): ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Completed</span>
                                <?php elseif($order['status'] === 'pending'): ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-700">Pending</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">Failed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($orders) === 0): ?>
                            <tr><td colspan="4" class="py-8 text-center text-gray-500">No orders yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="glass rounded-2xl shadow-sm overflow-hidden border border-gray-100">
            <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white">
                <h3 class="text-lg font-bold text-gray-900">Wallet Transactions</h3>
            </div>
            <div class="overflow-x-auto bg-white/50">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-sm">
                            <th class="py-3 px-6 font-medium">Ref</th>
                            <th class="py-3 px-6 font-medium">Type</th>
                            <th class="py-3 px-6 font-medium">Amount</th>
                            <th class="py-3 px-6 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <?php foreach($transactions as $txn): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-4 px-6 font-medium text-gray-900 text-xs truncate max-w-[100px]"><?= htmlspecialchars($txn['reference']) ?></td>
                            <td class="py-4 px-6">
                                <?php if($txn['type'] === 'credit'): ?>
                                    <span class="text-green-600 font-bold uppercase text-xs">Credit</span>
                                <?php else: ?>
                                    <span class="text-red-600 font-bold uppercase text-xs">Debit</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 font-medium text-gray-900">
                                <?= $txn['type'] === 'credit' ? '+' : '-' ?>₦<?= number_format($txn['amount'], 2) ?>
                            </td>
                            <td class="py-4 px-6 text-gray-500 text-xs"><?= date('M j, g:i a', strtotime($txn['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($transactions) === 0): ?>
                            <tr><td colspan="4" class="py-8 text-center text-gray-500">No transactions yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
