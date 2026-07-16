import os
import re

admin_dir = r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\EXAM-HUB\admin"

# Regex to match the sidebar block
aside_pattern = re.compile(r'<aside class="w-64 bg-slate-900.*?</aside>', re.DOTALL)
replacement = r"<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>"

for filename in os.listdir(admin_dir):
    if filename.endswith(".php") and filename not in ['login.php', 'dashboard.php']:
        filepath = os.path.join(admin_dir, filename)
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        if aside_pattern.search(content):
            new_content = aside_pattern.sub(replacement, content)
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"Updated {filename}")
        else:
            print(f"Skipped {filename} (no aside block found)")
