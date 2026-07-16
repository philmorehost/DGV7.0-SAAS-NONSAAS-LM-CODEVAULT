<?php
// Free Files listings page for CodeVault PHP
$stmt = $db->query("SELECT p.*, u.name as seller_name FROM products p JOIN users u ON p.seller_id = u.id WHERE p.status = 'approved' AND p.price = 0 ORDER BY p.id DESC");
$products = $stmt->fetchAll();
?>

<!-- Breadcrumbs -->
<nav class="text-xs text-gray-500 mb-6 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
    <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
    <span class="text-gray-400 font-bold">/</span>
    <span class="text-slate-700 font-semibold">🎁 Free Files</span>
</nav>

<div class="space-y-6">
    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded p-6 md:p-8 flex justify-between items-center flex-wrap gap-4 shadow-sm">
        <div class="space-y-1">
            <h2 class="font-black text-2xl tracking-tight flex items-center gap-1.5 leading-none">🎁 Free Code Files & Scripts</h2>
            <p class="text-xs text-emerald-100 font-medium">Download standard assets for free. Check out structures, components, and templates without licensing fees.</p>
        </div>
        <div class="bg-black/25 text-white border border-white/20 font-bold text-xs uppercase tracking-wider px-3.5 py-1.5 rounded">
            Free Download
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="text-center py-16 bg-white border border-gray-200 rounded text-slate-400 font-bold text-xs">
            No free files listings at the moment. Keep checking back!
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach($products as $p): ?>
                <div class="bg-white border border-gray-200 rounded shadow-sm hover:shadow transition-all flex flex-col justify-between overflow-hidden relative group">
                    
                    <!-- Free Badge -->
                    <span class="absolute top-2.5 left-2.5 bg-emerald-500 text-white font-extrabold text-[9px] uppercase px-2 py-0.5 rounded tracking-wide z-10 select-none border border-emerald-400 shadow-sm">
                        FREE
                    </span>
                    
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
                            <span class="font-mono font-black text-[#5cb85c] bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                Free
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
