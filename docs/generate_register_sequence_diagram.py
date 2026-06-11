from datetime import datetime
from pathlib import Path
from xml.etree.ElementTree import Element, SubElement, ElementTree

from PIL import Image, ImageDraw, ImageFont
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas


OUT_DIR = Path("docs/Sequence Diagram")
OUT_PDF = OUT_DIR / "Register_Sequence_Diagram.pdf"

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
BOX_FONT = load_font(8.5, True)
MSG_FONT = load_font(7.2, True)
NOTE_FONT = load_font(6.8, True)


def sx(value):
    return int(round(value * SCALE))


def xy(x, y):
    return int(round(x * SCALE)), int(round(y * SCALE))


def draw_text_center(draw, x, y, text, font, fill="black"):
    box = draw.textbbox((0, 0), text, font=font)
    tw, th = box[2] - box[0], box[3] - box[1]
    draw.text((sx(x) - tw / 2, sx(y) - th / 2), text, font=font, fill=fill)


def draw_text_left(draw, x, y, text, font, fill="black"):
    draw.text((sx(x), sx(y)), text, font=font, fill=fill)


def draw_line(draw, p1, p2, width=0.8, fill="black"):
    draw.line((*xy(*p1), *xy(*p2)), fill=fill, width=sx(width))


def draw_dashed_line(draw, p1, p2, dash=4, gap=4, width=0.8, fill="black"):
    x1, y1 = p1
    x2, y2 = p2
    if x1 == x2:
        step = dash + gap
        current = min(y1, y2)
        end = max(y1, y2)
        while current < end:
            draw_line(draw, (x1, current), (x2, min(current + dash, end)), width, fill)
            current += step
    elif y1 == y2:
        step = dash + gap
        current = min(x1, x2)
        end = max(x1, x2)
        while current < end:
            draw_line(draw, (current, y1), (min(current + dash, end), y2), width, fill)
            current += step


def arrowhead(draw, x, y, direction="right", fill="black"):
    size = 5
    if direction == "right":
        points = [xy(x, y), xy(x - size, y - size / 2), xy(x - size, y + size / 2)]
    elif direction == "left":
        points = [xy(x, y), xy(x + size, y - size / 2), xy(x + size, y + size / 2)]
    else:
        points = [xy(x, y), xy(x - size / 2, y - size), xy(x + size / 2, y - size)]
    draw.polygon(points, fill=fill)


def message(draw, x1, x2, y, label, dashed=False):
    start, end = (x1, y), (x2, y)
    direction = "right" if x2 > x1 else "left"
    if dashed:
        draw_dashed_line(draw, start, end, dash=3, gap=3, width=0.65)
    else:
        draw_line(draw, start, end, width=0.75)
    arrowhead(draw, x2, y, direction=direction)
    draw_text_center(draw, (x1 + x2) / 2, y - 12, label, MSG_FONT)


def self_message(draw, x, y, label):
    right = x + 42
    draw_line(draw, (x, y), (right, y), width=0.75)
    draw_line(draw, (right, y), (right, y + 26), width=0.75)
    draw_line(draw, (right, y + 26), (x, y + 26), width=0.75)
    arrowhead(draw, x, y + 26, direction="left")
    draw_text_left(draw, right + 8, y + 7, label, MSG_FONT)


def participant(draw, x, y, w, h, label):
    left, top = x - w / 2, y
    draw.rectangle((*xy(left, top), *xy(left + w, top + h)), outline="black", fill="white", width=sx(0.9))
    draw_text_center(draw, x, y + h / 2, label, BOX_FONT)


def activation(draw, x, y1, y2, w=12, fill="#c7c7c7"):
    draw.rectangle((*xy(x - w / 2, y1), *xy(x + w / 2, y2)), fill=fill)


def alt_fragment(draw, x, y, w, h, title, cond1, cond2, divider_y):
    draw.rectangle((*xy(x, y), *xy(x + w, y + h)), outline="black", width=sx(1.0))
    tab_w, tab_h = 130, 26
    poly = [xy(x, y), xy(x + tab_w, y), xy(x + tab_w, y + tab_h - 10), xy(x + tab_w - 12, y + tab_h), xy(x, y + tab_h)]
    draw.polygon(poly, outline="black", fill="white")
    draw.line((xy(x, y)[0], xy(y, y)[1], xy(x, y)[0], xy(x, y + tab_h)[1]), fill="black", width=sx(1))
    draw_text_left(draw, x + 8, y + 7, title, NOTE_FONT)
    draw_text_left(draw, x + 22, y + 24, cond1, NOTE_FONT)
    draw_dashed_line(draw, (x, divider_y), (x + w, divider_y), dash=3, gap=3, width=0.6)
    draw_text_left(draw, x + 22, divider_y + 12, cond2, NOTE_FONT)


def build_png():
    img = Image.new("RGB", (IMG_W, IMG_H), "white")
    draw = ImageDraw.Draw(img)

    lifeline_top = 96
    lifeline_bottom = 582
    xs = {
        "user": 95,
        "register": 305,
        "users_db": 530,
        "profile_db": 735,
    }

    draw_text_center(draw, PAGE_W / 2, 24, "Sequence Diagram: ระบบสมัครสมาชิก", TITLE_FONT)

    participant(draw, xs["user"], 48, 150, 38, ": User")
    participant(draw, xs["register"], 48, 180, 38, "สมัครสมาชิก : Job_Find")
    participant(draw, xs["users_db"], 48, 155, 38, "Users : Database")
    participant(draw, xs["profile_db"], 48, 170, 38, "Profile : Database")

    for x in xs.values():
        draw_dashed_line(draw, (x, lifeline_top), (x, lifeline_bottom), dash=2, gap=3, width=0.6)

    activation(draw, xs["user"], 112, 578, w=14)
    activation(draw, xs["register"], 112, 565, w=14)
    activation(draw, xs["users_db"], 304, 345, w=14)
    activation(draw, xs["users_db"], 452, 500, w=14)
    activation(draw, xs["profile_db"], 508, 548, w=14)

    ux, rx, udx, pdx = xs["user"] + 7, xs["register"] - 7, xs["users_db"] - 7, xs["profile_db"] - 7
    r_right = xs["register"] + 7
    db_user_left = xs["users_db"] - 7
    db_profile_left = xs["profile_db"] - 7

    message(draw, ux, rx, 125, "คลิก สมัครสมาชิก")
    message(draw, rx, ux, 158, "แสดงหน้าสมัครสมาชิก", dashed=True)
    message(draw, ux, rx, 196, "กรอกข้อมูลสมัครสมาชิก")
    message(draw, ux, rx, 232, "กดปุ่มสมัครสมาชิก")
    self_message(draw, r_right, 260, "ตรวจสอบข้อมูล")
    activation(draw, xs["register"] + 18, 264, 292, w=10, fill="#8a8a8a")
    message(draw, r_right, db_user_left, 318, "ตรวจ username/email ซ้ำ")
    message(draw, db_user_left, r_right, 346, "ส่งผลการตรวจสอบ", dashed=True)

    alt_fragment(
        draw,
        38,
        374,
        765,
        156,
        "alt : ตรวจสอบบัญชี",
        "[ข้อมูลไม่ถูกต้อง หรือ username/email ซ้ำ]",
        "[ข้อมูลถูกต้อง]",
        452,
    )
    message(draw, rx, ux, 432, "แสดงข้อความแจ้งข้อผิดพลาด", dashed=True)
    message(draw, r_right, db_user_left, 486, "บันทึกข้อมูลบัญชีผู้ใช้")
    message(draw, r_right, db_profile_left, 516, "สร้างโปรไฟล์ตามบทบาท")
    message(draw, db_profile_left, r_right, 542, "บันทึกสำเร็จ", dashed=True)
    message(draw, rx, ux, 568, "สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ", dashed=True)

    return img


def save_pdf(img):
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    c = canvas.Canvas(str(OUT_PDF), pagesize=landscape(A4))
    c.drawImage(ImageReader(img), 0, 0, width=PAGE_W, height=PAGE_H)
    c.showPage()
    c.save()


def mx_cell(root, attrs):
    return SubElement(root, "mxCell", attrs)


def geom(cell, x=None, y=None, w=None, h=None, relative=None):
    attrs = {"as": "geometry"}
    if x is not None:
        attrs["x"] = str(round(x, 2))
    if y is not None:
        attrs["y"] = str(round(y, 2))
    if w is not None:
        attrs["width"] = str(round(w, 2))
    if h is not None:
        attrs["height"] = str(round(h, 2))
    if relative is not None:
        attrs["relative"] = str(relative)
    return SubElement(cell, "mxGeometry", attrs)


def add_box(root, id_, x, y, w, h, value, bold=False):
    style = "rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#000000;fontSize=12;align=center;verticalAlign=middle;"
    if bold:
        value = f"<b>{value}</b>"
    cell = mx_cell(root, {"id": id_, "value": value, "style": style, "vertex": "1", "parent": "1"})
    geom(cell, x, y, w, h)


def add_text(root, id_, x, y, w, h, value):
    style = "text;html=1;strokeColor=none;fillColor=#ffffff;fontSize=10;align=center;verticalAlign=middle;whiteSpace=wrap;"
    cell = mx_cell(root, {"id": id_, "value": value, "style": style, "vertex": "1", "parent": "1"})
    geom(cell, x, y, w, h)


def add_rect(root, id_, x, y, w, h, fill="#c7c7c7"):
    style = f"rounded=0;whiteSpace=wrap;html=1;fillColor={fill};strokeColor=none;"
    cell = mx_cell(root, {"id": id_, "value": "", "style": style, "vertex": "1", "parent": "1"})
    geom(cell, x, y, w, h)


def add_edge(root, id_, points, value="", dashed=False, arrow=True):
    style = "edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;strokeColor=#000000;strokeWidth=1;"
    style += "dashed=1;dashPattern=3 3;" if dashed else ""
    style += "endArrow=block;endFill=1;" if arrow else "endArrow=none;"
    cell = mx_cell(root, {"id": id_, "value": value, "style": style, "edge": "1", "parent": "1"})
    g = geom(cell, relative=1)
    sx_, sy_ = points[0]
    tx_, ty_ = points[-1]
    SubElement(g, "mxPoint", {"x": str(sx_), "y": str(sy_), "as": "sourcePoint"})
    SubElement(g, "mxPoint", {"x": str(tx_), "y": str(ty_), "as": "targetPoint"})
    middle = points[1:-1]
    if middle:
        arr = SubElement(g, "Array", {"as": "points"})
        for px, py in middle:
            SubElement(arr, "mxPoint", {"x": str(px), "y": str(py)})


def build_drawio():
    mxfile = Element(
        "mxfile",
        {
            "host": "app.diagrams.net",
            "modified": datetime.now().isoformat(timespec="seconds"),
            "agent": "Codex",
            "version": "24.7.17",
            "type": "device",
        },
    )
    diagram = SubElement(mxfile, "diagram", {"id": "register-sequence", "name": "Register"})
    model = SubElement(
        diagram,
        "mxGraphModel",
        {
            "dx": "1200",
            "dy": "850",
            "grid": "1",
            "gridSize": "10",
            "guides": "1",
            "tooltips": "1",
            "connect": "1",
            "arrows": "1",
            "fold": "1",
            "page": "1",
            "pageScale": "1",
            "pageWidth": str(int(PAGE_W)),
            "pageHeight": str(int(PAGE_H)),
            "math": "0",
            "shadow": "0",
        },
    )
    root = SubElement(model, "root")
    mx_cell(root, {"id": "0"})
    mx_cell(root, {"id": "1", "parent": "0"})

    add_text(root, "title", 285, 12, 280, 24, "<b>Sequence Diagram: ระบบสมัครสมาชิก</b>")
    participants = {
        "user": (20, 48, 150, 38, ": User", 95),
        "register": (215, 48, 180, 38, "สมัครสมาชิก : Job_Find", 305),
        "users": (452, 48, 155, 38, "Users : Database", 530),
        "profile": (650, 48, 170, 38, "Profile : Database", 735),
    }
    for id_, (x, y, w, h, label, cx) in participants.items():
        add_box(root, f"box_{id_}", x, y, w, h, label, bold=True)
        add_edge(root, f"life_{id_}", [(cx, 96), (cx, 582)], dashed=True, arrow=False)

    add_rect(root, "act_user", 88, 112, 14, 466)
    add_rect(root, "act_register", 298, 112, 14, 453)
    add_rect(root, "act_users_1", 523, 304, 14, 41)
    add_rect(root, "act_users_2", 523, 452, 14, 48)
    add_rect(root, "act_profile", 728, 508, 14, 40)
    add_rect(root, "act_validate", 318, 264, 10, 28, fill="#8a8a8a")

    messages = [
        ("m1", [(102, 125), (298, 125)], "คลิก สมัครสมาชิก", False),
        ("m2", [(298, 158), (102, 158)], "แสดงหน้าสมัครสมาชิก", True),
        ("m3", [(102, 196), (298, 196)], "กรอกข้อมูลสมัครสมาชิก", False),
        ("m4", [(102, 232), (298, 232)], "กดปุ่มสมัครสมาชิก", False),
        ("m6", [(312, 318), (523, 318)], "ตรวจ username/email ซ้ำ", False),
        ("m7", [(523, 346), (312, 346)], "ส่งผลการตรวจสอบ", True),
        ("m8", [(298, 432), (102, 432)], "แสดงข้อความแจ้งข้อผิดพลาด", True),
        ("m9", [(312, 486), (523, 486)], "บันทึกข้อมูลบัญชีผู้ใช้", False),
        ("m10", [(312, 516), (728, 516)], "สร้างโปรไฟล์ตามบทบาท", False),
        ("m11", [(728, 542), (312, 542)], "บันทึกสำเร็จ", True),
        ("m12", [(298, 568), (102, 568)], "สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ", True),
    ]
    for id_, points, value, dashed in messages:
        add_edge(root, id_, points, value=value, dashed=dashed)

    add_edge(root, "self_1", [(312, 260), (355, 260), (355, 286), (312, 286)], "ตรวจสอบข้อมูล")

    add_box(root, "alt_box", 38, 374, 765, 156, "", bold=False)
    add_box(root, "alt_label", 38, 374, 130, 26, "alt : ตรวจสอบบัญชี", bold=True)
    add_text(root, "cond_1", 58, 398, 220, 20, "[ข้อมูลไม่ถูกต้อง หรือ username/email ซ้ำ]")
    add_edge(root, "alt_divider", [(38, 452), (803, 452)], dashed=True, arrow=False)
    add_text(root, "cond_2", 58, 466, 120, 20, "[ข้อมูลถูกต้อง]")

    return mxfile


def save_drawio():
    ElementTree(build_drawio()).write(OUT_DRAWIO, encoding="utf-8", xml_declaration=True)


if __name__ == "__main__":
    image = build_png()
    save_pdf(image)
    print(OUT_PDF)
