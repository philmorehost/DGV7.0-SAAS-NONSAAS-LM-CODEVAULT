<?php
// Curated Collections listings page for CodeVault PHP
$collection_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($collection_id > 0) {
    // Fetch specific collection
    $stmt = $db->prepare("SELECT * FROM collections WHERE id = ?");
    $stmt->execute([$collection_id]);
    $collection = $stmt->fetch();
    
    if (!$collection) {
        echo "<div class='bg-white rounded border p-12 text-center text-xs text-slate-500 font-bold'>⚠️ Collection not found.</div>";
        return;
    }
    
    // Fetch products in this collection
    $p_stmt = $db->prepare("SELECT p.*, u.name as seller_name FROM collection_items ci JOIN products p ON ci.product_id = p.id JOIN users u ON p.seller_id = u.id WHERE ci.collection_id = ? AND p.status = 'approved' ORDER BY p.id DESC");
    $p_stmt->execute([$collection_id]);
    $products = $p_stmt->fetchAll();
    ?>
    <!-- Breadcrumbs -->
    <nav class="text-xs text-gray-500 mb-6 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
        <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
        <span class="text-gray-400 font-bold">/</span>
        <a href="index.php?page=collections" class="hover:text-slate-900 transition-colors">Collections</a>
        <span class="text-gray-400 font-bold">/</span>
        <span class="text-slate-700 font-semibold"><?php echo htmlspecialchars($collection['title']); ?></span>
    </nav>

    <div class="space-y-6">
        <div>
            <h3 class="font-black text-slate-900 text-xl tracking-tight leading-none mb-1"><?php echo htmlspecialchars($collection['title']); ?></h3>
            <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($collection['description']); ?></p>
        </div>

        <?php if (empty($products)): ?>
            <div class="text-center py-16 bg-white border border-gray-200 rounded text-slate-400 font-bold text-xs">
                No active approved products in this collection currently.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach($products as $p): ?>
                    <!-- Product Card -->
                    <div class="bg-white border border-gray-200 rounded shadow-sm hover:shadow transition-all flex flex-col justify-between overflow-hidden relative group">
                        
                        <!-- Thumbnail Frame -->
                        <div class="block aspect-[16/10] overflow-hidden bg-slate-100 relative">
                            <img src="<?php echo htmlspecialchars($p['thumbnail']); ?>" class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($p['title']); ?> Thumbnail" referrerpolicy="no-referrer">
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
                            <h4 class="font-bold text-xs text-slate-900 truncate pt-1">
                                <a href="index.php?page=product&id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></a>
                            </h4>
                            
                            <div class="flex justify-between items-center text-[10px] pt-1">
                                <span class="text-slate-450">by <?php echo htmlspecialchars($p['seller_name']); ?></span>
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

<?php } else { 
    // Fetch all collections
    $all_cols = $db->query("SELECT * FROM collections ORDER BY id DESC")->fetchAll();
    ?>
    <!-- Breadcrumbs -->
    <nav class="text-xs text-gray-500 mb-6 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
        <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
        <span class="text-gray-400 font-bold">/</span>
        <span class="text-slate-700 font-semibold">Collections</span>
    </nav>

    <div class="space-y-6">
        <div>
            <h3 class="font-black text-slate-900 text-xl tracking-tight leading-none mb-1">Curated Collections</h3>
            <p class="text-xs text-slate-500 mt-1">Discover thematic groupings of high-quality templates and components curated by CodeVault administrators.</p>
        </div>

        <?php if (empty($all_cols)): ?>
            <div class="text-center py-16 bg-white border border-gray-200 rounded text-slate-400 font-bold text-xs">
                No curated collections list is available right now. Keep checking back!
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach($all_cols as $col): ?>
                    <?php 
                    // Get first product image for collection preview
                    $prev_stmt = $db->prepare("SELECT p.thumbnail FROM collection_items ci JOIN products p ON ci.product_id = p.id WHERE ci.collection_id = ? AND p.status = 'approved' LIMIT 1");
                    $prev_stmt->execute([$col['id']]);
                    $cover_img = $prev_stmt->fetchColumn() ?: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600';
                    
                    // Count items
                    $cnt_stmt = $db->prepare("SELECT COUNT(*) FROM collection_items WHERE collection_id = ?");
                    $cnt_stmt->execute([$col['id']]);
                    $items_count = $cnt_stmt->fetchColumn();
                    ?>
                    <!-- Collection Card Codester Style -->
                    <div class="bg-white border border-gray-200 rounded shadow-sm hover:shadow transition-all flex flex-col justify-between overflow-hidden">
                        <a href="index.php?page=collections&id=<?php echo $col['id']; ?>" class="block aspect-[16/10] overflow-hidden bg-slate-100 relative">
                            <img src="<?php echo htmlspecialchars($cover_img); ?>" class="w-full h-full object-cover">
                            <span class="absolute bottom-2 right-2 bg-slate-950/80 text-white font-mono text-[9px] font-bold px-2 py-0.5 rounded">
                                <?php echo $items_count; ?> Products
                            </span>
                        </a>
                        <div class="p-5 space-y-2">
                            <h4 class="font-extrabold text-sm text-slate-900 hover:text-slate-950">
                                <a href="index.php?page=collections&id=<?php echo $col['id']; ?>"><?php echo htmlspecialchars($col['title']); ?></a>
                            </h4>
                            <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars($col['description'] ?: 'Curated assets list.'); ?></p>
                            
                            <a href="index.php?page=collections&id=<?php echo $col['id']; ?>" class="inline-block pt-2 text-xs font-bold text-[#5cb85c] hover:underline">Explore Collection ➔</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php } ?>
