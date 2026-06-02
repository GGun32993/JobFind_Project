<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "location_helpers.php";
require_once "profile_image_helpers.php";

ensure_profile_image_schema($conn);

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$current_user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT profile_image FROM users WHERE user_id='$user_id'"));
$profile_image = trim($current_user['profile_image'] ?? '');
$location_summary = getFreelancerLocationSummary($conn, $user_id);
$user_location = $location_summary['label'] ?? '';

// Get location-based recommended jobs
$recommended_jobs = getRecommendedJobs($conn, $user_id, limit: 10, min_match_score: 30);
$recommend = null;

// For backwards compatibility with the rest of the code
if (!empty($recommended_jobs)) {
    // Create a temporary result set from the array
    $recommend = $recommended_jobs;
}

$popular_employers = mysqli_query($conn,"
    SELECT u.user_id,
           COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS company_name,
           COALESCE(l.total_likes,0) AS total_likes,
           COALESCE(r.total_reviews,0) AS total_reviews,
           COALESCE(r.avg_rating,0) AS avg_rating,
           COALESCE(j.total_jobs,0) AS total_jobs
    FROM users u
    LEFT JOIN employer_profile ep ON ep.user_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_likes
        FROM like_employer
        GROUP BY employer_id
    ) l ON l.employer_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating
        FROM employer_review
        GROUP BY employer_id
    ) r ON r.employer_id = u.user_id
    LEFT JOIN (
        SELECT employer_id, COUNT(*) AS total_jobs
        FROM job
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
           (SELECT file_name FROM resume WHERE freelancer_id=u.user_id ORDER BY resume_id DESC LIMIT 1) AS resume_file,
           COALESCE(fr.total_reviews, 0) AS total_reviews,
           COALESCE(fr.avg_rating, 0) AS avg_rating
    FROM users u
    LEFT JOIN freelancer_profile fp ON fp.user_id = u.user_id
    LEFT JOIN (
        SELECT freelancer_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating
        FROM freelancer_review
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY freelancer_id
    ) fr ON fr.freelancer_id = u.user_id
    WHERE u.role='freelancer'
    AND COALESCE(fr.total_reviews, 0) > 0
    ORDER BY avg_rating DESC, total_reviews DESC
    LIMIT 7
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
        FROM freelancer_review fr
        LEFT JOIN users u ON u.user_id = fr.employer_id
        LEFT JOIN employer_profile ep ON ep.user_id = fr.employer_id
        LEFT JOIN job j ON j.job_id = fr.job_id
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

// ── Popular Categories - Rating ในช่วง 7 วัน ──
$popular_jobs = mysqli_query($conn,"
    SELECT COALESCE(j.category, 'ไม่ระบุ') AS category,
           COUNT(j.job_id) AS total_jobs,
           COALESCE(jr.total_reviews, 0) AS total_reviews,
           COALESCE(jr.avg_rating, 0) AS avg_rating
    FROM job j
    LEFT JOIN (
        SELECT job_id, 
                COUNT(*) AS total_reviews, 
                AVG(rating) AS avg_rating
        FROM freelancer_review
        WHERE job_id IS NOT NULL 
        AND job_id > 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY job_id
        UNION ALL
        SELECT job_id,
                COUNT(*) AS total_reviews,
                AVG(rating) AS avg_rating
        FROM employer_review
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
           COUNT(DISTINCT j.job_id) AS total_jobs,
           COUNT(ja.application_id) AS applicant_count,
           COUNT(DISTINCT ja.freelancer_id) AS freelancer_count,
           MAX(j.created_at) AS latest_job_at
    FROM job j
    JOIN job_application ja ON ja.job_id = j.job_id
    WHERE j.admin_status='approved'
    GROUP BY COALESCE(NULLIF(j.category,''), 'ไม่ระบุ')
    ORDER BY applicant_count DESC, total_jobs DESC, latest_job_at DESC
    LIMIT 5
");

$most_applied_jobs_list = [];
if($most_applied_jobs){
    while($row = mysqli_fetch_assoc($most_applied_jobs)){
        $row['applicant_count'] = (int)$row['applicant_count'];
        $row['total_jobs'] = (int)$row['total_jobs'];
        $row['freelancer_count'] = (int)$row['freelancer_count'];
        $most_applied_jobs_list[] = $row;
    }
}

$recommended_count = is_array($recommend) ? count($recommend) : 0;
$popular_employer_count = $popular_employers ? mysqli_num_rows($popular_employers) : 0;
$top_freelancer_count = $top_freelancers ? mysqli_num_rows($top_freelancers) : 0;
$popular_job_count = $popular_jobs ? mysqli_num_rows($popular_jobs) : 0;
$most_applied_count = count($most_applied_jobs_list);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=9">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Freelancer Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:   #0f172a;
    --navy2:  #1e293b;
    --navy3:  #334155;
    --accent: #6366f1;
    --accent2:#818cf8;
    --light:  #f1f5f9;
    --white:  #ffffff;
    --text:   #0f172a;
    --muted:  #64748b;
    --border: #e2e8f0;
    --green:  #10b981;
    --yellow: #f59e0b;
    --red:    #ef4444;
    --radius: 14px;
  }

  body {
    font-family: 'Sora', sans-serif;
    background: var(--light);
    color: var(--text);
    display: flex;
    min-height: 100vh;
  }

  /* ── Sidebar ── */
  .sidebar {
    width: 240px;
    min-height: 100vh;
    background: var(--navy);
    display: flex;
    flex-direction: column;
    padding: 28px 0;
    position: fixed;
    top: 0; left: 0;
    z-index: 100;
  }

  .sidebar-brand {
    padding: 0 24px 28px;
    border-bottom: 1px solid var(--navy3);
  }
  .sidebar-brand .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
  }
  .sidebar-brand .logo-icon {
    width: 36px; height: 36px;
    background: var(--accent);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px;
  }
  .sidebar-brand .logo-text {
    font-size: 15px; font-weight: 600;
    color: #fff; line-height: 1.2;
  }
  .sidebar-brand .logo-sub {
    font-size: 11px; color: var(--navy3);
    font-weight: 400;
  }

  .sidebar-nav {
    padding: 20px 12px;
    flex: 1;
    display: flex; flex-direction: column; gap: 4px;
  }
  .nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 13.5px; font-weight: 500;
    transition: background .15s, color .15s;
  }
  .nav-item:hover { background: var(--navy2); color: #e2e8f0; }
  .nav-item.active { background: var(--accent); color: #fff; }
  .nav-item i { font-size: 17px; width: 20px; text-align: center; }

  .nav-divider {
    height: 1px; background: var(--navy3);
    margin: 10px 14px;
  }

  .sidebar-footer {
    padding: 16px 12px 0;
  }
  .nav-logout {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    color: #f87171;
    text-decoration: none;
    font-size: 13.5px; font-weight: 500;
    transition: background .15s;
  }
  .nav-logout:hover { background: rgba(239,68,68,.12); }
  .nav-logout i { font-size: 17px; }

  /* ── Main ── */
  .main {
    margin-left: 240px;
    flex: 1;
    padding: 32px 44px 48px;
    min-height: 100vh;
  }

  /* ── Topbar ── */
  .topbar {
    position: relative;
    display: flex; align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
    padding: 18px 22px;
    background: var(--navy);
    color: #fff;
    border: 1px solid rgba(148,163,184,.2);
    border-radius: 18px;
    box-shadow: 0 16px 34px rgba(15,23,42,.14);
    overflow: hidden;
  }
  .topbar::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 4px;
    background: var(--accent);
  }
  .topbar-greeting {
    position: relative;
    min-width: 0;
  }
  .topbar-greeting h2 {
    font-size: 22px; font-weight: 700;
    color: #fff;
    margin-bottom: 5px;
  }
  .topbar-greeting p {
    font-size: 13px; color: #cbd5e1; margin-top: 0;
    max-width: 640px;
  }
  .topbar-kicker {
    display: inline-flex; align-items: center; gap: 7px;
    color: #c7d2fe;
    font-size: 12px; font-weight: 700;
    margin-bottom: 6px;
  }
  .topbar-meta {
    display: flex; flex-wrap: wrap; gap: 8px;
    margin-top: 11px;
  }
  .topbar-chip {
    display: inline-flex; align-items: center; gap: 6px;
    min-height: 28px;
    padding: 5px 10px;
    border: 1px solid rgba(199,210,254,.22);
    border-radius: 999px;
    background: rgba(255,255,255,.08);
    color: #e2e8f0;
    font-size: 12px; font-weight: 600;
  }
  .topbar-chip i { color: #c7d2fe; font-size: 13px; }
  .topbar-side {
    position: relative;
    display: flex; align-items: center; gap: 10px;
    flex-shrink: 0;
  }
  .hero-action {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    min-height: 36px;
    padding: 8px 14px;
    border-radius: 10px;
    border: 1px solid rgba(199,210,254,.26);
    background: rgba(255,255,255,.08);
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: background .15s, border-color .15s, transform .1s;
  }
  .hero-action:hover {
    color: #fff;
    background: rgba(255,255,255,.14);
    border-color: rgba(199,210,254,.46);
    transform: translateY(-1px);
  }
  .hero-action.primary {
    background: var(--accent);
    border-color: var(--accent);
    box-shadow: 0 10px 24px rgba(99,102,241,.26);
  }
  .hero-action.primary:hover { background: #4f46e5; border-color: #4f46e5; }
  .topbar-avatar {
    width: 42px; height: 42px;
    border-radius: 14px;
    background: var(--accent);
    color: #fff; font-weight: 600; font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    border: 1px solid rgba(255,255,255,.22);
    overflow: hidden;
  }
  .topbar-avatar img { width:100%; height:100%; object-fit:cover; display:block; }

  /* ── Section header ── */
  .section-header {
    display: flex; align-items: center; justify-content: space-between; gap: 14px;
    margin: 8px 0 14px;
  }
  .section-header h4 {
    font-size: 17px; font-weight: 700;
    margin: 0;
  }
  .section-header p {
    color: var(--muted);
    font-size: 12.5px;
    margin: 3px 0 0;
  }
  .badge-count {
    display: inline-flex; align-items: center; gap: 6px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    color: var(--accent);
    font-size: 12px; font-weight: 700;
    padding: 7px 11px;
    border-radius: 999px;
  }

  /* ── Job card ── */
  .job-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px 24px;
    margin-bottom: 12px;
    display: flex; gap: 18px;
    transition: box-shadow .2s, border-color .2s;
  }
  .job-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,.07);
    border-color: #c7d2fe;
  }

  .job-logo {
    width: 52px; height: 52px; flex-shrink: 0;
    border-radius: 12px; background: var(--light);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    overflow: hidden;
  }
  .job-logo.has-image { background: var(--white); }
  .job-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }

  .job-body { flex: 1; min-width: 0; }
  .job-top  { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
  .job-title { font-size: 15px; font-weight: 600; margin-bottom: 3px; }
  .job-desc {
    font-size: 13px; color: var(--muted);
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
    margin: 6px 0 10px;
    line-height: 1.6;
  }
  .job-meta {
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
    font-size: 12.5px; color: var(--muted);
  }
  .job-meta span { display: flex; align-items: center; gap: 4px; }
  .job-meta i { font-size: 13px; }

  .tag {
    display: inline-block;
    font-size: 11px; font-weight: 600;
    padding: 3px 10px; border-radius: 20px; margin-top: 10px; margin-right: 4px;
  }
  .tag-cat { background: #eef2ff; color: var(--accent); }
  .tag-status { background: #d1fae5; color: #065f46; }
  .tag-match { display: inline-flex; align-items: center; gap: 5px; }
  .stars { color: var(--yellow); font-size: 13px; }

  .btn-apply {
    flex-shrink: 0; align-self: center;
    background: var(--accent); color: #fff;
    border: none; border-radius: 10px;
    padding: 10px 22px;
    font-size: 13px; font-weight: 600;
    text-decoration: none; white-space: nowrap;
    transition: background .15s, transform .1s;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .btn-apply:hover {
    background: #4f46e5; color: #fff;
    transform: translateY(-1px);
  }
  .btn-detail {
    flex-shrink: 0; align-self: center;
    background: var(--white); color: var(--text);
    border: 1px solid var(--border); border-radius: 10px;
    padding: 10px 22px; font-size: 13px; font-weight: 600;
    text-decoration: none; white-space: nowrap;
    transition: background .15s, transform .1s;
    display: inline-flex; align-items: center; gap: 6px;
    margin-right: 8px;
  }
  .btn-detail:hover { background: var(--light); color: var(--text); }

  .empty-state {
    text-align: center; padding: 48px 20px;
    color: var(--muted);
  }
  .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; }
  .empty-state p { font-size: 14px; }

  .popular-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: 0 10px 26px rgba(15,23,42,.04);
  }
  .popular-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; margin-bottom: 14px;
  }
  .popular-head h4 { font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 9px; margin: 0; }
  .popular-head h4 i {
    width: 30px; height: 30px;
    border-radius: 10px;
    background: #eef2ff;
    color: var(--accent);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 15px;
  }
  .popular-top-label {
    font-size: 11px; font-weight: 700; color: var(--accent);
    background: #eef2ff; border: 1px solid #c7d2fe;
    padding: 4px 10px; border-radius: 999px;
  }
  .popular-list { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
  .popular-item {
    display: flex; flex-direction: column; justify-content: space-between; gap: 11px;
    width: 100%;
    min-width: 0; min-height: 108px; padding: 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text); text-decoration: none;
    background: #fff;
    font-family:'Sora',sans-serif;
    text-align:left;
    cursor:pointer;
    transition: border-color .15s, box-shadow .2s, transform .15s;
  }
  .popular-item:hover { color: var(--text); border-color: #c7d2fe; box-shadow: 0 12px 24px rgba(99,102,241,.12); transform: translateY(-2px); }
  .popular-item:focus-visible { outline: 3px solid rgba(99,102,241,.24); outline-offset: 2px; }
  .popular-top { display: flex; align-items: center; gap: 10px; min-width: 0; }
  .popular-rank {
    width: 28px; height: 28px; border-radius: 9px;
    background: var(--accent); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; flex-shrink: 0;
  }
  .popular-avatar {
    width: 36px; height: 36px; border-radius: 11px;
    background: var(--light); color: var(--accent);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0; overflow:hidden;
  }
  .popular-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
  .popular-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .popular-stats { display: flex; flex-wrap: wrap; gap: 6px; }
  .popular-pill {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600; color: var(--muted);
    background: var(--light); border: 1px solid transparent; border-radius: 999px;
    padding: 4px 8px;
  }
  .popular-pill i { color: var(--accent); font-size: 12px; }
  .popular-empty { grid-column: 1 / -1; text-align: center; color: var(--muted); padding: 24px; font-size: 13px; }

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

  @media(max-width: 1200px){
    .popular-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .topbar { align-items: flex-start; }
    .topbar-side { flex-wrap: wrap; justify-content: flex-end; }
  }
  @media(max-width: 768px){
    .sidebar { display: none; }
    .main { margin-left: 0; padding: 20px 16px; }
    .topbar { flex-direction: column; align-items: stretch; padding: 20px; }
    .topbar-side { justify-content: flex-start; }
    .hero-action { flex: 1 1 130px; }
    .topbar-avatar { margin-left: auto; }
    .section-header { align-items: flex-start; }
    .job-card { flex-wrap: wrap; }
    .btn-detail,.btn-apply { width: 100%; justify-content: center; margin-top: 8px; margin-right: 0; }
    .popular-list,.profile-info-grid { grid-template-columns: 1fr; }
    .profile-info-box.full { grid-column:auto; }
  }
</style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="index.php" class="logo">
      <div class="logo-icon" style="width:160px!important;height:240px!important;min-width:160px!important;max-width:160px!important;max-height:240px!important;flex:0 0 240px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=9" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Job_Find</div>
        <div class="logo-sub" style="display:none!important;">Freelancer</div>
      </div>
    </a>
  </div>

  <nav class="sidebar-nav">
    <a href="freelancer_dashboard.php" class="nav-item active">
      <i class="bi bi-grid"></i> Dashboard
    </a>
    <a href="browse_jobs.php" class="nav-item">
      <i class="bi bi-briefcase"></i> Browse Jobs
    </a>
    <a href="my_applications.php" class="nav-item">
      <i class="bi bi-file-earmark-text"></i> My Applications
    </a>
    <a href="my_profile.php" class="nav-item">
      <i class="bi bi-person-circle"></i> My Profile
    </a>
    <a href="freelancer_reviews.php" class="nav-item">
      <i class="bi bi-star"></i> My Reviews
    </a>
    <a href="upload_resume.php" class="nav-item">
      <i class="bi bi-cloud-upload"></i> Upload Resume
    </a>

    <div class="nav-divider"></div>

    <a href="support_messages.php" class="nav-item">
      <i class="bi bi-chat-dots"></i> Support Chat
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="nav-logout">
      <i class="bi bi-box-arrow-left"></i> Logout
    </a>
  </div>
</aside>

<!-- ── Main Content ── -->
<main class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-greeting">
      <div class="topbar-kicker"><i class="bi bi-stars"></i> Freelancer Workspace</div>
      <h2>Welcome back, <?php echo htmlspecialchars($username); ?></h2>
      <p>งานแนะนำและอันดับยอดนิยมที่อัปเดตจากกิจกรรมล่าสุดของแพลตฟอร์ม</p>
      <div class="topbar-meta">
        <span class="topbar-chip"><i class="bi bi-geo-alt"></i><?php echo $user_location ? htmlspecialchars($user_location) : 'ยังไม่ได้ระบุพื้นที่'; ?></span>
        <span class="topbar-chip"><i class="bi bi-briefcase"></i><?php echo $recommended_count; ?> งานแนะนำ</span>
        <span class="topbar-chip"><i class="bi bi-building"></i><?php echo $popular_employer_count; ?> ผู้ว่าจ้างเด่น</span>
      </div>
    </div>
    <div class="topbar-side">
      <a href="browse_jobs.php" class="hero-action primary"><i class="bi bi-search"></i> Browse Jobs</a>
      <a href="my_profile.php" class="hero-action"><i class="bi bi-person"></i> Profile</a>
      <div class="topbar-avatar" title="<?php echo htmlspecialchars($username); ?>">
        <?php if($profile_image !== ''): ?>
          <img src="<?php echo profile_image_src($profile_image); ?>" alt="Profile image">
        <?php else: ?>
          <?php echo profile_initials($username); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recommended Jobs -->
  <div class="section-header">
    <div>
      <h4>งานที่แนะนำสำหรับคุณ</h4>
      <p>งานที่ตรงกับพื้นที่และตำแหน่งของคุณ</p>
    </div>
    <span class="badge-count"><i class="bi bi-briefcase"></i><?php echo $recommended_count; ?> งาน</span>
  </div>

  <?php
    $hasJobs = false;
    if (is_array($recommend)) {
      foreach ($recommend as $job):
        $hasJobs = true;
        $icons = ['💼','🖥️','📐','📊','🚀','🎨','⚙️','📱','✍️','📢','🎓','💰'];
        $icon  = $icons[crc32($job['title'] ?? 'job') % count($icons)];
        $match_score = $job['match_score'] ?? 0;
        $distance_km = $job['distance_km'] ?? null;
        $job_image = trim($job['image_path'] ?? '');
        $cat = trim($job['category'] ?? '');
        $status = trim($job['status'] ?? '');
        if ($status === '') {
          $status = 'open';
        }
        $avg_rating = round((float)($job['avg_rating'] ?? 0), 1);
        $total_reviews = (int)($job['total_reviews'] ?? 0);
        $stars = '';
        if ($avg_rating > 0) {
          $full = floor($avg_rating);
          $half = ($avg_rating - $full) >= 0.5 ? 1 : 0;
          $empty = 5 - $full - $half;
          $stars .= str_repeat('<i class="bi bi-star-fill"></i>', $full);
          if ($half) $stars .= '<i class="bi bi-star-half"></i>';
          $stars .= str_repeat('<i class="bi bi-star"></i>', $empty);
        }

        // Determine match badge style
        $match_label = 'Nearby Match';
        $match_style = 'background: #eef2ff; color: #4f46e5;';
        if ($match_score >= 95) {
          $match_label = 'Perfect Match';
          $match_style = 'background: #d1fae5; color: #059669;';
        } elseif ($match_score >= 85) {
          $match_label = 'Great Match';
          $match_style = 'background: #fef3c7; color: #d97706;';
        } elseif ($match_score >= 70) {
          $match_label = 'Good Match';
          $match_style = 'background: #dbeafe; color: #1d4ed8;';
        }
  ?>
  <div class="job-card">
    <div class="job-logo <?php echo $job_image !== '' ? 'has-image' : ''; ?>">
      <?php if($job_image !== ''): ?>
      <img src="<?php echo htmlspecialchars($job_image); ?>" alt="<?php echo htmlspecialchars($job['title'] ?? 'Untitled Job'); ?>">
      <?php else: ?>
      <?php echo $icon; ?>
      <?php endif; ?>
    </div>

    <div class="job-body">
      <div class="job-top">
        <div>
          <div class="job-title"><?php echo htmlspecialchars($job['title'] ?? 'Untitled Job'); ?></div>
          <?php if($cat !== ''): ?>
          <span class="tag tag-cat"><i class="bi bi-tag"></i> <?php echo htmlspecialchars($cat); ?></span>
          <?php endif; ?>
          <span class="tag tag-status"><?php echo htmlspecialchars($status); ?></span>
          <span class="tag tag-match" style="<?php echo $match_style; ?>">
            <i class="bi bi-hand-thumbs-up"></i> <?php echo $match_label; ?> (<?php echo (int)$match_score; ?>%)
          </span>
        </div>
      </div>

      <p class="job-desc"><?php echo htmlspecialchars($job['description'] ?? ''); ?></p>

      <div class="job-meta">
        <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($job['location'] ?? 'ไม่ระบุ'); ?></span>
        <span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($job['salary'] ?? '0'); ?></span>
        <?php if (!empty($job['company_name'])): ?>
        <span><i class="bi bi-briefcase"></i><?php echo htmlspecialchars($job['company_name']); ?></span>
        <?php endif; ?>
        <?php if (!empty($job['created_at'])): ?>
        <span><i class="bi bi-clock"></i><?php echo date('d M Y', strtotime($job['created_at'])); ?></span>
        <?php endif; ?>
        <?php if ($distance_km !== null): ?>
        <span><i class="bi bi-pin"></i><?php echo round($distance_km, 1); ?> กม.</span>
        <?php endif; ?>
        <span>
          <?php if($avg_rating > 0): ?>
            <span class="stars"><?php echo $stars; ?></span>
            <?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)
          <?php else: ?>
            <i class="bi bi-star" style="color:var(--yellow)"></i> No rating yet
          <?php endif; ?>
        </span>
      </div>
    </div>

    <a href="view_job.php?job_id=<?php echo (int)$job['job_id']; ?>" class="btn-detail">
      <i class="bi bi-info-circle"></i> Detail
    </a>

    <a href="apply_job.php?job_id=<?php echo (int)$job['job_id']; ?>" class="btn-apply">
      <i class="bi bi-send"></i> Apply
    </a>
  </div>
  <?php
      endforeach;
    }
  ?>

  <?php if (!$hasJobs): ?>
  <div class="empty-state">
    <i class="bi bi-inbox"></i>
    <p>ยังไม่พบงานแนะนำในขณะนี้<br>กรุณาอัปเดตข้อมูลพื้นที่ของคุณในโปรไฟล์</p>
  </div>
  <?php endif; ?>

  <!-- Popular Employers -->
  <section class="popular-card">
    <div class="popular-head">
      <h4><i class="bi bi-trophy"></i> ผู้ว่าจ้างยอดนิยม</h4>
      <span class="popular-top-label">Top <?php echo $popular_employer_count; ?></span>
    </div>

    <div class="popular-list">
      <?php
        $rank = 1;
        $hasPopular = false;
        while($emp = mysqli_fetch_assoc($popular_employers)):
          $hasPopular = true;
          $rating = round($emp['avg_rating'] ?? 0, 1);
      ?>
      <a href="employer_profile.php?employer_id=<?php echo (int)$emp['user_id']; ?>&return_url=freelancer_dashboard.php" class="popular-item">
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
      <span class="popular-top-label">Top <?php echo $top_freelancer_count; ?></span>
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
      <span class="popular-top-label">Top <?php echo $popular_job_count; ?></span>
    </div>

    <div class="popular-list">
      <?php
        $rank = 1;
        $hasPopularJobs = false;
        $categoryIcons = ['IT' => '💻', 'Design' => '🎨', 'Marketing' => '📢', 'Accounting' => '💰'];
        while($category_data = mysqli_fetch_assoc($popular_jobs)):
          $hasPopularJobs = true;
          $rating = round($category_data['avg_rating'] ?? 0, 1);
          $category_icon = $categoryIcons[$category_data['category']] ?? '📁';
      ?>
      <a href="browse_jobs.php?category=<?php echo urlencode($category_data['category']); ?>" class="popular-item">
        <div class="popular-top">
          <div class="popular-rank"><?php echo $rank; ?></div>
          <div class="popular-avatar" style="font-size: 24px;"><?php echo $category_icon; ?></div>
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
        $categoryIcons = ['IT' => '💻', 'Design' => '🎨', 'Marketing' => '📢', 'Accounting' => '💰', 'Programmer' => '💻', 'Data Analyst' => '📊', 'Cyber Security' => '🛡️', 'AI Engineer' => '🤖'];
        foreach($most_applied_jobs_list as $category_rank):
          $category = $category_rank['category'] ?? 'ไม่ระบุ';
          $categoryUrl = ($category !== 'ไม่ระบุ')
              ? 'browse_jobs.php?category=' . urlencode($category)
              : 'browse_jobs.php';
          $category_icon = $categoryIcons[$category] ?? '📁';
      ?>
      <a href="<?php echo htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8'); ?>" class="popular-item">
        <div class="popular-top">
          <div class="popular-rank"><?php echo $rank; ?></div>
          <div class="popular-avatar" style="font-size: 24px;"><?php echo $category_icon; ?></div>
          <div class="popular-name"><?php echo htmlspecialchars($category); ?></div>
        </div>
        <div class="popular-stats">
          <span class="popular-pill"><i class="bi bi-people-fill"></i><?php echo (int)$category_rank['applicant_count']; ?> ผู้สมัคร</span>
          <span class="popular-pill"><i class="bi bi-briefcase"></i><?php echo (int)$category_rank['total_jobs']; ?> งาน</span>
          <span class="popular-pill"><i class="bi bi-person-check-fill"></i><?php echo (int)$category_rank['freelancer_count']; ?> คน</span>
        </div>
      </a>
      <?php $rank++; endforeach; ?>
    </div>
    <?php else: ?>
      <div class="popular-empty">ยังไม่มีข้อมูลการสมัครงาน</div>
    <?php endif; ?>
  </section>

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
  if(data.resume_file){
    resumeWrap.style.display = '';
    resumeLink.href = data.resume_file.startsWith('uploads/') ? data.resume_file : 'uploads/' + data.resume_file;
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
