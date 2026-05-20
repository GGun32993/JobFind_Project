<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: login.php"); exit();
}

$id = intval($_GET['id'] ?? 0);
if(!$id){ header("Location: admin_manage_users.php"); exit(); }

$user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE user_id='$id'"));
if(!$user){ header("Location: admin_manage_users.php"); exit(); }

$fl_profile = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM freelancer_profile WHERE user_id='$id'")) ?? ['skill'=>'','experience'=>'','location'=>''];
$em_profile = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM employer_profile WHERE user_id='$id'")) ?? ['employer_name'=>'','employer_description'=>''];

$success = false; $dup_err = false;

if(isset($_POST['save'])){
    $un   = mysqli_real_escape_string($conn, trim($_POST['username']));
    $em   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $fn   = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $ph   = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $pw   = trim($_POST['password']);

    $dup = mysqli_fetch_assoc(mysqli_query($conn,"SELECT user_id FROM users WHERE (username='$un' OR email='$em') AND user_id!='$id'"));
    if($dup){ $dup_err = true; }
    else {
        $pw_sql = $pw ? ", password='".mysqli_real_escape_string($conn,$pw)."'" : '';
        mysqli_query($conn,"UPDATE users SET username='$un', email='$em', fullname='$fn', phone='$ph', role='$role' $pw_sql WHERE user_id='$id'");

        if($role === 'freelancer'){
            $skill = mysqli_real_escape_string($conn, $_POST['skill'] ?? '');
            $exp   = mysqli_real_escape_string($conn, $_POST['experience'] ?? '');
            $loc   = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
            $ex = mysqli_fetch_assoc(mysqli_query($conn,"SELECT user_id FROM freelancer_profile WHERE user_id='$id'"));
            if($ex) mysqli_query($conn,"UPDATE freelancer_profile SET skill='$skill',experience='$exp',location='$loc' WHERE user_id='$id'");
            else mysqli_query($conn,"INSERT INTO freelancer_profile (user_id,skill,experience,location) VALUES ('$id','$skill','$exp','$loc')");
        }
        if($role === 'employer'){
            $co_name = mysqli_real_escape_string($conn, $_POST['employer_name'] ?? $fn);
            $co_desc = mysqli_real_escape_string($conn, $_POST['employer_description'] ?? '');
            $ex = mysqli_fetch_assoc(mysqli_query($conn,"SELECT user_id FROM employer_profile WHERE user_id='$id'"));
            if($ex) mysqli_query($conn,"UPDATE employer_profile SET employer_name='$co_name',employer_description='$co_desc' WHERE user_id='$id'");
            else mysqli_query($conn,"INSERT INTO employer_profile (user_id,employer_name,employer_description) VALUES ('$id','$co_name','$co_desc')");
        }
        $success = true;
        // refresh data
        $user       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE user_id='$id'"));
        $fl_profile = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM freelancer_profile WHERE user_id='$id'")) ?? ['skill'=>'','experience'=>'','location'=>''];
        $em_profile = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM employer_profile WHERE user_id='$id'")) ?? ['employer_name'=>'','employer_description'=>''];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root { --navy:#0f172a; --navy2:#1e293b; --navy3:#334155; --accent:#6366f1; --light:#f1f5f9; --white:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --green:#10b981; --red:#ef4444; --radius:14px; }
  body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; }

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

  .main { margin-left:240px; flex:1; padding:36px 40px; }
  .content-wrap { max-width:660px; }

  .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .btn-back { display:inline-flex; align-items:center; gap:7px; background:var(--white); border:1px solid var(--border); color:var(--text); border-radius:10px; padding:9px 18px; font-size:13.5px; font-weight:500; text-decoration:none; transition:background .15s; }
  .btn-back:hover { background:var(--light); color:var(--text); }

  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
  .toast-ok  { background:var(--navy); color:#fff; }
  .toast-err { background:#7f1d1d; color:#fff; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* User banner */
  .user-banner { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; gap:16px; }
  .u-avatar { width:52px; height:52px; border-radius:50%; font-size:18px; font-weight:600; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .ua-freelancer { background:var(--accent); }
  .ua-employer   { background:#0ea5e9; }
  .ua-admin      { background:var(--navy3); }
  .u-name  { font-size:16px; font-weight:600; }
  .u-email { font-size:13px; color:var(--muted); margin-top:2px; }

  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:28px; margin-bottom:16px; }
  .section-title { font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
  .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  .field-group { margin-bottom:16px; }
  .field-group label { display:block; font-size:13px; font-weight:500; margin-bottom:6px; }
  .field-group label span { color:var(--muted); font-weight:400; font-size:12px; }

  .input-wrap { position:relative; }
  .input-wrap i.prefix { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:15px; color:var(--muted); pointer-events:none; }
  .form-input { width:100%; padding:11px 14px 11px 38px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:13.5px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  .form-input.no-icon { padding-left:14px; }
  textarea.form-input { resize:vertical; min-height:90px; line-height:1.7; padding-left:14px; }

  /* Role select */
  .role-select { display:flex; gap:10px; }
  .role-opt { flex:1; }
  .role-opt input { display:none; }
  .role-opt label { display:flex; align-items:center; gap:8px; padding:10px 14px; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-size:13px; transition:all .15s; }
  .role-opt input:checked + label { border-color:var(--accent); background:#eef2ff; color:var(--accent); font-weight:600; }

  .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; border:none; border-radius:10px; padding:12px 28px; font-size:14px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; transition:background .15s; }
  .btn-save:hover { background:#4f46e5; }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .two-col { grid-template-columns:1fr; } }
</style>
</head>
<body>

<?php if($success): ?>
<div class="toast-bar toast-ok" id="toast"><i class="bi bi-check-circle-fill" style="color:var(--green);font-size:18px;"></i> บันทึกการเปลี่ยนแปลงแล้ว</div>
<?php elseif($dup_err): ?>
<div class="toast-bar toast-err" id="toast"><i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;font-size:18px;"></i> Username หรือ Email ซ้ำกับผู้ใช้อื่น</div>
<?php endif; ?>
<script>const t=document.getElementById('toast'); if(t) setTimeout(()=>t.style.opacity='0',3500);</script>

<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-shield-check"></i></div>
      <div><div class="logo-text">FreelanceHub</div><div class="logo-sub">Admin Panel</div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php"          class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="admin_manage_users.php"       class="nav-item active"><i class="bi bi-people"></i> Manage Users</a>
    <a href="admin_manage_jobs.php"        class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="admin_manage_categories.php"  class="nav-item"><i class="bi bi-tag"></i> Categories</a>
    <div class="nav-divider"></div>
    <a href="admin_support.php"            class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<main class="main">
<div class="content-wrap">

  <div class="topbar">
    <div>
      <h2>แก้ไขผู้ใช้</h2>
      <p>จัดการข้อมูลของ <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
    </div>
    <a href="admin_manage_users.php" class="btn-back"><i class="bi bi-arrow-left"></i> กลับ</a>
  </div>

  <!-- User banner -->
  <div class="user-banner">
    <div class="u-avatar ua-<?php echo $user['role']; ?>"><?php echo strtoupper(substr($user['fullname'] ?: $user['username'],0,2)); ?></div>
    <div>
      <div class="u-name"><?php echo htmlspecialchars($user['fullname'] ?: $user['username']); ?></div>
      <div class="u-email"><?php echo htmlspecialchars($user['email']); ?> &nbsp;·&nbsp; <?php echo ucfirst($user['role']); ?></div>
    </div>
  </div>

  <form method="POST">
  <!-- Account info -->
  <div class="form-card">
    <div class="section-title"><i class="bi bi-person"></i> ข้อมูลบัญชี</div>

    <div class="two-col">
      <div class="field-group">
        <label>Username</label>
        <div class="input-wrap"><i class="bi bi-at prefix"></i>
        <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username']); ?>" required></div>
      </div>
      <div class="field-group">
        <label>Email</label>
        <div class="input-wrap"><i class="bi bi-envelope prefix"></i>
        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
      </div>
    </div>

    <div class="two-col">
      <div class="field-group">
        <label>ชื่อ-นามสกุล / บริษัท</label>
        <div class="input-wrap"><i class="bi bi-person prefix"></i>
        <input type="text" name="fullname" class="form-input" value="<?php echo htmlspecialchars($user['fullname']); ?>"></div>
      </div>
      <div class="field-group">
        <label>เบอร์โทร</label>
        <div class="input-wrap"><i class="bi bi-telephone prefix"></i>
        <input type="text" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone']); ?>"></div>
      </div>
    </div>

    <div class="field-group">
      <label>รหัสผ่านใหม่ <span>(เว้นว่างถ้าไม่ต้องการเปลี่ยน)</span></label>
      <div class="input-wrap"><i class="bi bi-lock prefix"></i>
      <input type="password" name="password" class="form-input" placeholder="รหัสผ่านใหม่ (ไม่บังคับ)"></div>
    </div>

    <div class="field-group">
      <label>Role</label>
      <div class="role-select">
        <?php foreach(['freelancer'=>'💼','employer'=>'🏢','admin'=>'🛡️'] as $r=>$icon): ?>
        <div class="role-opt">
          <input type="radio" name="role" id="role-<?php echo $r; ?>" value="<?php echo $r; ?>" <?php echo $user['role']===$r?'checked':''; ?> onchange="toggleProfile()">
          <label for="role-<?php echo $r; ?>"><?php echo $icon.' '.ucfirst($r); ?></label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Freelancer profile -->
  <div class="form-card" id="fl-section" style="<?php echo $user['role']!=='freelancer'?'display:none;':''; ?>">
    <div class="section-title"><i class="bi bi-tools"></i> ข้อมูล Freelancer</div>
    <div class="field-group">
      <label>Skills</label>
      <input type="text" name="skill" class="form-input no-icon" placeholder="PHP, JavaScript, Figma" value="<?php echo htmlspecialchars($fl_profile['skill'] ?? ''); ?>">
    </div>
    <div class="field-group">
      <label>Location</label>
      <input type="text" name="location" class="form-input no-icon" placeholder="กรุงเทพฯ, Remote" value="<?php echo htmlspecialchars($fl_profile['location'] ?? ''); ?>">
    </div>
    <div class="field-group">
      <label>Experience</label>
      <textarea name="experience" class="form-input"><?php echo htmlspecialchars($fl_profile['experience'] ?? ''); ?></textarea>
    </div>
  </div>

  <!-- Employer profile -->
  <div class="form-card" id="em-section" style="<?php echo $user['role']!=='employer'?'display:none;':''; ?>">
    <div class="section-title"><i class="bi bi-building"></i> ข้อมูลบริษัท</div>
    <div class="field-group">
      <label>ชื่อบริษัท</label>
      <input type="text" name="employer_name" class="form-input no-icon" placeholder="ชื่อบริษัท" value="<?php echo htmlspecialchars($em_profile['employer_name'] ?? ''); ?>">
    </div>
    <div class="field-group">
      <label>รายละเอียดบริษัท</label>
      <textarea name="employer_description" class="form-input"><?php echo htmlspecialchars($em_profile['employer_description'] ?? ''); ?></textarea>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;">
    <button type="submit" name="save" class="btn-save">
      <i class="bi bi-check-lg"></i> บันทึกการเปลี่ยนแปลง
    </button>
  </div>
  </form>

</div>
</main>

<script>
  function toggleProfile(){
    const role = document.querySelector('input[name="role"]:checked')?.value;
    document.getElementById('fl-section').style.display = role==='freelancer' ? '' : 'none';
    document.getElementById('em-section').style.display = role==='employer'   ? '' : 'none';
  }
</script>
</body>
</html>