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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['access_id'] ?? 0;
    
    if ($action === 'approve') {
        $discount_type = $_POST['discount_type'];
        $discount_value = (float)$_POST['discount_value'];
        $api_key = bin2hex(random_bytes(16)); // Generate 32 char key
        
        $stmt = $pdo->prepare("UPDATE api_access SET status = 'approved', api_key = ?, discount_type = ?, discount_value = ? WHERE id = ?");
        $stmt->execute([$api_key, $discount_type, $discount_value, $id]);
        $success = "API Access approved. Key generated.";
        
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE api_access SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        $success = "API Access rejected.";
    } elseif ($action === 'update') {
        $discount_type = $_POST['discount_type'];
        $discount_value = (float)$_POST['discount_value'];
        
        $stmt = $pdo->prepare("UPDATE api_access SET discount_type = ?, discount_value = ? WHERE id = ?");
        $stmt->execute([$discount_type, $discount_value, $id]);
        $success = "Discount updated.";
    } elseif ($action === 'revoke') {
        $stmt = $pdo->prepare("UPDATE api_access SET status = 'rejected', api_key = NULL WHERE id = ?");
        $stmt->execute([$id]);
        $success = "API Access revoked.";
    }
}

$query = "SELECT a.*, u.email, u.firstname, u.lastname 
          FROM api_access a 
          JOIN users u ON a.user_id = u.id 
          ORDER BY a.created_at DESC";
$requests = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Users - EXAM-HUB Admin</title>
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
                <h1 class="text-xl font-bold text-gray-800">API Access Requests</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8">
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-800">All Requests</h2>
                    <p class="text-sm text-gray-500">Ensure the domain name is valid before approving.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-sm">
                                <th class="py-3 px-6 font-medium">User</th>
                                <th class="py-3 px-6 font-medium">Domain Name</th>
                                <th class="py-3 px-6 font-medium">Status</th>
                                <th class="py-3 px-6 font-medium">Discount</th>
                                <th class="py-3 px-6 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <?php foreach($requests as $req): ?>
                            <tr class="hover:bg-gray-50" x-data="{ openConfig: false }">
                                <td class="py-3 px-6">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($req['firstname'] . ' ' . $req['lastname']) ?></div>
                                    <div class="text-gray-500 text-xs"><?= htmlspecialchars($req['email']) ?></div>
                                </td>
                                <td class="py-3 px-6 text-blue-600 font-medium">
                                    <a href="http://<?= htmlspecialchars($req['domain_name']) ?>" target="_blank" class="hover:underline">
                                        <?= htmlspecialchars($req['domain_name']) ?>
                                    </a>
                                </td>
                                <td class="py-3 px-6">
                                    <?php if($req['status'] === 'approved'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Approved</span>
                                    <?php elseif($req['status'] === 'pending'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6">
                                    <?php if($req['status'] === 'approved'): ?>
                                        <?= $req['discount_type'] === 'percentage' ? floatval($req['discount_value']) . '%' : '₦' . number_format($req['discount_value'], 2) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <?php if($req['status'] === 'pending'): ?>
                                        <button @click="openConfig = true" class="text-blue-600 hover:text-blue-800 font-medium mr-3">Review</button>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="access_id" value="<?= $req['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Reject</button>
                                        </form>
                                    <?php elseif($req['status'] === 'approved'): ?>
                                        <button @click="openConfig = true" class="text-blue-600 hover:text-blue-800 font-medium mr-3">Update Discount</button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Revoke this API Key?');">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="access_id" value="<?= $req['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Modal -->
                                    <div x-show="openConfig" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display: none;">
                                        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                            <div x-show="openConfig" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="openConfig = false"></div>
                                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                                            <div x-show="openConfig" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="<?= $req['status'] === 'approved' ? 'update' : 'approve' ?>">
                                                    <input type="hidden" name="access_id" value="<?= $req['id'] ?>">
                                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                                        <div class="sm:flex sm:items-start">
                                                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                                                    Configure API Access
                                                                </h3>
                                                                <div class="mt-4 space-y-4">
                                                                    <div>
                                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type</label>
                                                                        <select name="discount_type" class="w-full border rounded px-3 py-2">
                                                                            <option value="percentage" <?= $req['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                                                            <option value="fixed" <?= $req['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount (₦)</option>
                                                                        </select>
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value</label>
                                                                        <input type="number" step="0.01" name="discount_value" value="<?= $req['discount_value'] ?>" class="w-full border rounded px-3 py-2">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                                                            <?= $req['status'] === 'approved' ? 'Save Changes' : 'Approve & Generate Key' ?>
                                                        </button>
                                                        <button type="button" @click="openConfig = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- End Modal -->
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($requests) === 0): ?>
                                <tr><td colspan="5" class="py-8 text-center text-gray-500">No API requests yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
