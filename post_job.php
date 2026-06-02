<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "job_image_helpers.php";
require_once "location_schema.php";
require_once "category_helpers.php";
require_once "employer_sidebar_helpers.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];
$error = '';
ensure_job_image_schema($conn);
ensure_location_schema($conn);
ensure_category_schema($conn);
ensure_default_job_categories($conn);
$sidebar_pending_apps = get_employer_pending_application_count($conn, $employer_id);

$employer_location = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT ep.address, ep.province, ep.district, ep.latitude, ep.longitude,
           u.latitude AS user_lat, u.longitude AS user_lon
    FROM users u
    LEFT JOIN employer_profile ep ON ep.user_id = u.user_id
    WHERE u.user_id = '$employer_id'
    LIMIT 1
")) ?: [];
$default_job_lat = $_POST['latitude'] ?? ($employer_location['latitude'] ?? $employer_location['user_lat'] ?? '');
$default_job_lng = $_POST['longitude'] ?? ($employer_location['longitude'] ?? $employer_location['user_lon'] ?? '');
$default_job_lat = is_numeric($default_job_lat) ? (float)$default_job_lat : '';
$default_job_lng = is_numeric($default_job_lng) ? (float)$default_job_lng : '';

function jobfind_location_text_from_profile(array $profile, string $fallback = ''): string {
    $parts = array_filter([
        trim($profile['district'] ?? ''),
        trim($profile['province'] ?? '')
    ]);
    $location = trim(implode(', ', $parts));
    if($location === ''){
        $location = trim($profile['address'] ?? '');
    }
    return $location !== '' ? $location : $fallback;
}

$default_job_location = jobfind_location_text_from_profile($employer_location);

if(isset($_POST['submit'])){
    $title       = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $location    = mysqli_real_escape_string($conn, $default_job_location);
    $salary_raw  = trim($_POST['salary'] ?? '');
    $salary      = $salary_raw !== '' && is_numeric($salary_raw) ? mysqli_real_escape_string($conn, $salary_raw) : '0';
    $deadline_raw = trim($_POST['deadline'] ?? '');
    $deadline    = mysqli_real_escape_string($conn, $deadline_raw);
    $deadline_sql = $deadline !== '' ? "'$deadline'" : "NULL";
    $category_raw = trim($_POST['category'] ?? '');
    $job_subcategory_raw = trim($_POST['job_subcategory'] ?? '');
    $category    = mysqli_real_escape_string($conn, $category_raw);
    $job_subcategory = mysqli_real_escape_string($conn, $job_subcategory_raw);
    $employment_type = jobfind_normalize_employment_type($_POST['employment_type'] ?? 'freelance_project');
    $employment_type_sql = mysqli_real_escape_string($conn, $employment_type);
    $latitude     = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : (!empty($employer_location['latitude']) ? floatval($employer_location['latitude']) : null);
    $longitude    = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : (!empty($employer_location['longitude']) ? floatval($employer_location['longitude']) : null);
    if($latitude === null && !empty($employer_location['user_lat'])){
        $latitude = floatval($employer_location['user_lat']);
    }
    if($longitude === null && !empty($employer_location['user_lon'])){
        $longitude = floatval($employer_location['user_lon']);
    }
    $latitude_sql = $latitude !== null ? sprintf('%.8F', $latitude) : "NULL";
    $longitude_sql = $longitude !== null ? sprintf('%.8F', $longitude) : "NULL";
    if($category_raw === '' || $job_subcategory_raw === ''){
        $error = 'กรุณาเลือกประเภทงานหลักและงานย่อย';
    }
    if($error === ''){
        $valid_category_pair = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT js.subcategory_id
            FROM job_subcategories js
            JOIN categories c ON c.category_id=js.category_id
            WHERE c.name='$category' AND js.name='$job_subcategory'
            LIMIT 1
        "));
        if(!$valid_category_pair){
            $error = 'กรุณาเลือกงานย่อยที่อยู่ในประเภทงานหลัก';
        }
    }

    $job_image_paths = $error === '' ? save_uploaded_job_images($_FILES['job_images'] ?? [], $employer_id, $error) : [];

    if($error === ''){
        $job_image_path = $job_image_paths[0] ?? '';
        $job_image_sql = $job_image_path !== '' ? "'" . mysqli_real_escape_string($conn, $job_image_path) . "'" : "NULL";
        $result = mysqli_query($conn,"
            INSERT INTO job (employer_id,title,description,location,salary,latitude,longitude,deadline,category,job_subcategory,employment_type,image_path,status,admin_status)
            VALUES ('$employer_id','$title','$description','$location','$salary',$latitude_sql,$longitude_sql,$deadline_sql,'$category','$job_subcategory','$employment_type_sql',$job_image_sql,'open','pending')
        ");

        if($result){
            $job_id = mysqli_insert_id($conn);
            $images_saved = true;
            foreach($job_image_paths as $index => $path){
                $path_sql = mysqli_real_escape_string($conn, $path);
                $sort_order = intval($index);
                $images_saved = mysqli_query($conn, "
                    INSERT INTO job_images (job_id, image_path, sort_order)
                    VALUES ('$job_id', '$path_sql', '$sort_order')
                ");
                if(!$images_saved){
                    break;
                }
            }

            if(!$images_saved){
                mysqli_query($conn, "DELETE FROM job_images WHERE job_id='$job_id'");
                mysqli_query($conn, "DELETE FROM job WHERE job_id='$job_id'");
                foreach($job_image_paths as $path){
                    delete_job_image_file($path);
                }
                $error = 'บันทึกรูปภาพประกอบไม่สำเร็จ';
            } else {
                sync_job_primary_image($conn, $job_id);
                header("Location: employer_manage_jobs.php?posted=1");
                exit();
            }
        } else {
            foreach($job_image_paths as $path){
                delete_job_image_file($path);
            }
            $error = mysqli_error($conn);
        }
    }
}

// ดึง categories จาก DB (Admin จัดการได้)
$cats = jobfind_get_categories_with_subcategories($conn);
$category_subcategory_map = jobfind_category_subcategory_map($cats);
$selected_category = $_POST['category'] ?? '';
$selected_job_subcategory = $_POST['job_subcategory'] ?? '';
$selected_employment_type = jobfind_normalize_employment_type($_POST['employment_type'] ?? 'freelance_project');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=13">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Post Job</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.min.css" />
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

  :root {
    --navy:   #0f172a;  --navy2:  #1e293b;  --navy3:  #334155;
    --accent: #6366f1;  --light:  #f1f5f9;  --white:  #ffffff;
    --text:   #0f172a;  --muted:  #64748b;  --border: #e2e8f0;
    --green:  #10b981;  --red:    #ef4444;  --radius: 14px;
  }

  body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; }

  /* ── Sidebar ── */
  .sidebar { width:240px; min-height:100vh; background:var(--navy); display:flex; flex-direction:column; padding:28px 0; position:fixed; top:0; left:0; z-index:100; }
  .sidebar-brand { padding:0 24px 28px; border-bottom:1px solid var(--navy3); }
  .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
  .logo-icon { width:36px; height:36px; background:var(--accent); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; }
  .logo-text { font-size:15px; font-weight:600; color:#fff; line-height:1.2; }
  .logo-sub  { font-size:11px; color:var(--navy3); }
  .sidebar-nav { padding:20px 12px; flex:1; display:flex; flex-direction:column; gap:4px; }
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#94a3b8; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s,color .15s; }
  .nav-item:hover  { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }
  .nav-logout i { font-size:17px; }

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; display:flex; justify-content:center; }
  .content-wrap { width:100%; max-width:720px; }

  /* ── Topbar ── */
  .topbar { margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Alert error ── */
  .alert-err { background:#fee2e2; border:1px solid #fca5a5; border-radius:10px; padding:12px 16px; font-size:13.5px; color:#991b1b; display:flex; align-items:center; gap:8px; margin-bottom:20px; }

  /* ── Form card ── */
  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:28px; }

  .section-title { font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
  .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

  .field-group { margin-bottom:18px; }
  .field-group label { display:block; font-size:13px; font-weight:500; color:var(--text); margin-bottom:6px; }
  .field-group label .req { color:var(--red); margin-left:2px; }
  .field-group label .hint { color:var(--muted); font-weight:400; font-size:12px; margin-left:6px; }

  .form-input { width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; background:var(--white); }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  textarea.form-input { resize:vertical; min-height:110px; line-height:1.7; }

  .input-icon-wrap { position:relative; }
  .input-icon-wrap i { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--muted); pointer-events:none; }
  .input-icon-wrap .form-input { padding-left:40px; }

  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

  .file-upload { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:14px; padding:14px 16px; border:1px solid var(--border); border-radius:14px; background:#f8fafc; cursor:pointer; transition:border-color .15s,background .15s,box-shadow .15s; }
  .file-upload:hover { border-color:var(--accent); background:#eef2ff; box-shadow:0 8px 18px rgba(99,102,241,.10); }
  .file-upload input { display:none; }
  .upload-icon { width:48px; height:48px; border-radius:12px; background:#eef2ff; color:var(--accent); display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
  .upload-title { font-size:13.5px; font-weight:600; color:var(--text); margin-bottom:3px; }
  .upload-hint { font-size:12px; color:var(--muted); line-height:1.5; }
  .upload-preview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(84px,1fr)); gap:10px; margin-top:10px; }
  .upload-preview { width:100%; height:64px; border-radius:10px; object-fit:cover; border:1px solid var(--border); display:block; }
  .upload-action { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:42px; padding:0 16px; border:1px solid var(--accent); border-radius:10px; background:var(--accent); color:#fff; font-size:13px; font-weight:600; white-space:nowrap; box-shadow:0 8px 18px rgba(99,102,241,.18); }

  /* ── Info box ── */
  .info-box { background:#eef2ff; border:1px solid #c7d2fe; border-radius:10px; padding:14px 16px; display:flex; gap:10px; margin-bottom:24px; }
  .info-box i { color:var(--accent); font-size:18px; flex-shrink:0; margin-top:1px; }
  .info-box p { font-size:13px; color:#3730a3; line-height:1.7; margin:0; }

  /* ── Submit btn ── */
  .btn-post { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; border:none; border-radius:10px; padding:13px 30px; font-size:14px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; transition:background .15s,transform .1s; }
  .btn-post:hover { background:#4f46e5; transform:translateY(-1px); }
  .btn-post:active { transform:scale(.98); }

  /* ── Char counter ── */
  .char-count { font-size:11.5px; color:var(--muted); text-align:right; margin-top:4px; }
  .btn-open-map { display:inline-flex; align-items:center; gap:6px; background:#eef2ff; color:var(--accent); border:1px solid #c7d2fe; border-radius:8px; padding:8px 14px; font-size:13px; font-weight:500; cursor:pointer; transition:all .15s; }
  .btn-open-map:hover { background:#c7d2fe; }
  .coord-display { font-size:12px; color:var(--muted); margin-top:6px; }
  .map-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
  .map-modal.active { display:flex; }
  .map-container { background:white; border-radius:16px; width:90%; max-width:900px; height:90vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.3); }
  .map-header { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
  .map-header h3 { margin:0; font-size:18px; font-weight:600; }
  .map-close { background:none; border:none; font-size:24px; cursor:pointer; color:var(--muted); }
  #job-map { flex:1; }
  .map-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end; }
  .map-footer button { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:500; font-size:14px; }
  .btn-map-confirm { background:var(--accent); color:white; }
  .btn-map-cancel { background:var(--light); color:var(--text); }
  .map-info { padding:12px 16px; background:#eef2ff; border-radius:8px; font-size:13px; color:#3730a3; }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; display:block; } .two-col { grid-template-columns:1fr; } .file-upload { grid-template-columns:auto 1fr; } .upload-action { grid-column:1 / -1; } .map-container { width:100%; height:100%; max-width:none; border-radius:0; } }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=13" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Job_Find</div>
        <div class="logo-sub" style="display:none!important;">Employer</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="employer_dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"             class="nav-item active"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs<?php render_employer_manage_jobs_badge($sidebar_pending_apps); ?></a>
    <a href="saved_freelancers.php"    class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="employer_reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_review.php"      class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
    <a href="employer_profile.php"     class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="support_messages.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">
<div class="content-wrap">

  <div class="topbar">
    <h2>Post New Job</h2>
    <p>กรอกรายละเอียดงานที่ต้องการรับสมัคร Freelancer</p>
  </div>

  <?php if($error): ?>
  <div class="alert-err">
    <i class="bi bi-exclamation-triangle-fill"></i>
    เกิดข้อผิดพลาด: <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>

  <!-- Info box -->
  <div class="info-box">
    <i class="bi bi-info-circle-fill"></i>
    <p>งานที่โพสต์จะถูกส่งให้ Admin ตรวจสอบก่อน จึงจะแสดงบนหน้า Browse Jobs<br>
    โดยปกติใช้เวลาไม่นาน กรุณากรอกข้อมูลให้ครบถ้วนและชัดเจน</p>
  </div>

  <form method="POST" enctype="multipart/form-data">
  <div class="form-card">

    <!-- ข้อมูลงาน -->
    <div class="section-title"><i class="bi bi-briefcase"></i> ข้อมูลงาน</div>

    <div class="field-group">
      <label>ชื่อตำแหน่ง <span class="req">*</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-type-h1"></i>
        <input type="text" name="title" class="form-input"
               placeholder="เช่น Senior Frontend Developer, Graphic Designer"
               required maxlength="100"
               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
      </div>
    </div>

    <div class="field-group">
      <label>รายละเอียดงาน <span class="req">*</span></label>
      <textarea name="description" id="desc-input" class="form-input"
                placeholder="อธิบายงานที่ต้องทำ, ทักษะที่ต้องการ, ขอบเขตงาน..."
                required maxlength="2000"
                oninput="updateCount()"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
      <div class="char-count"><span id="desc-count">0</span> / 2000</div>
    </div>

    <?php if(!empty($cats)): ?>
    <div class="two-col">
      <div class="field-group">
        <label>ประเภทงานหลัก <span class="req">*</span></label>
        <div class="input-icon-wrap">
          <i class="bi bi-tag"></i>
          <select name="category" id="category-select" class="form-input" style="padding-left:40px;cursor:pointer;" required>
            <option value="">-- เลือกประเภทงานหลัก --</option>
            <?php foreach($cats as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat['name']); ?>"
              <?php echo $selected_category === $cat['name'] ? 'selected' : ''; ?>>
              <?php echo $cat['icon'].' '.$cat['name']; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field-group">
        <label>งานย่อย <span class="req">*</span></label>
        <div class="input-icon-wrap">
          <i class="bi bi-diagram-3"></i>
          <select name="job_subcategory" id="job-subcategory-select" class="form-input" style="padding-left:40px;cursor:pointer;" required
                  data-selected="<?php echo htmlspecialchars($selected_job_subcategory); ?>">
            <option value="">-- เลือกประเภทงานหลักก่อน --</option>
          </select>
        </div>
      </div>
    </div>
    <?php else: ?>
    <input type="hidden" name="category" value="">
    <?php endif; ?>

    <div class="field-group">
      <label>ลักษณะการจ้าง <span class="req">*</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-briefcase"></i>
        <select name="employment_type" class="form-input" style="padding-left:40px;cursor:pointer;" required>
          <?php foreach(jobfind_employment_type_options() as $type_value => $type_label): ?>
          <option value="<?php echo htmlspecialchars($type_value); ?>"
            <?php echo $selected_employment_type === $type_value ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($type_label); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- รายละเอียดเพิ่มเติม -->
    <div class="section-title" style="margin-top:8px;"><i class="bi bi-map"></i> สถานที่และค่าตอบแทน</div>

    <div class="field-group">
      <label>รูปภาพประกอบ</label>
      <label class="file-upload" for="job-image-input">
        <div class="upload-icon" id="job-image-icon"><i class="bi bi-image"></i></div>
        <div>
          <div class="upload-title" id="job-image-title">เลือกรูปภาพ</div>
          <div class="upload-hint">เลือกได้หลายรูป รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 5MB ต่อรูป</div>
        </div>
        <span class="upload-action"><i class="bi bi-upload"></i> เลือกรูปภาพ</span>
        <input type="file" name="job_images[]" id="job-image-input" accept="image/jpeg,image/png,image/webp" multiple onchange="previewJobImages(this)">
      </label>
      <div class="upload-preview-grid" id="job-image-preview-grid"></div>
    </div>

    <div class="field-group">
      <label>ค่าจ้าง (บาท) <span class="hint">(ไม่บังคับ)</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-currency-dollar"></i>
        <input type="number" name="salary" class="form-input"
               placeholder="เช่น 50000"
               min="0"
               value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>">
      </div>
    </div>

    <div class="field-group">
      <label>ตำแหน่งงานบนแผนที่ / Job pin</label>
      <button type="button" class="btn-open-map" onclick="openJobMapModal()">
        <i class="bi bi-geo"></i> เลือกตำแหน่งงาน
      </button>
      <div class="coord-display" id="job-coord-display">
        <?php
          if (!empty($default_job_lat) && !empty($default_job_lng)) {
            echo "ปักหมุดงานแล้ว";
          } else {
            echo "ยังไม่ได้ปักหมุดงาน ระบบจะแมชจากข้อความสถานที่แทน";
          }
        ?>
      </div>
      <input type="hidden" name="latitude" id="job-latitude" value="<?php echo htmlspecialchars($default_job_lat ?? ''); ?>">
      <input type="hidden" name="longitude" id="job-longitude" value="<?php echo htmlspecialchars($default_job_lng ?? ''); ?>">
    </div>

    <div class="field-group">
      <label>วันสิ้นสุดรับสมัคร <span class="hint">(ไม่บังคับ)</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-calendar-event"></i>
        <input type="date" name="deadline" class="form-input"
               min="<?php echo date('Y-m-d'); ?>"
               value="<?php echo htmlspecialchars($_POST['deadline'] ?? ''); ?>">
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;padding-top:8px;">
      <button type="submit" name="submit" class="btn-post">
        <i class="bi bi-send"></i> โพสต์งานนี้
      </button>
    </div>

  </div>
  </form>

  <div class="map-modal" id="jobMapModal">
    <div class="map-container">
      <div class="map-header">
        <h3><i class="bi bi-geo"></i> ปักหมุดตำแหน่งงาน</h3>
        <button class="map-close" type="button" onclick="closeJobMapModal()">&times;</button>
      </div>
      <div style="padding:16px;">
        <div class="map-info">
          คลิกบนแผนที่เพื่อเลือกพื้นที่ทำงาน ถ้าไม่เลือก ระบบจะใช้พิกัดบริษัทหรือข้อความสถานที่
        </div>
      </div>
      <div id="job-map"></div>
      <div class="map-footer">
        <button type="button" class="btn-map-cancel" onclick="closeJobMapModal()">ยกเลิก</button>
        <button type="button" class="btn-map-confirm" onclick="confirmJobMapLocation()">ยืนยัน</button>
      </div>
    </div>
  </div>

</div>
</main>

<script src="assets/vendor/leaflet/leaflet.min.js"></script>
<script src="assets/js/location-map-picker.js"></script>
<script>
  function updateCount(){
    const ta = document.getElementById('desc-input');
    document.getElementById('desc-count').textContent = ta.value.length;
  }
  updateCount();

  const categorySubcategoryMap = <?php echo json_encode($category_subcategory_map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  function updateJobSubcategoryOptions(resetSelected = false){
    const categorySelect = document.getElementById('category-select');
    const subcategorySelect = document.getElementById('job-subcategory-select');
    if(!categorySelect || !subcategorySelect) return;

    const selectedCategory = categorySelect.value;
    const currentValue = resetSelected ? '' : (subcategorySelect.dataset.selected || subcategorySelect.value || '');
    const options = categorySubcategoryMap[selectedCategory] || [];

    subcategorySelect.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = selectedCategory ? '-- เลือกงานย่อย --' : '-- เลือกประเภทงานหลักก่อน --';
    subcategorySelect.appendChild(placeholder);

    options.forEach((name) => {
      const option = document.createElement('option');
      option.value = name;
      option.textContent = name;
      option.selected = name === currentValue;
      subcategorySelect.appendChild(option);
    });

    subcategorySelect.disabled = !selectedCategory;
    if(options.length === 0 && selectedCategory){
      placeholder.textContent = 'ยังไม่มีงานย่อยในประเภทนี้';
    }
  }

  document.getElementById('category-select')?.addEventListener('change', () => updateJobSubcategoryOptions(true));
  updateJobSubcategoryOptions(false);

  function previewJobImages(input){
    const files = Array.from(input.files || []);
    const grid = document.getElementById('job-image-preview-grid');
    const icon = document.getElementById('job-image-icon');
    const title = document.getElementById('job-image-title');

    grid.innerHTML = '';

    if(files.length === 0){
      icon.style.display = 'flex';
      title.textContent = 'เลือกรูปภาพ';
      return;
    }

    files.forEach((file) => {
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.alt = file.name;
      img.className = 'upload-preview';
      grid.appendChild(img);
    });

    icon.style.display = 'none';
    title.textContent = files.length + ' รูปที่เลือก';
  }
  let jobMap = null;
  let jobLat = <?php echo !empty($default_job_lat) ? $default_job_lat : '13.7563'; ?>;
  let jobLng = <?php echo !empty($default_job_lng) ? $default_job_lng : '100.5018'; ?>;
  let jobHasPin = <?php echo (!empty($default_job_lat) && !empty($default_job_lng)) ? 'true' : 'false'; ?>;

  function setJobPosition(lat, lng) {
    jobLat = Number(lat);
    jobLng = Number(lng);
    jobHasPin = true;
  }

  function openJobMapModal() {
    const modal = document.getElementById('jobMapModal');
    modal.classList.add('active');

    setTimeout(() => {
      if (!jobMap) {
        jobMap = createJobFindMapPicker({
          elementId: 'job-map',
          lat: jobLat,
          lng: jobLng,
          hasPin: jobHasPin,
          radiusKm: 30,
          showCircle: false,
          onChange: setJobPosition
        });
      }

      if (jobMap) {
        jobMap.resize();
      }
      if (jobHasPin) {
        jobMap.setView(jobLat, jobLng);
      }
    }, 100);
  }

  function closeJobMapModal() {
    document.getElementById('jobMapModal').classList.remove('active');
  }

  function confirmJobMapLocation() {
    if (!jobHasPin) {
      alert('กรุณาเลือกตำแหน่งงานบนแผนที่');
      return;
    }

    document.getElementById('job-latitude').value = jobLat.toFixed(6);
    document.getElementById('job-longitude').value = jobLng.toFixed(6);
    document.getElementById('job-coord-display').textContent = 'ปักหมุดงานแล้ว';
    closeJobMapModal();
  }

  document.getElementById('jobMapModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeJobMapModal();
    }
  });
</script>
</body>
</html>
