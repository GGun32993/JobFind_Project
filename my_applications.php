<?php
require_once __DIR__ . "/config.php";
require_once "job_image_helpers.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: login.php");
    exit();
}

$freelancer_id = $_SESSION['user_id'];
ensure_job_image_schema($conn);

$result = mysqli_query($conn,"
    SELECT job_application.*,
           job.job_id,
           job.title,
           job.description,
           job.category,
           job.image_path,
           job.location,
           job.salary,
           job.status AS job_status,
           job.employer_id
    FROM job_application
    JOIN job ON job.job_id = job_application.job_id
    WHERE job_application.freelancer_id='$freelancer_id'
    ORDER BY job_application.application_id DESC
");

$total      = mysqli_num_rows($result);
$pending    = 0; $accepted = 0; $rejected = 0; $completed = 0;

// pre-fetch all rows to count stats
$rows = [];
while($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $s = strtolower($r['status']);
    if($s === 'pending')   $pending++;
    elseif($s === 'accepted' || $s === 'approved') $accepted++;
    elseif($s === 'rejected') $rejected++;
    if(strtolower($r['job_status']) === 'completed') $completed++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=11">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Applications</title>
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
    --yellow: #f59e0b;
    --red:    #ef4444;
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
  .nav-logout i { font-size:17px; }

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; min-height:100vh; }

  /* ── Topbar ── */
  .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Stat cards ── */
  .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:28px; }
  .stat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; }
  .stat-card .label { font-size:12px; color:var(--muted); margin-bottom:6px; }
  .stat-card .value { font-size:26px; font-weight:600; line-height:1; }
  .stat-card.s-total  .value { color:var(--accent); }
  .stat-card.s-pend   .value { color:var(--yellow); }
  .stat-card.s-ok     .value { color:var(--green); }
  .stat-card.s-done   .value { color:var(--navy3); }

  /* ── Filter tabs ── */
  .filter-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
  .ftab { padding:7px 16px; border-radius:30px; border:1px solid var(--border); background:var(--white); font-size:13px; font-weight:500; color:var(--muted); cursor:pointer; transition:all .15s; }
  .ftab:hover { border-color:#a5b4fc; color:var(--accent); }
  .ftab.active { background:var(--accent); border-color:var(--accent); color:#fff; }

  /* ── App card ── */
  .app-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:22px 24px; margin-bottom:12px; display:flex; gap:18px; transition:box-shadow .2s,border-color .2s; }
  .app-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); border-color:#c7d2fe; }
  .app-card.hidden { display:none; }

  .app-icon { width:52px; height:52px; flex-shrink:0; border-radius:12px; background:var(--light); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:24px; overflow:hidden; }
  .app-icon.has-image { background:var(--white); }
  .app-icon img { width:100%; height:100%; object-fit:cover; display:block; }

  .app-body { flex:1; min-width:0; }
  .app-title { font-size:15px; font-weight:600; margin-bottom:4px; }
  .app-desc  { font-size:13px; color:var(--muted); margin:5px 0 10px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.6; }
  .app-meta  { display:flex; align-items:center; gap:14px; font-size:12.5px; color:var(--muted); flex-wrap:wrap; }
  .app-meta span { display:flex; align-items:center; gap:4px; }
  .app-meta i { font-size:13px; }

  .badge-pill { display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:600; padding:4px 12px; border-radius:20px; }
  .bp-pending   { background:#fef9c3; color:#854d0e; }
  .bp-accepted  { background:#d1fae5; color:#065f46; }
  .bp-rejected  { background:#fee2e2; color:#991b1b; }
  .bp-other     { background:#f1f5f9; color:var(--muted); }
  .bp-completed { background:#e0e7ff; color:#3730a3; }
  .bp-reviewed  { background:#f1f5f9; color:var(--muted); }

  .app-actions { display:flex; align-items:center; gap:8px; margin-top:14px; padding-top:12px; border-top:1px solid var(--border); flex-wrap:wrap; }

  .btn-detail {
    display:inline-flex; align-items:center; gap:6px;
    background:var(--white); color:var(--accent); border:1px solid var(--border);
    border-radius:10px; padding:8px 18px;
    font-size:13px; font-weight:600; text-decoration:none;
    transition:background .15s, border-color .15s, transform .1s;
  }
  .btn-detail:hover { background:#eef2ff; border-color:#a5b4fc; color:var(--accent); transform:translateY(-1px); }

  .btn-rate {
    display:inline-flex; align-items:center; gap:6px;
    background:var(--yellow); color:#fff; border:none;
    border-radius:10px; padding:8px 18px;
    font-size:13px; font-weight:600; text-decoration:none;
    transition:opacity .15s, transform .1s;
  }
  .btn-rate:hover { opacity:.88; color:#fff; transform:translateY(-1px); }

  /* ── Empty ── */
  .empty-state { text-align:center; padding:60px 20px; color:var(--muted); display:none; }
  .empty-state i { font-size:44px; margin-bottom:12px; display:block; }
  .empty-state p { font-size:14px; }

  .empty-all { text-align:center; padding:60px 20px; color:var(--muted); }
  .empty-all i { font-size:44px; margin-bottom:12px; display:block; }

  @media(max-width:768px){
    .sidebar { display:none; }
    .main { margin-left:0; padding:20px 16px; }
    .stat-grid { grid-template-columns:repeat(2,1fr); }
    .app-card { flex-wrap:wrap; }
  }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon" style="width:180px!important;height:180px!important;min-width:180px!important;max-width:180px!important;max-height:180px!important;flex:0 0 180px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=11" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Job_Find</div>
        <div class="logo-sub" style="display:none!important;">Freelancer</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="freelancer_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="browse_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Browse Jobs</a>
    <a href="my_applications.php" class="nav-item active"><i class="bi bi-file-earmark-text"></i> My Applications</a>
    <a href="my_profile.php" class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="freelancer_reviews.php" class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="upload_resume.php" class="nav-item"><i class="bi bi-cloud-upload"></i> Upload Resume</a>
    <div class="nav-divider"></div>
    <a href="support_messages.php" class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ─ -->
<main class="main">

  <!-- ✅ ปุ่มย้อนกลับ -->
  <a href="job_review.php" class="btn-back" style="display:inline-flex;align-items:center;gap:7px;background:var(--white);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:9px 18px;font-size:13.5px;font-weight:500;text-decoration:none;margin-bottom:16px;transition:background .15s;">
    <i class="bi bi-arrow-left"></i> กลับไปรีวิวงาน
  </a>

  <div class="topbar">
    <div>
      <h2>My Applications</h2>
      <p>ติดตามสถานะการสมัครงานทั้งหมดของคุณ</p>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card s-total">
      <div class="label"><i class="bi bi-collection"></i> ทั้งหมด</div>
      <div class="value"><?php echo $total; ?></div>
    </div>
    <div class="stat-card s-pend">
      <div class="label"><i class="bi bi-hourglass-split"></i> รอพิจารณา</div>
      <div class="value"><?php echo $pending; ?></div>
    </div>
    <div class="stat-card s-ok">
      <div class="label"><i class="bi bi-check-circle"></i> ผ่านการคัดเลือก</div>
      <div class="value"><?php echo $accepted; ?></div>
    </div>
    <div class="stat-card s-done">
      <div class="label"><i class="bi bi-flag"></i> งานเสร็จสิ้น</div>
      <div class="value"><?php echo $completed; ?></div>
    </div>
  </div>

  <!-- Filter tabs -->
  <div class="filter-tabs">
    <button class="ftab active" data-filter="" onclick="setFilter(this)">ทั้งหมด</button>
    <button class="ftab" data-filter="pending"   onclick="setFilter(this)"><i class="bi bi-hourglass-split"></i> Pending</button>
    <button class="ftab" data-filter="accepted"  onclick="setFilter(this)"><i class="bi bi-check-circle"></i> Accepted</button>
    <button class="ftab" data-filter="rejected"  onclick="setFilter(this)"><i class="bi bi-x-circle"></i> Rejected</button>
    <button class="ftab" data-filter="completed" onclick="setFilter(this)"><i class="bi bi-flag"></i> Completed</button>
  </div>

  <!-- Application cards -->
  <?php if($total === 0): ?>
  <div class="empty-all">
    <i class="bi bi-inbox"></i>
    <p>คุณยังไม่มีการสมัครงาน<br><a href="browse_jobs.php" style="color:var(--accent);">เริ่มหางานได้เลย →</a></p>
  </div>
  <?php else: ?>

  <?php
  $categoryIcons = [
    'IT & Software' => '💻',
    'Design' => '🎨',
    'Marketing' => '📢',
    'Writing' => '✍️',
    'Finance' => '💰',
    'Education' => '🎓',
    'Other' => '📦',
  ];
  $icons = ['💼','🖥️','📐','📊','🚀','🎨','⚙️','📱','✍️','📢','🎓','💰'];
  foreach($rows as $row):
    $category = trim($row['category'] ?? '');
    $icon = $categoryIcons[$category] ?? $icons[crc32($row['title']) % count($icons)] ?? '💼';
    if($icon === '') $icon = '💼';
    $job_id = $row['job_id'];
    $job_image = get_job_primary_image($conn, $job_id, $row['image_path'] ?? '');
    $app_status  = strtolower($row['status']);
    $job_status  = strtolower($row['job_status']);

    // badge for application status
    $badgeClass = match($app_status) {
      'pending'           => 'bp-pending',
      'accepted','approved' => 'bp-accepted',
      'rejected'          => 'bp-rejected',
      default             => 'bp-other',
    };
    $badgeIcon = match($app_status) {
      'pending'           => 'bi-hourglass-split',
      'accepted','approved' => 'bi-check-circle-fill',
      'rejected'          => 'bi-x-circle-fill',
      default             => 'bi-circle',
    };

    // badge for job status
    $jobBadgeClass = ($job_status === 'completed') ? 'bp-completed' : (($job_status === 'closed') ? 'bp-completed' : 'bp-other');

    // data-filter for JS
    $filterVal = ($app_status === 'accepted' || $app_status === 'approved') ? 'accepted' : $app_status;
  ?>
  <div class="app-card" data-filter="<?php echo htmlspecialchars($filterVal); ?>">
    <div class="app-icon <?php echo $job_image !== '' ? 'has-image' : ''; ?>">
      <?php if($job_image !== ''): ?>
        <img src="<?php echo htmlspecialchars($job_image); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
      <?php else: ?>
        <?php echo $icon; ?>
      <?php endif; ?>
    </div>

    <div class="app-body">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div class="app-title"><?php echo htmlspecialchars($row['title']); ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
          <span class="badge-pill <?php echo $badgeClass; ?>">
            <i class="bi <?php echo $badgeIcon; ?>"></i>
            <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
          </span>
          <span class="badge-pill <?php echo $jobBadgeClass; ?>">
            <i class="bi bi-briefcase"></i>
            <?php echo htmlspecialchars(ucfirst($row['job_status'])); ?>
          </span>
        </div>
      </div>

      <p class="app-desc"><?php echo htmlspecialchars($row['description']); ?></p>

      <div class="app-meta">
        <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($row['location']); ?></span>
        <span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($row['salary']); ?></span>
      </div>

      <?php
      // แสดงปุ่มรีวิวเมื่อ accepted (บริษัทรับแล้ว) หรือ completed
      $can_review = ($app_status === 'accepted' || $app_status === 'approved' || $job_status === 'completed');
      $employer_id = $row['employer_id'];

      $check_review = mysqli_query($conn,"
          SELECT * FROM employer_review
          WHERE job_id='$job_id'
          AND freelancer_id='$freelancer_id'
      ");
      $already_reviewed = mysqli_num_rows($check_review) > 0;
      ?>

      <div class="app-actions">
        <a href="view_job.php?job_id=<?php echo (int)$job_id; ?>&return_url=<?php echo urlencode('my_applications.php'); ?>" class="btn-detail">
          <i class="bi bi-eye"></i> Detail
        </a>
        <?php if($can_review): ?>
        <?php if(!$already_reviewed): ?>
        <!-- ใหม่ -->
        <a href="job_review.php?job_id=<?php echo (int)$job_id; ?>" class="btn-rate">
          <i class="bi bi-star-fill"></i> รีวิวงาน
        </a>
        <?php else: ?>
        <span class="badge-pill bp-reviewed">
          <i class="bi bi-check-circle-fill"></i> รีวิวแล้ว
        </span>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="empty-state" id="empty-filter">
    <i class="bi bi-funnel"></i>
    <p>ไม่มีรายการในหมวดนี้</p>
  </div>

  <?php endif; ?>

</main>

<script>
  let currentFilter = '';

  function setFilter(el) {
    document.querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    currentFilter = el.dataset.filter;

    const cards = document.querySelectorAll('.app-card');
    let visible = 0;
    cards.forEach(c => {
      const match = !currentFilter || c.dataset.filter === currentFilter;
      c.classList.toggle('hidden', !match);
      if(match) visible++;
    });

    const emp = document.getElementById('empty-filter');
    if(emp) emp.style.display = visible === 0 ? 'block' : 'none';
  }
</script>
</body>
</html>
