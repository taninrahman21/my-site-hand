"""Fitness starter site: an independent gym and class studio."""

from blocks import Site
from common import base_styles, check_palette, font_pair, palette
from kit import (basics, contact, faq, features, footer, header, hero_split, image_text, pricing, stats, steps, team,
                 template, testimonial)
from sections import cta_band, page_intro

MANIFEST = {
    "slug": "fitness",
    "name": "Fitness & Gym",
    "category": "fitness",
    "description": "A bold, energetic site for a gym, studio or personal trainer. Classes, memberships with prices, coaches and a free trial offer.",
    "version": "1.0.0",
}

PALETTES = [
    check_palette({"id": "volt", "name": "Volt", "colors": {
        "base": "#0d0f0c", "surface": "#161a14", "accent": "#20261d", "contrast": "#f1f5ec",
        "muted": "#a7b3a0", "primary": "#c8f04a", "on-primary": "#0d0f0c"}}),
    check_palette({"id": "blaze", "name": "Blaze", "colors": {
        "base": "#0f0d0c", "surface": "#1a1614", "accent": "#26201d", "contrast": "#f7f1ec",
        "muted": "#b7a99f", "primary": "#ff7a3d", "on-primary": "#0f0d0c"}}),
    palette("power", "Power red", "#c81e3a"),
    palette("cobalt", "Cobalt", "#1d4ed8"),
    palette("forest", "Trail", "#166534"),
    palette("steel", "Steel", "#27272a"),
]

FONT_PAIRS = [
    font_pair("power", "Power", "geometric", "system", "800"),
    font_pair("modern", "Modern", "system", "system", "800"),
    font_pair("athletic", "Athletic", "slab", "system", "700"),
    font_pair("humanist", "Humanist", "humanist", "humanist", "700"),
    font_pair("soft", "Soft", "rounded", "system", "800"),
]

STYLES = base_styles(button_radius="0px")

NAVIGATION = [
    {"label": "Classes", "page": "classes"},
    {"label": "Membership", "page": "membership"},
    {"label": "Coaches", "page": "coaches"},
    {"label": "Visit", "page": "visit"},
]


def build(s: Site):
    biz = basics(s, "Ironwood Fitness", "Strength, conditioning and community", "Ironwood Fitness",
                 "(555) 016-8820", "train@ironwood.example", "77 Canal Street, Riverside",
                 hours="Mon to Fri: 5:30am to 10pm<br>Sat and Sun: 7am to 6pm")
    hdr = header(s, "Free trial week", "membership")
    ftr = footer(s, biz, "A strength and conditioning gym for real people. No mirrors to pose in, just good coaching.",
                 "Train hard, recover well.", contact_heading="Find us")

    home = "\n\n".join([
        hero_split(s, "home", "Strength and conditioning gym",
                   "Get stronger than you were last month",
                   "Coached classes, open gym and a community that notices when you are missing. Your first week is free.",
                   ("Start your free week", "membership"), ("See the classes", "classes"),
                   ("assets/gym.webp", "Illustration of a gym with a squat rack, barbell and kettlebells"),
                   radius="0px", aspect="1/1"),
        stats(s, "home", "stats", [("40+", "coached classes every week"), ("6", "full-time coaches"),
                                   ("5:30am", "doors open on weekdays"), ("1,200", "members and counting")], bg="surface"),
        features(s, "home", "classes", "Classes", "Classes", "Find your kind of workout", None,
                 [("Strength", "Learn the big lifts with small groups and a coach watching every rep."),
                  ("Conditioning", "Short, hard sessions that build engines. Scaled for every level."),
                  ("Mobility", "Slow down, move better and recover so you can come back tomorrow.")],
                 bg="base", card_bg="surface", radius="0px", more=("Full timetable", "classes")),
        image_text(s, "home", "coaching", "Coaching", "Real coaching", "Every class has a coach, not a playlist",
                   "Our coaches correct your form, adjust the workout to your level and remember your name. <strong>New members get a free one-to-one session</strong> to set goals.",
                   ("assets/class.webp", "Illustration of a group fitness class with mats, kettlebells and a coach"),
                   btn=("Meet the coaches", "coaches"), bg="surface", radius="0px"),
        pricing(s, "home", "plans", "Membership", "Membership", "Simple memberships, no contracts",
                "Cancel any time with 30 days notice. Every plan starts with a free week.",
                [("Open gym", "$45", "per month", "Full gym access<br>Open 7 days<br>Locker room and showers", "Start free week", False),
                 ("Unlimited", "$89", "per month", "All classes and open gym<br>Free one-to-one session<br>Member events",
                  "Start free week", True),
                 ("Class pack", "$120", "10 classes", "Use within 3 months<br>Any class type<br>Share with a friend",
                  "Buy a pack", False)],
                radius="0px", cta_page="membership"),
        testimonial(s, "home", "I had never lifted anything heavier than my shopping. A year later I deadlift my body weight and actually look forward to 6am.",
                    "Lauren K., member since 2023", bg="surface"),
        cta_band(s, "home", "Your first week is on us", "Try every class, meet the coaches and see if Ironwood fits you.", "Claim your free week", "membership"),
    ])

    classes = "\n\n".join([
        page_intro(s, "classes", "Classes", "Something for every goal and every level",
                   "Classes run from 5:30am to 8pm. Every one can be scaled, so beginners and athletes train side by side."),
        features(s, "classes", "list", "Class list", "Timetable", "Our weekly classes", None,
                 [("Strength", "Mon, Wed, Fri at 6am, 12pm and 6pm. Squats, presses and pulls."),
                  ("Conditioning", "Daily at 7am and 7pm. Intervals on bikes, rowers and sleds."),
                  ("Kettlebells", "Tue and Thu at 6am and 6pm. Swings, carries and full-body flows."),
                  ("Mobility", "Tue, Thu and Sun at 9am. Stretching and control for better movement."),
                  ("Beginners", "Sat at 9am. Learn the basics in a small, friendly group."),
                  ("Teens", "Mon and Wed at 4pm. Safe strength training for ages 13 to 17.")],
                 bg="base", card_bg="surface", radius="0px"),
        steps(s, "classes", "first", "Your first week", "Your first week at Ironwood",
              [("Book a free intro", "A 30-minute session with a coach to talk goals and test movement."),
               ("Try the classes", "Come to as many classes as you like for seven days."),
               ("Choose a plan", "Pick the membership that fits, or walk away with no hard feelings.")],
              bg="surface"),
        cta_band(s, "classes", "See you on the floor", "Book your free intro session today.", "Book an intro", "visit"),
    ])

    membership = "\n\n".join([
        page_intro(s, "membership", "Membership", "Join without the small print",
                   "No joining fee, no annual contract and no hard sell. Just pick a plan and start training."),
        pricing(s, "membership", "plans", "Plans", "Plans", "Pick the plan that fits your week", None,
                [("Open gym", "$45", "per month", "Full gym access<br>Open 7 days<br>Locker room and showers", "Start free week", False),
                 ("Unlimited", "$89", "per month", "All classes and open gym<br>Free one-to-one session<br>Member events",
                  "Start free week", True),
                 ("Personal training", "$60", "per session", "One-to-one coaching<br>Custom program<br>Monthly check-ins",
                  "Book a session", False)],
                radius="0px", cta_page="visit"),
        faq(s, "membership", "faq", "Membership questions", [
            ("Is there a joining fee?", "No. You pay for your first month only after your free week ends."),
            ("Can I freeze my membership?", "Yes. Freeze for up to three months a year for travel, injury or anything else."),
            ("I am a complete beginner. Is that OK?", "Absolutely. <strong>Most of our members started with no experience.</strong> Every class can be scaled."),
            ("How do I cancel?", "Email us. We stop the next payment after 30 days, and you can keep training until then."),
        ]),
        cta_band(s, "membership", "Start with a free week", "No card needed to try. Just book an intro and come in.", "Book an intro", "visit"),
    ])

    coaches = "\n\n".join([
        page_intro(s, "coaches", "Coaches", "The people in your corner",
                   "Qualified, experienced and genuinely interested in how you are doing."),
        team(s, "coaches", "team", "Our coaches", "Every coach holds a recognised qualification and keeps learning every year.",
             [("Marcus Reid", "Head coach", "Former rugby player, 12 years coaching strength. Loves a good deadlift and a bad pun."),
              ("Jess Nakamura", "Conditioning coach", "Ran her first marathon at 40. Makes hard workouts feel possible."),
              ("Dev Patel", "Mobility and rehab", "Physiotherapy background. Helps members train around old injuries.")],
             radius="0px"),
        stats(s, "coaches", "stats", [("60+", "years of coaching between them"), ("15", "recognised qualifications"),
                                      ("1:12", "coach to member ratio in class"), ("100%", "of classes coached")], bg="surface"),
        cta_band(s, "coaches", "Train with us", "Book a free intro and meet the team.", "Book an intro", "visit"),
    ])

    visit = "\n\n".join([
        page_intro(s, "visit", "Visit", "Come and see the gym",
                   "Drop in during staffed hours for a tour, or book your free intro session online."),
        contact(s, "visit", biz, "Find Ironwood", "Getting here",
                "We are on Canal Street, next to the old mill. <strong>Free parking</strong> behind the building and secure bike racks at the door.",
                "What to bring", "Comfortable clothes, trainers and a water bottle. We have towels, lockers and showers.",
                radius="0px"),
    ])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": home},
            {"slug": "classes", "title": "Classes", "markup": classes},
            {"slug": "membership", "title": "Membership", "markup": membership},
            {"slug": "coaches", "title": "Coaches", "markup": coaches},
            {"slug": "visit", "title": "Visit", "markup": visit},
        ],
        "parts": {"header": hdr, "footer": ftr},
        "templates": template(),
    }
