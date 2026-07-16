<?php session_start();
include("../func/bc-admin-config.php");

$vid = $get_logged_admin_details['id'];

// AJAX Handler for Drafts
if (isset($_GET['action'])) {
    // Security: Verify Vendor session
    if (!isset($_SESSION['admin_session'])) {
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

        $check = mysqli_query($connection_server, "SELECT id FROM sas_mail_drafts WHERE is_super_admin=0 AND vendor_id='$vid' LIMIT 1");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($connection_server, "UPDATE sas_mail_drafts SET subject='$subject', mailto='$mailto', body_html='$body_html', body_json='$body_json' WHERE is_super_admin=0 AND vendor_id='$vid'");
        } else {
            mysqli_query($connection_server, "INSERT INTO sas_mail_drafts (vendor_id, subject, mailto, body_html, body_json, is_super_admin) VALUES ('$vid', '$subject', '$mailto', '$body_html', '$body_json', 0)");
        }
        echo json_encode(['status' => 'success']);
        exit;
    }
    if ($_GET['action'] == 'load_draft') {
        $res = mysqli_query($connection_server, "SELECT * FROM sas_mail_drafts WHERE is_super_admin=0 AND vendor_id='$vid' LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            echo json_encode($row);
        } else {
            echo json_encode(['status' => 'empty']);
        }
        exit;
    }
    
    // Vendor Asset Upload Handler for GrapesJS
    if ($_GET['action'] == 'upload_asset' && isset($_FILES['files'])) {
        $upload_dir = '../uploaded-image/vendor_' . $vid . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $responses = [];
        foreach ($_FILES['files']['name'] as $key => $name) {
            $tmp_name = $_FILES['files']['tmp_name'][$key];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_extensions)) continue;

            $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target = $upload_dir . $filename;

            if (move_uploaded_file($tmp_name, $target)) {
                $responses[] = [
                    'src' => $web_http_host . '/uploaded-image/vendor_' . $vid . '/' . $filename,
                    'type' => 'image'
                ];
            }
        }
        echo json_encode(['data' => $responses]);
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
            $res = sendVendorEmailSpecific($mailto, $subject, $body);
            if ($res == "success") $success_count++;
        }

        // External targets
        if (!empty($external_emails)) {
            foreach ($external_emails as $ext_email) {
                sendVendorEmail($ext_email, $subject, $body);
            }
            $success_count += count($external_emails);
        }

        if ($success_count > 0) {
            $_SESSION["product_purchase_response"] = "Campaign Dispatch Successful! (Targeted: $success_count)";
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
    <title>Marketing Suite | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    
    <!-- Vendor CSS Files -->
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">

    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .marketing-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            background: #fff;
            padding: 20px;
        }
        #editor-container {
            height: 300px;
            border-radius: 0 0 10px 10px;
        }
        .ql-toolbar {
            border-radius: 10px 10px 0 0;
            background: #f8fafc;
        }
    </style>
</head>
<body>
    <?php include("../func/bc-admin-header.php"); ?>

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
                    <div class="row">
        <div class="col-md-12">
            <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-megaphone me-2"></i>Send Email</h5>
            <form id="mainForm" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted text-uppercase">Email Subject</label>
                        <input name="subject" id="subject" type="text" class="form-control" placeholder="e.g. System Update v2.0" required />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted text-uppercase">Target Audience</label>
                        <select name="mailto" id="mailto" class="form-select">
                            <option value="">No internal target (External only)</option>
                            <option value="all">All Users</option>
                            <option value="a">Active Users Only</option>
                            <option value="b">Blocked Accounts</option>
                            <option value="d">Deleted Accounts</option>
                            <option value="bd">Blocked & Deleted</option>
                            <option value="Select User">Select User</option>
                        </select>
                    </div>
                </div>

                <div id="select_user_div" class="mb-3" style="display:none;">
                    <label class="form-label fw-bold text-muted text-uppercase">Select Users (comma separated emails)</label>
                    <input name="paste_emails" id="paste_emails" type="text" class="form-control" placeholder="user1@email.com, user2@email.com" />
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Upload CSV (Optional)</label>
                    <input type="file" name="email_file" class="form-control form-control-sm" accept=".csv,.txt" />
                    <div class="form-text" style="font-size: 11px;">Upload a CSV file containing email addresses (one per line).</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-muted text-uppercase">Message Content</label>
                    <div class="mb-2 small text-muted">
                        <strong>Supported Tags:</strong> <code>{firstname}</code>, <code>{lastname}</code>, <code>{email}</code>, <code>{phone}</code>, <code>{address}</code>, <code>{website}</code>
                    </div>
                    <div id="editor-container"></div>
                    <textarea name="body" id="body_html" style="display:none;"></textarea>
                    <input type="hidden" name="body_json" id="body_json" />
                </div>

                <div class="mb-3 d-flex gap-2">
                    <button type="submit" name="send-mail" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-send-fill me-2"></i> Send Campaign
                    </button>
                    <button type="button" class="btn btn-outline-secondary fw-bold px-4 py-2 rounded-pill" onclick="saveDraft(event)">
                        <i class="bi bi-save me-2"></i> Save Draft
                    </button>
                </div>
            </form>
        </div>
    </div>
            </div>
        </div>
      </div>
    </section>

    <?php include("../func/bc-admin-footer.php"); ?>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Write your email content here...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        document.getElementById('mailto').addEventListener('change', function() {
            if (this.value === 'Select User') {
                document.getElementById('select_user_div').style.display = 'block';
            } else {
                document.getElementById('select_user_div').style.display = 'none';
            }
        });

        fetch('?action=load_draft')
            .then(res => res.json())
            .then(data => {
                if (data.body_html) {
                    quill.root.innerHTML = data.body_html;
                    document.getElementById('subject').value = data.subject || '';
                    document.getElementById('mailto').value = data.mailto || 'all';
                    if (data.mailto === 'Select User') {
                        document.getElementById('select_user_div').style.display = 'block';
                    }
                }
            });

        document.getElementById('mainForm').onsubmit = function() {
            document.getElementById('body_html').value = quill.root.innerHTML;
            document.getElementById('body_json').value = JSON.stringify(quill.getContents());
            return true;
        };

        function saveDraft(event) {
            const btn = event.currentTarget;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Saving...';

            fetch('?action=save_draft', {
                method: 'POST',
                body: JSON.stringify({
                    subject: document.getElementById('subject').value,
                    mailto: document.getElementById('mailto').value,
                    body_html: quill.root.innerHTML,
                    body_json: JSON.stringify(quill.getContents())
                }),
                headers: { 'Content-Type': 'application/json' }
            }).then(() => {
                btn.innerHTML = '<i class="bi bi-check me-2"></i> Saved';
                setTimeout(() => btn.innerHTML = '<i class="bi bi-save me-2"></i> Save Draft', 2000);
            });
        }
    </script>
</body>
</html>