<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';

function customBCMailSender($from,$to,$subject,$message,$headers, $background = true){
    global $connection_server;

    // Branch DG6.7 Optimization: Optionally skip blocking SMTP for login notifications
    // if requested, or if the server is known to be slow.
    // For now, we will use a shorter timeout to prevent "everlasting" loads.

	$smtpMAIL = new PHPMailer(true);
    $sent = false;
	try {
         // Resolve SMTP Context (Vendor vs Platform)
         $vid = resolveVendorID();
         $smtp_config = null;

         if ($vid > 0) {
             $q = mysqli_query($connection_server, "SELECT smtp_host, smtp_user, smtp_pass, smtp_port, smtp_sec FROM sas_vendors WHERE id='$vid' LIMIT 1");
             if ($q && $r = mysqli_fetch_assoc($q)) {
                 if (!empty($r['smtp_host']) && !empty($r['smtp_user'])) $smtp_config = $r;
             }
         }

         if (!$smtp_config) {
             $q = mysqli_query($connection_server, "SELECT smtp_host, smtp_user, smtp_pass, smtp_port, smtp_sec FROM sas_super_admin LIMIT 1");
             if ($q && $r = mysqli_fetch_assoc($q)) {
                 if (!empty($r['smtp_host']) && !empty($r['smtp_user'])) $smtp_config = $r;
             }
         }

		 //Server settings
		$smtp_host = $smtp_config['smtp_host'] ?? 'mail.cheaperdata.com.ng';
        $smtp_user = $smtp_config['smtp_user'] ?? 'notification@cheaperdata.com.ng';
        $smtp_pass = $smtp_config['smtp_pass'] ?? '';
        $smtp_port = (int)($smtp_config['smtp_port'] ?? 25);
        $smtp_sec = $smtp_config['smtp_sec'] ?? 'tls';

		$smtpMAIL->isSMTP();
		$smtpMAIL->Host = $smtp_host;
		$smtpMAIL->SMTPAuth = true;
		if ($smtp_sec == 'ssl') $smtpMAIL->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        elseif ($smtp_sec == 'tls') $smtpMAIL->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

		$smtpMAIL->Port = $smtp_port;
        $smtpMAIL->CharSet = 'UTF-8';
        $smtpMAIL->Timeout = 7; // Branch DG6.7 Optimization: Shorter timeout for faster failover
		
		$smtpMAIL->Username = $smtp_user;
		$smtpMAIL->Password = $smtp_pass;
		
		 //Sender and recipient settings
		$smtpMAIL->setFrom($smtp_user, "System Notification");
		$smtpMAIL->addAddress($to);
		$smtpMAIL->addReplyTo($smtp_user);

        // Extract Cc from headers if present
        if (preg_match('/Cc:\s*([^\r\n]+)/i', $headers, $cc_matches)) {
            $cc_emails = explode(',', $cc_matches[1]);
            foreach($cc_emails as $cc_email) {
                $cc_email = trim($cc_email);
                if (!empty($cc_email)) $smtpMAIL->addCC($cc_email);
            }
        }
		
		 //Setting the email content
		$smtpMAIL->IsHTML(true);
        $smtpMAIL->Encoding = 'base64'; // Fix "lines too long" error
		$smtpMAIL->Subject = $subject;
		$smtpMAIL->Body = $message;
		$smtpMAIL->AltBody = strip_tags($message);
		$sent = $smtpMAIL->send();
	} catch (Exception $e) {
        // Fallback to Inbuilt Mail Functions
	    $sent = mail($to,$subject,$message,$headers);

	}
    return $sent;
}

function sendEmailWithAttachments($to, $subject, $message, $from_name, $from_email, $attachments = array()) {
    global $connection_server;
    $smtpMAIL = new PHPMailer(true);
    $sent = false;
    try {
        // Resolve SMTP Context (Vendor vs Platform)
        $vid = resolveVendorID();
        $smtp_config = null;

        if ($vid > 0) {
            $q = mysqli_query($connection_server, "SELECT smtp_host, smtp_user, smtp_pass, smtp_port, smtp_sec FROM sas_vendors WHERE id='$vid' LIMIT 1");
            if ($q && $r = mysqli_fetch_assoc($q)) {
                if (!empty($r['smtp_host']) && !empty($r['smtp_user'])) $smtp_config = $r;
            }
        }

        if (!$smtp_config) {
            $q = mysqli_query($connection_server, "SELECT smtp_host, smtp_user, smtp_pass, smtp_port, smtp_sec FROM sas_super_admin LIMIT 1");
            if ($q && $r = mysqli_fetch_assoc($q)) {
                if (!empty($r['smtp_host']) && !empty($r['smtp_user'])) $smtp_config = $r;
            }
        }

        $smtp_host = $smtp_config['smtp_host'] ?? 'mail.cheaperdata.com.ng';
        $smtp_user = $smtp_config['smtp_user'] ?? 'notification@cheaperdata.com.ng';
        $smtp_pass = $smtp_config['smtp_pass'] ?? '';
        $smtp_port = (int)($smtp_config['smtp_port'] ?? 25);
        $smtp_sec = $smtp_config['smtp_sec'] ?? 'tls';

        $smtpMAIL->isSMTP();
        $smtpMAIL->Host = $smtp_host;
        $smtpMAIL->SMTPAuth = true;
        if ($smtp_sec == 'ssl') $smtpMAIL->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        elseif ($smtp_sec == 'tls') $smtpMAIL->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $smtpMAIL->Port = $smtp_port;
        $smtpMAIL->CharSet = 'UTF-8';
        $smtpMAIL->Timeout = 20;

        $smtpMAIL->Username = $smtp_user;
        $smtpMAIL->Password = $smtp_pass;

        $smtpMAIL->setFrom($smtp_user, $from_name);
        $smtpMAIL->addAddress($to);
        $smtpMAIL->addReplyTo($from_email);

        $smtpMAIL->IsHTML(true);
        $smtpMAIL->Encoding = 'base64'; // Fix "lines too long" error
        $smtpMAIL->Subject = $subject;
        $smtpMAIL->Body = $message;
        $smtpMAIL->AltBody = strip_tags($message);

        foreach ($attachments as $file) {
            if (file_exists($file)) {
                $smtpMAIL->addAttachment($file);
            }
        }

        $sent = $smtpMAIL->send();
    } catch (Exception $e) {
        // Fallback
        $boundary = md5(time());
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: $from_name <$from_email>\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        $sent = mail($to, $subject, $message, $headers);

    }
    return $sent;
}
