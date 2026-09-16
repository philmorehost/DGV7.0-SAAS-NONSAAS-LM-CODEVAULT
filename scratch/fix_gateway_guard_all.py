import os, re, sys

ROOTS = [
    r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-SAAS\func\api-gateway",
    r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-NON-SAAS\func\api-gateway",
]

# A plain variable assignment from json_decode, matched through the closing
# ');' so the guard is inserted AFTER the full statement, not inside the call.
DECODE_LINE = re.compile(r'^(\s*)\$(\w+)\s*=\s*json_decode\s*\([^\n]*?\)\s*;', re.MULTILINE)
# Array-offset access of a variable:  $var[
ACCESS = re.compile(r'\$(\w+)\[')
ALREADY_GUARDED = '"code" => "999"'

apply_mode = '--apply' in sys.argv
modified = []
skipped = []

for root in ROOTS:
    for dirpath, _, names in os.walk(root):
        for name in names:
            if not name.endswith('.php'):
                continue
            p = os.path.join(dirpath, name)
            with open(p, 'r', encoding='utf-8', errors='replace') as f:
                content = f.read()
            if ALREADY_GUARDED in content:
                skipped.append(p)
                continue
            accessed = set(ACCESS.findall(content))
            if not accessed:
                continue
            # Collect all decode lines whose variable is later array-accessed
            insertions = []
            for m in DECODE_LINE.finditer(content):
                indent = m.group(1)
                var = m.group(2)
                if var in accessed:
                    guard = indent + 'if(!is_array($' + var + ')){ $' + var + ' = array(); }'
                    insertions.append((m.end(), guard))
            if not insertions:
                continue
            # Build new content by inserting after each match (process from end to keep offsets valid)
            new_content = content
            for end, guard in sorted(insertions, key=lambda x: -x[0]):
                new_content = new_content[:end] + "\n" + guard + new_content[end:]
            modified.append(p)
            if apply_mode:
                with open(p, 'w', encoding='utf-8', newline='') as f:
                    f.write(new_content)

print(f"Files to modify: {len(modified)}")
if not apply_mode:
    print("(dry run - pass --apply to write)")
else:
    print(f"Applied. Already guarded (skipped): {len(skipped)}")
