<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../helpers/profile_image_helpers.php";

$freelancer_id = jobfind_require_role('freelancer');
$employer_id   = intval($_GET['employer_id'] ?? 0);
$job_id        = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;

function safe_return_url($url, $fallback = ''){
    $url = trim((string)$url);
    if($url === '' || preg_match('/[\r\n]/', $url)){
        return $fallback;
    }

    $parts = parse_url($url);
    if($parts === false || isset($parts['scheme']) || isset($parts['host']) || strpos($url, '//') === 0){
        return $fallback;
    }

    if(!preg_match('/^[A-Za-z0-9_\/.-]+\.php(\?[A-Za-z0-9_%=&.\-\/]*)?$/', $url)){
        return $fallback;
    }

    return $url;
}

$return_url = safe_return_url($_GET['return_url'] ?? ($_POST['return_url'] ?? ''));
$return_query = $return_url !== '' ? '&return_url=' . urlencode($return_url) : '';

if(!$employer_id){ header("Location: " . ($return_url ?: "browse_jobs.php")); exit(); }
ensure_profile_image_schema($conn);

$employer = mysqli_fetch_assoc(mysqli_query($conn, "SELECT u.user_id, u.profile_image, COALESCE(ep.employer_name, u.username) AS company_name FROM Users u LEFT JOIN Employer_Profile ep ON ep.user_id = u.user_id WHERE u.user_id='$employer_id'"));
if(!$employer){ header("Location: browse_jobs.php"); exit(); }

$profile_url = "../employer/profile.php?employer_id=$employer_id" . $return_query;
$back_url = $return_url ?: "my_applications.php";
$liked = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 1
    FROM Like_Employer
    WHERE freelancer_id='$freelancer_id'
    AND employer_id='$employer_id'
    LIMIT 1
")) ? true : false;

// ตรวจสอบว่ารีวิวไปแล้วหรือยัง (แยกกรณีมี job_id กับ ไม่มี job_id)
if($job_id){
    $already_q = "SELECT review_id FROM Employer_Review WHERE employer_id='$employer_id' AND freelancer_id='$freelancer_id' AND job_id='$job_id'";
} else {
    $already_q = "SELECT review_id FROM Employer_Review WHERE employer_id='$employer_id' AND freelancer_id='$freelancer_id' AND job_id IS NULL";
}
$already = mysqli_fetch_assoc(mysqli_query($conn, $already_q));

$error = '';

if(isset($_POST['submit']) && !$already){
    $rating  = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    if($rating < 1 || $rating > 5){
        $error = 'กรุณาเลือกคะแนน 1-5 ดาว';
    } else {
        $job_val = $job_id ? "'$job_id'" : "NULL";
        mysqli_query($conn, "INSERT INTO Employer_Review (employer_id, freelancer_id, job_id, rating, comment) VALUES ('$employer_id','$freelancer_id',$job_val,'$rating','$comment')");
        header("Location: ../employer/profile.php?employer_id=$employer_id&reviewed=1" . $return_query);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=14">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รีวิวผู้ว่าจ้าง</title>
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

  .main { margin-left:240px; flex:1; padding:36px 40px; display:flex; align-items:flex-start; justify-content:center; }
  .content-wrap { width:100%; max-width:560px; }

  .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; gap:12px; flex-wrap:wrap; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .btn-back { display:inline-flex; align-items:center; gap:7px; background:var(--white); border:1px solid var(--border); color:var(--text); border-radius:10px; padding:9px 18px; font-size:13.5px; font-weight:500; text-decoration:none; transition:background .15s; white-space:nowrap; }
  .btn-back:hover { background:var(--light); color:var(--text); }

  .btn-like { display:inline-flex; align-items:center; gap:8px; background:var(--white); border:1px solid var(--border); color:var(--muted); border-radius:10px; padding:10px 18px; font-size:13.5px; font-weight:500; cursor:pointer; transition:all .2s; white-space:nowrap; }
  .btn-like:hover { border-color:var(--red); color:var(--red); }
  .btn-like.liked { background:var(--red); color:#fff; border-color:var(--red); }
  .btn-like i { font-size:16px; transition:transform .2s; }
  .btn-like.liked i { transform:scale(1.2); }

  .company-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 24px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
  .co-left { display:flex; align-items:center; gap:16px; flex:1; min-width:0; }
  .co-avatar { width:52px; height:52px; border-radius:12px; background:var(--navy); color:#fff; font-size:19px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
  .co-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .co-name { font-size:15px; font-weight:600; }
  .co-job  { font-size:13px; color:var(--muted); margin-top:3px; display:flex; align-items:center; gap:5px; }

  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:28px; }
  .section-title { font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:18px; display:flex; align-items:center; gap:8px; }

  .star-rating { display:flex; flex-direction:row-reverse; justify-content:flex-end; gap:6px; margin-bottom:10px; }
  .star-rating input { display:none; }
  .star-rating label { font-size:42px; color:#e2e8f0 !important; cursor:pointer; transition:color .15s, transform .1s; line-height:1; }
  .star-rating label:hover,
  .star-rating label:hover ~ label,
  .star-rating input:checked ~ label { color:var(--yellow) !important; }
  .star-rating label:hover { transform:scale(1.15); }
  .star-desc { font-size:13px; color:var(--muted); margin-bottom:20px; height:20px; }

  .form-input { width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; background:var(--white); }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  textarea.form-input { resize:vertical; min-height:110px; line-height:1.7; }
  .char-count { font-size:11.5px; color:var(--muted); text-align:right; margin-top:4px; }

  .btn-submit { width:100%; padding:13px; background:var(--accent); color:#fff; border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:15px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:background .15s,transform .1s; }
  .btn-submit:hover { background:#4f46e5; transform:translateY(-1px); }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }

</style>
<link rel="stylesheet" href="../assets/css/freelancehub-theme.css">

</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="../index.php" class="logo">
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;margin:0 auto!important;align-self:center!important;"><img class="brand-logo-img" src="../assets/images/jobfind-logo.png?v=14" alt="Freelance Matching Online logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div><div class="logo-text" style="display:none!important;">Freelance Matching Online</div><div class="logo-sub" style="display:none!important;">Freelancer</div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="browse_jobs.php"          class="nav-item"><i class="bi bi-briefcase"></i> Browse Jobs</a>
    <a href="my_applications.php"      class="nav-item active"><i class="bi bi-file-earmark-text"></i> My Applications</a>
    <a href="profile.php"           class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="reviews.php"   class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="upload_resume.php"        class="nav-item"><i class="bi bi-cloud-upload"></i> Upload Resume</a>
    <div class="nav-divider"></div>
    <a href="../support/messages.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<main class="main">
<div class="content-wrap">

  <div class="topbar">
    <div>
      <h2>รีวิวผู้ว่าจ้าง</h2>
      <p>แชร์ประสบการณ์การทำงานกับผู้ว่าจ้างนี้</p>
    </div>
    <a href="<?php echo htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn-back"><i class="bi bi-arrow-left"></i> กลับ</a>
  </div>

  <div class="company-card">
    <div class="co-left">
      <div class="co-avatar">
        <?php if(!empty($employer['profile_image'])): ?>
          <img src="<?php echo profile_image_src($employer['profile_image']); ?>" alt="Employer profile image">
        <?php else: ?>
          <?php echo profile_initials($employer['company_name'] ?? '?'); ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="co-name"><?php echo htmlspecialchars($employer['company_name']); ?></div>
        <?php if($job_id): ?><div class="co-job">จากงาน ID <?php echo $job_id; ?></div><?php endif; ?>
      </div>
    </div>
    <button type="button" class="btn-like <?php echo $liked ? 'liked' : ''; ?>" onclick="likeEmployer(<?php echo $employer_id; ?>)">
      <i class="bi <?php echo $liked ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
      <span id="like-text"><?php echo $liked ? 'ถูกใจแล้ว' : 'ถูกใจ'; ?></span>
    </button>
  </div>

  <?php if($already): ?>
  <div class="reviewed-box">
    <i class="bi bi-check-circle-fill" style="font-size:22px;"></i>
    คุณได้รีวิวผู้ว่าจ้างนี้แล้ว
  </div>

  <?php else: ?>

  <?php if($error): ?>
  <div class="alert-err">
    <i class="bi bi-exclamation-circle-fill"></i>
    <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>

  <form method="POST">
  <?php if($return_url !== ''): ?>
  <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <div class="form-card">

    <div class="section-title"><i class="bi bi-star"></i> ให้คะแนน</div>

    <div class="star-rating">
      <?php for($i=5;$i>=1;$i--): ?>
      <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo ($i==5)?'checked':''; ?>>
      <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> ดาว">★</label>
      <?php endfor; ?>
    </div>
    <div class="star-desc" id="star-desc">ดีมาก 🎉</div>

    <div class="field-group">
      <label>ความคิดเห็น <span>(ไม่บังคับ)</span></label>
      <textarea name="comment" id="comment-input" class="form-input" placeholder="เล่าประสบการณ์การทำงานกับผู้ว่าจ้างนี้ เช่น ความเป็นมืออาชีพ การสื่อสาร การจ่ายเงิน ฯลฯ" maxlength="500" oninput="updateCount()"><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
      <div class="char-count"><span id="char-count">0</span> / 500</div>
    </div>

    <button type="submit" name="submit" class="btn-submit">
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

  const checked = document.querySelector('.star-rating input:checked');
  if(checked) document.getElementById('star-desc').textContent = descs[checked.value] || '';

  function updateCount(){
    const ta = document.getElementById('comment-input');
    document.getElementById('char-count').textContent = ta ? ta.value.length : 0;
  }
  updateCount();

  // Like functionality
  function likeEmployer(employerId) {
    const btn = document.querySelector('.btn-like');
    const icon = btn.querySelector('i');
    
    btn.style.opacity = '0.6';
    btn.style.pointerEvents = 'none';
    
    fetch('../actions/like_employer.php?employer_id=' + employerId)
      .then(response => response.json())
      .then(data => {
        if(data.success) {
          if(data.liked) {
            btn.classList.add('liked');
            icon.className = 'bi bi-heart-fill';
            document.getElementById('like-text').textContent = 'ถูกใจแล้ว';
          } else {
            btn.classList.remove('liked');
            icon.className = 'bi bi-heart';
            document.getElementById('like-text').textContent = 'ถูกใจ';
          }
        } else {
          alert('เกิดข้อผิดพลาด: ' + data.message);
        }
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
      })
      .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาด');
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
      });
  }

  // Check if already liked
  function checkLiked() {
    const employerId = <?php echo $employer_id; ?>;
    fetch('../actions/check_liked.php?employer_id=' + employerId)
      .then(response => response.json())
      .then(data => {
        if(data.liked) {
          const btn = document.querySelector('.btn-like');
          const icon = btn.querySelector('i');
          btn.classList.add('liked');
          icon.className = 'bi bi-heart-fill';
          document.getElementById('like-text').textContent = 'ถูกใจแล้ว';
        } else {
          const btn = document.querySelector('.btn-like');
          const icon = btn.querySelector('i');
          btn.classList.remove('liked');
          icon.className = 'bi bi-heart';
          document.getElementById('like-text').textContent = 'ถูกใจ';
        }
      })
      .catch(error => console.error('Error checking like:', error));
  }

  checkLiked();
</script>
</body>
</html>
