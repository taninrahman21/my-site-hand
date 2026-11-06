"""Business / agency starter site: a small digital marketing studio."""

from blocks import Site
from common import base_styles, check_palette, font_pair, palette
from kit import (basics, contact, features, footer, header, hero_split, image_text, pricing, stats, steps, team,
                 template, testimonial, faq)
from sections import cta_band, page_intro

MANIFEST = {
    "slug": "agency",
    "name": "Business & Agency",
    "category": "business",
    "description": "A confident site for an agency, consultancy or small company. Services with clear packages, case studies, your team and an easy way to start a project.",
    "version": "1.0.0",
}

PALETTES = [
    check_palette({"id": "indigo", "name": "Indigo", "colors": {
        "base": "#ffffff", "surface": "#f5f4fb", "accent": "#e7e4f8", "contrast": "#15122b",
        "muted": "#4f4a68", "primary": "#4b32c3", "on-primary": "#ffffff"}}),
    palette("ocean", "Ocean", "#0b6bcb"),
    palette("emerald", "Emerald", "#047857"),
    palette("graphite", "Graphite", "#334155", base="#ffffff"),
    palette("ember", "Ember", "#c2410c", base="#fffcf9"),
    check_palette({"id": "midnight", "name": "Midnight", "colors": {
        "base": "#0d0c1d", "surface": "#16152c", "accent": "#211f3d", "contrast": "#f1f0fb",
        "muted": "#aeaacb", "primary": "#a597ff", "on-primary": "#0d0c1d"}}),
]

FONT_PAIRS = [
    font_pair("geometric", "Geometric", "geometric", "system", "700"),
    font_pair("modern", "Modern", "system", "system", "700"),
    font_pair("humanist", "Humanist", "humanist", "humanist", "700"),
    font_pair("editorial", "Editorial", "transitional", "system", "600"),
    font_pair("soft", "Soft", "rounded", "system", "700"),
]

STYLES = base_styles(button_radius="8px")

NAVIGATION = [
    {"label": "Services", "page": "services"},
    {"label": "Work", "page": "work"},
    {"label": "About", "page": "about"},
    {"label": "Contact", "page": "contact"},
]


def build(s: Site):
    biz = basics(s, "Northwind Studio", "Websites and marketing that bring in customers", "Northwind Studio",
                 "(555) 019-3381", "hello@northwind.example", "18 Foundry Lane, Suite 4, Portland")
    hdr = header(s, "Start a project")
    ftr = footer(s, biz, "A small team that builds websites, brands and campaigns for growing businesses.",
                 "Built with WordPress.")

    home = "\n\n".join([
        hero_split(s, "home", "Digital studio for growing businesses",
                   "Websites and marketing that bring in real customers",
                   "We design, build and run the online side of your business, so you can get back to running the rest of it.",
                   ("Start a project", "contact"), ("See our work", "work"),
                   ("assets/hero.webp", "Illustration of a laptop showing a website with rising sales charts")),
        stats(s, "home", "stats", [("120+", "websites launched since 2016"), ("3.4x", "average rise in enquiries"),
                                   ("9 years", "average client relationship"), ("4.9/5", "from 80 client reviews")]),
        features(s, "home", "services", "Services", "What we do", "Everything you need to grow online",
                 "Pick one service or let us run the whole thing. Either way you get one team and one point of contact.",
                 [("Websites", "Fast, accessible WordPress sites your team can update without calling us."),
                  ("Brand and design", "Logos, colours and messaging that make you easy to recognise and remember."),
                  ("Search and ads", "Local SEO and paid campaigns tracked to leads, not just clicks.")],
                 more=("All services", "services")),
        steps(s, "home", "process", "How we work", "How a project works",
              [("Discovery call", "Thirty minutes to understand your goals, your customers and your budget."),
               ("Plan and design", "A clear proposal, then designs you can comment on before anything is built."),
               ("Launch and grow", "We launch, measure what works and keep improving it with you every month.")],
              bg="surface"),
        testimonial(s, "home", "Northwind rebuilt our site in six weeks and our online bookings tripled by the end of the quarter. They explain everything in plain English.",
                    "Priya S., owner of Fernhill Dental"),
        cta_band(s, "home", "Ready to grow?", "Tell us about your business. We reply within one working day.", "Start a project"),
    ])

    services = "\n\n".join([
        page_intro(s, "services", "Services", "One team for your website, brand and marketing",
                   "Fixed-price packages for most projects, and honest advice about what you do not need."),
        features(s, "services", "list", "Service list", "Services", "What we can help with", None,
                 [("Website design", "Custom WordPress sites designed around what your customers need to do."),
                  ("Website care", "Updates, backups, security checks and small changes every month."),
                  ("Brand identity", "Logo, colours, fonts and a short guide your whole team can follow."),
                  ("Local SEO", "Show up in maps and local searches with accurate, consistent listings."),
                  ("Paid ads", "Google and social campaigns with a monthly report you can actually read."),
                  ("Email marketing", "Newsletters and automated emails that bring customers back.")],
                 bg="base", card_bg="surface"),
        pricing(s, "services", "packages", "Packages", "Packages", "Simple, fixed prices",
                "Every package includes hosting setup, training and 30 days of support after launch.",
                [("Starter site", "$2,900", "one-off", "Up to 5 pages<br>Mobile-friendly design<br>Contact form and maps",
                  "Choose Starter", False),
                 ("Growth site", "$6,500", "one-off", "Up to 12 pages<br>Brand refresh<br>Local SEO setup<br>Analytics dashboard",
                  "Choose Growth", True),
                 ("Care plan", "$190", "per month", "Updates and backups<br>Two hours of changes<br>Monthly report",
                  "Choose Care", False)]),
        faq(s, "services", "faq", "Questions clients ask", [
            ("How long does a website take?", "Most sites launch in four to eight weeks, depending on how quickly we receive your content."),
            ("Do I own my website?", "Yes. You own the site, the domain and all content. <strong>No lock-in contracts.</strong>"),
            ("Can you work with my existing site?", "Often, yes. We start with a free review and tell you honestly whether to keep it or rebuild."),
        ]),
        cta_band(s, "services", "Not sure where to start?", "Book a free 30-minute call and we will suggest the simplest next step.", "Book a call"),
    ])

    work = "\n\n".join([
        page_intro(s, "work", "Our work", "Results our clients can measure",
                   "A few recent projects for local businesses and growing brands."),
        image_text(s, "work", "case1", "Case study 1", "Website and local SEO", "Fernhill Dental",
                   "A new website with online booking and a local search plan. <strong>Online bookings tripled</strong> within three months.",
                   ("assets/work.webp", "Illustration of a phone and a poster showing a new brand and website"), bg="base"),
        image_text(s, "work", "case2", "Case study 2", "Brand and paid ads", "Copperline Coffee",
                   "A fresh brand and a seasonal ad campaign for a three-shop coffee roaster. <strong>Wholesale enquiries up 60%.</strong>",
                   ("assets/team.webp", "Illustration of a team working together around a table with laptops and notes"),
                   image_left=False, bg="surface"),
        stats(s, "work", "results", [("3x", "more online bookings for Fernhill Dental"), ("60%", "more wholesale enquiries"),
                                    ("2.1s", "average page load on our sites"), ("100%", "of clients own their site")]),
        cta_band(s, "work", "Want results like these?", "Let us look at your site and your numbers, free of charge.", "Get a free review"),
    ])

    about = "\n\n".join([
        page_intro(s, "about", "About us", "A small studio that answers the phone",
                   "We are eight people who care about craft and about the businesses we work with."),
        image_text(s, "about", "story", "Story", "Our story", "Started by two friends in 2016",
                   "Northwind began when two friends, a designer and a developer, kept seeing small businesses pay too much for websites that did too little.",
                   ("assets/team.webp", "Illustration of a team working together around a table with laptops and notes"),
                   body2="Today we work with more than 60 clients, and most of them have been with us for years. <strong>We would rather keep a client than win a new one.</strong>"),
        team(s, "about", "team", "The people you will work with", "No account managers in the middle. You talk to the people doing the work.",
             [("Hana Moreau", "Co-founder, design lead", "Hana leads brand and web design and still sketches every homepage by hand first."),
              ("Leo Brandt", "Co-founder, development", "Leo builds our WordPress sites and makes sure they load fast on every phone."),
              ("Ama Owusu", "Marketing lead", "Ama runs search and ad campaigns and turns the numbers into plain-English reports.")]),
        cta_band(s, "about", "Let us get to know you", "Tell us about your business and where you want it to go.", "Say hello"),
    ])

    contact_page = "\n\n".join([
        page_intro(s, "contact", "Contact", "Let us talk about your project",
                   "Tell us a little about your business and what you need. We reply within one working day."),
        contact(s, "contact", biz, "Get in touch", "What happens next",
                "We read every message ourselves. If we are a good fit, we book a <strong>free 30-minute call</strong> to understand your goals.",
                "Prefer to visit?", "Our studio is above the bakery on Foundry Lane. Drop by any weekday, the coffee is on us."),
    ])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": home},
            {"slug": "services", "title": "Services", "markup": services},
            {"slug": "work", "title": "Work", "markup": work},
            {"slug": "about", "title": "About", "markup": about},
            {"slug": "contact", "title": "Contact", "markup": contact_page},
        ],
        "parts": {"header": hdr, "footer": ftr},
        "templates": template(),
    }
