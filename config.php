<?php

// start session safely
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);
ini_set('default_socket_timeout', '3');

$host = "127.0.0.1";
$port = 3306;
$user = "root";
$pass = "";
$db   = "jobfind";

$db_error = '';
$conn = mysqli_init();
$connected = false;

if($conn){
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    if(defined('MYSQLI_OPT_READ_TIMEOUT')){
        mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, 3);
    }

    $connected = @mysqli_real_connect($conn, $host, $user, $pass, $db, $port);
}

if(!$connected){
    $db_error = "Database connection failed. Please make sure MySQL is running and the jobfind database exists.";
    error_log($db_error . " " . mysqli_connect_error());
    $conn = null;

    if(!defined('JOBFIND_ALLOW_DB_FAILURE')){
        http_response_code(503);
        echo "<!doctype html><html lang=\"en\"><head><meta charset=\"UTF-8\"><title>Database unavailable</title></head><body>";
        echo "<h1>Database unavailable</h1>";
        echo "<p>" . htmlspecialchars($db_error, ENT_QUOTES, 'UTF-8') . "</p>";
        echo "</body></html>";
        exit();
    }
} else {
    mysqli_set_charset($conn, "utf8mb4");
}

?>
