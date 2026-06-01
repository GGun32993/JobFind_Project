<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "job_image_helpers.php";

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

$job_id = intval($_GET['id'] ?? 0);
ensure_job_image_schema($conn);

// ดึงข้อมูลงาน + ข้อมูล employer
$result = mysqli_query($conn,"
    SELECT job.*, users.username, users.email, users.phone, users.fullname
    FROM job
    JOIN users ON users.user_id = job.employer_id
    WHERE job.job_id = '$job_id'
");
$job = mysqli_fetch_assoc($result);

if(!$job){
    echo "<script>alert('ไม่พบงานนี้'); window.location.href='admin_manage_jobs.php';</script>";
    exit();
}

$job_images = get_job_images($conn, $job_id);

// approve / reject action
if(isset($_GET['approve'])){
    mysqli_query($conn,"UPDATE job SET admin_status='approved', status='approved' WHERE job_id='$job_id'");
    header("Location: admin_job_detail.php?id=$job_id&done=approved");
    exit();
}
if(isset($_GET['reject'])){
    mysqli_query($conn,"UPDATE job SET admin_status='rejected' WHERE job_id='$job_id'");
    header("Location: admin_job_detail.php?id=$job_id&done=rejected");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=6">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Detail - Job_Find Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  :root {
    --navy:#0f172a; --navy2:#1e293b; --navy3:#334155;
    --accent:#6366f1; --light:#f1f5f9; --white:#ffffff;
    --text:#0f172a; --muted:#64748b; --border:#e2e8f0;
    --green:#10b981; --yellow:#f59e0b; --red:#ef4444; --radius:14px;
  }
  body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; }
  .sidebar { width:240px; min-height:100vh; background:var(--navy); display:flex; flex-direction:column; padding:28px 0; position:fixed; top:0; left:0; z-index:100; }
  .sidebar-brand { padding:0 24px 28px; border-bottom:1px solid var(--navy3); }
  .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
  .logo-icon { width:36px; height:36px; background:var(--accent); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; }
  .logo-text { font-size:15px; font-weight:600; color:#fff; }
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
  .topbar-wrap { display:flex; align-items:center; gap:16px; margin-bottom:28px; }
  .btn-back { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:13px; color:var(--muted); text-decoration:none; background:var(--white); transition:all .15s; }
  .btn-back:hover { background:var(--light); color:var(--text); border-color:var(--navy3); }
  .topbar h2 { font-size:22px; font-weight:600; margin:0; }
  .topbar p { font-size:13px; color:var(--muted); margin-top:2px; }
  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
  
  .detail-grid { display:grid; grid-template-columns:1.2fr .8fr; gap:24px; align-items:start; }
  .card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:24px; }
  .card h4 { font-size:15px; font-weight:600; margin-bottom:18px; display:flex; align-items:center; gap:8px; }
  
  .job-title { font-size:18px; font-weight:600; margin-bottom:8px; }
  .job-meta { display:flex; gap:14px; flex-wrap:wrap; font-size:13px; color:var(--muted); margin-bottom:20px; }
  .job-meta span { display:flex; align-items:center; gap:5px; }
  
  .job-desc { font-size:14px; line-height:1.8; color:var(--text); white-space:pre-wrap; }
  .job-image-preview { width:100%; max-height:260px; object-fit:cover; display:block; border-radius:12px; border:1px solid var(--border); margin:16px 0 20px; }
  .job-image-gallery { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin:16px 0 20px; }
  .job-image-gallery img { width:100%; height:120px; object-fit:cover; display:block; border-radius:12px; border:1px solid var(--border); background:var(--light); }
  
  .info-row { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--border); }
  .info-row:last-child { border-bottom:none; }
  .info-label { font-size:13px; color:var(--muted); }
  .info-value { font-size:14px; font-weight:500; text-align:right; max-width:55%; word-break:break-word; }
  
  .status-pill { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:5px 13px; border-radius:20px; }
  .sp-pending { background:#fef9c3; color:#854d0e; }
  .sp-approved { background:#d1fae5; color:#065f46; }
  .sp-rejected { background:#fee2e2; color:#991b1b; }
  
  .action-bar { display:flex; gap:10px; margin-top:24px; flex-wrap:wrap; }
  .btn-approve { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border-radius:10px; font-size:13px; font-weight:600; background:var(--green); color:#fff; text-decoration:none; border:none; cursor:pointer; font-family:'Sora',sans-serif; }
  .btn-reject { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border-radius:10px; font-size:13px; font-weight:600; background:#fee2e2; color:#991b1b; text-decoration:none; border:none; cursor:pointer; font-family:'Sora',sans-serif; }
  .btn-done { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border-radius:10px; font-size:13px; font-weight:500; background:var(--light); color:var(--muted); border:1px solid var(--border); cursor:default; font-family:'Sora',sans-serif; }
  
  .section-divider { margin:20px 0; border:0; border-top:1px dashed var(--border); }
  
  @media(max-width:900px){ .detail-grid { grid-template-columns:1fr; } }
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<!-- Toast -->
<?php if(isset($_GET['done'])): ?>
<div class="toast-bar" id="toast">
  <?php if($_GET['done']==='approved'): ?>
    <i class="bi bi-check-circle-fill" style="color:var(--green);"></i> Approved เรียบร้อยแล้ว
  <?php else: ?>
    <i class="bi bi-x-circle-fill" style="color:#f87171;"></i> Rejected เรียบร้อยแล้ว
  <?php endif; ?>
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php endif; ?>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon"><img class="brand-logo-img" src="assets/images/jobfind-logo-icon.png?v=6" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text">Job_Find</div>
        <div class="logo-sub">Admin Panel</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="admin_manage_users.php" class="nav-item"><i class="bi bi-people"></i> Manage Users</a>
    <a href="admin_manage_jobs.php" class="nav-item active"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="admin_manage_categories.php" class="nav-item"><i class="bi bi-tag"></i> Categories</a>
    <div class="nav-divider"></div>
    <a href="admin_support.php" class="nav-item">
      <i class="bi bi-chat-dots"></i> Support Chat
      <?php if($admin_unread_support > 0): ?><span class="nav-badge"><?php echo $admin_unread_support; ?></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- Main -->
<main class="main">
  <div class="topbar-wrap">
    <a href="admin_manage_jobs.php" class="btn-back"><i class="bi bi-arrow-left"></i> กลับ</a>
    <div>
      <h2>Job Detail</h2>
      <p>รายละเอียดตำแหน่งงาน #<?php echo $job['job_id']; ?></p>
    </div>
  </div>

  <div class="detail-grid">
    <!-- Job Info -->
    <div class="card">
      <h4><i class="bi bi-briefcase" style="color:var(--accent);"></i> ข้อมูลงาน</h4>
      
      <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
      <?php if(!empty($job_images)): ?>
      <div class="job-image-gallery">
        <?php foreach($job_images as $image): ?>
        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($job['title']); ?>">
        <?php endforeach; ?>
      </div>
      <?php elseif(!empty($job['image_path'])): ?>
      <img src="<?php echo htmlspecialchars($job['image_path']); ?>" alt="<?php echo htmlspecialchars($job['title']); ?>" class="job-image-preview">
      <?php endif; ?>
      
      <div class="job-meta">
        <?php if(!empty($job['location'])): ?>
        <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
        <?php endif; ?>
        <?php if(!empty($job['salary'])): ?>
        <span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($job['salary']); ?></span>
        <?php endif; ?>
        <span><i class="bi bi-clock"></i><?php echo !empty($job['created_at']) ? date('d M Y H:i', strtotime($job['created_at'])) : '-'; ?></span>
      </div>
      
      <hr class="section-divider">
      
      <div style="margin-bottom:12px;">
        <strong style="font-size:13px;color:var(--muted);">รายละเอียดงาน</strong>
      </div>
      <div class="job-desc"><?php echo nl2br(htmlspecialchars($job['description'])); ?></div>
      
      <?php if(!empty($job['requirements'])): ?>
      <hr class="section-divider">
      <div style="margin-bottom:12px;">
        <strong style="font-size:13px;color:var(--muted);">คุณสมบัติที่ต้องการ</strong>
      </div>
      <div class="job-desc"><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></div>
      <?php endif; ?>
    </div>

    <!-- Employer & Actions -->
    <div>
      <div class="card">
        <h4><i class="bi bi-building" style="color:var(--accent);"></i> ข้อมูลผู้ว่าจ้าง</h4>
        
        <div class="info-row">
          <span class="info-label">Username</span>
          <span class="info-value"><?php echo htmlspecialchars($job['username']); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">ชื่อ-นามสกุล</span>
          <span class="info-value"><?php echo htmlspecialchars($job['fullname'] ?? '-'); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Email</span>
          <span class="info-value"><?php echo htmlspecialchars($job['email']); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">เบอร์โทรศัพท์</span>
          <span class="info-value"><?php echo htmlspecialchars($job['phone'] ?? '-'); ?></span>
        </div>
      </div>

      <div class="card" style="margin-top:24px;">
        <h4><i class="bi bi-shield-check" style="color:var(--accent);"></i> สถานะการอนุมัติ</h4>
        
        <div class="info-row">
          <span class="info-label">Admin Status</span>
          <span class="info-value">
            <span class="status-pill sp-<?php echo $job['admin_status']; ?>">
              <i class="bi bi-<?php echo $job['admin_status']==='approved'?'check-circle':($job['admin_status']==='rejected'?'x-circle':'hourglass-split'); ?>"></i>
              <?php echo ucfirst($job['admin_status']); ?>
            </span>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Public Status</span>
          <span class="info-value">
            <span class="status-pill sp-<?php echo $job['status']; ?>">
              <?php echo ucfirst($job['status']); ?>
            </span>
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="action-bar">
        <?php if($job['admin_status'] === 'pending'): ?>
          <a href="?id=<?php echo $job_id; ?>&approve=1" class="btn-approve" onclick="return confirm('ยืนยันการ Approve งานนี้?')">
            <i class="bi bi-check-lg"></i> Approve
          </a>
          <a href="?id=<?php echo $job_id; ?>&reject=1" class="btn-reject" onclick="return confirm('ยืนยันการ Reject งานนี้?')">
            <i class="bi bi-x-lg"></i> Reject
          </a>
        <?php elseif($job['admin_status'] === 'approved'): ?>
          <span class="btn-done"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> Approved แล้ว</span>
          <a href="?id=<?php echo $job_id; ?>&reject=1" class="btn-reject" onclick="return confirm('ต้องการเปลี่ยนเป็น Reject ใช่ไหม?')">
            <i class="bi bi-x-lg"></i> Reject
          </a>
        <?php else: ?>
          <span class="btn-done"><i class="bi bi-x-circle-fill" style="color:var(--red);"></i> Rejected แล้ว</span>
          <a href="?id=<?php echo $job_id; ?>&approve=1" class="btn-approve" onclick="return confirm('ต้องการเปลี่ยนเป็น Approve ใช่ไหม?')">
            <i class="bi bi-check-lg"></i> Approve
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

</body>
</html>
