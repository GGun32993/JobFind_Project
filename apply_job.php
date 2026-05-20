<?php
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
header("Location: login.php");
exit();
}

$freelancer_id = $_SESSION['user_id'];
$job_id = $_GET['job_id'];

// check duplicate
$check = mysqli_query($conn,"
SELECT *
FROM job_application
WHERE job_id='$job_id'
AND freelancer_id='$freelancer_id'
");

if(mysqli_num_rows($check)>0){

header("Location: my_applications.php");
exit();

}

// insert application
mysqli_query($conn,"
INSERT INTO job_application
(job_id,freelancer_id,status)
VALUES
('$job_id','$freelancer_id','pending')
");

// redirect after apply
header("Location: my_applications.php");
exit();

?>