import os
import zlib
import base64
import codecs

src_file = os.path.join(os.path.dirname(__file__), 'core/license_src.php')
out_file = os.path.join(os.path.dirname(__file__), 'core/license.php')

with open(src_file, 'r', encoding='utf-8') as f:
    code = f.read()

# Remove PHP tags
code = code.replace('<?php', '').replace('?>', '').strip()

# Layer 1: zlib deflate -> base64
# PHP's gzdeflate uses raw DEFLATE. Python's zlib.compress uses zlib format (with headers).
# To get raw DEFLATE without zlib headers/checksum, we use -zlib.MAX_WBITS
compressor = zlib.compressobj(9, zlib.DEFLATED, -zlib.MAX_WBITS)
deflated = compressor.compress(code.encode('utf-8')) + compressor.flush()

layer1 = base64.b64encode(deflated).decode('ascii')

# Layer 2: str_rot13
layer2 = codecs.encode(layer1, 'rot_13')

# Split into chunks of 32
chunks = [layer2[i:i+32] for i in range(0, len(layer2), 32)]

# Generate PHP output
output = "<?php\n"
output += "/* EXAM-HUB PROPRIETARY LICENSE FILE - DO NOT MODIFY */\n"
output += "$a = [\n"
for chunk in chunks:
    # Escape backslashes and single quotes if any (though base64 has none of these)
    output += f"    '{chunk}',\n"
output += "];\n"
output += "$b = implode('', $a);\n"
output += "$c = str_rot13($b);\n"
output += "$d = base64_decode($c);\n"
output += "$e = gzinflate($d);\n"
output += "eval($e);\n"

with open(out_file, 'w', encoding='utf-8') as f:
    f.write(output)

print(f"Obfuscated license generated successfully at: {out_file}")
