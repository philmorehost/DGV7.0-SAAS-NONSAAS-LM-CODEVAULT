<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin/login');
    exit;
}

$pdo = get_db_connection();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['page_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    
    $stmt = $pdo->prepare("UPDATE pages SET title = ?, content = ? WHERE id = ?");
    $stmt->execute([$title, $content, $id]);
    $success = "Page updated successfully.";
}

$pages = $pdo->query("SELECT * FROM pages ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

$edit_page = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_page = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages - EXAM-HUB Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-8">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-900 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <h1 class="text-xl font-bold text-gray-800">Dynamic Pages Management</h1>
            </div>
        </header>
        
        <div class="flex-1 overflow-y-auto p-8">
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($edit_page): ?>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Edit: <?= htmlspecialchars($edit_page['title']) ?></h2>
                    <form method="POST" id="page-form">
                        <input type="hidden" name="page_id" value="<?= $edit_page['id'] ?>">
                        <input type="hidden" name="content" id="content-input">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Page Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($edit_page['title']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                            <div id="editor" class="h-64 bg-white rounded-lg">
                                <?= $edit_page['content'] ?>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-blue-700">Save Changes</button>
                            <a href="/admin/pages" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-bold hover:bg-gray-300">Cancel</a>
                        </div>
                    </form>
                </div>

                <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
                <style>
                    .ck-editor__editable_inline {
                        min-height: 400px;
                    }
                </style>
                <script>
                    let editorInstance;
                    ClassicEditor
                        .create(document.querySelector('#editor'), {
                            toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'codeBlock', 'insertTable', 'mediaEmbed', '|', 'undo', 'redo' ]
                        })
                        .then(editor => {
                            editorInstance = editor;
                        })
                        .catch(error => {
                            console.error(error);
                        });

                    document.getElementById('page-form').onsubmit = function() {
                        var content = document.querySelector('input[name=content]');
                        content.value = editorInstance.getData();
                    };
                </script>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800">All Pages</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-sm">
                                <th class="py-3 px-6 font-medium">Page Title</th>
                                <th class="py-3 px-6 font-medium">URL Slug</th>
                                <th class="py-3 px-6 font-medium">Last Updated</th>
                                <th class="py-3 px-6 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100">
                            <?php foreach($pages as $p): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-6 font-medium text-gray-900"><?= htmlspecialchars($p['title']) ?></td>
                                <td class="py-3 px-6 text-gray-500">/page?slug=<?= htmlspecialchars($p['slug']) ?></td>
                                <td class="py-3 px-6 text-gray-500"><?= htmlspecialchars($p['updated_at']) ?></td>
                                <td class="py-3 px-6 text-right">
                                    <a href="/admin/pages?edit=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">Edit</a>
                                    <a href="/page?slug=<?= $p['slug'] ?>" target="_blank" class="ml-3 text-gray-600 hover:text-gray-800 font-medium">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
