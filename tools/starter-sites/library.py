"""
Shared section library: sections a user (or AI) can add to any page of any
starter site. Dev tooling only; build.py writes /starter-sites/_library/.

Every section is built on the placeholder page "s" with section id "x", so
all its slot ids start with "s.x" (or "image:s.x"). The plugin rewrites that
prefix per instance, e.g. "home.x3.heading", when a section is added.

Sections only use the shared preset slugs (base, surface, primary...), so they
take on each starter site's palette, fonts and corners automatically.
"""

from blocks import group, heading, paragraph, section
from kit import faq, features, image_text, pricing, stats, steps, team, testimonial
from sections import cta_band

PAGE = "s"
SID = "x"


def text_block(s):
    s.group("s/x", "s", "Text")
    return section(
        group(
            heading(s.text("s.x.heading", "A few words about us")),
            paragraph(s.rich("s.x.body", "Tell visitors what makes you different, in your own words. <strong>Keep it short and specific.</strong>", label="Text")),
            layout={"type": "constrained", "contentSize": "760px"},
        ),
    )


LIBRARY = [
    ("features", "Three features", "Three cards with a heading and a short text.",
     lambda s: features(s, PAGE, SID, "Three features", "Why choose us", "What makes us different",
                        "A short line that sums up why people pick you.",
                        [("Friendly experts", "Real people who know their work and explain it clearly."),
                         ("Fair prices", "Clear prices up front, with no surprises later."),
                         ("Here for you", "Quick replies and help whenever you need it.")])),
    ("steps", "How it works", "Three numbered steps.",
     lambda s: steps(s, PAGE, SID, "How it works", "How it works",
                     [("Get in touch", "Tell us what you need. We reply within one working day."),
                      ("Make a plan", "We agree what happens next, when, and what it costs."),
                      ("Done", "We do the work and check that you are happy with it.")])),
    ("stats", "Numbers", "Four big numbers with captions.",
     lambda s: stats(s, PAGE, SID, [("10+", "years in business"), ("500+", "happy customers"),
                                    ("4.9/5", "average review"), ("24h", "reply time")], label="Numbers")),
    ("testimonial", "Testimonial", "A customer quote.",
     lambda s: testimonial(s, PAGE, "Friendly, quick and great value. I would recommend them to anyone.",
                           "A happy customer", sid=SID)),
    ("faq", "Questions", "Four questions and answers that open and close.",
     lambda s: faq(s, PAGE, SID, "Frequently asked questions",
                   [("How do I get started?", "Get in touch by phone or email and we will take it from there."),
                    ("How much does it cost?", "Prices depend on what you need. <strong>Ask us for a free quote.</strong>"),
                    ("How long does it take?", "Most work starts within a week. We give you a clear timeline up front."),
                    ("Can I change my mind?", "Yes. Talk to us and we will find a fair solution.")])),
    ("pricing", "Pricing", "Three plans with prices; one can be highlighted.",
     lambda s: pricing(s, PAGE, SID, "Pricing", "Pricing", "Simple, honest prices",
                       "Choose the option that fits you best.",
                       [("Basic", "$49", "per month", "The essentials<br>Email support", "Choose Basic", False),
                        ("Standard", "$99", "per month", "Everything in Basic<br>Priority support<br>Monthly review", "Choose Standard", True),
                        ("Premium", "$199", "per month", "Everything in Standard<br>Dedicated contact", "Choose Premium", False)],
                       cta_page="-last")),
    ("team", "Team", "Three people with a role and a short bio.",
     lambda s: team(s, PAGE, SID, "Meet the team", "The people behind the work.",
                    [("Alex Morgan", "Founder", "Started the business and still looks after every customer personally."),
                     ("Sam Lee", "Team lead", "Keeps everything running smoothly and on time."),
                     ("Jordan Kay", "Specialist", "Brings years of experience and a lot of patience.")])),
    ("image_text", "Image and text", "An image beside a heading, text and a button.",
     lambda s: image_text(s, PAGE, SID, "Image and text", "Our story", "Built on care and good work",
                          "A short paragraph about you, your story or one thing you do especially well. <strong>Make it personal.</strong>",
                          ("lib:assets/feature.webp", "Illustration of abstract shapes, a plant and a sunny window"),
                          btn=("Get in touch", "-last"))),
    ("cta", "Call to action", "A full-width coloured band with a button.",
     lambda s: cta_band(s, "s.x", "Ready to get started?", "Get in touch today. We would love to hear from you.", "Contact us", "-last")),
    ("text", "Text", "A heading and a paragraph.", text_block),
]
