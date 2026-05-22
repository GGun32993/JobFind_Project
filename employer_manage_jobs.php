<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: /JobFind_Project/login.php");  // ✅ ระบุ path ชัดเจน
    exit();
}

$employer_id = $_SESSION['user_id'];

// ── 1.4.2.7 ลบประกาศ ──
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    mysqli_query($conn,"DELETE FROM job WHERE job_id='$del_id' AND employer_id='$employer_id'");
    header("Location: employer_manage_jobs.php?deleted=1");
    exit();
}

// ── 1.4.2.5 ปิดประกาศอัตโนมัติเมื่อ deadline ผ่าน ──
mysqli_query($conn,"
    UPDATE job
    SET status='closed'
    WHERE employer_id='$employer_id'
    AND deadline IS NOT NULL
    AND deadline != ''
    AND deadline < CURDATE()
    AND status != 'closed'
    AND status != 'completed'
");

// ── ดึงงานทั้งหมด ──
$result = mysqli_query($conn,"
    SELECT j.*,
        (SELECT COUNT(*) FROM job_application WHERE job_id=j.job_id) AS total_apps,
        (SELECT COUNT(*) FROM job_application WHERE job_id=j.job_id AND status='pending') AS pending_apps
    FROM job j
    WHERE employer_id='$employer_id'
    ORDER BY job_id DESC
");

$rows        = [];
$cnt_all     = 0;
$cnt_open    = 0;
$cnt_closed  = 0;
$cnt_pending = 0;

while($r = mysqli_fetch_assoc($result)){
    $rows[] = $r;
    $cnt_all++;
    if($r['status']==='approved')  $cnt_open++;
    elseif($r['status']==='closed' || $r['status']==='completed') $cnt_closed++;
    elseif($r['admin_status']==='pending') $cnt_pending++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Jobs</title>
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
  .nav-badge { position:absolute; right:12px; background:var(--red); color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }
  .nav-logout i { font-size:17px; }

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; }

  /* ── Topbar ── */
  .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .btn-post-new { display:inline-flex; align-items:center; gap:7px; background:var(--accent); color:#fff; border:none; border-radius:10px; padding:11px 22px; font-size:14px; font-weight:600; text-decoration:none; transition:background .15s,transform .1s; white-space:nowrap; }
  .btn-post-new:hover { background:#4f46e5; color:#fff; transform:translateY(-1px); }

  /* ── Toast ── */
  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* ── Stat row ── */
  .stat-row { display:flex; gap:12px; margin-bottom:22px; flex-wrap:wrap; }
  .stat-mini { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; display:flex; align-items:center; gap:12px; flex:1; min-width:120px; cursor:pointer; transition:border-color .15s; }
  .stat-mini:hover { border-color:#a5b4fc; }
  .stat-mini.active-f { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.1); }
  .sm-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
  .sm-val  { font-size:22px; font-weight:600; line-height:1; }
  .sm-lbl  { font-size:12px; color:var(--muted); margin-top:3px; }
  .si-purple { background:#eef2ff; color:var(--accent); }
  .si-green  { background:#d1fae5; color:var(--green); }
  .si-gray   { background:#f1f5f9; color:var(--navy3); }
  .si-yellow { background:#fef9c3; color:#854d0e; }

  /* ── Toolbar ── */
  .toolbar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
  .search-wrap { position:relative; flex:1; min-width:200px; }
  .search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:15px; color:var(--muted); pointer-events:none; }
  .search-wrap input { width:100%; padding:10px 14px 10px 38px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:13.5px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; }
  .search-wrap input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  .result-info { font-size:13px; color:var(--muted); white-space:nowrap; }

  /* ── Job card ── */
  .job-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:22px 24px; margin-bottom:12px; transition:box-shadow .2s,border-color .2s; }
  .job-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); border-color:#c7d2fe; }
  .job-card.hidden { display:none; }
  .job-card.pending-border { border-left:3px solid var(--yellow); }
  .job-card.closed-card   { opacity:.75; }

  .jc-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
  .jc-title { font-size:15px; font-weight:600; margin-bottom:3px; }
  .jc-desc  { font-size:13px; color:var(--muted); line-height:1.7; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:12px; }
  .jc-meta  { display:flex; align-items:center; gap:14px; font-size:12.5px; color:var(--muted); flex-wrap:wrap; }
  .jc-meta span { display:flex; align-items:center; gap:4px; }

  .badges { display:flex; gap:6px; flex-wrap:wrap; }
  .pill { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; padding:4px 11px; border-radius:20px; }
  .p-pending   { background:#fef9c3; color:#854d0e; }
  .p-approved  { background:#d1fae5; color:#065f46; }
  .p-rejected  { background:#fee2e2; color:#991b1b; }
  .p-closed    { background:#f1f5f9; color:var(--navy3); }
  .p-completed { background:#e0e7ff; color:#3730a3; }
  .p-apps      { background:#e0f2fe; color:#0369a1; }

  /* deadline warning */
  .deadline-warn { font-size:12px; color:var(--red); display:flex; align-items:center; gap:4px; }

  .jc-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding-top:14px; border-top:1px solid var(--border); margin-top:12px; }

  /* Action buttons */
  .act-wrap { display:flex; gap:7px; flex-wrap:wrap; }
  .act-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 14px; border-radius:9px; font-size:12.5px; font-weight:500; text-decoration:none; border:none; cursor:pointer; font-family:'Sora',sans-serif; transition:opacity .15s,transform .1s; white-space:nowrap; }
  .act-btn:hover { opacity:.85; transform:translateY(-1px); }
  .ab-view     { background:#eef2ff; color:var(--accent); }
  .ab-edit     { background:#fef9c3; color:#854d0e; }
  .ab-complete { background:#d1fae5; color:#065f46; }
  .ab-delete   { background:#fee2e2; color:#991b1b; }

  /* Empty */
  .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
  .empty-state i { font-size:44px; color:#c7d2fe; margin-bottom:12px; display:block; }

  /* ── Delete modal ── */
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; align-items:center; justify-content:center; }
  .modal-overlay.show { display:flex; }
  .modal-box { background:var(--white); border-radius:var(--radius); padding:30px; max-width:390px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.22); }
  .modal-icon { width:54px; height:54px; border-radius:50%; background:#fee2e2; color:var(--red); font-size:26px; display:flex; align-items:center; justify-content:center; margin-bottom:16px; }
  .modal-title { font-size:17px; font-weight:600; margin-bottom:6px; }
  .modal-sub   { font-size:13px; color:var(--muted); line-height:1.7; margin-bottom:24px; }
  .modal-actions { display:flex; gap:10px; }
  .btn-cancel  { flex:1; padding:11px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; font-weight:500; cursor:pointer; background:var(--white); color:var(--text); }
  .btn-cancel:hover { background:var(--light); }
  .btn-del-confirm { flex:1; padding:11px; border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; font-weight:600; cursor:pointer; background:var(--red); color:#fff; text-align:center; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:6px; }
  .btn-del-confirm:hover { opacity:.88; color:#fff; }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
</style>
</head>
<body>

<!-- ── Toast ── -->
<?php if(isset($_GET['deleted'])): ?>
<div class="toast-bar" id="toast"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> ลบประกาศเรียบร้อยแล้ว</div>
<?php elseif(isset($_GET['posted'])): ?>
<div class="toast-bar" id="toast"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> โพสต์งานสำเร็จ รอ Admin อนุมัติ</div>
<?php endif; ?>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3500);</script>

<!-- ── Delete modal ── -->
<div class="modal-overlay" id="del-modal">
  <div class="modal-box">
    <div class="modal-icon"><i class="bi bi-trash3"></i></div>
    <div class="modal-title">ยืนยันการลบประกาศ</div>
    <div class="modal-sub">คุณต้องการลบงาน <strong id="del-title"></strong> ใช่ไหม?<br>ข้อมูลการสมัครทั้งหมดจะหายไปด้วย</div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">ยกเลิก</button>
      <a href="#" id="del-link" class="btn-del-confirm"><i class="bi bi-trash3"></i> ลบเลย</a>
    </div>
  </div>
</div>

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
    <a href="employer_manage_jobs.php" class="nav-item active">
      <i class="bi bi-briefcase"></i> Manage Jobs
      <?php if($cnt_pending > 0): ?><span class="nav-badge"><?php echo $cnt_pending; ?></span><?php endif; ?>
    </a>
    <a href="saved_freelancers.php"    class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="employer_reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_review.php"      class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
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

  <div class="topbar">
    <div>
      <h2>Manage Jobs</h2>
      <p>จัดการประกาศรับสมัครงานทั้งหมดของคุณ</p>
    </div>
    <a href="post_job.php" class="btn-post-new">
      <i class="bi bi-plus-lg"></i> โพสต์งานใหม่
    </a>
  </div>

  <!-- Stat row -->
  <div class="stat-row">
    <div class="stat-mini active-f" data-f="" onclick="setFilter(this,'')">
      <div class="sm-icon si-purple"><i class="bi bi-briefcase"></i></div>
      <div><div class="sm-val"><?php echo $cnt_all; ?></div><div class="sm-lbl">ทั้งหมด</div></div>
    </div>
    <div class="stat-mini" data-f="approved" onclick="setFilter(this,'approved')">
      <div class="sm-icon si-green"><i class="bi bi-check-circle"></i></div>
      <div><div class="sm-val"><?php echo $cnt_open; ?></div><div class="sm-lbl">เปิดรับอยู่</div></div>
    </div>
    <div class="stat-mini" data-f="pending" onclick="setFilter(this,'pending')">
      <div class="sm-icon si-yellow"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="sm-val"><?php echo $cnt_pending; ?></div><div class="sm-lbl">รออนุมัติ</div></div>
    </div>
    <div class="stat-mini" data-f="closed" onclick="setFilter(this,'closed')">
      <div class="sm-icon si-gray"><i class="bi bi-archive"></i></div>
      <div><div class="sm-val"><?php echo $cnt_closed; ?></div><div class="sm-lbl">ปิด/เสร็จแล้ว</div></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" id="search-input" placeholder="ค้นหาชื่องาน..." oninput="filterCards()">
    </div>
    <span class="result-info">พบ <strong id="result-count"><?php echo $cnt_all; ?></strong> งาน</span>
  </div>

  <!-- Job cards -->
  <?php if(empty($rows)): ?>
  <div class="empty-state">
    <i class="bi bi-briefcase"></i>
    <p>ยังไม่มีงานที่โพสต์<br><a href="post_job.php" style="color:var(--accent);">โพสต์งานแรกของคุณเลย →</a></p>
  </div>
  <?php else: ?>

  <?php
  $icons = ['💼','🖥️','📐','📊','🚀','🎨','⚙️','📱','✍️','📢','🎓','💰'];
  foreach($rows as $row):
    $icon       = $icons[crc32($row['title']) % count($icons)];
    $adm        = $row['admin_status'];
    $sts        = $row['status'];
    $total_apps = $row['total_apps'];
    $pend_apps  = $row['pending_apps'];

    // admin status pill
    $ap = match($adm){ 'approved'=>'p-approved','rejected'=>'p-rejected', default=>'p-pending' };
    $ai = match($adm){ 'approved'=>'bi-check-circle-fill','rejected'=>'bi-x-circle-fill', default=>'bi-hourglass-split' };

    // job status pill
    $sp = match($sts){ 'approved'=>'p-approved','closed'=>'p-closed','completed'=>'p-completed','rejected'=>'p-rejected', default=>'p-pending' };
    $sl = match($sts){ 'approved'=>'เปิดรับ','closed'=>'ปิดแล้ว','completed'=>'เสร็จสิ้น','rejected'=>'Rejected', default=>ucfirst($sts) };

    // deadline
    $deadline_str = '';
    $is_expired   = false;
    if(!empty($row['deadline'])){
        $dl = strtotime($row['deadline']);
        $is_expired = ($dl < strtotime('today'));
        $deadline_str = date('d M Y', $dl);
    }

    // card filter key
    $filter_key = ($sts==='closed'||$sts==='completed') ? 'closed' : $adm;
    $border_class = ($adm==='pending') ? 'pending-border' : (($sts==='closed'||$sts==='completed') ? 'closed-card' : '');
  ?>
  <div class="job-card <?php echo $border_class; ?>"
       data-filter="<?php echo $filter_key; ?>"
       data-title="<?php echo strtolower(htmlspecialchars($row['title'])); ?>">

    <div class="jc-top">
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:26px;"><?php echo $icon; ?></span>
        <div>
          <div class="jc-title"><?php echo htmlspecialchars($row['title']); ?></div>
          <div class="badges">
            <span class="pill <?php echo $ap; ?>"><i class="bi <?php echo $ai; ?>"></i> Admin: <?php echo ucfirst($adm); ?></span>
            <span class="pill <?php echo $sp; ?>"><?php echo $sl; ?></span>
            <?php if($total_apps > 0): ?>
            <span class="pill p-apps"><i class="bi bi-people"></i> <?php echo $total_apps; ?> คนสมัคร<?php echo $pend_apps>0?" ($pend_apps รอ)":''; ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <p class="jc-desc"><?php echo htmlspecialchars($row['description']); ?></p>

    <div class="jc-meta">
      <?php if(!empty($row['location'])): ?><span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($row['location']); ?></span><?php endif; ?>
      <?php if(!empty($row['salary'])): ?><span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($row['salary']); ?></span><?php endif; ?>
      <?php if($deadline_str): ?>
        <?php if($is_expired): ?>
          <span class="deadline-warn"><i class="bi bi-exclamation-triangle-fill"></i> Deadline ผ่านแล้ว (<?php echo $deadline_str; ?>)</span>
        <?php else: ?>
          <span><i class="bi bi-calendar-event"></i> Deadline: <?php echo $deadline_str; ?></span>
        <?php endif; ?>
      <?php endif; ?>
      <?php if(!empty($row['created_at'])): ?>
        <span><i class="bi bi-clock"></i><?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
      <?php endif; ?>
    </div>

    <div class="jc-footer">
      <div class="act-wrap">
        <!-- 1.4.2.8 ดูใบสมัคร -->
        <a href="view_applicants.php?job_id=<?php echo $row['job_id']; ?>" class="act-btn ab-view">
          <i class="bi bi-people"></i> ดูผู้สมัคร
          <?php if($pend_apps > 0): ?><span style="background:#ef4444;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:2px;"><?php echo $pend_apps; ?></span><?php endif; ?>
        </a>

        <!-- 1.4.2.6 แก้ไขประกาศ -->
        <?php if($sts !== 'completed'): ?>
        <a href="edit_job.php?job_id=<?php echo $row['job_id']; ?>" class="act-btn ab-edit">
          <i class="bi bi-pencil"></i> แก้ไข
        </a>
        <?php endif; ?>

        <!-- 1.4.2.10 Mark Completed -->
        <?php if($sts === 'approved'): ?>
        <a href="complete_job.php?job_id=<?php echo $row['job_id']; ?>" class="act-btn ab-complete"
           onclick="return confirm('ยืนยันว่างานเสร็จสิ้นแล้ว?')">
          <i class="bi bi-flag-fill"></i> งานเสร็จสิ้น
        </a>
        <?php endif; ?>

        <!-- 1.4.2.7 ลบประกาศ -->
        <?php if($sts !== 'completed'): ?>
        <button class="act-btn ab-delete"
          onclick="confirmDelete(<?php echo $row['job_id']; ?>,'<?php echo htmlspecialchars($row['title'],ENT_QUOTES); ?>')">
          <i class="bi bi-trash3"></i> ลบ
        </button>
        <?php endif; ?>
      </div>
    </div>

  </div>
  <?php endforeach; ?>

  <div class="empty-state" id="empty-filter" style="display:none;">
    <i class="bi bi-funnel"></i>
    <p>ไม่พบงานในหมวดนี้</p>
  </div>

  <?php endif; ?>

</main>

<script>
  let currentFilter = '';

  function setFilter(el, f){
    document.querySelectorAll('.stat-mini').forEach(s => s.classList.remove('active-f'));
    el.classList.add('active-f');
    currentFilter = f;
    filterCards();
  }

  function filterCards(){
    const kw    = document.getElementById('search-input').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.job-card');
    let visible = 0;

    cards.forEach(c => {
      const matchF = !currentFilter || c.dataset.filter === currentFilter;
      const matchK = !kw || (c.dataset.title||'').includes(kw);
      const show   = matchF && matchK;
      c.classList.toggle('hidden', !show);
      if(show) visible++;
    });

    document.getElementById('result-count').textContent = visible;
    const ef = document.getElementById('empty-filter');
    if(ef) ef.style.display = visible === 0 && document.querySelectorAll('.job-card').length > 0 ? 'block' : 'none';
  }

  function confirmDelete(id, title){
    document.getElementById('del-title').textContent = title;
    document.getElementById('del-link').href = 'employer_manage_jobs.php?delete=' + id;
    document.getElementById('del-modal').classList.add('show');
  }

  function closeModal(){
    document.getElementById('del-modal').classList.remove('show');
  }

  document.getElementById('del-modal').addEventListener('click', function(e){
    if(e.target === this) closeModal();
  });
</script>
</body>
</html>
