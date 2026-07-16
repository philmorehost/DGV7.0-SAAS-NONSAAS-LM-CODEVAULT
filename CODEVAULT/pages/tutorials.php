<?php
// Tutorials Page for CodeVault PHP
$diff_filter = isset($_GET['difficulty']) ? trim($_GET['difficulty']) : 'all';

$query_str = "SELECT * FROM tutorials WHERE 1=1";
$params = [];

if ($diff_filter !== 'all') {
    $query_str .= " AND difficulty = ?";
    $params[] = $diff_filter;
}

$query_str .= " ORDER BY id DESC";
$tut_stmt = $db->prepare($query_str);
$tut_stmt->execute($params);
$tutorials = $tut_stmt->fetchAll();

// Extract YT Id helper
if (!function_exists('get_yt_id')) {
    function get_yt_id($url) {
        $regExp = '/^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/';
        preg_match($regExp, $url, $matches);
        return (isset($matches[2]) && strlen($matches[2]) == 11) ? $matches[2] : '';
    }
}
?>

<!-- Breadcrumbs -->
<nav class="text-xs text-gray-500 mb-6 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
    <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
    <span class="text-gray-400 font-bold">/</span>
    <span class="text-slate-700 font-semibold">Tutorials</span>
</nav>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <span class="text-[9px] font-bold uppercase tracking-widest text-[#5cb85c] bg-emerald-50 px-2 py-0.5 rounded border border-emerald-150">Technical Blueprints</span>
            <h2 class="text-2xl font-black text-slate-900 mt-3 tracking-tight leading-none">Developer Tutorials</h2>
            <p class="text-slate-500 text-xs mt-2 max-w-xl">Learn how to deploy, configure, and customize digital assets with detailed video instructions.</p>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-1.5 p-1 bg-slate-100 border rounded select-none font-bold text-[10px] uppercase tracking-wider">
            <a href="index.php?page=tutorials&difficulty=all" class="px-3 py-1.5 rounded transition-all <?php echo $diff_filter === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'; ?>">All Levels</a>
            <a href="index.php?page=tutorials&difficulty=beginner" class="px-3 py-1.5 rounded transition-all <?php echo $diff_filter === 'beginner' ? 'bg-white text-[#5cb85c] shadow-sm' : 'text-slate-500 hover:text-slate-800'; ?>">Beginner</a>
            <a href="index.php?page=tutorials&difficulty=intermediate" class="px-3 py-1.5 rounded transition-all <?php echo $diff_filter === 'intermediate' ? 'bg-white text-blue-650 shadow-sm' : 'text-slate-500 hover:text-slate-800'; ?>">Intermediate</a>
            <a href="index.php?page=tutorials&difficulty=advanced" class="px-3 py-1.5 rounded transition-all <?php echo $diff_filter === 'advanced' ? 'bg-white text-purple-650 shadow-sm' : 'text-slate-500 hover:text-slate-800'; ?>">Advanced</a>
        </div>
    </div>

    <?php if (empty($tutorials)): ?>
        <div class="bg-white border rounded p-12 text-center text-slate-400 font-bold text-xs">
            No tutorials found in this difficulty level.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach($tutorials as $tut): ?>
                <?php $yt_code = get_yt_id($tut['youtube_url']); ?>
                <div class="bg-white border border-gray-200 rounded overflow-hidden hover:shadow transition-all flex flex-col justify-between">
                    
                    <div>
                        <!-- YouTube Video preview thumbnail -->
                        <?php if ($yt_code): ?>
                            <div class="aspect-video bg-black relative group overflow-hidden">
                                <img src="https://img.youtube.com/vi/<?php echo $yt_code; ?>/hqdefault.jpg" alt="Video cover" class="w-full h-full object-cover">
                                <button 
                                    onclick="openTutorialPlayer('<?php echo $yt_code; ?>')"
                                    class="absolute inset-0 bg-black/45 hover:bg-black/55 backdrop-blur-[1px] flex items-center justify-center transition-colors outline-none cursor-pointer"
                                >
                                    <div class="w-12 h-12 rounded-full bg-white text-black flex items-center justify-center text-lg shadow-lg transform group-hover:scale-105 transition-transform">
                                        ▶
                                    </div>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="aspect-video bg-slate-900 flex items-center justify-center text-3xl">📖</div>
                        <?php endif; ?>

                        <!-- Content details -->
                        <div class="p-5 space-y-2.5">
                            <div class="flex items-center justify-between text-[9px] font-bold uppercase select-none">
                                <span class="text-[#5cb85c] bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100"><?php echo htmlspecialchars($tut['category']); ?></span>
                                <span class="px-1.5 py-0.5 rounded border <?php echo $tut['difficulty'] === 'beginner' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : ($tut['difficulty'] === 'intermediate' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-purple-50 text-purple-600 border-purple-100'); ?>">
                                    <?php echo htmlspecialchars($tut['difficulty']); ?>
                                </span>
                            </div>

                            <h3 class="font-bold text-slate-900 text-sm leading-snug tracking-tight line-clamp-2"><?php echo htmlspecialchars($tut['title']); ?></h3>
                            <p class="text-sm text-slate-500 font-normal leading-relaxed line-clamp-3"><?php echo htmlspecialchars(strip_tags($tut['content'])); ?></p>
                        </div>
                    </div>

                    <div class="px-5 pb-5 pt-1">
                        <button 
                            onclick="openTutorialPlayer('<?php echo $yt_code; ?>')"
                            class="w-full bg-slate-900 border hover:bg-slate-800 text-white font-bold text-xs py-2 rounded transition-colors outline-none flex items-center justify-center gap-1.5"
                        >
                            Open Blueprint Video Player ➔
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Player Modal -->
<div id="tutorial-player-modal" class="fixed inset-0 bg-black/90 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden select-none" onclick="closeTutorialPlayer()">
    <div class="w-full max-w-3xl bg-slate-950 rounded border border-white/5 overflow-hidden shadow-2xl relative" onclick="event.stopPropagation()">
        <button onclick="closeTutorialPlayer()" class="absolute right-4 top-4 text-white/50 hover:text-white font-bold text-xs z-20">✕ Close</button>
        <div class="aspect-video bg-black">
            <iframe 
                id="tut-player-frame"
                width="100%" 
                height="100%" 
                src="" 
                title="Active tutorial player frame" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen
                class="w-full h-full"
            ></iframe>
        </div>
    </div>
</div>

<script>
    function openTutorialPlayer(ytCode) {
        if (!ytCode) return;
        const frame = document.getElementById('tut-player-frame');
        if (frame) {
            frame.src = "https://www.youtube.com/embed/" + ytCode + "?autoplay=1";
        }
        document.getElementById('tutorial-player-modal').classList.remove('hidden');
    }

    function closeTutorialPlayer() {
        const frame = document.getElementById('tut-player-frame');
        if (frame) {
            frame.src = "";
        }
        document.getElementById('tutorial-player-modal').classList.add('hidden');
    }
</script>
