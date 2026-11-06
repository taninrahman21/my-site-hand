"""
Section kit for starter sites (dev tooling, never shipped).

Each builder declares its own slots and returns core block markup, so a site
definition reads like a page outline. Slot ids are "<page>.<section>.<field>".
"""

from blocks import (button, buttons, card, column, columns, details, eyebrow, group, heading, image, navigation,
                    paragraph, post_content, quote, section, separator, site_title, template_part)
from sections import centered_head, label_value

BAND = {"top": "var:preset|spacing|30", "bottom": "var:preset|spacing|30"}


def link(page):
    return "{{page:%s}}" % page


# ---------------------------------------------------------------- site parts

def basics(s, title, tagline, name, phone, email, address, hours=None, address_label="Address"):
    """Business basics group. Returns the tokens other sections reuse."""
    s.group("site/basics", "site", "Business basics")
    s.text("site.title", title, max=60, label="Site title", hint="Shown in the header, browser tab and search results.")
    s.text("site.tagline", tagline, max=100, label="Tagline")
    biz = {
        "name": s.text("biz.name", name, max=80, label="Business name"),
        "phone": s.text("biz.phone", phone, max=32, label="Phone"),
        "email": s.text("biz.email", email, max=80, label="Email"),
        "address": s.text("biz.address", address, max=120, label=address_label),
        "address_label": address_label,
    }
    if hours:
        biz["hours"] = s.rich("biz.hours", hours, max=200, label="Opening hours", hint="One line per day range.")
    return biz


def header(s, cta_label, cta_page="contact", title_size="large", weight="700", bg="base"):
    s.group("site/header", "site", "Header")
    return group(
        group(
            site_title(font_size=title_size, weight=weight),
            group(
                navigation(),
                buttons(button(s.text("header.cta", cta_label, max=24, label="Header button"), link(cta_page))),
                layout={"type": "flex", "flexWrap": "nowrap"}, gap="var:preset|spacing|40",
            ),
            align="wide", layout={"type": "flex", "flexWrap": "wrap", "justifyContent": "space-between"},
        ),
        align="full", bg=bg, padding=BAND, layout={"type": "constrained"},
    )


def footer(s, biz, about, legal, contact_heading="Get in touch", hours_heading="Opening hours"):
    s.group("site/footer", "site", "Footer")
    cols = [
        column(
            site_title(font_size="large"),
            paragraph(s.text("footer.about", about, label="About text"), text="muted"),
            width="40%",
        ),
        column(
            heading(s.text("footer.contact_heading", contact_heading, max=40, label="Contact heading"), 4),
            paragraph(biz["address"] + "<br>" + biz["phone"] + "<br>" + biz["email"], text="muted"),
        ),
    ]
    if "hours" in biz:
        cols.append(column(
            heading(s.text("footer.hours_heading", hours_heading, max=40, label="Hours heading"), 4),
            paragraph(biz["hours"], text="muted"),
        ))
    return group(
        columns(*cols, gap="var:preset|spacing|50"),
        group(
            separator(),
            paragraph("© " + biz["name"] + ". " + s.text("footer.legal", legal, max=120, label="Legal line"),
                      text="muted", font_size="small"),
            align="wide",
        ),
        align="full", bg="surface",
        padding={"top": "var:preset|spacing|70", "bottom": "var:preset|spacing|50"},
        layout={"type": "constrained"},
    )


def template():
    return {
        "starter-full-width": {
            "title": "Full width, no title",
            "markup": "\n\n".join([
                template_part("header", "header"),
                group(post_content(), tag_name="main", margin={"top": "0", "bottom": "0"}, layout={"type": "default"}),
                template_part("footer", "footer"),
            ]),
        },
    }


# ------------------------------------------------------------------ sections

def _buttons(s, prefix, primary, secondary=None, justify=None, top="50"):
    items = [button(s.text(prefix + ".cta_primary", primary[0], max=28), link(primary[1]))]
    if secondary:
        items.append(button(s.text(prefix + ".cta_secondary", secondary[0], max=28), link(secondary[1]), outline=True))
    return buttons(*items, justify=justify, style={"spacing": {"margin": {"top": "var:preset|spacing|%s" % top}}})


def hero_split(s, page, eb, head, sub, primary, secondary=None, img=None, bg="surface", aspect="4/3",
               radius="16px", text_width="52%", pad="70", hint="The main promise, in under ten words."):
    """Text beside an image."""
    s.group("%s/hero" % page, page, "Hero")
    p = page + ".hero"
    text = column(
        eyebrow(s.text(p + ".eyebrow", eb, max=40)),
        heading(s.text(p + ".heading", head, max=80, hint=hint), 1, font_size="huge"),
        paragraph(s.text(p + ".subheading", sub, max=220), text="muted", font_size="large"),
        _buttons(s, p, primary, secondary),
        width=text_width, vcenter=True,
    )
    pic = column(
        image(s.image("image:%s.hero" % page, img[0], label="Hero image"),
              s.text(p + ".image_alt", img[1], max=140, label="Hero image description",
                     hint="Describe the image for people who cannot see it."),
              aspect=aspect, radius=radius),
        vcenter=True,
    )
    return section(columns(text, pic, vcenter=True, gap="var:preset|spacing|70"), bg=bg, pad=pad)


def hero_center(s, page, eb, head, sub, primary, secondary=None, bg="base", text=None, pad="80", width="860px"):
    """Big centred statement, no image."""
    s.group("%s/hero" % page, page, "Hero")
    p = page + ".hero"
    return section(
        group(
            eyebrow(s.text(p + ".eyebrow", eb, max=40), align="center", text=text or "primary"),
            heading(s.text(p + ".heading", head, max=90, hint="The main promise, in one line."), 1,
                    font_size="huge", align="center", text=text),
            paragraph(s.text(p + ".subheading", sub, max=220), align="center", font_size="large",
                      text=text or "muted"),
            _buttons(s, p, primary, secondary, justify="center"),
            layout={"type": "constrained", "contentSize": width},
        ),
        bg=bg, text=text, pad=pad,
    )


def stats(s, page, sid, items, bg="base", label="Highlights"):
    """A row of big numbers (or short facts) with a caption each."""
    s.group("%s/%s" % (page, sid), page, label)
    cols = []
    for i, (value, caption) in enumerate(items, 1):
        cols.append(column(
            paragraph(s.text("%s.%s.item%d_value" % (page, sid, i), value, max=16, label="Fact %d number" % i),
                      text="primary", font_size="xx-large", style={"typography": {"fontWeight": "700", "lineHeight": "1.1"}}),
            paragraph(s.text("%s.%s.item%d_text" % (page, sid, i), caption, max=80, label="Fact %d caption" % i), text="muted"),
        ))
    return section(columns(*cols, gap="var:preset|spacing|50"), bg=bg, pad="60")


def features(s, page, sid, label, eb, head, intro, items, bg="surface", card_bg="base", radius="16px",
             more=None, prefix="Item"):
    """Centred heading, then a row of cards (3 per row; 6 items make two rows)."""
    s.group("%s/%s" % (page, sid), page, label)
    rows = []
    for start in range(0, len(items), 3):
        cols = []
        for i in range(start, min(start + 3, len(items))):
            title, text = items[i]
            cols.append(column(card(
                heading(s.text("%s.%s.item%d_title" % (page, sid, i + 1), title, label="%s %d title" % (prefix, i + 1)), 3),
                paragraph(s.text("%s.%s.item%d_text" % (page, sid, i + 1), text, label="%s %d text" % (prefix, i + 1)), text="muted"),
                bg=card_bg, radius=radius,
            )))
        rows.append(columns(*cols, gap="var:preset|spacing|40"))
    parts = [centered_head(s, "%s.%s" % (page, sid), eb, head, intro)] + rows
    if more:
        parts.append(buttons(button(s.text("%s.%s.link" % (page, sid), more[0], max=28), link(more[1]), outline=True),
                             justify="center", style={"spacing": {"margin": {"top": "var:preset|spacing|50"}}}))
    return section(*parts, bg=bg)


def image_text(s, page, sid, label, eb, head, body, img, btn=None, image_left=True, bg="base", radius="16px",
               aspect="4/3", body2=None):
    """Image beside text with an optional button."""
    s.group("%s/%s" % (page, sid), page, label)
    p = "%s.%s" % (page, sid)
    text_parts = [
        eyebrow(s.text(p + ".eyebrow", eb, max=40)),
        heading(s.text(p + ".heading", head)),
        paragraph(s.rich(p + ".body1" if body2 else p + ".body", body)),
    ]
    if body2:
        text_parts.append(paragraph(s.rich(p + ".body2", body2)))
    if btn:
        text_parts.append(buttons(button(s.text(p + ".button", btn[0], max=28), link(btn[1]), outline=True)))
    text = column(*text_parts, width="50%", vcenter=True)
    pic = column(
        image(s.image("image:%s" % p, img[0], label="Image"),
              s.text(p + ".image_alt", img[1], max=140, label="Image description"),
              aspect=aspect, radius=radius),
        width="50%", vcenter=True,
    )
    cols = [pic, text] if image_left else [text, pic]
    return section(columns(*cols, vcenter=True, gap="var:preset|spacing|70"), bg=bg)


def steps(s, page, sid, label, head, items, bg="base", numbered=True):
    """Numbered steps with a coloured rule on top."""
    s.group("%s/%s" % (page, sid), page, label)
    cols = []
    for i, (title, text) in enumerate(items, 1):
        parts = []
        if numbered:
            parts.append(paragraph("0%d" % i, text="primary", font_size="x-large", style={"typography": {"fontWeight": "700"}}))
        parts += [
            heading(s.text("%s.%s.item%d_title" % (page, sid, i), title, label="Step %d title" % i), 3),
            paragraph(s.text("%s.%s.item%d_text" % (page, sid, i), text, label="Step %d text" % i), text="muted"),
        ]
        cols.append(column(group(*parts, border_top={"color": "var:preset|color|primary", "width": "3px"},
                                 padding={"top": "var:preset|spacing|40"})))
    return section(
        group(heading(s.text("%s.%s.heading" % (page, sid), head)), align="wide"),
        columns(*cols, gap="var:preset|spacing|50"),
        bg=bg,
    )


def testimonial(s, page, text, name, bg="accent", sid="testimonial"):
    s.group("%s/%s" % (page, sid), page, "Testimonial")
    return section(
        group(
            quote(s.text("%s.%s.quote" % (page, sid), text, max=280), s.text("%s.%s.name" % (page, sid), name, max=70, label="Name")),
            layout={"type": "constrained", "contentSize": "720px"},
        ),
        bg=bg, pad="70",
    )


def price_list(s, page, sid, label, head, items, bg="base", note=None):
    """Two columns of name, price and a short description."""
    s.group("%s/%s" % (page, sid), page, label)
    rows = []
    for i, (name, desc, price) in enumerate(items, 1):
        pfx = "%s.%s.item%d" % (page, sid, i)
        rows.append(group(
            group(
                heading(s.text(pfx + "_name", name, max=60, label="Item %d name" % i), 3, font_size="medium"),
                paragraph(s.text(pfx + "_price", price, max=16, label="Item %d price" % i), style={"typography": {"fontWeight": "600"}}),
                layout={"type": "flex", "flexWrap": "nowrap", "justifyContent": "space-between", "verticalAlignment": "top"},
            ),
            paragraph(s.text(pfx + "_desc", desc, label="Item %d description" % i), text="muted", font_size="small"),
            gap="0.25rem",
        ))
    half = (len(rows) + 1) // 2
    parts = [
        group(heading(s.text("%s.%s.heading" % (page, sid), head, max=50, label="Section heading")), align="wide"),
        columns(column(*rows[:half]), column(*rows[half:]), gap="var:preset|spacing|70"),
    ]
    if note:
        parts.append(group(paragraph(s.text("%s.%s.note" % (page, sid), note, label="Note"), text="muted", font_size="small"), align="wide"))
    return section(*parts, bg=bg, pad="60")


def pricing(s, page, sid, label, eb, head, intro, plans, bg="base", radius="16px", cta_page="contact"):
    """Plan cards: name, price, what is included, button.

    Cards sit on "surface" so the primary-coloured price always passes the
    palette contrast checks (primary on surface is a checked pair). The
    featured plan is marked with a label and a solid button instead of a
    different background.
    """
    s.group("%s/%s" % (page, sid), page, label)
    cols = []
    for i, (name, price, period, included, btn, featured) in enumerate(plans, 1):
        pfx = "%s.%s.plan%d" % (page, sid, i)
        badge = [eyebrow(s.text(pfx + "_badge", "Most popular", max=24, label="Plan %d label" % i))] if featured else []
        cols.append(column(card(
            *badge,
            heading(s.text(pfx + "_name", name, max=40, label="Plan %d name" % i), 3),
            paragraph(s.text(pfx + "_price", price, max=16, label="Plan %d price" % i),
                      font_size="xx-large", text="primary", style={"typography": {"fontWeight": "700", "lineHeight": "1.1"}}),
            paragraph(s.text(pfx + "_period", period, max=40, label="Plan %d price note" % i), text="muted", font_size="small"),
            paragraph(s.rich(pfx + "_included", included, label="Plan %d includes" % i, hint="One item per line.")),
            buttons(button(s.text(pfx + "_button", btn, max=28, label="Plan %d button" % i), link(cta_page), outline=not featured)),
            bg="surface", radius=radius,
        )))
    return section(
        centered_head(s, "%s.%s" % (page, sid), eb, head, intro),
        columns(*cols, gap="var:preset|spacing|40"),
        bg=bg,
    )


def faq(s, page, sid, head, items, bg="surface"):
    s.group("%s/%s" % (page, sid), page, "Questions")
    blocks = []
    for i, (q, a) in enumerate(items, 1):
        blocks.append(details(s.text("%s.%s.q%d" % (page, sid, i), q, max=120, label="Question %d" % i),
                              s.rich("%s.%s.a%d" % (page, sid, i), a, label="Answer %d" % i)))
        blocks.append(separator())
    return section(
        group(heading(s.text("%s.%s.heading" % (page, sid), head)), *blocks[:-1],
              layout={"type": "constrained", "contentSize": "760px"}),
        bg=bg,
    )


def team(s, page, sid, head, intro, members, bg="base", card_bg="surface", radius="16px"):
    s.group("%s/%s" % (page, sid), page, "Team")
    cols = []
    for i, (name, role, bio) in enumerate(members, 1):
        pfx = "%s.%s.member%d" % (page, sid, i)
        cols.append(column(card(
            heading(s.text(pfx + "_name", name, max=60, label="Person %d name" % i), 3),
            eyebrow(s.text(pfx + "_role", role, max=60, label="Person %d role" % i)),
            paragraph(s.text(pfx + "_bio", bio, label="Person %d bio" % i), text="muted"),
            bg=card_bg, radius=radius,
        )))
    return section(
        group(
            heading(s.text("%s.%s.heading" % (page, sid), head), align="center"),
            paragraph(s.text("%s.%s.intro" % (page, sid), intro), align="center", text="muted", font_size="large"),
            layout={"type": "constrained", "contentSize": "680px"},
        ),
        columns(*cols, gap="var:preset|spacing|40"),
        bg=bg,
    )


def listings(s, page, sid, label, head, items, bg="base", radius="12px", more=None):
    """Property-style cards: image, price, title, short facts."""
    s.group("%s/%s" % (page, sid), page, label)
    cols = []
    for i, (img, alt, price, title, facts) in enumerate(items, 1):
        pfx = "%s.%s.item%d" % (page, sid, i)
        cols.append(column(
            image(s.image("image:%s.%s%d" % (page, sid, i), img, label="Listing %d photo" % i),
                  s.text(pfx + "_alt", alt, max=140, label="Listing %d photo description" % i), aspect="4/3", radius=radius),
            paragraph(s.text(pfx + "_price", price, max=24, label="Listing %d price" % i),
                      text="primary", font_size="x-large", style={"typography": {"fontWeight": "700"}}),
            heading(s.text(pfx + "_title", title, max=60, label="Listing %d title" % i), 3, font_size="medium"),
            paragraph(s.text(pfx + "_facts", facts, max=80, label="Listing %d details" % i), text="muted", font_size="small"),
        ))
    parts = [group(heading(s.text("%s.%s.heading" % (page, sid), head)), align="wide"),
             columns(*cols, gap="var:preset|spacing|50")]
    if more:
        parts.append(buttons(button(s.text("%s.%s.link" % (page, sid), more[0], max=28), link(more[1]), outline=True),
                             justify="center", style={"spacing": {"margin": {"top": "var:preset|spacing|50"}}}))
    return section(*parts, bg=bg)


def contact(s, page, biz, box_heading, side_heading, side_body, extra_heading=None, extra_body=None, bg="base",
            radius="16px"):
    """Contact details card beside directions or booking notes."""
    s.group("%s/details" % page, page, "Details")
    rows = [label_value("Phone", biz["phone"]), label_value("Email", biz["email"]),
            label_value(biz.get("address_label", "Address"), biz["address"])]
    if "hours" in biz:
        rows.append(group(
            paragraph("Opening hours", text="muted", font_size="small",
                      style={"typography": {"fontWeight": "600", "textTransform": "uppercase", "letterSpacing": "0.08em"}}),
            paragraph(biz["hours"]), gap="0.25rem"))
    side = [heading(s.text(page + ".details.side_heading", side_heading, label="Side heading")),
            paragraph(s.rich(page + ".details.side_body", side_body, label="Side text"))]
    if extra_heading:
        side += [heading(s.text(page + ".details.extra_heading", extra_heading, label="Second heading"), 3),
                 paragraph(s.rich(page + ".details.extra_body", extra_body, label="Second text"))]
    return section(
        columns(
            column(card(heading(s.text(page + ".details.heading", box_heading, label="Contact box heading"), font_size="x-large"),
                        *rows, bg="surface", radius=radius)),
            column(*side),
            gap="var:preset|spacing|70",
        ),
        bg=bg,
    )
