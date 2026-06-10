<?php
// Product Detail Page for CodeVault PHP
$prod_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $db->prepare("SELECT p.*, u.name as seller_name, u.is_verified as seller_verified, u.id as seller_id, u.avatar_url as seller_avatar,
                      (SELECT COUNT(*) FROM purchases pu WHERE pu.product_id = p.id) as real_sales
                      FROM products p
                      JOIN users u ON p.seller_id = u.id
                      WHERE p.id = ?");
$stmt->execute([$prod_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='bg-white rounded-lg p-16 text-center border border-gray-200 shadow-sm'><span class='text-4xl'>⚠️</span><h3 class='font-bold mt-4 text-lg text-slate-800'>Product Not Found</h3><p class='text-xs text-slate-500 mt-1'>The requested item has been deleted or moved. <a href='index.php' class='text-[#5cb85c] font-bold hover:underline'>Go back home</a>.</p></div>";
    return;
}

// Increment views_count in database on page load
$db->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?")->execute([$product['id']]);
$product['views_count']++; // Sync local variable

// Fetch Wishlist status
$is_wishlisted = false;
if (is_logged_in()) {
    $ws_stmt = $db->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
    $ws_stmt->execute([$_SESSION['user_id'], $product['id']]);
    $is_wishlisted = (bool)$ws_stmt->fetch();
}

// Fetch Reviews with Sorting support (Most Recent, Highest Rated, Lowest Rated)
$sort_reviews = isset($_GET['sort_reviews']) ? $_GET['sort_reviews'] : 'recent';
$order_clause = "ORDER BY r.created_at DESC";

if ($sort_reviews === 'highest') {
    $order_clause = "ORDER BY r.rating DESC, r.created_at DESC";
} elseif ($sort_reviews === 'lowest') {
    $order_clause = "ORDER BY r.rating ASC, r.created_at DESC";
}

$rev_stmt = $db->prepare("SELECT r.*, u.name as reviewer_name, u.avatar_url as reviewer_avatar FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? $order_clause");
$rev_stmt->execute([$product['id']]);
$reviews = $rev_stmt->fetchAll();

// Product screenshots parsing
$preview_images_arr = !empty($product['preview_images']) ? json_decode($product['preview_images'], true) : [];
if (!is_array($preview_images_arr)) $preview_images_arr = [];
$all_images = array_filter(array_merge([$product['thumbnail']], $preview_images_arr));
if (empty($all_images)) $all_images = ['https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600'];
$js_all_images = json_encode($all_images);

// Check if user has purchased this product
$has_purchased = false;
if (is_logged_in()) {
    $pur_stmt = $db->prepare("SELECT 1 FROM purchases WHERE buyer_id = ? AND product_id = ?");
    $pur_stmt->execute([$_SESSION['user_id'], $product['id']]);
    $has_purchased = (bool)$pur_stmt->fetch();
}

// Check if buyer follows this seller
$is_following = false;
if (is_logged_in()) {
    $fl_stmt = $db->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
    $fl_stmt->execute([$_SESSION['user_id'], $product['seller_id']]);
    $is_following = (bool)$fl_stmt->fetch();
}

// Fetch seller followers count
$followers_stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
$followers_stmt->execute([$product['seller_id']]);
$followers_count = $followers_stmt->fetchColumn();

// Fetch seller other products
$seller_prods_stmt = $db->prepare("SELECT * FROM products WHERE seller_id = ? AND id != ? AND status = 'approved' LIMIT 2");
$seller_prods_stmt->execute([$product['seller_id'], $product['id']]);
$seller_prods = $seller_prods_stmt->fetchAll();

// Fetch related items in same category
$related_stmt = $db->prepare("SELECT p.*, u.name as seller_name FROM products p JOIN users u ON p.seller_id = u.id WHERE p.category = ? AND p.id != ? AND p.status = 'approved' LIMIT 3");
$related_stmt->execute([$product['category'], $product['id']]);
$related_products = $related_stmt->fetchAll();

// Helper: Youtube URL parse ID
if (!function_exists('get_youtube_id')) {
    function get_youtube_id($url) {
        $regExp = '/^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/';
        preg_match($regExp, $url, $matches);
        return (isset($matches[2]) && strlen($matches[2]) == 11) ? $matches[2] : '';
    }
}

// Flash Sale Checker
$is_on_sale = false;
if (!empty($product['discount_price']) && floatval($product['discount_price']) > 0) {
    if (empty($product['sale_ends_at']) || strtotime($product['sale_ends_at']) > time()) {
        $is_on_sale = true;
    }
}

// Tags parser
$tags_arr = [];
if (!empty($product['tags'])) {
    $tags_arr = array_filter(array_map('trim', explode(',', $product['tags'])));
}
?>

<!-- Breadcrumbs -->
<nav class="text-xs text-gray-500 mb-6 flex flex-wrap items-center gap-1 bg-white px-4 py-3 border border-gray-200 rounded">
    <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
    <span class="text-gray-400 font-bold">/</span>
    <a href="index.php?page=marketplace&category=<?php echo urlencode($product['category']); ?>" class="hover:text-slate-900 transition-colors"><?php echo htmlspecialchars($product['category']); ?></a>
    <span class="text-gray-400 font-bold">/</span>
    <span class="text-slate-700 truncate font-semibold"><?php echo htmlspecialchars($product['title']); ?></span>
</nav>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: 60% Width (Images, Video, Description Tabs, Related Items) -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Premium Image Viewer / Carousel Container -->
        <div class="bg-white rounded border border-gray-200 overflow-hidden shadow-sm">
            <!-- Active Image View Frame -->
            <div class="aspect-video bg-slate-950 relative group cursor-pointer overflow-hidden" onclick="openLightboxModal(<?php echo htmlspecialchars(json_encode($product)); ?>, <?php echo htmlspecialchars($js_all_images); ?>)">
                <img 
                    id="prod-detail-active-img" 
                    src="<?php echo htmlspecialchars($all_images[0]); ?>" 
                    alt="<?php echo htmlspecialchars($product['title']); ?> Active screenshot outline"
                    class="w-full h-full object-cover transition-all duration-300 group-hover:scale-[1.02]"
                    referrerpolicy="no-referrer"
                >
                
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/35 to-transparent p-4 flex justify-between items-center">
                    <div class="bg-[#5cb85c] text-white text-[9px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded">CodeVault Certified</div>
                    <div id="lightbox-counter" class="bg-black/50 text-white/90 text-[10px] font-mono font-bold px-2 py-0.5 rounded">1 / <?php echo count($all_images); ?> Screenshots</div>
                </div>

                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <span class="bg-white text-slate-800 font-bold rounded px-4 py-2 shadow-lg text-xs transform scale-95 group-hover:scale-100 transition-transform">
                        🔎 Click to Preview Fullscreen Slides
                    </span>
                </div>
            </div>

            <!-- Thumbnail Carousel Bar -->
            <?php if (count($all_images) > 1): ?>
                <div class="p-3 border-t border-gray-150 bg-slate-50 flex gap-2.5 overflow-x-auto select-none">
                    <?php foreach($all_images as $index => $img_url): ?>
                        <button 
                            onclick="setDetailActiveImage(<?php echo $index; ?>, '<?php echo htmlspecialchars($img_url); ?>')"
                            class="thumb-btn-class w-20 h-14 rounded border-2 <?php echo $index === 0 ? 'border-[#5cb85c] opacity-100' : 'border-transparent opacity-75 hover:opacity-100'; ?> flex-shrink-0 transition-all overflow-hidden"
                            id="thumb-btn-<?php echo $index; ?>"
                        >
                            <img src="<?php echo htmlspecialchars($img_url); ?>" class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($product['title']); ?> - Gallery Screenshot <?php echo $index + 1; ?>" referrerpolicy="no-referrer">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Codester Tabbed Section -->
        <div class="bg-white border border-gray-200 rounded shadow-sm overflow-hidden">
            <!-- Tabs Menu -->
            <div class="flex border-b border-gray-200 bg-slate-50 text-slate-600 text-xs font-bold select-none flex-wrap">
                <button onclick="switchTab('description')" id="tab-btn-description" class="px-5 py-3 border-r border-gray-200 bg-white text-slate-900 border-b-2 border-b-[#5cb85c] hover:bg-white transition-colors focus:outline-none">
                    📄 Description
                </button>
                <button onclick="switchTab('reviews')" id="tab-btn-reviews" class="px-5 py-3 border-r border-gray-200 hover:bg-white hover:text-slate-900 transition-colors border-b-2 border-b-transparent focus:outline-none">
                    ⭐ Reviews (<?php echo count($reviews); ?>)
                </button>
                <button onclick="switchTab('comments')" id="tab-btn-comments" class="px-5 py-3 border-r border-gray-200 hover:bg-white hover:text-slate-900 transition-colors border-b-2 border-b-transparent focus:outline-none">
                    💬 Comments & Pre-Sales Q&A
                </button>
                <button onclick="switchTab('changelog')" id="tab-btn-changelog" class="px-5 py-3 hover:bg-white hover:text-slate-900 transition-colors border-b-2 border-b-transparent focus:outline-none">
                    ⚙️ Version Log
                </button>
            </div>

            <!-- Tab Contents -->
            <div class="p-6 md:p-8">
                
                <!-- Description Tab Content -->
                <div id="tab-content-description" class="tab-pane space-y-6">
                    <h2 class="text-xl font-bold text-slate-900 border-b border-gray-100 pb-3"><?php echo htmlspecialchars($product['title']); ?></h2>
                    
                    <div class="text-base text-slate-600 leading-relaxed font-normal whitespace-pre-wrap description-body"><?php echo htmlspecialchars($product['description']); ?></div>
                    
                    <!-- YouTube Video Blueprint -->
                    <?php if (!empty($product['live_demo_url']) && (strpos($product['live_demo_url'], 'youtube') !== false || strpos($product['live_demo_url'], 'youtu.be') !== false)): ?>
                        <?php $yt_id = get_youtube_id($product['live_demo_url']); ?>
                        <?php if ($yt_id): ?>
                            <div class="mt-8 pt-6 border-t border-gray-100">
                                <h4 class="font-bold text-sm text-gray-900 tracking-tight mb-4 flex items-center gap-1">
                                    <span class="text-red-500 text-lg">▶</span> Video Blueprint & Walkthrough
                                </h4>
                                <div class="aspect-video bg-black rounded overflow-hidden shadow-sm">
                                    <iframe 
                                        width="100%" 
                                        height="100%" 
                                        src="https://www.youtube.com/embed/<?php echo $yt_id; ?>" 
                                        title="YouTube video player" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen
                                        class="w-full h-full"
                                    ></iframe>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Reviews Tab Content -->
                <div id="tab-content-reviews" class="tab-pane hidden space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-3">
                        <h3 class="font-bold text-lg text-slate-900">Audited Buyer Reviews</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sort:</span>
                            <select 
                                onchange="location.href='index.php?page=product&id=<?php echo $product['id']; ?>&sort_reviews=' + this.value + '#tab-btn-reviews'"
                                class="px-2.5 py-1 text-xs border border-gray-200 bg-white text-slate-700 focus:border-[#5cb85c] cursor-pointer outline-none rounded"
                            >
                                <option value="recent" <?php echo $sort_reviews === 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                                <option value="highest" <?php echo $sort_reviews === 'highest' ? 'selected' : ''; ?>>Highest Rated</option>
                                <option value="lowest" <?php echo $sort_reviews === 'lowest' ? 'selected' : ''; ?>>Lowest Rated</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (is_logged_in() && $has_purchased): ?>
                        <!-- Submit a Review Form -->
                        <form method="POST" action="index.php?action=review_add" class="p-5 bg-slate-50 border border-gray-200 rounded space-y-4">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <h4 class="font-bold text-xs text-slate-500 uppercase tracking-wider">Share Your Purchase Experience</h4>
                            
                            <div class="flex items-center gap-3">
                                <label class="text-xs font-bold text-slate-700">Rating:</label>
                                <select name="rating" required class="px-2.5 py-1 rounded border outline-none bg-white text-xs text-gray-800 font-bold focus:border-[#5cb85c]">
                                    <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                                    <option value="4">⭐⭐⭐⭐ (4/5)</option>
                                    <option value="3">⭐⭐⭐ (3/5)</option>
                                    <option value="2">⭐⭐ (2/5)</option>
                                    <option value="1">⭐ (1/5)</option>
                                </select>
                            </div>

                            <div class="space-y-1.5">
                                <textarea name="comment" required placeholder="Describe configurations, build quality, code structure..." rows="3" class="w-full px-3 py-2 rounded border border-gray-200 focus:border-[#5cb85c] bg-white outline-none text-xs leading-relaxed transition-all shadow-inner"></textarea>
                            </div>

                            <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs transition-all active:scale-95 shadow">
                                Submit Audit Review
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (empty($reviews)): ?>
                        <div class="text-center py-10 bg-slate-50/50 rounded border border-dashed border-gray-200">
                            <span class="text-3xl">⭐</span>
                            <p class="text-xs text-slate-450 font-bold mt-2">No Reviews Yet</p>
                            <p class="text-[10px] text-slate-400 mt-1 max-w-xs mx-auto">Only verified buyers who completed a successful transaction can leave reviews.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($reviews as $rev): ?>
                                <div class="p-4 bg-slate-50/40 border border-gray-200 rounded flex gap-4">
                                    <div class="w-9 h-9 rounded-full bg-slate-200 flex-shrink-0 flex items-center justify-center overflow-hidden">
                                        <?php if (!empty($rev['reviewer_avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($rev['reviewer_avatar']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="text-xs font-bold text-slate-500"><?php echo strtoupper(substr($rev['reviewer_name'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <div class="flex justify-between items-center flex-wrap gap-2">
                                            <h5 class="font-bold text-xs text-slate-800"><?php echo htmlspecialchars($rev['reviewer_name']); ?></h5>
                                            <div class="text-xs text-yellow-400">
                                                <?php echo str_repeat('★', intval($rev['rating'])) . str_repeat('☆', 5 - intval($rev['rating'])); ?>
                                            </div>
                                        </div>
                                        <p class="text-xs text-slate-600 leading-relaxed"><?php echo htmlspecialchars($rev['comment']); ?></p>
                                        <span class="block text-[9px] font-semibold text-slate-400 font-mono"><?php echo date('M d, Y - H:i', strtotime($rev['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Comments Tab Content (Pre-Sales Q&A) -->
                <div id="tab-content-comments" class="tab-pane hidden space-y-6">
                    <h3 class="font-bold text-lg text-slate-900 border-b border-gray-100 pb-3">Pre-Sales Discussion / Public Comments</h3>
                    
                    <!-- Pre-sales mock Q&A list -->
                    <div class="space-y-4">
                        <div class="p-4 bg-slate-50 border border-gray-200 rounded space-y-2">
                            <div class="flex justify-between text-xs flex-wrap gap-1">
                                <span class="font-bold text-slate-800">👤 tech_enthusiast</span>
                                <span class="text-[10px] text-gray-400 font-semibold font-mono">2 days ago</span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed font-normal">Hello, does this script support MySQL 8.0 and PHP 8.2? I want to make sure before buying.</p>
                            <div class="pl-4 border-l-2 border-[#5cb85c] mt-2 space-y-1">
                                <div class="flex justify-between text-xs">
                                    <span class="font-bold text-[#5cb85c]">👑 <?php echo htmlspecialchars($product['seller_name']); ?> (Seller)</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed font-normal">Yes, it has been fully tested and supports PHP 8.1 - 8.3 and MySQL 5.7 to 8.0. Drop a support ticket if you run into any setup hiccups!</p>
                            </div>
                        </div>
                        
                        <div class="p-4 bg-slate-50 border border-gray-200 rounded space-y-2">
                            <div class="flex justify-between text-xs flex-wrap gap-1">
                                <span class="font-bold text-slate-800">👤 web_innovator</span>
                                <span class="text-[10px] text-gray-400 font-semibold font-mono">1 week ago</span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed font-normal">Hi seller, do you offer setup customization services if we need custom database tables added?</p>
                            <div class="pl-4 border-l-2 border-[#5cb85c] mt-2 space-y-1">
                                <div class="flex justify-between text-xs">
                                    <span class="font-bold text-[#5cb85c]">👑 <?php echo htmlspecialchars($product['seller_name']); ?> (Seller)</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed font-normal">Hi! Yes, I do custom installations and feature modifications. You can contact me via the support/profile panel after purchase.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Ask a Question form -->
                    <div class="p-5 border border-gray-200 bg-white rounded space-y-3">
                        <h4 class="font-bold text-xs text-slate-500 uppercase tracking-wider">Ask a pre-sales question</h4>
                        <textarea id="mock-comment-textarea" placeholder="Type your question here. Public comments must respect marketplace terms..." rows="3" class="w-full px-3 py-2 rounded border border-gray-250 outline-none text-xs focus:border-[#5cb85c] shadow-inner"></textarea>
                        <button onclick="submitMockComment()" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs transition-colors">Post Question</button>
                        <p id="mock-comment-alert" class="text-xs text-[#5cb85c] font-bold hidden">🎉 Comment submitted successfully! (Awaiting moderation check)</p>
                    </div>
                </div>

                <!-- Version / Change Log Tab Content -->
                <div id="tab-content-changelog" class="tab-pane hidden space-y-6">
                    <h3 class="font-bold text-lg text-slate-900 border-b border-gray-100 pb-3">Changelog & Version Timeline</h3>
                    
                    <div class="relative border-l border-gray-200 pl-6 ml-3 space-y-8 text-xs">
                        
                        <!-- Log entry active -->
                        <div class="relative">
                            <span class="absolute -left-9 top-0.5 w-5 h-5 rounded-full bg-[#5cb85c] border-4 border-white text-white flex items-center justify-center shadow-sm"></span>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-sm text-slate-900">v<?php echo htmlspecialchars($product['version']); ?></span>
                                    <span class="bg-emerald-50 text-[#5cb85c] font-bold text-[9px] px-1.5 py-0.5 rounded border border-emerald-150">Current Release</span>
                                </div>
                                <span class="block text-[10px] text-gray-400 font-mono"><?php echo date('M d, Y', strtotime($product['created_at'])); ?></span>
                                <ul class="list-disc list-inside text-slate-600 mt-2 space-y-1">
                                    <li>Initial release and deployment on CodeVault</li>
                                    <li>Pre-compiled production assets mapped</li>
                                    <li>Responsive Tailwind CSS UI layout structure verified</li>
                                    <li>Security configurations and folder directory rules tested</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Previous logs -->
                        <div class="relative opacity-60">
                            <span class="absolute -left-9 top-0.5 w-5 h-5 rounded-full bg-slate-300 border-4 border-white text-white flex items-center justify-center shadow-sm"></span>
                            <div class="space-y-1">
                                <span class="font-black text-sm text-slate-900">v0.9.0-beta</span>
                                <span class="block text-[10px] text-gray-400 font-mono">Alpha build testing stage</span>
                                <ul class="list-disc list-inside text-slate-500 mt-2 space-y-1">
                                    <li>Core codebase setup and routes dispatcher verification</li>
                                    <li>Dummy database schemas integrated for buyer transactions</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <!-- Tags List display pills -->
        <?php if (!empty($tags_arr)): ?>
            <div class="bg-white border border-gray-200 rounded p-5 shadow-sm space-y-2.5">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Tagged Taxonomies</span>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($tags_arr as $tag): ?>
                        <a href="index.php?page=marketplace&q=<?php echo urlencode($tag); ?>" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold transition-colors">
                            🏷️ <?php echo htmlspecialchars($tag); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Related Items Widget -->
        <div class="space-y-4">
            <h3 class="font-black text-slate-900 text-base uppercase tracking-tight flex items-center gap-1.5">
                📦 You May Also Like
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php if (empty($related_products)): ?>
                    <div class="col-span-3 text-center py-8 bg-white border border-gray-200 rounded text-slate-400 text-xs font-bold">
                        No similar products found in this category.
                    </div>
                <?php else: ?>
                    <?php foreach ($related_products as $rp): ?>
                        <div class="bg-white border border-gray-200 rounded shadow-sm hover:shadow-md transition-all flex flex-col justify-between overflow-hidden">
                            <a href="index.php?page=product&id=<?php echo $rp['id']; ?>" class="block aspect-[16/10] overflow-hidden bg-slate-100">
                                 <img src="<?php echo htmlspecialchars($rp['thumbnail']); ?>" class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($rp['title']); ?> Thumbnail" referrerpolicy="no-referrer">
                            </a>
                            <div class="p-3.5 space-y-2">
                                <a href="index.php?page=product&id=<?php echo $rp['id']; ?>" class="block font-bold text-slate-900 text-xs truncate hover:underline hover:text-slate-950">
                                    <?php echo htmlspecialchars($rp['title']); ?>
                                </a>
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="text-slate-400 font-semibold">by <?php echo htmlspecialchars($rp['seller_name']); ?></span>
                                    <span class="font-mono font-black text-slate-950 bg-slate-50 px-1.5 py-0.5 rounded">
                                        <?php echo format_price($rp['discount_price'] ?: $rp['price']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Column: 40% Width (Checkout panel, Specifications, Seller stats) -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Cart & Checkout Action Panel -->
        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-5">
            
            <div class="text-center pb-4 border-b border-gray-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Digital License Price</span>
                
                <?php if ($is_on_sale): ?>
                    <!-- On Sale Price Layout -->
                    <div class="flex items-center justify-center gap-3 my-2 select-none">
                        <span class="text-3xl font-black text-[#5cb85c] font-mono price-display-text"><?php echo format_price($product['discount_price']); ?></span>
                        <span class="text-sm text-gray-450 line-through font-mono font-semibold standard-price-line"><?php echo format_price($product['price']); ?></span>
                        <span class="bg-[#f0ad4e] text-white text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded sale-badge">Sale</span>
                    </div>
                    
                    <!-- Countdown timer component -->
                    <?php if (!empty($product['sale_ends_at'])): ?>
                        <div class="bg-orange-50 border border-orange-100 text-orange-700 px-3 py-1.5 rounded text-[10px] font-bold flex items-center justify-center gap-1.5" id="flash-countdown-container">
                            <span>⏳</span>
                            <span id="flash-timer-display" data-ends="<?php echo date('c', strtotime($product['sale_ends_at'])); ?>">00h 00m 00s left</span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Regular Price Layout -->
                    <p class="text-3xl font-black text-slate-900 font-mono my-2 price-display-text"><?php echo format_price($product['price']); ?></p>
                <?php endif; ?>
                
                <p class="text-[9px] text-[#5cb85c] font-bold uppercase tracking-wider mt-2 flex items-center justify-center gap-1">
                    <span>✓</span> Instant Digital Source Delivery
                </p>
            </div>

            <!-- Standard vs Extended License Tier Picker -->
            <?php if (!empty($product['licensing_enabled']) && intval($product['licensing_enabled']) === 1 && !empty($product['extended_price']) && floatval($product['extended_price']) > 0): ?>
                <div class="p-3.5 bg-slate-50 border border-gray-200 rounded space-y-2.5">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block select-none">Select License Level</span>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="radio" name="license_select" value="standard" checked class="form-radio text-[#5cb85c] focus:ring-[#5cb85c] h-3.5 w-3.5" onchange="updateLicenseTypePrice('standard')">
                            <div class="flex-1 text-[11px]">
                                <span class="font-extrabold text-slate-800 block">Standard License</span>
                                <span class="text-[9px] text-slate-400">Single domain name validation</span>
                            </div>
                            <span class="font-mono font-bold text-slate-700 text-xs"><?php echo format_price($product['discount_price'] ?: $product['price']); ?></span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer select-none border-t pt-2 border-gray-200">
                            <input type="radio" name="license_select" value="extended" class="form-radio text-[#5cb85c] focus:ring-[#5cb85c] h-3.5 w-3.5" onchange="updateLicenseTypePrice('extended')">
                            <div class="flex-1 text-[11px]">
                                <span class="font-extrabold text-slate-800 block">Extended License</span>
                                <span class="text-[9px] text-slate-400">Multiple domains (bypasses validation check)</span>
                            </div>
                            <span class="font-mono font-bold text-slate-700 text-xs"><?php echo format_price($product['extended_price']); ?></span>
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Licensing/Actions suite -->
            <div class="space-y-3">
                <input type="hidden" id="selected-license-type" value="standard">
                <?php if ($has_purchased || is_admin() || (is_logged_in() && $_SESSION['user_id'] == $product['seller_id'])): ?>
                    <!-- Already Unlocked / Download Zip -->
                    <div class="p-4 bg-emerald-50 border border-emerald-150 text-center rounded space-y-2.5">
                        <p class="text-xs font-bold text-emerald-800 flex items-center justify-center gap-1">
                            <span>🎉</span> Digital License Verified!
                        </p>
                        <a 
                            href="index.php?action=download&id=<?php echo $product['id']; ?>" 
                            class="w-full inline-flex items-center justify-center gap-1.5 bg-[#5cb85c] hover:bg-[#4cae4c] text-white py-3 rounded text-xs font-extrabold shadow transition-all active:scale-95 border-b-2 border-green-700"
                        >
                            📥 Download Source Zip
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Paystack instant gateway Checkout -->
                    <button 
                        onclick="triggerDirectPaystackCheckout(<?php echo htmlspecialchars(json_encode($product)); ?>)"
                        class="w-full bg-[#5cb85c] hover:bg-[#4cae4c] text-white py-3.5 rounded text-xs font-extrabold shadow transition-all flex items-center justify-center gap-2 active:scale-95 border-b-2 border-green-700"
                    >
                        💰 Purchase with Paystack
                    </button>

                    <!-- Cart loading drawer action -->
                    <form method="POST" action="index.php?action=cart_add_detail" class="w-full">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="license_type" id="cart-license-type" value="standard">
                        <button 
                            type="submit" 
                            class="w-full border border-gray-300 hover:bg-slate-50 text-slate-800 py-3 rounded text-xs font-extrabold transition-all flex items-center justify-center gap-2"
                        >
                            🛒 Add item to Cart
                        </button>
                    </form>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-2 pt-2">
                    <!-- Wishlist Toggle Button -->
                    <form method="POST" action="index.php?action=wishlist_toggle">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button 
                            type="submit" 
                            class="w-full py-2 bg-slate-50 hover:bg-slate-100 border border-gray-250 text-xs font-bold rounded flex items-center justify-center gap-1.5 transition-colors <?php echo $is_wishlisted ? 'text-red-500' : 'text-slate-500'; ?>"
                        >
                            <span>❤️</span> <?php echo $is_wishlisted ? 'Saved' : 'Wishlist'; ?>
                        </button>
                    </form>

                    <!-- Live Demo Preview link -->
                    <?php if (!empty($product['live_demo_url'])): ?>
                        <a 
                            href="<?php echo htmlspecialchars($product['live_demo_url']); ?>" 
                            target="_blank" 
                            class="w-full py-2 bg-slate-800 hover:bg-slate-900 border border-slate-950 text-white text-xs font-bold rounded flex items-center justify-center gap-1.5 transition-colors text-center"
                        >
                            <span>🌐</span> Live Preview
                        </a>
                    <?php else: ?>
                        <button 
                            disabled 
                            class="w-full py-2 bg-slate-100 text-slate-400 border border-slate-200 text-xs font-bold rounded flex items-center justify-center gap-1.5 cursor-not-allowed"
                        >
                            <span>🌐</span> No Demo
                        </button>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Specifications Table Section -->
            <div class="pt-4 border-t border-gray-100 space-y-2.5 text-xs select-none">
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 font-semibold">Author Support</span>
                    <span class="font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">Life-time free</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 font-semibold">Version</span>
                    <span class="font-mono font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">v<?php echo htmlspecialchars($product['version']); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 font-semibold">Released Date</span>
                    <span class="font-bold text-slate-800"><?php echo date('M Y', strtotime($product['created_at'])); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 font-semibold">Category</span>
                    <span class="font-bold text-[#5cb85c] hover:underline"><a href="index.php?page=marketplace&category=<?php echo urlencode($product['category']); ?>"><?php echo htmlspecialchars($product['category']); ?></a></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 font-semibold">Views</span>
                    <span class="font-bold text-slate-700 font-mono"><?php echo number_format($product['views_count']); ?> views</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 font-semibold">Downloads</span>
                    <span class="font-bold text-slate-700 font-mono"><?php echo number_format($product['real_sales'] + $product['sales_count']); ?> items</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400 font-semibold">Security Check</span>
                    <span class="font-bold text-[#5cb85c] flex items-center gap-0.5">🔒 Verified Safe</span>
                </div>
            </div>

        </div>

        <!-- Seller author Details Card -->
        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
            
            <div class="flex items-center gap-4">
                <!-- Avatar -->
                <div class="w-12 h-12 rounded bg-slate-150 text-[#5cb85c] flex-shrink-0 flex items-center justify-center font-extrabold shadow-inner overflow-hidden border border-gray-200">
                    <?php if (!empty($product['seller_avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($product['seller_avatar']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="text-xl"><?php echo strtoupper(substr($product['seller_name'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="flex-1 min-w-0">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-none mb-1">Developer</p>
                    <div class="font-bold text-slate-900 truncate flex items-center gap-1">
                        <a href="index.php?page=seller_profile&id=<?php echo $product['seller_id']; ?>" class="hover:underline hover:text-slate-950">
                            <?php echo htmlspecialchars($product['seller_name']); ?>
                        </a>
                        <?php if ($product['seller_verified']): ?>
                            <span class="text-blue-500 font-extrabold" title="Verified Professional Developer">✓</span>
                        <?php endif; ?>
                    </div>
                    <span class="block text-[10px] text-gray-400 mt-0.5"><?php echo $followers_count; ?> Followers</span>
                </div>
            </div>

            <!-- Follow / Unfollow user action form -->
            <form method="POST" action="index.php?action=follow_seller">
                <input type="hidden" name="seller_id" value="<?php echo $product['seller_id']; ?>">
                <button 
                    type="submit" 
                    class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded text-xs font-bold tracking-tight shadow flex items-center justify-center gap-1.5 transition-colors"
                >
                    👤 <?php echo $is_following ? 'Unfollow Developer' : 'Follow Developer'; ?>
                </button>
            </form>

            <!-- More from this seller list -->
            <?php if (!empty($seller_prods)): ?>
                                <div class="pt-4 border-t border-gray-100 space-y-3">
                    <h4 class="font-bold text-[10px] text-slate-400 uppercase tracking-wider">More from this Developer</h4>
                    <div class="space-y-2.5">
                        <?php foreach ($seller_prods as $sp): ?>
                            <a href="index.php?page=product&id=<?php echo $sp['id']; ?>" class="flex items-center gap-3 p-1.5 rounded hover:bg-slate-50 transition-colors">
                                <img src="<?php echo htmlspecialchars($sp['thumbnail']); ?>" class="w-8 h-8 rounded object-cover shrink-0 border" alt="<?php echo htmlspecialchars($sp['title']); ?> Thumbnail">
                                <div class="flex-1 min-w-0">
                                    <h5 class="font-bold text-[11px] text-slate-800 truncate leading-tight"><?php echo htmlspecialchars($sp['title']); ?></h5>
                                    <span class="text-[9px] text-[#5cb85c] font-mono font-bold"><?php echo format_price($sp['discount_price'] ?: $sp['price']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Social Sharing Card -->
        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
            <h4 class="font-bold text-[10px] text-slate-405 uppercase tracking-widest block select-none">Share this Product</h4>
            
            <?php 
            // Construct absolute URL
            $share_url = urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI']);
            $share_title = urlencode("Check out " . $product['title'] . " on CodeVault!");
            ?>
            
            <div class="grid grid-cols-2 gap-2 select-none">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" class="flex items-center justify-center gap-1.5 py-2 px-3 bg-[#3b5998] hover:bg-[#324d83] text-white text-[11px] font-bold rounded transition-colors text-center shadow-sm">
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/></svg>
                    Facebook
                </a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" target="_blank" class="flex items-center justify-center gap-1.5 py-2 px-3 bg-[#1da1f2] hover:bg-[#1a90da] text-white text-[11px] font-bold rounded transition-colors text-center shadow-sm">
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                    Twitter
                </a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $share_url; ?>" target="_blank" class="flex items-center justify-center gap-1.5 py-2 px-3 bg-[#0077b5] hover:bg-[#00669c] text-white text-[11px] font-bold rounded transition-colors text-center shadow-sm col-span-2">
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    LinkedIn
                </a>
            </div>
        </div>

        <!-- Sidebar Advertisement Card -->
        <?php 
        $ad_sidebar_enabled = get_setting('ad_sidebar_enabled', '0');
        $ad_sidebar_code = get_setting('ad_sidebar_code', '');
        if ($ad_sidebar_enabled === '1' && !empty($ad_sidebar_code)): 
        ?>
            <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                <h4 class="font-bold text-[10px] text-slate-405 uppercase tracking-widest block select-none">Advertisement</h4>
                <div class="text-center overflow-hidden max-w-full">
                    <?php echo $ad_sidebar_code; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- Real-time script updates inside template -->
<script>
    // Handles changing the active main preview image
    const allDetailImages = <?php echo $js_all_images; ?>;
    
    function setDetailActiveImage(index, url) {
        const detailImg = document.getElementById('prod-detail-active-img');
        if (detailImg) {
            detailImg.src = url;
        }

        // Update counter text
        const text = document.getElementById('lightbox-counter');
        if (text) {
            text.innerText = (index + 1) + " / " + allDetailImages.length;
        }

        // Highlight active border
        document.querySelectorAll('.thumb-btn-class').forEach(el => {
            el.className = "thumb-btn-class w-20 h-14 rounded border-2 border-transparent opacity-75 hover:opacity-100 flex-shrink-0 transition-all overflow-hidden";
        });
        const currentBtn = document.getElementById('thumb-btn-' + index);
        if (currentBtn) {
            currentBtn.className = "thumb-btn-class w-20 h-14 rounded border-2 border-[#5cb85c] opacity-100 flex-shrink-0 transition-all overflow-hidden";
        }
    }

    // Handles Switching interactive tabs
    function switchTab(tabName) {
        // Hide all panels
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
        
        // Show selected panel
        const activePanel = document.getElementById('tab-content-' + tabName);
        if (activePanel) activePanel.classList.remove('hidden');

        // Reset tab buttons style
        document.querySelectorAll("[id^='tab-btn-']").forEach(btn => {
            btn.className = "px-5 py-3 border-r border-gray-200 hover:bg-white hover:text-slate-900 transition-colors border-b-2 border-b-transparent focus:outline-none";
        });

        // Set active style on current tab button
        const activeBtn = document.getElementById('tab-btn-' + tabName);
        if (activeBtn) {
            activeBtn.className = "px-5 py-3 border-r border-gray-200 bg-white text-slate-900 border-b-2 border-b-[#5cb85c] hover:bg-white transition-colors focus:outline-none";
        }
    }

    // Pre-sales comment simulator helper
    function submitMockComment() {
        const txt = document.getElementById('mock-comment-textarea').value.trim();
        if (txt.length < 5) return;
        document.getElementById('mock-comment-textarea').value = '';
        const alert = document.getElementById('mock-comment-alert');
        alert.classList.remove('hidden');
        setTimeout(() => alert.classList.add('hidden'), 5000);
    }

    // Countdown Timer for sale duration
    const displayEl = document.getElementById('flash-timer-display');
    if (displayEl) {
        const endTimeStr = displayEl.getAttribute('data-ends');
        if (endTimeStr) {
            const endsAt = new Date(endTimeStr).getTime();
            
            function tickCountdown() {
                const now = new Date().getTime();
                const diff = endsAt - now;

                if (diff <= 0) {
                    const timerContainer = document.getElementById('flash-countdown-container');
                    if (timerContainer) timerContainer.classList.add('hidden');
                    return;
                }

                const hrs = Math.floor(diff / (1000 * 60 * 60));
                const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const secs = Math.floor((diff % (1000 * 60)) / 1000);

                displayEl.innerText = `${hrs}h ${mins}m ${secs}s left`;
            }

            tickCountdown();
            setInterval(tickCountdown, 1000);
        }
    }

    // Dynamic licensing pricing selection handler
    function updateLicenseTypePrice(type) {
        const isSale = <?php echo $is_on_sale ? 'true' : 'false'; ?>;
        const stdPrice = <?php echo floatval($product['price']); ?>;
        const discPrice = <?php echo floatval($product['discount_price'] ?: 0); ?>;
        const extPrice = <?php echo floatval($product['extended_price'] ?: 0); ?>;
        
        const priceText = document.querySelectorAll('.price-display-text');
        const saleLine = document.querySelectorAll('.standard-price-line');
        const saleBadge = document.querySelectorAll('.sale-badge');
        
        // Update hidden fields
        const selectedTypeInput = document.getElementById('selected-license-type');
        if (selectedTypeInput) selectedTypeInput.value = type;
        const cartTypeInput = document.getElementById('cart-license-type');
        if (cartTypeInput) cartTypeInput.value = type;
        
        if (type === 'extended') {
            saleLine.forEach(el => el.classList.add('hidden'));
            saleBadge.forEach(el => el.classList.add('hidden'));
            priceText.forEach(el => {
                el.innerText = '$' + extPrice.toFixed(2);
            });
        } else {
            if (isSale) {
                saleLine.forEach(el => el.classList.remove('hidden'));
                saleBadge.forEach(el => el.classList.remove('hidden'));
                priceText.forEach(el => {
                    el.innerText = '$' + discPrice.toFixed(2);
                });
            } else {
                priceText.forEach(el => {
                    el.innerText = '$' + stdPrice.toFixed(2);
                });
            }
        }
    }
</script>
