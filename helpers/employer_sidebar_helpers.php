<?php
require_once __DIR__ . "/../config/config.php";

if(!function_exists('get_employer_pending_application_count')){
    function get_employer_pending_application_count($conn, $employer_id){
        $employer_id = (int)$employer_id;
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total_pending
            FROM job_application ja
            JOIN job j ON j.job_id = ja.job_id
            WHERE j.employer_id = ?
            AND ja.status = 'pending'
        ");

        if(!$stmt){
            return 0;
        }

        $stmt->bind_param("i", $employer_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total_pending'] ?? 0);
    }
}

if(!function_exists('render_employer_manage_jobs_badge')){
    function render_employer_manage_jobs_badge($count){
        $count = (int)$count;
        if($count <= 0){
            return;
        }

        $label = $count > 99 ? '99+' : (string)$count;
        echo '<span class="nav-badge">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
