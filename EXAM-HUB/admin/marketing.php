<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_email'])) {
        $subject = $_POST['subject'] ?? '';
        $body = $_POST['body'] ?? '';
        $audience = $_POST['audience'] ?? 'all';
        
        $recipients = [];
        
        if ($audience === 'all' || $audience === 'users') {
            $stmt = $pdo->query("SELECT email FROM users WHERE role='user'");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recipients[] = $row['email'];
            }
        }
        
        if ($audience === 'all' || $audience === 'guests') {
            $stmt = $pdo->query("SELECT email FROM guest_emails");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recipients[] = $row['email'];
            }
        }
        
        if ($audience === 'external' && !empty($_POST['external_emails'])) {
            $emails = explode(',', $_POST['external_emails']);
            foreach ($emails as $email) {
                $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
                if ($email) {
                    $recipients[] = $email;
                }
            }
        }
        
        $recipients = array_unique($recipients);
        
        // Mock sending - In production, dispatch to a Queue / SMTP service.
        $success = "Email campaign '$subject' queued successfully for " . count($recipients) . " recipients.";
        
        if (isset($_POST['save_template']) && !empty($_POST['template_name'])) {
            $stmt = $pdo->prepare("INSERT INTO email_templates (name, subject, body) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['template_name'], $subject, $body]);
            $success .= " Template saved.";
        }
    }
}

$templates = $pdo->query("SELECT * FROM email_templates ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$user_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$guest_count = $pdo->query("SELECT COUNT(*) FROM guest_emails")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Marketing - EXAM-HUB Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <style>
        .ck-editor__editable_inline {
            min-height: 400px;
        }
    </style>
    <script>
      let editorInstance;
      document.addEventListener("DOMContentLoaded", function() {
          ClassicEditor
              .create(document.querySelector('#email_body'), {
                  toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', '|', 'undo', 'redo' ]
              })
              .then(editor => {
                  editorInstance = editor;
              })
              .catch(error => {
                  console.error(error);
              });
      });
      
      function loadTemplate(subject, body) {
          document.getElementById('subject').value = subject;
          if (editorInstance) {
              editorInstance.setData(body);
          }
      }
    </script>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-8">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-900 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h1 class="text-xl font-bold text-gray-800">Email Marketing</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8 flex flex-col lg:flex-row gap-8">
            
            <!-- Composer -->
            <div class="flex-1 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <?php if (isset($success)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="email-form" class="space-y-6">
                    <input type="hidden" name="send_email" value="1">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
                            <select id="audience_select" name="audience" class="w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="document.getElementById('external_emails_div').style.display = (this.value === 'external' ? 'block' : 'none');">
                                <option value="all">Everyone (<?= $user_count + $guest_count ?> Contacts)</option>
                                <option value="users">Registered Users (<?= $user_count ?>)</option>
                                <option value="guests">Guest Customers (<?= $guest_count ?>)</option>
                                <option value="external">External Email Addresses</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Campaign Subject</label>
                            <input type="text" id="subject" name="subject" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Exciting news from EXAM-HUB!">
                        </div>
                    </div>

                    <div id="external_emails_div" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-1">External Email Addresses (Comma-separated)</label>
                        <input type="text" name="external_emails" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="user1@example.com, user2@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Body (Rich Text)</label>
                        <textarea id="email_body" name="body" class="w-full"></textarea>
                    </div>

                    <div class="flex items-center gap-4 bg-gray-50 p-4 rounded-lg border">
                        <input type="checkbox" name="save_template" id="save_template" value="1" class="rounded text-blue-600">
                        <label for="save_template" class="text-sm font-medium text-gray-700">Save this email as a reusable template</label>
                        <input type="text" name="template_name" placeholder="Template Name (e.g. Promo 2024)" class="px-3 py-1 text-sm border rounded">
                    </div>

                    <button type="submit" class="w-full py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition shadow-lg shadow-blue-500/30">
                        Launch Campaign
                    </button>
                </form>
                <script>
                    document.getElementById('email-form').onsubmit = function() {
                        var content = document.querySelector('#email_body');
                        content.value = editorInstance.getData();
                    };
                </script>
            </div>
            
            <!-- Templates Sidebar -->
            <div class="w-full lg:w-80 space-y-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Saved Templates</h2>
                    <?php if (count($templates) > 0): ?>
                        <ul class="space-y-3">
                            <?php foreach ($templates as $t): ?>
                                <li class="p-3 border rounded-lg hover:bg-gray-50 cursor-pointer transition" 
                                    onclick="loadTemplate('<?= addslashes(htmlspecialchars($t['subject'])) ?>', '<?= addslashes(htmlspecialchars($t['body'])) ?>')">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($t['name']) ?></div>
                                    <div class="text-xs text-gray-500 truncate mt-1"><?= htmlspecialchars($t['subject']) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 text-center py-4">No templates saved yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
