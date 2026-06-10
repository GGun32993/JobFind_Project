from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from reportlab.lib.pagesizes import A4, landscape
from reportlab.pdfgen import canvas


OUT_PDF = Path("docs/JobFind_ER_Chen_A4.pdf")
OUT_PNG = Path("docs/JobFind_ER_Chen_A4_preview.png")

PAGE_W, PAGE_H = landscape(A4)
SCALE = 2.8
IMG_W, IMG_H = int(PAGE_W * SCALE), int(PAGE_H * SCALE)


def font(size, bold=False):
    candidates = [
        Path("C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf"),
        Path("C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf"),
    ]
    for path in candidates:
        if path.exists():
            return ImageFont.truetype(str(path), int(size * SCALE))
    return ImageFont.load_default()


ENTITY_FONT = font(7.0, True)
ATTR_FONT = font(5.7)
REL_FONT = font(5.7)
CARD_FONT = font(5.1, True)


def p(x, y):
    return int(round(x * SCALE)), int(round((PAGE_H - y) * SCALE))


def sx(v):
    return int(round(v * SCALE))


def text_size(draw, text, fnt):
    box = draw.textbbox((0, 0), text, font=fnt)
    return box[2] - box[0], box[3] - box[1]


def center_text(draw, x, y, text, fnt, max_w=None, underline=None):
    chosen = fnt
    label = text
    if max_w:
        while text_size(draw, label, chosen)[0] > sx(max_w) and chosen.size > int(4 * SCALE):
            chosen = font((chosen.size / SCALE) - 0.25, chosen.path.endswith("arialbd.ttf") if hasattr(chosen, "path") else False)
        while text_size(draw, label, chosen)[0] > sx(max_w) and len(label) > 4:
            label = label[:-4] + "..."

    tx, ty = p(x, y)
    tw, th = text_size(draw, label, chosen)
    draw.text((tx - tw / 2, ty - th / 2), label, font=chosen, fill="black")
    if underline:
        yy = ty + th / 2 + sx(1)
        x1 = tx - tw / 2
        x2 = tx + tw / 2
        if underline == "dashed":
            dash = sx(2.2)
            gap = sx(1.5)
            pos = x1
            while pos < x2:
                draw.line((pos, yy, min(pos + dash, x2), yy), fill="black", width=sx(0.45))
                pos += dash + gap
        else:
            draw.line((x1, yy, x2, yy), fill="black", width=sx(0.45))


def line(draw, a, b, label=None, t=0.5, offset=(0, 0)):
    ax, ay = p(*a)
    bx, by = p(*b)
    draw.line((ax, ay, bx, by), fill="black", width=sx(0.45))
    if label:
        lx = a[0] + (b[0] - a[0]) * t + offset[0]
        ly = a[1] + (b[1] - a[1]) * t + offset[1]
        px, py = p(lx, ly)
        draw.rectangle((px - sx(9), py - sx(5), px + sx(9), py + sx(5)), fill="white")
        center_text(draw, lx, ly, label, CARD_FONT, 17)


def entity(draw, x, y, w, h, label):
    x1, y1 = p(x - w / 2, y + h / 2)
    x2, y2 = p(x + w / 2, y - h / 2)
    draw.rectangle((x1, y1, x2, y2), outline="black", fill="white", width=sx(0.85))
    center_text(draw, x, y, label, ENTITY_FONT, w - 4)


def attribute(draw, x, y, label, key=None, w=None, h=18):
    if w is None:
        temp = Image.new("RGB", (1, 1), "white")
        temp_draw = ImageDraw.Draw(temp)
        w = max(34, (text_size(temp_draw, label, ATTR_FONT)[0] / SCALE) + 12)

    x1, y1 = p(x - w / 2, y + h / 2)
    x2, y2 = p(x + w / 2, y - h / 2)
    draw.ellipse((x1, y1, x2, y2), outline="black", fill="white", width=sx(0.65))
    center_text(draw, x, y, label, ATTR_FONT, w - 5, underline=key)


def relation(draw, x, y, w, h, label):
    pts = [p(x, y + h / 2), p(x + w / 2, y), p(x, y - h / 2), p(x - w / 2, y)]
    draw.polygon(pts, outline="black", fill="white")
    draw.line(pts + [pts[0]], fill="black", width=sx(0.75))
    center_text(draw, x, y, label, REL_FONT, w - 8)


def build_image():
    img = Image.new("RGB", (IMG_W, IMG_H), "white")
    draw = ImageDraw.Draw(img)

    # Layout intentionally mirrors the provided sample: one central entity,
    # surrounding entities, ovals as attributes, diamonds as relationships.
    entities = {
        "Users": (421, 318, 56, 24),
        "Freelancer_Profile": (178, 430, 92, 24),
        "Employer_Profile": (650, 430, 92, 24),
        "Job": (664, 287, 58, 24),
        "Job_Application": (421, 218, 92, 24),
        "Resume": (178, 218, 60, 24),
        "Freelancer_Review": (258, 84, 92, 24),
        "Employer_Review": (584, 84, 92, 24),
        "Categories": (752, 528, 70, 24),
    }

    relations = {
        "HasF": (300, 382, 42, 24, "Has"),
        "HasE": (535, 382, 42, 24, "Has"),
        "Posts": (542, 308, 48, 25, "Posts"),
        "Uploads": (292, 205, 54, 25, "Uploads"),
        "Submits": (421, 268, 58, 26, "Submits"),
        "For": (542, 238, 42, 25, "For"),
        "WritesFR": (342, 158, 54, 25, "Writes"),
        "ReceivesFR": (250, 158, 58, 25, "Receives"),
        "WritesER": (500, 158, 54, 25, "Writes"),
        "ReceivesER": (592, 158, 58, 25, "Receives"),
        "Classified": (760, 460, 64, 26, "Classified"),
    }

    edges = [
        ("Users", "HasF", "1", "1", "Freelancer_Profile"),
        ("Users", "HasE", "1", "1", "Employer_Profile"),
        ("Users", "Posts", "1", "N", "Job"),
        ("Users", "Uploads", "1", "N", "Resume"),
        ("Users", "Submits", "1", "N", "Job_Application"),
        ("Job", "For", "1", "N", "Job_Application"),
        ("Users", "WritesFR", "1", "N", "Freelancer_Review"),
        ("Users", "ReceivesFR", "1", "N", "Freelancer_Review"),
        ("Users", "WritesER", "1", "N", "Employer_Review"),
        ("Users", "ReceivesER", "1", "N", "Employer_Review"),
        ("Categories", "Classified", "1", "N", "Job"),
    ]

    for src, rel, src_card, dst_card, dst in edges:
        sx0, sy0 = entities[src][:2]
        rx0, ry0 = relations[rel][:2]
        dx0, dy0 = entities[dst][:2]
        line(draw, (sx0, sy0), (rx0, ry0), src_card, 0.66)
        line(draw, (rx0, ry0), (dx0, dy0), dst_card, 0.34)

    attrs = {
        "Users": [
            ("user_id", 421, 395, "solid"), ("username", 352, 374), ("email", 328, 318),
            ("password", 360, 266), ("fullname", 488, 374), ("role", 508, 276),
        ],
        "Freelancer_Profile": [
            ("freelancer_id", 78, 472, "solid"), ("user_id", 109, 430, "dashed"), ("skill", 176, 488),
            ("experience", 242, 472), ("location", 92, 388), ("rating", 210, 388),
        ],
        "Employer_Profile": [
            ("employer_id", 804, 430, "solid"), ("user_id", 725, 414, "dashed"), ("employer_name", 650, 488),
            ("description", 586, 472), ("like_count", 618, 388),
        ],
        "Categories": [
            ("category_id", 676, 560, "solid"), ("name", 752, 572), ("icon", 816, 560),
            ("description", 812, 503),
        ],
        "Job": [
            ("job_id", 598, 342, "solid"), ("employer_id", 610, 316, "dashed"), ("title", 733, 342),
            ("description", 762, 287), ("salary", 608, 252), ("deadline", 664, 220),
            ("status", 734, 244), ("category", 812, 384),
        ],
        "Job_Application": [
            ("application_id", 332, 230, "solid"), ("job_id", 390, 182, "dashed"),
            ("freelancer_id", 478, 182, "dashed"), ("apply_date", 512, 230),
            ("status", 421, 148),
        ],
        "Resume": [
            ("resume_id", 84, 256, "solid"), ("freelancer_id", 178, 274, "dashed"),
            ("file_name", 90, 182), ("upload_date", 206, 182),
        ],
        "Freelancer_Review": [
            ("review_id", 170, 112, "solid"), ("job_id", 224, 130, "dashed"),
            ("rating", 258, 30), ("comment", 336, 48),
        ],
        "Employer_Review": [
            ("review_id", 492, 112, "solid"), ("job_id", 548, 130, "dashed"),
            ("rating", 584, 30), ("comment", 666, 48),
        ],
    }

    for entity_name, items in attrs.items():
        ex, ey = entities[entity_name][:2]
        for item in items:
            _, ax, ay = item[:3]
            line(draw, (ex, ey), (ax, ay))

    for x, y, w, h, label in relations.values():
        relation(draw, x, y, w, h, label)

    for name, (x, y, w, h) in entities.items():
        entity(draw, x, y, w, h, name)

    for items in attrs.values():
        for item in items:
            label, x, y = item[:3]
            key = item[3] if len(item) > 3 else None
            attribute(draw, x, y, label, key=key)

    return img


def save_pdf(img):
    temp_png = str(OUT_PNG)
    img.save(temp_png)
    c = canvas.Canvas(str(OUT_PDF), pagesize=landscape(A4))
    c.drawImage(temp_png, 0, 0, width=PAGE_W, height=PAGE_H)
    c.showPage()
    c.save()


if __name__ == "__main__":
    image = build_image()
    save_pdf(image)
