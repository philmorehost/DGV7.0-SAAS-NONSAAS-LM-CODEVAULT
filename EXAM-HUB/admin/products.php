<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

$pdo = get_db_connection();

// Auto-migrate products table to add active_provider columns if missing
try {
    $columns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('active_provider', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN active_provider VARCHAR(50) DEFAULT 'vtpass'");
        $pdo->exec("ALTER TABLE products ADD COLUMN vtpass_id VARCHAR(100) DEFAULT ''");
        $pdo->exec("ALTER TABLE products ADD COLUMN clubkonnect_id VARCHAR(100) DEFAULT ''");
        $pdo->exec("ALTER TABLE products ADD COLUMN naijaresultpins_id VARCHAR(100) DEFAULT ''");
        $pdo->exec("ALTER TABLE products ADD COLUMN logo VARCHAR(255) DEFAULT ''");
    }
    if (!in_array('external_link', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN external_link VARCHAR(255) DEFAULT ''");
    }
} catch (Exception $e) {}

// Drop the old provider_sync unique index which causes duplicate entry '' errors
try {
    $pdo->exec("ALTER TABLE products DROP INDEX provider_sync");
} catch (Exception $e) {}

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_product'])) {
        $id = $_POST['product_id'];
        $name = $_POST['name'];
        $active_provider = $_POST['active_provider'] ?? '';
        $vtpass_id = $_POST['vtpass_id'] ?? '';
        $clubkonnect_id = $_POST['clubkonnect_id'] ?? '';
        $naijaresultpins_id = $_POST['naijaresultpins_id'] ?? '';
        $original_price = (float)$_POST['original_price'];
        $markup_type = $_POST['markup_type'];
        $markup_value = (float)$_POST['markup_value'];
        $status = $_POST['status'];
        $external_link = $_POST['external_link'] ?? '';
        
        $selling = $original_price;
        if ($markup_type === 'fixed') {
            $selling += $markup_value;
        } else {
            $selling += ($original_price * ($markup_value / 100));
        }
        
        $stmt = $pdo->prepare("SELECT logo FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $current_logo = $stmt->fetchColumn();
        
        $logo_path = $current_logo;
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../assets/img/products/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $filename = uniqid('prod_') . '.' . $file_ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) {
                    $logo_path = '/assets/img/products/' . $filename;
                }
            }
        }
        
        $stmt = $pdo->prepare("UPDATE products SET name = ?, logo = ?, active_provider = ?, vtpass_id = ?, clubkonnect_id = ?, naijaresultpins_id = ?, original_price = ?, markup_type = ?, markup_value = ?, selling_price = ?, status = ?, external_link = ? WHERE id = ?");
        $stmt->execute([$name, $logo_path, $active_provider, $vtpass_id, $clubkonnect_id, $naijaresultpins_id, $original_price, $markup_type, $markup_value, $selling, $status, $external_link, $id]);
        $success = "Product updated successfully.";
    } elseif (isset($_POST['add_product'])) {
        $name = $_POST['new_name'];
        if (trim($name) !== '') {
            $stmt = $pdo->prepare("INSERT INTO products (name, api_provider, provider_product_id, active_provider, vtpass_id, clubkonnect_id, naijaresultpins_id, original_price, selling_price, status, external_link) VALUES (?, '', '', 'vtpass', '', '', '', 0, 0, 'disabled', '')");
            $stmt->execute([$name]);
            $success = "New product added successfully. Please configure its settings below.";
        }
    } elseif (isset($_POST['delete_product'])) {
        $id = $_POST['product_id'];
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Product deleted successfully.";
    }
}

$products = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Routing & Markup - EXAM-HUB Admin</title>
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
                <h1 class="text-xl font-bold text-gray-800">Product Routing & Pricing</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8">
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Add New Product Form -->
            <div class="bg-white rounded-xl shadow-sm border border-blue-200 overflow-hidden mb-8 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Add New Exam Product</h3>
                    <a href="api_reference.php" target="_blank" class="px-4 py-2 bg-blue-50 text-blue-600 font-bold rounded-lg border border-blue-200 hover:bg-blue-100 transition-colors text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        View Provider Product IDs
                    </a>
                </div>
                <form method="POST" class="flex gap-4">
                    <input type="hidden" name="add_product" value="1">
                    <input type="text" name="new_name" placeholder="E.g. WAEC GCE Result Checker" required class="flex-1 border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-green-700 transition-colors">Add Product</button>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <?php foreach($products as $p): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <form method="POST" enctype="multipart/form-data" class="p-6">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        
                        <div class="flex justify-between items-start mb-6 border-b pb-4">
                            <div class="flex items-center gap-4">
                                <?php if (!empty($p['logo'])): ?>
                                    <img src="<?= htmlspecialchars($p['logo']) ?>" class="w-16 h-16 object-contain rounded-lg border">
                                <?php else: ?>
                                    <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 font-bold text-2xl">
                                        <?= substr(htmlspecialchars($p['name']), 0, 1) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-1">
                                        <input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" class="border-b-2 border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent" placeholder="Product Name">
                                    </h3>
                                    <p class="text-sm text-gray-500">Configure routing and pricing for this product.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="text-right">
                                    <div class="text-sm text-gray-500">Current Selling Price</div>
                                    <div class="text-2xl font-black text-blue-600">₦<?= number_format($p['selling_price'], 2) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Left Column: Routing & Logo -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Product Image (Logo)</label>
                                    <input type="file" name="logo" accept="image/png, image/jpeg, image/webp" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Active API Provider (Routing)</label>
                                    <select name="active_provider" class="w-full border rounded-lg px-3 py-2 text-gray-700 bg-gray-50 font-medium focus:ring-blue-500 focus:border-blue-500">
                                        <option value="vtpass" <?= ($p['active_provider'] ?? '') === 'vtpass' ? 'selected' : '' ?>>VTPass</option>
                                        <option value="clubkonnect" <?= ($p['active_provider'] ?? '') === 'clubkonnect' ? 'selected' : '' ?>>ClubKonnect</option>
                                        <option value="naijaresultpins" <?= ($p['active_provider'] ?? '') === 'naijaresultpins' ? 'selected' : '' ?>>NaijaResultPins</option>
                                    </select>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-2 border rounded p-3 bg-gray-50">
                                    <div class="col-span-3 text-xs font-semibold text-gray-500 uppercase mb-1">Provider Product IDs</div>
                                    
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">VTPass ID</label>
                                        <input type="text" name="vtpass_id" value="<?= htmlspecialchars($p['vtpass_id'] ?? '') ?>" class="w-full border rounded px-2 py-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">ClubKonnect ID</label>
                                        <input type="text" name="clubkonnect_id" value="<?= htmlspecialchars($p['clubkonnect_id'] ?? '') ?>" class="w-full border rounded px-2 py-1 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">NaijaResultPins ID</label>
                                        <input type="text" name="naijaresultpins_id" value="<?= htmlspecialchars($p['naijaresultpins_id'] ?? '') ?>" class="w-full border rounded px-2 py-1 text-xs">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">External Link <span class="text-xs font-normal text-gray-500">(Optional)</span></label>
                                    <input type="url" name="external_link" value="<?= htmlspecialchars($p['external_link'] ?? '') ?>" placeholder="https://example.com/checkout" class="w-full border rounded-lg px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">If set, clicking 'Buy' will redirect the user to this link instead of internal checkout.</p>
                                </div>
                            </div>

                            <!-- Right Column: Pricing -->
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Cost Price (₦)</label>
                                        <input type="number" step="0.01" name="original_price" value="<?= $p['original_price'] ?>" class="w-full border rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                        <select name="status" class="w-full border rounded-lg px-3 py-2">
                                            <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active (Visible)</option>
                                            <option value="disabled" <?= $p['status'] === 'disabled' ? 'selected' : '' ?>>Disabled (Hidden)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Markup Type</label>
                                        <select name="markup_type" class="w-full border rounded-lg px-3 py-2">
                                            <option value="fixed" <?= $p['markup_type'] === 'fixed' ? 'selected' : '' ?>>₦ Fixed Amount</option>
                                            <option value="percentage" <?= $p['markup_type'] === 'percentage' ? 'selected' : '' ?>>% Percentage</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Markup Value</label>
                                        <input type="number" step="0.01" name="markup_value" value="<?= $p['markup_value'] ?>" class="w-full border rounded-lg px-3 py-2">
                                    </div>
                                </div>
                                
                                <div class="text-right pt-2 flex justify-end gap-4">
                                    <button type="submit" name="delete_product" value="1" onclick="return confirm('Are you sure you want to delete this product?');" class="bg-red-100 text-red-600 px-6 py-2 rounded-lg font-bold hover:bg-red-200 transition-all">Delete</button>
                                    <button type="submit" name="update_product" value="1" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all">Save Changes</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($products) === 0): ?>
                    <div class="text-center py-12 bg-white rounded-2xl shadow-sm border border-gray-100 text-gray-500">
                        No products available in the database.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
