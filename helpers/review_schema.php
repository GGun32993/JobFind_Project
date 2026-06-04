<?php

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/location_schema.php";

function jobfind_table_readable($conn, $table)
{
    if (!$conn || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
    if ($result) {
        return true;
    }

    error_log("Job_Find review schema: table $table is not readable - " . mysqli_error($conn));
    return false;
}

function ensure_freelancer_review_schema($conn)
{
    static $done = false;

    if ($done || !$conn) {
        return $done;
    }

    if (!jobfind_table_readable($conn, 'freelancer_review')) {
        mysqli_query($conn, "DROP TABLE IF EXISTS `freelancer_review`");
    }

    $ok = mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS `freelancer_review` (
            `review_id` int(11) NOT NULL AUTO_INCREMENT,
            `freelancer_id` int(11) DEFAULT NULL,
            `job_id` int(11) NOT NULL DEFAULT 0,
            `employer_id` int(11) DEFAULT NULL,
            `comment` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `rating` int(11) DEFAULT NULL,
            `review` text DEFAULT NULL,
            PRIMARY KEY (`review_id`),
            KEY `idx_freelancer_review_freelancer` (`freelancer_id`),
            KEY `idx_freelancer_review_employer` (`employer_id`),
            KEY `idx_freelancer_review_job` (`job_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!$ok) {
        error_log("Job_Find review schema: failed creating freelancer_review - " . mysqli_error($conn));
        return false;
    }

    jobfind_add_column_if_missing($conn, 'freelancer_review', 'comment', 'TEXT DEFAULT NULL AFTER `employer_id`');
    jobfind_add_column_if_missing($conn, 'freelancer_review', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `comment`');
    jobfind_add_column_if_missing($conn, 'freelancer_review', 'rating', 'INT DEFAULT NULL AFTER `created_at`');
    jobfind_add_column_if_missing($conn, 'freelancer_review', 'review', 'TEXT DEFAULT NULL AFTER `rating`');
    jobfind_add_index_if_missing($conn, 'freelancer_review', 'idx_freelancer_review_freelancer', '(`freelancer_id`)');
    jobfind_add_index_if_missing($conn, 'freelancer_review', 'idx_freelancer_review_employer', '(`employer_id`)');
    jobfind_add_index_if_missing($conn, 'freelancer_review', 'idx_freelancer_review_job', '(`job_id`)');

    $done = jobfind_table_readable($conn, 'freelancer_review');
    return $done;
}

?>
