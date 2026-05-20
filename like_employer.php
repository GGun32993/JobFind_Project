<?php
session_start();
include("config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
header("Location: login.php");
exit();
}

$freelancer_id = $_SESSION['user_id'];
$employer_id = $_GET['employer_id'];

// check duplicate
$check = mysqli_query($conn,"
SELECT * FROM like_employer
WHERE freelancer_id='$freelancer_id'
AND employer_id='$employer_id'
");

if(mysqli_num_rows($check) == 0){

mysqli_query($conn,"
INSERT INTO like_employer
(freelancer_id, employer_id)
VALUES
('$freelancer_id','$employer_id')
");

echo "<script>alert('Employer liked');</script>";

}else{

echo "<script>alert('Already liked');</script>";

}

echo "<script>window.history.back();</script>";
?>