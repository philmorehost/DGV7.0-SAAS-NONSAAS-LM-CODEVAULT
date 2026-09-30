<?php
/**
 * Report the 16 KB-page-size readiness of an APK/AAB, and the ABI set it ships.
 *
 * Play's "Your app does not support 16 KB memory page sizes" comes from the ELF program headers of
 * every native library: each PT_LOAD segment must have p_align >= 16384. A library built by an old
 * NDK has p_align = 4096 and cannot run on a 16 KB-page device, so a rebuild with NDK r27+ (or a
 * newer copy of the library from the vendor) is the only fix - no Gradle flag can change it.
 *
 * The "no longer supports N devices" warning is usually a change in which ABIs are shipped, so the
 * ABI set is printed per artifact and can be compared between two builds.
 *
 * Usage: php check_native_16kb.php <apk-or-aab> [<second-artifact-to-compare>]
 */

$paths = array_slice($argv, 1);
if (!count($paths)) {
    fwrite(STDERR, "usage: php check_native_16kb.php <apk-or-aab> [<second>]\n");
    exit(2);
}

/** Parse an ELF file and return its PT_LOAD alignments (and class/machine). */
function elf_load_alignments($bytes)
{
    if (strlen($bytes) < 64 || substr($bytes, 0, 4) !== "\x7fELF") return null;
    $class = ord($bytes[4]);          // 1 = 32-bit, 2 = 64-bit
    $data  = ord($bytes[5]);          // 1 = little endian
    $le    = ($data === 1);
    $u16 = function ($off) use ($bytes, $le) { return $le ? unpack('v', substr($bytes, $off, 2))[1] : unpack('n', substr($bytes, $off, 2))[1]; };
    $u32 = function ($off) use ($bytes, $le) { return $le ? unpack('V', substr($bytes, $off, 4))[1] : unpack('N', substr($bytes, $off, 4))[1]; };
    $u64 = function ($off) use ($bytes, $le) {
        $parts = $le ? unpack('Vlow/Vhigh', substr($bytes, $off, 8)) : unpack('Nhigh/Nlow', substr($bytes, $off, 8));
        return $parts['low'] + ($parts['high'] * 4294967296);
    };

    $machine = $u16(0x12);
    if ($class === 2) {
        $phoff = $u64(0x20); $phentsize = $u16(0x36); $phnum = $u16(0x38);
    } else {
        $phoff = $u32(0x1C); $phentsize = $u16(0x2A); $phnum = $u16(0x2C);
    }

    $aligns = array();
    for ($i = 0; $i < $phnum; $i++) {
        $base = $phoff + ($i * $phentsize);
        if ($base + $phentsize > strlen($bytes)) break;
        $type = $u32($base);
        if ($type !== 1) continue;    // PT_LOAD
        $aligns[] = ($class === 2) ? $u64($base + 0x30) : $u32($base + 0x1C);
    }
    return array('class' => $class === 2 ? '64-bit' : '32-bit', 'machine' => $machine, 'aligns' => $aligns);
}

function machine_name($m)
{
    $names = array(3 => 'x86', 0x28 => 'arm', 0x3E => 'x86_64', 0xB7 => 'aarch64');
    return isset($names[$m]) ? $names[$m] : ('machine ' . $m);
}

$reports = array();
foreach ($paths as $path) {
    if (!is_file($path)) { fwrite(STDERR, "not found: $path\n"); exit(2); }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) { fwrite(STDERR, "cannot open: $path\n"); exit(2); }

    echo "================= " . basename($path) . " =================\n";
    echo "size: " . number_format(filesize($path) / 1048576, 2) . " MB\n";

    $abis = array();
    $libs = array();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        $name = $st['name'];
        if (!preg_match('#(?:^|/)(?:base/)?lib/([^/]+)/([^/]+\.so)$#', $name, $m)) continue;
        $abi = $m[1];
        $abis[$abi] = true;
        $compressed = ($st['comp_size'] !== $st['size'] && $st['comp_method'] !== 0);
        // ZipArchive::statIndex() does not reliably expose the local-header data offset, so the
        // in-zip alignment is only reported when it is available. Play's own check reads the ELF
        // program headers, which is what matters here.
        $zip16k = null;
        if (!$compressed && isset($st['offset'])) {
            $zip16k = $st['offset'] % 16384 === 0 ? 16384 : 4096;
        }
        $libs[] = array(
            'abi' => $abi, 'name' => $m[2], 'size' => $st['size'],
            'compressed' => $compressed, 'zip16k' => $zip16k,
            'elf' => elf_load_alignments($zip->getFromIndex($i)),
        );
    }
    $zip->close();

    ksort($abis);
    echo "ABIs shipped: " . (count($abis) ? implode(', ', array_keys($abis)) : '(none)') . "\n";
    printf("native libraries: %d\n\n", count($libs));

    $worst = array();
    foreach ($libs as $l) {
        $elf = $l['elf'];
        if ($elf === null) {
            printf("  %-9s %-34s %8s  NOT AN ELF FILE\n", $l['abi'], $l['name'], number_format($l['size'] / 1024, 1) . 'K');
            continue;
        }
        $min = min($elf['aligns']);
        $ok = $min >= 16384;
        printf("  %-9s %-34s %8s  %s %-9s PT_LOAD p_align=%d %s%s\n",
            $l['abi'], $l['name'], number_format($l['size'] / 1024, 1) . 'K',
            $elf['class'], machine_name($elf['machine']), $min,
            $ok ? 'OK' : 'TOO SMALL - will not run on 16 KB devices',
            $l['compressed'] ? ' [compressed in zip]' : ($l['zip16k'] === 16384 ? ' [zip-aligned 16K]' : ' [zip NOT 16K aligned]'));
        if (!$ok) $worst[] = $l['abi'] . '/' . $l['name'];
    }
    echo "\n";
    $reports[$path] = array('abis' => array_keys($abis), 'bad' => $worst);
}

if (count($reports) === 2) {
    $keys = array_keys($reports);
    echo "================= ABI COMPARISON =================\n";
    $a = $reports[$keys[0]]; $b = $reports[$keys[1]];
    echo "  " . basename($keys[0]) . ": " . implode(', ', $a['abis']) . "\n";
    echo "  " . basename($keys[1]) . ": " . implode(', ', $b['abis']) . "\n";
    $lost = array_diff($a['abis'], $b['abis']);
    $gained = array_diff($b['abis'], $a['abis']);
    echo "  ABIs in the FIRST artifact but not the second: " . (count($lost) ? implode(', ', $lost) : 'none') . "\n";
    echo "  ABIs in the SECOND artifact but not the first: " . (count($gained) ? implode(', ', $gained) : 'none') . "\n";
    echo "\n";
}

echo "================= VERDICT =================\n";
foreach ($reports as $path => $r) {
    echo basename($path) . ":\n";
    echo $r['bad']
        ? "  16 KB page size: FAILS - " . count($r['bad']) . " native librar" . (count($r['bad']) === 1 ? 'y' : 'ies')
          . " built with 4 KB alignment:\n     - " . implode("\n     - ", array_slice($r['bad'], 0, 10)) . "\n"
        : "  16 KB page size: PASS - every native library is 16 KB aligned\n";
}
