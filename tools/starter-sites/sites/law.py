"""Law firm starter site: a small family and estate law practice."""

from blocks import Site
from common import base_styles, check_palette, font_pair, palette
from kit import (basics, contact, faq, features, footer, header, hero_split, image_text, stats, steps, team,
                 template, testimonial)
from sections import cta_band, page_intro

MANIFEST = {
    "slug": "law",
    "name": "Law Firm",
    "category": "legal",
    "description": "A serious, reassuring site for a law firm, accountant or advisory practice. Practice areas, your people, how you work and a free consultation offer.",
    "version": "1.0.0",
}

PALETTES = [
    check_palette({"id": "oxford", "name": "Oxford green", "colors": {
        "base": "#fdfcf9", "surface": "#f3f0e8", "accent": "#e8e2d3", "contrast": "#1b1a17",
        "muted": "#55524a", "primary": "#1f3a2e", "on-primary": "#ffffff"}}),
    palette("navy", "Navy", "#1e3a5f"),
    palette("burgundy", "Burgundy", "#7a1f2b", base="#fffcfb"),
    palette("slate", "Slate", "#334155"),
    palette("bronze", "Bronze", "#7c5a2a", base="#fffdf8"),
    check_palette({"id": "chamber", "name": "Chamber", "colors": {
        "base": "#121411", "surface": "#1b1e1a", "accent": "#262a24", "contrast": "#f2f1ea",
        "muted": "#b2b0a4", "primary": "#cdb27a", "on-primary": "#121411"}}),
]

FONT_PAIRS = [
    font_pair("classic", "Classic", "transitional", "system", "600"),
    font_pair("traditional", "Traditional", "old-style", "system", "600"),
    font_pair("formal", "Formal", "didone", "system", "500"),
    font_pair("modern", "Modern", "system", "system", "700"),
    font_pair("humanist", "Humanist", "humanist", "humanist", "600"),
]

STYLES = base_styles(button_radius="2px")

NAVIGATION = [
    {"label": "Practice areas", "page": "practice-areas"},
    {"label": "Our lawyers", "page": "lawyers"},
    {"label": "Fees", "page": "fees"},
    {"label": "Contact", "page": "contact"},
]


def build(s: Site):
    biz = basics(s, "Carter & Hale", "Family and estate law, explained plainly", "Carter & Hale Solicitors",
                 "(555) 013-5507", "enquiries@carterhale.example", "Chancery House, 5 Market Square, Ashford",
                 hours="Mon to Fri: 9am to 5:30pm<br>Evening appointments on request")
    hdr = header(s, "Free consultation", weight="600")
    ftr = footer(s, biz, "Family, property and estate law for people, not corporations. Clear advice, fixed fees where possible.",
                 "The information on this website is general and is not legal advice.", contact_heading="Office")

    home = "\n\n".join([
        hero_split(s, "home", "Family, property and estate law",
                   "Clear legal advice when it matters most",
                   "Divorce, wills, house moves and estates. We explain your options in plain English and tell you the cost before you commit.",
                   ("Book a free consultation", "contact"), ("Our practice areas", "practice-areas"),
                   ("assets/office.webp", "Illustration of a calm office with a desk, law books and a reading lamp"),
                   radius="2px"),
        stats(s, "home", "stats", [("35 years", "advising local families"), ("2,000+", "cases handled"),
                                   ("Fixed fees", "for most services"), ("4.9/5", "client rating")], bg="surface"),
        features(s, "home", "areas", "Practice areas", "How we can help", "Practice areas", None,
                 [("Family law", "Separation, divorce and arrangements for children, handled with care."),
                  ("Wills and estates", "Wills, lasting powers of attorney and probate, done properly."),
                  ("Property", "Buying, selling and remortgaging, with updates at every step.")],
                 bg="base", card_bg="surface", radius="2px", more=("All practice areas", "practice-areas")),
        steps(s, "home", "how", "How we work", "How we work with you",
              [("Free first conversation", "Thirty minutes to understand your situation, with no obligation."),
               ("A clear plan and price", "We set out your options, the likely steps and the cost in writing."),
               ("One lawyer throughout", "The same person handles your matter from start to finish.")],
              bg="surface"),
        testimonial(s, "home", "During the hardest year of my life, Eleanor made everything feel manageable. I always knew what was happening and what it would cost.",
                    "A family law client, 2025"),
        cta_band(s, "home", "Talk to us in confidence", "Your first conversation is free and completely confidential.", "Book a free consultation"),
    ])

    areas = "\n\n".join([
        page_intro(s, "practice-areas", "Practice areas", "Legal help for life's big moments",
                   "We focus on a few areas of law and know them deeply, rather than doing a little of everything."),
        features(s, "practice-areas", "list", "Areas", "Practice areas", "What we do", None,
                 [("Divorce and separation", "Amicable agreements where possible, firm representation where needed."),
                  ("Children arrangements", "Where children live and time with each parent, always putting them first."),
                  ("Wills", "Simple and complex wills, reviewed every five years at no charge."),
                  ("Lasting power of attorney", "Choose who decides for you if you cannot, before you need to."),
                  ("Probate and estates", "Practical help after a death, from forms to final accounts."),
                  ("Residential property", "Buying, selling and remortgaging homes, with weekly updates.")],
                 bg="base", card_bg="surface", radius="2px"),
        image_text(s, "practice-areas", "approach", "Approach", "Our approach", "Calm, practical, on your side",
                   "Legal problems are stressful. We return calls the same day, avoid jargon and <strong>tell you the cost before any work starts</strong>.",
                   ("assets/columns.webp", "Illustration of a classic building with stone columns and steps"),
                   bg="surface", radius="2px"),
        cta_band(s, "practice-areas", "Not sure where your issue fits?", "Call us. If we cannot help, we will point you to someone who can.", "Get in touch"),
    ])

    lawyers = "\n\n".join([
        page_intro(s, "lawyers", "Our lawyers", "Experienced, approachable lawyers",
                   "Every lawyer here has at least ten years of experience in the area they practise."),
        team(s, "lawyers", "team", "Meet the partners", "You will always know who is handling your matter and how to reach them.",
             [("Eleanor Carter", "Partner, family law", "Accredited family law specialist. Known for keeping difficult separations calm."),
              ("James Hale", "Partner, wills and estates", "Thirty years of probate experience. Patient, thorough and kind."),
              ("Aisha Bello", "Senior associate, property", "Handles 200 house moves a year and still answers her own phone.")],
             radius="2px"),
        stats(s, "lawyers", "credentials", [("3", "accredited specialists"), ("10+ yrs", "experience each"),
                                            ("Same day", "call-backs"), ("1", "lawyer per client, start to finish")], bg="surface"),
        cta_band(s, "lawyers", "Speak to a lawyer", "Book a free 30-minute consultation.", "Book now"),
    ])

    fees = "\n\n".join([
        page_intro(s, "fees", "Fees", "Clear fees, agreed upfront",
                   "Most of our work is fixed fee. Where it cannot be, we give you a written estimate and update it if anything changes."),
        features(s, "fees", "examples", "Example fees", "Examples", "Typical fixed fees", None,
                 [("Single will", "From $450 plus tax, including a review meeting."),
                  ("Mirror wills for couples", "From $750 plus tax, for two matching wills."),
                  ("House purchase", "From $1,250 plus tax and searches, for a standard sale.")],
                 bg="base", card_bg="surface", radius="2px"),
        faq(s, "fees", "faq", "Questions about fees", [
            ("Is the first meeting really free?", "Yes. The first 30-minute conversation is free, with no obligation to instruct us."),
            ("Do you offer payment plans?", "For family matters, yes. <strong>Ask us about monthly payments.</strong>"),
            ("Will there be extra costs?", "Sometimes there are court fees or searches. We list these separately before you agree."),
        ]),
        cta_band(s, "fees", "Ask for a quote", "Tell us what you need and we will send a fixed quote in writing.", "Request a quote"),
    ])

    contact_page = "\n\n".join([
        page_intro(s, "contact", "Contact", "Speak to us in confidence",
                   "Call, email or visit our office on Market Square. Everything you tell us is confidential."),
        contact(s, "contact", biz, "Contact the office", "Book a free consultation",
                "Call the office or email us with a short summary. A lawyer will call you back <strong>the same working day</strong>.",
                "Visiting us", "Our office is wheelchair accessible. Parking is available on Market Square and Church Lane.",
                radius="2px"),
    ])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": home},
            {"slug": "practice-areas", "title": "Practice Areas", "markup": areas},
            {"slug": "lawyers", "title": "Our Lawyers", "markup": lawyers},
            {"slug": "fees", "title": "Fees", "markup": fees},
            {"slug": "contact", "title": "Contact", "markup": contact_page},
        ],
        "parts": {"header": hdr, "footer": ftr},
        "templates": template(),
    }
