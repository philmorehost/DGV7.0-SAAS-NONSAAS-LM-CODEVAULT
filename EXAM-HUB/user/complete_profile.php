<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$pdo = get_db_connection();
$user_id = $_SESSION['user_id'];

// Fetch latest user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: /login');
    exit;
}

// If phone already exists, redirect to dashboard
if (!empty($user['phone'])) {
    header('Location: /user/dashboard');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    
    if (empty($phone)) {
        $error = "Phone number is required.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
        if ($stmt->execute([$phone, $user_id])) {
            header('Location: /user/dashboard');
            exit;
        } else {
            $error = "Failed to update phone number. Please try again.";
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-[80vh] flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
            Complete your profile
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
            We need your phone number to automatically generate your dedicated Virtual Bank Account for easy funding.
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="glass py-8 px-4 shadow sm:rounded-2xl sm:px-10">
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form class="space-y-6" method="POST">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                    <div class="mt-1">
                        <input name="phone" type="tel" autocomplete="tel" required placeholder="e.g. 08012345678"
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                        Save and Continue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
