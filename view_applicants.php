<?php
session_start();
include "config.php";  // ✅ ใช้ config.php เหมือนไฟล์อื่น

// ✅ ตรวจสอบด้วย $_SESSION['role']
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: /JobFind_Project/login.php");
    exit();
}

$job_id = $_GET['job_id'] ?? 0;
$employer_id = $_SESSION['user_id'];

// ✅ ใช้ตาราง 'job' และคีย์ 'job_id'
$job_query = "SELECT * FROM job WHERE job_id = ? AND employer_id = ?";
$stmt = $conn->prepare($job_query);
$stmt->bind_param("ii", $job_id, $employer_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: employer_manage_jobs.php");
    exit();
}

// ดึงผู้สมัครจากตาราง 'job_application'
// ตัด u.location ออก เพราะไม่มีในตาราง users
$applicants_query = "
    SELECT ja.*, u.username, u.email, u.phone, u.fullname,
           fp.skill, fp.experience, fp.rating, fp.location
    FROM job_application ja
    JOIN users u ON ja.freelancer_id = u.user_id
    LEFT JOIN freelancer_profile fp ON u.user_id = fp.user_id
    WHERE ja.job_id = ?
    ORDER BY ja.apply_date DESC
";

$stmt = $conn->prepare($applicants_query);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$applicants = $stmt->get_result();
$stmt->close();

// ✅ ฟังก์ชันตรวจสอบการบันทึก (ใช้ user_id)
function isFreelancerSaved($conn, $employer_id, $freelancer_id) {
    $query = "SELECT id FROM saved_freelancers WHERE employer_id = ? AND freelancer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $employer_id, $freelancer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_saved = $result->num_rows > 0;
    $stmt->close();
    return $is_saved;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผู้สมัคร - <?php echo htmlspecialchars($job['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --navy: #0f172a; --navy2: #1e293b; --navy3: #334155;
            --accent: #6366f1; --light: #f1f5f9; --white: #ffffff;
            --text: #0f172a; --muted: #64748b; --border: #e2e8f0;
            --green: #10b981; --yellow: #f59e0b; --red: #ef4444;
            --radius: 14px;
        }
        body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; }
        
        /* Sidebar - ใช้แบบเดียวกับ employer_manage_jobs.php */
        .sidebar { width:240px; min-height:100vh; background:var(--navy); display:flex; flex-direction:column; padding:28px 0; position:fixed; top:0; left:0; z-index:100; }
        .sidebar-brand { padding:0 24px 28px; border-bottom:1px solid var(--navy3); }
        .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .logo-icon { width:36px; height:36px; background:var(--accent); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; }
        .logo-text { font-size:15px; font-weight:600; color:#fff; line-height:1.2; }
        .logo-sub { font-size:11px; color:var(--navy3); }
        .sidebar-nav { padding:20px 12px; flex:1; display:flex; flex-direction:column; gap:4px; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#94a3b8; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s,color .15s; }
        .nav-item:hover { background:var(--navy2); color:#e2e8f0; }
        .nav-item.active { background:var(--accent); color:#fff; }
        .nav-item i { font-size:17px; width:20px; text-align:center; }
        .sidebar-footer { padding:16px 12px 0; }
        .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; }
        .nav-logout:hover { background:rgba(239,68,68,.12); }

        .main { margin-left:240px; flex:1; padding:36px 40px; }
        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
        .page-title { font-size:22px; font-weight:600; margin-bottom:5px; }
        .page-subtitle { font-size:13px; color:var(--muted); }
        
        .back-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; background:var(--white); color:var(--accent); border:1px solid var(--accent); border-radius:10px; text-decoration:none; font-size:13.5px; font-weight:500; transition:all .15s; }
        .back-btn:hover { background:var(--accent); color:#fff; }

        .applicants-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:16px; }
        
        .applicant-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px; transition:box-shadow .2s,border-color .2s; cursor:pointer; }
        .applicant-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07); border-color:#a5b4fc; }
        
        .applicant-header { display:flex; align-items:center; gap:14px; margin-bottom:12px; }
        .applicant-avatar { width:56px; height:56px; background:linear-gradient(135deg,#6366f1,#8b5cf6); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; font-weight:600; flex-shrink:0; }
        .applicant-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
        
        .applicant-info h4 { font-size:16px; font-weight:600; color:var(--text); margin-bottom:4px; }
        .applicant-meta { display:flex; gap:12px; font-size:12.5px; color:var(--muted); flex-wrap:wrap; }
        .rating { color:#f59e0b; }
        
        .view-btn { width:100%; padding:10px; background:var(--accent); color:#fff; border:none; border-radius:10px; font-size:13.5px; font-weight:500; cursor:pointer; transition:opacity .15s; margin-top:8px; }
        .view-btn:hover { opacity:.9; }

        /* Modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:1000; align-items:center; justify-content:center; padding:20px; }
        .modal-overlay.show { display:flex; }
        .modal-content { background:var(--white); border-radius:var(--radius); max-width:680px; width:100%; max-height:90vh; overflow-y:auto; }
        
        .modal-header-custom { background:linear-gradient(135deg,#6366f1,#8b5cf6); padding:24px 28px; color:#fff; position:relative; display:flex; justify-content:space-between; align-items:flex-start; }
        .modal-close { background:rgba(255,255,255,.2); border:none; width:34px; height:34px; border-radius:50%; color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .15s; }
        .modal-close:hover { background:rgba(255,255,255,.3); }
        
        .modal-header-info { display:flex; gap:16px; align-items:center; }
        .modal-avatar { width:70px; height:70px; border-radius:50%; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; font-size:26px; font-weight:600; }
        .modal-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
        .modal-name { font-size:22px; font-weight:600; margin-bottom:6px; }
        .modal-meta { display:flex; gap:14px; font-size:13px; opacity:.9; }
        
        /* Save Button */
        .btn-save { background:rgba(255,255,255,.2); border:2px solid #fff; color:#fff; padding:7px 15px; border-radius:20px; cursor:pointer; font-size:13px; font-weight:500; display:inline-flex; align-items:center; gap:5px; transition:all .2s; }
        .btn-save:hover { background:#fff; color:#6366f1; }
        .btn-save.saved { background:#fff; color:#6366f1; }

        .modal-body-custom { padding:24px 28px; }
        .section-title { font-size:14px; font-weight:600; color:var(--text); margin-bottom:12px; display:flex; align-items:center; gap:7px; }
        
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; }
        .info-item { background:var(--light); padding:14px; border-radius:10px; }
        .info-label { font-size:12px; color:var(--muted); margin-bottom:4px; }
        .info-value { font-size:14px; color:var(--text); font-weight:500; }
        
        .skills-container { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px; }
        .skill-badge { background:#eef2ff; color:var(--accent); padding:5px 12px; border-radius:20px; font-size:12px; font-weight:500; }
        
        .action-buttons { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:20px; }
        .btn-accept { background:var(--green); color:#fff; border:none; padding:11px; border-radius:10px; font-weight:500; cursor:pointer; transition:opacity .15s; }
        .btn-reject { background:var(--red); color:#fff; border:none; padding:11px; border-radius:10px; font-weight:500; cursor:pointer; transition:opacity .15s; }
        .btn-accept:hover, .btn-reject:hover { opacity:.9; }

        /* Notification */
        .notification { position:fixed; top:20px; right:20px; background:var(--green); color:#fff; padding:12px 20px; border-radius:10px; z-index:9999; animation:slideIn .3s ease; }
        .notification.error { background:var(--red); }
        @keyframes slideIn { from{opacity:0;transform:translateX(100px)} to{opacity:1;transform:translateX(0)} }

        @media(max-width:768px){ .sidebar{display:none;} .main{margin-left:0;padding:20px 16px;} .info-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>

<!-- Sidebar -->
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
    <a href="employer_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php" class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="employer_manage_jobs.php" class="nav-item active"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="employer_reviews.php" class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="employer_profile.php" class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div style="height:1px;background:var(--navy3);margin:10px 14px;"></div>
    <a href="support_chat.php" class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- Main -->
<main class="main">
  <div class="page-header">
    <div>
      <h1 class="page-title">ผู้สมัครงาน</h1>
      <p class="page-subtitle">Freelancer ที่สมัครงาน "<?php echo htmlspecialchars($job['title']); ?>"</p>
    </div>
    <a href="employer_manage_jobs.php" class="back-btn">
      <i class="bi bi-arrow-left"></i> กลับ Manage Jobs
    </a>
  </div>

  <div class="applicants-grid">
    <?php while ($applicant = $applicants->fetch_assoc()): ?>
      <?php 
      $initials = strtoupper(mb_substr($applicant['username'], 0, 2, 'UTF-8'));
      $is_saved = isFreelancerSaved($conn, $employer_id, $applicant['freelancer_id']);
      ?>
      <div class="applicant-card" onclick='openModal(<?php echo json_encode($applicant, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS); ?>)'>
        <div class="applicant-header">
          <div class="applicant-avatar">
            <?php if(!empty($applicant['profile_picture'])): ?>
              <img src="<?php echo htmlspecialchars($applicant['profile_picture']); ?>" alt="">
            <?php else: ?>
              <?php echo $initials; ?>
            <?php endif; ?>
          </div>
          <div class="applicant-info">
            <h4><?php echo htmlspecialchars($applicant['username']); ?></h4>
            <div class="applicant-meta">
              <span><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($applicant['location'] ?? 'ไม่ระบุ'); ?></span>
              <?php if($applicant['rating']): ?>
                <span class="rating"><i class="bi bi-star-fill"></i> <?php echo number_format($applicant['rating'], 1); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div style="margin-bottom:10px;">
          <small style="color:var(--muted);">
            <i class="bi bi-clock"></i> สมัครเมื่อ <?php echo date('d/m/Y', strtotime($applicant['apply_date'])); ?>
          </small>
        </div>
        <button class="view-btn">ดูรายละเอียด</button>
      </div>
    <?php endwhile; ?>
    
    <?php if($applicants->num_rows == 0): ?>
      <div style="grid-column:1/-1;text-align:center;padding:40px;background:var(--white);border-radius:var(--radius);">
        <i class="bi bi-clock"></i> สมัครเมื่อ <?php echo date('d/m/Y', strtotime($applicant['apply_date'])); ?>
        <p style="margin-top:12px;color:var(--muted);">ยังไม่มีผู้สมัครงานนี้</p>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Modal -->
<div id="freelancerModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header-custom">
      <button type="button" class="modal-close" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
      
      <div class="modal-header-info">
        <div id="modalAvatar" class="modal-avatar"></div>
        <div>
          <h2 id="modalName" class="modal-name"></h2>
          <div class="modal-meta">
            <span id="modalLocation"></span>
            <span id="modalRating"></span>
          </div>
        </div>
      </div>
      
      <!-- ปุ่มบันทึก -->
      <button id="saveBtn" class="btn-save" onclick="toggleSave()">
        <i class="bi bi-bookmark" id="saveIcon"></i>
        <span id="saveText">บันทึก</span>
      </button>
    </div>
    
    <div class="modal-body-custom">
      <div class="section-title"><i class="bi bi-envelope"></i> ข้อมูลติดต่อ</div>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Email</div>
          <div class="info-value" id="modalEmail"></div>
        </div>
        <div class="info-item">
          <div class="info-label">Phone</div>
          <div class="info-value" id="modalPhone"></div>
        </div>
      </div>

      <div class="section-title"><i class="bi bi-person-workspace"></i> ข้อมูล Freelancer</div>
      
      <div style="margin-bottom:20px;">
        <div class="info-label" style="margin-bottom:8px;">Skills</div>
        <div id="modalSkills" class="skills-container"></div>
      </div>

      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Experience</div>
          <div class="info-value" id="modalExperience"></div>
        </div>
        <div class="info-item">
          <div class="info-label">Location</div>
          <div class="info-value" id="modalLocation2"></div>
        </div>
      </div>

      <div class="info-item" style="margin-bottom:20px;">
        <div class="info-label">About</div>
        <div class="info-value" id="modalAbout" style="line-height:1.6;"></div>
      </div>

      <div class="action-buttons">
        <button class="btn-accept" onclick="acceptApplicant()"><i class="bi bi-check-lg"></i> ยอมรับ</button>
        <button class="btn-reject" onclick="rejectApplicant()"><i class="bi bi-x-lg"></i> ปฏิเสธ</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentFreelancerId = null;
let currentJobId = <?php echo $job_id; ?>;
let isSaved = false;

// ต้องมี function openModal(applicant) { ครอบไว้ครับ
function openModal(applicant) {
    currentFreelancerId = applicant.freelancer_id;

    // แก้ตรงส่วนนี้
    const loc = applicant.location || 'ไม่ระบุ'; 
    
    document.getElementById('modalName').textContent = applicant.username;
    document.getElementById('modalEmail').textContent = applicant.email;
    document.getElementById('modalPhone').textContent = applicant.phone || 'ไม่ระบุ';
    document.getElementById('modalLocation').textContent = '📍 ' + loc;
    document.getElementById('modalLocation2').textContent = loc;
    document.getElementById('modalExperience').textContent = applicant.experience || 'ไม่ระบุ';
    document.getElementById('modalAbout').textContent = 'ไม่มีข้อมูลเพิ่มเติม';
    
    const avatar = document.getElementById('modalAvatar');
    if (applicant.profile_picture) {
        avatar.innerHTML = `<img src="${applicant.profile_picture}" alt="">`;
    } else {
        avatar.textContent = applicant.username.substring(0,2).toUpperCase();
    }
    
    if (applicant.rating) {
        // ตารางไม่มี review_count ให้ใส่เป็น 0 ไปก่อนครับ
        document.getElementById('modalRating').textContent = `⭐ ${parseFloat(applicant.rating).toFixed(1)}`;
    } else {
        document.getElementById('modalRating').textContent = '⭐ ยังไม่มีรีวิว';
    }
    
    // แก้ส่วน skills container
    const skillsContainer = document.getElementById('modalSkills');
    skillsContainer.innerHTML = '';
    if (applicant.skill) {  
        applicant.skill.split(',').forEach(skill => {
            const badge = document.createElement('span');
            badge.className = 'skill-badge';
            badge.textContent = skill.trim();
            skillsContainer.appendChild(badge);
        });
    } else {
        skillsContainer.innerHTML = '<span style="color:var(--muted);">ยังไม่มีทักษะ</span>';
    }
    
    checkSaveStatus(applicant.freelancer_id);
    document.getElementById('freelancerModal').classList.add('show');
    document.body.style.overflow = 'hidden';
} // ปิดฟังก์ชันตรงนี้

function closeModal() {
// ... (โค้ดเดิมด้านล่างปล่อยไว้ได้เลยครับ)
    document.getElementById('freelancerModal').classList.remove('show');
    document.body.style.overflow = 'auto';
}

function checkSaveStatus(freelancerId) {
    fetch('check_saved_freelancer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `freelancer_id=${freelancerId}`
    })
    .then(r => r.json())
    .then(data => {
        isSaved = data.is_saved;
        updateSaveButton();
    });
}

function toggleSave() {
    if (!currentFreelancerId) return;
    const action = isSaved ? 'unsave' : 'save';
    
    fetch('save_freelancer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `freelancer_id=${currentFreelancerId}&action=${action}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            isSaved = !isSaved;
            updateSaveButton();
            showNotification(isSaved ? 'บันทึกแล้ว ✓' : 'ยกเลิกการบันทึก', isSaved ? 'success' : 'success');
        } else {
            showNotification(data.message || 'เกิดข้อผิดพลาด', 'error');
        }
    });
}

function updateSaveButton() {
    const btn = document.getElementById('saveBtn');
    const icon = document.getElementById('saveIcon');
    const text = document.getElementById('saveText');
    
    if (isSaved) {
        btn.classList.add('saved');
        icon.className = 'bi bi-bookmark-check-fill';
        text.textContent = 'บันทึกแล้ว';
    } else {
        btn.classList.remove('saved');
        icon.className = 'bi bi-bookmark';
        text.textContent = 'บันทึก';
    }
}

function showNotification(msg, type) {
    const n = document.createElement('div');
    n.className = `notification ${type==='error'?'error':''}`;
    n.textContent = msg;
    document.body.appendChild(n);
    setTimeout(() => n.remove(), 3000);
}

function acceptApplicant() {
    if (!currentFreelancerId) return;
    if (confirm('ยอมรับผู้สมัครคนนี้?')) {
        showNotification('ยอมรับแล้ว ✓');
        closeModal();
        // เพิ่ม AJAX สำหรับอัปเดตสถานะที่นี่
    }
}

function rejectApplicant() {
    if (!currentFreelancerId) return;
    if (confirm('ปฏิเสธผู้สมัครคนนี้?')) {
        showNotification('ปฏิเสธแล้ว');
        closeModal();
        // เพิ่ม AJAX สำหรับอัปเดตสถานะที่นี่
    }
}

document.getElementById('freelancerModal').addEventListener('click', e => {
    if (e.target.id === 'freelancerModal') closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>