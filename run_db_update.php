<?php
// Set execution timeout to 120 seconds in case remote inserts take time
set_time_limit(120);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/password_helpers.php';
require_once __DIR__ . '/helpers/category_helpers.php';

if (!$conn) {
    die("<h3>เชื่อมต่อฐานข้อมูลล้มเหลว กรุณาตรวจสอบการตั้งค่าฐานข้อมูลบน Server</h3>");
}

mysqli_query($conn, "SET NAMES utf8mb4");

echo "<h2>เริ่มการอัปเดตฐานข้อมูลระบบสำหรับเซิร์ฟเวอร์ที่ Deploy...</h2>";

// 1. Clear previous seeded data to avoid duplication if run multiple times
$check_seed = mysqli_query($conn, "SELECT user_id FROM Users WHERE email LIKE '%@jobfind.com'");
if ($check_seed && mysqli_num_rows($check_seed) > 0) {
    echo "<p style='color:orange;'>กำลังเคลียร์ข้อมูลจำลองเดิมที่เคยสร้าง...</p>";
    $user_ids = [];
    while ($row = mysqli_fetch_assoc($check_seed)) {
        $user_ids[] = intval($row['user_id']);
    }
    $ids_str = implode(',', $user_ids);
    mysqli_query($conn, "DELETE FROM Job WHERE employer_id IN ($ids_str)");
    mysqli_query($conn, "DELETE FROM Employer_Profile WHERE user_id IN ($ids_str)");
    mysqli_query($conn, "DELETE FROM Users WHERE user_id IN ($ids_str)");
}

// 2. Seed 20 category groups and subcategories to ensure DB tables have them
ensure_category_schema($conn);
ensure_default_job_categories($conn);
echo "<p style='color:green;'>ตรวจสอบ/อัปเดตตารางหมวดหมู่หลักและหมวดหมู่ย่อยเรียบร้อยแล้ว</p>";

$mock_data = [
    1 => [
        'company' => 'สำนักงานกฎหมายสยามพาร์ทเนอร์ส (Siam Partners Law Office)',
        'title' => 'ทนายความ / ที่ปรึกษากฎหมายอิสระ (Corporate Contract Consultant)',
        'category' => 'กฎหมายและเอกสาร',
        'subcategory' => 'ที่ปรึกษากฎหมาย',
        'description' => 'ตรวจสอบและร่างสัญญาการจ้างงาน สัญญาความร่วมมือทางธุรกิจ (MOU) และเอกสารราชการต่างๆ สำหรับบริษัทสตาร์ทอัพทางด้านฟินเทค ให้คำแนะนำปรึกษาข้อกฎหมายคุ้มครองข้อมูลส่วนบุคคล (PDPA)',
        'salary' => 45000
    ],
    2 => [
        'company' => 'พิกเซล ครีเอทีฟ สตูดิโอ (Pixel Creative Studio)',
        'title' => 'รับออกแบบโลโก้และ CI แบรนด์อาหารเสริม (Logo & Brand Identity Design)',
        'category' => 'กราฟิกดีไซน์',
        'subcategory' => 'ออกแบบโลโก้',
        'description' => 'ออกแบบโลโก้และจัดทำคู่มืออัตลักษณ์แบรนด์ (Brand Identity / CI Manual) สำหรับผลิตภัณฑ์อาหารเสริมเพื่อสุขภาพสไตล์มินิมอล ต้องการงานด่วนภายใน 2 สัปดาห์ มีไกด์ไลน์ความต้องการให้ชัดเจน',
        'salary' => 15000
    ],
    3 => [
        'company' => 'ดิจิทัล บูสท์ เอเจนซี่ (Digital Boost Agency)',
        'title' => 'ผู้เชี่ยวชาญยิงแอด Facebook / TikTok แฟชั่น (Media Buyer / Ads Expert)',
        'category' => 'การตลาดและโฆษณา',
        'subcategory' => 'ยิงแอด Facebook',
        'description' => 'วางแผนการตลาดออนไลน์ ยิงโฆษณา และปรับปรุงผลลัพธ์ (Optimise Ads) สำหรับแบรนด์แฟชั่นเสื้อผ้าสตรี มีประสบการณ์บริหารจัดการงบโฆษณาเพจมากกว่า 50,000 บาทต่อเดือน จะพิจารณาเป็นพิเศษ',
        'salary' => 25000
    ],
    4 => [
        'company' => 'แอคคาวน์ติ้ง พลัส (Accounting Plus Co., Ltd.)',
        'title' => 'รับทำบัญชีรายเดือนและยื่นภาษี (Monthly Accounting & Tax Filing)',
        'category' => 'การเงินและบัญชี',
        'subcategory' => 'บัญชีรายเดือน',
        'description' => 'จัดทำบัญชีซื้อขาย จัดเก็บเอกสารรายรับรายจ่าย พร้อมบันทึกบัญชีลงโปรแกรมสำเร็จรูป จัดทำแบบยื่นภาษีเงินได้นิติบุคคลประจำเดือน (ภ.พ.30, ภ.ง.ด.1, ภ.ง.ด.3, ภ.ง.ด.53) ครบวงจร',
        'salary' => 12000
    ],
    5 => [
        'company' => 'สำนักพิมพ์เวิร์ดคราฟต์ (Wordcraft Publishing)',
        'title' => 'นักเขียนบทความ SEO สายไอทีและเทคโนโลยี (Tech SEO Content Writer)',
        'category' => 'การเขียนและแปลภาษา',
        'subcategory' => 'เขียนบทความ',
        'description' => 'เขียนบทความสร้างสรรค์เกี่ยวกับแนวโน้มเทคโนโลยี ซอฟต์แวร์ และการเขียนโปรแกรม ความยาว 1,200 - 1,500 คำต่อบทความ ตามคีย์เวิร์ดที่ได้รับมอบหมาย เพื่อดันอันดับการค้นหาบนหน้าแรก Google',
        'salary' => 8000
    ],
    6 => [
        'company' => 'เอ็ดดู สเปซ เรียนออนไลน์ (EduSpace Online Academy)',
        'title' => 'ติวเตอร์สอนวิชาคณิตศาสตร์ ม.ปลาย (Mathematics Online Tutor)',
        'category' => 'การศึกษาและติวเตอร์',
        'subcategory' => 'ติวเตอร์ออนไลน์',
        'description' => 'จัดทำเนื้อหาสอนสดออนไลน์ผ่านโปรแกรม Zoom สอนในรายวิชาคณิตศาสตร์เพิ่มเติมระดับชั้น ม.4 - ม.6 เน้นทักษะการทำข้อสอบเตรียมสอบเข้ามหาวิทยาลัย มีสไลด์และคู่มือการสอนให้เรียบร้อย',
        'salary' => 18000
    ],
    7 => [
        'company' => 'โก โลจิสติกส์ ประเทศไทย (Go Logistics Thailand)',
        'title' => 'เจ้าหน้าที่วางแผนเส้นทางการจัดส่ง (Delivery Route Planner)',
        'category' => 'ขนส่งและโลจิสติกส์',
        'subcategory' => 'วางแผนเส้นทาง',
        'description' => 'วิเคราะห์และจัดเส้นทางการขนส่งสินค้าประจำวันของรถบรรทุก 4 ล้อและ 6 ล้อ เพื่อช่วยลดระยะเวลาและประหยัดน้ำมันเชื้อเพลิง ประสานงานกับพนักงานขนส่งเพื่อระบุปัญหาหน้างานแบบ Real-time',
        'salary' => 22000
    ],
    8 => [
        'company' => 'บิวตี้แคร์ ออนไลน์ (BeautyCare Online Co., Ltd.)',
        'title' => 'แอดมินตอบแชทเพจและปิดการขาย (Page Admin / Sales Support)',
        'category' => 'งานขายและบริการลูกค้า',
        'subcategory' => 'แอดมินตอบแชต',
        'description' => 'ตอบข้อซักถามลูกค้าทางกล่องข้อความ Facebook และ Line Official Account แนะนำโปรโมชั่นและตอบคำถามเกี่ยวกับผลิตภัณฑ์ดูแลผิวหน้า ปิดยอดขาย คีย์สลิปโอนเงินเข้าระบบ และส่งสรุปยอดประจำวัน',
        'salary' => 15000
    ],
    9 => [
        'company' => 'โฮม เซอร์วิส โซลูชั่นส์ (Home Service Solutions)',
        'title' => 'ช่างไฟฟ้าและช่างประปาประจำโครงการ (On-site Electrician/Plumber)',
        'category' => 'งานช่างและซ่อมบำรุง',
        'subcategory' => 'ช่างไฟฟ้า',
        'description' => 'ปฏิบัติหน้าที่ตรวจสอบ บำรุงรักษา และซ่อมแซมระบบไฟฟ้า แสงสว่าง รวมถึงปั๊มน้ำและระบบประปาสุขาภิบาลของหมู่บ้านจัดสรร สัญญาจ้างชั่วคราวรายโครงการ ทำงานตามการเรียกของฝ่ายนิติบุคคล',
        'salary' => 28000
    ],
    10 => [
        'company' => 'ดาต้า เอ็นทรี เซอร์วิส (Data Entry Services Co., Ltd.)',
        'title' => 'พนักงานคีย์ข้อมูลสินค้าลงเว็บไซต์ (Data Entry Assistant)',
        'category' => 'งานธุรการและคีย์ข้อมูล',
        'subcategory' => 'คีย์ข้อมูล',
        'description' => 'นำเข้าข้อมูลคุณสมบัติ รูปภาพ และราคาสินค้าแฟชั่นลงระบบหลังบ้านของร้านค้าออนไลน์ (Shopify/Lazada/Shopee) คีย์ข้อมูลที่ถูกต้อง แม่นยำ และจัดหมวดหมู่สินค้าให้เป็นระเบียบเรียบร้อย',
        'salary' => 10000
    ],
    11 => [
        'company' => 'เอ็ม สตาร์ ออร์กาไนเซอร์ (M Star Organizer)',
        'title' => 'รับสมัคร Staff Event งานสัมมนาไอที (Event Staff / MC)',
        'category' => 'งานบริการและอีเวนต์',
        'subcategory' => 'Staff Event',
        'description' => 'รับสมัครพนักงานชั่วคราวช่วยดูแลความเรียบร้อยหน้างานสัมมนาไอทีนานาชาติ ประจำจุดลงทะเบียน ต้อนรับผู้เข้าร่วมงาน อำนวยความสะดวกในห้องสัมมนา ทำงาน 2 วัน (เสาร์-อาทิตย์)',
        'salary' => 4000
    ],
    12 => [
        'company' => 'สตูดิโอ ซาวด์เวฟ (Soundwave Audio Studio)',
        'title' => 'รับพากย์เสียงสปอตโฆษณาและวิดีโอ (Voice Over Artist)',
        'category' => 'ดนตรีและเสียง',
        'subcategory' => 'พากย์เสียง',
        'description' => 'บันทึกเสียงพากย์ภาษาไทย โทนอบอุ่น เป็นทางการปนเป็นกันเอง สำหรับใช้อัดสปอตโฆษณาในสื่อแอปพลิเคชันมือถือความยาว 30-45 วินาที จำเป็นต้องส่งตัวอย่างเสียง (Demo Voice) ก่อนเพื่อพิจารณา',
        'salary' => 5000
    ],
    13 => [
        'company' => 'วีดีโอคราฟท์ โปรดักชั่น (VideoCraft Production)',
        'title' => 'ตัดต่อคลิปสั้นลง TikTok / Reels แบรนด์อาหาร (Video Editor for Shorts/TikTok)',
        'category' => 'ตัดต่อวิดีโอและแอนิเมชัน',
        'subcategory' => 'ตัดต่อวิดีโอ',
        'description' => 'ตัดต่อและใส่เอฟเฟกต์ คำบรรยาย (Subtitle) รวมถึงเพลงประกอบที่ดึงดูดใจ สำหรับคลิปแนะนำวิธีการทำอาหารสั้นความยาวไม่เกิน 1 นาที ส่งงานสัปดาห์ละ 3 คลิป มีวัตถุดิบและสตอรี่บอร์ดให้',
        'salary' => 12000
    ],
    14 => [
        'company' => 'เน็กซ์เจน ดีเวลลอปเมนท์ (NextGen Software Development)',
        'title' => 'Full Stack Developer (Next.js & Node.js) พัฒนาเว็บแอป (Web App Developer)',
        'category' => 'เทคโนโลยีและซอฟต์แวร์',
        'subcategory' => 'Website Development',
        'description' => 'เขียนโค้ดพัฒนาหน้าเว็บส่วนติดต่อผู้ใช้งานด้วย React/Next.js และพัฒนา API ระบบจัดเก็บสินค้าหลังบ้านด้วย Node.js/Express ทำงานร่วมกับฐานข้อมูล MySQL มีทักษะการใช้งาน Git อย่างคล่องแคล่ว',
        'salary' => 65000
    ],
    15 => [
        'company' => 'เลนส์ คราฟเตอร์ สตูดิโอ (LensCrafter Photo Studio)',
        'title' => 'ช่างภาพถ่ายสินค้าแฟชั่นและ Lookbook (Product Fashion Photographer)',
        'category' => 'ถ่ายภาพและวิดีโอ',
        'subcategory' => 'ถ่ายภาพสินค้า',
        'description' => 'รับงานถ่ายภาพแฟชั่น Lookbook คอลเลกชันใหม่ในสตูดิโอ จัดไฟ จัดวางองค์ประกอบภาพ และปรับแต่งแสงเงาเบื้องต้น ส่งมอบไฟล์ภาพคุณภาพสูงจำนวนอย่างน้อย 50 ภาพสำหรับการใช้งานออนไลน์',
        'salary' => 20000
    ],
    16 => [
        'company' => 'โกลบอล บิสิเนส คอนซัลติ้ง (Global Business Consulting Group)',
        'title' => 'ที่ปรึกษาเขียนแผนธุรกิจสำหรับ SMEs ขอทุน (Business Plan Writer for SMEs)',
        'category' => 'บริหารธุรกิจและที่ปรึกษา',
        'subcategory' => 'Business Plan',
        'description' => 'จัดทำเล่มรายงานแผนธุรกิจ (Business Plan) วิเคราะห์ส่วนแบ่งทางการตลาด คู่แข่ง วางแผนการเงิน การคำนวณจุดคุ้มทุน เพื่อยื่นเสนอขอสินเชื่อธนาคารพาณิชย์ หรือยื่นขอรับเงินทุนสนับสนุนภาครัฐ',
        'salary' => 35000
    ],
    17 => [
        'company' => 'เวลเนส แอนด์ ฟิตเนส เซ็นเตอร์ (Wellness & Fitness Center)',
        'title' => 'เทรนเนอร์ฟิตเนสส่วนบุคคล / นักโภชนาการ (Personal Fitness Trainer)',
        'category' => 'สุขภาพและความงาม',
        'subcategory' => 'เทรนเนอร์ฟิตเนส',
        'description' => 'ออกแบบคอร์สและตารางออกกำลังกายรวมถึงตารางโภชนาการสำหรับลดน้ำหนักและเพิ่มมวลกล้ามเนื้อส่วนบุคคล ให้คำแนะนำการทำอาหารสุขภาพ ให้ความรู้ความเข้าใจเกี่ยวกับการดูแลรักษาสุขภาพทางไกล',
        'salary' => 15000
    ],
    18 => [
        'company' => 'อาร์คิเทค แอนด์ บิลเดอร์ (Architect & Builder Co., Ltd.)',
        'title' => 'เขียนแบบก่อสร้างและทำภาพ 3D Rendering (Draftsman & 3D Render)',
        'category' => 'สถาปัตยกรรมและวิศวกรรม',
        'subcategory' => 'เขียนแบบ',
        'description' => 'ขึ้นโมเดล 3D และจัดทำภาพจำลองทัศนียภาพสมจริง (3D Perspective Rendering) สำหรับโครงการบ้านเดี่ยว 2 ชั้น 3 หลัง และเขียนแบบสถาปัตยกรรม/โครงสร้างเพื่อส่งยื่นขออนุญาตปลูกสร้างอาคารกับเขต',
        'salary' => 40000
    ],
    19 => [
        'company' => 'ยูสเซอร์ ครีเอทีฟ แล็ป (User Creative Lab)',
        'title' => 'UI/UX Designer ออกแบบ Mobile App (UX/UI Designer for Mobile Application)',
        'category' => 'ออกแบบเว็บไซต์และ UI/UX',
        'subcategory' => 'UX/UI Design',
        'description' => 'วิเคราะห์พฤติกรรมผู้ใช้ วาดโครงร่าง (Wireframe) และพัฒนาหน้าตาแอปพลิเคชันจองที่พัก/โรงแรมบนสมาร์ทโฟน ด้วยโปรแกรม Figma ออกแบบ UI Components และเชื่อมโยงหน้าจอสร้าง Prototype สำหรับทดสอบ',
        'salary' => 30000
    ],
    20 => [
        'company' => 'เอนี่ติง ทาสก์ส ประเทศไทย (Anything Tasks Thailand)',
        'title' => 'ผู้ช่วยประสานงานทั่วไป / ทำธุระให้ผู้บริหาร (Personal Assistant / General Tasker)',
        'category' => 'อื่นๆ',
        'subcategory' => 'ผู้ช่วยส่วนตัว',
        'description' => 'ผู้ช่วยทำงานจิปาถะทั่วไป เช่น รับส่งเอกสารสำคัญ ติดต่อจองตั๋วเดินทาง ประสานงานประชุมสัมมนา ปฏิบัติงานเป็นรายชิ้นงานหรือรายสัปดาห์ตามตกลง มีความยืดหยุ่นเรื่องเวลา',
        'salary' => 12000
    ]
];

$bangkok_districts = [
    ['name' => 'เขตวัฒนา, กรุงเทพมหานคร', 'lat' => 13.7367, 'lng' => 100.5600],
    ['name' => 'เขตปทุมวัน, กรุงเทพมหานคร', 'lat' => 13.7462, 'lng' => 100.5300],
    ['name' => 'เขตบางรัก, กรุงเทพมหานคร', 'lat' => 13.7250, 'lng' => 100.5250],
    ['name' => 'เขตพญาไท, กรุงเทพมหานคร', 'lat' => 13.7800, 'lng' => 100.5400],
    ['name' => 'เขตดินแดง, กรุงเทพมหานคร', 'lat' => 13.7620, 'lng' => 100.5650],
    ['name' => 'เขตห้วยขวาง, กรุงเทพมหานคร', 'lat' => 13.7750, 'lng' => 100.5750],
    ['name' => 'เขตจตุจักร, กรุงเทพมหานคร', 'lat' => 13.8050, 'lng' => 100.5550],
    ['name' => 'เขตคลองเตย, กรุงเทพมหานคร', 'lat' => 13.7150, 'lng' => 100.5800],
    ['name' => 'เขตพระโขนง, กรุงเทพมหานคร', 'lat' => 13.7000, 'lng' => 100.6000],
    ['name' => 'เขตบางนา, กรุงเทพมหานคร', 'lat' => 13.6650, 'lng' => 100.6150],
    ['name' => 'เขตลาดพร้าว, กรุงเทพมหานคร', 'lat' => 13.8000, 'lng' => 100.6100],
    ['name' => 'เขตวังทองหลาง, กรุงเทพมหานคร', 'lat' => 13.7850, 'lng' => 100.6150],
    ['name' => 'เขตบางกะปิ, กรุงเทพมหานคร', 'lat' => 13.7700, 'lng' => 100.6400],
    ['name' => 'เขตสวนหลวง, กรุงเทพมหานคร', 'lat' => 13.7300, 'lng' => 100.6300],
    ['name' => 'เขตประเวศ, กรุงเทพมหานคร', 'lat' => 13.7000, 'lng' => 100.6700],
    ['name' => 'เขตดอนเมือง, กรุงเทพมหานคร', 'lat' => 13.9100, 'lng' => 100.5900],
    ['name' => 'เขตหลักสี่, กรุงเทพมหานคร', 'lat' => 13.8850, 'lng' => 100.5750],
    ['name' => 'เขตบางซื่อ, กรุงเทพมหานคร', 'lat' => 13.8100, 'lng' => 100.5300],
    ['name' => 'เขตดุสิต, กรุงเทพมหานคร', 'lat' => 13.7750, 'lng' => 100.5150],
    ['name' => 'เขตพระนคร, กรุงเทพมหานคร', 'lat' => 13.7550, 'lng' => 100.4950]
];

$password_plain = "password123";
$hashed_password = jobfind_hash_password($password_plain);

echo "<ul>";
foreach ($mock_data as $i => $data) {
    $username = "employer" . $i;
    $email = "employer" . $i . "@jobfind.com";
    $fullname = "ผู้ว่าจ้างคนที่ " . $i . " (" . $data['company'] . ")";
    $phone = "08" . str_pad($i, 8, "0", STR_PAD_LEFT);
    $role = "employer";
    
    // Pick a district round-robin
    $district = $bangkok_districts[($i - 1) % count($bangkok_districts)];
    $loc_name = $district['name'];
    $latitude = $district['lat'];
    $longitude = $district['lng'];

    $ok_user = mysqli_query($conn, "
        INSERT INTO Users (username, email, password, fullname, phone, gender, role, latitude, longitude)
        VALUES ('$username', '$email', '$hashed_password', '$fullname', '$phone', NULL, '$role', $latitude, $longitude)
    ");
    
    if (!$ok_user) {
        echo "<li style='color:red;'>ผู้ใช้ $username สมัครล้มเหลว: " . mysqli_error($conn) . "</li>";
        continue;
    }
    
    $user_id = mysqli_insert_id($conn);
    $address = "123/" . $i . " ถนนสุขุมวิท";
    
    mysqli_query($conn, "
        INSERT INTO Employer_Profile (user_id, employer_name, employer_description, address, province, district, postal_code, latitude, longitude)
        VALUES ('$user_id', '{$data['company']}', 'ผู้ให้บริการระดับพรีเมียมในด้าน {$data['category']}', '$address', 'กรุงเทพมหานคร', 'วัฒนา', '10110', $latitude, $longitude)
    ");

    $title = mysqli_real_escape_string($conn, $data['title']);
    $desc = mysqli_real_escape_string($conn, $data['description']);
    $sal = intval($data['salary']);
    $cat = mysqli_real_escape_string($conn, $data['category']);
    $sub = mysqli_real_escape_string($conn, $data['subcategory']);
    $deadline = "2026-12-31";

    $ok_job = mysqli_query($conn, "
        INSERT INTO Job (employer_id, title, description, location, salary, latitude, longitude, deadline, category, job_subcategory, employment_type, image_path, status, admin_status)
        VALUES ('$user_id', '$title', '$desc', '$loc_name', $sal, $latitude, $longitude, '$deadline', '$cat', '$sub', 'freelance_project', NULL, 'open', 'approved')
    ");

    if ($ok_job) {
        echo "<li style='color:green;'>สร้าง $username และลงงาน \"$title\" ในพื้นที่ \"$loc_name\" สำเร็จ!</li>";
    } else {
        echo "<li style='color:red;'>สร้าง $username สำเร็จ แต่ลงงานล้มเหลว: " . mysqli_error($conn) . "</li>";
    }
}
echo "</ul>";

// 3. Update any pre-existing jobs to Dec 2026 deadline & Bangkok locations
$pre_jobs = mysqli_query($conn, "SELECT job_id, title FROM Job WHERE employer_id NOT IN (SELECT user_id FROM Users WHERE email LIKE '%@jobfind.com')");
if ($pre_jobs && mysqli_num_rows($pre_jobs) > 0) {
    echo "<h3>กำลังอัปเดตงานเก่าที่มีอยู่เดิม...</h3><ul>";
    $idx = 0;
    while ($row = mysqli_fetch_assoc($pre_jobs)) {
        $job_id = intval($row['job_id']);
        $district = $bangkok_districts[$idx % count($bangkok_districts)];
        $loc_name = mysqli_real_escape_string($conn, $district['name']);
        $lat = $district['lat'];
        $lng = $district['lng'];

        mysqli_query($conn, "
            UPDATE Job 
            SET deadline = '2026-12-31', 
                location = '$loc_name', 
                latitude = $lat, 
                longitude = $lng 
            WHERE job_id = $job_id
        ");
        echo "<li style='color:blue;'>อัปเดตงาน #$job_id \"{$row['title']}\" -> $loc_name</li>";
        $idx++;
    }
    echo "</ul>";
}

echo "<h3 style='color:green;'>อัปเดตและลงข้อมูลงาน 20 หมวดหมู่บน Deployed Database เสร็จสมบูรณ์!</h3>";
echo "<p style='color:red; font-weight:bold;'>*** โปรดลบไฟล์ run_db_update.php นี้ออกจาก Host เพื่อความปลอดภัย ***</p>";
?>
