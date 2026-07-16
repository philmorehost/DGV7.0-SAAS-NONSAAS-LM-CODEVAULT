<?php
require_once 'includes/functions.php';
header('Content-Type: application/json');
$res = paystack_call('dedicated_account/available_providers', 'GET', [], false);
echo json_encode($res, JSON_PRETTY_PRINT);
