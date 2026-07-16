<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

try {
    $pdo = get_db_connection();
    
    // Check if columns exist
    $columns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('active_provider', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN active_provider VARCHAR(50) DEFAULT 'vtpass'");
        echo "Added active_provider\n";
    }
    
    if (!in_array('vtpass_id', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN vtpass_id VARCHAR(100) DEFAULT ''");
        echo "Added vtpass_id\n";
    }
    
    if (!in_array('clubkonnect_id', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN clubkonnect_id VARCHAR(100) DEFAULT ''");
        echo "Added clubkonnect_id\n";
    }
    
    if (!in_array('naijaresultpins_id', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN naijaresultpins_id VARCHAR(100) DEFAULT ''");
        echo "Added naijaresultpins_id\n";
    }
    
    if (!in_array('logo', $columns)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN logo VARCHAR(255) DEFAULT ''");
        echo "Added logo\n";
    }

    echo "Migration complete.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
