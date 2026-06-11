from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas


OUT_DIR = Path("docs/Activity Diagram")
OUT_PDF = OUT_DIR / "Login_Activity_Diagram.pdf"

PAGE_W, PAGE_H = landscape(A4)
SCALE = 3
IMG_W, IMG_H = int(PAGE_W * SCALE), int(PAGE_H * SCALE)


def load_font(size, bold=False):
    candidates = [
        Path("C:/Windows/Fonts/tahomabd.ttf" if bold else "C:/Windows/Fonts/tahoma.ttf"),
        Path("C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf"),
    ]
    for path in candidates:
        if path.exists():
            return ImageFont.truetype(str(path), int(size * SCALE))
    return ImageFont.load_default()


TITLE_FONT = load_font(12, True)
HEADER_FONT = load_font(8.5, True)
TEXT_FONT = load_font(7.1, True)
LABEL_FONT = load_font(6.5, True)


def sx(value):
    return int(round(value * SCALE))


def xy(x, y):
    return int(round(x * SCALE)), int(round(y * SCALE))


def text_size(draw, value, font):
    box = draw.textbbox((0, 0), value, font=font)
    return box[2] - box[0], box[3] - box[1]


def centered_text(draw, x, y, value, font=TEXT_FONT):
    tw, th = text_size(draw, value, font)
    draw.text((sx(x) - tw / 2, sx(y) - th / 2), value, fill="black", font=font)


def line(draw, points, width=0.8):
    scaled = [xy(x, y) for x, y in points]
    draw.line(scaled, fill="black", width=sx(width), joint="curve")


def arrowhead(draw, x, y, direction):
    size = 6
    if direction == "right":
        pts = [xy(x, y), xy(x - size, y - size / 2), xy(x - size, y + size / 2)]
    elif direction == "left":
        pts = [xy(x, y), xy(x + size, y - size / 2), xy(x + size, y + size / 2)]
    elif direction == "down":
        pts = [xy(x, y), xy(x - size / 2, y - size), xy(x + size / 2, y - size)]
    else:
        pts = [xy(x, y), xy(x - size / 2, y + size), xy(x + size / 2, y + size)]
    draw.polygon(pts, fill="black")


def connector(draw, points, label=None, label_xy=None):
    line(draw, points)
    x1, y1 = points[-2]
    x2, y2 = points[-1]
    if abs(x2 - x1) > abs(y2 - y1):
        direction = "right" if x2 > x1 else "left"
    else:
        direction = "down" if y2 > y1 else "up"
    arrowhead(draw, x2, y2, direction)
    if label and label_xy:
        lx, ly = label_xy
        tw, th = text_size(draw, label, LABEL_FONT)
        draw.rectangle(
            (sx(lx) - tw / 2 - sx(3), sx(ly) - th / 2 - sx(2), sx(lx) + tw / 2 + sx(3), sx(ly) + th / 2 + sx(2)),
            fill="white",
        )
        centered_text(draw, lx, ly, label, LABEL_FONT)


def rounded_action(draw, x, y, w, h, value):
    left, top = x - w / 2, y - h / 2
    draw.rounded_rectangle((*xy(left, top), *xy(left + w, top + h)), radius=sx(10), outline="black", fill="white", width=sx(1.0))
    centered_text(draw, x, y, value)


def decision(draw, x, y, w, h, value):
    pts = [xy(x, y - h / 2), xy(x + w / 2, y), xy(x, y + h / 2), xy(x - w / 2, y)]
    draw.polygon(pts, outline="black", fill="white")
    centered_text(draw, x, y - 3, value)


def start_node(draw, x, y):
    draw.ellipse((*xy(x - 9, y - 9), *xy(x + 9, y + 9)), fill="black", outline="black")


def final_node(draw, x, y):
    draw.ellipse((*xy(x - 11, y - 11), *xy(x + 11, y + 11)), fill="white", outline="black", width=sx(1.1))
    draw.ellipse((*xy(x - 7, y - 7), *xy(x + 7, y + 7)), fill="black", outline="black")


def swimlanes(draw):
    x0, y0, w, h = 35, 42, 772, 545
    header_h = 34
    lanes = [
        ("User", x0, 235),
        ("เข้าสู่ระบบ : Freelance Matching Online", x0 + 235, 300),
        ("Users : Database", x0 + 535, 237),
    ]
    draw.rectangle((*xy(x0, y0), *xy(x0 + w, y0 + h)), outline="black", width=sx(1.0))
    draw.line((*xy(x0, y0 + header_h), *xy(x0 + w, y0 + header_h)), fill="black", width=sx(1.0))
    for title, x, lane_w in lanes:
        if x != x0:
            draw.line((*xy(x, y0), *xy(x, y0 + h)), fill="black", width=sx(1.0))
        centered_text(draw, x + lane_w / 2, y0 + header_h / 2, title, HEADER_FONT)


def build_image():
    img = Image.new("RGB", (IMG_W, IMG_H), "white")
    draw = ImageDraw.Draw(img)

    centered_text(draw, PAGE_W / 2, 22, "Activity Diagram: ระบบเข้าสู่ระบบ", TITLE_FONT)
    swimlanes(draw)

    user_x = 152
    system_x = 420
    db_x = 688

    start_node(draw, user_x, 92)
    rounded_action(draw, user_x, 128, 122, 34, "คลิก เข้าสู่ระบบ")
    rounded_action(draw, system_x, 128, 142, 34, "แสดงหน้าเข้าสู่ระบบ")
    rounded_action(draw, user_x, 192, 150, 34, "กรอกอีเมลและรหัสผ่าน")
    rounded_action(draw, user_x, 252, 128, 34, "กดปุ่มเข้าสู่ระบบ")
    rounded_action(draw, system_x, 252, 126, 34, "ตรวจสอบข้อมูล")
    rounded_action(draw, db_x, 252, 156, 34, "ค้นหาบัญชีจากอีเมล")
    rounded_action(draw, system_x, 322, 170, 36, "ตรวจรหัสผ่านและ role")
    decision(draw, system_x, 392, 110, 60, "ข้อมูลถูกต้อง")
    rounded_action(draw, system_x, 475, 150, 34, "บันทึก Session ผู้ใช้")
    rounded_action(draw, system_x, 527, 190, 34, "นำทางไป Dashboard ตาม role")
    final_node(draw, system_x, 572)

    connector(draw, [(user_x, 101), (user_x, 111)])
    connector(draw, [(user_x + 61, 128), (system_x - 71, 128)])
    connector(draw, [(system_x, 145), (system_x, 165), (user_x, 165), (user_x, 175)])
    connector(draw, [(user_x, 209), (user_x, 235)])
    connector(draw, [(user_x + 64, 252), (system_x - 63, 252)])
    connector(draw, [(system_x + 63, 252), (db_x - 78, 252)])
    connector(draw, [(db_x, 269), (db_x, 286), (system_x, 286), (system_x, 304)])
    connector(draw, [(system_x, 340), (system_x, 362)])
    connector(draw, [(system_x, 422), (system_x, 458)], "ถูกต้อง", (449, 441))
    connector(draw, [(system_x, 492), (system_x, 510)])
    connector(draw, [(system_x, 544), (system_x, 561)])

    connector(
        draw,
        [(system_x - 55, 392), (60, 392), (60, 192), (user_x - 75, 192)],
        "ไม่ถูกต้อง",
        (225, 382),
    )

    return img


def save_pdf(img):
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    c = canvas.Canvas(str(OUT_PDF), pagesize=landscape(A4))
    c.drawImage(ImageReader(img), 0, 0, width=PAGE_W, height=PAGE_H)
    c.showPage()
    c.save()


if __name__ == "__main__":
    image = build_image()
    save_pdf(image)
    print(OUT_PDF)
