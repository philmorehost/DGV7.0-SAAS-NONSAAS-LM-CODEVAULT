<?php
/**
 * Cron Job Script for Marketing Emails
 * Run this via CLI: php marketing_emails.php
 * Usage: Sends promotional emails to users who haven't made a purchase recently.
 */
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/mail.php';

$pdo = get_db_connection();

echo "Starting Marketing Broadcast...\n";

// Find users who haven't made an order in the last 30 days
$stmt = $pdo->query("
    SELECT u.id, u.firstname, u.email 
    FROM users u
    WHERE u.role = 'user' 
    AND u.status = 'active'
    AND NOT EXISTS (
        SELECT 1 FROM orders o 
        WHERE o.user_id = u.id 
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    )
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "No users found needing marketing emails today.\n";
    exit;
}

$site_title = get_setting('site_title', 'EXAM-HUB');
$subject = "Don't Miss Out! Get Your Exam PINs Instantly on $site_title";

$count = 0;
foreach ($users as $user) {
    $body = "
    <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
        <h2>Hello {$user['firstname']},</h2>
        <p>It's been a while since we saw you! <strong>$site_title</strong> is still the fastest and most reliable platform in Nigeria to get your WAEC, NECO, NABTEB, and JAMB result checker PINs.</p>
        <p>Why wait in line when you can get your PIN delivered instantly to your screen?</p>
        <p><a href='http://{$_SERVER['HTTP_HOST']}/catalog' style='display: inline-block; padding: 10px 20px; background-color: #2563eb; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold;'>Buy a PIN Now</a></p>
        <p>Best Regards,<br>The $site_title Team</p>
    </div>
    ";
    
    if (send_transactional_email($user['email'], $subject, $body)) {
        echo "Sent marketing email to: {$user['email']}\n";
        $count++;
    }
}

echo "Marketing Broadcast Complete. Sent $count emails.\n";
