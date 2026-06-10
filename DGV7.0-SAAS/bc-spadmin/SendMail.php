<?php session_start();
include("../func/bc-spadmin-config.php");

// AJAX Handler for Drafts
if (isset($_GET['action'])) {
    // Security: Explicitly verify Super Admin session
    if (!isset($_SESSION['spadmin_session'])) {
        header("HTTP/1.1 403 Forbidden");
        echo json_encode(['error' => 'Unauthorized access']);
        exit;
    }

    if ($_GET['action'] == 'save_draft') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $subject = mysqli_real_escape_string($connection_server, $data['subject']);
        $mailto = mysqli_real_escape_string($connection_server, $data['mailto']);
        $body_html = mysqli_real_escape_string($connection_server, $data['body_html']);
        $body_json = mysqli_real_escape_string($connection_server, $data['body_json']);

        $check = mysqli_query($connection_server, "SELECT id FROM sas_mail_drafts WHERE is_super_admin=1 AND vendor_id=0 LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($connection_server, "UPDATE sas_mail_drafts SET subject='$subject', mailto='$mailto', body_html='$body_html', body_json='$body_json' WHERE is_super_admin=1 AND vendor_id=0");
        } else {
            mysqli_query($connection_server, "INSERT INTO sas_mail_drafts (vendor_id, subject, mailto, body_html, body_json, is_super_admin) VALUES (0, '$subject', '$mailto', '$body_html', '$body_json', 1)");
        }
        echo json_encode(['status' => 'success']);
        exit;
    }
    if ($_GET['action'] == 'load_draft') {
        $res = mysqli_query($connection_server, "SELECT * FROM sas_mail_drafts WHERE is_super_admin=1 AND vendor_id=0 LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            echo json_encode($row);
        } else {
            echo json_encode(['status' => 'empty']);
        }
        exit;
    }
}

if (isset($_POST["send-mail"])) {
    $subject = trim($_POST["subject"]);
    $body = trim($_POST["body"]); // GrapesJS output
    $mailto = mysqli_real_escape_string($connection_server, trim(strip_tags(strtolower($_POST["mailto"]))));
    
    $external_emails = [];

    // Process Paste field
    if (!empty($_POST['paste_emails'])) {
        $pasted = preg_split('/[\s,]+/', $_POST['paste_emails'], -1, PREG_SPLIT_NO_EMPTY);
        foreach ($pasted as $email) {
            if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) $external_emails[] = trim($email);
        }
    }

    // Process File Upload
    if (isset($_FILES['email_file']) && $_FILES['email_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['email_file']['name'], PATHINFO_EXTENSION));
        if ($ext == 'csv') {
            if (($handle = fopen($_FILES['email_file']['tmp_name'], "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    foreach ($data as $email) {
                        if (!empty($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL)) $external_emails[] = trim($email);
                    }
                }
                fclose($handle);
            }
        } else if ($ext == 'txt') {
            $content = file_get_contents($_FILES['email_file']['tmp_name']);
            $lines = preg_split('/[\s,]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($lines as $email) {
                if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) $external_emails[] = trim($email);
            }
        }
    }

    $external_emails = array_unique($external_emails);

    if (!empty($subject) && !empty($body)) {
        $success_count = 0;

        // Internal targets
        if (!empty($mailto)) {
            $res = sendSuperAdminEmailSpecific($mailto, $subject, $body);
            if ($res == "success") $success_count++;
        }
        
        // External targets
        if (!empty($external_emails)) {
            foreach ($external_emails as $ext_email) {
                sendSuperAdminEmail($ext_email, $subject, $body);
            }
            $success_count += count($external_emails);
        }

        if ($success_count > 0) {
            $_SESSION["product_purchase_response"] = "Global Dispatch Successful! (Targeted: $success_count)";
        } else {
            $_SESSION["product_purchase_response"] = "Error: No targets selected or dispatch failed.";
        }
    } else {
        $_SESSION["product_purchase_response"] = "Error: Subject and Body are required.";
    }
    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Marketing Suite | Super Admin</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    
    <!-- Vendor CSS Files -->
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">

    <!-- GrapesJS -->
    <link href="https://unpkg.com/grapesjs/dist/css/grapes.min.css" rel="stylesheet">
    <script src="https://unpkg.com/grapesjs"></script>
    <script src="https://unpkg.com/grapesjs-preset-newsletter"></script>

    <style>
        .marketing-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            background: #fff;
            height: calc(100vh - 180px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .marketing-card .card-body {
            flex: 1;
            overflow: hidden;
        }
        #gjs {
            border: 1px solid #ddd;
            overflow: hidden;
            height: 100% !important;
        }
        .placeholder-btn {
            cursor: pointer;
            transition: all 0.2s;
            font-size: 11px;
            padding: 5px 8px;
        }
        .placeholder-btn:hover {
            transform: scale(1.05);
            background: #eef2ff !important;
        }
        .app-sidebar {
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            padding: 20px;
            overflow-y: auto;
            height: 100%;
        }
        .gjs-cv-canvas {
            width: 100%;
            height: 100%;
            top: 0;
        }
        .main-content-area {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body>
    <?php include("../func/bc-spadmin-header.php"); ?>

    <div class="pagetitle">
      <h1>MARKETING SUITE</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
          <li class="breadcrumb-item active">Send Mail</li>
        </ol>
      </nav>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
            <div class="marketing-card">
                <div class="card-body p-0">
                    <div class="row g-0 h-100">
                        <!-- Left Controls -->
                        <div class="col-md-3 app-sidebar">
                            <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-megaphone me-2"></i>Campaign Settings</h5>

                            <form id="mainForm" method="post" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Email Subject</label>
                                    <input name="subject" id="subject" type="text" class="form-control rounded-3" placeholder="e.g. System Update v2.0" required />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Target Audience</label>
                                    <select name="mailto" id="mailto" class="form-select rounded-3">
                                        <option value="">No internal target (External only)</option>
                                        <option value="all">All Vendors</option>
                                        <option value="a">Active Vendors Only</option>
                                        <option value="b">Suspended Vendors</option>
                                        <option value="d">Deleted Accounts</option>
                                        <option value="bd">Blocked & Deleted</option>
                                    </select>
                                </div>

                                <hr class="my-3 opacity-50">

                                <h6 class="fw-bold mb-3 small text-muted text-uppercase">External Marketing</h6>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold"><i class="bi bi-file-earmark-arrow-up me-1"></i> Bulk Upload (.csv, .txt)</label>
                                    <input type="file" name="email_file" class="form-control form-control-sm rounded-3" accept=".csv,.txt">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold"><i class="bi bi-clipboard-plus me-1"></i> Paste Emails</label>
                                    <textarea name="paste_emails" class="form-control rounded-3" rows="2" placeholder="Separate by comma or newline"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-2">Personalization Tags</label>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-light text-primary border placeholder-btn" onclick="insertPlaceholder('{firstname}')">{firstname}</span>
                                        <span class="badge bg-light text-primary border placeholder-btn" onclick="insertPlaceholder('{lastname}')">{lastname}</span>
                                        <span class="badge bg-light text-primary border placeholder-btn" onclick="insertPlaceholder('{email}')">{email}</span>
                                        <span class="badge bg-light text-primary border placeholder-btn" onclick="insertPlaceholder('{phone}')">{phone}</span>
                                        <span class="badge bg-light text-primary border placeholder-btn" onclick="insertPlaceholder('{address}')">{address}</span>
                                        <span class="badge bg-light text-primary border placeholder-btn" onclick="insertPlaceholder('{website}')">{website}</span>
                                    </div>
                                </div>

                                <textarea name="body" id="body_html" hidden></textarea>
                                <input type="hidden" name="body_json" id="body_json">

                                <div class="d-grid gap-2 mt-4">
                                    <button type="button" class="btn btn-outline-primary btn-sm fw-bold" onclick="saveDraft()">
                                        <i class="bi bi-save me-2"></i>Save Draft
                                    </button>
                                    <button name="send-mail" type="submit" class="btn btn-primary fw-bold shadow">
                                        <i class="bi bi-send-fill me-2"></i>Dispatch Campaign
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Right Builder -->
                        <div class="col-md-9 main-content-area">
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
                                <h4 class="fw-bold mb-0">Visual Email Builder</h4>
                                <button type="button" class="btn btn-light btn-sm text-danger fw-bold" onclick="if(confirm('Clear all content?')) editor.setComponents('')">
                                    <i class="bi bi-trash me-1"></i> Clear Canvas
                                </button>
                            </div>
                            <div id="gjs"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </section>

    <?php include("../func/bc-spadmin-footer.php"); ?>

    <script>
        let editor;

        window.onload = () => {
            editor = grapesjs.init({
                container: '#gjs',
                fromElement: false,
                height: '100%',
                width: 'auto',
                storageManager: false,
                plugins: ['grapesjs-preset-newsletter'],
                pluginsOpts: {
                    'grapesjs-preset-newsletter': {
                        modalTitleImport: 'Import template',
                    }
                },
                assetManager: {
                    upload: 'SaUpload.php',
                    params: { type: 'marketing' }
                },
                canvas: {
                    styles: [
                        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
                        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'
                    ]
                }
            });

            // Load draft automatically if exists
            fetch('?action=load_draft')
                .then(res => res.json())
                .then(data => {
                    if (data.body_json) {
                        editor.setComponents(JSON.parse(data.body_json));
                        document.getElementById('subject').value = data.subject;
                        document.getElementById('mailto').value = data.mailto;
                    }
                });

            // Sync HTML to hidden textarea before submit
            document.getElementById('mainForm').onsubmit = (e) => {
                document.getElementById('body_html').value = editor.runCommand('gjs-get-inlined-html');
                document.getElementById('body_json').value = JSON.stringify(editor.getComponents());
            };
        };

        function insertPlaceholder(tag) {
            const selected = editor.getSelected();
            if (selected && selected.is('text')) {
                selected.append(tag);
            } else {
                alert('Please select a text block first to insert a placeholder.');
            }
        }

        function saveDraft() {
            const data = {
                subject: document.getElementById('subject').value,
                mailto: document.getElementById('mailto').value,
                body_html: editor.runCommand('gjs-get-inlined-html'),
                body_json: JSON.stringify(editor.getComponents())
            };

            fetch('?action=save_draft', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(res => res.json()).then(res => {
                if(res.status == 'success') alert('Draft saved successfully!');
            });
        }
    </script>
</body>
</html>