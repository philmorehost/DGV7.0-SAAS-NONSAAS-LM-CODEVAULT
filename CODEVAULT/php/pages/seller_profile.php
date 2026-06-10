<?php
// Public Seller Profile Page for CodeVault PHP
$seller_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch seller profile
$s_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$s_stmt->execute([$seller_id]);
$seller = $s_stmt->fetch();

if (!$seller) {
    echo "<div class='bg-white rounded border p-12 text-center text-xs text-slate-500 font-bold'>⚠️ Developer Profile Not Found.</div>";
    return;
}

// Check follow status
$is_following = false;
if (is_logged_in()) {
    $fl_stmt = $db->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
    $fl_stmt->execute([$_SESSION['user_id'], $seller_id]);
    $is_following = (bool)$fl_stmt->fetch();
}

// Followers count
$f_count_stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
$f_count_stmt->execute([$seller_id]);
$followers_count = $f_count_stmt->fetchColumn();

// Fetch seller's products
$p_stmt = $db->prepare("SELECT p.*, (SELECT COUNT(*) FROM purchases pu WHERE pu.product_id = p.id) as real_sales FROM products p WHERE p.seller_id = ? AND p.status = 'approved' ORDER BY p.id DESC");
$p_stmt->execute([$seller_id]);
$products = $p_stmt->fetchAll();
?>

<!-- Breadcrumbs -->
<nav class="text-xs text-gray-500 mb-6 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
    <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
    <span class="text-gray-400 font-bold">/</span>
    <span class="text-slate-700 font-semibold">Developers</span>
    <span class="text-gray-400 font-bold">/</span>
    <span class="text-slate-550 truncate font-semibold"><?php echo htmlspecialchars($seller['name']); ?></span>
</nav>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    
    <!-- Left Profile Widget -->
    <div class="lg:col-span-1 bg-white border border-gray-200 rounded p-6 shadow-sm flex flex-col items-center text-center space-y-4 self-start">
        <div class="w-20 h-20 rounded bg-slate-100 text-[#5cb85c] flex items-center justify-center font-black text-3xl shadow-inner border overflow-hidden">
            <?php if (!empty($seller['avatar_url'])): ?>
                <img src="<?php echo htmlspecialchars($seller['avatar_url']); ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <?php echo strtoupper(substr($seller['name'], 0, 1)); ?>
            <?php endif; ?>
        </div>
        
        <div>
            <h2 class="font-extrabold text-slate-900 text-lg flex items-center justify-center gap-1">
                <?php echo htmlspecialchars($seller['name']); ?>
                <?php if ($seller['is_verified']): ?>
                    <span class="text-blue-500 text-sm font-extrabold" title="Verified Professional Developer">✓</span>
                <?php endif; ?>
            </h2>
            <p class="text-[10px] text-[#5cb85c] font-bold uppercase tracking-wider mt-0.5">Level 4 Vendor</p>
            <span class="block text-[10px] text-slate-400 mt-2 font-semibold font-mono"><?php echo $followers_count; ?> Followers</span>
        </div>

        <form method="POST" action="index.php?action=follow_seller" class="w-full">
            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
            <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded text-xs font-bold shadow flex items-center justify-center gap-1.5 transition-colors">
                👤 <?php echo $is_following ? 'Unfollow' : 'Follow'; ?>
            </button>
        </form>

        <hr class="w-full border-gray-150">

        <div class="w-full text-left space-y-2 text-xs">
            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Developer Biography</span>
            <p class="text-slate-600 leading-relaxed font-normal whitespace-pre-wrap"><?php echo htmlspecialchars($seller['bio'] ?: 'No biography details provided.'); ?></p>
            <span class="text-[9px] font-bold text-slate-450 uppercase tracking-widest block pt-2">Joined Date</span>
            <p class="text-slate-650 font-semibold font-mono"><?php echo date('F Y', strtotime($seller['created_at'])); ?></p>
        </div>
    </div>
    
    <!-- Right Grid: Developer's Marketplace Listings -->
    <div class="lg:col-span-3 space-y-6">
        <div>
            <h3 class="font-black text-slate-900 text-xl tracking-tight leading-none mb-1">Developer Storefront</h3>
            <p class="text-xs text-slate-500">Explore digital products, templates, and scripts created by <?php echo htmlspecialchars($seller['name']); ?>.</p>
        </div>

        <?php if (empty($products)): ?>
            <div class="text-center py-16 bg-white border border-gray-200 rounded text-slate-400 font-bold text-xs">
                No active products uploaded by this developer yet.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach($products as $p): ?>
                    <!-- Product Card Codester Style -->
                    <div class="bg-white border border-gray-200 rounded shadow-sm hover:shadow transition-all flex flex-col justify-between overflow-hidden relative group">
                        
                        <!-- Thumbnail Frame -->
                        <div class="block aspect-[16/10] overflow-hidden bg-slate-100 relative">
                             <img src="<?php echo htmlspecialchars($p['thumbnail']); ?>" class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($p['title']); ?> Thumbnail" referrerpolicy="no-referrer">
                            
                            <!-- Hover Overlay standard demo / add buttons -->
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                <?php if (!empty($p['live_demo_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($p['live_demo_url']); ?>" target="_blank" class="px-3 py-1.5 bg-slate-900 text-white text-[10px] font-bold uppercase rounded hover:bg-slate-800 transition-colors">Demo</a>
                                <?php endif; ?>
                                <a href="index.php?page=product&id=<?php echo $p['id']; ?>" class="px-3 py-1.5 bg-[#5cb85c] text-white text-[10px] font-bold uppercase rounded hover:bg-[#4cae4c] transition-colors">Details</a>
                            </div>
                        </div>
                        
                        <!-- Details Panel -->
                        <div class="p-4 space-y-2">
                            <span class="text-[9px] uppercase tracking-wider text-[#5cb85c] font-black bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100"><?php echo htmlspecialchars($p['category']); ?></span>
                            <h4 class="font-bold text-xs text-slate-900 hover:text-slate-950 truncate pt-1">
                                <a href="index.php?page=product&id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></a>
                            </h4>
                            
                            <div class="flex justify-between items-center text-[10px] pt-1">
                                <span class="text-slate-500 font-semibold"><?php echo $p['real_sales'] + $p['sales_count']; ?> Sales</span>
                                <span class="font-mono font-black text-slate-900 bg-slate-50 px-1.5 py-0.5 rounded">
                                    <?php echo format_price($p['discount_price'] ?: $p['price']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>
