<?php
session_start();
include("config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];

// get location
$profile = mysqli_query($conn,"
    SELECT location
    FROM freelancer_profile
    WHERE user_id='$user_id'
");
$data = mysqli_fetch_assoc($profile);
$user_location = "";
if($data){ $user_location = $data['location']; }

// recommend jobs
if($user_location != ""){
    $recommend = mysqli_query($conn,"
        SELECT * FROM job
        WHERE location LIKE '%$user_location%'
        AND status='approved'
        ORDER BY created_at DESC
        LIMIT 5
    ");
} else {
    $recommend = mysqli_query($conn,"
        SELECT * FROM job
        WHERE status='approved'
        ORDER BY created_at DESC
        LIMIT 5
    ");
}

$popular_employers = mysqli_query($conn,"
    SELECT u.user_id,
           COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS company_name,
           COALESCE(l.total_likes,0) AS total_likes,
           COALESCE(r.total_reviews,0) AS total_reviews,
           COALESCE(r.avg_rating,0) AS avg_rating,
           COALESCE(j.total_jobs,0) AS total_jobs
    FROM users u
    LEFT JOIN employer_profile ep ON ep.user_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_likes
        FROM like_employer
        GROUP BY employer_id
    ) l ON l.employer_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating
        FROM employer_review
        GROUP BY employer_id
    ) r ON r.employer_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_jobs
        FROM job
        WHERE admin_status='approved'
        GROUP BY employer_id
    ) j ON j.employer_id = u.user_id
    WHERE u.role='employer'
    ORDER BY total_likes DESC, avg_rating DESC, total_reviews DESC, total_jobs DESC, company_name ASC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Freelancer Dashboard</title>
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
    --accent2:#818cf8;
    --light:  #f1f5f9;
    --white:  #ffffff;
    --text:   #0f172a;
    --muted:  #64748b;
    --border: #e2e8f0;
    --green:  #10b981;
    --yellow: #f59e0b;
    --red:    #ef4444;
    --radius: 14px;
  }

  body {
    font-family: 'Sora', sans-serif;
    background: var(--light);
    color: var(--text);
    display: flex;
    min-height: 100vh;
  }

  /* ── Sidebar ── */
  .sidebar {
    width: 240px;
    min-height: 100vh;
    background: var(--navy);
    display: flex;
    flex-direction: column;
    padding: 28px 0;
    position: fixed;
    top: 0; left: 0;
    z-index: 100;
  }

  .sidebar-brand {
    padding: 0 24px 28px;
    border-bottom: 1px solid var(--navy3);
  }
  .sidebar-brand .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
  }
  .sidebar-brand .logo-icon {
    width: 36px; height: 36px;
    background: var(--accent);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px;
  }
  .sidebar-brand .logo-text {
    font-size: 15px; font-weight: 600;
    color: #fff; line-height: 1.2;
  }
  .sidebar-brand .logo-sub {
    font-size: 11px; color: var(--navy3);
    font-weight: 400;
  }

  .sidebar-nav {
    padding: 20px 12px;
    flex: 1;
    display: flex; flex-direction: column; gap: 4px;
  }
  .nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 13.5px; font-weight: 500;
    transition: background .15s, color .15s;
  }
  .nav-item:hover { background: var(--navy2); color: #e2e8f0; }
  .nav-item.active { background: var(--accent); color: #fff; }
  .nav-item i { font-size: 17px; width: 20px; text-align: center; }

  .nav-divider {
    height: 1px; background: var(--navy3);
    margin: 10px 14px;
  }

  .sidebar-footer {
    padding: 16px 12px 0;
  }
  .nav-logout {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    color: #f87171;
    text-decoration: none;
    font-size: 13.5px; font-weight: 500;
    transition: background .15s;
  }
  .nav-logout:hover { background: rgba(239,68,68,.12); }
  .nav-logout i { font-size: 17px; }

  /* ── Main ── */
  .main {
    margin-left: 240px;
    flex: 1;
    padding: 36px 40px;
    min-height: 100vh;
  }

  /* ── Topbar ── */
  .topbar {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
  }
  .topbar-greeting h2 {
    font-size: 22px; font-weight: 600;
    color: var(--text);
  }
  .topbar-greeting p {
    font-size: 13px; color: var(--muted); margin-top: 2px;
  }
  .topbar-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: var(--accent);
    color: #fff; font-weight: 600; font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
  }

  /* ── Section header ── */
  .section-header {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 18px;
  }
  .section-header h4 {
    font-size: 16px; font-weight: 600;
  }
  .badge-count {
    background: var(--accent);
    color: #fff;
    font-size: 11px; font-weight: 600;
    padding: 2px 9px;
    border-radius: 20px;
  }

  /* ── Job card ── */
  .job-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px 24px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: box-shadow .2s, border-color .2s;
  }
  .job-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,.07);
    border-color: #c7d2fe;
  }

  .job-logo {
    width: 50px; height: 50px; flex-shrink: 0;
    border-radius: 12px;
    background: var(--light);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
  }

  .job-info { flex: 1; min-width: 0; }
  .job-title {
    font-size: 15px; font-weight: 600;
    color: var(--text); margin-bottom: 4px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .job-meta {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    font-size: 12.5px; color: var(--muted);
  }
  .job-meta span { display: flex; align-items: center; gap: 4px; }
  .job-meta i { font-size: 13px; }

  .tag {
    display: inline-block;
    background: #eef2ff;
    color: var(--accent);
    font-size: 11px; font-weight: 600;
    padding: 3px 10px; border-radius: 20px;
    margin-top: 8px;
  }

  .btn-apply {
    flex-shrink: 0;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 9px 20px;
    font-size: 13px; font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: background .15s, transform .1s;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .btn-apply:hover {
    background: #4f46e5; color: #fff;
    transform: translateY(-1px);
  }

  .empty-state {
    text-align: center; padding: 48px 20px;
    color: var(--muted);
  }
  .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; }
  .empty-state p { font-size: 14px; }

  .popular-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 22px;
    margin-bottom: 28px;
  }
  .popular-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; margin-bottom: 16px;
  }
  .popular-head h4 { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin: 0; }
  .popular-head h4 i { color: var(--accent); }
  .popular-top-label {
    font-size: 11px; font-weight: 700; color: var(--accent);
    background: #eef2ff; border: 1px solid #c7d2fe;
    padding: 4px 10px; border-radius: 999px;
  }
  .popular-list { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
  .popular-item {
    display: flex; flex-direction: column; gap: 10px;
    min-width: 0; padding: 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text); text-decoration: none;
    background: #fff;
    transition: border-color .15s, box-shadow .2s, transform .15s;
  }
  .popular-item:hover { color: var(--text); border-color: #c7d2fe; box-shadow: 0 6px 18px rgba(99,102,241,.12); transform: translateY(-1px); }
  .popular-top { display: flex; align-items: center; gap: 10px; min-width: 0; }
  .popular-rank {
    width: 28px; height: 28px; border-radius: 9px;
    background: var(--accent); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; flex-shrink: 0;
  }
  .popular-avatar {
    width: 34px; height: 34px; border-radius: 10px;
    background: var(--light); color: var(--accent);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
  }
  .popular-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .popular-stats { display: flex; flex-wrap: wrap; gap: 6px; }
  .popular-pill {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600; color: var(--muted);
    background: var(--light); border-radius: 999px;
    padding: 4px 8px;
  }
  .popular-pill i { color: var(--accent); font-size: 12px; }
  .popular-empty { grid-column: 1 / -1; text-align: center; color: var(--muted); padding: 24px; font-size: 13px; }

  @media(max-width: 1200px){ .popular-list { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media(max-width: 768px){
    .sidebar { display: none; }
    .main { margin-left: 0; padding: 20px 16px; }
    .job-card { flex-wrap: wrap; }
    .btn-apply { width: 100%; justify-content: center; margin-top: 8px; }
    .popular-list { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

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
    <a href="freelancer_dashboard.php" class="nav-item active">
      <i class="bi bi-grid"></i> Dashboard
    </a>
    <a href="browse_jobs.php" class="nav-item">
      <i class="bi bi-briefcase"></i> Browse Jobs
    </a>
    <a href="my_applications.php" class="nav-item">
      <i class="bi bi-file-earmark-text"></i> My Applications
    </a>
    <a href="my_profile.php" class="nav-item">
      <i class="bi bi-person-circle"></i> My Profile
    </a>
    <a href="freelancer_reviews.php" class="nav-item">
      <i class="bi bi-star"></i> My Reviews
    </a>
    <a href="upload_resume.php" class="nav-item">
      <i class="bi bi-cloud-upload"></i> Upload Resume
    </a>

    <div class="nav-divider"></div>

    <a href="support_chat.php" class="nav-item">
      <i class="bi bi-chat-dots"></i> Support Chat
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout">
      <i class="bi bi-box-arrow-left"></i> Logout
    </a>
  </div>
</aside>

<!-- ── Main Content ── -->
<main class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-greeting">
      <h2>👋 Welcome back, <?php echo htmlspecialchars($username); ?></h2>
      <p>Here are jobs recommended for you <?php if($user_location): ?>in <strong><?php echo htmlspecialchars($user_location); ?></strong><?php endif; ?></p>
    </div>
    <div class="topbar-avatar" title="<?php echo htmlspecialchars($username); ?>">
      <?php echo strtoupper(substr($username, 0, 1)); ?>
    </div>
  </div>

  <!-- Popular Employers -->
  <section class="popular-card">
    <div class="popular-head">
      <h4><i class="bi bi-trophy"></i> ผู้ว่าจ้างยอดนิยม</h4>
      <span class="popular-top-label">Top 5</span>
    </div>

    <div class="popular-list">
      <?php
        $rank = 1;
        $hasPopular = false;
        while($emp = mysqli_fetch_assoc($popular_employers)):
          $hasPopular = true;
          $rating = round($emp['avg_rating'] ?? 0, 1);
      ?>
      <a href="employer_profile.php?employer_id=<?php echo (int)$emp['user_id']; ?>" class="popular-item">
        <div class="popular-top">
          <div class="popular-rank"><?php echo $rank; ?></div>
          <div class="popular-avatar"><i class="bi bi-building"></i></div>
          <div class="popular-name"><?php echo htmlspecialchars($emp['company_name']); ?></div>
        </div>
        <div class="popular-stats">
          <span class="popular-pill"><i class="bi bi-heart-fill"></i><?php echo (int)$emp['total_likes']; ?> ไลก์</span>
          <span class="popular-pill"><i class="bi bi-star-fill"></i><?php echo $rating > 0 ? $rating : '-'; ?></span>
          <span class="popular-pill"><i class="bi bi-briefcase-fill"></i><?php echo (int)$emp['total_jobs']; ?> งาน</span>
        </div>
      </a>
      <?php $rank++; endwhile; ?>

      <?php if(!$hasPopular): ?>
      <div class="popular-empty">ยังไม่มีข้อมูลผู้ว่าจ้าง</div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Recommended Jobs -->
  <div class="section-header">
    <h4>Recommended Jobs</h4>
    <span class="badge-count">
      <?php
        // count rows — rewind pointer if needed
        $count = mysqli_num_rows($recommend);
        echo $count;
      ?>
    </span>
  </div>

  <?php
    $hasJobs = false;
    while($job = mysqli_fetch_assoc($recommend)):
      $hasJobs = true;
      $icons = ['💼','🖥️','📐','📊','🚀','🎨','⚙️','📱'];
      $icon  = $icons[crc32($job['title']) % count($icons)];
  ?>
  <div class="job-card">
    <div class="job-logo"><?php echo $icon; ?></div>

    <div class="job-info">
      <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
      <div class="job-meta">
        <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
        <span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($job['salary']); ?></span>
        <?php if(!empty($job['created_at'])): ?>
        <span><i class="bi bi-clock"></i><?php echo date('d M Y', strtotime($job['created_at'])); ?></span>
        <?php endif; ?>
      </div>
      <span class="tag">Open</span>
    </div>

    <a href="apply_job.php?job_id=<?php echo (int)$job['job_id']; ?>" class="btn-apply">
      <i class="bi bi-send"></i> Apply
    </a>
  </div>
  <?php endwhile; ?>

  <?php if(!$hasJobs): ?>
  <div class="empty-state">
    <i class="bi bi-inbox"></i>
    <p>No recommended jobs found at the moment.<br>Try updating your profile location.</p>
  </div>
  <?php endif; ?>

</main>

</body>
</html>
