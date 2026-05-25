<?php
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ตรวจสอบ category จาก URL parameter
$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';

// ดึง categories จาก DB
$browse_cats = [];
$cat_check = mysqli_query($conn,"SHOW TABLES LIKE 'categories'");
if($cat_check && mysqli_num_rows($cat_check) > 0){
    $cat_res = mysqli_query($conn,"SELECT * FROM categories ORDER BY category_id ASC");
    while($c = mysqli_fetch_assoc($cat_res)) $browse_cats[] = $c;
}
if(empty($browse_cats)){
    $browse_cats = [
        ['name'=>'IT','icon'=>'💻'],
        ['name'=>'Design','icon'=>'🎨'],
        ['name'=>'Marketing','icon'=>'📢'],
        ['name'=>'Accounting','icon'=>'💰'],
    ];
}

// ── ปิดงานที่ deadline ผ่านแล้วอัตโนมัติ (ทุกครั้งที่หน้าโหลด) ──
mysqli_query($conn,"
    UPDATE job SET status='closed'
    WHERE deadline IS NOT NULL
    AND deadline != ''
    AND deadline < CURDATE()
    AND status NOT IN ('closed','completed')
");

$query = "
    SELECT *
    FROM job
    WHERE admin_status='approved'
    AND status!='closed'
    ORDER BY created_at DESC
";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Browse Jobs</title>
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

  /* ── Sidebar (same as dashboard) ── */
  .sidebar {
    width: 240px; min-height: 100vh;
    background: var(--navy);
    display: flex; flex-direction: column;
    padding: 28px 0;
    position: fixed; top: 0; left: 0;
    z-index: 100;
  }
  .sidebar-brand { padding: 0 24px 28px; border-bottom: 1px solid var(--navy3); }
  .sidebar-brand .logo {
    display: flex; align-items: center; gap: 10px; text-decoration: none;
  }
  .logo-icon {
    width: 36px; height: 36px; background: var(--accent);
    border-radius: 10px; display: flex; align-items: center;
    justify-content: center; color: #fff; font-size: 18px;
  }
  .logo-text { font-size: 15px; font-weight: 600; color: #fff; line-height: 1.2; }
  .logo-sub  { font-size: 11px; color: var(--navy3); font-weight: 400; }
  .sidebar-nav { padding: 20px 12px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
  .nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 10px;
    color: #94a3b8; text-decoration: none;
    font-size: 13.5px; font-weight: 500;
    transition: background .15s, color .15s;
  }
  .nav-item:hover { background: var(--navy2); color: #e2e8f0; }
  .nav-item.active { background: var(--accent); color: #fff; }
  .nav-item i { font-size: 17px; width: 20px; text-align: center; }
  .nav-divider { height: 1px; background: var(--navy3); margin: 10px 14px; }
  .sidebar-footer { padding: 16px 12px 0; }
  .nav-logout {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 10px;
    color: #f87171; text-decoration: none;
    font-size: 13.5px; font-weight: 500; transition: background .15s;
  }
  .nav-logout:hover { background: rgba(239,68,68,.12); }
  .nav-logout i { font-size: 17px; }

  /* ── Main ── */
  .main { margin-left: 240px; flex: 1; padding: 36px 40px; min-height: 100vh; }

  /* ── Topbar ── */
  .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
  .topbar h2 { font-size: 22px; font-weight: 600; }
  .topbar p  { font-size: 13px; color: var(--muted); margin-top: 2px; }

  /* ── Search bar ── */
  .search-wrap {
    position: relative; margin-bottom: 16px;
  }
  .search-wrap i {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    font-size: 17px; color: var(--muted); pointer-events: none;
  }
  .search-wrap input {
    width: 100%; padding: 12px 16px 12px 44px;
    border: 1px solid var(--border); border-radius: var(--radius);
    font-family: 'Sora', sans-serif; font-size: 14px;
    background: var(--white); color: var(--text);
    outline: none; transition: border-color .15s, box-shadow .15s;
  }
  .search-wrap input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
  }

  /* ── Category pills ── */
  .category-bar {
    display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px;
  }
  .cat-pill {
    padding: 7px 16px; border-radius: 30px;
    border: 1px solid var(--border); background: var(--white);
    font-size: 13px; font-weight: 500; color: var(--muted);
    cursor: pointer; transition: all .15s;
    display: flex; align-items: center; gap: 6px;
  }
  .cat-pill:hover { border-color: var(--accent2); color: var(--accent); }
  .cat-pill.active {
    background: var(--accent); border-color: var(--accent);
    color: #fff;
  }
  .cat-pill i { font-size: 14px; }

  /* ── Results bar ── */
  .results-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
  }
  .results-bar span { font-size: 13px; color: var(--muted); }
  #result-count { font-weight: 600; color: var(--text); }

  /* ── Job card ── */
  .job-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px 24px;
    margin-bottom: 12px;
    display: flex; gap: 18px;
    transition: box-shadow .2s, border-color .2s;
  }
  .job-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.07); border-color: #c7d2fe; }
  .job-card.hidden { display: none; }

  .job-logo {
    width: 52px; height: 52px; flex-shrink: 0;
    border-radius: 12px; background: var(--light);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    overflow: hidden;
  }
  .job-logo.has-image { background: var(--white); }
  .job-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }

  .job-body { flex: 1; min-width: 0; }
  .job-top  { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
  .job-title { font-size: 15px; font-weight: 600; margin-bottom: 3px; }
  .job-desc  {
    font-size: 13px; color: var(--muted);
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
    margin: 6px 0 10px;
    line-height: 1.6;
  }
  .job-meta {
    display: flex; align-items: center; gap: 16px;
    flex-wrap: wrap; font-size: 12.5px; color: var(--muted);
  }
  .job-meta span { display: flex; align-items: center; gap: 4px; }
  .job-meta i { font-size: 13px; }

  .tag {
    display: inline-block; font-size: 11px; font-weight: 600;
    padding: 3px 10px; border-radius: 20px; margin-top: 10px; margin-right: 4px;
  }
  .tag-cat  { background: #eef2ff; color: var(--accent); }
  .tag-status { background: #d1fae5; color: #065f46; }

  .stars { color: var(--yellow); font-size: 13px; }

  .btn-apply {
    flex-shrink: 0; align-self: center;
    background: var(--accent); color: #fff;
    border: none; border-radius: 10px;
    padding: 10px 22px; font-size: 13px; font-weight: 600;
    text-decoration: none; white-space: nowrap;
    transition: background .15s, transform .1s;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .btn-apply:hover { background: #4f46e5; color: #fff; transform: translateY(-1px); }

  .btn-detail {
    flex-shrink: 0; align-self: center;
    background: var(--white); color: var(--text);
    border: 1px solid var(--border); border-radius: 10px;
    padding: 10px 22px; font-size: 13px; font-weight: 600;
    text-decoration: none; white-space: nowrap;
    transition: background .15s, transform .1s;
    display: inline-flex; align-items: center; gap: 6px;
    margin-right: 8px;
  }
  .btn-detail:hover {
      background: var(--light);
}

  /* ── Empty state ── */
  .empty-state {
    text-align: center; padding: 60px 20px; color: var(--muted);
    display: none;
  }
  .empty-state i { font-size: 44px; margin-bottom: 12px; display: block; }
  .empty-state p { font-size: 14px; }

  /* ── Salary filter ── */
  .salary-section { margin-bottom:16px; }
  .salary-label { font-size:12.5px; font-weight:600; color:var(--muted); margin-bottom:8px; display:flex; align-items:center; gap:6px; }
  .salary-pills { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
  .salary-pill { padding:6px 14px; border-radius:30px; border:1px solid var(--border); background:var(--white); font-size:12.5px; font-weight:500; color:var(--muted); cursor:pointer; transition:all .15s; }
  .salary-pill:hover { border-color:#a5b4fc; color:var(--accent); }
  .salary-pill.active { background:var(--accent); border-color:var(--accent); color:#fff; }

  .salary-custom { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .salary-custom label { font-size:12.5px; color:var(--muted); white-space:nowrap; }
  .salary-input { width:130px; padding:8px 12px; border:1px solid var(--border); border-radius:9px; font-family:'Sora',sans-serif; font-size:13px; color:var(--text); outline:none; transition:border-color .15s; }
  .salary-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.1); }
  .salary-sep { color:var(--muted); font-size:13px; }
  .btn-salary-apply { padding:8px 16px; background:var(--accent); color:#fff; border:none; border-radius:9px; font-family:'Sora',sans-serif; font-size:13px; font-weight:600; cursor:pointer; transition:background .15s; white-space:nowrap; }
  .btn-salary-apply:hover { background:#4f46e5; }
  .btn-salary-clear { padding:8px 12px; background:var(--light); color:var(--muted); border:1px solid var(--border); border-radius:9px; font-family:'Sora',sans-serif; font-size:13px; cursor:pointer; transition:background .15s; }
  .btn-salary-clear:hover { background:var(--border); }

  /* salary badge on card */
  .salary-badge { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:20px; background:#d1fae5; color:#065f46; }

  @media(max-width:768px){
    .sidebar { display: none; }
    .main { margin-left: 0; padding: 20px 16px; }
    .job-card { flex-wrap: wrap; }
    .btn-apply { width: 100%; justify-content: center; margin-top: 10px; }
  }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

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
    <a href="freelancer_dashboard.php" class="nav-item">
      <i class="bi bi-grid"></i> Dashboard
    </a>
    <a href="browse_jobs.php" class="nav-item active">
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

<!-- ── Main ── -->
<main class="main">

  <div class="topbar">
    <div>
      <h2>Browse Jobs</h2>
      <p>ค้นหางานที่ใช่สำหรับคุณ</p>
    </div>
  </div>

  <!-- Search -->
  <div class="search-wrap">
    <i class="bi bi-search"></i>
    <input type="text" id="search-input"
           placeholder="ค้นหาด้วย keyword เช่น React, กราฟิก, กรุงเทพฯ ..."
           oninput="filterJobs()" />
  </div>

  <!-- Category pills (ดึงจาก DB — Admin จัดการได้) -->
  <div class="category-bar" id="cat-bar">
    <button class="cat-pill active" data-cat="" onclick="setCategory(this)">
      <i class="bi bi-grid-3x3-gap"></i> ทั้งหมด
    </button>
    <?php foreach($browse_cats as $bc): ?>
    <button class="cat-pill" data-cat="<?php echo htmlspecialchars($bc['name']); ?>" onclick="setCategory(this)">
      <?php echo $bc['icon'].' '.htmlspecialchars($bc['name']); ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- Salary range filter -->
  <div style="margin-bottom:16px;">
    <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">
      <i class="bi bi-currency-dollar"></i> ช่วงเงินเดือน (บาท)
    </div>
    <div class="salary-pills">
      <button class="salary-pill active" data-min="0" data-max="0" onclick="setSalary(this)">ทั้งหมด</button>
      <button class="salary-pill" data-min="0" data-max="10000" onclick="setSalary(this)">ต่ำกว่า 10,000</button>
      <button class="salary-pill" data-min="10000" data-max="30000" onclick="setSalary(this)">10,000 – 30,000</button>
      <button class="salary-pill" data-min="30000" data-max="60000" onclick="setSalary(this)">30,000 – 60,000</button>
      <button class="salary-pill" data-min="60000" data-max="100000" onclick="setSalary(this)">60,000 – 100,000</button>
      <button class="salary-pill" data-min="100000" data-max="999999999" onclick="setSalary(this)">100,000 ขึ้นไป</button>
    </div>
    <div class="salary-custom">
      <label>กำหนดเอง:</label>
      <input type="number" id="sal-min" class="salary-input" placeholder="ขั้นต่ำ" min="0">
      <span class="salary-sep">–</span>
      <input type="number" id="sal-max" class="salary-input" placeholder="สูงสุด" min="0">
      <button class="btn-salary-apply" onclick="applyCustomSalary()">
        <i class="bi bi-funnel"></i> กรอง
      </button>
      <button class="btn-salary-clear" onclick="clearSalary()">
        <i class="bi bi-x"></i> ล้าง
      </button>
    </div>
  </div>

  <!-- Results count -->
  <div class="results-bar">
    <span>พบ <span id="result-count">0</span> ตำแหน่งงาน</span>
  </div>

  <!-- Job list (PHP render) -->
  <div id="job-list">
  <?php
  $icons = ['💼','🖥️','📐','📊','🚀','🎨','⚙️','📱','✍️','📢','🎓','💰'];
  $total = 0;

  if(mysqli_num_rows($result) == 0){
      echo '<p style="color:var(--muted);font-size:14px;">No jobs available</p>';
  }

  while($row = mysqli_fetch_assoc($result)):
      $total++;
      $employer_id  = $row['employer_id'];
      $rating_query = mysqli_query($conn,"
          SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
          FROM employer_review
          WHERE employer_id='$employer_id'
      ");
      $rating_data  = mysqli_fetch_assoc($rating_query);
      $avg_rating   = round($rating_data['avg_rating'], 1);
      $total_reviews= $rating_data['total_reviews'];

      $icon = $icons[crc32($row['title']) % count($icons)];
      $job_image = trim($row['image_path'] ?? '');

      // ---- category: ดึงจาก DB (ถ้ามี field 'category') หรือ fallback ว่าง
      $cat = isset($row['category']) ? htmlspecialchars($row['category']) : '';

      // star display
      $stars = '';
      if($avg_rating > 0){
          $full  = floor($avg_rating);
          $half  = ($avg_rating - $full) >= 0.5 ? 1 : 0;
          $empty = 5 - $full - $half;
          $stars .= str_repeat('<i class="bi bi-star-fill"></i>', $full);
          if($half) $stars .= '<i class="bi bi-star-half"></i>';
          $stars .= str_repeat('<i class="bi bi-star"></i>', $empty);
      }
      // parse salary เป็นตัวเลข
      $salary_num = 0;
      if(!empty($row['salary'])){
          preg_match('/[\d,]+/', str_replace(',','',$row['salary']), $m);
          $salary_num = isset($m[0]) ? intval($m[0]) : 0;
      }
  ?>
  <div class="job-card"
       data-title="<?php echo strtolower(htmlspecialchars($row['title'])); ?>"
       data-location="<?php echo strtolower(htmlspecialchars($row['location'])); ?>"
       data-desc="<?php echo strtolower(htmlspecialchars(strip_tags($row['description']))); ?>"
       data-cat="<?php echo $cat; ?>"
       data-salary="<?php echo $salary_num; ?>">

    <div class="job-logo <?php echo $job_image !== '' ? 'has-image' : ''; ?>">
      <?php if($job_image !== ''): ?>
      <img src="<?php echo htmlspecialchars($job_image); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
      <?php else: ?>
      <?php echo $icon; ?>
      <?php endif; ?>
    </div>

    <div class="job-body">
      <div class="job-top">
        <div>
          <div class="job-title"><?php echo htmlspecialchars($row['title']); ?></div>
          <?php if($cat): ?>
          <span class="tag tag-cat"><i class="bi bi-tag"></i> <?php echo $cat; ?></span>
          <?php endif; ?>
          <span class="tag tag-status"><?php echo htmlspecialchars($row['status']); ?></span>
        </div>
      </div>

      <p class="job-desc"><?php echo htmlspecialchars($row['description']); ?></p>

      <div class="job-meta">
        <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($row['location']); ?></span>
        <span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($row['salary']); ?></span>
        <?php if(!empty($row['created_at'])): ?>
        <span><i class="bi bi-clock"></i><?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
        <?php endif; ?>
        <span>
          <?php if($avg_rating > 0): ?>
            <span class="stars"><?php echo $stars; ?></span>
            <?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)
          <?php else: ?>
            <i class="bi bi-star" style="color:var(--yellow)"></i> No rating yet
          <?php endif; ?>
        </span>
      </div>
    </div>

    <a href="view_job.php?job_id=<?php echo (int)$row['job_id']; ?>" class="btn-detail">
      <i class="bi bi-info-circle"></i> Detail
    </a>

    <a href="apply_job.php?job_id=<?php echo (int)$row['job_id']; ?>" class="btn-apply">
      <i class="bi bi-send"></i> Apply
    </a>
  </div>
  <?php endwhile; ?>
  </div>

  <!-- Empty state -->
  <div class="empty-state" id="empty-state">
    <i class="bi bi-inbox"></i>
    <p>ไม่พบงานที่ตรงกับคำค้นหา<br>ลองเปลี่ยน keyword หรือหมวดหมู่ดูครับ</p>
  </div>

</main>

<script>
  let currentCat = <?php echo json_encode($selected_category, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
  let salMin = 0, salMax = 0; // 0,0 = ทั้งหมด
  const cards = document.querySelectorAll('.job-card');
  const countEl = document.getElementById('result-count');
  const emptyEl = document.getElementById('empty-state');

  countEl.textContent = cards.length;

  // เมื่อหน้า load ให้อัปเดต UI ตาม URL parameter
  document.addEventListener('DOMContentLoaded', function(){
    if(currentCat){
      const activePill = Array.from(document.querySelectorAll('.cat-pill')).find(p => p.dataset.cat === currentCat);
      if(activePill){
        document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
        activePill.classList.add('active');
      }
    }
    filterJobs();
  });

  function setCategory(el) {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    currentCat = el.dataset.cat;
    const url = new URL(window.location.href);
    if(currentCat){
      url.searchParams.set('category', currentCat);
    } else {
      url.searchParams.delete('category');
    }
    window.history.replaceState({}, '', url);
    filterJobs();
  }

  function setSalary(el) {
    document.querySelectorAll('.salary-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    salMin = parseInt(el.dataset.min);
    salMax = parseInt(el.dataset.max);
    // clear custom inputs
    document.getElementById('sal-min').value = '';
    document.getElementById('sal-max').value = '';
    filterJobs();
  }

  function applyCustomSalary() {
    const mn = parseInt(document.getElementById('sal-min').value) || 0;
    const mx = parseInt(document.getElementById('sal-max').value) || 999999999;
    salMin = mn; salMax = mx;
    // deactivate preset pills
    document.querySelectorAll('.salary-pill').forEach(p => p.classList.remove('active'));
    filterJobs();
  }

  function clearSalary() {
    salMin = 0; salMax = 0;
    document.getElementById('sal-min').value = '';
    document.getElementById('sal-max').value = '';
    // activate "ทั้งหมด" pill
    document.querySelector('.salary-pill[data-min="0"][data-max="0"]').classList.add('active');
    filterJobs();
  }

  function filterJobs() {
    const kw = document.getElementById('search-input').value.toLowerCase().trim();
    let visible = 0;

    cards.forEach(card => {
      const matchKw = !kw ||
        card.dataset.title.includes(kw) ||
        card.dataset.location.includes(kw) ||
        card.dataset.desc.includes(kw);

      const matchCat = !currentCat || card.dataset.cat === currentCat;

      // salary filter: salMin=0,salMax=0 = ไม่กรอง
      const s = parseInt(card.dataset.salary) || 0;
      let matchSal = true;
      if(salMin === 0 && salMax === 0){
        matchSal = true; // ทั้งหมด
      } else if(salMax === 0 || salMax === 999999999){
        matchSal = s >= salMin; // ขั้นต่ำขึ้นไป
      } else if(salMin === 0){
        matchSal = s <= salMax && s > 0; // ต่ำกว่าขั้นสูงสุด
      } else {
        matchSal = s >= salMin && s <= salMax;
      }
      // ถ้างานไม่ระบุเงินเดือน (salary=0) ให้โชว์เสมอยกเว้นกรอง preset
      if(s === 0 && (salMin > 0 || salMax > 0)) matchSal = false;

      if(matchKw && matchCat && matchSal){
        card.classList.remove('hidden');
        visible++;
      } else {
        card.classList.add('hidden');
      }
    });

    countEl.textContent = visible;
    emptyEl.style.display = visible === 0 ? 'block' : 'none';
  }
</script>
</body>
</html>
