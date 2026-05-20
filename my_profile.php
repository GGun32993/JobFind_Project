<?php
session_start();
include("config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

$user         = mysqli_query($conn,"SELECT * FROM users WHERE user_id='$user_id'");
$user_data    = mysqli_fetch_assoc($user);

$profile      = mysqli_query($conn,"SELECT * FROM freelancer_profile WHERE user_id='$user_id'");
$profile_data = mysqli_fetch_assoc($profile);

if(!$profile_data){
    $profile_data = ["skill"=>"","experience"=>"","location"=>""];
}

$success = false;
$dup_err = false;
if(isset($_POST['update'])){
    $new_username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email        = mysqli_real_escape_string($conn, trim($_POST['email']));
    $fullname     = mysqli_real_escape_string($conn, $_POST['fullname']);
    $phone        = mysqli_real_escape_string($conn, $_POST['phone']);
    $skill        = mysqli_real_escape_string($conn, $_POST['skill']);
    $experience   = mysqli_real_escape_string($conn, $_POST['experience']);
    $location     = mysqli_real_escape_string($conn, $_POST['location']);

    // เช็ค duplicate username และ email (ยกเว้นตัวเอง)
    $dup = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT user_id FROM users
        WHERE (username='$new_username' OR email='$email')
        AND user_id != '$user_id'
    "));

    if($dup){
        $dup_err = true;
    } else {
        mysqli_query($conn,"
            UPDATE users SET username='$new_username', email='$email', fullname='$fullname', phone='$phone'
            WHERE user_id='$user_id'
        ");
        mysqli_query($conn,"
            UPDATE freelancer_profile
            SET skill='$skill', experience='$experience', location='$location'
            WHERE user_id='$user_id'
        ");
        $_SESSION['username'] = $new_username;
        $username = $new_username;
        $success = true;
        header("Refresh:0");
    }
}

$initials = strtoupper(substr($user_data['fullname'] ?: $username, 0, 2));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
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
  }
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

  @media(max-width:768px){
    .sidebar { display:none; }
    .main { margin-left:0; padding:20px 16px; }
    .two-col { grid-template-columns:1fr; }
    .profile-banner { flex-wrap:wrap; }
  }
</style>
</head>
<body>

<?php if($success): ?>
<div class="toast-bar" id="toast">
  <i class="bi bi-check-circle-fill"></i> อัปเดตโปรไฟล์สำเร็จแล้ว
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; }, 3000);</script>
<?php elseif($dup_err): ?>
<div class="toast-bar" id="toast" style="background:#7f1d1d;">
  <i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;font-size:18px;"></i> Username นี้ถูกใช้งานแล้ว กรุณาเปลี่ยน
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
        <div class="logo-sub">Dashboard</div>
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
    <div class="avatar-circle"><?php echo $initials ?: '?'; ?></div>
    <div class="banner-info">
      <h3><?php echo htmlspecialchars($user_data['fullname'] ?: $username); ?></h3>
      <p>@<?php echo htmlspecialchars($username); ?></p>
      <div class="banner-tags">
        <?php if(!empty($profile_data['location'])): ?>
        <span class="btag"><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($profile_data['location']); ?></span>
        <?php endif; ?>
        <?php if(!empty($profile_data['skill'])): ?>
        <span class="btag"><i class="bi bi-tools"></i><?php echo htmlspecialchars($profile_data['skill']); ?></span>
        <?php endif; ?>
        <?php if(!empty($user_data['phone'])): ?>
        <span class="btag"><i class="bi bi-telephone"></i><?php echo htmlspecialchars($user_data['phone']); ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Form -->
  <form method="POST">
  <div class="form-card">

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

    <div class="field-group">
      <label>Location</label>
      <div class="input-icon-wrap">
        <i class="bi bi-geo-alt"></i>
        <input class="form-input" type="text" name="location"
               value="<?php echo htmlspecialchars($profile_data['location']); ?>"
               placeholder="เช่น กรุงเทพฯ, เชียงใหม่">
      </div>
    </div>

    <div style="display:flex; justify-content:flex-end; padding-top:8px;">
      <button type="submit" name="update" class="btn-save">
        <i class="bi bi-check-lg"></i> บันทึกการเปลี่ยนแปลง
      </button>
    </div>

  </div>
  </form>

</main>
</body>
</html>