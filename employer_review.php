<?php
session_start();
include("config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "employer"){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ แก้ Query: ใช้ LEFT JOIN job แทน JOIN job เพื่อรองรับรีวิวบริษัทที่ job_id เป็น NULL
$reviews = mysqli_query($conn,"
    SELECT er.*, u.username as freelancer_name, j.title as job_title
    FROM employer_review er
    JOIN users u ON er.freelancer_id = u.user_id
    LEFT JOIN job j ON er.job_id = j.job_id
    WHERE er.employer_id = '$user_id' AND er.job_id IS NULL
    ORDER BY er.review_id DESC
");

$review_count = mysqli_num_rows($reviews);

// คำนวณเรตติ้งเฉลี่ย
$avg_rating = 0;
if($review_count > 0){
    $sum = 0;
    $temp = mysqli_query($conn, "SELECT AVG(rating) as avg FROM employer_review WHERE employer_id='$user_id'");
    $data = mysqli_fetch_assoc($temp);
    $avg_rating = $data['avg'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รีวิวบริษัท</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root { --navy:#0f172a; --navy2:#1e293b; --navy3:#334155; --accent:#6366f1; --light:#f1f5f9; --white:#ffffff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --yellow:#f59e0b; --radius:14px; }
  body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; }

  /* Sidebar & Main */
  .sidebar { width:240px; min-height:100vh; background:var(--navy); display:flex; flex-direction:column; padding:28px 0; position:fixed; top:0; left:0; z-index:100; }
  .sidebar-brand { padding:0 24px 28px; border-bottom:1px solid var(--navy3); }
  .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
  .logo-icon { width:36px; height:36px; background:var(--accent); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; }
  .logo-text { font-size:15px; font-weight:600; color:#fff; line-height:1.2; }
  .logo-sub { font-size:11px; color:var(--navy3); }
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
  .topbar { margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p { font-size:13px; color:var(--muted); margin-top:2px; }

  /* Card Styles */
  .review-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px; margin-bottom:12px; }
  .rev-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px; }
  .rev-username { font-weight:600; font-size:14px; color:var(--text); }
  .rev-job-title { font-size:13px; color:var(--muted); background:var(--light); padding:4px 10px; border-radius:8px; display:inline-flex; align-items:center; gap:4px; }
  .rev-stars { font-size:18px; color:var(--yellow); margin-bottom:8px; }
  .rev-comment { font-size:14px; color:var(--text); line-height:1.6; margin-top:8px; }
  .rev-date { font-size:11px; color:var(--muted); text-align:right; margin-top:6px; }

  /* Empty State */
  .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
  .empty-state i { font-size:50px; color:#c7d2fe; margin-bottom:16px; display:block; }
  .empty-state p { font-size:14px; }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div><div class="logo-text">FreelanceHub</div><div class="logo-sub">Employer</div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="employer_dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="saved_freelancers.php"    class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="employer_reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_review.php"      class="nav-item active"><i class="bi bi-building"></i> รีวิวบริษัท</a>
    <a href="employer_profile.php"     class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="support_chat.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <h2>รีวิวบริษัท</h2>
    <p>รีวิวบริษัทที่ได้รับจาก Freelancer</p>
  </div>

  <?php if($review_count == 0): ?>
  <div class="empty-state">
    <i class="bi bi-star"></i>
    <p>ยังไม่มีรีวิวจาก Freelancer<br>เมื่องานเสร็จสิ้น Freelancer จะสามารถรีวิวคุณได้</p>
  </div>

  <?php else: ?>

  <div style="margin-bottom:20px; padding:15px; background:var(--white); border-radius:var(--radius); border:1px solid var(--border); display:inline-flex; align-items:center; gap:10px;">
    <span style="font-size:14px; font-weight:600;">เรตติ้งเฉลี่ย:</span>
    <span style="font-size:20px; font-weight:700; color:var(--yellow);">
      <i class="bi bi-star-fill"></i> <?php echo number_format($avg_rating, 1); ?>
    </span>
    <span style="font-size:12px; color:var(--muted);">(<?php echo $review_count; ?> รีวิว)</span>
  </div>

  <?php while($row = mysqli_fetch_assoc($reviews)): ?>
  <div class="review-card">
    <div class="rev-header">
      <div class="rev-username">
        <i class="bi bi-person-circle" style="color:var(--accent);"></i>
        <?php echo htmlspecialchars($row['freelancer_name']); ?>
      </div>
      
      <div class="rev-job-title">
        <?php 
        // ✅ ตรวจสอบว่าเป็นรีวิวงาน หรือ รีวิวบริษัท
        if($row['job_id'] == null): ?>
          <i class="bi bi-building"></i> รีวิวบริษัท
        <?php else: ?>
          <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($row['job_title'] ?: 'งานนี้'); ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="rev-stars">
      <?php 
      // สร้างดาวตามคะแนน
      $stars = str_repeat('★', $row['rating']) . str_repeat('☆', 5 - $row['rating']);
      echo $stars;
      ?>
    </div>

    <?php if(!empty($row['comment'])): ?>
    <div class="rev-comment">
      <?php echo nl2br(htmlspecialchars($row['comment'])); ?>
    </div>
    <?php endif; ?>

    <div class="rev-date">
      <?php echo date('d M Y, H:i', strtotime($row['created_at'])); ?>
    </div>
  </div>
  <?php endwhile; ?>

  <?php endif; ?>

</main>

</body>
</html>
