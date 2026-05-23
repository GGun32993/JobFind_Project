<?php

function ensure_job_image_schema($conn){
    $image_col = mysqli_query($conn, "SHOW COLUMNS FROM job LIKE 'image_path'");
    if($image_col && mysqli_num_rows($image_col) === 0){
        mysqli_query($conn, "ALTER TABLE job ADD image_path VARCHAR(255) DEFAULT NULL AFTER category");
    }

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS job_images (
            image_id INT(11) NOT NULL AUTO_INCREMENT,
            job_id INT(11) NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT(11) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (image_id),
            KEY job_id (job_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    mysqli_query($conn, "
        INSERT INTO job_images (job_id, image_path, sort_order)
        SELECT j.job_id, j.image_path, 0
        FROM job j
        WHERE j.image_path IS NOT NULL
          AND j.image_path <> ''
          AND NOT EXISTS (
              SELECT 1
              FROM job_images ji
              WHERE ji.job_id = j.job_id
                AND ji.image_path = j.image_path
          )
    ");
}

function job_image_max_count(){
    return 10;
}

function flatten_job_image_files($files){
    if(!isset($files['name'])){
        return [];
    }

    $flat = [];
    if(is_array($files['name'])){
        $total = count($files['name']);
        for($i = 0; $i < $total; $i++){
            if(($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE){
                continue;
            }
            $flat[] = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
    } elseif($files['error'] !== UPLOAD_ERR_NO_FILE) {
        $flat[] = $files;
    }

    return $flat;
}

function save_uploaded_job_images($files, $employer_id, &$error){
    $error = '';
    $flat_files = flatten_job_image_files($files);
    $saved_paths = [];

    if(count($flat_files) > job_image_max_count()){
        $error = 'อัปโหลดรูปภาพได้ไม่เกิน ' . job_image_max_count() . ' รูปต่อครั้ง';
        return [];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'job_images';
    if(!is_dir($upload_dir)){
        mkdir($upload_dir, 0775, true);
    }

    foreach($flat_files as $index => $file){
        if($file['error'] !== UPLOAD_ERR_OK){
            $error = 'ไม่สามารถอัปโหลดรูปได้ กรุณาลองใหม่อีกครั้ง';
        } elseif($file['size'] > 5 * 1024 * 1024){
            $error = 'รูปภาพประกอบแต่ละรูปต้องมีขนาดไม่เกิน 5MB';
        } else {
            $image_info = @getimagesize($file['tmp_name']);
            if(!$image_info || !isset($allowed[$image_info['mime']])){
                $error = 'รองรับเฉพาะไฟล์รูป JPG, PNG หรือ WEBP เท่านั้น';
            }
        }

        if($error !== ''){
            foreach($saved_paths as $path){
                delete_job_image_file($path);
            }
            return [];
        }

        $ext = $allowed[$image_info['mime']];
        $new_name = 'job_' . intval($employer_id) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . ($index + 1) . '.' . $ext;
        $target_path = $upload_dir . DIRECTORY_SEPARATOR . $new_name;

        if(move_uploaded_file($file['tmp_name'], $target_path)){
            $saved_paths[] = 'uploads/job_images/' . $new_name;
        } else {
            $error = 'บันทึกรูปภาพประกอบไม่สำเร็จ';
            foreach($saved_paths as $path){
                delete_job_image_file($path);
            }
            return [];
        }
    }

    return $saved_paths;
}

function get_job_images($conn, $job_id){
    $job_id = intval($job_id);
    $images = [];
    $res = mysqli_query($conn, "
        SELECT image_id, job_id, image_path, sort_order
        FROM job_images
        WHERE job_id = '$job_id'
        ORDER BY sort_order ASC, image_id ASC
    ");

    if($res){
        while($row = mysqli_fetch_assoc($res)){
            $images[] = $row;
        }
    }

    return $images;
}

function get_job_primary_image($conn, $job_id, $fallback = ''){
    $images = get_job_images($conn, $job_id);
    if(!empty($images)){
        return $images[0]['image_path'];
    }
    return trim($fallback ?? '');
}

function sync_job_primary_image($conn, $job_id){
    $job_id = intval($job_id);
    $primary = get_job_primary_image($conn, $job_id, '');
    $primary_sql = $primary !== '' ? "'" . mysqli_real_escape_string($conn, $primary) . "'" : "NULL";
    return mysqli_query($conn, "UPDATE job SET image_path=$primary_sql WHERE job_id='$job_id'");
}

function delete_job_image_file($path){
    $path = trim($path ?? '');
    if($path === ''){
        return;
    }

    $full_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . $path);
    $uploads_dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
    if(!$full_path || !$uploads_dir || strpos($full_path, $uploads_dir) !== 0 || !is_file($full_path)){
        return;
    }

    $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
    if(!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)){
        return;
    }

    unlink($full_path);
}

?>
