<?php
// Main Entry Point
if (!file_exists(__DIR__ . '/core/config.php')) {
    header('Location: /install/index.php');
    exit;
}

// Load core files
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

// Display landing page
$pdo = get_db_connection();
$products = $pdo->query("SELECT * FROM products ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>
<!-- Premium Landing Page Hero Section -->
<div class="flex-grow flex flex-col justify-center items-center text-center px-4 sm:px-6 lg:px-8 mt-16 mb-24">
    <div class="max-w-4xl mx-auto space-y-8 animate-[fade-in-up_1s_ease-out]">
        <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight">
            Instant <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-purple-600">Exam PINs</span> Delivered Fast
        </h1>
        <p class="mt-4 max-w-2xl text-xl text-gray-600 mx-auto">
            Get your WAEC, NECO, and NABTEB scratch cards instantly. Buy securely as a guest or create an account to fund your virtual wallet for faster checkout.
        </p>
        <div class="mt-10 flex flex-col sm:flex-row justify-center gap-4">
            <a href="/catalog" class="px-8 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold rounded-full text-lg shadow-xl shadow-blue-500/30 hover:scale-105 transition-transform duration-300">
                Buy PINs Now
            </a>
            <a href="/register" class="px-8 py-4 glass text-blue-600 font-bold rounded-full text-lg hover:bg-blue-50 transition-colors duration-300 border border-blue-200">
                Create Free Account
            </a>
        </div>
    </div>
    
    <!-- Products Section -->
    <?php if (count($products) > 0): ?>
    <div class="mt-20 w-full max-w-7xl mx-auto px-4">
        <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 text-center mb-12">Available Products</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php foreach ($products as $card): ?>
                <div class="glass relative rounded-3xl p-8 flex flex-col items-center text-center transition-all duration-300 hover:shadow-2xl hover:-translate-y-2 border border-white/40">
                    <?php if (!empty($card['logo'])): ?>
                        <img src="<?= htmlspecialchars($card['logo']) ?>" alt="<?= htmlspecialchars($card['name']) ?>" class="h-32 w-auto object-contain mb-6 drop-shadow-md">
                    <?php else: ?>
                        <div class="h-32 w-32 bg-gradient-to-br from-blue-100 to-blue-50 rounded-2xl flex items-center justify-center mb-6 shadow-inner text-blue-600 text-5xl font-black">
                            <?= substr(htmlspecialchars($card['name']), 0, 1) ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($card['name']) ?></h3>
                    
                    <div class="text-2xl font-black text-blue-600 mb-6 mt-auto">₦<?= number_format($card['selling_price'], 2) ?></div>
                    
                    <?php if (!empty($card['external_link'])): ?>
                        <a href="<?= htmlspecialchars($card['external_link']) ?>" target="_blank" class="w-full block text-center bg-blue-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 text-sm">
                            Visit Link
                        </a>
                    <?php else: ?>
                        <form action="/checkout" method="GET" class="w-full">
                            <input type="hidden" name="card_id" value="<?= htmlspecialchars($card['id']) ?>">
                            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-500/30 text-sm">
                                Purchase Now
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Feature Highlights -->
    <div class="mt-24 grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto w-full">
        <div class="glass p-8 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <h3 class="text-xl font-bold mb-2">Instant Delivery</h3>
            <p class="text-gray-600">PINs and Serial numbers are displayed instantly and sent to your email.</p>
        </div>
        <div class="glass p-8 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h3 class="text-xl font-bold mb-2">Secure Payments</h3>
            <p class="text-gray-600">Pay securely via Payhub or use your funded virtual wallet.</p>
        </div>
        <div class="glass p-8 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-xl flex items-center justify-center mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <h3 class="text-xl font-bold mb-2">24/7 Support</h3>
            <p class="text-gray-600">Our customer support is always available to resolve any issues you face.</p>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
