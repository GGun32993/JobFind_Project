<?php

require_once __DIR__ . "/location_schema.php";

function ensure_chat_message_read_schema($conn)
{
    static $done = false;

    if ($done || !$conn || !jobfind_table_exists($conn, 'Chat_Messages')) {
        return $done;
    }

    $done = jobfind_add_column_if_missing(
        $conn,
        'Chat_Messages',
        'is_read',
        'TINYINT(1) DEFAULT 0'
    );

    return $done;
}

function admin_unread_support_count($conn, $admin_id)
{
    $admin_id = (int)$admin_id;
    if (!$conn || $admin_id <= 0 || !jobfind_table_exists($conn, 'Chat_Messages')) {
        return 0;
    }

    ensure_chat_message_read_schema($conn);

    $result = mysqli_query($conn, "
        SELECT COUNT(*) AS c
        FROM Chat_Messages cm
        JOIN Users u ON u.user_id=cm.sender_id
        WHERE cm.receiver_id='$admin_id'
        AND cm.is_read=0
    ");
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return (int)($row['c'] ?? 0);
}

