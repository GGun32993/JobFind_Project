<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "profile_image_helpers.php";
require_once "employer_sidebar_helpers.php";

ensure_profile_image_schema($conn);

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "employer"){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sidebar_pending_apps = get_employer_pending_application_count($conn, $user_id);

$result = mysqli_query($conn,"
    SELECT er.*, u.username AS freelancer_name, u.profile_image, j.title AS job_title
    FROM employer_review er
    JOIN users u ON er.freelancer_id = u.user_id
    LEFT JOIN job j ON er.job_id = j.job_id
    WHERE er.employer_id = '$user_id'
    ORDER BY er.created_at DESC
");

$rows = [];
$total = 0;
$sum = 0;
$dist = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];

while($row = mysqli_fetch_assoc($result)){
    $rows[] = $row;
    $total++;
    $rating = (int)$row['rating'];
    $sum += $rating;
    if(isset($dist[$rating])){
        $dist[$rating]++;
    }
}

$avg = $total > 0 ? round($sum / $total, 1) : 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo.png?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รีวิวบริษัท</title>
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
  .main { margin-left:240px; flex:1; padding:36px 40px; }

  /* ── Topbar ── */
  .topbar { margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Summary card ── */
  .summary-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:28px; display:flex; gap:36px; align-items:center; margin-bottom:24px; flex-wrap:wrap; }
  .avg-block { text-align:center; flex-shrink:0; }
  .avg-block .big-num { font-size:56px; font-weight:600; line-height:1; color:var(--text); }
  .avg-block .big-stars { font-size:22px; color:var(--yellow); letter-spacing:2px; margin:6px 0 4px; }
  .avg-block .sub { font-size:12px; color:var(--muted); }
  .dist-block { flex:1; min-width:200px; }
  .dist-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
  .dist-row .lbl { font-size:12.5px; color:var(--muted); white-space:nowrap; width:36px; flex-shrink:0; }
  .dist-bar-wrap { flex:1; height:8px; background:var(--light); border-radius:4px; overflow:hidden; }
  .dist-bar { height:100%; background:var(--yellow); border-radius:4px; }
  .dist-row .cnt { font-size:12px; color:var(--muted); width:24px; text-align:right; flex-shrink:0; }
  .tip-block { flex:1; min-width:180px; background:var(--light); border-radius:12px; padding:16px 18px; }
  .tip-block .tip-title { font-size:13px; font-weight:600; margin-bottom:6px; }
  .tip-block .tip-text  { font-size:12.5px; color:var(--muted); line-height:1.7; }

  /* ── Filter tabs ── */
  .filter-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
  .ftab { padding:7px 16px; border-radius:30px; border:1px solid var(--border); background:var(--white); font-size:13px; font-weight:500; color:var(--muted); cursor:pointer; transition:all .15s; }
  .ftab:hover { border-color:#a5b4fc; color:var(--accent); }
  .ftab.active { background:var(--accent); border-color:var(--accent); color:#fff; }

  /* ── Review card ── */
  .review-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:22px 24px; margin-bottom:12px; transition:box-shadow .2s,border-color .2s; }
  .review-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); border-color:#c7d2fe; }
  .review-card.hidden { display:none; }

  .rc-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
  .rc-user { display:flex; align-items:center; gap:12px; }
  .fl-avatar { width:42px; height:42px; border-radius:50%; background:var(--accent); color:#fff; font-size:15px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
  .fl-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .fl-name { font-size:14px; font-weight:600; }
  .fl-job  { font-size:12px; color:var(--muted); margin-top:2px; display:flex; align-items:center; gap:4px; }

  .rc-stars-wrap { display:flex; align-items:center; gap:6px; flex-shrink:0; }
  .stars-row { font-size:18px; color:var(--yellow); letter-spacing:1px; }
  .stars-row .empty { color:#e2e8f0; }
  .rating-num { font-size:13px; font-weight:600; }

  .star-badge { display:inline-flex; align-items:center; gap:4px; font-size:11.5px; font-weight:600; padding:4px 10px; border-radius:20px; }
  .sb-5 { background:#fef9c3; color:#92400e; }
  .sb-4 { background:#d1fae5; color:#065f46; }
  .sb-3 { background:#e0f2fe; color:#075985; }
  .sb-2 { background:#fee2e2; color:#991b1b; }
  .sb-1 { background:#fee2e2; color:#991b1b; }

  .rc-comment { font-size:14px; color:var(--text); line-height:1.7; background:var(--light); border-radius:10px; padding:14px 16px; margin-bottom:12px; border-left:3px solid var(--accent); }
  .rc-date { font-size:12px; color:var(--muted); display:flex; align-items:center; gap:5px; }

  /* ── Empty ── */
  .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
  .empty-state i { font-size:44px; color:#c7d2fe; margin-bottom:12px; display:block; }
  .empty-state p { font-size:14px; }
  .empty-filter  { text-align:center; padding:40px; color:var(--muted); display:none; }
  .empty-filter i { font-size:36px; color:#c7d2fe; margin-bottom:10px; display:block; }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .summary-card { flex-direction:column; gap:20px; } }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon"><img class="brand-logo-img" src="assets/images/jobfind-logo-icon.png?v=2" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div><div class="logo-text">Job_Find</div><div class="logo-sub">Employer</div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="employer_dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs<?php render_employer_manage_jobs_badge($sidebar_pending_apps); ?></a>
    <a href="saved_freelancers.php"    class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="employer_reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_review.php"      class="nav-item active"><i class="bi bi-building"></i> รีวิวบริษัท</a>
    <a href="employer_profile.php"     class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="support_messages.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
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

  <?php if($total > 0): ?>

  <!-- Rating summary -->
  <div class="summary-card">
    <div class="avg-block">
      <div class="big-num"><?php echo $avg; ?></div>
      <div class="big-stars">
        <?php
          $full  = floor($avg);
          $half  = ($avg - $full) >= 0.5 ? 1 : 0;
          $empty = 5 - $full - $half;
          echo str_repeat('★', $full);
          if($half) echo '½';
          echo str_repeat('☆', $empty);
        ?>
      </div>
      <div class="sub"><?php echo $total; ?> รีวิว</div>
    </div>

    <div class="dist-block">
      <?php foreach([5,4,3,2,1] as $s):
        $pct = $total > 0 ? round($dist[$s] / $total * 100) : 0;
      ?>
      <div class="dist-row">
        <span class="lbl"><i class="bi bi-star-fill" style="color:var(--yellow);font-size:11px;"></i> <?php echo $s; ?></span>
        <div class="dist-bar-wrap"><div class="dist-bar" style="width:<?php echo $pct; ?>%"></div></div>
        <span class="cnt"><?php echo $dist[$s]; ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="tip-block">
      <div class="tip-title">เกี่ยวกับคะแนนนี้</div>
      <div class="tip-text">
        คะแนนเฉลี่ยของบริษัทคือ <strong><?php echo $avg; ?>/5</strong><br>
        รีวิวเหล่านี้มาจาก Freelancer ที่เคยร่วมงานกับบริษัทของคุณ<br>
        ช่วยให้ผู้สมัครประเมินความน่าเชื่อถือก่อนสมัครงาน
      </div>
    </div>
  </div>

  <!-- Filter tabs -->
  <div class="filter-tabs">
    <button class="ftab active" data-star="0" onclick="setFilter(this)">ทั้งหมด</button>
    <button class="ftab" data-star="5" onclick="setFilter(this)">★★★★★ 5 ดาว</button>
    <button class="ftab" data-star="4" onclick="setFilter(this)">★★★★☆ 4 ดาว</button>
    <button class="ftab" data-star="3" onclick="setFilter(this)">★★★☆☆ 3 ดาว</button>
    <button class="ftab" data-star="2" onclick="setFilter(this)">★★☆☆☆ ≤2 ดาว</button>
  </div>

  <?php endif; ?>

  <?php if($total === 0): ?>
  <div class="empty-state">
    <i class="bi bi-star"></i>
    <p>ยังไม่มีรีวิวจาก Freelancer<br>เมื่องานเสร็จสิ้น Freelancer จะสามารถรีวิวคุณได้</p>
  </div>

  <?php else: ?>

  <?php foreach($rows as $row):
    $rating      = (int)$row['rating'];
    $fl_name     = $row['freelancer_name'] ?? '-';
    $fl_init     = profile_initials($fl_name);
    $profile_img = trim($row['profile_image'] ?? '');
    $date_str    = date('d M Y', strtotime($row['created_at']));
    $sb_class    = 'sb-' . $rating;
    $star_label  = [1=>'แย่',2=>'พอใช้',3=>'ปานกลาง',4=>'ดี',5=>'ดีมาก'][$rating] ?? '';
    $job_label   = empty($row['job_id']) ? 'รีวิวบริษัท' : ($row['job_title'] ?: 'งานนี้');
    $job_icon    = empty($row['job_id']) ? 'bi-building' : 'bi-briefcase';
  ?>
  <div class="review-card" data-star="<?php echo $rating; ?>">
    <div class="rc-top">
      <div class="rc-user">
        <div class="fl-avatar">
          <?php if($profile_img !== ''): ?>
            <img src="<?php echo profile_image_src($profile_img); ?>" alt="Profile image">
          <?php else: ?>
            <?php echo htmlspecialchars($fl_init); ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="fl-name"><?php echo htmlspecialchars($fl_name); ?></div>
          <div class="fl-job">
            <i class="bi <?php echo $job_icon; ?>" style="font-size:11px;"></i>
            <?php echo htmlspecialchars($job_label); ?>
          </div>
        </div>
      </div>

      <div class="rc-stars-wrap">
        <div class="stars-row">
          <?php for($i=1;$i<=5;$i++): ?>
            <span class="<?php echo $i <= $rating ? '' : 'empty'; ?>">★</span>
          <?php endfor; ?>
        </div>
        <span class="rating-num"><?php echo $rating; ?>.0</span>
        <span class="star-badge <?php echo $sb_class; ?>"><?php echo $star_label; ?></span>
      </div>
    </div>

    <?php if(!empty($row['comment'])): ?>
    <div class="rc-comment">
      "<?php echo htmlspecialchars($row['comment']); ?>"
    </div>
    <?php endif; ?>

    <div class="rc-date">
      <i class="bi bi-clock"></i> <?php echo $date_str; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="empty-filter" id="empty-filter">
    <i class="bi bi-funnel"></i>
    <p>ไม่มีรีวิวในระดับดาวนี้</p>
  </div>

  <?php endif; ?>

</main>

<script>
  function setFilter(el){
    document.querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    const star = parseInt(el.dataset.star);
    const cards = document.querySelectorAll('.review-card');
    let visible = 0;

    cards.forEach(c => {
      const s = parseInt(c.dataset.star);
      const show = star === 0 ? true : (star === 2 ? s <= 2 : s === star);
      c.classList.toggle('hidden', !show);
      if(show) visible++;
    });

    const ef = document.getElementById('empty-filter');
    if(ef) ef.style.display = visible === 0 ? 'block' : 'none';
  }
</script>
</body>
</html>
