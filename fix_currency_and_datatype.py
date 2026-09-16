#!/usr/bin/env python3
"""
FINAL FIX for double-encoded UTF-8 currency symbols in Android Kotlin/XML source files.

The files were originally valid UTF-8. The first fix script wrote them with BOM and 
a latin-1/cp1252 transformation that needs to be reversed.

This script:
1. Reads file as UTF-8 (handles BOM)
2. Strips BOM if present
3. Applies cp1252 -> UTF-8 un-mojibake
4. Writes back as UTF-8 without BOM
"""
import os

BASE = r"c:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM"

APPS_TO_FIX = [
    r"DGV7.0-NON-SAAS\PayHub-Android\app\src\main",
    r"DGV7.0-NON-SAAS\DG6-Android\app\src\main",
    r"DGV7.0-NON-SAAS\MZEEVTU-Android\app\src\main",
]

EXTENSIONS = {".kt", ".xml", ".java", ".swift", ".html"}

def try_fix_mojibake(content_str):
    """
    Attempt to fix double-encoded UTF-8 (UTF-8 stored as cp1252 re-encoded as UTF-8).
    Returns (fixed_str, was_changed).
    """
    try:
        raw = content_str.encode('cp1252')
        fixed = raw.decode('utf-8')
        if fixed != content_str:
            return fixed, True
        return content_str, False
    except (UnicodeEncodeError, UnicodeDecodeError):
        return content_str, False

fixed_files = []
skipped_files = []
errors = []

for app_rel in APPS_TO_FIX:
    app_path = os.path.join(BASE, app_rel)
    if not os.path.exists(app_path):
        print(f"  [SKIP] Not found: {app_path}")
        continue
    for root, dirs, files in os.walk(app_path):
        for fname in files:
            ext = os.path.splitext(fname)[1].lower()
            if ext not in EXTENSIONS:
                continue
            fpath = os.path.join(root, fname)
            try:
                # Read raw bytes first to detect BOM and actual encoding
                with open(fpath, 'rb') as f:
                    raw_bytes = f.read()

                # Check for BOM  
                has_bom = raw_bytes.startswith(b'\xef\xbb\xbf')
                if has_bom:
                    raw_bytes = raw_bytes[3:]  # strip BOM

                # Decode as UTF-8
                try:
                    content = raw_bytes.decode('utf-8')
                except UnicodeDecodeError:
                    # Try latin-1 as fallback
                    content = raw_bytes.decode('latin-1')

                fixed, changed = try_fix_mojibake(content)

                if changed or has_bom:
                    # Write back as UTF-8 without BOM, preserving original line endings
                    with open(fpath, "w", encoding="utf-8", newline='') as f:
                        f.write(fixed)
                    rel = os.path.relpath(fpath, BASE)
                    fixed_files.append(rel)
                    reason = []
                    if changed: reason.append("mojibake")
                    if has_bom: reason.append("BOM")
                    print(f"  [FIXED:{','.join(reason)}] {rel}")
                else:
                    skipped_files.append(fpath)

            except Exception as e:
                errors.append((fpath, str(e)))
                print(f"  [ERROR] {fpath}: {e}")

print(f"\n{'='*60}")
print(f"Total files fixed:   {len(fixed_files)}")
print(f"Total files skipped: {len(skipped_files)}")
print(f"Total errors:        {len(errors)}")
if errors:
    print("\nErrors:")
    for p, e in errors:
        print(f"  {p}: {e}")
