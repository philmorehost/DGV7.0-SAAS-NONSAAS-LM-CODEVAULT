<?php error_reporting(0);
include_once("app-config.php");

header("Content-Type: application/json");
$incoming_post_request = file_get_contents("php://input");
fwrite(fopen("forgot.txt", "a"), $incoming_post_request . "\n\n");
$decode_post_request = json_decode($incoming_post_request, true);
if (json_last_error() === JSON_ERROR_NONE) {

    //Select Vendor Table
    $vendor_id = resolveVendorID();
    $select_vendor_table = mysqli_fetch_array(mysqli_query($connection_server, "SELECT * FROM sas_vendors WHERE id='$vendor_id' AND status=1 LIMIT 1"));
    if ($select_vendor_table) {

        $username = mysqli_real_escape_string($connection_server, trim(strip_tags($decode_post_request["username"])));

        $status_update = "failed";
        $status_msg = "Unknown Error";

        if (
            !empty($username)
        ) {
            $checkuser = mysqli_query($connection_server, "SELECT * FROM sas_users WHERE vendor_id = '" . $select_vendor_table["id"] . "' AND (username = '" . $username . "' OR email='" . $username . "')");
            if (mysqli_num_rows($checkuser) == 1) {
                $get_user_detail = mysqli_fetch_array($checkuser);
                $new_user_password = substr(str_shuffle("1234567890abcdefghijklmnopqrstuvwxyz"), 0, 10);
                $new_user_password_hashed = md5($new_user_password);

                $update_user_password = mysqli_query($connection_server, "UPDATE sas_users SET `password`='" . $new_user_password_hashed . "' WHERE vendor_id = '" . $select_vendor_table["id"] . "' AND (username = '" . $username . "' OR email='" . $username . "')");
                if ($update_user_password === true) {
                    // Email Beginning
                    $login_template_encoded_text_array = array("{firstname}" => $get_user_detail["firstname"], "{lastname}" => $get_user_detail["lastname"], "{password}" => $new_user_password);
                    $raw_login_template_subject = getUserEmailTemplate('user-auto-pass-generate', 'subject');
                    $raw_login_template_body = getUserEmailTemplate('user-auto-pass-generate', 'body');
                    foreach ($login_template_encoded_text_array as $array_key => $array_val) {
                        $raw_login_template_subject = str_replace($array_key, $array_val, $raw_login_template_subject);
                        $raw_login_template_body = str_replace($array_key, $array_val, $raw_login_template_body);
                    }

                    beeMailer($get_user_detail["email"], $raw_login_template_subject, $raw_login_template_body);
                    // Email End

                    $status_update = "success";
                    $status_msg = "New Password sent to " . $get_user_detail["email"];
                } else {
                    $status_msg = "Err: Unable to reset password";
                }
            } else {
                $status_msg = "Invalid Username or email";
            }
        } else {
            if (empty($username)) {
                $status_msg = "Empty Username";
            }
        }
        // fwrite(fopen("login.txt", "a"), $status_msg . "\n\n");

        $app_json = json_encode(array("json-status" => "success", "status" => $status_update, "status-msg" => $status_msg), true);
        // fwrite(fopen("login.txt", "a"), $app_json . "\n\n");
    } else {
        //Website not registered
        $app_json = json_encode(array("json-status" => "failed", "status" => "failed", "status-msg" => "Website not registered"), true);
    }
} else {
    $app_json = json_encode(array("json-status" => "success", "status" => "failed", "status-msg" => "Bad Request"), true);
}


echo $app_json;
?>