"""
Design tokens shared by every starter site: spacing scale, type scale, base
global styles and font pairs. Sites override only what makes them distinct.

Preset slugs deliberately reuse the slugs Twenty Twenty-Five and Twenty
Twenty-Four already use for font sizes and spacing (small..xx-large, 10..80),
so when the builder merges them over the active theme's presets, the theme's
own templates (blog, single post, 404) pick up the same scale.
"""

FONT_SIZES = [
    {"name": "Small", "slug": "small", "size": "0.875rem", "fluid": False},
    {"name": "Medium", "slug": "medium", "size": "1.0625rem", "fluid": {"min": "1rem", "max": "1.125rem"}},
    {"name": "Large", "slug": "large", "size": "1.375rem", "fluid": {"min": "1.2rem", "max": "1.375rem"}},
    {"name": "Extra Large", "slug": "x-large", "size": "2rem", "fluid": {"min": "1.6rem", "max": "2rem"}},
    {"name": "Extra Extra Large", "slug": "xx-large", "size": "2.75rem", "fluid": {"min": "2rem", "max": "2.75rem"}},
    {"name": "Huge", "slug": "huge", "size": "3.75rem", "fluid": {"min": "2.4rem", "max": "3.75rem"}},
]

SPACING_SIZES = [
    {"name": "1", "slug": "10", "size": "0.5rem"},
    {"name": "2", "slug": "20", "size": "0.75rem"},
    {"name": "3", "slug": "30", "size": "1rem"},
    {"name": "4", "slug": "40", "size": "1.5rem"},
    {"name": "5", "slug": "50", "size": "clamp(1.5rem, 4vw, 2.25rem)"},
    {"name": "6", "slug": "60", "size": "clamp(2rem, 5vw, 3.5rem)"},
    {"name": "7", "slug": "70", "size": "clamp(2.5rem, 7vw, 5rem)"},
    {"name": "8", "slug": "80", "size": "clamp(3.5rem, 9vw, 7rem)"},
]

# System font stacks only: no web font files, no Google Fonts request.
STACKS = {
    "system": "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
    "humanist": "Seravek, 'Gill Sans Nova', Ubuntu, Calibri, 'DejaVu Sans', source-sans-pro, sans-serif",
    "geometric": "Avenir, Montserrat, Corbel, 'URW Gothic', source-sans-pro, sans-serif",
    "transitional": "Charter, 'Bitstream Charter', 'Sitka Text', Cambria, serif",
    "old-style": "'Iowan Old Style', 'Palatino Linotype', 'URW Palladio L', P052, serif",
    "didone": "Didot, 'Bodoni MT', 'Noto Serif Display', 'URW Palladio L', P052, Sylfaen, serif",
    "rounded": "ui-rounded, 'Hiragino Maru Gothic ProN', Quicksand, Comfortaa, Manjari, 'Arial Rounded MT', Calibri, source-sans-pro, sans-serif",
    "slab": "Rockwell, 'Rockwell Nova', 'Roboto Slab', 'DejaVu Serif', 'Sitka Small', serif",
}


def font_pair(pair_id, name, heading, body, heading_weight="650"):
    return {
        "id": pair_id,
        "name": name,
        "heading": {"name": name + " headings", "fontFamily": STACKS[heading], "fontWeight": heading_weight},
        "body": {"name": name + " body", "fontFamily": STACKS[body]},
    }


def base_styles(button_radius="8px"):
    """theme.json (v3) shaped global styles. The builder adds the palette and
    font families from the user's choices before saving."""
    return {
        "version": 3,
        "settings": {
            "typography": {"fontSizes": FONT_SIZES},
            "spacing": {"spacingSizes": SPACING_SIZES},
        },
        "styles": {
            # Starter pages end in a full-width band; without this the root
            # block gap leaves a strip of background above the footer.
            "css": ":where(.wp-site-blocks) > footer{margin-block-start:0}",
            "color": {"background": "var:preset|color|base", "text": "var:preset|color|contrast"},
            "typography": {
                "fontFamily": "var:preset|font-family|body",
                "fontSize": "var:preset|font-size|medium",
                "lineHeight": "1.65",
            },
            "spacing": {"blockGap": "1.25rem"},
            "elements": {
                "heading": {
                    "color": {"text": "var:preset|color|contrast"},
                    "typography": {
                        "fontFamily": "var:preset|font-family|heading",
                        # Replaced by the chosen font pair's heading weight.
                        "fontWeight": "650",
                        "lineHeight": "1.15",
                        "letterSpacing": "-0.01em",
                    },
                },
                "h1": {"typography": {"fontSize": "var:preset|font-size|xx-large"}},
                "h2": {"typography": {"fontSize": "var:preset|font-size|x-large"}},
                "h3": {"typography": {"fontSize": "var:preset|font-size|large"}},
                "h4": {"typography": {"fontSize": "var:preset|font-size|medium"}},
                "link": {
                    "color": {"text": "var:preset|color|primary"},
                    "typography": {"textDecoration": "underline"},
                    ":hover": {"color": {"text": "var:preset|color|contrast"}},
                },
                "button": {
                    "color": {"background": "var:preset|color|primary", "text": "var:preset|color|on-primary"},
                    # Overridden site-wide by the corner style setting.
                    "border": {"radius": "var(--wp--custom--radius--button, %s)" % button_radius},
                    "spacing": {"padding": {"top": "0.8rem", "right": "1.5rem", "bottom": "0.8rem", "left": "1.5rem"}},
                    "typography": {"fontSize": "var:preset|font-size|medium", "fontWeight": "600", "textDecoration": "none"},
                    ":hover": {"color": {"background": "var:preset|color|contrast", "text": "var:preset|color|base"}},
                    ":focus": {"color": {"background": "var:preset|color|contrast", "text": "var:preset|color|base"}},
                },
                "caption": {"color": {"text": "var:preset|color|muted"}, "typography": {"fontSize": "var:preset|font-size|small"}},
            },
            "blocks": {
                "core/quote": {
                    "border": {"left": {"color": "var:preset|color|primary", "width": "3px", "style": "solid"}},
                    "spacing": {"padding": {"left": "var:preset|spacing|40"}},
                    "typography": {"fontStyle": "normal"},
                },
                "core/navigation": {"typography": {"fontSize": "var:preset|font-size|medium"}},
                "core/site-title": {"elements": {"link": {"color": {"text": "var:preset|color|contrast"}, "typography": {"textDecoration": "none"}}}},
                "core/separator": {"color": {"text": "var:preset|color|muted"}},
                "core/details": {"spacing": {"padding": {"top": "var:preset|spacing|30", "bottom": "var:preset|spacing|30"}}},
            },
        },
    }


DEFAULT_FONT_PAIRS = [
    font_pair("modern", "Modern", "system", "system"),
    font_pair("classic", "Classic", "transitional", "system", "600"),
    font_pair("humanist", "Humanist", "humanist", "humanist"),
    font_pair("elegant", "Elegant", "old-style", "system", "600"),
    font_pair("geometric", "Geometric", "geometric", "system", "700"),
]


# ---------------------------------------------------------------------------
# Palette generation: a port of Color_Contrast::palette_from_brand(), so
# generated palettes pass the same WCAG AA pairs the validator checks.
# ---------------------------------------------------------------------------

import colorsys

TEXT_PAIRS = [("contrast", "base"), ("contrast", "surface"), ("contrast", "accent"), ("muted", "base"),
              ("muted", "surface"), ("primary", "base"), ("primary", "surface"), ("on-primary", "primary")]


def _lum(hexc):
    r, g, b = (int(hexc[i:i + 2], 16) / 255 for i in (1, 3, 5))
    f = lambda c: c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b)


def ratio(a, b):
    la, lb = _lum(a), _lum(b)
    return (max(la, lb) + 0.05) / (min(la, lb) + 0.05)


def _hsl(h, s, l):
    r, g, b = colorsys.hls_to_rgb((h % 360) / 360, max(0, min(1, l)), max(0, min(1, s)))
    return "#%02x%02x%02x" % (round(r * 255), round(g * 255), round(b * 255))


def palette(pid, name, brand, base="#ffffff"):
    """Light palette from one brand colour, nudged until every text pair passes AA."""
    r, g, b = (int(brand[i:i + 2], 16) / 255 for i in (1, 3, 5))
    h, l, s = colorsys.rgb_to_hls(r, g, b)
    h *= 360
    tint = min(s, 0.35)
    c = {
        "base": base,
        "surface": _hsl(h, tint, 0.965),
        "accent": _hsl(h, tint, 0.915),
        "contrast": _hsl(h, min(s, 0.30), 0.12),
        "muted": _hsl(h, min(s, 0.15), 0.36),
        "primary": _hsl(h, s, min(l, 0.45)),
        "on-primary": "#ffffff",
    }
    pl = min(l, 0.45)
    while pl > 0.08 and (ratio("#ffffff", c["primary"]) < 4.5 or ratio(c["primary"], c["surface"]) < 4.5 or ratio(c["primary"], c["base"]) < 4.5):
        pl -= 0.02
        c["primary"] = _hsl(h, s, pl)
    ml = 0.36
    while ml > 0.08 and (ratio(c["muted"], c["surface"]) < 4.5 or ratio(c["muted"], c["base"]) < 4.5):
        ml -= 0.02
        c["muted"] = _hsl(h, min(s, 0.15), ml)
    failing = [p for p in TEXT_PAIRS if ratio(c[p[0]], c[p[1]]) < 4.5]
    assert not failing, (pid, failing)
    return {"id": pid, "name": name, "colors": c}


def check_palette(p):
    """Fail the build early if a hand-made palette misses AA."""
    failing = [(a, b, round(ratio(p["colors"][a], p["colors"][b]), 2)) for a, b in TEXT_PAIRS if ratio(p["colors"][a], p["colors"][b]) < 4.5]
    assert not failing, (p["id"], failing)
    return p
