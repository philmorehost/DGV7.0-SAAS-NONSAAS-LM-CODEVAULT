<?php session_start();
include("../func/bc-admin-config.php");

$vid = $get_logged_admin_details['id'];

// Handle KYC Actions
if (isset($_GET['action']) && isset($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
    $action = $_GET['action'];
    $status = ($action == 'approve') ? 2 : 0;

    mysqli_query($connection_server, "UPDATE sas_users SET kyc_status='$status' WHERE id='$uid' AND vendor_id='$vid'");
    $_SESSION['product_purchase_response'] = "User KYC status updated to " . (($status == 2) ? 'Approved' : 'Rejected');
    header("Location: KYCManagement.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>KYC Management | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-admin-header.php"); ?>

    <div class="pagetitle">
      <h1>KYC MANAGEMENT</h1>
      <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li><li class="breadcrumb-item active">KYC Management</li></ol></nav>
    </div>

    <section class="section dashboard">
      <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-white py-4 border-0">
                    <h5 class="fw-bold mb-0 text-primary">Pending Identity Verifications</h5>
                    <p class="text-muted small mb-0">Review and approve user identity documents</p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">User</th>
                                    <th>ID Type</th>
                                    <th>Value</th>
                                    <th>Submitted Date</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $get_pending_kyc = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id='$vid' AND kyc_status=1 ORDER BY reg_date DESC");
                                if ($get_pending_kyc && mysqli_num_rows($get_pending_kyc) > 0):
                                    while ($user = mysqli_fetch_assoc($get_pending_kyc)):
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?php echo $user['firstname']." ".$user['lastname']; ?></div>
                                        <div class="small text-muted">@<?php echo $user['username']; ?></div>
                                    </td>
                                    <td>
                                        <?php echo !empty($user['bvn']) ? 'BVN' : (!empty($user['nin']) ? 'NIN' : 'Media Only'); ?>
                                    </td>
                                    <td><?php echo $user['bvn'] ?: $user['nin'] ?: 'N/A'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['reg_date'])); ?></td>
                                    <td class="text-center">
                                        <a href="KYCManagement.php?action=approve&uid=<?php echo $user['id']; ?>" class="btn btn-success btn-sm rounded-pill px-3 me-2">Approve</a>
                                        <a href="KYCManagement.php?action=reject&uid=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm rounded-pill px-3">Reject</a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No pending KYC requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </section>

    <?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>