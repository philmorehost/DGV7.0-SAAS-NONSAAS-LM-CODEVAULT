<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

$pdo = get_db_connection();
$admin_id = $_SESSION['user_id'];

$success = '';
$error = '';

// Helper function for attachment upload
function handle_attachment($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $upload_dir = __DIR__ . '/../assets/uploads/tickets/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = $file['name'];
    $file_type = $file['type'];
    $tmp_name = $file['tmp_name'];
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("File size exceeds 5MB limit.");
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf'];
    
    // Validate mime type and extension
    $is_image = strpos($file_type, 'image/') === 0;
    $is_pdf = $file_type === 'application/pdf';

    if (!in_array($ext, $allowed_exts) || (!$is_image && !$is_pdf)) {
        throw new Exception("Invalid file type. Only images and PDFs are allowed.");
    }

    $new_filename = 'attach_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
        return '/assets/uploads/tickets/' . $new_filename;
    }

    throw new Exception("Failed to save uploaded file.");
}

// Actions Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reply_ticket'])) {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        // Verify ticket exists
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $error = "Ticket not found.";
        } elseif (empty($message)) {
            $error = "Message content cannot be empty.";
        } else {
            try {
                $attachment_url = null;
                if (!empty($_FILES['attachment']['name'])) {
                    $attachment_url = handle_attachment($_FILES['attachment']);
                }

                $pdo->beginTransaction();

                // Insert admin reply
                $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, attachment) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticket_id, $admin_id, $message, $attachment_url]);

                // Update ticket status to replied
                $stmt = $pdo->prepare("UPDATE tickets SET status = 'replied', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$ticket_id]);

                $pdo->commit();
                $success = "Reply sent and ticket status updated to Replied.";
                header("Location: /admin/tickets?id=" . $ticket_id);
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    } elseif (isset($_POST['close_ticket'])) {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt->execute([$ticket_id])) {
            $success = "Ticket closed successfully.";
            header("Location: /admin/tickets?id=" . $ticket_id);
            exit;
        } else {
            $error = "Failed to close ticket.";
        }
    }
}

// Fetch routing parameters
$ticket_id = (int)($_GET['id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$search_query = trim($_GET['search'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - Admin - EXAM-HUB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                <h1 class="text-xl font-bold text-gray-800">Support Ticket Management</h1>
            </div>
            <div class="text-sm text-gray-500">Logged in as <?= htmlspecialchars($_SESSION['email']) ?></div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8">

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r shadow-sm flex items-center justify-between">
                    <span><?= htmlspecialchars($success) ?></span>
                    <button onclick="this.parentElement.style.display='none'" class="text-green-700 hover:text-green-900 focus:outline-none">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r shadow-sm flex items-center justify-between">
                    <span><?= htmlspecialchars($error) ?></span>
                    <button onclick="this.parentElement.style.display='none'" class="text-red-700 hover:text-red-900 focus:outline-none">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            <?php endif; ?>

            <!-- TICKET DETAILS VIEW -->
            <?php if ($ticket_id): 
                $stmt = $pdo->prepare("
                    SELECT t.*, u.firstname, u.lastname, u.email 
                    FROM tickets t 
                    JOIN users u ON t.user_id = u.id 
                    WHERE t.id = ?
                ");
                $stmt->execute([$ticket_id]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$ticket):
                    echo "<div class='bg-white p-8 rounded-xl shadow-sm border border-gray-200 text-center text-red-600 font-bold'>Ticket not found.</div>";
                else:
                    // Fetch messages thread
                    $stmt = $pdo->prepare("
                        SELECT tm.*, u.firstname, u.lastname, u.role 
                        FROM ticket_messages tm 
                        JOIN users u ON tm.sender_id = u.id 
                        WHERE tm.ticket_id = ? 
                        ORDER BY tm.created_at ASC
                    ");
                    $stmt->execute([$ticket_id]);
                    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <div class="mb-6">
                    <a href="/admin/tickets" class="text-blue-600 hover:underline text-sm font-semibold flex items-center gap-1">
                        &larr; Back to Ticket List
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Ticket Details Info -->
                    <div class="lg:col-span-1">
                        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                            <h2 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b">Metadata</h2>
                            <div class="space-y-4">
                                <div>
                                    <span class="text-xs text-gray-400 block font-semibold uppercase">User</span>
                                    <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($ticket['firstname'] . ' ' . $ticket['lastname']) ?></span>
                                    <span class="text-xs text-gray-500 block"><?= htmlspecialchars($ticket['email']) ?></span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400 block font-semibold uppercase">Subject</span>
                                    <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></span>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400 block font-semibold uppercase">Status</span>
                                    <?php if ($ticket['status'] === 'open'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Open</span>
                                    <?php elseif ($ticket['status'] === 'replied'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Replied</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Closed</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400 block font-semibold uppercase">Created At</span>
                                    <span class="text-xs text-gray-700"><?= date('M j, Y, g:i a', strtotime($ticket['created_at'])) ?></span>
                                </div>
                                
                                <?php if ($ticket['status'] !== 'closed'): ?>
                                    <div class="pt-4 border-t">
                                        <form action="" method="POST" onsubmit="return confirm('Are you sure you want to close this ticket?');">
                                            <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                                            <button type="submit" name="close_ticket" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-xl text-sm transition shadow-sm">
                                                Close Ticket
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Discussion & Reply -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 max-h-[600px] overflow-y-auto space-y-4">
                            <h3 class="text-lg font-bold text-gray-900 border-b pb-2">Messages Thread</h3>
                            
                            <?php foreach ($messages as $msg): 
                                $isAdmin = $msg['role'] === 'admin';
                            ?>
                                <div class="flex flex-col <?= $isAdmin ? 'items-end' : 'items-start' ?>">
                                    <div class="max-w-[85%] rounded-2xl p-4 shadow-sm <?= $isAdmin ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-gray-100 text-gray-900 rounded-tl-none' ?>">
                                        <div class="flex justify-between items-center gap-4 mb-1 text-xs <?= $isAdmin ? 'text-blue-200' : 'text-gray-500' ?>">
                                            <span class="font-bold"><?= htmlspecialchars($msg['firstname'] . ' ' . $msg['lastname']) ?> (<?= $isAdmin ? 'Support Team (You)' : 'User' ?>)</span>
                                            <span><?= date('M j, g:i a', strtotime($msg['created_at'])) ?></span>
                                        </div>
                                        <p class="text-sm whitespace-pre-line leading-relaxed"><?= htmlspecialchars($msg['message']) ?></p>
                                        
                                        <?php if ($msg['attachment']): ?>
                                            <div class="mt-3 pt-2 border-t <?= $isAdmin ? 'border-blue-500' : 'border-gray-200' ?> flex items-center justify-between">
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                    </svg>
                                                    <span class="truncate max-w-[150px]"><?= basename($msg['attachment']) ?></span>
                                                </div>
                                                <a href="<?= htmlspecialchars($msg['attachment']) ?>" target="_blank" class="text-xs font-bold underline hover:opacity-85 <?= $isAdmin ? 'text-white' : 'text-blue-600' ?>">View/Download</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Post Admin Reply -->
                        <?php if ($ticket['status'] !== 'closed'): ?>
                            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                                <h4 class="text-md font-bold text-gray-900 mb-4">Post support reply</h4>
                                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">Message</label>
                                        <textarea name="message" rows="4" required class="w-full rounded-xl border border-gray-200 p-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Type your response to the user..."></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">Attachment (Optional)</label>
                                        <input type="file" name="attachment" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        <p class="text-xs text-gray-400 mt-1">Supported formats: Images (PNG, JPG, JPEG, GIF, WEBP) and PDFs. Max size: 5MB.</p>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" name="reply_ticket" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-blue-700 shadow-md transition text-sm">Send Reply</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-100 rounded-xl p-4 text-center text-sm text-gray-600 border">
                                This ticket is closed.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TICKETS LIST VIEW -->
            <?php else: 
                // Build queries with filters
                $where_clauses = [];
                $params = [];

                if ($status_filter) {
                    $where_clauses[] = "t.status = ?";
                    $params[] = $status_filter;
                }

                if ($search_query) {
                    $where_clauses[] = "(t.subject LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
                    $like_query = "%{$search_query}%";
                    $params[] = $like_query;
                    $params[] = $like_query;
                    $params[] = $like_query;
                    $params[] = $like_query;
                }

                $where_sql = '';
                if (!empty($where_clauses)) {
                    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
                }

                $sql = "
                    SELECT t.*, u.firstname, u.lastname, u.email 
                    FROM tickets t 
                    JOIN users u ON t.user_id = u.id 
                    {$where_sql} 
                    ORDER BY t.updated_at DESC
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <!-- Filters & Search Header -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
                    <form action="" method="GET" class="flex flex-col md:flex-row gap-4 items-end justify-between">
                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Search Tickets</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" placeholder="Search by subject, user, or email...">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Filter by Status</label>
                                <select name="status" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">All Statuses</option>
                                    <option value="open" <?= $status_filter === 'open' ? 'selected' : '' ?>>Open</option>
                                    <option value="replied" <?= $status_filter === 'replied' ? 'selected' : '' ?>>Replied</option>
                                    <option value="closed" <?= $status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-2 w-full md:w-auto">
                            <button type="submit" class="flex-1 md:flex-none bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg text-sm transition">
                                Apply
                            </button>
                            <?php if ($status_filter || $search_query): ?>
                                <a href="/admin/tickets" class="flex-1 md:flex-none bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-6 rounded-lg text-sm text-center transition">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Tickets List Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <?php if (empty($tickets)): ?>
                        <div class="p-12 text-center text-gray-500">
                            <p class="font-medium text-gray-700">No support tickets found matching criteria</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-500">
                                <thead class="bg-gray-50 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4">ID</th>
                                        <th class="px-6 py-4">User</th>
                                        <th class="px-6 py-4">Subject</th>
                                        <th class="px-6 py-4">Status</th>
                                        <th class="px-6 py-4">Last Activity</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($tickets as $t): ?>
                                        <tr class="hover:bg-gray-50/50 transition">
                                            <td class="px-6 py-4 font-bold text-gray-700 font-mono">#<?= $t['id'] ?></td>
                                            <td class="px-6 py-4">
                                                <span class="font-bold text-gray-900 block"><?= htmlspecialchars($t['firstname'] . ' ' . $t['lastname']) ?></span>
                                                <span class="text-xs text-gray-400"><?= htmlspecialchars($t['email']) ?></span>
                                            </td>
                                            <td class="px-6 py-4 font-semibold text-gray-800"><?= htmlspecialchars($t['subject']) ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($t['status'] === 'open'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Open</span>
                                                <?php elseif ($t['status'] === 'replied'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Replied</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Closed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-xs"><?= date('M j, Y, g:i a', strtotime($t['updated_at'])) ?></td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="/admin/tickets?id=<?= $t['id'] ?>" class="text-blue-600 hover:text-blue-900 font-bold">Manage Thread &rarr;</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        sidebar.classList.toggle('-translate-x-full');
        backdrop.classList.toggle('hidden');
    }
    </script>
</body>
</html>
