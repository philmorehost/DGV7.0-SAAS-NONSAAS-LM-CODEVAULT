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

// Fetch user info for check
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header('Location: /login');
    exit;
}

$success = '';
$error = '';

// Helper function to handle uploads safely
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
    
    // Validate both extension and mime type
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

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_ticket'])) {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        if (empty($subject) || empty($message)) {
            $error = "Subject and message are required.";
        } else {
            try {
                $attachment_url = null;
                if (!empty($_FILES['attachment']['name'])) {
                    $attachment_url = handle_attachment($_FILES['attachment']);
                }

                $pdo->beginTransaction();

                // Insert ticket
                $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, status) VALUES (?, ?, 'open')");
                $stmt->execute([$user_id, $subject]);
                $ticket_id = $pdo->lastInsertId();

                // Insert message
                $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, attachment) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticket_id, $user_id, $message, $attachment_url]);

                $pdo->commit();
                $success = "Support ticket created successfully!";
                header("Location: /user/tickets?id=" . $ticket_id);
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    } elseif (isset($_POST['reply_ticket'])) {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        // Verify ticket ownership
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
        $stmt->execute([$ticket_id, $user_id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $error = "Unauthorized action or ticket not found.";
        } elseif (empty($message)) {
            $error = "Message content is required to reply.";
        } elseif ($ticket['status'] === 'closed') {
            $error = "This ticket is closed and cannot be replied to.";
        } else {
            try {
                $attachment_url = null;
                if (!empty($_FILES['attachment']['name'])) {
                    $attachment_url = handle_attachment($_FILES['attachment']);
                }

                $pdo->beginTransaction();

                // Insert reply
                $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, attachment) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticket_id, $user_id, $message, $attachment_url]);

                // Update ticket status back to open if it was replied/closed previously (or keep it open)
                $stmt = $pdo->prepare("UPDATE tickets SET status = 'open', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$ticket_id]);

                $pdo->commit();
                $success = "Reply posted successfully!";
                header("Location: /user/tickets?id=" . $ticket_id);
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

// Route Logic
$action = $_GET['action'] ?? '';
$ticket_id = (int)($_GET['id'] ?? 0);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow">
    <!-- Breadcrumb -->
    <div class="mb-6">
        <nav class="flex text-sm text-gray-500">
            <a href="/user/dashboard" class="hover:text-blue-600 font-medium">Dashboard</a>
            <span class="mx-2">/</span>
            <a href="/user/tickets" class="hover:text-blue-600 font-medium">Support Tickets</a>
            <?php if ($action === 'new'): ?>
                <span class="mx-2">/</span>
                <span class="text-gray-800">Open New Ticket</span>
            <?php elseif ($ticket_id): ?>
                <span class="mx-2">/</span>
                <span class="text-gray-800">Ticket #<?= $ticket_id ?></span>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Alert Banners -->
    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r shadow-sm flex items-center justify-between">
            <div class="flex items-center">
                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <button onclick="this.parentElement.style.display='none'" class="text-green-700 hover:text-green-900 focus:outline-none">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r shadow-sm flex items-center justify-between">
            <div class="flex items-center">
                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <button onclick="this.parentElement.style.display='none'" class="text-red-700 hover:text-red-900 focus:outline-none">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    <?php endif; ?>

    <!-- VIEW TICKET DETAIL -->
    <?php if ($ticket_id): 
        // Fetch ticket details
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
        $stmt->execute([$ticket_id, $user_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket):
            echo "<div class='glass p-8 rounded-2xl text-center text-red-600 font-bold'>Ticket not found.</div>";
        else:
            // Fetch messages in thread
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Ticket Info Panel -->
            <div class="lg:col-span-1">
                <div class="glass p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">Ticket Details</h2>
                    <div class="space-y-4">
                        <div>
                            <span class="text-xs text-gray-400 block font-semibold uppercase">Ticket ID</span>
                            <span class="text-sm font-bold text-gray-800">#<?= $ticket['id'] ?></span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block font-semibold uppercase">Subject</span>
                            <span class="text-base font-bold text-gray-950"><?= htmlspecialchars($ticket['subject']) ?></span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block font-semibold uppercase">Status</span>
                            <?php if ($ticket['status'] === 'open'): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Open</span>
                            <?php elseif ($ticket['status'] === 'replied'): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Replied</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Closed</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block font-semibold uppercase">Created At</span>
                            <span class="text-sm text-gray-700"><?= date('F j, Y, g:i a', strtotime($ticket['created_at'])) ?></span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block font-semibold uppercase">Last Update</span>
                            <span class="text-sm text-gray-700"><?= date('F j, Y, g:i a', strtotime($ticket['updated_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages Thread -->
            <div class="lg:col-span-2 space-y-6">
                <div class="glass p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col max-h-[600px] overflow-y-auto space-y-4 font-normal text-slate-800">
                    <h3 class="text-lg font-bold text-gray-900 border-b pb-2 mb-2">Conversation History</h3>
                    
                    <?php foreach ($messages as $msg): 
                        $isAdmin = $msg['role'] === 'admin';
                    ?>
                        <div class="flex flex-col <?= $isAdmin ? 'items-start' : 'items-end' ?>">
                            <div class="max-w-[85%] rounded-2xl p-4 shadow-sm <?= $isAdmin ? 'bg-white border border-gray-100 text-gray-900 rounded-tl-none' : 'bg-blue-600 text-white rounded-tr-none' ?>">
                                <div class="flex justify-between items-center gap-4 mb-1 text-xs <?= $isAdmin ? 'text-gray-500' : 'text-blue-200' ?>">
                                    <span class="font-bold"><?= htmlspecialchars($msg['firstname'] . ' ' . $msg['lastname']) ?> (<?= $isAdmin ? 'Support Team' : 'You' ?>)</span>
                                    <span><?= date('M j, g:i a', strtotime($msg['created_at'])) ?></span>
                                </div>
                                <p class="text-sm whitespace-pre-line leading-relaxed"><?= htmlspecialchars($msg['message']) ?></p>
                                
                                <?php if ($msg['attachment']): ?>
                                    <div class="mt-3 pt-2 border-t <?= $isAdmin ? 'border-gray-100' : 'border-blue-500' ?> flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            <span class="text-xs truncate max-w-[150px]"><?= basename($msg['attachment']) ?></span>
                                        </div>
                                        <a href="<?= htmlspecialchars($msg['attachment']) ?>" target="_blank" class="text-xs font-bold underline hover:opacity-85 <?= $isAdmin ? 'text-blue-600' : 'text-white' ?>">View/Download</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Form -->
                <?php if ($ticket['status'] !== 'closed'): ?>
                    <div class="glass p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h4 class="text-md font-bold text-gray-900 mb-4">Post a Reply</h4>
                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Message</label>
                                <textarea name="message" rows="4" required class="w-full rounded-xl border border-gray-200 p-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Type your response here..."></textarea>
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
                    <div class="bg-gray-100 rounded-xl p-4 text-center text-sm text-gray-600 border border-gray-200">
                        This ticket is closed. If you require further assistance, please open a new ticket.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- CREATE NEW TICKET -->
    <?php elseif ($action === 'new'): ?>
        <div class="max-w-3xl mx-auto">
            <div class="glass p-8 rounded-2xl shadow-sm border border-gray-100">
                <div class="border-b pb-4 mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Open a Support Ticket</h2>
                    <p class="text-sm text-gray-500 mt-1">Describe your query or transaction issue, and our support team will get back to you shortly.</p>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Subject / Issue Title</label>
                        <input type="text" name="subject" required class="w-full rounded-xl border border-gray-200 p-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="e.g., Transaction pending, wallet update error">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Describe your issue</label>
                        <textarea name="message" rows="6" required class="w-full rounded-xl border border-gray-200 p-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Provide full details here..."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Attachment (Optional)</label>
                        <input type="file" name="attachment" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-1">Supported formats: Images (PNG, JPG, JPEG, GIF, WEBP) and PDFs. Max size: 5MB.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <a href="/user/tickets" class="border border-gray-200 px-6 py-2.5 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                        <button type="submit" name="create_ticket" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-blue-700 shadow-md transition text-sm">Submit Ticket</button>
                    </div>
                </form>
            </div>
        </div>

    <!-- LIST TICKETS -->
    <?php else: 
        // Fetch tickets list
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$user_id]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Support Tickets</h1>
                <p class="text-sm text-gray-500 mt-1">Need help? Open a ticket or view replies from our support representatives.</p>
            </div>
            <a href="/user/tickets?action=new" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-md hover:bg-blue-700 transition text-sm">Open New Ticket</a>
        </div>

        <div class="glass rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <?php if (empty($tickets)): ?>
                <div class="p-12 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <p class="font-medium text-gray-700">No support tickets found</p>
                    <p class="text-xs text-gray-400 mt-1">When you create tickets, they will be listed here.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-left text-sm text-gray-500">
                        <thead class="bg-gray-50/50 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4">Ticket ID</th>
                                <th class="px-6 py-4">Subject</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Created Date</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($tickets as $t): ?>
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-4 font-bold text-gray-700 font-mono">#<?= $t['id'] ?></td>
                                    <td class="px-6 py-4 font-bold text-gray-905"><?= htmlspecialchars($t['subject']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($t['status'] === 'open'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Open</span>
                                        <?php elseif ($t['status'] === 'replied'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Replied</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-xs"><?= date('M j, Y, g:i a', strtotime($t['created_at'])) ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="/user/tickets?id=<?= $t['id'] ?>" class="text-blue-600 hover:text-blue-900 font-bold">View Thread &rarr;</a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
