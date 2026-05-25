<?php
define('JOBFIND_ALLOW_DB_FAILURE', true);
require_once __DIR__ . "/config.php";

$titleSearch = trim($_GET['title'] ?? '');
$locationSearch = trim($_GET['location'] ?? '');
$dbError = $conn ? '' : ($db_error ?: 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้ (Database unavailable).');

function e($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function db_fetch_all($conn, $sql, $types = '', $params = []){
    $rows = [];
    $stmt = mysqli_prepare($conn, $sql);

    if(!$stmt){
        error_log("Query prepare failed: " . mysqli_error($conn));
        return $rows;
    }

    if($types !== ''){
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if(!mysqli_stmt_execute($stmt)){
        error_log("Query execute failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return $rows;
    }

    $result = mysqli_stmt_get_result($stmt);
    if($result){
        while($row = mysqli_fetch_assoc($result)){
            $rows[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function db_count($conn, $sql){
    $result = mysqli_query($conn, $sql);
    if(!$result){
        error_log("Count query failed: " . mysqli_error($conn));
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int)($row['c'] ?? 0);
}

function initials($name){
    $name = trim((string)$name);
    if($name === ''){
        return 'JF';
    }

    preg_match_all('/[A-Za-z0-9]/', $name, $matches);
    $letters = $matches[0] ?? [];
    if(count($letters) >= 2){
        return strtoupper($letters[0] . $letters[1]);
    }
    if(count($letters) === 1){
        return strtoupper($letters[0]);
    }

    if(function_exists('mb_substr')){
        return mb_substr($name, 0, 2, 'UTF-8');
    }

    return substr($name, 0, 2);
}

function job_status_label($status){
    $labels = [
        'open' => 'เปิดรับสมัคร',
        'in_progress' => 'กำลังดำเนินงาน',
        'completed' => 'งานเสร็จสิ้น',
        'closed' => 'ปิดรับสมัคร'
    ];

    return $labels[$status] ?? 'เปิดรับสมัคร';
}

$categories = [];
$featuredJobs = [];
$companies = [];
$stats = ['jobs' => 0, 'companies' => 0, 'users' => 0];

if($conn){
    $categories = db_fetch_all($conn, "
        SELECT c.name,
               COALESCE(NULLIF(c.icon,''), LEFT(c.name, 2)) AS icon,
               COUNT(j.job_id) AS jobs
        FROM categories c
        LEFT JOIN job j ON j.category = c.name AND j.admin_status = 'approved'
        GROUP BY c.category_id, c.name, c.icon
        ORDER BY jobs DESC, c.name ASC
        LIMIT 6
    ");

    if(count($categories) === 0){
        $categories = db_fetch_all($conn, "
            SELECT COALESCE(NULLIF(category,''), 'Other') AS name,
                   LEFT(COALESCE(NULLIF(category,''), 'Other'), 2) AS icon,
                   COUNT(*) AS jobs
            FROM job
            WHERE admin_status = 'approved'
            GROUP BY COALESCE(NULLIF(category,''), 'Other')
            ORDER BY jobs DESC, name ASC
            LIMIT 6
        ");
    }

    $jobWhere = ["j.admin_status = 'approved'"];
    $jobTypes = '';
    $jobParams = [];

    if($titleSearch !== ''){
        $likeTitle = '%' . $titleSearch . '%';
        $jobWhere[] = "(j.title LIKE ? OR j.category LIKE ? OR u.username LIKE ? OR u.fullname LIKE ? OR ep.employer_name LIKE ?)";
        $jobTypes .= 'sssss';
        array_push($jobParams, $likeTitle, $likeTitle, $likeTitle, $likeTitle, $likeTitle);
    }

    if($locationSearch !== ''){
        $likeLocation = '%' . $locationSearch . '%';
        $jobWhere[] = "j.location LIKE ?";
        $jobTypes .= 's';
        $jobParams[] = $likeLocation;
    }

    $featuredJobs = db_fetch_all($conn, "
        SELECT j.job_id,
               j.title,
               j.salary,
               j.location,
               j.status,
               j.category,
               j.created_at,
               COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS company
        FROM job j
        JOIN users u ON u.user_id = j.employer_id
        LEFT JOIN employer_profile ep ON ep.user_id = u.user_id
        WHERE " . implode(' AND ', $jobWhere) . "
        ORDER BY j.created_at DESC
        LIMIT 6
    ", $jobTypes, $jobParams);

    $companies = db_fetch_all($conn, "
        SELECT u.user_id,
               COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS name,
               COALESCE(NULLIF(ep.employer_description,''), 'นายจ้างที่ยืนยันแล้ว (Verified employer)') AS industry,
               COUNT(j.job_id) AS jobs
        FROM users u
        LEFT JOIN employer_profile ep ON ep.user_id = u.user_id
        LEFT JOIN job j ON j.employer_id = u.user_id AND j.admin_status = 'approved'
        WHERE u.role = 'employer'
        GROUP BY u.user_id, u.username, u.fullname, ep.employer_name, ep.employer_description
        ORDER BY jobs DESC, name ASC
        LIMIT 6
    ");

    $stats = [
        'jobs' => db_count($conn, "SELECT COUNT(*) AS c FROM job WHERE admin_status = 'approved'"),
        'companies' => db_count($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'employer'"),
        'users' => db_count($conn, "SELECT COUNT(*) AS c FROM users WHERE role != 'admin'")
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="JobFind แพลตฟอร์มหางานที่ช่วยให้ผู้สมัครเจองานคุณภาพจากบริษัทที่น่าเชื่อถือ">
<title>JobFind - หางานที่ใช่ | Find Your Dream Job</title>
<style>
  *,
  *::before,
  *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    letter-spacing: 0;
  }

  :root {
    --navy-950: #06101f;
    --navy-900: #0b1220;
    --navy-850: #101a2f;
    --navy-800: #13233d;
    --blue-600: #2563eb;
    --blue-500: #3b82f6;
    --cyan-400: #22d3ee;
    --text: #0f172a;
    --muted: #64748b;
    --soft: #eff6ff;
    --white: #ffffff;
    --border: #dbe4ef;
    --shadow-sm: 0 12px 28px rgba(15, 23, 42, .08);
    --shadow-md: 0 22px 50px rgba(15, 23, 42, .14);
    --radius: 16px;
    --radius-sm: 10px;
  }

  html {
    scroll-behavior: smooth;
    background: var(--navy-950);
  }

  body {
    min-height: 100vh;
    font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
    color: var(--text);
    background: #f6f9fd;
    line-height: 1.6;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
  }

  a {
    color: inherit;
    text-decoration: none;
  }

  button,
  input {
    font: inherit;
  }

  .page {
    overflow: hidden;
  }

  .container {
    width: min(1180px, calc(100% - 40px));
    margin: 0 auto;
  }

  .navbar {
    position: sticky;
    top: 0;
    z-index: 30;
    border-bottom: 1px solid rgba(255, 255, 255, .10);
    background: rgba(6, 16, 31, .88);
    backdrop-filter: blur(18px);
  }

  .nav-inner {
    min-height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
  }

  .logo {
    display: inline-flex;
    align-items: center;
    gap: 11px;
    color: #ffffff;
    font-size: 22px;
    font-weight: 850;
  }

  .logo-mark {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #2563eb, #22d3ee);
    box-shadow: 0 14px 30px rgba(37, 99, 235, .34);
    font-size: 14px;
    font-weight: 900;
  }

  .nav-links {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .nav-links a {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    padding: 0 13px;
    border-radius: 999px;
    color: #cbd5e1;
    font-size: 14px;
    font-weight: 700;
    transition: color .18s ease, background .18s ease, transform .18s ease;
  }

  .nav-links a:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, .09);
    transform: translateY(-1px);
  }

  .nav-actions {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .btn {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid transparent;
    border-radius: 999px;
    padding: 0 18px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 800;
    transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease;
  }

  .btn-outline {
    border-color: rgba(255, 255, 255, .18);
    color: #e2e8f0;
    background: rgba(255, 255, 255, .06);
  }

  .btn-outline:hover {
    border-color: rgba(255, 255, 255, .32);
    background: rgba(255, 255, 255, .10);
    transform: translateY(-1px);
  }

  .btn-primary {
    color: #ffffff;
    background: linear-gradient(135deg, var(--blue-600), var(--cyan-400));
    box-shadow: 0 16px 32px rgba(37, 99, 235, .28);
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 22px 42px rgba(37, 99, 235, .34);
  }

  .hero {
    position: relative;
    min-height: 680px;
    display: flex;
    align-items: center;
    color: #ffffff;
    background:
      linear-gradient(rgba(6, 16, 31, .88), rgba(6, 16, 31, .78)),
      radial-gradient(circle at 22% 24%, rgba(59, 130, 246, .34), transparent 30%),
      radial-gradient(circle at 82% 18%, rgba(34, 211, 238, .24), transparent 28%),
      linear-gradient(135deg, #06101f 0%, #0b1220 44%, #13233d 100%);
  }

  .hero-visual {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
  }

  .hero-visual::before,
  .hero-visual::after {
    content: "";
    position: absolute;
    width: 760px;
    height: 760px;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 50%;
  }

  .hero-visual::before {
    top: -280px;
    right: -180px;
  }

  .hero-visual::after {
    bottom: -380px;
    left: -260px;
  }

  .job-stream {
    position: absolute;
    inset: 110px -40px auto auto;
    width: min(620px, 52vw);
    display: grid;
    gap: 16px;
    transform: rotate(-7deg);
    opacity: .52;
  }

  .stream-row {
    display: grid;
    grid-template-columns: 52px 1fr 110px;
    align-items: center;
    gap: 14px;
    padding: 14px;
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: 18px;
    background: rgba(255, 255, 255, .08);
    box-shadow: 0 24px 70px rgba(0, 0, 0, .18);
  }

  .stream-row:nth-child(2) {
    margin-left: 54px;
  }

  .stream-row:nth-child(3) {
    margin-left: 108px;
  }

  .stream-mark {
    width: 52px;
    height: 52px;
    border-radius: 15px;
    background: linear-gradient(135deg, rgba(37, 99, 235, .96), rgba(34, 211, 238, .86));
  }

  .stream-lines {
    display: grid;
    gap: 9px;
  }

  .stream-lines span {
    display: block;
    height: 9px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .44);
  }

  .stream-lines span:last-child {
    width: 66%;
    background: rgba(255, 255, 255, .24);
  }

  .stream-pill {
    height: 30px;
    border-radius: 999px;
    background: rgba(34, 211, 238, .24);
  }

  .hero-content {
    position: relative;
    z-index: 2;
    width: min(820px, 100%);
    padding: 110px 0 98px;
  }

  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 34px;
    padding: 6px 13px;
    border: 1px solid rgba(255, 255, 255, .16);
    border-radius: 999px;
    background: rgba(255, 255, 255, .08);
    color: #bfdbfe;
    font-size: 13px;
    font-weight: 800;
  }

  .eyebrow-dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #22d3ee;
    box-shadow: 0 0 0 5px rgba(34, 211, 238, .16);
  }

  .hero h1 {
    margin-top: 24px;
    max-width: 760px;
    font-size: clamp(44px, 7vw, 78px);
    line-height: 1.02;
    font-weight: 900;
  }

  .hero p {
    max-width: 650px;
    margin-top: 24px;
    color: #cbd5e1;
    font-size: clamp(16px, 2vw, 19px);
  }

  .search-panel {
    width: min(860px, 100%);
    margin-top: 36px;
    padding: 12px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, .8fr) auto;
    gap: 10px;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 22px;
    background: rgba(255, 255, 255, .96);
    box-shadow: 0 28px 70px rgba(0, 0, 0, .28);
  }

  .search-field {
    min-height: 58px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 16px;
    border: 1px solid #dbe4ef;
    border-radius: 16px;
    background: #ffffff;
  }

  .search-icon {
    width: 34px;
    height: 34px;
    border-radius: 11px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: #eff6ff;
    color: var(--blue-600);
    font-size: 16px;
  }

  .search-field input {
    width: 100%;
    border: 0;
    outline: 0;
    color: var(--text);
    background: transparent;
    font-size: 15px;
    font-weight: 650;
  }

  .search-field input::placeholder {
    color: #94a3b8;
    font-weight: 600;
  }

  .search-btn {
    min-height: 58px;
    border-radius: 16px;
    padding: 0 28px;
  }

  .hero-trust {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    margin-top: 28px;
    color: #cbd5e1;
    font-size: 14px;
    font-weight: 700;
  }

  .trust-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .trust-check {
    color: #22d3ee;
    font-weight: 900;
  }

  section {
    padding: 86px 0;
  }

  .section-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 30px;
  }

  .section-kicker {
    color: var(--blue-600);
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
  }

  .section-title {
    margin-top: 8px;
    color: #071327;
    font-size: clamp(28px, 4vw, 42px);
    line-height: 1.18;
    font-weight: 900;
  }

  .section-copy {
    max-width: 430px;
    color: var(--muted);
    font-size: 15px;
  }

  .category-grid,
  .company-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 16px;
  }

  .category-card,
  .company-card,
  .job-card,
  .stat-card {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: rgba(255, 255, 255, .96);
    box-shadow: var(--shadow-sm);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  }

  .category-card:hover,
  .company-card:hover,
  .job-card:hover,
  .stat-card:hover {
    transform: translateY(-5px);
    border-color: #bfdbfe;
    box-shadow: var(--shadow-md);
  }

  .category-card {
    min-height: 156px;
    padding: 22px;
  }

  .category-icon,
  .company-mark {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    color: var(--blue-600);
    font-size: 14px;
    font-weight: 900;
  }

  .category-card h3 {
    margin-top: 18px;
    color: #0f172a;
    font-size: 17px;
    font-weight: 850;
  }

  .category-card p {
    margin-top: 4px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 700;
  }

  .jobs-section {
    background:
      linear-gradient(180deg, #ffffff 0%, #f6f9fd 100%);
  }

  .job-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
  }

  .job-card {
    position: relative;
    overflow: hidden;
    padding: 22px;
  }

  .job-card::before {
    content: "";
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
    background: linear-gradient(90deg, var(--blue-600), var(--cyan-400));
  }

  .job-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
  }

  .job-title {
    color: #071327;
    font-size: 19px;
    line-height: 1.3;
    font-weight: 900;
  }

  .job-tag {
    flex: 0 0 auto;
    border-radius: 999px;
    padding: 6px 10px;
    background: #eff6ff;
    color: var(--blue-600);
    font-size: 12px;
    font-weight: 900;
  }

  .company-name {
    margin-top: 8px;
    color: #475569;
    font-size: 14px;
    font-weight: 750;
  }

  .job-meta {
    display: grid;
    gap: 9px;
    margin-top: 22px;
    color: #475569;
    font-size: 14px;
    font-weight: 650;
  }

  .job-meta span {
    display: flex;
    align-items: center;
    gap: 9px;
  }

  .db-alert,
  .empty-state {
    grid-column: 1 / -1;
    padding: 22px;
    border: 1px solid #bfdbfe;
    border-radius: var(--radius);
    background: #eff6ff;
    color: #1e3a8a;
    font-size: 15px;
    font-weight: 750;
    box-shadow: var(--shadow-sm);
  }

  .db-alert {
    margin-bottom: 24px;
    border-color: #fecaca;
    background: #fff1f2;
    color: #991b1b;
  }

  .meta-dot {
    width: 26px;
    height: 26px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: var(--blue-600);
    font-size: 13px;
  }

  .apply-btn {
    width: 100%;
    margin-top: 24px;
    color: #ffffff;
    background: var(--navy-900);
  }

  .apply-btn:hover {
    background: linear-gradient(135deg, var(--blue-600), var(--navy-800));
    transform: translateY(-2px);
  }

  .companies-section {
    background: var(--navy-950);
    color: #ffffff;
  }

  .companies-section .section-title {
    color: #ffffff;
  }

  .companies-section .section-copy {
    color: #aebed2;
  }

  .company-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .company-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 22px;
    background: rgba(255, 255, 255, .06);
    border-color: rgba(255, 255, 255, .12);
    box-shadow: none;
  }

  .company-card:hover {
    border-color: rgba(34, 211, 238, .42);
    background: rgba(255, 255, 255, .09);
  }

  .company-card h3 {
    color: #ffffff;
    font-size: 18px;
    font-weight: 850;
  }

  .company-card p {
    color: #aebed2;
    font-size: 13px;
  }

  .company-card strong {
    color: #bfdbfe;
  }

  .stats-section {
    padding: 64px 0;
    background:
      linear-gradient(135deg, rgba(37, 99, 235, .10), rgba(34, 211, 238, .08)),
      #ffffff;
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
  }

  .stat-card {
    padding: 28px;
    text-align: center;
  }

  .stat-number {
    color: #071327;
    font-size: clamp(32px, 5vw, 48px);
    line-height: 1;
    font-weight: 950;
  }

  .stat-label {
    margin-top: 9px;
    color: var(--muted);
    font-size: 15px;
    font-weight: 800;
  }

  .footer {
    padding: 54px 0 34px;
    color: #aebed2;
    background: var(--navy-950);
  }

  .footer-grid {
    display: grid;
    grid-template-columns: 1.2fr .8fr .8fr;
    gap: 28px;
  }

  .footer h3,
  .footer h4 {
    color: #ffffff;
  }

  .footer h3 {
    font-size: 24px;
    font-weight: 900;
  }

  .footer p,
  .footer a {
    color: #aebed2;
    font-size: 14px;
  }

  .footer a:hover {
    color: #ffffff;
  }

  .footer-links,
  .social-links {
    display: grid;
    gap: 10px;
    margin-top: 14px;
  }

  .copyright {
    margin-top: 34px;
    padding-top: 22px;
    border-top: 1px solid rgba(255, 255, 255, .10);
    color: #71839d;
    font-size: 13px;
  }

  @media (max-width: 980px) {
    .nav-inner {
      align-items: flex-start;
      flex-direction: column;
      padding: 16px 0;
    }

    .nav-links,
    .nav-actions {
      width: 100%;
      flex-wrap: wrap;
    }

    .search-panel {
      grid-template-columns: 1fr;
    }

    .category-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .job-grid,
    .company-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .job-stream {
      width: 720px;
      inset: auto -240px 20px auto;
      opacity: .22;
    }
  }

  @media (max-width: 680px) {
    .container {
      width: min(100% - 28px, 1180px);
    }

    .hero {
      min-height: auto;
    }

    .hero-content {
      padding: 82px 0 72px;
    }

    .hero h1 {
      font-size: clamp(40px, 13vw, 56px);
    }

    .nav-links a {
      padding: 0 10px;
    }

    .nav-actions .btn {
      flex: 1;
    }

    section {
      padding: 62px 0;
    }

    .section-head {
      display: block;
    }

    .section-copy {
      margin-top: 12px;
    }

    .category-grid,
    .job-grid,
    .company-grid,
    .stats-grid,
    .footer-grid {
      grid-template-columns: 1fr;
    }

    .category-card {
      min-height: auto;
    }
  }
</style>
</head>
<body>
<div class="page">
  <nav class="navbar">
    <div class="container nav-inner">
      <a class="logo" href="index.php" aria-label="JobFind home">
        <span class="logo-mark">JF</span>
        <span>JobFind</span>
      </a>

      <div class="nav-links" aria-label="Primary navigation">
        <a href="#home">หน้าแรก</a>
        <a href="#jobs">ค้นหางาน</a>
        <a href="#companies">บริษัท</a>
        <a href="#about">เกี่ยวกับเรา</a>
      </div>

      <div class="nav-actions">
        <a class="btn btn-outline" href="login.php">เข้าสู่ระบบ</a>
        <a class="btn btn-primary" href="register.php">สมัครสมาชิก</a>
      </div>
    </div>
  </nav>

  <header class="hero" id="home">
    <div class="hero-visual" aria-hidden="true">
      <div class="job-stream">
        <div class="stream-row">
          <span class="stream-mark"></span>
          <span class="stream-lines"><span></span><span></span></span>
          <span class="stream-pill"></span>
        </div>
        <div class="stream-row">
          <span class="stream-mark"></span>
          <span class="stream-lines"><span></span><span></span></span>
          <span class="stream-pill"></span>
        </div>
        <div class="stream-row">
          <span class="stream-mark"></span>
          <span class="stream-lines"><span></span><span></span></span>
          <span class="stream-pill"></span>
        </div>
      </div>
    </div>

    <div class="container">
      <div class="hero-content">
        <div class="eyebrow"><span class="eyebrow-dot"></span> แพลตฟอร์มหางานสำหรับทีมยุคใหม่ | Modern Hiring</div>
        <h1>หางานที่ใช่ เริ่มต้นที่ JobFind</h1>
        <p>ค้นหางานคุณภาพจากบริษัทที่น่าเชื่อถือ เปรียบเทียบตำแหน่งได้มั่นใจ และสมัครงานได้เร็วขึ้นแบบ Find Your Dream Job.</p>

        <form class="search-panel" method="GET" action="index.php#jobs">
          <label class="search-field">
            <span class="search-icon">Q</span>
            <input type="text" name="title" value="<?php echo e($titleSearch); ?>" placeholder="ตำแหน่งงาน, keyword, หรือบริษัท">
          </label>
          <label class="search-field">
            <span class="search-icon">L</span>
            <input type="text" name="location" value="<?php echo e($locationSearch); ?>" placeholder="จังหวัดหรือพื้นที่ทำงาน">
          </label>
          <button class="btn btn-primary search-btn" type="submit">ค้นหางาน</button>
        </form>

        <div class="hero-trust">
          <span class="trust-item"><span class="trust-check">+</span> บริษัทตรวจสอบแล้ว</span>
          <span class="trust-item"><span class="trust-check">+</span> เงินเดือนชัดเจน</span>
          <span class="trust-item"><span class="trust-check">+</span> สมัครง่ายแบบ Fast Apply</span>
        </div>
      </div>
    </div>
  </header>

  <section id="categories">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="section-kicker">หมวดยอดนิยม / Popular Categories</div>
          <h2 class="section-title">สายงานที่กำลังต้องการคน</h2>
        </div>
        <p class="section-copy">เลือกดูหมวดงานจากฐานข้อมูลจริง ทั้งสายเทคโนโลยี ดีไซน์ การตลาด บัญชี และงานธุรกิจดิจิทัล</p>
      </div>

      <div class="category-grid">
        <?php foreach($categories as $category): ?>
        <article class="category-card">
          <div class="category-icon"><?php echo e(($category["icon"] ?? '') === '?' ? initials($category["name"]) : $category["icon"]); ?></div>
          <h3><?php echo e($category["name"]); ?></h3>
          <p><?php echo e(number_format((int)$category["jobs"])); ?> ตำแหน่งที่เปิดรับ</p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="jobs-section" id="jobs">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="section-kicker">งานแนะนำ / Featured Jobs</div>
          <h2 class="section-title">งานล่าสุดจากฐานข้อมูล JobFind</h2>
        </div>
        <p class="section-copy">
          <?php if($dbError): ?>
            ไม่สามารถโหลดข้อมูลจากฐานข้อมูลได้ในขณะนี้
          <?php elseif($titleSearch !== '' || $locationSearch !== ''): ?>
            แสดงงานที่ผ่านการอนุมัติจากฐานข้อมูล และตรงกับคำค้นหาของคุณ
          <?php else: ?>
            รวมตำแหน่งงานที่ผ่านการอนุมัติล่าสุดจากระบบจริงของ JobFind
          <?php endif; ?>
        </p>
      </div>

      <?php if($dbError): ?>
      <div class="db-alert"><?php echo e($dbError); ?></div>
      <?php endif; ?>

      <div class="job-grid">
        <?php if(count($featuredJobs) === 0): ?>
        <div class="empty-state">ไม่พบงานในฐานข้อมูลที่ตรงกับคำค้นหานี้</div>
        <?php endif; ?>

        <?php foreach($featuredJobs as $job): ?>
        <article class="job-card">
          <div class="job-top">
            <div>
              <h3 class="job-title"><?php echo e($job["title"]); ?></h3>
              <p class="company-name"><?php echo e($job["company"]); ?></p>
            </div>
            <span class="job-tag"><?php echo e($job["category"] ?: "Job"); ?></span>
          </div>

          <div class="job-meta">
            <span><span class="meta-dot">$</span><?php echo ((float)$job["salary"] > 0) ? "THB " . e(number_format((float)$job["salary"], 2)) : "ไม่ระบุเงินเดือน"; ?></span>
            <span><span class="meta-dot">L</span><?php echo e(trim($job["location"]) !== '' ? $job["location"] : "ไม่ระบุสถานที่"); ?></span>
            <span><span class="meta-dot">S</span><?php echo e(job_status_label($job["status"])); ?></span>
          </div>

          <a class="btn apply-btn" href="view_job.php?job_id=<?php echo e($job["job_id"]); ?>">สมัครงาน / Apply</a>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="companies-section" id="companies">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="section-kicker">บริษัทแนะนำ / Top Companies</div>
          <h2 class="section-title">บริษัทที่กำลังเปิดรับสมัคร</h2>
        </div>
        <p class="section-copy">ดูรายชื่อนายจ้างจริงในระบบ พร้อมจำนวนงานที่เปิดรับสมัครบน JobFind</p>
      </div>

      <div class="company-grid">
        <?php if(count($companies) === 0): ?>
        <div class="empty-state">ยังไม่มีข้อมูลบริษัทในฐานข้อมูล</div>
        <?php endif; ?>

        <?php foreach($companies as $company): ?>
        <article class="company-card">
          <div class="company-mark"><?php echo e(initials($company["name"])); ?></div>
          <div>
            <h3><?php echo e($company["name"]); ?></h3>
            <p><?php echo e($company["industry"]); ?> - <strong><?php echo e(number_format((int)$company["jobs"])); ?> งานที่เปิดรับ</strong></p>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="stats-section" id="about">
    <div class="container">
      <div class="stats-grid">
        <article class="stat-card">
          <div class="stat-number"><?php echo e(number_format($stats["jobs"])); ?></div>
          <div class="stat-label">งานทั้งหมด</div>
        </article>
        <article class="stat-card">
          <div class="stat-number"><?php echo e(number_format($stats["companies"])); ?></div>
          <div class="stat-label">บริษัท</div>
        </article>
        <article class="stat-card">
          <div class="stat-number"><?php echo e(number_format($stats["users"])); ?></div>
          <div class="stat-label">ผู้ใช้งาน</div>
        </article>
      </div>
    </div>
  </section>

  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div>
          <h3>JobFind</h3>
          <p>แพลตฟอร์มหางานสำหรับผู้สมัครและบริษัทที่ต้องการทีมคุณภาพ พร้อมประสบการณ์แบบ modern job board.</p>
        </div>
        <div>
          <h4>ติดต่อเรา</h4>
          <div class="footer-links">
            <a href="mailto:hello@jobfind.local">hello@jobfind.local</a>
            <a href="tel:+6620000000">+66 2 000 0000</a>
            <span>Bangkok, Thailand</span>
          </div>
        </div>
        <div>
          <h4>ช่องทาง Social</h4>
          <div class="social-links">
            <a href="#">LinkedIn</a>
            <a href="#">Facebook</a>
            <a href="#">X</a>
          </div>
        </div>
      </div>

      <div class="copyright">&copy; 2026 JobFind. สงวนลิขสิทธิ์</div>
    </div>
  </footer>
</div>
</body>
</html>
