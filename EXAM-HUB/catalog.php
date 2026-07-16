<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

$pdo = get_db_connection();
$products = $pdo->query("SELECT * FROM products ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-12">
    <div class="text-center mb-16">
        <h1 class="text-4xl font-extrabold text-gray-900 sm:text-5xl">Exam PINs & Cards</h1>
        <p class="mt-4 text-xl text-gray-600">Instant delivery for WAEC, NECO, NABTEB, and JAMB.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($products as $card): ?>
            <div class="glass relative rounded-3xl p-8 flex flex-col items-center text-center transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 border border-white/40">
                <?php if ($card['status'] === 'disabled'): ?>
                    <div class="absolute inset-0 bg-white/60 backdrop-blur-sm rounded-3xl z-10 flex items-center justify-center">
                        <span class="bg-red-600 text-white font-bold px-6 py-2 rounded-full shadow-lg transform -rotate-12 text-lg tracking-widest">OUT OF STOCK</span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($card['logo'])): ?>
                    <img src="<?= htmlspecialchars($card['logo']) ?>" alt="<?= htmlspecialchars($card['name']) ?>" class="h-32 w-auto object-contain mb-6 drop-shadow-md">
                <?php else: ?>
                    <div class="h-32 w-32 bg-gradient-to-br from-blue-100 to-blue-50 rounded-2xl flex items-center justify-center mb-6 shadow-inner text-blue-600 text-5xl font-black">
                        <?= substr(htmlspecialchars($card['name']), 0, 1) ?>
                    </div>
                <?php endif; ?>
                
                <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($card['name']) ?></h3>
                
                <div class="text-3xl font-black text-blue-600 mb-6">₦<?= number_format($card['selling_price'], 2) ?></div>
                
                <?php if (!empty($card['external_link'])): ?>
                    <a href="<?= htmlspecialchars($card['external_link']) ?>" target="_blank" class="w-full mt-auto block text-center bg-blue-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 <?php echo $card['status'] === 'disabled' ? 'opacity-50 pointer-events-none' : ''; ?>">
                        Visit Link
                    </a>
                <?php else: ?>
                    <form action="/checkout" method="GET" class="w-full mt-auto <?php echo $card['status'] === 'disabled' ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <input type="hidden" name="card_id" value="<?= htmlspecialchars($card['id']) ?>">
                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                            Purchase Now
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($products)): ?>
            <div class="col-span-full text-center py-12 bg-white rounded-2xl shadow-sm border border-gray-100">
                <p class="text-gray-500 text-lg">No products available at the moment. Admin needs to sync products.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
