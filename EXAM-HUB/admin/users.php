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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $target_id = (int)$_POST['user_id'];
        
        if ($action === 'suspend') {
            $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$target_id]);
            $success = "User suspended successfully.";
        } elseif ($action === 'unsuspend') {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$target_id]);
            $success = "User reactivated successfully.";
        } elseif ($action === 'delete') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM transactions WHERE user_id = ?")->execute([$target_id]);
                $pdo->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$target_id]);
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
                $pdo->commit();
                $success = "User deleted successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to delete user: " . $e->getMessage();
            }
        } elseif ($action === 'credit_user') {
            $amount = (float)$_POST['amount'];
            $credit_action = $_POST['credit_action'];
            
            if ($amount <= 0) {
                $error = "Amount must be greater than zero.";
            } else {
                $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $stmt->execute([$target_id]);
                $current_balance = (float)$stmt->fetchColumn();
                
                if ($credit_action === 'credit') {
                    $new_balance = $current_balance + $amount;
                    $ref = 'MAN_CRED_' . time() . rand(100, 999);
                    $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$new_balance, $target_id]);
                    $pdo->prepare("INSERT INTO transactions (user_id, reference, type, amount, status, payment_method) VALUES (?, ?, 'deposit', ?, 'completed', 'manual')")->execute([$target_id, $ref, $amount]);
                    $success = "User credited successfully.";
                } else {
                    $new_balance = max(0, $current_balance - $amount);
                    $ref = 'MAN_DEB_' . time() . rand(100, 999);
                    $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?")->execute([$new_balance, $target_id]);
                    $pdo->prepare("INSERT INTO transactions (user_id, reference, type, amount, status, payment_method) VALUES (?, ?, 'payout', ?, 'completed', 'manual')")->execute([$target_id, $ref, $amount]);
                    $success = "User debited successfully.";
                }
            }
        } elseif ($action === 'login_as') {
            $_SESSION['admin_as_user'] = true;
            $_SESSION['original_admin_id'] = $_SESSION['user_id'];
            $_SESSION['user_id'] = $target_id;
            // Get user details
            $u = $pdo->prepare("SELECT email, role FROM users WHERE id = ?");
            $u->execute([$target_id]);
            $u = $u->fetch(PDO::FETCH_ASSOC);
            $_SESSION['email'] = $u['email'];
            $_SESSION['role'] = $u['role'];
            header('Location: /user/dashboard');
            exit;
        } elseif ($action === 'edit_user') {
            $firstname = $_POST['firstname'];
            $lastname = $_POST['lastname'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, password = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $email, $hash, $target_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $email, $target_id]);
            }
            $success = "User updated successfully.";
        }
    }
}

// Fetch users
$users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - EXAM-HUB Admin</title>
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
                <h1 class="text-xl font-bold text-gray-800">User Management</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8" x-data="{ editModalOpen: false, editUser: {}, creditModalOpen: false, creditUser: {} }">
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
                    <h2 class="text-lg font-bold text-gray-800">All Registered Users</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-sm">
                                <th class="py-3 px-6 font-medium">Name</th>
                                <th class="py-3 px-6 font-medium">Email</th>
                                <th class="py-3 px-6 font-medium">Wallet</th>
                                <th class="py-3 px-6 font-medium">Status</th>
                                <th class="py-3 px-6 font-medium">Registered</th>
                                <th class="py-3 px-6 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <?php foreach($users as $u): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-6 text-gray-900 font-medium"><?= htmlspecialchars($u['firstname'] . ' ' . $u['lastname']) ?></td>
                                <td class="py-3 px-6 text-gray-500"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="py-3 px-6 text-blue-600 font-bold">₦<?= number_format($u['wallet_balance'], 2) ?></td>
                                <td class="py-3 px-6">
                                    <?php if($u['status'] === 'active'): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6 text-gray-500"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td class="py-3 px-6 text-right space-x-2">
                                    <button @click="editUser = <?= htmlspecialchars(json_encode($u)) ?>; editModalOpen = true" class="text-blue-600 hover:text-blue-800 font-medium">Edit</button>
                                    <button @click="creditUser = <?= htmlspecialchars(json_encode($u)) ?>; creditModalOpen = true" class="text-green-600 hover:text-green-800 font-medium">Credit/Debit</button>
                                    
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <?php if($u['status'] === 'active'): ?>
                                            <input type="hidden" name="action" value="suspend">
                                            <button type="submit" class="text-yellow-600 hover:text-yellow-800 font-medium" onclick="return confirm('Suspend this user?')">Suspend</button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="unsuspend">
                                            <button type="submit" class="text-green-600 hover:text-green-800 font-medium">Activate</button>
                                        <?php endif; ?>
                                    </form>

                                    <form method="POST" class="inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="action" value="login_as">
                                        <button type="submit" class="text-purple-600 hover:text-purple-800 font-medium">Login As</button>
                                    </form>

                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete this user? This will also remove their transaction history and orders.')">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Edit Modal -->
            <div x-show="editModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;" x-cloak>
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                    <div class="px-6 py-4 border-b flex justify-between items-center">
                        <h3 class="font-bold text-lg">Edit User</h3>
                        <button @click="editModalOpen = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>
                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" x-model="editUser.id">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" name="firstname" x-model="editUser.firstname" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input type="text" name="lastname" x-model="editUser.lastname" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" x-model="editUser.email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-1">New Password (leave blank to keep current)</label>
                                <input type="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg font-medium">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Credit/Debit Modal -->
            <div x-show="creditModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;" x-cloak>
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                    <div class="px-6 py-4 border-b flex justify-between items-center">
                        <h3 class="font-bold text-lg">Credit / Debit Wallet</h3>
                        <button @click="creditModalOpen = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>
                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="action" value="credit_user">
                            <input type="hidden" name="user_id" x-model="creditUser.id">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                                <div class="px-4 py-2 bg-gray-50 border rounded-lg text-gray-700 font-medium" x-text="creditUser.firstname + ' ' + creditUser.lastname + ' (' + creditUser.email + ')'"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Current Balance</label>
                                <div class="px-4 py-2 bg-gray-50 border rounded-lg text-blue-600 font-bold">₦<span x-text="parseFloat(creditUser.wallet_balance).toFixed(2)"></span></div>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                                <select name="credit_action" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <option value="credit">Credit (Add Funds)</option>
                                    <option value="debit">Debit (Remove Funds)</option>
                                </select>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₦)</label>
                                <input type="number" name="amount" min="0.01" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>

                            <div class="flex justify-end gap-3">
                                <button type="button" @click="creditModalOpen = false" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg font-medium">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
