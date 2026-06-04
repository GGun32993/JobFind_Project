<?php
session_start();
require_once __DIR__ . "/../config/config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="freelancer"){
    header("Location: ../login.php");
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

    $resume_path = JOBFIND_UPLOADS_PATH . DIRECTORY_SEPARATOR . $data['file_name'];
    if(is_file($resume_path)){
        unlink($resume_path);
    }

    mysqli_query($conn,"
    DELETE FROM resume
    WHERE resume_id='".$data['resume_id']."'
    ");

}

header("Location: ../freelancer/upload_resume.php");
exit();
