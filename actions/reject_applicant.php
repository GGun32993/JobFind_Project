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

// ตรวจสอบว่า application นี้เป็นของงานที่ employer เป็นเจ้าของ
$check = mysqli_query($conn,"
    SELECT ja.application_id, ja.job_id
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

// UPDATE status เป็น rejected
mysqli_query($conn,"
    UPDATE Job_Application
    SET status = 'rejected'
    WHERE application_id = '$application_id'
");

// redirect กลับหน้า view_applicants
header("Location: ../employer/view_applicants.php?job_id=$job_id&rejected=1");
exit();
