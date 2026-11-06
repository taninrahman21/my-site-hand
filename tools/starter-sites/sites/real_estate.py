"""Real estate starter site: an independent agent or small agency."""

from blocks import Site
from common import base_styles, check_palette, font_pair, palette
from kit import (basics, contact, faq, features, footer, header, hero_split, image_text, listings, stats, steps,
                 template, testimonial)
from sections import cta_band, page_intro

MANIFEST = {
    "slug": "real-estate",
    "name": "Real Estate",
    "category": "property",
    "description": "A polished site for a real estate agent or small agency. Featured listings, a clear guide for sellers, your story and a free valuation offer.",
    "version": "1.0.0",
}

PALETTES = [
    check_palette({"id": "harbor", "name": "Harbor navy", "colors": {
        "base": "#ffffff", "surface": "#f4f6f8", "accent": "#e2e8ee", "contrast": "#0e1c2b",
        "muted": "#4a5868", "primary": "#1d3c5e", "on-primary": "#ffffff"}}),
    palette("pine", "Pine", "#1f5f46", base="#fcfdfb"),
    palette("terracotta", "Terracotta", "#a0482a", base="#fffcf9"),
    palette("charcoal", "Charcoal", "#2f3437"),
    palette("coastal", "Coastal", "#1b6f8a", base="#fbfdfe"),
    check_palette({"id": "evening", "name": "Evening", "colors": {
        "base": "#10151c", "surface": "#18202a", "accent": "#222c39", "contrast": "#f1f4f8",
        "muted": "#aab5c3", "primary": "#d9b26b", "on-primary": "#10151c"}}),
]

FONT_PAIRS = [
    font_pair("classic", "Classic", "transitional", "system", "600"),
    font_pair("elegant", "Elegant", "old-style", "system", "600"),
    font_pair("modern", "Modern", "system", "system", "700"),
    font_pair("geometric", "Geometric", "geometric", "system", "700"),
    font_pair("humanist", "Humanist", "humanist", "humanist", "600"),
]

STYLES = base_styles(button_radius="4px")

NAVIGATION = [
    {"label": "Homes for sale", "page": "listings"},
    {"label": "Sell", "page": "sell"},
    {"label": "About", "page": "about"},
    {"label": "Contact", "page": "contact"},
]

HOMES = [
    ("assets/house-1.webp", "Illustration of a modern two-storey house with large windows and a garden"),
    ("assets/house-2.webp", "Illustration of a cottage with a red door, a porch and flower beds"),
    ("assets/house-3.webp", "Illustration of a row of three townhouses in different colours"),
]


def build(s: Site):
    biz = basics(s, "Harbor & Pine Realty", "Helping families find home in Cedar Bay", "Harbor & Pine Realty",
                 "(555) 015-6290", "hello@harborpine.example", "42 Main Street, Cedar Bay",
                 hours="Mon to Fri: 9am to 6pm<br>Saturday: 10am to 4pm<br>Sunday: by appointment")
    hdr = header(s, "Free valuation", "sell", weight="600")
    ftr = footer(s, biz, "Local agents who know every street in Cedar Bay. Buying, selling and renting since 2010.",
                 "Licensed real estate brokerage.", contact_heading="Office")

    home = "\n\n".join([
        hero_split(s, "home", "Cedar Bay real estate",
                   "Find the home that fits your next chapter",
                   "Local agents, honest advice and homes you will not see anywhere else. Buying or selling, we are with you from first viewing to keys.",
                   ("See homes for sale", "listings"), ("Get a free valuation", "sell"),
                   HOMES[0], radius="6px"),
        stats(s, "home", "stats", [("350+", "homes sold in Cedar Bay"), ("21 days", "average time to sale"),
                                   ("98%", "of asking price achieved"), ("15 yrs", "selling locally")], bg="surface"),
        listings(s, "home", "featured", "Featured homes", "Featured homes", [
            (HOMES[0][0], HOMES[0][1], "$685,000", "14 Seaview Road", "4 beds · 3 baths · 2,400 sq ft"),
            (HOMES[1][0], HOMES[1][1], "$449,000", "7 Orchard Lane", "3 beds · 2 baths · 1,650 sq ft"),
            (HOMES[2][0], HOMES[2][1], "$389,000", "22 Harbour Row", "2 beds · 2 baths · 1,200 sq ft"),
        ], more=("All homes for sale", "listings"), radius="6px"),
        image_text(s, "home", "selling", "Selling", "Thinking of selling?", "Know what your home is worth",
                   "We know what sold on your street last month and why. <strong>Get a free, no-pressure valuation</strong> within 48 hours.",
                   HOMES[1], btn=("Book a valuation", "sell"), bg="surface", radius="6px"),
        testimonial(s, "home", "Harbor & Pine sold our house in nine days for more than we hoped. They handled every call so we could focus on the move.",
                    "The Alvarez family, sold in 2025"),
        cta_band(s, "home", "Let us find your next home", "Tell us what you are looking for and we will call you first when it comes up.", "Contact an agent"),
    ])

    listings_page = "\n\n".join([
        page_intro(s, "listings", "Homes for sale", "Homes for sale in Cedar Bay",
                   "New homes come up every week. Call us to book a viewing, or ask to hear about homes before they go online."),
        listings(s, "listings", "new", "Just listed", "Just listed", [
            (HOMES[0][0], HOMES[0][1], "$685,000", "14 Seaview Road", "4 beds · 3 baths · 2,400 sq ft"),
            (HOMES[1][0], HOMES[1][1], "$449,000", "7 Orchard Lane", "3 beds · 2 baths · 1,650 sq ft"),
            (HOMES[2][0], HOMES[2][1], "$389,000", "22 Harbour Row", "2 beds · 2 baths · 1,200 sq ft"),
        ], radius="6px"),
        listings(s, "listings", "more", "More homes", "Family homes", [
            (HOMES[1][0], HOMES[1][1], "$525,000", "3 Maple Court", "4 beds · 2 baths · 1,900 sq ft"),
            (HOMES[0][0], HOMES[0][1], "$799,000", "88 Cliff Drive", "5 beds · 4 baths · 3,100 sq ft"),
            (HOMES[2][0], HOMES[2][1], "$359,000", "5 Station Mews", "2 beds · 1 bath · 980 sq ft"),
        ], bg="surface", radius="6px"),
        cta_band(s, "listings", "Not seeing the right home?", "Tell us what you need. Many of our homes sell before they are advertised.", "Tell us what you need"),
    ])

    sell = "\n\n".join([
        page_intro(s, "sell", "Sell with us", "Sell for the best price, with less stress",
                   "Clear advice, great photos and an agent who answers the phone on Saturdays."),
        steps(s, "sell", "process", "Process", "How selling works",
              [("Free valuation", "We visit, look at recent sales nearby and give you an honest price range."),
               ("Get market ready", "Professional photos, floor plans and a launch plan for the first two weeks."),
               ("Offers and closing", "We negotiate for you and stay with you until the keys change hands.")]),
        features(s, "sell", "included", "What is included", "Included", "Everything you need to sell",
                 None,
                 [("Professional photos", "Daylight and twilight photography, plus a floor plan."),
                  ("Local marketing", "Featured on the big property sites and our buyer list of 2,000 people."),
                  ("Viewings handled", "We show every buyer around, including evenings and weekends.")],
                 radius="6px"),
        faq(s, "sell", "faq", "Questions sellers ask", [
            ("What do you charge?", "A fixed percentage agreed upfront, and nothing at all unless we sell your home."),
            ("How long will it take to sell?", "Our average is 21 days from listing to accepted offer, but every home is different."),
            ("Do I need to be home for viewings?", "No. <strong>We handle every viewing</strong> and send you feedback the same day."),
        ], bg="base"),
        cta_band(s, "sell", "What is your home worth?", "Book a free valuation and get an honest price range within 48 hours.", "Book a valuation"),
    ])

    about = "\n\n".join([
        page_intro(s, "about", "About", "Local agents, local knowledge",
                   "Harbor & Pine was started in 2010 by two agents who grew up in Cedar Bay."),
        image_text(s, "about", "story", "Story", "Our story", "We know every street",
                   "Sarah Lin and David Moss opened their first office above the bakery on Main Street. They wanted an agency that treats a home sale as the big life moment it is.",
                   HOMES[2], body2="Fifteen years later we are still on Main Street, still answering our own phones, and <strong>most of our clients come from friends</strong>.",
                   radius="6px"),
        stats(s, "about", "numbers", [("350+", "happy sellers"), ("4.9/5", "from 300 reviews"),
                                      ("6", "local agents"), ("1", "office, on Main Street")], bg="surface"),
        cta_band(s, "about", "Come and say hello", "Pop into the office or give us a call. The coffee is always on.", "Contact us"),
    ])

    contact_page = "\n\n".join([
        page_intro(s, "contact", "Contact", "Talk to a local agent",
                   "Buying, selling or just curious about the market? We are happy to help."),
        contact(s, "contact", biz, "Call or visit", "Book a viewing",
                "Call the office or email us with the address of the home you would like to see. <strong>Evening and weekend viewings</strong> are available.",
                "Selling?", "Ask for a free valuation. We visit within 48 hours and there is no obligation.",
                radius="6px"),
    ])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": home},
            {"slug": "listings", "title": "Homes for Sale", "markup": listings_page},
            {"slug": "sell", "title": "Sell", "markup": sell},
            {"slug": "about", "title": "About", "markup": about},
            {"slug": "contact", "title": "Contact", "markup": contact_page},
        ],
        "parts": {"header": hdr, "footer": ftr},
        "templates": template(),
    }
