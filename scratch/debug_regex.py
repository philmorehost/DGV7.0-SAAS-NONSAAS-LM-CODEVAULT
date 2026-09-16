import re
p = r'C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-SAAS\func\api-gateway\airtime-vtpass-com.php'
with open(p, 'rb') as f:
    raw = f.read()
text = raw.decode('utf-8', 'replace')
print('len:', len(text))
print('has "code" substring:', '$curl_json_result["code"]' in text)
lines = text.splitlines()
for i, l in enumerate(lines):
    if 'json_decode' in l:
        print(i + 1, repr(l))
r = re.compile(r'^(\s*)\$curl_json_result\s*=\s*json_decode\(\$curl_result,\s*true\);')
for i, l in enumerate(lines):
    if r.search(l):
        print('MATCH line', i + 1)
