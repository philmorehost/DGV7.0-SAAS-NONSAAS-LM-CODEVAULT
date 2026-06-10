<?php
header('Content-Type: application/json');
include("app-config.php");

try {
    $headers = apache_request_headers();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $api_key = '';

    if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        $api_key = trim($matches[1]);
    } else {
        $api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
    }

    if (empty($api_key)) {
        throw new Error("Missing API Key");
    }

    $api_key_esc = mysqli_real_escape_string($connection_server, $api_key);
    $user_q = mysqli_query($connection_server, "SELECT id, account_level, vendor_id FROM sas_users WHERE api_key='$api_key_esc' LIMIT 1");
    if (mysqli_num_rows($user_q) == 0) {
        throw new Error("Invalid API Key");
    }
    
    $user = mysqli_fetch_assoc($user_q);
    $vendor_id = $user['vendor_id'];
    $account_level = (int)$user['account_level'];
    
    $table_map = [
        1 => 'sas_smart_parameter_values',
        2 => 'sas_agent_parameter_values',
        3 => 'sas_api_parameter_values'
    ];
    $pricing_table = $table_map[$account_level] ?? 'sas_smart_parameter_values';

    $network = mysqli_real_escape_string($connection_server, $_GET['network'] ?? '');
    $type = mysqli_real_escape_string($connection_server, $_GET['type'] ?? '');

    if (!$network || !$type) {
        throw new Error("Missing network or type parameters");
    }

    $type_map = [
        'dd' => 'dd-data',
        'sme' => 'sme-data',
        'shared' => 'shared-data',
        'cg' => 'cg-data',
        'airtime' => 'airtime',
        'cable' => 'cable',
        'electric' => 'electric',
        'exam' => 'exam',
        'betting' => 'betting'
    ];
    $api_type = $type_map[$type] ?? $type;

    $val_col = in_array($api_type, ['airtime', 'electric', 'betting']) ? 'val_1' : 'val_2';

    $prod_q = mysqli_query($connection_server, "SELECT id FROM sas_products WHERE vendor_id='$vendor_id' AND product_name='$network' LIMIT 1");
    if (mysqli_num_rows($prod_q) == 0) {
        throw new Error("Product $network not found");
    }
    $product_id = mysqli_fetch_assoc($prod_q)['id'];

    $api_ids_q = mysqli_query($connection_server, "SELECT id FROM sas_apis WHERE vendor_id='$vendor_id' AND api_type='$api_type'");
    $api_ids = [];
    while ($arow = mysqli_fetch_assoc($api_ids_q)) {
        $api_ids[] = $arow['id'];
    }

    if (empty($api_ids)) {
        throw new Error("No upstream API configured for $api_type");
    }

    $api_list = implode(',', $api_ids);
    $plans = [];
    $plans_q = mysqli_query($connection_server, "SELECT val_1, $val_col, val_3, val_4 FROM $pricing_table WHERE vendor_id='$vendor_id' AND product_id='$product_id' AND api_id IN ($api_list) AND status=1 GROUP BY val_1");
    
    while ($p = mysqli_fetch_assoc($plans_q)) {
        $plans[] = [
            'name' => !empty($p['val_4']) ? $p['val_4'] : strtoupper($network) . " " . str_replace(["_", "-"], " ", strtoupper($p['val_1'])),
            'code' => $p['val_1'],
            'price' => (float)$p[$val_col],
            'days' => (int)($p['val_3'] ?? 30)
        ];
    }

    usort($plans, function($a, $b) {
        return $a['days'] <=> $b['days'];
    });

    echo json_encode(["success" => true, "plans" => $plans]);

} catch (Error $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
