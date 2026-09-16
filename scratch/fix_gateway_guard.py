import os, re, sys

ROOTS = [
    r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-SAAS\func\api-gateway",
    r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-NON-SAAS\func\api-gateway",
]

DECODE_RE = re.compile(r'^(\s*)\$curl_json_result\s*=\s*json_decode\(\$curl_result,\s*true\);', re.MULTILINE)
GUARD = 'if(!is_array($curl_json_result) || !isset($curl_json_result["code"])){\n{ind}$curl_json_result = array("code" => "999", "response_description" => "Invalid API response. Check your API credentials and try again.");\n{ind}}'
HAS_CODE = '$curl_json_result["code"]'
MARKER = '"code" => "999"'

apply_mode = '--apply' in sys.argv
modified = []
skipped_has_guard = []
for root in ROOTS:
    for dirpath, _, names in os.walk(root):
        for name in names:
            if not name.endswith('.php'):
                continue
            p = os.path.join(dirpath, name)
            with open(p, 'r', encoding='utf-8', errors='replace') as f:
                content = f.read()
            if HAS_CODE not in content:
                continue
            if MARKER in content:
                skipped_has_guard.append(p)
                continue
            m = DECODE_RE.search(content)
            if not m:
                print(f"  !! no json_decode line found: {p}")
                continue
            indent = m.group(1)
            guard = GUARD.replace('{ind}', indent)
            insert_at = m.end()
            new_content = content[:insert_at] + "\n" + guard + content[insert_at:]
            modified.append(p)
            if apply_mode:
                with open(p, 'w', encoding='utf-8', newline='') as f:
                    f.write(new_content)

print(f"Files to modify: {len(modified)}")
if not apply_mode:
    print("(dry run - pass --apply to write)")
    for p in modified[:10]:
        print("  ", p)
    if len(modified) > 10:
        print(f"   ... and {len(modified)-10} more")
else:
    for p in modified:
        print("  applied:", p)
    print(f"Already had guard (skipped): {len(skipped_has_guard)}")
