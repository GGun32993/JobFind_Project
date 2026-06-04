<?php
// check_saved_freelancer.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/config.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employer') {
    echo json_encode(['is_saved' => false]);
    exit();
}

$employer_id = $_SESSION['user_id'];
$freelancer_id = $_POST['freelancer_id'] ?? 0;
$is_saved = false;

if ($freelancer_id) {
    $query = "SELECT id FROM saved_freelancers WHERE employer_id = ? AND freelancer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $employer_id, $freelancer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_saved = $result->num_rows > 0;
    $stmt->close();
}
echo json_encode(['is_saved' => $is_saved]);
$conn->close();
?>
