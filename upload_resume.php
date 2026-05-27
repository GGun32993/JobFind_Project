<?php
session_start();
require_once __DIR__ . "/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: login.php");
    exit();
}

$freelancer_id = $_SESSION['user_id'];

$alert_type = '';
$alert_msg  = '';

// upload
if(isset($_POST['upload'])){
    if(isset($_FILES['resume']) && $_FILES['resume']['error']==0){
        $filename = $_FILES['resume']['name'];
        $tmp      = $_FILES['resume']['tmp_name'];
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if($ext != "pdf"){
            $alert_type = 'error';
            $alert_msg  = 'อัปโหลดได้เฉพาะไฟล์ PDF เท่านั้น';
        } else {
            $newname = time()."_".$filename;
            move_uploaded_file($tmp, "uploads/".$newname);
            mysqli_query($conn,"
                INSERT INTO resume (freelancer_id, file_name)
                VALUES ('$freelancer_id','$newname')
            ");
            $alert_type = 'success';
            $alert_msg  = 'อัปโหลด Resume สำเร็จแล้ว';
        }
    } else {
        $alert_type = 'error';
        $alert_msg  = 'กรุณาเลือกไฟล์ก่อนอัปโหลด';
    }
}

// get resume
$res  = mysqli_query($conn,"
    SELECT * FROM resume
    WHERE freelancer_id='$freelancer_id'
    ORDER BY resume_id DESC
    LIMIT 1
");
$data = mysqli_fetch_assoc($res);

// file size display
$file_size = '';
if($data){
    $path = "uploads/".$data['file_name'];
    if(file_exists($path)){
        $bytes = filesize($path);
        $file_size = $bytes < 1048576
            ? round($bytes/1024, 1).' KB'
            : round($bytes/1048576, 2).' MB';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Resume</title>
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

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; min-height:100vh; }
  .content-wrap { max-width:600px; }

  .topbar { margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Toast ── */
  .toast-bar {
    position:fixed; top:24px; right:24px; z-index:999;
    background:var(--navy); color:#fff;
    padding:14px 20px; border-radius:12px;
    display:flex; align-items:center; gap:10px;
    font-size:14px; font-weight:500;
    box-shadow:0 8px 24px rgba(0,0,0,.18);
    animation:slideIn .3s ease;
    transition:opacity .4s;
  }
  .toast-bar.err { background:#7f1d1d; }
  .toast-bar i { font-size:18px; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* ── Upload zone ── */
  .upload-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:28px; margin-bottom:20px; }
  .section-title {
    font-size:13px; font-weight:600; color:var(--muted);
    text-transform:uppercase; letter-spacing:.05em;
    margin-bottom:20px; display:flex; align-items:center; gap:8px;
  }
  .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

  .drop-zone {
    border:2px dashed var(--border);
    border-radius:var(--radius);
    padding:40px 20px;
    text-align:center;
    cursor:pointer;
    transition:border-color .2s, background .2s;
    margin-bottom:20px;
    position:relative;
  }
  .drop-zone:hover, .drop-zone.drag-over {
    border-color:var(--accent);
    background:#f5f3ff;
  }
  .drop-zone input[type="file"] {
    position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
  }
  .drop-icon { font-size:40px; color:#c7d2fe; margin-bottom:12px; }
  .drop-title { font-size:15px; font-weight:600; margin-bottom:4px; }
  .drop-sub   { font-size:13px; color:var(--muted); }
  .drop-sub span { color:var(--accent); font-weight:500; }
  .file-chosen { font-size:13px; color:var(--accent); font-weight:500; margin-top:10px; display:none; }

  .btn-upload {
    width:100%; padding:12px;
    background:var(--accent); color:#fff;
    border:none; border-radius:10px;
    font-family:'Sora',sans-serif;
    font-size:14px; font-weight:600;
    cursor:pointer; display:flex; align-items:center;
    justify-content:center; gap:8px;
    transition:background .15s, transform .1s;
  }
  .btn-upload:hover { background:#4f46e5; transform:translateY(-1px); }

  /* ── Current resume card ── */
  .resume-card {
    background:var(--white); border:1px solid var(--border);
    border-radius:var(--radius); padding:22px 24px;
  }
  .file-row {
    display:flex; align-items:center; gap:16px;
    padding:16px; background:var(--light);
    border-radius:10px; margin-bottom:16px;
  }
  .file-icon {
    width:48px; height:48px; border-radius:10px;
    background:#fee2e2; display:flex; align-items:center;
    justify-content:center; font-size:22px; color:#dc2626; flex-shrink:0;
  }
  .file-info { flex:1; min-width:0; }
  .file-name {
    font-size:14px; font-weight:600;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    margin-bottom:3px;
  }
  .file-meta { font-size:12px; color:var(--muted); }

  .resume-actions { display:flex; gap:10px; flex-wrap:wrap; }
  .btn-view {
    flex:1; padding:10px 16px;
    background:var(--green); color:#fff;
    border:none; border-radius:10px;
    font-family:'Sora',sans-serif;
    font-size:13px; font-weight:600;
    text-decoration:none; text-align:center;
    display:flex; align-items:center; justify-content:center; gap:6px;
    transition:opacity .15s;
  }
  .btn-view:hover { opacity:.85; color:#fff; }
  .btn-delete {
    padding:10px 16px;
    background:#fee2e2; color:#dc2626;
    border:1px solid #fca5a5; border-radius:10px;
    font-family:'Sora',sans-serif;
    font-size:13px; font-weight:600;
    text-decoration:none; text-align:center;
    display:flex; align-items:center; gap:6px;
    transition:background .15s;
    white-space:nowrap;
  }
  .btn-delete:hover { background:#fecaca; color:#dc2626; }

  .no-resume {
    text-align:center; padding:28px 20px;
    color:var(--muted);
  }
  .no-resume i { font-size:36px; margin-bottom:10px; display:block; color:#c7d2fe; }
  .no-resume p { font-size:13px; }

  @media(max-width:768px){
    .sidebar { display:none; }
    .main { margin-left:0; padding:20px 16px; }
  }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<?php if($alert_msg): ?>
<div class="toast-bar <?php echo $alert_type==='error'?'err':''; ?>" id="toast">
  <i class="bi bi-<?php echo $alert_type==='success'?'check-circle-fill':'exclamation-circle-fill'; ?>"></i>
  <?php echo $alert_msg; ?>
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; }, 3500);</script>
<?php endif; ?>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div>
        <div class="logo-text">FreelanceHub</div>
        <div class="logo-sub">Freelancer</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="freelancer_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="browse_jobs.php"          class="nav-item"><i class="bi bi-briefcase"></i> Browse Jobs</a>
    <a href="my_applications.php"      class="nav-item"><i class="bi bi-file-earmark-text"></i> My Applications</a>
    <a href="my_profile.php"           class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <a href="freelancer_reviews.php"   class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="upload_resume.php"        class="nav-item active"><i class="bi bi-cloud-upload"></i> Upload Resume</a>
    <div class="nav-divider"></div>
    <a href="support_chat.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">
<div class="content-wrap">

  <div class="topbar">
    <h2>Upload Resume</h2>
    <p>อัปโหลดไฟล์ Resume ของคุณในรูปแบบ PDF</p>
  </div>

  <!-- Upload card -->
  <div class="upload-card">
    <div class="section-title"><i class="bi bi-cloud-arrow-up"></i> อัปโหลดไฟล์ใหม่</div>

    <form method="POST" enctype="multipart/form-data">
      <div class="drop-zone" id="drop-zone">
        <input type="file" name="resume" id="file-input" accept=".pdf" required onchange="showFileName(this)">
        <div class="drop-icon"><i class="bi bi-file-earmark-pdf"></i></div>
        <div class="drop-title">ลากไฟล์มาวางที่นี่</div>
        <div class="drop-sub">หรือ <span>คลิกเพื่อเลือกไฟล์</span></div>
        <div class="file-chosen" id="file-chosen">
          <i class="bi bi-check-circle-fill"></i> <span id="chosen-name"></span>
        </div>
      </div>

      <p style="font-size:12px;color:var(--muted);margin-bottom:16px;display:flex;align-items:center;gap:5px;">
        <i class="bi bi-info-circle"></i> รองรับเฉพาะไฟล์ .pdf เท่านั้น ขนาดไม่เกิน 5MB
      </p>

      <button type="submit" name="upload" class="btn-upload">
        <i class="bi bi-cloud-upload"></i> อัปโหลด Resume
      </button>
    </form>
  </div>

  <!-- Current resume card -->
  <div class="resume-card">
    <div class="section-title"><i class="bi bi-file-earmark-check"></i> Resume ปัจจุบัน</div>

    <?php if($data): ?>
    <div class="file-row">
      <div class="file-icon"><i class="bi bi-filetype-pdf"></i></div>
      <div class="file-info">
        <div class="file-name"><?php echo htmlspecialchars($data['file_name']); ?></div>
        <div class="file-meta">
          <i class="bi bi-file-earmark" style="font-size:11px;"></i> PDF
          <?php if($file_size): ?> &nbsp;·&nbsp; <?php echo $file_size; ?><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="resume-actions">
      <a href="uploads/<?php echo htmlspecialchars($data['file_name']); ?>"
         target="_blank" class="btn-view">
        <i class="bi bi-eye"></i> ดู Resume
      </a>
      <a href="delete_resume.php" class="btn-delete"
         onclick="return confirm('ต้องการลบ Resume นี้ใช่ไหม?')">
        <i class="bi bi-trash"></i> ลบไฟล์
      </a>
    </div>

    <?php else: ?>
    <div class="no-resume">
      <i class="bi bi-file-earmark-x"></i>
      <p>ยังไม่มี Resume ในระบบ<br>อัปโหลดไฟล์ PDF เพื่อให้ Employer เห็นโปรไฟล์ของคุณ</p>
    </div>
    <?php endif; ?>
  </div>

</div>
</main>

<script>
  function showFileName(input){
    const chosen = document.getElementById('file-chosen');
    const name   = document.getElementById('chosen-name');
    if(input.files && input.files[0]){
      name.textContent = input.files[0].name;
      chosen.style.display = 'block';
    }
  }

  const zone = document.getElementById('drop-zone');
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const fi = document.getElementById('file-input');
    fi.files = e.dataTransfer.files;
    showFileName(fi);
  });
</script>
</body>
</html>
