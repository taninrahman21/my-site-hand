"""Home services starter site: plumbing, electrical and heating."""

from blocks import Site
from common import base_styles, check_palette, font_pair, palette
from kit import (basics, contact, faq, features, footer, header, hero_split, image_text, price_list, stats, steps,
                 team, template, testimonial)
from sections import cta_band, page_intro

MANIFEST = {
    "slug": "home-services",
    "name": "Home Services",
    "category": "trades",
    "description": "A trustworthy site for a plumber, electrician, cleaner or handyman. Services, upfront prices, service areas and a big call button.",
    "version": "1.0.0",
}

PALETTES = [
    check_palette({"id": "safety", "name": "Navy and orange", "colors": {
        "base": "#ffffff", "surface": "#f3f5f8", "accent": "#e2e8f0", "contrast": "#0f1b2d",
        "muted": "#475467", "primary": "#b8420d", "on-primary": "#ffffff"}}),
    palette("trust", "Trust blue", "#1d4ed8"),
    palette("fresh", "Fresh green", "#15803d"),
    palette("red", "Fire red", "#b91c1c"),
    palette("slate", "Slate", "#334155"),
    check_palette({"id": "night", "name": "Night shift", "colors": {
        "base": "#0f1722", "surface": "#172131", "accent": "#21304a", "contrast": "#eef3fa",
        "muted": "#a9b6c8", "primary": "#ff9a4d", "on-primary": "#0f1722"}}),
]

FONT_PAIRS = [
    font_pair("sturdy", "Sturdy", "slab", "system", "700"),
    font_pair("modern", "Modern", "system", "system", "800"),
    font_pair("geometric", "Geometric", "geometric", "system", "700"),
    font_pair("humanist", "Humanist", "humanist", "humanist", "700"),
    font_pair("classic", "Classic", "transitional", "system", "700"),
]

STYLES = base_styles(button_radius="6px")

NAVIGATION = [
    {"label": "Home", "page": "home"},
    {"label": "Services", "page": "services"},
    {"label": "Prices", "page": "prices"},
    {"label": "About", "page": "about"},
    {"label": "Contact", "page": "contact"},
]


def build(s: Site):
    biz = basics(s, "Hartley Home Services", "Plumbing, electrical and heating you can trust", "Hartley Home Services",
                 "(555) 012-4467", "help@hartleyhome.example", "Serving Oakdale and 20 miles around",
                 hours="Mon to Fri: 7am to 7pm<br>Saturday: 8am to 4pm<br>Emergencies: 24 hours, 7 days",
                 address_label="Service area")
    hdr = header(s, "Book a visit")
    ftr = footer(s, biz, "Licensed and insured plumbers and electricians. Family-run since 2004.",
                 "Licensed and fully insured.")

    home = "\n\n".join([
        hero_split(s, "home", "Licensed plumbers and electricians",
                   "Fixed today, done right, priced upfront",
                   "Leaks, power cuts, boilers and everything in between. A real person answers the phone, and you get the price before we start.",
                   ("Book a visit", "contact"), ("See our prices", "prices"),
                   ("assets/van.webp", "Illustration of a service van parked outside a house on a sunny street"),
                   radius="8px"),
        stats(s, "home", "trust", [("24/7", "emergency call-outs, every day"), ("60 min", "average arrival time in town"),
                                   ("20 yrs", "family-run in Oakdale"), ("4.9/5", "from 600+ local reviews")], bg="surface"),
        features(s, "home", "services", "Services", "What we fix", "One call for the whole house", None,
                 [("Plumbing", "Leaks, blocked drains, toilets, taps and water heaters."),
                  ("Electrical", "Fault finding, rewiring, lighting, sockets and safety checks."),
                  ("Heating", "Boiler repairs, servicing and new installations."),
                  ("Bathrooms", "Full refits planned, priced and finished by one team."),
                  ("Smart home", "Thermostats, doorbells and EV chargers fitted safely."),
                  ("Emergencies", "Burst pipes and power cuts, any hour of the day or night.")],
                 bg="base", card_bg="surface", radius="8px", more=("All services", "services")),
        steps(s, "home", "how", "How it works", "Booking takes two minutes",
              [("Call or book online", "Tell us what is wrong. We give you a time slot that suits you."),
               ("Get a fixed price", "Our engineer checks the job and tells you the price before starting."),
               ("Job done, guaranteed", "Every repair comes with a 12-month guarantee on parts and labour.")],
              bg="surface"),
        testimonial(s, "home", "Our boiler died on a Sunday night. Hartley had an engineer here in under an hour and the heating back on before bedtime. Fair price, no fuss.",
                    "Gareth W., Oakdale"),
        cta_band(s, "home", "Something broken right now?", "Call us any time. Emergencies are answered 24 hours a day.", "Book a visit"),
    ])

    services = "\n\n".join([
        page_intro(s, "services", "Services", "Repairs, installs and servicing",
                   "From a dripping tap to a full rewire, our own engineers do the work. We never send subcontractors."),
        image_text(s, "services", "plumbing", "Plumbing", "Plumbing", "Leaks fixed fast",
                   "Leaks, blocked drains, toilets, taps, showers and water heaters. <strong>Most repairs are finished on the first visit</strong>, because our vans carry the common parts.",
                   ("assets/tools.webp", "Illustration of a toolbox with a wrench, pipe fittings and a measuring tape"),
                   radius="8px"),
        image_text(s, "services", "electrical", "Electrical", "Electrical", "Safe, certified electrical work",
                   "Fault finding, new sockets and lighting, consumer units and full rewires. Every job is tested and certified, and you get the paperwork by email.",
                   ("assets/house.webp", "Illustration of a house cut away to show pipes and wiring inside the walls"),
                   image_left=False, bg="surface", radius="8px"),
        faq(s, "services", "faq", "Common questions", [
            ("Do you charge a call-out fee?", "No call-out fee in our service area during normal hours. Emergency visits have a flat $89 attendance charge."),
            ("Are you licensed and insured?", "Yes. All our engineers are licensed, and we carry full public liability insurance."),
            ("Do you guarantee your work?", "Yes. <strong>Every repair has a 12-month guarantee</strong> on parts and labour."),
            ("Can I get a quote for a bigger job?", "Of course. Book a free visit and we will send a written, fixed quote within two days."),
        ], bg="base"),
        cta_band(s, "services", "Need a hand?", "Book online in two minutes, or call and talk to a real person.", "Book a visit"),
    ])

    prices = "\n\n".join([
        page_intro(s, "prices", "Prices", "Honest, upfront prices",
                   "Typical prices for common jobs. You always get a fixed price before any work starts."),
        price_list(s, "prices", "plumbing", "Plumbing prices", "Plumbing", [
            ("Fix a leaking tap", "Washer or cartridge replaced, parts included.", "$95"),
            ("Unblock a drain", "Sinks, showers and baths, cleared and tested.", "$120"),
            ("Replace a toilet", "Supply and fit a standard toilet, old one removed.", "$340"),
            ("Water heater service", "Full safety check and clean.", "$150"),
        ]),
        price_list(s, "prices", "electrical", "Electrical prices", "Electrical", [
            ("Add a socket", "New double socket on an existing circuit.", "$140"),
            ("Replace a light fitting", "Customer-supplied fitting, fitted and tested.", "$85"),
            ("Safety inspection", "Full home inspection with a written report.", "$190"),
            ("EV charger install", "Standard home charger, fitted and certified.", "From $650"),
        ], bg="surface", note="Prices include labour and standard parts. Your engineer confirms the exact price before starting."),
        cta_band(s, "prices", "Want a fixed quote?", "Tell us about the job and we will send a price, usually the same day.", "Get a quote"),
    ])

    about = "\n\n".join([
        page_intro(s, "about", "About us", "A family business since 2004",
                   "Started by Tom Hartley with one van and a toolbox. Now 14 engineers, still family-run."),
        image_text(s, "about", "story", "Story", "Our story", "Local people, local work",
                   "We live where we work, so our reputation is everything. That is why we turn up when we say we will, explain what we find and <strong>never charge more than we quote</strong>.",
                   ("assets/van.webp", "Illustration of a service van parked outside a house on a sunny street"), radius="8px"),
        team(s, "about", "team", "Meet some of the team", "Every engineer is employed by us, trained by us and background checked.",
             [("Tom Hartley", "Founder, master plumber", "Tom still takes calls on Saturday mornings and knows half the boilers in town by name."),
              ("Rosa Diaz", "Lead electrician", "Rosa runs our electrical team and signs off every certificate we issue."),
              ("Sam Okoro", "Heating engineer", "Sam services more than 400 boilers a year and explains everything as he goes.")],
             radius="8px"),
        cta_band(s, "about", "Let us take care of it", "Friendly, local and guaranteed. Book your visit today.", "Book a visit"),
    ])

    contact_page = "\n\n".join([
        page_intro(s, "contact", "Contact", "Book a visit or ask a question",
                   "Call us for emergencies. For everything else, email or call during opening hours."),
        contact(s, "contact", biz, "Call or email", "Where we work",
                "Oakdale and every town within <strong>20 miles</strong>, including Millbrook, Westfield and Cedar Hill.",
                "Emergency?", "Call our main number at any hour. If water is leaking, <strong>turn off your stopcock first</strong>.",
                radius="8px"),
    ])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": home},
            {"slug": "services", "title": "Services", "markup": services},
            {"slug": "prices", "title": "Prices", "markup": prices},
            {"slug": "about", "title": "About", "markup": about},
            {"slug": "contact", "title": "Contact", "markup": contact_page},
        ],
        "parts": {"header": hdr, "footer": ftr},
        "templates": template(),
    }
