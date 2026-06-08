<?php

require_once __DIR__ . "/../config/config.php";

function jobfind_table_exists($conn, $table)
{
    if (!$conn || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $table_sql = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table_sql'");
    return $result && mysqli_num_rows($result) > 0;
}

function jobfind_column_exists($conn, $table, $column)
{
    if (!$conn || !preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    $column_sql = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column_sql'");
    return $result && mysqli_num_rows($result) > 0;
}

function jobfind_index_exists($conn, $table, $index)
{
    if (!$conn || !preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $index)) {
        return false;
    }

    $index_sql = mysqli_real_escape_string($conn, $index);
    $result = mysqli_query($conn, "SHOW INDEX FROM `$table` WHERE Key_name = '$index_sql'");
    return $result && mysqli_num_rows($result) > 0;
}

function jobfind_add_column_if_missing($conn, $table, $column, $definition)
{
    if (!jobfind_table_exists($conn, $table) || jobfind_column_exists($conn, $table, $column)) {
        return true;
    }

    $ok = mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    if (!$ok) {
        error_log("Job_Find location schema: failed adding $table.$column - " . mysqli_error($conn));
    }

    return (bool)$ok;
}

function jobfind_add_index_if_missing($conn, $table, $index, $definition)
{
    if (!jobfind_table_exists($conn, $table) || jobfind_index_exists($conn, $table, $index)) {
        return true;
    }

    $ok = mysqli_query($conn, "ALTER TABLE `$table` ADD INDEX `$index` $definition");
    if (!$ok) {
        error_log("Job_Find location schema: failed adding index $table.$index - " . mysqli_error($conn));
    }

    return (bool)$ok;
}

function jobfind_gender_options()
{
    return [
        'male' => 'ชาย',
        'female' => 'หญิง',
        'lgbtq' => 'LGBTQ+',
    ];
}

function jobfind_normalize_gender($gender)
{
    $gender = strtolower(trim((string)$gender));
    return array_key_exists($gender, jobfind_gender_options()) ? $gender : '';
}

function jobfind_gender_label($gender)
{
    $gender = jobfind_normalize_gender($gender);
    $options = jobfind_gender_options();
    return $gender !== '' ? $options[$gender] : '';
}

function jobfind_normalize_age($age)
{
    $age = trim((string)$age);
    if ($age === '' || !ctype_digit($age)) {
        return null;
    }

    $age = (int)$age;
    return ($age >= 1 && $age <= 120) ? $age : null;
}

function jobfind_employment_type_options()
{
    return [
        'freelance_project' => 'ฟรีแลนซ์ (จ้างเป็นโปรเจกต์)',
        'contract' => 'สัญญาจ้าง (รายเดือน/รายปี)',
        'part_time' => 'พาร์ทไทม์ (รายชั่วโมง/รายวัน)',
        'full_time' => 'งานประจำ',
    ];
}

function jobfind_normalize_employment_type($employment_type)
{
    $employment_type = trim((string)$employment_type);
    return array_key_exists($employment_type, jobfind_employment_type_options()) ? $employment_type : 'freelance_project';
}

function jobfind_employment_type_label($employment_type)
{
    $employment_type = trim((string)$employment_type);
    $options = jobfind_employment_type_options();
    return array_key_exists($employment_type, $options) ? $options[$employment_type] : '';
}

function ensure_location_schema($conn)
{
    static $done = false;

    if ($done || !$conn) {
        return $done;
    }

    if (jobfind_table_exists($conn, 'Freelancer_Profile')) {
        jobfind_add_column_if_missing($conn, 'Freelancer_Profile', 'age', 'INT DEFAULT NULL AFTER `experience`');
        jobfind_add_column_if_missing($conn, 'Freelancer_Profile', 'address', 'VARCHAR(255) DEFAULT NULL AFTER `location`');
        jobfind_add_column_if_missing($conn, 'Freelancer_Profile', 'province', 'VARCHAR(100) DEFAULT NULL AFTER `address`');
        jobfind_add_column_if_missing($conn, 'Freelancer_Profile', 'district', 'VARCHAR(100) DEFAULT NULL AFTER `province`');
        jobfind_add_column_if_missing($conn, 'Freelancer_Profile', 'postal_code', 'VARCHAR(20) DEFAULT NULL AFTER `district`');
        jobfind_add_column_if_missing($conn, 'Freelancer_Profile', 'latitude', 'DOUBLE DEFAULT NULL AFTER `postal_code`');
        jobfind_add_column_if_missing($conn, 'Freelancer_Profile', 'longitude', 'DOUBLE DEFAULT NULL AFTER `latitude`');
        jobfind_add_column_if_missing($conn, 'Freelancer_Profile', 'preferred_radius_km', 'DOUBLE NOT NULL DEFAULT 30 AFTER `longitude`');

        jobfind_add_index_if_missing($conn, 'Freelancer_Profile', 'idx_freelancer_geo', '(`latitude`, `longitude`)');
        jobfind_add_index_if_missing($conn, 'Freelancer_Profile', 'idx_freelancer_radius', '(`preferred_radius_km`)');
        jobfind_add_index_if_missing($conn, 'Freelancer_Profile', 'idx_freelancer_user', '(`user_id`)');
    }

    if (jobfind_table_exists($conn, 'Employer_Profile')) {
        jobfind_add_column_if_missing($conn, 'Employer_Profile', 'address', 'VARCHAR(255) DEFAULT NULL AFTER `employer_description`');
        jobfind_add_column_if_missing($conn, 'Employer_Profile', 'province', 'VARCHAR(100) DEFAULT NULL AFTER `address`');
        jobfind_add_column_if_missing($conn, 'Employer_Profile', 'district', 'VARCHAR(100) DEFAULT NULL AFTER `province`');
        jobfind_add_column_if_missing($conn, 'Employer_Profile', 'postal_code', 'VARCHAR(20) DEFAULT NULL AFTER `district`');
        jobfind_add_column_if_missing($conn, 'Employer_Profile', 'latitude', 'DOUBLE DEFAULT NULL AFTER `postal_code`');
        jobfind_add_column_if_missing($conn, 'Employer_Profile', 'longitude', 'DOUBLE DEFAULT NULL AFTER `latitude`');

        jobfind_add_index_if_missing($conn, 'Employer_Profile', 'idx_employer_geo', '(`latitude`, `longitude`)');
        jobfind_add_index_if_missing($conn, 'Employer_Profile', 'idx_employer_user', '(`user_id`)');
    }

    if (jobfind_table_exists($conn, 'Users')) {
        jobfind_add_column_if_missing($conn, 'Users', 'gender', "VARCHAR(20) DEFAULT NULL AFTER `phone`");
        jobfind_add_column_if_missing($conn, 'Users', 'latitude', 'DOUBLE DEFAULT NULL AFTER `phone`');
        jobfind_add_column_if_missing($conn, 'Users', 'longitude', 'DOUBLE DEFAULT NULL AFTER `latitude`');

        jobfind_add_index_if_missing($conn, 'Users', 'idx_user_geo', '(`latitude`, `longitude`)');
    }

    if (jobfind_table_exists($conn, 'Job')) {
        jobfind_add_column_if_missing($conn, 'Job', 'latitude', 'DOUBLE DEFAULT NULL AFTER `location`');
        jobfind_add_column_if_missing($conn, 'Job', 'longitude', 'DOUBLE DEFAULT NULL AFTER `latitude`');
        jobfind_add_column_if_missing($conn, 'Job', 'employment_type', 'VARCHAR(40) DEFAULT NULL AFTER `category`');

        jobfind_add_index_if_missing($conn, 'Job', 'idx_job_geo', '(`latitude`, `longitude`)');
        jobfind_add_index_if_missing($conn, 'Job', 'idx_job_status_admin', '(`status`, `admin_status`)');
    }

    $done = true;
    return true;
}

?>
