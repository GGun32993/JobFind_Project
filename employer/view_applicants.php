<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../helpers/profile_image_helpers.php";
require_once __DIR__ . "/../helpers/employer_sidebar_helpers.php";
require_once __DIR__ . "/../helpers/review_schema.php";

ensure_profile_image_schema($conn);
ensure_freelancer_review_schema($conn);

$job_id      = $_GET['job_id'] ?? 0;
$employer_id = jobfind_require_role('employer');
$sidebar_pending_apps = get_employer_pending_application_count($conn, $employer_id);

$job_query = "SELECT * FROM Job WHERE job_id = ? AND employer_id = ?";
$stmt = $conn->prepare($job_query);
$stmt->bind_param("ii", $job_id, $employer_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$job){ header("Location: manage_jobs.php"); exit(); }

$applicants_query = "
    SELECT ja.*, u.username, u.email, u.phone, u.fullname, u.profile_image,
           fp.skill, fp.experience, fp.rating, fp.location,
           COALESCE(fr_stats.review_count, 0) AS review_count,
           fr_stats.review_rating AS review_rating,
           (SELECT file_name FROM Resume WHERE freelancer_id=ja.freelancer_id ORDER BY resume_id DESC LIMIT 1) AS resume_file
    FROM Job_Application ja
    JOIN Users u ON ja.freelancer_id = u.user_id
    LEFT JOIN Freelancer_Profile fp ON u.user_id = fp.user_id
    LEFT JOIN (
        SELECT freelancer_id, COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS review_rating
        FROM Freelancer_Review
        WHERE rating IS NOT NULL
        GROUP BY freelancer_id
    ) fr_stats ON fr_stats.freelancer_id = u.user_id
    WHERE ja.job_id = ?
    ORDER BY ja.apply_date DESC
";
$stmt = $conn->prepare($applicants_query);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$applicants = $stmt->get_result();
$stmt->close();

function isFreelancerSaved($conn, $employer_id, $freelancer_id){
    $query = "SELECT id FROM Saved_Freelancers WHERE employer_id = ? AND freelancer_id = ?";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("ii", $employer_id, $freelancer_id);
    $stmt->execute();
    $is_saved = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $is_saved;
}

function getFreelancerReviewHistory($conn, $freelancer_ids){
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

// นับสถิติ
$all_rows = []; $cnt_pending = 0; $cnt_accepted = 0; $cnt_rejected = 0;
$tmp = $conn->prepare($applicants_query);
$tmp->bind_param("i", $job_id);
$tmp->execute();
$tmp_res = $tmp->get_result();
while($r = $tmp_res->fetch_assoc()){
    $all_rows[] = $r;
    $s = strtolower($r['status']);
    if($s==='pending') $cnt_pending++;
    elseif($s==='accepted'||$s==='hired') $cnt_accepted++;
    elseif($s==='rejected') $cnt_rejected++;
}
$tmp->close();
$cnt_all = count($all_rows);

$review_history_by_freelancer = getFreelancerReviewHistory($conn, array_column($all_rows, 'freelancer_id'));
foreach($all_rows as &$applicant_row){
    $fid = (int)($applicant_row['freelancer_id'] ?? 0);
    $history = $review_history_by_freelancer[$fid] ?? [];
    $applicant_row['review_history'] = $history;
    $applicant_row['review_count'] = (int)($applicant_row['review_count'] ?? count($history));
    $applicant_row['review_rating'] = $applicant_row['review_rating'] !== null
        ? round((float)$applicant_row['review_rating'], 1)
        : 0;
}
unset($applicant_row);

// reset pointer
$stmt2 = $conn->prepare($applicants_query);
$stmt2->bind_param("i", $job_id);
$stmt2->execute();
$applicants = $stmt2->get_result();
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=13">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ผู้สมัครงาน — <?php echo htmlspecialchars($job['title']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --navy:#0f172a;--navy2:#1e293b;--navy3:#334155;
    --accent:#6366f1;--light:#f1f5f9;--white:#ffffff;
    --text:#0f172a;--muted:#64748b;--border:#e2e8f0;
    --green:#10b981;--yellow:#f59e0b;--red:#ef4444;--blue:#0ea5e9;--radius:14px;
  }
  body{font-family:'Sora',sans-serif;background:var(--light);color:var(--text);display:flex;min-height:100vh;}

  /* ── Sidebar ── */
  .sidebar{width:240px;min-height:100vh;background:var(--navy);display:flex;flex-direction:column;padding:28px 0;position:fixed;top:0;left:0;z-index:100;}
  .sidebar-brand{padding:0 24px 28px;border-bottom:1px solid var(--navy3);}
  .logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
  .logo-icon{width:36px;height:36px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;}
  .logo-text{font-size:15px;font-weight:600;color:#fff;line-height:1.2;}
  .logo-sub{font-size:11px;color:var(--navy3);}
  .sidebar-nav{padding:20px 12px;flex:1;display:flex;flex-direction:column;gap:4px;}
  .nav-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:13.5px;font-weight:500;transition:background .15s,color .15s;}
  .nav-item:hover{background:var(--navy2);color:#e2e8f0;}
  .nav-item.active{background:var(--accent);color:#fff;}
  .nav-item i{font-size:17px;width:20px;text-align:center;}
  .nav-divider{height:1px;background:var(--navy3);margin:10px 14px;}
  .sidebar-footer{padding:16px 12px 0;}
  .nav-logout{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#f87171;text-decoration:none;font-size:13.5px;font-weight:500;transition:background .15s;}
  .nav-logout:hover{background:rgba(239,68,68,.12);}

  /* ── Main ── */
  .main{margin-left:240px;flex:1;padding:36px 40px;}
  .topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:24px;flex-wrap:wrap;}
  .topbar h2{font-size:22px;font-weight:600;}
  .topbar p{font-size:13px;color:var(--muted);margin-top:2px;}
  .btn-back{display:inline-flex;align-items:center;gap:7px;background:var(--white);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:9px 18px;font-size:13.5px;font-weight:500;text-decoration:none;transition:background .15s;white-space:nowrap;}
  .btn-back:hover{background:var(--light);color:var(--text);}

  /* Toast */
  .toast-notif{position:fixed;top:24px;right:24px;z-index:9999;padding:13px 20px;border-radius:12px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,.2);animation:slideIn .3s ease;transition:opacity .4s;}
  @keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

  /* Job banner */
  .job-banner{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px 24px;margin-bottom:20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
  .jb-title{font-size:16px;font-weight:600;}
  .jb-meta{font-size:12.5px;color:var(--muted);display:flex;gap:14px;flex-wrap:wrap;margin-top:3px;}
  .jb-meta span{display:flex;align-items:center;gap:4px;}

  /* Stats */
  .stat-row{display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
  .stat-mini{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;display:flex;align-items:center;gap:12px;flex:1;min-width:100px;}
  .sm-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
  .sm-val{font-size:20px;font-weight:600;line-height:1;}
  .sm-lbl{font-size:11.5px;color:var(--muted);margin-top:2px;}
  .si-purple{background:#eef2ff;color:var(--accent);}
  .si-yellow{background:#fef9c3;color:#854d0e;}
  .si-green{background:#d1fae5;color:var(--green);}
  .si-red{background:#fee2e2;color:var(--red);}

  /* Cards grid */
  .cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}

  .app-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;cursor:pointer;transition:box-shadow .2s,border-color .2s,transform .15s;}
  .app-card:hover{box-shadow:0 6px 24px rgba(0,0,0,.09);border-color:#c7d2fe;transform:translateY(-2px);}

  .card-top{display:flex;align-items:center;gap:14px;margin-bottom:14px;}
  .card-avatar{width:52px;height:52px;border-radius:50%;background:var(--accent);color:#fff;font-size:18px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;}
  .card-avatar img{width:100%;height:100%;object-fit:cover;display:block;}
  .card-name{font-size:15px;font-weight:600;margin-bottom:3px;}
  .card-meta{font-size:12.5px;color:var(--muted);display:flex;gap:10px;flex-wrap:wrap;}
  .card-meta span{display:flex;align-items:center;gap:3px;}

  .card-skills{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
  .skill-tag{font-size:11px;padding:3px 10px;border-radius:20px;background:#eef2ff;color:var(--accent);font-weight:500;}
  .skill-more{font-size:11px;color:var(--muted);}

  .status-pill{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;}
  .sp-pending{background:#fef9c3;color:#854d0e;}
  .sp-accepted,.sp-hired{background:#d1fae5;color:#065f46;}
  .sp-rejected{background:#fee2e2;color:#991b1b;}

  .card-footer{display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid var(--border);margin-top:4px;}
  .card-date{font-size:12px;color:var(--muted);}
  .btn-detail{display:inline-flex;align-items:center;gap:5px;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:7px 14px;font-size:12.5px;font-weight:500;font-family:'Sora',sans-serif;cursor:pointer;transition:background .15s;}
  .btn-detail:hover{background:#4f46e5;}

  /* Empty */
  .empty-state{text-align:center;padding:60px 20px;color:var(--muted);background:var(--white);border-radius:var(--radius);border:1px solid var(--border);}
  .empty-state i{font-size:44px;color:#c7d2fe;margin-bottom:12px;display:block;}

  /* ── Modal ── */
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:200;align-items:center;justify-content:center;padding:20px;}
  .modal-overlay.show{display:flex;}
  .modal-box{background:var(--white);border-radius:20px;max-width:660px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.24);}

  /* Modal header */
  .modal-head{background:var(--navy);padding:28px;border-radius:20px 20px 0 0;display:flex;gap:18px;align-items:flex-start;position:relative;}
  .modal-av{width:64px;height:64px;border-radius:50%;background:var(--accent);color:#fff;font-size:24px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:3px solid rgba(255,255,255,.2);overflow:hidden;}
  .modal-av img{width:100%;height:100%;object-fit:cover;display:block;}
  .modal-head-info{flex:1;}
  .modal-head-name{font-size:20px;font-weight:600;color:#fff;margin-bottom:4px;}
  .modal-head-meta{display:flex;gap:14px;font-size:13px;color:#94a3b8;flex-wrap:wrap;}
  .modal-close-btn{position:absolute;top:16px;right:16px;background:rgba(255,255,255,.15);border:none;width:32px;height:32px;border-radius:50%;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:background .15s;}
  .modal-close-btn:hover{background:rgba(255,255,255,.25);}

  /* Save button */
  .btn-save-fl{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;border:2px solid rgba(255,255,255,.4);background:transparent;color:#fff;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;font-family:'Sora',sans-serif;}
  .btn-save-fl:hover,.btn-save-fl.saved{background:#fff;color:var(--accent);border-color:#fff;}

  /* Modal body */
  .modal-body{padding:24px 28px;}
  .modal-section{margin-bottom:20px;}
  .modal-section-title{font-size:12.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;display:flex;align-items:center;gap:7px;}
  .modal-section-title::after{content:'';flex:1;height:1px;background:var(--border);}

  .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
  .info-box{background:var(--light);border-radius:10px;padding:14px;}
  .info-box .lbl{font-size:12px;color:var(--muted);margin-bottom:4px;}
  .info-box .val{font-size:14px;font-weight:500;color:var(--text);}

  .exp-box{background:var(--light);border-radius:10px;padding:14px;font-size:13.5px;color:var(--text);line-height:1.7;border-left:3px solid var(--accent);}

  .modal-skills{display:flex;flex-wrap:wrap;gap:7px;}
  .modal-skill{font-size:12px;font-weight:500;padding:4px 12px;border-radius:20px;background:#eef2ff;color:var(--accent);}

  /* Review history */
  .review-summary{display:grid;grid-template-columns:130px 1fr;gap:12px;margin-bottom:12px;}
  .review-score-card{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px;text-align:center;}
  .review-score-num{font-size:30px;font-weight:600;line-height:1;color:#92400e;}
  .review-score-stars{color:var(--yellow);font-size:14px;margin:5px 0 3px;display:flex;justify-content:center;gap:2px;}
  .review-score-count{font-size:12px;color:#92400e;}
  .review-distribution{background:var(--light);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:7px;}
  .review-dist-row{display:grid;grid-template-columns:34px 1fr 24px;align-items:center;gap:8px;font-size:12px;color:var(--muted);}
  .review-dist-label{display:flex;align-items:center;gap:3px;white-space:nowrap;}
  .review-dist-bar{height:7px;background:#e2e8f0;border-radius:99px;overflow:hidden;}
  .review-dist-fill{height:100%;background:var(--yellow);border-radius:99px;}
  .review-list{display:flex;flex-direction:column;gap:10px;max-height:280px;overflow-y:auto;padding-right:2px;}
  .review-item{background:var(--light);border:1px solid var(--border);border-radius:10px;padding:13px;}
  .review-item-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px;}
  .review-employer{font-size:13.5px;font-weight:600;color:var(--text);}
  .review-job{font-size:12px;color:var(--muted);margin-top:2px;display:flex;align-items:center;gap:4px;}
  .review-item-stars{color:var(--yellow);display:flex;gap:1px;font-size:13px;white-space:nowrap;}
  .review-comment{font-size:13px;line-height:1.6;color:var(--text);margin:8px 0;}
  .review-date{font-size:11.5px;color:var(--muted);display:flex;align-items:center;gap:4px;}
  .review-empty{background:var(--light);border:1px dashed #cbd5e1;border-radius:10px;padding:16px;color:var(--muted);font-size:13px;text-align:center;}

  /* Resume link */
  .btn-resume{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;background:#e0f2fe;color:#0369a1;font-size:13px;font-weight:500;text-decoration:none;transition:opacity .15s;}
  .btn-resume:hover{opacity:.85;color:#0369a1;}

  /* Action buttons */
  .modal-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);}
  .btn-accept{padding:11px;background:var(--green);color:#fff;border:none;border-radius:10px;font-family:'Sora',sans-serif;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .15s;}
  .btn-accept:hover{opacity:.85;}
  .btn-reject{padding:11px;background:#fee2e2;color:#991b1b;border:none;border-radius:10px;font-family:'Sora',sans-serif;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .15s;}
  .btn-reject:hover{opacity:.85;}
  .btn-rate{padding:11px;background:var(--yellow);color:#854d0e;border:none;border-radius:10px;font-family:'Sora',sans-serif;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .15s,transform .1s;}
  .btn-rate:hover{opacity:.85;transform:translateY(-1px);}
  .btn-hired-done{padding:11px;background:var(--light);color:var(--muted);border:1px solid var(--border);border-radius:10px;font-family:'Sora',sans-serif;font-size:14px;font-weight:500;cursor:default;display:flex;align-items:center;justify-content:center;gap:6px;grid-column:1/-1;}

  @media(max-width:768px){.sidebar{display:none;}.main{margin-left:0;padding:20px 16px;}.info-grid{grid-template-columns:1fr;}.review-summary{grid-template-columns:1fr;}.modal-actions{grid-template-columns:1fr;}}
</style>
<link rel="stylesheet" href="../assets/css/freelancehub-theme.css">

</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="../index.php" class="logo">
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;margin:0 auto!important;align-self:center!important;"><img class="brand-logo-img" src="../assets/images/jobfind-logo.png?v=13" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div><div class="logo-text" style="display:none!important;">Job_Find</div><div class="logo-sub" style="display:none!important;">Employer</div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"   class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"             class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="manage_jobs.php" class="nav-item active"><i class="bi bi-briefcase"></i> Manage Jobs<?php render_employer_manage_jobs_badge($sidebar_pending_apps); ?></a>
    <a href="saved_freelancers.php"    class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="reviews.php"     class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="company_review.php"      class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
    <a href="profile.php"     class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="../support/messages.php"         class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- Main -->
<main class="main">

  <div class="topbar">
    <div>
      <h2>ผู้สมัครงาน</h2>
      <p>รายชื่อ Freelancer ที่สมัครมาทั้งหมด</p>
    </div>
    <a href="manage_jobs.php" class="btn-back">
      <i class="bi bi-arrow-left"></i> กลับ Manage Jobs
    </a>
  </div>

  <!-- Job banner -->
  <div class="job-banner">
    <span style="font-size:28px;">💼</span>
    <div>
      <div class="jb-title"><?php echo htmlspecialchars($job['title']); ?></div>
      <div class="jb-meta">
        <?php if(!empty($job['location'])): ?><span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($job['location']); ?></span><?php endif; ?>
        <?php if(!empty($job['salary'])): ?><span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($job['salary']); ?></span><?php endif; ?>
        <span><i class="bi bi-people"></i><?php echo $cnt_all; ?> คนสมัคร</span>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stat-row">
    <div class="stat-mini"><div class="sm-icon si-purple"><i class="bi bi-people"></i></div><div><div class="sm-val"><?php echo $cnt_all; ?></div><div class="sm-lbl">ทั้งหมด</div></div></div>
    <div class="stat-mini"><div class="sm-icon si-yellow"><i class="bi bi-hourglass-split"></i></div><div><div class="sm-val"><?php echo $cnt_pending; ?></div><div class="sm-lbl">รอพิจารณา</div></div></div>
    <div class="stat-mini"><div class="sm-icon si-green"><i class="bi bi-check-circle"></i></div><div><div class="sm-val"><?php echo $cnt_accepted; ?></div><div class="sm-lbl">รับแล้ว</div></div></div>
    <div class="stat-mini"><div class="sm-icon si-red"><i class="bi bi-x-circle"></i></div><div><div class="sm-val"><?php echo $cnt_rejected; ?></div><div class="sm-lbl">ไม่ผ่าน</div></div></div>
  </div>

  <!-- Cards -->
  <?php if($cnt_all === 0): ?>
  <div class="empty-state">
    <i class="bi bi-inbox"></i>
    <p>ยังไม่มีผู้สมัครงานนี้</p>
  </div>
  <?php else: ?>
  <div class="cards-grid">
  <?php
  $icons = ['💼','🖥️','📐','📊','🚀','🎨','⚙️','📱','✍️','📢','🎓','💰'];
  foreach($all_rows as $row):
    $init     = profile_initials($row['username']);
    $status   = strtolower($row['status']);
    $sp_class = match($status){ 'accepted','hired'=>'sp-accepted','rejected'=>'sp-rejected',default=>'sp-pending' };
    $sp_label = match($status){ 'accepted','hired'=>'รับแล้ว','rejected'=>'ไม่ผ่าน',default=>'รอพิจารณา' };
    $review_count = (int)($row['review_count'] ?? 0);
    $avg_r    = round(($row['review_rating'] ?? 0) ?: ($row['rating'] ?? 0), 1);
    $skills   = !empty($row['skill']) ? array_map('trim', explode(',', $row['skill'])) : [];
    $date_str = !empty($row['apply_date']) ? date('d M Y', strtotime($row['apply_date'])) : '';
    $is_saved = isFreelancerSaved($conn, $employer_id, $row['freelancer_id']);
    $profile_img = trim($row['profile_image'] ?? '');
    $modal_row = $row;
    $modal_row['profile_image_url'] = $profile_img !== '' ? jobfind_url($profile_img) : '';
    $data_json = htmlspecialchars(
        json_encode($modal_row, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG),
        ENT_QUOTES,
        'UTF-8'
    );
  ?>
  <div class="app-card" onclick='openModal(<?php echo $data_json; ?>)'>
    <div class="card-top">
      <div class="card-avatar">
        <?php if($profile_img !== ''): ?>
          <img src="<?php echo profile_image_src($profile_img); ?>" alt="Profile image">
        <?php else: ?>
          <?php echo $init; ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="card-name"><?php echo htmlspecialchars($row['username']); ?></div>
        <div class="card-meta">
          <?php if(!empty($row['location'])): ?><span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($row['location']); ?></span><?php endif; ?>
          <?php if($avg_r > 0): ?><span style="color:var(--yellow);"><i class="bi bi-star-fill"></i><?php echo $avg_r; ?><?php echo $review_count > 0 ? ' ('.$review_count.')' : ''; ?></span><?php endif; ?>
        </div>
      </div>
      <span class="status-pill <?php echo $sp_class; ?>"><?php echo $sp_label; ?></span>
    </div>

    <?php if(!empty($skills)): ?>
    <div class="card-skills">
      <?php foreach(array_slice($skills,0,3) as $sk): ?>
      <span class="skill-tag"><?php echo htmlspecialchars($sk); ?></span>
      <?php endforeach; ?>
      <?php if(count($skills)>3): ?><span class="skill-more">+<?php echo count($skills)-3; ?> อื่นๆ</span><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card-footer">
      <span class="card-date"><i class="bi bi-clock" style="font-size:11px;"></i> <?php echo $date_str; ?></span>
      <button class="btn-detail" onclick="event.stopPropagation();openModal(<?php echo $data_json; ?>)">
        <i class="bi bi-eye"></i> ดูรายละเอียด
      </button>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>

<!-- Modal -->
<div class="modal-overlay" id="fl-modal">
  <div class="modal-box">

    <!-- Header -->
    <div class="modal-head">
      <button class="modal-close-btn" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
      <div class="modal-av" id="m-av"></div>
      <div class="modal-head-info">
        <div class="modal-head-name" id="m-name"></div>
        <div class="modal-head-meta">
          <span id="m-location"></span>
          <span id="m-rating"></span>
          <span id="m-status-badge"></span>
        </div>
        <div style="margin-top:10px;">
          <button class="btn-save-fl" id="save-btn" onclick="toggleSave()">
            <i class="bi bi-bookmark" id="save-icon"></i>
            <span id="save-text">บันทึก</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="modal-body">

      <div class="modal-section">
        <div class="modal-section-title"><i class="bi bi-envelope"></i> ข้อมูลติดต่อ</div>
        <div class="info-grid">
          <div class="info-box"><div class="lbl">Email</div><div class="val" id="m-email"></div></div>
          <div class="info-box"><div class="lbl">Phone</div><div class="val" id="m-phone"></div></div>
        </div>
      </div>

      <div class="modal-section">
        <div class="modal-section-title"><i class="bi bi-tools"></i> ทักษะ</div>
        <div class="modal-skills" id="m-skills"></div>
      </div>

      <div class="modal-section" id="m-exp-wrap">
        <div class="modal-section-title"><i class="bi bi-briefcase"></i> ประสบการณ์</div>
        <div class="exp-box" id="m-exp"></div>
      </div>

      <div class="modal-section" id="m-review-wrap">
        <div class="modal-section-title"><i class="bi bi-star"></i> ประวัติรีวิวและเรท</div>
        <div class="review-summary">
          <div class="review-score-card">
            <div class="review-score-num" id="m-review-score">-</div>
            <div class="review-score-stars" id="m-review-stars"></div>
            <div class="review-score-count" id="m-review-count">ยังไม่มีรีวิว</div>
          </div>
          <div class="review-distribution" id="m-review-dist"></div>
        </div>
        <div class="review-list" id="m-review-list"></div>
      </div>

      <div class="modal-section" id="m-resume-wrap" style="display:none;">
        <div class="modal-section-title"><i class="bi bi-file-earmark-pdf"></i> Resume</div>
        <a href="#" id="m-resume-link" target="_blank" class="btn-resume">
          <i class="bi bi-filetype-pdf"></i> ดู Resume PDF
        </a>
      </div>

      <!-- Actions -->
      <div id="m-actions"></div>

    </div>
  </div>
</div>

<script>
let currentId = null, currentJobId = <?php echo intval($job_id); ?>, isSaved = false;

function openModal(ap){
  currentId = ap.freelancer_id;
  const status = (ap.status||'').toLowerCase();

  const avatar = document.getElementById('m-av');
  avatar.innerHTML = '';
  const fallbackInitials = (ap.username || '?').substring(0, 2).toUpperCase();
  if (ap.profile_image_url) {
    const img = document.createElement('img');
    img.src = ap.profile_image_url;
    img.alt = 'Profile image';
    img.onerror = () => {
      avatar.innerHTML = '';
      avatar.textContent = fallbackInitials;
    };
    avatar.appendChild(img);
  } else {
    avatar.textContent = fallbackInitials;
  }
  document.getElementById('m-name').textContent  = ap.username;
  document.getElementById('m-email').textContent = ap.email || '—';
  document.getElementById('m-phone').textContent = ap.phone || 'ไม่ระบุ';
  document.getElementById('m-location').textContent = ap.location ? '📍 '+ap.location : '';
  const reviewRating = Number(ap.review_rating || 0);
  const reviewCount = parseInt(ap.review_count || 0, 10);
  const profileRating = Number(ap.rating || 0);
  const displayRating = reviewRating > 0 ? reviewRating : profileRating;
  document.getElementById('m-rating').textContent = displayRating > 0
    ? `\u2605 ${displayRating.toFixed(1)}${reviewCount > 0 ? ` (${reviewCount} รีวิว)` : ''}`
    : '';

  const badges = {accepted:'✅ รับแล้ว', hired:'✅ รับแล้ว', rejected:'❌ ไม่ผ่าน', pending:'⏳ รอพิจารณา'};
  document.getElementById('m-status-badge').textContent = badges[status] || '';

  // Skills
  const sc = document.getElementById('m-skills');
  sc.innerHTML = '';
  if(ap.skill){
    ap.skill.split(',').forEach(s => {
      const b = document.createElement('span');
      b.className = 'modal-skill';
      b.textContent = s.trim();
      sc.appendChild(b);
    });
  } else { sc.innerHTML = '<span style="color:var(--muted);">ไม่มีข้อมูล</span>'; }

  // Experience
  const expEl = document.getElementById('m-exp');
  expEl.textContent = ap.experience || 'ไม่ระบุ';

  renderReviewHistory(ap);

  // Actions
  const actEl = document.getElementById('m-actions');
  const isHired = status==='accepted'||status==='hired';
  const isRejected = status==='rejected';
  if(isHired){
    actEl.innerHTML = `
      <div class="modal-actions" style="flex-direction:column;">
        <div class="btn-hired-done"><i class="bi bi-check-circle-fill" style="color:var(--green);"></i> รับเข้าทำงานแล้ว</div>
        <button class="btn-rate" onclick="rateFreelancer(${ap.freelancer_id})"><i class="bi bi-star"></i> รีวิว Freelancer</button>
      </div>`;
  } else if(isRejected){
    actEl.innerHTML = `<div class="btn-hired-done"><i class="bi bi-x-circle-fill" style="color:var(--red);"></i> ปฏิเสธแล้ว</div>`;
  } else {
    actEl.innerHTML = `
      <div class="modal-actions">
        <button class="btn-accept" onclick="acceptApplicant(${ap.application_id})"><i class="bi bi-person-check"></i> รับเข้าทำงาน</button>
        <button class="btn-reject" onclick="rejectApplicant(${ap.application_id})"><i class="bi bi-x-lg"></i> ปฏิเสธ</button>
      </div>`;
  }

  // Resume
  const resumeWrap = document.getElementById('m-resume-wrap');
  const resumeLink = document.getElementById('m-resume-link');
  const uploadsBaseUrl = <?php echo json_encode(jobfind_url('uploads/')); ?>;
  if(ap.resume_file){
    resumeWrap.style.display = '';
    const resumeFile = ap.resume_file.startsWith('uploads/') ? ap.resume_file.substring('uploads/'.length) : ap.resume_file;
    resumeLink.href = uploadsBaseUrl + resumeFile;
  } else {
    resumeWrap.style.display = 'none';
  }
  document.getElementById('fl-modal').classList.add('show');
  document.body.style.overflow = 'hidden';
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
  return parsed.toLocaleDateString('th-TH', { day:'2-digit', month:'short', year:'numeric' });
}

function renderReviewHistory(ap){
  const reviews = Array.isArray(ap.review_history) ? ap.review_history : [];
  const total = parseInt(ap.review_count || reviews.length || 0, 10);
  const avg = Number(ap.review_rating || 0);
  const scoreEl = document.getElementById('m-review-score');
  const starsEl = document.getElementById('m-review-stars');
  const countEl = document.getElementById('m-review-count');
  const distEl = document.getElementById('m-review-dist');
  const listEl = document.getElementById('m-review-list');

  scoreEl.textContent = avg > 0 ? avg.toFixed(1) : '-';
  countEl.textContent = total > 0 ? `${total} รีวิว` : 'ยังไม่มีรีวิว';
  appendStars(starsEl, avg);

  const counts = {1:0, 2:0, 3:0, 4:0, 5:0};
  reviews.forEach(review => {
    const rating = parseInt(review.rating || 0, 10);
    if(counts[rating] !== undefined){
      counts[rating]++;
    }
  });

  distEl.innerHTML = '';
  [5,4,3,2,1].forEach(star => {
    const row = document.createElement('div');
    row.className = 'review-dist-row';

    const label = document.createElement('div');
    label.className = 'review-dist-label';
    const icon = document.createElement('i');
    icon.className = 'bi bi-star-fill';
    icon.style.color = 'var(--yellow)';
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

function closeModal(){
  document.getElementById('fl-modal').classList.remove('show');
  document.body.style.overflow = '';
}

function checkSave(fid){
  fetch('../actions/check_saved_freelancer.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`freelancer_id=${fid}`
  }).then(r=>r.json()).then(d=>{ isSaved=d.is_saved; updateSaveBtn(); });
}

function toggleSave(){
  if(!currentId) return;
  fetch('../actions/save_freelancer.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`freelancer_id=${currentId}&action=${isSaved?'unsave':'save'}`
  }).then(r=>r.json()).then(d=>{
    if(d.success){ isSaved=!isSaved; updateSaveBtn(); showToast(isSaved?'บันทึกแล้ว ✓':'ยกเลิกการบันทึกแล้ว','#0f172a'); }
    else showToast(d.message||'เกิดข้อผิดพลาด','#ef4444');
  });
}

function updateSaveBtn(){
  const btn=document.getElementById('save-btn');
  const icon=document.getElementById('save-icon');
  const txt=document.getElementById('save-text');
  if(isSaved){ btn.classList.add('saved'); icon.className='bi bi-bookmark-check-fill'; txt.textContent='บันทึกแล้ว'; }
  else { btn.classList.remove('saved'); icon.className='bi bi-bookmark'; txt.textContent='บันทึก'; }
}

function acceptApplicant(appId){
  if(!confirm('รับ Freelancer คนนี้เข้าทำงาน?')) return;
  window.location.href = `../actions/hire.php?application_id=${appId}`;
}

function rejectApplicant(appId){
  if(!confirm('ปฏิเสธผู้สมัครคนนี้?')) return;
  window.location.href = `../actions/reject_applicant.php?application_id=${appId}`;
}

function rateFreelancer(flId){
  if(!flId || !currentJobId) return;
  window.location.href = `rate_freelancer.php?freelancer_id=${flId}&job_id=${currentJobId}`;
}

function showToast(msg, bg){
  const t = document.createElement('div');
  t.className = 'toast-notif';
  t.style.background = bg||'#0f172a';
  t.style.color = '#fff';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; setTimeout(()=>t.remove(),400); }, 2500);
}

document.getElementById('fl-modal').addEventListener('click', e=>{ if(e.target.id==='fl-modal') closeModal(); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeModal(); });
</script>
</body>
</html>
