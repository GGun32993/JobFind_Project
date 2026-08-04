<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";

$freelancer_id = jobfind_require_role('freelancer');
$job_id = intval($_GET['job_id'] ?? 0);

if($job_id <= 0){
    header("Location: ../freelancer/browse_jobs.php");
    exit();
}

// check duplicate
$check = mysqli_query($conn,"
SELECT *
FROM Job_Application
WHERE job_id='$job_id'
AND freelancer_id='$freelancer_id'
");

if(mysqli_num_rows($check)>0){

header("Location: ../freelancer/my_applications.php");
exit();

}

// insert application
mysqli_query($conn,"
INSERT INTO Job_Application
(job_id,freelancer_id,status)
VALUES
('$job_id','$freelancer_id','pending')
");

// redirect after apply
header("Location: ../freelancer/my_applications.php");
exit();

?>
