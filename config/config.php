<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);

if (!defined('JOBFIND_ROOT_PATH')) {
    define('JOBFIND_ROOT_PATH', dirname(__DIR__));
}

if (!defined('JOBFIND_UPLOADS_PATH')) {
    define('JOBFIND_UPLOADS_PATH', JOBFIND_ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
}

if (!function_exists('jobfind_base_url')) {
    function jobfind_base_url()
    {
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $script_dir = str_replace('\\', '/', dirname($script_name));
        if ($script_dir === '.' || $script_dir === '/') {
            $script_dir = '';
        }

        $last_segment = basename($script_dir);
        if (in_array($last_segment, ['admin', 'employer', 'freelancer', 'actions', 'support', '_legacy'], true)) {
            $script_dir = str_replace('\\', '/', dirname($script_dir));
            if ($script_dir === '.' || $script_dir === '/') {
                $script_dir = '';
            }
        }

        return rtrim($script_dir, '/');
    }
}

if (!function_exists('jobfind_url')) {
    function jobfind_url($path = '')
    {
        $path = ltrim((string)$path, '/');
        $base_url = jobfind_base_url();
        return ($base_url === '' ? '' : $base_url) . '/' . $path;
    }
}

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
