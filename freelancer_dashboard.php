<?php
session_start();
include("config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];

// get location
$profile = mysqli_query($conn,"
    SELECT location
    FROM freelancer_profile
    WHERE user_id='$user_id'
");
$data = mysqli_fetch_assoc($profile);
$user_location = "";
if($data){ $user_location = $data['location']; }

// recommend jobs
if($user_location != ""){
    $recommend = mysqli_query($conn,"
        SELECT * FROM job
        WHERE location LIKE '%$user_location%'
        AND status='approved'
        ORDER BY created_at DESC
        LIMIT 5
    ");
} else {
    $recommend = mysqli_query($conn,"
        SELECT * FROM job
        WHERE status='approved'
        ORDER BY created_at DESC
        LIMIT 5
    ");
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
    SELECT j.job_id,
           j.title,
           j.category,
           j.location,
           j.salary,
           COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS company_name,
           COUNT(ja.application_id) AS applicant_count
    FROM job j
    JOIN job_application ja ON ja.job_id = j.job_id
    LEFT JOIN users u ON u.user_id = j.employer_id
    LEFT JOIN employer_profile ep ON ep.user_id = j.employer_id
    WHERE j.admin_status='approved'
    GROUP BY j.job_id, j.title, j.category, j.location, j.salary, company_name
    ORDER BY applicant_count DESC, j.created_at DESC
    LIMIT 5
");

$most_applied_jobs_list = [];
$max_applicants = 0;
if($most_applied_jobs){
    while($row = mysqli_fetch_assoc($most_applied_jobs)){
        $row['applicant_count'] = (int)$row['applicant_count'];
        $max_applicants = max($max_applicants, $row['applicant_count']);
        $most_applied_jobs_list[] = $row;
    }
}

$recommended_count = $recommend ? mysqli_num_rows($recommend) : 0;
$popular_employer_count = $popular_employers ? mysqli_num_rows($popular_employers) : 0;
$top_freelancer_count = $top_freelancers ? mysqli_num_rows($top_freelancers) : 0;
$popular_job_count = $popular_jobs ? mysqli_num_rows($popular_jobs) : 0;
$most_applied_count = count($most_applied_jobs_list);
?>
<!DOCTYPE html>
<html lang="th">
<head>
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
  }

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
    border-radius: 16px;
    padding: 18px 20px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 20px rgba(15,23,42,.035);
    transition: box-shadow .2s, border-color .2s, transform .15s;
  }
  .job-card:hover {
    box-shadow: 0 14px 32px rgba(15,23,42,.08);
    border-color: #c7d2fe;
    transform: translateY(-1px);
  }

  .job-logo {
    width: 50px; height: 50px; flex-shrink: 0;
    border-radius: 12px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
  }

  .job-info { flex: 1; min-width: 0; }
  .job-title {
    font-size: 15px; font-weight: 600;
    color: var(--text); margin-bottom: 4px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .job-meta {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    font-size: 12.5px; color: var(--muted);
  }
  .job-meta span { display: flex; align-items: center; gap: 4px; }
  .job-meta i { font-size: 13px; }

  .tag {
    display: inline-flex; align-items: center; gap: 5px;
    background: #eef2ff;
    color: var(--accent);
    font-size: 11px; font-weight: 600;
    padding: 3px 10px; border-radius: 20px;
    margin-top: 8px;
  }

  .btn-apply {
    flex-shrink: 0;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 9px 20px;
    font-size: 13px; font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: background .15s, transform .1s;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .btn-apply:hover {
    background: #4f46e5; color: #fff;
    transform: translateY(-1px);
  }

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
    font-size: 17px; flex-shrink: 0;
  }
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

  .application-rank-list { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
  .application-rank-item {
    display: flex; flex-direction: column; justify-content: space-between; gap: 12px;
    min-width: 0; min-height: 142px; padding: 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    color: var(--text);
    text-decoration: none;
    cursor: pointer;
    transition: border-color .15s, box-shadow .2s, transform .15s;
  }
  .application-rank-item:hover { color: var(--text); border-color: #c7d2fe; box-shadow: 0 12px 24px rgba(99,102,241,.12); transform: translateY(-2px); }
  .application-rank-top { display: flex; gap: 10px; min-width: 0; }
  .application-rank-no {
    width: 30px; height: 30px; border-radius: 10px;
    background: var(--accent); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800; flex-shrink: 0;
  }
  .application-rank-title { font-size: 13.5px; font-weight: 700; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .application-rank-company { font-size: 11.5px; color: var(--muted); margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .application-rank-meta { display: flex; flex-wrap: wrap; gap: 6px; }
  .application-rank-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--light);
    color: var(--muted);
    font-size: 11px; font-weight: 700;
  }
  .application-rank-pill i { color: var(--accent); font-size: 12px; }
  .application-rank-category { color: var(--accent); background: #eef2ff; border-color: #c7d2fe; }
  .application-rank-progress { height: 6px; border-radius: 999px; background: var(--light); overflow: hidden; }
  .application-rank-progress span { display: block; height: 100%; border-radius: inherit; background: var(--accent); }

  .profile-modal-overlay { display:none; position:fixed; inset:0; z-index:300; background:rgba(15,23,42,.62); align-items:center; justify-content:center; padding:20px; }
  .profile-modal-overlay.show { display:flex; }
  .profile-modal { width:min(680px,100%); max-height:90vh; overflow:auto; background:var(--white); border-radius:20px; box-shadow:0 24px 70px rgba(15,23,42,.28); }
  .profile-modal-head { position:relative; display:flex; gap:18px; align-items:flex-start; padding:28px; border-radius:20px 20px 0 0; background:var(--navy); color:#fff; }
  .profile-modal-close { position:absolute; top:16px; right:16px; width:34px; height:34px; border:0; border-radius:50%; background:rgba(255,255,255,.14); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; }
  .profile-modal-close:hover { background:rgba(255,255,255,.24); }
  .profile-modal-avatar { width:64px; height:64px; border-radius:50%; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:700; border:3px solid rgba(255,255,255,.22); flex-shrink:0; }
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

  @media(max-width: 1200px){
    .popular-list,.application-rank-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
    .btn-apply { width: 100%; justify-content: center; margin-top: 8px; }
    .popular-list,.application-rank-list,.profile-info-grid { grid-template-columns: 1fr; }
    .profile-info-box.full { grid-column:auto; }
  }
</style>
</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="#" class="logo">
      <div class="logo-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div>
        <div class="logo-text">FreelanceHub</div>
        <div class="logo-sub">Dashboard</div>
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

    <a href="support_chat.php" class="nav-item">
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
        <?php echo strtoupper(substr($username, 0, 1)); ?>
      </div>
    </div>
  </div>

  <!-- Most Applied Jobs -->
  <section class="popular-card">
    <div class="popular-head">
      <h4><i class="bi bi-bar-chart-line"></i> ตำแหน่งงานที่มีผู้สมัครมากที่สุด</h4>
      <span class="popular-top-label">Top <?php echo $most_applied_count; ?></span>
    </div>

    <?php if(!empty($most_applied_jobs_list)): ?>
    <div class="application-rank-list">
      <?php foreach($most_applied_jobs_list as $index => $job_rank):
        $percent = $max_applicants > 0 ? max(12, round(($job_rank['applicant_count'] / $max_applicants) * 100)) : 0;
        $categoryUrl = (!empty($job_rank['category']) && $job_rank['category'] !== 'ไม่ระบุ')
            ? 'browse_jobs.php?category=' . urlencode($job_rank['category'])
            : 'browse_jobs.php';
      ?>
      <a href="<?php echo htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8'); ?>" class="application-rank-item">
        <div class="application-rank-top">
          <div class="application-rank-no"><?php echo $index + 1; ?></div>
          <div style="min-width:0;">
            <div class="application-rank-title"><?php echo htmlspecialchars($job_rank['title']); ?></div>
            <div class="application-rank-company"><?php echo htmlspecialchars($job_rank['company_name'] ?? 'ไม่ระบุผู้ว่าจ้าง'); ?></div>
          </div>
        </div>
        <div class="application-rank-meta">
          <span class="application-rank-pill"><i class="bi bi-people-fill"></i><?php echo (int)$job_rank['applicant_count']; ?> ผู้สมัคร</span>
          <?php if(!empty($job_rank['category']) && $job_rank['category'] !== 'ไม่ระบุ'): ?>
          <span class="application-rank-pill application-rank-category"><i class="bi bi-tag"></i><?php echo htmlspecialchars($job_rank['category']); ?></span>
          <?php endif; ?>
          <?php if(!empty($job_rank['location'])): ?><span class="application-rank-pill"><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($job_rank['location']); ?></span><?php endif; ?>
        </div>
        <div class="application-rank-progress"><span style="width:<?php echo $percent; ?>%;"></span></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="popular-empty">ยังไม่มีข้อมูลการสมัครงาน</div>
    <?php endif; ?>
  </section>

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
          $profileData = [
            'user_id' => (int)$freelancer['user_id'],
            'username' => $freelancer['username'] ?? '',
            'fullname' => $freelancer['fullname'] ?? '',
            'email' => $freelancer['email'] ?? '',
            'phone' => $freelancer['phone'] ?? '',
            'location' => $freelancer['location'] ?? '',
            'skill' => $freelancer['skill'] ?? '',
            'experience' => $freelancer['experience'] ?? '',
            'resume_file' => $freelancer['resume_file'] ?? '',
            'avg_rating' => $rating,
            'total_reviews' => (int)$freelancer['total_reviews'],
          ];
      ?>
      <button type="button" class="popular-item" onclick='openFreelancerProfile(<?php echo htmlspecialchars(json_encode($profileData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), ENT_QUOTES, "UTF-8"); ?>)'>
        <div class="popular-top">
          <div class="popular-rank"><?php echo $rank; ?></div>
          <div class="popular-avatar"><i class="bi bi-person-circle"></i></div>
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

  <!-- Recommended Jobs -->
  <div class="section-header">
    <div>
      <h4>งานที่แนะนำสำหรับคุณ</h4>
      <p>เลือกจากตำแหน่งและงานล่าสุดที่เปิดรับสมัคร</p>
    </div>
    <span class="badge-count"><i class="bi bi-briefcase"></i><?php echo $recommended_count; ?> งาน</span>
  </div>

  <?php
    $hasJobs = false;
    while($job = mysqli_fetch_assoc($recommend)):
      $hasJobs = true;
      $icons = ['💼','🖥️','📐','📊','🚀','🎨','⚙️','📱'];
      $icon  = $icons[crc32($job['title']) % count($icons)];
  ?>
  <div class="job-card">
    <div class="job-logo"><?php echo $icon; ?></div>

    <div class="job-info">
      <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
      <div class="job-meta">
        <span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
        <span><i class="bi bi-currency-dollar"></i><?php echo htmlspecialchars($job['salary']); ?></span>
        <?php if(!empty($job['created_at'])): ?>
        <span><i class="bi bi-clock"></i><?php echo date('d M Y', strtotime($job['created_at'])); ?></span>
        <?php endif; ?>
      </div>
      <span class="tag"><i class="bi bi-check-circle"></i> Open</span>
    </div>

    <a href="apply_job.php?job_id=<?php echo (int)$job['job_id']; ?>" class="btn-apply">
      <i class="bi bi-send"></i> Apply
    </a>
  </div>
  <?php endwhile; ?>

  <?php if(!$hasJobs): ?>
  <div class="empty-state">
    <i class="bi bi-inbox"></i>
    <p>No recommended jobs found at the moment.<br>Try updating your profile location.</p>
  </div>
  <?php endif; ?>

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
        <div class="profile-section" id="fp-resume-wrap" style="display:none;">
          <div class="profile-section-title"><i class="bi bi-file-earmark-pdf"></i> Resume</div>
          <a href="#" target="_blank" class="profile-resume-link" id="fp-resume"><i class="bi bi-filetype-pdf"></i> ดู Resume PDF</a>
        </div>
      </div>
    </div>
  </div>

</main>

<script>
function openFreelancerProfile(data){
  const displayName = data.fullname || data.username || '-';
  document.getElementById('fp-avatar').textContent = (data.username || displayName).substring(0, 2).toUpperCase();
  document.getElementById('fp-name').textContent = displayName;
  document.getElementById('fp-location').textContent = data.location ? '📍 ' + data.location : '';
  document.getElementById('fp-rating').textContent = data.avg_rating > 0 ? '⭐ ' + Number(data.avg_rating).toFixed(1) : '⭐ -';
  document.getElementById('fp-reviews').textContent = (data.total_reviews || 0) + ' รีวิว';
  document.getElementById('fp-email').textContent = data.email || '-';
  document.getElementById('fp-phone').textContent = data.phone || '-';
  document.getElementById('fp-experience').textContent = data.experience || 'ยังไม่ได้ระบุ';

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
