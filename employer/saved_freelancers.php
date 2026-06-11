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
           (SELECT COUNT(*) FROM Freelancer_Review fr WHERE fr.freelancer_id = u.user_id AND fr.rating IS NOT NULL) AS review_count,
           (SELECT ROUND(AVG(fr.rating), 1) FROM Freelancer_Review fr WHERE fr.freelancer_id = u.user_id AND fr.rating IS NOT NULL) AS review_rating
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

function get_saved_freelancer_review_history($conn, $freelancer_ids){
    $ids = array_values(array_unique(array_map('intval', $freelancer_ids)));
    $ids = array_values(array_filter($ids, fn($id) => $id > 0));
    if(empty($ids)){
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = "
        SELECT fr.freelancer_id, fr.rating,
               COALESCE(NULLIF(fr.comment, ''), NULLIF(fr.review, '')) AS comment,
               fr.created_at,
               COALESCE(NULLIF(ep.employer_name, ''), NULLIF(u.fullname, ''), u.username) AS employer_name,
               COALESCE(NULLIF(j.title, ''), 'ไม่ระบุงาน') AS job_title
        FROM Freelancer_Review fr
        LEFT JOIN Users u ON u.user_id = fr.employer_id
        LEFT JOIN Employer_Profile ep ON ep.user_id = fr.employer_id
        LEFT JOIN Job j ON j.job_id = fr.job_id
        WHERE fr.freelancer_id IN ($placeholders)
          AND fr.rating IS NOT NULL
        ORDER BY fr.freelancer_id ASC, fr.created_at DESC, fr.review_id DESC
    ";

    $stmt = $conn->prepare($query);
    if(!$stmt){
        return [];
    }

    $types = str_repeat('i', count($ids));
    $bind_params = [$types];
    foreach($ids as $idx => $id){
        $bind_params[] = &$ids[$idx];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
    $stmt->execute();
    $result = $stmt->get_result();

    $reviews = [];
    while($row = $result->fetch_assoc()){
        $fid = (int)$row['freelancer_id'];
        $reviews[$fid][] = [
            'rating' => (int)($row['rating'] ?? 0),
            'comment' => $row['comment'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'employer_name' => $row['employer_name'] ?? 'Employer',
            'job_title' => $row['job_title'] ?? 'ไม่ระบุงาน'
        ];
    }
    $stmt->close();

    return $reviews;
}

$review_history_by_freelancer = get_saved_freelancer_review_history($conn, array_column($saved_freelancers, 'freelancer_id'));
foreach($saved_freelancers as &$saved_freelancer){
    $fid = (int)($saved_freelancer['freelancer_id'] ?? 0);
    $history = $review_history_by_freelancer[$fid] ?? [];
    $saved_freelancer['review_history'] = $history;
    $saved_freelancer['review_count'] = (int)($saved_freelancer['review_count'] ?? count($history));
    $saved_freelancer['review_rating'] = $saved_freelancer['review_rating'] !== null
        ? round((float)$saved_freelancer['review_rating'], 1)
        : 0;
}
unset($saved_freelancer);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=14">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Saved Freelancers - Freelance Matching Online</title>
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
  .card-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px;}
  .review-btn{display:inline-flex;align-items:center;gap:8px;background:#fffbeb;color:#92400e;border:1px solid #fde68a;border-radius:10px;padding:9px 12px;font-family:'Sora',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s,transform .1s;}
  .review-btn:hover{background:#fef3c7;border-color:#fbbf24;transform:translateY(-1px);}
  .resume-link{display:inline-flex;align-items:center;gap:8px;background:#e0f2fe;color:#0369a1;text-decoration:none;border-radius:10px;padding:9px 12px;font-size:13px;font-weight:600;margin-top:0;}
  .resume-link:hover{background:#bae6fd;color:#075985;}
  .saved-date{border-top:1px solid var(--border);margin-top:16px;padding-top:12px;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:7px;}

  .modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.58);display:none;align-items:center;justify-content:center;padding:24px;z-index:240;}
  .modal-overlay.show{display:flex;}
  .review-modal{width:min(720px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 24px 60px rgba(15,23,42,.28);}
  .review-modal-head{position:relative;background:var(--navy);color:#fff;padding:22px 24px;display:flex;gap:14px;align-items:center;}
  .modal-avatar{width:58px;height:58px;border-radius:14px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:21px;font-weight:700;flex-shrink:0;overflow:hidden;border:1px solid rgba(255,255,255,.18);}
  .modal-avatar img{width:100%;height:100%;object-fit:cover;display:block;}
  .modal-title-wrap{min-width:0;padding-right:34px;}
  .modal-name{font-size:19px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .modal-handle{font-size:12.5px;color:#cbd5e1;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .modal-rating-pill{display:inline-flex;align-items:center;gap:7px;margin-top:9px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:999px;padding:6px 10px;font-size:12.5px;font-weight:600;color:#fff;}
  .modal-rating-pill i{color:var(--yellow);}
  .modal-close{position:absolute;top:14px;right:14px;width:34px;height:34px;border:0;border-radius:10px;background:rgba(255,255,255,.12);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;}
  .modal-close:hover{background:rgba(255,255,255,.2);}
  .review-modal-body{padding:22px 24px 24px;}
  .review-summary{display:grid;grid-template-columns:140px 1fr;gap:12px;margin-bottom:14px;}
  .review-score-card{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:16px;text-align:center;}
  .review-score-num{font-size:34px;font-weight:700;line-height:1;color:#92400e;}
  .review-score-stars{color:var(--yellow);font-size:15px;margin:7px 0 4px;display:flex;justify-content:center;gap:2px;}
  .review-score-count{font-size:12px;color:#92400e;}
  .review-distribution{background:var(--light);border:1px solid var(--border);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:7px;}
  .review-dist-row{display:grid;grid-template-columns:36px 1fr 26px;align-items:center;gap:8px;font-size:12px;color:var(--muted);}
  .review-dist-label{display:flex;align-items:center;gap:3px;white-space:nowrap;}
  .review-dist-label i{color:var(--yellow);}
  .review-dist-bar{height:7px;background:#e2e8f0;border-radius:999px;overflow:hidden;}
  .review-dist-fill{height:100%;background:var(--yellow);border-radius:999px;}
  .review-list{display:flex;flex-direction:column;gap:10px;max-height:310px;overflow-y:auto;padding-right:2px;}
  .review-item{background:var(--light);border:1px solid var(--border);border-radius:12px;padding:13px;}
  .review-item-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px;}
  .review-employer{font-size:13.5px;font-weight:700;color:var(--text);}
  .review-job{font-size:12px;color:var(--muted);margin-top:2px;display:flex;align-items:center;gap:5px;}
  .review-item-stars{color:var(--yellow);display:flex;gap:1px;font-size:13px;white-space:nowrap;}
  .review-comment{font-size:13px;line-height:1.6;color:var(--text);margin:8px 0;}
  .review-date{font-size:11.5px;color:var(--muted);display:flex;align-items:center;gap:5px;}
  .review-empty{background:var(--light);border:1px dashed #cbd5e1;border-radius:12px;padding:18px;color:var(--muted);font-size:13px;text-align:center;}

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
    .review-summary{grid-template-columns:1fr;}
    .review-modal-head{padding:20px 18px;}
    .review-modal-body{padding:18px;}
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
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;margin:0 auto!important;align-self:center!important;"><img class="brand-logo-img" src="../assets/images/jobfind-logo.png?v=14" alt="Freelance Matching Online logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Freelance Matching Online</div>
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
          $review_count = (int)($freelancer['review_count'] ?? 0);
          $rating = $freelancer['review_rating'] ?: ($freelancer['rating'] ?: 0);
          $profile_img = trim($freelancer['profile_image'] ?? '');
          $modal_row = $freelancer;
          $modal_row['display_name'] = $display_name;
          $modal_row['initials'] = $initials;
          $modal_row['profile_image_url'] = $profile_img !== '' ? jobfind_url($profile_img) : '';
          $data_json = htmlspecialchars(
              json_encode($modal_row, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG),
              ENT_QUOTES,
              'UTF-8'
          );
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
              <span>Rating: <strong><?php echo $rating > 0 ? e(number_format((float)$rating, 1)) . ($review_count > 0 ? ' (' . $review_count . ' รีวิว)' : '') : 'ยังไม่มีรีวิว'; ?></strong></span>
            </div>
          </div>

          <div class="card-actions">
            <button class="review-btn" type="button" onclick='openReviewModal(<?php echo $data_json; ?>)'>
              <i class="bi bi-star"></i>
              ดูรีวิว<?php echo $review_count > 0 ? ' (' . $review_count . ')' : ''; ?>
            </button>
            <?php if(!empty($freelancer['resume_file'])): ?>
              <a class="resume-link" href="<?php echo e(jobfind_url('uploads/' . $freelancer['resume_file'])); ?>" target="_blank">
                <i class="bi bi-file-earmark-pdf"></i> ดู Resume
              </a>
            <?php endif; ?>
          </div>

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

<div class="modal-overlay" id="review-modal" onclick="closeReviewModal()">
  <div class="review-modal" role="dialog" aria-modal="true" aria-labelledby="review-modal-name" onclick="event.stopPropagation()">
    <div class="review-modal-head">
      <button class="modal-close" type="button" onclick="closeReviewModal()" aria-label="ปิดหน้าต่างรีวิว">
        <i class="bi bi-x-lg"></i>
      </button>
      <div class="modal-avatar" id="review-modal-avatar"></div>
      <div class="modal-title-wrap">
        <div class="modal-name" id="review-modal-name"></div>
        <div class="modal-handle" id="review-modal-handle"></div>
        <div class="modal-rating-pill" id="review-modal-rating">
          <i class="bi bi-star-fill"></i>
          <span>ยังไม่มีรีวิว</span>
        </div>
      </div>
    </div>
    <div class="review-modal-body">
      <div class="review-summary">
        <div class="review-score-card">
          <div class="review-score-num" id="review-score">-</div>
          <div class="review-score-stars" id="review-stars"></div>
          <div class="review-score-count" id="review-count">ยังไม่มีรีวิว</div>
        </div>
        <div class="review-distribution" id="review-dist"></div>
      </div>
      <div class="review-list" id="review-list"></div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function openReviewModal(freelancer){
  const modal = document.getElementById('review-modal');
  const avatar = document.getElementById('review-modal-avatar');
  const displayName = freelancer.display_name || freelancer.fullname || freelancer.username || 'Freelancer';
  const username = freelancer.username || '';
  const initials = freelancer.initials || (displayName.substring(0, 1).toUpperCase() || 'F');
  const reviewRating = Number(freelancer.review_rating || 0);
  const profileRating = Number(freelancer.rating || 0);
  const reviewCount = parseInt(freelancer.review_count || 0, 10);
  const displayRating = reviewRating > 0 ? reviewRating : profileRating;

  avatar.innerHTML = '';
  if(freelancer.profile_image_url){
    const img = document.createElement('img');
    img.src = freelancer.profile_image_url;
    img.alt = 'Profile image';
    img.onerror = () => {
      avatar.innerHTML = '';
      avatar.textContent = initials;
    };
    avatar.appendChild(img);
  } else {
    avatar.textContent = initials;
  }

  document.getElementById('review-modal-name').textContent = displayName;
  document.getElementById('review-modal-handle').textContent = username ? `@${username}` : '';

  const ratingPill = document.getElementById('review-modal-rating');
  ratingPill.querySelector('span').textContent = displayRating > 0
    ? `${displayRating.toFixed(1)}${reviewCount > 0 ? ` จาก ${reviewCount} รีวิว` : ' เรทจากโปรไฟล์'}`
    : 'ยังไม่มีรีวิว';

  renderReviewHistory(freelancer, displayRating);
  modal.classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeReviewModal(){
  const modal = document.getElementById('review-modal');
  modal.classList.remove('show');
  document.body.style.overflow = '';
}

function appendStars(container, rating){
  container.innerHTML = '';
  const value = Number(rating || 0);
  const full = Math.floor(value);
  const hasHalf = value - full >= 0.5;
  for(let i = 1; i <= 5; i++){
    const icon = document.createElement('i');
    if(i <= full){
      icon.className = 'bi bi-star-fill';
    } else if(i === full + 1 && hasHalf){
      icon.className = 'bi bi-star-half';
    } else {
      icon.className = 'bi bi-star';
    }
    container.appendChild(icon);
  }
}

function formatReviewDate(value){
  if(!value) return '';
  const parsed = new Date(String(value).replace(' ', 'T'));
  if(Number.isNaN(parsed.getTime())){
    return value;
  }
  return parsed.toLocaleDateString('th-TH', {day:'2-digit', month:'short', year:'numeric'});
}

function renderReviewHistory(freelancer, displayRating){
  const reviews = Array.isArray(freelancer.review_history) ? freelancer.review_history : [];
  const total = parseInt(freelancer.review_count || reviews.length || 0, 10);
  const reviewRating = Number(freelancer.review_rating || 0);
  const profileRating = Number(freelancer.rating || 0);
  const score = reviewRating > 0 ? reviewRating : (displayRating || profileRating || 0);
  const scoreEl = document.getElementById('review-score');
  const starsEl = document.getElementById('review-stars');
  const countEl = document.getElementById('review-count');
  const distEl = document.getElementById('review-dist');
  const listEl = document.getElementById('review-list');

  scoreEl.textContent = score > 0 ? score.toFixed(1) : '-';
  countEl.textContent = total > 0 ? `${total} รีวิว` : (profileRating > 0 ? 'เรทจากโปรไฟล์' : 'ยังไม่มีรีวิว');
  appendStars(starsEl, score);

  const counts = {1:0, 2:0, 3:0, 4:0, 5:0};
  reviews.forEach(review => {
    const rating = parseInt(review.rating || 0, 10);
    if(counts[rating] !== undefined){
      counts[rating]++;
    }
  });

  distEl.innerHTML = '';
  [5, 4, 3, 2, 1].forEach(star => {
    const row = document.createElement('div');
    row.className = 'review-dist-row';

    const label = document.createElement('div');
    label.className = 'review-dist-label';
    const icon = document.createElement('i');
    icon.className = 'bi bi-star-fill';
    label.appendChild(icon);
    label.appendChild(document.createTextNode(` ${star}`));

    const bar = document.createElement('div');
    bar.className = 'review-dist-bar';
    const fill = document.createElement('div');
    fill.className = 'review-dist-fill';
    fill.style.width = total > 0 ? `${Math.round((counts[star] / total) * 100)}%` : '0%';
    bar.appendChild(fill);

    const count = document.createElement('div');
    count.textContent = counts[star];

    row.appendChild(label);
    row.appendChild(bar);
    row.appendChild(count);
    distEl.appendChild(row);
  });

  listEl.innerHTML = '';
  if(reviews.length === 0){
    const empty = document.createElement('div');
    empty.className = 'review-empty';
    empty.textContent = 'ยังไม่มีประวัติรีวิวจากผู้ว่าจ้าง';
    listEl.appendChild(empty);
    return;
  }

  reviews.forEach(review => {
    const item = document.createElement('div');
    item.className = 'review-item';

    const head = document.createElement('div');
    head.className = 'review-item-head';

    const reviewer = document.createElement('div');
    const employer = document.createElement('div');
    employer.className = 'review-employer';
    employer.textContent = review.employer_name || 'Employer';

    const job = document.createElement('div');
    job.className = 'review-job';
    const jobIcon = document.createElement('i');
    jobIcon.className = 'bi bi-briefcase';
    job.appendChild(jobIcon);
    job.appendChild(document.createTextNode(review.job_title || 'ไม่ระบุงาน'));

    reviewer.appendChild(employer);
    reviewer.appendChild(job);

    const stars = document.createElement('div');
    stars.className = 'review-item-stars';
    appendStars(stars, parseInt(review.rating || 0, 10));

    head.appendChild(reviewer);
    head.appendChild(stars);
    item.appendChild(head);

    const comment = document.createElement('div');
    comment.className = 'review-comment';
    const commentText = (review.comment || '').trim();
    comment.textContent = commentText !== '' ? commentText : 'ไม่มีความคิดเห็นเพิ่มเติม';
    item.appendChild(comment);

    const dateText = formatReviewDate(review.created_at);
    if(dateText){
      const date = document.createElement('div');
      date.className = 'review-date';
      const dateIcon = document.createElement('i');
      dateIcon.className = 'bi bi-clock';
      date.appendChild(dateIcon);
      date.appendChild(document.createTextNode(dateText));
      item.appendChild(date);
    }

    listEl.appendChild(item);
  });
}

document.addEventListener('keydown', function(event){
  if(event.key === 'Escape'){
    closeReviewModal();
  }
});

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
