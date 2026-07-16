<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * CodeVault Unified Router & Main Controller
 * Establishes sessions, intercepts CRUD actions, and renders modular panels
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to installer if config is missing
$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

// Clean SEF URL routing logic
$clean_route = '';
if (isset($_GET['_route'])) {
    $clean_route = trim($_GET['_route'], '/');
} else {
    // Fallback: parse request URI
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($request_uri, PHP_URL_PATH);
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_dir = dirname($script_name);
    // Replace backslashes with forward slashes for Windows local development
    $base_dir = str_replace('\\', '/', $base_dir);
    if ($base_dir !== '/' && $base_dir !== '') {
        if (strpos($path, $base_dir) === 0) {
            $path = substr($path, strlen($base_dir));
        }
    }
    // Remove index.php prefix if present
    if (strpos($path, '/index.php') === 0) {
        $path = substr($path, 10);
    }
    $clean_route = trim($path, '/');
}

if (!empty($clean_route) && $clean_route !== 'index.php') {
    $parts = explode('/', $clean_route);
    if (count($parts) > 0) {
        $clean_page = $parts[0];
        
        $route_mappings = [
            'marketplace' => 'marketplace',
            'product' => 'product',
            'dashboard' => 'dashboard',
            'blog' => 'blog',
            'forums' => 'forums',
            'tutorials' => 'tutorials',
            'affiliate' => 'affiliate',
            'seller' => 'seller_profile',
            'seller-profile' => 'seller_profile',
            'flash-sale' => 'flash_sale',
            'free-files' => 'free_files',
            'collections' => 'collections',
            'policies' => 'policies',
            'help' => 'help_center',
            'help_center' => 'help_center',
        ];
        
        if (isset($route_mappings[$clean_page])) {
            $_GET['page'] = $route_mappings[$clean_page];
            if (isset($parts[1])) {
                if (is_numeric($parts[1])) {
                    $_GET['id'] = intval($parts[1]);
                } else {
                    $_GET['slug'] = $parts[1];
                }
            }
        }
    }
}

// Initialize session cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$page = isset($_GET['page']) ? trim($_GET['page']) : 'marketplace';

// Dynamic XML Sitemap Generator
if ($clean_route === 'sitemap.xml' || $action === 'sitemap') {
    header('Content-Type: application/xml; charset=utf-8');
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || ($_SERVER['SERVER_PORT'] == 443);
    $protocol = $is_https ? 'https' : 'http';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_dir = dirname($script_name);
    $base_dir = str_replace('\\', '/', $base_dir);
    $base_path = ($base_dir === '/' || $base_dir === '\\') ? '' : $base_dir;
    $site_url = $protocol . '://' . $host . $base_path;
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Base static pages
    $pages = ['', 'marketplace', 'collections', 'flash-sale', 'free-files', 'affiliate', 'blog'];
    foreach ($pages as $p) {
        $loc = empty($p) ? $site_url . '/' : $site_url . '/' . $p;
        if (get_setting('clean_urls', '1') === '0') {
            $loc = empty($p) ? $site_url . '/index.php' : $site_url . '/index.php?page=' . str_replace('-', '_', $p);
        }
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($loc) . '</loc>' . "\n";
        echo '    <changefreq>daily</changefreq>' . "\n";
        echo '    <priority>' . (empty($p) ? '1.0' : '0.8') . '</priority>' . "\n";
        echo '  </url>' . "\n";
    }
    
    // Approved products
    $prod_stmt = $db->query("SELECT id, slug, created_at FROM products WHERE status = 'approved' ORDER BY id DESC");
    while ($p_row = $prod_stmt->fetch()) {
        $loc = $site_url . '/product/' . $p_row['id'];
        if (get_setting('clean_urls', '1') === '0') {
            $loc = $site_url . '/index.php?page=product&amp;id=' . $p_row['id'];
        }
        $lastmod = date('c', strtotime($p_row['created_at']));
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($loc) . '</loc>' . "\n";
        echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.7</priority>' . "\n";
        echo '  </url>' . "\n";
    }
    
    // Blog articles
    $blog_stmt = $db->query("SELECT id, created_at FROM blog_posts ORDER BY id DESC");
    while ($b_row = $blog_stmt->fetch()) {
        $loc = $site_url . '/blog?post_id=' . $b_row['id'];
        $lastmod = date('c', strtotime($b_row['created_at']));
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($loc) . '</loc>' . "\n";
        echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.6</priority>' . "\n";
        echo '  </url>' . "\n";
    }
    
    echo '</urlset>' . "\n";
    exit;
}

// Dynamic robots.txt Generator
if ($clean_route === 'robots.txt' || $action === 'robots') {
    header('Content-Type: text/plain; charset=utf-8');
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || ($_SERVER['SERVER_PORT'] == 443);
    $protocol = $is_https ? 'https' : 'http';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_dir = dirname($script_name);
    $base_dir = str_replace('\\', '/', $base_dir);
    $base_path = ($base_dir === '/' || $base_dir === '\\') ? '' : $base_dir;
    $site_url = $protocol . '://' . $host . $base_path;
    
    $sitemap_url = $site_url . '/sitemap.xml';
    if (get_setting('clean_urls', '1') === '0') {
        $sitemap_url = $site_url . '/index.php?action=sitemap';
    }
    
    echo "User-agent: *\n";
    echo "Disallow: /admin/\n";
    echo "Disallow: /index.php?page=dashboard\n";
    echo "Disallow: " . $base_path . "/dashboard\n";
    echo "\n";
    echo "Sitemap: " . $sitemap_url . "\n";
    exit;
}

// Dynamic ads.txt Generator
if ($clean_route === 'ads.txt' || $action === 'ads') {
    header('Content-Type: text/plain; charset=utf-8');
    echo get_setting('ads_txt_content', '');
    exit;
}

// Dynamic Favicon Generator (SVG)
if ($clean_route === 'favicon.ico' || $clean_route === 'favicon.svg' || $action === 'favicon') {
    header('Content-Type: image/svg+xml');
    
    $site_name = get_platform_name();
    $first_letter = mb_strtoupper(mb_substr($site_name, 0, 1));
    if (empty($first_letter)) {
        $first_letter = 'C';
    }
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
      <defs>
        <linearGradient id="favGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#5cb85c" />
          <stop offset="100%" stop-color="#4cae4c" />
        </linearGradient>
      </defs>
      <rect width="32" height="32" rx="8" fill="url(#favGrad)" />
      <text x="16" y="21" font-family="system-ui, -apple-system, sans-serif" font-weight="900" font-size="19" fill="#ffffff" text-anchor="middle" dominant-baseline="middle"><?php echo htmlspecialchars($first_letter); ?></text>
    </svg>
    <?php
    exit;
}

$error = '';
$success = '';

// Handle referral tracking cookie if passed in URL
if (isset($_GET['ref'])) {
    $ref_id = intval($_GET['ref']);
    if ($ref_id > 0) {
        setcookie('ref_by', $ref_id, time() + (86400 * 30), "/"); // active for 30 days
    }
}

// ---------------- ACTION LISTENERS (POSTS INTERCEPTOR) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($action)) {
    
    // AJAX Image Upload & Auto-WebP Optimization
    if ($action === 'image_upload_ajax') {
        header('Content-Type: application/json');
        if (get_setting('demo_mode', '0') === '1' && !is_admin()) {
            echo json_encode(['status' => 'error', 'message' => 'Uploads are disabled in read-only Demo Mode.']);
            exit;
        }
        if (!is_logged_in() || ($_SESSION['user_role'] !== 'seller' && !is_admin())) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized upload attempt.']);
            exit;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded or file transfer error.']);
            exit;
        }

        $file = $_FILES['file'];
        $temp_path = $file['tmp_name'];
        $file_name = basename($file['name']);
        
        // Mime validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $temp_path);
        finfo_close($finfo);

        $allowed_mimes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
            'image/webp', 'image/svg+xml', 'image/bmp'
        ];

        if (!in_array($mime_type, $allowed_mimes)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file format. Only images are allowed.']);
            exit;
        }

        // Define uploads directory relative to Document Root
        $upload_dir = __DIR__ . '/uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $title_param = isset($_POST['title']) ? trim($_POST['title']) : '';
        $type_param = isset($_POST['type']) ? trim($_POST['type']) : 'screenshot'; // thumb vs screenshot
        
        $base_name = 'product-asset';
        if (!empty($title_param)) {
            $slug = preg_replace('~[^\pL\d]+~u', '-', $title_param);
            if (function_exists('iconv')) {
                $slug = @iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
            }
            $slug = preg_replace('~[^-\w]+~', '', $slug);
            $slug = trim($slug, '-');
            $slug = preg_replace('~-+~', '-', $slug);
            $slug = strtolower($slug);
            if (!empty($slug)) {
                $base_name = $slug;
            }
        } else {
            $orig_name = pathinfo($file_name, PATHINFO_FILENAME);
            $slug = preg_replace('~[^\pL\d]+~u', '-', $orig_name);
            $slug = preg_replace('~[^-\w]+~', '', $slug);
            $slug = trim($slug, '-');
            $slug = preg_replace('~-+~', '-', $slug);
            $slug = strtolower($slug);
            if (!empty($slug)) {
                $base_name = $slug;
            }
        }

        $uniq_suffix = substr(md5(uniqid(microtime(), true)), 0, 8);
        $target_extension = 'webp';
        
        $final_name = $base_name . '-' . $type_param . '-' . $uniq_suffix;
        $original_size = filesize($temp_path);
        $gd_converted = false;
        $final_path = '';

        if (function_exists('imagewebp') && $mime_type !== 'image/svg+xml') {
            $src_img = null;
            if ($mime_type === 'image/jpeg' || $mime_type === 'image/jpg') {
                $src_img = imagecreatefromjpeg($temp_path);
            } elseif ($mime_type === 'image/png') {
                $src_img = imagecreatefrompng($temp_path);
                if ($src_img) {
                    imagealphablending($src_img, false);
                    imagesavealpha($src_img, true);
                }
            } elseif ($mime_type === 'image/webp') {
                $src_img = imagecreatefromwebp($temp_path);
            }
            
            if ($src_img) {
                $webp_temp = tempnam(sys_get_temp_dir(), 'webp');
                imagewebp($src_img, $webp_temp, 85);
                imagedestroy($src_img);
                
                $webp_size = filesize($webp_temp);
                
                // Compare sizes and keep the smaller one
                if ($webp_size < $original_size) {
                    $final_path = $upload_dir . $final_name . '.webp';
                    rename($webp_temp, $final_path);
                    $target_extension = 'webp';
                    $gd_converted = true;
                } else {
                    unlink($webp_temp);
                }
            }
        }
        
        if (!$gd_converted) {
            $orig_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($orig_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $orig_ext = 'jpg';
            }
            $target_extension = $orig_ext;
            $final_path = $upload_dir . $final_name . '.' . $target_extension;
            move_uploaded_file($temp_path, $final_path);
        }

        // Return public relative path
        $public_url = 'uploads/' . basename($final_path);
        echo json_encode([
            'status' => 'success', 
            'url' => $public_url,
            'original_size' => $original_size,
            'final_size' => filesize($final_path),
            'optimized' => ($target_extension === 'webp')
        ]);
        exit;
    }

    // Auth: Login
    if ($action === 'login') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $pass = isset($_POST['pass']) ? trim($_POST['pass']) : '';
        
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user_row = $stmt->fetch();
        
        if ($user_row && password_verify($pass, $user_row['password'])) {
            $_SESSION['user_id'] = $user_row['id'];
            $_SESSION['user_name'] = $user_row['name'];
            $_SESSION['user_role'] = $user_row['role'];
            $success = "Welcome back, " . htmlspecialchars($user_row['name']) . "!";
        } else {
            $error = "Malformed credentials or incorrect password. Please retry.";
        }
        header("Location: index.php?page=" . urlencode($page));
        exit;
    }

    // Auth: Forgot Password Request
    if ($action === 'forgot_password') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        if (empty($email)) {
            $_SESSION['flash_error'] = "Email address is required.";
            header("Location: index.php?page=" . urlencode($page));
            exit;
        }

        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user_row = $stmt->fetch();

        if ($user_row) {
            $otp = strval(rand(100000, 999999));
            $expiry = date('Y-m-d H:i:s', time() + 900); // 15 minutes
            
            $up_otp = $db->prepare("UPDATE users SET reset_otp = ?, reset_otp_expires_at = ? WHERE id = ?");
            $up_otp->execute([$otp, $expiry, $user_row['id']]);

            $site_name = get_platform_name();
            $subject = "Password Reset Verification Code: " . $otp;
            $body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="utf-8"></head>
            <body style="font-family: sans-serif; padding: 20px; color: #333;">
                <h2>Password Reset Request</h2>
                <p>Hello ' . htmlspecialchars($user_row['name']) . ',</p>
                <p>We received a request to reset your password. Use the following 6-digit One-Time Password (OTP) to complete the verification. This OTP is valid for 15 minutes.</p>
                <div style="font-size: 24px; font-weight: bold; background: #f7fafc; border: 1px solid #e2e8f0; padding: 15px; text-align: center; border-radius: 6px; margin: 20px 0; color: #5cb85c; letter-spacing: 4px;">
                    ' . $otp . '
                </div>
                <p>If you did not request this reset, please ignore this email.</p>
                <p>Thanks,<br>' . htmlspecialchars($site_name) . '</p>
            </body>
            </html>';

            send_custom_email($email, $subject, $body);
        }

        $_SESSION['flash_success'] = "If the account exists, an OTP code has been dispatched to your email.";
        header("Location: index.php?page=" . urlencode($page) . "&forgot_success=1&email=" . urlencode($email));
        exit;
    }

    // Auth: Reset Password Verify OTP
    if ($action === 'reset_password') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
        $pass = isset($_POST['pass']) ? trim($_POST['pass']) : '';

        if (empty($email) || empty($otp) || empty($pass)) {
            $_SESSION['flash_error'] = "All reset fields are required.";
            header("Location: index.php?page=" . urlencode($page) . "&forgot_success=1&email=" . urlencode($email));
            exit;
        }

        $stmt = $db->prepare("SELECT id, reset_otp, reset_otp_expires_at FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user_row = $stmt->fetch();

        if ($user_row && !empty($user_row['reset_otp']) && $user_row['reset_otp'] === $otp) {
            $now = date('Y-m-d H:i:s');
            if ($user_row['reset_otp_expires_at'] >= $now) {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);
                $up = $db->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expires_at = NULL WHERE id = ?");
                $up->execute([$hash, $user_row['id']]);

                $_SESSION['flash_success'] = "Password reset successful! You can now sign in.";
                header("Location: index.php?page=" . urlencode($page));
                exit;
            } else {
                $_SESSION['flash_error'] = "The OTP code has expired. Please request a new code.";
            }
        } else {
            $_SESSION['flash_error'] = "Invalid verification OTP code. Please verify and retry.";
        }

        header("Location: index.php?page=" . urlencode($page) . "&forgot_success=1&email=" . urlencode($email));
        exit;
    }
    
    // Auth: Register
    if ($action === 'register') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $pass = isset($_POST['pass']) ? trim($_POST['pass']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : ''; // 'buyer', 'seller'
        
        if (empty($name) || empty($email) || empty($pass)) {
            $_SESSION['flash_error'] = "All registration fields are required.";
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "Enter a valid email address.";
        } else {
            // Check uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $_SESSION['flash_error'] = "This email is already registered.";
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);
                
                // Referral link check
                $referred_by = isset($_COOKIE['ref_by']) ? intval($_COOKIE['ref_by']) : null;
                
                $ins = $db->prepare("INSERT INTO users (name, email, password, role, referred_by) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$name, $email, $hash, $role, $referred_by]);
                $new_id = $db->lastInsertId();
                
                // Initialize wallet
                $db->prepare("INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.0, 0.0)")->execute([$new_id]);
                
                // Add affiliate attribution record if referred_by is logged
                if ($referred_by) {
                    $db->prepare("INSERT INTO affiliate_referrals (referrer_id, referred_id, amount) VALUES (?, ?, 0.0)")->execute([$referred_by, $new_id]);
                    // Clear cookie
                    setcookie('ref_by', '', time() - 3600, '/');
                }
                
                $_SESSION['user_id'] = $new_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = $role;
                $_SESSION['flash_success'] = "Account successfully created!";
                
                // Welcome email
                $site_name = get_platform_name();
                $subject = "Welcome to " . $site_name . "!";
                $body = '
                <!DOCTYPE html>
                <html>
                <head><meta charset="utf-8"></head>
                <body style="font-family: sans-serif; padding: 20px; color: #333;">
                    <h2>Welcome to ' . htmlspecialchars($site_name) . ', ' . htmlspecialchars($name) . '!</h2>
                    <p>We are excited to have you on board as a ' . htmlspecialchars($role) . '.</p>
                    <p>You can now log in and manage your account.</p>
                </body>
                </html>';
                send_custom_email($email, $subject, $body);
            }
        }
        header("Location: index.php?page=" . urlencode($page));
        exit;
    }
    
    // Auth: Logout
    if ($action === 'logout') {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_name']);
        unset($_SESSION['user_role']);
        session_destroy();
        header("Location: index.php?page=marketplace");
        exit;
    }

    // Cart: Add item from Home
    if ($action === 'cart_add') {
        $id = intval($_POST['product_id']);
        if ($id > 0 && !in_array($id, $_SESSION['cart'])) {
            $_SESSION['cart'][] = $id;
        }
        header("Location: index.php?page=marketplace");
        exit;
    }

    // Cart: Add item from Product Details
    if ($action === 'cart_add_detail') {
        $id = intval($_POST['product_id']);
        $license_type = isset($_POST['license_type']) ? trim($_POST['license_type']) : 'standard';
        if ($id > 0 && !in_array($id, $_SESSION['cart'])) {
            $_SESSION['cart'][] = $id;
            $_SESSION['cart_licenses'][$id] = $license_type;
        }
        header("Location: index.php?page=product&id=" . $id);
        exit;
    }

    // Cart: Remove item from drawer
    if ($action === 'cart_remove') {
        $id = intval($_POST['product_id']);
        $_SESSION['cart'] = array_values(array_diff($_SESSION['cart'], [$id]));
        header("Location: index.php?page=" . urlencode($page));
        exit;
    }

    // License: Generate license key on demand/retry
    if ($action === 'generate_license') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "You must log in to perform this action.";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        }
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $buyer = get_logged_in_user();
        
        // Fetch purchase record
        $purch_stmt = $db->prepare("SELECT pu.*, p.licensing_enabled, p.license_manager_url, p.license_manager_secret FROM purchases pu JOIN products p ON pu.product_id = p.id WHERE pu.id = ? AND pu.buyer_id = ?");
        $purch_stmt->execute([$purchase_id, $buyer['id']]);
        $purchase = $purch_stmt->fetch();
        
        if ($purchase) {
            if (empty($purchase['license_key']) && !empty($purchase['licensing_enabled']) && intval($purchase['licensing_enabled']) === 1) {
                $license_key = generate_product_license($purchase, $buyer['email'], $purchase['license_type'] ?? 'standard');
                if ($license_key) {
                    $db->prepare("UPDATE purchases SET license_key = ? WHERE id = ?")->execute([$license_key, $purchase_id]);
                    $_SESSION['flash_success'] = "License key generated successfully!";
                } else {
                    $_SESSION['flash_error'] = "Failed to generate license key. The licensing server might be offline or misconfigured. Please try again later.";
                }
            } else {
                $_SESSION['flash_error'] = "License already generated or licensing not required for this product.";
            }
        } else {
            $_SESSION['flash_error'] = "Purchase record not found.";
        }
        header("Location: index.php?page=dashboard&tab=purchases");
        exit;
    }

    // License: Upgrade standard license to extended license using wallet balance
    if ($action === 'upgrade_license') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "You must log in to perform this action.";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        }
        
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $buyer = get_logged_in_user();
        
        // Fetch purchase record along with product licensing details
        $purch_stmt = $db->prepare("SELECT pu.*, p.licensing_enabled, p.license_manager_url, p.license_manager_secret, p.extended_price, p.seller_id, p.title FROM purchases pu JOIN products p ON pu.product_id = p.id WHERE pu.id = ? AND pu.buyer_id = ?");
        $purch_stmt->execute([$purchase_id, $buyer['id']]);
        $purchase = $purch_stmt->fetch();
        
        if (!$purchase) {
            $_SESSION['flash_error'] = "Purchase record not found.";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        }
        
        if (empty($purchase['licensing_enabled']) || intval($purchase['licensing_enabled']) !== 1 || empty($purchase['license_key'])) {
            $_SESSION['flash_error'] = "This purchase is not eligible for upgrades (licensing inactive or key not generated).";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        }
        
        if (($purchase['license_type'] ?? 'standard') === 'extended') {
            $_SESSION['flash_error'] = "This purchase is already on the Extended License level.";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        }
        
        $extended_price = floatval($purchase['extended_price']);
        if ($extended_price <= 0) {
            $_SESSION['flash_error'] = "The seller has not configured an Extended License price for this product.";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        }
        
        $upgrade_cost = $extended_price - floatval($purchase['amount']);
        if ($upgrade_cost <= 0) {
            $_SESSION['flash_error'] = "Invalid upgrade cost configuration.";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        }
        
        // Fetch buyer's wallet
        $w_stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
        $w_stmt->execute([$buyer['id']]);
        $buyer_wallet = $w_stmt->fetch();
        $buyer_balance = floatval($buyer_wallet['balance'] ?? 0.0);
        
        if ($buyer_balance < $upgrade_cost) {
            $_SESSION['flash_error'] = sprintf("Insufficient wallet balance. Upgrade requires $%s, but you have $%s.", number_format($upgrade_cost, 2), number_format($buyer_balance, 2));
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        }
        
        try {
            $db->beginTransaction();
            
            // Deduct from buyer
            $db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?")
               ->execute([$upgrade_cost, $buyer['id']]);
               
            // Settle seller wallet (commission + escrow hold)
            $comm_percent = defined('PLATFORM_COMMISSION') ? floatval(PLATFORM_COMMISSION) : 15.0;
            $seller_share = $upgrade_cost * ((100.0 - $comm_percent) / 100.0);
            
            $escrow_days = intval(get_setting('escrow_lock_days', 7));
            if ($escrow_days > 0) {
                $db->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?")
                   ->execute([$seller_share, $purchase['seller_id']]);
                $status = 'pending_clearance';
                $available_at = date('Y-m-d H:i:s', time() + ($escrow_days * 86400));
            } else {
                $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
                   ->execute([$seller_share, $purchase['seller_id']]);
                $status = 'completed';
                $available_at = date('Y-m-d H:i:s');
            }
            
            // Record seller transactions and notifications
            $db->prepare("INSERT INTO transactions (user_id, amount, type, status, paystack_ref, available_at) VALUES (?, ?, 'sale', ?, ?, ?)")
               ->execute([$purchase['seller_id'], $seller_share, $status, 'pstk_upg_' . uniqid(), $available_at]);
               
            $db->prepare("INSERT INTO notifications (user_id, type, product_id, message) VALUES (?, 'sale', ?, ?)")
               ->execute([$purchase['seller_id'], $purchase['product_id'], "License Upgrade! " . htmlspecialchars($buyer['name']) . " upgraded " . htmlspecialchars($purchase['title']) . " to Extended License."]);
            
            // Call License Manager Upgrade API
            $api_url = rtrim($purchase['license_manager_url'], '/') . '/api-upgrade-license.php';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'api_secret' => $purchase['license_manager_secret'],
                'license_key' => $purchase['license_key']
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("License Manager connection timeout: " . $curl_error);
            }
            
            $api_res = json_decode($response, true);
            if (!$api_res || !isset($api_res['success']) || !$api_res['success']) {
                $err_msg = $api_res['message'] ?? 'Unknown error';
                throw new Exception("License Manager API rejected request: " . $err_msg);
            }
            
            // Update purchase record
            $db->prepare("UPDATE purchases SET license_type = 'extended', amount = amount + ? WHERE id = ?")
               ->execute([$upgrade_cost, $purchase_id]);
               
            $db->commit();
            $_SESSION['flash_success'] = "Congratulations! Your license has been upgraded to Extended (Multi-domain).";
            
        } catch (Exception $ex) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("CodeVault Upgrade License Exception: " . $ex->getMessage());
            $_SESSION['flash_error'] = "Upgrade Failed: " . $ex->getMessage();
        }
        
        header("Location: index.php?page=dashboard&tab=purchases");
        exit;
    }

    // Checkout: Simulate Paystack transaction and instant purchase delivery
    if ($action === 'cart_checkout') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "You must log in to complete checkout.";
            header("Location: index.php?page=" . urlencode($page));
            exit;
        }
        
        $buyer = get_logged_in_user();
        if (empty($_SESSION['cart'])) {
            $_SESSION['flash_error'] = "Your cart is empty.";
            header("Location: index.php?page=marketplace");
            exit;
        }
        
        $coupon = isset($_SESSION['applied_coupon']) ? $_SESSION['applied_coupon'] : null;
        
        // Check if the buyer was referred and this is their first purchase
        $is_first_purchase = false;
        if (get_setting('affiliate_system', '1') === '1' && !empty($buyer['referred_by'])) {
            $prior_purchases_stmt = $db->prepare("SELECT COUNT(*) FROM purchases WHERE buyer_id = ?");
            $prior_purchases_stmt->execute([$buyer['id']]);
            if (intval($prior_purchases_stmt->fetchColumn()) === 0) {
                $is_first_purchase = true;
            }
        }
        
        // Loop over items to checkout and simulate purchase success
        try {
            $db->beginTransaction();
            
            // Calculate total original price first
            $total_orig = 0.0;
            $p_rows = [];
            foreach($_SESSION['cart'] as $pid) {
                $p_stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
                $p_stmt->execute([$pid]);
                $p_row = $p_stmt->fetch();
                if ($p_row) {
                    $p_rows[] = $p_row;
                    $item_license_type = $_SESSION['cart_licenses'][$pid] ?? 'standard';
                    $base_price = ($item_license_type === 'extended' && !empty($p_row['extended_price'])) ? floatval($p_row['extended_price']) : floatval($p_row['discount_price'] ?: $p_row['price']);
                    $total_orig += $base_price;
                }
            }
            
            $final_total = $total_orig;
            if ($coupon) {
                if ($coupon['type'] === 'percentage') {
                    $discount = $total_orig * ($coupon['value'] / 100);
                    $final_total = max(0.0, $total_orig - $discount);
                } else { // fixed
                    $discount = floatval($coupon['value']);
                    $final_total = max(0.0, $total_orig - $discount);
                }
                // Update coupon use count
                $db->prepare("UPDATE coupon_codes SET uses_count = uses_count + 1 WHERE id = ?")->execute([$coupon['id']]);
            }
            
            $ratio = $total_orig > 0 ? ($final_total / $total_orig) : 1;
            
            foreach($p_rows as $p_row) {
                $pid = $p_row['id'];
                $item_license_type = $_SESSION['cart_licenses'][$pid] ?? 'standard';
                $base_price = ($item_license_type === 'extended' && !empty($p_row['extended_price'])) ? floatval($p_row['extended_price']) : floatval($p_row['discount_price'] ?: $p_row['price']);
                $item_price = $base_price * $ratio;
                $seller_id = $p_row['seller_id'];
                
                // Create purchase record
                $license_key = null;
                if (!empty($p_row['licensing_enabled']) && intval($p_row['licensing_enabled']) === 1) {
                    $license_key = generate_product_license($p_row, $buyer['email'], $item_license_type);
                }

                $db->prepare("INSERT INTO purchases (buyer_id, product_id, amount, license_key, license_type) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$buyer['id'], $pid, $item_price, $license_key, $item_license_type]);
                
                // Settle seller wallet with dynamic escrow lock duration
                $comm_percent = defined('PLATFORM_COMMISSION') ? floatval(PLATFORM_COMMISSION) : 15.0;
                $seller_share = $item_price * ((100.0 - $comm_percent) / 100.0);
                
                $escrow_days = intval(get_setting('escrow_lock_days', 7));
                if ($escrow_days > 0) {
                    $db->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?")
                       ->execute([$seller_share, $seller_id]);
                    $status = 'pending_clearance';
                    $available_at = date('Y-m-d H:i:s', time() + ($escrow_days * 86400));
                } else {
                    $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
                       ->execute([$seller_share, $seller_id]);
                    $status = 'completed';
                    $available_at = date('Y-m-d H:i:s');
                }
                
                // Update product sales count
                $db->prepare("UPDATE products SET sales_count = sales_count + 1 WHERE id = ?")
                   ->execute([$pid]);
                
                // Create transaction for seller
                $db->prepare("INSERT INTO transactions (user_id, amount, type, status, paystack_ref, available_at) VALUES (?, ?, 'sale', ?, ?, ?)")
                   ->execute([$seller_id, $seller_share, $status, 'pstk_' . uniqid(), $available_at]);
                
                // Notification log for seller
                $db->prepare("INSERT INTO notifications (user_id, type, product_id, message) VALUES (?, 'sale', ?, ?)")
                   ->execute([$seller_id, $pid, "You made a sale! " . htmlspecialchars($buyer['name']) . " purchased " . htmlspecialchars($p_row['title'])]);
                
                // Check if this is the seller's first sale (referred seller first sale commission)
                $is_seller_first_sale = false;
                $vendor_stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
                $vendor_stmt->execute([$seller_id]);
                $vendor_row = $vendor_stmt->fetch();
                if ($vendor_row && !empty($vendor_row['referred_by'])) {
                    $prior_sales_stmt = $db->prepare("SELECT COUNT(*) FROM purchases p JOIN products prod ON p.product_id = prod.id WHERE prod.seller_id = ?");
                    $prior_sales_stmt->execute([$seller_id]);
                    if (intval($prior_sales_stmt->fetchColumn()) === 1) {
                        $is_seller_first_sale = true;
                    }
                }

                // Affiliate Referral Commission processing (dynamic settings percentage, first purchase association)
                if (get_setting('affiliate_system', '1') === '1') {
                    $aff_pct = floatval(get_setting('affiliate_percentage', '10'));
                    
                    // Case A: Referred Buyer's First Purchase
                    if (!empty($buyer['referred_by']) && $is_first_purchase) {
                        $referrer = intval($buyer['referred_by']);
                        $affiliate_commission = $item_price * ($aff_pct / 100.0);
                        if ($affiliate_commission > 0) {
                            if ($escrow_days > 0) {
                                $db->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?")
                                   ->execute([$affiliate_commission, $referrer]);
                                $aff_status = 'pending_clearance';
                                $aff_available_at = date('Y-m-d H:i:s', time() + ($escrow_days * 86400));
                            } else {
                                $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
                                   ->execute([$affiliate_commission, $referrer]);
                                $aff_status = 'completed';
                                $aff_available_at = date('Y-m-d H:i:s');
                            }
                            
                            // Log inside affiliate referrals
                            $check_ref = $db->prepare("SELECT id FROM affiliate_referrals WHERE referrer_id = ? AND referred_id = ? LIMIT 1");
                            $check_ref->execute([$referrer, $buyer['id']]);
                            $ref_row = $check_ref->fetch();
                            if ($ref_row) {
                                $db->prepare("UPDATE affiliate_referrals SET amount = amount + ? WHERE id = ?")
                                   ->execute([$affiliate_commission, $ref_row['id']]);
                            } else {
                                $db->prepare("INSERT INTO affiliate_referrals (referrer_id, referred_id, amount) VALUES (?, ?, ?)")
                                   ->execute([$referrer, $buyer['id'], $affiliate_commission]);
                            }
                            
                            $db->prepare("INSERT INTO transactions (user_id, amount, type, status, paystack_ref, available_at) VALUES (?, ?, 'affiliate', ?, ?, ?)")
                               ->execute([$referrer, $affiliate_commission, $aff_status, 'aff_buyer_' . uniqid(), $aff_available_at]);
                        }
                    }
                    
                    // Case B: Referred Seller's First Sale
                    if ($vendor_row && !empty($vendor_row['referred_by']) && $is_seller_first_sale) {
                        $referrer = intval($vendor_row['referred_by']);
                        $affiliate_commission = $item_price * ($aff_pct / 100.0);
                        if ($affiliate_commission > 0) {
                            if ($escrow_days > 0) {
                                $db->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?")
                                   ->execute([$affiliate_commission, $referrer]);
                                $aff_status = 'pending_clearance';
                                $aff_available_at = date('Y-m-d H:i:s', time() + ($escrow_days * 86400));
                            } else {
                                $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
                                   ->execute([$affiliate_commission, $referrer]);
                                $aff_status = 'completed';
                                $aff_available_at = date('Y-m-d H:i:s');
                            }
                            
                            // Log inside affiliate referrals
                            $check_ref = $db->prepare("SELECT id FROM affiliate_referrals WHERE referrer_id = ? AND referred_id = ? LIMIT 1");
                            $check_ref->execute([$referrer, $seller_id]);
                            $ref_row = $check_ref->fetch();
                            if ($ref_row) {
                                $db->prepare("UPDATE affiliate_referrals SET amount = amount + ? WHERE id = ?")
                                   ->execute([$affiliate_commission, $ref_row['id']]);
                            } else {
                                $db->prepare("INSERT INTO affiliate_referrals (referrer_id, referred_id, amount) VALUES (?, ?, ?)")
                                   ->execute([$referrer, $seller_id, $affiliate_commission]);
                            }
                            
                            $db->prepare("INSERT INTO transactions (user_id, amount, type, status, paystack_ref, available_at) VALUES (?, ?, 'affiliate', ?, ?, ?)")
                               ->execute([$referrer, $affiliate_commission, $aff_status, 'aff_seller_' . uniqid(), $aff_available_at]);
                        }
                    }
                }
            }
            $db->commit();
            
            // Construct and send email alerts
            try {
                $site_name = get_platform_name();
                $buyer_email = $buyer['email'];
                
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || ($_SERVER['SERVER_PORT'] == 443);
                $protocol = $is_https ? 'https' : 'http';
                $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
                $base_dir = dirname($script_name);
                $base_dir = str_replace('\\', '/', $base_dir);
                $base_path = ($base_dir === '/' || $base_dir === '\\') ? '' : $base_dir;
                $site_url = $protocol . '://' . $host . $base_path;
                
                $buyer_subject = "Your Purchase Receipt from " . $site_name;
                $buyer_body = "<h3>Thank you for your purchase!</h3>";
                $buyer_body .= "<p>Order Summary:</p><table border='1' cellpadding='8' style='border-collapse:collapse; border-color:#e2e8f0;'>";
                $buyer_body .= "<tr style='background:#f7fafc;'><th>Product</th><th>License</th><th>Price</th><th>Download</th></tr>";
                
                foreach($p_rows as $p_row) {
                    $pid = $p_row['id'];
                    $item_license_type = $_SESSION['cart_licenses'][$pid] ?? 'standard';
                    $base_price = ($item_license_type === 'extended' && !empty($p_row['extended_price'])) ? floatval($p_row['extended_price']) : floatval($p_row['discount_price'] ?: $p_row['price']);
                    $item_price = $base_price * $ratio;
                    $seller_id = $p_row['seller_id'];
                    
                    $lic_stmt = $db->prepare("SELECT license_key FROM purchases WHERE buyer_id = ? AND product_id = ? AND license_type = ? ORDER BY id DESC LIMIT 1");
                    $lic_stmt->execute([$buyer['id'], $pid, $item_license_type]);
                    $license_key = $lic_stmt->fetchColumn();
                    
                    $download_link = $site_url . '/index.php?page=dashboard&tab=purchases';
                    $buyer_body .= "<tr>";
                    $buyer_body .= "<td>" . htmlspecialchars($p_row['title']) . "</td>";
                    $buyer_body .= "<td>" . ucfirst($item_license_type) . "</td>";
                    $buyer_body .= "<td>$" . number_format($item_price, 2) . "</td>";
                    $buyer_body .= "<td><a href='" . $download_link . "'>Download Here</a>" . ($license_key ? "<br><span style='font-size:11px; color:#718096;'>License: " . htmlspecialchars($license_key) . "</span>" : "") . "</td>";
                    $buyer_body .= "</tr>";
                    
                    // Email Seller
                    $sel_stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                    $sel_stmt->execute([$seller_id]);
                    $seller_info = $sel_stmt->fetch();
                    if ($seller_info) {
                        $seller_subject = "💰 You made a sale on " . $site_name . "!";
                        $seller_body = "Hi " . htmlspecialchars($seller_info['name']) . ",<br><br>";
                        $seller_body .= "Great news! A buyer has purchased your product <strong>" . htmlspecialchars($p_row['title']) . "</strong> (" . ucfirst($item_license_type) . " License).<br>";
                        $seller_body .= "Your wallet has been credited with $" . number_format($item_price * ((100.0 - (defined('PLATFORM_COMMISSION') ? floatval(PLATFORM_COMMISSION) : 15.0)) / 100.0), 2) . ".<br><br>";
                        $seller_body .= "Log in to your dashboard to view your transactions.<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                        send_custom_email($seller_info['email'], $seller_subject, $seller_body);
                    }
                }
                $buyer_body .= "</table><br><p>Total Paid: $" . number_format($final_total, 2) . "</p>";
                $buyer_body .= "<p>Enjoy your scripts!</p>";
                send_custom_email($buyer_email, $buyer_subject, $buyer_body);
            } catch (Exception $email_ex) {
                error_log("Cart checkout email failure: " . $email_ex->getMessage());
            }
            
            // Clear cart & coupons
            $_SESSION['cart'] = [];
            unset($_SESSION['applied_coupon']);
            $_SESSION['flash_success'] = "Checkout processed successfully! Instant license unlocked.";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = "Transaction processing failed: " . $e->getMessage();
            header("Location: index.php?page=" . urlencode($page));
            exit;
        }
    }

    // Direct Single Checkout
    if ($action === 'direct_checkout') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "You must log in to buy this product.";
            header("Location: index.php?page=product&id=" . intval($_POST['product_id']));
            exit;
        }
        
        $pid = intval($_POST['product_id']);
        $license_type = isset($_POST['license_type']) ? trim($_POST['license_type']) : 'standard';
        $buyer = get_logged_in_user();
        $coupon = isset($_SESSION['applied_coupon']) ? $_SESSION['applied_coupon'] : null;
        
        // Check if the buyer was referred and this is their first purchase
        $is_first_purchase = false;
        if (get_setting('affiliate_system', '1') === '1' && !empty($buyer['referred_by'])) {
            $prior_purchases_stmt = $db->prepare("SELECT COUNT(*) FROM purchases WHERE buyer_id = ?");
            $prior_purchases_stmt->execute([$buyer['id']]);
            if (intval($prior_purchases_stmt->fetchColumn()) === 0) {
                $is_first_purchase = true;
            }
        }
        
        try {
            $db->beginTransaction();
            $p_stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $p_stmt->execute([$pid]);
            $p_row = $p_stmt->fetch();
            
            if ($p_row) {
                $price = floatval(($license_type === 'extended' && !empty($p_row['extended_price'])) ? $p_row['extended_price'] : ($p_row['discount_price'] ?: $p_row['price']));
                
                if ($coupon) {
                    if ($coupon['type'] === 'percentage') {
                        $discount = $price * ($coupon['value'] / 100);
                        $price = max(0.0, $price - $discount);
                    } else {
                        $discount = floatval($coupon['value']);
                        $price = max(0.0, $price - $discount);
                    }
                    $db->prepare("UPDATE coupon_codes SET uses_count = uses_count + 1 WHERE id = ?")->execute([$coupon['id']]);
                }
                
                $seller_id = $p_row['seller_id'];
                
                $license_key = null;
                if (!empty($p_row['licensing_enabled']) && intval($p_row['licensing_enabled']) === 1) {
                    $license_key = generate_product_license($p_row, $buyer['email'], $license_type);
                }

                $db->prepare("INSERT INTO purchases (buyer_id, product_id, amount, license_key, license_type) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$buyer['id'], $pid, $price, $license_key, $license_type]);
                
                $comm_percent = defined('PLATFORM_COMMISSION') ? floatval(PLATFORM_COMMISSION) : 15.0;
                $seller_share = $price * ((100.0 - $comm_percent) / 100.0);
                
                $escrow_days = intval(get_setting('escrow_lock_days', 7));
                if ($escrow_days > 0) {
                    $db->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?")
                       ->execute([$seller_share, $seller_id]);
                    $status = 'pending_clearance';
                    $available_at = date('Y-m-d H:i:s', time() + ($escrow_days * 86400));
                } else {
                    $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
                       ->execute([$seller_share, $seller_id]);
                    $status = 'completed';
                    $available_at = date('Y-m-d H:i:s');
                }
                
                $db->prepare("UPDATE products SET sales_count = sales_count + 1 WHERE id = ?")
                   ->execute([$pid]);
                
                $db->prepare("INSERT INTO transactions (user_id, amount, type, status, paystack_ref, available_at) VALUES (?, ?, 'sale', ?, ?, ?)")
                   ->execute([$seller_id, $seller_share, $status, 'pstk_' . uniqid(), $available_at]);
                
                $db->prepare("INSERT INTO notifications (user_id, type, product_id, message) VALUES (?, 'sale', ?, ?)")
                   ->execute([$seller_id, $pid, "Dynamic direct checkout! " . htmlspecialchars($buyer['name']) . " purchased " . htmlspecialchars($p_row['title'])]);
                
                // Check if this is the seller's first sale (referred seller first sale commission)
                $is_seller_first_sale = false;
                $vendor_stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
                $vendor_stmt->execute([$seller_id]);
                $vendor_row = $vendor_stmt->fetch();
                if ($vendor_row && !empty($vendor_row['referred_by'])) {
                    $prior_sales_stmt = $db->prepare("SELECT COUNT(*) FROM purchases p JOIN products prod ON p.product_id = prod.id WHERE prod.seller_id = ?");
                    $prior_sales_stmt->execute([$seller_id]);
                    if (intval($prior_sales_stmt->fetchColumn()) === 1) {
                        $is_seller_first_sale = true;
                    }
                }

                // Affiliate Referral Commission processing (dynamic settings percentage, first purchase association)
                if (get_setting('affiliate_system', '1') === '1') {
                    $aff_pct = floatval(get_setting('affiliate_percentage', '10'));
                    
                    // Case A: Referred Buyer's First Purchase
                    if (!empty($buyer['referred_by']) && $is_first_purchase) {
                        $referrer = intval($buyer['referred_by']);
                        $affiliate_commission = $price * ($aff_pct / 100.0);
                        if ($affiliate_commission > 0) {
                            if ($escrow_days > 0) {
                                $db->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?")
                                   ->execute([$affiliate_commission, $referrer]);
                                $aff_status = 'pending_clearance';
                                $aff_available_at = date('Y-m-d H:i:s', time() + ($escrow_days * 86400));
                            } else {
                                $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
                                   ->execute([$affiliate_commission, $referrer]);
                                $aff_status = 'completed';
                                $aff_available_at = date('Y-m-d H:i:s');
                            }
                            
                            // Log inside affiliate referrals
                            $check_ref = $db->prepare("SELECT id FROM affiliate_referrals WHERE referrer_id = ? AND referred_id = ? LIMIT 1");
                            $check_ref->execute([$referrer, $buyer['id']]);
                            $ref_row = $check_ref->fetch();
                            if ($ref_row) {
                                $db->prepare("UPDATE affiliate_referrals SET amount = amount + ? WHERE id = ?")
                                   ->execute([$affiliate_commission, $ref_row['id']]);
                            } else {
                                $db->prepare("INSERT INTO affiliate_referrals (referrer_id, referred_id, amount) VALUES (?, ?, ?)")
                                   ->execute([$referrer, $buyer['id'], $affiliate_commission]);
                            }
                            
                            $db->prepare("INSERT INTO transactions (user_id, amount, type, status, paystack_ref, available_at) VALUES (?, ?, 'affiliate', ?, ?, ?)")
                               ->execute([$referrer, $affiliate_commission, $aff_status, 'aff_buyer_' . uniqid(), $aff_available_at]);
                        }
                    }
                    
                    // Case B: Referred Seller's First Sale
                    if ($vendor_row && !empty($vendor_row['referred_by']) && $is_seller_first_sale) {
                        $referrer = intval($vendor_row['referred_by']);
                        $affiliate_commission = $price * ($aff_pct / 100.0);
                        if ($affiliate_commission > 0) {
                            if ($escrow_days > 0) {
                                $db->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?")
                                   ->execute([$affiliate_commission, $referrer]);
                                $aff_status = 'pending_clearance';
                                $aff_available_at = date('Y-m-d H:i:s', time() + ($escrow_days * 86400));
                            } else {
                                $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
                                   ->execute([$affiliate_commission, $referrer]);
                                $aff_status = 'completed';
                                $aff_available_at = date('Y-m-d H:i:s');
                            }
                            
                            // Log inside affiliate referrals
                            $check_ref = $db->prepare("SELECT id FROM affiliate_referrals WHERE referrer_id = ? AND referred_id = ? LIMIT 1");
                            $check_ref->execute([$referrer, $seller_id]);
                            $ref_row = $check_ref->fetch();
                            if ($ref_row) {
                                $db->prepare("UPDATE affiliate_referrals SET amount = amount + ? WHERE id = ?")
                                   ->execute([$affiliate_commission, $ref_row['id']]);
                            } else {
                                $db->prepare("INSERT INTO affiliate_referrals (referrer_id, referred_id, amount) VALUES (?, ?, ?)")
                                   ->execute([$referrer, $seller_id, $affiliate_commission]);
                            }
                            
                            $db->prepare("INSERT INTO transactions (user_id, amount, type, status, paystack_ref, available_at) VALUES (?, ?, 'affiliate', ?, ?, ?)")
                               ->execute([$referrer, $affiliate_commission, $aff_status, 'aff_seller_' . uniqid(), $aff_available_at]);
                        }
                    }
                }
            }
            $db->commit();
            
            // Construct and send email alerts
            try {
                $site_name = get_platform_name();
                $buyer_email = $buyer['email'];
                
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || ($_SERVER['SERVER_PORT'] == 443);
                $protocol = $is_https ? 'https' : 'http';
                $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
                $base_dir = dirname($script_name);
                $base_dir = str_replace('\\', '/', $base_dir);
                $base_path = ($base_dir === '/' || $base_dir === '\\') ? '' : $base_dir;
                $site_url = $protocol . '://' . $host . $base_path;
                
                $buyer_subject = "Your Purchase Receipt from " . $site_name;
                $buyer_body = "<h3>Thank you for your purchase!</h3>";
                $buyer_body .= "<p>Order Summary:</p><table border='1' cellpadding='8' style='border-collapse:collapse; border-color:#e2e8f0;'>";
                $buyer_body .= "<tr style='background:#f7fafc;'><th>Product</th><th>License</th><th>Price</th><th>Download</th></tr>";
                
                $download_link = $site_url . '/index.php?page=dashboard&tab=purchases';
                $buyer_body .= "<tr>";
                $buyer_body .= "<td>" . htmlspecialchars($p_row['title']) . "</td>";
                $buyer_body .= "<td>" . ucfirst($license_type) . "</td>";
                $buyer_body .= "<td>$" . number_format($price, 2) . "</td>";
                $buyer_body .= "<td><a href='" . $download_link . "'>Download Here</a>" . ($license_key ? "<br><span style='font-size:11px; color:#718096;'>License: " . htmlspecialchars($license_key) . "</span>" : "") . "</td>";
                $buyer_body .= "</tr>";
                
                // Email Seller
                $sel_stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                $sel_stmt->execute([$seller_id]);
                $seller_info = $sel_stmt->fetch();
                if ($seller_info) {
                    $seller_subject = "💰 You made a sale on " . $site_name . "!";
                    $seller_body = "Hi " . htmlspecialchars($seller_info['name']) . ",<br><br>";
                    $seller_body .= "Great news! A buyer has purchased your product <strong>" . htmlspecialchars($p_row['title']) . "</strong> (" . ucfirst($license_type) . " License).<br>";
                    $seller_body .= "Your wallet has been credited with $" . number_format($seller_share, 2) . ".<br><br>";
                    $seller_body .= "Log in to your dashboard to view your transactions.<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                    send_custom_email($seller_info['email'], $seller_subject, $seller_body);
                }
                
                $buyer_body .= "</table><br><p>Total Paid: $" . number_format($price, 2) . "</p>";
                $buyer_body .= "<p>Enjoy your scripts!</p>";
                send_custom_email($buyer_email, $buyer_subject, $buyer_body);
            } catch (Exception $email_ex) {
                error_log("Direct checkout email failure: " . $email_ex->getMessage());
            }
            unset($_SESSION['applied_coupon']);
            $_SESSION['flash_success'] = "Direct payment successful! Instant file access unlocked.";
            header("Location: index.php?page=dashboard&tab=purchases");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = "Direct payment error: " . $e->getMessage();
            header("Location: index.php?page=product&id=" . $pid);
            exit;
        }
    }

    // Product Code manager: Add / Edit
    if ($action === 'product_save') {
        if (get_setting('demo_mode', '0') === '1' && !is_admin()) {
            $_SESSION['flash_error'] = "The platform is currently in read-only Demo Mode. Product listings cannot be modified by sellers.";
            header("Location: index.php?page=dashboard&tab=products");
            exit;
        }
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $price = floatval($_POST['price']);
        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $thumbnail = isset($_POST['thumbnail']) ? trim($_POST['thumbnail']) : '';
        $download_url = isset($_POST['download_url']) ? trim($_POST['download_url']) : '';
        $live_demo_url = isset($_POST['live_demo_url']) ? trim($_POST['live_demo_url']) : '';
        $screenshots = isset($_POST['screenshots']) ? $_POST['screenshots'] : []; // array of URLs
        $is_featured = isset($_POST['is_featured']) ? intval($_POST['is_featured']) : 0;
        $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
        $version = isset($_POST['version']) ? trim($_POST['version']) : '1.0.0';
        $discount_price = (!empty($_POST['discount_price']) && floatval($_POST['discount_price']) > 0) ? floatval($_POST['discount_price']) : null;
        $sale_ends_at = !empty($_POST['sale_ends_at']) ? $_POST['sale_ends_at'] : null;

        $licensing_enabled = isset($_POST['licensing_enabled']) ? intval($_POST['licensing_enabled']) : 0;
        $license_manager_url = isset($_POST['license_manager_url']) ? trim($_POST['license_manager_url']) : '';
        $license_manager_secret = isset($_POST['license_manager_secret']) ? trim($_POST['license_manager_secret']) : '';
        $extended_price = (!empty($_POST['extended_price']) && floatval($_POST['extended_price']) > 0) ? floatval($_POST['extended_price']) : null;
        
        $preview_images_json = json_encode(array_filter(array_map('trim', $screenshots)));
        
        if (empty($title) || empty($description) || $price <= 0 || empty($download_url)) {
            $_SESSION['flash_error'] = "All primary product inputs are required.";
        } else {
            if ($id > 0) {
                // Fetch old state for notification triggers before editing
                $old_stmt = $db->prepare("SELECT price, title, is_featured, status FROM products WHERE id = ?");
                $old_stmt->execute([$id]);
                $old_product = $old_stmt->fetch();

                $prev_price = $old_product ? floatval($old_product['price']) : $price;
                $new_price = floatval($price);
                $prev_featured = $old_product ? intval($old_product['is_featured']) : 0;
                $next_featured = intval($is_featured);
                
                // If user editing is seller, status becomes 'pending', if admin, keeps status
                $status = is_admin() ? (isset($_POST['status']) ? trim($_POST['status']) : $old_product['status']) : 'pending';

                // Edit
                $isAdminVal = is_admin() ? 1 : 0;
                $up_stmt = $db->prepare("UPDATE products SET title = ?, description = ?, price = ?, category = ?, thumbnail = ?, download_url = ?, preview_images = ?, live_demo_url = ?, is_featured = ?, tags = ?, version = ?, status = ?, discount_price = ?, sale_ends_at = ?, licensing_enabled = ?, license_manager_url = ?, license_manager_secret = ?, extended_price = ? WHERE id = ? AND (seller_id = ? OR ? = 1)");
                $up_stmt->execute([$title, $description, $price, $category, $thumbnail, $download_url, $preview_images_json, $live_demo_url, $is_featured, $tags, $version, $status, $discount_price, $sale_ends_at, $licensing_enabled, $license_manager_url, $license_manager_secret, $extended_price, $id, $_SESSION['user_id'], $isAdminVal]);
                
                if ($status === 'approved') {
                    send_product_email_ads($db, $id);
                }
                
                // Track dynamic triggers for wishlisters
                try {
                    $wish_stmt = $db->prepare("SELECT user_id FROM wishlist WHERE product_id = ?");
                    $wish_stmt->execute([$id]);
                    $wishlisters = $wish_stmt->fetchAll();

                    foreach ($wishlisters as $w) {
                        if ($new_price < $prev_price) {
                            $message = "🔥 Price Drop: \"" . $title . "\" has dropped to $" . number_format($new_price, 2) . " (was $" . number_format($prev_price, 2) . ")! Check it out!";
                            $ins_not = $db->prepare("INSERT INTO notifications (user_id, type, product_id, message) VALUES (?, 'price_drop', ?, ?)");
                            $ins_not->execute([$w['user_id'], $id, $message]);
                        } elseif ($next_featured > $prev_featured) {
                            $message = "🌟 Featured Product: Great news! \"" . $title . "\" on your wishlist has been Selected as a Featured Script!";
                            $ins_not = $db->prepare("INSERT INTO notifications (user_id, type, product_id, message) VALUES (?, 'product_featured', ?, ?)");
                            $ins_not->execute([$w['user_id'], $id, $message]);
                        } else {
                            $message = "✨ Update: The product \"" . $title . "\" on your wishlist has received an update.";
                            $ins_not = $db->prepare("INSERT INTO notifications (user_id, type, product_id, message) VALUES (?, 'product_update', ?, ?)");
                            $ins_not->execute([$w['user_id'], $id, $message]);
                        }
                    }
                } catch (Exception $not_err) {
                    // Ignore notification failures gracefully
                }

                $_SESSION['flash_success'] = is_admin() ? "Product updated successfully!" : "Product updated and submitted for review.";
            } else {
                // Add
                $status = is_admin() ? 'approved' : 'pending';
                $ins_stmt = $db->prepare("INSERT INTO products (seller_id, title, description, price, category, thumbnail, download_url, preview_images, live_demo_url, is_featured, tags, version, status, discount_price, sale_ends_at, licensing_enabled, license_manager_url, license_manager_secret, extended_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins_stmt->execute([$_SESSION['user_id'], $title, $description, $price, $category, $thumbnail, $download_url, $preview_images_json, $live_demo_url, $is_featured, $tags, $version, $status, $discount_price, $sale_ends_at, $licensing_enabled, $license_manager_url, $license_manager_secret, $extended_price]);
                
                if ($status === 'approved') {
                    send_product_email_ads($db, $db->lastInsertId());
                }
                
                // Email confirmation to seller and alert to admin
                $seller = get_logged_in_user();
                if ($seller) {
                    $site_name = get_platform_name();
                    if ($status === 'pending') {
                        $seller_subject = "Product Submitted for Review: " . $title;
                        $seller_body = "Hi " . htmlspecialchars($seller['name']) . ",<br><br>Your product <strong>" . htmlspecialchars($title) . "</strong> has been submitted and is currently pending review. We will notify you once it has been processed.<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                        send_custom_email($seller['email'], $seller_subject, $seller_body);

                        $admin_email = get_setting('smtp_from_email', 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                        $admin_subject = "[Review Required] New Product Uploaded: " . $title;
                        $admin_body = "A new product has been uploaded by " . htmlspecialchars($seller['name']) . " and is waiting in the review queue.<br><br>Product Title: " . htmlspecialchars($title) . "<br><br>Log in to the admin dashboard to review.";
                        send_custom_email($admin_email, $admin_subject, $admin_body);
                    } else if ($status === 'approved') {
                        $seller_subject = "Product Published: " . $title;
                        $seller_body = "Hi " . htmlspecialchars($seller['name']) . ",<br><br>Your product <strong>" . htmlspecialchars($title) . "</strong> is now live on the marketplace!<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                        send_custom_email($seller['email'], $seller_subject, $seller_body);
                    }
                }
                
                $_SESSION['flash_success'] = is_admin() ? "Marketplace product published successfully!" : "Product submitted for administrative review.";
            }
        }
        header("Location: index.php?page=dashboard&tab=products");
        exit;
    }

    // Product Pruning
    if ($action === 'product_delete') {
        if (get_setting('demo_mode', '0') === '1' && !is_admin()) {
            $_SESSION['flash_error'] = "The platform is currently in read-only Demo Mode. Product listings cannot be deleted by sellers.";
            header("Location: index.php?page=dashboard&tab=products");
            exit;
        }
        $id = intval($_POST['id']);
        if ($id > 0) {
            if (is_admin()) {
                $del_stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                $del_stmt->execute([$id]);
                $_SESSION['flash_success'] = "Product pruned from catalog administratively.";
                header("Location: index.php?page=dashboard&tab=admin_products");
            } else {
                $del_stmt = $db->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
                $del_stmt->execute([$id, $_SESSION['user_id']]);
                $_SESSION['flash_success'] = "Product deleted successfully.";
                header("Location: index.php?page=dashboard&tab=products");
            }
            exit;
        }
    }

    // Reviews Add
    if ($action === 'review_add') {
        $pid = intval($_POST['product_id']);
        $rating = intval($_POST['rating']);
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        
        if ($pid > 0 && $rating > 0) {
            // Write review
            $ins_stmt = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
            $ins_stmt->execute([$pid, $_SESSION['user_id'], $rating, $comment]);
            
            // Recalculate average rating of product
            $avg_stmt = $db->prepare("SELECT AVG(rating) FROM reviews WHERE product_id = ?");
            $avg_stmt->execute([$pid]);
            $avg = $avg_stmt->fetchColumn();
            
            $db->prepare("UPDATE products SET rating = ? WHERE id = ?")->execute([$avg, $pid]);
            $_SESSION['flash_success'] = "Review published. Rating score is recorded.";
        }
        header("Location: index.php?page=product&id=" . $pid);
        exit;
    }

    // Support Messaging Sending
    if ($action === 'message_send') {
        $receiver_id = intval($_POST['receiver_id']);
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        
        if ($receiver_id > 0 && !empty($content)) {
            $db->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)")
               ->execute([$_SESSION['user_id'], $receiver_id, $content]);
        }
        header("Location: index.php?page=dashboard&tab=support");
        exit;
    }

    // Forums adding new discussion thread
    if ($action === 'forum_thread_add') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $body = isset($_POST['body']) ? trim($_POST['body']) : '';
        
        if (!empty($title) && !empty($body)) {
            $db->prepare("INSERT INTO forum_threads (title, category, author_id, author_name, body) VALUES (?, ?, ?, ?, ?)")
               ->execute([$title, $category, $_SESSION['user_id'], $_SESSION['user_name'], $body]);
            $_SESSION['flash_success'] = "Forum thread created successfully!";
        }
        header("Location: index.php?page=forums");
        exit;
    }

    // Forums comment replying
    if ($action === 'forum_reply_add') {
        $tid = intval($_POST['thread_id']);
        $body = isset($_POST['body']) ? trim($_POST['body']) : '';
        
        if ($tid > 0 && !empty($body)) {
            $db->prepare("INSERT INTO forum_posts (thread_id, author_name, body) VALUES (?, ?, ?)")
               ->execute([$tid, $_SESSION['user_name'], $body]);
            $_SESSION['flash_success'] = "Forum comment added!";
        }
        header("Location: index.php?page=forums&thread_id=" . $tid);
        exit;
    }

    // Verification request uploading
    if ($action === 'verify_upload') {
        $url = isset($_POST['document_url']) ? trim($_POST['document_url']) : '';
        if (!empty($url)) {
            $db->prepare("INSERT INTO verification_requests (seller_id, document_url) VALUES (?, ?)")
               ->execute([$_SESSION['user_id'], $url]);
            $_SESSION['flash_success'] = "Identification files uploaded for administrator audits.";
        }
        header("Location: index.php?page=dashboard&tab=verification");
        exit;
    }

    // Seller Settlement Withdrawals Requesting
    if ($action === 'withdrawal_request') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "You must log in to request withdrawals.";
            header("Location: index.php?page=dashboard");
            exit;
        }
        $userId = $_SESSION['user_id'];
        $w_stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
        $w_stmt->execute([$userId]);
        $wallet = $w_stmt->fetch();
        if (!$wallet) {
            $_SESSION['flash_error'] = "Wallet not found.";
            header("Location: index.php?page=dashboard");
            exit;
        }

        $amount = floatval($_POST['amount']);
        $bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '';
        $account_num = isset($_POST['account_number']) ? trim($_POST['account_number']) : '';
        $account_name = isset($_POST['account_name']) ? trim($_POST['account_name']) : '';
        
        if ($amount <= 0 || empty($bank_name) || empty($account_num)) {
            $_SESSION['flash_error'] = "Please fill in valid payout parameters.";
        } else if ($amount > floatval($wallet['balance'])) {
            $_SESSION['flash_error'] = "Insufficient wallet balance to cashout requested amount.";
        } else {
            $charge_percent = floatval(get_setting('withdrawal_charge', 5));
            $charge = $amount * ($charge_percent / 100.0);
            $net = $amount - $charge;
            if ($net <= 0) {
                $_SESSION['flash_error'] = "Withdrawal amount must exceed processing charges standard thresholds.";
            } else {
                $db->beginTransaction();
                try {
                    // Lock amount in wallet (deduct from user balance immediately)
                    $db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?")->execute([$amount, $userId]);
                    
                    // Create pending logs
                    $db->prepare("INSERT INTO withdrawals (user_id, amount, bank_name, account_number, account_name, charge_amount, net_amount) VALUES (?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$userId, $amount, $bank_name, $account_num, $account_name, $charge, $net]);
                    
                    // Add transaction Log
                    $db->prepare("INSERT INTO transactions (user_id, amount, type, status) VALUES (?, ?, 'withdrawal', 'pending')")
                       ->execute([$userId, $amount]);
                    
                    $db->commit();
                    
                    // Email Notification to Seller & Alert to Admin
                    try {
                        $site_name = get_platform_name();
                        $seller = get_logged_in_user();
                        if ($seller) {
                            $seller_subject = "Withdrawal Request Received - " . $site_name;
                            $seller_body = "Hi " . htmlspecialchars($seller['name']) . ",<br><br>Your withdrawal request of $" . number_format($amount, 2) . " has been received and is currently pending review. A processing fee of $" . number_format($charge, 2) . " applies, resulting in a net payout of $" . number_format($net, 2) . ".<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                            send_custom_email($seller['email'], $seller_subject, $seller_body);

                            $admin_email = get_setting('smtp_from_email', 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                            $admin_subject = "[Payout Required] New Withdrawal Request: $" . number_format($amount, 2);
                            $admin_body = "A new withdrawal request of $" . number_format($amount, 2) . " has been submitted by " . htmlspecialchars($seller['name']) . ".<br><br>Bank: " . htmlspecialchars($bank_name) . "<br>Account number: " . htmlspecialchars($account_num) . "<br>Account name: " . htmlspecialchars($account_name) . "<br><br>Log in to the Admin Dashboard to review/approve.";
                            send_custom_email($admin_email, $admin_subject, $admin_body);
                        }
                    } catch (Exception $email_ex) {
                        error_log("Withdrawal email notification error: " . $email_ex->getMessage());
                    }
                    
                    $_SESSION['flash_success'] = "Settlement requested! Funds deducted dynamically into escrow checks.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['flash_error'] = "Payout request fail: " . $e->getMessage();
                }
            }
        }
        header("Location: index.php?page=dashboard&tab=withdrawals");
        exit;
    }

    // Admin handling withdrawal settlement payouts
    if ($action === 'admin_withdrawal_handle') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized access.";
            header("Location: index.php");
            exit;
        }
        
        $id = intval($_POST['id']);
        $status = isset($_POST['status']) ? trim($_POST['status']) : ''; // 'approved', 'rejected'
        
        if ($id > 0 && ($status === 'approved' || $status === 'rejected')) {
            $db->beginTransaction();
            try {
                // Fetch withdrawal record to check user and amount
                $wd_stmt = $db->prepare("SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'");
                $wd_stmt->execute([$id]);
                $wd_row = $wd_stmt->fetch();
                
                if ($wd_row) {
                    $wd_user = $wd_row['user_id'];
                    $amount = floatval($wd_row['amount']);
                    
                    if ($status === 'rejected') {
                        // Refund wallet
                        $db->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")->execute([$amount, $wd_user]);
                    }
                    
                    // Update main record stats
                    $db->prepare("UPDATE withdrawals SET status = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?")
                       ->execute([$status, $id]);
                    
                    $_SESSION['flash_success'] = "Payout request was " . htmlspecialchars($status) . ".";
                    
                    // Email Notification to Seller
                    try {
                        $seller_stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                        $seller_stmt->execute([$wd_user]);
                        $seller = $seller_stmt->fetch();
                        if ($seller) {
                            $site_name = get_platform_name();
                            $seller_subject = "Withdrawal Request " . ucfirst($status) . " - " . $site_name;
                            if ($status === 'approved') {
                                $seller_body = "Hi " . htmlspecialchars($seller['name']) . ",<br><br>Great news! Your withdrawal request of $" . number_format($amount, 2) . " has been approved and processed.<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                            } else {
                                $seller_body = "Hi " . htmlspecialchars($seller['name']) . ",<br><br>We regret to inform you that your withdrawal request of $" . number_format($amount, 2) . " was rejected. The funds have been refunded to your wallet balance.<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                            }
                            send_custom_email($seller['email'], $seller_subject, $seller_body);
                        }
                    } catch (Exception $email_ex) {
                        error_log("Withdrawal payout email notification error: " . $email_ex->getMessage());
                    }
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_error'] = "System audit error: " . $e->getMessage();
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_withdrawals");
        exit;
    }

    // Admin handling Seller Identification Verification Applications
    if ($action === 'admin_verify_handle') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized access.";
            header("Location: index.php");
            exit;
        }

        $id = intval($_POST['id']);
        $status = isset($_POST['status']) ? trim($_POST['status']) : ''; // 'approved', 'rejected'

        if ($id > 0 && ($status === 'approved' || $status === 'rejected')) {
            $db->beginTransaction();
            try {
                $vr_stmt = $db->prepare("SELECT * FROM verification_requests WHERE id = ?");
                $vr_stmt->execute([$id]);
                $vr_row = $vr_stmt->fetch();

                if ($vr_row) {
                    $seller_id = $vr_row['seller_id'];
                    
                    // Update User verification columns
                    $v_bit = ($status === 'approved') ? 1 : 0;
                    $db->prepare("UPDATE users SET is_verified = ? WHERE id = ?")->execute([$v_bit, $seller_id]);
                    
                    // Update Request record status
                    $db->prepare("UPDATE verification_requests SET status = ? WHERE id = ?")->execute([$status, $id]);
                }
                $db->commit();
                $_SESSION['flash_success'] = "Seller verification request resolved: " . strtoupper($status);
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_error'] = "Failed to evaluate identification document: " . $e->getMessage();
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_verifications");
        exit;
    }

    // Admin updating global system settings configuration parameters
    if ($action === 'admin_settings_update') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }

        $pub_key = isset($_POST['paystack_public_key']) ? trim($_POST['paystack_public_key']) : '';
        $sec_key = isset($_POST['paystack_secret_key']) ? trim($_POST['paystack_secret_key']) : '';
        $charge = isset($_POST['withdrawal_charge']) ? trim($_POST['withdrawal_charge']) : '';
        $currency = isset($_POST['currency']) ? trim($_POST['currency']) : '';
        $demo_mode = isset($_POST['demo_mode']) ? trim($_POST['demo_mode']) : '0';
        $clean_urls = isset($_POST['clean_urls']) ? trim($_POST['clean_urls']) : '1';
        $aff_sys = isset($_POST['affiliate_system']) ? trim($_POST['affiliate_system']) : '1';
        $aff_pct = isset($_POST['affiliate_percentage']) ? trim($_POST['affiliate_percentage']) : '10';
        $escrow_days = isset($_POST['escrow_lock_days']) ? intval($_POST['escrow_lock_days']) : 7;
        $global_lm_url = isset($_POST['global_lm_url']) ? trim($_POST['global_lm_url']) : '';
        $global_lm_secret = isset($_POST['global_lm_secret']) ? trim($_POST['global_lm_secret']) : '';

        set_setting('paystack_public_key', $pub_key);
        set_setting('paystack_secret_key', $sec_key);
        set_setting('withdrawal_charge', $charge);
        set_setting('currency', $currency);
        set_setting('demo_mode', $demo_mode);
        set_setting('clean_urls', $clean_urls);
        set_setting('affiliate_system', $aff_sys);
        set_setting('affiliate_percentage', $aff_pct);
        set_setting('escrow_lock_days', $escrow_days);
        set_setting('global_lm_url', $global_lm_url);
        set_setting('global_lm_secret', $global_lm_secret);


        $smtp_enabled = isset($_POST['smtp_enabled']) ? trim($_POST['smtp_enabled']) : '0';
        $smtp_host = isset($_POST['smtp_host']) ? trim($_POST['smtp_host']) : '';
        $smtp_port = isset($_POST['smtp_port']) ? trim($_POST['smtp_port']) : '25';
        $smtp_secure = isset($_POST['smtp_secure']) ? trim($_POST['smtp_secure']) : 'none';
        $smtp_user = isset($_POST['smtp_user']) ? trim($_POST['smtp_user']) : '';
        $smtp_pass = isset($_POST['smtp_pass']) ? trim($_POST['smtp_pass']) : '';
        $smtp_from_email = isset($_POST['smtp_from_email']) ? trim($_POST['smtp_from_email']) : '';
        $smtp_from_name = isset($_POST['smtp_from_name']) ? trim($_POST['smtp_from_name']) : '';

        set_setting('smtp_enabled', $smtp_enabled);
        set_setting('smtp_host', $smtp_host);
        set_setting('smtp_port', $smtp_port);
        set_setting('smtp_secure', $smtp_secure);
        set_setting('smtp_user', $smtp_user);
        set_setting('smtp_pass', $smtp_pass);
        set_setting('smtp_from_email', $smtp_from_email);
        set_setting('smtp_from_name', $smtp_from_name);

        $_SESSION['flash_success'] = "Global system configuration overrides updated successfully!";
        header("Location: index.php?page=dashboard&tab=admin_settings");
        exit;
    }

    // User Support Ticket creation
    if ($action === 'ticket_create') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "You must log in to create a ticket.";
            header("Location: index.php?page=dashboard");
            exit;
        }
        
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'normal';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        
        if (empty($subject) || empty($category) || empty($message)) {
            $_SESSION['flash_error'] = "Please fill in all ticket parameters.";
            header("Location: index.php?page=dashboard&tab=support_tickets");
            exit;
        }
        
        $user = get_logged_in_user();
        
        $ins = $db->prepare("INSERT INTO support_tickets (user_id, subject, category, priority, status) VALUES (?, ?, ?, ?, 'open')");
        $ins->execute([$user['id'], $subject, $category, $priority]);
        $ticket_id = $db->lastInsertId();
        
        $ins_msg = $db->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_name, content) VALUES (?, ?, ?, ?)");
        $ins_msg->execute([$ticket_id, $user['id'], $user['name'], $message]);
        
        // Email alert to Admin
        try {
            $admin_email = get_setting('smtp_from_email', 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $admin_subject = "[Support Alert] New Ticket: " . $subject;
            $admin_body = "A new support ticket has been created by " . htmlspecialchars($user['name']) . ".<br><br>Subject: " . htmlspecialchars($subject) . "<br>Category: " . htmlspecialchars($category) . "<br>Priority: " . htmlspecialchars($priority) . "<br><br>Message:<br>" . nl2br(htmlspecialchars($message));
            send_custom_email($admin_email, $admin_subject, $admin_body);
        } catch (Exception $email_ex) {
            error_log("Ticket create email alert error: " . $email_ex->getMessage());
        }
        
        $_SESSION['flash_success'] = "Support ticket created successfully!";
        header("Location: index.php?page=dashboard&tab=support_tickets&ticket_id=" . $ticket_id);
        exit;
    }

    // Add message/reply to Support Ticket
    if ($action === 'ticket_message_add') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "You must log in to reply.";
            header("Location: index.php?page=dashboard");
            exit;
        }
        
        $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        
        if ($ticket_id <= 0 || empty($message)) {
            $_SESSION['flash_error'] = "Message content is required.";
            header("Location: index.php?page=dashboard&tab=support_tickets");
            exit;
        }
        
        $user = get_logged_in_user();
        
        // Fetch ticket to verify ownership (if not admin)
        $t_stmt = $db->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $t_stmt->execute([$ticket_id]);
        $ticket = $t_stmt->fetch();
        
        if (!$ticket || (!is_admin() && intval($ticket['user_id']) !== intval($user['id']))) {
            $_SESSION['flash_error'] = "Unauthorized or ticket not found.";
            header("Location: index.php?page=dashboard&tab=support_tickets");
            exit;
        }
        
        // Insert message
        $ins_msg = $db->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_name, content) VALUES (?, ?, ?, ?)");
        $ins_msg->execute([$ticket_id, $user['id'], $user['name'], $message]);
        
        // Reopen/Update ticket status
        $next_status = is_admin() ? 'answered' : 'open';
        $up_status = $db->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        $up_status->execute([$next_status, $ticket_id]);
        
        $site_name = get_platform_name();
        
        try {
            if (is_admin()) {
                // Reply by admin -> Notify User
                $owner_stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                $owner_stmt->execute([$ticket['user_id']]);
                $owner = $owner_stmt->fetch();
                if ($owner) {
                    $user_subject = "New reply on your ticket: " . $ticket['subject'] . " - " . $site_name;
                    $user_body = "Hi " . htmlspecialchars($owner['name']) . ",<br><br>Our support staff has replied to your ticket \"<strong>" . htmlspecialchars($ticket['subject']) . "</strong>\":<br><br>" . nl2br(htmlspecialchars($message)) . "<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                    send_custom_email($owner['email'], $user_subject, $user_body);
                }
                $_SESSION['flash_success'] = "Reply sent and client notified.";
                header("Location: index.php?page=dashboard&tab=admin_tickets&ticket_id=" . $ticket_id);
                exit;
            } else {
                // Reply by client -> Notify Admin
                $admin_email = get_setting('smtp_from_email', 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $admin_subject = "[Ticket Reply] Client updated ticket: " . $ticket['subject'];
                $admin_body = "Client " . htmlspecialchars($user['name']) . " left a new reply on support ticket: \"<strong>" . htmlspecialchars($ticket['subject']) . "</strong>\":<br><br>" . nl2br(htmlspecialchars($message));
                send_custom_email($admin_email, $admin_subject, $admin_body);
                $_SESSION['flash_success'] = "Reply sent successfully.";
                header("Location: index.php?page=dashboard&tab=support_tickets&ticket_id=" . $ticket_id);
                exit;
            }
        } catch (Exception $email_ex) {
            error_log("Ticket reply email alert error: " . $email_ex->getMessage());
            $_SESSION['flash_success'] = "Reply sent successfully.";
            if (is_admin()) {
                header("Location: index.php?page=dashboard&tab=admin_tickets&ticket_id=" . $ticket_id);
            } else {
                header("Location: index.php?page=dashboard&tab=support_tickets&ticket_id=" . $ticket_id);
            }
            exit;
        }
    }

    // Admin change ticket parameters (status, priority)
    if ($action === 'admin_ticket_status') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        
        $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $priority = isset($_POST['priority']) ? trim($_POST['priority']) : '';
        
        if ($ticket_id > 0) {
            if (!empty($status)) {
                $db->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$status, $ticket_id]);
                
                // Notify User of status change
                try {
                    $t_stmt = $db->prepare("SELECT * FROM support_tickets WHERE id = ?");
                    $t_stmt->execute([$ticket_id]);
                    $ticket = $t_stmt->fetch();
                    if ($ticket) {
                        $owner_stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                        $owner_stmt->execute([$ticket['user_id']]);
                        $owner = $owner_stmt->fetch();
                        if ($owner) {
                            $site_name = get_platform_name();
                            $user_subject = "Ticket Status Updated: " . $ticket['subject'];
                            $user_body = "Hi " . htmlspecialchars($owner['name']) . ",<br><br>The status of your ticket \"<strong>" . htmlspecialchars($ticket['subject']) . "</strong>\" has been marked as <strong>" . htmlspecialchars($status) . "</strong>.<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                            send_custom_email($owner['email'], $user_subject, $user_body);
                        }
                    }
                } catch (Exception $email_ex) {
                    error_log("Ticket status email error: " . $email_ex->getMessage());
                }
            }
            if (!empty($priority)) {
                $db->prepare("UPDATE support_tickets SET priority = ? WHERE id = ?")->execute([$priority, $ticket_id]);
            }
            $_SESSION['flash_success'] = "Ticket parameters updated.";
        }
        
        header("Location: index.php?page=dashboard&tab=admin_tickets" . ($ticket_id > 0 ? "&ticket_id=" . $ticket_id : ""));
        exit;
    }

    // Admin updating global SEO & Analytics parameters
    if ($action === 'admin_seo_update') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }

        $settings_to_update = [
            'seo_site_title',
            'seo_meta_keywords',
            'seo_meta_description',
            'seo_og_image',
            'analytics_ga4_id',
            'analytics_gtm_id',
            'analytics_facebook_pixel_id',
            'seo_header_injection',
            'seo_footer_injection',
            'schema_business_type',
            'schema_phone',
            'schema_address'
        ];

        foreach ($settings_to_update as $key) {
            $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
            set_setting($key, $val);
        }

        $_SESSION['flash_success'] = "SEO and Analytics configuration parameters successfully saved!";
        header("Location: index.php?page=dashboard&tab=admin_seo");
        exit;
    }

    // Admin updating global Ads & Monetization parameters
    if ($action === 'admin_ads_update') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }

        $ad_top_enabled = isset($_POST['ad_top_enabled']) ? trim($_POST['ad_top_enabled']) : '0';
        $ad_top_code = isset($_POST['ad_top_code']) ? trim($_POST['ad_top_code']) : '';
        $ad_sidebar_enabled = isset($_POST['ad_sidebar_enabled']) ? trim($_POST['ad_sidebar_enabled']) : '0';
        $ad_sidebar_code = isset($_POST['ad_sidebar_code']) ? trim($_POST['ad_sidebar_code']) : '';
        $ads_txt = isset($_POST['ads_txt_content']) ? $_POST['ads_txt_content'] : '';

        set_setting('ad_top_enabled', $ad_top_enabled);
        set_setting('ad_top_code', $ad_top_code);
        set_setting('ad_sidebar_enabled', $ad_sidebar_enabled);
        set_setting('ad_sidebar_code', $ad_sidebar_code);
        set_setting('ads_txt_content', $ads_txt);

        // physically write ads.txt to root directory
        try {
            file_put_contents(__DIR__ . '/ads.txt', $ads_txt);
        } catch (Exception $e) {
            // Ignore write failures due to directory permissions
        }

        $_SESSION['flash_success'] = "Monetization and Ads configuration successfully saved!";
        header("Location: index.php?page=dashboard&tab=admin_ads");
        exit;
    }

    // Admin adding or updating categories
    if ($action === 'admin_category_save') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }

        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $icon = isset($_POST['icon']) ? trim($_POST['icon']) : '';
        $cat_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (empty($name)) {
            $_SESSION['flash_error'] = "Category name cannot be empty.";
        } else {
            try {
                if ($cat_id > 0) {
                    $stmt = $db->prepare("UPDATE categories SET name = ?, icon = ? WHERE id = ?");
                    $stmt->execute([$name, $icon, $cat_id]);
                    $_SESSION['flash_success'] = "Category updated successfully!";
                } else {
                    $stmt = $db->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)");
                    $stmt->execute([$name, $icon]);
                    $_SESSION['flash_success'] = "Category created successfully!";
                }
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to save category: " . $e->getMessage();
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_categories");
        exit;
    }

    // Admin removing categories
    if ($action === 'admin_category_delete') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }

        $id = intval($_POST['id']);
        if ($id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_success'] = "Category deleted successfully!";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to delete category: " . $e->getMessage();
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_categories");
        exit;
    }

    // Admin Coupon Save
    if ($action === 'coupon_save') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $code = strtoupper(isset($_POST['code']) ? trim($_POST['code']) : '');
        $type = isset($_POST['type']) ? trim($_POST['type']) : ''; // 'percentage', 'fixed'
        $value = floatval($_POST['value']);
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $max_uses = !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null;
        $coupon_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (empty($code) || $value <= 0) {
            $_SESSION['flash_error'] = "Invalid coupon code or value.";
        } else {
            try {
                if ($coupon_id > 0) {
                    $stmt = $db->prepare("UPDATE coupon_codes SET code = ?, type = ?, value = ?, expiry_date = ?, max_uses = ? WHERE id = ?");
                    $stmt->execute([$code, $type, $value, $expiry_date, $max_uses, $coupon_id]);
                    $_SESSION['flash_success'] = "Coupon updated successfully!";
                } else {
                    $stmt = $db->prepare("INSERT INTO coupon_codes (code, type, value, expiry_date, max_uses) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$code, $type, $value, $expiry_date, $max_uses]);
                    $_SESSION['flash_success'] = "Coupon created successfully!";
                }
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to save coupon: " . $e->getMessage();
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_coupons");
        exit;
    }

    // Admin Coupon Delete
    if ($action === 'coupon_delete') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $id = intval($_POST['id']);
        if ($id > 0) {
            $db->prepare("DELETE FROM coupon_codes WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = "Coupon deleted successfully!";
        }
        header("Location: index.php?page=dashboard&tab=admin_coupons");
        exit;
    }

    // Buyer Apply Coupon Code
    if ($action === 'coupon_apply') {
        $code = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';
        $redirect_page = isset($_POST['redirect_page']) ? trim($_POST['redirect_page']) : $page;
        $redirect_id = isset($_POST['redirect_id']) ? intval($_POST['redirect_id']) : 0;
        
        $location = "index.php?page=" . urlencode($redirect_page);
        if ($redirect_id > 0) {
            $location .= "&id=" . $redirect_id;
        }

        if (empty($code)) {
            $_SESSION['flash_error'] = "Coupon code cannot be empty.";
        } else {
            $stmt = $db->prepare("SELECT * FROM coupon_codes WHERE code = ?");
            $stmt->execute([strtoupper($code)]);
            $coupon = $stmt->fetch();
            
            if (!$coupon) {
                $_SESSION['flash_error'] = "Invalid coupon code.";
            } else {
                $today = date('Y-m-d');
                if ($coupon['expiry_date'] && $coupon['expiry_date'] < $today) {
                    $_SESSION['flash_error'] = "Coupon code has expired.";
                } else if ($coupon['max_uses'] !== null && $coupon['uses_count'] >= $coupon['max_uses']) {
                    $_SESSION['flash_error'] = "Coupon code has reached its usage limit.";
                } else {
                    $_SESSION['applied_coupon'] = $coupon;
                    $_SESSION['flash_success'] = "Coupon applied successfully!";
                }
            }
        }
        header("Location: " . $location);
        exit;
    }

    // Buyer Remove Coupon
    if ($action === 'coupon_remove') {
        unset($_SESSION['applied_coupon']);
        $_SESSION['flash_success'] = "Coupon removed successfully.";
        header("Location: index.php?page=" . urlencode($page) . (isset($_GET['id']) ? "&id=" . intval($_GET['id']) : ""));
        exit;
    }

    // Admin Collection Save
    if ($action === 'collection_save') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $collection_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $product_ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];

        if (empty($title)) {
            $_SESSION['flash_error'] = "Collection title cannot be empty.";
        } else {
            try {
                $db->beginTransaction();
                if ($collection_id > 0) {
                    $stmt = $db->prepare("UPDATE collections SET title = ?, description = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $collection_id]);
                } else {
                    $stmt = $db->prepare("INSERT INTO collections (title, description) VALUES (?, ?)");
                    $stmt->execute([$title, $description]);
                    $collection_id = $db->lastInsertId();
                }

                // Sync products
                $db->prepare("DELETE FROM collection_items WHERE collection_id = ?")->execute([$collection_id]);
                if (!empty($product_ids)) {
                    $ins_item = $db->prepare("INSERT INTO collection_items (collection_id, product_id) VALUES (?, ?)");
                    foreach ($product_ids as $pid) {
                        $ins_item->execute([$collection_id, intval($pid)]);
                    }
                }
                $db->commit();
                $_SESSION['flash_success'] = "Collection saved successfully!";
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_error'] = "Failed to save collection: " . $e->getMessage();
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_collections");
        exit;
    }

    // Admin Collection Delete
    if ($action === 'collection_delete') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $id = intval($_POST['id']);
        if ($id > 0) {
            $db->beginTransaction();
            $db->prepare("DELETE FROM collection_items WHERE collection_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM collections WHERE id = ?")->execute([$id]);
            $db->commit();
            $_SESSION['flash_success'] = "Collection deleted successfully!";
        }
        header("Location: index.php?page=dashboard&tab=admin_collections");
        exit;
    }

    // Admin Product Approval
    if ($action === 'product_approve') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $id = intval($_POST['id']);
        $status = isset($_POST['status']) ? trim($_POST['status']) : ''; // 'approved', 'rejected'
        $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

        if ($id > 0 && ($status === 'approved' || $status === 'rejected')) {
            $stmt = $db->prepare("UPDATE products SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            if ($status === 'approved') {
                send_product_email_ads($db, $id);
            }

            // Fetch seller_id & title
            $p_stmt = $db->prepare("SELECT seller_id, title FROM products WHERE id = ?");
            $p_stmt->execute([$id]);
            $prod = $p_stmt->fetch();
            if ($prod) {
                $msg = $status === 'approved' 
                    ? "🎉 Congratulations! Your product \"" . htmlspecialchars($prod['title']) . "\" has been approved and is now live." 
                    : "⚠️ Your product \"" . htmlspecialchars($prod['title']) . "\" was rejected. Reason: " . htmlspecialchars($feedback);
                $db->prepare("INSERT INTO notifications (user_id, type, product_id, message) VALUES (?, 'product_approved', ?, ?)")
                   ->execute([$prod['seller_id'], $id, $msg]);

                // Email notification to seller
                try {
                    $seller_stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                    $seller_stmt->execute([$prod['seller_id']]);
                    $seller = $seller_stmt->fetch();
                    if ($seller) {
                        $site_name = get_platform_name();
                        $email_subject = "Product Catalog Review: " . $prod['title'];
                        $email_body = "Hi " . htmlspecialchars($seller['name']) . ",<br><br>" . $msg . "<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                        send_custom_email($seller['email'], $email_subject, $email_body);
                    }
                } catch (Exception $email_ex) {
                    error_log("Product review email error: " . $email_ex->getMessage());
                }
            }
            $_SESSION['flash_success'] = "Product review complete: " . strtoupper($status);
        }
        header("Location: index.php?page=dashboard&tab=admin_products");
        exit;
    }

    // Admin bulk-approve/reject products
    if ($action === 'bulk_product_approve') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $product_ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];
        $status = isset($_POST['status']) ? trim($_POST['status']) : ''; // 'approved', 'rejected'

        if (!empty($product_ids) && ($status === 'approved' || $status === 'rejected')) {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE products SET status = ? WHERE id = ?");
                $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, type, product_id, message) VALUES (?, 'product_approved', ?, ?)");
                
                foreach ($product_ids as $pid) {
                    $pid = intval($pid);
                    $stmt->execute([$status, $pid]);
                    
                    if ($status === 'approved') {
                        send_product_email_ads($db, $pid);
                    }
                    
                    // Notify seller
                    $p_stmt = $db->prepare("SELECT seller_id, title FROM products WHERE id = ?");
                    $p_stmt->execute([$pid]);
                    $prod = $p_stmt->fetch();
                    if ($prod) {
                        $msg = $status === 'approved' 
                            ? "🎉 Congratulations! Your product \"" . htmlspecialchars($prod['title']) . "\" has been approved in bulk and is now live." 
                            : "⚠️ Your product \"" . htmlspecialchars($prod['title']) . "\" was rejected during bulk review.";
                        $notif_stmt->execute([$prod['seller_id'], $pid, $msg]);

                        // Email notification to seller
                        try {
                            $seller_stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                            $seller_stmt->execute([$prod['seller_id']]);
                            $seller = $seller_stmt->fetch();
                            if ($seller) {
                                $site_name = get_platform_name();
                                $email_subject = "Product Catalog Review: " . $prod['title'];
                                $email_body = "Hi " . htmlspecialchars($seller['name']) . ",<br><br>" . $msg . "<br><br>Thanks,<br>" . htmlspecialchars($site_name);
                                send_custom_email($seller['email'], $email_subject, $email_body);
                            }
                        } catch (Exception $email_ex) {
                            error_log("Product bulk review email error: " . $email_ex->getMessage());
                        }
                    }
                }
                $db->commit();
                $_SESSION['flash_success'] = "Bulk review complete: " . count($product_ids) . " products " . $status;
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_error'] = "Bulk action failed: " . $e->getMessage();
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_products");
        exit;
    }

    // Admin Flash Sale Setup
    if ($action === 'flash_sale_toggle') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $id = intval($_POST['id']);
        $discount_price = (!empty($_POST['discount_price']) && floatval($_POST['discount_price']) > 0) ? floatval($_POST['discount_price']) : null;
        $sale_ends_at = !empty($_POST['sale_ends_at']) ? $_POST['sale_ends_at'] : null;

        if ($id > 0) {
            $stmt = $db->prepare("UPDATE products SET discount_price = ?, sale_ends_at = ? WHERE id = ?");
            $stmt->execute([$discount_price, $sale_ends_at, $id]);
            $_SESSION['flash_success'] = "Flash sale settings updated!";
        }
        header("Location: index.php?page=dashboard&tab=admin_flash_sales");
        exit;
    }

    // User Profile & Account Settings Update
    if ($action === 'profile_update') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        if (get_setting('demo_mode', '0') === '1' && !is_admin()) {
            $_SESSION['flash_error'] = "The platform is currently in read-only Demo Mode. Profile modifications are disabled.";
            header("Location: index.php?page=dashboard&tab=profile");
            exit;
        }
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
        $avatar_url = isset($_POST['avatar_url']) ? trim($_POST['avatar_url']) : '';
        
        if (empty($name)) {
            $_SESSION['flash_error'] = "Name field cannot be left blank.";
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ?, bio = ?, avatar_url = ? WHERE id = ?");
            $stmt->execute([$name, $bio, $avatar_url, $_SESSION['user_id']]);
            $_SESSION['user_name'] = $name;
            $_SESSION['flash_success'] = "Profile details updated successfully!";
        }
        header("Location: index.php?page=dashboard&tab=profile");
        exit;
    }

    // Admin User Management
    if ($action === 'admin_user_manage') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $id = intval($_POST['id']);
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
        $ban_action = isset($_POST['ban_action']) ? trim($_POST['ban_action']) : '';

        if ($id > 0) {
            if ($ban_action === 'ban') {
                $db->prepare("UPDATE users SET role = 'banned' WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = "User account has been suspended.";
            } else if ($ban_action === 'unban') {
                $db->prepare("UPDATE users SET role = 'buyer' WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = "User account has been reinstated.";
            } else if (!empty($role)) {
                $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);
                $_SESSION['flash_success'] = "User role modified successfully.";
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_users");
        exit;
    }

    // Admin Blog CRUD - Save
    if ($action === 'admin_blog_save') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $author = !empty($_POST['author']) ? trim($_POST['author']) : $_SESSION['user_name'];
        $thumbnail = isset($_POST['thumbnail']) ? trim($_POST['thumbnail']) : '';

        if (empty($title) || empty($content)) {
            $_SESSION['flash_error'] = "Title and content cannot be blank.";
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE blog_posts SET title = ?, content = ?, author = ?, thumbnail = ? WHERE id = ?");
                $stmt->execute([$title, $content, $author, $thumbnail, $id]);
                $_SESSION['flash_success'] = "Blog post updated successfully!";
            } else {
                $stmt = $db->prepare("INSERT INTO blog_posts (title, content, author, thumbnail) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $content, $author, $thumbnail]);
                $_SESSION['flash_success'] = "Blog post created successfully!";
            }
        }
        header("Location: index.php?page=dashboard&tab=admin_blog");
        exit;
    }

    // Admin Blog CRUD - Delete
    if ($action === 'admin_blog_delete') {
        if (!is_admin()) {
            $_SESSION['flash_error'] = "Unauthorized.";
            header("Location: index.php");
            exit;
        }
        $id = intval($_POST['id']);
        if ($id > 0) {
            $db->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
            $_SESSION['flash_success'] = "Blog post pruned from records.";
        }
        header("Location: index.php?page=dashboard&tab=admin_blog");
        exit;
    }

    // Live AJAX Search Handler
    if ($action === 'live_search') {
        header('Content-Type: application/json');
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if (strlen($q) < 2) {
            echo json_encode([]);
            exit;
        }
        try {
            $stmt = $db->prepare("SELECT id, title, price, discount_price, category, thumbnail FROM products WHERE status = 'approved' AND (title LIKE ? OR description LIKE ? OR tags LIKE ?) LIMIT 5");
            $stmt->execute(["%$q%", "%$q%", "%$q%"]);
            $results = $stmt->fetchAll();
            
            foreach ($results as &$item) {
                $item['formatted_price'] = format_price($item['price']);
                $item['formatted_discount'] = $item['discount_price'] ? format_price($item['discount_price']) : null;
            }
            echo json_encode($results);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        exit;
    }

    // AJAX Wishlist Toggle
    if ($action === 'wishlist_ajax') {
        header('Content-Type: application/json');
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'message' => 'Must sign in first to watchlist items.']);
            exit;
        }
        $pid = intval($_POST['product_id']);
        $uid = $_SESSION['user_id'];
        
        try {
            $chk_stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $chk_stmt->execute([$uid, $pid]);
            $ws_row = $chk_stmt->fetch();
            if ($ws_row) {
                $db->prepare("DELETE FROM wishlist WHERE id = ?")->execute([$ws_row['id']]);
                echo json_encode(['success' => true, 'status' => 'removed', 'message' => 'Removed from Wishlist.']);
            } else {
                $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)")->execute([$uid, $pid]);
                echo json_encode(['success' => true, 'status' => 'added', 'message' => 'Added to Wishlist.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit;
    }

    // Follow Seller Action
    if ($action === 'follow_seller') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "Must sign in first to follow sellers.";
            header("Location: index.php?page=seller_profile&id=" . intval($_POST['seller_id']));
            exit;
        }
        $seller_id = intval($_POST['seller_id']);
        $uid = $_SESSION['user_id'];
        if ($seller_id === $uid) {
            $_SESSION['flash_error'] = "You cannot follow yourself.";
            header("Location: index.php?page=seller_profile&id=" . $seller_id);
            exit;
        }
        $chk = $db->prepare("SELECT id FROM follows WHERE follower_id = ? AND followed_id = ?");
        $chk->execute([$uid, $seller_id]);
        $row = $chk->fetch();
        if ($row) {
            $db->prepare("DELETE FROM follows WHERE id = ?")->execute([$row['id']]);
            $_SESSION['flash_success'] = "You unfollowed this seller.";
        } else {
            $db->prepare("INSERT INTO follows (follower_id, followed_id) VALUES (?, ?)")->execute([$uid, $seller_id]);
            $_SESSION['flash_success'] = "You are now following this seller!";
        }
        header("Location: index.php?page=seller_profile&id=" . $seller_id);
        exit;
    }

    // Download action handler: downloads static file as zip attachment
    if ($action === 'download') {
        $pid = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        // Fetch product
        $p_stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $p_stmt->execute([$pid]);
        $prod = $p_stmt->fetch();
        
        if ($prod) {
            // Check authorization (purchased or seller or admin)
            $auth_dl = false;
            if (is_admin()) {
                $auth_dl = true;
            } else if (is_logged_in()) {
                if ($_SESSION['user_id'] == $prod['seller_id']) {
                    $auth_dl = true;
                } else {
                    $pur_chk = $db->prepare("SELECT 1 FROM purchases WHERE buyer_id = ? AND product_id = ?");
                    $pur_chk->execute([$_SESSION['user_id'], $pid]);
                    if ($pur_chk->fetch()) $auth_dl = true;
                }
            }
            
            if ($auth_dl) {
                // Return a generic zip file stream
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $prod['title']) . '_v1_0.zip"');
                
                // Read mock zip payload or empty string
                echo "PK\x03\x04\x14\x00\x08\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x0a\x00\x00\x00readme.txtThis is a secured marketplace delivery files for " . htmlspecialchars($prod['title']) . "! License: MIT CodeVault Certified.";
                exit;
            } else {
                $_SESSION['flash_error'] = "Unauthorized download. License activation required.";
                header("Location: index.php?page=product&id=" . $pid);
                exit;
            }
        }
        header("Location: index.php");
        exit;
    }

    // Wishlist Toggling action
    if ($action === 'wishlist_toggle') {
        if (!is_logged_in()) {
            $_SESSION['flash_error'] = "Must sign in first to watchlist items.";
            header("Location: index.php?page=product&id=" . intval($_POST['product_id']));
            exit;
        }
        $pid = intval($_POST['product_id']);
        $uid = $_SESSION['user_id'];
        
        // Check if exists
        $chk_stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $chk_stmt->execute([$uid, $pid]);
        $ws_row = $chk_stmt->fetch();
        if ($ws_row) {
            $db->prepare("DELETE FROM wishlist WHERE id = ?")->execute([$ws_row['id']]);
            $_SESSION['flash_success'] = "Removed from your custom Wishlist.";
        } else {
            $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)")->execute([$uid, $pid]);
            $_SESSION['flash_success'] = "Product watchlisted successfully!";
        }
        header("Location: index.php?page=product&id=" . $pid);
        exit;
    }
}

// Intercept flash messages
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Fetch general products for cart info mapping
$cart_products_arr = [];
if (!empty($_SESSION['cart'])) {
    $in_placeholder_clause = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $cart_q = $db->prepare("SELECT * FROM products WHERE id IN ($in_placeholder_clause)");
    $cart_q->execute($_SESSION['cart']);
    $cart_products_arr = $cart_q->fetchAll();
}
$cart_total_price = 0.0;
foreach($cart_products_arr as $cp) $cart_total_price += floatval($cp['discount_price'] ?: $cp['price']);

// Fetch unread notifications count
$unread_notifications_count = 0;
$notifications_list = [];
if (is_logged_in()) {
    $notif_stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $notif_stmt->execute([$_SESSION['user_id']]);
    $notifications_list = $notif_stmt->fetchAll();
    
    $notif_count_stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND `read` = 0");
    $notif_count_stmt->execute([$_SESSION['user_id']]);
    $unread_notifications_count = $notif_count_stmt->fetchColumn();
}

// Fetch categories for Tier-2 menu
$header_categories_stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
$header_categories = $header_categories_stmt->fetchAll();

?>
<?php
// Retrieve active page info for context-specific SEO
$page_title = get_setting('seo_site_title', 'CodeVault - Digital Marketplace');
$page_desc = get_setting('seo_meta_description', 'Buy and sell scripts, themes, and plugins.');
$page_keywords = get_setting('seo_meta_keywords', 'scripts, themes, plugins, templates, marketplace');
$page_og_img = get_setting('seo_og_image', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600');
$page_type = 'website';

// Fetch absolute current URL
$current_abs_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');

$p_seo = null;
if ($page === 'product' && isset($_GET['id'])) {
    $p_seo_stmt = $db->prepare("SELECT title, description, price, thumbnail FROM products WHERE id = ?");
    $p_seo_stmt->execute([intval($_GET['id'])]);
    $p_seo = $p_seo_stmt->fetch();
    if ($p_seo) {
        $page_title = htmlspecialchars($p_seo['title']) . " - CodeVault";
        $page_desc = htmlspecialchars(substr(strip_tags($p_seo['description']), 0, 160));
        if (!empty($p_seo['thumbnail'])) {
            $page_og_img = (strpos($p_seo['thumbnail'], 'http') === 0) ? $p_seo['thumbnail'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']) . '/' . $p_seo['thumbnail']);
        }
        $page_type = 'product';
    }
} elseif ($page === 'blog' && isset($_GET['post_id'])) {
    $b_seo_stmt = $db->prepare("SELECT title, content, thumbnail FROM blog_posts WHERE id = ?");
    $b_seo_stmt->execute([intval($_GET['post_id'])]);
    $b_seo = $b_seo_stmt->fetch();
    if ($b_seo) {
        $page_title = htmlspecialchars($b_seo['title']) . " - Blog - CodeVault";
        $page_desc = htmlspecialchars(substr(strip_tags($b_seo['content']), 0, 160));
        if (!empty($b_seo['thumbnail'])) {
            $page_og_img = (strpos($b_seo['thumbnail'], 'http') === 0) ? $b_seo['thumbnail'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']) . '/' . $b_seo['thumbnail']);
        }
        $page_type = 'article';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php 
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? true : ($_SERVER['SERVER_PORT'] == 443));
        $protocol = $is_https ? 'https' : 'http';
        $base_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base_path = ($base_dir === '/' || $base_dir === '\\') ? '' : $base_dir;
        echo htmlspecialchars($protocol . '://' . $host . $base_path . '/index.php?action=favicon'); 
    ?>">
    
    <!-- Basic SEO -->
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $page_desc; ?>">
    <meta name="keywords" content="<?php echo $page_keywords; ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?php echo $page_type; ?>">
    <meta property="og:url" content="<?php echo $current_abs_url; ?>">
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $page_desc; ?>">
    <meta property="og:image" content="<?php echo $page_og_img; ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo $current_abs_url; ?>">
    <meta property="twitter:title" content="<?php echo $page_title; ?>">
    <meta property="twitter:description" content="<?php echo $page_desc; ?>">
    <meta property="twitter:image" content="<?php echo $page_og_img; ?>">
    
    <!-- Google Tag Manager -->
    <?php if ($gtm_id = get_setting('analytics_gtm_id')): ?>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo htmlspecialchars($gtm_id); ?>');</script>
    <?php endif; ?>
    
    <!-- Google Analytics (GA4) -->
    <?php if ($ga4_id = get_setting('analytics_ga4_id')): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($ga4_id); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?php echo htmlspecialchars($ga4_id); ?>');
    </script>
    <?php endif; ?>
    
    <!-- Facebook Pixel -->
    <?php if ($pixel_id = get_setting('analytics_facebook_pixel_id')): ?>
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '<?php echo htmlspecialchars($pixel_id); ?>');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($pixel_id); ?>&ev=PageView&noscript=1"
    /></noscript>
    <?php endif; ?>
    
    <!-- Schema.org structured JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "<?php echo htmlspecialchars(get_setting('schema_business_type', 'Store')); ?>",
      "name": "<?php echo htmlspecialchars(get_setting('seo_site_title', 'CodeVault')); ?>",
      "url": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']); ?>",
      <?php if ($phone = get_setting('schema_phone')): ?>
      "telephone": "<?php echo htmlspecialchars($phone); ?>",
      <?php endif; ?>
      <?php if ($address = get_setting('schema_address')): ?>
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "<?php echo htmlspecialchars($address); ?>"
      },
      <?php endif; ?>
      "description": "<?php echo htmlspecialchars($page_desc); ?>"
    }
    </script>
    <?php if ($page === 'product' && isset($p_seo) && $p_seo): ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Product",
      "name": "<?php echo htmlspecialchars($p_seo['title']); ?>",
      "image": "<?php echo $page_og_img; ?>",
      "description": "<?php echo htmlspecialchars(substr(strip_tags($p_seo['description']), 0, 200)); ?>",
      "offers": {
        "@type": "Offer",
        "url": "<?php echo $current_abs_url; ?>",
        "priceCurrency": "USD",
        "price": "<?php echo floatval($p_seo['price'] ?? 0.00); ?>",
        "availability": "https://schema.org/InStock"
      }
    }
    </script>
    <?php endif; ?>

    <!-- Header Custom Injected Code -->
    <?php echo get_setting('seo_header_injection'); ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        .accent-color {
            color: #5cb85c;
        }
        .accent-bg {
            background-color: #5cb85c;
        }
        .accent-border {
            border-color: #5cb85c;
        }
        .hover-accent:hover {
            color: #5cb85c;
        }
        .hover-accent-bg:hover {
            background-color: #4cae4c;
        }
        /* Dropdown custom visibility */
        .group:hover .group-hover\:block {
            display: block;
        }
    </style>
</head>
<body class="bg-[#f5f5f5] text-slate-800 min-h-screen flex flex-col justify-between">
    <!-- Google Tag Manager (noscript) -->
    <?php if ($gtm_id = get_setting('analytics_gtm_id')): ?>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo htmlspecialchars($gtm_id); ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <?php endif; ?>

    <!-- Codester 2-Tier Header -->
    <header class="w-full sticky top-0 z-40 shadow-md">
        
        <!-- Tier 1: Dark Slate top navigation -->
        <div class="bg-[#1c2229] py-3 px-6 text-white border-b border-slate-700/50">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
                
                <!-- Logo & Autocomplete Search -->
                <div class="flex items-center gap-6 w-full md:w-auto">
                    <a href="index.php?page=marketplace" class="flex items-center gap-2.5 shrink-0">
                        <div class="w-9 h-9 rounded-lg bg-[#5cb85c] flex items-center justify-center text-white font-black text-xl shadow-md">
                            C
                        </div>
                        <div>
                            <h1 class="font-extrabold text-base tracking-tight leading-tight">CodeVault</h1>
                        </div>
                    </a>
                    
                    <!-- Live Autocomplete Search Container -->
                    <div class="relative w-full md:w-80 shrink-0">
                        <div class="flex items-center">
                            <input 
                                type="text" 
                                id="live-search-input" 
                                autocomplete="off"
                                placeholder="Search scripts, themes, templates..." 
                                class="w-full pl-3 pr-10 py-1.5 rounded bg-slate-800 text-white text-xs border border-slate-700 outline-none focus:border-[#5cb85c] focus:bg-slate-900 transition-colors"
                            >
                            <span class="absolute right-3 text-slate-400 pointer-events-none text-xs">🔍</span>
                        </div>
                        
                        <!-- Floating Live Search Dropdown -->
                        <div id="live-search-results" class="absolute left-0 right-0 mt-2 bg-white rounded-lg shadow-xl border border-gray-200 text-slate-800 hidden overflow-hidden z-50">
                            <!-- JS Inject results -->
                        </div>
                    </div>
                </div>

                <!-- Top Tier Menu links & Actions -->
                <div class="flex items-center justify-between md:justify-end gap-6 w-full md:w-auto text-xs font-semibold">
                    <div class="flex items-center gap-4 text-slate-300">
                        <a href="<?php echo url('free_files'); ?>" class="hover:text-white transition-colors <?php echo $page === 'free_files' ? 'text-white' : ''; ?>">Free Files</a>
                        <a href="<?php echo url('flash_sale'); ?>" class="hover:text-white transition-colors flex items-center gap-1 <?php echo $page === 'flash_sale' ? 'text-white' : ''; ?>">
                            <span class="text-orange-400">⚡</span> Flash Sale
                        </a>
                        <a href="<?php echo url('forums'); ?>" class="hover:text-white transition-colors <?php echo $page === 'forums' ? 'text-white' : ''; ?>">Forums</a>
                        <a href="<?php echo url('blog'); ?>" class="hover:text-white transition-colors <?php echo $page === 'blog' ? 'text-white' : ''; ?>">Blog</a>
                        <a href="<?php echo url('tutorials'); ?>" class="hover:text-white transition-colors <?php echo $page === 'tutorials' ? 'text-white' : ''; ?>">Blueprints</a>
                        <a href="<?php echo url('collections'); ?>" class="hover:text-white transition-colors <?php echo $page === 'collections' ? 'text-white' : ''; ?>">Collections</a>
                    </div>
                    
                    <div class="flex items-center gap-4 bg-transparent border-l border-slate-700/50 pl-4">
                        <!-- Shopping Cart Button -->
                        <button onclick="toggleCartDrawer()" class="relative p-2 rounded bg-slate-800 hover:bg-slate-700 transition-colors outline-none">
                            🛒
                            <?php if (!empty($_SESSION['cart'])): ?>
                                <span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-[#5cb85c] text-white flex items-center justify-center text-[10px] font-mono font-bold shadow shadow-emerald-500/20"><?php echo count($_SESSION['cart']); ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Notifications Bell Dropdown -->
                        <?php if (is_logged_in()): ?>
                            <div class="relative group">
                                <button class="p-2 rounded bg-slate-800 hover:bg-slate-700 transition-colors outline-none relative">
                                    🔔
                                    <?php if ($unread_notifications_count > 0): ?>
                                        <span class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-red-500 text-white flex items-center justify-center text-[9px] font-mono font-bold"><?php echo $unread_notifications_count; ?></span>
                                    <?php endif; ?>
                                </button>
                                
                                <div class="absolute right-0 top-full pt-2 w-80 hidden group-hover:block z-50">
                                    <div class="bg-white rounded-lg shadow-xl border border-gray-100 text-slate-800 overflow-hidden">
                                        <div class="p-3 border-b border-gray-100 font-bold flex justify-between items-center text-xs">
                                            <span>Notifications</span>
                                            <span class="text-[10px] text-gray-400 font-medium"><?php echo $unread_notifications_count; ?> unread</span>
                                        </div>
                                        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                                            <?php if (empty($notifications_list)): ?>
                                                <div class="p-4 text-center text-xs text-gray-400">No notifications yet.</div>
                                            <?php else: ?>
                                                <?php foreach ($notifications_list as $notif): ?>
                                                    <div class="p-3 hover:bg-gray-50 flex flex-col gap-1 text-[11px] <?php echo $notif['read'] == 0 ? 'bg-emerald-50/50' : ''; ?>">
                                                        <p class="leading-relaxed"><?php echo htmlspecialchars($notif['message']); ?></p>
                                                        <span class="text-[9px] text-gray-400"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-2 bg-gray-50 text-center border-t border-gray-100">
                                            <a href="index.php?page=dashboard&tab=notifications" class="text-xs text-[#5cb85c] hover:underline font-bold">View all dashboard notifications</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- User Profile Menu -->
                        <?php if (is_logged_in()): ?>
                            <div class="relative group">
                                <a href="<?php echo url('dashboard'); ?>" class="flex items-center gap-1.5 px-4 py-1.5 bg-[#5cb85c] hover:bg-[#4cae4c] text-white font-bold rounded transition-colors text-xs shadow-sm outline-none">
                                    <span>Dashboard</span>
                                    <span class="hidden md:inline border-l border-white/20 pl-1.5 text-slate-200"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                                </a>
                                
                                <div class="absolute right-0 top-full pt-2 w-48 hidden group-hover:block z-50">
                                    <div class="bg-white rounded-lg shadow-xl border border-gray-100 text-slate-800 overflow-hidden">
                                        <div class="p-3 bg-gray-50/50 border-b border-gray-100">
                                            <p class="font-bold text-xs text-slate-900 line-clamp-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                                            <p class="text-[9px] text-gray-400 capitalize"><?php echo htmlspecialchars($_SESSION['user_role']); ?></p>
                                        </div>
                                        <a href="<?php echo url('dashboard'); ?>" class="block px-4 py-2.5 text-xs text-slate-700 hover:bg-gray-50 hover:text-slate-900 transition-colors">My Dashboard</a>
                                        <a href="<?php echo url('dashboard'); ?>?tab=purchases" class="block px-4 py-2.5 text-xs text-slate-700 hover:bg-gray-50 hover:text-slate-900 transition-colors">Purchases</a>
                                        <?php if (is_seller()): ?>
                                            <a href="<?php echo url('dashboard'); ?>?tab=products" class="block px-4 py-2.5 text-xs text-slate-700 hover:bg-gray-50 hover:text-slate-900 transition-colors">My Products</a>
                                            <a href="<?php echo url('dashboard'); ?>?tab=sales" class="block px-4 py-2.5 text-xs text-slate-700 hover:bg-gray-50 hover:text-slate-900 transition-colors">Sales History</a>
                                        <?php endif; ?>
                                        <a href="<?php echo url('affiliate'); ?>" class="block px-4 py-2.5 text-xs text-slate-700 hover:bg-gray-50 hover:text-slate-900 transition-colors">Affiliate Link</a>
                                        <hr class="border-gray-100">
                                        <a href="index.php?action=logout" class="block px-4 py-2.5 text-xs text-red-600 hover:bg-red-50 transition-colors">Logout</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <button onclick="openLoginModal()" class="px-4 py-1.5 bg-[#5cb85c] hover:bg-[#4cae4c] text-white font-bold rounded transition-colors text-xs shadow-sm">Sign In / Sign Up</button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Tier 2: White Category dropdown bar -->
        <div class="bg-white border-b border-gray-200 shadow-sm py-2 px-6">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                
                <!-- Mega Dropdowns / Category List -->
                <div class="flex items-center gap-6 text-slate-700 text-xs font-bold bg-transparent">
                    <a href="index.php?page=marketplace" class="text-slate-900 hover-accent transition-colors flex items-center gap-1 font-extrabold text-[13px] mr-2">
                        Marketplace Home
                    </a>
                    
                    <?php foreach ($header_categories as $cat): ?>
                        <a href="index.php?page=marketplace&category=<?php echo urlencode($cat['name']); ?>" class="hover-accent transition-colors py-1.5 flex items-center gap-1.5">
                            <?php if ($cat['icon']): ?>
                                <span><?php echo htmlspecialchars($cat['icon']); ?></span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- CTA buttons -->
                <div class="hidden sm:flex items-center gap-3">
                    <?php if (is_seller()): ?>
                        <button onclick="openProductModal()" class="px-4 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded text-xs font-bold flex items-center gap-1 shadow-sm">
                            <span>📤</span> Upload Work
                        </button>
                    <?php else: ?>
                        <a href="index.php?page=dashboard&tab=verification" class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300/40 rounded text-xs font-bold flex items-center gap-1">
                            Start Selling
                        </a>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>

    </header>

    <!-- Content Outer Wrapper -->
    <main class="max-w-7xl w-full mx-auto px-6 py-8 flex-1 flex flex-col justify-start">
        
        <!-- Top Advertisement Zone -->
        <?php 
        $ad_top_enabled = get_setting('ad_top_enabled', '0');
        $ad_top_code = get_setting('ad_top_code', '');
        if ($ad_top_enabled === '1' && !empty($ad_top_code)): 
        ?>
            <div class="mb-6 p-2 bg-white rounded border border-gray-200/80 shadow-sm flex items-center justify-center overflow-hidden max-w-full animate-fade">
                <div class="w-full text-center">
                    <?php echo $ad_top_code; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Action Alerts -->
        <?php if ($error): ?>
            <div class="mb-8 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3 shadow-sm">
                <span class="text-base mt-0.5">⚠️</span>
                <div>
                    <h4 class="font-bold text-red-900 text-sm">Action Failure</h4>
                    <p class="text-xs text-red-700 font-medium mt-0.5 leading-relaxed"><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-8 p-4 bg-emerald-50 border border-emerald-200 rounded-lg flex items-start gap-3 shadow-sm">
                <span class="text-base mt-0.5">✓</span>
                <div>
                    <h4 class="font-bold text-emerald-900 text-sm">System Success</h4>
                    <p class="text-xs text-emerald-700 font-medium mt-0.5 leading-relaxed"><?php echo $success; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Active View Inclusion -->
        <?php
        $view_map = [
            'marketplace' => 'marketplace.php',
            'product' => 'product.php',
            'dashboard' => 'dashboard.php',
            'blog' => 'blog.php',
            'forums' => 'forums.php',
            'tutorials' => 'tutorials.php',
            'affiliate' => 'affiliate.php',
            'seller_profile' => 'seller_profile.php',
            'flash_sale' => 'flash_sale.php',
            'free_files' => 'free_files.php',
            'collections' => 'collections.php',
            'policies' => 'policies.php',
            'help_center' => 'help_center.php',
        ];
        
        $load_view = isset($view_map[$page]) ? $view_map[$page] : 'marketplace.php';
        $view_path = __DIR__ . '/pages/' . $load_view;
        
        if (file_exists($view_path)) {
            require_once $view_path;
        } else {
            echo "<div class='text-center py-16 font-semibold border rounded-lg bg-white text-slate-400'>Error loading template grid file: " . htmlspecialchars($load_view) . "</div>";
        }
        ?>

    </main>

    <!-- Codester-Style 4-Column Dark Footer -->
    <footer class="bg-[#1c2229] border-t border-slate-700 text-slate-300 py-16 px-6 text-xs select-none">
        <div class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-10">
            
            <!-- Column 1: About / Branding -->
            <div class="space-y-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded bg-[#5cb85c] flex items-center justify-center text-white font-black text-lg">
                        C
                    </div>
                    <span class="font-extrabold text-base tracking-tight text-white">CodeVault</span>
                </div>
                <p class="leading-relaxed text-slate-400">
                    CodeVault is a premier digital marketplace for developers, designer and developers. Buy and sell PHP scripts, app templates, premium themes, plugins, and graphics.
                </p>
                <div class="flex gap-3 text-slate-400 text-sm">
                    <a href="#" class="hover:text-white transition-colors">🌐</a>
                    <a href="#" class="hover:text-white transition-colors">🐦</a>
                    <a href="#" class="hover:text-white transition-colors">💬</a>
                    <a href="#" class="hover:text-white transition-colors">🐙</a>
                </div>
            </div>

            <!-- Column 2: Marketplace items -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-white text-sm uppercase tracking-wide border-b border-slate-800 pb-2">Marketplace</h4>
                <div class="flex flex-col gap-2.5 text-slate-400">
                    <a href="<?php echo url('marketplace'); ?>" class="hover:text-[#5cb85c] transition-colors">Browse Products</a>
                    <a href="<?php echo url('flash_sale'); ?>" class="hover:text-[#5cb85c] transition-colors">Flash Sales</a>
                    <a href="<?php echo url('free_files'); ?>" class="hover:text-[#5cb85c] transition-colors">Free Files</a>
                    <a href="<?php echo url('collections'); ?>" class="hover:text-[#5cb85c] transition-colors">Curated Collections</a>
                    <a href="<?php echo url('marketplace'); ?>?category=Scripts" class="hover:text-[#5cb85c] transition-colors">PHP Scripts</a>
                    <a href="<?php echo url('marketplace'); ?>?category=Themes" class="hover:text-[#5cb85c] transition-colors">WordPress Themes</a>
                </div>
            </div>

            <!-- Column 3: Community & Programs -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-white text-sm uppercase tracking-wide border-b border-slate-800 pb-2">Community</h4>
                <div class="flex flex-col gap-2.5 text-slate-400">
                    <a href="<?php echo url('forums'); ?>" class="hover:text-[#5cb85c] transition-colors">Discussions Forum</a>
                    <a href="<?php echo url('blog'); ?>" class="hover:text-[#5cb85c] transition-colors">Insights Blog</a>
                    <a href="<?php echo url('tutorials'); ?>" class="hover:text-[#5cb85c] transition-colors">Blueprints Tutorials</a>
                    <a href="<?php echo url('affiliate'); ?>" class="hover:text-[#5cb85c] transition-colors">Affiliate Program</a>
                    <a href="<?php echo url('dashboard'); ?>?tab=verification" class="hover:text-[#5cb85c] transition-colors">Become a Seller</a>
                </div>
            </div>

            <!-- Column 4: Legals & Support -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-white text-sm uppercase tracking-wide border-b border-slate-800 pb-2">Support & Help</h4>
                <div class="flex flex-col gap-2.5 text-slate-400">
                    <a href="<?php echo url('help_center'); ?>" class="hover:text-[#5cb85c] transition-colors">Help Center</a>
                    <a href="<?php echo url('policies'); ?>?tab=licensing" class="hover:text-[#5cb85c] transition-colors">Licensing Terms</a>
                    <a href="<?php echo url('policies'); ?>?tab=terms" class="hover:text-[#5cb85c] transition-colors">Terms of Service</a>
                    <a href="<?php echo url('policies'); ?>?tab=privacy" class="hover:text-[#5cb85c] transition-colors">Privacy Policy</a>
                    <a href="<?php echo url('policies'); ?>?tab=refunds" class="hover:text-[#5cb85c] transition-colors">Refund Policy</a>
                    <a href="<?php echo url('policies'); ?>?tab=aml" class="hover:text-[#5cb85c] transition-colors">KYC & AML Policy</a>
                </div>
            </div>

        </div>
        
        <div class="max-w-7xl mx-auto border-t border-slate-850 mt-12 pt-8 flex flex-col sm:flex-row justify-between items-center gap-4 text-slate-500 bg-transparent text-[11px] font-semibold">
            <p>&copy; 2026 CodeVault Inc. All rights reserved. Platform powered by secure Paystack escrow channels.</p>
            <div class="flex gap-4">
                <span>Safe SSL</span>
                <span>•</span>
                <span>Secure Payments</span>
                <span>•</span>
                <span>Instant Delivery</span>
            </div>
        </div>
        
    </footer>

    <!-- Authorization Portal Modal (Login & Register) -->
    <div id="login-auth-portal-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden select-none">
        <div class="bg-white rounded-lg w-full max-w-sm p-8 border border-white/10 shadow-2xl relative">
            <button onclick="closeLoginModal()" class="absolute right-6 top-6 text-slate-400 hover:text-slate-900 font-bold text-lg outline-none">✕</button>
            
            <!-- Tab switches inside modal -->
            <div class="flex gap-4 mb-6 border-b pb-2 text-sm font-bold uppercase tracking-wider">
                <button onclick="toggleAuthTab('login')" id="tab-login-btn" class="border-b-2 border-[#5cb85c] text-[#5cb85c] outline-none pb-1">Sign In</button>
                <button onclick="toggleAuthTab('register')" id="tab-register-btn" class="border-b-2 border-transparent text-slate-400 hover:text-slate-850 outline-none pb-1">Create Account</button>
            </div>

             <form id="auth-login-form" method="POST" action="index.php?action=login&page=<?php echo urlencode($page); ?>" class="space-y-4">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-450 uppercase tracking-widest">Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c] shadow-sm">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-455 uppercase tracking-widest">Secret Password</label>
                    <input type="password" name="pass" required placeholder="••••••••" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-mono focus:border-[#5cb85c] shadow-sm">
                </div>
                <div class="text-right">
                    <a href="javascript:void(0)" onclick="toggleAuthTab('forgot')" class="text-[10px] text-[#5cb85c] font-bold hover:underline">Forgot Password?</a>
                </div>
                <button type="submit" class="w-full py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold uppercase rounded text-xs shadow transition-colors">Authenticate</button>
            </form>

            <!-- Forgot Password request form -->
            <form id="auth-forgot-form" method="POST" action="index.php?action=forgot_password&page=<?php echo urlencode($page); ?>" class="space-y-4 hidden text-left">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Email Address</label>
                    <input type="email" name="email" required placeholder="Enter account email" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c] shadow-sm">
                </div>
                <button type="submit" class="w-full py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold uppercase rounded text-xs shadow transition-colors">Request Reset OTP</button>
                <div class="text-center mt-2">
                    <a href="javascript:void(0)" onclick="toggleAuthTab('login')" class="text-[10px] text-slate-500 font-bold hover:underline">← Back to Sign In</a>
                </div>
            </form>

            <!-- Reset Password verification form -->
            <form id="auth-reset-form" method="POST" action="index.php?action=reset_password&page=<?php echo urlencode($page); ?>" class="space-y-4 hidden text-left">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                <div class="bg-emerald-50 border border-emerald-100 rounded p-3 text-[11px] text-emerald-800 font-medium text-center">
                    Enter the 6-digit OTP code dispatched to <?php echo htmlspecialchars($_GET['email'] ?? ''); ?>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">6-Digit Verification Code (OTP)</label>
                    <input type="text" name="otp" required maxlength="6" placeholder="e.g. 123456" class="w-full px-4 py-3 rounded border text-center outline-none bg-white text-base tracking-widest font-black focus:border-[#5cb85c] shadow-sm">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Define New Password</label>
                    <input type="password" name="pass" required placeholder="Enter new password" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-mono focus:border-[#5cb85c] shadow-sm">
                </div>
                <button type="submit" class="w-full py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold uppercase rounded text-xs shadow transition-colors">Reset Password</button>
                <div class="text-center mt-2">
                    <a href="javascript:void(0)" onclick="toggleAuthTab('forgot')" class="text-[10px] text-slate-500 font-bold hover:underline">← Resend OTP Code</a>
                </div>
            </form>

            <!-- Register form block -->
            <form id="auth-register-form" method="POST" action="index.php?action=register&page=<?php echo urlencode($page); ?>" class="space-y-4 hidden">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Full Legal Name</label>
                    <input type="text" name="name" required placeholder="e.g. John Doe" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c] shadow-sm">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Email Address</label>
                    <input type="email" name="email" required placeholder="john@example.com" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c] shadow-sm">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Define Password</label>
                    <input type="password" name="pass" required placeholder="Create password" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-mono focus:border-[#5cb85c] shadow-sm">
                </div>
                <!-- Role selector -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Intended Account Role</label>
                    <select name="role" required class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c] shadow-sm">
                        <option value="buyer">Buyer (Retrieve & Download items)</option>
                        <option value="seller">Seller (Upload scripts & receive payouts)</option>
                    </select>
                </div>
                <button type="submit" class="w-full py-3.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold uppercase rounded text-xs shadow transition-colors">Join CodeVault</button>
            </form>
        </div>
    </div>

    <!-- Slide Out Shopping Drawer Panel -->
    <div id="shopping-cart-drawer" class="fixed inset-0 z-50 overflow-hidden hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="toggleCartDrawer()"></div>
            
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div class="pointer-events-auto w-screen max-w-md">
                    <div class="flex h-full flex-col overflow-y-scroll bg-white rounded-l-lg border-l py-6 shadow-2xl relative">
                        <div class="px-6 flex items-start justify-between">
                            <h2 class="text-lg font-black text-slate-950 tracking-tight" id="slide-over-title">Review Shopping Cart</h2>
                            <button type="button" onclick="toggleCartDrawer()" class="text-slate-400 hover:text-slate-900 outline-none text-base">✕</button>
                        </div>
                        
                        <div class="mt-8 flex-1 px-6 space-y-4">
                            <?php if (empty($cart_products_arr)): ?>
                                <div class="text-center py-16 text-slate-400 font-bold text-xs leading-relaxed border border-dashed rounded-lg p-6">
                                    🛒 Your Shopping Cart is currently empty.
                                </div>
                            <?php else: ?>
                                <div class="divide-y divide-gray-100 text-xs space-y-3">
                                    <?php foreach($cart_products_arr as $item): ?>
                                        <div class="flex justify-between items-center py-3 bg-transparent">
                                            <div class="flex items-center gap-2.5">
                                                <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" class="w-10 h-10 object-cover rounded">
                                                <div>
                                                    <h4 class="font-extrabold text-slate-900 line-clamp-1 mb-0.5"><?php echo htmlspecialchars($item['title']); ?></h4>
                                                    <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($item['category']); ?></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="font-mono font-bold text-slate-850 text-sm"><?php echo format_price($item['discount_price'] ?: $item['price']); ?></span>
                                                <form method="POST" action="index.php?action=cart_remove&page=<?php echo urlencode($page); ?>">
                                                    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="text-red-500 font-bold hover:scale-105 transition-transform text-sm outline-none">✕</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Checkout Summary Panel -->
                        <div class="border-t border-gray-100 px-6 pt-6 space-y-4">
                            <!-- Coupon discount code entry -->
                            <form method="POST" action="index.php?action=coupon_apply&page=<?php echo urlencode($page); ?>" class="flex gap-2">
                                <input type="text" name="coupon_code" required placeholder="Enter Coupon Code" class="flex-1 px-3 py-2 rounded border text-xs outline-none focus:border-[#5cb85c]">
                                <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded text-xs transition-colors">Apply</button>
                            </form>
                            
                            <?php if (isset($_SESSION['applied_coupon'])): ?>
                                <div class="p-2.5 bg-emerald-50 border border-emerald-100 text-[#5cb85c] rounded text-[11px] font-bold flex justify-between items-center">
                                    <span>Coupon Code Applied: <strong><?php echo htmlspecialchars($_SESSION['applied_coupon']['code']); ?></strong> (-<?php echo $_SESSION['applied_coupon']['type'] === 'percentage' ? $_SESSION['applied_coupon']['value'].'%' : format_price($_SESSION['applied_coupon']['value']); ?>)</span>
                                    <a href="index.php?action=coupon_remove&page=<?php echo urlencode($page); ?>" class="text-red-500 hover:underline">Remove</a>
                                </div>
                            <?php endif; ?>

                            <div class="flex justify-between items-center font-bold text-xs select-none">
                                <span class="text-slate-500">Cart Total:</span>
                                <span class="text-lg font-mono font-black text-slate-900">
                                    <?php
                                    $final_cart_total = $cart_total_price;
                                    if (isset($_SESSION['applied_coupon'])) {
                                        $cp_row = $_SESSION['applied_coupon'];
                                        if ($cp_row['type'] === 'percentage') {
                                            $final_cart_total = max(0.0, $cart_total_price - ($cart_total_price * ($cp_row['value']/100)));
                                        } else {
                                            $final_cart_total = max(0.0, $cart_total_price - floatval($cp_row['value']));
                                        }
                                    }
                                    echo format_price($final_cart_total);
                                    ?>
                                </span>
                            </div>

                            <?php if (!empty($cart_products_arr)): ?>
                                <button 
                                    onclick="triggerPaystackCartCheckout()"
                                    class="w-full bg-[#5cb85c] hover:bg-[#4cae4c] text-white py-3.5 rounded text-xs font-extrabold uppercase shadow transition-all flex items-center justify-center gap-2 outline-none"
                                >
                                    💰 Paystack Cart Checkout
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Payment Loader Modal -->
    <div id="payment-loader-overlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md flex flex-col items-center justify-center p-4 z-50 hidden select-none">
        <div class="w-16 h-16 border-4 border-[#5cb85c] border-t-transparent rounded-full animate-spin"></div>
        <h3 class="font-black text-white text-xl mt-6">Securing Paystack Gateway Link...</h3>
        <p class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest mt-1">Connecting payment ledger pipelines</p>
    </div>

    <!-- Screenshot Fullscreen Lightbox Modal component -->
    <div id="screenshot-lightbox-modal-core" class="fixed inset-0 bg-black/95 backdrop-blur-md z-50 flex flex-col justify-between hidden select-none" onclick="closeLightboxModal()">
        
        <!-- Header controls -->
        <div class="p-6 flex justify-between items-center text-white" onclick="event.stopPropagation()">
            <div>
                <h4 id="lightbox-title-text" class="font-extrabold text-base tracking-tight leading-none mb-1">Product Screenshots</h4>
                <p class="text-[10px] font-bold text-[#5cb85c] uppercase tracking-wider" id="lightbox-category-text">Scripts</p>
            </div>
            <button onclick="closeLightboxModal()" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-xs font-bold uppercase rounded transition-all outline-none">✕ Close</button>
        </div>

        <!-- Carousel Content Frame -->
        <div class="flex-1 flex items-center justify-between px-6" onclick="event.stopPropagation()">
            <button onclick="prevLightboxImage()" class="p-4 bg-white/10 hover:bg-white/20 select-none text-white rounded-full transition-all outline-none">◀</button>
            <div class="max-w-4xl max-h-[70vh] rounded overflow-hidden border border-white/10 shadow-2xl">
                <img id="lightbox-main-img-view" src="" alt="Active product image view" class="max-w-full max-h-[70vh] object-cover" referrerpolicy="no-referrer">
            </div>
            <button onclick="nextLightboxImage()" class="p-4 bg-white/10 hover:bg-white/20 select-none text-white rounded-full transition-all outline-none">▶</button>
        </div>

        <!-- Footer indicator -->
        <div class="p-6 text-center text-white font-mono text-xs select-none" onclick="event.stopPropagation()">
            <span id="lightbox-footer-indicator">1 / 1</span>
        </div>

    </div>

    <!-- Stateful Product Management Modal (Add and Edit) -->
    <div id="product-studio-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden select-none">
        <div class="bg-white rounded-lg w-full max-w-2xl p-8 border border-white/10 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <button onclick="closeProductModal()" class="absolute right-6 top-6 text-slate-400 hover:text-slate-900 font-bold text-lg outline-none">✕</button>
            
            <h3 id="product-modal-title" class="font-black text-xl text-slate-900 mb-2">Publish Code script</h3>
            <p class="text-xs text-slate-500 mb-6">Listed products are peer-vetted automatically. Earn split royalties instantly through verified Paystack settlement triggers.</p>

            <form method="POST" action="index.php?action=product_save" class="space-y-4">
                <input type="hidden" name="id" id="prod-input-id" value="0">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Product Title</label>
                        <input type="text" name="title" id="prod-input-title" required placeholder="e.g. SaaS Boilerplate..." class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c] shadow-sm">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Regular Price</label>
                        <input type="number" step="0.01" name="price" id="prod-input-price" required placeholder="49.99" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-mono focus:border-[#5cb85c] shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Taxonomy category</label>
                        <select name="category" id="prod-input-category" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c] shadow-sm">
                            <?php
                            $cat_opts_stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
                            $cat_options = $cat_opts_stmt->fetchAll();
                            foreach ($cat_options as $cat_opt):
                            ?>
                                <option value="<?php echo htmlspecialchars($cat_opt['name']); ?>"><?php echo htmlspecialchars($cat_opt['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Interactive Video / demo URL</label>
                        <input type="url" name="live_demo_url" id="prod-input-demo" placeholder="https://demo.example.com" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs focus:border-[#5cb85c] shadow-sm">
                    </div>
                </div>

                <div class="space-y-2 border-t pt-4">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Product Thumbnail Image</label>
                        <div class="inline-flex rounded shadow-sm" role="group">
                            <button type="button" onclick="setThumbMode('url')" id="btn-thumb-mode-url" class="px-2.5 py-1 text-[10px] font-bold text-white bg-[#5cb85c] rounded-l border border-[#5cb85c] outline-none">URL Input</button>
                            <button type="button" onclick="setThumbMode('file')" id="btn-thumb-mode-file" class="px-2.5 py-1 text-[10px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-r border border-gray-300 outline-none">Local Upload</button>
                        </div>
                    </div>
                    
                    <div class="flex gap-4 items-center">
                        <div class="w-16 h-16 rounded border bg-slate-50 flex items-center justify-center overflow-hidden shrink-0 shadow-inner" id="thumb-preview-box">
                            <img id="prod-thumb-preview-img" src="https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600" class="w-full h-full object-cover" alt="Thumbnail Preview" referrerpolicy="no-referrer">
                        </div>
                        
                        <div class="flex-1">
                            <div id="thumb-input-pane-url" class="block">
                                <input type="url" name="thumbnail" id="prod-input-thumbnail" required value="https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600" oninput="updateThumbPreview(this.value)" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs focus:border-[#5cb85c] shadow-sm">
                            </div>
                            
                            <div id="thumb-input-pane-file" class="hidden">
                                <div id="thumb-dropzone" class="border-2 border-dashed border-slate-350 rounded p-4 text-center cursor-pointer hover:border-[#5cb85c] transition-colors relative flex flex-col items-center justify-center bg-slate-50/50">
                                    <span class="text-xs text-slate-500 font-bold" id="thumb-upload-text">📁 Click or Drop Thumbnail File</span>
                                    <input type="file" id="thumb-file-input" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                                    <div id="thumb-upload-progress" class="w-full bg-slate-200 h-1 rounded overflow-hidden mt-1.5 hidden">
                                        <div id="thumb-upload-progress-bar" class="bg-[#5cb85c] h-full" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Licensed Deliverable Zip URL</label>
                        <input type="url" name="download_url" id="prod-input-zip" required value="https://example.com/source_code.zip" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs focus:border-[#5cb85c] shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tags (comma-separated)</label>
                        <input type="text" name="tags" id="prod-input-tags" placeholder="php, template, tailwind" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs focus:border-[#5cb85c] shadow-sm">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Release Version</label>
                        <input type="text" name="version" id="prod-input-version" placeholder="1.0.0" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs focus:border-[#5cb85c] shadow-sm">
                    </div>
                </div>

                <!-- Product Licensing Settings -->
                <div class="p-4 bg-slate-50 border border-slate-200 rounded space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-xs font-bold text-slate-800">Require Script License key</h4>
                            <p class="text-[9px] text-slate-455 leading-normal">Generate license keys automatically on purchase using an external License Manager API.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="licensing_enabled" 
                                id="prod-input-licensing-enabled"
                                value="1"
                                class="sr-only peer"
                                onchange="toggleLicensingFields()"
                            >
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer:checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#5cb85c]"></div>
                        </label>
                    </div>
                    <div id="licensing-fields-container" class="grid grid-cols-1 sm:grid-cols-2 gap-4 hidden">
                        <div class="space-y-1 sm:col-span-2">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Extended License Price (Optional)</label>
                            <input type="number" step="0.01" name="extended_price" id="prod-input-extended-price" placeholder="Leave empty if not offering extended license" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-mono focus:border-[#5cb85c] shadow-sm">
                        </div>
                        <div class="sm:col-span-2 text-[10px] text-slate-500 bg-white border p-3 rounded leading-relaxed">
                            💡 <strong>Integration Note:</strong> To validate this license within your script, use the central validation snippet provided by the platform. Check the documentation for the <code>license-verifier.php</code> integration.
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 border-t pt-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-orange-500 uppercase tracking-widest">Discount/Sale Price (Optional)</label>
                        <input type="number" step="0.01" name="discount_price" id="prod-input-discount" placeholder="Leave empty if no sale" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs focus:border-orange-400 shadow-sm">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-orange-500 uppercase tracking-widest">Sale Ends At (Optional)</label>
                        <input type="datetime-local" name="sale_ends_at" id="prod-input-sale-ends" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs focus:border-orange-400 shadow-sm">
                    </div>
                </div>

                <?php if (is_admin()): ?>
                    <div class="space-y-1 border-t pt-4">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Approval Status</label>
                        <select name="status" id="prod-input-status" class="w-full px-4 py-3 rounded border outline-none bg-white text-xs font-bold focus:border-[#5cb85c] shadow-sm">
                            <option value="approved">Approved (Live)</option>
                            <option value="pending">Pending Review</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Screenshots input lists -->
                <div class="space-y-3 p-4 border rounded bg-slate-50/50">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Gallery Screenshots (Max 10)</label>
                        <span class="text-[10px] font-bold text-slate-400 font-mono" id="screenshot-counter">0 / 10</span>
                    </div>
                    
                    <!-- Drag & Drop Zone -->
                    <div id="screenshots-dropzone" class="border-2 border-dashed border-slate-350 rounded-lg p-5 text-center cursor-pointer hover:border-[#5cb85c] transition-colors relative flex flex-col items-center justify-center bg-white">
                        <span class="text-xl mb-1">🖼️</span>
                        <span class="text-xs text-slate-600 font-bold" id="shots-upload-text">Drag & Drop Screenshots here, or click to browse</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Supports PNG, JPG, WEBP, GIF (Automatically optimized)</span>
                        <input type="file" id="screenshots-file-input" accept="image/*" multiple class="absolute inset-0 opacity-0 cursor-pointer">
                    </div>
                    
                    <!-- Manual input block -->
                    <div class="flex gap-2">
                        <input type="url" id="screenshot-manual-url" placeholder="https://example.com/screenshot.jpg" class="flex-1 px-3 py-2 rounded border bg-white outline-none text-xs focus:border-[#5cb85c]">
                        <button type="button" onclick="addManualScreenshotUrl()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded text-xs transition-colors shrink-0 outline-none">Add URL</button>
                    </div>

                    <!-- Previews grid -->
                    <div id="screenshots-preview-grid" class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2 select-none">
                        <!-- Previews injected here -->
                    </div>
                    
                    <!-- Hidden inputs container -->
                    <div id="screenshots-hidden-inputs"></div>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Detailed README Documentation</label>
                    <textarea name="description" id="prod-input-desc" required rows="4" placeholder="Detail installation requirements, setup guides, and library dependencies..." class="w-full px-4 py-3 rounded border outline-none bg-white text-xs leading-relaxed focus:border-[#5cb85c] shadow-sm"></textarea>
                </div>

                <!-- Featured checkbox toggle -->
                <div class="p-4 bg-slate-50 border border-slate-100 rounded flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold text-slate-800">Feature this product</h4>
                        <p class="text-[9px] text-slate-450 leading-normal max-w-xs">Checking this will tag the script as Featured and trigger price/announcement notifications for wishlisters.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="is_featured" 
                            id="prod-input-featured"
                            value="1"
                            class="sr-only peer"
                        >
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer:checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#5cb85c]"></div>
                    </label>
                </div>

                <button type="submit" class="w-full py-4 bg-slate-900 hover:bg-slate-800 text-white font-extrabold uppercase rounded text-xs shadow transition-all">
                    Publish System Asset
                </button>
            </form>
        </div>
    </div>

    <!-- REALTIME SCRIPT INTERFACES -->
    <script>
        // Modal toggling states
        function openLoginModal() {
            document.getElementById('login-auth-portal-modal').classList.remove('hidden');
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeLoginModal();
                closeLightboxModal();
                closeProductModal();
            }
        });

        function closeLoginModal() {
            document.getElementById('login-auth-portal-modal').classList.add('hidden');
        }

        function toggleAuthTab(tab) {
            const login_form = document.getElementById('auth-login-form');
            const register_form = document.getElementById('auth-register-form');
            const forgot_form = document.getElementById('auth-forgot-form');
            const reset_form = document.getElementById('auth-reset-form');
            const login_btn = document.getElementById('tab-login-btn');
            const register_btn = document.getElementById('tab-register-btn');
            const tabs_header = login_btn.parentElement;

            login_form.classList.add('hidden');
            register_form.classList.add('hidden');
            forgot_form.classList.add('hidden');
            reset_form.classList.add('hidden');

            if (tab === 'login') {
                tabs_header.classList.remove('hidden');
                login_form.classList.remove('hidden');
                login_btn.className = "border-b-2 border-[#5cb85c] text-[#5cb85c] outline-none pb-1";
                register_btn.className = "border-b-2 border-transparent text-slate-400 hover:text-slate-800 outline-none pb-1";
            } else if (tab === 'register') {
                tabs_header.classList.remove('hidden');
                register_form.classList.remove('hidden');
                login_btn.className = "border-b-2 border-transparent text-slate-400 hover:text-slate-800 outline-none pb-1";
                register_btn.className = "border-b-2 border-[#5cb85c] text-[#5cb85c] outline-none pb-1";
            } else if (tab === 'forgot') {
                tabs_header.classList.add('hidden');
                forgot_form.classList.remove('hidden');
            } else if (tab === 'reset') {
                tabs_header.classList.add('hidden');
                reset_form.classList.remove('hidden');
            }
        }

        // Auto-show reset form if forgot success is in URL parameters
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('forgot_success') && urlParams.has('email')) {
                openLoginModal();
                toggleAuthTab('reset');
            }
        });

        function toggleCartDrawer() {
            const cart_drawer = document.getElementById('shopping-cart-drawer');
            if (cart_drawer.classList.contains('hidden')) {
                cart_drawer.classList.remove('hidden');
            } else {
                cart_drawer.classList.add('hidden');
            }
        }

        // Simulating Paystack Gateway loaders
        function triggerPaystackCartCheckout() {
            document.getElementById('payment-loader-overlay').classList.remove('hidden');
            setTimeout(() => {
                const checkoutForm = document.createElement('form');
                checkoutForm.method = 'POST';
                checkoutForm.action = 'index.php?action=cart_checkout';
                document.body.appendChild(checkoutForm);
                checkoutForm.submit();
            }, 1500);
        }

        function triggerDirectPaystackCheckout(product) {
            document.getElementById('payment-loader-overlay').classList.remove('hidden');
            setTimeout(() => {
                const directForm = document.createElement('form');
                directForm.method = 'POST';
                directForm.action = 'index.php?action=direct_checkout';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'product_id';
                idInput.value = product.id;
                directForm.appendChild(idInput);
                
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'license_type';
                typeInput.value = document.getElementById('selected-license-type')?.value || 'standard';
                directForm.appendChild(typeInput);
                
                document.body.appendChild(directForm);
                directForm.submit();
            }, 1500);
        }

        // Active Fullscreen Lightbox Variables
        let lightboxImages = [];
        let lightboxActiveIndex = 0;

        function openLightboxModal(product, imagesArray, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            lightboxImages = imagesArray || [];
            lightboxActiveIndex = 0;
            
            if (lightboxImages.length === 0) return;

            document.getElementById('lightbox-title-text').innerText = product.title || "Preview Screenshots";
            document.getElementById('lightbox-category-text').innerText = product.category || "License Asset";
            
            updateLightboxContent();
            document.getElementById('screenshot-lightbox-modal-core').classList.remove('hidden');
        }

        function closeLightboxModal() {
            document.getElementById('screenshot-lightbox-modal-core').classList.add('hidden');
        }

        function updateLightboxContent() {
            document.getElementById('lightbox-main-img-view').src = lightboxImages[lightboxActiveIndex];
            document.getElementById('lightbox-footer-indicator').innerText = (lightboxActiveIndex + 1) + " / " + lightboxImages.length;
        }

        function prevLightboxImage() {
            lightboxActiveIndex = (lightboxActiveIndex === 0) ? (lightboxImages.length - 1) : (lightboxActiveIndex - 1);
            updateLightboxContent();
        }

        // Live Search implementation
        const searchInput = document.getElementById('live-search-input');
        const searchResults = document.getElementById('live-search-results');

        if (searchInput && searchResults) {
            let debounceTimer;
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const query = this.value.trim();
                if (query.length < 2) {
                    searchResults.innerHTML = '';
                    searchResults.classList.add('hidden');
                    return;
                }
                
                debounceTimer = setTimeout(() => {
                    fetch(`index.php?action=live_search&q=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length === 0) {
                                searchResults.innerHTML = '<div class="p-3 text-xs text-gray-400">No results found.</div>';
                            } else {
                                let html = '<div class="divide-y divide-gray-100 text-xs">';
                                data.forEach(item => {
                                    const priceStr = item.formatted_discount ? `<span class="text-[#5cb85c]">${item.formatted_discount}</span> <span class="line-through text-gray-450 text-[10px]">${item.formatted_price}</span>` : `<span class="text-slate-800">${item.formatted_price}</span>`;
                                    html += `
                                        <a href="index.php?page=product&id=${item.id}" class="flex items-center gap-3 p-2.5 hover:bg-slate-50 transition-colors">
                                            <img src="${item.thumbnail}" class="w-8 h-8 rounded object-cover shrink-0">
                                            <div class="flex-1 min-w-0">
                                                <h5 class="font-bold text-slate-900 truncate leading-tight">${item.title}</h5>
                                                <span class="text-[9px] uppercase font-bold text-gray-400 tracking-wider">${item.category}</span>
                                            </div>
                                            <div class="font-mono font-bold shrink-0">${priceStr}</div>
                                        </a>
                                    `;
                                });
                                html += '</div>';
                                searchResults.innerHTML = html;
                            }
                            searchResults.classList.remove('hidden');
                        })
                        .catch(() => {
                            searchResults.innerHTML = '';
                            searchResults.classList.add('hidden');
                        });
                }, 300);
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.add('hidden');
                }
            });
        }

        function nextLightboxImage() {
            lightboxActiveIndex = (lightboxActiveIndex === lightboxImages.length - 1) ? 0 : (lightboxActiveIndex + 1);
            updateLightboxContent();
        }

        // Dynamic Screenshots state
        let uploadedScreenshots = [];

        function setThumbMode(mode) {
            const paneUrl = document.getElementById('thumb-input-pane-url');
            const paneFile = document.getElementById('thumb-input-pane-file');
            const btnUrl = document.getElementById('btn-thumb-mode-url');
            const btnFile = document.getElementById('btn-thumb-mode-file');
            
            if (mode === 'url') {
                if (paneUrl) paneUrl.classList.remove('hidden');
                if (paneFile) paneFile.classList.add('hidden');
                if (btnUrl) btnUrl.className = "px-2.5 py-1 text-[10px] font-bold text-white bg-[#5cb85c] rounded-l border border-[#5cb85c] outline-none";
                if (btnFile) btnFile.className = "px-2.5 py-1 text-[10px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-r border border-gray-300 outline-none";
            } else {
                if (paneUrl) paneUrl.classList.add('hidden');
                if (paneFile) paneFile.classList.remove('hidden');
                if (btnUrl) btnUrl.className = "px-2.5 py-1 text-[10px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-l border border-gray-300 outline-none";
                if (btnFile) btnFile.className = "px-2.5 py-1 text-[10px] font-bold text-white bg-[#5cb85c] rounded-r border border-[#5cb85c] outline-none";
            }
        }

        function updateThumbPreview(url) {
            const previewImg = document.getElementById('prod-thumb-preview-img');
            if (previewImg) {
                previewImg.src = url || 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600';
            }
        }

        // WebP auto-conversion pipeline with Canvas size check
        function convertToWebP(file, quality = 0.85) {
            return new Promise((resolve) => {
                if (!window.FileReader || !window.HTMLCanvasElement) {
                    resolve({ blob: file, name: file.name, ext: file.name.split('.').pop() });
                    return;
                }
                if (file.type === 'image/svg+xml' || file.name.endsWith('.svg')) {
                    resolve({ blob: file, name: file.name, ext: 'svg' });
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.naturalWidth;
                        canvas.height = img.naturalHeight;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        canvas.toBlob((webpBlob) => {
                            if (webpBlob && webpBlob.size < file.size) {
                                const baseName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                                resolve({ blob: webpBlob, name: baseName + '.webp', ext: 'webp' });
                            } else {
                                resolve({ blob: file, name: file.name, ext: file.name.split('.').pop() });
                            }
                        }, 'image/webp', quality);
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        function uploadFileAJAX(fileBlob, fileName, type, onProgress, onSuccess, onError) {
            const formData = new FormData();
            formData.append('file', fileBlob, fileName);
            formData.append('type', type);
            const titleVal = document.getElementById('prod-input-title').value;
            if (titleVal) {
                formData.append('title', titleVal);
            }
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php?action=image_upload_ajax', true);
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    onProgress(percent);
                }
            };
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.status === 'success') {
                            onSuccess(res.url);
                        } else {
                            onError(res.message);
                        }
                    } catch(e) {
                        onError('Malformed server response.');
                    }
                } else {
                    onError('HTTP Error ' + xhr.status);
                }
            };
            xhr.onerror = function() {
                onError('Network transfer failure.');
            };
            xhr.send(formData);
        }

        // Thumbnail file upload listener
        document.addEventListener('DOMContentLoaded', () => {
            const thumbInput = document.getElementById('thumb-file-input');
            const thumbDropzone = document.getElementById('thumb-dropzone');
            const thumbText = document.getElementById('thumb-upload-text');
            const thumbProgress = document.getElementById('thumb-upload-progress');
            const thumbProgressBar = document.getElementById('thumb-upload-progress-bar');
            
            if (thumbDropzone && thumbInput) {
                thumbDropzone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    thumbDropzone.classList.add('border-[#5cb85c]', 'bg-emerald-50/20');
                });
                thumbDropzone.addEventListener('dragleave', () => {
                    thumbDropzone.classList.remove('border-[#5cb85c]', 'bg-emerald-50/20');
                });
                thumbDropzone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    thumbDropzone.classList.remove('border-[#5cb85c]', 'bg-emerald-50/20');
                    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                        handleThumbUpload(e.dataTransfer.files[0]);
                    }
                });
                thumbInput.addEventListener('change', () => {
                    if (thumbInput.files && thumbInput.files[0]) {
                        handleThumbUpload(thumbInput.files[0]);
                    }
                });
            }

            function handleThumbUpload(file) {
                if (!file.type.startsWith('image/')) {
                    alert('Invalid file format. Thumbnail must be an image.');
                    return;
                }
                thumbText.innerText = 'Converting & Uploading...';
                thumbProgress.classList.remove('hidden');
                
                convertToWebP(file).then(({ blob, name }) => {
                    uploadFileAJAX(blob, name, 'thumbnail', 
                        (percent) => {
                            thumbProgressBar.style.width = percent + '%';
                        },
                        (url) => {
                            thumbText.innerText = '📁 Upload Complete!';
                            thumbProgress.classList.add('hidden');
                            document.getElementById('prod-input-thumbnail').value = url;
                            updateThumbPreview(url);
                        },
                        (err) => {
                            thumbText.innerText = '📁 Click or Drop Thumbnail File';
                            thumbProgress.classList.add('hidden');
                            alert('Upload failed: ' + err);
                        }
                    );
                });
            }

            // Screenshots Gallery listeners
            const shotsInput = document.getElementById('screenshots-file-input');
            const shotsDropzone = document.getElementById('screenshots-dropzone');
            
            if (shotsDropzone && shotsInput) {
                shotsDropzone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    shotsDropzone.classList.add('border-[#5cb85c]', 'bg-emerald-50/20');
                });
                shotsDropzone.addEventListener('dragleave', () => {
                    shotsDropzone.classList.remove('border-[#5cb85c]', 'bg-emerald-50/20');
                });
                shotsDropzone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    shotsDropzone.classList.remove('border-[#5cb85c]', 'bg-emerald-50/20');
                    if (e.dataTransfer.files) {
                        handleScreenshotsUpload(e.dataTransfer.files);
                    }
                });
                shotsInput.addEventListener('change', () => {
                    if (shotsInput.files) {
                        handleScreenshotsUpload(shotsInput.files);
                    }
                });
            }

            function handleScreenshotsUpload(files) {
                const maxAllowed = 10;
                
                for (let i = 0; i < files.length; i++) {
                    let currentCount = uploadedScreenshots.length;
                    const file = files[i];
                    if (!file.type.startsWith('image/')) {
                        alert('File ' + file.name + ' is not a valid image.');
                        continue;
                    }
                    if (currentCount >= maxAllowed) {
                        alert('Maximum limit of 10 screenshots reached.');
                        break;
                    }
                    
                    // Allow sellers to see the thumbnail of the images as they drop the images (using createObjectURL)
                    const localUrl = URL.createObjectURL(file);
                    const fileIndex = uploadedScreenshots.length;
                    
                    const newShot = {
                        url: localUrl,
                        originalUrl: '',
                        isUploading: true,
                        progress: 0,
                        name: file.name
                    };
                    
                    uploadedScreenshots.push(newShot);
                    
                    renderScreenshotsGrid();
                    
                    // Upload closure
                    (function(index, fileObj, urlLocal) {
                        convertToWebP(fileObj).then(({ blob, name }) => {
                            uploadFileAJAX(blob, name, 'screenshot',
                                (percent) => {
                                    if (uploadedScreenshots[index]) {
                                        uploadedScreenshots[index].progress = percent;
                                        updateScreenshotProgressUI(index, percent);
                                    }
                                },
                                (url) => {
                                    if (uploadedScreenshots[index]) {
                                        uploadedScreenshots[index].url = url;
                                        uploadedScreenshots[index].originalUrl = url;
                                        uploadedScreenshots[index].isUploading = false;
                                        renderScreenshotsGrid();
                                    }
                                    URL.revokeObjectURL(urlLocal);
                                },
                                (err) => {
                                    alert('Failed to upload ' + fileObj.name + ': ' + err);
                                    removeScreenshot(index);
                                    URL.revokeObjectURL(urlLocal);
                                }
                            );
                        });
                    })(fileIndex, file, localUrl);
                }
            }
            // Listen to product title changes to dynamically update screenshots alt text
            const titleInput = document.getElementById('prod-input-title');
            if (titleInput) {
                titleInput.addEventListener('input', () => {
                    renderScreenshotsGrid();
                });
            }
        });

        function renderScreenshotsGrid() {
            const grid = document.getElementById('screenshots-preview-grid');
            const hiddenInputs = document.getElementById('screenshots-hidden-inputs');
            const counter = document.getElementById('screenshot-counter');
            
            if (!grid) return;
            grid.innerHTML = '';
            hiddenInputs.innerHTML = '';
            
            counter.innerText = uploadedScreenshots.length + " / 10";
            
            const titleInput = document.getElementById('prod-input-title');
            const titleVal = (titleInput && titleInput.value) ? titleInput.value.trim() : 'Product';
            
            uploadedScreenshots.forEach((shot, index) => {
                const itemDiv = document.createElement('div');
                itemDiv.className = "relative aspect-video rounded border overflow-hidden bg-slate-100 group border-gray-200 shadow-sm";
                
                const altText = titleVal + " - screenshot " + (index + 1);
                
                let progressOverlay = '';
                if (shot.isUploading) {
                    progressOverlay = `
                        <div class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center text-white text-[9px] font-bold p-2 z-10" id="shot-progress-${index}">
                            <span>Compressing...</span>
                            <div class="w-full bg-white/20 h-1 rounded overflow-hidden mt-1">
                                <div class="bg-[#5cb85c] h-full transition-all duration-300" id="shot-progress-bar-${index}" style="width: ${shot.progress}%"></div>
                            </div>
                        </div>
                    `;
                }
                
                itemDiv.innerHTML = `
                    <img src="${shot.url}" class="w-full h-full object-cover" alt="${altText}" referrerpolicy="no-referrer">
                    ${progressOverlay}
                    <button type="button" onclick="removeScreenshot(${index})" class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center shadow z-20 outline-none hover:scale-105 transition-transform" title="Remove image">✕</button>
                `;
                grid.appendChild(itemDiv);
                
                if (!shot.isUploading && shot.originalUrl) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'screenshots[]';
                    hidden.value = shot.originalUrl;
                    hiddenInputs.appendChild(hidden);
                }
            });
        }

        function updateScreenshotProgressUI(index, percent) {
            const bar = document.getElementById(`shot-progress-bar-${index}`);
            if (bar) {
                bar.style.width = percent + '%';
            }
        }

        function removeScreenshot(index) {
            uploadedScreenshots.splice(index, 1);
            renderScreenshotsGrid();
        }

        function addManualScreenshotUrl() {
            const input = document.getElementById('screenshot-manual-url');
            if (!input) return;
            const urlVal = input.value.trim();
            if (!urlVal) return;
            
            if (uploadedScreenshots.length >= 10) {
                alert('Maximum limit of 10 screenshots reached.');
                return;
            }
            
            uploadedScreenshots.push({
                url: urlVal,
                originalUrl: urlVal,
                isUploading: false,
                progress: 100,
                name: 'manual-url'
            });
            
            input.value = '';
            renderScreenshotsGrid();
        }

        // Product Manager Modal state
        function openProductModal(prodData) {
            const titleEl = document.getElementById('product-modal-title');
            const idInput = document.getElementById('prod-input-id');
            const titleInput = document.getElementById('prod-input-title');
            const priceInput = document.getElementById('prod-input-price');
            const categoryInput = document.getElementById('prod-input-category');
            const demoInput = document.getElementById('prod-input-demo');
            const descInput = document.getElementById('prod-input-desc');
            const thumbnailInput = document.getElementById('prod-input-thumbnail');
            const zipInput = document.getElementById('prod-input-zip');
            const featuredInput = document.getElementById('prod-input-featured');
            
            const tagsInput = document.getElementById('prod-input-tags');
            const versionInput = document.getElementById('prod-input-version');
            const discountInput = document.getElementById('prod-input-discount');
            const saleEndsInput = document.getElementById('prod-input-sale-ends');
            const statusInput = document.getElementById('prod-input-status');

            const licensingEnabledInput = document.getElementById('prod-input-licensing-enabled');


            uploadedScreenshots = [];

            if (prodData) {
                titleEl.innerText = "Edit Code script";
                idInput.value = prodData.id;
                titleInput.value = prodData.title;
                priceInput.value = prodData.price;
                categoryInput.value = prodData.category;
                demoInput.value = prodData.live_demo_url || '';
                descInput.value = prodData.description;
                thumbnailInput.value = prodData.thumbnail;
                zipInput.value = prodData.download_url;
                
                tagsInput.value = prodData.tags || '';
                versionInput.value = prodData.version || '1.0.0';
                discountInput.value = prodData.discount_price || '';
                saleEndsInput.value = prodData.sale_ends_at ? prodData.sale_ends_at.substring(0, 16) : '';
                if (statusInput) {
                    statusInput.value = prodData.status || 'pending';
                }

                if (featuredInput) {
                    featuredInput.checked = (prodData.is_featured == 1);
                }

                if (licensingEnabledInput) {
                    licensingEnabledInput.checked = (prodData.licensing_enabled == 1);
                }

                const extendedPriceInput = document.getElementById('prod-input-extended-price');
                if (extendedPriceInput) {
                    extendedPriceInput.value = prodData.extended_price || '';
                }
                
                let shots = [];
                try {
                    shots = JSON.parse(prodData.preview_images) || [];
                } catch(e) {}
                shots.forEach(s => {
                    if (s) {
                        uploadedScreenshots.push({
                            url: s,
                            originalUrl: s,
                            isUploading: false,
                            progress: 100,
                            name: 'screenshot-saved'
                        });
                    }
                });
                
                setThumbMode('url');
                updateThumbPreview(prodData.thumbnail);
            } else {
                titleEl.innerText = "Publish Code script";
                idInput.value = '0';
                titleInput.value = '';
                priceInput.value = '';
                demoInput.value = '';
                descInput.value = '';
                
                tagsInput.value = '';
                versionInput.value = '1.0.0';
                discountInput.value = '';
                saleEndsInput.value = '';
                if (statusInput) {
                    statusInput.value = 'approved';
                }

                if (featuredInput) {
                    featuredInput.checked = false;
                }
                if (licensingEnabledInput) {
                    licensingEnabledInput.checked = false;
                }

                const extendedPriceInput = document.getElementById('prod-input-extended-price');
                if (extendedPriceInput) {
                    extendedPriceInput.value = '';
                }
                thumbnailInput.value = "https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600";
                zipInput.value = "https://example.com/source_code.zip";
                
                setThumbMode('url');
                updateThumbPreview("https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&q=80&w=600");
            }
            
            toggleLicensingFields();
            renderScreenshotsGrid();
            document.getElementById('product-studio-modal').classList.remove('hidden');
        }

        function closeProductModal() {
            document.getElementById('product-studio-modal').classList.add('hidden');
        }

        function toggleLicensingFields() {
            const chk = document.getElementById('prod-input-licensing-enabled');
            const container = document.getElementById('licensing-fields-container');
            if (chk && container) {
                if (chk.checked) {
                    container.classList.remove('hidden');
                } else {
                    container.classList.add('hidden');
                }
            }
        }


    </script>

    <!-- Footer Custom Injected Code -->
    <?php echo get_setting('seo_footer_injection'); ?>

</body>
</html>
