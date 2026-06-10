<?php session_start();
include("../func/bc-spadmin-config.php");

// Handle Global KYC Actions
if (isset($_GET['action']) && isset($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
    $action = $_GET['action'];
    $status = ($action == 'approve') ? 2 : 0;

    mysqli_query($connection_server, "UPDATE sas_users SET kyc_status='$status' WHERE id='$uid'");
    $_SESSION['product_purchase_response'] = "User KYC status updated globally.";
    header("Location: KYCManagement.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Global KYC Management | Super Admin</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-spadmin-header.php"); ?>

    <div class="pagetitle">
      <h1>GLOBAL KYC REVIEW</h1>
      <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li><li class="breadcrumb-item active">KYC Review</li></ol></nav>
    </div>

    <section class="section dashboard">
      <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-white py-4 border-0">
                    <h5 class="fw-bold mb-0 text-primary">Cross-Platform Verification Queue</h5>
                    <p class="text-muted small mb-0">Monitor identity verifications across all active vendors</p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Vendor</th>
                                    <th>User</th>
                                    <th>ID Type</th>
                                    <th>Value</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $get_all_pending = mysqli_query($connection_server, "SELECT u.*, v.company_name FROM sas_users u JOIN sas_vendors v ON u.vendor_id = v.id WHERE u.kyc_status=1 ORDER BY u.date DESC");
                                if (mysqli_num_rows($get_all_pending) > 0):
                                    while ($user = mysqli_fetch_assoc($get_all_pending)):
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary"><?php echo $user['company_name']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo $user['firstname']." ".$user['lastname']; ?></div>
                                        <div class="small text-muted">@<?php echo $user['username']; ?></div>
                                    </td>
                                    <td><?php echo !empty($user['bvn']) ? 'BVN' : 'NIN'; ?></td>
                                    <td><?php echo $user['bvn'] ?: $user['nin']; ?></td>
                                    <td class="text-center">
                                        <a href="KYCManagement.php?action=approve&uid=<?php echo $user['id']; ?>" class="btn btn-success btn-sm rounded-pill px-3">Approve</a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No pending global KYC requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </section>

    <?php include("../func/bc-spadmin-footer.php"); ?>
</body>
</html>
