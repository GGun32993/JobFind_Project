<?php

require_once __DIR__ . "/location_schema.php";

function ensure_chat_message_read_schema($conn)
{
    static $done = false;

    if ($done || !$conn) {
        return $done;
    }

    // Ensure Chat_Messages table exists
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS `Chat_Messages` (
            `message_id` INT AUTO_INCREMENT PRIMARY KEY,
            `sender_id` INT NOT NULL,
            `receiver_id` INT NOT NULL,
            `message` TEXT NOT NULL,
            `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `is_read` TINYINT(1) DEFAULT 0,
            INDEX `idx_sender_receiver` (`sender_id`, `receiver_id`),
            INDEX `idx_receiver_is_read` (`receiver_id`, `is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (jobfind_table_exists($conn, 'Chat_Messages')) {
        jobfind_add_column_if_missing(
            $conn,
            'Chat_Messages',
            'is_read',
            'TINYINT(1) DEFAULT 0'
        );
    }

    $done = true;
    return $done;
}

function admin_unread_support_count($conn, $admin_id)
{
    $admin_id = (int)$admin_id;
    if (!$conn || $admin_id <= 0) {
        return 0;
    }

    ensure_chat_message_read_schema($conn);

    if (!jobfind_table_exists($conn, 'Chat_Messages')) {
        return 0;
    }

    $result = mysqli_query($conn, "
        SELECT COUNT(*) AS c
        FROM Chat_Messages cm
        JOIN Users u ON u.user_id=cm.sender_id
        WHERE cm.receiver_id IN (SELECT user_id FROM Users WHERE role='admin')
        AND cm.is_read=0
    ");
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return (int)($row['c'] ?? 0);
}

