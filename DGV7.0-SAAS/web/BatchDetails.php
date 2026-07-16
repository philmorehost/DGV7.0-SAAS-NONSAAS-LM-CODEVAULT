<?php session_start();
    include("../func/bc-config.php");

    if(!isset($_GET["batch"]) || empty(trim(strip_tags($_GET["batch"])))){
        header("Location: BatchTransactions.php");
        exit();
    }

    $batch = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["batch"])));
    $batch_progress = bc_get_bulk_batch_progress($connection_server, $get_logged_user_details["vendor_id"], $get_logged_user_details["username"], $batch);
?>
<!DOCTYPE html>
<head>
    <title>Batch Details | <?php echo $get_all_site_details["site_title"]; ?></title>
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
    <?php include("../func/bc-header.php"); ?>
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
            $get_user_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_transactions WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$get_logged_user_details["username"]."' && batch_number='$batch' ORDER BY date DESC");

            // Some queue items never reach chargeUser() (insufficient balance, blocked
            // phone, gateway error, abuse limit, etc.) and so never get a sas_transactions
            // row at all. Pull those separately so they still show up here instead of
            // silently vanishing while still counting toward "failed" in the badge above.
            $tx_references = array();
            if ($get_user_transaction_details) {
                while ($tx_row = mysqli_fetch_assoc($get_user_transaction_details)) {
                    $tx_references[$tx_row['reference']] = $tx_row;
                }
            }
            $uncharged_queue_items = array();
            $get_queue_items = mysqli_query($connection_server, "SELECT * FROM sas_bulk_queue_items WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$get_logged_user_details["username"]."' && batch_number='$batch' ORDER BY id DESC");
            if ($get_queue_items) {
                while ($qi_row = mysqli_fetch_assoc($get_queue_items)) {
                    if (empty($qi_row['reference']) || !isset($tx_references[$qi_row['reference']])) {
                        $uncharged_queue_items[] = $qi_row;
                    }
                }
            }
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
                <?php if ($bp_status !== "completed"): ?>
                    <small class="text-muted d-block mt-1">Processing in the background — this page refreshes automatically. You don't need to keep it open.</small>
                <?php endif; ?>
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
                            <th>Date/Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (empty($tx_references) && empty($uncharged_queue_items)) {
                            echo '<tr><td colspan="5" class="text-center py-4">No transactions found in this batch.</td></tr>';
                        } else {
                            foreach ($tx_references as $row) {
                                $status_text = tranStatus($row['status']);
                                $status_class = ($row['status'] == 1 ? 'text-success' : ($row['status'] == 2 ? 'text-warning' : 'text-danger'));
                                echo '<tr>
                                    <td><strong>'.$row['product_unique_id'].'</strong><br><small class="text-muted">'.$row['reference'].'</small></td>
                                    <td>₦'.number_format($row['discounted_amount'], 2).'</td>
                                    <td><span class="'.$status_class.' fw-bold">'.$status_text.'</span></td>
                                    <td>'.date('M d, Y H:i:s', strtotime($row['date'])).'</td>
                                    <td><button class="btn btn-primary btn-sm" onclick="showTransactionDetails(\''.$row['reference'].'\')">Details</button></td>
                                </tr>';
                            }
                            // Items rejected before a charge was ever attempted — no transaction
                            // row exists, so surface the queue's own recorded reason instead.
                            // Items still 'pending'/'processing' haven't been attempted yet, so
                            // they aren't failures — just show them as still in progress.
                            foreach ($uncharged_queue_items as $qi_row) {
                                if ($qi_row['status'] === 'done') {
                                    $qi_status_html = '<span class="text-danger fw-bold">Failed</span>';
                                    $qi_desc = htmlspecialchars($qi_row['response_desc'] ?? 'Not processed');
                                } else {
                                    $qi_status_html = '<span class="text-warning fw-bold">'.ucfirst($qi_row['status']).'</span>';
                                    $qi_desc = 'Waiting to be processed';
                                }
                                echo '<tr>
                                    <td><strong>'.htmlspecialchars($qi_row['phone_number']).'</strong></td>
                                    <td>&mdash;</td>
                                    <td>'.$qi_status_html.'</td>
                                    <td>'.($qi_row['processed_at'] ? date('M d, Y H:i:s', strtotime($qi_row['processed_at'])) : '&mdash;').'</td>
                                    <td><small class="text-muted">'.$qi_desc.'</small></td>
                                </tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
    </section>
    <?php include("../func/bc-footer.php"); ?>
</body>
</html>