<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../helpers/support_helpers.php";
require_once __DIR__ . "/../helpers/location_schema.php";
require_once __DIR__ . "/../helpers/profile_image_helpers.php";

$admin_id_for_badge = jobfind_require_role('admin');

ensure_location_schema($conn);
ensure_profile_image_schema($conn);

$admin_unread_support = admin_unread_support_count($conn, $admin_id_for_badge);

$toast = '';
$edit_user_id = $_GET['id'] ?? 0;

// ==========================================
// จัดการเมื่อมีการกดปุ่ม "บันทึกการแก้ไข" (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, jobfind_digits_only($_POST['phone'] ?? ''));
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $role = mysqli_real_escape_string($conn, trim($_POST['role']));
    
    // 1. อัปเดตตาราง users
    $sql_user = "UPDATE Users SET username=?, email=?, phone=?, fullname=?, role=? WHERE user_id=?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("sssssi", $username, $email, $phone, $fullname, $role, $edit_user_id);
    $user_updated = $stmt_user->execute();
    
    // 2. อัปเดตตาม role
    if ($role === 'freelancer') {
        $skill = mysqli_real_escape_string($conn, trim($_POST['skill']));
        $experience = mysqli_real_escape_string($conn, trim($_POST['experience']));
        $location = mysqli_real_escape_string($conn, trim($_POST['location']));
        
        $check_sql = "SELECT freelancer_id FROM Freelancer_Profile WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $edit_user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $sql_freelancer = "UPDATE Freelancer_Profile SET skill=?, experience=?, location=? WHERE user_id=?";
            $stmt_freelancer = $conn->prepare($sql_freelancer);
            $stmt_freelancer->bind_param("sssi", $skill, $experience, $location, $edit_user_id);
            $profile_updated = $stmt_freelancer->execute();
        } else {
            $sql_freelancer = "INSERT INTO Freelancer_Profile (user_id, skill, experience, location) VALUES (?, ?, ?, ?)";
            $stmt_freelancer = $conn->prepare($sql_freelancer);
            $stmt_freelancer->bind_param("isss", $edit_user_id, $skill, $experience, $location);
            $profile_updated = $stmt_freelancer->execute();
        }
    } elseif ($role === 'employer') {
        $employer_name = mysqli_real_escape_string($conn, trim($_POST['employer_name']));
        $employer_description = mysqli_real_escape_string($conn, trim($_POST['employer_description']));
        
        $check_sql = "SELECT employer_id FROM Employer_Profile WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $edit_user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $sql_employer = "UPDATE Employer_Profile SET employer_name=?, employer_description=? WHERE user_id=?";
            $stmt_employer = $conn->prepare($sql_employer);
            $stmt_employer->bind_param("ssi", $employer_name, $employer_description, $edit_user_id);
            $profile_updated = $stmt_employer->execute();
        } else {
            $sql_employer = "INSERT INTO Employer_Profile (user_id, employer_name, employer_description) VALUES (?, ?, ?)";
            $stmt_employer = $conn->prepare($sql_employer);
            $stmt_employer->bind_param("iss", $edit_user_id, $employer_name, $employer_description);
            $profile_updated = $stmt_employer->execute();
        }
    } else {
        $profile_updated = true;
    }
    
    if ($user_updated && $profile_updated) {
        $toast = 'success';
    } else {
        $toast = 'error';
    }
    header("Location: edit_user.php?id=$edit_user_id&toast=$toast"); exit();
}

// ==========================================
// ดึงข้อมูลเดิมมาแสดงในฟอร์ม (GET)
// ==========================================
$sql_fetch = "SELECT * FROM Users WHERE user_id = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $edit_user_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();

if($result->num_rows === 0){
    echo "<script>alert('ไม่พบผู้ใช้งานนี้'); window.location.href='manage_users.php';</script>";
    exit();
}
$user_data = $result->fetch_assoc();
$user_role = $user_data['role'];

$profile_data = null;
if ($user_role === 'freelancer') {
    $sql_profile = "SELECT * FROM Freelancer_Profile WHERE user_id = ?";
    $stmt_profile = $conn->prepare($sql_profile);
    $stmt_profile->bind_param("i", $edit_user_id);
    $stmt_profile->execute();
    $profile_result = $stmt_profile->get_result();
    $profile_data = $profile_result->fetch_assoc();
} elseif ($user_role === 'employer') {
    $sql_profile = "SELECT * FROM Employer_Profile WHERE user_id = ?";
    $stmt_profile = $conn->prepare($sql_profile);
    $stmt_profile->bind_param("i", $edit_user_id);
    $stmt_profile->execute();
    $profile_result = $stmt_profile->get_result();
    $profile_data = $profile_result->fetch_assoc();
}

$profile_address = trim($profile_data['address'] ?? '');
$profile_postal_code = trim($profile_data['postal_code'] ?? '');
$profile_location_parts = array_filter([
    trim($profile_data['district'] ?? ''),
    trim($profile_data['province'] ?? '')
]);
$profile_location_display = !empty($profile_location_parts)
    ? implode(', ', $profile_location_parts)
    : trim($profile_data['location'] ?? '');
if ($profile_location_display === '' && $profile_address !== '') {
    $profile_location_display = $profile_address;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=14">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit User - Freelance Matching Online Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

  :root {
    --navy:#0f172a; --navy2:#1e293b; --navy3:#334155;
    --accent:#6366f1; --light:#f1f5f9; --white:#ffffff;
    --text:#0f172a; --muted:#64748b; --border:#e2e8f0;
    --green:#10b981; --red:#ef4444; --yellow:#f59e0b; --radius:14px;
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

  /* ── Main ─ */
  .main { margin-left:240px; flex:1; padding:36px 40px; }

  /* ── Topbar ── */
  .topbar { margin-bottom:28px; }
  .topbar-wrap { display:flex; align-items:center; gap:16px; }
  .btn-back { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:13px; color:var(--muted); text-decoration:none; background:var(--white); transition:all .15s; }
  .btn-back:hover { background:var(--light); color:var(--text); border-color:var(--navy3); }
  .topbar h2 { font-size:22px; font-weight:600; margin:0; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Toast ── */
  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
  .toast-bar i { font-size:18px; }
  .toast-ok  { background:var(--navy); }
  .toast-err { background:#7f1d1d; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* ── Layout 2 col ── */
  .layout { display:grid; grid-template-columns:380px 1fr; gap:24px; align-items:start; }

  /* ── Form card ── */
  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:24px; position:sticky; top:36px; }
  .form-card h4 { font-size:15px; font-weight:600; margin-bottom:18px; display:flex; align-items:center; gap:8px; }

  .field-group { margin-bottom:16px; }
  .field-group label { display:block; font-size:13px; font-weight:500; margin-bottom:6px; }
  .field-group label span { color:var(--muted); font-weight:400; font-size:12px; }
  .form-input { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; background:var(--light); }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); background:var(--white); }
  textarea.form-input { resize:vertical; min-height:80px; }

  .form-select { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; background:var(--light); }
  .form-select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); background:var(--white); }

  .form-actions { display:flex; gap:8px; margin-top:24px; }
  .btn-save { flex:1; padding:11px; background:var(--accent); color:#fff; border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:background .15s; }
  .btn-save:hover { background:#4f46e5; }
  .btn-cancel-edit { padding:11px 16px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--muted); text-decoration:none; display:flex; align-items:center; gap:5px; transition:background .15s; background:var(--white); }
  .btn-cancel-edit:hover { background:var(--light); color:var(--text); }

  /* ── Info card ── */
  .info-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:24px; }
  .info-card h4 { font-size:15px; font-weight:600; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
  
  .info-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--border); }
  .info-row:last-child { border-bottom:none; }
  .info-label { font-size:13px; color:var(--muted); }
  .info-value { font-size:14px; font-weight:500; text-align:right; max-width:60%; word-break:break-word; }
  
  .role-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; }
  .role-freelancer { background:#dbeafe; color:#1e40af; }
  .role-employer { background:#dcfce7; color:#166534; }
  .role-admin { background:#fef3c7; color:#92400e; }

  .section-divider { margin:20px 0; border:0; border-top:1px dashed var(--border); }

  @media(max-width:900px){ .layout { grid-template-columns:1fr; } .form-card { position:static; } }
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
</style>
<link rel="stylesheet" href="../assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Toast ── -->
<?php
if(isset($_GET['toast'])):
    $type = ($_GET['toast']==='success') ? 'ok' : 'err';
    $icon = ($type==='ok') ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    $msg = ($_GET['toast']==='success') ? 'บันทึกข้อมูลเรียบร้อยแล้ว' : 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
    $color = ($type==='ok') ? 'var(--green)' : '#fca5a5';
?>
<div class="toast-bar toast-<?php echo $type; ?>" id="toast">
  <i class="bi <?php echo $icon; ?>" style="color:<?php echo $color; ?>;"></i>
  <?php echo $msg; ?>
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php endif; ?>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="../index.php" class="logo">
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;margin:0 auto!important;align-self:center!important;"><img class="brand-logo-img" src="../assets/images/jobfind-logo.png?v=14" alt="Freelance Matching Online logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Freelance Matching Online</div>
        <div class="logo-sub" style="display:none!important;">Admin Panel</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"          class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="manage_users.php"       class="nav-item active"><i class="bi bi-people"></i> Manage Users</a>
    <a href="manage_jobs.php"        class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="manage_categories.php"  class="nav-item"><i class="bi bi-tag"></i> Categories</a>
    <div class="nav-divider"></div>
    <a href="support.php"            class="nav-item">
      <i class="bi bi-chat-dots"></i> Support Chat
      <?php if($admin_unread_support > 0): ?><span class="nav-badge"><?php echo $admin_unread_support; ?></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <div class="topbar">
    <div class="topbar-wrap">
      <a href="manage_users.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> กลับ
      </a>
      <div>
        <h2>Edit User</h2>
        <p>แก้ไขข้อมูลบัญชีผู้ใช้งานในระบบ (#ID: <?php echo $edit_user_id; ?>)</p>
      </div>
    </div>
  </div>

  <div class="layout">

    <!-- Form (Edit User) -->
    <div class="form-card">
      <h4><i class="bi bi-pencil" style="color:var(--accent);"></i> แก้ไขข้อมูลผู้ใช้</h4>
      <form method="POST">

        <div class="field-group">
          <label>Username</label>
          <input type="text" name="username" class="form-input"
                 value="<?php echo htmlspecialchars($user_data['username']); ?>"
                 required maxlength="100">
        </div>

        <div class="field-group">
          <label>Email</label>
          <input type="email" name="email" class="form-input"
                 value="<?php echo htmlspecialchars($user_data['email']); ?>"
                 required maxlength="150">
        </div>

        <div class="field-group">
          <label>เบอร์โทรศัพท์</label>
          <input type="text" name="phone" class="form-input"
                 value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                 inputmode="numeric" pattern="[0-9]*"
                 oninput="this.value=this.value.replace(/\D/g,'')"
                 placeholder="เช่น 0812345678" maxlength="20">
        </div>

        <div class="field-group">
          <label>ชื่อ-นามสกุล</label>
          <input type="text" name="fullname" class="form-input"
                 value="<?php echo htmlspecialchars($user_data['fullname'] ?? ''); ?>"
                 placeholder="เช่น สมชาย ใจดี" maxlength="150">
        </div>

        <div class="field-group">
          <label>Role (สิทธิ์ผู้ใช้งาน)</label>
          <select name="role" id="roleSelect" class="form-select" required>
            <option value="freelancer" <?php echo ($user_data['role']==='freelancer')?'selected':''; ?>>Freelancer</option>
            <option value="employer" <?php echo ($user_data['role']==='employer')?'selected':''; ?>>Employer</option>
            <option value="admin" <?php echo ($user_data['role']==='admin')?'selected':''; ?>>Admin</option>
          </select>
        </div>

        <!-- ส่วนของ Freelancer -->
        <div id="freelancerSection" style="display:none;">
          <hr class="section-divider">
          <div class="field-group">
            <label>ทักษะ (Skill) <span>(คั่นด้วย ,)</span></label>
            <textarea name="skill" class="form-input" placeholder="เช่น PHP, Web Development, Graphic Design"><?php echo htmlspecialchars($profile_data['skill'] ?? ''); ?></textarea>
          </div>
          <div class="field-group">
            <label>ประสบการณ์</label>
            <input type="text" name="experience" class="form-input"
                   value="<?php echo htmlspecialchars($profile_data['experience'] ?? ''); ?>"
                   placeholder="เช่น 2 Years, Fresh Graduate">
          </div>
          <div class="field-group">
            <label>สถานที่</label>
            <input type="text" name="location" class="form-input"
                   value="<?php echo htmlspecialchars($profile_data['location'] ?? ''); ?>"
                   placeholder="เช่น Bangkok, Chiang Mai">
          </div>
        </div>

        <!-- ส่วนของ Employer -->
        <div id="employerSection" style="display:none;">
          <hr class="section-divider">
          <div class="field-group">
            <label>ชื่อบริษัท / Company Name</label>
            <input type="text" name="employer_name" class="form-input"
                   value="<?php echo htmlspecialchars($profile_data['employer_name'] ?? ''); ?>">
          </div>
          <div class="field-group">
            <label>รายละเอียดบริษัท</label>
            <textarea name="employer_description" class="form-input" rows="4" placeholder="แนะนำตัวให้ Freelancer รู้จักบริษัทคุณ"><?php echo htmlspecialchars($profile_data['employer_description'] ?? ''); ?></textarea>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-save">
            <i class="bi bi-check-lg"></i> บันทึก
          </button>
          <a href="manage_users.php" class="btn-cancel-edit">
            <i class="bi bi-x-lg"></i> ยกเลิก
          </a>
        </div>
      </form>
    </div>

    <!-- User Info Card -->
    <div class="info-card">
      <h4><i class="bi bi-info-circle" style="color:var(--accent);"></i> ข้อมูลผู้ใช้</h4>
      
      <div class="info-row">
        <span class="info-label">User ID</span>
        <span class="info-value">#<?php echo $edit_user_id; ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Role</span>
        <span class="info-value">
          <span class="role-badge role-<?php echo $user_role; ?>">
            <i class="bi bi-person-badge"></i>
            <?php echo ucfirst($user_role); ?>
          </span>
        </span>
      </div>
      <div class="info-row">
        <span class="info-label">Created</span>
        <span class="info-value"><?php echo !empty($user_data['created_at']) ? date('d M Y', strtotime($user_data['created_at'])) : '-'; ?></span>
      </div>
      
      <?php if($user_role === 'freelancer' && $profile_data): ?>
      <hr class="section-divider">
      <div class="info-row">
        <span class="info-label">Skill</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_data['skill'] ?? '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Experience</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_data['experience'] ?? '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Location</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_data['location'] ?? '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">ที่อยู่</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_address !== '' ? $profile_address : '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">จังหวัด / อำเภอ</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_location_display !== '' ? $profile_location_display : '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">รหัสไปรษณีย์</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_postal_code !== '' ? $profile_postal_code : '-'); ?></span>
      </div>
      <?php elseif($user_role === 'employer' && $profile_data): ?>
      <hr class="section-divider">
      <div class="info-row">
        <span class="info-label">Company</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_data['employer_name'] ?? '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">รายละเอียดบริษัท</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_data['employer_description'] ?? '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">ที่อยู่</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_address !== '' ? $profile_address : '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">จังหวัด / อำเภอ</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_location_display !== '' ? $profile_location_display : '-'); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">รหัสไปรษณีย์</span>
        <span class="info-value"><?php echo htmlspecialchars($profile_postal_code !== '' ? $profile_postal_code : '-'); ?></span>
      </div>
      <?php elseif($user_role !== 'admin'): ?>
      <hr class="section-divider">
      <div class="info-row">
        <span class="info-label">โปรไฟล์</span>
        <span class="info-value">ยังไม่มีโปรไฟล์</span>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<script>
  function toggleRoleSection() {
    const role = document.getElementById('roleSelect').value;
    const freelancerSection = document.getElementById('freelancerSection');
    const employerSection = document.getElementById('employerSection');
    
    freelancerSection.style.display = 'none';
    employerSection.style.display = 'none';
    
    if (role === 'freelancer') {
      freelancerSection.style.display = 'block';
    } else if (role === 'employer') {
      employerSection.style.display = 'block';
    }
  }
  
  // เรียกใช้ครั้งแรก
  toggleRoleSection();
  
  // เพิ่ม event listener
  document.getElementById('roleSelect').addEventListener('change', toggleRoleSection);
</script>
</body>
</html>
