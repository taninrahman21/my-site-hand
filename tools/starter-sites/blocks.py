"""
Core block markup helpers for the starter site generator.

Dev tooling only: tools/ never ships (see .distignore). The generated files in
/starter-sites/ are the shipped artifact, and `wp my-site-hand
validate-starter-sites` plus tools/starter-sites/validate-blocks.cjs check them.

Every helper returns serialized block markup that matches what the block's
save() function produces, so the editor never reports "unexpected content".
Inline style declaration order does not matter to block validation, but class
names and tags do.
"""

import json


class Site:
    """Collects slot definitions while pages are being written."""

    def __init__(self):
        self.groups = []
        self.slots = []
        self._ids = set()
        self._group = None

    def group(self, group_id, page, label):
        """Start a new editor group. Following slots belong to it."""
        if not any(g["id"] == group_id for g in self.groups):
            self.groups.append({"id": group_id, "page": page, "label": label})
        self._group = group_id

    def slot(self, slot_id, default, *, type="text", max=None, label=None, hint=""):
        """Declare a slot (or reuse one) and return its token."""
        token = "{{" + slot_id + "}}"
        if slot_id in self._ids:
            return token
        if self._group is None:
            raise ValueError("slot %s declared outside a group" % slot_id)
        if type == "image" and not slot_id.startswith("image:"):
            raise ValueError("image slot ids start with image: (%s)" % slot_id)
        entry = {
            "id": slot_id,
            "group": self._group,
            "type": type,
            "label": label or default_label(slot_id),
            "default": default,
        }
        if max is not None:
            entry["max"] = max
        elif type in ("text", "richtext"):
            entry["max"] = max_for(default)
        if hint:
            entry["hint"] = hint
        self.slots.append(entry)
        self._ids.add(slot_id)
        return token

    # Shorthands.
    def text(self, slot_id, default, **kw):
        return self.slot(slot_id, default, type="text", **kw)

    def rich(self, slot_id, default, **kw):
        return self.slot(slot_id, default, type="richtext", **kw)

    def image(self, slot_id, default, **kw):
        return self.slot(slot_id, default, type="image", **kw)


PLAIN_LABELS = {
    "eyebrow": "Label above heading",
    "heading": "Heading",
    "subheading": "Text under heading",
    "lead": "Intro text",
    "intro": "Intro text",
    "body": "Text",
    "body1": "First paragraph",
    "body2": "Second paragraph",
    "text": "Text",
    "cta_primary": "Main button",
    "cta_secondary": "Second button",
    "button": "Button text",
    "link": "Link text",
    "quote": "Quote",
    "name": "Name",
    "category": "Category",
    "title": "Title",
}


def default_label(slot_id):
    """Plain-English field label for site owners, never a developer id."""
    import re
    last = slot_id.split(":")[-1].split(".")[-1]
    if last in PLAIN_LABELS:
        return PLAIN_LABELS[last]
    m = re.match(r"item(\d+)_(title|text|name|desc|price)$", last)
    if m:
        part = {"title": "title", "text": "text", "name": "name", "desc": "description", "price": "price"}[m.group(2)]
        return "Item %s %s" % (m.group(1), part)
    m = re.match(r"q(\d+)$", last)
    if m:
        return "Question %s" % m.group(1)
    return last.replace("_", " ").capitalize()


def max_for(default):
    """Generous, round max length: room to rewrite, not room for an essay."""
    n = len(default)
    for cap in (24, 40, 60, 80, 120, 160, 220, 320, 480):
        if n * 1.6 <= cap:
            return cap
    return 600


# ---------------------------------------------------------------------------
# Serialization helpers
# ---------------------------------------------------------------------------

def attrs_json(attrs):
    return json.dumps(attrs, separators=(",", ":"), ensure_ascii=False)


def opener(name, attrs):
    return "<!-- wp:%s %s -->" % (name, attrs_json(attrs)) if attrs else "<!-- wp:%s -->" % name


def closer(name):
    return "<!-- /wp:%s -->" % name


def void(name, attrs=None):
    return "<!-- wp:%s %s /-->" % (name, attrs_json(attrs)) if attrs else "<!-- wp:%s /-->" % name


def wrap(name, attrs, html):
    return "%s\n%s\n%s" % (opener(name, attrs), html, closer(name))


def css_value(v):
    if isinstance(v, str) and v.startswith("var:"):
        return "var(--wp--%s)" % v[4:].replace("|", "--")
    return v


def kebab(s):
    return "".join("-" + c.lower() if c.isupper() else c for c in s)


def style_css(style):
    """Inline CSS for the style attribute keys these helpers use."""
    out = []
    border = style.get("border", {})
    if "radius" in border:
        out.append("border-radius:%s" % border["radius"])
    for side in ("top", "right", "bottom", "left"):
        if side in border:
            for k, v in border[side].items():
                out.append("border-%s-%s:%s" % (side, k, css_value(v)))
    for key in ("color", "width", "style"):
        if key in border:
            out.append("border-%s:%s" % (key, css_value(border[key])))
    spacing = style.get("spacing", {})
    for prop in ("margin", "padding"):
        for side, v in spacing.get(prop, {}).items():
            out.append("%s-%s:%s" % (prop, side, css_value(v)))
    for k, v in style.get("typography", {}).items():
        out.append("%s:%s" % (kebab(k), css_value(v)))
    dims = style.get("dimensions", {})
    if "minHeight" in dims:
        out.append("min-height:%s" % dims["minHeight"])
    return ";".join(out)


def color_classes(text=None, bg=None, font_size=None, border=False):
    c = []
    if text:
        c += ["has-%s-color" % text, "has-text-color"]
    if bg:
        c += ["has-%s-background-color" % bg, "has-background"]
    if border:
        c.append("has-border-color")
    if font_size:
        c.append("has-%s-font-size" % font_size)
    return c


def tag(name, classes, style, inner, extra=""):
    cls = ' class="%s"' % " ".join(classes) if classes else ""
    sty = ' style="%s"' % style if style else ""
    return "<%s%s%s%s>%s</%s>" % (name, cls, extra, sty, inner, name)


def apply_common(attrs, *, text=None, bg=None, font_size=None, style=None, class_name=None):
    if class_name:
        attrs["className"] = class_name
    if style:
        attrs["style"] = style
    if bg:
        attrs["backgroundColor"] = bg
    if text:
        attrs["textColor"] = text
    if font_size:
        attrs["fontSize"] = font_size
    return attrs


# ---------------------------------------------------------------------------
# Blocks
# ---------------------------------------------------------------------------

def paragraph(content, *, text=None, font_size=None, align=None, style=None, class_name=None):
    attrs = {}
    if align:
        attrs["align"] = align
    apply_common(attrs, text=text, font_size=font_size, style=style, class_name=class_name)
    classes = ([class_name] if class_name else []) + (["has-text-align-" + align] if align else [])
    classes += color_classes(text=text, font_size=font_size)
    return wrap("paragraph", attrs, tag("p", classes, style_css(style or {}), content))


def eyebrow(content, *, align=None, text="primary"):
    """Small uppercase label above a heading."""
    return paragraph(
        content,
        text=text,
        font_size="small",
        align=align,
        style={"typography": {"fontWeight": "600", "letterSpacing": "0.08em", "textTransform": "uppercase"}},
    )


def heading(content, level=2, *, font_size=None, align=None, text=None, style=None):
    attrs = {}
    if align:
        attrs["textAlign"] = align
    if level != 2:
        attrs["level"] = level
    apply_common(attrs, text=text, font_size=font_size, style=style)
    classes = ["wp-block-heading"] + (["has-text-align-" + align] if align else [])
    classes += color_classes(text=text, font_size=font_size)
    return wrap("heading", attrs, tag("h%d" % level, classes, style_css(style or {}), content))


def button(label, href, *, outline=False, bg=None, text=None, width=None):
    attrs = {}
    if outline:
        attrs["className"] = "is-style-outline"
    if bg:
        attrs["backgroundColor"] = bg
    if text:
        attrs["textColor"] = text
    if width:
        attrs["width"] = width
    outer = ["wp-block-button"]
    if width:
        outer += ["has-custom-width", "wp-block-button__width-%d" % width]
    if outline:
        outer.append("is-style-outline")
    link_classes = ["wp-block-button__link"] + color_classes(text=text, bg=bg) + ["wp-element-button"]
    link = '<a class="%s" href="%s">%s</a>' % (" ".join(link_classes), href, label)
    return wrap("button", attrs, '<div class="%s">%s</div>' % (" ".join(outer), link))


def buttons(*children, justify=None, style=None):
    attrs = {}
    if justify:
        attrs["layout"] = {"type": "flex", "justifyContent": justify}
    if style:
        attrs["style"] = style
    return wrap("buttons", attrs, tag("div", ["wp-block-buttons"], style_css(style or {}), "\n".join(children)))


def radius_var(kind, fallback):
    """A corner radius the design panel can change site-wide.

    The value is a CSS custom property set in global styles
    (settings.custom.radius.*); the fallback is the starter site's own look,
    used until someone picks a corner style. Plain CSS, so it keeps working
    without the plugin.
    """
    return "var(--wp--custom--radius--%s, %s)" % (kind, fallback or "0px")


def image(src, alt, *, aspect="4/3", radius="16px", size="large"):
    attrs = {"aspectRatio": aspect, "scale": "cover", "sizeSlug": size}
    classes = ["wp-block-image", "size-" + size]
    img_style = "aspect-ratio:%s;object-fit:cover" % aspect
    radius = radius_var("media", radius)
    if radius:
        attrs["style"] = {"border": {"radius": radius}}
        classes.append("has-custom-border")
        img_style = "border-radius:%s;%s" % (radius, img_style)
    img = '<img src="%s" alt="%s" style="%s"/>' % (src, alt, img_style)
    return wrap("image", attrs, '<figure class="%s">%s</figure>' % (" ".join(classes), img))


def group(*children, align=None, bg=None, text=None, padding=None, margin=None, layout=None,
          tag_name=None, radius=None, border_top=None, min_height=None, gap=None, class_name=None):
    style = {}
    spacing = {}
    if margin:
        spacing["margin"] = margin
    if padding:
        spacing["padding"] = padding
    if gap:
        spacing["blockGap"] = gap
    if spacing:
        style["spacing"] = spacing
    if radius:
        style.setdefault("border", {})["radius"] = radius
    if border_top:
        style.setdefault("border", {})["top"] = border_top
    if min_height:
        style["dimensions"] = {"minHeight": min_height}
    attrs = {}
    if tag_name:
        attrs["tagName"] = tag_name
    if align:
        attrs["align"] = align
    apply_common(attrs, text=text, bg=bg, style=style or None, class_name=class_name)
    if layout:
        attrs["layout"] = layout
    classes = ["wp-block-group"] + ([class_name] if class_name else []) + (["align" + align] if align else [])
    classes += color_classes(text=text, bg=bg)
    css = style_css(style)
    return wrap("group", attrs, tag(tag_name or "div", classes, css, "\n".join(children)))


def section(*children, bg=None, text=None, pad="80", layout=None):
    """Full-width page section. Margins are zeroed so coloured bands touch."""
    return group(
        *children,
        align="full",
        bg=bg,
        text=text,
        margin={"top": "0", "bottom": "0"},
        padding={"top": "var:preset|spacing|%s" % pad, "bottom": "var:preset|spacing|%s" % pad},
        layout=layout or {"type": "constrained"},
    )


def card(*children, bg="base", pad="50", radius="16px"):
    p = "var:preset|spacing|%s" % pad
    return group(
        *children,
        bg=bg,
        radius=radius_var("card", radius),
        min_height="100%",
        padding={"top": p, "right": p, "bottom": p, "left": p},
    )


def columns(*children, align="wide", vcenter=False, gap=None):
    attrs = {}
    if vcenter:
        attrs["verticalAlignment"] = "center"
    if align:
        attrs["align"] = align
    if gap:
        attrs["style"] = {"spacing": {"blockGap": {"top": gap, "left": gap}}}
    classes = ["wp-block-columns"] + (["align" + align] if align else [])
    if vcenter:
        classes.append("are-vertically-aligned-center")
    return wrap("columns", attrs, tag("div", classes, "", "\n".join(children)))


def column(*children, width=None, vcenter=False):
    attrs = {}
    if vcenter:
        attrs["verticalAlignment"] = "center"
    if width:
        attrs["width"] = width
    classes = ["wp-block-column"] + (["is-vertically-aligned-center"] if vcenter else [])
    return wrap("column", attrs, tag("div", classes, "flex-basis:%s" % width if width else "", "\n".join(children)))


def quote(text, cite):
    inner = paragraph(text, font_size="large")
    return wrap("quote", {}, '<blockquote class="wp-block-quote">%s<cite>%s</cite></blockquote>' % (inner, cite))


def details(summary, body):
    return wrap("details", {}, '<details class="wp-block-details"><summary>%s</summary>%s</details>' % (summary, paragraph(body)))


def separator():
    # The wide style: some themes (Twenty Twenty-Four) draw the default
    # separator as a short centred rule.
    return wrap("separator", {"className": "is-style-wide"},
                '<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>')


def spacer(height):
    return wrap("spacer", {"height": height}, '<div style="height:%s" aria-hidden="true" class="wp-block-spacer"></div>' % height)


def site_title(*, font_size="large", weight="700"):
    return void("site-title", {"level": 0, "style": {"typography": {"fontWeight": weight}}, "fontSize": font_size})


def navigation(justify="right"):
    # No ref and no links on purpose: the builder injects the links when it
    # stages a build and swaps in a wp_navigation ref when it applies one.
    return void("navigation", {"overlayMenu": "mobile", "layout": {"type": "flex", "justifyContent": justify}})


def template_part(slug, area):
    return void("template-part", {"slug": slug, "area": area, "tagName": area})


def post_content():
    return void("post-content", {"align": "full", "layout": {"type": "default"}})
