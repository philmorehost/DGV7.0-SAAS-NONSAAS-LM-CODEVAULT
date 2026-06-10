<?php
// Marketplace View for CodeVault PHP (Codester Redesign)

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'latest';
$price_filter = isset($_GET['price']) ? trim($_GET['price']) : 'all';

$limit = 24;
$page_num = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page_num - 1) * $limit;

// Fetch stats for hero
$stat_products = $db->query("SELECT COUNT(*) FROM products WHERE status = 'approved'")->fetchColumn();
$stat_sellers = $db->query("SELECT COUNT(DISTINCT id) FROM users WHERE role = 'seller'")->fetchColumn();
$stat_downloads = $db->query("SELECT COUNT(*) FROM purchases")->fetchColumn();

// Fetch categories
$cat_stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $cat_stmt->fetchAll();

// Construct Query
$params = [];
$where_clauses = ["p.status = 'approved'"];

if (!empty($search)) {
    $where_clauses[] = "(p.title LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category !== 'all') {
    $where_clauses[] = "p.category = ?";
    $params[] = $category;
}

if ($price_filter === 'free') {
    $where_clauses[] = "p.price = 0";
} elseif ($price_filter === 'premium') {
    $where_clauses[] = "p.price > 0";
}

$where_str = implode(" AND ", $where_clauses);

// Total counts for pagination
$count_stmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $where_str");
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Sorting
$order_by = "p.id DESC";
if ($sort === 'popular') {
    $order_by = "real_sales DESC, p.sales_count DESC";
} elseif ($sort === 'rating') {
    $order_by = "p.rating DESC";
} elseif ($sort === 'price_asc') {
    $order_by = "p.price ASC";
} elseif ($sort === 'price_desc') {
    $order_by = "p.price DESC";
}

$query_str = "SELECT p.*, u.name as seller_name, u.is_verified as seller_verified, u.avatar_url as seller_avatar,
              (SELECT COUNT(*) FROM purchases pu WHERE pu.product_id = p.id) as real_sales
              FROM products p
              JOIN users u ON p.seller_id = u.id
              WHERE $where_str
              ORDER BY $order_by
              LIMIT $limit OFFSET $offset";

$prod_stmt = $db->prepare($query_str);
$prod_stmt->execute($params);
$products = $prod_stmt->fetchAll();

// Check if wishlist exists in user session
$user_wishlist = [];
if (is_logged_in()) {
    $wish_q = $db->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wish_q->execute([$_SESSION['user_id']]);
    $user_wishlist = $wish_q->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!-- Codester Hero Stats Banner -->
<div class="bg-white border border-gray-200 rounded-lg p-8 mb-8 shadow-sm">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
        <div>
            <h2 class="text-3xl font-black text-slate-900 tracking-tight leading-tight">Buy & Sell Premium Web Assets</h2>
            <p class="text-slate-500 text-xs mt-3 leading-relaxed">
                Discover clean-coded PHP scripts, mobile app templates, WordPress themes, plugins, and graphic packs. Vetted and instantly delivered to jumpstart your production.
            </p>
            <div class="mt-6 flex flex-wrap gap-6 text-slate-800 text-xs font-bold bg-transparent">
                <div class="flex items-center gap-2">
                    <span class="text-xl">💾</span>
                    <div>
                        <p class="text-[14px] font-mono leading-none"><?php echo number_format($stat_products); ?></p>
                        <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400 mt-1">Scripts & Themes</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 border-l border-gray-200 pl-6">
                    <span class="text-xl">🧑‍💻</span>
                    <div>
                        <p class="text-[14px] font-mono leading-none"><?php echo number_format($stat_sellers); ?></p>
                        <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400 mt-1">Active Sellers</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 border-l border-gray-200 pl-6">
                    <span class="text-xl">📥</span>
                    <div>
                        <p class="text-[14px] font-mono leading-none"><?php echo number_format($stat_downloads); ?></p>
                        <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400 mt-1">Downloads</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Big Search Form -->
        <div class="bg-[#1c2229] p-6 rounded-lg border border-slate-700/50">
            <h4 class="text-white text-xs font-bold uppercase tracking-wider mb-3">Find what you need</h4>
            <form method="GET" action="index.php" class="flex gap-2">
                <input type="hidden" name="page" value="marketplace">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <input 
                    type="text" 
                    name="search" 
                    value="<?php echo htmlspecialchars($search); ?>" 
                    placeholder="Search from php backend, react, flutter..." 
                    class="flex-1 px-3 py-2 rounded bg-slate-800 text-white text-xs border border-slate-700 outline-none focus:border-[#5cb85c] focus:bg-slate-900 transition-colors"
                >
                <button type="submit" class="px-5 py-2 bg-[#5cb85c] hover:bg-[#4cae4c] text-white font-bold rounded text-xs transition-colors shadow">
                    Search
                </button>
            </form>
            <div class="mt-3 flex flex-wrap gap-2 text-[10px] text-slate-400">
                <span>Trending:</span>
                <a href="index.php?page=marketplace&search=saas" class="hover:text-white underline">saas</a>,
                <a href="index.php?page=marketplace&search=laravel" class="hover:text-white underline">laravel</a>,
                <a href="index.php?page=marketplace&search=react" class="hover:text-white underline">react</a>,
                <a href="index.php?page=marketplace&search=gateway" class="hover:text-white underline">gateway</a>
            </div>
        </div>
    </div>
</div>

<!-- Category Icon Grid (homepage-like selector) -->
<?php if ($category === 'all' && empty($search)): ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4 mb-8">
        <?php 
        $cat_icons = [
            'Scripts' => ['icon' => '💾', 'bg' => 'bg-emerald-55 border-emerald-100', 'text' => 'text-emerald-700'],
            'Templates' => ['icon' => '🎨', 'bg' => 'bg-blue-50 border-blue-100', 'text' => 'text-blue-700'],
            'Plugins' => ['icon' => '🔌', 'bg' => 'bg-orange-50 border-orange-100', 'text' => 'text-orange-700'],
            'Mobile' => ['icon' => '📱', 'bg' => 'bg-indigo-50 border-indigo-100', 'text' => 'text-indigo-700'],
            'Themes' => ['icon' => '👁️', 'bg' => 'bg-purple-50 border-purple-100', 'text' => 'text-purple-700']
        ];
        foreach ($categories as $cat): 
            $theme = isset($cat_icons[$cat['name']]) ? $cat_icons[$cat['name']] : ['icon' => '📦', 'bg' => 'bg-slate-50 border-slate-100', 'text' => 'text-slate-700'];
        ?>
            <a href="index.php?page=marketplace&category=<?php echo urlencode($cat['name']); ?>" class="flex flex-col items-center justify-center p-4 bg-white border border-gray-200 rounded-lg hover:border-[#5cb85c] hover:shadow-md transition-all text-center">
                <span class="text-2xl mb-2"><?php echo $theme['icon']; ?></span>
                <span class="text-xs font-extrabold text-slate-800"><?php echo htmlspecialchars($cat['name']); ?></span>
                <?php 
                $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category = ? AND status = 'approved'");
                $cnt->execute([$cat['name']]);
                $c_val = $cnt->fetchColumn();
                ?>
                <span class="text-[9px] text-gray-400 mt-1 font-bold"><?php echo $c_val; ?> items</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Main Section Layout -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    
    <!-- Left Sidebar: Filters -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Category Selector -->
        <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
            <h3 class="font-extrabold text-xs text-gray-400 uppercase tracking-wider mb-3">Categories</h3>
            <div class="flex flex-col gap-1 text-xs">
                <a 
                    href="index.php?page=marketplace&category=all&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&price=<?php echo $price_filter; ?>" 
                    class="px-3 py-2 rounded font-bold flex justify-between items-center <?php echo $category === 'all' ? 'bg-[#5cb85c] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'; ?>"
                >
                    <span>All Marketplace</span>
                    <span class="text-[10px] px-2 py-0.5 rounded <?php echo $category === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'; ?>"><?php echo $stat_products; ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <?php 
                    $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category = ? AND status = 'approved'");
                    $cnt->execute([$cat['name']]);
                    $c_val = $cnt->fetchColumn();
                    ?>
                    <a 
                        href="index.php?page=marketplace&category=<?php echo urlencode($cat['name']); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&price=<?php echo $price_filter; ?>" 
                        class="px-3 py-2 rounded font-bold flex justify-between items-center <?php echo $category === $cat['name'] ? 'bg-[#5cb85c] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'; ?>"
                    >
                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        <span class="text-[10px] px-2 py-0.5 rounded <?php echo $category === $cat['name'] ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'; ?>"><?php echo $c_val; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Price filter -->
        <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
            <h3 class="font-extrabold text-xs text-gray-400 uppercase tracking-wider mb-3">Price Type</h3>
            <div class="flex flex-col gap-1 text-xs">
                <a 
                    href="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&price=all" 
                    class="px-3 py-2 rounded font-bold flex items-center justify-between <?php echo $price_filter === 'all' ? 'text-[#5cb85c] bg-emerald-50/50' : 'text-slate-600 hover:bg-slate-50'; ?>"
                >
                    <span>All Types</span>
                    <span>✓</span>
                </a>
                <a 
                    href="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&price=premium" 
                    class="px-3 py-2 rounded font-bold flex items-center justify-between <?php echo $price_filter === 'premium' ? 'text-[#5cb85c] bg-emerald-50/50' : 'text-slate-600 hover:bg-slate-50'; ?>"
                >
                    <span>Paid Premium</span>
                    <span>💎</span>
                </a>
                <a 
                    href="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&price=free" 
                    class="px-3 py-2 rounded font-bold flex items-center justify-between <?php echo $price_filter === 'free' ? 'text-[#5cb85c] bg-emerald-50/50' : 'text-slate-600 hover:bg-slate-50'; ?>"
                >
                    <span>Free Files</span>
                    <span>🎁</span>
                </a>
            </div>
        </div>
        
    </div>

    <!-- Right Side: Grid + Header sort + Pagination -->
    <div class="lg:col-span-3 space-y-6">
        
        <!-- Filter and Sort Top bar -->
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex flex-col sm:flex-row justify-between items-center gap-4 shadow-sm text-xs font-semibold">
            <p class="text-slate-500">
                Found <strong class="text-slate-900"><?php echo $total_products; ?></strong> products in <strong class="text-slate-900 capitalize"><?php echo htmlspecialchars($category); ?></strong>
            </p>
            
            <div class="flex items-center gap-3">
                <span class="text-slate-400">Sort By:</span>
                <select 
                    onchange="location = this.value;"
                    class="bg-white border border-gray-300 rounded px-2.5 py-1.5 text-xs text-slate-800 font-bold outline-none focus:border-[#5cb85c]"
                >
                    <option value="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&price=<?php echo $price_filter; ?>&sort=latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest Releases</option>
                    <option value="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&price=<?php echo $price_filter; ?>&sort=popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Top Sellers</option>
                    <option value="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&price=<?php echo $price_filter; ?>&sort=rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Best Rated</option>
                    <option value="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&price=<?php echo $price_filter; ?>&sort=price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&price=<?php echo $price_filter; ?>&sort=price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <!-- Empty state -->
            <div class="bg-white border border-gray-200 rounded-lg p-16 text-center shadow-sm">
                <span class="text-5xl">📦</span>
                <h3 class="font-bold text-gray-900 text-lg mt-4">No Digital Products Found</h3>
                <p class="text-xs text-slate-500 mt-2 max-w-sm mx-auto">We couldn't locate any approved scripts or items matching your category or search query.</p>
                <a href="index.php?page=marketplace" class="mt-6 inline-block bg-slate-900 hover:bg-slate-800 text-white font-bold px-5 py-2.5 rounded text-xs transition-colors shadow">
                    Reset Catalog View
                </a>
            </div>
        <?php else: ?>
            <!-- Products Grid Codester style -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach($products as $prod): ?>
                    <?php
                    $preview_images_arr = !empty($prod['preview_images']) ? json_decode($prod['preview_images'], true) : [];
                    if (!is_array($preview_images_arr)) $preview_images_arr = [];
                    $all_images = array_filter(array_merge([$prod['thumbnail']], $preview_images_arr));
                    if (empty($all_images)) $all_images = ['https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600'];
                    $js_all_images = json_encode($all_images);
                    
                    $is_wishlisted = in_array($prod['id'], $user_wishlist);
                    $original_price = floatval($prod['price']);
                    $has_discount = ($prod['discount_price'] !== null && floatval($prod['discount_price']) > 0);
                    $display_price = $has_discount ? floatval($prod['discount_price']) : $original_price;
                    ?>
                    
                    <!-- Codester Product Card -->
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-all flex flex-col h-full relative group">
                        
                        <!-- Thumbnail & Hover -->
                        <div class="aspect-[4/3] relative overflow-hidden bg-gray-50 border-b select-none">
                            <a href="index.php?page=product&id=<?php echo $prod['id']; ?>">
                                <img 
                                    src="<?php echo htmlspecialchars($prod['thumbnail']); ?>" 
                                    alt="<?php echo htmlspecialchars($prod['title']); ?>"
                                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    loading="lazy"
                                >
                            </a>
                            
                            <!-- Badges -->
                            <div class="absolute top-3 left-3 flex flex-col gap-1.5 z-10">
                                <?php if ($prod['is_featured'] == 1): ?>
                                    <span class="bg-orange-500 text-white text-[9px] font-black px-2 py-0.5 rounded shadow shadow-orange-500/10 uppercase tracking-wide">Featured</span>
                                <?php endif; ?>
                                <?php if ($has_discount): ?>
                                    <span class="bg-red-500 text-white text-[9px] font-black px-2 py-0.5 rounded shadow uppercase tracking-wide">Sale</span>
                                <?php endif; ?>
                                <?php if ($display_price == 0): ?>
                                    <span class="bg-emerald-500 text-white text-[9px] font-black px-2 py-0.5 rounded shadow uppercase tracking-wide">Free</span>
                                <?php endif; ?>
                            </div>

                            <!-- AJAX Wishlist button -->
                            <button 
                                onclick="ajaxToggleWishlist(<?php echo $prod['id']; ?>, this)"
                                class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/95 backdrop-blur flex items-center justify-center text-xs shadow-md border hover:bg-slate-50 transition-colors z-10 outline-none"
                                title="<?php echo $is_wishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>"
                            >
                                <span class="wishlist-icon text-base <?php echo $is_wishlisted ? 'text-red-500' : 'text-slate-400'; ?>">
                                    <?php echo $is_wishlisted ? '♥' : '♡'; ?>
                                </span>
                            </button>

                            <!-- Hover controls -->
                            <div class="absolute bottom-3 inset-x-3 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300 bg-slate-900/90 rounded p-2 flex gap-2 z-20 border border-slate-700 shadow shadow-black/10">
                                <?php if (!empty($prod['live_demo_url'])): ?>
                                    <a 
                                        href="<?php echo htmlspecialchars($prod['live_demo_url']); ?>" 
                                        target="_blank" 
                                        class="flex-1 bg-slate-800 hover:bg-slate-700 text-white py-1.5 px-2 text-center font-bold rounded text-[10px] transition-colors"
                                    >
                                        🌐 Demo
                                    </a>
                                <?php endif; ?>
                                <button 
                                    onclick="openLightboxModal(<?php echo htmlspecialchars(json_encode($prod)); ?>, <?php echo htmlspecialchars($js_all_images); ?>, event)"
                                    class="flex-1 bg-white hover:bg-gray-100 text-black py-1.5 px-2 text-center font-bold rounded text-[10px] transition-colors"
                                >
                                    🔍 Preview
                                </button>
                                <form method="POST" action="index.php?action=cart_add" class="flex shrink-0">
                                    <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                                    <button 
                                        type="submit" 
                                        class="bg-[#5cb85c] hover:bg-[#4cae4c] text-white p-1.5 rounded transition-colors flex items-center justify-center font-bold text-xs"
                                        title="Add to Cart"
                                    >
                                        🛒
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Info details -->
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <a href="index.php?page=marketplace&category=<?php echo urlencode($prod['category']); ?>" class="text-[9px] font-extrabold uppercase text-[#5cb85c] hover:underline"><?php echo htmlspecialchars($prod['category']); ?></a>
                                <div class="flex items-center gap-0.5 text-[10px]">
                                    <span class="text-amber-400">★</span>
                                    <span class="font-mono font-bold text-slate-800"><?php echo number_format($prod['rating'], 1); ?></span>
                                </div>
                            </div>
                            
                            <a href="index.php?page=product&id=<?php echo $prod['id']; ?>" class="block flex-1">
                                <h4 class="font-bold text-slate-900 group-hover:text-[#5cb85c] transition-colors line-clamp-1 mb-1.5 text-sm"><?php echo htmlspecialchars($prod['title']); ?></h4>
                                <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed mb-3"><?php echo htmlspecialchars(strip_tags($prod['description'])); ?></p>
                            </a>

                            <!-- Tags System Preview -->
                            <?php if (!empty($prod['tags'])): ?>
                                <div class="flex flex-wrap gap-1.5 mb-3.5">
                                    <?php 
                                    $tags_arr = explode(',', $prod['tags']);
                                    foreach (array_slice($tags_arr, 0, 3) as $tg): 
                                        $tg = trim($tg);
                                        if (empty($tg)) continue;
                                    ?>
                                        <a href="index.php?page=marketplace&search=<?php echo urlencode($tg); ?>" class="text-[8px] font-bold text-slate-400 bg-slate-100 hover:bg-slate-200 px-1.5 py-0.5 rounded transition-colors">#<?php echo htmlspecialchars($tg); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Seller details -->
                            <div class="flex items-center justify-between border-t pt-3 mt-auto">
                                <a href="index.php?page=seller_profile&id=<?php echo $prod['seller_id']; ?>" class="flex items-center gap-1.5 group/author">
                                    <div class="w-5 h-5 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600">
                                        <?php echo strtoupper(substr($prod['seller_name'], 0, 1)); ?>
                                    </div>
                                    <span class="text-[10px] text-slate-500 font-bold group-hover/author:text-slate-900 transition-colors">
                                        <?php echo htmlspecialchars($prod['seller_name']); ?>
                                        <?php if ($prod['seller_verified'] == 1): ?>
                                            <span class="text-[#5cb85c]" title="Verified Seller">✓</span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                                
                                <div class="flex items-baseline gap-1 text-right">
                                    <?php if ($has_discount): ?>
                                        <span class="text-[11px] line-through text-gray-400 font-bold"><?php echo format_price($original_price); ?></span>
                                        <span class="text-sm font-mono font-black text-red-500"><?php echo format_price($display_price); ?></span>
                                    <?php else: ?>
                                        <span class="text-sm font-mono font-black text-slate-900"><?php echo format_price($original_price); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Codester Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center items-center gap-1.5 text-xs font-bold text-slate-700 bg-transparent">
                    <?php if ($page_num > 1): ?>
                        <a href="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&price=<?php echo $price_filter; ?>&p=<?php echo $page_num - 1; ?>" class="px-3 py-2 bg-white border border-gray-200 rounded hover:border-[#5cb85c] hover:bg-[#5cb85c] hover:text-white transition-all shadow-sm">◀ Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a 
                            href="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&price=<?php echo $price_filter; ?>&p=<?php echo $i; ?>" 
                            class="px-3.5 py-2 border rounded transition-all <?php echo $page_num === $i ? 'bg-[#5cb85c] border-[#5cb85c] text-white' : 'bg-white border-gray-200 hover:border-[#5cb85c]'; ?>"
                        >
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <a href="index.php?page=marketplace&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&price=<?php echo $price_filter; ?>&p=<?php echo $page_num + 1; ?>" class="px-3 py-2 bg-white border border-gray-200 rounded hover:border-[#5cb85c] hover:bg-[#5cb85c] hover:text-white transition-all shadow-sm">Next ▶</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>

<!-- AJAX Wishlist Script logic -->
<script>
function ajaxToggleWishlist(productId, btn) {
    const icon = btn.querySelector('.wishlist-icon');
    
    // Construct Form Data
    const formData = new FormData();
    formData.append('product_id', productId);
    
    fetch('index.php?action=wishlist_ajax', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (data.status === 'added') {
                icon.innerText = '♥';
                icon.className = 'wishlist-icon text-base text-red-500';
                btn.title = 'Remove from Wishlist';
            } else {
                icon.innerText = '♡';
                icon.className = 'wishlist-icon text-base text-slate-400';
                btn.title = 'Add to Wishlist';
            }
        } else {
            // If unauthorized, trigger login modal
            openLoginModal();
        }
    })
    .catch(() => {
        alert('Wishlist sync failed.');
    });
}
</script>
