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
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $tx_id = (int)$_POST['transaction_id'];
    $action = $_POST['action'];
    
    // Get transaction details
    $tx = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND type = 'deposit' AND payment_method = 'transfer'");
    $tx->execute([$tx_id]);
    $tx = $tx->fetch(PDO::FETCH_ASSOC);
    
    if ($tx && $tx['status'] === 'pending') {
        if ($action === 'approve') {
            $pdo->beginTransaction();
            try {
                // Update transaction
                $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?")->execute([$tx_id]);
                // Fund user wallet
                $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['user_id']]);
                
                $pdo->commit();
                $success = "Transfer approved and user wallet funded.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to process transfer.";
            }
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE transactions SET status = 'failed' WHERE id = ?")->execute([$tx_id]);
            $success = "Transfer rejected.";
        }
    }
}

// Fetch transfers
$query = "
    SELECT t.*, u.firstname, u.lastname, u.email as user_email
    FROM transactions t 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE t.type = 'deposit' AND t.payment_method = 'transfer'
    ORDER BY FIELD(t.status, 'pending', 'completed', 'failed', 'cancelled'), t.id DESC 
";
$transfers = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Transfers - EXAM-HUB Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-8">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-900 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h1 class="text-xl font-bold text-gray-800">Bank Transfer Approvals</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8" x-data="{ proofModalOpen: false, currentProof: '' }">
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800">All Manual Transfer Notifications</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-sm">
                                <th class="py-3 px-6 font-medium">Ref</th>
                                <th class="py-3 px-6 font-medium">User</th>
                                <th class="py-3 px-6 font-medium">Amount</th>
                                <th class="py-3 px-6 font-medium">Status</th>
                                <th class="py-3 px-6 font-medium">Date</th>
                                <th class="py-3 px-6 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <?php if(count($transfers) > 0): ?>
                                <?php foreach($transfers as $t): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-6 font-medium text-gray-900"><?= htmlspecialchars($t['reference']) ?></td>
                                    <td class="py-3 px-6">
                                        <div class="text-gray-900 font-medium"><?= htmlspecialchars($t['firstname'] . ' ' . $t['lastname']) ?></div>
                                        <div class="text-gray-500 text-xs"><?= htmlspecialchars($t['user_email']) ?></div>
                                    </td>
                                    <td class="py-3 px-6 text-blue-600 font-bold">₦<?= number_format($t['amount'], 2) ?></td>
                                    <td class="py-3 px-6">
                                        <?php
                                        $c = $t['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($t['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $c ?>"><?= ucfirst($t['status']) ?></span>
                                    </td>
                                    <td class="py-3 px-6 text-gray-500"><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></td>
                                    <td class="py-3 px-6 text-right space-x-2">
                                        <?php if(!empty($t['proof_image'])): ?>
                                            <button @click="currentProof = '<?= htmlspecialchars($t['proof_image']) ?>'; proofModalOpen = true" class="text-blue-600 hover:text-blue-800 font-medium">View Proof</button>
                                        <?php endif; ?>
                                        
                                        <?php if($t['status'] === 'pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="text-green-600 hover:text-green-800 font-medium ml-2" onclick="return confirm('Approve transfer and fund user wallet?')">Approve</button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="transaction_id" value="<?= $t['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="text-red-600 hover:text-red-800 font-medium ml-2" onclick="return confirm('Reject this transfer?')">Reject</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="py-8 text-center text-gray-500">No bank transfers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Proof Modal -->
            <div x-show="proofModalOpen" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50" style="display: none;">
                <div class="bg-white p-2 rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto relative">
                    <button @click="proofModalOpen = false" class="absolute top-4 right-4 bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center hover:bg-red-600 z-10">&times;</button>
                    <img :src="currentProof" class="w-full h-auto rounded-lg" alt="Transfer Proof">
                </div>
            </div>

        </div>
    </main>
</body>
</html>
