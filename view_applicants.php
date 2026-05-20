<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: login.php");
    exit();
}

if(!isset($_GET['job_id'])){
    header("Location: employer_manage_jobs.php");
    exit();
}

$job_id      = intval($_GET['job_id']);
$employer_id = $_SESSION['user_id'];

// ตรวจสอบว่า job เป็นของ employer นี้
$check = mysqli_query($conn,"SELECT * FROM job WHERE job_id='$job_id' AND employer_id='$employer_id'");
if(mysqli_num_rows($check)==0){ echo "Invalid job"; exit(); }

$job_info = mysqli_fetch_assoc($check);

// ดึง applicants
$result = mysqli_query($conn,"
    SELECT
        ja.application_id,
        ja.freelancer_id,
        ja.status,
        u.username,
        fp.skill,
        fp.location,
        fp.experience,
        (SELECT AVG(rating) FROM freelancer_review WHERE freelancer_id=ja.freelancer_id) AS avg_rating,
        (SELECT COUNT(*) FROM freelancer_review WHERE freelancer_id=ja.freelancer_id) AS total_reviews
    FROM job_application ja
    JOIN users u ON u.user_id = ja.freelancer_id
    LEFT JOIN freelancer_profile fp ON fp.user_id = ja.freelancer_id
    WHERE ja.job_id='$job_id'
    ORDER BY ja.application_id DESC
");

$rows        = [];
$cnt_all     = 0;
$cnt_pending = 0;
$cnt_hired   = 0;
$cnt_reject  = 0;

while($r = mysqli_fetch_assoc($result)){
    $rows[] = $r;
    $cnt_all++;
    $s = strtolower($r['status']);
    if($s === 'pending')  $cnt_pending++;
    elseif($s === 'accepted' || $s === 'hired') $cnt_hired++;
    elseif($s === 'rejected') $cnt_reject++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Applicants</title>
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

  /* ── Topbar ── */
  .topbar { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:28px; flex-wrap:wrap; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .btn-back { display:inline-flex; align-items:center; gap:7px; background:var(--white); border:1px solid var(--border); color:var(--text); border-radius:10px; padding:9px 18px; font-size:13.5px; font-weight:500; text-decoration:none; transition:background .15s; white-space:nowrap; }
  .btn-back:hover { background:var(--light); color:var(--text); }

  /* ── Job info banner ── */
  .job-banner { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 24px; margin-bottom:22px; display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
  .job-banner .job-icon { font-size:28px; }
  .job-banner .job-title { font-size:16px; font-weight:600; }
  .job-banner .job-meta  { font-size:12.5px; color:var(--muted); margin-top:3px; display:flex; gap:14px; flex-wrap:wrap; }
  .job-banner .job-meta span { display:flex; align-items:center; gap:4px; }

  /* ── Stat row ── */
  .stat-row { display:flex; gap:12px; margin-bottom:22px; flex-wrap:wrap; }
  .stat-mini { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:14px 20px; display:flex; align-items:center; gap:12px; flex:1; min-width:110px; cursor:pointer; transition:border-color .15s; }
  .stat-mini:hover { border-color:#a5b4fc; }
  .stat-mini.active-f { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.1); }
  .sm-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
  .sm-val  { font-size:20px; font-weight:600; line-height:1; }
  .sm-lbl  { font-size:11.5px; color:var(--muted); margin-top:2px; }
  .si-purple { background:#eef2ff; color:var(--accent); }
  .si-yellow { background:#fef9c3; color:#854d0e; }
  .si-green  { background:#d1fae5; color:var(--green); }
  .si-red    { background:#fee2e2; color:var(--red); }

  /* ── Applicant card ── */
  .app-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:22px 24px; margin-bottom:12px; transition:box-shadow .2s,border-color .2s; }
  .app-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); border-color:#c7d2fe; }
  .app-card.hidden { display:none; }
  .app-card.hired-card { border-left:3px solid var(--green); }

  .ac-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
  .ac-user { display:flex; align-items:center; gap:12px; }
  .ac-avatar { width:48px; height:48px; border-radius:50%; background:var(--accent); color:#fff; font-size:17px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .ac-name { font-size:15px; font-weight:600; }
  .ac-meta { font-size:12.5px; color:var(--muted); margin-top:3px; display:flex; gap:12px; flex-wrap:wrap; }
  .ac-meta span { display:flex; align-items:center; gap:4px; }

  /* status pill */
  .s-pill { display:inline-flex; align-items:center; gap:4px; font-size:11.5px; font-weight:600; padding:5px 13px; border-radius:20px; flex-shrink:0; }
  .sp-pending  { background:#fef9c3; color:#854d0e; }
  .sp-accepted { background:#d1fae5; color:#065f46; }
  .sp-hired    { background:#d1fae5; color:#065f46; }
  .sp-rejected { background:#fee2e2; color:#991b1b; }

  /* rating stars */
  .star-row { color:var(--yellow); font-size:13px; }

  /* experience preview */
  .ac-exp { font-size:13px; color:var(--muted); line-height:1.7; background:var(--light); border-radius:9px; padding:10px 14px; margin:10px 0; border-left:3px solid #c7d2fe; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

  /* action buttons */
  .ac-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding-top:14px; border-top:1px solid var(--border); margin-top:4px; }
  .act-wrap { display:flex; gap:8px; flex-wrap:wrap; }
  .act-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13px; font-weight:500; text-decoration:none; border:none; cursor:pointer; font-family:'Sora',sans-serif; transition:opacity .15s,transform .1s; white-space:nowrap; }
  .act-btn:hover { opacity:.85; transform:translateY(-1px); }
  .ab-resume  { background:#e0f2fe; color:#0369a1; }
  .ab-hire    { background:#d1fae5; color:#065f46; }
  .ab-rate    { background:#fef9c3; color:#854d0e; }
  .ab-reject  { background:#fee2e2; color:#991b1b; }
  .ab-hired-done { background:var(--light); color:var(--muted); cursor:default; }
  .ab-hired-done:hover { transform:none; opacity:1; }
  .no-resume  { font-size:12.5px; color:var(--muted); display:flex; align-items:center; gap:4px; }

  /* empty */
  .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
  .empty-state i { font-size:44px; color:#c7d2fe; margin-bottom:12px; display:block; }
  .empty-state p { font-size:14px; }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
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
    <a href="employer_dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item active"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="employer_reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_profile.php"     class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="support_chat.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <?php if(isset($_GET['hired'])): ?>
  <div class="toast-bar" id="toast" style="position:fixed;top:24px;right:24px;z-index:999;background:#0f172a;color:#fff;padding:14px 20px;border-radius:12px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.18);transition:opacity .4s;">
    <i class="bi bi-check-circle-fill" style="color:#10b981;font-size:18px;"></i> รับ Freelancer เข้าทำงานเรียบร้อยแล้ว
  </div>
  <?php elseif(isset($_GET['rejected'])): ?>
  <div class="toast-bar" id="toast" style="position:fixed;top:24px;right:24px;z-index:999;background:#0f172a;color:#fff;padding:14px 20px;border-radius:12px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.18);transition:opacity .4s;">
    <i class="bi bi-x-circle-fill" style="color:#f87171;font-size:18px;"></i> ปฏิเสธผู้สมัครเรียบร้อยแล้ว
  </div>
  <?php elseif(isset($_GET['reviewed'])): ?>
  <div class="toast-bar" id="toast" style="position:fixed;top:24px;right:24px;z-index:999;background:#0f172a;color:#fff;padding:14px 20px;border-radius:12px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.18);transition:opacity .4s;">
    <i class="bi bi-star-fill" style="color:#f59e0b;font-size:18px;"></i> ส่งรีวิวเรียบร้อยแล้ว ขอบคุณ!
  </div>
  <?php endif; ?>
  <?php if(isset($_GET['hired']) || isset($_GET['rejected']) || isset($_GET['reviewed'])): ?>
  <script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3500);</script>
  <?php endif; ?>

  <div class="topbar">
    <div>
      <h2>ผู้สมัครงาน</h2>
      <p>รายชื่อ Freelancer ที่สมัครมาทั้งหมด</p>
    </div>
    <a href="employer_manage_jobs.php" class="btn-back">
      <i class="bi bi-arrow-left"></i> กลับ Manage Jobs
    </a>
  </div>

  <!-- Job info banner -->
  <div class="job-banner">
    <span class="job-icon">💼</span>
    <div>
      <div class="job-title"><?php echo htmlspecialchars($job_info['title']); ?></div>
      <div class="job-meta">
        <?php if(!empty($job_info['location'])): ?>
        <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($job_info['location']); ?></span>
        <?php endif; ?>
        <?php if(!empty($job_info['salary'])): ?>
        <span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($job_info['salary']); ?></span>
        <?php endif; ?>
        <span><i class="bi bi-people"></i><?php echo $cnt_all; ?> คนสมัคร</span>
      </div>
    </div>
  </div>

  <!-- Stat row -->
  <div class="stat-row">
    <div class="stat-mini active-f" data-f="" onclick="setFilter(this,'')">
      <div class="sm-icon si-purple"><i class="bi bi-people"></i></div>
      <div><div class="sm-val"><?php echo $cnt_all; ?></div><div class="sm-lbl">ทั้งหมด</div></div>
    </div>
    <div class="stat-mini" data-f="pending" onclick="setFilter(this,'pending')">
      <div class="sm-icon si-yellow"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="sm-val"><?php echo $cnt_pending; ?></div><div class="sm-lbl">รอพิจารณา</div></div>
    </div>
    <div class="stat-mini" data-f="hired" onclick="setFilter(this,'hired')">
      <div class="sm-icon si-green"><i class="bi bi-check-circle"></i></div>
      <div><div class="sm-val"><?php echo $cnt_hired; ?></div><div class="sm-lbl">รับเข้าทำงาน</div></div>
    </div>
    <div class="stat-mini" data-f="rejected" onclick="setFilter(this,'rejected')">
      <div class="sm-icon si-red"><i class="bi bi-x-circle"></i></div>
      <div><div class="sm-val"><?php echo $cnt_reject; ?></div><div class="sm-lbl">ไม่ผ่าน</div></div>
    </div>
  </div>

  <!-- Applicant cards -->
  <?php if(empty($rows)): ?>
  <div class="empty-state">
    <i class="bi bi-inbox"></i>
    <p>ยังไม่มีผู้สมัครงานนี้<br>แชร์ประกาศให้ Freelancer เห็นมากขึ้น</p>
  </div>

  <?php else: ?>
  <?php foreach($rows as $row):
    $status     = strtolower($row['status']);
    $is_hired   = ($status === 'accepted' || $status === 'hired');
    $init       = strtoupper(substr($row['username'], 0, 1));
    $avg_r      = round($row['avg_rating'] ?? 0, 1);
    $total_r    = $row['total_reviews'] ?? 0;

    $sp_class = match($status){
      'accepted','hired' => 'sp-hired',
      'rejected'         => 'sp-rejected',
      default            => 'sp-pending',
    };
    $sp_label = match($status){
      'accepted','hired' => 'รับเข้าทำงาน',
      'rejected'         => 'ไม่ผ่าน',
      default            => 'รอพิจารณา',
    };
    $sp_icon = match($status){
      'accepted','hired' => 'bi-check-circle-fill',
      'rejected'         => 'bi-x-circle-fill',
      default            => 'bi-hourglass-split',
    };

    // resume
    $resume_row = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT * FROM resume WHERE freelancer_id='".$row['freelancer_id']."'
        ORDER BY resume_id DESC LIMIT 1
    "));

    $filter_key = $is_hired ? 'hired' : $status;
  ?>
  <div class="app-card <?php echo $is_hired ? 'hired-card' : ''; ?>"
       data-filter="<?php echo $filter_key; ?>">

    <div class="ac-top">
      <div class="ac-user">
        <div class="ac-avatar"><?php echo $init; ?></div>
        <div>
          <div class="ac-name"><?php echo htmlspecialchars($row['username']); ?></div>
          <div class="ac-meta">
            <?php if(!empty($row['location'])): ?>
            <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($row['location']); ?></span>
            <?php endif; ?>
            <?php if(!empty($row['skill'])): ?>
            <span><i class="bi bi-tools"></i><?php echo htmlspecialchars($row['skill']); ?></span>
            <?php endif; ?>
            <?php if($avg_r > 0): ?>
            <span>
              <span class="star-row">★</span>
              <?php echo $avg_r; ?> (<?php echo $total_r; ?> รีวิว)
            </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <span class="s-pill <?php echo $sp_class; ?>">
        <i class="bi <?php echo $sp_icon; ?>"></i> <?php echo $sp_label; ?>
      </span>
    </div>

    <?php if(!empty($row['experience'])): ?>
    <div class="ac-exp"><?php echo htmlspecialchars($row['experience']); ?></div>
    <?php endif; ?>

    <div class="ac-footer">
      <div class="act-wrap">
        <!-- 1.4.2.9 ดู Resume -->
        <?php if($resume_row): ?>
        <a href="uploads/<?php echo htmlspecialchars($resume_row['file_name']); ?>"
           target="_blank" class="act-btn ab-resume">
          <i class="bi bi-file-earmark-pdf"></i> ดู Resume
        </a>
        <?php else: ?>
        <span class="no-resume"><i class="bi bi-file-earmark-x"></i> ไม่มี Resume</span>
        <?php endif; ?>

        <!-- 1.4.2.10 Hire -->
        <?php if(!$is_hired && $status !== 'rejected'): ?>
        <a href="hire.php?application_id=<?php echo $row['application_id']; ?>"
           class="act-btn ab-hire"
           onclick="return confirm('รับ <?php echo htmlspecialchars($row['username'],ENT_QUOTES); ?> เข้าทำงานใช่ไหม?')">
          <i class="bi bi-person-check"></i> รับเข้าทำงาน
        </a>
        <?php elseif($is_hired): ?>
        <span class="act-btn ab-hired-done"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> รับแล้ว</span>
        <?php endif; ?>

        <!-- 1.4.2.11-12 Rate / Review -->
        <a href="rate_freelancer.php?job_id=<?php echo $job_id; ?>&freelancer_id=<?php echo $row['freelancer_id']; ?>"
           class="act-btn ab-rate">
          <i class="bi bi-star"></i> รีวิว
        </a>

        <!-- Reject -->
        <?php if(!$is_hired && $status !== 'rejected'): ?>
        <a href="reject_applicant.php?application_id=<?php echo $row['application_id']; ?>"
           class="act-btn ab-reject"
           onclick="return confirm('ปฏิเสธผู้สมัครคนนี้?')">
          <i class="bi bi-x-lg"></i> ปฏิเสธ
        </a>
        <?php endif; ?>
      </div>
    </div>

  </div>
  <?php endforeach; ?>

  <div class="empty-state" id="empty-filter" style="display:none;">
    <i class="bi bi-funnel"></i>
    <p>ไม่มีผู้สมัครในกลุ่มนี้</p>
  </div>

  <?php endif; ?>

</main>

<script>
  let currentFilter = '';

  function setFilter(el, f){
    document.querySelectorAll('.stat-mini').forEach(s => s.classList.remove('active-f'));
    el.classList.add('active-f');
    currentFilter = f;

    const cards = document.querySelectorAll('.app-card');
    let visible = 0;
    cards.forEach(c => {
      const show = !f || c.dataset.filter === f;
      c.classList.toggle('hidden', !show);
      if(show) visible++;
    });

    const ef = document.getElementById('empty-filter');
    if(ef) ef.style.display = visible === 0 && cards.length > 0 ? 'block' : 'none';
  }
</script>
</body>
</html>