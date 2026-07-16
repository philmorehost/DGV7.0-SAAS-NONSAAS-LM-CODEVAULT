<?php
function get_db_connection() {
    static $pdo;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

function get_setting($key, $default = null) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

function set_setting($key, $value) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

function generate_favicon($text) {
    $first_letter = strtoupper(substr(trim($text), 0, 1));
    if (empty($first_letter)) $first_letter = 'E';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#2563eb" rx="20"/><text x="50" y="70" font-family="sans-serif" font-size="60" font-weight="bold" fill="#ffffff" text-anchor="middle">'.$first_letter.'</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
function auto_migrate() {
    $current_version = get_setting('schema_version', '1.0');
    $target_version = '1.12'; // Bumped to add tickets migration

    if ($current_version < $target_version) {
        $pdo = get_db_connection();
        
        // 1.0 to 1.1
        if ($current_version < '1.1') {
            try {
                $pdo->exec("ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `payment_method` varchar(50) DEFAULT NULL");
                $pdo->exec("ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `proof_image` varchar(255) DEFAULT NULL");
            } catch(PDOException $e) {}
        }
        
        // Ensure new routing columns exist
        if ($current_version < '1.5') {
            try {
                $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS logo VARCHAR(255) DEFAULT NULL AFTER name");
            } catch(PDOException $e) {}
        }
        
        // Add email to orders
        if ($current_version < '1.6') {
            try {
                $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER phone");
            } catch(PDOException $e) {}
        }

        // Seed pages (1.7)
        if ($current_version < '1.7') {
            try {
                $about = "<h2>Welcome to EXAM-HUB</h2><p>EXAM-HUB is Nigeria's most trusted and reliable platform for purchasing automated exam result checker PINs and tokens. We are dedicated to providing seamless, instant, and secure access to WAEC, NECO, NABTEB, and JAMB e-PINs for students, parents, and schools across the country.</p><h3>Our Mission</h3><p>Our mission is to eliminate the stress and delays traditionally associated with checking exam results. We bridge the gap between examining bodies and students by offering a 100% automated, 24/7 digital delivery system that guarantees you get your PINs delivered to your screen and email instantly.</p><h3>Why Choose Us?</h3><ul><li><strong>Instant Delivery:</strong> Zero waiting time. Your PINs are displayed immediately after successful payment and backed up to your email.</li><li><strong>Unmatched Reliability:</strong> Our systems are directly integrated with top-tier API providers, ensuring maximum uptime and valid PINs every single time.</li><li><strong>Secure Transactions:</strong> We employ bank-grade encryption and partner with industry-leading payment gateways to ensure your financial data is always safe.</li><li><strong>Developer API:</strong> We provide robust API infrastructure for developers and businesses to integrate our services directly into their own applications.</li></ul><p>At EXAM-HUB, we believe education should be accessible, and accessing your results should be the easiest part of the journey.</p>";
                $terms = "<h2>Terms and Conditions</h2><p>Welcome to EXAM-HUB. By accessing or using our website, APIs, and services, you agree to be bound by the following Terms and Conditions. Please read them carefully before making any purchases.</p><h3>1. Service Description</h3><p>EXAM-HUB provides a digital platform for the instant purchase and delivery of examination result checker PINs (WAEC, NECO, NABTEB) and registration tokens (JAMB). We act as a technology intermediary between the end-user and the official examining bodies' authorized vendors.</p><h3>2. Digital Goods and Refund Policy</h3><p>Due to the digital and consumable nature of our products (PINs and Tokens), <strong>all sales are final and non-refundable</strong> once a PIN has been successfully generated and delivered. It is the user's responsibility to ensure they are purchasing the correct card for the correct examination year and type.</p><h3>3. API Usage</h3><p>If you are granted access to the EXAM-HUB Developer API, you agree to keep your API Key strictly confidential. You are fully responsible for all transactions and activities that occur under your API Key. EXAM-HUB reserves the right to revoke API access immediately if fraudulent activity, rate-limit abuse, or unauthorized domain requests are detected.</p><h3>4. User Responsibilities</h3><ul><li>You must provide an accurate email address and phone number during checkout to ensure receipt of your purchased PINs.</li><li>You agree not to use our platform for any illegal activities, money laundering, or fraudulent card testing.</li><li>We reserve the right to suspend or terminate accounts that violate our terms or engage in abusive behavior towards our systems or staff.</li></ul><h3>5. Limitation of Liability</h3><p>EXAM-HUB shall not be held liable for temporary downtimes experienced by the official examination body portals (e.g., WAECDirect, NECO Results), as we do not control their servers. We only guarantee the validity of the PINs sold.</p>";
                $privacy = "<h2>Privacy Policy</h2><p>At EXAM-HUB, your privacy is our priority. This Privacy Policy outlines how we collect, use, and protect your personal information when you use our website and services.</p><h3>1. Information We Collect</h3><p>When you use EXAM-HUB, we may collect the following information:</p><ul><li><strong>Personal Details:</strong> Your name, email address, and phone number when you register an account or make a guest purchase.</li><li><strong>Transaction Data:</strong> Payment references, purchase history, and selected payment methods. <em>Note: We do not store your credit/debit card numbers. All payments are processed securely by our trusted payment gateway partners.</em></li><li><strong>Technical Data:</strong> Your IP address, browser type, and device information for security monitoring and fraud prevention.</li></ul><h3>2. How We Use Your Information</h3><p>We use the collected information for the following purposes:</p><ul><li>To instantly deliver your purchased PINs via email.</li><li>To provide customer support and resolve any transaction disputes.</li><li>To send you important account updates, system maintenance notices, or promotional offers (which you can opt out of).</li><li>To prevent fraudulent activities and secure our platform against unauthorized access.</li></ul><h3>3. Data Sharing and Protection</h3><p>We do not sell, trade, or rent your personal information to third parties. We may share necessary transaction data strictly with our payment processing partners and API providers solely for the purpose of fulfilling your order. We implement strict security measures, including SSL encryption, to protect your data.</p><h3>4. Cookies</h3><p>Our website uses cookies to enhance your browsing experience, keep you logged in, and analyze site traffic. You can choose to disable cookies through your browser settings, but this may affect the functionality of our services.</p><h3>5. Changes to This Policy</h3><p>We reserve the right to update this Privacy Policy at any time. Any changes will be reflected on this page with an updated revision date.</p>";
                
                $stmt = $pdo->prepare("UPDATE pages SET content = ? WHERE slug = ?");
                $stmt->execute([$about, 'about-us']);
                $stmt->execute([$terms, 'terms']);
                $stmt->execute([$privacy, 'privacy']);
            } catch(PDOException $e) {}
        }

        // Virtual Bank Accounts (1.8 / 1.9)
        if ($current_version < '1.9') {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `virtual_bank_name` varchar(100) DEFAULT NULL");
            } catch(PDOException $e) {}
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `virtual_account_number` varchar(50) DEFAULT NULL");
            } catch(PDOException $e) {}
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `virtual_account_name` varchar(150) DEFAULT NULL");
            } catch(PDOException $e) {}
        }

        $schema_path = __DIR__ . '/../install/schema.sql';
        
        if (file_exists($schema_path)) {
            $schema = file_get_contents($schema_path);
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            try {
                foreach($statements as $stmt) {
                    if(!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }
                
                // Extra alter statements that might not be in schema.sql but need to run
                $pdo->exec("ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL");
                
                try {
                    $pdo->exec("ALTER TABLE products DROP COLUMN IF EXISTS api_provider");
                    $pdo->exec("ALTER TABLE products DROP COLUMN IF EXISTS provider_product_id");
                    $pdo->exec("ALTER TABLE products DROP COLUMN IF EXISTS is_hidden");
                    
                    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS active_provider ENUM('vtpass', 'clubkonnect', 'naijaresultpins') NOT NULL DEFAULT 'vtpass'");
                    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS vtpass_id VARCHAR(50) DEFAULT NULL");
                    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS clubkonnect_id VARCHAR(50) DEFAULT NULL");
                    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS naijaresultpins_id VARCHAR(50) DEFAULT NULL");
                    
                    $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
                    if ($count > 4) { // Only truncate and reseed if the old duplicate data is still there
                        $pdo->exec("TRUNCATE TABLE products");
                        $stmt = $pdo->prepare("INSERT INTO products (name, active_provider, vtpass_id, clubkonnect_id, naijaresultpins_id, original_price, selling_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute(['WAEC Result Checker', 'vtpass', 'waec|waec', 'waec|01', '1', 3500, 3700, 'active']);
                        $stmt->execute(['NECO Result Checker', 'vtpass', 'neco|neco', 'neco|01', '2', 1200, 1500, 'active']);
                        $stmt->execute(['NABTEB Result Checker', 'vtpass', 'nabteb|nabteb', 'nabteb|01', '3', 1000, 1200, 'active']);
                        $stmt->execute(['JAMB E-Pin', 'vtpass', 'jamb|jamb', 'jamb|01', '4', 6200, 6500, 'active']);
                    }
                } catch(PDOException $e) {}
                
            } catch (PDOException $e) {
                // We silently fail in production so users don't see raw SQL errors, 
                // but the check will re-run next time.
            }
        } // Closing brace for if (file_exists($schema_path))
        
        // Phone number capture (1.11) - MUST run before schema_version is set
        if ($current_version < '1.11') {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL");
            } catch(PDOException $e) {}
        }

        // Support Tickets Tables (1.12)
        if ($current_version < '1.12') {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `tickets` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `subject` varchar(255) NOT NULL,
                    `status` enum('open', 'replied', 'closed') DEFAULT 'open',
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS `ticket_messages` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `ticket_id` int(11) NOT NULL,
                    `sender_id` int(11) NOT NULL,
                    `message` text NOT NULL,
                    `attachment` varchar(255) DEFAULT NULL,
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            } catch(PDOException $e) {}
        }

        // Set schema version AFTER all migrations have run
        set_setting('schema_version', $target_version);
    }
}

// Trigger auto migration on every page load (it only runs SQL if version is outdated)
auto_migrate();
// Runtime License Validation Hook
if (file_exists(__DIR__ . '/license.php')) {
    require_once __DIR__ . '/license.php';
}
