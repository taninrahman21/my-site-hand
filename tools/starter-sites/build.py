"""
Generate a starter site package from its definition in sites/<slug>.py.

    python tools/starter-sites/build.py clinic
    python tools/starter-sites/build.py _library

Writes /starter-sites/<slug>/ (manifest, page sections, parts, templates,
slots, styles, palettes, fonts). Each page is stored as an ordered list of
sections, one file each, so users can hide, reorder and add sections.
"_library" builds the shared section library in /starter-sites/_library/.

Images in assets/ and screenshots/ are produced separately (render_art.py and
capture-screenshots.cjs) and are left alone.

Dev tooling only, never shipped.
"""

import importlib
import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
sys.path.insert(0, os.path.join(HERE, "sites"))

from blocks import Site  # noqa: E402

PLUGIN = os.path.dirname(os.path.dirname(HERE))
TOKEN = re.compile(r"\{\{\s*([a-z0-9_.:-]+)\s*\}\}")


def write(path, content):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8", newline="\n") as fh:
        fh.write(content.rstrip("\n") + "\n")


def write_json(path, data):
    write(path, json.dumps(data, indent="\t", ensure_ascii=False))


def split_sections(site, page_slug, markup):
    """Split a page into its top-level sections and name each one.

    Sections are joined with a blank line and nothing inside a section ever
    contains one, so the split is exact. A section is named after the editor
    group of its first slot ("home/hero" -> "hero").
    """
    groups = {g["id"]: g for g in site.groups}
    slot_group = {sl["id"]: sl["group"] for sl in site.slots}
    out, seen = [], set()

    for n, chunk in enumerate(c for c in markup.split("\n\n") if c.strip()):
        assert chunk.startswith("<!-- wp:"), "section %d of %s does not start with a block" % (n, page_slug)
        sid, label = "section-%d" % (n + 1), "Section %d" % (n + 1)
        for token in TOKEN.findall(chunk):
            if token.startswith("page:") or token not in slot_group:
                continue
            gid = slot_group[token]
            if gid.startswith(page_slug + "/"):
                sid = gid.split("/", 1)[1]
                label = groups[gid]["label"]
                break
        base, i = sid, 2
        while sid in seen:
            sid, i = "%s-%d" % (base, i), i + 1
        seen.add(sid)
        out.append({"id": sid, "label": label, "markup": chunk})
    return out


def build_site(slug):
    # Module names cannot contain hyphens; the folder is the manifest slug.
    mod = importlib.import_module(slug.replace("-", "_"))
    slug = mod.MANIFEST["slug"]
    site = Site()
    built = mod.build(site)
    out = os.path.join(PLUGIN, "starter-sites", slug)

    # Old single-file pages from earlier builds.
    pages_dir = os.path.join(out, "pages")
    if os.path.isdir(pages_dir):
        for name in os.listdir(pages_dir):
            if name.endswith(".html"):
                os.remove(os.path.join(pages_dir, name))

    pages = []
    for page in built["pages"]:
        sections = []
        for sec in split_sections(site, page["slug"], page["markup"]):
            rel = "pages/%s/%s.html" % (page["slug"], sec["id"])
            write(os.path.join(out, rel), sec["markup"])
            sections.append({"id": sec["id"], "label": sec["label"], "file": rel})
        entry = {"slug": page["slug"], "title": page["title"], "sections": sections, "template": "starter-full-width"}
        if page.get("is_front_page"):
            entry["is_front_page"] = True
        pages.append(entry)

    parts = {}
    for area, markup in built["parts"].items():
        rel = "parts/%s.html" % area
        write(os.path.join(out, rel), markup)
        parts[area] = rel

    templates = {}
    for tslug, tpl in built["templates"].items():
        rel = "templates/%s.html" % tslug
        write(os.path.join(out, rel), tpl["markup"])
        templates[tslug] = {"title": tpl["title"], "file": rel}

    shots_dir = os.path.join(out, "screenshots")
    screenshots = []
    if os.path.isdir(shots_dir):
        order = [p["slug"] for p in pages]
        names = sorted(os.listdir(shots_dir), key=lambda n: (order.index(n.split(".")[0]) if n.split(".")[0] in order else 99, n))
        screenshots = ["screenshots/" + n for n in names if n.endswith((".jpg", ".webp", ".png"))]

    manifest = dict(mod.MANIFEST)
    manifest.update({
        "pages": pages,
        "template_parts": parts,
        "templates": templates,
        "global_styles": "styles.json",
        "palettes": "palettes.json",
        "font_pairs": "fonts.json",
        "navigation": mod.NAVIGATION,
        "slots_schema": "slots.json",
        "screenshots": screenshots,
    })

    write_json(os.path.join(out, "manifest.json"), manifest)
    write_json(os.path.join(out, "slots.json"), {"groups": site.groups, "slots": site.slots})
    write_json(os.path.join(out, "styles.json"), mod.STYLES)
    write_json(os.path.join(out, "palettes.json"), {"palettes": mod.PALETTES})
    write_json(os.path.join(out, "fonts.json"), {"font_pairs": mod.FONT_PAIRS})

    n_sections = sum(len(p["sections"]) for p in pages)
    print("%s: %d pages, %d sections, %d slots -> %s" % (slug, len(pages), n_sections, len(site.slots), out))


def build_library():
    from library import LIBRARY

    out = os.path.join(PLUGIN, "starter-sites", "_library")
    entries = []
    for stype, label, desc, fn in LIBRARY:
        site = Site()
        markup = fn(site).strip()
        assert "\n\n" not in markup, stype
        for token in TOKEN.findall(markup):
            assert token.startswith(("s.x", "image:s.x", "page:-last")), (stype, token)
        rel = "sections/%s.html" % stype
        write(os.path.join(out, rel), markup)
        slots = []
        for sl in site.slots:
            assert sl["id"].startswith(("s.x", "image:s.x")), (stype, sl["id"])
            slots.append({k: v for k, v in sl.items() if k != "group"})
        entries.append({"type": stype, "label": label, "description": desc, "file": rel, "slots": slots})

    write_json(os.path.join(out, "library.json"), {"version": 1, "prefix": "s.x", "sections": entries})
    print("_library: %d section types -> %s" % (len(entries), out))


if __name__ == "__main__":
    for arg in sys.argv[1:] or ["clinic"]:
        build_library() if "_library" == arg else build_site(arg)
