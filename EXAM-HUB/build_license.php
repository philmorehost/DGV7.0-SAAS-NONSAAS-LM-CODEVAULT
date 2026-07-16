<?php
$source_file = __DIR__ . '/core/license_src.php';
$output_file = __DIR__ . '/core/license.php';

if (!file_exists($source_file)) {
    die("Source file not found.\n");
}

$code = file_get_contents($source_file);

// Remove PHP tags
$code = str_replace(['<?php', '?>'], '', $code);
$code = trim($code);

// Multi-layer Obfuscation
// Layer 1: Base64 + Gzdeflate
$layer1 = base64_encode(gzdeflate($code, 9));
// Layer 2: Str_rot13
$layer2 = str_rot13($layer1);
// Layer 3: Split into chunks
$chunks = str_split($layer2, 32);

// Generate PHP output
$output = "<?php\n";
$output .= "/* EXAM-HUB PROPRIETARY LICENSE FILE - DO NOT MODIFY */\n";
$output .= "\$a = [\n";
foreach ($chunks as $chunk) {
    $output .= "    '" . addslashes($chunk) . "',\n";
}
$output .= "];\n";

$output .= "\$b = implode('', \$a);\n";
$output .= "\$c = str_rot13(\$b);\n";
$output .= "\$d = base64_decode(\$c);\n";
$output .= "\$e = gzinflate(\$d);\n";
$output .= "eval(\$e);\n";

file_put_contents($output_file, $output);
echo "Obfuscated license generated successfully at: $output_file\n";
