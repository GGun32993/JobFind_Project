<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// ── stats ──
$total_users     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM users WHERE role!='admin'"))['c'];
$total_freelance = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM users WHERE role='freelancer'"))['c'];
$total_employer  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM users WHERE role='employer'"))['c'];
$total_jobs      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM job"))['c'];
$pending_jobs    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM job WHERE admin_status='pending'"))['c'];
$total_apps      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM job_application"))['c'];

// ── auto-add is_read column ถ้ายังไม่มี ──
$admin_id = $_SESSION['user_id'];
$col_check = mysqli_query($conn,"SHOW COLUMNS FROM chat_messages LIKE 'is_read'");
if(mysqli_num_rows($col_check) === 0){
    mysqli_query($conn,"ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) DEFAULT 0");
}

// ── unread support messages (เฉพาะที่ยังไม่ได้อ่าน) ──
$unread_res = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS c FROM chat_messages
    WHERE receiver_id='$admin_id'
    AND is_read = 0
"))['c'];

// ── recent jobs ──
$recent_jobs = mysqli_query($conn,"
    SELECT job.title, job.admin_status, users.username AS employer, job.created_at
    FROM job
    JOIN users ON users.user_id = job.employer_id
    ORDER BY job.created_at DESC
    LIMIT 5
");

// ── recent users ──
$recent_users = mysqli_query($conn,"
    SELECT username, role, created_at
    FROM users
    WHERE role != 'admin'
    ORDER BY created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

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
    --blue:   #0ea5e9;
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
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#94a3b8; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s,color .15s; position:relative; }
  .nav-item:hover  { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .nav-badge { position:absolute; right:12px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }
  .nav-logout i { font-size:17px; }

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; }

  /* ── Topbar ── */
  .topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; }
  .topbar-left h2 { font-size:22px; font-weight:600; }
  .topbar-left p  { font-size:13px; color:var(--muted); margin-top:2px; }
  .admin-chip { display:flex; align-items:center; gap:10px; background:var(--white); border:1px solid var(--border); border-radius:30px; padding:8px 16px 8px 10px; }
  .admin-chip .av { width:30px; height:30px; border-radius:50%; background:var(--accent); color:#fff; font-size:12px; font-weight:600; display:flex; align-items:center; justify-content:center; }
  .admin-chip span { font-size:13px; font-weight:500; }

  /* ── Stat grid ── */
  .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
  .stat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 22px; display:flex; align-items:center; gap:16px; }
  .stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
  .si-purple { background:#eef2ff; color:var(--accent); }
  .si-green  { background:#d1fae5; color:var(--green); }
  .si-yellow { background:#fef9c3; color:var(--yellow); }
  .si-red    { background:#fee2e2; color:var(--red); }
  .si-blue   { background:#e0f2fe; color:var(--blue); }
  .si-navy   { background:#e2e8f0; color:var(--navy3); }
  .stat-info .value { font-size:26px; font-weight:600; line-height:1; }
  .stat-info .label { font-size:12px; color:var(--muted); margin-top:4px; }

  /* ── Quick links ── */
  .quick-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:28px; }
  .quick-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 18px; text-decoration:none; color:var(--text); display:flex; flex-direction:column; align-items:flex-start; gap:10px; transition:box-shadow .2s,border-color .2s,transform .15s; position:relative; overflow:hidden; }
  .quick-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.09); border-color:#c7d2fe; transform:translateY(-2px); color:var(--text); }
  .qc-icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:20px; }
  .qc-title { font-size:14px; font-weight:600; }
  .qc-sub   { font-size:12px; color:var(--muted); }
  .qc-arrow { position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:18px; color:var(--border); transition:color .15s,right .15s; }
  .quick-card:hover .qc-arrow { color:var(--accent); right:12px; }
  .pending-dot { position:absolute; top:14px; right:14px; width:8px; height:8px; border-radius:50%; background:var(--red); }

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
  .sp-fl { background:#eef2ff; color:var(--accent); }
  .sp-em { background:#e0f2fe; color:#0369a1; }

  @media(max-width:1100px){ .stat-grid { grid-template-columns:repeat(2,1fr); } .quick-grid { grid-template-columns:repeat(2,1fr); } }
  @media(max-width:768px) { .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .two-col { grid-template-columns:1fr; } }
</style>
</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-shield-check"></i></div>
      <div>
        <div class="logo-text">FreelanceHub</div>
        <div class="logo-sub">Admin Panel</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php"          class="nav-item active"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="admin_manage_users.php"        class="nav-item"><i class="bi bi-people"></i> Manage Users</a>
    <a href="admin_manage_jobs.php"         class="nav-item">
      <i class="bi bi-briefcase"></i> Manage Jobs
      <?php if($pending_jobs > 0): ?><span class="nav-badge"><?php echo $pending_jobs; ?></span><?php endif; ?>
    </a>
    <a href="admin_manage_categories.php"   class="nav-item"><i class="bi bi-tag"></i> Categories</a>
    <div class="nav-divider"></div>
    <a href="admin_support.php"             class="nav-item">
      <i class="bi bi-chat-dots"></i> Support Chat
      <?php if($unread_res > 0): ?><span class="nav-badge"><?php echo $unread_res; ?></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <h2>Admin Dashboard</h2>
      <p>ยินดีต้อนรับกลับ, <?php echo htmlspecialchars($username); ?> 👋</p>
    </div>
    <div class="admin-chip">
      <div class="av"><?php echo strtoupper(substr($username,0,1)); ?></div>
      <span><?php echo htmlspecialchars($username); ?></span>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-people"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_users; ?></div>
        <div class="label">ผู้ใช้งานทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-person-badge"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_freelance; ?></div>
        <div class="label">Freelancer</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-building"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_employer; ?></div>
        <div class="label">Employer</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-yellow"><i class="bi bi-briefcase"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_jobs; ?></div>
        <div class="label">งานทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-red"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $pending_jobs; ?></div>
        <div class="label">งานรออนุมัติ</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-navy"><i class="bi bi-file-earmark-text"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_apps; ?></div>
        <div class="label">การสมัครทั้งหมด</div>
      </div>
    </div>
  </div>

  <!-- Quick links -->
  <div class="quick-grid" style="margin-bottom:28px;">
    <a href="admin_manage_users.php" class="quick-card">
      <div class="qc-icon si-purple"><i class="bi bi-people"></i></div>
      <div class="qc-title">Manage Users</div>
      <div class="qc-sub">จัดการผู้ใช้งาน</div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="admin_manage_jobs.php" class="quick-card">
      <?php if($pending_jobs > 0): ?><div class="pending-dot"></div><?php endif; ?>
      <div class="qc-icon si-yellow"><i class="bi bi-briefcase"></i></div>
      <div class="qc-title">Manage Jobs</div>
      <div class="qc-sub"><?php echo $pending_jobs > 0 ? $pending_jobs.' งานรออนุมัติ' : 'จัดการตำแหน่งงาน'; ?></div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="admin_manage_categories.php" class="quick-card">
      <div class="qc-icon si-green"><i class="bi bi-tag"></i></div>
      <div class="qc-title">Categories</div>
      <div class="qc-sub">จัดการหมวดหมู่</div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="admin_support.php" class="quick-card">
      <?php if($unread_res > 0): ?><div class="pending-dot"></div><?php endif; ?>
      <div class="qc-icon si-blue"><i class="bi bi-chat-dots"></i></div>
      <div class="qc-title">Support Chat</div>
      <div class="qc-sub"><?php echo $unread_res > 0 ? $unread_res.' ข้อความใหม่' : 'ดูการสนทนา'; ?></div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
  </div>

  <!-- Recent tables -->
  <div class="two-col">

    <!-- Recent Jobs -->
    <div class="table-card">
      <div class="table-head">
        <h4><i class="bi bi-briefcase" style="color:var(--accent);margin-right:6px;"></i> งานล่าสุด</h4>
        <a href="admin_manage_jobs.php">ดูทั้งหมด →</a>
      </div>
      <table class="mini-table">
        <thead>
          <tr>
            <th>ตำแหน่ง</th>
            <th>Employer</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $has = false;
          while($j = mysqli_fetch_assoc($recent_jobs)):
            $has = true;
            $sc  = match(strtolower($j['admin_status'])){
              'approved' => 'sp-approved',
              'rejected' => 'sp-rejected',
              default    => 'sp-pending',
            };
        ?>
          <tr>
            <td style="font-weight:500;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($j['title']); ?></td>
            <td style="color:var(--muted);"><?php echo htmlspecialchars($j['employer']); ?></td>
            <td><span class="status-pill <?php echo $sc; ?>"><?php echo ucfirst($j['admin_status']); ?></span></td>
          </tr>
        <?php endwhile; ?>
        <?php if(!$has): ?>
          <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:24px;">ยังไม่มีงาน</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Users -->
    <div class="table-card">
      <div class="table-head">
        <h4><i class="bi bi-people" style="color:var(--accent);margin-right:6px;"></i> ผู้ใช้ล่าสุด</h4>
        <a href="admin_manage_users.php">ดูทั้งหมด →</a>
      </div>
      <table class="mini-table">
        <thead>
          <tr>
            <th>Username</th>
            <th>Role</th>
            <th>วันที่สมัคร</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $has2 = false;
          while($u = mysqli_fetch_assoc($recent_users)):
            $has2 = true;
            $rc   = $u['role'] === 'freelancer' ? 'sp-fl' : 'sp-em';
        ?>
          <tr>
            <td style="font-weight:500;"><?php echo htmlspecialchars($u['username']); ?></td>
            <td><span class="status-pill <?php echo $rc; ?>"><?php echo ucfirst($u['role']); ?></span></td>
            <td style="color:var(--muted);"><?php echo !empty($u['created_at']) ? date('d M Y', strtotime($u['created_at'])) : '—'; ?></td>
          </tr>
        <?php endwhile; ?>
        <?php if(!$has2): ?>
          <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:24px;">ยังไม่มีผู้ใช้</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

</main>
</body>
</html>