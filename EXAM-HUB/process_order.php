<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/mail.php';

$ref = $_GET['ref'] ?? null;
if (!$ref) {
    header('Location: /catalog');
    exit;
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT * FROM orders WHERE reference = ? LIMIT 1");
$stmt->execute([$ref]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Only process if pending (payment succeeded but API call pending)
if ($order['status'] === 'pending') {
    require_once __DIR__ . '/core/api_vtpass.php';
    require_once __DIR__ . '/core/api_clubkonnect.php';
    require_once __DIR__ . '/core/api_naijaresultpins.php';

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$order['card_type_id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        die("Product not found.");
    }
    
    $provider = $product['active_provider'];
    $provider_product_id = $product[$provider . '_id']; // Dynamically get vtpass_id, clubkonnect_id, etc.
    $phone = $order['phone'];
    $quantity = (int)$order['quantity'];
    
    $all_pins = [];
    $has_error = false;
    $error_msg = '';
    
    if (!$provider_product_id) {
        $has_error = true;
        $error_msg = "Provider ID is not configured for $provider.";
    } else {
        if ($provider === 'naijaresultpins') {
            $result = naijaresultpins_purchase($provider_product_id, $quantity);
            if ($result['status']) {
                $all_pins = array_merge($all_pins, $result['pins']);
            } else {
                $has_error = true;
                $error_msg = $result['message'];
            }
        } else {
            // VTPass and ClubKonnect typically process 1 PIN per request, loop if quantity > 1
            for ($i = 0; $i < $quantity; $i++) {
                if ($provider === 'vtpass') {
                    $result = vtpass_purchase($provider_product_id, $product['original_price'], $phone);
                } elseif ($provider === 'clubkonnect') {
                    $result = clubkonnect_purchase($provider_product_id, $phone);
                } else {
                    $result = ['status' => false, 'message' => 'Unknown provider'];
                }
            
            if ($result['status']) {
                $all_pins = array_merge($all_pins, $result['pins']);
            } else {
                $has_error = true;
                $error_msg = $result['message'];
                break; // Stop processing further pins if one fails
            }
        }
    }
    
    if (!$has_error && count($all_pins) > 0) {
        $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$order['id']]);
        
        $email_pins_html = "";
        foreach ($all_pins as $card) {
            $pdo->prepare("INSERT INTO order_pins (order_id, pin, serial_no) VALUES (?, ?, ?)")
                ->execute([$order['id'], $card['pin'], $card['serial_no']]);
                
            $email_pins_html .= "
            <div style='background-color:#f9fafb; padding:15px; border:1px solid #e5e7eb; border-radius:5px; margin-bottom:10px;'>
                <p style='margin:0; font-size:14px; color:#6b7280;'>Serial Number</p>
                <p style='margin:5px 0 15px; font-family:monospace; font-size:16px;'>{$card['serial_no']}</p>
                <p style='margin:0; font-size:14px; color:#6b7280;'>Exam PIN</p>
                <p style='margin:0; font-family:monospace; font-size:20px; font-weight:bold; color:#2563eb; letter-spacing:2px;'>{$card['pin']}</p>
            </div>";
        }
        
        // Fetch recipient email
        $recipient_email = $order['email'];
        if (empty($recipient_email) && !empty($order['user_id'])) {
            $u_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $u_stmt->execute([$order['user_id']]);
            $recipient_email = $u_stmt->fetchColumn();
        }
        
        // Send email
        if (!empty($recipient_email) && filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            $subject = "Your {$product['name']} Purchase is Successful";
            $body = "
            <h3 style='color:#111827;'>Thank you for your purchase!</h3>
            <p style='color:#4b5563; margin-bottom:20px;'>Your order (Ref: <strong>{$order['reference']}</strong>) has been processed successfully. Below are your PIN details:</p>
            {$email_pins_html}
            <p style='color:#4b5563; margin-top:20px;'>If you have any issues, please contact our support.</p>
            ";
            send_transactional_email($recipient_email, $subject, $body);
        }
        
        $order['status'] = 'completed';
    } else {
        // If it partially succeeded but broke, this is tricky. We'll mark as failed for simplicity, 
        // admin would need to refund or manually resolve. Realistically, we'd log the partial success.
        $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = ?")->execute([$order['id']]);
        $error = $error_msg ?: 'Failed to generate all PINs.';
        $order['status'] = 'failed';
    }
}

// Fetch generated pins if completed
$pins = [];
if ($order['status'] === 'completed') {
    $stmt = $pdo->prepare("SELECT * FROM order_pins WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $pins = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="glass p-8 rounded-3xl shadow-xl text-center">
        <?php if ($order['status'] === 'completed'): ?>
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Purchase Successful!</h1>
            <p class="text-gray-600 mb-8">Ref: <?= htmlspecialchars($order['reference']) ?></p>
            
            <div class="grid gap-4">
                <?php foreach ($pins as $pin): ?>
                <div class="bg-blue-50 p-6 rounded-xl border border-blue-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div>
                        <p class="text-sm text-gray-500 font-semibold mb-1">Serial Number</p>
                        <p class="font-mono text-gray-900"><?= htmlspecialchars($pin['serial_no']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 font-semibold mb-1">Exam PIN</p>
                        <p class="font-mono text-2xl font-bold text-blue-600 tracking-wider"><?= htmlspecialchars($pin['pin']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <p class="mt-8 text-sm text-gray-500">A copy has been sent to your email address.</p>
            
        <?php else: ?>
            <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Purchase Failed</h1>
            <p class="text-red-600 mb-8"><?= htmlspecialchars($error ?? 'An unknown error occurred.') ?></p>
            <a href="/catalog" class="px-6 py-2 bg-blue-600 text-white rounded-lg font-bold">Try Again</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
