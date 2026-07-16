<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

header("Content-Type: application/xml; charset=utf-8");
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$pages = [
    '/',
    '/catalog',
    '/login',
    '/register',
    '/about'
];

foreach ($pages as $page) {
    echo "  <url>\n";
    echo "      <loc>" . htmlspecialchars($base_url . $page) . "</loc>\n";
    echo "      <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "      <changefreq>daily</changefreq>\n";
    echo "      <priority>" . ($page === '/' ? '1.0' : '0.8') . "</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
