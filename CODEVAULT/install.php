<?php
/**
 * CodeVault Installer Script
 * Fully-featured multi-stage web installer.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$config_file = __DIR__ . '/config.php';

// If installation is already completed, allow reset via URL or prevent re-run
if (file_exists($config_file) && !isset($_GET['reset'])) {
    // Already installed, redirect to index
    header('Location: index.php');
    exit;
}

$stage = isset($_GET['stage']) ? intval($_GET['stage']) : 1;
$error = '';
$success = '';

// Stage transitions & submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['stage1_submit'])) {
        // Version and extension checks succeeded
        header('Location: install.php?stage=2');
        exit;
    }
    
    if (isset($_POST['stage2_submit'])) {
        $db_type = $_POST['db_type'];
        
        if ($db_type === 'mysql') {
            $db_host = trim($_POST['db_host']);
            $db_name = trim($_POST['db_name']);
            $db_user = trim($_POST['db_user']);
            $db_pass = trim($_POST['db_pass']);
            
            if (empty($db_host) || empty($db_name) || empty($db_user)) {
                $error = "Please fill in all required MySQL credentials.";
            } else {
                try {
                    // Try to connect to MySQL
                    $dsn = "mysql:host=$db_host;charset=utf8mb4";
                    $conn = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    
                    // Create Database if not exists
                    $conn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $conn->exec("USE `$db_name`");
                    
                    // Connected successfully! Save to session first
                    $_SESSION['db_config'] = [
                        'type' => 'mysql',
                        'host' => $db_host,
                        'name' => $db_name,
                        'user' => $db_user,
                        'pass' => $db_pass
                    ];
                    
                    // Install schema
                    $schema_sql = file_get_contents(__DIR__ . '/database.sql');
                    if ($schema_sql === false) {
                        $error = "Could not find /php/database.sql file to import the database schema.";
                    } else {
                        $conn->exec($schema_sql);
                        $success = "Database created and schema imported successfully!";
                        header('Location: install.php?stage=3');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = "Database Connection Failed: " . $e->getMessage();
                }
            }
        } else {
            // SQLite configuration
            $sqlite_path = __DIR__ . '/marketplace.db';
            try {
                // Initialize SQLite
                $conn = new PDO("sqlite:" . $sqlite_path);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $_SESSION['db_config'] = [
                    'type' => 'sqlite',
                    'path' => $sqlite_path
                ];
                
                // Read and execute schema
                $schema_sql = file_get_contents(__DIR__ . '/database.sql');
                if ($schema_sql === false) {
                    $error = "Could not find /php/database.sql file.";
                } else {
                    // SQLite requires executing statements individually, but for PDO SQLite, we can try exec.
                    // Let's strip MySQL specific parameters from schema (like ON DUPLICATE KEY or AUTO_INCREMENT with INT)
                    // But our schema is SQLite safe as it uses IF NOT EXISTS with flexible types.
                    // Replace standard MySQL options if we encounter exceptions
                    try {
                        $conn->exec($schema_sql);
                    } catch (Exception $e1) {
                        // Attempt SQLite direct exec
                        $queries = explode(';', $schema_sql);
                        foreach ($queries as $q) {
                            $q = trim($q);
                            if (!empty($q)) {
                                $conn->exec($q);
                            }
                        }
                    }
                    
                    header('Location: install.php?stage=3');
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Failed to open or write to SQLite database: " . $e->getMessage() . ". Ensure the /php directory is writable.";
            }
        }
    }
    
    if (isset($_POST['stage3_submit'])) {
        $admin_name = trim($_POST['admin_name']);
        $admin_email = trim($_POST['admin_email']);
        $admin_pass = trim($_POST['admin_pass']);
        $admin_pass_confirm = trim($_POST['admin_pass_confirm']);
        
        if (empty($admin_name) || empty($admin_email) || empty($admin_pass)) {
            $error = "All admin login details are required.";
        } else if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else if ($admin_pass !== $admin_pass_confirm) {
            $error = "Passwords do not match.";
        } else {
            // Retrieve DB config from session
            $db_config = isset($_SESSION['db_config']) ? $_SESSION['db_config'] : null;
            if (!$db_config) {
                $error = "Database configuration is missing. Please go back to Stage 2.";
            } else {
                try {
                    // Connect to db to insert admin user
                    if ($db_config['type'] === 'mysql') {
                        $dsn = "mysql:host=" . $db_config['host'] . ";dbname=" . $db_config['name'] . ";charset=utf8mb4";
                        $conn = new PDO($dsn, $db_config['user'], $db_config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    } else {
                        $conn = new PDO("sqlite:" . $db_config['path'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    }
                    
                    // Hash password with bcrypt
                    $hashed_pass = password_hash($admin_pass, PASSWORD_BCRYPT, ['cost' => 10]);
                    
                    // Insert or Update existing admin@codevault.com seed user
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->execute([$admin_email]);
                    $existing_admin = $check_stmt->fetch();
                    
                    if ($existing_admin) {
                        $up_stmt = $conn->prepare("UPDATE users SET password = ?, name = ?, role = 'admin', is_verified = 1 WHERE id = ?");
                        $up_stmt->execute([$hashed_pass, $admin_name, $existing_admin['id']]);
                        $admin_id = $existing_admin['id'];
                    } else {
                        $ins_stmt = $conn->prepare("INSERT INTO users (email, password, name, role, is_verified) VALUES (?, ?, ?, 'admin', 1)");
                        $ins_stmt->execute([$admin_email, $hashed_pass, $admin_name]);
                        $admin_id = $conn->lastInsertId();
                    }
                    
                    // Ensure admin has a wallet
                    $wallet_stmt = $conn->prepare("INSERT OR IGNORE INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.0, 0.0)");
                    try {
                        $wallet_stmt->execute([$admin_id]);
                    } catch (Exception $e) {
                        // For MySQL
                        $wallet_stmt_mysql = $conn->prepare("INSERT IGNORE INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.0, 0.0)");
                        $wallet_stmt_mysql->execute([$admin_id]);
                    }
                    
                    // Now, write config.php file
                    $config_content = "<?php\n";
                    $config_content .= "// CodeVault Generated Database Configuration File\n\n";
                    if ($db_config['type'] === 'mysql') {
                        $config_content .= "define('DB_TYPE', 'mysql');\n";
                        $config_content .= "define('DB_HOST', '" . addslashes($db_config['host']) . "');\n";
                        $config_content .= "define('DB_NAME', '" . addslashes($db_config['name']) . "');\n";
                        $config_content .= "define('DB_USER', '" . addslashes($db_config['user']) . "');\n";
                        $config_content .= "define('DB_PASS', '" . addslashes($db_config['pass']) . "');\n";
                    } else {
                        $config_content .= "define('DB_TYPE', 'sqlite');\n";
                        $config_content .= "define('DB_SQLITE_PATH', '" . addslashes($db_config['path']) . "');\n";
                    }
                    $config_content .= "\n// Base settings\ndefine('PLATFORM_NAME', 'CodeVault');\n";
                    
                    if (file_put_contents($config_file, $config_content) === false) {
                        $error = "Failed to write config.php. Please ensure directory permissions are writable.";
                    } else {
                        // Clear database configuration session
                        unset($_SESSION['db_config']);
                        $_SESSION['admin_user_details'] = [
                            'name' => $admin_name,
                            'email' => $admin_email
                        ];
                        header('Location: install.php?stage=4');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = "Failed to establish database or register admin account: " . $e->getMessage();
                }
            }
        }
    }
}

// Stage 1 checks
$php_version = phpversion();
$php_version_ok = version_compare($php_version, '7.4.0', '>=');
$extensions = [
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'openssl' => extension_loaded('openssl'),
    'mbstring' => extension_loaded('mbstring'),
    'session' => extension_loaded('session')
];
$all_extensions_ok = $extensions['pdo'] && ($extensions['pdo_mysql'] || $extensions['pdo_sqlite']) && $extensions['openssl'] && $extensions['session'];
$writable_dir = is_writable(__DIR__);
$stage1_ready = $php_version_ok && $all_extensions_ok && $writable_dir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeVault Installation Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="bg-[#fafbfd] text-slate-800 min-h-screen flex flex-col justify-between">
    
    <!-- Navbar -->
    <header class="bg-white border-b border-gray-100 py-5 px-6 shadow-sm">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white font-extrabold text-lg shadow-md shadow-emerald-500/25">
                    C
                </div>
                <div>
                    <h1 class="font-extrabold text-lg tracking-tight text-gray-900">CodeVault</h1>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Self-Hosted Setup</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold px-3 py-1 bg-slate-100 text-slate-600 rounded-lg">PHP v<?php echo htmlspecialchars(explode('-', $php_version)[0]); ?></span>
            </div>
        </div>
    </header>

    <!-- Content -->
    <main class="max-w-3xl w-full mx-auto px-6 py-12 flex-1 flex flex-col justify-center">
        
        <!-- Step Indicators -->
        <div class="mb-10">
            <div class="flex items-center justify-between relative">
                <!-- Progress Line -->
                <div class="absolute h-1 bg-gray-200 left-0 right-0 top-1/2 -translate-y-1/2 z-0 rounded-full"></div>
                <div class="absolute h-1 bg-emerald-500 left-0 top-1/2 -translate-y-1/2 z-0 rounded-full transition-all duration-500" style="width: <?php echo (($stage - 1) / 3) * 100; ?>%"></div>

                <!-- Step 1 -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm border-2 <?php echo $stage >= 1 ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-white border-gray-200 text-slate-400'; ?>">1</div>
                    <span class="text-xs font-bold mt-2 <?php echo $stage >= 1 ? 'text-slate-900' : 'text-slate-400'; ?>">Environment</span>
                </div>
                <!-- Step 2 -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm border-2 <?php echo $stage >= 2 ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-white border-gray-200 text-slate-400'; ?>">2</div>
                    <span class="text-xs font-bold mt-2 <?php echo $stage >= 2 ? 'text-slate-900' : 'text-slate-400'; ?>">Database</span>
                </div>
                <!-- Step 3 -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm border-2 <?php echo $stage >= 3 ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-white border-gray-200 text-slate-400'; ?>">3</div>
                    <span class="text-xs font-bold mt-2 <?php echo $stage >= 3 ? 'text-slate-900' : 'text-slate-400'; ?>">Administrator</span>
                </div>
                <!-- Step 4 -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm border-2 <?php echo $stage >= 4 ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/20' : 'bg-white border-gray-200 text-slate-400'; ?>">4</div>
                    <span class="text-xs font-bold mt-2 <?php echo $stage >= 4 ? 'text-slate-900' : 'text-slate-400'; ?>">Completed</span>
                </div>
            </div>
        </div>

        <!-- Alert messages -->
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-2xl flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h4 class="font-bold text-red-900 text-sm">Action Required</h4>
                    <p class="text-xs text-red-700 mt-1 leading-relaxed"><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Wizard Card -->
        <div class="bg-white border border-gray-100 rounded-[2.5rem] shadow-xl shadow-slate-100 p-8 md:p-12">
            
            <?php if ($stage === 1): ?>
                <!-- Stage 1: Welcome & Server Requirements -->
                <div>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight">Welcome to CodeVault Installer</h2>
                    <p class="text-slate-500 text-sm mt-2 leading-relaxed">Let's verify that your server is prepared to run CodeVault Marketplace. We will examine the PHP version, active database extensions, and folder system permissions.</p>
                    
                    <div class="mt-8 space-y-4">
                        <!-- PHP Version check -->
                        <div class="p-4 bg-slate-50 rounded-2xl flex items-center justify-between border border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl <?php echo $php_version_ok ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'; ?> flex items-center justify-center text-sm font-bold">
                                    PHP
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm text-gray-900">PHP Version Check</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Required: v7.4.0 or greater. Your version: v<?php echo htmlspecialchars(explode('-', phpversion())[0]); ?></p>
                                </div>
                            </div>
                            <div>
                                <?php if ($php_version_ok): ?>
                                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">✓ Perfect</span>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-red-600 bg-red-50 px-2.5 py-1 rounded-full">✗ Outdated</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- System Directory Permissions -->
                        <div class="p-4 bg-slate-50 rounded-2xl flex items-center justify-between border border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl <?php echo $writable_dir ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'; ?> flex items-center justify-center text-sm">
                                    📁
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm text-gray-900">Directory Write Privileges</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Wizard needs to write config.php and save files inside: <code class="font-mono text-[10px]"><?php echo htmlspecialchars(basename(__DIR__)); ?>/</code></p>
                                </div>
                            </div>
                            <div>
                                <?php if ($writable_dir): ?>
                                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">✓ Writable</span>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-red-600 bg-red-50 px-2.5 py-1 rounded-full">✗ Writable Required</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Extensions List check -->
                        <div class="p-5 border border-slate-100 rounded-2xl">
                            <h4 class="font-bold text-xs text-slate-400 uppercase tracking-widest mb-3">Required PHP Extensions</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <?php foreach($extensions as $name => $loaded): ?>
                                    <div class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100 text-xs font-semibold">
                                        <span class="text-sm"><?php echo $loaded ? '🟢' : '🔴'; ?></span>
                                        <span class="font-mono text-slate-700"><?php echo $name; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="mt-8 flex justify-end">
                        <button 
                            type="submit" 
                            name="stage1_submit" 
                            <?php echo !$stage1_ready ? 'disabled' : ''; ?> 
                            class="px-6 py-3.5 bg-emerald-500 text-white hover:bg-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed font-bold rounded-2xl text-sm transition-all shadow-lg hover:shadow-emerald-500/25 flex items-center gap-2"
                        >
                            Next: Configure Database 
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </form>
                </div>

            <?php elseif ($stage === 2): ?>
                <!-- Stage 2: Database and Schema Installation -->
                <div>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight">Stage 2: Database Setup</h2>
                    <p class="text-slate-500 text-sm mt-2 leading-relaxed">Establish a PDO database connection. CodeVault supports both MySQL (preferred for production cPanel) and SQLite (great for quick deployment without configuration).</p>
                    
                    <form method="POST" class="mt-8 space-y-6">
                        
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Database Type</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="border-2 border-emerald-500 bg-emerald-50/20 p-4 rounded-2xl cursor-pointer flex items-center justify-between select-none" id="label_sqlite">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl">💾</span>
                                        <div>
                                            <p class="font-bold text-sm text-slate-900">SQLite</p>
                                            <p class="text-[10px] text-slate-500 mt-0.5">Automatic Single-File</p>
                                        </div>
                                    </div>
                                    <input type="radio" name="db_type" value="sqlite" checked onclick="toggleDBFields('sqlite')" class="accent-emerald-500">
                                </label>

                                <label class="border border-gray-200 hover:border-emerald-500 p-4 rounded-2xl cursor-pointer flex items-center justify-between select-none" id="label_mysql">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl">🌐</span>
                                        <div>
                                            <p class="font-bold text-sm text-slate-700">MySQL / MariaDB</p>
                                            <p class="text-[10px] text-slate-500 mt-0.5">High Performance SQL</p>
                                        </div>
                                    </div>
                                    <input type="radio" name="db_type" value="mysql" onclick="toggleDBFields('mysql')" class="accent-emerald-500">
                                </label>
                            </div>
                        </div>

                        <!-- MySQL Fields -->
                        <div id="mysql_fields" class="hidden space-y-4 border border-slate-100 p-6 rounded-3xl bg-slate-50/50">
                            <h3 class="font-bold text-xs text-slate-400 uppercase tracking-widest mb-2">MySQL Server Credentials</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-700">Database Host</label>
                                    <input type="text" name="db_host" value="localhost" placeholder="localhost" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 bg-white outline-none text-sm transition-all shadow-sm">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-700">Database Name</label>
                                    <input type="text" name="db_name" value="codevault_db" placeholder="codevault_db" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 bg-white outline-none text-sm transition-all shadow-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-700">DB Username</label>
                                    <input type="text" name="db_user" value="root" placeholder="root" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 bg-white outline-none text-sm transition-all shadow-sm">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-slate-700">DB Password</label>
                                    <input type="password" name="db_pass" placeholder="Database Password" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 bg-white outline-none text-sm transition-all shadow-sm">
                                </div>
                            </div>
                        </div>

                        <!-- SQLite Info -->
                        <div id="sqlite_info" class="p-5 bg-emerald-50/20 border border-emerald-100 rounded-3xl">
                            <p class="text-xs text-emerald-800 leading-relaxed">✓ Selecting <strong>SQLite</strong> will automatically seed and install the relational schema into a robust local file named <code class="font-mono bg-white border border-emerald-100 px-1 py-0.5 rounded text-[10px]">marketplace.db</code> in this directory. There is absolutely no external configuration needed.</p>
                        </div>

                        <div class="flex justify-between items-center pt-4">
                            <a href="install.php?stage=1" class="text-xs font-bold text-slate-500 hover:text-slate-900 transition-colors">← Back to System Check</a>
                            <button 
                                type="submit" 
                                name="stage2_submit" 
                                class="px-6 py-3.5 bg-emerald-500 text-white hover:bg-emerald-600 font-bold rounded-2xl text-sm transition-all shadow-lg hover:shadow-emerald-500/25 flex items-center gap-2"
                            >
                                Install Schema & Move Next 
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                    function toggleDBFields(type) {
                        const sqlite_label = document.getElementById('label_sqlite');
                        const mysql_label = document.getElementById('label_mysql');
                        const mysql_fields = document.getElementById('mysql_fields');
                        const sqlite_info = document.getElementById('sqlite_info');

                        if (type === 'mysql') {
                            mysql_fields.classList.remove('hidden');
                            sqlite_info.classList.add('hidden');
                            mysql_label.className = "border-2 border-emerald-500 bg-emerald-50/20 p-4 rounded-2xl cursor-pointer flex items-center justify-between select-none";
                            sqlite_label.className = "border border-gray-200 hover:border-emerald-500 p-4 rounded-2xl cursor-pointer flex items-center justify-between select-none";
                        } else {
                            mysql_fields.classList.add('hidden');
                            sqlite_info.classList.remove('hidden');
                            sqlite_label.className = "border-2 border-emerald-500 bg-emerald-50/20 p-4 rounded-2xl cursor-pointer flex items-center justify-between select-none";
                            mysql_label.className = "border border-gray-200 hover:border-emerald-500 p-4 rounded-2xl cursor-pointer flex items-center justify-between select-none";
                        }
                    }
                </script>

            <?php elseif ($stage === 3): ?>
                <!-- Stage 3: Admin Login details Setup -->
                <div>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight">Stage 3: Create Administrator</h2>
                    <p class="text-slate-500 text-sm mt-2 leading-relaxed">Establish the main System Administrator login credentials. This account holds full rights to control platform currencies, approve payouts, verify sellers, and moderate forums.</p>
                    
                    <form method="POST" class="mt-8 space-y-4">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-700">Administrator Name</label>
                            <input type="text" name="admin_name" value="Admin Master" required placeholder="e.g. John Doe" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 bg-white outline-none text-sm transition-all shadow-sm">
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-700">Admin Email Address</label>
                            <input type="email" name="admin_email" value="admin@codevault.com" required placeholder="admin@codevault.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 bg-white outline-none text-sm transition-all shadow-sm">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700">Secret Password</label>
                                <input type="password" name="admin_pass" value="admin123" required placeholder="admin123" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 bg-white outline-none text-sm transition-all shadow-sm">
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-bold text-slate-700">Confirm Password</label>
                                <input type="password" name="admin_pass_confirm" value="admin123" required placeholder="Re-enter password" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-emerald-500 bg-white outline-none text-sm transition-all shadow-sm">
                            </div>
                        </div>

                        <div class="flex justify-between items-center pt-6">
                            <span class="text-xs text-slate-400 font-semibold">Note: Default password is set to <strong>admin123</strong></span>
                            <button 
                                type="submit" 
                                name="stage3_submit" 
                                class="px-6 py-3.5 bg-emerald-500 text-white hover:bg-emerald-600 font-bold rounded-2xl text-sm transition-all shadow-lg hover:shadow-emerald-500/25 flex items-center gap-2"
                            >
                                Complete Installation 
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>

            <?php elseif ($stage === 4): ?>
                <!-- Stage 4: Congratulations & Instructions -->
                <?php
                $admin_info = isset($_SESSION['admin_user_details']) ? $_SESSION['admin_user_details'] : ['name' => 'Admin Master', 'email' => 'admin@codevault.com'];
                ?>
                <div class="text-center">
                    <div class="w-20 h-20 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl mx-auto shadow-inner shadow-emerald-500/10 mb-6">
                        🎉
                    </div>
                    
                    <h2 class="text-3xl font-black text-slate-900 tracking-tight">Congratulations!</h2>
                    <p class="text-emerald-600 font-extrabold text-sm mt-1 uppercase tracking-wider">CodeVault Is Successfully Installed!</p>
                    <p class="text-slate-500 text-sm mt-4 leading-relaxed max-w-lg mx-auto">Your configuration details have been generated inside <code class="font-mono bg-slate-100 border px-1 py-0.5 rounded text-xs">config.php</code>. All tables and initial seed categories have been dynamically mapped.</p>
                    
                    <div class="mt-8 p-6 bg-slate-50 rounded-3xl border border-slate-100 text-left max-w-xl mx-auto space-y-4">
                        <h4 class="font-bold text-xs text-slate-400 uppercase tracking-widest">Administrator Credentials Registered</h4>
                        <div class="text-sm font-semibold text-slate-700 bg-white p-4 rounded-2xl border border-slate-100 flex flex-col gap-2">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($admin_info['name']); ?></p>
                            <p><strong>Email Address:</strong> <span class="font-mono text-emerald-600 font-bold"><?php echo htmlspecialchars($admin_info['email']); ?></span></p>
                        </div>

                        <h4 class="font-bold text-xs text-slate-400 tracking-widest uppercase mt-6">Next Steps as System Administrator</h4>
                        <ul class="text-xs text-slate-600 space-y-2 leading-relaxed list-decimal list-inside pl-2">
                            <li>Visit the <strong>Platform settings tab</strong> on the Admin Dashboard.</li>
                            <li>Configure your secure sandbox and live <strong>Paystack API keys</strong>.</li>
                            <li>Set your local <strong>platform currency</strong> (USD, NGN, EUR, etc.) and average settlement processing windows.</li>
                            <li>Moderate seller verification documents to activate billing accounts instantly.</li>
                            <li>For security reasons, we strongly advise deleting or renaming <code class="font-mono bg-red-50 text-red-600 border border-red-100 px-1 py-0.5 rounded text-[10px]">install.php</code> before going live.</li>
                        </ul>
                    </div>

                    <div class="mt-10 flex justify-center">
                        <a 
                            href="index.php?install_success=true" 
                            class="px-8 py-4 bg-emerald-500 text-white hover:bg-emerald-600 font-extrabold rounded-2xl text-sm transition-all shadow-xl shadow-emerald-500/20 active:scale-95 flex items-center gap-2"
                        >
                            Open CodeVault Web Marketplace 
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <footer class="py-8 text-center text-xs text-slate-400 font-semibold border-t border-gray-100 bg-white h-auto">
        &copy; 2026 CodeVault Software Inc. All rights reserved.
    </footer>

</body>
</html>
