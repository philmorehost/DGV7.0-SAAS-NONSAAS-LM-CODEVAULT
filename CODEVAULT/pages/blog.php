<?php
// Blog Subpage for CodeVault PHP
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

if ($post_id > 0) {
    // Single Blog Post View
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $p = $stmt->fetch();
    
    if (!$p) {
        echo "<div class='text-center py-12 bg-white rounded border border-gray-200 shadow-sm'><p class='text-sm text-slate-500 font-bold'>Article not found.</p><a href='index.php?page=blog' class='text-[#5cb85c] font-bold text-xs hover:underline mt-2 inline-block'>Back to Blog feed</a></div>";
        return;
    }
    ?>
    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Breadcrumbs -->
        <nav class="text-xs text-gray-500 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
            <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
            <span class="text-gray-400 font-bold">/</span>
            <a href="index.php?page=blog" class="hover:text-slate-900 transition-colors">Blog</a>
            <span class="text-gray-400 font-bold">/</span>
            <span class="text-slate-700 font-semibold truncate"><?php echo htmlspecialchars($p['title']); ?></span>
        </nav>
        
        <div class="bg-white rounded overflow-hidden border border-gray-200 shadow-sm">
            <div class="aspect-video bg-gray-50 relative">
                <img src="<?php echo htmlspecialchars($p['thumbnail']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" class="w-full h-full object-cover">
            </div>
            
            <div class="p-6 md:p-8 space-y-5">
                <div class="flex items-center gap-2 text-xs text-slate-450 font-bold">
                    <span>👤 by <?php echo htmlspecialchars($p['author']); ?></span>
                    <span class="font-mono">• <?php echo date('F d, Y', strtotime($p['created_at'])); ?></span>
                </div>
                
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-tight"><?php echo htmlspecialchars($p['title']); ?></h1>
                
                <div class="text-slate-600 text-sm md:text-base leading-relaxed whitespace-pre-wrap font-normal"><?php echo htmlspecialchars($p['content']); ?></div>
            </div>
        </div>
    </div>
    <?php
} else {
    // Blog Feed Listing
    $stmt = $db->query("SELECT * FROM blog_posts ORDER BY id DESC");
    $posts = $stmt->fetchAll();
    ?>
    <!-- Breadcrumbs -->
    <nav class="text-xs text-gray-500 mb-6 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
        <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
        <span class="text-gray-400 font-bold">/</span>
        <span class="text-slate-700 font-semibold">Blog</span>
    </nav>

    <div class="space-y-8">
        <div>
            <span class="text-[9px] font-bold uppercase tracking-widest text-[#5cb85c] bg-emerald-50 px-2 py-0.5 rounded border border-emerald-150">Insights & Diaries</span>
            <h2 class="text-2xl font-black text-slate-900 mt-3 tracking-tight leading-none">Engineering Diaries & Insights</h2>
            <p class="text-slate-500 text-xs mt-2 max-w-xl">In-depth blueprints, marketing setups for software creators, and system reviews compiled by the core design teams.</p>
        </div>

        <?php if (empty($posts)): ?>
            <div class="text-center py-16 bg-white border border-gray-200 rounded text-xs font-bold text-slate-400">No blog posts registered yet.</div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach($posts as $post): ?>
                    <div class="group bg-white border border-gray-200 rounded overflow-hidden hover:shadow transition-all flex flex-col h-full">
                        <div class="aspect-video bg-gray-100 overflow-hidden relative">
                            <a href="index.php?page=blog&post_id=<?php echo $post['id']; ?>">
                                <img src="<?php echo htmlspecialchars($post['thumbnail']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-300">
                            </a>
                        </div>
                        <div class="p-5 flex flex-col flex-1">
                            <div class="flex items-center gap-1.5 text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                                <span><?php echo htmlspecialchars($post['author']); ?></span>
                                <span>•</span>
                                <span class="font-mono"><?php echo date('Y-m-d', strtotime($post['created_at'])); ?></span>
                            </div>
                            
                            <a href="index.php?page=blog&post_id=<?php echo $post['id']; ?>">
                                <h3 class="font-bold text-gray-900 group-hover:text-[#5cb85c] transition-colors text-sm line-clamp-2 leading-snug tracking-tight mb-2"><?php echo htmlspecialchars($post['title']); ?></h3>
                            </a>
                            
                            <p class="text-sm text-gray-500 line-clamp-3 mb-4 leading-relaxed flex-1 font-normal">
                                <?php echo htmlspecialchars(strip_tags($post['content'])); ?>
                            </p>
                            
                            <a href="index.php?page=blog&post_id=<?php echo $post['id']; ?>" class="mt-auto inline-flex items-center gap-1 text-[10px] font-bold text-[#5cb85c] hover:text-[#4cae4c] transition-colors uppercase tracking-wider">
                                Read Article ➔
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
