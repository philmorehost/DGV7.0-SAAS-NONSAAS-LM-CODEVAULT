import os, sys

ROOTS = [
    r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-SAAS\func\api-gateway",
    r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-NON-SAAS\func\api-gateway",
]

apply_mode = '--apply' in sys.argv
modified = []

for root in ROOTS:
    for dirpath, _, names in os.walk(root):
        for name in names:
            if not name.endswith('.php'):
                continue
            p = os.path.join(dirpath, name)
            with open(p, 'r', encoding='utf-8', errors='replace') as f:
                lines = f.readlines()
            changed = False
            new_lines = []
            for line in lines:
                nl = line
                # Only FAILED description lines that (misleadingly) say "credited to 234 ... failed"
                if 'Transaction Failed' in nl and 'credited to 234' in nl and '" failed"' in nl:
                    nl = nl.replace('credited to 234', 'data to 234').replace('" failed"', '" was not delivered"')
                    if nl != line:
                        changed = True
                new_lines.append(nl)
            if changed:
                modified.append(p)
                if apply_mode:
                    with open(p, 'w', encoding='utf-8', newline='') as f:
                        f.writelines(new_lines)

print(f"Files to fix: {len(modified)}")
if not apply_mode:
    print("(dry run - pass --apply to write)")
    for p in modified:
        print("  ", p)
