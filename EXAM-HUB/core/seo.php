<?php
function get_seo_tags($title, $url, $is_article = false, $author = '') {
    $schema = [];
    
    // BreadcrumbList Schema
    $schema[] = [
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => [
            [
                "@type" => "ListItem",
                "position" => 1,
                "name" => "Home",
                "item" => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/"
            ],
            [
                "@type" => "ListItem",
                "position" => 2,
                "name" => $title,
                "item" => $url
            ]
        ]
    ];
    
    if ($is_article) {
        $schema[] = [
            "@context" => "https://schema.org",
            "@type" => "NewsArticle",
            "headline" => $title,
            "datePublished" => date('c'), // Should be dynamic in a real blog post
            "dateModified" => date('c'),
            "author" => [
                "@type" => "Person",
                "name" => $author ?: "EXAM-HUB Editorial Team",
                "url" => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/about"
            ]
        ];
    }
    
    $json_ld = '';
    foreach ($schema as $s) {
        $json_ld .= '<script type="application/ld+json">' . json_encode($s, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    return $json_ld;
}

function get_ai_visibility_tags($title, $description) {
    return '
    <meta name="ai-visibility" content="enhanced">
    <meta name="robots" content="max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="title" content="'.htmlspecialchars($title).'">
    <meta name="description" content="'.htmlspecialchars($description).'">
    <meta property="og:title" content="'.htmlspecialchars($title).'">
    <meta property="og:description" content="'.htmlspecialchars($description).'">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    ';
}
