"""Portfolio starter site: an independent brand and web designer."""

from blocks import (Site, button, buttons, card, column, columns, eyebrow, group, heading, image, navigation,
                    paragraph, post_content, quote, section, separator, site_title, template_part)
from common import base_styles, font_pair
from sections import card_row, cta_band, label_value, page_intro

MANIFEST = {
    "slug": "portfolio",
    "name": "Portfolio",
    "category": "creative",
    "description": "A clean, type-led portfolio for a designer, photographer or studio. Selected work, case studies, services and an easy way to get in touch.",
    "version": "1.0.0",
}

PALETTES = [
    {"id": "ink", "name": "Ink", "colors": {
        "base": "#ffffff", "surface": "#f4f4f1", "accent": "#e9e7e1", "contrast": "#111111",
        "muted": "#545454", "primary": "#111111", "on-primary": "#ffffff"}},
    {"id": "electric", "name": "Electric blue", "colors": {
        "base": "#ffffff", "surface": "#f2f4fb", "accent": "#e1e6f8", "contrast": "#0d1020",
        "muted": "#4d5266", "primary": "#2b44d6", "on-primary": "#ffffff"}},
    {"id": "coral", "name": "Coral", "colors": {
        "base": "#fffaf8", "surface": "#fbeee9", "accent": "#f6ddd4", "contrast": "#26140f",
        "muted": "#5e4840", "primary": "#b23b1e", "on-primary": "#ffffff"}},
    {"id": "forest", "name": "Forest", "colors": {
        "base": "#fbfcfa", "surface": "#edf1ec", "accent": "#dbe4d9", "contrast": "#132018",
        "muted": "#4a5a4f", "primary": "#1f5c3a", "on-primary": "#ffffff"}},
    {"id": "midnight", "name": "Midnight", "colors": {
        "base": "#0e0f13", "surface": "#17191f", "accent": "#22252d", "contrast": "#f2f2ee",
        "muted": "#a9abb3", "primary": "#c9f26b", "on-primary": "#0e0f13"}},
    {"id": "sunflower", "name": "Sunflower", "colors": {
        "base": "#fffdf5", "surface": "#fbf4dc", "accent": "#f5e7b5", "contrast": "#1d1a0c",
        "muted": "#55502f", "primary": "#6e5800", "on-primary": "#ffffff"}},
]

FONT_PAIRS = [
    font_pair("geometric", "Geometric", "geometric", "system", "700"),
    font_pair("modern", "Modern", "system", "system", "700"),
    font_pair("editorial", "Editorial", "didone", "system", "500"),
    font_pair("humanist", "Humanist", "humanist", "humanist", "600"),
    font_pair("soft", "Soft", "rounded", "system", "700"),
]

STYLES = base_styles(button_radius="0px")

NAVIGATION = [
    {"label": "Work", "page": "work"},
    {"label": "Services", "page": "services"},
    {"label": "About", "page": "about"},
    {"label": "Contact", "page": "contact"},
]

PROJECTS = [
    ("Harbor Coffee Roasters", "Brand identity and packaging",
     "A new identity for a small-batch roaster: logo, bag labels, menu boards and a simple online shop.",
     "assets/project-1.webp", "Illustration of coffee bags and a cup with a bold circular logo"),
    ("Fieldnotes Journal", "Editorial design and website",
     "A magazine layout system and a fast reading website for an independent nature journal.",
     "assets/project-2.webp", "Illustration of an open magazine spread with large type and leaf shapes"),
    ("Northside Climbing", "Website and signage",
     "A booking website and wayfinding signs for a climbing gym, built around bright route colours.",
     "assets/project-3.webp", "Illustration of a phone and a laptop showing a colourful climbing website"),
]


def build(s: Site):
    pages = {}

    # ------------------------------------------------------------------ site
    s.group("site/basics", "site", "Business basics")
    s.text("site.title", "Noor Haddad", max=60, label="Site title", hint="Your name or studio name.")
    s.text("site.tagline", "Brand and web designer", max=100, label="Tagline")
    biz_name = s.text("biz.name", "Noor Haddad Studio", max=80, label="Business name")
    biz_phone = s.text("biz.phone", "(555) 018-2365", max=32, label="Phone")
    biz_email = s.text("biz.email", "hello@noorhaddad.example", max=80, label="Email")
    biz_address = s.text("biz.address", "Lisbon, working with clients worldwide", max=120, label="Location")

    s.group("site/header", "site", "Header")
    header = group(
        group(
            site_title(font_size="large", weight="700"),
            group(
                navigation(),
                buttons(button(s.text("header.cta", "Start a project", max=24, label="Header button"), "{{page:contact}}")),
                layout={"type": "flex", "flexWrap": "nowrap"}, gap="var:preset|spacing|40",
            ),
            align="wide", layout={"type": "flex", "flexWrap": "wrap", "justifyContent": "space-between"},
        ),
        align="full", bg="base",
        padding={"top": "var:preset|spacing|40", "bottom": "var:preset|spacing|40"},
        layout={"type": "constrained"},
    )

    s.group("site/footer", "site", "Footer")
    footer = group(
        columns(
            column(
                heading(s.text("footer.heading", "Have a project in mind?", max=60, label="Footer heading"), 2, font_size="xx-large"),
                buttons(button(s.text("footer.button", "Get in touch", max=24, label="Footer button"), "{{page:contact}}")),
                width="60%",
            ),
            column(
                paragraph(biz_email + "<br>" + biz_phone + "<br>" + biz_address, text="muted"),
                width="40%",
            ),
            gap="var:preset|spacing|60",
        ),
        group(
            separator(),
            paragraph("© " + biz_name + ". " + s.text("footer.legal", "Designed with care, built with WordPress.", max=80, label="Legal line"),
                      text="muted", font_size="small"),
            align="wide",
        ),
        align="full", bg="surface",
        padding={"top": "var:preset|spacing|70", "bottom": "var:preset|spacing|50"},
        layout={"type": "constrained"},
    )

    # ------------------------------------------------------------------ home
    s.group("home/hero", "home", "Hero")
    hero = section(
        group(
            eyebrow(s.text("home.hero.eyebrow", "Brand and web design", max=40), text="muted"),
            heading(s.text("home.hero.heading", "I help small businesses look as good as they are", max=80,
                           hint="What you do and for whom, in one line."), 1, font_size="huge"),
            paragraph(s.text("home.hero.subheading",
                             "Clear brands and fast, friendly websites for independent shops, cafés and studios. Based in Lisbon, working everywhere.",
                             max=220), text="muted", font_size="large"),
            buttons(
                button(s.text("home.hero.cta_primary", "See my work", max=28), "{{page:work}}"),
                button(s.text("home.hero.cta_secondary", "Start a project", max=28), "{{page:contact}}", outline=True),
                style={"spacing": {"margin": {"top": "var:preset|spacing|50"}}},
            ),
            align="wide", layout={"type": "constrained", "contentSize": "880px", "justifyContent": "left"},
        ),
        pad="80",
    )

    s.group("home/work", "home", "Selected work")
    work_cols = []
    for i, (title, cat, _desc, img, alt) in enumerate(PROJECTS, 1):
        work_cols.append(column(
            image(s.image("image:home.work%d" % i, img, label="Project %d image" % i),
                  s.text("home.work.item%d_alt" % i, alt, max=140, label="Project %d image description" % i),
                  radius=None),
            heading(s.text("home.work.item%d_title" % i, title, label="Project %d name" % i), 3),
            paragraph(s.text("home.work.item%d_text" % i, cat, label="Project %d type" % i), text="muted", font_size="small"),
        ))
    work = section(
        group(
            group(
                heading(s.text("home.work.heading", "Selected work", max=40)),
                paragraph('<a href="{{page:work}}">' + s.text("home.work.link", "All projects", max=24, label="Link text") + '</a>'),
                layout={"type": "flex", "flexWrap": "wrap", "justifyContent": "space-between", "verticalAlignment": "bottom"},
            ),
            align="wide",
        ),
        columns(*work_cols, gap="var:preset|spacing|50"),
        pad="60",
    )

    s.group("home/services", "home", "Services")
    services = section(
        group(heading(s.text("home.services.heading", "What I can help with", max=60)), align="wide"),
        card_row(s, "home", "services", [
            ("Brand identity", "Logo, colours, type and a short guide, so everything you make looks like you."),
            ("Websites", "Simple WordPress sites you can update yourself, fast on every phone."),
            ("Print and packaging", "Labels, menus, signs and anything else that needs to look good in hand."),
        ], "Service", "base", radius=None),
        bg="surface",
    )

    s.group("home/testimonial", "home", "Testimonial")
    testimonial = section(
        group(
            quote(s.text("home.testimonial.quote",
                         "Noor listened more than she talked, and the new brand finally feels like us. Sales of our bagged coffee doubled.",
                         max=260),
                  s.text("home.testimonial.name", "Tomás P., Harbor Coffee Roasters", max=60, label="Name")),
            layout={"type": "constrained", "contentSize": "760px"},
        ),
        pad="70",
    )

    pages["home"] = "\n\n".join([hero, work, services, testimonial,
        cta_band(s, "home", "Let's make something good", "Tell me about your project. I reply to every message within two working days.", "Start a project")])

    # ------------------------------------------------------------------ work
    intro = page_intro(s, "work", "Work", "Selected projects",
                       "A few recent projects for independent businesses. More on request.")
    case_blocks = []
    for i, (title, cat, desc, img, alt) in enumerate(PROJECTS, 1):
        s.group("work/project%d" % i, "work", "Project %d" % i)
        img_col = column(
            image(s.image("image:work.project%d" % i, img, label="Image"),
                  s.text("work.project%d.image_alt" % i, alt, max=140, label="Image description"),
                  radius=None),
            width="55%", vcenter=True,
        )
        text_col = column(
            eyebrow(s.text("work.project%d.category" % i, cat, max=60, label="Category"), text="muted"),
            heading(s.text("work.project%d.title" % i, title, label="Project name")),
            paragraph(s.rich("work.project%d.body" % i, desc + " <strong>Results:</strong> " + [
                "a brand the owners use on everything, from cups to vans.",
                "readers now spend twice as long on each article.",
                "online bookings replaced the phone queue within a month."][i - 1], label="Description")),
            width="45%", vcenter=True,
        )
        cols = [img_col, text_col] if i % 2 else [text_col, img_col]
        case_blocks.append(section(columns(*cols, vcenter=True, gap="var:preset|spacing|70"),
                                   bg="surface" if i % 2 == 0 else None, pad="70"))
    pages["work"] = "\n\n".join([intro] + case_blocks +
        [cta_band(s, "work", "Want results like these?", "I take on a few new projects each season.", "Start a project")])

    # -------------------------------------------------------------- services
    intro = page_intro(s, "services", "Services", "Design that works as hard as you do",
                       "Fixed-price packages, so you know the cost before we start.")
    s.group("services/packages", "services", "Packages")
    packages = section(
        card_row(s, "services", "packages", [
            ("Brand starter", "Logo, colour palette, two typefaces and a one-page guide. Two rounds of changes. From $1,800."),
            ("Website", "A WordPress site of up to six pages, set up so you can edit it yourself. From $3,500."),
            ("Brand and website", "Both, designed together so everything matches. From $4,900."),
        ], "Package", "surface", radius=None),
    )
    s.group("services/process", "services", "Process")
    steps = []
    for i, (title, text) in enumerate([
        ("Talk", "A free 30-minute call about your business, your customers and your goals."),
        ("Design", "I share first ideas within two weeks, then we refine them together."),
        ("Launch", "Final files, your new site live, and a short guide so you can keep it going."),
    ], 1):
        steps.append(column(group(
            paragraph("0%d" % i, text="primary", font_size="x-large", style={"typography": {"fontWeight": "700"}}),
            heading(s.text("services.process.item%d_title" % i, title, label="Step %d title" % i), 3),
            paragraph(s.text("services.process.item%d_text" % i, text, label="Step %d text" % i), text="muted"),
            border_top={"color": "var:preset|color|contrast", "width": "2px"},
            padding={"top": "var:preset|spacing|40"},
        )))
    process = section(
        group(heading(s.text("services.process.heading", "How we will work together", max=60)), align="wide"),
        columns(*steps, gap="var:preset|spacing|50"),
        bg="surface",
    )
    pages["services"] = "\n\n".join([intro, packages, process,
        cta_band(s, "services", "Not sure what you need?", "Book a free call and we will work it out together.", "Book a call")])

    # ----------------------------------------------------------------- about
    intro = page_intro(s, "about", "About", "Hello, I'm Noor",
                       "A brand and web designer who likes small businesses, clear words and good coffee.")
    s.group("about/bio", "about", "Bio")
    bio = section(
        columns(
            column(
                image(s.image("image:about.portrait", "assets/portrait.webp", label="Portrait"),
                      s.text("about.bio.image_alt", "Illustrated portrait of a designer at her desk with sketches pinned on the wall", max=140,
                             label="Portrait description"),
                      aspect="4/5", radius=None),
                width="40%",
            ),
            column(
                paragraph(s.rich("about.bio.body1",
                                 "I spent eight years at design studios in London and Lisbon, working for large brands. I loved the craft but missed knowing the people I designed for.")),
                paragraph(s.rich("about.bio.body2",
                                 "Since 2020 I have worked on my own with cafés, shops, makers and small studios. <strong>You work with me directly</strong>, from the first call to the last file.")),
                heading(s.text("about.bio.clients_heading", "Some people I have worked with", max=60), 3),
                paragraph(s.text("about.bio.clients", "Harbor Coffee Roasters, Fieldnotes Journal, Northside Climbing, Casa Verde Ceramics, The Little Print Shop", max=240,
                                 label="Clients"), text="muted"),
                width="60%", vcenter=True,
            ),
            gap="var:preset|spacing|70",
        ),
    )
    pages["about"] = "\n\n".join([intro, bio,
        cta_band(s, "about", "Say hello", "I would love to hear what you are working on.", "Get in touch")])

    # --------------------------------------------------------------- contact
    intro = page_intro(s, "contact", "Contact", "Let's talk about your project",
                       "Email is best. Tell me a little about your business, what you need and when you need it.")
    s.group("contact/details", "contact", "Details")
    contact = section(
        columns(
            column(
                label_value("Email", biz_email),
                label_value("Phone", biz_phone),
                label_value("Based in", biz_address),
            ),
            column(
                card(
                    heading(s.text("contact.availability.heading", "Availability", max=40), 3),
                    paragraph(s.rich("contact.availability.body",
                                     "I am booking projects for <strong>next season</strong>. Small updates for existing clients can usually start within a week.")),
                    bg="surface", radius=None,
                ),
            ),
            gap="var:preset|spacing|70",
        ),
    )
    pages["contact"] = "\n\n".join([intro, contact])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": pages["home"]},
            {"slug": "work", "title": "Work", "markup": pages["work"]},
            {"slug": "services", "title": "Services", "markup": pages["services"]},
            {"slug": "about", "title": "About", "markup": pages["about"]},
            {"slug": "contact", "title": "Contact", "markup": pages["contact"]},
        ],
        "parts": {"header": header, "footer": footer},
        "templates": {
            "starter-full-width": {
                "title": "Full width, no title",
                "markup": "\n\n".join([
                    template_part("header", "header"),
                    group(post_content(), tag_name="main", margin={"top": "0", "bottom": "0"}, layout={"type": "default"}),
                    template_part("footer", "footer"),
                ]),
            },
        },
    }
