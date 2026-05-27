<?php

$repair_key = "jobfind-repair-2026";
if (!hash_equals($repair_key, $_GET['key'] ?? '')) {
    http_response_code(403);
    echo "Forbidden. Add ?key=jobfind-repair-2026 to run the database repair.";
    exit();
}

define('JOBFIND_ALLOW_DB_FAILURE', true);
require_once __DIR__ . "/config.php";

header("Content-Type: text/plain; charset=utf-8");

if (!$conn) {
    echo $db_error ?: "Database connection failed: " . mysqli_connect_error();
    exit();
}

$results = [];

function repair_run($label, $sql)
{
    global $conn, $results;

    if (mysqli_query($conn, $sql)) {
        $results[] = "[OK] " . $label;
        return true;
    }

    $results[] = "[ERROR] " . $label . " - " . mysqli_error($conn);
    return false;
}

function repair_table_exists($table)
{
    global $conn;
    $table = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $result && mysqli_num_rows($result) > 0;
}

function repair_column_exists($table, $column)
{
    global $conn;
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function repair_index_exists($table, $index)
{
    global $conn;
    $table = mysqli_real_escape_string($conn, $table);
    $index = mysqli_real_escape_string($conn, $index);
    $result = mysqli_query($conn, "SHOW INDEX FROM `$table` WHERE Key_name='$index'");
    return $result && mysqli_num_rows($result) > 0;
}

function repair_add_column($table, $column, $definition)
{
    if (!repair_table_exists($table)) {
        return;
    }

    if (repair_column_exists($table, $column)) {
        return;
    }

    repair_run("add column $table.$column", "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
}

function repair_add_index($table, $index, $definition)
{
    if (!repair_table_exists($table)) {
        return;
    }

    if (repair_index_exists($table, $index)) {
        return;
    }

    repair_run("add index $table.$index", "ALTER TABLE `$table` ADD INDEX `$index` $definition");
}

$tables = [
    "users" => "
        CREATE TABLE IF NOT EXISTS `users` (
            `user_id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `password` varchar(255) DEFAULT NULL,
            `fullname` varchar(100) DEFAULT NULL,
            `phone` varchar(20) DEFAULT NULL,
            `latitude` double DEFAULT NULL,
            `longitude` double DEFAULT NULL,
            `profile_image` varchar(255) DEFAULT NULL,
            `role` enum('admin','employer','freelancer') DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `company_details` text DEFAULT NULL,
            PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "categories" => "
        CREATE TABLE IF NOT EXISTS `categories` (
            `category_id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) DEFAULT NULL,
            `icon` varchar(10) DEFAULT '?',
            `description` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "chat_messages" => "
        CREATE TABLE IF NOT EXISTS `chat_messages` (
            `message_id` int(11) NOT NULL AUTO_INCREMENT,
            `sender_id` int(11) DEFAULT NULL,
            `receiver_id` int(11) DEFAULT NULL,
            `message` text DEFAULT NULL,
            `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_read` tinyint(1) DEFAULT 0,
            PRIMARY KEY (`message_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "employer_profile" => "
        CREATE TABLE IF NOT EXISTS `employer_profile` (
            `employer_id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `employer_name` varchar(100) DEFAULT NULL,
            `employer_description` text DEFAULT NULL,
            `address` varchar(255) DEFAULT NULL,
            `province` varchar(100) DEFAULT NULL,
            `district` varchar(100) DEFAULT NULL,
            `postal_code` varchar(20) DEFAULT NULL,
            `latitude` double DEFAULT NULL,
            `longitude` double DEFAULT NULL,
            `like_count` int(11) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`employer_id`),
            KEY `idx_employer_user` (`user_id`),
            KEY `idx_employer_geo` (`latitude`,`longitude`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "freelancer_profile" => "
        CREATE TABLE IF NOT EXISTS `freelancer_profile` (
            `freelancer_id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `skill` text DEFAULT NULL,
            `experience` text DEFAULT NULL,
            `location` varchar(100) DEFAULT NULL,
            `address` varchar(255) DEFAULT NULL,
            `province` varchar(100) DEFAULT NULL,
            `district` varchar(100) DEFAULT NULL,
            `postal_code` varchar(20) DEFAULT NULL,
            `latitude` double DEFAULT NULL,
            `longitude` double DEFAULT NULL,
            `preferred_radius_km` double NOT NULL DEFAULT 30,
            `rating` float DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`freelancer_id`),
            KEY `idx_freelancer_user` (`user_id`),
            KEY `idx_freelancer_geo` (`latitude`,`longitude`),
            KEY `idx_freelancer_radius` (`preferred_radius_km`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "job" => "
        CREATE TABLE IF NOT EXISTS `job` (
            `job_id` int(11) NOT NULL AUTO_INCREMENT,
            `employer_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `description` text NOT NULL,
            `location` varchar(255) NOT NULL,
            `salary` decimal(10,2) DEFAULT 0.00,
            `latitude` double DEFAULT NULL,
            `longitude` double DEFAULT NULL,
            `deadline` datetime DEFAULT NULL,
            `status` enum('open','in_progress','completed','closed') DEFAULT 'open',
            `admin_status` enum('pending','approved','rejected') DEFAULT 'pending',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `category` varchar(100) DEFAULT NULL,
            `image_path` varchar(255) DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`job_id`),
            KEY `employer_id` (`employer_id`),
            KEY `idx_job_geo` (`latitude`,`longitude`),
            KEY `idx_job_status_admin` (`status`,`admin_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "job_images" => "
        CREATE TABLE IF NOT EXISTS `job_images` (
            `image_id` int(11) NOT NULL AUTO_INCREMENT,
            `job_id` int(11) NOT NULL,
            `image_path` varchar(255) NOT NULL,
            `sort_order` int(11) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`image_id`),
            KEY `job_id` (`job_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "job_application" => "
        CREATE TABLE IF NOT EXISTS `job_application` (
            `application_id` int(11) NOT NULL AUTO_INCREMENT,
            `job_id` int(11) DEFAULT NULL,
            `freelancer_id` int(11) DEFAULT NULL,
            `apply_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status` varchar(50) DEFAULT 'pending',
            PRIMARY KEY (`application_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "like_employer" => "
        CREATE TABLE IF NOT EXISTS `like_employer` (
            `like_id` int(11) NOT NULL AUTO_INCREMENT,
            `freelancer_id` int(11) DEFAULT NULL,
            `employer_id` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`like_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "resume" => "
        CREATE TABLE IF NOT EXISTS `resume` (
            `resume_id` int(11) NOT NULL AUTO_INCREMENT,
            `freelancer_id` int(11) DEFAULT NULL,
            `file_name` varchar(255) DEFAULT NULL,
            `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`resume_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "saved_freelancers" => "
        CREATE TABLE IF NOT EXISTS `saved_freelancers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `employer_id` int(11) NOT NULL,
            `freelancer_id` int(11) NOT NULL,
            `saved_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_save` (`employer_id`,`freelancer_id`),
            KEY `freelancer_id` (`freelancer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    "employer_rating" => "
        CREATE TABLE IF NOT EXISTS `employer_rating` (
            `rating_id` int(11) NOT NULL AUTO_INCREMENT,
            `employer_id` int(11) DEFAULT NULL,
            `freelancer_id` int(11) DEFAULT NULL,
            `score` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`rating_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "freelancer_rating" => "
        CREATE TABLE IF NOT EXISTS `freelancer_rating` (
            `rating_id` int(11) NOT NULL AUTO_INCREMENT,
            `freelancer_id` int(11) DEFAULT NULL,
            `employer_id` int(11) DEFAULT NULL,
            `score` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`rating_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "employer_review" => "
        CREATE TABLE IF NOT EXISTS `employer_review` (
            `review_id` int(11) NOT NULL AUTO_INCREMENT,
            `employer_id` int(11) DEFAULT NULL,
            `freelancer_id` int(11) DEFAULT NULL,
            `job_id` int(11) DEFAULT NULL,
            `rating` int(11) NOT NULL,
            `comment` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`review_id`),
            KEY `job_id` (`job_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
    "freelancer_review" => "
        CREATE TABLE IF NOT EXISTS `freelancer_review` (
            `review_id` int(11) NOT NULL AUTO_INCREMENT,
            `freelancer_id` int(11) DEFAULT NULL,
            `job_id` int(11) DEFAULT NULL,
            `employer_id` int(11) DEFAULT NULL,
            `comment` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `rating` int(11) DEFAULT NULL,
            `review` text DEFAULT NULL,
            PRIMARY KEY (`review_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ",
];

foreach ($tables as $table => $sql) {
    repair_run("create table $table if missing", $sql);
}

repair_add_column("users", "latitude", "DOUBLE DEFAULT NULL");
repair_add_column("users", "longitude", "DOUBLE DEFAULT NULL");
repair_add_column("users", "profile_image", "VARCHAR(255) DEFAULT NULL");
repair_add_column("users", "company_details", "TEXT DEFAULT NULL");

repair_add_column("chat_messages", "is_read", "TINYINT(1) DEFAULT 0");

repair_add_column("employer_profile", "address", "VARCHAR(255) DEFAULT NULL");
repair_add_column("employer_profile", "province", "VARCHAR(100) DEFAULT NULL");
repair_add_column("employer_profile", "district", "VARCHAR(100) DEFAULT NULL");
repair_add_column("employer_profile", "postal_code", "VARCHAR(20) DEFAULT NULL");
repair_add_column("employer_profile", "latitude", "DOUBLE DEFAULT NULL");
repair_add_column("employer_profile", "longitude", "DOUBLE DEFAULT NULL");
repair_add_column("employer_profile", "like_count", "INT(11) DEFAULT 0");

repair_add_column("freelancer_profile", "address", "VARCHAR(255) DEFAULT NULL");
repair_add_column("freelancer_profile", "province", "VARCHAR(100) DEFAULT NULL");
repair_add_column("freelancer_profile", "district", "VARCHAR(100) DEFAULT NULL");
repair_add_column("freelancer_profile", "postal_code", "VARCHAR(20) DEFAULT NULL");
repair_add_column("freelancer_profile", "latitude", "DOUBLE DEFAULT NULL");
repair_add_column("freelancer_profile", "longitude", "DOUBLE DEFAULT NULL");
repair_add_column("freelancer_profile", "preferred_radius_km", "DOUBLE NOT NULL DEFAULT 30");
repair_add_column("freelancer_profile", "rating", "FLOAT DEFAULT 0");

repair_add_column("job", "latitude", "DOUBLE DEFAULT NULL");
repair_add_column("job", "longitude", "DOUBLE DEFAULT NULL");
repair_add_column("job", "admin_status", "ENUM('pending','approved','rejected') DEFAULT 'pending'");
repair_add_column("job", "category", "VARCHAR(100) DEFAULT NULL");
repair_add_column("job", "image_path", "VARCHAR(255) DEFAULT NULL");
repair_add_column("job", "updated_at", "TIMESTAMP NULL DEFAULT NULL");

repair_add_column("job_images", "sort_order", "INT(11) NOT NULL DEFAULT 0");
repair_add_column("job_images", "created_at", "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

repair_add_column("job_application", "status", "VARCHAR(50) DEFAULT 'pending'");

repair_add_column("saved_freelancers", "saved_at", "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

repair_add_column("employer_review", "rating", "INT(11) NOT NULL DEFAULT 0");
repair_add_column("employer_review", "comment", "TEXT DEFAULT NULL");

repair_add_column("freelancer_review", "comment", "TEXT DEFAULT NULL");
repair_add_column("freelancer_review", "rating", "INT(11) DEFAULT NULL");
repair_add_column("freelancer_review", "review", "TEXT DEFAULT NULL");

repair_add_index("users", "idx_user_geo", "(`latitude`, `longitude`)");
repair_add_index("employer_profile", "idx_employer_user", "(`user_id`)");
repair_add_index("employer_profile", "idx_employer_geo", "(`latitude`, `longitude`)");
repair_add_index("freelancer_profile", "idx_freelancer_user", "(`user_id`)");
repair_add_index("freelancer_profile", "idx_freelancer_geo", "(`latitude`, `longitude`)");
repair_add_index("freelancer_profile", "idx_freelancer_radius", "(`preferred_radius_km`)");
repair_add_index("job", "idx_job_geo", "(`latitude`, `longitude`)");
repair_add_index("job", "idx_job_status_admin", "(`status`, `admin_status`)");

$category_count = 0;
$category_result = mysqli_query($conn, "SELECT COUNT(*) AS c FROM categories");
if ($category_result) {
    $category_count = (int)(mysqli_fetch_assoc($category_result)['c'] ?? 0);
}

if ($category_count === 0) {
    repair_run("seed default categories", "
        INSERT INTO categories (name, icon, description) VALUES
        ('IT', 'IT', 'Computer and technology jobs'),
        ('Design', 'DES', 'Graphic and UI jobs'),
        ('Marketing', 'MKT', 'Marketing jobs'),
        ('Accounting', 'ACC', 'Finance jobs')
    ");
}

echo "JobFind database repair finished.\n\n";
echo implode("\n", $results);
echo "\n\nDelete database_repair.php from the server after this succeeds.";
