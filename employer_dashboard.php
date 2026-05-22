<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ── stats ──
$total_jobs   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM job WHERE employer_id='$user_id'"))['c'];
$active_jobs  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM job WHERE employer_id='$user_id' AND status='approved'"))['c'];
$total_apps   = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS c FROM job_application ja
    JOIN job j ON j.job_id = ja.job_id
    WHERE j.employer_id='$user_id'
"))['c'];
$pending_apps = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS c FROM job_application ja
    JOIN job j ON j.job_id = ja.job_id
    WHERE j.employer_id='$user_id' AND ja.status='pending'
"))['c'];

// ── employer rating ──
$rating_data = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT AVG(rating) AS avg_r, COUNT(*) AS total_r
    FROM employer_review WHERE employer_id='$user_id'
"));
$avg_rating   = round($rating_data['avg_r'] ?? 0, 1);
$total_review = $rating_data['total_r'] ?? 0;

// ── recent applications ──
$recent_apps = mysqli_query($conn,"
    SELECT ja.status, ja.application_id, j.title, u.username AS freelancer
    FROM job_application ja
    JOIN job j ON j.job_id = ja.job_id
    JOIN users u ON u.user_id = ja.freelancer_id
    WHERE j.employer_id='$user_id'
    ORDER BY ja.application_id DESC
    LIMIT 5
");

// ── recent jobs ──
$recent_jobs = mysqli_query($conn,"
    SELECT title, status, admin_status, created_at
    FROM job
    WHERE employer_id='$user_id'
    ORDER BY created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employer Dashboard</title>
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
    --blue:   #0ea5e9;  --radius: 14px;
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
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#94a3b8; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s,color .15s; position:relative; }
  .nav-item:hover  { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-badge { position:absolute; right:12px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }
  .nav-logout i { font-size:17px; }

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; }

  /* ── Topbar ── */
  .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .topbar-avatar { width:44px; height:44px; border-radius:50%; background:var(--accent); color:#fff; font-size:16px; font-weight:600; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; }

  /* ── Stat grid ── */
  .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
  .stat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 22px; display:flex; align-items:center; gap:16px; }
  .stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
  .si-purple { background:#eef2ff; color:var(--accent); }
  .si-green  { background:#d1fae5; color:var(--green); }
  .si-yellow { background:#fef9c3; color:var(--yellow); }
  .si-blue   { background:#e0f2fe; color:var(--blue); }
  .si-star   { background:#fef9c3; color:#d97706; }
  .stat-info .value { font-size:26px; font-weight:600; line-height:1; }
  .stat-info .label { font-size:12px; color:var(--muted); margin-top:4px; }

  /* ── Quick links ── */
  .quick-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:28px; }
  .quick-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 18px; text-decoration:none; color:var(--text); display:flex; flex-direction:column; align-items:flex-start; gap:10px; transition:box-shadow .2s,border-color .2s,transform .15s; position:relative; }
  .quick-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.09); border-color:#c7d2fe; transform:translateY(-2px); color:var(--text); }
  .qc-icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:20px; }
  .qc-title { font-size:14px; font-weight:600; }
  .qc-sub   { font-size:12px; color:var(--muted); }
  .qc-arrow { position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:18px; color:var(--border); transition:color .15s,right .15s; }
  .quick-card:hover .qc-arrow { color:var(--accent); right:12px; }
  .pending-dot { position:absolute; top:14px; right:14px; width:8px; height:8px; border-radius:50%; background:var(--red); }

  /* ── Rating block ── */
  .rating-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 22px; margin-bottom:28px; display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
  .rating-big { font-size:42px; font-weight:600; line-height:1; }
  .rating-stars { color:var(--yellow); font-size:20px; margin:4px 0; }
  .rating-sub  { font-size:12px; color:var(--muted); }
  .rating-divider { width:1px; height:60px; background:var(--border); flex-shrink:0; }
  .rating-tip { font-size:13px; color:var(--muted); line-height:1.7; }
  .rating-tip strong { color:var(--text); }

  /* ── Two col ── */
  .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }

  /* ── Table card ── */
  .table-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
  .table-head { padding:18px 22px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
  .table-head h4 { font-size:15px; font-weight:600; }
  .table-head a  { font-size:12.5px; color:var(--accent); text-decoration:none; font-weight:500; }
  .table-head a:hover { text-decoration:underline; }
  .mini-table { width:100%; border-collapse:collapse; }
  .mini-table th { font-size:11.5px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; padding:10px 22px; text-align:left; border-bottom:1px solid var(--border); background:var(--light); }
  .mini-table td { font-size:13px; padding:11px 22px; border-bottom:1px solid var(--border); vertical-align:middle; }
  .mini-table tr:last-child td { border-bottom:none; }
  .mini-table tr:hover td { background:#f8f9ff; }
  .status-pill { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; }
  .sp-pending  { background:#fef9c3; color:#854d0e; }
  .sp-approved { background:#d1fae5; color:#065f46; }
  .sp-rejected { background:#fee2e2; color:#991b1b; }
  .sp-accepted { background:#d1fae5; color:#065f46; }
  .empty-td { text-align:center; color:var(--muted); padding:28px !important; font-size:13px; }

  @media(max-width:1100px){ .stat-grid,.quick-grid { grid-template-columns:repeat(2,1fr); } }
  @media(max-width:768px) { .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .two-col { grid-template-columns:1fr; } }
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
        <div class="logo-sub">Employer</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="employer_dashboard.php"  class="nav-item active"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"            class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item">
      <i class="bi bi-briefcase"></i> Manage Jobs
      <?php if($pending_apps > 0): ?><span class="nav-badge"><?php echo $pending_apps; ?></span><?php endif; ?>
    </a>
    <a href="saved_freelancers.php" class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="employer_reviews.php"    class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_review.php"     class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
    <a href="employer_profile.php"    class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="support_chat.php"        class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div>
      <h2>Welcome back, <?php echo htmlspecialchars($username); ?> 👋</h2>
      <p>จัดการงานและดูการสมัครของ Freelancer ได้ที่นี่</p>
    </div>
    <div class="topbar-avatar"><?php echo strtoupper(substr($username,0,1)); ?></div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-briefcase"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_jobs; ?></div>
        <div class="label">งานทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-check-circle"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $active_jobs; ?></div>
        <div class="label">งานที่เปิดรับ</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-file-earmark-text"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_apps; ?></div>
        <div class="label">การสมัครทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-yellow"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $pending_apps; ?></div>
        <div class="label">รอพิจารณา</div>
      </div>
    </div>
  </div>

  <!-- Quick links -->
  <div class="quick-grid">
    <a href="post_job.php" class="quick-card">
      <div class="qc-icon si-purple"><i class="bi bi-plus-circle"></i></div>
      <div class="qc-title">Post Job</div>
      <div class="qc-sub">โพสต์งานใหม่</div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="employer_manage_jobs.php" class="quick-card">
      <?php if($pending_apps > 0): ?><div class="pending-dot"></div><?php endif; ?>
      <div class="qc-icon si-green"><i class="bi bi-briefcase"></i></div>
      <div class="qc-title">Manage Jobs</div>
      <div class="qc-sub"><?php echo $pending_apps > 0 ? $pending_apps.' คนรอพิจารณา' : 'จัดการตำแหน่งงาน'; ?></div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="employer_reviews.php" class="quick-card">
      <div class="qc-icon si-star"><i class="bi bi-star"></i></div>
      <div class="qc-title">My Reviews</div>
      <div class="qc-sub"><?php echo $avg_rating > 0 ? '⭐ '.$avg_rating.' / 5.0' : 'ยังไม่มีรีวิว'; ?></div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="employer_profile.php" class="quick-card">
      <div class="qc-icon si-blue"><i class="bi bi-person-circle"></i></div>
      <div class="qc-title">My Profile</div>
      <div class="qc-sub">แก้ไขโปรไฟล์</div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
  </div>

  <!-- Rating summary (ถ้ามีรีวิว) -->
  <?php if($avg_rating > 0): ?>
  <div class="rating-card" style="margin-bottom:28px;">
    <div>
      <div class="rating-big"><?php echo $avg_rating; ?></div>
      <div class="rating-stars">
        <?php
          $full  = floor($avg_rating);
          $half  = ($avg_rating - $full) >= 0.5 ? 1 : 0;
          $empty = 5 - $full - $half;
          echo str_repeat('★',$full);
          if($half) echo '½';
          echo str_repeat('☆',$empty);
        ?>
      </div>
      <div class="rating-sub"><?php echo $total_review; ?> รีวิว</div>
    </div>
    <div class="rating-divider"></div>
    <div class="rating-tip">
      <strong>คะแนนจาก Freelancer</strong><br>
      คะแนนนี้จะแสดงบนหน้างานของคุณ<br>
      ช่วยให้ Freelancer ตัดสินใจสมัครได้ง่ายขึ้น
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent tables -->
  <div class="two-col">

    <!-- Recent Applications -->
    <div class="table-card">
      <div class="table-head">
        <h4><i class="bi bi-file-earmark-text" style="color:var(--accent);margin-right:6px;"></i>การสมัครล่าสุด</h4>
        <a href="employer_manage_jobs.php">ดูทั้งหมด →</a>
      </div>
      <table class="mini-table">
        <thead>
          <tr><th>Freelancer</th><th>ตำแหน่ง</th><th>สถานะ</th></tr>
        </thead>
        <tbody>
        <?php
          $has = false;
          while($a = mysqli_fetch_assoc($recent_apps)):
            $has = true;
            $sc  = match(strtolower($a['status'])){ 'accepted'=>'sp-accepted','rejected'=>'sp-rejected',default=>'sp-pending' };
        ?>
          <tr>
            <td style="font-weight:500;"><?php echo htmlspecialchars($a['freelancer']); ?></td>
            <td style="color:var(--muted);font-size:12.5px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($a['title']); ?></td>
            <td><span class="status-pill <?php echo $sc; ?>"><?php echo ucfirst($a['status']); ?></span></td>
          </tr>
        <?php endwhile; ?>
        <?php if(!$has): ?>
          <tr><td colspan="3" class="empty-td">ยังไม่มีการสมัคร</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Jobs -->
    <div class="table-card">
      <div class="table-head">
        <h4><i class="bi bi-briefcase" style="color:var(--accent);margin-right:6px;"></i>งานของคุณ</h4>
        <a href="employer_manage_jobs.php">ดูทั้งหมด →</a>
      </div>
      <table class="mini-table">
        <thead>
          <tr><th>ตำแหน่ง</th><th>Admin</th><th>วันที่</th></tr>
        </thead>
        <tbody>
        <?php
          $has2 = false;
          while($j = mysqli_fetch_assoc($recent_jobs)):
            $has2 = true;
            $sc2  = match(strtolower($j['admin_status'])){ 'approved'=>'sp-approved','rejected'=>'sp-rejected',default=>'sp-pending' };
        ?>
          <tr>
            <td style="font-weight:500;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($j['title']); ?></td>
            <td><span class="status-pill <?php echo $sc2; ?>"><?php echo ucfirst($j['admin_status']); ?></span></td>
            <td style="color:var(--muted);font-size:12px;"><?php echo !empty($j['created_at']) ? date('d M Y',strtotime($j['created_at'])) : '—'; ?></td>
          </tr>
        <?php endwhile; ?>
        <?php if(!$has2): ?>
          <tr><td colspan="3" class="empty-td">ยังไม่มีงาน — <a href="post_job.php" style="color:var(--accent);">โพสต์เลย</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

</main>
</body>
</html>
