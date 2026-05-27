<?php
session_start();
require_once __DIR__ . "/config.php";
require_once "job_image_helpers.php";
require_once "profile_image_helpers.php";

// ตรวจสอบว่าเป็น Freelancer หรือไม่
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: login.php");
    exit();
}

$job_id = $_GET['job_id'] ?? 0;
ensure_job_image_schema($conn);
ensure_profile_image_schema($conn);

function safe_return_url($url, $fallback = ''){
    $url = trim((string)$url);
    if($url === '' || preg_match('/[\r\n]/', $url)){
        return $fallback;
    }

    $parts = parse_url($url);
    if($parts === false || isset($parts['scheme']) || isset($parts['host']) || strpos($url, '//') === 0){
        return $fallback;
    }

    if(!preg_match('/^[A-Za-z0-9_\/.-]+\.php(\?[A-Za-z0-9_%=&.\-\/]*)?$/', $url)){
        return $fallback;
    }

    return $url;
}

$return_url = safe_return_url($_GET['return_url'] ?? '');
$back_url = $return_url ?: 'browse_jobs.php';
$back_label = strpos($back_url, 'my_applications.php') === 0 ? 'กลับไป My Applications' : 'กลับไป Browse Jobs';

// ดึงข้อมูลงาน
$job_query = "SELECT * FROM job WHERE job_id = ?";
$stmt = $conn->prepare($job_query);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: " . $back_url);
    exit();
}

$job_images = get_job_images($conn, $job_id);
$job_status = trim($job['status'] ?? '') ?: 'open';

// ✅ ดึงข้อมูลนายจ้าง + รายละเอียดบริษัท (JOIN 2 ตาราง)
$employer_query = "
    SELECT u.user_id, u.username, u.email, u.phone, u.created_at, u.profile_image,
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
    $employer = ['username' => 'ไม่ทราบชื่อ', 'email' => 'ไม่มีอีเมล', 'phone' => '', 'employer_description' => '', 'created_at' => '', 'profile_image' => ''];
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

// ✅ เตรียมข้อมูลส่งให้ JavaScript
$employer_js_data = [
    'username' => $employer['username'] ?? 'ไม่ทราบชื่อ',
    'email'    => $employer['email'] ?? 'ไม่มีอีเมล',
    'phone'    => $employer['phone'] ?? '',
    'company_details' => $employer['employer_description'] ?? '',
    'profile_image' => $employer['profile_image'] ?? '',
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
    <title><?php echo htmlspecialchars($job['title']); ?> - FreelanceHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap');
        
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            --navy: #0f172a;
            --navy2: #1e293b;
            --navy3: #334155;
            --accent: #6366f1;
            --accent-hover: #4f46e5;
            --light: #f1f5f9;
            --white: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --green: #10b981;
            --yellow: #f59e0b;
            --red: #ef4444;
            --orange: #f97316;
            --blue: #3b82f6;
            --radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,.08);
            --shadow-lg: 0 10px 24px rgba(0,0,0,.12);
            --shadow-xl: 0 20px 40px rgba(0,0,0,.16);
        }
        
        body {
            font-family: 'Sora', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        /* ========== Sidebar ========== */
        .sidebar {
            width: 240px; min-height: 100vh;
            background: var(--navy);
            display: flex; flex-direction: column;
            padding: 28px 0;
            position: fixed; top: 0; left: 0;
            z-index: 100;
        }
        
        .sidebar-brand { padding: 0 24px 28px; border-bottom: 1px solid var(--navy3); }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .logo-icon {
            width: 36px;
            height: 36px;
            background: var(--accent);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
        }
        
        .logo-text {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            line-height: 1.2;
            letter-spacing: 0;
        }
        
        .logo-sub {
            font-size: 11px;
            color: var(--navy3);
            font-weight: 400;
        }
        
        .sidebar-nav { padding: 20px 12px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background .15s, color .15s;
        }
        
        .nav-item i {
            font-size: 17px;
            width: 20px;
            text-align: center;
        }
        
        .nav-item:hover {
            background: var(--navy2);
            color: #e2e8f0;
        }
        
        .nav-item.active {
            background: var(--accent);
            color: #fff;
        }
        
        .nav-divider {
            height: 1px;
            background: var(--navy3);
            margin: 10px 14px;
        }
        
        .sidebar-footer {
            padding: 16px 12px 0;
        }
        
        .nav-logout {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #f87171;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background .15s;
        }
        
        .nav-logout:hover {
            background: rgba(239,68,68,.12);
        }
        
        .nav-logout i {
            font-size: 17px;
        }
        
        /* ========== Main Content ========== */
        .main {
            margin-left: 240px;
            padding: 28px 32px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
        }
        
        .content-container {
            width: 100%;
            max-width: 920px;
        }

        .page-header {
            margin-bottom: 24px;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--white);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all .2s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-back:hover {
            background: var(--light);
            border-color: var(--accent);
            color: var(--accent);
            transform: translateX(-4px);
            box-shadow: var(--shadow-md);
        }
        
        /* ========== Job Detail Card ========== */
        .job-detail {
            background: var(--white);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            margin: 0 auto;
        }
        
        .job-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 28px;
            align-items: start;
            margin-bottom: 32px;
            padding-bottom: 28px;
            border-bottom: 2px solid var(--light);
        }
        
        .job-title-section {
            flex: 1;
        }
        
        .job-category-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
            color: white;
            border-radius: 9px;
            font-size: 10.5px;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            box-shadow: 0 2px 8px rgba(99,102,241,.2);
        }
        
        .job-title {
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 14px;
            color: var(--navy);
            line-height: 1.2;
            letter-spacing: 0;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-top: 12px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 500;
        }
        
        .meta-item i {
            font-size: 16px;
            color: var(--accent);
            width: 18px;
            text-align: center;
        }
        
        .meta-item strong {
            color: var(--text);
            font-weight: 600;
        }
        
        .job-status {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
            justify-content: flex-start;
            min-width: 190px;
            padding: 18px;
            background: var(--light);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            width: 100%;
            justify-content: center;
        }
        
        .status-badge.open {
            background: #d1fae5;
            color: var(--green);
            border: 1px solid #6ee7b7;
        }
        
        .status-badge.closed {
            background: #fee2e2;
            color: var(--red);
            border: 1px solid #fca5a5;
        }
        
        .salary-highlight {
            font-size: 26px;
            font-weight: 800;
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, rgba(99,102,241,.1) 0%, rgba(139,92,246,.1) 100%);
            border-radius: 9px;
            border: 1px solid var(--border);
        }
        
        .salary-highlight i {
            font-size: 22px;
        }

        .job-image-section {
            margin: -12px 0 32px;
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--light);
        }

        .job-image-section img {
            width: 100%;
            max-height: 360px;
            object-fit: cover;
            display: block;
        }

        .job-image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin: -12px 0 32px;
        }

        .job-image-gallery img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--light);
        }
        
        /* ========== Section ========== */
        .section {
            margin-bottom: 32px;
        }
        
        .section-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--navy);
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--light);
        }
        
        .section-title i {
            color: var(--accent);
            font-size: 20px;
            width: 24px;
            text-align: center;
        }
        
        .section-content {
            line-height: 1.75;
            color: var(--text);
            font-size: 14px;
            background: var(--light);
            padding: 18px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        
        /* ========== Info Grid ========== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-top: 0;
        }
        
        .info-card {
            background: var(--light);
            padding: 18px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            transition: all .2s ease;
            text-align: center;
        }
        
        .info-card:hover {
            border-color: var(--accent);
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        }
        
        .info-label {
            font-size: 10.5px;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            line-height: 1.3;
        }
        
        .info-value i {
            color: var(--accent);
            font-size: 18px;
        }
        
        /* ========== Employer Card ========== */
        .employer-card {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 100%);
            border-radius: var(--radius);
            padding: 24px;
            margin-top: 0;
            border: 2px solid var(--accent);
            transition: all .3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            min-height: auto;
            color: white;
        }
        
        .employer-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: -100px;
            width: 300px;
            height: 300px;
            background: rgba(99,102,241,.1);
            border-radius: 50%;
        }
        
        .employer-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(99,102,241,.3);
            border-color: #8b5cf6;
        }
        
        .employer-header {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }
        
        .employer-avatar {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 26px;
            font-weight: 800;
            box-shadow: 0 8px 24px rgba(99,102,241,.4);
            border: 3px solid rgba(255,255,255,.2);
            flex-shrink: 0;
            overflow: hidden;
        }

        .employer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .employer-info {
            flex: 1;
        }
        
        .employer-name {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 8px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0;
        }
        
        .employer-name i {
            color: #fff;
            font-size: 16px;
            opacity: 0.7;
        }
        
        .employer-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            font-size: 13px;
            color: rgba(255,255,255,.85);
        }
        
        .employer-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .employer-meta i {
            color: #fff;
            opacity: 0.8;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: #fff;
        }
        
        .rating-display .stars {
            color: var(--yellow);
        }
        
        .employer-hint {
            font-size: 12px;
            color: rgba(255,255,255,.8);
            margin-top: 12px;
            padding: 12px;
            background: rgba(255,255,255,.1);
            border-radius: 9px;
            text-align: center;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,.15);
            position: relative;
            z-index: 1;
        }
        
        /* ========== Action Buttons ========== */
        .action-bar {
            margin-top: 36px;
            padding-top: 28px;
            border-top: 2px solid var(--light);
            display: flex;
            gap: 16px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .btn-apply {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all .3s ease;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(99,102,241,.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-apply:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(99,102,241,.4);
            color: #fff;
        }
        
        .btn-apply i {
            font-size: 16px;
        }
        
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 24px;
            background: white;
            color: var(--accent);
            border: 2px solid var(--accent);
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all .2s ease;
            text-decoration: none;
        }
        
        .btn-secondary:hover {
            background: var(--accent);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99,102,241,.25);
        }
        
        .btn-secondary i {
            font-size: 16px;
        }
        
        /* ========== Modal ========== */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.7);
            backdrop-filter: blur(8px);
            z-index: 200;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn .2s ease;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-box {
            background: var(--white);
            border-radius: 16px;
            max-width: 640px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: slideUp .3s ease;
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-head {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 100%);
            padding: 26px;
            border-radius: 16px 16px 0 0;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            position: relative;
        }
        
        .modal-av {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 3px solid rgba(255,255,255,.2);
            box-shadow: 0 8px 16px rgba(0,0,0,.2);
            overflow: hidden;
        }

        .modal-av img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .modal-head-info {
            flex: 1;
        }
        
        .modal-head-name {
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
        }
        
        .modal-head-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            color: #94a3b8;
        }
        
        .modal-head-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .modal-close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255,255,255,.15);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all .2s ease;
        }
        
        .modal-close-btn:hover {
            background: rgba(255,255,255,.25);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 26px;
        }
        
        .modal-section {
            margin-bottom: 22px;
        }
        
        .modal-section:last-child {
            margin-bottom: 0;
        }
        
        .modal-section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }
        
        .modal-section-title i {
            color: var(--accent);
        }
        
        .modal-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .modal-info-box {
            background: var(--light);
            border-radius: 12px;
            padding: 14px;
            border: 1px solid var(--border);
        }
        
        .modal-info-box.full {
            grid-column: 1 / -1;
        }
        
        .modal-info-box .lbl {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .modal-info-box .val {
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
            line-height: 1.6;
        }
        
        /* ========== Review Card ========== */
        .reviews-container {
            max-height: 340px;
            overflow-y: auto;
            padding-right: 8px;
        }
        
        .review-card {
            background: var(--light);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border-left: 4px solid var(--accent);
            transition: all .2s ease;
        }
        
        .review-card:hover {
            background: #e0e7ff;
            transform: translateX(4px);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .reviewer-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .reviewer-name i {
            color: var(--accent);
            font-size: 12px;
        }
        
        .review-date {
            font-size: 12px;
            color: var(--muted);
        }
        
        .review-stars {
            font-size: 14px;
            color: var(--yellow);
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .review-comment {
            font-size: 14px;
            color: var(--text);
            line-height: 1.7;
        }
        
        .no-reviews {
            text-align: center;
            padding: 30px 16px;
            color: var(--muted);
            font-size: 14px;
        }
        
        .no-reviews i {
            font-size: 40px;
            color: var(--border);
            margin-bottom: 12px;
        }
        
        /* ========== Responsive ========== */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .main {
                margin-left: 0;
                padding: 20px 16px;
            }

            .job-detail {
                padding: 24px;
            }
            
            .job-header {
                display: block;
            }
            
            .job-status {
                align-items: flex-start;
            }
            
            .job-title {
                font-size: 24px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-info-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-box {
                margin: 0;
                max-height: 100vh;
                border-radius: 0;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-apply,
            .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
<link rel="stylesheet" href="assets/css/freelancehub-theme.css">

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
        <a href="freelancer_dashboard.php" class="nav-item">
            <i class="bi bi-grid"></i> Dashboard
        </a>
        <a href="browse_jobs.php" class="nav-item active">
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

<!-- Main Content -->
<main class="main">
    <div class="content-container">
        <div class="page-header">
            <a href="<?php echo htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn-back">
                <i class="bi bi-arrow-left"></i> <?php echo htmlspecialchars($back_label, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>

        <div class="job-detail">
        <div class="section">
            <h2 class="section-title">
                <i class="bi bi-building"></i>
                ข้อมูลนายจ้าง
            </h2>
            <div class="employer-card" onclick="openEmployerModal()">
                <div class="employer-header">
                    <div class="employer-avatar">
                        <?php if(!empty($employer['profile_image'])): ?>
                            <img src="<?php echo profile_image_src($employer['profile_image']); ?>" alt="Employer profile image">
                        <?php else: ?>
                            <?php echo profile_initials($employer['username'] ?? 'EM'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="employer-info">
                        <h4 class="employer-name">
                            <?php echo htmlspecialchars($employer['username'] ?? 'ไม่ทราบชื่อนายจ้าง'); ?>
                            <i class="bi bi-box-arrow-up-right"></i>
                        </h4>
                        <div class="employer-meta">
                            <span>
                                <i class="bi bi-envelope-fill"></i>
                                <?php echo htmlspecialchars($employer['email'] ?? 'ไม่มีอีเมล'); ?>
                            </span>
                            <span class="rating-display">
                                <span class="stars">
                                    <i class="bi bi-star-fill"></i>
                                </span>
                                <?php echo $avg_rating > 0 ? $avg_rating : 'ยังไม่มีรีวิว'; ?>
                                <span style="color: var(--muted);">(<?php echo count($employer_reviews); ?> รีวิว)</span>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="employer-hint">
                    <i class="bi bi-hand-index"></i> คลิกเพื่อดูโปรไฟล์นายจ้างและรีวิวทั้งหมด
                </div>
            </div>
        </div>

        <div class="job-header">
            <div class="job-title-section">
                <div class="job-category-badge">
                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($job['category'] ?? 'ทั่วไป'); ?>
                </div>
                <h1 class="job-title"><?php echo htmlspecialchars($job['title'] ?? 'ไม่ระบุชื่องาน'); ?></h1>
                <div class="job-meta">
                    <div class="meta-item">
                        <i class="bi bi-geo-alt-fill"></i>
                        <strong><?php echo htmlspecialchars($job['location'] ?? 'ไม่ระบุสถานที่'); ?></strong>
                    </div>
                    <div class="meta-item">
                        <i class="bi bi-clock-fill"></i>
                        ลงเมื่อ <?php echo date('d M Y', strtotime($job['created_at'])); ?>
                    </div>
                    <div class="meta-item">
                        <i class="bi bi-calendar-event-fill"></i>
                        Deadline: <?php echo !empty($job['deadline']) ? date('d M Y', strtotime($job['deadline'])) : 'ไม่ระบุ'; ?>
                    </div>
                </div>
            </div>
            <div class="job-status">
                <div class="status-badge <?php echo strtolower($job_status); ?>">
                    <i class="bi bi-circle-fill"></i>
                    <?php echo strtoupper($job_status); ?>
                </div>
                <div class="salary-highlight">
                    <i class="bi bi-currency-dollar"></i>
                    <?php echo number_format($job['salary'] ?? 0, 0); ?>
                </div>
            </div>
        </div>

        <?php if(!empty($job_images)): ?>
        <div class="job-image-gallery">
            <?php foreach($job_images as $image): ?>
            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="<?php echo htmlspecialchars($job['title'] ?? 'รูปภาพประกอบ'); ?>">
            <?php endforeach; ?>
        </div>
        <?php elseif(!empty($job['image_path'])): ?>
        <div class="job-image-section">
            <img src="<?php echo htmlspecialchars($job['image_path']); ?>" alt="<?php echo htmlspecialchars($job['title'] ?? 'รูปประกอบงาน'); ?>">
        </div>
        <?php endif; ?>

        <div class="section">
            <h2 class="section-title">
                <i class="bi bi-file-text"></i>
                รายละเอียดงาน
            </h2>
            <p class="section-content"><?php echo nl2br(htmlspecialchars($job['description'] ?? 'ไม่มีรายละเอียด')); ?></p>
        </div>

        <div class="section">
            <h2 class="section-title">
                <i class="bi bi-info-circle"></i>
                ข้อมูลเพิ่มเติม
            </h2>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">สถานที่</div>
                    <div class="info-value">
                        <i class="bi bi-geo-alt-fill"></i>
                        <?php echo htmlspecialchars($job['location'] ?? 'ไม่ระบุ'); ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">ค่าตอบแทน</div>
                    <div class="info-value">
                        <i class="bi bi-currency-dollar"></i>
                        <?php echo number_format($job['salary'] ?? 0, 2); ?> บาท
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">วันที่ลงประกาศ</div>
                    <div class="info-value">
                        <i class="bi bi-calendar-check"></i>
                        <?php echo date('d M Y', strtotime($job['created_at'])); ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">Deadline</div>
                    <div class="info-value">
                        <i class="bi bi-calendar-x"></i>
                        <?php echo !empty($job['deadline']) ? date('d M Y', strtotime($job['deadline'])) : 'ไม่ระบุ'; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <a href="apply_job.php?job_id=<?php echo $job_id; ?>" class="btn-apply">
                <i class="bi bi-send-fill"></i>
                สมัครงานนี้
            </a>
            <a href="<?php echo htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn-secondary">
                <i class="bi bi-arrow-left"></i>
                <?php echo htmlspecialchars($back_label, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
    </div>
    </div>
</main>

<!-- Employer Profile Modal -->
<div class="modal-overlay" id="emp-modal">
    <div class="modal-box">
        <div class="modal-head">
            <button class="modal-close-btn" onclick="closeEmployerModal()">
                <i class="bi bi-x-lg"></i>
            </button>
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
                <div class="modal-section-title">
                    <i class="bi bi-building"></i>
                    ข้อมูลนายจ้าง
                </div>
                <div class="modal-info-grid">
                    <div class="modal-info-box full">
                        <div class="lbl">รายละเอียดบริษัท</div>
                        <div class="val" id="emp-m-company"></div>
                    </div>
                    <div class="modal-info-box">
                        <div class="lbl">Email</div>
                        <div class="val" id="emp-m-email"></div>
                    </div>
                    <div class="modal-info-box">
                        <div class="lbl">เบอร์โทรศัพท์</div>
                        <div class="val" id="emp-m-phone"></div>
                    </div>
                </div>
            </div>

            <div class="modal-section">
                <div class="modal-section-title">
                    <i class="bi bi-star-fill"></i>
                    รีวิวจาก Freelancer
                </div>
                <div id="emp-reviews-list" class="reviews-container"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ข้อมูลจาก PHP
const empData = <?php echo json_encode($employer_js_data, JSON_UNESCAPED_UNICODE); ?>;
const empReviews = <?php echo json_encode($employer_reviews, JSON_UNESCAPED_UNICODE); ?>;

function setEmployerModalAvatar(){
    const avatar = document.getElementById('emp-m-av');
    avatar.textContent = '';

    if (empData.profile_image) {
        const img = document.createElement('img');
        img.src = empData.profile_image;
        img.alt = 'Employer profile image';
        avatar.appendChild(img);
    } else {
        avatar.textContent = empData.username.substring(0, 2).toUpperCase();
    }
}

function openEmployerModal() {
    setEmployerModalAvatar();
    document.getElementById('emp-m-name').textContent = empData.username;
    
    document.getElementById('emp-m-company').textContent = empData.company_details || 'ไม่ระบุรายละเอียดบริษัท';
    document.getElementById('emp-m-email').textContent = empData.email;
    document.getElementById('emp-m-phone').textContent = empData.phone || 'ไม่ระบุเบอร์โทรศัพท์';
    
    document.getElementById('emp-m-rating').innerHTML = empData.avg_rating > 0 
        ? `<i class="bi bi-star-fill" style="color:var(--yellow)"></i> ${empData.avg_rating} (${empData.total_reviews} รีวิว)` 
        : '<i class="bi bi-star" style="color:var(--muted)"></i> ยังไม่มีรีวิว';
    
    document.getElementById('emp-m-joined').innerHTML = empData.joined 
        ? `<i class="bi bi-calendar-check"></i> เข้าร่วมเมื่อ ${new Date(empData.joined).toLocaleDateString('th-TH')}` 
        : '';

    const list = document.getElementById('emp-reviews-list');
    list.innerHTML = '';
    
    if (empReviews.length === 0) {
        list.innerHTML = `
            <div class="no-reviews">
                <i class="bi bi-chat-quote"></i>
                <p>ยังไม่มีรีวิวจาก Freelancer</p>
            </div>
        `;
    } else {
        empReviews.forEach(rev => {
            const stars = '⭐'.repeat(Math.round(rev.rating));
            const date = rev.created_at ? new Date(rev.created_at).toLocaleDateString('th-TH') : 'ไม่ระบุวันที่';
            const comment = rev.comment || 'ไม่มีความคิดเห็นเพิ่มเติม';
            
            list.innerHTML += `
                <div class="review-card">
                    <div class="review-header">
                        <span class="reviewer-name">
                            <i class="bi bi-person-circle"></i>
                            ${rev.reviewer_name}
                        </span>
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
