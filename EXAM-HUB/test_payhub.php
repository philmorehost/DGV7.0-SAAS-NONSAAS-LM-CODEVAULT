<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/api_payhub.php';

try {
    $virtual_account = payhub_generate_virtual_account(1);
    var_dump($virtual_account);
} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile();
}
