<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";

$employer_id = jobfind_require_role('employer');
$job_id = intval($_GET['job_id'] ?? 0);

if($job_id <= 0){
    header("Location: ../employer/manage_jobs.php");
    exit();
}

mysqli_query($conn,"
UPDATE Job
SET status='completed'
WHERE job_id='$job_id'
AND employer_id='$employer_id'
");

header("Location: ../employer/manage_jobs.php");
exit();
?>
