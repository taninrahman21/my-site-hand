"""Reusable page sections for starter sites (dev tooling, never shipped)."""

from blocks import (button, buttons, card, column, columns, eyebrow, group, heading, paragraph, section)


def page_intro(s, page, eyebrow_text, heading_text, lead, bg="surface"):
    """Title band at the top of an inner page."""
    s.group("%s/intro" % page, page, "Introduction")
    return section(
        group(
            eyebrow(s.text(page + ".intro.eyebrow", eyebrow_text, max=40)),
            heading(s.text(page + ".intro.heading", heading_text), 1, font_size="xx-large"),
            paragraph(s.text(page + ".intro.lead", lead), text="muted", font_size="large"),
            align="wide", layout={"type": "constrained", "contentSize": "760px", "justifyContent": "left"},
        ),
        bg=bg, pad="70",
    )


def cta_band(s, page, heading_text, text, label, link="contact"):
    """Full-width primary-colour call to action."""
    s.group("%s/cta" % page, page, "Call to action")
    return section(
        group(
            heading(s.text(page + ".cta.heading", heading_text), align="center", text="on-primary"),
            paragraph(s.text(page + ".cta.text", text), align="center", font_size="large", text="on-primary"),
            buttons(button(s.text(page + ".cta.button", label, max=28), "{{page:%s}}" % link, bg="base", text="contrast"),
                    justify="center", style={"spacing": {"margin": {"top": "var:preset|spacing|40"}}}),
            layout={"type": "constrained", "contentSize": "720px"},
        ),
        bg="primary", text="on-primary", pad="70",
    )


def card_row(s, page, section_id, items, label_prefix="Item", card_bg="base", radius="16px"):
    """A row of equal cards, each a heading and a short text."""
    cols = []
    for i, (title, text) in enumerate(items, 1):
        cols.append(column(card(
            heading(s.text("%s.%s.item%d_title" % (page, section_id, i), title, label="%s %d title" % (label_prefix, i)), 3),
            paragraph(s.text("%s.%s.item%d_text" % (page, section_id, i), text, label="%s %d text" % (label_prefix, i)), text="muted"),
            bg=card_bg, radius=radius,
        )))
    return columns(*cols, gap="var:preset|spacing|40")


def centered_head(s, prefix, eyebrow_text, heading_text, intro=None, width="680px"):
    """Centred eyebrow, heading and optional intro above a section's content."""
    parts = [
        eyebrow(s.text(prefix + ".eyebrow", eyebrow_text, max=40), align="center"),
        heading(s.text(prefix + ".heading", heading_text), align="center"),
    ]
    if intro:
        parts.append(paragraph(s.text(prefix + ".intro", intro), align="center", text="muted", font_size="large"))
    return group(*parts, layout={"type": "constrained", "contentSize": width})


def label_value(label, token, label_style=None):
    """A small uppercase label above a value, for contact details."""
    return group(
        paragraph(label, text="muted", font_size="small",
                  style=label_style or {"typography": {"fontWeight": "600", "textTransform": "uppercase", "letterSpacing": "0.08em"}}),
        paragraph(token, font_size="large"),
        gap="0.25rem",
    )
