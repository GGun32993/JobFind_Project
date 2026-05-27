<?php
// save_freelancer.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/config.php";  // ✅ ใช้ config.php

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employer') {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit();
}

$employer_id = $_SESSION['user_id'];
$freelancer_id = $_POST['freelancer_id'] ?? 0;
$action = $_POST['action'] ?? '';

if (!$freelancer_id || !in_array($action, ['save', 'unsave'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    if ($action === 'save') {
        $sql = "INSERT INTO saved_freelancers (employer_id, freelancer_id, saved_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE saved_at = NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $employer_id, $freelancer_id);
    } else {
        $sql = "DELETE FROM saved_freelancers WHERE employer_id = ? AND freelancer_id = ?";
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
