"""Beauty salon and spa starter site."""

from blocks import Site
from common import base_styles, check_palette, font_pair, palette
from kit import (basics, contact, faq, features, footer, header, hero_split, image_text, price_list, team, template,
                 testimonial)
from sections import cta_band, page_intro

MANIFEST = {
    "slug": "salon",
    "name": "Salon & Spa",
    "category": "beauty",
    "description": "A calm, elegant site for a hair salon, beauty studio or day spa. Treatments with prices, your team and easy booking.",
    "version": "1.0.0",
}

PALETTES = [
    check_palette({"id": "blush", "name": "Blush", "colors": {
        "base": "#fffaf8", "surface": "#fbefec", "accent": "#f4dcd6", "contrast": "#2d1a1a",
        "muted": "#664b49", "primary": "#9c3556", "on-primary": "#ffffff"}}),
    palette("sage", "Sage spa", "#4d6b55", base="#fbfcf9"),
    palette("lavender", "Lavender", "#6b4fa0", base="#fdfbff"),
    palette("nude", "Nude", "#8a5a44", base="#fffbf7"),
    palette("ocean", "Sea mist", "#2f6f73", base="#fafdfd"),
    check_palette({"id": "noir", "name": "Noir", "colors": {
        "base": "#141012", "surface": "#1e181b", "accent": "#2a2125", "contrast": "#f8eef1",
        "muted": "#c0abb2", "primary": "#e8a0b4", "on-primary": "#141012"}}),
]

FONT_PAIRS = [
    font_pair("elegant", "Elegant", "old-style", "system", "500"),
    font_pair("luxe", "Luxe", "didone", "system", "500"),
    font_pair("classic", "Classic", "transitional", "humanist", "500"),
    font_pair("soft", "Soft", "rounded", "system", "600"),
    font_pair("modern", "Modern", "system", "system", "600"),
]

STYLES = base_styles(button_radius="999px")

NAVIGATION = [
    {"label": "Treatments", "page": "treatments"},
    {"label": "About", "page": "about"},
    {"label": "Team", "page": "team"},
    {"label": "Book", "page": "book"},
]


def build(s: Site):
    biz = basics(s, "Bloom & Co.", "Hair, beauty and spa", "Bloom & Co. Salon & Spa",
                 "(555) 014-7712", "hello@bloomandco.example", "9 Willow Arcade, Bayside",
                 hours="Tue to Fri: 9am to 8pm<br>Saturday: 9am to 5pm<br>Sun and Mon: closed")
    hdr = header(s, "Book now", "book", weight="500")
    ftr = footer(s, biz, "A calm space for hair, skin and nails, with time to slow down between appointments.",
                 "Gift cards available at reception.", contact_heading="Visit us")

    home = "\n\n".join([
        hero_split(s, "home", "Hair, beauty and spa",
                   "Take an hour that is just for you",
                   "Expert stylists, gentle facials and a calm room with no rush. Book online in a minute, any time of day.",
                   ("Book an appointment", "book"), ("View treatments", "treatments"),
                   ("assets/salon.webp", "Illustration of a bright salon with a styling chair, round mirror and plants"),
                   radius="24px", aspect="4/5"),
        features(s, "home", "treatments", "Treatments", "Treatments", "Something for every kind of day",
                 "Every appointment starts with a short chat about what you want, so nothing is rushed or guessed.",
                 [("Hair", "Cuts, colour, balayage and blow-dries, finished with care."),
                  ("Skin", "Facials and skin treatments matched to your skin, not a menu."),
                  ("Nails and spa", "Manicures, pedicures and massage to properly switch off.")],
                 radius="24px", more=("See prices", "treatments")),
        image_text(s, "home", "space", "Our space", "Our space", "A salon that feels like a pause",
                   "Soft light, quiet music and a cup of something warm while you wait. <strong>We book fewer clients</strong> so nobody is hurried.",
                   ("assets/products.webp", "Illustration of skincare bottles, a towel and a small vase of flowers on a shelf"),
                   btn=("About Bloom", "about"), radius="24px"),
        testimonial(s, "home", "The best colour I have ever had, and the first salon where I actually relaxed. Mia listened to every word.",
                    "Hannah R., client since 2021"),
        cta_band(s, "home", "Your chair is waiting", "Book online any time, or call us during opening hours.", "Book now", "book"),
    ])

    treatments = "\n\n".join([
        page_intro(s, "treatments", "Treatments", "Treatments and prices",
                   "Prices are a starting point and depend on hair length and stylist level. We confirm before we begin."),
        price_list(s, "treatments", "hair", "Hair", "Hair", [
            ("Cut and finish", "Consultation, wash, cut and blow-dry.", "$65"),
            ("Blow-dry", "Wash and a smooth or curled finish.", "$40"),
            ("Full colour", "All-over colour with gloss and finish.", "$120"),
            ("Balayage", "Hand-painted, natural-looking highlights.", "From $180"),
        ]),
        price_list(s, "treatments", "skin", "Skin and spa", "Skin and spa", [
            ("Signature facial", "Cleanse, exfoliate, mask and massage. 60 minutes.", "$95"),
            ("Express facial", "A quick glow before an event. 30 minutes.", "$55"),
            ("Back massage", "Relaxing or deep tissue. 30 minutes.", "$50"),
            ("Full body massage", "Unwind from head to toe. 60 minutes.", "$90"),
        ], bg="surface"),
        price_list(s, "treatments", "nails", "Nails", "Nails", [
            ("Gel manicure", "Shape, cuticle care and long-lasting gel.", "$45"),
            ("Spa pedicure", "Soak, scrub, massage and polish.", "$55"),
        ], note="New clients: a free skin or hair consultation is included with your first appointment."),
        cta_band(s, "treatments", "Found your treatment?", "Book online in under a minute.", "Book now", "book"),
    ])

    about = "\n\n".join([
        page_intro(s, "about", "About", "Small salon, big care",
                   "Bloom & Co. opened in 2018 with one idea: beauty appointments should feel restful, not rushed."),
        image_text(s, "about", "story", "Story", "Our story", "Started with two chairs and a kettle",
                   "Founder Mia Laurent trained in London and Paris before coming home to Bayside. She wanted a salon where people leave feeling better, not just looking better.",
                   ("assets/salon.webp", "Illustration of a bright salon with a styling chair, round mirror and plants"),
                   body2="We use <strong>vegan, cruelty-free products</strong>, recycle our foils and colour tubes, and pay our team fairly.",
                   radius="24px"),
        faq(s, "about", "faq", "Good to know", [
            ("Do I need a patch test for colour?", "Yes, for new colour clients. Pop in any time 48 hours before your appointment, it takes five minutes."),
            ("What is your cancellation policy?", "Please give us 24 hours notice. Late cancellations may be charged 50% of the treatment."),
            ("Do you sell gift cards?", "Yes, in any amount, at reception or by phone. <strong>They never expire.</strong>"),
        ]),
        cta_band(s, "about", "Come and see us", "We would love to look after you.", "Book now", "book"),
    ])

    team_page = "\n\n".join([
        page_intro(s, "team", "Team", "Meet the team",
                   "Friendly, experienced and always learning. Choose your favourite when you book."),
        team(s, "team", "team", "Our stylists and therapists", "Every team member trains with us for at least a month before seeing clients.",
             [("Mia Laurent", "Founder, senior stylist", "Colour specialist with 15 years of experience. Known for soft, natural balayage."),
              ("Chloe Park", "Stylist", "Precision cuts and bridal hair. Will tell you honestly what suits you."),
              ("Nadia Rahman", "Skin and spa therapist", "Facials and massage. Clients say they fall asleep within ten minutes.")],
             radius="24px"),
        testimonial(s, "team", "Chloe did my wedding hair and it held all night through dancing in the rain. I cannot recommend her enough.",
                    "Emily T., bride, June 2025"),
        cta_band(s, "team", "Book with your favourite", "Pick your stylist or therapist when you book online.", "Book now", "book"),
    ])

    book = "\n\n".join([
        page_intro(s, "book", "Book", "Book your appointment",
                   "Call us during opening hours or send an email, and we will find a time that suits you."),
        contact(s, "book", biz, "Book by phone or email", "Finding us",
                "We are inside Willow Arcade, next to the florist. <strong>Two hours free parking</strong> in the arcade car park.",
                "Before your visit", "Arrive five minutes early for your first appointment so we can talk through what you would like.",
                radius="24px"),
    ])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": home},
            {"slug": "treatments", "title": "Treatments", "markup": treatments},
            {"slug": "about", "title": "About", "markup": about},
            {"slug": "team", "title": "Team", "markup": team_page},
            {"slug": "book", "title": "Book", "markup": book},
        ],
        "parts": {"header": hdr, "footer": ftr},
        "templates": template(),
    }
