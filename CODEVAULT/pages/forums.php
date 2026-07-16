<?php
// Forums Discussion Board for CodeVault PHP
$thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : 0;
$forum_cat = isset($_GET['category']) ? trim($_GET['category']) : 'all';

// Default categories
$forum_categories = ['General Chat', 'Database Discussions', 'Payment Integrations', 'Frontend Engineering', 'Bug Audits'];

if ($thread_id > 0) {
    // Thread Detail View
    $stmt = $db->prepare("SELECT t.*, u.avatar_url as author_avatar FROM forum_threads t LEFT JOIN users u ON t.author_id = u.id WHERE t.id = ?");
    $stmt->execute([$thread_id]);
    $thread = $stmt->fetch();
    
    if (!$thread) {
        echo "<div class='text-center py-12 bg-white rounded border border-gray-200 shadow-sm'><p class='text-sm text-slate-500 font-bold'>Discussion Thread not found.</p><a href='index.php?page=forums' class='text-[#5cb85c] font-bold text-xs hover:underline mt-2 inline-block'>Back to Forums board</a></div>";
        return;
    }
    
    // Fetch replies
    $rep_stmt = $db->prepare("SELECT * FROM forum_posts WHERE thread_id = ? ORDER BY id ASC");
    $rep_stmt->execute([$thread['id']]);
    $replies = $rep_stmt->fetchAll();
    ?>
    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Breadcrumbs -->
        <nav class="text-xs text-gray-500 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
            <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
            <span class="text-gray-400 font-bold">/</span>
            <a href="index.php?page=forums" class="hover:text-slate-900 transition-colors">Forums</a>
            <span class="text-gray-400 font-bold">/</span>
            <span class="text-slate-700 font-semibold truncate"><?php echo htmlspecialchars($thread['title']); ?></span>
        </nav>
        
        <!-- OP Card -->
        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 flex-wrap gap-2">
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-6 h-6 bg-slate-100 rounded flex items-center justify-center text-xs">🧑‍💻</span>
                    <div>
                        <h4 class="font-bold text-slate-800"><?php echo htmlspecialchars($thread['author_name']); ?></h4>
                        <p class="text-[9px] font-mono text-slate-400"><?php echo date('Y-m-d H:i', strtotime($thread['created_at'])); ?></p>
                    </div>
                </div>
                <span class="text-[9px] uppercase tracking-wider font-extrabold text-[#5cb85c] bg-emerald-50 px-2 py-0.5 rounded border border-emerald-150"><?php echo htmlspecialchars($thread['category']); ?></span>
            </div>
            
            <h1 class="text-xl font-bold text-slate-900 tracking-tight leading-snug"><?php echo htmlspecialchars($thread['title']); ?></h1>
            <p class="text-sm md:text-base text-slate-655 leading-relaxed font-normal whitespace-pre-wrap py-2"><?php echo htmlspecialchars($thread['body']); ?></p>
        </div>

        <!-- Replies Timeline -->
        <div class="space-y-4">
            <h3 class="font-bold text-xs text-slate-450 uppercase tracking-wider pl-2">Answers & Comments (<?php echo count($replies); ?>)</h3>
            
            <?php foreach($replies as $rep): ?>
                <div class="p-5 bg-white border border-gray-200 rounded ml-4 sm:ml-6 shadow-sm">
                    <div class="flex items-center gap-2 mb-2 text-xs">
                        <span class="w-5 h-5 bg-slate-100 rounded flex items-center justify-center text-[10px]">💬</span>
                        <h5 class="font-extrabold text-slate-800"><?php echo htmlspecialchars($rep['author_name']); ?></h5>
                        <span class="text-[9px] font-mono text-slate-400">• <?php echo date('Y-m-d H:i', strtotime($rep['created_at'])); ?></span>
                    </div>
                    <p class="text-sm text-slate-650 leading-relaxed font-normal whitespace-pre-wrap"><?php echo htmlspecialchars($rep['body']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Reply Submission form -->
        <?php if (is_logged_in()): ?>
            <form method="POST" action="index.php?action=forum_reply_add" class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
                <input type="hidden" name="thread_id" value="<?php echo $thread['id']; ?>">
                <h4 class="font-bold text-xs text-slate-500 uppercase tracking-wider">Contribute an Answer</h4>
                
                <textarea name="body" required placeholder="Offer debugging advice, share code blocks, clarify configurations..." rows="3" class="w-full px-3 py-2 rounded border border-gray-250 bg-white outline-none text-xs leading-relaxed focus:border-[#5cb85c] shadow-inner"></textarea>
                
                <div class="flex justify-between items-center gap-2 flex-wrap">
                    <span class="text-[10px] text-slate-400 font-semibold">Keep comments helpful and polite.</span>
                    <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs transition-colors">
                        Submit Answer
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="p-6 bg-slate-50 rounded border text-center text-xs text-slate-500 font-semibold">
                Please <button onclick="openLoginModal()" class="text-[#5cb85c] font-bold hover:underline">log in</button> to post comments or answer questions.
            </div>
        <?php endif; ?>
    </div>
    <?php
} else {
    // Discussions Board Feed Home
    $query_str = "SELECT t.*, (SELECT COUNT(*) FROM forum_posts fp WHERE fp.thread_id = t.id) as replies_count FROM forum_threads t WHERE 1=1";
    $params = [];
    if ($forum_cat !== 'all') {
        $query_str .= " AND t.category = ?";
        $params[] = $forum_cat;
    }
    $query_str .= " ORDER BY t.id DESC";
    $th_stmt = $db->prepare($query_str);
    $th_stmt->execute($params);
    $threads = $th_stmt->fetchAll();
    ?>
    <!-- Breadcrumbs -->
    <nav class="text-xs text-gray-500 mb-6 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
        <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
        <span class="text-gray-400 font-bold">/</span>
        <span class="text-slate-700 font-semibold">Discussions Board</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- Left Column: Categories Selector -->
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white border border-gray-200 rounded p-4 shadow-sm space-y-3">
                <h3 class="font-extrabold text-[10px] text-slate-400 uppercase tracking-widest border-b pb-1.5">Forums folders</h3>
                <div class="flex flex-col gap-1 text-xs">
                    <a href="index.php?page=forums&category=all" class="p-2 rounded font-semibold transition-colors <?php echo $forum_cat === 'all' ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'; ?>">All Discussions</a>
                    <?php foreach($forum_categories as $f_cat): ?>
                        <a href="index.php?page=forums&category=<?php echo urlencode($f_cat); ?>" class="p-2 rounded font-semibold transition-colors <?php echo $forum_cat === $f_cat ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'; ?>"><?php echo $f_cat; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (is_logged_in()): ?>
                <button onclick="openNewThreadModal()" class="w-full bg-[#5cb85c] hover:bg-[#4cae4c] text-white py-2.5 rounded text-xs font-bold uppercase shadow transition-all active:scale-95 border-b-2 border-green-700">
                    + Start New Thread
                </button>
            <?php endif; ?>
        </div>

        <!-- Right Column: Threads listings -->
        <div class="lg:col-span-3 space-y-6">
            <div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-[#5cb85c] bg-emerald-50 px-2 py-0.5 rounded border border-emerald-150">Developer Collaboration</span>
                <h2 class="text-2xl font-black text-slate-900 mt-3 tracking-tight leading-none">Developer Collaboration Board</h2>
                <p class="text-slate-500 text-xs mt-2 max-w-xl">Discuss database schemas optimizations, custom Paystack integration tips, and layout hacks with vendors and community peers.</p>
            </div>

            <?php if (empty($threads)): ?>
                <div class="bg-white rounded p-12 text-center border text-slate-400 font-bold text-xs">No discussion threads found in this folder.</div>
            <?php else: ?>
                <div class="space-y-3.5">
                    <?php foreach($threads as $th): ?>
                        <div class="group bg-white border border-gray-200 p-4 rounded flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 hover:shadow transition-all">
                            <div class="space-y-1 min-w-0 flex-1">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-[#5cb85c] bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100"><?php echo htmlspecialchars($th['category']); ?></span>
                                <a href="index.php?page=forums&thread_id=<?php echo $th['id']; ?>" class="block pt-1.5">
                                    <h3 class="font-bold text-slate-850 group-hover:text-[#5cb85c] transition-colors text-sm truncate"><?php echo htmlspecialchars($th['title']); ?></h3>
                                </a>
                                <p class="text-[10px] text-slate-400 font-semibold">Started by <span class="text-slate-600 font-bold"><?php echo htmlspecialchars($th['author_name']); ?></span> on <?php echo date('M d, Y', strtotime($th['created_at'])); ?></p>
                            </div>
                            
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="font-mono text-[10px] font-bold text-slate-500 px-2 py-1 bg-slate-50 border rounded">💬 <?php echo $th['replies_count']; ?> Replies</span>
                                <a href="index.php?page=forums&thread_id=<?php echo $th['id']; ?>" class="p-2 bg-slate-100 group-hover:bg-[#5cb85c] group-hover:text-white rounded transition-colors text-xs font-bold text-slate-600">➔</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Create Discussion Modal component -->
    <div id="new-thread-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden select-none animate-fade">
        <div class="bg-white rounded w-full max-w-lg p-6 border shadow-2xl relative">
            <button onclick="closeNewThreadModal()" class="absolute right-4 top-4 text-slate-400 hover:text-slate-800 font-bold text-sm">✕</button>
            
            <h3 class="font-black text-slate-900 text-lg mb-1">Create Discussion Thread</h3>
            <p class="text-xs text-slate-500 mb-5">Select folders. Outline code details clearly to receive responses.</p>

            <form method="POST" action="index.php?action=forum_thread_add" class="space-y-4">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-450 uppercase">Discussion Title</label>
                    <input type="text" name="title" required placeholder="Subject..." class="w-full px-3 py-2 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c]">
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-450 uppercase">Folder Section</label>
                    <select name="category" required class="w-full px-3 py-2 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c]">
                        <?php foreach($forum_categories as $f_cat): ?>
                            <option value="<?php echo htmlspecialchars($f_cat); ?>"><?php echo $f_cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-450 uppercase">Details / Question</label>
                    <textarea name="body" required placeholder="Write details here..." rows="4" class="w-full px-3 py-2 rounded border outline-none bg-white text-xs leading-relaxed focus:border-[#5cb85c]"></textarea>
                </div>

                <button type="submit" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-extrabold uppercase rounded text-xs transition-colors shadow">Submit Thread</button>
            </form>
        </div>
    </div>

    <script>
        function openNewThreadModal() {
            document.getElementById('new-thread-modal').classList.remove('hidden');
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeNewThreadModal();
            }
        });

        function closeNewThreadModal() {
            document.getElementById('new-thread-modal').classList.add('hidden');
        }
    </script>
    <?php
}
?>
