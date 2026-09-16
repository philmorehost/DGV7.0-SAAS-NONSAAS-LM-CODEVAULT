import os, io, sys
from PIL import Image

BASE = r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM\DGV7.0-SAAS\asset"

Image.MAX_IMAGE_PIXELS = None  # avoid decompression-bomb guard stopping legit images

stats = {'png_opt': 0, 'png_quant': 0, 'jpg': 0, 'skipped': 0, 'errors': 0, 'grew': 0}
bytes_before = 0
bytes_after = 0
reduced = 0

def save_smaller(img, path, save_kwargs, original_size):
    """Save to temp; only replace original if strictly smaller."""
    buf = io.BytesIO()
    img.save(buf, format='PNG' if path.lower().endswith('.png') else 'JPEG', **save_kwargs)
    new_size = buf.tell()
    if new_size < original_size:
        with open(path, 'wb') as f:
            f.write(buf.getvalue())
        return original_size - new_size
    return 0

for root, _, names in os.walk(BASE):
    for name in names:
        p = os.path.join(root, name)
        low = name.lower()
        try:
            orig = os.path.getsize(p)
            if orig == 0:
                continue
            if low.endswith('.png'):
                with Image.open(p) as im:
                    im.load()
                    mode = im.mode
                    exif = im.info.get('exif')
                    if mode == 'RGB':
                        # Photo/truecolor -> palette quantize (256 colors), floyd-steinberg dither
                        q = im.quantize(colors=256, dither=Image.Dither.FLOYDSTEINBERG, method=Image.Quantize.MEDIANCUT)
                        saved = save_smaller(q, p, {'optimize': True}, orig)
                        if saved > 0:
                            stats['png_quant'] += 1
                    elif mode == 'RGBA':
                        # Keep alpha; lossless re-optimize only
                        saved = save_smaller(im, p, {'optimize': True}, orig)
                        if saved > 0:
                            stats['png_opt'] += 1
                    else:
                        saved = save_smaller(im, p, {'optimize': True}, orig)
                        if saved > 0:
                            stats['png_opt'] += 1
                    if saved <= 0:
                        stats['grew'] += 1
                        reduced += 0
                    else:
                        reduced += saved
            elif low.endswith(('.jpg', '.jpeg')):
                with Image.open(p) as im:
                    im.load()
                    exif = im.info.get('exif')
                    kw = {'quality': 80, 'optimize': True, 'progressive': True}
                    if exif:
                        kw['exif'] = exif
                    saved = save_smaller(im, p, kw, orig)
                    if saved > 0:
                        stats['jpg'] += 1
                        reduced += saved
                    else:
                        stats['grew'] += 1
            else:
                stats['skipped'] += 1
                continue
            bytes_before += orig
            bytes_after += os.path.getsize(p)
        except Exception as e:
            stats['errors'] += 1
            print(f"  ERR {p}: {e}")

print("DONE")
print(f"  PNG quantized : {stats['png_quant']}")
print(f"  PNG optimized : {stats['png_opt']}")
print(f"  JPG recompressed: {stats['jpg']}")
print(f"  Grew/skipped  : {stats['grew']}")
print(f"  Errors        : {stats['errors']}")
print(f"  Total before  : {bytes_before/1024/1024:.2f} MB")
print(f"  Total after   : {bytes_after/1024/1024:.2f} MB")
print(f"  Saved         : {reduced/1024/1024:.2f} MB")
