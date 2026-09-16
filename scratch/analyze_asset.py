import os, hashlib, sys
from collections import defaultdict
from PIL import Image

BASE = r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-SAAS\asset"

def md5(path, chunk=1 << 20):
    h = hashlib.md5()
    with open(path, 'rb') as f:
        for b in iter(lambda: f.read(chunk), b''):
            h.update(b)
    return h.hexdigest()

files = []
for root, _, names in os.walk(BASE):
    for n in names:
        p = os.path.join(root, n)
        files.append(p)

print(f"TOTAL FILES: {len(files)}")

# ---- Exact duplicates ----
by_hash = defaultdict(list)
for p in files:
    by_hash[md5(p)].append(p)

dups = {k: v for k, v in by_hash.items() if len(v) > 1}
wasted = sum((len(v) - 1) * os.path.getsize(v[0]) for v in dups.values())
print(f"EXACT-DUPLICATE GROUPS: {len(dups)} | WASTED: {wasted/1024/1024:.2f} MB")
# Show groups with most waste (sorted by potential saving)
groups_sorted = sorted(dups.values(), key=lambda v: (len(v)-1)*os.path.getsize(v[0]), reverse=True)
for g in groups_sorted[:12]:
    size = os.path.getsize(g[0])/1024
    print(f"  {os.path.relpath(g[0], BASE)}  x{len(g)}  ({size:.0f} KB each, save {(len(g)-1)*size:.0f} KB)")

# ---- Image dimensions + smush potential ----
print("\n--- LARGE IMAGES: dimensions & estimated smush gain ---")
imgs = []
for p in files:
    if p.lower().endswith(('.png', '.jpg', '.jpeg', '.gif')):
        try:
            with Image.open(p) as im:
                imgs.append((os.path.getsize(p), im.size, im.mode, p))
        except Exception:
            pass

imgs.sort(reverse=True)
# stats
total_img_bytes = sum(s for s, _, _, _ in imgs)
print(f"Image files: {len(imgs)} | total {total_img_bytes/1024/1024:.2f} MB")
# how many are larger than 512x512
big = [i for i in imgs if max(i[1]) > 800]
print(f"Images with a dimension >800px: {len(big)}")
# estimate smush savings: PNGs -> quantized/optimized typically ~50-70% reduction
pngs = [i for i in imgs if i[3].lower().endswith('.png')]
png_bytes = sum(s for s, _, _, _ in pngs)
print(f"PNGs: {len(pngs)} files, {png_bytes/1024/1024:.2f} MB (est. smush saving ~55% => {png_bytes*0.55/1024/1024:.1f} MB)")
for s, size, mode, p in imgs[:15]:
    print(f"  {os.path.relpath(p, BASE):55s} {size[0]}x{size[1]} {mode} {s/1024:.0f} KB")

# ---- Root-level file naming oddities (possible duplicates like nigeria-coat-of-arms vs nigeria-coa) ----
print("\n--- Similar names (possible near-dups) ---")
import re
base_names = defaultdict(list)
for p in files:
    rel = os.path.relpath(p, BASE).lower()
    key = re.sub(r'[^a-z0-9]', '', os.path.splitext(rel)[0])
    base_names[key].append(p)
for k, v in sorted(base_names.items(), key=lambda kv: -len(kv[1])):
    if len(v) > 1:
        print(f"  '{k}': {len(v)} files -> {[os.path.relpath(x,BASE) for x in v]}")
