<?php
define('JOBFIND_ALLOW_DB_FAILURE', true);
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/job_image_helpers.php";
require_once __DIR__ . "/location_schema.php";

$titleSearch = trim($_GET['title'] ?? '');
$locationSearch = trim($_GET['location'] ?? '');
$searchLat = isset($_GET['latitude']) && is_numeric($_GET['latitude']) ? (float)$_GET['latitude'] : null;
$searchLng = isset($_GET['longitude']) && is_numeric($_GET['longitude']) ? (float)$_GET['longitude'] : null;
$searchRadiusKm = isset($_GET['preferred_radius_km']) && is_numeric($_GET['preferred_radius_km'])
    ? max(1, min(300, (float)$_GET['preferred_radius_km']))
    : 30;
$hasLocationPin = $searchLat !== null && $searchLng !== null;
$dbError = $conn ? '' : ($db_error ?: 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้');

function e($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function db_fetch_all($conn, $sql, $types = '', $params = []){
    $rows = [];
    if(!$conn){
        return $rows;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if(!$stmt){
        error_log("Query prepare failed: " . mysqli_error($conn));
        return $rows;
    }

    if($types !== ''){
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        if($result){
            while($row = mysqli_fetch_assoc($result)){
                $rows[] = $row;
            }
        }
    } else {
        error_log("Query execute failed: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function db_count($conn, $sql){
    if(!$conn){
        return 0;
    }

    $result = mysqli_query($conn, $sql);
    if(!$result){
        error_log("Count query failed: " . mysqli_error($conn));
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int)($row['c'] ?? 0);
}

function table_exists($conn, $table){
    $table = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $result && mysqli_num_rows($result) > 0;
}

function initials($name){
    $name = trim((string)$name);
    if($name === ''){
        return 'FH';
    }

    preg_match_all('/[A-Za-z0-9]/', $name, $matches);
    $letters = $matches[0] ?? [];
    if(count($letters) >= 2){
        return strtoupper($letters[0] . $letters[1]);
    }

    if(count($letters) === 1){
        return strtoupper($letters[0]);
    }

    return function_exists('mb_substr') ? mb_substr($name, 0, 2, 'UTF-8') : substr($name, 0, 2);
}

function dashboard_for_role($role){
    $targets = [
        'admin' => 'admin_dashboard.php',
        'employer' => 'employer_dashboard.php',
        'freelancer' => 'freelancer_dashboard.php',
    ];

    return $targets[$role] ?? 'login.php';
}

function account_profile_for_role($role){
    $targets = [
        'admin' => 'admin_dashboard.php',
        'employer' => 'employer_profile.php',
        'freelancer' => 'my_profile.php',
    ];

    return $targets[$role] ?? 'login.php';
}

function job_status_label($status){
    $status = trim((string)$status);
    $labels = [
        '' => 'เปิดรับสมัคร',
        'open' => 'เปิดรับสมัคร',
        'in_progress' => 'กำลังดำเนินงาน',
        'completed' => 'เสร็จสิ้น',
        'closed' => 'ปิดรับสมัคร',
    ];

    return $labels[$status] ?? 'เปิดรับสมัคร';
}

function format_salary($salary){
    $amount = (float)$salary;
    return $amount > 0 ? '฿' . number_format($amount, 0) : 'ไม่ระบุงบประมาณ';
}

function category_icon($name){
    $name = strtolower((string)$name);
    if(str_contains($name, 'design')) return '🎨';
    if(str_contains($name, 'marketing')) return '📢';
    if(str_contains($name, 'writing')) return '✍️';
    if(str_contains($name, 'finance') || str_contains($name, 'account')) return '💰';
    if(str_contains($name, 'education')) return '🎓';
    if(str_contains($name, 'it') || str_contains($name, 'software') || str_contains($name, 'php') || str_contains($name, 'java')) return '💻';
    return '💼';
}

$role = $_SESSION['role'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']) && $role !== '';
$dashboardUrl = dashboard_for_role($role);
$accountProfileUrl = account_profile_for_role($role);
$primaryCtaUrl = $isLoggedIn ? $dashboardUrl : 'register.php';
$primaryCtaText = $isLoggedIn ? 'ไปที่แดชบอร์ด' : 'เริ่มต้นใช้งาน';
$secondaryCtaUrl = $isLoggedIn ? $accountProfileUrl : 'login.php';
$secondaryCtaText = $isLoggedIn ? 'จัดการบัญชีของฉัน' : 'เข้าสู่ระบบ';

$categories = [];
$featuredJobs = [];
$companies = [];
$stats = ['jobs' => 0, 'employers' => 0, 'freelancers' => 0];

if($conn){
    ensure_location_schema($conn);
    ensure_job_image_schema($conn);

    if(table_exists($conn, 'categories')){
        $categories = db_fetch_all($conn, "
            SELECT c.name,
                   COUNT(j.job_id) AS jobs
            FROM categories c
            LEFT JOIN job j ON j.category = c.name
                AND j.admin_status = 'approved'
                AND COALESCE(NULLIF(j.status,''), 'open') != 'closed'
            GROUP BY c.category_id, c.name
            ORDER BY jobs DESC, c.name ASC
            LIMIT 6
        ");
    }

    if(count($categories) === 0){
        $categories = db_fetch_all($conn, "
            SELECT COALESCE(NULLIF(category,''), 'Other') AS name,
                   COUNT(*) AS jobs
            FROM job
            WHERE admin_status = 'approved'
              AND COALESCE(NULLIF(status,''), 'open') != 'closed'
            GROUP BY COALESCE(NULLIF(category,''), 'Other')
            ORDER BY jobs DESC, name ASC
            LIMIT 6
        ");
    }

    $jobLatSql = "COALESCE(j.latitude, ep.latitude, u.latitude)";
    $jobLngSql = "COALESCE(j.longitude, ep.longitude, u.longitude)";
    $distanceSql = "(6371 * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(?)) * COS(RADIANS($jobLatSql)) * COS(RADIANS($jobLngSql) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS($jobLatSql))))))";
    $distanceSelect = '';
    $jobOrder = "j.created_at DESC";
    $jobWhere = ["j.admin_status = 'approved'", "COALESCE(NULLIF(j.status,''), 'open') != 'closed'"];
    $jobTypes = '';
    $jobParams = [];

    if($hasLocationPin){
        $distanceSelect = ", $distanceSql AS distance_km";
        $jobTypes .= 'ddd';
        array_push($jobParams, $searchLat, $searchLng, $searchLat);

        $jobWhere[] = "$jobLatSql IS NOT NULL AND $jobLngSql IS NOT NULL";
        $jobWhere[] = "$distanceSql <= ?";
        $jobTypes .= 'dddd';
        array_push($jobParams, $searchLat, $searchLng, $searchLat, $searchRadiusKm);
        $jobOrder = "distance_km ASC, j.created_at DESC";
    }

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
               j.description,
               j.salary,
               j.location,
               j.status,
               j.category,
               j.created_at,
               COALESCE(
                   (SELECT ji.image_path FROM job_images ji WHERE ji.job_id = j.job_id ORDER BY ji.sort_order ASC, ji.image_id ASC LIMIT 1),
                   j.image_path
               ) AS job_image
               $distanceSelect,
               COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS company
        FROM job j
        JOIN users u ON u.user_id = j.employer_id
        LEFT JOIN employer_profile ep ON ep.user_id = u.user_id
        WHERE " . implode(' AND ', $jobWhere) . "
        ORDER BY $jobOrder
        LIMIT 6
    ", $jobTypes, $jobParams);

    $companies = db_fetch_all($conn, "
        SELECT u.user_id,
               u.profile_image,
               COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS name,
               COALESCE(NULLIF(ep.employer_description,''), 'ผู้ว่าจ้างในระบบ FreelanceHub') AS description,
               COUNT(j.job_id) AS jobs
        FROM users u
        LEFT JOIN employer_profile ep ON ep.user_id = u.user_id
        LEFT JOIN job j ON j.employer_id = u.user_id
            AND j.admin_status = 'approved'
            AND COALESCE(NULLIF(j.status,''), 'open') != 'closed'
        WHERE u.role = 'employer'
        GROUP BY u.user_id, u.profile_image, u.username, u.fullname, ep.employer_name, ep.employer_description
        ORDER BY jobs DESC, name ASC
        LIMIT 4
    ");

    $stats = [
        'jobs' => db_count($conn, "SELECT COUNT(*) AS c FROM job WHERE admin_status = 'approved'"),
        'employers' => db_count($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'employer'"),
        'freelancers' => db_count($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'freelancer'"),
    ];
}

$heroStyle = "--hero-bg:
    radial-gradient(circle at 76% 24%, rgba(20,184,166,.30) 0%, rgba(20,184,166,0) 30%),
    radial-gradient(circle at 18% 72%, rgba(91,95,244,.34) 0%, rgba(91,95,244,0) 34%),
    linear-gradient(135deg, #0b1220 0%, #12203a 54%, #0f172a 100%);";
$pinStatusText = $hasLocationPin
    ? 'ปักหมุดแล้ว รัศมี ' . number_format($searchRadiusKm, 0) . ' กม.'
    : ($locationSearch !== '' ? $locationSearch : 'ยังไม่ได้ปักหมุดพื้นที่หางาน');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="FreelanceHub แพลตฟอร์มหางานฟรีแลนซ์และจ้างงานแบบเป็นระบบ">
<title>FreelanceHub - หางานฟรีแลนซ์และจ้างงาน</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.min.css">
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">
<style>
  :root {
    --navy: #0b1220;
    --navy2: #111c31;
    --accent: #5b5ff4;
    --cyan: #06b6d4;
    --green: #14b87a;
    --orange: #f97316;
    --yellow: #f59e0b;
    --light: #eef3f8;
    --white: #ffffff;
    --text: #0f172a;
    --muted: #64748b;
    --border: #dbe4ef;
    --radius: 8px;
    --shadow-sm: 0 1px 2px rgba(15, 23, 42, .04), 0 10px 24px rgba(15, 23, 42, .06);
    --shadow-md: 0 18px 42px rgba(15, 23, 42, .10);
  }

  html { scroll-behavior: smooth; background: var(--navy); }
  body { min-height: 100vh; margin: 0; color: var(--text); background: #f8fbff; }
  a { color: inherit; text-decoration: none; }
  button, input { font: inherit; }

  .shell { overflow-x: hidden; background: #f7fbff; }
  .shell .container { width: min(1160px, calc(100% - 36px)); margin: 0 auto; }

  .top-nav {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(255, 255, 255, .92) !important;
    border-bottom: 1px solid var(--border);
    backdrop-filter: blur(16px);
  }

  .nav-inner {
    min-height: 74px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
  }

  .shell .brand {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    color: #14213d !important;
    font-weight: 800;
  }

  .shell .brand-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--accent), var(--green));
    color: #fff;
    box-shadow: 0 12px 24px rgba(91, 95, 244, .28);
  }

  .shell .brand-name { color: #14213d !important; font-size: 22px; line-height: 1; }

  .nav-links {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .nav-links a {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    padding: 0 12px;
    border-radius: 8px;
    color: #405571;
    font-size: 14px;
    font-weight: 700;
  }

  .nav-links a:hover { background: #eef2ff; color: var(--accent); }

  .nav-actions { display: flex; align-items: center; gap: 10px; }

  .btn {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid transparent;
    border-radius: 10px;
    padding: 0 18px;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: transform .15s, box-shadow .15s, background .15s, border-color .15s;
  }

  .btn:hover { transform: translateY(-1px); }
  .btn-primary { background: linear-gradient(135deg, var(--accent), var(--green)); color: #fff; box-shadow: 0 12px 22px rgba(91, 95, 244, .22); }
  .btn-secondary { background: #fff; color: #24364f; border-color: var(--border); }
  .btn-secondary:hover { border-color: #aeb9ff; color: var(--accent); background: #f8faff; }
  .btn-ghost { background: rgba(255, 255, 255, .12); color: #fff; border-color: rgba(255, 255, 255, .22); }
  .btn-ghost:hover { background: rgba(255, 255, 255, .18); color: #fff; }

  .shell .hero {
    min-height: clamp(560px, 82vh, 720px);
    display: flex;
    align-items: center;
    background: var(--hero-bg) !important;
    background-size: cover !important;
    background-position: center !important;
    color: #fff !important;
  }

  .hero-content {
    width: min(760px, 100%);
    padding: 70px 0 84px;
  }

  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid rgba(219, 228, 255, .24);
    border-radius: 999px;
    background: rgba(255, 255, 255, .10);
    color: #dce7ff;
    font-size: 13px;
    font-weight: 800;
  }

  .shell .hero h1 {
    margin: 22px 0 16px;
    color: #fff !important;
    font-size: clamp(40px, 6vw, 68px);
    line-height: 1.05;
    font-weight: 900;
  }

  .shell .hero-copy {
    max-width: 660px;
    margin: 0 0 30px;
    color: #d8e3f3 !important;
    font-size: 17px;
    line-height: 1.75;
  }

  .search-strip {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 10px;
    max-width: 860px;
    margin-bottom: 24px;
  }

  .search-field {
    min-height: 54px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 16px;
    border: 1px solid rgba(219, 228, 255, .24);
    border-radius: 10px;
    background: rgba(255, 255, 255, .94);
    color: #1f314a;
  }

  .search-field i { color: var(--accent); font-size: 18px; }
  .search-field input { width: 100%; border: 0; outline: 0; background: transparent; color: var(--text); font-size: 14px; }
  .search-field input::placeholder { color: #7a8ba2; }
  .search-strip .btn { min-height: 54px; }

  .location-pin-field {
    min-height: 54px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 8px 12px 8px 16px;
    border: 1px solid rgba(219, 228, 255, .24);
    border-radius: 10px;
    background: rgba(255, 255, 255, .94);
    color: #1f314a;
  }

  .location-pin-field > i {
    color: var(--accent);
    font-size: 18px;
  }

  .pin-copy {
    display: grid;
    gap: 2px;
    min-width: 0;
  }

  .pin-label {
    color: #1f314a;
    font-size: 13px;
    font-weight: 900;
    line-height: 1.2;
  }

  .pin-status {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .btn-pin {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 1px solid #c7d2fe;
    border-radius: 9px;
    background: #eef2ff;
    color: var(--accent);
    padding: 0 12px;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
  }

  .btn-pin:hover {
    background: #dfe6ff;
  }

  .map-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 22px;
    background: rgba(15, 23, 42, .62);
  }

  .map-modal.active { display: flex; }

  .map-container {
    width: min(900px, 100%);
    height: min(760px, 92vh);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .32);
  }

  .map-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
  }

  .map-header h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    color: #172033;
    font-size: 17px;
    font-weight: 900;
  }

  .map-close {
    border: 0;
    background: transparent;
    color: var(--muted);
    font-size: 26px;
    line-height: 1;
    cursor: pointer;
  }

  .map-info {
    margin: 16px 16px 0;
    padding: 12px 14px;
    border-radius: 10px;
    background: #eef2ff;
    color: #3730a3;
    font-size: 13px;
    font-weight: 700;
  }

  .map-radius-control {
    margin: 12px 16px 14px;
    padding: 13px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #f8fafc;
  }

  .radius-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
  }

  .radius-row span {
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
  }

  .radius-value {
    color: var(--accent);
    font-size: 13px;
    font-weight: 900;
    white-space: nowrap;
  }

  .radius-slider {
    width: 100%;
    accent-color: var(--accent);
  }

  .radius-scale {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
  }

  #index-map {
    flex: 1;
    min-height: 360px;
  }

  .map-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 18px;
    border-top: 1px solid var(--border);
  }

  .map-footer button {
    border: 0;
    border-radius: 10px;
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
  }

  .btn-map-cancel {
    background: var(--light);
    color: var(--text);
  }

  .btn-map-confirm {
    background: var(--accent);
    color: #fff;
  }

  .hero-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .hero-metrics {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
    margin-top: 32px;
    color: #dce7ff;
  }

  .metric {
    min-width: 132px;
    padding: 12px 0;
    border-top: 1px solid rgba(255, 255, 255, .22);
  }

  .metric strong {
    display: block;
    margin-bottom: 2px;
    color: #fff;
    font-size: 24px;
    line-height: 1;
  }

  .metric span { font-size: 12px; font-weight: 800; color: #b9c8dd; }

  section {
    padding: 64px 0;
    border-top: 1px solid rgba(219, 228, 239, .74);
  }

  .categories-band {
    background:
      linear-gradient(180deg, #f8fbff 0%, #f4f8fd 100%);
  }

  .jobs-band {
    background:
      linear-gradient(120deg, rgba(91, 95, 244, .06), transparent 38%),
      linear-gradient(240deg, rgba(20, 184, 166, .08), transparent 34%),
      #eef5fb;
  }

  .companies-band {
    background:
      linear-gradient(120deg, rgba(6, 182, 212, .07), transparent 42%),
      #f7fbff;
  }

  .start-band {
    background:
      linear-gradient(135deg, rgba(91, 95, 244, .07), rgba(20, 184, 166, .06)),
      #f1f7fb;
  }

  .section-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 26px;
  }

  .section-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--accent);
    font-size: 13px;
    font-weight: 900;
    margin-bottom: 8px;
  }

  .section-title {
    margin: 0;
    color: #071327 !important;
    font-size: clamp(26px, 4vw, 38px);
    line-height: 1.18;
    font-weight: 900;
  }

  .section-desc {
    max-width: 460px;
    margin: 0;
    color: #5d6f86;
    font-size: 14px;
    line-height: 1.7;
  }

  .category-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
  }

  .category-card,
  .job-card,
  .company-card,
  .path-card,
  .empty-state,
  .db-alert {
    border: 1px solid var(--border);
    border-radius: 8px;
    background: rgba(255, 255, 255, .96);
    box-shadow: var(--shadow-sm);
  }

  .category-card {
    min-height: 142px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 18px;
  }

  .category-card:hover,
  .job-card:hover,
  .company-card:hover,
  .path-card:hover {
    border-color: #bcc8ff;
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
  }

  .category-icon {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: #eef2ff;
    color: var(--accent);
    font-size: 20px;
  }

  .category-card h3,
  .job-title,
  .company-card h3,
  .path-card h3 {
    margin: 0;
    color: #172033;
    font-size: 16px;
    font-weight: 900;
    line-height: 1.3;
  }

  .category-card p,
  .company-card p,
  .path-card p {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.55;
  }

  .job-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
  }

  .job-card {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .job-media {
    height: 148px;
    background: linear-gradient(135deg, #eef2ff, #dff8f7);
    overflow: hidden;
  }

  .job-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .job-media-fallback {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
    font-size: 42px;
  }

  .job-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 18px;
  }

  .job-top {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
  }

  .job-chip {
    flex: 0 0 auto;
    height: 28px;
    display: inline-flex;
    align-items: center;
    padding: 0 10px;
    border-radius: 999px;
    background: #eef2ff;
    color: var(--accent);
    font-size: 11px;
    font-weight: 900;
  }

  .company-name {
    margin: 0 0 12px;
    color: #52657e;
    font-size: 13px;
    font-weight: 700;
  }

  .job-desc {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 44px;
    margin: 0 0 14px;
    color: #5d6f86;
    font-size: 13px;
    line-height: 1.65;
  }

  .job-meta {
    display: grid;
    gap: 8px;
    margin: auto 0 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }

  .job-meta span {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #52657e;
    font-size: 13px;
    font-weight: 700;
  }

  .job-meta i { color: var(--accent); }

  .company-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
  }

  .company-card {
    padding: 18px;
  }

  .company-avatar {
    width: 54px;
    height: 54px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--accent), var(--green));
    color: #fff;
    font-weight: 900;
  }

  .company-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .company-count {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    color: #047857;
    font-size: 12px;
    font-weight: 900;
  }

  .path-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }

  .path-card {
    display: grid;
    grid-template-columns: 56px 1fr auto;
    align-items: center;
    gap: 16px;
    padding: 20px;
  }

  .path-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e0faef;
    color: #047857;
    font-size: 25px;
  }

  .path-card:nth-child(2) .path-icon {
    background: #eef2ff;
    color: var(--accent);
  }

  .db-alert,
  .empty-state {
    padding: 24px;
    color: #52657e;
    text-align: center;
  }

  .db-alert {
    margin-bottom: 16px;
    border-color: #fecdd3;
    background: #fff1f2;
    color: #be123c;
  }

  @media (max-width: 1024px) {
    .nav-links { display: none; }
    .category-grid { grid-template-columns: repeat(3, 1fr); }
    .job-grid { grid-template-columns: repeat(2, 1fr); }
    .company-grid { grid-template-columns: repeat(2, 1fr); }
  }

  @media (max-width: 720px) {
    .container { width: min(100% - 28px, 1160px); }
    .nav-inner { min-height: auto; padding: 14px 0; flex-wrap: wrap; }
    .brand { flex: 1 1 100%; }
    .nav-actions { width: 100%; }
    .nav-actions .btn { flex: 1; padding: 0 10px; }
    .hero { min-height: auto; }
    .hero-content { padding: 54px 0 58px; }
    .hero h1 { font-size: clamp(34px, 12vw, 48px); }
    .search-strip { grid-template-columns: 1fr; }
    .hero-actions .btn { width: 100%; }
    .section-head { align-items: flex-start; flex-direction: column; }
    section { padding: 46px 0; }
    .category-grid,
    .job-grid,
    .company-grid,
    .path-grid { grid-template-columns: 1fr; }
    .path-card { grid-template-columns: 48px 1fr; }
    .path-card .btn { grid-column: 1 / -1; }
  }
</style>
</head>
<body>
<div class="shell">
  <nav class="top-nav">
    <div class="container nav-inner">
      <a class="brand" href="index.php" aria-label="FreelanceHub home">
        <span class="brand-icon"><i class="bi bi-lightning-charge-fill"></i></span>
        <span class="brand-name">FreelanceHub</span>
      </a>

      <div class="nav-links" aria-label="Primary navigation">
        <a href="#jobs">งานล่าสุด</a>
        <a href="#categories">หมวดงาน</a>
        <a href="#companies">ผู้ว่าจ้าง</a>
        <a href="#start">เริ่มใช้งาน</a>
      </div>

      <div class="nav-actions">
        <a class="btn btn-secondary" href="<?php echo e($secondaryCtaUrl); ?>"><?php echo e($secondaryCtaText); ?></a>
        <a class="btn btn-primary" href="<?php echo e($primaryCtaUrl); ?>"><?php echo e($primaryCtaText); ?></a>
      </div>
    </div>
  </nav>

  <header class="hero" style="<?php echo $heroStyle; ?>">
    <div class="container">
      <div class="hero-content">
        <span class="eyebrow"><i class="bi bi-stars"></i> แพลตฟอร์มจ้างงานและหางานฟรีแลนซ์</span>
        <h1>FreelanceHub</h1>
        <p class="hero-copy">ค้นหางานที่ตรงทักษะ สมัครงาน ติดตามสถานะ และรีวิวผู้ว่าจ้างได้ในระบบเดียว ผู้ว่าจ้างก็สามารถโพสต์งาน จัดการผู้สมัคร และเก็บประวัติการทำงานได้ครบถ้วน</p>

        <form class="search-strip" method="GET" action="index.php#jobs">
          <label class="search-field">
            <i class="bi bi-search"></i>
            <input type="text" name="title" value="<?php echo e($titleSearch); ?>" placeholder="ค้นหาชื่องาน หมวดงาน หรือบริษัท">
          </label>
          <div class="location-pin-field">
            <i class="bi bi-geo-alt"></i>
            <div class="pin-copy">
              <span class="pin-label">ปักหมุดพื้นที่หางาน</span>
              <span class="pin-status" id="index-location-status"><?php echo e($pinStatusText); ?></span>
            </div>
            <button class="btn-pin" type="button" onclick="openIndexMapModal()">
              <i class="bi bi-pin-map"></i> เลือก
            </button>
            <input type="hidden" name="location" id="index-location" value="<?php echo e($locationSearch); ?>">
            <input type="hidden" name="latitude" id="index-latitude" value="<?php echo $hasLocationPin ? e($searchLat) : ''; ?>">
            <input type="hidden" name="longitude" id="index-longitude" value="<?php echo $hasLocationPin ? e($searchLng) : ''; ?>">
            <input type="hidden" name="preferred_radius_km" id="index-radius" value="<?php echo e($searchRadiusKm); ?>">
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-right"></i> ค้นหา</button>
        </form>

        <div class="hero-actions">
          <a class="btn btn-primary" href="<?php echo e($primaryCtaUrl); ?>"><i class="bi bi-person-plus"></i> <?php echo e($primaryCtaText); ?></a>
          <a class="btn btn-ghost" href="#jobs"><i class="bi bi-briefcase"></i> ดูงานล่าสุด</a>
        </div>

        <div class="hero-metrics" aria-label="Platform statistics">
          <div class="metric"><strong><?php echo e(number_format($stats['jobs'])); ?></strong><span>งานในระบบ</span></div>
          <div class="metric"><strong><?php echo e(number_format($stats['employers'])); ?></strong><span>ผู้ว่าจ้าง</span></div>
          <div class="metric"><strong><?php echo e(number_format($stats['freelancers'])); ?></strong><span>ฟรีแลนซ์</span></div>
        </div>
      </div>
    </div>
  </header>

  <section class="categories-band" id="categories">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="section-kicker"><i class="bi bi-grid"></i> หมวดงาน</div>
          <h2 class="section-title">เริ่มจากสายงานที่สนใจ</h2>
        </div>
        <p class="section-desc">เลือกหมวดเพื่อกรองงานจากข้อมูลจริงในระบบ หรือค้นหาต่อจากช่องด้านบน</p>
      </div>

      <div class="category-grid">
        <?php if(count($categories) === 0): ?>
          <div class="empty-state">ยังไม่มีหมวดงานในระบบ</div>
        <?php endif; ?>
        <?php foreach($categories as $category): ?>
          <a class="category-card" href="index.php?title=<?php echo urlencode($category['name']); ?>#jobs">
            <span class="category-icon"><?php echo e(category_icon($category['name'])); ?></span>
            <span>
              <h3><?php echo e($category['name']); ?></h3>
              <p><?php echo e(number_format((int)$category['jobs'])); ?> งานที่เปิดรับ</p>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="jobs-band" id="jobs">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="section-kicker"><i class="bi bi-briefcase"></i> งานล่าสุด</div>
          <h2 class="section-title"><?php echo ($titleSearch !== '' || $locationSearch !== '') ? 'ผลการค้นหา' : 'งานที่กำลังเปิดรับ'; ?></h2>
        </div>
        <p class="section-desc">ดูรายละเอียดงานก่อนสมัครได้ เมื่อเข้าสู่ระบบด้วยบัญชี Freelancer</p>
      </div>

      <?php if($dbError): ?>
        <div class="db-alert"><?php echo e($dbError); ?></div>
      <?php endif; ?>

      <div class="job-grid">
        <?php if(count($featuredJobs) === 0): ?>
          <div class="empty-state">ไม่พบงานที่ตรงกับเงื่อนไขนี้</div>
        <?php endif; ?>

        <?php foreach($featuredJobs as $job): ?>
          <?php
            $jobImage = trim($job['job_image'] ?? '');
            $jobHref = $role === 'freelancer'
              ? 'view_job.php?job_id=' . urlencode($job['job_id']) . '&return_url=' . urlencode('index.php')
              : 'login.php';
          ?>
          <article class="job-card">
            <div class="job-media">
              <?php if($jobImage !== ''): ?>
                <img src="<?php echo e($jobImage); ?>" alt="<?php echo e($job['title']); ?>">
              <?php else: ?>
                <div class="job-media-fallback"><?php echo e(category_icon($job['category'])); ?></div>
              <?php endif; ?>
            </div>
            <div class="job-body">
              <div class="job-top">
                <div>
                  <h3 class="job-title"><?php echo e($job['title']); ?></h3>
                  <p class="company-name"><?php echo e($job['company']); ?></p>
                </div>
                <span class="job-chip"><?php echo e($job['category'] ?: 'ทั่วไป'); ?></span>
              </div>
              <p class="job-desc"><?php echo e($job['description'] ?: 'ไม่มีรายละเอียดเพิ่มเติม'); ?></p>
              <div class="job-meta">
                <span><i class="bi bi-cash-coin"></i><?php echo e(format_salary($job['salary'])); ?></span>
                <span><i class="bi bi-geo-alt"></i><?php echo e(trim($job['location']) !== '' ? $job['location'] : 'ไม่ระบุสถานที่'); ?></span>
                <?php if($hasLocationPin && isset($job['distance_km'])): ?>
                  <span><i class="bi bi-signpost-split"></i>ห่างประมาณ <?php echo e(number_format((float)$job['distance_km'], 1)); ?> กม.</span>
                <?php endif; ?>
                <span><i class="bi bi-circle-fill"></i><?php echo e(job_status_label($job['status'])); ?></span>
              </div>
              <a class="btn btn-primary" href="<?php echo e($jobHref); ?>"><i class="bi bi-eye"></i> ดูรายละเอียด</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="companies-band" id="companies">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="section-kicker"><i class="bi bi-building"></i> ผู้ว่าจ้าง</div>
          <h2 class="section-title">บริษัทที่มีงานเปิดรับ</h2>
        </div>
        <p class="section-desc">ดูภาพรวมผู้ว่าจ้างที่ใช้งานระบบและจำนวนงานที่เปิดรับอยู่</p>
      </div>

      <div class="company-grid">
        <?php if(count($companies) === 0): ?>
          <div class="empty-state">ยังไม่มีข้อมูลผู้ว่าจ้าง</div>
        <?php endif; ?>
        <?php foreach($companies as $company): ?>
          <article class="company-card">
            <div class="company-avatar">
              <?php if(trim($company['profile_image'] ?? '') !== ''): ?>
                <img src="<?php echo e($company['profile_image']); ?>" alt="<?php echo e($company['name']); ?>">
              <?php else: ?>
                <?php echo e(initials($company['name'])); ?>
              <?php endif; ?>
            </div>
            <h3><?php echo e($company['name']); ?></h3>
            <p><?php echo e($company['description']); ?></p>
            <span class="company-count"><i class="bi bi-briefcase-fill"></i> <?php echo e(number_format((int)$company['jobs'])); ?> งานเปิดรับ</span>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="start-band" id="start">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="section-kicker"><i class="bi bi-arrow-up-right-circle"></i> เริ่มใช้งาน</div>
          <h2 class="section-title">เลือกทางเข้าที่ตรงกับงานของคุณ</h2>
        </div>
        <p class="section-desc">หน้าแรกนี้ออกแบบให้พาไป workflow หลักได้ทันที ไม่ว่าจะสมัครงานหรือจ้างฟรีแลนซ์</p>
      </div>

      <div class="path-grid">
        <article class="path-card">
          <span class="path-icon"><i class="bi bi-person-workspace"></i></span>
          <div>
            <h3>สำหรับ Freelancer</h3>
            <p>ค้นหางาน สมัครงาน ติดตามสถานะ และรีวิวผู้ว่าจ้างหลังจบงาน</p>
          </div>
          <a class="btn btn-primary" href="<?php echo $role === 'freelancer' ? 'browse_jobs.php' : 'register.php'; ?>">เริ่มหางาน</a>
        </article>
        <article class="path-card">
          <span class="path-icon"><i class="bi bi-building-add"></i></span>
          <div>
            <h3>สำหรับ Employer</h3>
            <p>โพสต์งาน จัดการผู้สมัคร บันทึกรีวิว และดูภาพรวมการจ้างงาน</p>
          </div>
          <a class="btn btn-secondary" href="<?php echo $role === 'employer' ? 'post_job.php' : 'register.php'; ?>">เริ่มจ้างงาน</a>
        </article>
      </div>
    </div>
  </section>

  <div class="map-modal" id="indexMapModal">
    <div class="map-container">
      <div class="map-header">
        <h3><i class="bi bi-geo"></i> ปักพื้นที่หางาน</h3>
        <button type="button" class="map-close" onclick="closeIndexMapModal()">&times;</button>
      </div>
      <div class="map-info">
        กดบนแผนที่เพื่อปักหมุดพื้นที่ที่ต้องการหางาน แล้วกำหนดรัศมีการค้นหา
      </div>
      <div class="map-radius-control">
        <div class="radius-row">
          <span>วงค้นหางานบนแผนที่</span>
          <strong class="radius-value"><span id="index-map-radius-label"><?php echo e(number_format($searchRadiusKm, 0)); ?></span> กม.</strong>
        </div>
        <input class="radius-slider" type="range" id="index-map-radius-slider"
               min="1" max="300" step="1"
               value="<?php echo e($searchRadiusKm); ?>"
               oninput="updateIndexRadius(this.value)">
        <div class="radius-scale">
          <span>1 กม.</span>
          <span>300 กม.</span>
        </div>
      </div>
      <div id="index-map"></div>
      <div class="map-footer">
        <button type="button" class="btn-map-cancel" onclick="closeIndexMapModal()">ยกเลิก</button>
        <button type="button" class="btn-map-confirm" onclick="confirmIndexMapLocation()">ยืนยันตำแหน่ง</button>
      </div>
    </div>
  </div>
</div>
<script src="assets/vendor/leaflet/leaflet.min.js"></script>
<script src="assets/js/location-map-picker.js"></script>
<script>
let indexMapInstance = null;
let indexSelectedLat = <?php echo $hasLocationPin ? json_encode($searchLat) : '13.7563'; ?>;
let indexSelectedLng = <?php echo $hasLocationPin ? json_encode($searchLng) : '100.5018'; ?>;
let indexHasSelectedPin = <?php echo $hasLocationPin ? 'true' : 'false'; ?>;
let indexSelectedRadiusKm = Number(document.getElementById('index-radius')?.value || 30);

function setIndexSelectedPosition(lat, lng) {
  indexSelectedLat = Number(lat);
  indexSelectedLng = Number(lng);
  indexHasSelectedPin = true;
}

function updateIndexRadius(value) {
  indexSelectedRadiusKm = Math.max(1, Math.min(300, Number(value) || 30));
  const hiddenRadius = document.getElementById('index-radius');
  const mapRadius = document.getElementById('index-map-radius-slider');
  const mapLabel = document.getElementById('index-map-radius-label');

  if (hiddenRadius) hiddenRadius.value = indexSelectedRadiusKm;
  if (mapRadius) mapRadius.value = indexSelectedRadiusKm;
  if (mapLabel) mapLabel.textContent = indexSelectedRadiusKm;
  if (indexMapInstance) indexMapInstance.setRadius(indexSelectedRadiusKm);
}

function openIndexMapModal() {
  const modal = document.getElementById('indexMapModal');
  modal.classList.add('active');

  setTimeout(() => {
    if (!indexMapInstance) {
      indexMapInstance = createJobFindMapPicker({
        elementId: 'index-map',
        lat: indexSelectedLat,
        lng: indexSelectedLng,
        hasPin: indexHasSelectedPin,
        radiusKm: indexSelectedRadiusKm,
        showCircle: true,
        onChange: setIndexSelectedPosition
      });
    }

    if (indexMapInstance) {
      indexMapInstance.resize();
      if (indexHasSelectedPin) {
        indexMapInstance.setView(indexSelectedLat, indexSelectedLng);
        indexMapInstance.setRadius(indexSelectedRadiusKm);
      }
    }
  }, 100);
}

function closeIndexMapModal() {
  document.getElementById('indexMapModal').classList.remove('active');
}

function confirmIndexMapLocation() {
  if (!indexHasSelectedPin) {
    alert('กรุณาเลือกตำแหน่งบนแผนที่');
    return;
  }

  document.getElementById('index-latitude').value = indexSelectedLat.toFixed(6);
  document.getElementById('index-longitude').value = indexSelectedLng.toFixed(6);
  document.getElementById('index-radius').value = indexSelectedRadiusKm;
  document.getElementById('index-location').value = '';
  document.getElementById('index-location-status').textContent = `ปักหมุดแล้ว รัศมี ${indexSelectedRadiusKm} กม.`;
  closeIndexMapModal();
}

document.getElementById('indexMapModal').addEventListener('click', function(event) {
  if (event.target === this) {
    closeIndexMapModal();
  }
});

updateIndexRadius(indexSelectedRadiusKm);
</script>
</body>
</html>
