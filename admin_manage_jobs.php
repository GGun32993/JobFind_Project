<?php
session_start();
require_once __DIR__ . "/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: login.php");
    exit();
}

$admin_unread_support = 0;
$admin_id_for_badge = (int)$_SESSION['user_id'];
$col_check = mysqli_query($conn,"SHOW COLUMNS FROM chat_messages LIKE 'is_read'");
if($col_check && mysqli_num_rows($col_check) === 0){
    mysqli_query($conn,"ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
}
$unread_support = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS c
    FROM chat_messages cm
    JOIN users u ON u.user_id=cm.sender_id
    WHERE cm.receiver_id='$admin_id_for_badge'
    AND cm.is_read=0
"));
$admin_unread_support = (int)($unread_support['c'] ?? 0);

// approve
if(isset($_GET['approve'])){
    $job_id = intval($_GET['approve']);
    mysqli_query($conn,"UPDATE job SET admin_status='approved', status='approved' WHERE job_id='$job_id'");
    header("Location: admin_manage_jobs.php?done=approved");
    exit();
}

// reject
if(isset($_GET['reject'])){
    $job_id = intval($_GET['reject']);
    mysqli_query($conn,"UPDATE job SET admin_status='rejected' WHERE job_id='$job_id'");
    header("Location: admin_manage_jobs.php?done=rejected");
    exit();
}

// get all jobs
$result = mysqli_query($conn,"
    SELECT job.*, users.username
    FROM job
    JOIN users ON users.user_id = job.employer_id
    ORDER BY job.created_at DESC
");

$rows         = [];
$cnt_all      = 0;
$cnt_pending  = 0;
$cnt_approved = 0;
$cnt_rejected = 0;

while($r = mysqli_fetch_assoc($result)){
    $rows[] = $r;
    $cnt_all++;
    if($r['admin_status']==='pending')  $cnt_pending++;
    elseif($r['admin_status']==='approved') $cnt_approved++;
    elseif($r['admin_status']==='rejected') $cnt_rejected++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=9">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Jobs</title>
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

  .btn-detail:hover { 
  background:#4f46e5; 
  transform:translateY(-1px);
  box-shadow:0 4px 12px rgba(99,102,241,.3);
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

  /* ── Toast ── */
  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
  .toast-bar i { font-size:18px; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* ── Stat row ── */
  .stat-row { display:flex; gap:12px; margin-bottom:22px; flex-wrap:wrap; }
  .stat-mini { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; display:flex; align-items:center; gap:12px; flex:1; min-width:130px; cursor:pointer; transition:border-color .15s; }
  .stat-mini:hover { border-color:#a5b4fc; }
  .stat-mini.active-filter { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.1); }
  .sm-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
  .sm-val  { font-size:24px; font-weight:600; line-height:1; }
  .sm-lbl  { font-size:12px; color:var(--muted); margin-top:3px; }
  .si-purple { background:#eef2ff; color:var(--accent); }
  .si-yellow { background:#fef9c3; color:#854d0e; }
  .si-green  { background:#d1fae5; color:var(--green); }
  .si-red    { background:#fee2e2; color:var(--red); }

  /* ── Toolbar ── */
  .toolbar { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
  .search-wrap { position:relative; flex:1; min-width:200px; }
  .search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:15px; color:var(--muted); pointer-events:none; }
  .search-wrap input { width:100%; padding:10px 14px 10px 38px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:13.5px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; }
  .search-wrap input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
  .result-info { font-size:13px; color:var(--muted); white-space:nowrap; }

  /* ── Job cards ── */
  .job-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:22px 24px; margin-bottom:12px; transition:box-shadow .2s,border-color .2s; }
  .job-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); border-color:#c7d2fe; }
  .job-card.hidden { display:none; }
  .job-card.pending-card { border-left:3px solid var(--yellow); }

  .jc-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
  .jc-title { font-size:15px; font-weight:600; margin-bottom:4px; }
  .jc-employer { font-size:13px; color:var(--muted); display:flex; align-items:center; gap:5px; }

  .jc-desc { font-size:13px; color:var(--muted); line-height:1.7; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:12px; }

  .jc-meta { display:flex; align-items:center; gap:14px; font-size:12.5px; color:var(--muted); flex-wrap:wrap; margin-bottom:14px; }
  .jc-meta span { display:flex; align-items:center; gap:4px; }

  .jc-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding-top:14px; border-top:1px solid var(--border); }

  /* Status badge */
  .status-pill { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:5px 13px; border-radius:20px; }
  .sp-pending  { background:#fef9c3; color:#854d0e; }
  .sp-approved { background:#d1fae5; color:#065f46; }
  .sp-rejected { background:#fee2e2; color:#991b1b; }

  /* Action buttons */
  .btn-approve { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:9px; font-size:13px; font-weight:600; background:var(--green); color:#fff; text-decoration:none; border:none; cursor:pointer; transition:opacity .15s,transform .1s; font-family:'Sora',sans-serif; }
  .btn-approve:hover { opacity:.85; color:#fff; transform:translateY(-1px); }
  .btn-reject  { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:9px; font-size:13px; font-weight:600; background:#fee2e2; color:#991b1b; text-decoration:none; border:none; cursor:pointer; transition:opacity .15s,transform .1s; font-family:'Sora',sans-serif; }
  .btn-reject:hover  { opacity:.85; color:#991b1b; transform:translateY(-1px); }
  .btn-done    { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:9px; font-size:13px; font-weight:500; background:var(--light); color:var(--muted); border:1px solid var(--border); cursor:default; font-family:'Sora',sans-serif; }

  .actions-wrap { display:flex; gap:8px; flex-wrap:wrap; }

  /* Empty */
  .empty-state { text-align:center; padding:60px 20px; color:var(--muted); display:none; }
  .empty-state i { font-size:44px; color:#c7d2fe; margin-bottom:12px; display:block; }

  /* Confirm modal */
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; align-items:center; justify-content:center; }
  .modal-overlay.show { display:flex; }
  .modal-box { background:var(--white); border-radius:var(--radius); padding:30px; max-width:400px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.22); }
  .modal-icon { width:54px; height:54px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:16px; }
  .mi-approve { background:#d1fae5; color:var(--green); }
  .mi-reject  { background:#fee2e2; color:var(--red); }
  .modal-title { font-size:17px; font-weight:600; margin-bottom:6px; }
  .modal-sub   { font-size:13px; color:var(--muted); line-height:1.7; margin-bottom:24px; }
  .modal-actions { display:flex; gap:10px; }
  .btn-cancel  { flex:1; padding:11px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; font-weight:500; cursor:pointer; background:var(--white); color:var(--text); }
  .btn-cancel:hover { background:var(--light); }
  .btn-modal-confirm { flex:1; padding:11px; border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; font-weight:600; cursor:pointer; color:#fff; text-align:center; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:6px; }
  .bmc-approve { background:var(--green); }
  .bmc-reject  { background:var(--red); }
  .btn-modal-confirm:hover { opacity:.88; color:#fff; }

  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Toast ── -->
<?php if(isset($_GET['done'])): ?>
<div class="toast-bar" id="toast">
  <?php if($_GET['done']==='approved'): ?>
    <i class="bi bi-check-circle-fill" style="color:var(--green);"></i> Approved งานเรียบร้อยแล้ว
  <?php else: ?>
    <i class="bi bi-x-circle-fill" style="color:#f87171;"></i> Rejected งานเรียบร้อยแล้ว
  <?php endif; ?>
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php endif; ?>

<!-- ── Confirm modal ── -->
<div class="modal-overlay" id="confirm-modal">
  <div class="modal-box">
    <div class="modal-icon" id="modal-icon-wrap"><i id="modal-icon" class="bi"></i></div>
    <div class="modal-title" id="modal-title"></div>
    <div class="modal-sub"  id="modal-sub"></div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal()">ยกเลิก</button>
      <a href="#" id="modal-confirm-btn" class="btn-modal-confirm"></a>
    </div>
  </div>
</div>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon" style="width:160px!important;height:240px!important;min-width:160px!important;max-width:160px!important;max-height:240px!important;flex:0 0 240px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=9" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Job_Find</div>
        <div class="logo-sub" style="display:none!important;">Admin Panel</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php"          class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="admin_manage_users.php"       class="nav-item"><i class="bi bi-people"></i> Manage Users</a>
    <a href="admin_manage_jobs.php"        class="nav-item active">
      <i class="bi bi-briefcase"></i> Manage Jobs
      <?php if($cnt_pending>0): ?><span class="nav-badge"><?php echo $cnt_pending; ?></span><?php endif; ?>
    </a>
    <a href="admin_manage_categories.php"  class="nav-item"><i class="bi bi-tag"></i> Categories</a>
    <div class="nav-divider"></div>
    <a href="admin_support.php"            class="nav-item">
      <i class="bi bi-chat-dots"></i> Support Chat
      <?php if($admin_unread_support > 0): ?><span class="nav-badge"><?php echo $admin_unread_support; ?></span><?php endif; ?>
    </a>
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
      <p>อนุมัติหรือปฏิเสธตำแหน่งงานที่ Employer โพสต์</p>
    </div>
  </div>

  <!-- Stat row (clickable filter) -->
  <div class="stat-row">
    <div class="stat-mini active-filter" data-filter="" onclick="setStatFilter(this, '')">
      <div class="sm-icon si-purple"><i class="bi bi-briefcase"></i></div>
      <div><div class="sm-val"><?php echo $cnt_all; ?></div><div class="sm-lbl">ทั้งหมด</div></div>
    </div>
    <div class="stat-mini" data-filter="pending" onclick="setStatFilter(this, 'pending')">
      <div class="sm-icon si-yellow"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="sm-val"><?php echo $cnt_pending; ?></div><div class="sm-lbl">รออนุมัติ</div></div>
    </div>
    <div class="stat-mini" data-filter="approved" onclick="setStatFilter(this, 'approved')">
      <div class="sm-icon si-green"><i class="bi bi-check-circle"></i></div>
      <div><div class="sm-val"><?php echo $cnt_approved; ?></div><div class="sm-lbl">Approved</div></div>
    </div>
    <div class="stat-mini" data-filter="rejected" onclick="setStatFilter(this, 'rejected')">
      <div class="sm-icon si-red"><i class="bi bi-x-circle"></i></div>
      <div><div class="sm-val"><?php echo $cnt_rejected; ?></div><div class="sm-lbl">Rejected</div></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" id="search-input" placeholder="ค้นหาตำแหน่งงาน, Employer..." oninput="filterCards()">
    </div>
    <span class="result-info">พบ <strong id="result-count"><?php echo $cnt_all; ?></strong> งาน</span>
  </div>

  <!-- Job cards -->
  <div id="job-list">
  <?php
  $icons = ['💼','🖥️','📐','📊','🚀','🎨','⚙️','📱','✍️','📢','🎓','💰'];
  foreach($rows as $row):
    $status    = $row['admin_status'];
    $icon      = $icons[crc32($row['title']) % count($icons)];
    $sp_class  = match($status){ 'approved'=>'sp-approved','rejected'=>'sp-rejected',default=>'sp-pending' };
    $sp_label  = match($status){ 'approved'=>'Approved','rejected'=>'Rejected',default=>'Pending' };
    $sp_icon   = match($status){ 'approved'=>'bi-check-circle-fill','rejected'=>'bi-x-circle-fill',default=>'bi-hourglass-split' };
    $date_str  = !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '';
  ?>
  <div class="job-card <?php echo $status==='pending'?'pending-card':''; ?>"
       data-status="<?php echo $status; ?>"
       data-search="<?php echo strtolower(htmlspecialchars($row['title'].' '.$row['username'])); ?>">

    <div class="jc-top">
      <div>
        <div class="jc-title">
          <?php echo htmlspecialchars($row['title']); ?>
          <?php if($status==='pending'): ?>
          <span style="font-size:11px;font-weight:600;background:#fef9c3;color:#854d0e;padding:2px 9px;border-radius:12px;margin-left:6px;vertical-align:middle;">ใหม่</span>
          <?php endif; ?>
        </div>
        <div class="jc-employer">
          <i class="bi bi-building" style="font-size:12px;"></i>
          <?php echo htmlspecialchars($row['username']); ?>
        </div>
      </div>
      <span class="status-pill <?php echo $sp_class; ?>">
        <i class="bi <?php echo $sp_icon; ?>"></i> <?php echo $sp_label; ?>
      </span>
    </div>

    <p class="jc-desc"><?php echo htmlspecialchars($row['description']); ?></p>

    <div class="jc-meta">
      <?php if(!empty($row['location'])): ?>
      <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($row['location']); ?></span>
      <?php endif; ?>
      <?php if(!empty($row['salary'])): ?>
      <span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($row['salary']); ?></span>
      <?php endif; ?>
      <?php if($date_str): ?>
      <span><i class="bi bi-clock"></i><?php echo $date_str; ?></span>
      <?php endif; ?>
      <span><i class="bi bi-hash"></i>ID: <?php echo $row['job_id']; ?></span>
    </div>

    <div class="jc-footer">
      <a href="admin_job_detail.php?id=<?php echo $row['job_id']; ?>" 
          class="btn-detail" 
          style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-size:13px;font-weight:600;background:var(--accent);color:#fff;text-decoration:none;transition:all .15s;">
        <i class="bi bi-eye"></i> Detail
      </a>

      <div class="actions-wrap">
        <?php if($status === 'pending'): ?>
          <button class="btn-approve"
            onclick="confirmAction('approve', <?php echo $row['job_id']; ?>, '<?php echo htmlspecialchars($row['title'],ENT_QUOTES); ?>')">
            <i class="bi bi-check-lg"></i> Approve
          </button>
          <button class="btn-reject"
            onclick="confirmAction('reject', <?php echo $row['job_id']; ?>, '<?php echo htmlspecialchars($row['title'],ENT_QUOTES); ?>')">
            <i class="bi bi-x-lg"></i> Reject
          </button>

        <?php elseif($status === 'approved'): ?>
          <span class="btn-done"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> Approved แล้ว</span>
          <button class="btn-reject"
            onclick="confirmAction('reject', <?php echo $row['job_id']; ?>, '<?php echo htmlspecialchars($row['title'],ENT_QUOTES); ?>')">
            <i class="bi bi-x-lg"></i> Reject
          </button>

        <?php else: ?>
          <span class="btn-done"><i class="bi bi-x-circle-fill" style="color:var(--red);"></i> Rejected แล้ว</span>
          <button class="btn-approve"
            onclick="confirmAction('approve', <?php echo $row['job_id']; ?>, '<?php echo htmlspecialchars($row['title'],ENT_QUOTES); ?>')">
            <i class="bi bi-check-lg"></i> Approve
          </button>
        <?php endif; ?>
      </div>
    </div>

  </div>
  <?php endforeach; ?>
  </div>

  <div class="empty-state" id="empty-state">
    <i class="bi bi-inbox"></i>
    <p>ไม่พบงานที่ตรงกับเงื่อนไข</p>
  </div>

</main>

<script>
  let currentStatus = '';

  function setStatFilter(el, status){
    document.querySelectorAll('.stat-mini').forEach(s => s.classList.remove('active-filter'));
    el.classList.add('active-filter');
    currentStatus = status;
    filterCards();
  }

  function filterCards(){
    const kw   = document.getElementById('search-input').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.job-card');
    let visible = 0;

    cards.forEach(c => {
      const matchStatus = !currentStatus || c.dataset.status === currentStatus;
      const matchKw     = !kw || (c.dataset.search||'').includes(kw);
      const show = matchStatus && matchKw;
      c.classList.toggle('hidden', !show);
      if(show) visible++;
    });

    document.getElementById('result-count').textContent = visible;
    document.getElementById('empty-state').style.display = visible === 0 ? 'block' : 'none';
  }

  function confirmAction(type, id, title){
    const isApprove = type === 'approve';
    document.getElementById('modal-icon-wrap').className = 'modal-icon ' + (isApprove ? 'mi-approve' : 'mi-reject');
    document.getElementById('modal-icon').className = 'bi ' + (isApprove ? 'bi-check-lg' : 'bi-x-lg');
    document.getElementById('modal-title').textContent = isApprove ? 'ยืนยันการ Approve' : 'ยืนยันการ Reject';
    document.getElementById('modal-sub').innerHTML = (isApprove
      ? 'คุณต้องการ <strong>Approve</strong> งาน'
      : 'คุณต้องการ <strong>Reject</strong> งาน') + ' <strong>"' + title + '"</strong> ใช่ไหม?';
    const btn = document.getElementById('modal-confirm-btn');
    btn.href = 'admin_manage_jobs.php?' + type + '=' + id;
    btn.className = 'btn-modal-confirm ' + (isApprove ? 'bmc-approve' : 'bmc-reject');
    btn.innerHTML = isApprove
      ? '<i class="bi bi-check-lg"></i> Approve เลย'
      : '<i class="bi bi-x-lg"></i> Reject เลย';
    document.getElementById('confirm-modal').classList.add('show');
  }

  function closeModal(){
    document.getElementById('confirm-modal').classList.remove('show');
  }

  document.getElementById('confirm-modal').addEventListener('click', function(e){
    if(e.target === this) closeModal();
  });
</script>
</body>
</html>
