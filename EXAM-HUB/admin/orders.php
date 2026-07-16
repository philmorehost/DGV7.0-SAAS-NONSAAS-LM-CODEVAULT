<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $order_id = intval($_POST['order_id']);
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ? AND status != 'completed'");
    $stmt->execute([$order_id]);
    header('Location: /admin/orders.php');
    exit;
}

// Fetch orders with user details (LEFT JOIN because some orders might be guest users with user_id NULL)
$query = "
    SELECT o.*, u.firstname, u.lastname, u.email as user_email
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.id DESC 
    LIMIT 500
";
$orders = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Orders - EXAM-HUB Admin</title>
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
                <h1 class="text-xl font-bold text-gray-800">Order Management</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800">Recent PIN Orders</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-sm">
                                <th class="py-3 px-6 font-medium">Ref</th>
                                <th class="py-3 px-6 font-medium">Customer</th>
                                <th class="py-3 px-6 font-medium">Product / Qty</th>
                                <th class="py-3 px-6 font-medium">Amount</th>
                                <th class="py-3 px-6 font-medium">Status</th>
                                <th class="py-3 px-6 font-medium">Date</th>
                                <th class="py-3 px-6 font-medium text-right">View</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <?php if(count($orders) > 0): ?>
                                <?php foreach($orders as $o): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-6 font-medium text-gray-900"><?= htmlspecialchars($o['reference']) ?></td>
                                    <td class="py-3 px-6">
                                        <?php if($o['user_id']): ?>
                                            <div class="text-gray-900 font-medium"><?= htmlspecialchars($o['firstname'] . ' ' . $o['lastname']) ?></div>
                                            <div class="text-gray-500 text-xs"><?= htmlspecialchars($o['user_email']) ?></div>
                                        <?php else: ?>
                                            <div class="text-gray-900 font-medium">Guest User</div>
                                            <div class="text-gray-500 text-xs"><?= htmlspecialchars($o['phone'] ?? 'N/A') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-6 text-gray-500">Card ID: <?= $o['card_type_id'] ?> <span class="text-xs text-gray-400">(x<?= $o['quantity'] ?>)</span></td>
                                    <td class="py-3 px-6 text-gray-900 font-medium">₦<?= number_format($o['amount'], 2) ?></td>
                                    <td class="py-3 px-6">
                                        <?php
                                        $c = $o['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($o['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $c ?>"><?= ucfirst($o['status']) ?></span>
                                    </td>
                                    <td class="py-3 px-6 text-gray-500"><?= date('M d, Y H:i', strtotime($o['created_at'])) ?></td>
                                    <td class="py-3 px-6 text-right space-x-2">
                                        <!-- For admin to view the receipt if needed -->
                                        <a href="/process_order.php?ref=<?= urlencode($o['reference']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">Receipt</a>
                                        <?php if ($o['status'] !== 'completed'): ?>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this uncompleted order?');">
                                            <input type="hidden" name="action" value="delete_order">
                                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium ml-2">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="py-8 text-center text-gray-500">No orders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
