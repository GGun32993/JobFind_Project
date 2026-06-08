<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";

if(!isset($_GET['application_id'])){
    header("Location: ../employer/manage_jobs.php");
    exit();
}

$application_id = intval($_GET['application_id']);
$employer_id    = jobfind_require_role('employer');

// ── ตรวจสอบว่า application นี้เป็นของงาน employer คนนี้จริง ──
$check = mysqli_query($conn,"
    SELECT ja.application_id, ja.job_id, ja.freelancer_id
    FROM Job_Application ja
    JOIN Job j ON j.job_id = ja.job_id
    WHERE ja.application_id = '$application_id'
    AND j.employer_id = '$employer_id'
");

if(mysqli_num_rows($check) == 0){
    header("Location: ../employer/manage_jobs.php");
    exit();
}

$row    = mysqli_fetch_assoc($check);
$job_id = $row['job_id'];

// ── UPDATE status เป็น accepted ──
mysqli_query($conn,"
    UPDATE Job_Application
    SET status = 'accepted'
    WHERE application_id = '$application_id'
");

// ── ปิดงานทันทีเมื่อรับคนแล้ว freelancer อื่นจะไม่เห็นอีก ──
mysqli_query($conn,"
    UPDATE Job
    SET status = 'closed'
    WHERE job_id = '$job_id'
");

// ── reject ผู้สมัครคนอื่นในงานเดียวกันอัตโนมัติ ──
mysqli_query($conn,"
    UPDATE Job_Application
    SET status = 'rejected'
    WHERE job_id = '$job_id'
    AND application_id != '$application_id'
    AND status = 'pending'
");

// ── ดึง freelancer_id ──
$freelancer_id = $row['freelancer_id'];

// ── redirect ไปรีวิว Freelancer ──
header("Location: ../employer/rate_freelancer.php?freelancer_id=$freelancer_id&job_id=$job_id");
exit();
