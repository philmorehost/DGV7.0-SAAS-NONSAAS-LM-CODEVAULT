<?php session_start();
    include("../func/bc-config.php");
	
    if(isset($_POST["submit-sender-id"])){
        $sender_id = mysqli_real_escape_string($connection_server, preg_replace("/[^a-zA-Z]+/","",trim(strip_tags(strtolower($_POST["sender-id"])))));
        $sample_message = mysqli_real_escape_string($connection_server, trim(strip_tags($_POST["sample-message"])));
        
        if(!empty($sender_id) && (strlen($sender_id) >= 3) && (strlen($sender_id) <= 11) && !empty($sample_message) && (strlen($sample_message) >= 1) && (strlen($sample_message) <= 160)) {
            $check_sender_id = mysqli_query($connection_server, "SELECT * FROM sas_bulk_sms_sender_id WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && sender_id='$sender_id'");
            $check_sender_id_by_user = mysqli_query($connection_server, "SELECT * FROM sas_bulk_sms_sender_id WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$get_logged_user_details["username"]."' && sender_id='$sender_id'");
            if(mysqli_num_rows($check_sender_id) == 0){
                mysqli_query($connection_server, "INSERT INTO sas_bulk_sms_sender_id (vendor_id, username, sender_id, sample_message, status) VALUES ('".$get_logged_user_details["vendor_id"]."', '".$get_logged_user_details["username"]."', '$sender_id', '$sample_message', '2')");
                //Sender ID Submitted Successfully For Review
                $json_response_array = array("desc" => "Sender ID Submitted Successfully For Review");
                $json_response_encode = json_encode($json_response_array,true);
            }else{
                if(mysqli_num_rows($check_sender_id) == 1){
                    if(mysqli_num_rows($check_sender_id_by_user) == 1){
                        //Sender ID Already Submitted by You
                        $json_response_array = array("desc" => "Sender ID Already Submitted by You");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                        //Sender ID Already Exists
                        $json_response_array = array("desc" => "Sender ID Already Exists");
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }else{
                    if(mysqli_num_rows($check_sender_id) > 1){
                        //Duplicated Details, Contact Admin
                        $json_response_array = array("desc" => "Duplicated Details, Contact Admin");
                        $json_response_encode = json_encode($json_response_array,true);
                    }
                }
            }
        }else{
            if(empty($sender_id)){
                //Sender ID Field Is Empty
                $json_response_array = array("desc" => "Sender ID Field Is Empty");
                $json_response_encode = json_encode($json_response_array,true); 
            }else{
                if(strlen($sender_id) < 3){
                    //Sender ID Must Not Be Less Than 3 Letter
                    $json_response_array = array("desc" => "Sender ID Must Not Be Less Than 3 Letter");
                    $json_response_encode = json_encode($json_response_array,true);
                }else{
                    if(strlen($sender_id) > 11){
                        //Sender ID Must Not Be Greater Than 11 Letter
                        $json_response_array = array("desc" => "Sender ID Must Not Be Greater Than 11 Letter");
                        $json_response_encode = json_encode($json_response_array,true);
                    }else{
                        if(empty($sample_message)){
                            //Sample Message Field Is Empty
                            $json_response_array = array("desc" => "Sample Message Field Is Empty");
                            $json_response_encode = json_encode($json_response_array,true); 
                        }else{
                            if(strlen($sample_message) < 1){
                                //Sample Message Must Not Be Less Than 1 Character
                                $json_response_array = array("desc" => "Sample Message Must Not Be Less Than 1 Character");
                                $json_response_encode = json_encode($json_response_array,true);
                            }else{
                                if(strlen($sample_message) > 160){
                                    //Sender ID Must Not Be Greater Than 160 Character
                                    $json_response_array = array("desc" => "Sender ID Must Not Be Greater Than 160 Character");
                                    $json_response_encode = json_encode($json_response_array,true);
                                }
                            }
                        }
                    }
                }
            }
        }

        $json_response_decode = json_decode($json_response_encode,true);
        $_SESSION["product_purchase_response"] = $json_response_decode["desc"];
        header("Location: ".$_SERVER["REQUEST_URI"]);
    }

?>
<!DOCTYPE html>
<head>
    <title>Sender ID | <?php echo $get_all_site_details["site_title"]; ?></title>
    <meta charset="UTF-8" />
    <meta name="description" content="<?php echo substr($get_all_site_details["site_desc"], 0, 160); ?>" />
    <meta http-equiv="Content-Type" content="text/html; " />
    <meta name="theme-color" content="black" />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="stylesheet" href="<?php echo $css_style_template_location; ?>">
    <link rel="stylesheet" href="/cssfile/bc-style.css">
    <meta name="author" content="Philmore Codes">
    <meta name="dc.creator" content="Philmore Codes">
    
      <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../assets-2/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets-2/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets-2/vendor/remixicon/remixicon.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets-2/css/style.css" rel="stylesheet">

</head>
<body>
	<?php include("../func/bc-header.php"); ?>	

	<div class="pagetitle">
      <h1>SUBMIT SENDER ID</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Submit Sender ID</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <?php include("../func/service-header.php"); ?>

      <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 p-4 mb-4">
                <h5 class="fw-bold mb-4 text-center">Register New Sender ID</h5>
                <form method="post" action="">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Sender ID (3-11 Letters)</label>
                        <input name="sender-id" type="text" placeholder="e.g BEETECH" pattern="[a-zA-Z]{3,11}" class="form-control form-control-lg fw-bold text-center" required/>
                        <small class="text-muted" style="font-size: 10px;">Only letters allowed. No spaces or special characters.</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted">Sample Message</label>
                        <textarea name="sample-message" rows="3" placeholder="Enter a sample of the message you intend to send" class="form-control" required></textarea>
                    </div>
                    <button name="submit-sender-id" type="submit" class="btn btn-primary btn-lg w-100 shadow-sm fw-bold">
                        SUBMIT FOR REVIEW
                    </button>
                </form>
            </div>
        
		<?php
            
            if(!isset($_GET["searchq"]) && isset($_GET["page"]) && !empty(trim(strip_tags($_GET["page"]))) && is_numeric(trim(strip_tags($_GET["page"]))) && (trim(strip_tags($_GET["page"])) >= 1)){
            	$page_num = mysqli_real_escape_string($connection_server, trim(strip_tags($_GET["page"])));
            	$offset_statement = " OFFSET ".((10 * $page_num) - 10);
            }else{
            	$offset_statement = "";
            }
            
            if(isset($_GET["searchq"]) && !empty(trim(strip_tags($_GET["searchq"])))){
                $search_statement = " && (sender_id LIKE '%".trim(strip_tags($_GET["searchq"]))."%')";
                $search_parameter = "searchq=".trim(strip_tags($_GET["searchq"]))."&&";
            }else{
                $search_statement = "";
                $search_parameter = "";
            }
            $get_user_transaction_details = mysqli_query($connection_server, "SELECT * FROM sas_bulk_sms_sender_id WHERE vendor_id='".$get_logged_user_details["vendor_id"]."' && username='".$_SESSION["user_session"]."' $search_statement ORDER BY date DESC LIMIT 10 $offset_statement");
            
        ?>
            <div class="card shadow-sm border-0 overflow-hidden rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">Your Sender IDs</h6>
                    <form method="get" action="SubmitSenderID.php" class="d-flex gap-2">
                        <input name="searchq" type="text" value="<?php echo trim(strip_tags($_GET["searchq"])); ?>" placeholder="Search ID..." class="form-control form-control-sm rounded-pill px-3" />
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Search</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0 px-4">Sender ID</th>
                                <th class="border-0">Sample Message</th>
                                <th class="border-0">Status</th>
                                <th class="border-0 text-end px-4">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($get_user_transaction_details) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($get_user_transaction_details)):
                                    $status_class = ($row['status'] == 1 ? 'text-success' : ($row['status'] == 2 ? 'text-warning' : 'text-danger'));
                                ?>
                                    <tr>
                                        <td class="px-4 fw-bold text-primary"><?php echo strtoupper($row["sender_id"]); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($row["sample_message"]); ?></td>
                                        <td class="small <?php echo $status_class; ?> fw-bold"><?php echo tranStatus($row["status"]); ?></td>
                                        <td class="text-end px-4 small text-muted"><?php echo date("M d, Y", strtotime($row["date"])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">No Sender IDs found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white py-3 border-0 d-flex justify-content-between">
                    <div>
                        <?php if(isset($_GET["page"]) && $_GET["page"] > 1): ?>
                            <a href="SubmitSenderID.php?<?php echo $search_parameter; ?>page=<?php echo ($_GET["page"] - 1); ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">Previous</a>
                        <?php endif; ?>
                    </div>
                    <?php
                        $next_page = ($_GET["page"] ?? 1) + 1;
                    ?>
                    <a href="SubmitSenderID.php?<?php echo $search_parameter; ?>page=<?php echo $next_page; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">Next</a>
                </div>
            </div>
        </div>
      </div>
    </section>
	<?php include("../func/bc-footer.php"); ?>
	
</body>
</html>