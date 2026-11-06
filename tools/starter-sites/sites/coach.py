"""Coach / consultant starter site: a career and leadership coach."""

from blocks import Site
from common import base_styles, check_palette, font_pair, palette
from kit import (basics, contact, faq, features, footer, header, hero_split, image_text, pricing, stats, steps,
                 template, testimonial)
from sections import cta_band, page_intro

MANIFEST = {
    "slug": "coach",
    "name": "Coach & Consultant",
    "category": "coaching",
    "description": "A warm, personal site for a coach, consultant or therapist. Your story, clear programs with prices, client stories and a free intro call.",
    "version": "1.0.0",
}

PALETTES = [
    check_palette({"id": "dusk", "name": "Dusk teal", "colors": {
        "base": "#fffdf9", "surface": "#fbf3e8", "accent": "#f4e3cc", "contrast": "#1f1a14",
        "muted": "#5d5043", "primary": "#155e75", "on-primary": "#ffffff"}}),
    palette("clay", "Clay", "#b0532e", base="#fffcf8"),
    palette("plum", "Plum", "#6d3b7d", base="#fffbfe"),
    palette("olive", "Olive", "#5b6b2f", base="#fefef9"),
    palette("ink", "Ink blue", "#253b8c"),
    check_palette({"id": "evening", "name": "Evening", "colors": {
        "base": "#141618", "surface": "#1d2023", "accent": "#272b2f", "contrast": "#f4f1ec",
        "muted": "#b6b0a6", "primary": "#f2b880", "on-primary": "#141618"}}),
]

FONT_PAIRS = [
    font_pair("warm", "Warm", "humanist", "humanist", "600"),
    font_pair("soft", "Soft", "rounded", "system", "700"),
    font_pair("editorial", "Editorial", "old-style", "system", "500"),
    font_pair("classic", "Classic", "transitional", "system", "600"),
    font_pair("modern", "Modern", "system", "system", "700"),
]

STYLES = base_styles(button_radius="999px")

NAVIGATION = [
    {"label": "Programs", "page": "programs"},
    {"label": "About", "page": "about"},
    {"label": "Stories", "page": "stories"},
    {"label": "Contact", "page": "contact"},
]


def build(s: Site):
    biz = basics(s, "Maya Ortiz", "Career and leadership coaching", "Maya Ortiz Coaching",
                 "(555) 011-2048", "hello@mayaortiz.example", "Online, and in person in Denver")
    hdr = header(s, "Book a free call", weight="600")
    ftr = footer(s, biz, "Coaching for people who are good at their job and ready for what comes next.",
                 "Coaching is not therapy or medical advice.")

    home = "\n\n".join([
        hero_split(s, "home", "Career and leadership coach",
                   "Find work that fits the person you are now",
                   "One-to-one coaching for people ready to change direction, step up to lead, or stop feeling stuck. Start with a free 20-minute call.",
                   ("Book a free call", "contact"), ("See programs", "programs"),
                   ("assets/portrait.webp", "Illustrated portrait of a smiling coach sitting in a bright room with plants"),
                   radius="24px", aspect="4/5"),
        features(s, "home", "help", "How I help", "How I help", "Is this you?", None,
                 [("Stuck in the wrong job", "You are good at it, but it no longer fits. You want a plan, not a leap in the dark."),
                  ("New to leading people", "You were promoted for your skills, and now you need a different set."),
                  ("Running on empty", "You want ambition without burnout, and a week that leaves room to breathe.")],
                 radius="24px"),
        image_text(s, "home", "approach", "Approach", "My approach", "Practical, kind and honest",
                   "We talk about what you actually want, then turn it into small steps you can take this week. <strong>No jargon and no personality tests you will forget.</strong>",
                   ("assets/notebook.webp", "Illustration of an open notebook, a pen and a cup of coffee on a wooden table"),
                   btn=("About Maya", "about"), radius="24px"),
        pricing(s, "home", "programs", "Programs", "Programs", "Ways to work together",
                "Every program starts with a free 20-minute call to check we are a good fit.",
                [("Single session", "$180", "75 minutes", "One focused session<br>Notes and next steps<br>Email follow-up",
                  "Book a session", False),
                 ("Next step", "$1,200", "8 sessions over 3 months", "Fortnightly coaching<br>Personal action plan<br>Email support between sessions",
                  "Book a free call", True),
                 ("Leadership", "$2,400", "12 sessions over 6 months", "For new and growing leaders<br>360 feedback review<br>Priority support",
                  "Book a free call", False)],
                radius="24px"),
        testimonial(s, "home", "Six months after our first call I changed careers, took a pay rise and stopped dreading Mondays. Maya asked the questions I had been avoiding.",
                    "Daniel F., product manager"),
        cta_band(s, "home", "Ready for your next step?", "Book a free 20-minute call. No pressure, no sales pitch.", "Book a free call"),
    ])

    programs = "\n\n".join([
        page_intro(s, "programs", "Programs", "Coaching that fits your life",
                   "Online or in person, at times that work around your job and family."),
        pricing(s, "programs", "plans", "Programs", "Programs", "Choose your program", None,
                [("Single session", "$180", "75 minutes", "One focused session<br>Notes and next steps<br>Email follow-up",
                  "Book a session", False),
                 ("Next step", "$1,200", "8 sessions over 3 months", "Fortnightly coaching<br>Personal action plan<br>Email support between sessions",
                  "Book a free call", True),
                 ("Leadership", "$2,400", "12 sessions over 6 months", "For new and growing leaders<br>360 feedback review<br>Priority support",
                  "Book a free call", False)],
                radius="24px"),
        steps(s, "programs", "how", "How it works", "How coaching works",
              [("Free intro call", "Twenty minutes to talk about where you are and where you want to be."),
               ("Your plan", "We agree goals, how often we meet and how we will know it is working."),
               ("Regular sessions", "Focused conversations with clear actions, and support in between.")],
              bg="surface"),
        faq(s, "programs", "faq", "Common questions", [
            ("Is coaching the same as therapy?", "No. Coaching looks forward and focuses on goals. If therapy would help you more, I will tell you."),
            ("Can my employer pay?", "Often, yes. <strong>I can send an invoice to your company</strong> and a short proposal for your manager."),
            ("What if it is not working?", "Tell me. We adjust the plan, and you can stop at any time with a refund for unused sessions."),
        ], bg="base"),
        cta_band(s, "programs", "Not sure which program fits?", "We will work it out together on a free call.", "Book a free call"),
    ])

    about = "\n\n".join([
        page_intro(s, "about", "About", "Hello, I am Maya",
                   "A former engineering manager who now helps people find work that fits."),
        image_text(s, "about", "story", "Story", "My story", "I have been where you are",
                   "I spent twelve years in tech, the last five leading teams. I was good at it and exhausted by it, and nobody had told me there were other options.",
                   ("assets/workshop.webp", "Illustration of a small workshop with a whiteboard covered in sticky notes"),
                   body2="Since 2019 I have coached more than 300 people through career changes, promotions and burnout. <strong>I am certified by the International Coaching Federation.</strong>",
                   radius="24px"),
        stats(s, "about", "numbers", [("300+", "clients coached"), ("12 yrs", "leading teams in tech"),
                                      ("ICF", "certified coach"), ("92%", "reach their main goal")], bg="surface"),
        cta_band(s, "about", "Let us talk", "Your first call is free, and there is no obligation.", "Book a free call"),
    ])

    stories = "\n\n".join([
        page_intro(s, "stories", "Client stories", "Real people, real changes",
                   "A few words from people I have coached. Names are shared with their permission."),
        testimonial(s, "stories", "Six months after our first call I changed careers, took a pay rise and stopped dreading Mondays.",
                    "Daniel F., product manager", sid="story1"),
        testimonial(s, "stories", "I became a manager overnight and had no idea what I was doing. Maya gave me a toolkit I still use every week.",
                    "Grace O., engineering lead", bg="base", sid="story2"),
        testimonial(s, "stories", "I came in burnt out and left with boundaries, a calmer calendar and my weekends back.",
                    "Tomasz W., agency founder", sid="story3"),
        cta_band(s, "stories", "Write your own story", "It starts with a free 20-minute conversation.", "Book a free call"),
    ])

    contact_page = "\n\n".join([
        page_intro(s, "contact", "Contact", "Book your free call",
                   "Send me a short note about where you are and what you would like to change. I reply within two working days."),
        contact(s, "contact", biz, "Get in touch", "What to expect",
                "A relaxed 20-minute video call. We talk about your situation and whether coaching could help. <strong>No preparation needed.</strong>",
                "Working with companies", "I also run leadership programs for teams. Ask for a proposal.",
                radius="24px"),
    ])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": home},
            {"slug": "programs", "title": "Programs", "markup": programs},
            {"slug": "about", "title": "About", "markup": about},
            {"slug": "stories", "title": "Client Stories", "markup": stories},
            {"slug": "contact", "title": "Contact", "markup": contact_page},
        ],
        "parts": {"header": hdr, "footer": ftr},
        "templates": template(),
    }
