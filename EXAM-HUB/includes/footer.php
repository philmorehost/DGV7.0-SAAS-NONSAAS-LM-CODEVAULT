    </main>
    <footer class="glass mt-auto py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-sm text-gray-500 flex items-center gap-2">
                    <img src="<?= $favicon_url ?>" class="h-6 w-6 grayscale" alt="Logo">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($site_title ?? 'EXAM-HUB') ?>. All rights reserved.
                </div>
                <div class="flex gap-6 text-sm">
                    <a href="/page?slug=about-us" class="text-gray-500 hover:text-blue-600 transition">About Us</a>
                    <a href="/page?slug=terms" class="text-gray-500 hover:text-blue-600 transition">Terms</a>
                    <a href="/page?slug=privacy" class="text-gray-500 hover:text-blue-600 transition">Privacy</a>
                    <a href="/page?slug=contact" class="text-gray-500 hover:text-blue-600 transition">Contact</a>
                </div>
            </div>
        </div>
    </footer>
    
    <?php
    $whatsapp = get_setting('whatsapp_number');
    if (!empty($whatsapp)):
        $wa_phone = preg_replace('/[^0-9]/', '', $whatsapp);
    ?>
    <a href="https://wa.me/<?= $wa_phone ?>" target="_blank" rel="noopener noreferrer" class="fixed bottom-6 right-6 bg-[#25D366] text-white p-4 rounded-full shadow-2xl hover:scale-110 hover:shadow-[#25D366]/50 transition-all duration-300 z-50 flex items-center justify-center group">
        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <span class="absolute right-16 bg-white text-gray-800 text-sm font-bold px-4 py-2 rounded-xl shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">Chat with us</span>
    </a>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
    <!-- Mobile Bottom Navigation -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-[60]" style="padding-bottom: env(safe-area-inset-bottom);">
        <div class="flex justify-around items-center h-16">
            <a href="/user/dashboard" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-blue-600 <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-blue-600' : '' ?>">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                <span class="text-[10px] mt-1 font-medium">Home</span>
            </a>
            <a href="/catalog" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-blue-600 <?= basename($_SERVER['PHP_SELF']) == 'catalog.php' ? 'text-blue-600' : '' ?>">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                <span class="text-[10px] mt-1 font-medium">Buy PINs</span>
            </a>
            <a href="/user/api" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-blue-600 <?= basename($_SERVER['PHP_SELF']) == 'api.php' ? 'text-blue-600' : '' ?>">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                <span class="text-[10px] mt-1 font-medium">API</span>
            </a>
            <a href="/logout" class="flex flex-col items-center justify-center w-full h-full text-red-500 hover:text-red-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                <span class="text-[10px] mt-1 font-medium">Logout</span>
            </a>
        </div>
    </div>
    <style>
        /* Add padding to the bottom of the body so content isn't hidden behind the sticky nav */
        @media (max-width: 768px) {
            body { padding-bottom: calc(4rem + env(safe-area-inset-bottom)); }
            /* Move whatsapp icon up */
            .fixed.bottom-6.right-6 { bottom: calc(5rem + env(safe-area-inset-bottom)); }
        }
    </style>
    <?php endif; ?>
    
</body>
</html>
