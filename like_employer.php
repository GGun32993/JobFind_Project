<?php
session_start();
include("config.php");

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "freelancer"){
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$freelancer_id = intval($_SESSION['user_id']);
$employer_id = intval($_GET['employer_id'] ?? 0);

if($employer_id <= 0){
    echo json_encode(['success' => false, 'message' => 'Invalid employer']);
    exit();
}

// Check if already liked
$check = mysqli_query($conn, "
    SELECT * FROM like_employer
    WHERE freelancer_id=$freelancer_id
    AND employer_id=$employer_id
");

if(mysqli_num_rows($check) == 0){
    // Insert like
    if(mysqli_query($conn, "
        INSERT INTO like_employer
        (freelancer_id, employer_id)
        VALUES
        ($freelancer_id, $employer_id)
    ")){
        echo json_encode(['success' => true, 'liked' => true, 'message' => 'Liked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    // Delete like (unlike)
    if(mysqli_query($conn, "
        DELETE FROM like_employer
        WHERE freelancer_id=$freelancer_id
        AND employer_id=$employer_id
    ")){
        echo json_encode(['success' => true, 'liked' => false, 'message' => 'Unliked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>
