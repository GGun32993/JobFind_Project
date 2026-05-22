<?php
session_start();
include("config.php");

if(!isset($_SESSION['user_id'])){ header("Location: login.php"); exit(); }

$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];

// ตรวจสอบโหมด: ดูสาธารณะ (Freelancer) หรือ แก้ไขตัวเอง (Employer)
$view_emp_id = intval($_GET['emp'] ?? $_GET['employer_id'] ?? 0);
$is_public   = ($view_emp_id > 0 && ($current_role != 'employer' || $view_emp_id != $current_user_id));

$success = false;
$dup_err = false;

// ── UPDATE PROFILE (เฉพาะ Employer แก้ตัวเอง) ──
if(!$is_public && isset($_POST['update'])){
    $new_username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $fullname     = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email        = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone        = mysqli_real_escape_string($conn, $_POST['phone']);
    $description  = mysqli_real_escape_string($conn, $_POST['description']);

    $dup = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT user_id FROM users
        WHERE (username='$new_username' OR email='$email')
        AND user_id != '$current_user_id'
    "));

    if($dup){
        $dup_err = true;
    } else {
        mysqli_query($conn,"
            UPDATE users SET username='$new_username', fullname='$fullname', email='$email', phone='$phone'
            WHERE user_id='$current_user_id'
        ");
        $_SESSION['username'] = $new_username;

        $check = mysqli_query($conn,"SELECT * FROM employer_profile WHERE user_id='$current_user_id'");
        if(mysqli_num_rows($check) > 0){
            mysqli_query($conn,"
                UPDATE employer_profile SET
                employer_name='$fullname', employer_description='$description'
                WHERE user_id='$current_user_id'
            ");
        } else {
            mysqli_query($conn,"
                INSERT INTO employer_profile (user_id, employer_name, employer_description)
                VALUES ('$current_user_id','$fullname','$description')
            ");
        }
        $success = true;
        header("Refresh:0");
    }
}

// ── FETCH DATA ──
$target_id = $is_public ? $view_emp_id : $current_user_id;
$user    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE user_id='$target_id'"));
$profile = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM employer_profile WHERE user_id='$target_id'"));

if(!$user){ header("Location: browse_jobs.php"); exit(); }

$initials = strtoupper(substr($user['fullname'] ?: $user['username'], 0, 2));

// ดึงรีวิว (เฉพาะโหมดสาธารณะ)
$reviews = [];
$avg_rating = 0;
$review_count = 0;
if($is_public){
    $rev_res = mysqli_query($conn,"
        SELECT er.*, u.username as reviewer_name, j.title as job_title
        FROM employer_review er
        JOIN users u ON er.freelancer_id = u.user_id
        LEFT JOIN job j ON er.job_id = j.job_id
        WHERE er.employer_id = '$target_id'
        ORDER BY er.review_id DESC
    ");
    while($r = mysqli_fetch_assoc($rev_res)){
        $reviews[] = $r;
        $avg_rating += $r['rating'];
        $review_count++;
    }
    $avg_rating = $review_count > 0 ? round($avg_rating / $review_count, 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $is_public ? 'โปรไฟล์บริษัท' : 'แก้ไขโปรไฟล์'; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root { --navy:#0f172a; --navy2:#1e293b; --navy3:#334155; --accent:#6366f1; --light:#f1f5f9; --white:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --green:#10b981; --yellow:#f59e0b; --radius:14px; }
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

  .main { margin-left:240px; flex:1; padding:36px 40px; display:flex; justify-content:center; }
  .content-wrap { width:100%; max-width:560px; }
  .topbar { margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .btn-back { display:inline-flex; align-items:center; gap:7px; background:var(--white); border:1px solid var(--border); color:var(--text); border-radius:10px; padding:9px 18px; font-size:13.5px; font-weight:500; text-decoration:none; margin-bottom:16px; transition:background .15s; }
  .btn-back:hover { background:var(--light); }

  .profile-banner { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; display:grid; grid-template-columns:56px 1fr; gap:16px; align-items:center; margin-bottom:18px; }
  .banner-avatar { width:56px; height:56px; border-radius:16px; background:var(--accent); color:#fff; font-size:18px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .banner-main { min-width:0; }
  .banner-main h3 { font-size:18px; font-weight:700; margin-bottom:4px; }
  .banner-main p { font-size:13px; color:var(--muted); margin-bottom:8px; }
  .banner-tags { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
  .banner-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
  .company-status { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--muted); background:var(--light); border-radius:999px; padding:6px 10px; }

  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px; }
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
  .info-box { background:var(--light); border-radius:12px; padding:14px; }
  .info-box .lbl { font-size:11px; color:var(--muted); margin-bottom:6px; display:block; }
  .info-box .val { font-size:14px; font-weight:600; color:var(--text); }
  .desc-box { background:var(--light); border-radius:var(--radius); padding:14px; font-size:14px; line-height:1.7; }
  .review-card { background:var(--light); border-radius:var(--radius); padding:14px; margin-bottom:12px; border-left:3px solid var(--accent); }
  .rev-header { display:flex; justify-content:space-between; margin-bottom:8px; flex-wrap:wrap; gap:10px; }
  .rev-name { font-size:13px; font-weight:600; }
  .rev-job { font-size:11px; color:var(--muted); }
  .rev-stars { font-size:12px; color:var(--yellow); margin-bottom:8px; }
  .rev-comment { font-size:13px; line-height:1.6; }
  .empty-rev { text-align:center; padding:28px 20px; color:var(--muted); font-size:13px; }

  @media(max-width:968px){ .profile-banner { grid-template-columns:1fr; text-align:center; } .banner-actions { justify-content:center; } .info-grid { grid-template-columns:1fr; } }
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .info-grid { grid-template-columns:1fr; } }
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
  .char-count { font-size:11.5px; color:var(--muted); text-align:right; margin-top:4px; }
  .btn-save { display:inline-flex; align-items:center; gap:8px; background:var(--accent); color:#fff; border:none; border-radius:10px; padding:12px 28px; font-size:14px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; transition:background .15s,transform .1s; }
  .btn-save:hover { background:#4f46e5; transform:translateY(-1px); }

  /* Public View Styles */
  .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; }
  .info-box { background:var(--light); border-radius:10px; padding:12px; }
  .info-box .lbl { font-size:11px; color:var(--muted); margin-bottom:3px; }
  .info-box .val { font-size:13px; font-weight:500; }
  .desc-box { background:var(--light); border-radius:10px; padding:14px; font-size:13.5px; line-height:1.7; margin-bottom:20px; }
  .review-card { background:var(--light); border-radius:10px; padding:14px; margin-bottom:10px; border-left:3px solid var(--accent); }
  .rev-header { display:flex; justify-content:space-between; margin-bottom:6px; }
  .rev-name { font-size:13px; font-weight:600; }
  .rev-job { font-size:11px; color:var(--muted); }
  .rev-stars { font-size:12px; color:var(--yellow); margin-bottom:6px; }
  .rev-comment { font-size:13px; line-height:1.6; }
  .empty-rev { text-align:center; padding:30px; color:var(--muted); font-size:13px; }

  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .info-grid { grid-template-columns:1fr; } }

  /* ปุ่มรีวิวบริษัท */
  .btn-company-review {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--light);
    color: var(--accent);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    margin-top: 12px;
    transition: all 0.15s;
  }
  .btn-company-review:hover {
    border-color: var(--accent);
    background: #eef2ff;
  }
</style>
</head>
<body>

<?php if($success): ?>
<div class="toast-bar" id="toast"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> อัปเดตโปรไฟล์สำเร็จแล้ว</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php elseif($dup_err): ?>
<div class="toast-bar" id="toast" style="background:#7f1d1d;"><i class="bi bi-exclamation-triangle-fill" style="color:#fca5a5;"></i> Username หรือ Email นี้ถูกใช้งานแล้ว</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },4000);</script>
<?php endif; ?>

<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div><div class="logo-text">FreelanceHub</div><div class="logo-sub"><?php echo $current_role == 'employer' ? 'Employer' : 'Dashboard'; ?></div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <?php if($current_role == 'employer'): ?>
      <a href="employer_dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
      <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
      <a href="employer_manage_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
      <a href="saved_freelancers.php"    class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
      <a href="employer_reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
      <a href="employer_review.php"      class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
      <a href="employer_profile.php"     class="nav-item active"><i class="bi bi-person-circle"></i> My Profile</a>
    <?php else: ?>
      <a href="freelancer_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
      <a href="browse_jobs.php"          class="nav-item active"><i class="bi bi-briefcase"></i> Browse Jobs</a>
      <a href="my_applications.php"      class="nav-item"><i class="bi bi-file-earmark-text"></i> My Applications</a>
      <a href="my_profile.php"           class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
      <a href="freelancer_reviews.php"   class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
      <a href="upload_resume.php"        class="nav-item"><i class="bi bi-cloud-upload"></i> Upload Resume</a>
    <?php endif; ?>
    <div class="nav-divider"></div>
    <a href="support_chat.php" class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<main class="main">
<div class="content-wrap">

  <?php if($is_public): ?>
    <a href="javascript:history.back()" class="btn-back"><i class="bi bi-arrow-left"></i> กลับ</a>
  <?php endif; ?>

  <div class="topbar">
    <h2><?php echo $is_public ? 'โปรไฟล์บริษัท' : 'แก้ไขโปรไฟล์'; ?></h2>
    <p><?php echo $is_public ? 'ข้อมูลบริษัทและประวัติรีวิว' : 'จัดการข้อมูลบริษัทและช่องทางติดต่อ'; ?></p>
  </div>

  <div class="profile-banner">
    <div class="banner-avatar"><?php echo $initials ?: '?'; ?></div>
    <div class="banner-main">
      <h3><?php echo htmlspecialchars($profile['employer_name'] ?? $user['fullname'] ?? '(ยังไม่ระบุ)'); ?></h3>
      <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
      <div class="banner-tags">
        <?php if(!empty($user['phone'])): ?>
        <span class="company-status"><i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($user['phone']); ?></span>
        <?php endif; ?>
        <?php if($is_public): ?>
        <span class="company-status"><i class="bi bi-star-fill"></i> <?php echo $avg_rating > 0 ? $avg_rating : 'ยังไม่มี'; ?> (<?php echo $review_count; ?> รีวิว)</span>
        <?php endif; ?>
        <span class="company-status"><i class="bi bi-building"></i> นายจ้าง</span>
      </div>
      <div class="banner-actions">
        <?php if($is_public): ?>
        <a href="employer_review_form.php?employer_id=<?php echo $target_id; ?>" class="btn-company-review">
          <i class="bi bi-building"></i> รีวิวผู้ว่าจ้าง
        </a>
        <?php endif; ?>
        <?php if(!$is_public): ?>
        <a href="employer_manage_jobs.php" class="btn-company-review" style="color:#fff; background:var(--accent); border:none;">
          <i class="bi bi-briefcase"></i> ดูงานของฉัน
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if(!$is_public): ?>
  <!-- ✅ MODE แก้ไข (Employer) -->
  <form method="POST">
  <div class="form-card">
    <div class="section-title"><i class="bi bi-at"></i> ข้อมูลบัญชี</div>
    <div class="field-group">
      <label>Username</label>
      <div class="input-icon-wrap">
        <i class="bi bi-at"></i>
        <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username']); ?>" required maxlength="50">
      </div>
    </div>

    <div class="section-title"><i class="bi bi-building"></i> ข้อมูลบริษัท</div>
    <div class="field-group">
      <label>ชื่อบริษัท / Company Name</label>
      <div class="input-icon-wrap">
        <i class="bi bi-building"></i>
        <input type="text" name="fullname" class="form-input" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
      </div>
    </div>
    <div class="field-group">
      <label>รายละเอียดบริษัท <span>(แนะนำตัวให้ Freelancer รู้จักบริษัทคุณ)</span></label>
      <div class="input-icon-wrap textarea-wrap">
        <i class="bi bi-card-text"></i>
        <textarea name="description" id="desc-input" class="form-input" style="padding-left:40px;" placeholder="เล่าเกี่ยวกับบริษัท..." maxlength="500" oninput="updateCount()"><?php echo htmlspecialchars($profile['employer_description'] ?? ''); ?></textarea>
      </div>
      <div class="char-count"><span id="desc-count">0</span> / 500</div>
    </div>

    <div class="section-title" style="margin-top:8px;"><i class="bi bi-person"></i> ข้อมูลติดต่อ</div>
    <div class="field-group">
      <label>Email</label>
      <div class="input-icon-wrap">
        <i class="bi bi-envelope"></i>
        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
      </div>
    </div>
    <div class="field-group">
      <label>เบอร์โทรศัพท์ <span>(ไม่บังคับ)</span></label>
      <div class="input-icon-wrap">
        <i class="bi bi-telephone"></i>
        <input type="text" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone']); ?>">
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;padding-top:8px;">
      <button type="submit" name="update" class="btn-save"><i class="bi bi-check-lg"></i> บันทึกการเปลี่ยนแปลง</button>
    </div>
  </div>
  </form>

  <?php else: ?>
  <!-- ✅ MODE สาธารณะ (Freelancer อ่าน) -->
  <div class="form-card">
      <div class="section-title"><i class="bi bi-info-circle"></i> ข้อมูลติดต่อ</div>
      <div class="info-grid">
        <div class="info-box"><div class="lbl">Email</div><div class="val"><?php echo htmlspecialchars($user['email'] ?? '—'); ?></div></div>
        <div class="info-box"><div class="lbl">เบอร์โทรศัพท์</div><div class="val"><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></div></div>
      </div>
      <div class="section-title"><i class="bi bi-building"></i> รายละเอียดบริษัท</div>
      <div class="desc-box">
        <?php echo nl2br(htmlspecialchars($profile['employer_description'] ?? 'ยังไม่ได้ระบุรายละเอียดเกี่ยวกับบริษัท')); ?>
      </div>
      <div class="section-title"><i class="bi bi-star"></i> รีวิวจาก Freelancer</div>
      <?php if(empty($reviews)): ?>
        <div class="empty-rev"><i class="bi bi-star" style="font-size:28px;display:block;margin-bottom:8px;"></i>ยังไม่มีรีวิวสำหรับบริษัทนี้</div>
      <?php else: ?>
        <?php foreach($reviews as $r): ?>
        <div class="review-card">
          <div class="rev-header">
            <span class="rev-name"><?php echo htmlspecialchars($r['reviewer_name']); ?></span>
            <span class="rev-job"><?php echo htmlspecialchars($r['job_title'] ?? 'งานนี้'); ?></span>
          </div>
          <div class="rev-stars">⭐ <?php echo $r['rating']; ?>/5</div>
          <div class="rev-comment"><?php echo nl2br(htmlspecialchars($r['comment'] ?? 'ไม่มีความคิดเห็นเพิ่มเติม')); ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</main>

<script>
function updateCount(){
  const ta = document.getElementById('desc-input');
  if(ta) document.getElementById('desc-count').textContent = ta.value.length;
}
updateCount();
</script>
</body>
</html>
