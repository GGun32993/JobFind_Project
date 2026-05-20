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

// ตรวจสอบว่า application นี้เป็นของงานที่ employer เป็นเจ้าของ
$check = mysqli_query($conn,"
    SELECT ja.application_id, ja.job_id
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

// UPDATE status เป็น rejected
mysqli_query($conn,"
    UPDATE job_application
    SET status = 'rejected'
    WHERE application_id = '$application_id'
");

// redirect กลับหน้า view_applicants
header("Location: view_applicants.php?job_id=$job_id&rejected=1");
exit();