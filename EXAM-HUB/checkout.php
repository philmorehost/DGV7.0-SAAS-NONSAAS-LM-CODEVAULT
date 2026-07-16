<?php
require_once __DIR__ . '/core/config.php';

// Workaround for Payhub Inline Checkout bug where it requests checkout.php via relative path
if (isset($_GET['embed']) && $_GET['embed'] == '1') {
    header("Location: https://merchant.payhub.com.ng/checkout.php?" . $_SERVER['QUERY_STRING']);
    exit;
}
require_once __DIR__ . '/core/functions.php';

$card_id = $_GET['card_id'] ?? null;
if (!$card_id) {
    header('Location: /catalog');
    exit;
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$card_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product || $product['status'] === 'disabled') {
    header('Location: /catalog');
    exit;
}

$card_name = $product['name'];
$price = floatval($product['selling_price']);

$is_logged_in = isset($_SESSION['user_id']);
$user_email = $is_logged_in ? $_SESSION['email'] : '';
$wallet_balance = $is_logged_in ? floatval($_SESSION['wallet_balance'] ?? 0) : 0;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = (int)($_POST['quantity'] ?? 1);
    $payment_method = $_POST['payment_method'] ?? 'payhub'; 
    $email = $_POST['email'] ?? $user_email;
    $phone = $_POST['phone'] ?? '';
    $total_amount = $price * $quantity;

    if ($quantity < 1 || $quantity > 100) {
        $error = "Quantity must be between 1 and 100.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (empty($phone)) {
        $error = "Phone number is required.";
    } else {
        if (!$is_logged_in) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO guest_emails (email) VALUES (?)");
            $stmt->execute([$email]);
        }

        $reference = 'ORD_' . time() . '_' . rand(1000, 9999);
        $user_id = $is_logged_in ? $_SESSION['user_id'] : null;

        if ($payment_method === 'wallet' && $is_logged_in) {
            if ($wallet_balance >= $total_amount) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                        ->execute([$total_amount, $user_id]);
                    
                    $pdo->prepare("INSERT INTO transactions (user_id, reference, type, amount, status, payment_method) VALUES (?, ?, 'purchase', ?, 'completed', 'wallet')")
                        ->execute([$user_id, $reference, $total_amount]);
                    
                    // Note: We use a custom query since we need to store phone somewhere. For simplicity, we can serialize it or just add a column.
                    // We will add phone column to DB.
                    $pdo->prepare("INSERT INTO orders (user_id, reference, card_type_id, quantity, amount, status, phone, email) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)")
                        ->execute([$user_id, $reference, $card_id, $quantity, $total_amount, $phone, $email]);
                    
                    $pdo->commit();
                    
                    header("Location: /process_order.php?ref=$reference");
                    exit;
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $error = "Failed to process wallet payment.";
                }
            } else {
                $error = "Insufficient wallet balance.";
            }
        } elseif ($payment_method === 'transfer') {
            $pdo->prepare("INSERT INTO transactions (user_id, reference, type, amount, status, payment_method) VALUES (?, ?, 'purchase', ?, 'pending', 'transfer')")
                ->execute([$user_id, $reference, $total_amount]);
            
            $pdo->prepare("INSERT INTO orders (user_id, reference, card_type_id, quantity, amount, status, phone, email) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)")
                ->execute([$user_id, $reference, $card_id, $quantity, $total_amount, $phone, $email]);
            
            $b_name = htmlspecialchars(get_setting('bank_name'));
            $b_acc_name = htmlspecialchars(get_setting('bank_account_name'));
            $b_acc_num = htmlspecialchars(get_setting('bank_account_number'));
            
            $success = "Your order is pending. Please transfer ₦" . number_format($total_amount, 2) . " to:<br>
            <strong>Bank:</strong> $b_name<br>
            <strong>Account Name:</strong> $b_acc_name<br>
            <strong>Account Number:</strong> $b_acc_num<br>
            The admin will approve your order shortly.";
        } else {
            $pdo->prepare("INSERT INTO transactions (user_id, reference, type, amount, status, payment_method) VALUES (?, ?, 'purchase', ?, 'pending', 'payhub')")
                ->execute([$user_id, $reference, $total_amount]);
            
            $pdo->prepare("INSERT INTO orders (user_id, reference, card_type_id, quantity, amount, status, phone) VALUES (?, ?, ?, ?, ?, 'pending', ?)")
                ->execute([$user_id, $reference, $card_id, $quantity, $total_amount, $phone]);
            
            $payhub_public_key = get_setting('payhub_public_key');
            $trigger_payhub = true;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="glass p-8 rounded-3xl shadow-xl">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-8 border-b pb-4">Checkout</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php else: ?>
            <?php if (!isset($trigger_payhub) || !$trigger_payhub): ?>
            <form method="POST" x-data="{ quantity: 1, price: <?= $price ?> }">
            <div class="bg-blue-50 p-6 rounded-xl mb-8 flex justify-between items-center border border-blue-100">
                <div>
                    <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($card_name) ?></h2>
                    <p class="text-blue-600 font-semibold">₦<?= number_format($price, 2) ?> per PIN</p>
                </div>
                <div class="text-right">
                    <span class="text-sm text-gray-500 block">Total Amount</span>
                    <span class="text-3xl font-extrabold text-gray-900" x-text="'₦' + (quantity * price).toLocaleString('en-US', {minimumFractionDigits: 2})"></span>
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity (Max 100)</label>
                    <input type="number" name="quantity" x-model.number="quantity" min="1" max="100" required
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address (For PIN delivery)</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user_email) ?>" <?= $is_logged_in ? 'readonly' : '' ?> required
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow <?= $is_logged_in ? 'bg-gray-100' : '' ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                    <input type="text" name="phone" required placeholder="080XXXXXXXX"
                           class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="payhub" class="peer sr-only" checked>
                            <div class="p-4 border-2 border-gray-200 rounded-xl peer-checked:border-blue-600 peer-checked:bg-blue-50 hover:bg-gray-50 transition-all text-center">
                                <span class="font-bold text-gray-900 block mb-1">Payhub Checkout</span>
                                <span class="text-xs text-gray-500">Cards, USSD, Transfer</span>
                            </div>
                        </label>

                        <?php if ($is_logged_in): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="wallet" class="peer sr-only">
                            <div class="p-4 border-2 border-gray-200 rounded-xl peer-checked:border-blue-600 peer-checked:bg-blue-50 hover:bg-gray-50 transition-all text-center">
                                <span class="font-bold text-gray-900 block mb-1">Virtual Wallet</span>
                                <span class="text-xs text-gray-500">Bal: ₦<?= number_format($wallet_balance, 2) ?></span>
                            </div>
                        </label>
                        <?php endif; ?>

                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="transfer" class="peer sr-only">
                            <div class="p-4 border-2 border-gray-200 rounded-xl peer-checked:border-blue-600 peer-checked:bg-blue-50 hover:bg-gray-50 transition-all text-center">
                                <span class="font-bold text-gray-900 block mb-1">Manual Transfer</span>
                                <span class="text-xs text-gray-500">Requires Admin Approval</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold py-4 px-8 rounded-xl text-lg hover:opacity-90 shadow-xl shadow-blue-500/30 transition-all transform hover:scale-[1.02]">
                        Complete Purchase
                    </button>
                    <p class="text-center text-sm text-gray-500 mt-4">By proceeding, you agree to our Terms and Conditions.</p>
                </div>
            </div>
        </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($trigger_payhub) && $trigger_payhub): ?>
<script src="https://merchant.payhub.com.ng/inline.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let handler = PayhubPop.setup({
            key: '<?= htmlspecialchars($payhub_public_key) ?>',
            email: '<?= htmlspecialchars($email) ?>',
            amount: <?= $total_amount * 100 ?>, // Payhub expects kobo/cents
            ref: '<?= htmlspecialchars($reference) ?>',
            onClose: function(){
                alert('Payment window closed. You can try again or select a different payment method.');
                window.location.href = '/checkout.php?id=<?= $card_id ?>';
            },
            callback: function(response){
                window.location.href = "/payhub_callback.php?ref=" + response.reference;
            }
        });
        handler.openIframe();
    });
</script>
<div class="text-center py-12">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
    <p class="text-gray-600 font-medium">Opening secure payment gateway...</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
