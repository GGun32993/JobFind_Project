<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="employer"){
    header("Location: login.php");
    exit();
}

if(!isset($_GET['application_id'])){
    header("Location: employer_manage_jobs.php");
    exit();
}

$application_id = intval($_GET['application_id']);
$employer_id    = $_SESSION['user_id'];

// ── ตรวจสอบว่า application นี้เป็นของงาน employer คนนี้จริง ──
$check = mysqli_query($conn,"
    SELECT ja.application_id, ja.job_id, ja.freelancer_id
    FROM job_application ja
    JOIN job j ON j.job_id = ja.job_id
    WHERE ja.application_id = '$application_id'
    AND j.employer_id = '$employer_id'
");

if(mysqli_num_rows($check) == 0){
    header("Location: employer_manage_jobs.php");
    exit();
}

$row    = mysqli_fetch_assoc($check);
$job_id = $row['job_id'];

// ── UPDATE status เป็น accepted ──
mysqli_query($conn,"
    UPDATE job_application
    SET status = 'accepted'
    WHERE application_id = '$application_id'
");

// ── ปิดงานทันทีเมื่อรับคนแล้ว freelancer อื่นจะไม่เห็นอีก ──
mysqli_query($conn,"
    UPDATE job
    SET status = 'closed'
    WHERE job_id = '$job_id'
");

// ── reject ผู้สมัครคนอื่นในงานเดียวกันอัตโนมัติ ──
mysqli_query($conn,"
    UPDATE job_application
    SET status = 'rejected'
    WHERE job_id = '$job_id'
    AND application_id != '$application_id'
    AND status = 'pending'
");

// ── redirect กลับ view_applicants ──
header("Location: view_applicants.php?job_id=$job_id&hired=1");
exit();