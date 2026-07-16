<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$site_title = get_setting('site_title', 'EXAM-HUB');
$meta_keywords = get_setting('meta_keywords');
if (empty(trim($meta_keywords))) {
    $meta_keywords = "buy waec scratch card online, neco result checker pin, nabteb pins, buy jamb pin, cheap exam pins nigeria, instant result checker";
}
$meta_desc = get_setting('meta_description');
if (empty(trim($meta_desc))) {
    $meta_desc = "Fast, reliable, and secure platform to purchase WAEC, NECO, NABTEB, and JAMB result checker PINs instantly in Nigeria.";
}

$site_logo = get_setting('site_logo') ?: '/assets/uploads/rectangular_logo_cropped.png';
$custom_favicon = get_setting('site_favicon') ?: '/assets/uploads/custom_favicon.png';
$favicon_url = $custom_favicon ? $custom_favicon : generate_favicon($site_title);
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

$analytics_code = get_setting('analytics_code');
$contact_email = get_setting('contact_email');
$social_facebook = get_setting('social_facebook');
$social_twitter = get_setting('social_twitter');
$social_instagram = get_setting('social_instagram');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?></title>
    <meta name="keywords" content="<?= htmlspecialchars($meta_keywords) ?>">
    <meta name="description" content="<?= htmlspecialchars($meta_desc) ?>">
    <link rel="icon" href="<?= $favicon_url ?>">
    
    <!-- SEO Canonical -->
    <link rel="canonical" href="<?= $current_url ?>">
    
    <!-- Open Graph / Social Media (Facebook, WhatsApp) -->
    <meta property="og:title" content="<?= htmlspecialchars($site_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_desc) ?>">
    <meta property="og:url" content="<?= $current_url ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= $base_url . ($site_logo ? $site_logo : $favicon_url) ?>">
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($site_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta_desc) ?>">
    <meta name="twitter:image" content="<?= $base_url . ($site_logo ? $site_logo : $favicon_url) ?>">

    <!-- JSON-LD Structured Data (Organization Schema) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "<?= htmlspecialchars($site_title) ?>",
      "url": "<?= $base_url ?>",
      "logo": "<?= $base_url . ($site_logo ? $site_logo : $favicon_url) ?>",
      <?php if ($contact_email): ?>"email": "<?= htmlspecialchars($contact_email) ?>",<?php endif; ?>
      "sameAs": [
        "<?= htmlspecialchars($social_facebook ?? '') ?>",
        "<?= htmlspecialchars($social_twitter ?? '') ?>",
        "<?= htmlspecialchars($social_instagram ?? '') ?>"
      ]
    }
    </script>
    
    <!-- Analytics Tracking Code -->
    <?php if (!empty($analytics_code)): ?>
        <?= $analytics_code ?>
    <?php endif; ?>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');
        body { font-family: 'Outfit', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob {
            animation: blob 7s infinite;
        }
        .animation-delay-2000 {
            animation-delay: 2s;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased relative min-h-screen flex flex-col">
    <!-- Animated background blobs -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
        <div class="absolute top-[20%] right-[-10%] w-96 h-96 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>
    </div>

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 glass shadow-sm transition-all duration-300" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center">
                    <a href="/" class="flex-shrink-0 flex items-center gap-3 group">
                        <?php if ($site_logo): ?>
                            <img src="<?= $site_logo ?>" class="h-16 object-contain transform group-hover:scale-105 transition duration-300" alt="Logo">
                        <?php else: ?>
                            <img src="<?= $favicon_url ?>" class="h-16 w-16 transform group-hover:scale-105 transition duration-300" alt="Logo">
                        <?php endif; ?>
                    </a>
                </div>
                <!-- Desktop Menu -->
                <div class="hidden sm:flex sm:items-center space-x-6">
                    <a href="/catalog" class="text-gray-700 hover:text-blue-600 font-medium transition">Buy PINs</a>
                    <a href="/api-docs" class="text-gray-700 hover:text-blue-600 font-medium transition">API Docs</a>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="/user/dashboard" class="text-gray-700 hover:text-blue-600 font-medium transition">Dashboard</a>
                        <a href="/user/profile" class="text-gray-700 hover:text-blue-600 font-medium transition">Profile</a>
                        <a href="/user/tickets" class="text-gray-700 hover:text-blue-600 font-medium transition">Support Tickets</a>
                        <a href="/logout" class="bg-blue-600 text-white px-5 py-2.5 rounded-full font-medium hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">Logout</a>
                    <?php else: ?>
                        <a href="/login" class="text-gray-700 hover:text-blue-600 font-medium transition">Login</a>
                        <a href="/register" class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-2.5 rounded-full font-medium hover:opacity-90 transition shadow-lg shadow-purple-500/30">Sign Up</a>
                    <?php endif; ?>
                </div>
                <!-- Mobile Menu Button -->
                <div class="flex items-center sm:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-700 hover:text-blue-600 focus:outline-none">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" style="display:none;" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" class="sm:hidden glass border-t border-gray-200" style="display:none;">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="/catalog" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">Buy PINs</a>
                <a href="/api-docs" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">API Docs</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="/user/dashboard" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">Dashboard</a>
                    <a href="/user/profile" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">Profile</a>
                    <a href="/user/tickets" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">Support Tickets</a>
                    <a href="/logout" class="block px-3 py-2 rounded-md text-base font-medium text-blue-600 hover:bg-blue-50">Logout</a>
                <?php else: ?>
                    <a href="/login" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-blue-600 hover:bg-blue-50">Login</a>
                    <a href="/register" class="block px-3 py-2 rounded-md text-base font-medium text-blue-600 hover:bg-blue-50">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="flex-grow flex flex-col">
