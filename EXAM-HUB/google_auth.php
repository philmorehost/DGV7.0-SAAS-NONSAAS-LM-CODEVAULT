<?php
session_start();
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

$client_id = get_setting('google_client_id');
$client_secret = get_setting('google_client_secret');
$redirect_uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/google_auth.php";

if (empty($client_id) || empty($client_secret)) {
    $_SESSION['error_message'] = "Google Authentication is not configured by the administrator.";
    header('Location: /login.php');
    exit;
}

// 1. Redirect to Google for Authentication
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ]);
    header('Location: ' . $auth_url);
    exit;
}

// 2. Handle Google Callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Exchange authorization code for access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code',
        'code' => $code
    ]));
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $token_data = json_decode($response, true);

    if ($http_code !== 200 || !isset($token_data['access_token'])) {
        $_SESSION['error_message'] = "Failed to authenticate with Google.";
        header('Location: /login.php');
        exit;
    }

    $access_token = $token_data['access_token'];

    // Fetch user profile info
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    $profile_response = curl_exec($ch);
    $profile_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $profile = json_decode($profile_response, true);

    if ($profile_code !== 200 || !isset($profile['email'])) {
        $_SESSION['error_message'] = "Failed to fetch Google profile information.";
        header('Location: /login.php');
        exit;
    }

    $email = $profile['email'];
    $firstname = $profile['given_name'] ?? 'Google';
    $lastname = $profile['family_name'] ?? 'User';

    $pdo = get_db_connection();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['status'] === 'suspended') {
            $_SESSION['error_message'] = "Your account has been suspended. Please contact support.";
            header('Location: /login.php');
            exit;
        }
        
        // Log in existing user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header('Location: ' . ($user['role'] === 'admin' ? '/admin/dashboard.php' : '/user/dashboard.php'));
        exit;
    } else {
        // Register new user
        $random_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, role, status) VALUES (?, ?, ?, ?, 'user', 'active')");
        if ($stmt->execute([$firstname, $lastname, $email, $hashed_password])) {
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'user';
            
            // Send welcome email (optional)
            if (function_exists('send_transactional_email')) {
                require_once __DIR__ . '/core/mail.php';
                $subject = "Welcome to " . get_setting('site_title', 'EXAM-HUB');
                $body = "<p>Hello $firstname,</p><p>Welcome! Your account has been created via Google Sign-In.</p><p>If you ever need to login with a password, you can reset it via the forgot password page.</p>";
                send_transactional_email($email, $subject, $body);
            }
            
            header('Location: /user/dashboard.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to create an account. Please try again.";
            header('Location: /register.php');
            exit;
        }
    }
}

// If access directly without action or code
header('Location: /login.php');
exit;
