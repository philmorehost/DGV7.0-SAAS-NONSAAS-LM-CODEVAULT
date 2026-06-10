<?php
function mailDesignTemplate($title, $message, $details_array, $show_app = true) {
    global $connection_server;

    // If the message is already a full HTML document (e.g. from GrapesJS), return it as is
    if (strpos($message, '<html') !== false || strpos($message, '<body') !== false) {
        return $message;
    }

    if (!empty(trim(strip_tags($title)))) {
        $mail_title = trim(strip_tags($title));
    } else {
        $mail_title = "Notification";
    }
    $mail_message = str_replace("\n", "<br/>", trim($message));

    $website_url = $_SERVER["HTTP_HOST"];
    $website_logo_url = "https://" . $website_url . "/uploaded-image/" . str_replace([":", "."], "-", $website_url) . "_logo.png";
    $primary_color = "#287bff";

    // Fetch active services for dynamic advertising
    $vendor_host = mysqli_real_escape_string($connection_server, $website_url);
    $vendor_q = mysqli_query($connection_server, "SELECT id FROM sas_vendors WHERE website_url='$vendor_host' LIMIT 1");
    $vendor_id = ($row = mysqli_fetch_assoc($vendor_q)) ? $row['id'] : 0;

    $services_list = [
        'data' => ['label' => 'Data Bundle', 'icon' => 'data.png', 'link' => '/web/Data.php'],
        'airtime' => ['label' => 'Airtime VTU', 'icon' => 'airtime.png', 'link' => '/web/Airtime.php'],
        'cable' => ['label' => 'Cable TV', 'icon' => 'cable.png', 'link' => '/web/Cable.php'],
        'electric' => ['label' => 'Electricity', 'icon' => 'electricity.png', 'link' => '/web/Electric.php'],
        'betting' => ['label' => 'Betting Fund', 'icon' => 'money.png', 'link' => '/web/Betting.php'],
        'exam' => ['label' => 'Exam PIN', 'icon' => 'exam.png', 'link' => '/web/Exam.php'],
        'bulk_sms' => ['label' => 'Bulk SMS', 'icon' => 'sms.png', 'link' => '/web/BulkSMS.php'],
        'virtual_card' => ['label' => 'Virtual Card', 'icon' => 'mastercard.png', 'link' => '/web/VirtualCard.php'],
        'gift_card' => ['label' => 'Gift Cards', 'icon' => 'gift-card/amazon.png', 'link' => '/web/GiftCard.php'],
        'crypto_hub' => ['label' => 'Crypto Hub', 'icon' => 'crypto/btc.png', 'link' => '/web/CryptoHub.php'],
        'data_card' => ['label' => 'Print Hub', 'icon' => 'exam.png', 'link' => '/web/PrintHub.php'],
        'withdraw' => ['label' => 'Wallet to Bank', 'icon' => 'money.png', 'link' => '/web/SendFund.php'],
    ];

    $services_html = '';
    if ($vendor_id > 0) {
        $sc_q = mysqli_query($connection_server, "SELECT service_name, status FROM sas_service_control WHERE vendor_id='$vendor_id'");
        $sc_settings = [];
        if ($sc_q) {
            while ($sc_r = mysqli_fetch_assoc($sc_q)) $sc_settings[$sc_r['service_name']] = $sc_r['status'];
        }

        $active_services = [];
        foreach ($services_list as $key => $data) {
            if (!isset($sc_settings[$key]) || $sc_settings[$key] == 1) {
                $active_services[] = $data;
            }
        }

        if (!empty($active_services)) {
            $services_html .= '<tr><td style="padding: 20px 0 10px 0; border-top: 1px solid #eee; text-align: center;">';
            $services_html .= '<h4 style="color: ' . $primary_color . '; margin: 0 0 15px 0; font-family: sans-serif;">Quick Services</h4>';
            $services_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">';
            foreach ($active_services as $adv) {
                $services_html .= '<a href="https://' . $website_url . $adv['link'] . '" style="display: inline-block; padding: 6px 12px; background-color: #f0f7ff; border: 1px solid #cce3ff; border-radius: 6px; text-decoration: none; color: #0056b3; font-size: 12px; margin: 4px; font-family: sans-serif; font-weight: bold;">';
                $services_html .= '<img src="https://' . $website_url . '/asset/' . $adv['icon'] . '" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 5px;"> ' . $adv['label'];
                $services_html .= '</a>';
            }
            $services_html .= '</td></tr></table></td></tr>';
        }
    }

    $app_section_html = "";
    if ($show_app) {
        $app_section_html = '<tr>
                <td style="padding: 20px 25px; background-color: #f8fafc; text-align: center; border-top: 1px solid #f1f5f9;">
                    <p style="margin: 0; font-size: 14px; color: #64748b; font-weight: bold;">DOWNLOAD AND INSTALL APP</p>
                    <div style="margin-top: 10px;">
                         <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" height="40"></a>
                    </div>
                </td>
            </tr>';
    }

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $mail_title . '</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f7fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f7fa; padding-bottom: 40px; }
        .main { background-color: #ffffff; margin: 0 auto; width: 100%; max-width: 600px; border-spacing: 0; color: #1e293b; border-radius: 12px; overflow: hidden; margin-top: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background-color: #ffffff; padding: 25px; text-align: center; border-bottom: 1px solid #f1f5f9; }
        .content { padding: 30px 25px; line-height: 1.6; }
        .footer { padding: 20px; text-align: center; color: #64748b; font-size: 12px; }
        .button { display: inline-block; padding: 12px 24px; background-color: ' . $primary_color . '; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px; }
        @media only screen and (max-width: 600px) {
            .main { width: 95% !important; margin-top: 10px !important; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table class="main" role="presentation">
            <tr>
                <td class="header">
                    <img src="' . $website_logo_url . '" alt="' . $website_url . '" style="max-height: 60px; width: auto;">
                </td>
            </tr>
            <tr>
                <td class="content">
                    <h2 style="margin-top: 0; color: #0f172a; font-size: 20px;">' . $mail_title . '</h2>
                    <div style="color: #475569; font-size: 16px;">
                        ' . $mail_message . '
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="https://' . $website_url . '/web/Dashboard.php" class="button">Go to Dashboard</a>
                    </div>
                </td>
            </tr>
            ' . $services_html . '
            ' . $app_section_html . '
            <tr>
                <td style="padding: 20px 25px; background-color: #f8fafc; text-align: center; border-top: 1px solid #f1f5f9;">
                    <p style="margin: 0; font-size: 14px; color: #64748b; font-weight: bold;">Need Help?</p>
                    <div style="margin-top: 10px;">
                        <a href="https://wa.me/' . ($details_array[0] ?? "") . '" style="text-decoration: none; color: #16a34a; font-size: 14px; font-weight: bold; margin: 0 10px;">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" width="16" style="vertical-align: middle; margin-right: 5px;"> WhatsApp Support
                        </a>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="footer">
                    <p style="margin-bottom: 10px;">&copy; ' . date("Y") . ' ' . $website_url . '. All rights reserved.</p>
                    <p style="margin: 0;">You received this email because you are a registered member of our platform.</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>';

    return $html;
}
