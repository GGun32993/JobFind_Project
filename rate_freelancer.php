<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "profile_image_helpers.php";
require_once "employer_sidebar_helpers.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: login.php");
    exit();
}

ensure_profile_image_schema($conn);

$employer_id   = $_SESSION['user_id'];
$freelancer_id = intval($_GET['freelancer_id'] ?? 0);
$job_id        = intval($_GET['job_id'] ?? 0);
$sidebar_pending_apps = get_employer_pending_application_count($conn, $employer_id);

if(!$freelancer_id || !$job_id){
    header("Location: employer_manage_jobs.php");
    exit();
}

// ── ดึงข้อมูล freelancer และงาน ──
$fl   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT username, fullname, profile_image FROM users WHERE user_id='$freelancer_id'"));
$job  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT title FROM job WHERE job_id='$job_id'"));
$fl_profile_image = trim($fl['profile_image'] ?? '');
$fl_display_name = trim($fl['fullname'] ?? '') !== '' ? $fl['fullname'] : ($fl['username'] ?? 'Unknown');

// ── เช็คว่ารีวิวไปแล้วหรือยัง ──
$already = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT review_id FROM freelancer_review
    WHERE employer_id='$employer_id' AND freelancer_id='$freelancer_id' AND job_id='$job_id'
"));

$success = false;
$error   = '';

if($_POST && !$already){
    $rating  = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    if($rating < 1 || $rating > 5){
        $error = 'กรุณาเลือกคะแนน 1-5 ดาว';
    } else {
        mysqli_query($conn,"
            INSERT INTO freelancer_review (employer_id,freelancer_id,job_id,rating,comment)
            VALUES ('$employer_id','$freelancer_id','$job_id','$rating','$comment')
        ");
        header("Location: view_applicants.php?job_id=$job_id&reviewed=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo.png?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rate Freelancer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

  :root {
    --navy:   #0f172a;  --navy2:  #1e293b;  --navy3:  #334155;
    --accent: #6366f1;  --light:  #f1f5f9;  --white:  #ffffff;
    --text:   #0f172a;  --muted:  #64748b;  --border: #e2e8f0;
    --green:  #10b981;  --yellow: #f59e0b;  --red:    #ef4444;
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
  .nav-item:hover  { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }
  .nav-logout i { font-size:17px; }

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; display:flex; align-items:flex-start; justify-content:center; }
  .content-wrap { width:100%; max-width:560px; }

  /* ── Topbar ── */
  .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; gap:12px; flex-wrap:wrap; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .btn-back { display:inline-flex; align-items:center; gap:7px; background:var(--white); border:1px solid var(--border); color:var(--text); border-radius:10px; padding:9px 18px; font-size:13.5px; font-weight:500; text-decoration:none; transition:background .15s; white-space:nowrap; }
  .btn-back:hover { background:var(--light); color:var(--text); }

  /* ── Freelancer info card ── */
  .fl-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; gap:16px; }
  .fl-avatar { width:52px; height:52px; border-radius:50%; background:var(--accent); color:#fff; font-size:19px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; border:1px solid var(--border); }
  .fl-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .fl-name { font-size:15px; font-weight:600; }
  .fl-job  { font-size:13px; color:var(--muted); margin-top:3px; display:flex; align-items:center; gap:5px; }

  /* ── Already reviewed ── */
  .reviewed-box { background:#d1fae5; border:1px solid #6ee7b7; border-radius:var(--radius); padding:20px 24px; display:flex; align-items:center; gap:12px; font-size:14px; color:#065f46; font-weight:500; }

  /* ── Alert ── */
  .alert-err { background:#fee2e2; border:1px solid #fca5a5; border-radius:10px; padding:12px 16px; font-size:13.5px; color:#991b1b; display:flex; align-items:center; gap:8px; margin-bottom:16px; }

  /* ── Form card ── */
  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:28px; }
  .section-title { font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
  .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

  /* ── Star rating ── */
  .star-rating { display:flex; flex-direction:row-reverse; justify-content:flex-end; gap:6px; margin-bottom:20px; }
  .star-rating input { display:none; }
  .star-rating label {
    font-size:42px; color:#e2e8f0 !important; cursor:pointer;
    transition:color .15s, transform .1s;
    line-height:1;
  }
  .star-rating label:hover,
  .star-rating label:hover ~ label,
  .star-rating input:checked ~ label { color:var(--yellow) !important; }
  .star-rating label:hover { transform:scale(1.15); }

  .star-desc { font-size:13px; color:var(--muted); margin-bottom:16px; height:20px; }

  /* ── Textarea ── */
  .field-group { margin-bottom:18px; }
  .field-group label { display:block; font-size:13px; font-weight:500; margin-bottom:6px; }
  .field-group label span { color:var(--muted); font-weight:400; font-size:12px; }
  .form-input { width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  textarea.form-input { resize:vertical; min-height:110px; line-height:1.7; }
  .char-count { font-size:11.5px; color:var(--muted); text-align:right; margin-top:4px; }

  /* ── Submit ── */
  .btn-submit { width:100%; padding:13px; background:var(--accent); color:#fff; border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:15px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:background .15s,transform .1s; }
  .btn-submit:hover { background:#4f46e5; transform:translateY(-1px); }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon"><img class="brand-logo-img" src="assets/images/jobfind-logo-icon.png?v=2" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text">Job_Find</div>
        <div class="logo-sub">Employer</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="employer_dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item active"><i class="bi bi-briefcase"></i> Manage Jobs<?php render_employer_manage_jobs_badge($sidebar_pending_apps); ?></a>
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
    <div>
      <h2>รีวิว Freelancer</h2>
      <p>ให้คะแนนและความคิดเห็นสำหรับงานที่ทำร่วมกัน</p>
    </div>
    <a href="view_applicants.php?job_id=<?php echo $job_id; ?>" class="btn-back">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  <!-- Freelancer info -->
  <div class="fl-card">
    <div class="fl-avatar">
      <?php if($fl_profile_image !== ''): ?>
        <img src="<?php echo profile_image_src($fl_profile_image); ?>" alt="<?php echo htmlspecialchars($fl_display_name); ?>">
      <?php else: ?>
        <?php echo profile_initials($fl_display_name); ?>
      <?php endif; ?>
    </div>
    <div>
      <div class="fl-name"><?php echo htmlspecialchars($fl_display_name); ?></div>
      <div class="fl-job">
        <i class="bi bi-briefcase" style="font-size:12px;"></i>
        <?php echo htmlspecialchars($job['title'] ?? 'Unknown Job'); ?>
      </div>
    </div>
  </div>

  <?php if($already): ?>
  <!-- Already reviewed -->
  <div class="reviewed-box">
    <i class="bi bi-check-circle-fill" style="font-size:22px;"></i>
    คุณได้รีวิว Freelancer คนนี้ในงานนี้ไปแล้ว
  </div>

  <?php else: ?>

  <?php if($error): ?>
  <div class="alert-err">
    <i class="bi bi-exclamation-circle-fill"></i>
    <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>

  <!-- Form -->
  <form method="POST">
  <div class="form-card">

    <div class="section-title"><i class="bi bi-star"></i> ให้คะแนน</div>

    <!-- Star picker -->
    <div class="star-rating" id="star-wrap">
      <?php for($i=5;$i>=1;$i--): ?>
      <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>"
             <?php echo ($i==5)?'checked':''; ?>>
      <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> ดาว">★</label>
      <?php endfor; ?>
    </div>
    <div class="star-desc" id="star-desc">ดีมาก 🎉</div>

    <!-- Comment -->
    <div class="field-group">
      <label>ความคิดเห็น <span>(ไม่บังคับ)</span></label>
      <textarea name="comment" id="comment-input" class="form-input"
                placeholder="เล่าประสบการณ์การทำงานร่วมกัน ทักษะ ความตรงต่อเวลา ฯลฯ"
                maxlength="500"
                oninput="updateCount()"><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
      <div class="char-count"><span id="char-count">0</span> / 500</div>
    </div>

    <button type="submit" class="btn-submit">
      <i class="bi bi-send"></i> ส่งรีวิว
    </button>

  </div>
  </form>

  <?php endif; ?>

</div>
</main>

<script>
  const descs = {5:'ดีมาก 🎉', 4:'ดี 👍', 3:'ปานกลาง 😐', 2:'พอใช้ 😕', 1:'แย่ 😞'};

  document.querySelectorAll('.star-rating input').forEach(inp => {
    inp.addEventListener('change', () => {
      document.getElementById('star-desc').textContent = descs[inp.value] || '';
    });
  });

  // set initial desc
  const checked = document.querySelector('.star-rating input:checked');
  if(checked) document.getElementById('star-desc').textContent = descs[checked.value] || '';

  function updateCount(){
    const ta = document.getElementById('comment-input');
    document.getElementById('char-count').textContent = ta.value.length;
  }
  updateCount();
</script>
</body>
</html>
