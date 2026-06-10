<?php session_start();
    include("../func/bc-config.php");

    if(!isset($_GET["batch"]) || empty(trim(strip_tags($_GET["batch"])))){
        header("Location: BatchTransactions.php");
        exit();
    }

    $batch = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["batch"])));
?>
<!DOCTYPE html>
<head>
    <title>Batch Details | <?php echo $get_all_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
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
        ?>
        <div class="card info-card px-5 py-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="card-title mb-0">Transactions in Batch: <?php echo $batch; ?></h5>
                <a href="BatchTransactions.php" class="btn btn-secondary btn-sm">Back to Batches</a>
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
                        if(mysqli_num_rows($get_user_transaction_details) >= 1){
                            while($row = mysqli_fetch_assoc($get_user_transaction_details)){
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
                        } else {
                            echo '<tr><td colspan="5" class="text-center py-4">No transactions found in this batch.</td></tr>';
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