<?php
// Comprehensive User Dashboard for CodeVault PHP - Codester Sidebar Design
if (!is_logged_in()) {
    echo "<div class='bg-white rounded border border-gray-200 p-16 text-center shadow-sm max-w-xl mx-auto'><span class='text-4xl'>🔒</span><h3 class='font-black mt-4 text-xl text-slate-800 tracking-tight'>Authentication Required</h3><p class='text-xs text-slate-500 mt-2 leading-relaxed'>You must log in or register an account to manage your digital purchases, sell codes, or process admin settlements.</p><button onclick='openLoginModal()' class='mt-6 px-6 py-2.5 bg-[#5cb85c] text-white hover:bg-[#4cae4c] font-bold rounded text-xs transition-colors shadow'>Open Gateway Panel</button></div>";
    return;
}

$user = get_logged_in_user();
$userId = $user['id'];
$user_role = $user['role']; // 'admin', 'seller', 'buyer'

// Switch active sub-module
$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'overview';

// Fetch User's Wallet
$wallet_stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
$wallet_stmt->execute([$userId]);
$wallet = $wallet_stmt->fetch();
if (!$wallet) {
    // Lazy init wallet if missing
    $db->prepare("INSERT OR IGNORE INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.0, 0.0)")->execute([$userId]);
    $wallet = ['user_id' => $userId, 'balance' => 0.0, 'pending_balance' => 0.0];
}
?>

<div class="flex flex-col lg:flex-row gap-8">
    
    <!-- Left Sidebar: Links depending on Role (Buyer, Seller, Admin) -->
    <div class="w-full lg:w-64 flex-shrink-0 bg-white border border-gray-200 rounded p-5 shadow-sm self-start">
        
        <!-- User Bio Info Mini Widget -->
        <div class="p-4 bg-slate-50 rounded mb-5 text-center border border-gray-150">
            <div class="w-16 h-16 rounded-full bg-slate-200 text-[#5cb85c] flex items-center justify-center font-extrabold text-2xl mx-auto mb-3 border overflow-hidden">
                <?php if (!empty($user['avatar_url'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <h4 class="font-bold text-slate-800 text-xs truncate mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
            <span class="text-[9px] uppercase tracking-wider bg-[#5cb85c]/10 text-[#5cb85c] px-2 py-0.5 rounded font-extrabold inline-block border border-[#5cb85c]/25">
                <?php echo strtoupper($user_role); ?>
            </span>
            <div class="mt-3 text-[10px] text-slate-500 font-semibold font-mono">
                Bal: <?php echo format_price($wallet['balance']); ?>
            </div>
        </div>
        
        <!-- Sidebar Navigation List -->
        <div class="space-y-1.5 text-xs font-bold text-slate-650">
            <a href="index.php?page=dashboard&tab=overview" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'overview' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                <span>📊</span> Overview & Activity
            </a>
            
            <!-- Buyer Tabs -->
            <?php if ($user_role === 'buyer'): ?>
                <a href="index.php?page=dashboard&tab=purchases" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'purchases' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>📥</span> My Purchases
                </a>
                <a href="index.php?page=dashboard&tab=wishlist" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'wishlist' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>❤️</span> My Wishlist
                </a>
                <a href="index.php?page=dashboard&tab=support" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'support' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>💬</span> Message Desk
                </a>
                <a href="index.php?page=dashboard&tab=support_tickets" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'support_tickets' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>🎫</span> Support Tickets
                </a>
            <?php endif; ?>

            <!-- Seller Tabs -->
            <?php if ($user_role === 'seller'): ?>
                <a href="index.php?page=dashboard&tab=products" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'products' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>📤</span> Products Studio
                </a>
                <a href="index.php?page=dashboard&tab=withdrawals" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'withdrawals' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>💰</span> Payout Settlements
                </a>
                <a href="index.php?page=dashboard&tab=seller_analytics" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'seller_analytics' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>📈</span> Traffic Analytics
                </a>
                <a href="index.php?page=dashboard&tab=verification" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'verification' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>🛡️</span> Apply Verified Badge
                </a>
                <a href="index.php?page=dashboard&tab=support_tickets" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'support_tickets' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>🎫</span> Support Tickets
                </a>
            <?php endif; ?>

            <!-- Admin Tabs -->
            <?php if ($user_role === 'admin'): ?>
                <a href="index.php?page=dashboard&tab=admin_users" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_users' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>👤</span> User Management
                </a>
                <a href="index.php?page=dashboard&tab=admin_products" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_products' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>📦</span> Catalog Manager
                </a>
                <a href="index.php?page=dashboard&tab=admin_categories" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_categories' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>📂</span> Manage Categories
                </a>
                <a href="index.php?page=dashboard&tab=admin_withdrawals" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_withdrawals' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>💸</span> Payout Audits
                </a>
                <a href="index.php?page=dashboard&tab=admin_verifications" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_verifications' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>🔒</span> ID Verifications
                </a>
                <a href="index.php?page=dashboard&tab=admin_flash_sales" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_flash_sales' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>⚡</span> Flash Sales
                </a>
                <a href="index.php?page=dashboard&tab=admin_collections" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_collections' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>🎨</span> Collections
                </a>
                <a href="index.php?page=dashboard&tab=admin_coupons" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_coupons' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>🎟️</span> Coupon Codes
                </a>
                <a href="index.php?page=dashboard&tab=admin_blog" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_blog' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>📰</span> Blog Manager
                </a>
                <a href="index.php?page=dashboard&tab=admin_tickets" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_tickets' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>🎫</span> Manage Support Tickets
                </a>
                <a href="index.php?page=dashboard&tab=admin_settings" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_settings' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>⚙️</span> Platform Config
                </a>
                <a href="index.php?page=dashboard&tab=admin_seo" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_seo' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>🔍</span> SEO & Analytics
                </a>
                <a href="index.php?page=dashboard&tab=admin_ads" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'admin_ads' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                    <span>📢</span> Monetization &amp; Ads
                </a>
            <?php endif; ?>
            
            <a href="index.php?page=dashboard&tab=profile" class="flex items-center gap-2.5 p-2.5 rounded hover:bg-slate-100 hover:text-slate-900 transition-colors <?php echo $active_tab === 'profile' ? 'bg-slate-100 text-slate-900 border-l-4 border-l-[#5cb85c]' : ''; ?>">
                <span>⚙️</span> Account Profile
            </a>
            
            <hr class="border-gray-150 my-2">
            
            <a href="index.php?action=logout" class="flex items-center gap-2.5 p-2.5 rounded text-red-600 hover:bg-red-50 transition-colors">
                <span>🚪</span> Logout Account
            </a>
        </div>
    </div>
    
    <!-- Right Panel Content Area -->
    <div class="flex-1 min-w-0">
        
        <?php if ($active_tab === 'overview'): ?>
            <!-- ---------------- Tab: OVERVIEW ---------------- -->
            <div class="space-y-6">
                <!-- Stats Overview Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white border border-gray-200 rounded p-5 shadow-sm text-center">
                        <span class="text-2xl">💰</span>
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-2">Available Balance</h4>
                        <p class="font-mono font-black text-slate-900 text-xl mt-1"><?php echo format_price($wallet['balance']); ?></p>
                    </div>
                    <?php if ($user_role !== 'buyer'): ?>
                        <div class="bg-white border border-gray-200 rounded p-5 shadow-sm text-center">
                            <span class="text-2xl">⏳</span>
                            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-2">Pending Escrow</h4>
                            <p class="font-mono font-black text-slate-500 text-xl mt-1"><?php echo format_price($wallet['pending_balance']); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="bg-white border border-gray-200 rounded p-5 shadow-sm text-center">
                            <span class="text-2xl">📥</span>
                            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-2">Purchased Items</h4>
                            <p class="font-mono font-black text-slate-900 text-xl mt-1">
                                <?php
                                $cnt = $db->prepare("SELECT COUNT(*) FROM purchases WHERE buyer_id = ?");
                                $cnt->execute([$userId]);
                                echo $cnt->fetchColumn();
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <div class="bg-white border border-gray-200 rounded p-5 shadow-sm text-center">
                        <span class="text-2xl">🔔</span>
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-2">Unread Alerts</h4>
                        <p class="font-mono font-black text-slate-900 text-xl mt-1">
                            <?php
                            $cnt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND `read` = 0");
                            $cnt->execute([$userId]);
                            echo $cnt->fetchColumn();
                            ?>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Notifications/Alert Center -->
                    <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                        <h3 class="font-bold text-sm text-slate-800 border-b pb-2 flex justify-between items-center">
                            <span>🔔 System Notifications</span>
                            <a href="index.php?action=notifications_read_all" class="text-[10px] text-[#5cb85c] hover:underline">Mark all as read</a>
                        </h3>
                        <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                            <?php
                            $not_stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 10");
                            $not_stmt->execute([$userId]);
                            $n_list = $not_stmt->fetchAll();
                            ?>
                            <?php if (empty($n_list)): ?>
                                <p class="text-center text-xs text-slate-400 py-8">No notifications recorded.</p>
                            <?php else: ?>
                                <?php foreach ($n_list as $n): ?>
                                    <div class="p-3 bg-slate-50 border rounded text-[11px] space-y-1 relative <?php echo !$n['read'] ? 'border-l-4 border-l-[#5cb85c] bg-emerald-50/20' : 'border-gray-200'; ?>">
                                        <div class="flex justify-between items-center">
                                            <span class="font-extrabold uppercase tracking-wider text-[8px] text-[#5cb85c]"><?php echo str_replace('_', ' ', $n['type'] ?: 'ALERT'); ?></span>
                                            <span class="text-[8px] text-slate-400 font-mono"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></span>
                                        </div>
                                        <p class="text-slate-700 leading-normal font-normal"><?php echo htmlspecialchars($n['message']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Financial Ledger Logs -->
                    <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                        <h3 class="font-bold text-sm text-slate-800 border-b pb-2">💼 Financial Transaction Ledger</h3>
                        <div class="space-y-2.5 max-h-72 overflow-y-auto">
                            <?php
                            $tx_stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 6");
                            $tx_stmt->execute([$userId]);
                            $txs = $tx_stmt->fetchAll();
                            ?>
                            <?php if (empty($txs)): ?>
                                <p class="text-center text-xs text-slate-400 py-8">No transaction logs recorded.</p>
                            <?php else: ?>
                                <?php foreach($txs as $tx): ?>
                                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded text-xs border border-gray-150">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm"><?php echo $tx['type'] === 'sale' ? '🟢' : '🔴'; ?></span>
                                            <div>
                                                <p class="font-bold text-slate-800 capitalize leading-tight"><?php echo htmlspecialchars($tx['type']); ?></p>
                                                <span class="text-[8px] text-slate-400 font-mono font-semibold"><?php echo date('M d, Y - H:i', strtotime($tx['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <span class="font-mono font-black <?php echo $tx['type'] === 'sale' ? 'text-[#5cb85c]' : 'text-rose-600'; ?>">
                                            <?php echo ($tx['type'] === 'sale' ? '+' : '-') . format_price($tx['amount']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'purchases' && $user_role === 'buyer'): ?>
            <!-- ---------------- Tab: BUYER PURCHASES ---------------- -->
            <?php
            $p_stmt = $db->prepare("SELECT p.title, p.category, p.download_url, p.id, p.licensing_enabled, p.license_manager_url, p.extended_price, pu.amount, pu.created_at, pu.license_key, pu.license_type, pu.id as purchase_id FROM purchases pu JOIN products p ON pu.product_id = p.id WHERE pu.buyer_id = ? ORDER BY pu.id DESC");
            $p_stmt->execute([$userId]);
            $purchases = $p_stmt->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Licensed Purchases</h3>
                    <p class="text-xs text-slate-500">Every script or template you purchase is recorded here. Download files or leave reviewed scores.</p>
                </div>

                <?php if (empty($purchases)): ?>
                    <div class="text-center py-16 bg-slate-50/50 rounded border border-dashed border-gray-200">
                        <span class="text-4xl">📥</span>
                        <h4 class="font-bold text-slate-800 text-sm mt-4">No Licensed Purchases</h4>
                        <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto">Explore CodeVault code vaults to secure your first template build.</p>
                        <a href="index.php?page=marketplace" class="mt-6 inline-block bg-[#5cb85c] hover:bg-[#4cae4c] text-white font-bold px-4 py-2 rounded text-xs transition-colors shadow">Browse Marketplace</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($purchases as $p): ?>
                            <div class="p-5 border border-gray-200 bg-slate-50/30 rounded flex flex-col justify-between">
                                <div class="space-y-1">
                                    <div class="flex justify-between items-center text-[10px] font-mono font-bold text-slate-450">
                                        <span class="uppercase text-[#5cb85c] bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100"><?php echo htmlspecialchars($p['category']); ?></span>
                                        <span><?php echo date('Y-m-d', strtotime($p['created_at'])); ?></span>
                                    </div>
                                    <h4 class="font-bold text-sm text-slate-900 pt-1 leading-snug"><?php echo htmlspecialchars($p['title']); ?></h4>
                                    <p class="text-[10px] font-mono text-slate-500 font-bold">Paid: <?php echo format_price($p['amount']); ?></p>
                                    
                                    <?php if (!empty($p['licensing_enabled']) && intval($p['licensing_enabled']) === 1): ?>
                                        <div class="mt-3 p-3 bg-slate-900 text-white rounded text-[11px] flex flex-col gap-1.5 relative border border-slate-700 shadow-inner">
                                            <div class="flex justify-between items-center">
                                                <span class="text-[8px] text-slate-400 font-extrabold uppercase tracking-widest">🔑 License Key</span>
                                                <span class="text-[8px] px-1.5 py-0.2 rounded font-extrabold uppercase tracking-wider <?php echo ($p['license_type'] ?? 'standard') === 'extended' ? 'bg-emerald-500 text-slate-950' : 'bg-slate-750 text-slate-350'; ?>">
                                                    <?php echo htmlspecialchars($p['license_type'] ?? 'standard'); ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($p['license_key'])): ?>
                                                <div class="flex items-center justify-between gap-2 bg-slate-950 p-1.5 rounded border border-slate-800 font-mono font-bold text-xs">
                                                    <span class="text-emerald-400 select-all" id="license-key-text-<?php echo $p['purchase_id']; ?>"><?php echo htmlspecialchars($p['license_key']); ?></span>
                                                    <button onclick="copyToClipboard('license-key-text-<?php echo $p['purchase_id']; ?>')" class="text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-2 py-0.5 rounded font-extrabold transition-colors outline-none">Copy</button>
                                                </div>
                                                <span class="text-[9px] text-slate-450">Manage your domain at: <a href="<?php echo htmlspecialchars($p['license_manager_url']); ?>" target="_blank" class="text-emerald-400 hover:underline font-bold"><?php echo htmlspecialchars(parse_url($p['license_manager_url'], PHP_URL_HOST) ?: $p['license_manager_url']); ?> ↗</a></span>
                                                
                                                <?php if (($p['license_type'] ?? 'standard') === 'standard' && !empty($p['extended_price']) && floatval($p['extended_price']) > 0): ?>
                                                    <?php 
                                                    $upgrade_cost = floatval($p['extended_price']) - floatval($p['amount']); 
                                                    $wallet_balance = floatval($wallet['balance'] ?? 0.0);
                                                    ?>
                                                    <div class="mt-2 pt-2 border-t border-slate-800 flex flex-col gap-1.5">
                                                        <div class="flex justify-between items-center text-[9px]">
                                                            <span class="text-slate-400">Upgrade to Extended:</span>
                                                            <span class="font-bold font-mono text-emerald-400">$<?php echo number_format($upgrade_cost, 2); ?></span>
                                                        </div>
                                                        <form method="POST" action="index.php?action=upgrade_license" onsubmit="return confirm('Upgrade this standard license to extended for $<?php echo number_format($upgrade_cost, 2); ?>? This will deduct the cost from your wallet balance.');">
                                                            <input type="hidden" name="purchase_id" value="<?php echo $p['purchase_id']; ?>">
                                                            <?php if ($wallet_balance >= $upgrade_cost): ?>
                                                                <button type="submit" class="w-full text-center bg-emerald-500 hover:bg-emerald-600 text-slate-950 text-[10px] py-1 rounded font-extrabold transition-all shadow-sm outline-none">
                                                                    🚀 Upgrade with Wallet Balance
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" disabled class="w-full text-center bg-slate-800 text-slate-500 text-[9px] py-1 rounded font-bold cursor-not-allowed outline-none">
                                                                    🔒 Insufficient Balance (Need $<?php echo number_format($upgrade_cost, 2); ?>, Have $<?php echo number_format($wallet_balance, 2); ?>)
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="flex flex-col gap-2">
                                                    <p class="text-[10px] text-amber-400 leading-normal font-normal">License generation is pending or timed out.</p>
                                                    <form method="POST" action="index.php?action=generate_license">
                                                        <input type="hidden" name="purchase_id" value="<?php echo $p['purchase_id']; ?>">
                                                        <button type="submit" class="w-full text-center bg-amber-500 hover:bg-amber-600 text-slate-950 text-[10px] py-1.5 rounded font-extrabold transition-all shadow-sm outline-none">
                                                            🔄 Generate License Key
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex gap-2 mt-4 pt-3 border-t border-gray-150">
                                    <a href="index.php?page=product&id=<?php echo $p['id']; ?>#tab-btn-reviews" class="flex-1 text-center bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs py-2 rounded font-bold border">
                                        ⭐ Review Item
                                    </a>
                                    <a href="index.php?action=download&id=<?php echo $p['id']; ?>" class="flex-1 text-center bg-[#5cb85c] hover:bg-[#4cae4c] text-white text-xs py-2 rounded font-extrabold shadow border-b-2 border-green-700">
                                        📥 Download Zip
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'wishlist' && $user_role === 'buyer'): ?>
            <!-- ---------------- Tab: BUYER WISHLIST ---------------- -->
            <?php
            $w_stmt = $db->prepare("SELECT p.*, u.name as seller_name FROM wishlist w JOIN products p ON w.product_id = p.id JOIN users u ON p.seller_id = u.id WHERE w.user_id = ? ORDER BY w.created_at DESC");
            $w_stmt->execute([$userId]);
            $wishlist_items = $w_stmt->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">My Wishlist</h3>
                    <p class="text-xs text-slate-500">Track files you are interested in. Get alert notifications on price changes or sales events.</p>
                </div>

                <?php if (empty($wishlist_items)): ?>
                    <div class="text-center py-16 bg-slate-50/50 rounded border border-dashed border-gray-200">
                        <span class="text-4xl">❤️</span>
                        <h4 class="font-bold text-slate-800 text-xs mt-4">Wishlist is Empty</h4>
                        <p class="text-[10px] text-slate-400 mt-1 max-w-xs mx-auto">Explore products and click the heart icon to watch list assets.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($wishlist_items as $item): ?>
                            <div class="p-4 border border-gray-200 bg-white rounded flex gap-4 items-center relative">
                                <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" class="w-16 h-16 rounded object-cover border shrink-0" alt="<?php echo htmlspecialchars($item['title']); ?> Thumbnail">
                                <div class="flex-1 min-w-0 space-y-1">
                                    <h4 class="font-bold text-xs text-slate-900 truncate"><a href="index.php?page=product&id=<?php echo $item['id']; ?>" class="hover:underline"><?php echo htmlspecialchars($item['title']); ?></a></h4>
                                    <p class="text-[9px] text-slate-400 font-semibold">by <?php echo htmlspecialchars($item['seller_name']); ?></p>
                                    <span class="font-mono font-black text-xs text-[#5cb85c] block">
                                        <?php echo format_price($item['discount_price'] ?: $item['price']); ?>
                                    </span>
                                </div>
                                <form method="POST" action="index.php?action=wishlist_toggle" class="shrink-0">
                                    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="p-2 text-red-500 hover:bg-red-50 rounded" title="Remove from Wishlist">✕</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'support' && $user_role === 'buyer'): ?>
            <!-- ---------------- Tab: BUYER SUPPORT MESSAGES ---------------- -->
            <?php
            $receiver_id = 1; // Default admin desk
            $msg_stmt = $db->prepare("SELECT m.*, s.name as sender_name FROM messages m JOIN users s ON m.sender_id = s.id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.id ASC");
            $msg_stmt->execute([$userId, $receiver_id, $receiver_id, $userId]);
            $conv_messages = $msg_stmt->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Direct Help Desk</h3>
                    <p class="text-xs text-slate-500 font-semibold">Discuss license inquiries or config issues directly with the platform operators.</p>
                </div>

                <div class="max-h-[300px] overflow-y-auto border border-gray-200 rounded p-5 bg-slate-50 space-y-4" id="chat-history-box">
                    <?php if (empty($conv_messages)): ?>
                        <p class="text-center text-xs text-slate-400 py-10 font-bold">No messaging logs. Open support ticket below.</p>
                    <?php else: ?>
                        <?php foreach($conv_messages as $m): ?>
                            <div class="flex flex-col <?php echo $m['sender_id'] == $userId ? 'items-end' : 'items-start'; ?>">
                                <div class="max-w-[75%] rounded p-3 text-xs <?php echo $m['sender_id'] == $userId ? 'bg-slate-900 text-white' : 'bg-white border text-slate-800'; ?>">
                                    <p class="font-extrabold text-[8px] mb-1 opacity-70"><?php echo htmlspecialchars($m['sender_name']); ?></p>
                                    <p class="leading-relaxed font-normal"><?php echo htmlspecialchars($m['content']); ?></p>
                                </div>
                                <span class="text-[8px] font-mono text-slate-450 mt-1"><?php echo date('H:i', strtotime($m['created_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" action="index.php?action=message_send" class="flex gap-2">
                    <input type="hidden" name="receiver_id" value="<?php echo $receiver_id; ?>">
                    <input type="text" name="content" required placeholder="Type support message description..." class="flex-1 px-4 py-2.5 border border-gray-200 outline-none rounded text-xs bg-white focus:border-[#5cb85c]">
                    <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs shadow active:scale-95 transition-all">Send</button>
                </form>
            </div>

        <?php elseif ($active_tab === 'products' && $user_role === 'seller'): ?>
            <!-- ---------------- Tab: SELLER PRODUCTS STUDIO ---------------- -->
            <?php
            $my_prod_stmt = $db->prepare("SELECT p.*, (SELECT COUNT(*) FROM purchases pu WHERE pu.product_id = p.id) as real_sales FROM products p WHERE p.seller_id = ? ORDER BY p.id DESC");
            $my_prod_stmt->execute([$userId]);
            $my_products = $my_prod_stmt->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <?php if (get_setting('demo_mode', '0') === '1'): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg flex items-center gap-2">
                        <span>⚠️</span>
                        <span><strong>Demo Mode Active:</strong> The platform is currently in read-only mode. Adding, editing, or deleting products is temporarily disabled for sellers.</span>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Products Studio</h3>
                        <p class="text-xs text-slate-500">Upload, edit, or delete your listed assets. Add tags, versions, and discount rates to optimize your sales.</p>
                    </div>
                    <button onclick="<?php echo get_setting('demo_mode', '0') === '1' ? "alert('Platform is in read-only Demo Mode. Additions are disabled.')" : "openProductModal()"; ?>" class="px-4 py-2 bg-[#5cb85c] hover:bg-[#4cae4c] text-white font-bold rounded text-xs shadow <?php echo get_setting('demo_mode', '0') === '1' ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                        + List New Product
                    </button>
                </div>

                <?php if (empty($my_products)): ?>
                    <p class="text-center text-xs text-slate-400 py-12 font-bold border border-dashed rounded">No items listed. List your first product now!</p>
                <?php else: ?>
                    <div class="overflow-x-auto border border-gray-150 rounded">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b font-bold text-slate-500 select-none">
                                    <th class="p-3">Title</th>
                                    <th class="p-3">Category</th>
                                    <th class="p-3">Price</th>
                                    <th class="p-3">Sales / Views</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-slate-700">
                                <?php foreach($my_products as $mp): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($mp['title']); ?></td>
                                        <td class="p-3 font-semibold text-slate-500"><?php echo htmlspecialchars($mp['category']); ?></td>
                                        <td class="p-3 font-bold font-mono text-slate-800">
                                            <?php if (!empty($mp['discount_price'])): ?>
                                                <span class="text-[#5cb85c]"><?php echo format_price($mp['discount_price']); ?></span>
                                                <span class="text-gray-450 line-through text-[10px]"><?php echo format_price($mp['price']); ?></span>
                                            <?php else: ?>
                                                <?php echo format_price($mp['price']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 font-semibold font-mono text-slate-500">
                                            🛍️ <?php echo $mp['real_sales']; ?> sales <span class="text-gray-300">|</span> 👁️ <?php echo number_format($mp['views_count']); ?> views
                                        </td>
                                        <td class="p-3">
                                            <?php if ($mp['status'] === 'approved'): ?>
                                                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 font-bold border border-emerald-100 text-[10px]">Live</span>
                                            <?php elseif ($mp['status'] === 'pending'): ?>
                                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-600 font-bold border border-amber-100 text-[10px]">Pending Approval</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded bg-red-50 text-red-600 font-bold border border-red-100 text-[10px]" title="Rejected: check alert messages">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 flex gap-2 justify-center">
                                            <a href="index.php?page=product&id=<?php echo $mp['id']; ?>" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 border text-slate-700 font-bold rounded">View</a>
                                            <button onclick="openProductModal(<?php echo htmlspecialchars(json_encode($mp)); ?>)" class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-150 font-bold rounded">Edit</button>
                                            <form method="POST" action="index.php?action=product_delete" onsubmit="return confirm('Prune this marketplace script permanently?');" class="inline">
                                                <input type="hidden" name="id" value="<?php echo $mp['id']; ?>">
                                                <button type="submit" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-500 border border-red-150 font-bold rounded">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'withdrawals' && $user_role === 'seller'): ?>
            <!-- ---------------- Tab: SELLER WITHDRAWALS / SETTLEMENTS ---------------- -->
            <?php
            $wd_stmt = $db->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC");
            $wd_stmt->execute([$userId]);
            $wds = $wd_stmt->fetchAll();
            $withdrawal_charge_fee = floatval(get_setting('withdrawal_charge', 5));
            ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Request Payout -->
                <div class="lg:col-span-1 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2">💰 Request Settlement</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">Funds are moved directly to your bank account. Admin handles approvals within 48h. Processing charge fee is <strong><?php echo htmlspecialchars($withdrawal_charge_fee); ?>%</strong> of the payout amount.</p>
                    
                    <form method="POST" action="index.php?action=withdrawal_request" class="space-y-3 pt-2">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Payout Amount</label>
                            <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Destination Bank</label>
                            <input type="text" name="bank_name" required placeholder="e.g. Access Bank" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Account Number</label>
                            <input type="text" name="account_number" required placeholder="10 Digits" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Account Holder Name</label>
                            <input type="text" name="account_name" required placeholder="Legal full name" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs transition-all shadow border-b-2 border-slate-955">
                            Submit Withdrawal Request
                        </button>
                    </form>
                </div>

                <!-- Historic Log -->
                <div class="lg:col-span-2 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2">📜 Settlement Logs</h3>
                    <?php if (empty($wds)): ?>
                        <p class="text-center text-xs text-slate-400 py-10">No payouts requested yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto border border-gray-150 rounded">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                        <th class="p-3">Requested</th>
                                        <th class="p-3">Fee / Net Payout</th>
                                        <th class="p-3">Bank Destination</th>
                                        <th class="p-3">Status</th>
                                        <th class="p-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-slate-650">
                                    <?php foreach($wds as $wd): ?>
                                        <tr>
                                            <td class="p-3 font-bold text-slate-900 font-mono"><?php echo format_price($wd['amount']); ?></td>
                                            <td class="p-3 font-mono">
                                                -<?php echo format_price($wd['charge_amount']); ?> / <strong class="text-slate-900"><?php echo format_price($wd['net_amount']); ?></strong>
                                            </td>
                                            <td class="p-3 font-semibold truncate max-w-[150px]"><?php echo htmlspecialchars($wd['bank_name']) . " | " . htmlspecialchars($wd['account_number']); ?></td>
                                            <td class="p-3">
                                                <?php if ($wd['status'] === 'pending'): ?>
                                                    <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-600 font-bold border border-amber-100 text-[10px]">Pending</span>
                                                <?php elseif ($wd['status'] === 'approved'): ?>
                                                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 font-bold border border-emerald-100 text-[10px]">Settled</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded bg-red-50 text-red-600 font-bold border border-red-100 text-[10px]">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3 font-mono font-semibold text-slate-400"><?php echo date('Y-m-d', strtotime($wd['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($active_tab === 'seller_analytics' && $user_role === 'seller'): ?>
            <!-- ---------------- Tab: SELLER TRAFFIC ANALYTICS ---------------- -->
            <?php
            $an_stmt = $db->prepare("SELECT title, views_count, (SELECT COUNT(*) FROM purchases WHERE product_id = products.id) as sales FROM products WHERE seller_id = ? AND status = 'approved' ORDER BY views_count DESC");
            $an_stmt->execute([$userId]);
            $analytics_data = $an_stmt->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Traffic & Conversions Analytics</h3>
                    <p class="text-xs text-slate-500">Assess which catalog products attract the most hits, and see individual purchase conversion metrics.</p>
                </div>

                <?php if (empty($analytics_data)): ?>
                    <p class="text-center text-xs text-slate-400 py-10">No active analytics statistics.</p>
                <?php else: ?>
                    <div class="space-y-5 border p-6 bg-slate-50 rounded">
                        <h4 class="font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">View distribution bar chart</h4>
                        <div class="space-y-4">
                            <?php foreach($analytics_data as $ad): ?>
                                <?php 
                                $max_views = max(1, max(array_column($analytics_data, 'views_count')));
                                $percentage = min(100, max(2, ($ad['views_count'] / $max_views) * 100));
                                $conv_rate = $ad['views_count'] > 0 ? number_format(($ad['sales'] / $ad['views_count']) * 100, 1) : '0.0';
                                ?>
                                <div class="space-y-1.5">
                                    <div class="flex justify-between text-xs font-semibold flex-wrap gap-1">
                                        <span class="text-slate-800 truncate max-w-[250px]"><?php echo htmlspecialchars($ad['title']); ?></span>
                                        <span class="text-slate-500 font-mono"><?php echo $ad['views_count']; ?> views | <?php echo $ad['sales']; ?> sales (<?php echo $conv_rate; ?>% conversion)</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded h-3 overflow-hidden shadow-inner">
                                        <div class="bg-[#5cb85c] h-full rounded transition-all duration-500" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'verification' && $user_role === 'seller'): ?>
            <!-- ---------------- Tab: SELLER VERIFICATION REQUESTS ---------------- -->
            <?php
            $v_stmt = $db->prepare("SELECT * FROM verification_requests WHERE seller_id = ? ORDER BY id DESC LIMIT 1");
            $v_stmt->execute([$userId]);
            $vr = $v_stmt->fetch();
            ?>
            <div class="bg-white border border-gray-200 rounded p-8 shadow-sm space-y-6 max-w-xl mx-auto">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Verify Developer Badge</h3>
                    <p class="text-xs text-slate-500">Verified status triggers checkmark badges on your listings, elevating authority and buyer conversion ratios.</p>
                </div>

                <?php if ($user['is_verified']): ?>
                    <div class="p-6 bg-emerald-50 border border-emerald-100 rounded text-center">
                        <span class="text-3xl">🛡️</span>
                        <h4 class="font-bold text-emerald-800 text-sm mt-2">Verified Elite Account Active</h4>
                        <p class="text-xs text-emerald-600/80 mt-1">Your verification credentials has been approved by the administrators.</p>
                    </div>
                <?php elseif ($vr && $vr['status'] === 'pending'): ?>
                    <div class="p-6 bg-amber-50 border border-amber-100 rounded text-center">
                        <span class="text-3xl">⏳</span>
                        <h4 class="font-bold text-amber-800 text-sm mt-2">Credentials Under Review</h4>
                        <p class="text-xs text-amber-600/80 mt-1">The security team is currently reviewing your uploaded ID documents.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="index.php?action=verify_upload" class="space-y-4 pt-4 border-t">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Identification Document URL</label>
                            <input type="url" name="document_url" required placeholder="https://example.com/id_scan.jpg" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all">
                            Submit Credentials
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'admin_users' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN USER MANAGEMENT ---------------- -->
            <?php
            $us_stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
            $all_users = $us_stmt->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">User Base Directory Manager</h3>
                    <p class="text-xs text-slate-500">Review users, edit account roles, or suspend/ban accounts due to license violations.</p>
                </div>

                <div class="overflow-x-auto border border-gray-150 rounded">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                <th class="p-3">Name</th>
                                <th class="p-3">Email</th>
                                <th class="p-3">Role</th>
                                <th class="p-3">Joined Date</th>
                                <th class="p-3 text-center">Manage / Role overrides</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-slate-700">
                            <?php foreach($all_users as $u): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td class="p-3 font-semibold text-slate-500 font-mono"><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td class="p-3">
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-600 font-bold border border-blue-100 text-[10px]">Admin</span>
                                        <?php elseif ($u['role'] === 'seller'): ?>
                                            <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 font-bold border border-emerald-100 text-[10px]">Seller</span>
                                        <?php elseif ($u['role'] === 'banned'): ?>
                                            <span class="px-2 py-0.5 rounded bg-red-50 text-red-600 font-bold border border-red-100 text-[10px]">Banned</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-bold border border-slate-200 text-[10px]">Buyer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 font-mono font-semibold text-slate-400"><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                                    <td class="p-3 flex gap-2 justify-center items-center">
                                        <!-- Role change form -->
                                        <form method="POST" action="index.php?action=admin_user_manage" class="flex gap-1">
                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                            <select name="role" class="px-1 py-0.5 border text-[10px] rounded outline-none font-bold cursor-pointer" onchange="this.form.submit()">
                                                <option value="buyer" <?php echo $u['role'] === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                                                <option value="seller" <?php echo $u['role'] === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                                <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </form>
                                        
                                        <!-- Ban/Unban trigger -->
                                        <form method="POST" action="index.php?action=admin_user_manage">
                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                            <?php if ($u['role'] === 'banned'): ?>
                                                <input type="hidden" name="ban_action" value="unban">
                                                <button type="submit" class="px-2 py-1 text-[10px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 rounded">Unban</button>
                                            <?php else: ?>
                                                <input type="hidden" name="ban_action" value="ban">
                                                <button type="submit" class="px-2 py-1 text-[10px] font-bold text-red-500 bg-red-50 border border-red-100 rounded">Suspend</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($active_tab === 'admin_products' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN CATALOG MANAGEMENT ---------------- -->
            <?php
            $admin_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : 'all';
            $p_q = "SELECT p.*, u.name as seller_name FROM products p JOIN users u ON p.seller_id = u.id";
            if ($admin_filter === 'pending') {
                $p_q .= " WHERE p.status = 'pending'";
            } elseif ($admin_filter === 'approved') {
                $p_q .= " WHERE p.status = 'approved'";
            } elseif ($admin_filter === 'rejected') {
                $p_q .= " WHERE p.status = 'rejected'";
            }
            $p_q .= " ORDER BY (CASE WHEN p.status = 'pending' THEN 0 ELSE 1 END), p.id DESC";
            $all_prods = $db->query($p_q)->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Catalog & Approvals Manager</h3>
                        <p class="text-xs text-slate-500">Approve or reject uploaded code scripts. Pending listings are highlighted at the top.</p>
                    </div>
                    
                    <div class="flex gap-2 text-xs font-bold select-none cursor-pointer">
                        <a href="index.php?page=dashboard&tab=admin_products&status_filter=all" class="px-2.5 py-1 rounded border <?php echo $admin_filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-600'; ?>">All</a>
                        <a href="index.php?page=dashboard&tab=admin_products&status_filter=pending" class="px-2.5 py-1 rounded border <?php echo $admin_filter === 'pending' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-600'; ?>">Pending Queue</a>
                        <a href="index.php?page=dashboard&tab=admin_products&status_filter=approved" class="px-2.5 py-1 rounded border <?php echo $admin_filter === 'approved' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-600'; ?>">Live Approved</a>
                    </div>
                </div>

                <form method="POST" action="index.php?action=bulk_product_approve">
                    <div class="flex justify-between items-center bg-slate-50 p-3.5 border rounded mb-4">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-650">
                            <input type="checkbox" id="select-all-catalog-checkbox" class="rounded w-4 h-4 cursor-pointer" onclick="toggleSelectAllProducts(this)">
                            <span>Select All Catalog Items for Bulk Action</span>
                        </div>
                        <div class="flex gap-1.5 text-xs font-bold">
                            <button type="submit" name="status" value="approved" class="px-3 py-1.5 bg-[#5cb85c] hover:bg-[#4cae4c] text-white rounded">Bulk Approve</button>
                            <button type="submit" name="status" value="rejected" class="px-3 py-1.5 bg-red-500 hover:bg-red-650 text-white rounded">Bulk Decline</button>
                        </div>
                    </div>

                    <div class="overflow-x-auto border border-gray-150 rounded">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b font-bold text-slate-500 select-none">
                                    <th class="p-3 w-8">Select</th>
                                    <th class="p-3">Product Title</th>
                                    <th class="p-3">Developer</th>
                                    <th class="p-3">Price</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-center">Approve / Decline Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-slate-650">
                                <?php foreach($all_prods as $ap): ?>
                                    <tr class="hover:bg-slate-50 <?php echo $ap['status'] === 'pending' ? 'bg-amber-50/15' : ''; ?>">
                                        <td class="p-3 text-center">
                                            <input type="checkbox" name="product_ids[]" value="<?php echo $ap['id']; ?>" class="catalog-chk-child rounded w-3.5 h-3.5 cursor-pointer">
                                        </td>
                                        <td class="p-3 font-bold text-slate-900">
                                            <a href="index.php?page=product&id=<?php echo $ap['id']; ?>" class="hover:underline" target="_blank"><?php echo htmlspecialchars($ap['title']); ?></a>
                                        </td>
                                        <td class="p-3 font-semibold text-slate-500">by <?php echo htmlspecialchars($ap['seller_name']); ?></td>
                                        <td class="p-3 font-bold font-mono text-slate-800"><?php echo format_price($ap['price']); ?></td>
                                        <td class="p-3">
                                            <?php if ($ap['status'] === 'approved'): ?>
                                                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 font-bold border border-emerald-100 text-[10px]">Approved</span>
                                            <?php elseif ($ap['status'] === 'pending'): ?>
                                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-600 font-bold border border-amber-100 text-[10px]">Pending Audit</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded bg-red-50 text-red-600 font-bold border border-red-100 text-[10px]">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 flex justify-center items-center gap-1.5">
                                            <?php if ($ap['status'] === 'pending'): ?>
                                                <button type="button" onclick="triggerReviewRejectionModal(<?php echo $ap['id']; ?>, '<?php echo htmlspecialchars(addslashes($ap['title'])); ?>')" class="px-2 py-1 bg-red-50 text-red-500 border border-red-100 text-[10px] font-bold rounded">Decline</button>
                                                
                                                <form method="POST" action="index.php?action=product_approve" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo $ap['id']; ?>">
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 text-emerald-600 text-[10px] font-bold rounded">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Standard Pruning delete -->
                                            <form method="POST" action="index.php?action=product_delete" onsubmit="return confirm('Remove product permanently?');" class="inline">
                                                <input type="hidden" name="id" value="<?php echo $ap['id']; ?>">
                                                <button type="submit" class="px-2 py-1 bg-slate-100 border hover:bg-slate-200 text-slate-500 text-[10px] font-bold rounded">Prune</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

            <!-- Decline Feedback Modal -->
            <div id="decline-feedback-modal-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden select-none animate-fade">
                <div class="bg-white rounded p-6 max-w-sm w-full border shadow-2xl relative">
                    <button onclick="closeReviewRejectionModal()" class="absolute right-4 top-4 text-slate-400 hover:text-slate-800 font-bold text-sm">✕</button>
                    <h3 class="font-black text-slate-900 text-base mb-1">Decline Product Submission</h3>
                    <p id="decline-modal-subtitle" class="text-[10px] text-slate-400 mb-4 truncate font-semibold"></p>
                    
                    <form method="POST" action="index.php?action=product_approve" class="space-y-3">
                        <input type="hidden" name="id" id="decline-modal-input-id" value="0">
                        <input type="hidden" name="status" value="rejected">
                        <div class="space-y-1">
                            <label class="text-[9px] font-bold text-slate-450 uppercase">Reason for Rejection</label>
                            <textarea name="feedback" required placeholder="Describe code issues, missing dependencies, copyright issues, or test errors..." rows="3" class="w-full px-3 py-2 border rounded outline-none text-xs focus:border-red-400 bg-white shadow-inner"></textarea>
                        </div>
                        <button type="submit" class="w-full py-2 bg-red-500 hover:bg-red-650 text-white font-extrabold rounded text-xs transition-colors">Submit Decline Reason</button>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'admin_categories' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN MANAGE CATEGORIES ---------------- -->
            <?php
            $all_cats = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
            ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Add/Edit form -->
                <div class="lg:col-span-1 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2" id="cat-studio-header">📂 Add Taxonomy Category</h3>
                    
                    <form method="POST" action="index.php?action=admin_category_save" class="space-y-3 pt-2">
                        <input type="hidden" name="id" id="cat-studio-input-id" value="0">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Category Name</label>
                            <input type="text" name="name" id="cat-studio-input-name" required placeholder="e.g. Mobile Apps" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Category Icon character/emoji</label>
                            <input type="text" name="icon" id="cat-studio-input-icon" placeholder="e.g. 📱" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs">Save Category</button>
                        <button type="button" onclick="resetCategoryForm()" id="cat-reset-btn" class="w-full py-1 text-[10px] text-slate-500 hover:underline hidden">Cancel Edit</button>
                    </form>
                </div>

                <!-- Existing Categories Table -->
                <div class="lg:col-span-2 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2">📂 Existing Categories</h3>
                    
                    <div class="overflow-x-auto border border-gray-150 rounded">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                    <th class="p-3 w-16">Icon</th>
                                    <th class="p-3">Category Name</th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-slate-650">
                                <?php foreach($all_cats as $cat): ?>
                                    <tr>
                                        <td class="p-3 text-base text-center font-mono"><?php echo htmlspecialchars($cat['icon'] ?: '📂'); ?></td>
                                        <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($cat['name']); ?></td>
                                        <td class="p-3 flex gap-2 justify-center">
                                            <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)" class="px-2.5 py-1 text-[10px] font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded">Edit</button>
                                            
                                            <form method="POST" action="index.php?action=admin_category_delete" onsubmit="return confirm('Delete this category? This might affect products using it.');" class="inline">
                                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" class="px-2.5 py-1 text-[10px] font-bold text-red-500 bg-red-50 border border-red-100 rounded">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'admin_withdrawals' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN WITHDRAWAL SETTLEMENTS AUDIT ---------------- -->
            <?php
            $all_wds = $db->query("SELECT w.*, u.name as user_name FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY w.id DESC")->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Payout Settlements Audit</h3>
                    <p class="text-xs text-slate-500">Review payout requests from sellers. Approve once bank transfer is completed, or decline to refund wallet balance.</p>
                </div>

                <?php if (empty($all_wds)): ?>
                    <p class="text-center text-xs text-slate-400 py-10">No payouts queued for audit.</p>
                <?php else: ?>
                    <div class="overflow-x-auto border border-gray-150 rounded">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                    <th class="p-3">Vendor</th>
                                    <th class="p-3">Amount</th>
                                    <th class="p-3">Fee / Net Payout</th>
                                    <th class="p-3">Bank details</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-center">Resolve</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-slate-655">
                                <?php foreach($all_wds as $awd): ?>
                                    <tr>
                                        <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($awd['user_name']); ?></td>
                                        <td class="p-3 font-bold font-mono text-slate-700"><?php echo format_price($awd['amount']); ?></td>
                                        <td class="p-3 font-semibold font-mono text-slate-500">
                                            -<?php echo format_price($awd['charge_amount']); ?> / <strong class="text-slate-800"><?php echo format_price($awd['net_amount']); ?></strong>
                                        </td>
                                        <td class="p-3 font-semibold font-mono leading-normal text-slate-700">
                                            Bank: <?php echo htmlspecialchars($awd['bank_name']); ?><br>
                                            Name: <?php echo htmlspecialchars($awd['account_name']); ?><br>
                                            Acct: <?php echo htmlspecialchars($awd['account_number']); ?>
                                        </td>
                                        <td class="p-3">
                                            <?php if ($awd['status'] === 'pending'): ?>
                                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-600 font-bold border border-amber-100 text-[10px]">Pending</span>
                                            <?php elseif ($awd['status'] === 'approved'): ?>
                                                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 font-bold border border-emerald-100 text-[10px]">Settled</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded bg-red-50 text-red-600 font-bold border border-red-100 text-[10px]">Declined</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 flex justify-center items-center gap-1.5">
                                            <?php if ($awd['status'] === 'pending'): ?>
                                                <form method="POST" action="index.php?action=admin_withdrawal_handle" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo $awd['id']; ?>">
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 text-emerald-600 text-[10px] font-bold rounded">Approve</button>
                                                </form>
                                                <form method="POST" action="index.php?action=admin_withdrawal_handle" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo $awd['id']; ?>">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 border border-red-100 text-red-500 text-[10px] font-bold rounded">Decline</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-slate-400 font-semibold font-mono text-[10px]">Resolved</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'admin_verifications' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN ID VERIFICATIONS ---------------- -->
            <?php
            $all_vrs = $db->query("SELECT vr.*, u.name as user_name FROM verification_requests vr JOIN users u ON vr.seller_id = u.id ORDER BY vr.id DESC")->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Government ID Review Desk</h3>
                    <p class="text-xs text-slate-500">Approve documentations from vendors to unlock their green developer checkmark badge across listings.</p>
                </div>

                <?php if (empty($all_vrs)): ?>
                    <p class="text-center text-xs text-slate-400 py-10 font-bold border border-dashed rounded">No ID verification applications queued.</p>
                <?php else: ?>
                    <div class="overflow-x-auto border border-gray-150 rounded">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                    <th class="p-3">Seller Name</th>
                                    <th class="p-3">ID Document link</th>
                                    <th class="p-3">Date Applied</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-center">Resolve</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-slate-655">
                                <?php foreach($all_vrs as $vr): ?>
                                    <tr>
                                        <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($vr['user_name']); ?></td>
                                        <td class="p-3 font-semibold text-[#5cb85c] hover:underline font-mono">
                                            <a href="<?php echo htmlspecialchars($vr['document_url']); ?>" target="_blank">📄 View ID Scan URL ➔</a>
                                        </td>
                                        <td class="p-3 font-mono font-semibold text-slate-400"><?php echo date('Y-m-d', strtotime($vr['created_at'])); ?></td>
                                        <td class="p-3">
                                            <?php if ($vr['status'] === 'pending'): ?>
                                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-600 font-bold border border-amber-100 text-[10px]">Pending</span>
                                            <?php elseif ($vr['status'] === 'approved'): ?>
                                                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 font-bold border border-emerald-100 text-[10px]">Verified</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded bg-red-50 text-red-600 font-bold border border-red-100 text-[10px]">Declined</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 flex justify-center items-center gap-1.5">
                                            <?php if ($vr['status'] === 'pending'): ?>
                                                <form method="POST" action="index.php?action=admin_verify_handle" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo $vr['id']; ?>">
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 text-emerald-600 text-[10px] font-bold rounded">Approve</button>
                                                </form>
                                                <form method="POST" action="index.php?action=admin_verify_handle" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo $vr['id']; ?>">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" class="px-2.5 py-1 bg-red-50 hover:bg-red-100 border border-red-100 text-red-500 text-[10px] font-bold rounded">Decline</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-slate-400 font-bold font-mono text-[10px]">Resolved</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'admin_flash_sales' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN FLASH SALES ---------------- -->
            <?php
            $fs_products = $db->query("SELECT id, title, price, discount_price, sale_ends_at FROM products WHERE status = 'approved' ORDER BY id DESC")->fetchAll();
            ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-6">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Flash Sales Manager</h3>
                    <p class="text-xs text-slate-500">Configure discount prices and expiry dates to feature products in the Flash Sale section.</p>
                </div>

                <div class="overflow-x-auto border border-gray-150 rounded">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                <th class="p-3">Product</th>
                                <th class="p-3">Original Price</th>
                                <th class="p-3">Discount Price</th>
                                <th class="p-3">Expiry Date</th>
                                <th class="p-3 text-center">Manage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-slate-655">
                            <?php foreach ($fs_products as $p): ?>
                                <tr>
                                    <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($p['title']); ?></td>
                                    <td class="p-3 font-mono font-bold text-slate-500"><?php echo format_price($p['price']); ?></td>
                                    <td class="p-3 font-mono font-bold text-[#5cb85c]">
                                        <?php echo $p['discount_price'] ? format_price($p['discount_price']) : 'None'; ?>
                                    </td>
                                    <td class="p-3 font-mono text-slate-400">
                                        <?php echo $p['sale_ends_at'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="p-3">
                                        <form method="POST" action="index.php?action=flash_sale_toggle" class="flex gap-2 items-center justify-center flex-wrap">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <input type="number" step="0.01" name="discount_price" placeholder="Discount" value="<?php echo $p['discount_price'] ?: ''; ?>" class="w-16 px-1.5 py-0.5 border text-[10px] rounded text-slate-800 outline-none font-bold">
                                            <input type="datetime-local" name="sale_ends_at" value="<?php echo $p['sale_ends_at'] ? date('Y-m-d\TH:i', strtotime($p['sale_ends_at'])) : ''; ?>" class="px-1 py-0.5 border text-[10px] rounded outline-none">
                                            <button type="submit" class="px-2 py-1 text-[10px] font-bold text-white bg-slate-900 hover:bg-slate-800 rounded">Set</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($active_tab === 'admin_collections' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN COLLECTIONS ---------------- -->
            <?php
            $all_collections = $db->query("SELECT * FROM collections ORDER BY id DESC")->fetchAll();
            $approved_products_list = $db->query("SELECT id, title FROM products WHERE status = 'approved' ORDER BY title ASC")->fetchAll();
            ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Add / Edit form -->
                <div class="lg:col-span-1 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2" id="coll-studio-header">🎨 Curated Collection Studio</h3>
                    
                    <form method="POST" action="index.php?action=collection_save" class="space-y-3 pt-2">
                        <input type="hidden" name="id" id="coll-studio-input-id" value="0">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Collection Title</label>
                            <input type="text" name="title" id="coll-studio-input-title" required placeholder="e.g. Best E-commerce Scripts" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Short Description</label>
                            <textarea name="description" id="coll-studio-input-desc" placeholder="Details about this curated group of scripts..." rows="2" class="w-full px-3 py-2 border rounded outline-none text-xs focus:border-[#5cb85c] bg-white"></textarea>
                        </div>
                        
                        <!-- Checkbox multi-select list -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase block mb-1">Check Products to Include</label>
                            <div class="border rounded max-h-36 overflow-y-auto p-2.5 bg-slate-50 space-y-1.5">
                                <?php foreach($approved_products_list as $ap): ?>
                                    <label class="flex items-center gap-2 text-xs cursor-pointer select-none">
                                        <input type="checkbox" name="product_ids[]" value="<?php echo $ap['id']; ?>" id="coll-prod-chk-<?php echo $ap['id']; ?>" class="rounded cursor-pointer">
                                        <span class="truncate"><?php echo htmlspecialchars($ap['title']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs">Save Collection</button>
                        <button type="button" onclick="resetCollectionForm()" id="coll-reset-btn" class="w-full py-1 text-[10px] text-slate-500 hover:underline hidden">Cancel Edit</button>
                    </form>
                </div>

                <!-- Existing Collections Table -->
                <div class="lg:col-span-2 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2">🎨 Curated Collections List</h3>
                    <?php if (empty($all_collections)): ?>
                        <p class="text-center text-xs text-slate-400 py-10">No curated bundles configured yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto border border-gray-150 rounded">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                        <th class="p-3">Title</th>
                                        <th class="p-3">Bundled items count</th>
                                        <th class="p-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-slate-655">
                                    <?php foreach ($all_collections as $col): ?>
                                        <?php 
                                        $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM collection_items WHERE collection_id = ?");
                                        $cnt_stmt->execute([$col['id']]);
                                        $it_count = $cnt_stmt->fetchColumn();
                                        
                                        // Retrieve product IDs inside this collection for edit populating
                                        $ids_stmt = $db->prepare("SELECT product_id FROM collection_items WHERE collection_id = ?");
                                        $ids_stmt->execute([$col['id']]);
                                        $bundled_ids = $ids_stmt->fetchAll(PDO::FETCH_COLUMN);
                                        $json_ids = json_encode($bundled_ids);
                                        ?>
                                        <tr>
                                            <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($col['title']); ?></td>
                                            <td class="p-3 font-semibold font-mono text-slate-500"><?php echo $it_count; ?> items in bundle</td>
                                            <td class="p-3 flex gap-2 justify-center">
                                                <button onclick="editCollection(<?php echo htmlspecialchars(json_encode($col)); ?>, <?php echo htmlspecialchars($json_ids); ?>)" class="px-2.5 py-1 text-[10px] font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded">Edit</button>
                                                
                                                <form method="POST" action="index.php?action=collection_delete" onsubmit="return confirm('Delete this curated collection?');" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo $col['id']; ?>">
                                                    <button type="submit" class="px-2.5 py-1 text-[10px] font-bold text-red-500 bg-red-50 border border-red-100 rounded">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($active_tab === 'admin_coupons' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN COUPON CODES ---------------- -->
            <?php
            $all_coupons = $db->query("SELECT * FROM coupon_codes ORDER BY id DESC")->fetchAll();
            ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Add / Edit form -->
                <div class="lg:col-span-1 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2" id="coup-studio-header">🎟️ Coupon Code Generator</h3>
                    
                    <form method="POST" action="index.php?action=coupon_save" class="space-y-3 pt-2">
                        <input type="hidden" name="id" id="coup-studio-input-id" value="0">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Coupon Code (Uppercase)</label>
                            <input type="text" name="code" id="coup-studio-input-code" required placeholder="e.g. FLASH30" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Discount Type</label>
                            <select name="type" id="coup-studio-input-type" class="w-full px-3 py-2 border rounded outline-none text-xs bg-white focus:border-[#5cb85c]">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (Platform Currency)</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Discount Value</label>
                            <input type="number" step="0.01" name="value" id="coup-studio-input-value" required placeholder="30.00" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Max Total Usage (Optional)</label>
                            <input type="number" name="max_uses" id="coup-studio-input-max" placeholder="e.g. 100" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Expiry Date (Optional)</label>
                            <input type="date" name="expiry_date" id="coup-studio-input-expiry" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        
                        <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs">Save Coupon</button>
                        <button type="button" onclick="resetCouponForm()" id="coup-reset-btn" class="w-full py-1 text-[10px] text-slate-500 hover:underline hidden">Cancel Edit</button>
                    </form>
                </div>

                <!-- Existing Coupons Table -->
                <div class="lg:col-span-2 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2">🎟️ Active Coupons</h3>
                    <?php if (empty($all_coupons)): ?>
                        <p class="text-center text-xs text-slate-400 py-10">No discount coupon codes listed.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto border border-gray-150 rounded">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                        <th class="p-3">Code</th>
                                        <th class="p-3">Discount</th>
                                        <th class="p-3">Usage</th>
                                        <th class="p-3">Expiry</th>
                                        <th class="p-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-slate-655">
                                    <?php foreach ($all_coupons as $coup): ?>
                                        <tr>
                                            <td class="p-3 font-bold text-slate-900 font-mono"><?php echo htmlspecialchars($coup['code']); ?></td>
                                            <td class="p-3 font-semibold font-mono text-slate-700">
                                                <?php echo $coup['type'] === 'percentage' ? $coup['value'].'%' : format_price($coup['value']); ?>
                                            </td>
                                            <td class="p-3 font-semibold font-mono text-slate-500">
                                                <?php echo $coup['uses_count']; ?> / <?php echo $coup['max_uses'] ?: '∞'; ?> uses
                                            </td>
                                            <td class="p-3 font-mono font-semibold text-slate-400">
                                                <?php echo $coup['expiry_date'] ?: 'Never'; ?>
                                            </td>
                                            <td class="p-3 flex gap-2 justify-center">
                                                <button onclick="editCoupon(<?php echo htmlspecialchars(json_encode($coup)); ?>)" class="px-2.5 py-1 text-[10px] font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded">Edit</button>
                                                
                                                <form method="POST" action="index.php?action=coupon_delete" onsubmit="return confirm('Delete this coupon?');" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo $coup['id']; ?>">
                                                    <button type="submit" class="px-2.5 py-1 text-[10px] font-bold text-red-500 bg-red-50 border border-red-100 rounded">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($active_tab === 'admin_blog' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN BLOG MANAGER ---------------- -->
            <?php
            $all_posts = $db->query("SELECT * FROM blog_posts ORDER BY id DESC")->fetchAll();
            ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Add / Edit form -->
                <div class="lg:col-span-1 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2" id="blog-studio-header">📰 Create Blog Post</h3>
                    
                    <form method="POST" action="index.php?action=admin_blog_save" class="space-y-3 pt-2">
                        <input type="hidden" name="id" id="blog-studio-input-id" value="0">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Article Title</label>
                            <input type="text" name="title" id="blog-studio-input-title" required placeholder="e.g. Scaling Web Apps in 2026" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Author Name</label>
                            <input type="text" name="author" id="blog-studio-input-author" placeholder="Default Admin" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        
                        <div class="space-y-2 border-t pt-2">
                            <div class="flex items-center justify-between">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">Article Thumbnail Image</label>
                                <div class="inline-flex rounded shadow-sm" role="group">
                                    <button type="button" onclick="setBlogThumbMode('url')" id="btn-blog-thumb-mode-url" class="px-2 py-0.5 text-[9px] font-bold text-white bg-[#5cb85c] rounded-l border border-[#5cb85c] outline-none">URL Input</button>
                                    <button type="button" onclick="setBlogThumbMode('file')" id="btn-blog-thumb-mode-file" class="px-2 py-0.5 text-[9px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-r border border-gray-300 outline-none">Local Upload</button>
                                </div>
                            </div>
                            
                            <div class="flex gap-3 items-center">
                                <div class="w-12 h-12 rounded border bg-slate-50 flex items-center justify-center overflow-hidden shrink-0 shadow-inner" id="blog-thumb-preview-box">
                                    <img id="blog-thumb-preview-img" src="https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600" class="w-full h-full object-cover" alt="Blog Preview" referrerpolicy="no-referrer">
                                </div>
                                
                                <div class="flex-1">
                                    <div id="blog-thumb-input-pane-url" class="block">
                                        <input type="url" name="thumbnail" id="blog-studio-input-thumb" value="https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600" oninput="updateBlogThumbPreview(this.value)" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                                    </div>
                                    
                                    <div id="blog-thumb-input-pane-file" class="hidden">
                                        <div id="blog-thumb-dropzone" class="border-2 border-dashed border-slate-350 rounded p-2 text-center cursor-pointer hover:border-[#5cb85c] transition-colors relative flex flex-col items-center justify-center bg-slate-50/50">
                                            <span class="text-[10px] text-slate-500 font-bold" id="blog-thumb-upload-text">📁 Click or Drop Blog Thumbnail</span>
                                            <input type="file" id="blog-thumb-file-input" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                                            <div id="blog-thumb-upload-progress" class="w-full bg-slate-200 h-1 rounded overflow-hidden mt-1 hidden">
                                                <div id="blog-thumb-upload-progress-bar" class="bg-[#5cb85c] h-full" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Detailed Content</label>
                            <textarea name="content" id="blog-studio-input-content" required placeholder="Write article content markup..." rows="4" class="w-full px-3 py-2 border rounded outline-none text-xs focus:border-[#5cb85c] bg-white"></textarea>
                        </div>
                        
                        <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs">Save Article</button>
                        <button type="button" onclick="resetBlogForm()" id="blog-reset-btn" class="w-full py-1 text-[10px] text-slate-500 hover:underline hidden">Cancel Edit</button>
                    </form>
                </div>

                <!-- Existing Posts Table -->
                <div class="lg:col-span-2 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                    <h3 class="font-extrabold text-sm text-slate-800 border-b pb-2">📰 Platform News / Articles</h3>
                    <?php if (empty($all_posts)): ?>
                        <p class="text-center text-xs text-slate-400 py-10">No blog posts found.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto border border-gray-150 rounded">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b font-bold text-slate-500">
                                        <th class="p-3">Title</th>
                                        <th class="p-3">Author</th>
                                        <th class="p-3">Date</th>
                                        <th class="p-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-slate-655">
                                    <?php foreach ($all_posts as $post): ?>
                                        <tr>
                                            <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($post['title']); ?></td>
                                            <td class="p-3 font-semibold text-slate-500"><?php echo htmlspecialchars($post['author']); ?></td>
                                            <td class="p-3 font-mono font-semibold text-slate-400"><?php echo date('Y-m-d', strtotime($post['created_at'])); ?></td>
                                            <td class="p-3 flex gap-2 justify-center">
                                                <button onclick="editBlog(<?php echo htmlspecialchars(json_encode($post)); ?>)" class="px-2.5 py-1 text-[10px] font-bold text-blue-600 bg-blue-50 border border-blue-100 rounded">Edit</button>
                                                
                                                <form method="POST" action="index.php?action=admin_blog_delete" onsubmit="return confirm('Delete this blog post?');" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                                    <button type="submit" class="px-2.5 py-1 text-[10px] font-bold text-red-500 bg-red-50 border border-red-100 rounded">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($active_tab === 'admin_settings' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN CONFIG SETTINGS ---------------- -->
            <div class="bg-white border border-gray-200 rounded p-8 shadow-sm space-y-6 max-w-xl mx-auto">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">System Configuration</h3>
                    <p class="text-xs text-slate-500">Configure global parameters. Settings are backtick escaped and written directly to the database.</p>
                </div>

                <form method="POST" action="index.php?action=admin_settings_update" class="space-y-4 pt-4 border-t">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Paystack Public API Key</label>
                        <input type="text" name="paystack_public_key" value="<?php echo htmlspecialchars(get_setting('paystack_public_key')); ?>" placeholder="pk_test_..." class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Paystack Secret API Key</label>
                        <input type="password" name="paystack_secret_key" value="<?php echo htmlspecialchars(get_setting('paystack_secret_key')); ?>" placeholder="sk_test_..." class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Global License Manager URL</label>
                        <input type="url" name="global_lm_url" value="<?php echo htmlspecialchars(get_setting('global_lm_url')); ?>" placeholder="https://yourdomain.com/lm" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                        <p class="text-[9px] text-slate-400">Used by all products for automated licensing.</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Global License Manager Secret</label>
                        <input type="password" name="global_lm_secret" value="<?php echo htmlspecialchars(get_setting('global_lm_secret')); ?>" placeholder="LM API Secret Key" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Commission payout fee charge (%)</label>
                        <input type="number" step="0.01" name="withdrawal_charge" value="<?php echo htmlspecialchars(get_setting('withdrawal_charge', '5')); ?>" placeholder="Commission payout fee charge" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Escrow Lock Duration (Days)</label>
                        <input type="number" min="0" step="1" name="escrow_lock_days" value="<?php echo htmlspecialchars(get_setting('escrow_lock_days', '7')); ?>" placeholder="e.g. 7" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Currency Symbol</label>
                        <input type="text" name="currency" value="<?php echo htmlspecialchars(get_setting('currency', '$')); ?>" placeholder="$" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase flex items-center justify-between">
                            <span>Enable Demo Mode (Read-Only)</span>
                            <span class="text-[9px] text-slate-400 normal-case font-medium">Sellers won't be able to list/edit scripts</span>
                        </label>
                        <select name="demo_mode" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                            <option value="0" <?php echo get_setting('demo_mode', '0') === '0' ? 'selected' : ''; ?>>OFF (Standard Mode)</option>
                            <option value="1" <?php echo get_setting('demo_mode', '0') === '1' ? 'selected' : ''; ?>>ON (Demo Mode Active)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase flex items-center justify-between">
                            <span>Search Engine Friendly (SEF) Clean URLs</span>
                            <span class="text-[9px] text-slate-400 normal-case font-medium">Gracefully falls back to standard routes if mod_rewrite is inactive</span>
                        </label>
                        <select name="clean_urls" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                            <option value="1" <?php echo get_setting('clean_urls', '1') === '1' ? 'selected' : ''; ?>>ON (Use SEF Clean URLs)</option>
                            <option value="0" <?php echo get_setting('clean_urls', '1') === '0' ? 'selected' : ''; ?>>OFF (Use Standard Query Parameters)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase flex items-center justify-between">
                            <span>Affiliate System Mode</span>
                            <span class="text-[9px] text-slate-400 normal-case font-medium">Control whether users can generate tracking links</span>
                        </label>
                        <select name="affiliate_system" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                            <option value="1" <?php echo get_setting('affiliate_system', '1') === '1' ? 'selected' : ''; ?>>ON (Affiliate System Enabled)</option>
                            <option value="0" <?php echo get_setting('affiliate_system', '1') === '0' ? 'selected' : ''; ?>>OFF (Affiliate System Disabled)</option>
                        </select>
                    </div>

                    <!-- SMTP Email Server Settings -->
                    <div class="pt-4 mt-4 border-t border-dashed border-gray-200 space-y-4 text-left">
                        <h4 class="font-extrabold text-xs text-slate-800 uppercase tracking-wider">SMTP Server Configuration</h4>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">SMTP Dispatch Status</label>
                            <select name="smtp_enabled" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                                <option value="0" <?php echo get_setting('smtp_enabled', '0') === '0' ? 'selected' : ''; ?>>Disabled (Fallback to standard PHP mail)</option>
                                <option value="1" <?php echo get_setting('smtp_enabled', '0') === '1' ? 'selected' : ''; ?>>Enabled (Use SMTP Connection)</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">SMTP Server Host</label>
                                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars(get_setting('smtp_host')); ?>" placeholder="mail.yourdomain.com" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">SMTP Server Port</label>
                                <input type="number" name="smtp_port" value="<?php echo htmlspecialchars(get_setting('smtp_port', '25')); ?>" placeholder="e.g. 587" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Encryption Mode</label>
                            <select name="smtp_secure" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                                <option value="none" <?php echo get_setting('smtp_secure', 'none') === 'none' ? 'selected' : ''; ?>>None (Plaintext)</option>
                                <option value="ssl" <?php echo get_setting('smtp_secure', 'none') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="tls" <?php echo get_setting('smtp_secure', 'none') === 'tls' ? 'selected' : ''; ?>>TLS / STARTTLS</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">SMTP Username</label>
                                <input type="text" name="smtp_user" value="<?php echo htmlspecialchars(get_setting('smtp_user')); ?>" placeholder="user@yourdomain.com" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">SMTP Password</label>
                                <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars(get_setting('smtp_pass')); ?>" placeholder="••••••••" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">Sender Email Address</label>
                                <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars(get_setting('smtp_from_email')); ?>" placeholder="no-reply@yourdomain.com" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">Sender Display Name</label>
                                <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars(get_setting('smtp_from_name')); ?>" placeholder="CodeVault Support" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all">
                        Save Configuration Override
                    </button>
                </form>
            </div>

        <?php elseif ($active_tab === 'admin_seo' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN SEO & ANALYTICS MANAGER ---------------- -->
            <div class="bg-white border border-gray-200 rounded p-6 sm:p-8 shadow-sm space-y-6 max-w-2xl mx-auto">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
                    <div>
                        <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">SEO & Analytics Manager</h3>
                        <p class="text-xs text-slate-500">Configure search engine metadata, Open Graph settings, analytics integration tags, and custom code injections.</p>
                    </div>
                </div>

                <!-- Responsive Tab Bar -->
                <div class="flex overflow-x-auto pb-2 border-b border-slate-100 gap-2 scrollbar-none">
                    <button type="button" id="seo-btn-basic" onclick="switchSeoTab('basic')" class="seo-tab-btn px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider rounded transition-all whitespace-nowrap outline-none border bg-slate-900 text-white border-slate-900">
                        🔍 Basic SEO
                    </button>
                    <button type="button" id="seo-btn-schema" onclick="switchSeoTab('schema')" class="seo-tab-btn px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider rounded transition-all whitespace-nowrap outline-none border bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200">
                        🏷️ Schema.org
                    </button>
                    <button type="button" id="seo-btn-tracking" onclick="switchSeoTab('tracking')" class="seo-tab-btn px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider rounded transition-all whitespace-nowrap outline-none border bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200">
                        📊 Tracking Pixels
                    </button>
                    <button type="button" id="seo-btn-injection" onclick="switchSeoTab('injection')" class="seo-tab-btn px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider rounded transition-all whitespace-nowrap outline-none border bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200">
                        💉 Code Injection
                    </button>
                    <button type="button" id="seo-btn-sitemap" onclick="switchSeoTab('sitemap')" class="seo-tab-btn px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider rounded transition-all whitespace-nowrap outline-none border bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200">
                        🗺️ Sitemap & Robots
                    </button>
                </div>

                <form method="POST" action="index.php?action=admin_seo_update" class="space-y-6">
                    
                    <!-- Panel 1: Basic SEO -->
                    <div id="seo-panel-basic" class="seo-tab-panel space-y-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Global Site Meta Title</label>
                            <input type="text" name="seo_site_title" value="<?php echo htmlspecialchars(get_setting('seo_site_title', 'CodeVault - Digital Marketplace')); ?>" placeholder="Site Title" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Global Meta Description</label>
                            <textarea name="seo_meta_description" rows="3" placeholder="Site Description" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]"><?php echo htmlspecialchars(get_setting('seo_meta_description', 'Buy and sell scripts, themes, and plugins.')); ?></textarea>
                        </div>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Global Meta Keywords</label>
                            <input type="text" name="seo_meta_keywords" value="<?php echo htmlspecialchars(get_setting('seo_meta_keywords', 'scripts, themes, plugins, templates, marketplace')); ?>" placeholder="keywords, list, here" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Default Social/OG Image URL</label>
                            <input type="url" name="seo_og_image" value="<?php echo htmlspecialchars(get_setting('seo_og_image', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600')); ?>" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                    </div>

                    <!-- Panel 2: Schema.org -->
                    <div id="seo-panel-schema" class="seo-tab-panel space-y-4 hidden">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Business Type (Schema.org)</label>
                            <select name="schema_business_type" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                                <option value="Store" <?php echo get_setting('schema_business_type', 'Store') === 'Store' ? 'selected' : ''; ?>>Store</option>
                                <option value="Organization" <?php echo get_setting('schema_business_type') === 'Organization' ? 'selected' : ''; ?>>Organization</option>
                                <option value="LocalBusiness" <?php echo get_setting('schema_business_type') === 'LocalBusiness' ? 'selected' : ''; ?>>LocalBusiness</option>
                                <option value="ProfessionalService" <?php echo get_setting('schema_business_type') === 'ProfessionalService' ? 'selected' : ''; ?>>ProfessionalService</option>
                            </select>
                        </div>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Support Phone Number</label>
                            <input type="text" name="schema_phone" value="<?php echo htmlspecialchars(get_setting('schema_phone')); ?>" placeholder="e.g. +1-555-555-5555" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Business Physical Address</label>
                            <input type="text" name="schema_address" value="<?php echo htmlspecialchars(get_setting('schema_address')); ?>" placeholder="e.g. 123 Tech Avenue, Silicon Valley, CA" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                        </div>
                    </div>

                    <!-- Panel 3: Tracking Pixels -->
                    <div id="seo-panel-tracking" class="seo-tab-panel space-y-4 hidden">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Google Analytics (GA4) Measurement ID</label>
                            <input type="text" name="analytics_ga4_id" value="<?php echo htmlspecialchars(get_setting('analytics_ga4_id')); ?>" placeholder="G-XXXXXXXXXX" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                        </div>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Google Tag Manager (GTM) Container ID</label>
                            <input type="text" name="analytics_gtm_id" value="<?php echo htmlspecialchars(get_setting('analytics_gtm_id')); ?>" placeholder="GTM-XXXXXXX" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                        </div>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Facebook / Meta Pixel ID</label>
                            <input type="text" name="analytics_facebook_pixel_id" value="<?php echo htmlspecialchars(get_setting('analytics_facebook_pixel_id')); ?>" placeholder="e.g. 1234567890" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]">
                        </div>
                    </div>

                    <!-- Panel 4: Code Injection -->
                    <div id="seo-panel-injection" class="seo-tab-panel space-y-4 hidden">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Header Custom Scripts (Injected before &lt;/head&gt;)</label>
                            <textarea name="seo_header_injection" rows="4" placeholder="<script>...header custom scripts...</script>" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]"><?php echo htmlspecialchars(get_setting('seo_header_injection')); ?></textarea>
                        </div>
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Footer Custom Scripts (Injected before &lt;/body&gt;)</label>
                            <textarea name="seo_footer_injection" rows="4" placeholder="<script>...footer custom scripts...</script>" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]"><?php echo htmlspecialchars(get_setting('seo_footer_injection')); ?></textarea>
                        </div>
                    </div>

                    <!-- Panel 5: Sitemap & Robots -->
                    <div id="seo-panel-sitemap" class="seo-tab-panel space-y-4 hidden">
                        <p class="text-[10px] text-slate-400 leading-relaxed">Dynamic XML sitemaps and robots.txt are generated live on each page load. You can copy the URLs to submit to Search Console or webmaster tools.</p>
                        
                        <div class="space-y-3 pt-2">
                            <!-- Sitemap URL -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">XML Sitemap URL</label>
                                <div class="flex p-1 bg-slate-50 border border-gray-250 rounded">
                                    <input 
                                        type="text" 
                                        id="seo-sitemap-url-val" 
                                        readonly 
                                        value="<?php 
                                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                            $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? true : ($_SERVER['SERVER_PORT'] == 443));
                                            $protocol = $is_https ? 'https' : 'http';
                                            $base_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                                            $base_path = ($base_dir === '/' || $base_dir === '\\') ? '' : $base_dir;
                                            $sitemap_url = $protocol . '://' . $host . $base_path . '/sitemap.xml';
                                            if (get_setting('clean_urls', '1') === '0') {
                                                $sitemap_url = $protocol . '://' . $host . $base_path . '/index.php?action=sitemap';
                                            }
                                            echo htmlspecialchars($sitemap_url); 
                                        ?>" 
                                        class="flex-1 bg-transparent border-none text-[10px] font-mono outline-none text-slate-550 font-semibold px-2 w-full"
                                    >
                                    <button 
                                        type="button"
                                        onclick="copySeoURL('seo-sitemap-url-val')"
                                        class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-[10px] uppercase rounded transition-colors outline-none flex items-center gap-1"
                                        title="Copy URL"
                                    >
                                        📋 Copy
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Robots.txt URL -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">Robots.txt URL</label>
                                <div class="flex p-1 bg-slate-50 border border-gray-250 rounded">
                                    <input 
                                        type="text" 
                                        id="seo-robots-url-val" 
                                        readonly 
                                        value="<?php 
                                            $robots_url = $protocol . '://' . $host . $base_path . '/robots.txt';
                                            if (get_setting('clean_urls', '1') === '0') {
                                                $robots_url = $protocol . '://' . $host . $base_path . '/index.php?action=robots';
                                            }
                                            echo htmlspecialchars($robots_url); 
                                        ?>" 
                                        class="flex-1 bg-transparent border-none text-[10px] font-mono outline-none text-slate-550 font-semibold px-2 w-full"
                                    >
                                    <button 
                                        type="button"
                                        onclick="copySeoURL('seo-robots-url-val')"
                                        class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-[10px] uppercase rounded transition-colors outline-none flex items-center gap-1"
                                        title="Copy URL"
                                    >
                                        📋 Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all border-b-2 border-slate-950">
                        Save SEO & Analytics Configuration
                    </button>
                </form>
            </div>

        <?php elseif ($active_tab === 'admin_ads' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN AD MONETIZATION MANAGER ---------------- -->
            <div class="bg-white border border-gray-200 rounded p-6 sm:p-8 shadow-sm space-y-6 max-w-2xl mx-auto">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
                    <div>
                        <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Monetization &amp; Advertisements</h3>
                        <p class="text-xs text-slate-500">Configure Adsense/script banners for top and sidebar placements, and manage your public ads.txt compliance file.</p>
                    </div>
                </div>

                <form method="POST" action="index.php?action=admin_ads_update" class="space-y-6">
                    <!-- Top Advertisement settings -->
                    <div class="space-y-4 pt-2 border-b pb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-sm text-slate-800">Top Header Banner Ad</h4>
                                <p class="text-[10px] text-slate-400">Renders below the header navigation zone site-wide.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer select-none">
                                <input type="checkbox" name="ad_top_enabled" value="1" <?php echo get_setting('ad_top_enabled', '0') === '1' ? 'checked' : ''; ?> class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#5cb85c]"></div>
                            </label>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Top Banner Ad HTML / Script Code</label>
                            <textarea name="ad_top_code" rows="4" placeholder="e.g. <ins class='adsbygoogle' ...></ins> or custom banner HTML" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]"><?php echo htmlspecialchars(get_setting('ad_top_code', '')); ?></textarea>
                        </div>
                    </div>

                    <!-- Sidebar Advertisement settings -->
                    <div class="space-y-4 border-b pb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-sm text-slate-800">Product Sidebar Ad</h4>
                                <p class="text-[10px] text-slate-400">Renders at the base of the product detail checkout sidebar.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer select-none">
                                <input type="checkbox" name="ad_sidebar_enabled" value="1" <?php echo get_setting('ad_sidebar_enabled', '0') === '1' ? 'checked' : ''; ?> class="sr-only peer">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#5cb85c]"></div>
                            </label>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Sidebar Ad HTML / Script Code</label>
                            <textarea name="ad_sidebar_code" rows="4" placeholder="e.g. <ins class='adsbygoogle' ...></ins> or custom banner HTML" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]"><?php echo htmlspecialchars(get_setting('ad_sidebar_code', '')); ?></textarea>
                        </div>
                    </div>

                    <!-- Ads.txt settings -->
                    <div class="space-y-4 pb-4">
                        <div>
                            <h4 class="font-bold text-sm text-slate-800">Ads.txt Management</h4>
                            <p class="text-[10px] text-slate-400">Authorized Digital Sellers validation configuration. Used by Google AdSense and other ad networks.</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-450 uppercase">Ads.txt Contents</label>
                            <textarea name="ads_txt_content" rows="6" placeholder="google.com, pub-xxxxxxxxxxxxxxxx, DIRECT, f08c47fec0942fa0" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-mono focus:border-[#5cb85c]"><?php echo htmlspecialchars(get_setting('ads_txt_content', '')); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all border-b-2 border-slate-950">
                        Save Monetization &amp; Ads Configuration
                    </button>
                </form>
            </div>

        <?php elseif ($active_tab === 'profile'): ?>
            <!-- ---------------- Tab: USER ACCOUNT PROFILE ---------------- -->
            <div class="bg-white border border-gray-200 rounded p-8 shadow-sm space-y-6 max-w-xl mx-auto">
                <div>
                    <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Account settings</h3>
                    <p class="text-xs text-slate-500">Configure your personal public developer information, profile bio details, and avatar icons.</p>
                </div>

                <form method="POST" action="index.php?action=profile_update" class="space-y-4 pt-4 border-t">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Full Profile Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Avatar Image URL</label>
                        <input type="url" name="avatar_url" value="<?php echo htmlspecialchars($user['avatar_url'] ?: ''); ?>" placeholder="https://example.com/avatar.jpg" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-450 uppercase">Biography / Profile Description</label>
                        <textarea name="bio" placeholder="Describe your coding experience, developer technologies, and support details..." rows="3" class="w-full px-3 py-2 border rounded outline-none text-xs focus:border-[#5cb85c] bg-white"><?php echo htmlspecialchars($user['bio'] ?: ''); ?></textarea>
                    </div>

                    <button type="submit" class="w-full px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all">
                        Update Account Information
                    </button>
                </form>
            </div>

        <?php elseif ($active_tab === 'support_tickets'): ?>
            <!-- ---------------- Tab: USER SUPPORT TICKETS ---------------- -->
            <?php
            $ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
            if ($ticket_id > 0):
                // View specific ticket
                $t_stmt = $db->prepare("SELECT * FROM support_tickets WHERE id = ?");
                $t_stmt->execute([$ticket_id]);
                $ticket = $t_stmt->fetch();
                
                if (!$ticket || (intval($ticket['user_id']) !== intval($user['id']) && $user_role !== 'admin')):
                    echo "<div class='bg-red-50 border border-red-200 text-red-700 p-4 rounded text-xs font-semibold'>Ticket not found or unauthorized.</div>";
                else:
                    // Fetch replies
                    $r_stmt = $db->prepare("SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC");
                    $r_stmt->execute([$ticket_id]);
                    $messages = $r_stmt->fetchAll();
                    
                    $status_colors = [
                        'open' => 'bg-amber-100 text-amber-800 border-amber-200',
                        'answered' => 'bg-blue-100 text-blue-800 border-blue-200',
                        'resolved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        'closed' => 'bg-slate-100 text-slate-800 border-slate-200'
                    ];
                    $st_color = $status_colors[$ticket['status']] ?? 'bg-slate-100 text-slate-800 border-slate-200';
            ?>
                    <div class="space-y-6 max-w-4xl mx-auto select-none text-left">
                        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm flex items-center justify-between">
                            <div>
                                <a href="index.php?page=dashboard&tab=support_tickets" class="text-[#5cb85c] font-bold text-xs hover:underline mb-1 inline-block">← Back to Ticket Desk</a>
                                <h3 class="font-black text-xl text-slate-900 tracking-tight leading-tight"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                <div class="flex gap-2.5 items-center mt-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    <span>Category: <?php echo htmlspecialchars(ucfirst($ticket['category'])); ?></span>
                                    <span>•</span>
                                    <span>Priority: <?php echo htmlspecialchars(ucfirst($ticket['priority'])); ?></span>
                                    <span>•</span>
                                    <span>Created: <?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></span>
                                </div>
                            </div>
                            <span class="text-[10px] font-extrabold uppercase px-3 py-1 rounded-full border <?php echo $st_color; ?>">
                                <?php echo htmlspecialchars($ticket['status']); ?>
                            </span>
                        </div>

                        <!-- Messages Thread -->
                        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                            <h4 class="font-extrabold text-sm text-slate-800 border-b pb-2">Conversation History</h4>
                            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2">
                                <?php foreach($messages as $msg): 
                                    // Check sender role
                                    $role_stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
                                    $role_stmt->execute([$msg['sender_id']]);
                                    $sender_role = $role_stmt->fetchColumn();
                                    $is_me = intval($msg['sender_id']) === intval($user['id']);
                                    $bubble_bg = $is_me ? 'bg-slate-50 border-slate-200' : 'bg-emerald-50/50 border-emerald-100';
                                    if ($sender_role === 'admin') {
                                        $bubble_bg = 'bg-[#1c2229]/5 border-slate-300';
                                    }
                                ?>
                                    <div class="p-4 rounded border <?php echo $bubble_bg; ?> text-xs leading-relaxed">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-extrabold text-slate-800">
                                                <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                <?php if ($sender_role === 'admin'): ?>
                                                    <span class="bg-slate-800 text-white text-[8px] font-black uppercase px-1 py-0.5 rounded ml-1">Staff</span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-semibold"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></span>
                                        </div>
                                        <p class="text-slate-650 whitespace-pre-line text-sm"><?php echo htmlspecialchars($msg['content']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved'): ?>
                            <!-- Reply Form -->
                            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm">
                                <h4 class="font-extrabold text-sm text-slate-800 mb-3">Post Reply</h4>
                                <form method="POST" action="index.php?action=ticket_message_add" class="space-y-4">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <div class="space-y-1">
                                        <textarea name="message" required rows="4" placeholder="Type your reply here..." class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]"></textarea>
                                    </div>
                                    <button type="submit" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all border-b-2 border-slate-950">
                                        Submit Reply
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="bg-slate-50 border text-center p-4 rounded text-xs font-bold text-slate-500">
                                This ticket is <?php echo htmlspecialchars($ticket['status']); ?>. If you still need support, please create a new ticket.
                            </div>
                        <?php endif; ?>
                    </div>
            <?php 
                endif;
            else:
                // View all tickets for this user
                $t_stmt = $db->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
                $t_stmt->execute([$user['id']]);
                $tickets = $t_stmt->fetchAll();
            ?>
                <div class="bg-white border border-gray-200 rounded p-8 shadow-sm space-y-6 max-w-4xl mx-auto select-none text-left">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
                        <div>
                            <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Support Ticket Desk</h3>
                            <p class="text-xs text-slate-500">Need help? Open a support ticket, and our administrative agents will resolve it shortly.</p>
                        </div>
                        <button onclick="document.getElementById('ticket-create-panel').classList.toggle('hidden')" class="px-4 py-2 bg-[#5cb85c] hover:bg-[#4ca84c] text-white font-extrabold rounded text-xs shadow transition-all">
                            ➕ Create Ticket
                        </button>
                    </div>

                    <!-- Create Ticket Form Panel (Hidden by default) -->
                    <div id="ticket-create-panel" class="hidden bg-slate-50 border p-6 rounded space-y-4">
                        <h4 class="font-black text-sm text-slate-800 tracking-tight leading-none">New Support Request</h4>
                        <form method="POST" action="index.php?action=ticket_create" class="space-y-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">Ticket Subject</label>
                                <input type="text" name="subject" required placeholder="Summarize your issue..." class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-450 uppercase">Category</label>
                                    <select name="category" required class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                                        <option value="technical">Technical Support</option>
                                        <option value="billing">Billing & Refunds</option>
                                        <option value="inquiry">General Inquiry</option>
                                        <option value="licensing">Licensing / Upgrade</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-450 uppercase">Priority Level</label>
                                    <select name="priority" required class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-450 uppercase">Detailed Description</label>
                                <textarea name="message" required rows="4" placeholder="Describe your issue with order ID, script version, or screenshot references..." class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]"></textarea>
                            </div>

                            <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all border-b-2 border-slate-950">
                                Submit Ticket
                            </button>
                        </form>
                    </div>

                    <!-- List of Tickets -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs font-medium text-slate-600 border-collapse">
                            <thead>
                                <tr class="border-b border-gray-150 text-[10px] text-slate-400 font-bold uppercase tracking-wider bg-slate-50/50">
                                    <th class="py-3 px-4">Subject</th>
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4">Priority</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-right">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400 font-bold">No support tickets found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $t): 
                                        $status_colors = [
                                            'open' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'answered' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'resolved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'closed' => 'bg-slate-100 text-slate-800 border-slate-200'
                                        ];
                                        $st_color = $status_colors[$t['status']] ?? 'bg-slate-100 text-slate-800 border-slate-200';
                                    ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="py-3.5 px-4 font-bold">
                                                <a href="index.php?page=dashboard&tab=support_tickets&ticket_id=<?php echo $t['id']; ?>" class="font-extrabold text-[#5cb85c] hover:underline text-sm block">
                                                    <?php echo htmlspecialchars($t['subject']); ?>
                                                </a>
                                            </td>
                                            <td class="py-3.5 px-4 font-bold uppercase tracking-wider text-[10px]"><?php echo htmlspecialchars($t['category']); ?></td>
                                            <td class="py-3.5 px-4">
                                                <span class="font-bold text-slate-700"><?php echo ucfirst(htmlspecialchars($t['priority'])); ?></span>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border <?php echo $st_color; ?>">
                                                    <?php echo htmlspecialchars($t['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-4 text-right text-slate-400 font-bold"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'admin_tickets' && $user_role === 'admin'): ?>
            <!-- ---------------- Tab: ADMIN MANAGE SUPPORT TICKETS ---------------- -->
            <?php
            $ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
            if ($ticket_id > 0):
                // View and reply as Admin
                $t_stmt = $db->prepare("SELECT t.*, u.name as client_name, u.email as client_email FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
                $t_stmt->execute([$ticket_id]);
                $ticket = $t_stmt->fetch();
                
                if (!$ticket):
                    echo "<div class='bg-red-50 border border-red-200 text-red-700 p-4 rounded text-xs font-semibold'>Ticket not found.</div>";
                else:
                    // Fetch replies
                    $r_stmt = $db->prepare("SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC");
                    $r_stmt->execute([$ticket_id]);
                    $messages = $r_stmt->fetchAll();
                    
                    $status_colors = [
                        'open' => 'bg-amber-100 text-amber-800 border-amber-200',
                        'answered' => 'bg-blue-100 text-blue-800 border-blue-200',
                        'resolved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        'closed' => 'bg-slate-100 text-slate-800 border-slate-200'
                    ];
                    $st_color = $status_colors[$ticket['status']] ?? 'bg-slate-100 text-slate-800 border-slate-200';
            ?>
                    <div class="space-y-6 max-w-4xl mx-auto select-none text-left">
                        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm flex items-center justify-between">
                            <div>
                                <a href="index.php?page=dashboard&tab=admin_tickets" class="text-[#5cb85c] font-bold text-xs hover:underline mb-1 inline-block">← Back to Ticket Queue</a>
                                <h3 class="font-black text-xl text-slate-900 tracking-tight leading-tight"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                <div class="flex gap-2.5 items-center mt-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    <span>Client: <?php echo htmlspecialchars($ticket['client_name']); ?> (<?php echo htmlspecialchars($ticket['client_email']); ?>)</span>
                                    <span>•</span>
                                    <span>Category: <?php echo htmlspecialchars(ucfirst($ticket['category'])); ?></span>
                                    <span>•</span>
                                    <span>Priority: <?php echo htmlspecialchars(ucfirst($ticket['priority'])); ?></span>
                                </div>
                            </div>
                            <span class="text-[10px] font-extrabold uppercase px-3 py-1 rounded-full border <?php echo $st_color; ?>">
                                <?php echo htmlspecialchars($ticket['status']); ?>
                            </span>
                        </div>

                        <!-- Parameter Update Panel -->
                        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm">
                            <h4 class="font-extrabold text-sm text-slate-800 mb-3">Update Ticket Parameters</h4>
                            <form method="POST" action="index.php?action=admin_ticket_status" class="flex gap-4 items-end">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                <div class="space-y-1 flex-1 text-left">
                                    <label class="text-[10px] font-bold text-slate-450 uppercase">Update Status</label>
                                    <select name="status" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                                        <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open (Pending Client reply)</option>
                                        <option value="answered" <?php echo $ticket['status'] === 'answered' ? 'selected' : ''; ?>>Answered</option>
                                        <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed / Archived</option>
                                    </select>
                                </div>
                                <div class="space-y-1 flex-1 text-left">
                                    <label class="text-[10px] font-bold text-slate-450 uppercase">Priority</label>
                                    <select name="priority" class="w-full px-3 py-2 border outline-none bg-white rounded text-xs font-bold focus:border-[#5cb85c]">
                                        <option value="normal" <?php echo $ticket['priority'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                        <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo $ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                                <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all border-b-2 border-slate-950">
                                    Save Changes
                                </button>
                            </form>
                        </div>

                        <!-- Conversation history -->
                        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                            <h4 class="font-extrabold text-sm text-slate-800 border-b pb-2">Client Conversation History</h4>
                            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2">
                                <?php foreach($messages as $msg): 
                                    $role_stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
                                    $role_stmt->execute([$msg['sender_id']]);
                                    $sender_role = $role_stmt->fetchColumn();
                                    $is_me = intval($msg['sender_id']) === intval($user['id']);
                                    $bubble_bg = $is_me ? 'bg-slate-50 border-slate-200' : 'bg-emerald-50/50 border-emerald-100';
                                    if ($sender_role === 'admin') {
                                        $bubble_bg = 'bg-[#1c2229]/5 border-slate-300';
                                    }
                                ?>
                                    <div class="p-4 rounded border <?php echo $bubble_bg; ?> text-xs leading-relaxed">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="font-extrabold text-slate-800">
                                                <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                <?php if ($sender_role === 'admin'): ?>
                                                    <span class="bg-slate-800 text-white text-[8px] font-black uppercase px-1 py-0.5 rounded ml-1">Staff</span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-semibold"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></span>
                                        </div>
                                        <p class="text-slate-650 whitespace-pre-line text-sm"><?php echo htmlspecialchars($msg['content']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Reply -->
                        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm">
                            <h4 class="font-extrabold text-sm text-slate-800 mb-3">Compose Support Reply</h4>
                            <form method="POST" action="index.php?action=ticket_message_add" class="space-y-4">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                <div class="space-y-1">
                                    <textarea name="message" required rows="4" placeholder="Type support staff message..." class="w-full px-3 py-2 border outline-none bg-white rounded text-xs focus:border-[#5cb85c]"></textarea>
                                </div>
                                <button type="submit" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded text-xs shadow transition-all border-b-2 border-slate-950">
                                    Send Reply
                                </button>
                            </form>
                        </div>
                    </div>
            <?php
                endif;
            else:
                // View all tickets in the system
                $filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
                
                $sql = "SELECT t.*, u.name as client_name FROM support_tickets t JOIN users u ON t.user_id = u.id";
                $params = [];
                if (!empty($filter_status)) {
                    $sql .= " WHERE t.status = ?";
                    $params[] = $filter_status;
                }
                $sql .= " ORDER BY t.created_at DESC";
                
                $t_stmt = $db->prepare($sql);
                $t_stmt->execute($params);
                $tickets = $t_stmt->fetchAll();
            ?>
                <div class="bg-white border border-gray-200 rounded p-8 shadow-sm space-y-6 max-w-4xl mx-auto select-none text-left">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4">
                        <div>
                            <h3 class="font-black text-xl text-slate-900 tracking-tight leading-none mb-1">Administrative Ticket Queue</h3>
                            <p class="text-xs text-slate-500">Manage, reply to, and resolve user-submitted support tickets across the marketplace.</p>
                        </div>
                    </div>

                    <!-- Queue Filters -->
                    <div class="flex gap-2 text-[10px] font-extrabold uppercase tracking-wider overflow-x-auto pb-2 scrollbar-none">
                        <a href="index.php?page=dashboard&tab=admin_tickets" class="px-3 py-2 rounded border transition-all whitespace-nowrap <?php echo empty($filter_status) ? 'bg-slate-900 text-white border-slate-900' : 'bg-white hover:bg-slate-50 border-gray-200 text-slate-600'; ?>">
                            All Tickets
                        </a>
                        <a href="index.php?page=dashboard&tab=admin_tickets&status=open" class="px-3 py-2 rounded border transition-all whitespace-nowrap <?php echo $filter_status === 'open' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white hover:bg-slate-50 border-gray-200 text-slate-600'; ?>">
                            Open
                        </a>
                        <a href="index.php?page=dashboard&tab=admin_tickets&status=answered" class="px-3 py-2 rounded border transition-all whitespace-nowrap <?php echo $filter_status === 'answered' ? 'bg-blue-500 text-white border-blue-500' : 'bg-white hover:bg-slate-50 border-gray-200 text-slate-600'; ?>">
                            Answered
                        </a>
                        <a href="index.php?page=dashboard&tab=admin_tickets&status=resolved" class="px-3 py-2 rounded border transition-all whitespace-nowrap <?php echo $filter_status === 'resolved' ? 'bg-emerald-500 text-white border-emerald-500' : 'bg-white hover:bg-slate-50 border-gray-200 text-slate-600'; ?>">
                            Resolved
                        </a>
                        <a href="index.php?page=dashboard&tab=admin_tickets&status=closed" class="px-3 py-2 rounded border transition-all whitespace-nowrap <?php echo $filter_status === 'closed' ? 'bg-slate-500 text-white border-slate-500' : 'bg-white hover:bg-slate-50 border-gray-200 text-slate-600'; ?>">
                            Closed
                        </a>
                    </div>

                    <!-- Table List -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs font-medium text-slate-600 border-collapse">
                            <thead>
                                <tr class="border-b border-gray-150 text-[10px] text-slate-400 font-bold uppercase tracking-wider bg-slate-50/50">
                                    <th class="py-3 px-4">Subject</th>
                                    <th class="py-3 px-4">Client</th>
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4">Priority</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-right">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400 font-bold">No tickets in this status queue.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $t): 
                                        $status_colors = [
                                            'open' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'answered' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'resolved' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'closed' => 'bg-slate-100 text-slate-800 border-slate-200'
                                        ];
                                        $st_color = $status_colors[$t['status']] ?? 'bg-slate-100 text-slate-800 border-slate-200';
                                    ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="py-3.5 px-4 font-bold">
                                                <a href="index.php?page=dashboard&tab=admin_tickets&ticket_id=<?php echo $t['id']; ?>" class="text-[#5cb85c] hover:underline text-sm block">
                                                    <?php echo htmlspecialchars($t['subject']); ?>
                                                </a>
                                            </td>
                                            <td class="py-3.5 px-4 text-slate-800 font-bold"><?php echo htmlspecialchars($t['client_name']); ?></td>
                                            <td class="py-3.5 px-4 font-bold uppercase tracking-wider text-[10px]"><?php echo htmlspecialchars($t['category']); ?></td>
                                            <td class="py-3.5 px-4">
                                                <span class="font-bold text-slate-700"><?php echo ucfirst(htmlspecialchars($t['priority'])); ?></span>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border <?php echo $st_color; ?>">
                                                    <?php echo htmlspecialchars($t['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-4 text-right text-slate-400 font-bold"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
    
</div>

<!-- Dashboard real-time scripts -->
<script>
    // Handles Category Manager edits populating
    function editCategory(cat) {
        document.getElementById('cat-studio-header').innerText = '📂 Edit Category';
        document.getElementById('cat-studio-input-id').value = cat.id;
        document.getElementById('cat-studio-input-name').value = cat.name;
        document.getElementById('cat-studio-input-icon').value = cat.icon || '';
        document.getElementById('cat-reset-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetCategoryForm() {
        document.getElementById('cat-studio-header').innerText = '📂 Add Taxonomy Category';
        document.getElementById('cat-studio-input-id').value = '0';
        document.getElementById('cat-studio-input-name').value = '';
        document.getElementById('cat-studio-input-icon').value = '';
        document.getElementById('cat-reset-btn').classList.add('hidden');
    }

    // Handles Blog Manager edits populating
    function editBlog(post) {
        document.getElementById('blog-studio-header').innerText = '📰 Edit Blog Post';
        document.getElementById('blog-studio-input-id').value = post.id;
        document.getElementById('blog-studio-input-title').value = post.title;
        document.getElementById('blog-studio-input-author').value = post.author || '';
        document.getElementById('blog-studio-input-thumb').value = post.thumbnail || '';
        if (typeof updateBlogThumbPreview === 'function') {
            updateBlogThumbPreview(post.thumbnail || '');
        }
        document.getElementById('blog-studio-input-content').value = post.content;
        document.getElementById('blog-reset-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetBlogForm() {
        document.getElementById('blog-studio-header').innerText = '📰 Create Blog Post';
        document.getElementById('blog-studio-input-id').value = '0';
        document.getElementById('blog-studio-input-title').value = '';
        document.getElementById('blog-studio-input-author').value = '';
        const defaultThumb = 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600';
        document.getElementById('blog-studio-input-thumb').value = defaultThumb;
        if (typeof updateBlogThumbPreview === 'function') {
            updateBlogThumbPreview(defaultThumb);
        }
        document.getElementById('blog-studio-input-content').value = '';
        document.getElementById('blog-reset-btn').classList.add('hidden');
    }

    // Handles Coupon Code edits populating
    function editCoupon(coup) {
        document.getElementById('coup-studio-header').innerText = '🎟️ Edit Coupon Code';
        document.getElementById('coup-studio-input-id').value = coup.id;
        document.getElementById('coup-studio-input-code').value = coup.code;
        document.getElementById('coup-studio-input-type').value = coup.type;
        document.getElementById('coup-studio-input-value').value = coup.value;
        document.getElementById('coup-studio-input-max').value = coup.max_uses || '';
        document.getElementById('coup-studio-input-expiry').value = coup.expiry_date || '';
        document.getElementById('coup-reset-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetCouponForm() {
        document.getElementById('coup-studio-header').innerText = '🎟️ Coupon Code Generator';
        document.getElementById('coup-studio-input-id').value = '0';
        document.getElementById('coup-studio-input-code').value = '';
        document.getElementById('coup-studio-input-type').value = 'percentage';
        document.getElementById('coup-studio-input-value').value = '';
        document.getElementById('coup-studio-input-max').value = '';
        document.getElementById('coup-studio-input-expiry').value = '';
        document.getElementById('coup-reset-btn').classList.add('hidden');
    }

    // Handles Collection Code edits populating
    function editCollection(col, items) {
        document.getElementById('coll-studio-header').innerText = '🎨 Edit Curated Collection';
        document.getElementById('coll-studio-input-id').value = col.id;
        document.getElementById('coll-studio-input-title').value = col.title;
        document.getElementById('coll-studio-input-desc').value = col.description || '';
        
        // Reset check boxes
        document.querySelectorAll("input[id^='coll-prod-chk-']").forEach(chk => {
            chk.checked = false;
        });

        // Set active checks
        if (items && Array.isArray(items)) {
            items.forEach(pid => {
                const el = document.getElementById('coll-prod-chk-' + pid);
                if (el) el.checked = true;
            });
        }
        document.getElementById('coll-reset-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetCollectionForm() {
        document.getElementById('coll-studio-header').innerText = '🎨 Curated Collection Studio';
        document.getElementById('coll-studio-input-id').value = '0';
        document.getElementById('coll-studio-input-title').value = '';
        document.getElementById('coll-studio-input-desc').value = '';
        
        document.querySelectorAll("input[id^='coll-prod-chk-']").forEach(chk => {
            chk.checked = false;
        });
        document.getElementById('coll-reset-btn').classList.add('hidden');
    }

    // Handles Catalog Multi-checkbox selects
    function toggleSelectAllProducts(master) {
        document.querySelectorAll('.catalog-chk-child').forEach(chk => {
            chk.checked = master.checked;
        });
    }

    // Catalog review rejection modal toggles
    function triggerReviewRejectionModal(id, title) {
        document.getElementById('decline-modal-input-id').value = id;
        document.getElementById('decline-modal-subtitle').innerText = "Script: " + title;
        document.getElementById('decline-feedback-modal-overlay').classList.remove('hidden');
    }

    function closeReviewRejectionModal() {
        document.getElementById('decline-feedback-modal-overlay').classList.add('hidden');
    }

    // Chat auto-scroll
    const chatBox = document.getElementById('chat-history-box');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Blog Manager dynamic modes and uploader
    function setBlogThumbMode(mode) {
        const paneUrl = document.getElementById('blog-thumb-input-pane-url');
        const paneFile = document.getElementById('blog-thumb-input-pane-file');
        const btnUrl = document.getElementById('btn-blog-thumb-mode-url');
        const btnFile = document.getElementById('btn-blog-thumb-mode-file');
        
        if (mode === 'url') {
            if (paneUrl) paneUrl.classList.remove('hidden');
            if (paneFile) paneFile.classList.add('hidden');
            if (btnUrl) btnUrl.className = "px-2 py-0.5 text-[9px] font-bold text-white bg-[#5cb85c] rounded-l border border-[#5cb85c] outline-none";
            if (btnFile) btnFile.className = "px-2 py-0.5 text-[9px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-r border border-gray-300 outline-none";
        } else {
            if (paneUrl) paneUrl.classList.add('hidden');
            if (paneFile) paneFile.classList.remove('hidden');
            if (btnUrl) btnUrl.className = "px-2 py-0.5 text-[9px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-l border border-gray-300 outline-none";
            if (btnFile) btnFile.className = "px-2 py-0.5 text-[9px] font-bold text-white bg-[#5cb85c] rounded-r border border-[#5cb85c] outline-none";
        }
    }

    function updateBlogThumbPreview(url) {
        const img = document.getElementById('blog-thumb-preview-img');
        if (img) {
            img.src = url || 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const blogInput = document.getElementById('blog-thumb-file-input');
        const blogDropzone = document.getElementById('blog-thumb-dropzone');
        const blogText = document.getElementById('blog-thumb-upload-text');
        const blogProgress = document.getElementById('blog-thumb-upload-progress');
        const blogProgressBar = document.getElementById('blog-thumb-upload-progress-bar');
        
        if (blogDropzone && blogInput) {
            blogDropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                blogDropzone.classList.add('border-[#5cb85c]', 'bg-emerald-50/20');
            });
            blogDropzone.addEventListener('dragleave', () => {
                blogDropzone.classList.remove('border-[#5cb85c]', 'bg-emerald-50/20');
            });
            blogDropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                blogDropzone.classList.remove('border-[#5cb85c]', 'bg-emerald-50/20');
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    handleBlogThumbUpload(e.dataTransfer.files[0]);
                }
            });
            blogInput.addEventListener('change', () => {
                if (blogInput.files && blogInput.files[0]) {
                    handleBlogThumbUpload(blogInput.files[0]);
                }
            });
        }

        function handleBlogThumbUpload(file) {
            if (!file.type.startsWith('image/')) {
                alert('Invalid file format. Thumbnail must be an image.');
                return;
            }
            if (blogText) blogText.innerText = 'Converting & Uploading...';
            if (blogProgress) blogProgress.classList.remove('hidden');
            
            // convertToWebP helper is loaded globally in index.php layout
            if (typeof convertToWebP === 'function') {
                convertToWebP(file).then(({ blob, name }) => {
                    sendBlogFile(blob, name);
                });
            } else {
                sendBlogFile(file, file.name);
            }
        }

        function sendBlogFile(fileBlob, fileName) {
            const formData = new FormData();
            formData.append('file', fileBlob, fileName);
            formData.append('type', 'blog');
            const titleVal = document.getElementById('blog-studio-input-title').value;
            if (titleVal) {
                formData.append('title', titleVal);
            }
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php?action=image_upload_ajax', true);
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable && blogProgressBar) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    blogProgressBar.style.width = percent + '%';
                }
            };
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.status === 'success') {
                            if (blogText) blogText.innerText = '📁 Upload Complete!';
                            if (blogProgress) blogProgress.classList.add('hidden');
                            const thumbInput = document.getElementById('blog-studio-input-thumb');
                            if (thumbInput) thumbInput.value = res.url;
                            updateBlogThumbPreview(res.url);
                        } else {
                            if (blogText) blogText.innerText = '📁 Click or Drop Blog Thumbnail';
                            if (blogProgress) blogProgress.classList.add('hidden');
                            alert('Upload failed: ' + res.message);
                        }
                    } catch(e) {
                        if (blogText) blogText.innerText = '📁 Click or Drop Blog Thumbnail';
                        if (blogProgress) blogProgress.classList.add('hidden');
                        alert('Malformed server response.');
                    }
                } else {
                    if (blogText) blogText.innerText = '📁 Click or Drop Blog Thumbnail';
                    if (blogProgress) blogProgress.classList.add('hidden');
                    alert('HTTP Error ' + xhr.status);
                }
            };
            xhr.onerror = function() {
                if (blogText) blogText.innerText = '📁 Click or Drop Blog Thumbnail';
                if (blogProgress) blogProgress.classList.add('hidden');
                alert('Network transfer failure.');
            };
            xhr.send(formData);
        }
    });

    // Copy SEO URLs to clipboard
    function copySeoURL(id) {
        const copyText = document.getElementById(id);
        if (copyText) {
            copyText.select();
            copyText.setSelectionRange(0, 99999); // For mobile devices
            navigator.clipboard.writeText(copyText.value).then(function() {
                alert("Copied to clipboard: " + copyText.value);
            }).catch(function() {
                // Fallback
                try {
                    document.execCommand("copy");
                    alert("Copied to clipboard: " + copyText.value);
                } catch (err) {
                    alert("Failed to copy URL. Please copy it manually.");
                }
            });
        }
    }

    // Toggle SEO and Tracking sub-tabs
    function switchSeoTab(tabId) {
        // Hide all panels
        document.querySelectorAll('.seo-tab-panel').forEach(p => p.classList.add('hidden'));
        // Show selected panel
        const activePanel = document.getElementById('seo-panel-' + tabId);
        if (activePanel) activePanel.classList.remove('hidden');

        // Reset all buttons
        document.querySelectorAll('.seo-tab-btn').forEach(btn => {
            btn.className = "seo-tab-btn px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider rounded transition-all whitespace-nowrap outline-none border bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200";
        });
        // Active styling for the clicked button
        const activeBtn = document.getElementById('seo-btn-' + tabId);
        if (activeBtn) {
            activeBtn.className = "seo-tab-btn px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider rounded transition-all whitespace-nowrap outline-none border bg-slate-900 text-white border-slate-900";
        }
    }

    function copyToClipboard(elementId) {
        const text = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(text).then(function() {
            alert("Copied to clipboard: " + text);
        }).catch(function() {
            try {
                const range = document.createRange();
                range.selectNode(document.getElementById(elementId));
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand("copy");
                window.getSelection().removeAllRanges();
                alert("Copied to clipboard: " + text);
            } catch (err) {
                alert("Failed to copy automatically. Please select and copy manually.");
            }
        });
    }
</script>
