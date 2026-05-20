<?php
session_start();
include("config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "employer"){
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$success  = false;
$dup_err  = false;

// ── UPDATE PROFILE ──
if(isset($_POST['update'])){
    $new_username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $fullname    = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email       = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone       = mysqli_real_escape_string($conn, $_POST['phone']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // เช็ค duplicate username และ email
    $dup = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT user_id FROM users
        WHERE (username='$new_username' OR email='$email')
        AND user_id != '$user_id'
    "));

    if($dup){
        $dup_err = true;
    } else {
        mysqli_query($conn,"
            UPDATE users SET username='$new_username', fullname='$fullname', email='$email', phone='$phone'
            WHERE user_id='$user_id'
        ");
        $_SESSION['username'] = $new_username;

        $check = mysqli_query($conn,"SELECT * FROM employer_profile WHERE user_id='$user_id'");
        if(mysqli_num_rows($check) > 0){
            mysqli_query($conn,"
                UPDATE employer_profile SET
                employer_name='$fullname', employer_description='$description'
                WHERE user_id='$user_id'
            ");
        } else {
            mysqli_query($conn,"
                INSERT INTO employer_profile (user_id, employer_name, employer_description)
                VALUES ('$user_id','$fullname','$description')
            ");
        }
        $success = true;
        header("Refresh:0");
    }
}

// ── GET DATA ──
$user    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE user_id='$user_id'"));
$profile = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM employer_profile WHERE user_id='$user_id'"));

$initials = strtoupper(substr($user['fullname'] ?: $user['username'], 0, 2));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employer Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

  :root {
    --navy:   #0f172a;  --navy2:  #1e293b;  --navy3:  #334155;
    --accent: #6366f1;  --light:  #f1f5f9;  --white:  #ffffff;
    --text:   #0f172a;  --muted:  #64748b;  --border: #e2e8f0;
    --green:  #10b981;  --radius: 14px;
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
  .main { margin-left:240px; flex:1; padding:36px 40px; }
  .content-wrap { max-width:620px; }

  /* ── Topbar ── */
  .topbar { margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Toast ── */
  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
  .toast-bar i { color:var(--green); font-size:18px; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* ── Profile banner ── */
  .profile-banner { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:26px 28px; display:flex; align-items:center; gap:20px; margin-bottom:20px; flex-wrap:wrap; }
  .avatar-circle { width:72px; height:72px; border-radius:50%; background:var(--accent); color:#fff; font-size:26px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; letter-spacing:1px; }
  .banner-info h3 { font-size:18px; font-weight:600; margin-bottom:3px; }
  .banner-info p  { font-size:13px; color:var(--muted); }
  .banner-tags { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
  .btag { font-size:11.5px; font-weight:500; padding:4px 12px; border-radius:20px; background:var(--light); color:var(--muted); display:flex; align-items:center; gap:5px; }

  /* ── Form card ── */
  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:28px; }
  .section-title { font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
  .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

  .field-group { margin-bottom:18px; }
  .field-group label { display:block; font-size:13px; font-weight:500; color:var(--text); margin-bottom:6px; }
  .field-group label span { color:var(--muted); font-weight:400; font-size:12px; }

  .form-input { width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; background:var(--white); }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  textarea.form-input { resize:vertical; min-height:100px; line-height:1.7; }

  .input-icon-wrap { position:relative; }
  .input-icon-wrap i { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--muted); pointer-events:none; }
  .input-icon-wrap .form-input { padding-left:40px; }
  .input-icon-wrap.textarea-wrap i { top:14px; transform:none; }

  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

  /* ── Submit btn ── */
  .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; border:none; border-radius:10px; padding:12px 28px; font-size:14px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; transition:background .15s,transform .1s; }
  .btn-save:hover { background:#4f46e5; transform:translateY(-1px); }

  /* ── Char counter ── */
  .char-count { font-size:11.5px; color:var(--muted); text-align:right; margin-top:4px; }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .two-col { grid-template-columns:1fr; } }
</style>
</head>
<body>

<!-- ── Toast ── -->
<?php if($success): ?>
<div class="toast-bar" id="toast">
  <i class="bi bi-check-circle-fill"></i> อัปเดตโปรไฟล์สำเร็จแล้ว
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php elseif($dup_err): ?>
<div class="toast-bar" id="toast" style="background:#7f1d1d;">
  <i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;font-size:18px;"></i> Username หรือ Email นี้ถูกใช้งานแล้ว
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },4000);</script>
<?php endif; ?>

<!-- ── Sidebar ── -->
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
  <nav class="sidebar-nav">
    <a href="employer_dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="employer_reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_profile.php"     class="nav-item active"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="support_chat.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">
<div class="content-wrap">

  <div class="topbar">
    <h2>My Profile</h2>
    <p>จัดการข้อมูลบริษัทและช่องทางติดต่อ</p>
  </div>

  <!-- Profile banner -->
  <div class="profile-banner">
    <div class="avatar-circle"><?php echo $initials ?: '?'; ?></div>
    <div class="banner-info">
      <h3><?php echo htmlspecialchars($user['fullname'] ?: '(ยังไม่ระบุ)'); ?></h3>
      <p><?php echo htmlspecialchars($user['email']); ?></p>
      <div class="banner-tags">
        <?php if(!empty($user['phone'])): ?>
        <span class="btag"><i class="bi bi-telephone"></i><?php echo htmlspecialchars($user['phone']); ?></span>
        <?php endif; ?>
        <?php if(!empty($profile['employer_description'])): ?>
        <span class="btag"><i class="bi bi-building"></i><?php echo mb_substr(htmlspecialchars($profile['employer_description']),0,30).'...'; ?></span>
        <?php endif; ?>
        <span class="btag"><i class="bi bi-briefcase"></i> Employer</span>
      </div>
    </div>
  </div>

  <!-- Form -->
  <form method="POST">
  <div class="form-card">

    <!-- ข้อมูลบัญชี -->
    <div class="section-title"><i class="bi bi-at"></i> ข้อมูลบัญชี</div>

    <div class="field-group">
      <label>Username</label>
      <div class="input-icon-wrap">
        <i class="bi bi-at"></i>
        <input type="text" name="username" class="form-input"
               placeholder="username"
               value="<?php echo htmlspecialchars($user['username']); ?>"
               required maxlength="50">
      </div>
    </div>

    <!-- ข้อมูลบริษัท -->
    <div class="section-title"><i class="bi bi-building"></i> ข้อมูลบริษัท</div>

    <div class="field-group">
      <label>ชื่อบริษัท / Company Name</label>
      <div class="input-icon-wrap">
        <i class="bi bi-building"></i>
        <input type="text" name="fullname" class="form-input"
               placeholder="ชื่อบริษัทหรือองค์กร"
               value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
      </div>
    </div>

    <div class="field-group">
      <label>รายละเอียดบริษัท <span>(แนะนำตัวให้ Freelancer รู้จักบริษัทคุณ)</span></label>
      <div class="input-icon-wrap textarea-wrap">
        <i class="bi bi-card-text"></i>
        <textarea name="description" id="desc-input" class="form-input"
                  style="padding-left:40px;"
                  placeholder="เล่าเกี่ยวกับบริษัท ธุรกิจ หรือสิ่งที่กำลังมองหา..."
                  maxlength="500"
                  oninput="updateCount()"><?php echo htmlspecialchars($profile['employer_description'] ?? ''); ?></textarea>
      </div>
      <div class="char-count"><span id="desc-count">0</span> / 500</div>
    </div>

    <!-- ช่องทางติดต่อ -->
    <div class="section-title" style="margin-top:8px;"><i class="bi bi-person"></i> ข้อมูลติดต่อ</div>

    <div class="field-group">
      <label>Email</label>
      <div class="input-icon-wrap">
        <i class="bi bi-envelope"></i>
        <input type="email" name="email" class="form-input"
               placeholder="email@company.com"
               value="<?php echo htmlspecialchars($user['email']); ?>" required>
      </div>
    </div>

    <div class="field-group">
      <label>เบอร์โทรศัพท์ <span>(ไม่บังคับ)</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-telephone"></i>
        <input type="text" name="phone" class="form-input"
               placeholder="0xx-xxx-xxxx"
               value="<?php echo htmlspecialchars($user['phone']); ?>">
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;padding-top:8px;">
      <button type="submit" name="update" class="btn-save">
        <i class="bi bi-check-lg"></i> บันทึกการเปลี่ยนแปลง
      </button>
    </div>

  </div>
  </form>

</div>
</main>

<script>
  function updateCount(){
    const ta = document.getElementById('desc-input');
    document.getElementById('desc-count').textContent = ta.value.length;
  }
  updateCount();
</script>
</body>
</html>