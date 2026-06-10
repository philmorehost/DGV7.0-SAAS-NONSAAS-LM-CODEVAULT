<?php session_start();
    include("../func/bc-admin-config.php");
    
    if(isset($_GET["order-ref"])){
    	$status = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["order-status"])));
    	$reference = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["order-ref"])));
    	$statusArray = array(1, 2);
    	if(is_numeric($status)){
    		if(in_array($status, $statusArray)){
    			$select_payment_order = mysqli_query($connection_server, "SELECT * FROM sas_submitted_payments WHERE vendor_id='".$get_logged_admin_details["id"]."' && reference='".$reference."'");
    			if(mysqli_num_rows($select_payment_order) == 1){
    				$get_payment_order = mysqli_fetch_array($select_payment_order);
    				if($status == 1){
    					$update_payment_status = mysqli_query($connection_server, "UPDATE sas_submitted_payments SET status='3' WHERE vendor_id='".$get_logged_admin_details["id"]."' && reference='".$reference."'");
    					$json_response_array = array("desc" => ucwords($get_payment_order["username"]." Order with N".toDecimal($get_payment_order["discounted_amount"],2)." rejected successfully"));
    					$json_response_encode = json_encode($json_response_array,true);
    				}
    				
    				if($status == 2){
						if(in_array($get_payment_order["status"], array("2","3"))){
							$purchase_method = "web";
							$purchase_method = strtoupper($purchase_method);
							$user = $get_payment_order["username"];
							$type = "credit";
							$amount = $get_payment_order["amount"];
							$discounted_amount = $get_payment_order["discounted_amount"];
							$type_alternative = ucwords("wallet ".$type);
							$reference_2 = substr(str_shuffle("12345678901234567890"), 0, 15);
							$description = ucwords("account ".$type."ed by admin ( payment order )");
							$transType = $type;
							$credit_other_user = chargeOtherUser($user, $transType, $user, ucwords("wallet ".$type), $reference_2, "", $amount, $discounted_amount, $description, $purchase_method, $_SERVER["HTTP_HOST"], "1");
							if(in_array($credit_other_user, array("success"))){
								$update_payment_status = mysqli_query($connection_server, "UPDATE sas_submitted_payments SET status='1' WHERE vendor_id='".$get_logged_admin_details["id"]."' && reference='".$reference."'");
								$json_response_array = array("desc" => ucwords($get_payment_order["username"]." Credited with N".toDecimal($get_payment_order["discounted_amount"],2)." successfully"));
								$json_response_encode = json_encode($json_response_array,true);
							}
							
							if($credit_other_user == "failed"){
								$json_response_array = array("desc" => "Cannot Proceed Processing Transaction");
								$json_response_encode = json_encode($json_response_array,true);
							}		
							
						}else{
							if(in_array($get_payment_order["status"], array("1"))){
								//Order Amount Had Already Been Deposited To User Account
								$json_response_array = array("desc" => "Order Amount Had Already Been Deposited To User Account");
								$json_response_encode = json_encode($json_response_array,true);
							}
						}
    				}
    			}else{
    				if(mysqli_num_rows($select_payment_order) > 1){
    					//Duplicated Orders
    					$json_response_array = array("desc" => "Duplicated Orders");
    					$json_response_encode = json_encode($json_response_array,true);
    				}else{
    					//Order Not Exists
    					$json_response_array = array("desc" => "Order Not Exists");
    					$json_response_encode = json_encode($json_response_array,true);
    				}
    			}
    		}else{
    			//Invalid Status Code
    			$json_response_array = array("desc" => "Invalid Status Code");
    			$json_response_encode = json_encode($json_response_array,true);
    		}
    	}else{
    		//Non-numeric string
    		$json_response_array = array("desc" => "Non-numeric string");
    		$json_response_encode = json_encode($json_response_array,true);
    	}
    	$json_response_decode = json_decode($json_response_encode,true);
    	$_SESSION["product_purchase_response"] = $json_response_decode["desc"];
    	header("Location: /bc-admin/PaymentOrders.php");
	exit();
    }
?>
<!DOCTYPE html>
<head>
    <title>Payment Orders | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="description" content="<?php echo substr($get_all_super_admin_site_details["site_desc"], 0, 160); ?>" />
    <meta http-equiv="Content-Type" content="text/html; " />
    <meta name="theme-color" content="black" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <meta name="author" content="Philmore Codes">
    <meta name="dc.creator" content="Philmore Codes">
    
          <!-- Vendor CSS Files -->
  <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/remixicon/remixicon.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets-2/css/style.css" rel="stylesheet">

</head>
<body>
	<?php include("../func/bc-admin-header.php"); ?>
	<div class="pagetitle">
      <h1>PAYMENT ORDERS</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Payment Orders</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">
        <div class="col-12">
            <?php
                $searchq = isset($_GET["searchq"]) ? trim(strip_tags($_GET["searchq"])) : "";
                $page_num = (isset($_GET["page"]) && is_numeric($_GET["page"]) && $_GET["page"] >= 1) ? (int)$_GET["page"] : 1;
                $limit = 20;
                $offset = ($page_num - 1) * $limit;

                $search_statement = "";
                $search_parameter = "";
                if(!empty($searchq)){
                    $search_esc = mysqli_real_escape_string($connection_server, $searchq);
                    $search_statement = " AND (reference LIKE '%$search_esc%' OR description LIKE '%$search_esc%' OR username LIKE '%$search_esc%' OR amount LIKE '%$search_esc%')";
                    $search_parameter = "searchq=".urlencode($searchq)."&";
                }

                $get_user_pending_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_submitted_payments WHERE vendor_id='".$get_logged_admin_details["id"]."' && status='2' $search_statement ORDER BY date DESC LIMIT $limit OFFSET $offset");
                $get_user_successful_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_submitted_payments WHERE vendor_id='".$get_logged_admin_details["id"]."' && status='1' $search_statement ORDER BY date DESC LIMIT $limit OFFSET $offset");
                $get_user_failed_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_submitted_payments WHERE vendor_id='".$get_logged_admin_details["id"]."' && status='3' $search_statement ORDER BY date DESC LIMIT $limit OFFSET $offset");
            ?>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-4 border-0">
                    <div class="row align-items-center g-3">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-0 text-primary">Payment Approval Portal</h5>
                            <p class="text-muted small mb-0">Review and approve manual payment submissions</p>
                        </div>
                        <div class="col-md-6">
                            <form method="get" action="PaymentOrders.php" class="d-flex gap-2 justify-content-md-end">
                                <input name="searchq" type="text" value="<?php echo $searchq; ?>" placeholder="User, Ref, Amount..." class="form-control" style="max-width: 250px;" />
                                <button type="submit" class="btn btn-primary px-4 fw-bold">Filter</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-5">
                        <h6 class="fw-bold mb-3 text-warning d-flex align-items-center"><i class="bi bi-hourglass-split me-2"></i>Pending Requests</h6>
                        <div class="table-responsive bg-light rounded-4 border p-2">
                        <?php
                            $query_result = $get_user_pending_transaction_details;
                            $is_admin = true; $is_payment_order = true;
                            include("../func/history-table.php");
                        ?>
                        </div>
                    </div>

                    <div class="mb-5">
                        <h6 class="fw-bold mb-3 text-success d-flex align-items-center"><i class="bi bi-check2-circle me-2"></i>Approved Payments</h6>
                        <div class="table-responsive bg-light rounded-4 border p-2">
                        <?php
                            $query_result = $get_user_successful_transaction_details;
                            $is_admin = true; $is_payment_order = true;
                            include("../func/history-table.php");
                        ?>
                        </div>
                    </div>

                    <div>
                        <h6 class="fw-bold mb-3 text-danger d-flex align-items-center"><i class="bi bi-x-circle me-2"></i>Rejected Payments</h6>
                        <div class="table-responsive bg-light rounded-4 border p-2">
                        <?php
                            $query_result = $get_user_failed_transaction_details;
                            $is_admin = true; $is_payment_order = true;
                            include("../func/history-table.php");
                        ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white py-4 border-0 text-center">
                    <div class="d-flex justify-content-center gap-2">
                        <?php if($page_num > 1): ?>
                        <a href="PaymentOrders.php?<?php echo $search_parameter; ?>page=<?php echo ($page_num - 1); ?>" class="btn btn-outline-primary btn-sm px-4 rounded-pill">Previous Page</a>
                        <?php endif; ?>
                        <a href="PaymentOrders.php?<?php echo $search_parameter; ?>page=<?php echo ($page_num + 1); ?>" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm">Next Page</a>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </section>

		
	<?php include("../func/bc-admin-footer.php"); ?>
	
</body>
</html>