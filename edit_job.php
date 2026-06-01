<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "job_image_helpers.php";
require_once "location_schema.php";
require_once "category_helpers.php";
require_once "employer_sidebar_helpers.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "employer"){
    header("Location: login.php");
    exit();
}

$job_id = intval($_GET['job_id'] ?? $_GET['id'] ?? 0);
$employer_id = intval($_SESSION['user_id']);
$toast = '';
ensure_job_image_schema($conn);
ensure_location_schema($conn);
ensure_category_schema($conn);
ensure_default_job_categories($conn);
$sidebar_pending_apps = get_employer_pending_application_count($conn, $employer_id);

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

$query = mysqli_query($conn, "SELECT * FROM job WHERE job_id='$job_id' AND employer_id='$employer_id'");
$job = mysqli_fetch_assoc($query);

if(!$job){
    echo "<script>alert('ไม่พบงานนี้ (ID: $job_id)'); window.location.href='employer_manage_jobs.php';</script>";
    exit();
}

$job_images = get_job_images($conn, $job_id);
$employer_location = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT address, province, district
    FROM employer_profile
    WHERE user_id='$employer_id'
    LIMIT 1
")) ?: [];

// ── UPDATE JOB ──
if(isset($_POST['update'])){
    $title       = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $location    = mysqli_real_escape_string($conn, jobfind_location_text_from_profile($employer_location, trim($job['location'] ?? '')));
    $salary      = mysqli_real_escape_string($conn, trim($_POST['salary']));
    $deadline    = mysqli_real_escape_string($conn, trim($_POST['deadline']));
    $category_raw = trim($_POST['category'] ?? '');
    $job_subcategory_raw = trim($_POST['job_subcategory'] ?? '');
    $category    = mysqli_real_escape_string($conn, $category_raw);
    $job_subcategory = mysqli_real_escape_string($conn, $job_subcategory_raw);
    $employment_type = jobfind_normalize_employment_type($_POST['employment_type'] ?? ($job['employment_type'] ?? 'freelance_project'));
    $employment_type_sql = mysqli_real_escape_string($conn, $employment_type);
    $image_error = '';
    $delete_image_ids = array_map('intval', $_POST['delete_image_ids'] ?? []);
    $uploaded_images = save_uploaded_job_images($_FILES['job_images'] ?? [], $employer_id, $image_error);
    $remaining_count = count($job_images) - count($delete_image_ids) + count($uploaded_images);

    if($category_raw === '' || $job_subcategory_raw === ''){
        $image_error = 'กรุณาเลือกประเภทงานหลักและงานย่อย';
    }
    if($image_error === ''){
        $valid_category_pair = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT js.subcategory_id
            FROM job_subcategories js
            JOIN categories c ON c.category_id=js.category_id
            WHERE c.name='$category' AND js.name='$job_subcategory'
            LIMIT 1
        "));
        if(!$valid_category_pair){
            $image_error = 'กรุณาเลือกงานย่อยที่อยู่ในประเภทงานหลัก';
        }
    }

    if($remaining_count > job_image_max_count()){
        $image_error = 'รูปภาพประกอบใส่ได้ไม่เกิน ' . job_image_max_count() . ' รูปต่อหนึ่งงาน';
    }

    if($image_error !== ''){
        foreach($uploaded_images as $path){
            delete_job_image_file($path);
        }
        echo "<script>alert('" . addslashes($image_error) . "'); window.history.back();</script>";
        exit();
    }

    mysqli_begin_transaction($conn);

    $updated = mysqli_query($conn, "
        UPDATE job SET 
            title='$title',
            description='$description',
            location='$location',
            salary='$salary',
            deadline='$deadline',
            category='$category',
            job_subcategory='$job_subcategory',
            employment_type='$employment_type_sql',
            updated_at=NOW()
        WHERE job_id='$job_id' AND employer_id='$employer_id'
    ");

    $delete_paths = [];
    if($updated && !empty($delete_image_ids)){
        $ids_sql = implode(',', $delete_image_ids);
        $delete_res = mysqli_query($conn, "
            SELECT image_id, image_path
            FROM job_images
            WHERE job_id='$job_id' AND image_id IN ($ids_sql)
        ");
        if($delete_res){
            while($img = mysqli_fetch_assoc($delete_res)){
                $delete_paths[] = $img['image_path'];
            }
        }
        $updated = mysqli_query($conn, "DELETE FROM job_images WHERE job_id='$job_id' AND image_id IN ($ids_sql)");
    }

    if($updated && !empty($uploaded_images)){
        $sort_res = mysqli_query($conn, "SELECT COALESCE(MAX(sort_order), -1) AS max_sort FROM job_images WHERE job_id='$job_id'");
        $sort_row = $sort_res ? mysqli_fetch_assoc($sort_res) : ['max_sort' => -1];
        $sort_order = intval($sort_row['max_sort']) + 1;

        foreach($uploaded_images as $path){
            $path_sql = mysqli_real_escape_string($conn, $path);
            $updated = mysqli_query($conn, "
                INSERT INTO job_images (job_id, image_path, sort_order)
                VALUES ('$job_id', '$path_sql', '$sort_order')
            ");
            $sort_order++;
            if(!$updated){
                break;
            }
        }
    }

    if($updated){
        $updated = sync_job_primary_image($conn, $job_id);
    }

    if(!$updated){
        mysqli_rollback($conn);
        foreach($uploaded_images as $path){
            delete_job_image_file($path);
        }
        echo "<script>alert('บันทึกการแก้ไขไม่สำเร็จ'); window.history.back();</script>";
        exit();
    }

    mysqli_commit($conn);

    foreach($delete_paths as $path){
        delete_job_image_file($path);
    }
    
    $toast = 'success';
    header("Location: edit_job.php?job_id=$job_id&toast=$toast");
    exit();
}

// ── GET JOB ──
$query = mysqli_query($conn, "SELECT * FROM job WHERE job_id='$job_id' AND employer_id='$employer_id'");
$job = mysqli_fetch_assoc($query);

if(!$job){
    echo "<script>alert('ไม่พบงานนี้ (ID: $job_id)'); window.location.href='employer_manage_jobs.php';</script>";
    exit();
}

$job_images = get_job_images($conn, $job_id);

// ดึง categories
$cats = jobfind_get_categories_with_subcategories($conn);
$category_subcategory_map = jobfind_category_subcategory_map($cats);
$selected_category = $job['category'] ?? '';
$selected_job_subcategory = $job['job_subcategory'] ?? '';
$selected_employment_type = jobfind_normalize_employment_type($job['employment_type'] ?? 'freelance_project');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo.png?v=3">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Job - Job_Find</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root {
    --navy:#0f172a; --navy2:#1e293b; --navy3:#334155;
    --accent:#6366f1; --light:#f1f5f9; --white:#ffffff;
    --text:#0f172a; --muted:#64748b; --border:#e2e8f0;
    --green:#10b981; --yellow:#f59e0b; --red:#ef4444; --radius:14px;
  }
  body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; }
  
  /* Sidebar */
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
  
  /* Main */
  .main { margin-left:240px; flex:1; padding:36px 40px; display:flex; justify-content:center; }
  .content-wrap { width:100%; max-width:720px; }
  .topbar-wrap { display:flex; align-items:center; gap:16px; margin-bottom:28px; }
  .btn-back { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:13px; color:var(--muted); text-decoration:none; background:var(--white); transition:all .15s; }
  .btn-back:hover { background:var(--light); color:var(--text); border-color:var(--navy3); }
  .topbar h2 { font-size:22px; font-weight:600; margin:0; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  
  /* Form */
  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
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
  .image-editor { display:flex; flex-direction:column; gap:14px; padding:16px; border:1px solid var(--border); border-radius:14px; background:#f8fafc; }
  .existing-image-grid,.new-image-preview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(118px,1fr)); gap:12px; }
  .existing-image-card,.new-image-preview-card { position:relative; min-height:104px; border:1px solid var(--border); border-radius:12px; overflow:hidden; background:var(--white); }
  .existing-image-card img,.new-image-preview-card img { width:100%; height:104px; object-fit:cover; display:block; }
  .image-delete-option { position:absolute; right:8px; bottom:8px; display:inline-grid; grid-auto-flow:column; place-content:center; place-items:center; gap:6px; height:32px; padding:0 10px; border:1px solid #fecaca; border-radius:9px; background:rgba(255,241,242,.94); color:#b91c1c; font-size:12px; font-weight:600; line-height:1; cursor:pointer; margin:0; box-shadow:0 6px 16px rgba(15,23,42,.12); }
  .image-delete-option input { position:absolute; opacity:0; pointer-events:none; }
  .image-delete-option.is-marked-delete { background:var(--red); border-color:var(--red); color:#fff; }
  .image-editor .file-upload { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:12px; width:100%; min-height:72px; padding:12px 14px; margin:0; border:1px dashed #c7d2fe; border-radius:12px; background:var(--white); color:var(--text); cursor:pointer; transition:border-color .15s,background .15s,box-shadow .15s; }
  .image-editor .file-upload:hover { border-color:var(--accent); background:#eef2ff; box-shadow:0 8px 18px rgba(99,102,241,.10); }
  .image-editor .file-upload input { display:none; }
  .upload-icon { width:44px; height:44px; border-radius:12px; background:#eef2ff; color:var(--accent); display:flex; align-items:center; justify-content:center; font-size:20px; }
  .upload-title { font-size:13.5px; font-weight:600; color:var(--text); line-height:1.3; }
  .image-hint { font-size:12px; color:var(--muted); line-height:1.5; margin-top:2px; }
  .upload-action { display:inline-grid; grid-auto-flow:column; place-content:center; place-items:center; gap:8px; height:42px; padding:0 14px; border-radius:10px; background:var(--accent); color:#fff; font-size:13px; font-weight:600; white-space:nowrap; box-shadow:0 8px 18px rgba(99,102,241,.18); }
  @media(max-width:560px){ .image-editor .file-upload { grid-template-columns:auto 1fr; } .upload-action { grid-column:1 / -1; } }
  .info-box { background:#eef2ff; border:1px solid #c7d2fe; border-radius:10px; padding:14px 16px; display:flex; gap:10px; margin-bottom:24px; }
  .info-box i { color:var(--accent); font-size:18px; flex-shrink:0; margin-top:1px; }
  .info-box p { font-size:13px; color:#3730a3; line-height:1.7; margin:0; }
  .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; border:none; border-radius:10px; padding:13px 30px; font-size:14px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; transition:background .15s,transform .1s; }
  .btn-save:hover { background:#4f46e5; transform:translateY(-1px); }
  .btn-cancel { display:inline-flex; align-items:center; gap:8px; background:var(--white); color:var(--muted); border:1px solid var(--border); border-radius:10px; padding:13px 30px; font-size:14px; font-weight:500; font-family:'Sora',sans-serif; cursor:pointer; text-decoration:none; transition:background .15s; margin-left:10px; }
  .btn-cancel:hover { background:var(--light); color:var(--text); }
  .job-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; margin-bottom:20px; }
  .badge-approved { background:#d1fae5; color:#065f46; }
  .badge-rejected { background:#fee2e2; color:#991b1b; }
  
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; display:block; } }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<?php if(isset($_GET['toast'])): ?>
<div class="toast-bar" id="toast">
  <i class="bi bi-check-circle-fill" style="color:var(--green);"></i>
  บันทึกการแก้ไขเรียบร้อยแล้ว
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php endif; ?>

<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon"><img class="brand-logo-img" src="assets/images/jobfind-logo-icon.png?v=3" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text">Job_Find</div>
        <div class="logo-sub">Employer</div>
      </div>
    </a>
  </div>
  
  <!-- ✅ แก้ไขส่วนนี้: เอา Applications ออก เพิ่ม My Reviews กลับมา -->
  <nav class="sidebar-nav">
    <a href="employer_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php" class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item active"><i class="bi bi-briefcase"></i> Manage Jobs<?php render_employer_manage_jobs_badge($sidebar_pending_apps); ?></a>
    <a href="saved_freelancers.php" class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="employer_reviews.php" class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_review.php" class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
    <a href="employer_profile.php" class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="support_messages.php" class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<main class="main">
<div class="content-wrap">

  <div class="topbar-wrap">
    <a href="employer_manage_jobs.php" class="btn-back">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
    <div>
      <h2>Edit Job</h2>
      <p>แก้ไขตำแหน่งงาน "<?php echo htmlspecialchars($job['title']); ?>"</p>
    </div>
  </div>

  <div class="job-badge badge-<?php echo $job['admin_status']; ?>">
    <i class="bi bi-<?php echo $job['admin_status']==='approved'?'check-circle':($job['admin_status']==='rejected'?'x-circle':'hourglass-split'); ?>"></i>
    <?php echo ucfirst($job['admin_status']); ?>
  </div>

  <form method="POST" enctype="multipart/form-data">
  <div class="form-card">

    <div class="section-title"><i class="bi bi-briefcase"></i> ข้อมูลงาน</div>

    <div class="field-group">
      <label>ชื่อตำแหน่ง <span class="req">*</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-type-h1"></i>
        <input type="text" name="title" class="form-input"
               placeholder="เช่น Senior Frontend Developer, Graphic Designer"
               required maxlength="100"
               value="<?php echo htmlspecialchars($job['title']); ?>">
      </div>
    </div>

    <div class="field-group">
      <label>รายละเอียดงาน <span class="req">*</span></label>
      <textarea name="description" class="form-input"
                placeholder="อธิบายงานที่ต้องทำ, ทักษะที่ต้องการ, ขอบเขตงาน..."
                required maxlength="2000"><?php echo htmlspecialchars($job['description']); ?></textarea>
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
    <input type="hidden" name="category" value="<?php echo htmlspecialchars($job['category'] ?? ''); ?>">
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

    <div class="field-group">
      <label>รูปภาพประกอบ</label>
      <div class="image-editor">
        <?php if(!empty($job_images)): ?>
        <div class="existing-image-grid">
          <?php foreach($job_images as $image): ?>
          <div class="existing-image-card">
            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($job['title']); ?>">
            <label class="image-delete-option">
              <i class="bi bi-trash"></i>
              <input type="checkbox" name="delete_image_ids[]" value="<?php echo (int)$image['image_id']; ?>" onchange="toggleDeleteImage(this)">
              <span>ลบ</span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <label class="file-upload" for="job-image-input">
          <div class="upload-icon"><i class="bi bi-images"></i></div>
          <div>
            <div class="upload-title" id="job-image-title">เพิ่มรูปภาพ</div>
            <div class="image-hint">เลือกเพิ่มได้หลายรูป รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 5MB ต่อรูป</div>
          </div>
          <span class="upload-action"><i class="bi bi-upload"></i> เลือกรูปภาพ</span>
          <input type="file" name="job_images[]" id="job-image-input" accept="image/jpeg,image/png,image/webp" multiple onchange="previewJobImages(this)">
        </label>
        <div class="new-image-preview-grid" id="job-image-preview-grid"></div>
      </div>
    </div>

    <div class="section-title" style="margin-top:8px;"><i class="bi bi-map"></i> สถานที่และค่าตอบแทน</div>

    <div class="field-group">
      <label>ค่าจ้าง (บาท) <span class="hint">(ไม่บังคับ)</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-currency-dollar"></i>
        <input type="number" name="salary" class="form-input"
               placeholder="เช่น 50000"
               min="0"
               value="<?php echo htmlspecialchars($job['salary'] ?? ''); ?>">
      </div>
    </div>

    <div class="field-group">
      <label>วันสิ้นสุดรับสมัคร <span class="hint">(ไม่บังคับ)</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-calendar-event"></i>
        <input type="date" name="deadline" class="form-input"
               min="<?php echo date('Y-m-d'); ?>"
               value="<?php echo ($job['deadline']) ? date('Y-m-d', strtotime($job['deadline'])) : ''; ?>">
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:8px;">
      <button type="submit" name="update" class="btn-save">
        <i class="bi bi-check-lg"></i> บันทึกการแก้ไข
      </button>
      <a href="employer_manage_jobs.php" class="btn-cancel">
        <i class="bi bi-x-lg"></i> ยกเลิก
      </a>
    </div>

  </div>
  </form>

</div>
</main>

<script>
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
    const title = document.getElementById('job-image-title');

    grid.innerHTML = '';

    if(files.length === 0){
      title.textContent = 'เพิ่มรูปภาพ';
      return;
    }

    files.forEach((file) => {
      const card = document.createElement('div');
      card.className = 'new-image-preview-card';

      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.alt = file.name;
      card.appendChild(img);
      grid.appendChild(card);
    });

    title.textContent = files.length + ' รูปใหม่ที่เลือก';
  }

  function toggleDeleteImage(input){
    const deleteButton = input.closest('.image-delete-option');

    if(input.checked){
      deleteButton?.classList.add('is-marked-delete');
    } else {
      deleteButton?.classList.remove('is-marked-delete');
    }
  }
</script>
</body>
</html>
