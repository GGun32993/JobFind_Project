<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "location_schema.php";
require_once "profile_image_helpers.php";

ensure_location_schema($conn);
ensure_profile_image_schema($conn);

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

$user         = mysqli_query($conn,"SELECT * FROM users WHERE user_id='$user_id'");
$user_data    = mysqli_fetch_assoc($user);
$profile_image = trim($user_data['profile_image'] ?? '');

$profile      = mysqli_query($conn,"SELECT * FROM freelancer_profile WHERE user_id='$user_id'");
$profile_data = mysqli_fetch_assoc($profile);
$has_profile  = (bool)$profile_data;

if(!$profile_data){
    $profile_data = [
        "skill"=>"",
        "experience"=>"",
        "location"=>"",
        "address"=>"",
        "province"=>"",
        "district"=>"",
        "postal_code"=>"",
        "latitude"=>null,
        "longitude"=>null,
        "preferred_radius_km"=>30
    ];
}
if(empty($profile_data['latitude']) && !empty($user_data['latitude'])){
    $profile_data['latitude'] = $user_data['latitude'];
}
if(empty($profile_data['longitude']) && !empty($user_data['longitude'])){
    $profile_data['longitude'] = $user_data['longitude'];
}
if(empty($profile_data['preferred_radius_km'])){
    $profile_data['preferred_radius_km'] = 30;
}

$toast = $_GET['toast'] ?? '';
$success = $toast === 'profile_saved';
$image_deleted = $toast === 'profile_image_deleted';
$image_delete_err = $toast === 'profile_image_delete_failed';
$dup_err = false;
$image_err = '';

if(isset($_POST['delete_profile_image'])){
    if($profile_image !== '' && mysqli_query($conn,"UPDATE users SET profile_image=NULL WHERE user_id='$user_id'")){
        delete_profile_image_file($profile_image);
        header("Location: my_profile.php?toast=profile_image_deleted");
        exit();
    }

    header("Location: my_profile.php?toast=profile_image_delete_failed");
    exit();
}

if(isset($_POST['update'])){
    $new_username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email        = mysqli_real_escape_string($conn, trim($_POST['email']));
    $fullname     = mysqli_real_escape_string($conn, $_POST['fullname']);
    $phone        = mysqli_real_escape_string($conn, $_POST['phone']);
    $skill        = mysqli_real_escape_string($conn, $_POST['skill']);
    $experience   = mysqli_real_escape_string($conn, $_POST['experience']);
    $address_raw  = trim($_POST['address'] ?? '');
    $province_raw = trim($_POST['province'] ?? '');
    $district_raw = trim($_POST['district'] ?? '');
    $location_raw = trim(implode(', ', array_filter([$district_raw, $province_raw])));
    if($location_raw === ''){
        $location_raw = $address_raw;
    }
    $location     = mysqli_real_escape_string($conn, $location_raw);
    $address      = mysqli_real_escape_string($conn, $address_raw);
    $province     = mysqli_real_escape_string($conn, $province_raw);
    $district     = mysqli_real_escape_string($conn, $district_raw);
    $postal_code  = mysqli_real_escape_string($conn, $_POST['postal_code'] ?? '');
    $latitude     = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude    = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $preferred_radius_km = isset($_POST['preferred_radius_km'])
        ? max(1, min(300, floatval($_POST['preferred_radius_km'])))
        : 30;
    $latitude_sql = $latitude !== null ? sprintf('%.8F', $latitude) : "NULL";
    $longitude_sql = $longitude !== null ? sprintf('%.8F', $longitude) : "NULL";
    $radius_sql = sprintf('%.2F', $preferred_radius_km);

    // เช็ค duplicate username และ email (ยกเว้นตัวเอง)
    $dup = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT user_id FROM users
        WHERE (username='$new_username' OR email='$email')
        AND user_id != '$user_id'
    "));

    if($dup){
        $dup_err = true;
    } else {
        $new_profile_image_path = '';
        if(profile_image_file_selected($_FILES['profile_image'] ?? [])){
            $new_profile_image_path = save_uploaded_profile_image($_FILES['profile_image'], $user_id, $image_err);
        }

        if($image_err !== ''){
            if($new_profile_image_path !== ''){
                delete_profile_image_file($new_profile_image_path);
            }
        } else {
            $profile_image_set = '';
            if($new_profile_image_path !== ''){
                $new_profile_image_sql = mysqli_real_escape_string($conn, $new_profile_image_path);
                $profile_image_set = ", profile_image='$new_profile_image_sql'";
            }

        mysqli_query($conn,"
            UPDATE users SET username='$new_username', email='$email', fullname='$fullname', phone='$phone',
                latitude=$latitude_sql, longitude=$longitude_sql
                $profile_image_set
            WHERE user_id='$user_id'
        ");

        $profile_exists = mysqli_fetch_assoc(mysqli_query($conn,"SELECT freelancer_id FROM freelancer_profile WHERE user_id='$user_id' LIMIT 1"));
        if($profile_exists){
            mysqli_query($conn,"
                UPDATE freelancer_profile
                SET skill='$skill', experience='$experience', location='$location',
                    address='$address', province='$province', district='$district', postal_code='$postal_code',
                    latitude=$latitude_sql, longitude=$longitude_sql, preferred_radius_km=$radius_sql
                WHERE user_id='$user_id'
            ");
        } else {
            mysqli_query($conn,"
                INSERT INTO freelancer_profile
                    (user_id, skill, experience, location, address, province, district, postal_code, latitude, longitude, preferred_radius_km)
                VALUES
                    ('$user_id', '$skill', '$experience', '$location', '$address', '$province', '$district', '$postal_code', $latitude_sql, $longitude_sql, $radius_sql)
            ");
        }
        $_SESSION['username'] = $new_username;
        $username = $new_username;
        if($new_profile_image_path !== ''){
            delete_profile_image_file($profile_image);
        }
        header("Location: my_profile.php?toast=profile_saved");
        exit();
        }
    }
}

$initials = profile_initials($user_data['fullname'] ?: $username);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.min.css" />
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:   #0f172a;
    --navy2:  #1e293b;
    --navy3:  #334155;
    --accent: #6366f1;
    --light:  #f1f5f9;
    --white:  #ffffff;
    --text:   #0f172a;
    --muted:  #64748b;
    --border: #e2e8f0;
    --green:  #10b981;
    --radius: 14px;
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
  .nav-item:hover { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; min-height:100vh; }

  /* ── Topbar ── */
  .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Avatar banner ── */
  .profile-banner {
    background:var(--white); border:1px solid var(--border);
    border-radius:var(--radius); padding:28px 28px 24px;
    display:flex; align-items:center; gap:22px;
    margin-bottom:20px;
  }
  .avatar-circle {
    width:72px; height:72px; border-radius:50%;
    background:var(--accent); color:#fff;
    font-size:26px; font-weight:600;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; letter-spacing:1px;
    overflow:hidden;
  }
  .avatar-circle img { width:100%; height:100%; object-fit:cover; display:block; }
  .banner-info h3 { font-size:18px; font-weight:600; margin-bottom:4px; }
  .banner-info p  { font-size:13px; color:var(--muted); }
  .banner-tags { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
  .btag {
    font-size:11.5px; font-weight:500; padding:4px 12px;
    border-radius:20px; background:var(--light); color:var(--muted);
    display:flex; align-items:center; gap:5px;
  }

  /* ── Form card ── */
  .form-card {
    background:var(--white); border:1px solid var(--border);
    border-radius:var(--radius); padding:28px;
  }
  .profile-image-editor {
    display:grid;
    grid-template-columns:84px 1fr;
    gap:16px;
    align-items:center;
    padding:16px;
    border:1px solid var(--border);
    border-radius:12px;
    background:#f8fafc;
    margin-bottom:18px;
  }
  .profile-image-preview {
    width:84px; height:84px; border-radius:18px;
    overflow:hidden; display:flex; align-items:center; justify-content:center;
    background:var(--accent); color:#fff; font-size:24px; font-weight:700;
  }
  .profile-image-preview img { width:100%; height:100%; object-fit:cover; display:block; }
  .profile-image-copy strong { display:block; font-size:14px; margin-bottom:4px; color:var(--text); }
  .profile-image-copy p { margin:0 0 10px; font-size:12.5px; color:var(--muted); line-height:1.6; }
  .profile-file-input { width:100%; max-width:360px; font-size:13px; }
  .image-delete-form { display:flex; justify-content:flex-end; margin:-4px 0 18px; }
  .btn-delete-image {
    display:inline-flex; align-items:center; gap:7px;
    background:#fff1f2; color:#be123c; border:1px solid #fecdd3;
    border-radius:10px; padding:9px 14px; font-size:12.5px; font-weight:700;
    cursor:pointer; transition:background .15s, transform .1s;
  }
  .btn-delete-image:hover { background:#ffe4e6; transform:translateY(-1px); }
  .section-title {
    font-size:13px; font-weight:600; color:var(--muted);
    text-transform:uppercase; letter-spacing:.05em;
    margin-bottom:16px; display:flex; align-items:center; gap:8px;
  }
  .section-title::after {
    content:''; flex:1; height:1px; background:var(--border);
  }

  .field-group { margin-bottom:18px; }
  .field-group label {
    display:block; font-size:13px; font-weight:500;
    color:var(--text); margin-bottom:6px;
  }
  .field-group label span { color:var(--muted); font-weight:400; font-size:12px; }

  .form-input {
    width:100%; padding:11px 14px;
    border:1px solid var(--border); border-radius:10px;
    font-family:'Sora',sans-serif; font-size:14px;
    color:var(--text); background:var(--white);
    outline:none; transition:border-color .15s, box-shadow .15s;
  }
  .form-input:focus {
    border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(99,102,241,.12);
  }
  .form-input[readonly] {
    background:var(--light); color:var(--muted); cursor:not-allowed;
  }
  textarea.form-input { resize:vertical; min-height:90px; line-height:1.6; }

  .input-icon-wrap { position:relative; }
  .input-icon-wrap i {
    position:absolute; left:13px; top:50%;
    transform:translateY(-50%); font-size:16px;
    color:var(--muted); pointer-events:none;
  }
  .input-icon-wrap .form-input { padding-left:38px; }

  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

  /* ── Toast ── */
  .toast-bar {
    position:fixed; top:24px; right:24px; z-index:999;
    background:#0f172a; color:#fff;
    padding:14px 20px; border-radius:12px;
    display:flex; align-items:center; gap:10px;
    font-size:14px; font-weight:500;
    box-shadow:0 8px 24px rgba(0,0,0,.18);
    animation:slideIn .3s ease;
  }
  .toast-bar i { color:var(--green); font-size:18px; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* ── Submit button ── */
  .btn-save {
    display:inline-flex; align-items:center; gap:8px;
    background:var(--accent); color:#fff;
    border:none; border-radius:10px;
    padding:12px 28px; font-size:14px; font-weight:600;
    font-family:'Sora',sans-serif; cursor:pointer;
    transition:background .15s, transform .1s;
  }
  .btn-save:hover { background:#4f46e5; transform:translateY(-1px); }
  .btn-save:active { transform:scale(.98); }
  .profile-actions { display:flex; justify-content:space-between; align-items:center; gap:14px; padding-top:8px; flex-wrap:wrap; }
  .profile-status { display:flex; align-items:center; gap:8px; color:var(--muted); font-size:12.5px; line-height:1.5; }
  .profile-status i { color:var(--accent); }

  /* ── Map Modal ── */
  .map-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; }
  .map-modal.active { display:flex; }
  .map-container { background:white; border-radius:16px; width:90%; max-width:900px; height:90vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.3); }
  .map-header { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
  .map-header h3 { margin:0; font-size:18px; font-weight:600; }
  .map-close { background:none; border:none; font-size:24px; cursor:pointer; color:var(--muted); }
  .map-close:hover { color:var(--text); }
  #freelancer-map { flex:1; }
  .map-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end; }
  .map-footer button { padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:500; font-size:14px; }
  .btn-map-confirm { background:var(--accent); color:white; }
  .btn-map-confirm:hover { background:#4f46e5; }
  .btn-map-cancel { background:var(--light); color:var(--text); }
  .btn-map-cancel:hover { background:#e2e8f0; }
  .map-info { padding:12px 16px; background:#eef2ff; border-radius:8px; font-size:13px; margin-bottom:12px; }
  .btn-open-map { display:inline-flex; align-items:center; gap:6px; background:#eef2ff; color:var(--accent); border:1px solid #c7d2fe; border-radius:8px; padding:8px 14px; font-size:13px; font-weight:500; cursor:pointer; transition:all .15s; }
  .btn-open-map:hover { background:#c7d2fe; color:var(--accent); }
  .coord-display { font-size:12px; color:var(--muted); margin-top:6px; }
  .radius-row { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:8px; }
  .radius-row span { font-size:12px; color:var(--muted); }
  .radius-value { font-size:13px; font-weight:700; color:var(--accent); white-space:nowrap; }
  .radius-slider { width:100%; accent-color:var(--accent); }
  .radius-scale { display:flex; justify-content:space-between; gap:10px; margin-top:6px; font-size:11px; color:var(--muted); }
  .map-radius-control {
    margin:0 16px 16px;
    padding:14px 16px;
    border:1px solid var(--border);
    border-radius:12px;
    background:#f8fafc;
    box-shadow:0 8px 18px rgba(15,23,42,.05);
  }
  .map-radius-control .radius-slider { height:28px; cursor:pointer; }

  @media(max-width:768px){
    .sidebar { display:none; }
    .main { margin-left:0; padding:20px 16px; }
    .two-col { grid-template-columns:1fr; }
    .profile-banner { flex-wrap:wrap; }
    .profile-image-editor { grid-template-columns:1fr; justify-items:start; }
    .profile-actions { align-items:stretch; flex-direction:column; }
    .btn-save { justify-content:center; width:100%; }
    .map-container { width:100%; height:100%; max-width:none; border-radius:0; }
  }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<?php if($success): ?>
<div class="toast-bar" id="toast">
  <i class="bi bi-check-circle-fill"></i> อัปเดตโปรไฟล์สำเร็จแล้ว
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; }, 3000);</script>
<?php elseif($image_deleted): ?>
<div class="toast-bar" id="toast">
  <i class="bi bi-check-circle-fill"></i> ลบรูปโปรไฟล์แล้ว
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; }, 3000);</script>
<?php elseif($dup_err): ?>
<div class="toast-bar" id="toast" style="background:#7f1d1d;">
  <i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;font-size:18px;"></i> Username นี้ถูกใช้งานแล้ว กรุณาเปลี่ยน
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; }, 4000);</script>
<?php elseif($image_delete_err || $image_err !== ''): ?>
<div class="toast-bar" id="toast" style="background:#7f1d1d;">
  <i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;font-size:18px;"></i> <?php echo htmlspecialchars($image_err ?: 'ลบรูปโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่'); ?>
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; }, 4000);</script>
<?php endif; ?>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div>
        <div class="logo-text">FreelanceHub</div>
        <div class="logo-sub">Freelancer</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="freelancer_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="browse_jobs.php"          class="nav-item"><i class="bi bi-briefcase"></i> Browse Jobs</a>
    <a href="my_applications.php"      class="nav-item"><i class="bi bi-file-earmark-text"></i> My Applications</a>
    <a href="my_profile.php"           class="nav-item active"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="freelancer_reviews.php"   class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="upload_resume.php"        class="nav-item"><i class="bi bi-cloud-upload"></i> Upload Resume</a>
    <div class="nav-divider"></div>
    <a href="support_chat.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <div class="topbar">
    <div>
      <h2>My Profile</h2>
      <p>จัดการข้อมูลส่วนตัวและทักษะของคุณ</p>
    </div>
  </div>

  <!-- Avatar banner -->
  <div class="profile-banner">
    <div class="avatar-circle">
      <?php if($profile_image !== ''): ?>
        <img src="<?php echo profile_image_src($profile_image); ?>" alt="Profile image">
      <?php else: ?>
        <?php echo $initials ?: '?'; ?>
      <?php endif; ?>
    </div>
    <div class="banner-info">
      <h3><?php echo htmlspecialchars($user_data['fullname'] ?: $username); ?></h3>
      <p>@<?php echo htmlspecialchars($username); ?></p>
      <div class="banner-tags">
        <?php if(!empty($profile_data['skill'])): ?>
        <span class="btag"><i class="bi bi-tools"></i><?php echo htmlspecialchars($profile_data['skill']); ?></span>
        <?php endif; ?>
        <?php if(!empty($user_data['phone'])): ?>
        <span class="btag"><i class="bi bi-telephone"></i><?php echo htmlspecialchars($user_data['phone']); ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if($profile_image !== ''): ?>
  <form method="POST" class="image-delete-form"
        onsubmit="return confirm('ยืนยันลบรูปโปรไฟล์?');">
    <button type="submit" name="delete_profile_image" class="btn-delete-image">
      <i class="bi bi-trash"></i> ลบรูปโปรไฟล์
    </button>
  </form>
  <?php endif; ?>

  <!-- Form -->
  <form method="POST" enctype="multipart/form-data">
  <div class="form-card">

    <div class="section-title"><i class="bi bi-image"></i> รูปโปรไฟล์</div>
    <div class="profile-image-editor">
      <div class="profile-image-preview">
        <?php if($profile_image !== ''): ?>
          <img src="<?php echo profile_image_src($profile_image); ?>" alt="Profile image preview">
        <?php else: ?>
          <?php echo $initials ?: '?'; ?>
        <?php endif; ?>
      </div>
      <div class="profile-image-copy">
        <strong>อัปโหลดหรือเปลี่ยนรูปโปรไฟล์</strong>
        <p>รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 3MB รูปใหม่จะแทนที่รูปเดิมเมื่อกดบันทึก</p>
        <input class="form-control profile-file-input" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
      </div>
    </div>

    <!-- Account info -->
    <div class="section-title"><i class="bi bi-person"></i> ข้อมูลบัญชี</div>

    <div class="field-group">
      <label>Username</label>
      <div class="input-icon-wrap">
        <i class="bi bi-at"></i>
        <input class="form-input" type="text" name="username"
               value="<?php echo htmlspecialchars($username); ?>"
               placeholder="username" required maxlength="50">
      </div>
    </div>

    <div class="two-col">
      <div class="field-group">
        <label>Full Name</label>
        <div class="input-icon-wrap">
          <i class="bi bi-person"></i>
          <input class="form-input" type="text" name="fullname"
                 value="<?php echo htmlspecialchars($user_data['fullname']); ?>"
                 placeholder="ชื่อ-นามสกุล">
        </div>
      </div>
      <div class="field-group">
        <label>Phone</label>
        <div class="input-icon-wrap">
          <i class="bi bi-telephone"></i>
          <input class="form-input" type="text" name="phone"
                 value="<?php echo htmlspecialchars($user_data['phone']); ?>"
                 placeholder="0xx-xxx-xxxx">
        </div>
      </div>
    </div>

    <div class="field-group">
      <label>Email</label>
      <div class="input-icon-wrap">
        <i class="bi bi-envelope"></i>
        <input class="form-input" type="email" name="email"
               value="<?php echo htmlspecialchars($user_data['email']); ?>"
               placeholder="email@example.com" required>
      </div>
    </div>

    <!-- Freelancer info -->
    <div class="section-title" style="margin-top:8px;"><i class="bi bi-tools"></i> ข้อมูล Freelancer</div>

    <div class="field-group">
      <label>Skills <span>(คั่นด้วยคอมม่า เช่น PHP, React, Figma)</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-lightning-charge"></i>
        <input class="form-input" type="text" name="skill"
               value="<?php echo htmlspecialchars($profile_data['skill']); ?>"
               placeholder="เช่น PHP, JavaScript, Figma">
      </div>
    </div>

    <div class="field-group">
      <label>Experience</label>
      <textarea class="form-input" name="experience"
                placeholder="เล่าประสบการณ์การทำงานของคุณ..."><?php echo htmlspecialchars($profile_data['experience']); ?></textarea>
    </div>

    <!-- Detailed Address -->
    <div class="section-title" style="margin-top:16px;"><i class="bi bi-map"></i> ที่อยู่โดยละเอียด</div>

    <div class="field-group">
      <label>ที่อยู่</label>
      <textarea class="form-input" name="address" placeholder="เช่น 123 ซ.สุขุมวิท ถ.สุขุมวิท..."><?php echo htmlspecialchars($profile_data['address'] ?? ''); ?></textarea>
    </div>

    <div class="two-col">
      <div class="field-group">
        <label>จังหวัด</label>
        <div class="input-icon-wrap">
          <i class="bi bi-building"></i>
          <select class="form-input" name="province" id="profile-province">
            <option value="">เลือกจังหวัด</option>
          </select>
        </div>
      </div>
      <div class="field-group">
        <label>อำเภอ</label>
        <div class="input-icon-wrap">
          <i class="bi bi-pin-map"></i>
          <select class="form-input" name="district" id="profile-district" disabled>
            <option value="">เลือกจังหวัดก่อน</option>
          </select>
        </div>
      </div>
    </div>

    <div class="field-group">
      <label>ปักหมุดตำแหน่งบนแผนที่</label>
      <button type="button" class="btn-open-map" onclick="openMapModal()">
        <i class="bi bi-geo"></i> เลือกตำแหน่งจากแผนที่
      </button>
      <div class="coord-display" id="coord-display">
        <?php 
          if (!empty($profile_data['latitude']) && !empty($profile_data['longitude'])) {
            echo "ปักหมุดแล้ว";
          } else {
            echo "ยังไม่ได้ปักหมุด";
          }
        ?>
      </div>
      <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($profile_data['latitude'] ?? ''); ?>">
      <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($profile_data['longitude'] ?? ''); ?>">
      <input type="hidden" name="preferred_radius_km" id="preferred_radius_km" value="<?php echo htmlspecialchars($profile_data['preferred_radius_km'] ?? 30); ?>">
    </div>

    <div class="field-group">
      <label>รหัสไปรษณีย์</label>
      <div class="input-icon-wrap">
        <i class="bi bi-mailbox"></i>
        <input class="form-input" type="text" name="postal_code" id="profile-postal-code"
               value="<?php echo htmlspecialchars($profile_data['postal_code'] ?? ''); ?>"
               placeholder="10110">
      </div>
    </div>

    <div class="profile-actions">
      <div class="profile-status">
        <?php if($has_profile): ?>
          <i class="bi bi-check-circle-fill"></i> โปรไฟล์ Freelancer พร้อมใช้งานและสามารถแก้ไขได้
        <?php else: ?>
          <i class="bi bi-info-circle-fill"></i> ยังไม่ได้ตั้งโปรไฟล์ กดบันทึกเพื่อสร้างโปรไฟล์ใหม่
        <?php endif; ?>
      </div>
      <button type="submit" name="update" class="btn-save">
        <i class="bi bi-check-lg"></i> <?php echo $has_profile ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างโปรไฟล์'; ?>
      </button>
    </div>

  </div>
  </form>

  <!-- Map Modal -->
  <div class="map-modal" id="mapModal">
    <div class="map-container">
      <div class="map-header">
        <h3><i class="bi bi-geo"></i> เลือกตำแหน่งตัวคุณ</h3>
        <button class="map-close" onclick="closeMapModal()">&times;</button>
      </div>
      <div style="padding:16px;">
        <div class="map-info">
          💡 ปักหมุด (กด) บนแผนที่เพื่อเลือกตำแหน่งของคุณ
        </div>
      </div>
      <div class="map-radius-control">
        <div class="radius-row">
          <span>วงค้นหางานบนแผนที่</span>
          <strong class="radius-value"><span id="map-radius-label"><?php echo htmlspecialchars($profile_data['preferred_radius_km'] ?? 30); ?></span> กม.</strong>
        </div>
        <input class="radius-slider" type="range" id="map_preferred_radius_km"
               min="1" max="300" step="1"
               value="<?php echo htmlspecialchars($profile_data['preferred_radius_km'] ?? 30); ?>"
               oninput="updateRadiusLabel(this.value)">
        <div class="radius-scale">
          <span>1 กม.</span>
          <span>300 กม.</span>
        </div>
      </div>
      <div id="freelancer-map"></div>
      <div class="map-footer">
        <button type="button" class="btn-map-cancel" onclick="closeMapModal()">ยกเลิก</button>
        <button type="button" class="btn-map-confirm" onclick="confirmMapLocation()">ยืนยัน</button>
      </div>
    </div>
  </div>

</main>

<script src="assets/vendor/leaflet/leaflet.min.js"></script>
<script src="assets/js/location-map-picker.js"></script>
<script src="assets/js/thai-location-selects.js"></script>
<script>
initThaiProvinceDistrictSelects({
  provinceId: 'profile-province',
  districtId: 'profile-district',
  postalCodeId: 'profile-postal-code',
  currentProvince: <?php echo json_encode($profile_data['province'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
  currentDistrict: <?php echo json_encode($profile_data['district'] ?? '', JSON_UNESCAPED_UNICODE); ?>
});

let mapInstance = null;
let selectedLat = <?php echo !empty($profile_data['latitude']) ? $profile_data['latitude'] : '13.7563'; ?>;
let selectedLng = <?php echo !empty($profile_data['longitude']) ? $profile_data['longitude'] : '100.5018'; ?>;
let hasSelectedPin = <?php echo (!empty($profile_data['latitude']) && !empty($profile_data['longitude'])) ? 'true' : 'false'; ?>;
let selectedRadiusKm = Number(document.getElementById('preferred_radius_km')?.value || 30);

function setSelectedPosition(lat, lng) {
  selectedLat = Number(lat);
  selectedLng = Number(lng);
  hasSelectedPin = true;
}

function updateRadiusLabel(value) {
  selectedRadiusKm = Math.max(1, Math.min(300, Number(value) || 30));
  const radiusInput = document.getElementById('preferred_radius_km');
  const mapRadiusInput = document.getElementById('map_preferred_radius_km');
  const radiusLabel = document.getElementById('radius-label');
  const mapRadiusLabel = document.getElementById('map-radius-label');

  if (radiusInput) radiusInput.value = selectedRadiusKm;
  if (mapRadiusInput) mapRadiusInput.value = selectedRadiusKm;
  if (radiusLabel) radiusLabel.textContent = selectedRadiusKm;
  if (mapRadiusLabel) mapRadiusLabel.textContent = selectedRadiusKm;
  if (mapInstance) {
    mapInstance.setRadius(selectedRadiusKm);
  }
}

function openMapModal() {
  const modal = document.getElementById('mapModal');
  modal.classList.add('active');
  
  setTimeout(() => {
    if (!mapInstance) {
      mapInstance = createJobFindMapPicker({
        elementId: 'freelancer-map',
        lat: selectedLat,
        lng: selectedLng,
        hasPin: hasSelectedPin,
        radiusKm: selectedRadiusKm,
        showCircle: true,
        onChange: setSelectedPosition
      });
    }
    
    if (mapInstance) {
      mapInstance.resize();
    }
    if (hasSelectedPin) {
      mapInstance.setView(selectedLat, selectedLng);
      mapInstance.setRadius(selectedRadiusKm);
    }
  }, 100);
}

function closeMapModal() {
  document.getElementById('mapModal').classList.remove('active');
}

function confirmMapLocation() {
  if (hasSelectedPin) {
    document.getElementById('latitude').value = selectedLat.toFixed(6);
    document.getElementById('longitude').value = selectedLng.toFixed(6);
    document.getElementById('coord-display').textContent = 'ปักหมุดแล้ว';
    closeMapModal();
  } else {
    alert('กรุณาเลือกตำแหน่งบนแผนที่');
  }
}

// Close modal when clicking outside
document.getElementById('mapModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeMapModal();
  }
});

updateRadiusLabel(selectedRadiusKm);
</script>
</body>
</html>
