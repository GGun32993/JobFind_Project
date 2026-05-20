<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: login.php");
    exit();
}

$freelancer_id = $_SESSION['user_id'];


$res = mysqli_query($conn,"
SELECT *
FROM resume
WHERE freelancer_id='$freelancer_id'
ORDER BY resume_id DESC
LIMIT 1
");

$data = mysqli_fetch_assoc($res);

if($data){

    unlink("uploads/".$data['file_name']);

    mysqli_query($conn,"
    DELETE FROM resume
    WHERE resume_id='".$data['resume_id']."'
    ");

}

header("Location: upload_resume.php");
exit();