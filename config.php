<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);

$host = "sql205.infinityfree.com";
$user = "if0_42031060";
$pass = "";
$db = "if0_42031060_jobfind";

$local_config = __DIR__ . "/config.local.php";
if (is_file($local_config)) {
    require $local_config;
}

$conn = @mysqli_connect($host, $user, $pass, $db);
$db_error = "";

if (!$conn) {
    $db_error = "Database connection failed: " . mysqli_connect_error();
    error_log($db_error);

    if (!defined('JOBFIND_ALLOW_DB_FAILURE')) {
        http_response_code(503);
        echo "<!doctype html>";
        echo "<html lang='en'>";
        echo "<head>";
        echo "<meta charset='UTF-8'>";
        echo "<title>Database unavailable</title>";
        echo "</head>";
        echo "<body>";
        echo "<h1>Database unavailable</h1>";
        echo "<p>" . htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8') . "</p>";
        echo "</body>";
        echo "</html>";
        exit();
    }
} else {
    mysqli_set_charset($conn, "utf8mb4");
}
