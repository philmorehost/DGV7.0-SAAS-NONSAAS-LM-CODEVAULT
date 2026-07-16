<?php
$zip = new ZipArchive;
$zip_path = 'c:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-NON-SAAS.zip';
if ($zip->open($zip_path) === TRUE) {
    echo "Total files: " . $zip->numFiles . "\n";
    for ($i = 0; $i < min($zip->numFiles, 30); $i++) {
        echo $zip->getNameIndex($i) . "\n";
    }
    if ($zip->numFiles > 30) {
        echo "... and " . ($zip->numFiles - 30) . " more files.\n";
    }
    $zip->close();
} else {
    echo "Failed to open zip file.\n";
}
