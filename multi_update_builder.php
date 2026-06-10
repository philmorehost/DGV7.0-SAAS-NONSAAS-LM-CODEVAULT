<?php
// multi_update_builder.php
// Local builder script to compile OTA update packages for SAAS and NON-SAAS tiers

// === CONFIGURATION ===
$old_version_dir = __DIR__ . '/old_version'; // Previous version path
$new_version_dir = __DIR__ . '/DGV7.0-SAAS'; // Master codebase path (using SAAS as master)
$new_version_num = '7.01'; // Target version number

// Base ignore list for both builds (e.g. git, logs, mobile apps, updates directories)
$base_ignore_list = [
    '.git',
    '.github',
    '.gitattributes',
    '.gitignore',
    'node_modules',
    '.env',
    'error_log',
    'DG6-Android',
    'DG6-iOS',
    'MZEEVTU',
    'MZEEVTU-iOS',
    'PayHub-Android',
    'PayHub-iOS',
    'logs',
    'scratch',
    'uploaded-image',
    'build_saas',
    'build_nonsaas',
    'multi_update_builder.php'
];

// Tier profiles configuration
$build_profiles = [
    'SAAS' => [
        'output_dir' => __DIR__ . '/build_saas',
        'exclude'    => [] // SAAS tier receives all files
    ],
    'NON-SAAS' => [
        'output_dir' => __DIR__ . '/build_nonsaas',
        'exclude'    => [
            // List of SAAS premium management files/folders to strip out
            'bc-spadmin/',
            'select-vendor.php',
            'VendorOrderPortal.php',
            'spadmin-logout.php',
            'Dashboard.php',
            'DownloadService.php'
        ]
    ]
];

// Command Line Argument overrides
if (php_sapi_name() === 'cli') {
    if (isset($argv[1])) {
        $new_version_num = trim($argv[1]);
    }
    if (isset($argv[2])) {
        $new_version_dir = rtrim(trim($argv[2]), '/\\');
    }
    if (isset($argv[3])) {
        $old_version_dir = rtrim(trim($argv[3]), '/\\');
    }
}

echo "==================================================\n";
echo " DGV7.0 OTA Update Builder - Version {$new_version_num}\n";
echo "==================================================\n";
echo "Master Codebase:  {$new_version_dir}\n";
echo "Previous Version: {$old_version_dir}\n\n";

if (!is_dir($new_version_dir)) {
    die("ERROR: Master codebase directory not found at: {$new_version_dir}\n");
}

// Check if old version exists, if not warn that all files will be compiled as new
$compare_mode = true;
if (!is_dir($old_version_dir)) {
    echo "WARNING: Previous version directory not found at: {$old_version_dir}\n";
    echo "All files in master codebase will be packaged as new/added files.\n\n";
    $compare_mode = false;
}

// Process profiles
foreach ($build_profiles as $tier_name => $config) {
    echo "--- Building [{$tier_name}] Tier package ---\n";
    
    $build_dir = $config['output_dir'];
    $tier_ignore_list = array_merge($base_ignore_list, $config['exclude']);
    
    // 1. Prepare clean output directory
    if (is_dir($build_dir)) {
        remove_directory($build_dir);
    }
    mkdir($build_dir . '/files', 0755, true);
    
    // 2. Scan directories and get SHA256 hashes
    $old_files = $compare_mode ? scan_directory($old_version_dir, $old_version_dir, $tier_ignore_list) : [];
    $new_files = scan_directory($new_version_dir, $new_version_dir, $tier_ignore_list);
    
    $files_to_delete = [];
    $files_added_or_modified = [];
    
    // 3. Find deleted files (exist in old, missing in new)
    foreach ($old_files as $relative_path => $hash) {
        if (!isset($new_files[$relative_path])) {
            $files_to_delete[] = str_replace('\\', '/', $relative_path);
        }
    }
    
    // 4. Find added or modified files (missing in old, or hash has changed)
    foreach ($new_files as $relative_path => $new_hash) {
        if (!isset($old_files[$relative_path]) || $old_files[$relative_path] !== $new_hash) {
            $files_added_or_modified[] = $relative_path;
            
            $source_file = $new_version_dir . '/' . $relative_path;
            $dest_file   = $build_dir . '/files/' . $relative_path;
            
            $dest_dir = dirname($dest_file);
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0755, true);
            }
            copy($source_file, $dest_file);
        }
    }
    
    // 5. Database Queries
    $database_queries = [];
    $sql_file = __DIR__ . '/migrations_' . strtolower(str_replace('-', '_', $tier_name)) . '.sql';
    if (!file_exists($sql_file)) {
        $sql_file = __DIR__ . '/migrations.sql';
    }
    
    if (file_exists($sql_file)) {
        echo "Including database migrations from: " . basename($sql_file) . "\n";
        $sql_content = file_get_contents($sql_file);
        $queries = array_filter(array_map('trim', explode(';', $sql_content)));
        foreach ($queries as $q) {
            if (!empty($q)) {
                $database_queries[] = $q . ';';
            }
        }
    }
    
    // 6. Generate manifest.json
    $manifest = [
        'version'          => $new_version_num,
        'tier'             => $tier_name,
        'files_to_delete'  => $files_to_delete,
        'database_queries' => $database_queries
    ];
    
    file_put_contents(
        $build_dir . '/manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    
    echo " -> Added/Modified:  " . count($files_added_or_modified) . " files\n";
    echo " -> Deleted/Obsolete: " . count($files_to_delete) . " files\n";
    echo " -> Output location:  {$build_dir}\n\n";
}

echo "==================================================\n";
echo "Build complete! Packages created successfully.\n";
echo "==================================================\n";

/* ==========================================================
 * HELPER FUNCTIONS
 * ========================================================== */

// Recursively scans a directory and returns an array of [relative_path => sha256_hash]
function scan_directory($dir, $base_dir, $ignore) {
    $results = [];
    if (!is_dir($dir)) return $results;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $real_path = $file->getRealPath();
            $relative_path = ltrim(substr($real_path, strlen($base_dir)), DIRECTORY_SEPARATOR);
            $norm_rel = str_replace('\\', '/', $relative_path);
            
            $skip = false;
            foreach ($ignore as $ignore_item) {
                $norm_ignore = str_replace('\\', '/', $ignore_item);
                
                // Check if directory pattern
                if (substr($norm_ignore, -1) === '/') {
                    if (strpos($norm_rel, $norm_ignore) === 0) {
                        $skip = true;
                        break;
                    }
                } else {
                    // Exact match or matches folder name exactly
                    if ($norm_rel === $norm_ignore || basename($norm_rel) === $norm_ignore) {
                        $skip = true;
                        break;
                    }
                }
            }
            
            if ($skip) continue;
            
            $results[$relative_path] = hash_file('sha256', $real_path);
        }
    }
    return $results;
}

// Recursively delete directory and its contents
function remove_directory($dir) {
    if (!is_dir($dir)) return;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir($dir);
}
