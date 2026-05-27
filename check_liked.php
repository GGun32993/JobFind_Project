<?php
session_start();
require_once __DIR__ . "/config.php";

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
    echo json_encode(['liked' => false]);
    exit();
}

$freelancer_id = intval($_SESSION['user_id']);
$employer_id = intval($_GET['employer_id'] ?? 0);

if($employer_id <= 0){
    echo json_encode(['liked' => false]);
    exit();
}

$check = mysqli_query($conn, "
    SELECT * FROM like_employer
    WHERE freelancer_id=$freelancer_id
    AND employer_id=$employer_id
");

$liked = mysqli_num_rows($check) > 0;
echo json_encode(['liked' => $liked]);
?>
