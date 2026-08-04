<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function jobfind_redirect($path)
{
    header("Location: " . $path);
    exit();
}

function jobfind_require_login($login_path = "../login.php")
{
    if (empty($_SESSION['user_id'])) {
        jobfind_redirect($login_path);
    }

    return (int)$_SESSION['user_id'];
}

function jobfind_require_role($role, $login_path = "../login.php")
{
    $user_id = jobfind_require_login($login_path);
    if (($_SESSION['role'] ?? '') !== $role) {
        jobfind_redirect($login_path);
    }

    return $user_id;
}

function jobfind_require_any_role(array $roles, $login_path = "../login.php")
{
    $user_id = jobfind_require_login($login_path);
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        jobfind_redirect($login_path);
    }

    return $user_id;
}

function jobfind_require_json_role($role, array $payload = ['success' => false, 'message' => 'Unauthorized'])
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $role) {
        http_response_code(401);
        echo json_encode($payload);
        exit();
    }

    return (int)$_SESSION['user_id'];
}

function jobfind_current_user_id()
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function jobfind_current_role()
{
    return (string)($_SESSION['role'] ?? '');
}
