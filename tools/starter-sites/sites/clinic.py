"""Clinic starter site: a family medical practice."""

from blocks import (Site, button, buttons, card, column, columns, details, eyebrow, group, heading, image,
                    navigation, paragraph, post_content, quote, section, separator, site_title, template_part)
from common import DEFAULT_FONT_PAIRS, base_styles

MANIFEST = {
    "slug": "clinic",
    "name": "Clinic",
    "category": "health",
    "description": "A calm, trustworthy site for a family doctor, dental practice or small clinic. Services, team, new patient FAQ and contact details.",
    "version": "1.0.0",
}

PALETTES = [
    {"id": "teal", "name": "Calm teal", "colors": {
        "base": "#ffffff", "surface": "#f1f7f6", "accent": "#dcefeb", "contrast": "#11292b",
        "muted": "#4a5e60", "primary": "#0d6b66", "on-primary": "#ffffff"}},
    {"id": "sky", "name": "Clear sky", "colors": {
        "base": "#ffffff", "surface": "#f3f6fb", "accent": "#e0e9f7", "contrast": "#0f1c2e",
        "muted": "#4a5568", "primary": "#1b5cb8", "on-primary": "#ffffff"}},
    {"id": "sage", "name": "Sage", "colors": {
        "base": "#fbfbf7", "surface": "#eff2e8", "accent": "#e1e8d6", "contrast": "#1f2a1f",
        "muted": "#4f594d", "primary": "#3c6642", "on-primary": "#ffffff"}},
    {"id": "sand", "name": "Warm sand", "colors": {
        "base": "#fffcf7", "surface": "#f7efe4", "accent": "#f0e1cd", "contrast": "#2b1d12",
        "muted": "#5d4d3f", "primary": "#94461c", "on-primary": "#ffffff"}},
    {"id": "plum", "name": "Soft plum", "colors": {
        "base": "#ffffff", "surface": "#f7f2f7", "accent": "#eddff0", "contrast": "#241326",
        "muted": "#59495b", "primary": "#762d7a", "on-primary": "#ffffff"}},
    {"id": "night", "name": "Night shift", "colors": {
        "base": "#0f1a1c", "surface": "#162528", "accent": "#1f3538", "contrast": "#eaf2f1",
        "muted": "#a8b9b8", "primary": "#6fd3c8", "on-primary": "#0b1716"}},
]

FONT_PAIRS = DEFAULT_FONT_PAIRS

STYLES = base_styles(button_radius="999px")

NAVIGATION = [
    {"label": "Home", "page": "home"},
    {"label": "Services", "page": "services"},
    {"label": "About", "page": "about"},
    {"label": "New patients", "page": "new-patients"},
    {"label": "Contact", "page": "contact"},
]


def cta_band(s, prefix, heading_text, text, label):
    s.group("%s/cta" % prefix.split(".")[0], prefix.split(".")[0], "Call to action")
    return section(
        group(
            heading(s.text(prefix + ".cta.heading", heading_text), align="center", text="on-primary"),
            paragraph(s.text(prefix + ".cta.text", text), align="center", font_size="large", text="on-primary"),
            buttons(button(s.text(prefix + ".cta.button", label, max=28), "{{page:contact}}", bg="base", text="contrast"),
                    justify="center", style={"spacing": {"margin": {"top": "var:preset|spacing|40"}}}),
            layout={"type": "constrained", "contentSize": "720px"},
        ),
        bg="primary", text="on-primary", pad="70",
    )


def page_intro(s, page, eyebrow_text, heading_text, lead):
    s.group("%s/intro" % page, page, "Introduction")
    return section(
        group(
            eyebrow(s.text(page + ".intro.eyebrow", eyebrow_text, max=40)),
            heading(s.text(page + ".intro.heading", heading_text), 1, font_size="xx-large"),
            paragraph(s.text(page + ".intro.lead", lead), text="muted", font_size="large"),
            align="wide", layout={"type": "constrained", "contentSize": "760px", "justifyContent": "left"},
        ),
        bg="surface", pad="70",
    )


def three_cards(s, page, section_id, items, bg="base"):
    cols = []
    for i, (title, text) in enumerate(items, 1):
        cols.append(column(card(
            heading(s.text("%s.%s.item%d_title" % (page, section_id, i), title), 3),
            paragraph(s.text("%s.%s.item%d_text" % (page, section_id, i), text), text="muted"),
            bg=bg,
        )))
    return columns(*cols, gap="var:preset|spacing|40")


def build(s: Site):
    pages = {}

    # ------------------------------------------------------------------ site
    s.group("site/basics", "site", "Business basics")
    s.text("site.title", "Riverside Family Clinic", max=60, label="Site title",
           hint="Shown in the header, browser tab and search results.")
    s.text("site.tagline", "Caring for every generation", max=100, label="Tagline",
           hint="A short line about the practice.")
    biz_name = s.text("biz.name", "Riverside Family Clinic", max=80, label="Business name")
    biz_phone = s.text("biz.phone", "(555) 014-2290", max=32, label="Phone")
    biz_email = s.text("biz.email", "hello@riversideclinic.example", max=80, label="Email")
    biz_address = s.text("biz.address", "48 Mill Street, Riverside", max=120, label="Address")
    biz_hours = s.rich("biz.hours", "Mon to Fri: 8am to 6pm<br>Saturday: 9am to 1pm<br>Sunday: closed",
                       max=160, label="Opening hours", hint="One line per day range.")

    s.group("site/header", "site", "Header")
    header = group(
        group(
            site_title(),
            group(
                navigation(),
                buttons(button(s.text("header.cta", "Book a visit", max=24, label="Header button"), "{{page:contact}}")),
                layout={"type": "flex", "flexWrap": "nowrap"}, gap="var:preset|spacing|40",
            ),
            align="wide", layout={"type": "flex", "flexWrap": "wrap", "justifyContent": "space-between"},
        ),
        align="full", bg="base",
        padding={"top": "var:preset|spacing|30", "bottom": "var:preset|spacing|30"},
        layout={"type": "constrained"},
    )

    s.group("site/footer", "site", "Footer")
    footer = group(
        columns(
            column(
                site_title(),
                paragraph(s.text("footer.about", "Family medicine for Riverside and the surrounding towns. Accepting new patients of all ages.",
                                 label="About text"), text="muted"),
                width="40%",
            ),
            column(
                heading(s.text("footer.visit_heading", "Visit us", max=40, label="Contact heading"), 4),
                paragraph(biz_address + "<br>" + biz_phone + "<br>" + biz_email, text="muted"),
            ),
            column(
                heading(s.text("footer.hours_heading", "Opening hours", max=40, label="Hours heading"), 4),
                paragraph(biz_hours, text="muted"),
            ),
            gap="var:preset|spacing|50",
        ),
        group(
            separator(),
            paragraph("© " + biz_name + ". " + s.text("footer.legal", "All rights reserved.", max=80, label="Legal line"),
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
        columns(
            column(
                eyebrow(s.text("home.hero.eyebrow", "Family medicine in Riverside", max=40)),
                heading(s.text("home.hero.heading", "Unhurried care for your whole family", max=70,
                               hint="The main promise, in under ten words."), 1, font_size="huge"),
                paragraph(s.text("home.hero.subheading",
                                 "From newborn checkups to long-term conditions, our doctors take the time to listen. Same-week appointments and most insurance plans accepted.",
                                 max=220), text="muted", font_size="large"),
                buttons(
                    button(s.text("home.hero.cta_primary", "Book an appointment", max=28), "{{page:contact}}"),
                    button(s.text("home.hero.cta_secondary", "See our services", max=28), "{{page:services}}", outline=True),
                    style={"spacing": {"margin": {"top": "var:preset|spacing|50"}}},
                ),
                width="55%", vcenter=True,
            ),
            column(
                image(s.image("image:home.hero", "assets/hero.webp", label="Hero image"),
                      s.text("home.hero.image_alt", "Illustration of a bright clinic room with an arched window and plants", max=140,
                             label="Hero image description", hint="Describe the image for people who cannot see it.")),
                width="45%", vcenter=True,
            ),
            vcenter=True, gap="var:preset|spacing|70",
        ),
        bg="surface", pad="70",
    )

    s.group("home/highlights", "home", "Highlights")
    highlights_items = [
        ("Same-week appointments", "Call before noon and we can often see you the same day."),
        ("Most insurance accepted", "We work with all major plans and post clear self-pay prices."),
        ("Open six days", "Early mornings and Saturday clinics for busy families."),
    ]
    hl_cols = []
    for i, (title, text) in enumerate(highlights_items, 1):
        hl_cols.append(column(group(
            heading(s.text("home.highlights.item%d_title" % i, title), 3),
            paragraph(s.text("home.highlights.item%d_text" % i, text), text="muted"),
            border_top={"color": "var:preset|color|primary", "width": "3px"},
            padding={"top": "var:preset|spacing|40"},
        )))
    highlights = section(columns(*hl_cols, gap="var:preset|spacing|50"), pad="70")

    s.group("home/services", "home", "Services")
    services = section(
        group(
            eyebrow(s.text("home.services.eyebrow", "What we do", max=40), align="center"),
            heading(s.text("home.services.heading", "Care for every stage of life"), align="center"),
            paragraph(s.text("home.services.intro", "One practice for the whole family, so your doctor already knows your history."),
                      align="center", text="muted", font_size="large"),
            layout={"type": "constrained", "contentSize": "680px"},
        ),
        three_cards(s, "home", "services", [
            ("Family medicine", "Checkups, illness visits and ongoing care for adults and children."),
            ("Children's health", "Well-baby visits, vaccinations, and school and sports physicals."),
            ("Chronic conditions", "Steady support for diabetes, asthma, blood pressure and more."),
        ]),
        buttons(button(s.text("home.services.link", "View all services", max=28), "{{page:services}}", outline=True),
                justify="center", style={"spacing": {"margin": {"top": "var:preset|spacing|50"}}}),
        bg="surface",
    )

    s.group("home/about", "home", "About")
    about = section(
        columns(
            column(
                image(s.image("image:home.about", "assets/team.webp", label="About image"),
                      s.text("home.about.image_alt", "Illustration of a doctor's desk with a stethoscope and notebook", max=140,
                             label="About image description")),
                width="45%", vcenter=True,
            ),
            column(
                eyebrow(s.text("home.about.eyebrow", "About us", max=40)),
                heading(s.text("home.about.heading", "A neighborhood practice since 2009")),
                paragraph(s.rich("home.about.body",
                                 "We are a small team of doctors, nurses and front desk staff who know our patients by name. <strong>Longer appointments</strong> mean you leave with answers, not more questions.")),
                buttons(button(s.text("home.about.button", "Meet our team", max=28), "{{page:about}}", outline=True)),
                width="55%", vcenter=True,
            ),
            vcenter=True, gap="var:preset|spacing|70",
        ),
    )

    s.group("home/testimonial", "home", "Testimonial")
    testimonial = section(
        group(
            quote(s.text("home.testimonial.quote",
                         "Dr. Ahmed spent real time with my father and explained every step. For the first time in years we feel looked after.",
                         max=260),
                  s.text("home.testimonial.name", "Maria L., patient since 2016", max=60, label="Name")),
            layout={"type": "constrained", "contentSize": "720px"},
        ),
        bg="accent", pad="70",
    )

    pages["home"] = "\n\n".join([
        hero, highlights, services, about, testimonial,
        cta_band(s, "home", "Ready to book your visit?",
                 "New patients are welcome. Call us or book online in a few minutes.", "Book an appointment"),
    ])

    # ----------------------------------------------------------------- about
    intro = page_intro(s, "about", "About us", "Good medicine starts with listening",
                       "Riverside Family Clinic was started by two local doctors who wanted a practice where nobody feels rushed.")

    s.group("about/story", "about", "Our story")
    story = section(
        columns(
            column(
                heading(s.text("about.story.heading", "Our story")),
                paragraph(s.rich("about.story.body1",
                                 "In 2009 Dr. Sara Ahmed and Dr. James Okafor opened a two-room clinic above the Mill Street pharmacy. They wanted appointments long enough to get to know each patient.")),
                paragraph(s.rich("about.story.body2",
                                 "Today we care for more than 4,000 families, but the idea is the same. <strong>You see the same doctor, visit after visit</strong>, and we keep your care in one place.")),
                width="55%", vcenter=True,
            ),
            column(
                image(s.image("image:about.story", "assets/building.webp", label="Story image"),
                      s.text("about.story.image_alt", "Illustration of a small brick clinic building on a tree-lined street", max=140,
                             label="Story image description")),
                width="45%", vcenter=True,
            ),
            vcenter=True, gap="var:preset|spacing|70",
        ),
    )

    s.group("about/values", "about", "Values")
    values = section(
        heading(s.text("about.values.heading", "What we believe"), align="center"),
        three_cards(s, "about", "values", [
            ("Time to talk", "Standard visits are 30 minutes, not 10, so there is room for every question."),
            ("Clear answers", "We explain your options in plain language and write the plan down for you."),
            ("Care that lasts", "Your doctor knows your history, which means fewer repeat tests and better follow-up."),
        ]),
        bg="surface",
    )

    s.group("about/team", "about", "Team")
    team_items = [
        ("Dr. Sara Ahmed", "Family physician, co-founder",
         "Sara has cared for Riverside families for over 15 years and leads our chronic care program."),
        ("Dr. James Okafor", "Pediatrician, co-founder",
         "James looks after our youngest patients, from newborn checks to teenage sports physicals."),
        ("Linda Park, RN", "Nurse manager",
         "Linda runs our vaccination clinics and makes sure every follow-up happens on time."),
    ]
    team_cols = []
    for i, (name, role, bio) in enumerate(team_items, 1):
        team_cols.append(column(card(
            heading(s.text("about.team.member%d_name" % i, name, max=60, label="Member %d name" % i), 3),
            eyebrow(s.text("about.team.member%d_role" % i, role, max=60, label="Member %d role" % i)),
            paragraph(s.text("about.team.member%d_bio" % i, bio, label="Member %d bio" % i), text="muted"),
            bg="surface",
        )))
    team = section(
        group(
            heading(s.text("about.team.heading", "Meet the team"), align="center"),
            paragraph(s.text("about.team.intro", "Doctors and nurses who live in the community they care for."),
                      align="center", text="muted", font_size="large"),
            layout={"type": "constrained", "contentSize": "680px"},
        ),
        columns(*team_cols, gap="var:preset|spacing|40"),
    )

    pages["about"] = "\n\n".join([
        intro, story, values, team,
        cta_band(s, "about", "Join our practice",
                 "We are accepting new patients of all ages. Getting started takes one phone call.", "Become a patient"),
    ])

    # -------------------------------------------------------------- services
    intro = page_intro(s, "services", "Services", "Everything your family needs, under one roof",
                       "Most visits happen right here, and when you need a specialist we arrange the referral and stay involved.")

    s.group("services/list", "services", "Service list")
    service_items = [
        ("Family medicine", "Annual checkups, illness visits and preventive screening for adults of every age."),
        ("Children's health", "Well-baby visits, growth checks, vaccinations, and school and sports physicals."),
        ("Women's health", "Cervical screening, contraception advice, pregnancy care and menopause support."),
        ("Chronic care", "Regular reviews for diabetes, asthma, heart health and high blood pressure."),
        ("Vaccinations", "Routine, travel and seasonal flu vaccines, often available the same week."),
        ("Minor procedures", "Stitches, wound care, skin checks and the removal of small skin lesions."),
    ]
    rows = []
    for row_start in (0, 3):
        cols = []
        for i in range(row_start, row_start + 3):
            title, text = service_items[i]
            cols.append(column(card(
                heading(s.text("services.list.item%d_title" % (i + 1), title, label="Service %d name" % (i + 1)), 3),
                paragraph(s.text("services.list.item%d_text" % (i + 1), text, label="Service %d description" % (i + 1)), text="muted"),
                bg="surface",
            )))
        rows.append(columns(*cols, gap="var:preset|spacing|40"))
    service_list = section(*rows)

    s.group("services/insurance", "services", "Insurance and payment")
    insurance = section(
        group(
            heading(s.text("services.insurance.heading", "Insurance and payment")),
            paragraph(s.rich("services.insurance.body",
                             "We accept most major insurance plans, including Medicare. If you pay for yourself, our prices are posted at the front desk and on request. <strong>Ask us before your visit</strong> and we will check your cover for you."),
                      font_size="large"),
            layout={"type": "constrained", "contentSize": "760px"},
        ),
        bg="accent", pad="70",
    )

    pages["services"] = "\n\n".join([
        intro, service_list, insurance,
        cta_band(s, "services", "Not sure which service you need?",
                 "Call us and a nurse will help you book the right appointment.", "Contact us"),
    ])

    # ---------------------------------------------------------- new patients
    intro = page_intro(s, "new-patients", "New patients", "Joining the practice is simple",
                       "Most new patients are registered and booked within a week. Here is what to expect.")

    s.group("new-patients/steps", "new-patients", "Steps")
    steps_items = [
        ("Call or visit us", "Tell us who is joining and we will check that we cover your address."),
        ("Fill in one form", "We send a short health questionnaire by email or hand it to you at the desk."),
        ("Meet your doctor", "Your first appointment is longer, so we can get to know you properly."),
    ]
    step_cols = []
    for i, (title, text) in enumerate(steps_items, 1):
        step_cols.append(column(group(
            paragraph(str(i), text="primary", font_size="x-large", style={"typography": {"fontWeight": "700"}}),
            heading(s.text("new-patients.steps.item%d_title" % i, title, label="Step %d title" % i), 3),
            paragraph(s.text("new-patients.steps.item%d_text" % i, text, label="Step %d text" % i), text="muted"),
            border_top={"color": "var:preset|color|primary", "width": "3px"},
            padding={"top": "var:preset|spacing|40"},
        )))
    steps = section(
        group(heading(s.text("new-patients.steps.heading", "Three steps to register")), align="wide"),
        columns(*step_cols, gap="var:preset|spacing|50"),
    )

    s.group("new-patients/faq", "new-patients", "Questions")
    faq_items = [
        ("Do I need to change doctors to register?",
         "Yes. When you register with us, your records move from your previous practice. We request them for you."),
        ("What should I bring to my first visit?",
         "Your insurance card, a photo ID and a list of any medicines you take. <strong>Arrive ten minutes early</strong> for your first appointment."),
        ("Can I book online?",
         "Yes. Once you are registered you can book, cancel and request repeat prescriptions online at any time."),
        ("What if I need care after hours?",
         "Call our main number and follow the prompts for the on-call doctor. In an emergency, always call 911."),
    ]
    faq_blocks = []
    for i, (q, a) in enumerate(faq_items, 1):
        faq_blocks.append(details(
            s.text("new-patients.faq.q%d" % i, q, max=120, label="Question %d" % i),
            s.rich("new-patients.faq.a%d" % i, a, label="Answer %d" % i),
        ))
        faq_blocks.append(separator())
    faq = section(
        group(
            heading(s.text("new-patients.faq.heading", "Common questions")),
            *faq_blocks[:-1],
            layout={"type": "constrained", "contentSize": "760px"},
        ),
        bg="surface",
    )

    pages["new-patients"] = "\n\n".join([
        intro, steps, faq,
        cta_band(s, "new-patients", "Ready to register?",
                 "Call us during opening hours and we will take it from there.", "Get in touch"),
    ])

    # --------------------------------------------------------------- contact
    intro = page_intro(s, "contact", "Contact", "We are here to help",
                       "Call us to book, ask a question or request a repeat prescription. We reply to email within one working day.")

    s.group("contact/details", "contact", "Details")
    detail_rows = []
    for label, token in (("Address", biz_address), ("Phone", biz_phone), ("Email", biz_email)):
        detail_rows.append(group(
            paragraph(label, text="muted", font_size="small", style={"typography": {"fontWeight": "600", "textTransform": "uppercase", "letterSpacing": "0.08em"}}),
            paragraph(token, font_size="large"),
            gap="0.25rem",
        ))
    contact = section(
        columns(
            column(
                card(
                    heading(s.text("contact.details.heading", "Visit or call", label="Contact box heading"), font_size="x-large"),
                    *detail_rows,
                    group(
                        paragraph("Opening hours", text="muted", font_size="small", style={"typography": {"fontWeight": "600", "textTransform": "uppercase", "letterSpacing": "0.08em"}}),
                        paragraph(biz_hours),
                        gap="0.25rem",
                    ),
                    bg="surface",
                ),
            ),
            column(
                heading(s.text("contact.directions.heading", "Getting here", label="Directions heading")),
                paragraph(s.rich("contact.directions.body", label="Directions", default=
                                 "We are on Mill Street, opposite the library. <strong>Free parking</strong> is available behind the building, and the number 4 bus stops right outside.")),
                heading(s.text("contact.urgent.heading", "Need urgent help?", label="Urgent care heading"), 3),
                paragraph(s.rich("contact.urgent.body", label="Urgent care text", default=
                                 "If you are unwell and it cannot wait, call us before 10am for a same-day appointment. <strong>In an emergency, call 911.</strong>")),
            ),
            gap="var:preset|spacing|70",
        ),
    )

    pages["contact"] = "\n\n".join([intro, contact])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": pages["home"]},
            {"slug": "about", "title": "About", "markup": pages["about"]},
            {"slug": "services", "title": "Services", "markup": pages["services"]},
            {"slug": "new-patients", "title": "New Patients", "markup": pages["new-patients"]},
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
