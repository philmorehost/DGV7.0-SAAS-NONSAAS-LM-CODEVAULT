CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `firstname` varchar(100) NOT NULL,
    `lastname` varchar(100) NOT NULL,
    `email` varchar(150) NOT NULL,
    `password` varchar(255) NOT NULL,
    `role` enum('user','admin') DEFAULT 'user',
    `wallet_balance` decimal(10,2) DEFAULT '0.00',
    `virtual_bank_name` varchar(100) DEFAULT NULL,
    `virtual_account_number` varchar(50) DEFAULT NULL,
    `virtual_account_name` varchar(150) DEFAULT NULL,
    `status` enum('active','suspended') DEFAULT 'active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `reference` varchar(50) NOT NULL,
    `type` enum('purchase','deposit','transfer') NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
    `payment_method` varchar(50) DEFAULT NULL,
    `proof_image` varchar(255) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `reference` varchar(50) NOT NULL,
    `card_type_id` int(11) NOT NULL,
    `quantity` int(11) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `status` enum('pending','completed','failed') DEFAULT 'pending',
    `phone` varchar(20) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_pins` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `pin` varchar(100) NOT NULL,
    `serial_no` varchar(100) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `logo` varchar(255) DEFAULT NULL,
    `active_provider` ENUM('vtpass', 'clubkonnect', 'naijaresultpins') NOT NULL DEFAULT 'vtpass',
    `vtpass_id` varchar(50) DEFAULT NULL,
    `clubkonnect_id` varchar(50) DEFAULT NULL,
    `naijaresultpins_id` varchar(50) DEFAULT NULL,
    `original_price` decimal(10,2) NOT NULL DEFAULT '0.00',
    `markup_type` enum('fixed','percentage') NOT NULL DEFAULT 'fixed',
    `markup_value` decimal(10,2) NOT NULL DEFAULT '0.00',
    `selling_price` decimal(10,2) NOT NULL DEFAULT '0.00',
    `status` enum('active','disabled') DEFAULT 'active',
    `logo` varchar(255) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `provider_sync` (`api_provider`, `provider_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `login_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(150) DEFAULT NULL,
    `ip_address` varchar(45) NOT NULL,
    `status` enum('success','failed') NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ip_rules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ip_address` varchar(45) NOT NULL,
    `status` enum('whitelisted','blacklisted') NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `country_rules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `country_code` varchar(5) NOT NULL,
    `country_name` varchar(100) NOT NULL,
    `status` enum('whitelisted','blacklisted','not_specified') DEFAULT 'not_specified',
    PRIMARY KEY (`id`),
    UNIQUE KEY `country_code` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `body` text NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `guest_emails` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(150) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Settings Insert
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
('site_title', 'EXAM-HUB'),
('license_key', ''),
('naijaresultpins_token', ''),
('vtpass_username', ''),
('vtpass_password', ''),
('clubkonnect_userid', ''),
('clubkonnect_apikey', ''),
('payhub_merchant_id', ''),
('bruteforce_username_max_fails', '5'),
('bruteforce_ip_max_fails', '5'),
('bruteforce_lockout_minutes', '60');

CREATE TABLE IF NOT EXISTS `pages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `slug` varchar(50) NOT NULL,
    `title` varchar(100) NOT NULL,
    `content` longtext,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `pages` (`slug`, `title`, `content`) VALUES 
('about-us', 'About Us', '<h2>Welcome to EXAM-HUB</h2><p>EXAM-HUB is Nigeria''s most trusted and reliable platform for purchasing automated exam result checker PINs and tokens. We are dedicated to providing seamless, instant, and secure access to WAEC, NECO, NABTEB, and JAMB e-PINs for students, parents, and schools across the country.</p><h3>Our Mission</h3><p>Our mission is to eliminate the stress and delays traditionally associated with checking exam results. We bridge the gap between examining bodies and students by offering a 100% automated, 24/7 digital delivery system that guarantees you get your PINs delivered to your screen and email instantly.</p><h3>Why Choose Us?</h3><ul><li><strong>Instant Delivery:</strong> Zero waiting time. Your PINs are displayed immediately after successful payment and backed up to your email.</li><li><strong>Unmatched Reliability:</strong> Our systems are directly integrated with top-tier API providers, ensuring maximum uptime and valid PINs every single time.</li><li><strong>Secure Transactions:</strong> We employ bank-grade encryption and partner with industry-leading payment gateways to ensure your financial data is always safe.</li><li><strong>Developer API:</strong> We provide robust API infrastructure for developers and businesses to integrate our services directly into their own applications.</li></ul><p>At EXAM-HUB, we believe education should be accessible, and accessing your results should be the easiest part of the journey.</p>'),
('terms', 'Terms and Conditions', '<h2>Terms and Conditions</h2><p>Welcome to EXAM-HUB. By accessing or using our website, APIs, and services, you agree to be bound by the following Terms and Conditions. Please read them carefully before making any purchases.</p><h3>1. Service Description</h3><p>EXAM-HUB provides a digital platform for the instant purchase and delivery of examination result checker PINs (WAEC, NECO, NABTEB) and registration tokens (JAMB). We act as a technology intermediary between the end-user and the official examining bodies'' authorized vendors.</p><h3>2. Digital Goods and Refund Policy</h3><p>Due to the digital and consumable nature of our products (PINs and Tokens), <strong>all sales are final and non-refundable</strong> once a PIN has been successfully generated and delivered. It is the user''s responsibility to ensure they are purchasing the correct card for the correct examination year and type.</p><h3>3. API Usage</h3><p>If you are granted access to the EXAM-HUB Developer API, you agree to keep your API Key strictly confidential. You are fully responsible for all transactions and activities that occur under your API Key. EXAM-HUB reserves the right to revoke API access immediately if fraudulent activity, rate-limit abuse, or unauthorized domain requests are detected.</p><h3>4. User Responsibilities</h3><ul><li>You must provide an accurate email address and phone number during checkout to ensure receipt of your purchased PINs.</li><li>You agree not to use our platform for any illegal activities, money laundering, or fraudulent card testing.</li><li>We reserve the right to suspend or terminate accounts that violate our terms or engage in abusive behavior towards our systems or staff.</li></ul><h3>5. Limitation of Liability</h3><p>EXAM-HUB shall not be held liable for temporary downtimes experienced by the official examination body portals (e.g., WAECDirect, NECO Results), as we do not control their servers. We only guarantee the validity of the PINs sold.</p>'),
('privacy', 'Privacy Policy', '<h2>Privacy Policy</h2><p>At EXAM-HUB, your privacy is our priority. This Privacy Policy outlines how we collect, use, and protect your personal information when you use our website and services.</p><h3>1. Information We Collect</h3><p>When you use EXAM-HUB, we may collect the following information:</p><ul><li><strong>Personal Details:</strong> Your name, email address, and phone number when you register an account or make a guest purchase.</li><li><strong>Transaction Data:</strong> Payment references, purchase history, and selected payment methods. <em>Note: We do not store your credit/debit card numbers. All payments are processed securely by our trusted payment gateway partners.</em></li><li><strong>Technical Data:</strong> Your IP address, browser type, and device information for security monitoring and fraud prevention.</li></ul><h3>2. How We Use Your Information</h3><p>We use the collected information for the following purposes:</p><ul><li>To instantly deliver your purchased PINs via email.</li><li>To provide customer support and resolve any transaction disputes.</li><li>To send you important account updates, system maintenance notices, or promotional offers (which you can opt out of).</li><li>To prevent fraudulent activities and secure our platform against unauthorized access.</li></ul><h3>3. Data Sharing and Protection</h3><p>We do not sell, trade, or rent your personal information to third parties. We may share necessary transaction data strictly with our payment processing partners and API providers solely for the purpose of fulfilling your order. We implement strict security measures, including SSL encryption, to protect your data.</p><h3>4. Cookies</h3><p>Our website uses cookies to enhance your browsing experience, keep you logged in, and analyze site traffic. You can choose to disable cookies through your browser settings, but this may affect the functionality of our services.</p><h3>5. Changes to This Policy</h3><p>We reserve the right to update this Privacy Policy at any time. Any changes will be reflected on this page with an updated revision date.</p>'),
('contact', 'Contact Us', '<p>Get in touch with us using the form below or via our support channels.</p>');

CREATE TABLE IF NOT EXISTS `api_access` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tickets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `status` enum('open', 'replied', 'closed') DEFAULT 'open',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ticket_messages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` int(11) NOT NULL,
    `sender_id` int(11) NOT NULL,
    `message` text NOT NULL,
    `attachment` varchar(255) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

