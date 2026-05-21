<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "employer"){
    header("Location: login.php");
    exit();
}

$job_id = intval($_GET['job_id'] ?? $_GET['id'] ?? 0);
$toast = '';

// ── UPDATE JOB ──
if(isset($_POST['update'])){
    $title       = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $location    = mysqli_real_escape_string($conn, trim($_POST['location']));
    $salary      = mysqli_real_escape_string($conn, trim($_POST['salary']));
    $deadline    = mysqli_real_escape_string($conn, trim($_POST['deadline']));
    $category    = mysqli_real_escape_string($conn, trim($_POST['category'] ?? ''));
    
    mysqli_query($conn, "
        UPDATE job SET 
            title='$title',
            description='$description',
            location='$location',
            salary='$salary',
            deadline='$deadline',
            category='$category',
            updated_at=NOW()
        WHERE job_id='$job_id'
    ");
    
    $toast = 'success';
    header("Location: edit_job.php?job_id=$job_id&toast=$toast");
    exit();
}

// ── GET JOB ──
$query = mysqli_query($conn, "SELECT * FROM job WHERE job_id='$job_id'");
$job = mysqli_fetch_assoc($query);

if(!$job){
    echo "<script>alert('ไม่พบงานนี้ (ID: $job_id)'); window.location.href='employer_manage_jobs.php';</script>";
    exit();
}

// ดึง categories
$cats = [];
$cat_check = mysqli_query($conn,"SHOW TABLES LIKE 'categories'");
if($cat_check && mysqli_num_rows($cat_check) > 0){
    $cat_res = mysqli_query($conn,"SELECT * FROM categories ORDER BY category_id ASC");
    while($c = mysqli_fetch_assoc($cat_res)) $cats[] = $c;
}
if(empty($cats)){
    $cats = [
        ['name'=>'IT & Software','icon'=>'💻'],['name'=>'Design','icon'=>'🎨'],
        ['name'=>'Marketing','icon'=>'📢'],    ['name'=>'Writing','icon'=>'✍️'],
        ['name'=>'Finance','icon'=>'💰'],      ['name'=>'Education','icon'=>'🎓'],
        ['name'=>'Other','icon'=>'📦'],
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Job - FreelanceHub</title>
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
  .main { margin-left:240px; flex:1; padding:36px 40px; }
  .content-wrap { max-width:660px; }
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
  
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
</style>
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
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div>
        <div class="logo-text">FreelanceHub</div>
        <div class="logo-sub">Employer</div>
      </div>
    </a>
  </div>
  
  <!-- ✅ แก้ไขส่วนนี้: เอา Applications ออก เพิ่ม My Reviews กลับมา -->
  <nav class="sidebar-nav">
    <a href="employer_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php" class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item active"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="employer_reviews.php" class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_profile.php" class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="support_chat.php" class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
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

  <div class="info-box">
    <i class="bi bi-info-circle-fill"></i>
    <p>งานที่แก้ไขจะถูกส่งให้ Admin ตรวจสอบอีกครั้ง (หากงานได้รับการอนุมัติแล้ว)</p>
  </div>

  <form method="POST">
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
    <div class="field-group">
      <label>หมวดหมู่งาน <span class="req">*</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-tag"></i>
        <select name="category" class="form-input" style="padding-left:40px;cursor:pointer;" required>
          <option value="">-- เลือกหมวดหมู่ --</option>
          <?php foreach($cats as $cat): ?>
          <option value="<?php echo htmlspecialchars($cat['name']); ?>"
            <?php echo ($job['category'] ?? '') === $cat['name'] ? 'selected' : ''; ?>>
            <?php echo $cat['icon'].' '.$cat['name']; ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php else: ?>
    <input type="hidden" name="category" value="<?php echo htmlspecialchars($job['category'] ?? ''); ?>">
    <?php endif; ?>

    <div class="section-title" style="margin-top:8px;"><i class="bi bi-map"></i> สถานที่และค่าตอบแทน</div>

    <div class="two-col">
      <div class="field-group">
        <label>สถานที่ <span class="hint">(ไม่บังคับ)</span></label>
        <div class="input-icon-wrap">
          <i class="bi bi-geo-alt"></i>
          <input type="text" name="location" class="form-input"
                 placeholder="เช่น กรุงเทพฯ, Remote"
                 value="<?php echo htmlspecialchars($job['location'] ?? ''); ?>">
        </div>
      </div>
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

</body>
</html>