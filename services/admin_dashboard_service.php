<?php

function admin_dashboard_count($conn, $sql)
{
    $result = mysqli_query($conn, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return (int)($row['c'] ?? 0);
}

function admin_dashboard_stats($conn)
{
    return [
        'total_users' => admin_dashboard_count($conn, "SELECT COUNT(*) AS c FROM Users WHERE role!='admin'"),
        'total_freelance' => admin_dashboard_count($conn, "SELECT COUNT(*) AS c FROM Users WHERE role='freelancer'"),
        'total_employer' => admin_dashboard_count($conn, "SELECT COUNT(*) AS c FROM Users WHERE role='employer'"),
        'total_jobs' => admin_dashboard_count($conn, "SELECT COUNT(*) AS c FROM Job"),
        'pending_jobs' => admin_dashboard_count($conn, "SELECT COUNT(*) AS c FROM Job WHERE admin_status='pending'"),
        'total_apps' => admin_dashboard_count($conn, "SELECT COUNT(*) AS c FROM Job_Application"),
    ];
}

function admin_dashboard_recent_jobs($conn, $limit = 5)
{
    $limit = max(1, (int)$limit);

    return mysqli_query($conn, "
        SELECT Job.title, Job.admin_status, Users.username AS employer, Job.created_at
        FROM Job
        JOIN Users ON Users.user_id = Job.employer_id
        ORDER BY Job.created_at DESC
        LIMIT $limit
    ");
}

function admin_dashboard_recent_users($conn, $limit = 5)
{
    $limit = max(1, (int)$limit);

    return mysqli_query($conn, "
        SELECT username, role, created_at
        FROM Users
        WHERE role != 'admin'
        ORDER BY created_at DESC
        LIMIT $limit
    ");
}

