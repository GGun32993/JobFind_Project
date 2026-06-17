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

if (!function_exists('jobfind_db_table_name_map')) {
    function jobfind_db_table_name_map()
    {
        return [
            'categories' => 'Categories',
            'category_seed_runs' => 'Category_Seed_Runs',
            'chat_messages' => 'Chat_Messages',
            'employer_profile' => 'Employer_Profile',
            'employer_rating' => 'Employer_Rating',
            'employer_review' => 'Employer_Review',
            'freelancer_profile' => 'Freelancer_Profile',
            'freelancer_rating' => 'Freelancer_Rating',
            'freelancer_review' => 'Freelancer_Review',
            'job' => 'Job',
            'job_application' => 'Job_Application',
            'job_images' => 'Job_Images',
            'job_subcategories' => 'Job_Subcategories',
            'like_employer' => 'Like_Employer',
            'resume' => 'Resume',
            'saved_freelancers' => 'Saved_Freelancers',
            'users' => 'Users',
        ];
    }
}

if (!function_exists('jobfind_db_quote_identifier')) {
    function jobfind_db_quote_identifier($identifier)
    {
        return '`' . str_replace('`', '``', (string)$identifier) . '`';
    }
}

if (!function_exists('jobfind_repair_table_names')) {
    function jobfind_repair_table_names($conn)
    {
        static $done = false;

        if ($done || !$conn) {
            return $done;
        }

        $done = true;
        $database_result = mysqli_query($conn, "SELECT DATABASE() AS db_name");
        $database_row = $database_result ? mysqli_fetch_assoc($database_result) : null;
        $database_name = $database_row['db_name'] ?? '';

        if ($database_name === '') {
            return false;
        }

        $case_variable_result = mysqli_query($conn, "SHOW VARIABLES LIKE 'lower_case_table_names'");
        $case_variable = $case_variable_result ? mysqli_fetch_assoc($case_variable_result) : null;
        if ((int)($case_variable['Value'] ?? 0) !== 0) {
            return true;
        }

        $database_sql = mysqli_real_escape_string($conn, $database_name);
        $tables_result = mysqli_query($conn, "
            SELECT TABLE_NAME, TABLE_TYPE
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = '$database_sql'
        ");

        if (!$tables_result) {
            error_log('Freelance Matching Online DB repair: failed reading table list - ' . mysqli_error($conn));
            return false;
        }

        $existing = [];
        $existing_by_lower = [];
        while ($table = mysqli_fetch_assoc($tables_result)) {
            $table_name = (string)$table['TABLE_NAME'];
            $existing[$table_name] = (string)$table['TABLE_TYPE'];
            $existing_by_lower[strtolower($table_name)][] = $table_name;
        }

        $rename_parts = [];
        foreach (jobfind_db_table_name_map() as $old_name => $new_name) {
            if (isset($existing[$new_name])) {
                continue;
            }

            $old_candidates = $existing_by_lower[strtolower($old_name)] ?? [];
            if (empty($old_candidates)) {
                continue;
            }

            $source_name = in_array($old_name, $old_candidates, true) ? $old_name : $old_candidates[0];
            if (($existing[$source_name] ?? '') !== 'BASE TABLE') {
                continue;
            }

            $rename_parts[] =
                jobfind_db_quote_identifier($database_name) . '.' . jobfind_db_quote_identifier($source_name) .
                ' TO ' .
                jobfind_db_quote_identifier($database_name) . '.' . jobfind_db_quote_identifier($new_name);
        }

        if (empty($rename_parts)) {
            return true;
        }

        $ok = mysqli_query($conn, 'RENAME TABLE ' . implode(', ', $rename_parts));
        if (!$ok) {
            error_log('Freelance Matching Online DB repair: failed renaming tables - ' . mysqli_error($conn));
        }

        return (bool)$ok;
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

if (!defined('JOBFIND_GEOAPIFY_API_KEY')) {
    $geoapify_api_key = $geoapify_api_key ?? (getenv('JOBFIND_GEOAPIFY_API_KEY') ?: '');
    define('JOBFIND_GEOAPIFY_API_KEY', (string)$geoapify_api_key);
}

if (!function_exists('jobfind_geoapify_api_key_attr')) {
    function jobfind_geoapify_api_key_attr()
    {
        return htmlspecialchars(JOBFIND_GEOAPIFY_API_KEY, ENT_QUOTES, 'UTF-8');
    }
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
        echo "<p>Please try again later.</p>";
        echo "</body>";
        echo "</html>";
        exit();
    }
} else {
    mysqli_set_charset($conn, "utf8mb4");
    jobfind_repair_table_names($conn);
}
