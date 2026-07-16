<?php session_start();
    include("../func/bc-admin-config.php");

    if(!isset($_GET["batch"]) || empty(trim(strip_tags($_GET["batch"])))){
        header("Location: BatchTransactions.php");
        exit();
    }

    $batch = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["batch"])));
    $batch_progress = bc_get_bulk_batch_progress($connection_server, $get_logged_admin_details["id"], null, $batch);
?>
<!DOCTYPE html>
<head>
    <title>Batch Details | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <?php if ($batch_progress["status"] !== "completed"): ?>
    <meta http-equiv="refresh" content="10">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">

  <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets-2/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include("../func/bc-admin-header.php"); ?>
    <div class="pagetitle">
      <h1>BATCH DETAILS</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="Dashboard.php">Home</a></li>
          <li class="breadcrumb-item"><a href="BatchTransactions.php">Batch Transactions</a></li>
          <li class="breadcrumb-item active"><?php echo $batch; ?></li>
        </ol>
      </nav>
    </div>

    <section class="section dashboard">
      <div class="col-12">
        <?php
            $get_user_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_transactions WHERE vendor_id='".$get_logged_admin_details["id"]."' && batch_number='$batch' ORDER BY date DESC");
        ?>
        <div class="card info-card px-5 py-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="card-title mb-0">Transactions in Batch: <?php echo $batch; ?></h5>
                <a href="BatchTransactions.php" class="btn btn-secondary btn-sm">Back to Batches</a>
            </div>

            <?php
                $bp_status = $batch_progress["status"];
                $bp_total = max(1, $batch_progress["total"]);
                $bp_done_pct = round((($batch_progress["successful"] + $batch_progress["failed"]) / $bp_total) * 100);
                $bp_badge_class = $bp_status === "completed" ? "bg-success" : ($bp_status === "processing" ? "bg-warning text-dark" : "bg-secondary");
            ?>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge <?php echo $bp_badge_class; ?> text-uppercase"><?php echo htmlspecialchars($bp_status); ?></span>
                    <small class="text-muted"><?php echo $batch_progress["successful"]; ?> successful &middot; <?php echo $batch_progress["failed"]; ?> failed &middot; <?php echo $batch_progress["pending"]; ?> pending of <?php echo $batch_progress["total"]; ?></small>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar <?php echo $bp_status === 'completed' ? 'bg-success' : 'bg-primary progress-bar-striped progress-bar-animated'; ?>" role="progressbar" style="width: <?php echo $bp_done_pct; ?>%"></div>
                </div>
                <?php if (!empty($batch_progress["ai_diagnosis"])): ?>
                    <div class="alert alert-warning mt-3 mb-0"><strong>AI Diagnosis:</strong> <?php echo htmlspecialchars($batch_progress["ai_diagnosis"]); ?></div>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Date/Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if(mysqli_num_rows($get_user_transaction_details) >= 1){
                            while($row = mysqli_fetch_assoc($get_user_transaction_details)){
                                $status_class = ($row['status'] == 1 ? 'text-success' : ($row['status'] == 2 ? 'text-warning' : 'text-danger'));
                                $status_text = tranStatus($row['status']);
                                echo '<tr>
                                    <td><strong>'.$row['product_unique_id'].'</strong><br><small class="text-muted">'.$row['reference'].'</small></td>
                                    <td>₦'.number_format($row['discounted_amount'], 2).'</td>
                                    <td class="'.$status_class.'">'.$status_text.'</td>
                                    <td><small>'.$row['description'].'</small></td>
                                    <td>'.date('M d, Y H:i:s', strtotime($row['date'])).'</td>
                                    <td><button class="btn btn-primary btn-sm" onclick="showTransactionDetails(\''.$row['reference'].'\')">Details</button></td>
                                </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="6" class="text-center py-4">No transactions found in this batch.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
    </section>
    <?php include("../func/bc-admin-footer.php"); ?>
</body>
</html>