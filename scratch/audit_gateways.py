import os, re, json

ROOTS = [
    r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-SAAS\func\api-gateway",
    r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-NON-SAAS\func\api-gateway",
]

# Find all assignments of the form:  $var = json_decode(...)
DECODE = re.compile(r'\$(\w+)\s*=\s*json_decode\s*\(', re.MULTILINE)
# Find array-offset accesses of a variable:  $var[
ACCESS = re.compile(r'\$(\w+)\[')

by_project = {}
for root in ROOTS:
    proj = os.path.basename(os.path.dirname(os.path.dirname(root)))
    files = []
    for dirpath, _, names in os.walk(root):
        for name in names:
            if not name.endswith('.php'):
                continue
            p = os.path.join(dirpath, name)
            with open(p, 'r', encoding='utf-8', errors='replace') as f:
                content = f.read()
            decodes = set(DECODE.findall(content))
            if not decodes:
                continue
            accessed = set(ACCESS.findall(content))
            risky = sorted(decodes & accessed)  # decoded vars that are later accessed with []
            guarded = '"code" => "999"' in content
            files.append({
                'path': os.path.relpath(p, root),
                'decoded_vars': sorted(decodes),
                'risky_vars': risky,
                'guarded': guarded,
            })
    by_project[proj] = files

for proj, files in by_project.items():
    print(f"\n=== {proj} : {len(files)} files that json_decode ===")
    ung = [f for f in files if not f['guarded']]
    g = [f for f in files if f['guarded']]
    print(f"  already guarded: {len(g)} | NOT guarded: {len(ung)}")
    # variable names used among unguarded
    varnames = {}
    for f in ung:
        for v in f['decoded_vars']:
            varnames[v] = varnames.get(v, 0) + 1
    print(f"  decoded variable names among unguarded: {varnames}")
    # list unguarded files with their risky vars
    for f in ung:
        print(f"    {f['path']}  risky={f['risky_vars']}")
