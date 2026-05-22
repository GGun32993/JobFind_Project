<?php
session_start();
include "config.php";

// ตรวจสอบว่าเป็น Freelancer หรือไม่
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: login.php");
    exit();
}

$job_id = $_GET['job_id'] ?? 0;

// ดึงข้อมูลงาน
$job_query = "SELECT * FROM job WHERE job_id = ?";
$stmt = $conn->prepare($job_query);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: browse_jobs.php");
    exit();
}

// ✅ ดึงข้อมูลนายจ้าง + รายละเอียดบริษัท (JOIN 2 ตาราง)
$employer_query = "
    SELECT u.user_id, u.username, u.email, u.phone, u.created_at,
           ep.employer_description
    FROM users u
    LEFT JOIN employer_profile ep ON u.user_id = ep.user_id
    WHERE u.user_id = ?
";
$stmt_emp = $conn->prepare($employer_query);
$stmt_emp->bind_param("i", $job['employer_id']);
$stmt_emp->execute();
$employer = $stmt_emp->get_result()->fetch_assoc();
$stmt_emp->close();

if (!$employer) {
    $employer = ['username' => 'ไม่ทราบชื่อ', 'email' => 'ไม่มีอีเมล', 'phone' => '', 'employer_description' => '', 'created_at' => ''];
}

// ── ดึงรีวิวของนายจ้าง ──
$reviews_query = "SELECT er.*, u.username as reviewer_name 
                  FROM employer_review er 
                  JOIN users u ON er.freelancer_id = u.user_id 
                  WHERE er.employer_id = ? 
                  ORDER BY er.created_at DESC";
$stmt_rev = $conn->prepare($reviews_query);
$stmt_rev->bind_param("i", $job['employer_id']);
$stmt_rev->execute();
$reviews_result = $stmt_rev->get_result();
$employer_reviews = [];
while($rev = $reviews_result->fetch_assoc()){
    $employer_reviews[] = $rev;
}
$stmt_rev->close();

// คำนวณเรตติ้งเฉลี่ย
$avg_rating = 0;
if(count($employer_reviews) > 0){
    $sum = array_sum(array_column($employer_reviews, 'rating'));
    $avg_rating = round($sum / count($employer_reviews), 1);
}

// ✅ เตรียมข้อมูลส่งให้ JavaScript (Map ชื่อคอลัมน์ให้ตรงกับที่ JS เรียกใช้)
$employer_js_data = [
    'username' => $employer['username'] ?? 'ไม่ทราบชื่อ',
    'email'    => $employer['email'] ?? 'ไม่มีอีเมล',
    'phone'    => $employer['phone'] ?? '',
    'company_details' => $employer['employer_description'] ?? '', // ดึงจาก employer_profile
    'joined'   => $employer['created_at'] ?? '',
    'avg_rating' => $avg_rating,
    'total_reviews' => count($employer_reviews)
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Detail - <?php echo htmlspecialchars($job['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --navy: #0f172a; --navy2: #1e293b; --navy3: #334155;
            --accent: #6366f1; --light: #f1f5f9; --white: #ffffff;
            --text: #0f172a; --muted: #64748b; --border: #e2e8f0;
            --green: #10b981; --yellow: #f59e0b; --red: #ef4444;
            --radius: 14px;
        }
        body { font-family: 'Sora', sans-serif; background: var(--light); color: var(--text); }
        
        /* Sidebar */
        .sidebar { width: 240px; min-height: 100vh; background: var(--navy); position: fixed; top: 0; left: 0; z-index: 100; }
        .sidebar-brand { padding: 0 24px 28px; border-bottom: 1px solid var(--navy3); }
        .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-icon { width: 36px; height: 36px; background: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
        .logo-text { font-size: 15px; font-weight: 600; color: #fff; line-height: 1.2; }
        .logo-sub { font-size: 11px; color: var(--navy3); }
        .sidebar-nav { padding: 20px 12px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; color: #94a3b8; text-decoration: none; font-size: 13.5px; font-weight: 500; transition: background .15s, color .15s; }
        .nav-item:hover { background: var(--navy2); color: #e2e8f0; }
        .nav-item.active { background: var(--accent); color: #fff; }
        .sidebar-footer { padding: 16px 12px 0; }
        .nav-logout { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; color: #f87171; text-decoration: none; font-size: 13.5px; font-weight: 500; }
        .nav-logout:hover { background: rgba(239,68,68,.12); }

        .main { margin-left: 240px; padding: 36px 40px; min-height: 100vh; }
        
        /* ✅ ปุ่มย้อนกลับ */
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: var(--white); color: var(--text); border: 1px solid var(--border); border-radius: 10px; text-decoration: none; font-size: 13.5px; font-weight: 500; margin-bottom: 20px; transition: all .15s; }
        .btn-back:hover { background: var(--light); border-color: var(--accent); color: var(--accent); }

        .job-detail { background: var(--white); border-radius: var(--radius); padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        .job-title { font-size: 28px; font-weight: 700; margin-bottom: 10px; color: var(--navy); }
        .job-meta { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .meta-item { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 14px; }
        .meta-item i { font-size: 16px; }
        
        .section { margin-bottom: 25px; }
        .section-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; color: var(--navy); }
        .section-content { line-height: 1.7; color: var(--text); }
        
        .employer-card { background: var(--light); border-radius: var(--radius); padding: 20px; margin-top: 20px; transition: box-shadow .2s; cursor: pointer; }
        .employer-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        .employer-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .employer-avatar { width: 60px; height: 60px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; font-weight: 600; }
        .employer-info h4 { font-size: 18px; font-weight: 600; margin-bottom: 5px; }
        .employer-meta { display: flex; gap: 15px; font-size: 13px; color: var(--muted); }
        
        .btn-apply { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--accent); color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all .15s; text-decoration: none; }
        .btn-apply:hover { background: #4f46e5; transform: translateY(-2px); color: #fff; }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:200; align-items:center; justify-content:center; padding:20px; }
        .modal-overlay.show { display:flex; }

        .review-card { background:var(--light); border-radius:10px; padding:14px; margin-bottom:10px; border-left:3px solid var(--accent); }
        .review-header { display:flex; justify-content:space-between; margin-bottom:6px; }
        .reviewer-name { font-size:13px; font-weight:600; color:var(--text); }
        .review-date { font-size:11px; color:var(--muted); }
        .review-stars { font-size:12px; color:var(--yellow); margin-bottom:6px; }
        .review-comment { font-size:13px; color:var(--text); line-height:1.6; }
        .modal-box { background:var(--white); border-radius:20px; max-width:660px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 24px 64px rgba(0,0,0,.24); }
        .modal-head { background:var(--navy); padding:24px; border-radius:20px 20px 0 0; display:flex; gap:16px; align-items:flex-start; position:relative; }
        .modal-av { width:56px; height:56px; border-radius:50%; background:var(--accent); color:#fff; font-size:20px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:3px solid rgba(255,255,255,.2); }
        .modal-head-info { flex:1; }
        .modal-head-name { font-size:18px; font-weight:600; color:#fff; margin-bottom:4px; }
        .modal-head-meta { display:flex; gap:12px; font-size:12px; color:#94a3b8; flex-wrap:wrap; }
        .modal-close-btn { position:absolute; top:14px; right:14px; background:rgba(255,255,255,.15); border:none; width:30px; height:30px; border-radius:50%; color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px; transition:background .15s; }
        .modal-close-btn:hover { background:rgba(255,255,255,.25); }
        .modal-body { padding:20px 24px; }
        .modal-section { margin-bottom:18px; }
        .modal-section-title { font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
        .modal-section-title::after { content:''; flex:1; height:1px; background:var(--border); }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .info-box { background:var(--light); border-radius:10px; padding:12px; }
        .info-box .lbl { font-size:11px; color:var(--muted); margin-bottom:3px; }
        .info-box .val { font-size:13px; font-weight:500; color:var(--text); line-height: 1.5; }
        
        @media(max-width:768px){
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 20px 16px; }
            .job-meta { flex-direction: column; gap: 10px; }
        }
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
                <div class="logo-sub">Freelancer</div>
            </div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="freelancer_dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
        <a href="browse_jobs.php" class="nav-item active"><i class="bi bi-briefcase"></i> Browse Jobs</a>
        <a href="my_applications.php" class="nav-item"><i class="bi bi-file-earmark-text"></i> My Applications</a>
        <a href="my_profile.php" class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
        <a href="freelancer_reviews.php" class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
        <a href="upload_resume.php" class="nav-item"><i class="bi bi-cloud-upload"></i> Upload Resume</a>
        <div style="height:1px;background:var(--navy3);margin:10px 14px;"></div>
        <a href="support_chat.php" class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
    </div>
</aside>

<!-- Main -->
<main class="main">
    <!-- ✅ ปุ่มย้อนกลับ -->
    <a href="browse_jobs.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> กลับไป Browse Jobs
    </a>

    <div class="job-detail">
        <h1 class="job-title"><?php echo htmlspecialchars($job['title'] ?? 'ไม่ระบุชื่องาน'); ?></h1>
        
        <div class="job-meta">
            <div class="meta-item"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($job['location'] ?? 'ไม่ระบุสถานที่'); ?></div>
            <div class="meta-item"><i class="bi bi-currency-dollar"></i> <?php echo number_format($job['salary'] ?? 0, 2); ?> บาท</div>
            <div class="meta-item"><i class="bi bi-clock"></i> ลงเมื่อ <?php echo date('d M Y', strtotime($job['created_at'])); ?></div>
            <div class="meta-item"><i class="bi bi-calendar-event"></i> Deadline: <?php echo !empty($job['deadline']) ? date('d M Y', strtotime($job['deadline'])) : 'ไม่ระบุ'; ?></div>
            <div class="meta-item"><i class="bi bi-tag"></i> หมวดหมู่: <?php echo htmlspecialchars($job['category'] ?? 'ไม่ระบุ'); ?></div>
            <div class="meta-item"><i class="bi bi-info-circle"></i> สถานะ: <?php echo htmlspecialchars($job['status'] ?? 'open'); ?></div>
        </div>
        
        <div class="section">
            <h2 class="section-title">รายละเอียดงาน</h2>
            <p class="section-content"><?php echo nl2br(htmlspecialchars($job['description'] ?? 'ไม่มีรายละเอียด')); ?></p>
        </div>
        
        <div class="employer-card" onclick="openEmployerModal()">
            <div class="employer-header">
                <div class="employer-avatar"><?php echo strtoupper(substr($employer['username'] ?? 'EM', 0, 2)); ?></div>
                <div class="employer-info">
                    <h4><?php echo htmlspecialchars($employer['username'] ?? 'ไม่ทราบชื่อนายจ้าง'); ?> <i class="bi bi-box-arrow-up-right" style="font-size:14px;color:var(--accent);margin-left:6px;"></i></h4>
                    <div class="employer-meta">
                        <span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($employer['email'] ?? 'ไม่มีอีเมล'); ?></span>
                        <span><i class="bi bi-star-fill" style="color:var(--yellow);"></i> <?php echo $avg_rating > 0 ? $avg_rating : 'ยังไม่มีรีวิว'; ?> (<?php echo count($employer_reviews); ?> รีวิว)</span>
                    </div>
                </div>
            </div>
            <p style="font-size:12px;color:var(--muted);margin-top:8px;">👆 กดเพื่อดูโปรไฟล์และรีวิวทั้งหมด</p>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="apply_job.php?job_id=<?php echo $job_id; ?>" class="btn-apply">
                <i class="bi bi-send"></i> สมัครงานนี้
            </a>
        </div>
    </div>
</main>

<!-- Employer Profile Modal -->
<div class="modal-overlay" id="emp-modal">
    <div class="modal-box">
        <div class="modal-head">
            <button class="modal-close-btn" onclick="closeEmployerModal()"><i class="bi bi-x-lg"></i></button>
            <div class="modal-av" id="emp-m-av"></div>
            <div class="modal-head-info">
                <div class="modal-head-name" id="emp-m-name"></div>
                <div class="modal-head-meta">
                    <span id="emp-m-rating"></span>
                    <span id="emp-m-joined"></span>
                </div>
            </div>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <div class="modal-section-title"><i class="bi bi-building"></i> ข้อมูลนายจ้าง</div>
                <!-- ✅ แก้ไข Grid แสดงข้อมูลบริษัท -->
                <div class="info-grid">
                    <div class="info-box" style="grid-column: 1/-1;">
                        <div class="lbl">รายละเอียดบริษัท</div>
                        <div class="val" id="emp-m-company"></div>
                    </div>
                    <div class="info-box">
                        <div class="lbl">Email</div>
                        <div class="val" id="emp-m-email"></div>
                    </div>
                    <div class="info-box">
                        <div class="lbl">เบอร์โทรศัพท์</div>
                        <div class="val" id="emp-m-phone"></div>
                    </div>
                </div>
            </div>

            <div class="modal-section">
                <div class="modal-section-title"><i class="bi bi-star"></i> รีวิวจาก Freelancer</div>
                <div id="emp-reviews-list" style="max-height:300px;overflow-y:auto;padding-right:4px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ข้อมูลจาก PHP
const empData = <?php echo json_encode($employer_js_data, JSON_UNESCAPED_UNICODE); ?>;
const empReviews = <?php echo json_encode($employer_reviews, JSON_UNESCAPED_UNICODE); ?>;

function openEmployerModal() {
    document.getElementById('emp-m-av').textContent = empData.username.substring(0,2).toUpperCase();
    document.getElementById('emp-m-name').textContent = empData.username;
    
    // ✅ แสดงข้อมูลบริษัท, Email, เบอร์โทร
    document.getElementById('emp-m-company').textContent = empData.company_details || 'ไม่ระบุรายละเอียดบริษัท';
    document.getElementById('emp-m-email').textContent = empData.email;
    document.getElementById('emp-m-phone').textContent = empData.phone || 'ไม่ระบุเบอร์โทรศัพท์';
    
    document.getElementById('emp-m-rating').textContent = empData.avg_rating > 0 ? `⭐ ${empData.avg_rating} (${empData.total_reviews} รีวิว)` : '⭐ ยังไม่มีรีวิว';
    document.getElementById('emp-m-joined').textContent = empData.joined ? `เข้าร่วมเมื่อ ${new Date(empData.joined).toLocaleDateString('th-TH')}` : '';

    const list = document.getElementById('emp-reviews-list');
    list.innerHTML = '';
    if (empReviews.length === 0) {
        list.innerHTML = '<p style="color:var(--muted);text-align:center;padding:20px;font-size:13px;">ยังไม่มีรีวิวจาก Freelancer</p>';
    } else {
        empReviews.forEach(rev => {
            const stars = '⭐'.repeat(Math.round(rev.rating));
            const date = rev.created_at ? new Date(rev.created_at).toLocaleDateString('th-TH') : 'ไม่ระบุวันที่';
            const comment = rev.comment ? rev.comment : 'ไม่มีความคิดเห็นเพิ่มเติม';
            list.innerHTML += `
                <div class="review-card">
                    <div class="review-header">
                        <span class="reviewer-name">${rev.reviewer_name}</span>
                        <span class="review-date">${date}</span>
                    </div>
                    <div class="review-stars">${stars} ${rev.rating}/5</div>
                    <div class="review-comment">${comment}</div>
                </div>
            `;
        });
    }
    document.getElementById('emp-modal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeEmployerModal() {
    document.getElementById('emp-modal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('emp-modal').addEventListener('click', e => {
    if (e.target.id === 'emp-modal') closeEmployerModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeEmployerModal();
});
</script>

</body>
</html>