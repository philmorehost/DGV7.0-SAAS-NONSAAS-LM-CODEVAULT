<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/security.php';

session_start();
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: /admin/index');
    exit;
}

$security = new Security();
$ip = $security->getClientIp();
$error = '';

if ($security->checkIpBlacklist($ip)) {
    die("Access Denied (IP Blacklisted).");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $lockout_msg = $security->checkBruteForce($email, $ip);
    
    if ($lockout_msg) {
        $error = $lockout_msg;
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            $security->logLoginAttempt($email, $ip, 'success');
            
            // Check if IP is newly seen for admin, send notification (Mock)
            // mail($user['email'], "Admin Login Alert", "Successful login from IP: $ip");
            
            header('Location: /admin/index');
            exit;
        } else {
            $error = "Invalid admin credentials.";
            $security->logLoginAttempt($email, $ip, 'failed');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - EXAM-HUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-slate-800 rounded-2xl shadow-2xl overflow-hidden border border-slate-700">
        <div class="p-8">
            <div class="text-center mb-8 flex flex-col items-center">
                <img src="/assets/uploads/rectangular_logo_cropped.png" class="h-16 w-auto mb-4 object-contain" alt="EXAM-HUB Logo">
                <h2 class="text-3xl font-bold text-white">Admin Secure Access</h2>
                <p class="text-slate-400 mt-2">Restricted Area</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-900/50 border border-red-500 text-red-200 p-4 mb-6 rounded text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-slate-300">Email Address</label>
                    <input type="email" name="email" required 
                           class="mt-1 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Password</label>
                    <input type="password" name="password" required 
                           class="mt-1 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>
                <button type="submit" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition">
                    Authenticate
                </button>
            </form>
        </div>
        <div class="bg-slate-900 px-8 py-4 border-t border-slate-700 text-center text-xs text-slate-500">
            IP Address Logged: <?= htmlspecialchars($ip) ?>
        </div>
    </div>
</body>
</html>
