<?php
// save_freelancer.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/config.php";  // ✅ ใช้ config.php

require_once __DIR__ . "/../helpers/auth_helpers.php";

$employer_id = jobfind_require_json_role('employer', ['success' => false, 'message' => 'Please login']);
$freelancer_id = intval($_POST['freelancer_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$freelancer_id || !in_array($action, ['save', 'unsave'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    if ($action === 'save') {
        $sql = "INSERT INTO Saved_Freelancers (employer_id, freelancer_id, saved_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE saved_at = NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $employer_id, $freelancer_id);
    } else {
        $sql = "DELETE FROM Saved_Freelancers WHERE employer_id = ? AND freelancer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $employer_id, $freelancer_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
$conn->close();
?>
