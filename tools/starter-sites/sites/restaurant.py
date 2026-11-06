"""Restaurant starter site: a neighbourhood Mediterranean restaurant."""

from blocks import (Site, button, buttons, card, column, columns, eyebrow, group, heading, image, navigation,
                    paragraph, post_content, quote, section, separator, site_title, template_part)
from common import base_styles, font_pair
from sections import card_row, centered_head, cta_band, label_value, page_intro

MANIFEST = {
    "slug": "restaurant",
    "name": "Restaurant",
    "category": "food and drink",
    "description": "A warm, inviting site for a restaurant, café or bistro. A full menu with prices, private dining, opening hours and reservations.",
    "version": "1.0.0",
}

PALETTES = [
    {"id": "terracotta", "name": "Terracotta", "colors": {
        "base": "#fbf6ee", "surface": "#f3e8d8", "accent": "#ead6bb", "contrast": "#2a1a12",
        "muted": "#5d4a3c", "primary": "#9a3a1a", "on-primary": "#ffffff"}},
    {"id": "olive", "name": "Olive grove", "colors": {
        "base": "#fafaf4", "surface": "#eef0e2", "accent": "#dfe4c9", "contrast": "#1f2416",
        "muted": "#4f5641", "primary": "#556b2f", "on-primary": "#ffffff"}},
    {"id": "brass", "name": "Charcoal and brass", "colors": {
        "base": "#16130f", "surface": "#211c17", "accent": "#2d261f", "contrast": "#f3ead9",
        "muted": "#bfb19b", "primary": "#d6a84e", "on-primary": "#16130f"}},
    {"id": "sea", "name": "Sea salt", "colors": {
        "base": "#f8fbfc", "surface": "#eaf2f5", "accent": "#d7e7ee", "contrast": "#10232c",
        "muted": "#46606b", "primary": "#1e5f7a", "on-primary": "#ffffff"}},
    {"id": "saffron", "name": "Saffron", "colors": {
        "base": "#fffaf0", "surface": "#fbefd6", "accent": "#f6e0b2", "contrast": "#2b1d05",
        "muted": "#5f4a20", "primary": "#8a5300", "on-primary": "#ffffff"}},
    {"id": "bistro", "name": "Bistro red", "colors": {
        "base": "#ffffff", "surface": "#f7efee", "accent": "#efdcda", "contrast": "#1f1414",
        "muted": "#5a4646", "primary": "#9e1b32", "on-primary": "#ffffff"}},
]

FONT_PAIRS = [
    font_pair("elegant", "Elegant", "old-style", "system", "600"),
    font_pair("bistro", "Bistro", "didone", "humanist", "600"),
    font_pair("classic", "Classic", "transitional", "system", "600"),
    font_pair("rustic", "Rustic", "slab", "humanist", "600"),
    font_pair("modern", "Modern", "system", "system"),
]

STYLES = base_styles(button_radius="4px")

NAVIGATION = [
    {"label": "Home", "page": "home"},
    {"label": "Menu", "page": "menu"},
    {"label": "About", "page": "about"},
    {"label": "Private dining", "page": "private-dining"},
    {"label": "Visit", "page": "visit"},
]


def menu_item(s, prefix, label, name, desc, price):
    """One dish: name and price on a line, description below."""
    return group(
        group(
            heading(s.text(prefix + "_name", name, max=60, label=label + " name"), 3, font_size="medium"),
            paragraph(s.text(prefix + "_price", price, max=12, label=label + " price"),
                      style={"typography": {"fontWeight": "600"}}),
            layout={"type": "flex", "flexWrap": "nowrap", "justifyContent": "space-between", "verticalAlignment": "top"},
        ),
        paragraph(s.text(prefix + "_desc", desc, label=label + " description"), text="muted", font_size="small"),
        gap="0.25rem",
    )


def build(s: Site):
    pages = {}

    # ------------------------------------------------------------------ site
    s.group("site/basics", "site", "Business basics")
    s.text("site.title", "Olive & Ember", max=60, label="Site title", hint="Shown in the header, browser tab and search results.")
    s.text("site.tagline", "Wood-fired Mediterranean kitchen", max=100, label="Tagline")
    biz_name = s.text("biz.name", "Olive & Ember", max=80, label="Business name")
    biz_phone = s.text("biz.phone", "(555) 017-4410", max=32, label="Phone")
    biz_email = s.text("biz.email", "table@oliveandember.example", max=80, label="Email")
    biz_address = s.text("biz.address", "212 Harbor Lane, Westport", max=120, label="Address")
    biz_hours = s.rich("biz.hours", "Tue to Thu: 5pm to 10pm<br>Fri and Sat: 5pm to 11pm<br>Sunday: 12pm to 9pm<br>Monday: closed",
                       max=200, label="Opening hours", hint="One line per day range.")

    s.group("site/header", "site", "Header")
    header = group(
        group(
            site_title(font_size="x-large", weight="600"),
            group(
                navigation(),
                buttons(button(s.text("header.cta", "Book a table", max=24, label="Header button"), "{{page:visit}}")),
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
                site_title(font_size="x-large", weight="600"),
                paragraph(s.text("footer.about", "Seasonal plates from the wood oven, natural wines and a long table for friends.", label="About text"), text="muted"),
                width="40%",
            ),
            column(
                heading(s.text("footer.visit_heading", "Find us", max=40, label="Address heading"), 4),
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
            paragraph("© " + biz_name + ". " + s.text("footer.legal", "Walk-ins welcome at the bar.", max=80, label="Legal line"),
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
                eyebrow(s.text("home.hero.eyebrow", "Open Tuesday to Sunday", max=40)),
                heading(s.text("home.hero.heading", "Fire, olive oil and a table for everyone", max=70,
                               hint="The feeling of the place, in under ten words."), 1, font_size="huge"),
                paragraph(s.text("home.hero.subheading",
                                 "Seasonal Mediterranean cooking from our wood oven, with bread baked every morning and a wine list from small growers.",
                                 max=220), text="muted", font_size="large"),
                buttons(
                    button(s.text("home.hero.cta_primary", "Book a table", max=28), "{{page:visit}}"),
                    button(s.text("home.hero.cta_secondary", "See the menu", max=28), "{{page:menu}}", outline=True),
                    style={"spacing": {"margin": {"top": "var:preset|spacing|50"}}},
                ),
                width="50%", vcenter=True,
            ),
            column(
                image(s.image("image:home.hero", "assets/table.webp", label="Hero image"),
                      s.text("home.hero.image_alt", "Illustration of a table set with bread, olives, a salad and two glasses of wine", max=140,
                             label="Hero image description", hint="Describe the image for people who cannot see it."),
                      aspect="1/1", radius="4px"),
                width="50%", vcenter=True,
            ),
            vcenter=True, gap="var:preset|spacing|70",
        ),
        bg="surface", pad="70",
    )

    s.group("home/kitchen", "home", "Our kitchen")
    kitchen = section(
        group(
            eyebrow(s.text("home.kitchen.eyebrow", "Our kitchen", max=40), align="center"),
            heading(s.text("home.kitchen.heading", "Simple food, cooked over real fire"), align="center", font_size="xx-large"),
            paragraph(s.rich("home.kitchen.body",
                             "Everything starts with the oven. We bake our bread in it each morning, roast vegetables from two local farms and finish fish over the embers. <em>The menu changes with the market</em>, so ask what came in today."),
                      align="center", font_size="large"),
            layout={"type": "constrained", "contentSize": "760px"},
        ),
    )

    s.group("home/dishes", "home", "Signature dishes")
    dish_cols = []
    for i, (name, text) in enumerate([
        ("Ember-roasted lamb", "Slow-cooked shoulder with smoked yoghurt, mint and crispy chickpeas."),
        ("Charred octopus", "With white beans, preserved lemon and our own olive oil."),
        ("Burnt honey tart", "Orange blossom cream and toasted almonds."),
    ], 1):
        dish_cols.append(column(card(
            heading(s.text("home.dishes.item%d_title" % i, name, label="Dish %d name" % i), 3),
            paragraph(s.text("home.dishes.item%d_text" % i, text, label="Dish %d description" % i), text="muted"),
            bg="base",
        )))
    dishes = section(
        centered_head(s, "home.dishes", "From the menu", "Dishes people come back for"),
        columns(*dish_cols, gap="var:preset|spacing|40"),
        buttons(button(s.text("home.dishes.link", "View the full menu", max=28), "{{page:menu}}", outline=True),
                justify="center", style={"spacing": {"margin": {"top": "var:preset|spacing|50"}}}),
        bg="surface",
    )

    s.group("home/room", "home", "The room")
    room = section(
        columns(
            column(
                image(s.image("image:home.room", "assets/room.webp", label="Room image"),
                      s.text("home.room.image_alt", "Illustration of a warm dining room with arched windows, a long wooden table and hanging lamps", max=140,
                             label="Room image description"),
                      radius="4px"),
                width="55%", vcenter=True,
            ),
            column(
                eyebrow(s.text("home.room.eyebrow", "The room", max=40)),
                heading(s.text("home.room.heading", "Forty seats, one long table and a bar")),
                paragraph(s.rich("home.room.body",
                                 "Book a table for two by the window, or bring the whole family to the long table. <strong>The bar is kept for walk-ins</strong>, every night.")),
                buttons(button(s.text("home.room.button", "Plan a private dinner", max=28), "{{page:private-dining}}", outline=True)),
                width="45%", vcenter=True,
            ),
            vcenter=True, gap="var:preset|spacing|70",
        ),
    )

    s.group("home/review", "home", "Review")
    review = section(
        group(
            quote(s.text("home.review.quote",
                         "The bread alone is worth the trip. Warm, generous food and staff who remember your name.",
                         max=260),
                  s.text("home.review.name", "Daniela R., regular since opening night", max=60, label="Name")),
            layout={"type": "constrained", "contentSize": "720px"},
        ),
        bg="accent", pad="70",
    )

    pages["home"] = "\n\n".join([
        hero, kitchen, dishes, room, review,
        cta_band(s, "home", "Save your seat", "Book online or call us. We keep the bar for walk-ins every night.", "Book a table", "visit"),
    ])

    # ------------------------------------------------------------------ menu
    intro = page_intro(s, "menu", "Menu", "Tonight's menu",
                       "Our dishes change with the season. Tell us about any allergies and we will happily adapt.")

    menu_sections = [
        ("starters", "Small plates", [
            ("Warm sourdough", "Baked this morning, with whipped butter and sea salt.", "$7"),
            ("Marinated olives", "Green and black olives with orange zest and fennel.", "$6"),
            ("Grilled halloumi", "With honey, thyme and toasted sesame.", "$12"),
            ("Burrata", "Roasted tomatoes, basil oil and charred bread.", "$15"),
        ]),
        ("mains", "From the oven", [
            ("Ember-roasted lamb", "Smoked yoghurt, mint, crispy chickpeas.", "$32"),
            ("Charred octopus", "White beans, preserved lemon, olive oil.", "$29"),
            ("Whole roast cauliflower", "Tahini, pomegranate and pistachio. Vegan.", "$22"),
            ("Market fish", "Whole fish of the day with herb salsa. Ask your server.", "MP"),
        ]),
        ("desserts", "Something sweet", [
            ("Burnt honey tart", "Orange blossom cream, toasted almonds.", "$11"),
            ("Olive oil cake", "With poached pears and crème fraîche.", "$10"),
            ("Chocolate mousse", "Dark chocolate, sea salt and hazelnut crunch.", "$10"),
            ("Cheese plate", "Three local cheeses, fig jam and crackers.", "$16"),
        ]),
    ]
    menu_blocks = []
    for key, title, items in menu_sections:
        s.group("menu/%s" % key, "menu", title)
        item_cols = []
        for i, (name, desc, price) in enumerate(items, 1):
            item_cols.append(menu_item(s, "menu.%s.item%d" % (key, i), "Dish %d" % i, name, desc, price))
        half = len(item_cols) // 2
        menu_blocks.append(section(
            group(heading(s.text("menu.%s.heading" % key, title, max=40, label="Section heading")), align="wide"),
            columns(column(*item_cols[:half]), column(*item_cols[half:]), gap="var:preset|spacing|70"),
            bg="surface" if "mains" == key else None, pad="60",
        ))

    s.group("menu/note", "menu", "Allergy note")
    note = section(
        group(
            paragraph(s.rich("menu.note.body",
                             "<strong>Allergies and diets:</strong> most dishes can be made vegetarian, vegan or gluten free. Please tell your server before you order."),
                      align="center"),
            layout={"type": "constrained", "contentSize": "720px"},
        ),
        bg="accent", pad="50",
    )

    pages["menu"] = "\n\n".join([intro] + menu_blocks + [note,
        cta_band(s, "menu", "Hungry yet?", "Tables fill up on Fridays and Saturdays, so book a few days ahead.", "Book a table", "visit")])

    # ----------------------------------------------------------------- about
    intro = page_intro(s, "about", "Our story", "A kitchen built around one oven",
                       "Olive & Ember opened in 2019 in an old bakery on Harbor Lane. We kept the oven.")
    s.group("about/story", "about", "Story")
    story = section(
        columns(
            column(
                heading(s.text("about.story.heading", "From bakery to restaurant")),
                paragraph(s.rich("about.story.body1",
                                 "Chef Amir Nasser grew up cooking with his grandmother in Haifa. After fifteen years in city kitchens he wanted somewhere smaller, where he could cook the food he loves for his neighbours.")),
                paragraph(s.rich("about.story.body2",
                                 "The old bakery came with a brick oven older than the building's wiring. <strong>We built the whole menu around it</strong>, and we still bake bread in it every morning.")),
                width="55%", vcenter=True,
            ),
            column(
                image(s.image("image:about.story", "assets/dish.webp", label="Story image"),
                      s.text("about.story.image_alt", "Illustration of a bowl of pasta with tomatoes and basil seen from above", max=140,
                             label="Story image description"),
                      aspect="1/1", radius="4px"),
                width="45%", vcenter=True,
            ),
            vcenter=True, gap="var:preset|spacing|70",
        ),
    )
    s.group("about/values", "about", "What matters to us")
    values = section(
        centered_head(s, "about.values", "What matters to us", "Good ingredients, cooked with care"),
        card_row(s, "about", "values", [
            ("Local first", "Vegetables from two farms within an hour of the restaurant, and fish from the morning boats."),
            ("Nothing wasted", "Bread becomes crumbs, peels become stock, and the menu follows what is at its best."),
            ("Room for everyone", "Vegan, gluten free or picky eater: tell us, and we will cook something you love."),
        ], "Value", "base"),
        bg="surface",
    )
    pages["about"] = "\n\n".join([intro, story, values,
        cta_band(s, "about", "Come and eat with us", "We would love to cook for you.", "Book a table", "visit")])

    # -------------------------------------------------------- private dining
    intro = page_intro(s, "private-dining", "Private dining", "Gather around our long table",
                       "Birthdays, rehearsal dinners and team nights for up to 24 guests, with a menu planned with you.")
    s.group("private-dining/options", "private-dining", "Options")
    options = section(
        card_row(s, "private-dining", "options", [
            ("The long table", "Up to 24 guests at one table in the main room. Sharing plates, served family style."),
            ("Whole restaurant", "Close the doors and make it yours, for up to 40 seated or 60 standing."),
            ("Chef's counter", "Six seats at the pass. A tasting menu cooked in front of you."),
        ], "Option", "surface"),
    )
    s.group("private-dining/how", "private-dining", "How it works")
    how = section(
        group(
            heading(s.text("private-dining.how.heading", "How it works")),
            paragraph(s.rich("private-dining.how.body",
                             "Tell us your date, guest count and any dietary needs. We send a suggested menu within two days, and you can change anything you like. <strong>A deposit holds the date.</strong>")),
            layout={"type": "constrained", "contentSize": "760px"},
        ),
        bg="accent", pad="70",
    )
    pages["private-dining"] = "\n\n".join([intro, options, how,
        cta_band(s, "private-dining", "Plan your event", "Call or email us and we will find a date that works.", "Get in touch", "visit")])

    # ----------------------------------------------------------------- visit
    intro = page_intro(s, "visit", "Visit", "Book a table or just drop in",
                       "Call us to book, or email for groups of more than eight. The bar is always kept for walk-ins.")
    s.group("visit/details", "visit", "Details")
    details = section(
        columns(
            column(
                card(
                    heading(s.text("visit.details.heading", "Reservations", label="Reservations heading"), font_size="x-large"),
                    label_value("Phone", biz_phone),
                    label_value("Email", biz_email),
                    label_value("Address", biz_address),
                    bg="surface",
                ),
            ),
            column(
                heading(s.text("visit.hours.heading", "Opening hours", label="Hours heading")),
                paragraph(biz_hours, font_size="large"),
                heading(s.text("visit.directions.heading", "Getting here", label="Directions heading"), 3),
                paragraph(s.rich("visit.directions.body", label="Directions", default=
                                 "We are on Harbor Lane, two minutes from the ferry. <strong>Street parking</strong> is free after 6pm.")),
            ),
            gap="var:preset|spacing|70",
        ),
    )
    pages["visit"] = "\n\n".join([intro, details])

    return {
        "pages": [
            {"slug": "home", "title": "Home", "is_front_page": True, "markup": pages["home"]},
            {"slug": "menu", "title": "Menu", "markup": pages["menu"]},
            {"slug": "about", "title": "About", "markup": pages["about"]},
            {"slug": "private-dining", "title": "Private Dining", "markup": pages["private-dining"]},
            {"slug": "visit", "title": "Visit", "markup": pages["visit"]},
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
