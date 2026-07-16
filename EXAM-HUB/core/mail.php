<?php
function send_transactional_email($to, $subject, $body) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: EXAM-HUB <noreply@' . $_SERVER['HTTP_HOST'] . '>' . "\r\n";
    
    // Check if we have an overriding template layout in the DB (mock logic)
    $full_body = "
    <div style='font-family: Arial, sans-serif; background-color: #f3f4f6; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
            <h2 style='color: #2563eb; border-bottom: 2px solid #eff6ff; padding-bottom: 10px;'>EXAM-HUB</h2>
            <div style='color: #374151; font-size: 16px; line-height: 1.6;'>
                $body
            </div>
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center;'>
                &copy; " . date('Y') . " EXAM-HUB. All rights reserved.
            </div>
        </div>
    </div>
    ";

    return mail($to, $subject, $full_body, $headers);
}
