<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';
$pdo = get_db_connection();

$queries = [
    "CREATE TABLE IF NOT EXISTS `products` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `api_provider` varchar(50) NOT NULL,
        `provider_product_id` varchar(50) NOT NULL,
        `original_price` decimal(10,2) NOT NULL DEFAULT '0.00',
        `markup_type` enum('fixed','percentage') NOT NULL DEFAULT 'fixed',
        `markup_value` decimal(10,2) NOT NULL DEFAULT '0.00',
        `selling_price` decimal(10,2) NOT NULL DEFAULT '0.00',
        `status` enum('active','disabled') DEFAULT 'active',
        `logo` varchar(255) DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `provider_sync` (`api_provider`, `provider_product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    "INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
    ('vtpass_username', ''),
    ('vtpass_password', ''),
    ('clubkonnect_userid', ''),
    ('clubkonnect_apikey', '')",
    
    "CREATE TABLE IF NOT EXISTS `pages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `slug` varchar(50) NOT NULL,
        `title` varchar(100) NOT NULL,
        `content` longtext,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    "INSERT IGNORE INTO `pages` (`slug`, `title`, `content`) VALUES 
    ('about-us', 'About Us', '<p>Welcome to EXAM-HUB. This is the about us page. Admin can edit this.</p>'),
    ('terms', 'Terms and Conditions', '<p>These are the terms and conditions. Admin can edit this.</p>'),
    ('privacy', 'Privacy Policy', '<p>This is the privacy policy. Admin can edit this.</p>'),
    ('contact', 'Contact Us', '<p>Get in touch with us using the form below or via our support channels.</p>')",
    
    "CREATE TABLE IF NOT EXISTS `api_access` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `domain_name` varchar(255) NOT NULL,
        `api_key` varchar(100) DEFAULT NULL,
        `status` enum('pending','approved','rejected') DEFAULT 'pending',
        `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
        `discount_value` decimal(10,2) DEFAULT '0.00',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `api_key` (`api_key`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL",
    
    "CREATE TABLE IF NOT EXISTS `tickets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `subject` varchar(255) NOT NULL,
        `status` enum('open', 'replied', 'closed') DEFAULT 'open',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    "CREATE TABLE IF NOT EXISTS `ticket_messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `ticket_id` int(11) NOT NULL,
        `sender_id` int(11) NOT NULL,
        `message` text NOT NULL,
        `attachment` varchar(255) DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        echo "Executed: " . substr($q, 0, 50) . "...<br>\n";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "<br>\n";
    }
}
echo "Done";
