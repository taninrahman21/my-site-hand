"""
Render the self-made SVG illustrations in art/<slug>/ to WebP in
/starter-sites/<slug>/assets/.

    python tools/starter-sites/render_art.py clinic

Uses headless Microsoft Edge (or Chrome) to rasterise and PHP GD to encode
WebP. Set MSH_BROWSER and MSH_PHP to override the executables.

Dev tooling only, never shipped.
"""

import os
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
PLUGIN = os.path.dirname(os.path.dirname(HERE))

BROWSER = os.environ.get("MSH_BROWSER", r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe")
PHP = os.environ.get("MSH_PHP", "php")
QUALITY = 82

TO_WEBP = r"""
$src = imagecreatefrompng($argv[1]);
imagepalettetotruecolor($src);
imagewebp($src, $argv[2], (int) $argv[3]);
echo filesize($argv[2]);
"""


def render(svg_path, out_path):
    with open(svg_path, encoding="utf-8") as fh:
        svg = fh.read()
    with tempfile.TemporaryDirectory() as tmp:
        html = os.path.join(tmp, "art.html")
        png = os.path.join(tmp, "art.png")
        with open(html, "w", encoding="utf-8") as fh:
            fh.write('<!doctype html><html><body style="margin:0;overflow:hidden">%s</body></html>' % svg)
        subprocess.run([
            BROWSER, "--headless=new", "--disable-gpu", "--hide-scrollbars",
            "--force-device-scale-factor=1", "--window-size=1200,900",
            "--screenshot=" + png, "file:///" + html.replace("\\", "/"),
        ], check=True, capture_output=True, timeout=60)
        php_args = [PHP]
        ext_dir = os.environ.get("MSH_PHP_EXT")
        if ext_dir:
            php_args += ["-d", "extension_dir=" + ext_dir, "-d", "extension=php_gd.dll"]
        size = subprocess.run(php_args + ["-r", TO_WEBP, png, out_path, str(QUALITY)],
                              check=True, capture_output=True, text=True).stdout
    print("  %s  %d KB" % (os.path.relpath(out_path, PLUGIN), int(size) // 1024))


def main(slug):
    src = os.path.join(HERE, "art", slug)
    out = os.path.join(PLUGIN, "starter-sites", slug, "assets")
    os.makedirs(out, exist_ok=True)
    for name in sorted(os.listdir(src)):
        if name.endswith(".svg"):
            render(os.path.join(src, name), os.path.join(out, name[:-4] + ".webp"))


if __name__ == "__main__":
    for arg in sys.argv[1:] or ["clinic"]:
        main(arg)
