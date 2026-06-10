<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../helpers/profile_image_helpers.php";
require_once __DIR__ . "/../helpers/employer_sidebar_helpers.php";
require_once __DIR__ . "/../helpers/category_helpers.php";
require_once __DIR__ . "/../helpers/review_schema.php";

ensure_category_schema($conn);
ensure_default_job_categories($conn);
ensure_profile_image_schema($conn);
ensure_freelancer_review_schema($conn);

$user_id  = jobfind_require_role('employer');
$username = $_SESSION['username'];
$current_user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT profile_image FROM Users WHERE user_id='$user_id'"));
$profile_image = trim($current_user['profile_image'] ?? '');

// ── stats ──
$total_jobs   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM Job WHERE employer_id='$user_id'"))['c'];
$active_jobs  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM Job WHERE employer_id='$user_id' AND admin_status='approved' AND status='open'"))['c'];
$total_apps   = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS c FROM Job_Application ja
    JOIN Job j ON j.job_id = ja.job_id
    WHERE j.employer_id='$user_id'
"))['c'];
$pending_apps = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS c FROM Job_Application ja
    JOIN Job j ON j.job_id = ja.job_id
    WHERE j.employer_id='$user_id' AND ja.status='pending'
"))['c'];
$sidebar_pending_apps = (int)$pending_apps;

// ── employer rating ──
$rating_data = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT AVG(rating) AS avg_r, COUNT(*) AS total_r
    FROM Employer_Review WHERE employer_id='$user_id'
"));
$avg_rating   = round($rating_data['avg_r'] ?? 0, 1);
$total_review = $rating_data['total_r'] ?? 0;

// ── recent applications ──
$recent_apps = mysqli_query($conn,"
    SELECT ja.status, ja.application_id, j.title, u.username AS freelancer
    FROM Job_Application ja
    JOIN Job j ON j.job_id = ja.job_id
    JOIN Users u ON u.user_id = ja.freelancer_id
    WHERE j.employer_id='$user_id'
    ORDER BY ja.application_id DESC
    LIMIT 5
");

// ── recent jobs ──
$recent_jobs = mysqli_query($conn,"
    SELECT title, status, admin_status, created_at
    FROM Job
    WHERE employer_id='$user_id'
    ORDER BY created_at DESC
    LIMIT 5
");

$popular_employers = mysqli_query($conn,"
    SELECT u.user_id,
           COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS company_name,
           COALESCE(l.total_likes,0) AS total_likes,
           COALESCE(r.total_reviews,0) AS total_reviews,
           COALESCE(r.avg_rating,0) AS avg_rating,
           COALESCE(j.total_jobs,0) AS total_jobs
    FROM Users u
    LEFT JOIN Employer_Profile ep ON ep.user_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_likes
        FROM Like_Employer
        GROUP BY employer_id
    ) l ON l.employer_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating
        FROM Employer_Review
        GROUP BY employer_id
    ) r ON r.employer_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_jobs
        FROM Job
        WHERE admin_status='approved'
        GROUP BY employer_id
    ) j ON j.employer_id = u.user_id
    WHERE u.role='employer'
    ORDER BY total_likes DESC, avg_rating DESC, total_reviews DESC, total_jobs DESC, company_name ASC
    LIMIT 5
");

// ── Top Freelancers - Rating สูงสุด 7 วัน ──
$top_freelancers = mysqli_query($conn,"
    SELECT u.user_id,
           u.username,
           u.fullname,
           u.email,
           u.phone,
           u.profile_image,
           COALESCE(fp.skill, '') AS skill,
           COALESCE(fp.experience, '') AS experience,
           COALESCE(fp.location, 'ไม่ระบุ') AS location,
           (SELECT file_name FROM Resume WHERE freelancer_id=u.user_id ORDER BY resume_id DESC LIMIT 1) AS resume_file,
           COALESCE(fr.total_reviews, 0) AS total_reviews,
           COALESCE(fr.avg_rating, 0) AS avg_rating
    FROM Users u
    LEFT JOIN Freelancer_Profile fp ON fp.user_id = u.user_id
    LEFT JOIN (
        SELECT freelancer_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating
        FROM Freelancer_Review
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY freelancer_id
    ) fr ON fr.freelancer_id = u.user_id
    WHERE u.role='freelancer'
    AND COALESCE(fr.total_reviews, 0) > 0
    ORDER BY avg_rating DESC, total_reviews DESC
    LIMIT 5
");

function getFreelancerReviewHistory($conn, $freelancer_id){
    $freelancer_id = (int)$freelancer_id;
    $reviews = [];
    $review_query = mysqli_query($conn,"
        SELECT fr.review_id,
               fr.rating,
               COALESCE(NULLIF(fr.comment,''), NULLIF(fr.review,''), '') AS comment,
               fr.created_at,
               COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username, 'ไม่ระบุผู้ว่าจ้าง') AS employer_name,
               COALESCE(NULLIF(j.title,''), 'ไม่ระบุชื่องาน') AS job_title
        FROM Freelancer_Review fr
        LEFT JOIN Users u ON u.user_id = fr.employer_id
        LEFT JOIN Employer_Profile ep ON ep.user_id = fr.employer_id
        LEFT JOIN Job j ON j.job_id = fr.job_id
        WHERE fr.freelancer_id = $freelancer_id
        AND fr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY fr.created_at DESC, fr.review_id DESC
        LIMIT 10
    ");

    if($review_query){
        while($review = mysqli_fetch_assoc($review_query)){
            $reviews[] = [
                'review_id' => (int)$review['review_id'],
                'rating' => (int)$review['rating'],
                'comment' => $review['comment'] ?? '',
                'created_at' => $review['created_at'] ?? '',
                'date' => !empty($review['created_at']) ? date('d M Y', strtotime($review['created_at'])) : '',
                'employer_name' => $review['employer_name'] ?? 'ไม่ระบุผู้ว่าจ้าง',
                'job_title' => $review['job_title'] ?? 'ไม่ระบุชื่องาน',
            ];
        }
    }

    return $reviews;
}

function dashboard_category_icon($category, $db_icon = ''){
    $db_icon = trim((string)$db_icon);
    if($db_icon !== '' && !preg_match('/[\x{00C0}-\x{00FF}\x{FFFD}]/u', $db_icon)){
        return $db_icon;
    }

    $icons = [
        'IT' => '&#128187;',
        'IT & Software' => '&#128187;',
        'Design' => '&#127912;',
        'Marketing' => '&#128227;',
        'Accounting' => '&#128176;',
        'Finance' => '&#128176;',
        'Writing' => '&#9997;&#65039;',
        'Education' => '&#127891;',
        'Programmer' => '&#128187;',
        'Data Analyst' => '&#128202;',
        'Cyber Security' => '&#128737;&#65039;',
        'AI Engineer' => '&#129302;',
        'Other' => '&#128230;'
    ];

    return html_entity_decode($icons[$category] ?? '&#128193;', ENT_QUOTES, 'UTF-8');
}

// ── Popular Categories - Rating ในช่วง 7 วัน ──
$popular_jobs = mysqli_query($conn,"
    SELECT COALESCE(j.category, 'ไม่ระบุ') AS category,
           COALESCE(MAX(NULLIF(c.icon, '')), '') AS category_icon,
           COUNT(j.job_id) AS total_jobs,
           COALESCE(jr.total_reviews, 0) AS total_reviews,
           COALESCE(jr.avg_rating, 0) AS avg_rating
    FROM Job j
    LEFT JOIN (
        SELECT name, MAX(NULLIF(icon, '')) AS icon
        FROM Categories
        GROUP BY name
    ) c ON c.name = j.category
    LEFT JOIN (
        SELECT job_id, 
                COUNT(*) AS total_reviews, 
                AVG(rating) AS avg_rating
        FROM Freelancer_Review
        WHERE job_id IS NOT NULL 
        AND job_id > 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY job_id
        UNION ALL
        SELECT job_id,
                COUNT(*) AS total_reviews,
                AVG(rating) AS avg_rating
        FROM Employer_Review
        WHERE job_id IS NOT NULL 
        AND job_id > 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY job_id
    ) jr ON jr.job_id = j.job_id
    WHERE j.admin_status='approved'
    AND (j.status IN ('in_progress', 'completed', 'closed')
         OR j.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    AND COALESCE(jr.total_reviews, 0) > 0
    GROUP BY COALESCE(j.category, 'ไม่ระบุ')
    ORDER BY avg_rating DESC, total_reviews DESC
    LIMIT 5
");

$most_applied_jobs = mysqli_query($conn,"
    SELECT COALESCE(NULLIF(j.category,''), 'ไม่ระบุ') AS category,
           COALESCE(MAX(NULLIF(c.icon, '')), '') AS category_icon,
           COUNT(DISTINCT j.job_id) AS total_jobs,
           COUNT(ja.application_id) AS applicant_count,
           SUM(CASE WHEN ja.status='pending' THEN 1 ELSE 0 END) AS pending_count,
           MAX(j.created_at) AS latest_job_at
    FROM Job j
    LEFT JOIN (
        SELECT name, MAX(NULLIF(icon, '')) AS icon
        FROM Categories
        GROUP BY name
    ) c ON c.name = j.category
    JOIN Job_Application ja ON ja.job_id = j.job_id
    WHERE j.employer_id='$user_id'
    GROUP BY COALESCE(NULLIF(j.category,''), 'ไม่ระบุ')
    ORDER BY applicant_count DESC, pending_count DESC, total_jobs DESC, latest_job_at DESC
    LIMIT 5
");

$most_applied_jobs_list = [];
if($most_applied_jobs){
    while($row = mysqli_fetch_assoc($most_applied_jobs)){
        $row['applicant_count'] = (int)$row['applicant_count'];
        $row['pending_count'] = (int)$row['pending_count'];
        $row['total_jobs'] = (int)$row['total_jobs'];
        $most_applied_jobs_list[] = $row;
    }
}
$most_applied_count = count($most_applied_jobs_list);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=13">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employer Dashboard</title>
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
  .topbar-avatar { width:44px; height:44px; border-radius:50%; background:var(--accent); color:#fff; font-size:16px; font-weight:600; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; overflow:hidden; }
  .topbar-avatar img { width:100%; height:100%; object-fit:cover; display:block; }

  /* ── Stat grid ── */
  .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
  .stat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 22px; display:flex; align-items:center; gap:16px; }
  .stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
  .si-purple { background:#eef2ff; color:var(--accent); }
  .si-green  { background:#d1fae5; color:var(--green); }
  .si-yellow { background:#fef9c3; color:var(--yellow); }
  .si-blue   { background:#e0f2fe; color:var(--blue); }
  .si-star   { background:#fef9c3; color:#d97706; }
  .stat-info .value { font-size:26px; font-weight:600; line-height:1; }
  .stat-info .label { font-size:12px; color:var(--muted); margin-top:4px; }

  /* ── Quick links ── */
  .quick-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:28px; }
  .quick-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 18px; text-decoration:none; color:var(--text); display:flex; flex-direction:column; align-items:flex-start; gap:10px; transition:box-shadow .2s,border-color .2s,transform .15s; position:relative; }
  .quick-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.09); border-color:#c7d2fe; transform:translateY(-2px); color:var(--text); }
  .qc-icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:20px; }
  .qc-title { font-size:14px; font-weight:600; }
  .qc-sub   { font-size:12px; color:var(--muted); }
  .qc-arrow { position:absolute; right:16px; top:50%; transform:translateY(-50%); font-size:18px; color:var(--border); transition:color .15s,right .15s; }
  .quick-card:hover .qc-arrow { color:var(--accent); right:12px; }
  .pending-dot { position:absolute; top:14px; right:14px; width:8px; height:8px; border-radius:50%; background:var(--red); }

  /* ── Rating block ── */
  .rating-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 22px; margin-bottom:28px; display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
  .rating-big { font-size:42px; font-weight:600; line-height:1; }
  .rating-stars { color:var(--yellow); font-size:20px; margin:4px 0; }
  .rating-sub  { font-size:12px; color:var(--muted); }
  .rating-divider { width:1px; height:60px; background:var(--border); flex-shrink:0; }
  .rating-tip { font-size:13px; color:var(--muted); line-height:1.7; }
  .rating-tip strong { color:var(--text); }

  .popular-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:20px 22px; margin-bottom:28px; }
  .popular-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; }
  .popular-head h4 { font-size:16px; font-weight:600; display:flex; align-items:center; gap:8px; margin:0; }
  .popular-head h4 i { color:var(--accent); }
  .popular-top-label { font-size:11px; font-weight:700; color:var(--accent); background:#eef2ff; border:1px solid #c7d2fe; padding:4px 10px; border-radius:999px; }
  .popular-list { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
  .popular-item { display:flex; flex-direction:column; gap:10px; min-width:0; padding:14px; border:1px solid var(--border); border-radius:12px; color:var(--text); text-decoration:none; background:#fff; transition:border-color .15s,box-shadow .2s,transform .15s; font-family:'Sora',sans-serif; text-align:left; cursor:pointer; }
  .popular-item:hover { color:var(--text); border-color:#c7d2fe; box-shadow:0 6px 18px rgba(99,102,241,.12); transform:translateY(-1px); }
  .popular-top { display:flex; align-items:center; gap:10px; min-width:0; }
  .popular-rank { width:28px; height:28px; border-radius:9px; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; }
  .popular-avatar { width:34px; height:34px; border-radius:10px; background:var(--light); color:var(--accent); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; overflow:hidden; }
  .popular-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .popular-name { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .popular-stats { display:flex; flex-wrap:wrap; gap:6px; }
  .popular-pill { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:var(--muted); background:var(--light); border-radius:999px; padding:4px 8px; }
  .popular-pill i { color:var(--accent); font-size:12px; }
  .popular-empty { grid-column:1 / -1; text-align:center; color:var(--muted); padding:24px; font-size:13px; }

  .profile-modal-overlay { display:none; position:fixed; inset:0; z-index:300; background:rgba(15,23,42,.62); align-items:center; justify-content:center; padding:20px; }
  .profile-modal-overlay.show { display:flex; }
  .profile-modal { width:min(680px,100%); max-height:90vh; overflow:auto; background:var(--white); border-radius:20px; box-shadow:0 24px 70px rgba(15,23,42,.28); }
  .profile-modal-head { position:relative; display:flex; gap:18px; align-items:flex-start; padding:28px; border-radius:20px 20px 0 0; background:var(--navy); color:#fff; }
  .profile-modal-close { position:absolute; top:16px; right:16px; width:34px; height:34px; border:0; border-radius:50%; background:rgba(255,255,255,.14); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; }
  .profile-modal-close:hover { background:rgba(255,255,255,.24); }
  .profile-modal-avatar { width:64px; height:64px; border-radius:50%; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:700; border:3px solid rgba(255,255,255,.22); flex-shrink:0; overflow:hidden; }
  .profile-modal-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .profile-modal-name { font-size:21px; font-weight:700; margin-bottom:8px; }
  .profile-modal-meta { display:flex; flex-wrap:wrap; gap:12px; color:#cbd5e1; font-size:13px; }
  .profile-modal-body { padding:24px 28px 28px; }
  .profile-section { margin-bottom:20px; }
  .profile-section:last-child { margin-bottom:0; }
  .profile-section-title { font-size:12.5px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; display:flex; align-items:center; gap:8px; margin-bottom:12px; }
  .profile-section-title::after { content:''; flex:1; height:1px; background:var(--border); }
  .profile-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .profile-info-box { background:var(--light); border-radius:10px; padding:13px 14px; }
  .profile-info-box.full { grid-column:1 / -1; }
  .profile-info-label { font-size:12px; color:var(--muted); margin-bottom:4px; }
  .profile-info-value { font-size:14px; font-weight:600; color:var(--text); line-height:1.6; word-break:break-word; }
  .profile-skills { display:flex; flex-wrap:wrap; gap:7px; }
  .profile-skill { font-size:12px; font-weight:600; padding:5px 12px; border-radius:999px; background:#eef2ff; color:var(--accent); }
  .profile-resume-link { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:10px; background:#e0f2fe; color:#0369a1; text-decoration:none; font-size:13px; font-weight:600; }
  .profile-resume-link:hover { color:#0369a1; opacity:.88; }
  .profile-review-list { display:grid; gap:10px; }
  .profile-review-card { border:1px solid var(--border); border-radius:12px; background:#fff; padding:14px; }
  .profile-review-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:10px; }
  .profile-review-employer { font-size:13.5px; font-weight:700; color:var(--text); }
  .profile-review-job { margin-top:3px; font-size:12px; color:var(--muted); display:flex; align-items:center; gap:5px; }
  .profile-review-rating { display:inline-flex; align-items:center; gap:5px; flex-shrink:0; color:#f59e0b; font-size:12.5px; font-weight:800; background:#fffbeb; border-radius:999px; padding:5px 9px; }
  .profile-review-comment { color:var(--text); background:var(--light); border-left:3px solid var(--accent); border-radius:8px; padding:10px 12px; font-size:13px; line-height:1.6; }
  .profile-review-date { margin-top:9px; display:flex; align-items:center; gap:5px; color:var(--muted); font-size:11.5px; }
  .profile-review-empty { color:var(--muted); background:var(--light); border-radius:10px; padding:14px; font-size:13px; text-align:center; }

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
  .sp-accepted { background:#d1fae5; color:#065f46; }
  .empty-td { text-align:center; color:var(--muted); padding:28px !important; font-size:13px; }

  @media(max-width:1100px){ .stat-grid,.quick-grid,.popular-list { grid-template-columns:repeat(2,1fr); } }
  @media(max-width:768px) { .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } .two-col,.popular-list,.profile-info-grid { grid-template-columns:1fr; } .profile-info-box.full { grid-column:auto; } }
</style>
<link rel="stylesheet" href="../assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Sidebar ── -->
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
    <a href="dashboard.php"  class="nav-item active"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="post_job.php"            class="nav-item"><i class="bi bi-plus-circle"></i> Post Job</a>
    <a href="manage_jobs.php" class="nav-item">
      <i class="bi bi-briefcase"></i> Manage Jobs
      <?php render_employer_manage_jobs_badge($sidebar_pending_apps); ?>
    </a>
    <a href="saved_freelancers.php" class="nav-item"><i class="bi bi-bookmark"></i> Saved Freelancers</a>
    <a href="reviews.php"    class="nav-item"><i class="bi bi-star"></i> My Reviews</a>
    <a href="company_review.php"     class="nav-item"><i class="bi bi-building"></i> รีวิวบริษัท</a>
    <a href="profile.php"    class="nav-item"><i class="bi bi-person-circle"></i> My Profile</a>
    <div class="nav-divider"></div>
    <a href="../support/messages.php"        class="nav-item"><i class="bi bi-chat-dots"></i> Support Chat</a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div>
      <h2>Welcome back, <?php echo htmlspecialchars($username); ?> 👋</h2>
      <p>จัดการงานและดูการสมัครของ Freelancer ได้ที่นี่</p>
    </div>
    <div class="topbar-avatar">
      <?php if($profile_image !== ''): ?>
        <img src="<?php echo profile_image_src($profile_image); ?>" alt="Profile image">
      <?php else: ?>
        <?php echo profile_initials($username); ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon si-purple"><i class="bi bi-briefcase"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_jobs; ?></div>
        <div class="label">งานทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-green"><i class="bi bi-check-circle"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $active_jobs; ?></div>
        <div class="label">งานที่เปิดรับ</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="bi bi-file-earmark-text"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $total_apps; ?></div>
        <div class="label">การสมัครทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-yellow"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-info">
        <div class="value"><?php echo $pending_apps; ?></div>
        <div class="label">รอพิจารณา</div>
      </div>
    </div>
  </div>

  <!-- Quick links -->
  <div class="quick-grid">
    <a href="post_job.php" class="quick-card">
      <div class="qc-icon si-purple"><i class="bi bi-plus-circle"></i></div>
      <div class="qc-title">Post Job</div>
      <div class="qc-sub">โพสต์งานใหม่</div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="manage_jobs.php" class="quick-card">
      <?php if($pending_apps > 0): ?><div class="pending-dot"></div><?php endif; ?>
      <div class="qc-icon si-green"><i class="bi bi-briefcase"></i></div>
      <div class="qc-title">Manage Jobs</div>
      <div class="qc-sub"><?php echo $pending_apps > 0 ? $pending_apps.' คนรอพิจารณา' : 'จัดการตำแหน่งงาน'; ?></div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="reviews.php" class="quick-card">
      <div class="qc-icon si-star"><i class="bi bi-star"></i></div>
      <div class="qc-title">My Reviews</div>
      <div class="qc-sub"><?php echo $avg_rating > 0 ? '⭐ '.$avg_rating.' / 5.0' : 'ยังไม่มีรีวิว'; ?></div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
    <a href="profile.php" class="quick-card">
      <div class="qc-icon si-blue"><i class="bi bi-person-circle"></i></div>
      <div class="qc-title">My Profile</div>
      <div class="qc-sub">แก้ไขโปรไฟล์</div>
      <i class="bi bi-arrow-right qc-arrow"></i>
    </a>
  </div>

  <!-- Popular Employers -->
  <section class="popular-card">
    <div class="popular-head">
      <h4><i class="bi bi-trophy"></i> ผู้ว่าจ้างยอดนิยม</h4>
      <span class="popular-top-label">Top 5</span>
    </div>

    <div class="popular-list">
      <?php
        $rank = 1;
        $hasPopular = false;
        while($emp = mysqli_fetch_assoc($popular_employers)):
          $hasPopular = true;
          $rating = round($emp['avg_rating'] ?? 0, 1);
      ?>
      <a href="profile.php?employer_id=<?php echo (int)$emp['user_id']; ?>" class="popular-item">
        <div class="popular-top">
          <div class="popular-rank"><?php echo $rank; ?></div>
          <div class="popular-avatar"><i class="bi bi-building"></i></div>
          <div class="popular-name"><?php echo htmlspecialchars($emp['company_name']); ?></div>
        </div>
        <div class="popular-stats">
          <span class="popular-pill"><i class="bi bi-heart-fill"></i><?php echo (int)$emp['total_likes']; ?> ไลก์</span>
          <span class="popular-pill"><i class="bi bi-star-fill"></i><?php echo $rating > 0 ? $rating : '-'; ?></span>
          <span class="popular-pill"><i class="bi bi-briefcase-fill"></i><?php echo (int)$emp['total_jobs']; ?> งาน</span>
        </div>
      </a>
      <?php $rank++; endwhile; ?>

      <?php if(!$hasPopular): ?>
      <div class="popular-empty">ยังไม่มีข้อมูลผู้ว่าจ้าง</div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Top Freelancers (7 Days) -->
  <section class="popular-card">
    <div class="popular-head">
      <h4><i class="bi bi-person-badge"></i> Freelancers ยอดเยี่ยม (7 วันที่ผ่านมา)</h4>
      <span class="popular-top-label">Top 5</span>
    </div>

    <div class="popular-list">
      <?php
        $rank = 1;
        $hasFreelancers = false;
        while($freelancer = mysqli_fetch_assoc($top_freelancers)):
          $hasFreelancers = true;
          $rating = round($freelancer['avg_rating'] ?? 0, 1);
          $reviewHistory = getFreelancerReviewHistory($conn, (int)$freelancer['user_id']);
          $profileData = [
            'freelancer_id' => (int)$freelancer['user_id'],
            'user_id' => (int)$freelancer['user_id'],
            'username' => $freelancer['username'] ?? '',
            'fullname' => $freelancer['fullname'] ?? '',
            'email' => $freelancer['email'] ?? '',
            'phone' => $freelancer['phone'] ?? '',
            'profile_image' => $freelancer['profile_image'] ?? '',
            'location' => $freelancer['location'] ?? '',
            'skill' => $freelancer['skill'] ?? '',
            'experience' => $freelancer['experience'] ?? '',
            'resume_file' => $freelancer['resume_file'] ?? '',
            'avg_rating' => $rating,
            'total_reviews' => (int)$freelancer['total_reviews'],
            'reviews' => $reviewHistory,
          ];
      ?>
      <button type="button" class="popular-item" onclick='openFreelancerProfile(<?php echo htmlspecialchars(json_encode($profileData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), ENT_QUOTES, "UTF-8"); ?>)'>
        <div class="popular-top">
          <div class="popular-rank"><?php echo $rank; ?></div>
          <div class="popular-avatar">
            <?php if(!empty($freelancer['profile_image'])): ?>
              <img src="<?php echo profile_image_src($freelancer['profile_image']); ?>" alt="Profile image">
            <?php else: ?>
              <i class="bi bi-person-circle"></i>
            <?php endif; ?>
          </div>
          <div class="popular-name"><?php echo htmlspecialchars($freelancer['username']); ?></div>
        </div>
        <div class="popular-stats">
          <span class="popular-pill"><i class="bi bi-map-fill"></i><?php echo htmlspecialchars($freelancer['location']); ?></span>
          <span class="popular-pill"><i class="bi bi-star-fill"></i><?php echo $rating > 0 ? $rating : '-'; ?></span>
          <span class="popular-pill"><i class="bi bi-chat-dots-fill"></i><?php echo (int)$freelancer['total_reviews']; ?> รีวิว</span>
        </div>
      </button>
      <?php $rank++; endwhile; ?>

      <?php if(!$hasFreelancers): ?>
      <div class="popular-empty">ยังไม่มีข้อมูล Freelancers ในช่วง 7 วันที่ผ่านมา</div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Popular Categories (7 Days - by Rating) -->
  <section class="popular-card">
    <div class="popular-head">
      <h4><i class="bi bi-fire"></i> หมวดงานยอดนิยม (7 วันที่ผ่านมา)</h4>
      <span class="popular-top-label">Top 5</span>
    </div>

    <div class="popular-list">
      <?php
        $rank = 1;
        $hasPopularJobs = false;
        while($category_data = mysqli_fetch_assoc($popular_jobs)):
          $hasPopularJobs = true;
          $rating = round($category_data['avg_rating'] ?? 0, 1);
          $category_icon = dashboard_category_icon($category_data['category'] ?? '', $category_data['category_icon'] ?? '');
      ?>
      <a href="../freelancer/browse_jobs.php?category=<?php echo urlencode($category_data['category']); ?>" class="popular-item">
        <div class="popular-top">
          <div class="popular-rank"><?php echo $rank; ?></div>
          <div class="popular-avatar" style="font-size: 24px;"><?php echo htmlspecialchars($category_icon, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="popular-name"><?php echo htmlspecialchars($category_data['category']); ?></div>
        </div>
        <div class="popular-stats">
          <span class="popular-pill"><i class="bi bi-briefcase"></i><?php echo (int)$category_data['total_jobs']; ?> งาน</span>
          <span class="popular-pill"><i class="bi bi-star-fill"></i><?php echo $rating > 0 ? $rating : '-'; ?></span>
          <span class="popular-pill"><i class="bi bi-chat-dots-fill"></i><?php echo (int)$category_data['total_reviews']; ?> รีวิว</span>
        </div>
      </a>
      <?php $rank++; endwhile; ?>

      <?php if(!$hasPopularJobs): ?>
      <div class="popular-empty">ยังไม่มีข้อมูลหมวดงานยอดนิยมในช่วง 7 วันที่ผ่านมา</div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Most Applied Jobs -->
  <section class="popular-card">
    <div class="popular-head">
      <h4><i class="bi bi-bar-chart-line"></i> ตำแหน่งงานที่มีผู้สมัครมากที่สุด</h4>
      <span class="popular-top-label">Top <?php echo $most_applied_count; ?></span>
    </div>

    <?php if(!empty($most_applied_jobs_list)): ?>
    <div class="popular-list">
      <?php
        $rank = 1;
        foreach($most_applied_jobs_list as $category_rank):
          $category = $category_rank['category'] ?? 'ไม่ระบุ';
          $category_icon = dashboard_category_icon($category, $category_rank['category_icon'] ?? '');
      ?>
      <a href="manage_jobs.php" class="popular-item">
        <div class="popular-top">
          <div class="popular-rank"><?php echo $rank; ?></div>
          <div class="popular-avatar" style="font-size: 24px;"><?php echo htmlspecialchars($category_icon, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="popular-name"><?php echo htmlspecialchars($category); ?></div>
        </div>
        <div class="popular-stats">
          <span class="popular-pill"><i class="bi bi-people-fill"></i><?php echo (int)$category_rank['applicant_count']; ?> ผู้สมัคร</span>
          <span class="popular-pill"><i class="bi bi-hourglass-split"></i><?php echo (int)$category_rank['pending_count']; ?> รอพิจารณา</span>
          <span class="popular-pill"><i class="bi bi-briefcase"></i><?php echo (int)$category_rank['total_jobs']; ?> งาน</span>
        </div>
      </a>
      <?php $rank++; endforeach; ?>
    </div>
    <?php else: ?>
      <div class="popular-empty">ยังไม่มีตำแหน่งงานที่มีผู้สมัคร</div>
    <?php endif; ?>
  </section>

  <!-- Rating summary (ถ้ามีรีวิว) -->
  <?php if($avg_rating > 0): ?>
  <div class="rating-card" style="margin-bottom:28px;">
    <div>
      <div class="rating-big"><?php echo $avg_rating; ?></div>
      <div class="rating-stars">
        <?php
          $full  = floor($avg_rating);
          $half  = ($avg_rating - $full) >= 0.5 ? 1 : 0;
          $empty = 5 - $full - $half;
          echo str_repeat('★',$full);
          if($half) echo '½';
          echo str_repeat('☆',$empty);
        ?>
      </div>
      <div class="rating-sub"><?php echo $total_review; ?> รีวิว</div>
    </div>
    <div class="rating-divider"></div>
    <div class="rating-tip">
      <strong>คะแนนจาก Freelancer</strong><br>
      คะแนนนี้จะแสดงบนหน้างานของคุณ<br>
      ช่วยให้ Freelancer ตัดสินใจสมัครได้ง่ายขึ้น
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent tables -->
  <div class="two-col">

    <!-- Recent Applications -->
    <div class="table-card">
      <div class="table-head">
        <h4><i class="bi bi-file-earmark-text" style="color:var(--accent);margin-right:6px;"></i>การสมัครล่าสุด</h4>
        <a href="manage_jobs.php">ดูทั้งหมด →</a>
      </div>
      <table class="mini-table">
        <thead>
          <tr><th>Freelancer</th><th>ตำแหน่ง</th><th>สถานะ</th></tr>
        </thead>
        <tbody>
        <?php
          $has = false;
          while($a = mysqli_fetch_assoc($recent_apps)):
            $has = true;
            $sc  = match(strtolower($a['status'])){ 'accepted'=>'sp-accepted','rejected'=>'sp-rejected',default=>'sp-pending' };
        ?>
          <tr>
            <td style="font-weight:500;"><?php echo htmlspecialchars($a['freelancer']); ?></td>
            <td style="color:var(--muted);font-size:12.5px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($a['title']); ?></td>
            <td><span class="status-pill <?php echo $sc; ?>"><?php echo ucfirst($a['status']); ?></span></td>
          </tr>
        <?php endwhile; ?>
        <?php if(!$has): ?>
          <tr><td colspan="3" class="empty-td">ยังไม่มีการสมัคร</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Jobs -->
    <div class="table-card">
      <div class="table-head">
        <h4><i class="bi bi-briefcase" style="color:var(--accent);margin-right:6px;"></i>งานของคุณ</h4>
        <a href="manage_jobs.php">ดูทั้งหมด →</a>
      </div>
      <table class="mini-table">
        <thead>
          <tr><th>ตำแหน่ง</th><th>Admin</th><th>วันที่</th></tr>
        </thead>
        <tbody>
        <?php
          $has2 = false;
          while($j = mysqli_fetch_assoc($recent_jobs)):
            $has2 = true;
            $sc2  = match(strtolower($j['admin_status'])){ 'approved'=>'sp-approved','rejected'=>'sp-rejected',default=>'sp-pending' };
        ?>
          <tr>
            <td style="font-weight:500;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($j['title']); ?></td>
            <td><span class="status-pill <?php echo $sc2; ?>"><?php echo ucfirst($j['admin_status']); ?></span></td>
            <td style="color:var(--muted);font-size:12px;"><?php echo !empty($j['created_at']) ? date('d M Y',strtotime($j['created_at'])) : '—'; ?></td>
          </tr>
        <?php endwhile; ?>
        <?php if(!$has2): ?>
          <tr><td colspan="3" class="empty-td">ยังไม่มีงาน — <a href="post_job.php" style="color:var(--accent);">โพสต์เลย</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

  <div class="profile-modal-overlay" id="freelancer-profile-modal">
    <div class="profile-modal">
      <div class="profile-modal-head">
        <button type="button" class="profile-modal-close" onclick="closeFreelancerProfile()"><i class="bi bi-x-lg"></i></button>
        <div class="profile-modal-avatar" id="fp-avatar"></div>
        <div>
          <div class="profile-modal-name" id="fp-name"></div>
          <div class="profile-modal-meta">
            <span id="fp-location"></span>
            <span id="fp-rating"></span>
            <span id="fp-reviews"></span>
          </div>
        </div>
      </div>
      <div class="profile-modal-body">
        <div class="profile-section">
          <div class="profile-section-title"><i class="bi bi-envelope"></i> ข้อมูลติดต่อ</div>
          <div class="profile-info-grid">
            <div class="profile-info-box"><div class="profile-info-label">Email</div><div class="profile-info-value" id="fp-email"></div></div>
            <div class="profile-info-box"><div class="profile-info-label">Phone</div><div class="profile-info-value" id="fp-phone"></div></div>
          </div>
        </div>
        <div class="profile-section">
          <div class="profile-section-title"><i class="bi bi-tools"></i> ทักษะ</div>
          <div class="profile-skills" id="fp-skills"></div>
        </div>
        <div class="profile-section">
          <div class="profile-section-title"><i class="bi bi-briefcase"></i> ประสบการณ์</div>
          <div class="profile-info-box full"><div class="profile-info-value" id="fp-experience"></div></div>
        </div>
        <div class="profile-section" id="fp-reviews-wrap">
          <div class="profile-section-title"><i class="bi bi-star"></i> ประวัติรีวิว</div>
          <div class="profile-review-list" id="fp-review-history"></div>
        </div>
        <div class="profile-section" id="fp-resume-wrap" style="display:none;">
          <div class="profile-section-title"><i class="bi bi-file-earmark-pdf"></i> Resume</div>
          <a href="#" target="_blank" class="profile-resume-link" id="fp-resume"><i class="bi bi-filetype-pdf"></i> ดู Resume PDF</a>
        </div>
      </div>
    </div>
  </div>

</main>
<script>
function renderFreelancerReviewHistory(reviews){
  const list = document.getElementById('fp-review-history');
  list.innerHTML = '';
  const reviewList = Array.isArray(reviews) ? reviews : [];

  if(!reviewList.length){
    const empty = document.createElement('div');
    empty.className = 'profile-review-empty';
    empty.textContent = 'ยังไม่มีประวัติรีวิวในช่วง 7 วันที่ผ่านมา';
    list.appendChild(empty);
    return;
  }

  reviewList.forEach(review => {
    const ratingValue = Number(review.rating || 0);
    const card = document.createElement('div');
    card.className = 'profile-review-card';

    const top = document.createElement('div');
    top.className = 'profile-review-top';

    const info = document.createElement('div');
    const employer = document.createElement('div');
    employer.className = 'profile-review-employer';
    employer.textContent = review.employer_name || 'ไม่ระบุผู้ว่าจ้าง';

    const job = document.createElement('div');
    job.className = 'profile-review-job';
    const jobIcon = document.createElement('i');
    jobIcon.className = 'bi bi-briefcase';
    const jobText = document.createElement('span');
    jobText.textContent = review.job_title || 'ไม่ระบุชื่องาน';
    job.appendChild(jobIcon);
    job.appendChild(jobText);
    info.appendChild(employer);
    info.appendChild(job);

    const rating = document.createElement('div');
    rating.className = 'profile-review-rating';
    const ratingIcon = document.createElement('i');
    ratingIcon.className = 'bi bi-star-fill';
    const ratingText = document.createElement('span');
    ratingText.textContent = ratingValue > 0 ? ratingValue.toFixed(1) : '-';
    rating.appendChild(ratingIcon);
    rating.appendChild(ratingText);

    top.appendChild(info);
    top.appendChild(rating);
    card.appendChild(top);

    if(review.comment){
      const comment = document.createElement('div');
      comment.className = 'profile-review-comment';
      comment.textContent = review.comment;
      card.appendChild(comment);
    }

    const date = document.createElement('div');
    date.className = 'profile-review-date';
    const dateIcon = document.createElement('i');
    dateIcon.className = 'bi bi-clock';
    const dateText = document.createElement('span');
    dateText.textContent = review.date || review.created_at || '';
    date.appendChild(dateIcon);
    date.appendChild(dateText);
    card.appendChild(date);

    list.appendChild(card);
  });
}

function openFreelancerProfile(data){
  const displayName = data.fullname || data.username || '-';
  const avatar = document.getElementById('fp-avatar');
  avatar.innerHTML = '';
  if (data.profile_image) {
    const img = document.createElement('img');
    img.src = data.profile_image;
    img.alt = 'Profile image';
    avatar.appendChild(img);
  } else {
    avatar.textContent = (data.username || displayName).substring(0, 2).toUpperCase();
  }
  document.getElementById('fp-name').textContent = displayName;
  document.getElementById('fp-location').textContent = data.location ? '📍 ' + data.location : '';
  document.getElementById('fp-rating').textContent = data.avg_rating > 0 ? '⭐ ' + Number(data.avg_rating).toFixed(1) : '⭐ -';
  document.getElementById('fp-reviews').textContent = (data.total_reviews || 0) + ' รีวิว';
  document.getElementById('fp-email').textContent = data.email || '-';
  document.getElementById('fp-phone').textContent = data.phone || '-';
  document.getElementById('fp-experience').textContent = data.experience || 'ยังไม่ได้ระบุ';
  renderFreelancerReviewHistory(data.reviews);

  const skills = document.getElementById('fp-skills');
  skills.innerHTML = '';
  const skillList = (data.skill || '').split(',').map(s => s.trim()).filter(Boolean);
  if(skillList.length){
    skillList.forEach(skill => {
      const span = document.createElement('span');
      span.className = 'profile-skill';
      span.textContent = skill;
      skills.appendChild(span);
    });
  } else {
    const span = document.createElement('span');
    span.style.color = 'var(--muted)';
    span.textContent = 'ยังไม่ได้ระบุ';
    skills.appendChild(span);
  }

  const resumeWrap = document.getElementById('fp-resume-wrap');
  const resumeLink = document.getElementById('fp-resume');
  const uploadsBaseUrl = <?php echo json_encode(jobfind_url('uploads/')); ?>;
  if(data.resume_file){
    resumeWrap.style.display = '';
    const resumeFile = data.resume_file.startsWith('uploads/') ? data.resume_file.substring('uploads/'.length) : data.resume_file;
    resumeLink.href = uploadsBaseUrl + resumeFile;
  } else {
    resumeWrap.style.display = 'none';
    resumeLink.href = '#';
  }

  document.getElementById('freelancer-profile-modal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeFreelancerProfile(){
  document.getElementById('freelancer-profile-modal').classList.remove('show');
  document.body.style.overflow = '';
}

document.getElementById('freelancer-profile-modal').addEventListener('click', function(e){
  if(e.target === this) closeFreelancerProfile();
});
document.addEventListener('keydown', function(e){
  if(e.key === 'Escape') closeFreelancerProfile();
});
</script>
</body>
</html>
