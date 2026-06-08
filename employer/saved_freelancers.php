<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../helpers/profile_image_helpers.php";
require_once __DIR__ . "/../helpers/employer_sidebar_helpers.php";
require_once __DIR__ . "/../helpers/review_schema.php";

ensure_profile_image_schema($conn);
ensure_freelancer_review_schema($conn);

$employer_id = jobfind_require_role('employer');
$sidebar_pending_apps = get_employer_pending_application_count($conn, $employer_id);

$query = "
    SELECT sf.id AS saved_id, sf.saved_at,
           u.user_id AS freelancer_id, u.username, u.fullname, u.email, u.phone, u.profile_image,
           fp.skill, fp.experience, fp.location, fp.rating,
           (SELECT file_name FROM Resume WHERE freelancer_id = u.user_id ORDER BY resume_id DESC LIMIT 1) AS resume_file,
           (SELECT COUNT(*) FROM Freelancer_Review fr WHERE fr.freelancer_id = u.user_id) AS review_count,
           (SELECT ROUND(AVG(fr.rating), 1) FROM Freelancer_Review fr WHERE fr.freelancer_id = u.user_id) AS review_rating
    FROM Saved_Freelancers sf
    JOIN Users u ON sf.freelancer_id = u.user_id
    LEFT JOIN Freelancer_Profile fp ON u.user_id = fp.user_id
    WHERE sf.employer_id = ?
    ORDER BY sf.saved_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$saved_result = $stmt->get_result();
$saved_freelancers = [];
while($row = $saved_result->fetch_assoc()){
    $saved_freelancers[] = $row;
}
$stmt->close();

function e($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function skill_list($skills){
    if(empty($skills)) return [];
    return array_values(array_filter(array_map('trim', explode(',', $skills))));
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=13">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Saved Freelancers - Job_Find</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');

  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --navy:#0f172a; --navy2:#1e293b; --navy3:#334155;
    --accent:#6366f1; --light:#f1f5f9; --white:#ffffff;
    --text:#0f172a; --muted:#64748b; --border:#e2e8f0;
    --green:#10b981; --red:#ef4444; --yellow:#f59e0b; --radius:14px;
  }

  body{
    font-family:'Sora',sans-serif;
    background:var(--light);
    color:var(--text);
    display:flex;
    line-height:1.5;
    min-height:100vh;
  }

  .sidebar{width:240px;min-height:100vh;background:var(--navy);display:flex;flex-direction:column;padding:28px 0;position:fixed;top:0;left:0;z-index:100;}
  .sidebar-brand{padding:0 24px 28px;border-bottom:1px solid var(--navy3);}
  .logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
  .logo-icon{width:36px;height:36px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;}
  .logo-text{font-size:15px;font-weight:600;color:#fff;line-height:1.2;}
  .logo-sub{font-size:11px;color:var(--navy3);}
  .sidebar-nav{padding:20px 12px;flex:1;display:flex;flex-direction:column;gap:4px;}
  .nav-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:13.5px;font-weight:500;transition:background .15s,color .15s;position:relative;}
  .nav-item:hover{background:var(--navy2);color:#e2e8f0;}
  .nav-item.active{background:var(--accent);color:#fff;}
  .nav-item i{font-size:17px;width:20px;text-align:center;}
  .nav-divider{height:1px;background:var(--navy3);margin:10px 14px;}
  .sidebar-footer{padding:16px 12px 0;}
  .nav-logout{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#f87171;text-decoration:none;font-size:13.5px;font-weight:500;transition:background .15s;}
  .nav-logout:hover{background:rgba(239,68,68,.12);}
  .nav-logout i{font-size:17px;}

  .main{margin-left:240px;min-height:100vh;padding:36px 40px;}
  .topbar{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px;}
  .topbar h2{font-size:24px;font-weight:700;margin-bottom:4px;}
  .topbar p{font-size:13px;color:var(--muted);}
  .count-pill{display:inline-flex;align-items:center;gap:8px;background:var(--white);border:1px solid var(--border);border-radius:12px;padding:10px 14px;font-size:13px;font-weight:600;color:var(--text);}
  .count-pill i{color:var(--accent);}

  .saved-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px;}
  .freelancer-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:0 4px 12px rgba(15,23,42,.05);transition:transform .15s,box-shadow .15s,border-color .15s;}
  .freelancer-card:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(15,23,42,.08);border-color:#c7d2fe;}
  .freelancer-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:16px;}
  .person{display:flex;gap:12px;align-items:center;min-width:0;}
  .avatar{width:52px;height:52px;border-radius:14px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0;overflow:hidden;}
  .avatar img{width:100%;height:100%;object-fit:cover;display:block;}
  .name-wrap{min-width:0;}
  .name{font-size:16px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .fullname{font-size:12px;color:var(--muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .unsave-btn{border:1px solid #fecaca;background:#fee2e2;color:var(--red);width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s,color .15s,border-color .15s;flex-shrink:0;}
  .unsave-btn:hover{background:var(--red);border-color:var(--red);color:#fff;}

  .meta-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
  .meta-box{background:var(--light);border:1px solid var(--border);border-radius:12px;padding:12px;min-width:0;}
  .meta-label{font-size:11px;color:var(--muted);margin-bottom:4px;}
  .meta-value{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .meta-value i{color:var(--accent);margin-right:5px;}

  .skills{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;}
  .skill{background:#eef2ff;color:var(--accent);border-radius:20px;padding:5px 10px;font-size:12px;font-weight:600;}
  .detail-list{display:grid;gap:8px;margin:14px 0;}
  .detail{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px;}
  .detail i{color:var(--accent);width:18px;text-align:center;}
  .detail strong{color:var(--text);font-weight:600;}
  .resume-link{display:inline-flex;align-items:center;gap:8px;background:#e0f2fe;color:#0369a1;text-decoration:none;border-radius:10px;padding:9px 12px;font-size:13px;font-weight:600;margin-top:4px;}
  .resume-link:hover{background:#bae6fd;color:#075985;}
  .saved-date{border-top:1px solid var(--border);margin-top:16px;padding-top:12px;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:7px;}

  .empty-state{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);min-height:430px;display:grid;place-items:center;padding:44px 24px;color:var(--muted);box-shadow:0 4px 14px rgba(15,23,42,.04);}
  .empty-panel{width:100%;max-width:620px;text-align:center;}
  .empty-visual{width:210px;height:132px;margin:0 auto 24px;position:relative;}
  .empty-badge{position:absolute;left:50%;top:8px;transform:translateX(-50%);width:82px;height:82px;border-radius:22px;background:#eef2ff;border:1px solid #c7d2fe;display:flex;align-items:center;justify-content:center;box-shadow:0 12px 24px rgba(99,102,241,.12);}
  .empty-badge i{font-size:38px;color:var(--accent);}
  .mini-card{position:absolute;display:flex;align-items:center;gap:7px;background:#fff;border:1px solid var(--border);border-radius:12px;padding:9px 11px;font-size:12px;font-weight:700;color:var(--text);box-shadow:0 8px 20px rgba(15,23,42,.08);}
  .mini-card i{font-size:14px;margin:0;}
  .mini-card.left{left:0;bottom:12px;}
  .mini-card.right{right:0;bottom:18px;}
  .mini-card.left i{color:var(--green);}
  .mini-card.right i{color:var(--yellow);}
  .empty-kicker{display:inline-flex;align-items:center;gap:7px;background:#f8fafc;border:1px solid var(--border);border-radius:999px;padding:6px 11px;color:var(--muted);font-size:12px;font-weight:700;margin-bottom:14px;}
  .empty-kicker i{font-size:13px;color:var(--accent);margin:0;}
  .empty-state h3{font-size:22px;color:var(--text);margin-bottom:8px;line-height:1.35;}
  .empty-state p{font-size:14px;line-height:1.7;margin:0 auto 22px;max-width:500px;}
  .empty-actions{display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;}
  .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:10px;background:var(--accent);color:#fff;text-decoration:none;font-size:13.5px;font-weight:600;box-shadow:0 8px 18px rgba(99,102,241,.22);}
  .btn-primary:hover{background:#4f46e5;color:#fff;}
  .btn-secondary{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;background:#fff;color:var(--text);border:1px solid var(--border);text-decoration:none;font-size:13.5px;font-weight:600;}
  .btn-secondary:hover{border-color:#c7d2fe;color:var(--accent);}
  .toast{position:fixed;right:24px;bottom:24px;background:var(--navy);color:#fff;border-radius:12px;padding:12px 16px;font-size:13px;font-weight:600;box-shadow:0 10px 24px rgba(15,23,42,.22);opacity:0;transform:translateY(10px);pointer-events:none;transition:opacity .2s,transform .2s;z-index:300;}
  .toast.show{opacity:1;transform:translateY(0);}

  @media(max-width:768px){
    .sidebar{display:none;}
    .main{margin-left:0;padding:20px 16px;}
    .topbar{flex-direction:column;}
    .saved-grid{grid-template-columns:1fr;}
    .meta-row{grid-template-columns:1fr;}
    .empty-state{min-height:380px;padding:34px 16px;}
    .empty-visual{width:190px;}
  }
</style>
<link rel="stylesheet" href="../assets/css/freelancehub-theme.css">

</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="../index.php" class="logo">
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;margin:0 auto!important;align-self:center!important;"><img class="brand-logo-img" src="../assets/images/jobfind-logo.png?v=13" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Job_Find</div>
        <div class="logo-sub" style="display:none!important;">Employer</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php" class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="manage_jobs.php" class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs<?php render_employer_manage_jobs_badge($sidebar_pending_apps); ?></a>
    <a href="saved_freelancers.php" class="nav-item active"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="reviews.php" class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="company_review.php" class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
    <a href="profile.php" class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="../support/messages.php" class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div>
      <h2>Freelancer ที่บันทึกไว้</h2>
      <p>เก็บรายชื่อผู้สมัครที่น่าสนใจไว้กลับมาดูและติดต่อภายหลัง</p>
    </div>
    <div class="count-pill">
      <i class="bi bi-bookmark-check"></i>
      <?php echo count($saved_freelancers); ?> saved
    </div>
  </div>

  <?php if(count($saved_freelancers) > 0): ?>
    <div class="saved-grid" id="saved-grid">
      <?php foreach($saved_freelancers as $freelancer): ?>
        <?php
          $skills = skill_list($freelancer['skill'] ?? '');
          $display_name = $freelancer['fullname'] ?: $freelancer['username'];
          $initials = profile_initials($freelancer['username'] ?: 'FL');
          $rating = $freelancer['review_rating'] ?: ($freelancer['rating'] ?: 0);
          $profile_img = trim($freelancer['profile_image'] ?? '');
        ?>
        <article class="freelancer-card" data-card="<?php echo (int)$freelancer['freelancer_id']; ?>">
          <div class="freelancer-head">
            <div class="person">
              <div class="avatar">
                <?php if($profile_img !== ''): ?>
                  <img src="<?php echo profile_image_src($profile_img); ?>" alt="Profile image">
                <?php else: ?>
                  <?php echo e($initials); ?>
                <?php endif; ?>
              </div>
              <div class="name-wrap">
                <div class="name"><?php echo e($display_name); ?></div>
                <div class="fullname">@<?php echo e($freelancer['username']); ?></div>
              </div>
            </div>
            <button class="unsave-btn" type="button" onclick="unsaveFreelancer(<?php echo (int)$freelancer['freelancer_id']; ?>)" title="ยกเลิกบันทึก">
              <i class="bi bi-bookmark-x"></i>
            </button>
          </div>

          <div class="meta-row">
            <div class="meta-box">
              <div class="meta-label">Email</div>
              <div class="meta-value"><?php echo e($freelancer['email'] ?: '-'); ?></div>
            </div>
            <div class="meta-box">
              <div class="meta-label">Phone</div>
              <div class="meta-value"><?php echo e($freelancer['phone'] ?: '-'); ?></div>
            </div>
          </div>

          <?php if(count($skills) > 0): ?>
            <div class="skills">
              <?php foreach($skills as $skill): ?>
                <span class="skill"><?php echo e($skill); ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="detail-list">
            <div class="detail">
              <i class="bi bi-geo-alt"></i>
              <span><?php echo e($freelancer['location'] ?: 'ไม่ระบุสถานที่'); ?></span>
            </div>
            <div class="detail">
              <i class="bi bi-briefcase"></i>
              <span>ประสบการณ์: <strong><?php echo e($freelancer['experience'] ?: '-'); ?></strong></span>
            </div>
            <div class="detail">
              <i class="bi bi-star-fill"></i>
              <span>Rating: <strong><?php echo $rating > 0 ? e(number_format((float)$rating, 1)) : 'ยังไม่มีรีวิว'; ?></strong></span>
            </div>
          </div>

          <?php if(!empty($freelancer['resume_file'])): ?>
            <a class="resume-link" href="<?php echo e(jobfind_url('uploads/' . $freelancer['resume_file'])); ?>" target="_blank">
              <i class="bi bi-file-earmark-pdf"></i> ดู Resume
            </a>
          <?php endif; ?>

          <div class="saved-date">
            <i class="bi bi-clock"></i>
            บันทึกเมื่อ <?php echo date('d M Y, H:i', strtotime($freelancer['saved_at'])); ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-panel">
        <div class="empty-visual" aria-hidden="true">
          <div class="empty-badge"><i class="bi bi-bookmark-check"></i></div>
          <div class="mini-card left"><i class="bi bi-check-circle-fill"></i> Shortlist</div>
          <div class="mini-card right"><i class="bi bi-star-fill"></i> Talent</div>
        </div>
        <div class="empty-kicker"><i class="bi bi-folder2-open"></i> Saved list is empty</div>
        <h3>ยังไม่มี Freelancer ที่บันทึกไว้</h3>
        <p>เลือกผู้สมัครที่น่าสนใจจากหน้างาน แล้วกดบันทึกเพื่อเก็บไว้กลับมาดูและติดต่อภายหลัง</p>
        <div class="empty-actions">
          <a href="manage_jobs.php" class="btn-primary">
            <i class="bi bi-briefcase"></i> ไปดูผู้สมัครงาน
          </a>
          <a href="post_job.php" class="btn-secondary">
            <i class="bi bi-plus-circle"></i> โพสต์งานใหม่
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<div class="toast" id="toast"></div>

<script>
function showToast(message){
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 1800);
}

function unsaveFreelancer(freelancerId){
  if(!confirm('ต้องการยกเลิกการบันทึก Freelancer คนนี้ใช่ไหม?')) return;

  fetch('../actions/save_freelancer.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `freelancer_id=${encodeURIComponent(freelancerId)}&action=unsave`
  })
  .then(response => response.json())
  .then(data => {
    if(!data.success){
      showToast(data.message || 'ไม่สามารถยกเลิกการบันทึกได้');
      return;
    }

    const card = document.querySelector(`[data-card="${freelancerId}"]`);
    if(card) card.remove();
    showToast('ยกเลิกการบันทึกแล้ว');

    if(!document.querySelector('.freelancer-card')){
      setTimeout(() => location.reload(), 600);
    }
  })
  .catch(() => showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
}
</script>
</body>
</html>
