<?php
// check_saved_freelancer.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";

$employer_id = jobfind_require_json_role('employer', ['is_saved' => false]);
$freelancer_id = intval($_POST['freelancer_id'] ?? 0);
$is_saved = false;

if ($freelancer_id) {
    $query = "SELECT id FROM Saved_Freelancers WHERE employer_id = ? AND freelancer_id = ?";
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
