<?php session_start();
    include("../func/bc-admin-config.php");
    
    if(isset($_GET["sender-id"])){
    	$status = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["sender-id-status"])));
    	$sender_id = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["sender-id"])));
    	$statusArray = array(1, 2, 3);
    	if(is_numeric($status)){
    		if(in_array($status, $statusArray)){
    			$select_sender_id = mysqli_query($connection_server, "SELECT * FROM sas_bulk_sms_sender_id WHERE vendor_id='".$get_logged_admin_details["id"]."' && sender_id='".$sender_id."'");
    			if(mysqli_num_rows($select_sender_id) == 1){
    				$get_sender_id = mysqli_fetch_array($select_sender_id);
    				if($status == 1){
    					$update_sender_id_status = mysqli_query($connection_server, "UPDATE sas_bulk_sms_sender_id SET status='3' WHERE vendor_id='".$get_logged_admin_details["id"]."' && sender_id='".$sender_id."'");
    					$json_response_array = array("desc" => ucwords($get_sender_id["username"]."(".$sender_id.") Sender ID rejected successfully"));
    					$json_response_encode = json_encode($json_response_array,true);
    				}
    				
    				if($status == 2){
						$update_sender_id_status = mysqli_query($connection_server, "UPDATE sas_bulk_sms_sender_id SET status='1' WHERE vendor_id='".$get_logged_admin_details["id"]."' && sender_id='".$sender_id."'");
						$json_response_array = array("desc" => ucwords($get_sender_id["username"]."(".$sender_id.") Approved successfully"));
						$json_response_encode = json_encode($json_response_array,true);
    				}

					if($status == 3){
						$update_sender_id_status = mysqli_query($connection_server, "UPDATE sas_bulk_sms_sender_id SET status='2' WHERE vendor_id='".$get_logged_admin_details["id"]."' && sender_id='".$sender_id."'");
						$json_response_array = array("desc" => ucwords($get_sender_id["username"]."(".$sender_id.") Disabled successfully"));
						$json_response_encode = json_encode($json_response_array,true);
    				}
    			}else{
    				if(mysqli_num_rows($select_sender_id) > 1){
    					//Duplicated Sender ID
    					$json_response_array = array("desc" => "Duplicated Sender ID");
    					$json_response_encode = json_encode($json_response_array,true);
    				}else{
    					//Sender ID Not Exists
    					$json_response_array = array("desc" => "Sender ID Not Exists");
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
    	header("Location: /bc-admin/SenderIDRequests.php");
    }
?>
<!DOCTYPE html>
<head>
    <title>Sender ID Requests | <?php echo $get_all_super_admin_site_details["site_title"]; ?></title>
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
      <div class="col-12">

        <?php
            
            if(!isset($_GET["searchq"]) && isset($_GET["page"]) && !empty(trim(strip_tags($_GET["page"]))) && is_numeric(trim(strip_tags($_GET["page"]))) && (trim(strip_tags($_GET["page"])) >= 1)){
            	$page_num = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["page"])));
            	$offset_statement = " OFFSET ".((10 * $page_num) - 10);
            }else{
            	$offset_statement = "";
            }
            
            if(isset($_GET["searchq"]) && !empty(trim(strip_tags($_GET["searchq"])))){
                $search_statement = " && (sender_id LIKE '%".trim(strip_tags($_GET["searchq"]))."%' OR username LIKE '%".trim(strip_tags($_GET["searchq"]))."%')";
                $search_parameter = "searchq=".trim(strip_tags($_GET["searchq"]))."&&";
            }else{
                $search_statement = "";
                $search_parameter = "";
            }
            $get_user_pending_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_bulk_sms_sender_id WHERE vendor_id='".$get_logged_admin_details["id"]."' && status='2' $search_statement ORDER BY date DESC LIMIT 10 $offset_statement");
            $get_user_successful_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_bulk_sms_sender_id WHERE vendor_id='".$get_logged_admin_details["id"]."' && status='1' $search_statement ORDER BY date DESC LIMIT 10 $offset_statement");
            $get_user_failed_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_bulk_sms_sender_id WHERE vendor_id='".$get_logged_admin_details["id"]."' && status='3' $search_statement ORDER BY date DESC LIMIT 10 $offset_statement");
            
        ?>
        <div class="card info-card px-5 py-5">
            <div class="row mb-3">
                <form method="get" action="SenderIDRequests.php" class="m-margin-tp-1 s-margin-tp-1">
                    <input style="user-select: auto;" name="searchq" type="text" value="<?php echo trim(strip_tags($_GET["searchq"])); ?>" placeholder="Sender ID, Username e.t.c" class="form-control mt-3" />
                    <button style="user-select: auto;" type="submit" class="btn btn-primary d-inline col-12 col-lg-auto my-2" >
                        <i class="bi bi-search"></i> Search
                    </button>
                </form>
            </div>

            <span style="user-select: auto;" class="fw-bold h4">PENDING REQUEST</span><br>
			
            <div style="user-select: auto; cursor: grab;" class="overflow-auto mt-1">
              <table style="" class="table table-responsive table-striped table-bordered" title="Horizontal Scroll: Shift + Mouse Scroll Button">
                  <thead class="thead-dark">
                    <tr>
                    	<th>S/N</th><th>Username ID</th><th>Sender ID</th><th>Sample Message</th><th>Status</th><th>Date</th><th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    if(mysqli_num_rows($get_user_pending_transaction_details) >= 1){
                    	while($user_transaction = mysqli_fetch_assoc($get_user_pending_transaction_details)){
                    		$transaction_type = ucwords($user_transaction["type_alternative"]);
                    		$countTransaction += 1;
                    		$reject_sender_id = '<span onclick="adminSenderIDStatus(`1`,`'.$user_transaction["sender_id"].'`,`'.$user_transaction["username"].'`);" style="text-decoration: underline; color: red;" class="a-cursor">Reject Request</span>';
                    		$accept_sender_id = '<span onclick="adminSenderIDStatus(`2`,`'.$user_transaction["sender_id"].'`,`'.$user_transaction["username"].'`);" style="text-decoration: underline; color: green;" class="a-cursor">Accept Request</span>';
                    		$all_sender_id_action = $reject_sender_id." | ".$accept_sender_id;
                    		echo 
                    		'<tr>
                    			<td>'.$countTransaction.'</td><td>'.ucwords($user_transaction["username"]).'</td><td>'.$user_transaction["sender_id"].'</td><td>'.$user_transaction["sample_message"].'</td><td>'.tranStatus($user_transaction["status"]).'</td><td>'.formDate($user_transaction["date"]).'</td><td>'.$all_sender_id_action.'</td>
                    		</tr>';
                    	}
                    }
                    ?>
                  </tbody>
                </table>
            </div><br/>

            <span style="user-select: auto;" class="fw-bold h4">SUCCESSFUL REQUEST</span><br>
			
            <div style="user-select: auto; cursor: grab;" class="overflow-auto mt-1">
              <table style="" class="table table-responsive table-striped table-bordered" title="Horizontal Scroll: Shift + Mouse Scroll Button">
                  <thead class="thead-dark">
                    <tr>
                    	<th>S/N</th><th>Username ID</th><th>Sender ID</th><th>Sample Message</th><th>Status</th><th>Date</th><th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    if(mysqli_num_rows($get_user_successful_transaction_details) >= 1){
                    	while($user_transaction = mysqli_fetch_assoc($get_user_successful_transaction_details)){
                    		$transaction_type = ucwords($user_transaction["type_alternative"]);
                    		$countTransaction += 1;
                    		$disable_sender_id = '<span onclick="adminSenderIDStatus(`3`,`'.$user_transaction["sender_id"].'`,`'.$user_transaction["username"].'`);" style="text-decoration: underline; color: green;" class="a-cursor">Disable Request</span>';
                    		$reject_sender_id = '<span onclick="adminSenderIDStatus(`1`,`'.$user_transaction["sender_id"].'`,`'.$user_transaction["username"].'`);" style="text-decoration: underline; color: red;" class="a-cursor">Reject Request</span>';
                    		$all_sender_id_action = $disable_sender_id." | ".$reject_sender_id;
                    		echo 
                    		'<tr>
                    			<td>'.$countTransaction.'</td><td>'.ucwords($user_transaction["username"]).'</td><td>'.$user_transaction["sender_id"].'</td><td>'.$user_transaction["sample_message"].'</td><td>'.tranStatus($user_transaction["status"]).'</td><td>'.formDate($user_transaction["date"]).'</td><td>'.$all_sender_id_action.'</td>
                    		</tr>';
                    	}
                    }
                    ?>
                  </tbody>
                </table>
            </div><br/>

            <span style="user-select: auto;" class="fw-bold h4">REJECTED REQUEST</span><br>
			
            <div style="user-select: auto; cursor: grab;" class="overflow-auto mt-1">
              <table style="" class="table table-responsive table-striped table-bordered" title="Horizontal Scroll: Shift + Mouse Scroll Button">
                  <thead class="thead-dark">
                    <tr>
                    	<th>S/N</th><th>Username ID</th><th>Sender ID</th><th>Sample Message</th><th>Status</th><th>Date</th><th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    if(mysqli_num_rows($get_user_failed_transaction_details) >= 1){
                    	while($user_transaction = mysqli_fetch_assoc($get_user_failed_transaction_details)){
                    		$transaction_type = ucwords($user_transaction["type_alternative"]);
                    		$countTransaction += 1;
                    		$disable_sender_id = '<span onclick="adminSenderIDStatus(`3`,`'.$user_transaction["sender_id"].'`,`'.$user_transaction["username"].'`);" style="text-decoration: underline; color: green;" class="a-cursor">Disable Request</span>';
                    		$accept_sender_id = '<span onclick="adminSenderIDStatus(`2`,`'.$user_transaction["sender_id"].'`,`'.$user_transaction["username"].'`);" style="text-decoration: underline; color: green;" class="a-cursor">Accept Request</span>';
                    		$all_sender_id_action = $disable_sender_id." | ".$accept_sender_id;
                    		echo 
                    		'<tr>
                    			<td>'.$countTransaction.'</td><td>'.ucwords($user_transaction["username"]).'</td><td>'.$user_transaction["sender_id"].'</td><td>'.$user_transaction["sample_message"].'</td><td>'.tranStatus($user_transaction["status"]).'</td><td>'.formDate($user_transaction["date"]).'</td><td>'.$all_sender_id_action.'</td>
                    		</tr>';
                    	}
                    }
                    ?>
                  </tbody>
                </table>
            </div><br/>
            
            <div class="mt-2 justify-content-between justify-items-center">
                <?php if(isset($_GET["page"]) && is_numeric(trim(strip_tags($_GET["page"]))) && (trim(strip_tags($_GET["page"])) > 1)){ ?>
                <a href="SenderIDRequests.php?<?php echo $search_parameter; ?>page=<?php echo (trim(strip_tags($_GET["page"])) - 1); ?>">
                    <button style="user-select: auto;" class="btn btn-primary col-auto">Prev</button>
                </a>
                <?php } ?>
                <?php
                	if(isset($_GET["page"]) && is_numeric(trim(strip_tags($_GET["page"]))) && (trim(strip_tags($_GET["page"])) >= 1)){
                		$trans_next = (trim(strip_tags($_GET["page"])) +1);
                	}else{
                		$trans_next = 2;
                	}
                ?>
                <a href="SenderIDRequests.php?<?php echo $search_parameter; ?>page=<?php echo $trans_next; ?>">
                    <button style="user-select: auto;" class="btn btn-primary col-auto">Next</button>
                </a>
            </div>
        </div>
      </div>
    </section>

		
	<?php include("../func/bc-admin-footer.php"); ?>
	
</body>
</html>