<?php

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/location_schema.php";

function ensure_profile_image_schema($conn)
{
    if (!$conn || !jobfind_table_exists($conn, 'users')) {
        return false;
    }

    return jobfind_add_column_if_missing($conn, 'users', 'profile_image', 'VARCHAR(255) DEFAULT NULL AFTER `longitude`');
}

function profile_initials($name, $max_chars = 1)
{
    $name = trim((string)$name);
    if ($name === '') {
        return '?';
    }

    $max_chars = max(1, (int)$max_chars);
    $chars = [];
    if (preg_match_all('/\S/u', $name, $matches)) {
        $chars = array_slice($matches[0], 0, $max_chars);
    }

    if (empty($chars)) {
        $chars = [substr($name, 0, 1)];
    }

    $initials = implode('', $chars);
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($initials, 'UTF-8');
    }

    return strtoupper($initials);
}

function profile_image_max_size()
{
    return 10 * 1024 * 1024;
}

function profile_image_max_size_label()
{
    $size_mb = profile_image_max_size() / 1024 / 1024;
    return rtrim(rtrim(number_format($size_mb, 1), '0'), '.') . 'MB';
}

function profile_image_allowed_mimes()
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function profile_image_file_selected($file)
{
    return isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;
}

function ensure_profile_image_upload_dir(&$error)
{
    $uploads_root = JOBFIND_UPLOADS_PATH;
    if (!is_dir($uploads_root) && !mkdir($uploads_root, 0775, true)) {
        $error = 'Cannot create uploads directory.';
        return '';
    }

    $upload_dir = $uploads_root . DIRECTORY_SEPARATOR . 'profile_images';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true)) {
        $error = 'Cannot create uploads/profile_images directory.';
        return '';
    }

    @chmod($uploads_root, 0775);
    @chmod($upload_dir, 0775);

    if (!is_writable($upload_dir)) {
        $error = 'uploads/profile_images is not writable by PHP.';
        return '';
    }

    return $upload_dir;
}

function save_uploaded_profile_image($file, $user_id, &$error)
{
    $error = '';

    if (!profile_image_file_selected($file)) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $upload_error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($upload_error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $error = 'รูปโปรไฟล์ต้องมีขนาดไม่เกิน ' . profile_image_max_size_label();
            return '';
        }
        $error = 'อัปโหลดรูปโปรไฟล์ไม่สำเร็จ กรุณาลองใหม่';
        return '';
    }

    if (($file['size'] ?? 0) > profile_image_max_size()) {
        $error = 'รูปโปรไฟล์ต้องมีขนาดไม่เกิน ' . profile_image_max_size_label();
        return '';
    }

    $allowed = profile_image_allowed_mimes();
    $image_info = @getimagesize($file['tmp_name']);
    if (!$image_info || !isset($allowed[$image_info['mime']])) {
        $error = 'รองรับเฉพาะไฟล์ JPG, PNG หรือ WEBP';
        return '';
    }

    $upload_dir = ensure_profile_image_upload_dir($error);
    if ($upload_dir === '') {
        return '';
    }

    $ext = $allowed[$image_info['mime']];
    $new_name = 'profile_' . intval($user_id) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target_path = $upload_dir . DIRECTORY_SEPARATOR . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        $error = 'Failed to save profile image to uploads/profile_images.';
        return '';
    }

    @chmod($target_path, 0644);
    return 'uploads/profile_images/' . $new_name;
}

function delete_profile_image_file($path)
{
    $path = trim($path ?? '');
    if ($path === '') {
        return;
    }

    $full_path = realpath(JOBFIND_ROOT_PATH . DIRECTORY_SEPARATOR . $path);
    $uploads_dir = realpath(JOBFIND_UPLOADS_PATH);
    if (!$full_path || !$uploads_dir || strpos($full_path, $uploads_dir) !== 0 || !is_file($full_path)) {
        return;
    }

    $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return;
    }

    unlink($full_path);
}

function profile_image_src($path)
{
    $path = trim($path ?? '');
    if ($path === '') {
        return '';
    }

    return htmlspecialchars(jobfind_url($path), ENT_QUOTES, 'UTF-8');
}
