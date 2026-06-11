from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas


OUT_DIR = Path("docs/Activity Diagram")
OUT_PDF = OUT_DIR / "Register_Activity_Diagram.pdf"

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
HEADER_FONT = load_font(8.6, True)
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
    draw.line([xy(x, y) for x, y in points], fill="black", width=sx(width), joint="curve")


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
    x0, y0, w, h = 35, 42, 772, 535
    header_h = 34
    lanes = [
        ("ผู้ว่าจ้าง, ผู้สมัคร", x0, 235),
        ("สมัครสมาชิก : Freelance Matching Online", x0 + 235, 300),
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

    centered_text(draw, PAGE_W / 2, 22, "Activity Diagram: ระบบสมัครสมาชิก", TITLE_FONT)
    swimlanes(draw)

    user_x = 152
    system_x = 420
    db_x = 688

    start_node(draw, user_x, 92)
    rounded_action(draw, user_x, 132, 155, 34, "กดปุ่ม เริ่มต้นใช้งาน")
    rounded_action(draw, system_x, 132, 160, 34, "แสดงหน้าจอ สมัครสมาชิก")
    rounded_action(draw, user_x, 205, 150, 34, "กรอกข้อมูลการสมัคร")
    rounded_action(draw, user_x, 275, 130, 34, "กดปุ่มส่งข้อมูล")
    rounded_action(draw, system_x, 275, 130, 34, "ตรวจสอบข้อมูล")
    decision(draw, system_x, 355, 118, 62, "บัญชีผู้ใช้ซ้ำหรือไม่")
    rounded_action(draw, user_x, 405, 155, 34, "กรอกข้อมูลผู้ใช้ใหม่")
    rounded_action(draw, system_x, 430, 150, 34, "ส่งข้อมูลบัญชีผู้ใช้")
    rounded_action(draw, db_x, 430, 160, 34, "บันทึกข้อมูลบัญชีผู้ใช้")
    rounded_action(draw, system_x, 505, 205, 36, "แสดงหน้าจอล็อกอิน เพื่อเข้าสู่ระบบ")
    final_node(draw, system_x, 555)

    connector(draw, [(user_x, 101), (user_x, 115)])
    connector(draw, [(user_x + 78, 132), (system_x - 80, 132)])
    connector(draw, [(system_x, 149), (system_x, 176), (user_x, 176), (user_x, 188)])
    connector(draw, [(user_x, 222), (user_x, 258)])
    connector(draw, [(user_x + 65, 275), (system_x - 65, 275)])
    connector(draw, [(system_x, 292), (system_x, 324)])

    connector(draw, [(system_x - 59, 355), (user_x, 355), (user_x, 388)], "ซ้ำ", (274, 344))
    connector(draw, [(user_x + 78, 405), (system_x - 65, 405), (system_x - 65, 292)])

    connector(draw, [(system_x, 386), (system_x, 413)], "ไม่ซ้ำ", (445, 400))
    connector(draw, [(system_x + 75, 430), (db_x - 80, 430)])
    connector(draw, [(db_x, 447), (db_x, 505), (system_x + 102, 505)])
    connector(draw, [(system_x, 523), (system_x, 544)])

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
