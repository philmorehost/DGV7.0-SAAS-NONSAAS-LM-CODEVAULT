<?php
session_start();

if (file_exists(__DIR__ . '/../core/config.php')) {
    die("System already installed. Please delete the install folder.");
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Check PHP version
$php_version = phpversion();
$php_ok = version_compare($php_version, '8.2.0', '>=');

// Check Extensions
$extensions = ['curl', 'pdo', 'mbstring'];
$missing_extensions = [];
foreach ($extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}
$ext_ok = empty($missing_extensions);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $license_key = $_POST['license_key'] ?? '';
        $domain = $_SERVER['HTTP_HOST'];
        
        if (!$php_ok || !$ext_ok) {
            $error = "Please resolve server requirements before proceeding.";
        } else {
            // Call License API
            $ch = curl_init('https://manager.pmhserver.name.ng/api.php');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['key' => $license_key, 'domain' => $domain]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            if ($result && isset($result['status']) && $result['status'] == 1) {
                $_SESSION['license_key'] = $license_key;
                header('Location: ?step=2');
                exit;
            } else {
                // If API fails locally or returns false, for development we can mock success if key is 'DEV'
                if ($license_key === 'DEV') {
                    $_SESSION['license_key'] = $license_key;
                    header('Location: ?step=2');
                    exit;
                }
                $error = "Invalid license key or domain. Message: " . ($result['message'] ?? 'Connection error');
            }
        }
    } elseif ($step === 2) {
        $db_host = $_POST['db_host'] ?? '';
        $db_name = $_POST['db_name'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Read and execute schema.sql
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            // Basic split to avoid PDO executing multiple statements at once if it throws error
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            foreach($statements as $stmt) {
                if(!empty($stmt)) {
                    $pdo->exec($stmt);
                }
            }
            
            $_SESSION['db'] = [
                'host' => $db_host,
                'name' => $db_name,
                'user' => $db_user,
                'pass' => $db_pass
            ];
            
            header('Location: ?step=3');
            exit;
        } catch (PDOException $e) {
            $error = "Database Connection Failed: " . $e->getMessage();
        }
    } elseif ($step === 3) {
        $admin_email = $_POST['admin_email'] ?? '';
        $admin_pass = $_POST['admin_pass'] ?? '';
        
        if (empty($admin_email) || empty($admin_pass)) {
            $error = "Please fill all fields.";
        } else {
            try {
                $db = $_SESSION['db'];
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8", $db['user'], $db['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, role) VALUES ('System', 'Admin', ?, ?, 'admin')");
                $stmt->execute([$admin_email, $hash]);
                
                // Create config.php
                $config_content = "<?php\n" .
                    "define('DB_HOST', '{$db['host']}');\n" .
                    "define('DB_NAME', '{$db['name']}');\n" .
                    "define('DB_USER', '{$db['user']}');\n" .
                    "define('DB_PASS', '{$db['pass']}');\n" .
                    "define('LICENSE_KEY', '{$_SESSION['license_key']}');\n";
                
                file_put_contents(__DIR__ . '/../core/config.php', $config_content);
                
                header('Location: ?step=4');
                exit;
            } catch (Exception $e) {
                $error = "Error creating admin: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXAM-HUB Installer - Step <?= $step ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="max-w-xl w-full bg-white rounded-lg shadow-xl overflow-hidden">
        <div class="bg-blue-600 p-6 text-center text-white">
            <h1 class="text-2xl font-bold">EXAM-HUB Installer</h1>
            <p class="text-blue-200">Step <?= $step ?> of 4</p>
        </div>
        
        <div class="p-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <h2 class="text-xl font-semibold mb-4">System Check & License Verification</h2>
                
                <div class="mb-6 space-y-2">
                    <div class="flex justify-between items-center p-3 <?= $php_ok ? 'bg-green-50' : 'bg-red-50' ?> rounded">
                        <span>PHP Version (>= 8.2)</span>
                        <span class="<?= $php_ok ? 'text-green-600' : 'text-red-600' ?> font-bold">
                            <?= $php_ok ? 'OK ('.$php_version.')' : 'Failed ('.$php_version.')' ?>
                        </span>
                    </div>
                    <?php foreach ($extensions as $ext): ?>
                        <?php $has = extension_loaded($ext); ?>
                        <div class="flex justify-between items-center p-3 <?= $has ? 'bg-green-50' : 'bg-red-50' ?> rounded">
                            <span>PHP Extension: <?= $ext ?></span>
                            <span class="<?= $has ? 'text-green-600' : 'text-red-600' ?> font-bold">
                                <?= $has ? 'Installed' : 'Missing' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">License Key</label>
                        <input type="text" name="license_key" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Domain: <?= htmlspecialchars($_SERVER['HTTP_HOST']) ?></p>
                    </div>
                    <button type="submit" <?= (!$php_ok || !$ext_ok) ? 'disabled' : '' ?> class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 disabled:opacity-50">
                        Verify & Continue
                    </button>
                </form>

            <?php elseif ($step === 2): ?>
                <h2 class="text-xl font-semibold mb-4">Database Configuration</h2>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Database Host</label>
                        <input type="text" name="db_host" value="localhost" required class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Database Name</label>
                        <input type="text" name="db_name" required class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Database User</label>
                        <input type="text" name="db_user" required class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Database Password</label>
                        <input type="password" name="db_pass" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                        Install Database
                    </button>
                </form>

            <?php elseif ($step === 3): ?>
                <h2 class="text-xl font-semibold mb-4">Admin Account Setup</h2>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Admin Email</label>
                        <input type="email" name="admin_email" required class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Admin Password</label>
                        <input type="password" name="admin_pass" required class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
                        Create Admin
                    </button>
                </form>

            <?php elseif ($step === 4): ?>
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-6">
                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="text-2xl font-bold mb-2">Installation Complete!</h2>
                    <p class="text-gray-600 mb-6">EXAM-HUB has been successfully installed. Please delete the <code>install</code> directory for security.</p>
                    
                    <a href="/admin/login" class="inline-block bg-blue-600 text-white font-bold py-2 px-6 rounded hover:bg-blue-700">
                        Login as Admin
                    </a>
                    <a href="/" class="inline-block mt-4 text-blue-600 hover:underline block">
                        Go to Homepage
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
