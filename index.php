<?php
define('JOBFIND_ALLOW_DB_FAILURE', true);
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/helpers/job_image_helpers.php";
require_once __DIR__ . "/helpers/location_schema.php";
require_once __DIR__ . "/helpers/category_helpers.php";

$titleSearch = trim($_GET['title'] ?? '');
$locationSearch = trim($_GET['location'] ?? '');
$searchLat = isset($_GET['latitude']) && is_numeric($_GET['latitude']) ? (float)$_GET['latitude'] : null;
$searchLng = isset($_GET['longitude']) && is_numeric($_GET['longitude']) ? (float)$_GET['longitude'] : null;
$searchRadiusKm = isset($_GET['preferred_radius_km']) && is_numeric($_GET['preferred_radius_km'])
    ? max(1, min(300, (float)$_GET['preferred_radius_km']))
    : 30;
$hasLocationPin = $searchLat !== null && $searchLng !== null;
$dbError = $conn ? '' : 'ไม่สามารถโหลดข้อมูลงานได้ในขณะนี้ กรุณาลองใหม่อีกครั้งภายหลัง';

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
    if(!$conn || !preg_match('/^[A-Za-z0-9_]+$/', (string)$table)){
        return false;
    }

    $table = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $result && mysqli_num_rows($result) > 0;
}

function column_exists($conn, $table, $column){
    if(
        !$conn
        || !preg_match('/^[A-Za-z0-9_]+$/', (string)$table)
        || !preg_match('/^[A-Za-z0-9_]+$/', (string)$column)
    ){
        return false;
    }

    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function coalesce_sql($expressions, $fallback = "''"){
    $expressions = array_values(array_filter($expressions, fn($expression) => trim((string)$expression) !== ''));
    if(count($expressions) === 0){
        return $fallback;
    }

    return count($expressions) === 1 ? $expressions[0] : 'COALESCE(' . implode(', ', $expressions) . ')';
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
        'admin' => 'admin/dashboard.php',
        'employer' => 'employer/dashboard.php',
        'freelancer' => 'freelancer/dashboard.php',
    ];

    return $targets[$role] ?? 'login.php';
}

function account_profile_for_role($role){
    $targets = [
        'admin' => 'admin/dashboard.php',
        'employer' => 'employer/profile.php',
        'freelancer' => 'freelancer/profile.php',
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

function text_lower($value){
    return function_exists('mb_strtolower')
        ? mb_strtolower((string)$value, 'UTF-8')
        : strtolower((string)$value);
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

function landing_category_groups($conn){
    if(!$conn || !table_exists($conn, 'Categories')){
        return jobfind_default_job_category_groups();
    }

    $hasIconColumn = column_exists($conn, 'Categories', 'icon');
    $iconSelect = $hasIconColumn ? 'icon' : "'' AS icon";
    $categoryOrder = jobfind_category_order_clause($conn, 'name', 'category_id');
    $categories = db_fetch_all($conn, "
        SELECT category_id,
               name,
               $iconSelect
        FROM Categories
        ORDER BY $categoryOrder
    ");

    if(count($categories) === 0){
        return jobfind_default_job_category_groups();
    }

    $groups = [];
    foreach($categories as $category){
        $categoryId = (int)($category['category_id'] ?? 0);
        $groups[$categoryId] = [
            'name' => $category['name'] ?? '',
            'icon' => $category['icon'] ?? '',
            'subcategories' => [],
        ];
    }

    if(table_exists($conn, 'Job_Subcategories')){
        $subcategoryOrder = jobfind_category_sort_expression($conn, 'name') . " ASC, subcategory_id ASC";
        $subcategories = db_fetch_all($conn, "
            SELECT category_id,
                   name
            FROM Job_Subcategories
            ORDER BY category_id ASC, $subcategoryOrder
        ");

        foreach($subcategories as $subcategory){
            $categoryId = (int)($subcategory['category_id'] ?? 0);
            $subcategoryName = trim((string)($subcategory['name'] ?? ''));
            if($subcategoryName !== '' && isset($groups[$categoryId])){
                $groups[$categoryId]['subcategories'][] = $subcategoryName;
            }
        }
    }

    return array_values($groups);
}

$role = $_SESSION['role'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']) && $role !== '';
$dashboardUrl = dashboard_for_role($role);
$accountProfileUrl = account_profile_for_role($role);
$primaryCtaUrl = $isLoggedIn ? $dashboardUrl : 'register.php';
$primaryCtaText = $isLoggedIn ? 'ไปที่แดชบอร์ด' : 'เริ่มต้นใช้งาน';
$secondaryCtaUrl = $isLoggedIn ? $accountProfileUrl : 'login.php';
$secondaryCtaText = $isLoggedIn ? 'จัดการบัญชีของฉัน' : 'เข้าสู่ระบบ';
$freelancerStartUrl = $role === 'freelancer'
    ? 'freelancer/browse_jobs.php'
    : ($isLoggedIn ? $dashboardUrl : 'register.php?role=freelancer');
$employerStartUrl = $role === 'employer'
    ? 'employer/post_job.php'
    : ($isLoggedIn ? $dashboardUrl : 'register.php?role=employer');

$categories = [];
$categoryShowcaseGroups = [];
$featuredJobs = [];
$companies = [];
$stats = ['jobs' => 0, 'total_jobs' => 0, 'employers' => 0, 'freelancers' => 0];

if($conn){
    $hasCategoriesTable = table_exists($conn, 'Categories');
    $hasCategoryIconColumn = $hasCategoriesTable && column_exists($conn, 'Categories', 'icon');
    $hasJobImagesTable = table_exists($conn, 'Job_Images');
    $hasJobImagePathColumn = column_exists($conn, 'Job', 'image_path');
    $hasJobSubcategoryColumn = column_exists($conn, 'Job', 'job_subcategory');
    $jobLatParts = [];
    $jobLngParts = [];

    if(column_exists($conn, 'Job', 'latitude')) $jobLatParts[] = 'j.latitude';
    if(column_exists($conn, 'Employer_Profile', 'latitude')) $jobLatParts[] = 'ep.latitude';
    if(column_exists($conn, 'Users', 'latitude')) $jobLatParts[] = 'u.latitude';
    if(column_exists($conn, 'Job', 'longitude')) $jobLngParts[] = 'j.longitude';
    if(column_exists($conn, 'Employer_Profile', 'longitude')) $jobLngParts[] = 'ep.longitude';
    if(column_exists($conn, 'Users', 'longitude')) $jobLngParts[] = 'u.longitude';

    $canSearchByDistance = $hasLocationPin && count($jobLatParts) > 0 && count($jobLngParts) > 0;

    if($hasCategoriesTable){
        $categoryOrder = jobfind_category_order_clause($conn, 'c.name', 'c.category_id');
        $categoryIconSelect = $hasCategoryIconColumn ? 'c.icon' : "'' AS icon";
        $categoryGroupBy = $hasCategoryIconColumn ? 'c.category_id, c.name, c.icon' : 'c.category_id, c.name';
        $categories = db_fetch_all($conn, "
            SELECT c.name,
                   $categoryIconSelect,
                   COUNT(j.job_id) AS jobs
            FROM Categories c
            LEFT JOIN Job j ON j.category = c.name
                AND j.admin_status = 'approved'
                AND COALESCE(NULLIF(j.status,''), 'open') != 'closed'
            GROUP BY $categoryGroupBy
            ORDER BY $categoryOrder
            LIMIT 20
        ");
    }

    if(count($categories) === 0){
        $categories = db_fetch_all($conn, "
            SELECT COALESCE(NULLIF(category,''), 'Other') AS name,
                   COUNT(*) AS jobs
            FROM Job
            WHERE admin_status = 'approved'
              AND COALESCE(NULLIF(status,''), 'open') != 'closed'
            GROUP BY COALESCE(NULLIF(category,''), 'Other')
            ORDER BY name ASC
            LIMIT 20
        ");
    }

    $jobLatSql = coalesce_sql($jobLatParts, 'NULL');
    $jobLngSql = coalesce_sql($jobLngParts, 'NULL');
    $distanceSql = "(6371 * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(?)) * COS(RADIANS($jobLatSql)) * COS(RADIANS($jobLngSql) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS($jobLatSql))))))";
    $distanceSelect = '';
    $jobOrder = "j.created_at DESC";
    $jobWhere = ["j.admin_status = 'approved'", "COALESCE(NULLIF(j.status,''), 'open') != 'closed'"];
    $jobTypes = '';
    $jobParams = [];

    if($canSearchByDistance){
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
        $titleConditions = ["j.title LIKE ?", "j.category LIKE ?", "u.username LIKE ?", "u.fullname LIKE ?", "ep.employer_name LIKE ?"];
        $titleParams = [$likeTitle, $likeTitle, $likeTitle, $likeTitle, $likeTitle];
        if($hasJobSubcategoryColumn){
            array_splice($titleConditions, 2, 0, "j.job_subcategory LIKE ?");
            array_splice($titleParams, 2, 0, $likeTitle);
        }

        $jobWhere[] = "(" . implode(' OR ', $titleConditions) . ")";
        $jobTypes .= str_repeat('s', count($titleParams));
        array_push($jobParams, ...$titleParams);
    }

    if($locationSearch !== ''){
        $likeLocation = '%' . $locationSearch . '%';
        $jobWhere[] = "j.location LIKE ?";
        $jobTypes .= 's';
        $jobParams[] = $likeLocation;
    }

    $jobImageExpressions = [];
    if($hasJobImagesTable){
        $jobImageExpressions[] = "(SELECT ji.image_path FROM Job_Images ji WHERE ji.job_id = j.job_id ORDER BY ji.sort_order ASC, ji.image_id ASC LIMIT 1)";
    }
    if($hasJobImagePathColumn){
        $jobImageExpressions[] = 'j.image_path';
    }
    $jobImageSelect = coalesce_sql($jobImageExpressions, "''") . " AS job_image";

    $featuredJobs = db_fetch_all($conn, "
        SELECT j.job_id,
               j.title,
               j.description,
               j.salary,
               j.location,
               j.status,
               j.category,
               j.created_at,
               $jobImageSelect
               $distanceSelect,
               COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS company
        FROM Job j
        JOIN Users u ON u.user_id = j.employer_id
        LEFT JOIN Employer_Profile ep ON ep.user_id = u.user_id
        WHERE " . implode(' AND ', $jobWhere) . "
        ORDER BY $jobOrder
        LIMIT 6
    ", $jobTypes, $jobParams);

    $companies = db_fetch_all($conn, "
        SELECT u.user_id,
               u.profile_image,
               COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS name,
               COALESCE(NULLIF(ep.employer_description,''), 'ผู้ว่าจ้างในระบบ Freelance Matching Online') AS description,
               COUNT(j.job_id) AS jobs
        FROM Users u
        LEFT JOIN Employer_Profile ep ON ep.user_id = u.user_id
        LEFT JOIN Job j ON j.employer_id = u.user_id
            AND j.admin_status = 'approved'
            AND COALESCE(NULLIF(j.status,''), 'open') != 'closed'
        WHERE u.role = 'employer'
        GROUP BY u.user_id, u.profile_image, u.username, u.fullname, ep.employer_name, ep.employer_description
        ORDER BY jobs DESC, name ASC
        LIMIT 4
    ");

    $stats = [
        'jobs' => db_count($conn, "SELECT COUNT(*) AS c FROM Job WHERE admin_status = 'approved' AND COALESCE(NULLIF(status,''), 'open') != 'closed'"),
        'total_jobs' => db_count($conn, "SELECT COUNT(*) AS c FROM Job"),
        'employers' => db_count($conn, "SELECT COUNT(*) AS c FROM Users WHERE role = 'employer'"),
        'freelancers' => db_count($conn, "SELECT COUNT(*) AS c FROM Users WHERE role = 'freelancer'"),
    ];
}

$categoryJobsByName = [];
foreach($categories as $category){
    $categoryJobsByName[(string)($category['name'] ?? '')] = (int)($category['jobs'] ?? 0);
}

$rawCategoryGroups = landing_category_groups($conn);
foreach($rawCategoryGroups as $category){
    $categoryName = trim((string)($category['name'] ?? ''));
    if($categoryName === ''){
        continue;
    }

    $subcategories = [];
    foreach(($category['subcategories'] ?? []) as $subcategory){
        $subcategoryName = trim((string)(is_array($subcategory) ? ($subcategory['name'] ?? '') : $subcategory));
        if($subcategoryName !== ''){
            $subcategories[] = $subcategoryName;
        }
    }

    if(empty($subcategories)){
        $subcategories[] = $categoryName;
    }

    $categoryShowcaseGroups[] = [
        'name' => $categoryName,
        'icon' => trim((string)($category['icon'] ?? '')) !== '' ? $category['icon'] : category_icon($categoryName),
        'jobs' => $categoryJobsByName[$categoryName] ?? 0,
        'subcategories' => array_values(array_unique($subcategories)),
    ];
}

$allShowcaseSubcategories = [];
foreach($categoryShowcaseGroups as $categoryGroup){
    foreach(($categoryGroup['subcategories'] ?? []) as $subcategoryName){
        $allShowcaseSubcategories[$subcategoryName] = true;
    }
}

$preferredPopularSubcategories = [
    'Website Development',
    'Mobile Application',
    'UX/UI Design',
    'SEO',
    'ยิงแอด Facebook',
    'Data & AI',
    'ออกแบบโลโก้',
    'ตัดต่อวิดีโอ',
];
$popularSubcategories = [];
foreach($preferredPopularSubcategories as $subcategoryName){
    if(isset($allShowcaseSubcategories[$subcategoryName])){
        $popularSubcategories[] = $subcategoryName;
    }
}
if(count($popularSubcategories) < 8){
    foreach(array_keys($allShowcaseSubcategories) as $subcategoryName){
        if(!in_array($subcategoryName, $popularSubcategories, true)){
            $popularSubcategories[] = $subcategoryName;
        }
        if(count($popularSubcategories) >= 8){
            break;
        }
    }
}

if(!empty($popularSubcategories)){
    array_unshift($categoryShowcaseGroups, [
        'name' => 'งานยอดนิยม',
        'icon' => '🚀',
        'jobs' => $stats['jobs'] ?? 0,
        'subcategories' => $popularSubcategories,
    ]);
}

$activeCategoryIndex = 0;
if($titleSearch !== ''){
    foreach($categoryShowcaseGroups as $index => $categoryGroup){
        $categoryName = (string)$categoryGroup['name'];
        $subcategories = $categoryGroup['subcategories'] ?? [];
        if(text_lower($categoryName) === text_lower($titleSearch)){
            $activeCategoryIndex = $index;
            break;
        }

        foreach($subcategories as $subcategoryName){
            if(text_lower((string)$subcategoryName) === text_lower($titleSearch)){
                $activeCategoryIndex = $index;
                break 2;
            }
        }
    }
}

$isSearchActive = $titleSearch !== '' || $locationSearch !== '' || $hasLocationPin;
$heroStyle = "--hero-bg: #f1f5f9;";
$defaultPinStatusText = 'ยังไม่ได้ปักหมุดพื้นที่หางาน';
$pinStatusText = $hasLocationPin
    ? 'ปักหมุดแล้ว รัศมี ' . number_format($searchRadiusKm, 0) . ' กม.'
    : ($locationSearch !== '' ? $locationSearch : $defaultPinStatusText);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="assets/images/jobfind-logo-icon.png?v=14">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Freelance Matching Online แพลตฟอร์มหางานฟรีแลนซ์และจ้างงานแบบเป็นระบบ">
<title>Freelance Matching Online - หางานฟรีแลนซ์และจ้างงาน</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.min.css">
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">
<?php /* Legacy inline landing stylesheet intentionally disabled; assets/css/index-modern.css owns this page. ?>
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

  html { scroll-behavior: smooth; background: var(--light); }
  body { min-height: 100vh; margin: 0; color: var(--text); background: var(--light); }
  a { color: inherit; text-decoration: none; }
  button, input { font: inherit; }

  .shell,
  .shell *,
  .shell *::before,
  .shell *::after {
    box-sizing: border-box;
  }

  .shell { overflow-x: hidden; background: var(--light); }
  .shell .container { width: min(1160px, calc(100% - 36px)); margin: 0 auto; }

  .top-nav {
    position: sticky;
    top: 0;
    z-index: 20;
    padding: 14px 0;
    background: rgba(241, 245, 249, .92) !important;
    border-bottom: 0;
    backdrop-filter: blur(16px);
  }

  .nav-inner {
    min-height: 74px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 0 24px;
    border: 1px solid rgba(219, 228, 239, .96);
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 16px 38px rgba(15, 23, 42, .08);
  }

  .shell .brand {
    display: inline-flex;
    align-items: center;
    gap: 0;
    color: #14213d !important;
    font-weight: 800;
  }

  .shell .brand-icon {
    width: 56px;
    height: 52px;
    border-radius: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    color: #fff;
    box-shadow: 0 12px 24px rgba(91, 95, 244, .18);
    overflow: hidden;
    padding: 0;
  }

  .shell .brand-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
  }

  .shell .brand-name { display: none; }

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
  .btn-ghost { background: #ffffff; color: #24364f; border-color: var(--border); }
  .btn-ghost:hover { background: #eef2ff; color: var(--accent); border-color: #c7d2fe; }

  .hero .btn-ghost {
    background: rgba(255, 255, 255, .06);
    color: #e2e8f0;
    border-color: rgba(255, 255, 255, .14);
  }

  .hero .btn-ghost:hover {
    background: rgba(255, 255, 255, .10);
    color: #ffffff;
    border-color: rgba(226, 232, 240, .24);
  }

  .shell .hero {
    min-height: auto;
    display: flex;
    align-items: center;
    background: var(--hero-bg) !important;
    background-size: cover !important;
    background-position: center !important;
    color: #0f172a !important;
    border-bottom: 0;
    padding: 18px 0 34px;
  }

  .hero-layout {
    min-height: 620px;
    display: grid;
    grid-template-columns: minmax(0, .95fr) minmax(380px, 1fr);
    align-items: stretch;
    gap: 0;
    overflow: hidden;
    border: 1px solid rgba(219, 228, 239, .96);
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .12);
  }

  .hero-content {
    position: relative;
    width: 100%;
    overflow: hidden;
    padding: 64px 48px;
    background: var(--navy);
    color: #ffffff;
  }

  .hero-content::before {
    content: "";
    position: absolute;
    top: -92px;
    right: -88px;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    background: rgba(99, 102, 241, .15);
  }

  .hero-content::after {
    content: "";
    position: absolute;
    bottom: -70px;
    left: -70px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(99, 102, 241, .10);
  }

  .hero-content > * {
    position: relative;
    z-index: 1;
  }

  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid rgba(255, 255, 255, .14);
    border-radius: 999px;
    background: rgba(255, 255, 255, .06);
    color: #c7d2fe;
    font-size: 13px;
    font-weight: 800;
    box-shadow: none;
  }

  .shell .hero h1 {
    margin: 22px 0 16px;
    color: #ffffff !important;
    font-size: clamp(42px, 5.4vw, 66px);
    line-height: 1.05;
    font-weight: 900;
  }

  .hero-title-part {
    display: inline;
  }

  .shell .hero-copy {
    max-width: 660px;
    margin: 0 0 30px;
    color: #94a3b8 !important;
    font-size: 17px;
    line-height: 1.75;
  }

  .search-strip {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    max-width: 520px;
    margin-bottom: 24px;
  }

  .search-field {
    min-height: 54px;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 16px;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    background: rgba(255, 255, 255, .94);
    color: #1f314a;
  }

  .search-field i { color: var(--accent); font-size: 18px; }
  .search-field input { width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; color: var(--text); font-size: 14px; }
  .search-field input::placeholder { color: #7a8ba2; }
  .search-strip .btn { width: 100%; min-height: 54px; }

  .location-pin-field {
    min-height: 54px;
    min-width: 0;
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 8px 12px 8px 16px;
    border: 1px solid #dbeafe;
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
    white-space: nowrap;
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
    color: #94a3b8;
  }

  .metric {
    min-width: 132px;
    padding: 12px 0;
    border-top: 1px solid rgba(255, 255, 255, .16);
  }

  .metric strong {
    display: block;
    margin-bottom: 2px;
    color: #ffffff;
    font-size: 24px;
    line-height: 1;
  }

  .metric span { font-size: 12px; font-weight: 800; color: #64748b; }

  .hero-side {
    display: grid;
    gap: 16px;
    align-self: stretch;
    align-content: center;
    padding: 44px;
    background: #ffffff;
  }

  .hero-logo-showcase {
    min-height: 230px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
  }

  .hero-logo-showcase img {
    width: min(100%, 285px);
    height: auto;
    max-height: 245px;
    object-fit: contain;
    display: block;
  }

  .role-choice-panel {
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    backdrop-filter: none;
    padding: 0;
  }

  .role-choice-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 30px;
    padding: 0 10px;
    border-radius: 999px;
    background: #ecfdf5;
    color: #047857;
    font-size: 12px;
    font-weight: 900;
  }

  .role-choice-panel h2 {
    margin: 16px 0 8px;
    color: #0b1220 !important;
    font-size: clamp(24px, 3vw, 34px);
    line-height: 1.16;
    font-weight: 900;
  }

  .role-choice-panel p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.65;
  }

  .role-choice-list {
    display: grid;
    gap: 12px;
    margin-top: 20px;
  }

  .role-choice-card {
    min-height: 104px;
    display: grid;
    grid-template-columns: 50px 1fr auto;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
    text-decoration: none;
    transition: transform .15s, border-color .15s, background .15s;
  }

  .role-choice-card:hover {
    transform: translateY(-1px);
    border-color: #93c5fd;
    background: #f8fbff;
    color: #0f172a;
  }

  .role-choice-icon {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #dcfce7;
    color: #047857;
    font-size: 22px;
  }

  .role-choice-card:nth-child(2) .role-choice-icon {
    background: #eef2ff;
    color: var(--accent);
  }

  .role-choice-card h3 {
    margin: 0;
    color: #0b1220;
    font-size: 16px;
    line-height: 1.25;
    font-weight: 900;
  }

  .role-choice-card p {
    margin-top: 5px;
    color: #64748b;
    font-size: 12.5px;
    line-height: 1.5;
  }

  .role-choice-action {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0 12px;
    border-radius: 8px;
    background: #eef2ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
  }

  section {
    padding: 64px 0;
    border-top: 0;
  }

  .categories-band {
    background: var(--light);
  }

  .jobs-band {
    background: var(--light);
  }

  .companies-band {
    background: var(--light);
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

  .category-showcase {
    padding: 28px 32px 30px;
    border: 1px solid #e5edf7;
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .08);
  }

  .category-tabs-shell {
    position: relative;
  }

  .category-tabs {
    display: flex;
    gap: 22px;
    overflow-x: auto;
    padding: 4px 54px 18px 4px;
    scroll-snap-type: x proximity;
    scrollbar-width: none;
  }

  .category-tabs::-webkit-scrollbar {
    display: none;
  }

  .category-tab {
    position: relative;
    flex: 0 0 132px;
    display: grid;
    justify-items: center;
    gap: 8px;
    border: 0;
    background: transparent;
    color: #5d6f86;
    padding: 0 8px 14px;
    cursor: pointer;
    scroll-snap-align: start;
  }

  .category-tab::after {
    content: "";
    position: absolute;
    left: 12px;
    right: 12px;
    bottom: 0;
    height: 4px;
    border-radius: 999px;
    background: transparent;
  }

  .category-tab.active {
    color: #0f172a;
  }

  .category-tab.active::after {
    background: #2563eb;
  }

  .category-tab-icon {
    width: 58px;
    height: 58px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: linear-gradient(180deg, #f8fafc, #eef2ff);
    color: #2563eb;
    font-size: 30px;
    filter: grayscale(.45);
    box-shadow: inset 0 -10px 18px rgba(148, 163, 184, .16);
    transition: filter .15s, transform .15s, box-shadow .15s;
  }

  .category-tab.active .category-tab-icon,
  .category-tab:hover .category-tab-icon {
    filter: none;
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(37, 99, 235, .14);
  }

  .category-tab-label {
    min-height: 42px;
    display: flex;
    align-items: center;
    text-align: center;
    font-size: 14px;
    font-weight: 900;
    line-height: 1.35;
  }

  .category-scroll-next {
    position: absolute;
    right: 0;
    top: 22px;
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #dbeafe;
    border-radius: 999px;
    background: rgba(255, 255, 255, .96);
    color: #2563eb;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .10);
    cursor: pointer;
  }

  .category-panels {
    margin-top: 16px;
  }

  .category-panel {
    display: none;
  }

  .category-panel.active {
    display: block;
  }

  .category-tile-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
  }

  .category-tile {
    min-height: 100px;
    position: relative;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    border-radius: 8px;
    padding: 18px;
    color: #fff;
    background: #0f172a;
    text-decoration: none;
    box-shadow: 0 14px 28px rgba(15, 23, 42, .10);
  }

  .category-tile::before {
    content: "";
    position: absolute;
    inset: 0;
    background: var(--tile-bg);
    background-size: cover;
    background-position: center;
    transform: scale(1);
    transition: transform .2s;
  }

  .category-tile::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(90deg, rgba(7, 19, 39, .76), rgba(7, 19, 39, .32)),
      linear-gradient(0deg, rgba(7, 19, 39, .32), rgba(7, 19, 39, .08));
  }

  .category-tile:hover::before {
    transform: scale(1.05);
  }

  .category-tile-title {
    position: relative;
    z-index: 1;
    color: #fff;
    font-size: 18px;
    font-weight: 900;
    line-height: 1.28;
    text-shadow: 0 2px 10px rgba(0, 0, 0, .32);
  }

  .category-visual-1 { --tile-bg: radial-gradient(circle at 18% 26%, rgba(34, 211, 238, .70), transparent 30%), linear-gradient(135deg, #0f172a, #155e75 52%, #2563eb); }
  .category-visual-2 { --tile-bg: radial-gradient(circle at 72% 18%, rgba(250, 204, 21, .70), transparent 28%), linear-gradient(135deg, #1e1b4b, #7c3aed 50%, #db2777); }
  .category-visual-3 { --tile-bg: radial-gradient(circle at 22% 72%, rgba(16, 185, 129, .70), transparent 28%), linear-gradient(135deg, #052e16, #0f766e 48%, #0284c7); }
  .category-visual-4 { --tile-bg: radial-gradient(circle at 80% 34%, rgba(248, 113, 113, .72), transparent 28%), linear-gradient(135deg, #111827, #7f1d1d 48%, #ea580c); }
  .category-visual-5 { --tile-bg: radial-gradient(circle at 24% 30%, rgba(147, 197, 253, .78), transparent 30%), linear-gradient(135deg, #172554, #1d4ed8 52%, #06b6d4); }
  .category-visual-6 { --tile-bg: radial-gradient(circle at 78% 22%, rgba(216, 180, 254, .70), transparent 28%), linear-gradient(135deg, #312e81, #6d28d9 52%, #0f172a); }
  .category-visual-7 { --tile-bg: radial-gradient(circle at 28% 76%, rgba(251, 146, 60, .70), transparent 26%), linear-gradient(135deg, #431407, #92400e 50%, #0f172a); }
  .category-visual-8 { --tile-bg: radial-gradient(circle at 72% 72%, rgba(45, 212, 191, .72), transparent 28%), linear-gradient(135deg, #082f49, #0f766e 48%, #1e293b); }

  .category-more-row {
    display: flex;
    justify-content: flex-end;
    margin-top: 22px;
  }

  .category-more {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #2563eb;
    font-size: 15px;
    font-weight: 900;
  }

  .category-more:hover {
    color: #1d4ed8;
  }

  .job-card,
  .company-card,
  .empty-state,
  .db-alert {
    border: 1px solid var(--border);
    border-radius: 14px;
    background: #ffffff;
    box-shadow: var(--shadow-sm);
  }

  .category-tile:hover,
  .job-card:hover,
  .company-card:hover {
    border-color: #bcc8ff;
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
  }

  .job-title,
  .company-card h3 {
    margin: 0;
    color: #172033;
    font-size: 16px;
    font-weight: 900;
    line-height: 1.3;
  }

  .company-card p {
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
    .hero-layout { min-height: auto; grid-template-columns: 1fr; gap: 0; }
    .hero-content { padding: 48px; }
    .hero-side { width: 100%; padding: 42px 48px 48px; }
    .role-choice-panel { width: 100%; }
    .category-showcase { padding: 22px; }
    .category-tile-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .job-grid { grid-template-columns: repeat(2, 1fr); }
    .company-grid { grid-template-columns: repeat(2, 1fr); }
  }

  @media (max-width: 720px) {
    .shell .container { width: 100%; max-width: 1160px; padding: 0 14px; }
    .nav-inner { min-height: auto; padding: 14px; flex-wrap: wrap; border-radius: 18px; }
    .brand { flex: 1 1 100%; }
    .nav-actions { width: 100%; }
    .nav-actions .btn { flex: 1 1 0; min-width: 0; padding: 0 10px; }
    .hero { min-height: auto; }
    .hero-layout { border-radius: 18px; }
    .hero-content { padding: 38px 24px 30px; }
    .shell .hero h1 { font-size: clamp(31px, 9.2vw, 38px); }
    .hero-title-part { display: block; }
    .shell .hero-copy { max-width: 100%; font-size: 15.5px; overflow-wrap: anywhere; }
    .hero-side { padding: 28px 22px 30px; }
    .hero-logo-showcase { min-height: 140px; padding: 0; }
    .hero-logo-showcase img { max-height: 170px; }
    .search-strip { width: 100%; max-width: 100%; grid-template-columns: 1fr; }
    .search-field,
    .location-pin-field { width: 100%; max-width: 100%; }
    .location-pin-field { grid-template-columns: auto minmax(0, 1fr) 40px; }
    .btn-pin { width: 40px; padding: 0; font-size: 0; }
    .btn-pin i { font-size: 16px; }
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
    .role-choice-panel { padding: 0; }
    .role-choice-card { grid-template-columns: 44px 1fr; align-items: flex-start; }
    .role-choice-icon { width: 44px; height: 44px; }
    .role-choice-action { grid-column: 1 / -1; }
    .section-head { align-items: flex-start; flex-direction: column; }
    section { padding: 46px 0; }
    .category-showcase { padding: 16px; }
    .category-tabs { gap: 12px; padding-right: 44px; }
    .category-tab { flex-basis: 104px; padding-inline: 4px; }
    .category-tab-icon { width: 48px; height: 48px; font-size: 24px; }
    .category-tab-label { min-height: 38px; font-size: 12.5px; }
    .category-scroll-next { top: 16px; }
    .category-tile-grid,
    .job-grid,
    .company-grid { grid-template-columns: 1fr; }
    .category-tile { min-height: 92px; padding: 16px; }
    .category-tile-title { font-size: 16px; }
  }
</style>
<?php */ ?>
<link rel="stylesheet" href="assets/css/index-modern.css?v=20260608-layout2">
</head>
<body>
<div class="shell">
  <nav class="top-nav">
    <div class="container nav-inner">
      <a class="brand" href="index.php" aria-label="Freelance Matching Online home">
        <span class="brand-icon"><img class="brand-logo-img" src="assets/images/jobfind-logo.png?v=14" alt="Freelance Matching Online logo"></span>
        <span class="brand-name" style="display:none!important;">Freelance Matching Online</span>
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
      <div class="hero-layout">
        <div class="hero-content">
          <span class="eyebrow"><i class="bi bi-stars"></i> Freelance Matching Online สำหรับฟรีแลนซ์และผู้ว่าจ้าง</span>
          <h1><span class="hero-title-part">หางานที่ใช่</span> <span class="hero-title-part">จ้างคนที่ชอบ</span></h1>
          <p class="hero-copy">เริ่มจากเลือกบทบาทของคุณ ค้นหางานที่ตรงทักษะ หรือโพสต์งานเพื่อหาคนที่เหมาะกับโปรเจกต์ พร้อมระบบสมัครงาน โปรไฟล์ รีวิว และติดตามสถานะในที่เดียว</p>

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
              <div class="pin-actions">
                <button class="btn-pin" type="button" onclick="openIndexMapModal()">
                  <i class="bi bi-pin-map"></i> เลือก
                </button>
                <?php if($hasLocationPin || $locationSearch !== ''): ?>
                  <button class="btn-pin btn-pin-clear" type="button" onclick="clearIndexLocation()">
                    <i class="bi bi-x-lg"></i> ล้าง
                  </button>
                <?php endif; ?>
              </div>
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

        <div class="hero-side">
          <div class="hero-logo-showcase" aria-hidden="true">
            <img src="assets/images/jobfind-logo.png?v=14" alt="">
          </div>

          <aside class="role-choice-panel" id="start" aria-label="เลือกเส้นทางเริ่มใช้งาน">
            <span class="role-choice-kicker"><i class="bi bi-arrow-up-right-circle"></i> เริ่มใช้งาน</span>
            <h2>เลือกบทบาทของคุณ</h2>
            <p>เริ่มจากฝั่งที่ใช่ แล้วระบบจะพาไปยังขั้นตอนที่เหมาะกับงานของคุณทันที</p>

            <div class="role-choice-list">
              <a class="role-choice-card" href="<?php echo e($freelancerStartUrl); ?>">
                <span class="role-choice-icon"><i class="bi bi-person-workspace"></i></span>
                <span>
                  <h3>Freelancer</h3>
                  <p>ค้นหางาน สมัครงาน และจัดการโปรไฟล์สำหรับรับงาน</p>
                </span>
                <span class="role-choice-action">เริ่มหางาน <i class="bi bi-arrow-right"></i></span>
              </a>

              <a class="role-choice-card" href="<?php echo e($employerStartUrl); ?>">
                <span class="role-choice-icon"><i class="bi bi-building-add"></i></span>
                <span>
                  <h3>Employer</h3>
                  <p>โพสต์งาน คัดเลือกผู้สมัคร และจัดการการจ้างงาน</p>
                </span>
                <span class="role-choice-action">เริ่มจ้างงาน <i class="bi bi-arrow-right"></i></span>
              </a>
            </div>
          </aside>
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
        <p class="section-desc">เลือกหมวดหลักเพื่อดูงานย่อย แล้วกดการ์ดเพื่อกรองงานที่ตรงกับความสนใจ</p>
      </div>

      <?php if(count($categoryShowcaseGroups) === 0): ?>
        <div class="category-showcase">
          <div class="empty-state">ยังไม่มีหมวดงานในระบบ</div>
        </div>
      <?php else: ?>
        <div class="category-showcase" data-category-showcase>
          <div class="category-tabs-shell">
            <div class="category-tabs" role="tablist" aria-label="หมวดงาน">
              <?php foreach($categoryShowcaseGroups as $index => $category): ?>
                <?php $isActiveCategory = $index === $activeCategoryIndex; ?>
                <button class="category-tab<?php echo $isActiveCategory ? ' active' : ''; ?>"
                        type="button"
                        role="tab"
                        id="category-tab-<?php echo e($index); ?>"
                        aria-controls="category-panel-<?php echo e($index); ?>"
                        aria-selected="<?php echo $isActiveCategory ? 'true' : 'false'; ?>"
                        tabindex="<?php echo $isActiveCategory ? '0' : '-1'; ?>"
                        data-category-tab="<?php echo e($index); ?>">
                  <span class="category-tab-icon" aria-hidden="true"><?php echo e($category['icon']); ?></span>
                  <span class="category-tab-label"><?php echo e($category['name']); ?></span>
                </button>
              <?php endforeach; ?>
            </div>
            <button class="category-scroll-next" type="button" aria-label="เลื่อนหมวดงาน" data-category-scroll-next>
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>

          <div class="category-panels">
            <?php foreach($categoryShowcaseGroups as $index => $category): ?>
              <?php
                $isActiveCategory = $index === $activeCategoryIndex;
                $subcategoryCards = array_slice($category['subcategories'] ?? [], 0, 8);
              ?>
              <div class="category-panel<?php echo $isActiveCategory ? ' active' : ''; ?>"
                   id="category-panel-<?php echo e($index); ?>"
                   role="tabpanel"
                   aria-labelledby="category-tab-<?php echo e($index); ?>"
                   data-category-panel="<?php echo e($index); ?>">
                <div class="category-tile-grid">
                  <?php foreach($subcategoryCards as $cardIndex => $subcategoryName): ?>
                    <?php $visualClass = 'category-visual-' . (($cardIndex % 8) + 1); ?>
                    <a class="category-tile <?php echo e($visualClass); ?>" href="index.php?title=<?php echo urlencode($subcategoryName); ?>#jobs">
                      <span class="category-tile-title"><?php echo e($subcategoryName); ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
                <div class="category-more-row">
                  <a class="category-more" href="index.php?title=<?php echo urlencode($category['name']); ?>#jobs">
                    ดูเพิ่มเติม <i class="bi bi-arrow-right"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="jobs-band" id="jobs">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="section-kicker"><i class="bi bi-briefcase"></i> งานล่าสุด</div>
          <h2 class="section-title"><?php echo $isSearchActive ? 'ผลการค้นหา' : 'งานที่กำลังเปิดรับ'; ?></h2>
        </div>
        <p class="section-desc">ดูรายละเอียดงานก่อนสมัครได้ เมื่อเข้าสู่ระบบด้วยบัญชี Freelancer</p>
      </div>

      <?php if($dbError): ?>
        <div class="db-alert"><?php echo e($dbError); ?></div>
      <?php endif; ?>

      <div class="job-grid">
        <?php if(!$dbError && count($featuredJobs) === 0): ?>
          <?php if($isSearchActive): ?>
            <div class="empty-state">ไม่พบงานที่ตรงกับเงื่อนไขนี้</div>
          <?php elseif(($stats['total_jobs'] ?? 0) > 0): ?>
            <div class="empty-state">มีข้อมูลงานในระบบแล้ว แต่ยังไม่มีงานที่อนุมัติและเปิดรับอยู่ในขณะนี้</div>
          <?php else: ?>
            <div class="empty-state">ยังไม่มีข้อมูลงานในระบบ</div>
          <?php endif; ?>
        <?php endif; ?>

        <?php foreach($featuredJobs as $job): ?>
          <?php
            $jobImage = trim($job['job_image'] ?? '');
            if($role === 'freelancer'){
              $jobHref = 'freelancer/view_job.php?job_id=' . urlencode($job['job_id']) . '&return_url=' . urlencode('index.php');
            } elseif($role === 'admin'){
              $jobHref = 'admin/job_detail.php?id=' . urlencode($job['job_id']);
            } elseif($role === 'employer'){
              $jobHref = $dashboardUrl;
            } else {
              $jobHref = 'login.php';
            }
          ?>
          <article class="job-card">
            <div class="job-media">
              <?php if($jobImage !== ''): ?>
                <img src="<?php echo e(jobfind_url($jobImage)); ?>" alt="<?php echo e($job['title']); ?>">
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
                <img src="<?php echo e(jobfind_url($company['profile_image'])); ?>" alt="<?php echo e($company['name']); ?>">
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
<script src="assets/js/location-map-picker.js?v=geoapify-search-20260617" data-geoapify-key="<?php echo jobfind_geoapify_api_key_attr(); ?>"></script>
<script>
document.querySelectorAll('[data-category-showcase]').forEach((showcase) => {
  const tabsWrap = showcase.querySelector('.category-tabs');
  const tabs = Array.from(showcase.querySelectorAll('[data-category-tab]'));
  const panels = Array.from(showcase.querySelectorAll('[data-category-panel]'));
  const scrollNext = showcase.querySelector('[data-category-scroll-next]');

  function activateCategory(index) {
    tabs.forEach((tab) => {
      const isActive = tab.dataset.categoryTab === String(index);
      tab.classList.toggle('active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      tab.tabIndex = isActive ? 0 : -1;
      if (isActive) {
        tab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      }
    });

    panels.forEach((panel) => {
      panel.classList.toggle('active', panel.dataset.categoryPanel === String(index));
    });
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => activateCategory(tab.dataset.categoryTab));
    tab.addEventListener('keydown', (event) => {
      const currentIndex = tabs.indexOf(tab);
      const nextIndex = event.key === 'ArrowRight'
        ? Math.min(currentIndex + 1, tabs.length - 1)
        : (event.key === 'ArrowLeft' ? Math.max(currentIndex - 1, 0) : currentIndex);

      if (nextIndex !== currentIndex) {
        event.preventDefault();
        activateCategory(tabs[nextIndex].dataset.categoryTab);
        tabs[nextIndex].focus();
      }
    });
  });

  scrollNext?.addEventListener('click', () => {
    tabsWrap?.scrollBy({ left: 360, behavior: 'smooth' });
  });
});

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

function clearIndexLocation() {
  indexHasSelectedPin = false;
  const latitude = document.getElementById('index-latitude');
  const longitude = document.getElementById('index-longitude');
  const location = document.getElementById('index-location');
  const status = document.getElementById('index-location-status');

  if (latitude) latitude.value = '';
  if (longitude) longitude.value = '';
  if (location) location.value = '';
  if (status) status.textContent = <?php echo json_encode($defaultPinStatusText, JSON_UNESCAPED_UNICODE); ?>;
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
