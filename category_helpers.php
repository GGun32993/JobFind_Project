<?php

function jobfind_default_job_categories()
{
    return [
        ['name' => 'กฎหมายและเอกสาร', 'icon' => '⚖️'],
        ['name' => 'กราฟิกดีไซน์', 'icon' => '🎨'],
        ['name' => 'การตลาดและโฆษณา', 'icon' => '📣'],
        ['name' => 'การเงินและบัญชี', 'icon' => '💰'],
        ['name' => 'การเขียนและแปลภาษา', 'icon' => '✍️'],
        ['name' => 'การศึกษาและติวเตอร์', 'icon' => '🎓'],
        ['name' => 'ขนส่งและโลจิสติกส์', 'icon' => '🚚'],
        ['name' => 'งานขายและบริการลูกค้า', 'icon' => '🛍️'],
        ['name' => 'งานช่างและซ่อมบำรุง', 'icon' => '🛠️'],
        ['name' => 'งานธุรการและคีย์ข้อมูล', 'icon' => '⌨️'],
        ['name' => 'งานบริการและอีเวนต์', 'icon' => '🎪'],
        ['name' => 'ดนตรีและเสียง', 'icon' => '🎵'],
        ['name' => 'ตัดต่อวิดีโอและแอนิเมชัน', 'icon' => '🎬'],
        ['name' => 'เทคโนโลยีและซอฟต์แวร์', 'icon' => '💻'],
        ['name' => 'ถ่ายภาพและวิดีโอ', 'icon' => '📷'],
        ['name' => 'บริหารธุรกิจและที่ปรึกษา', 'icon' => '📊'],
        ['name' => 'สุขภาพและความงาม', 'icon' => '💄'],
        ['name' => 'สถาปัตยกรรมและวิศวกรรม', 'icon' => '🏗️'],
        ['name' => 'ออกแบบเว็บไซต์และ UI/UX', 'icon' => '🖥️'],
        ['name' => 'อื่นๆ', 'icon' => '📦'],
    ];
}

function ensure_category_schema($conn)
{
    if (!$conn) {
        return false;
    }

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            icon VARCHAR(20) DEFAULT '📦',
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS category_seed_runs (
            seed_key VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    return true;
}

function ensure_default_job_categories($conn)
{
    if (!$conn) {
        return false;
    }

    ensure_category_schema($conn);

    $seed_key = 'job_categories_thai_20_v1';
    $seed_key_sql = mysqli_real_escape_string($conn, $seed_key);
    $seeded = mysqli_query($conn, "SELECT seed_key FROM category_seed_runs WHERE seed_key='$seed_key_sql' LIMIT 1");
    if ($seeded && mysqli_num_rows($seeded) > 0) {
        return true;
    }

    jobfind_migrate_legacy_categories($conn);

    foreach (jobfind_default_job_categories() as $category) {
        $name_sql = mysqli_real_escape_string($conn, $category['name']);
        $exists = mysqli_query($conn, "SELECT category_id FROM categories WHERE name='$name_sql' LIMIT 1");
        if ($exists && mysqli_num_rows($exists) > 0) {
            continue;
        }

        $icon_sql = mysqli_real_escape_string($conn, $category['icon']);
        mysqli_query($conn, "INSERT INTO categories (name, icon) VALUES ('$name_sql', '$icon_sql')");
    }

    mysqli_query($conn, "INSERT INTO category_seed_runs (seed_key) VALUES ('$seed_key_sql')");
    return true;
}

function jobfind_migrate_legacy_categories($conn)
{
    $legacy_categories = [
        'IT' => ['name' => 'เทคโนโลยีและซอฟต์แวร์', 'icon' => '💻'],
        'IT & Software' => ['name' => 'เทคโนโลยีและซอฟต์แวร์', 'icon' => '💻'],
        'Design' => ['name' => 'กราฟิกดีไซน์', 'icon' => '🎨'],
        'Marketing' => ['name' => 'การตลาดและโฆษณา', 'icon' => '📣'],
        'Accounting' => ['name' => 'การเงินและบัญชี', 'icon' => '💰'],
        'Finance' => ['name' => 'การเงินและบัญชี', 'icon' => '💰'],
        'Writing' => ['name' => 'การเขียนและแปลภาษา', 'icon' => '✍️'],
        'Education' => ['name' => 'การศึกษาและติวเตอร์', 'icon' => '🎓'],
        'Other' => ['name' => 'อื่นๆ', 'icon' => '📦'],
    ];

    foreach ($legacy_categories as $old_name => $new_category) {
        $old_sql = mysqli_real_escape_string($conn, $old_name);
        $new_sql = mysqli_real_escape_string($conn, $new_category['name']);
        $icon_sql = mysqli_real_escape_string($conn, $new_category['icon']);

        $old_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT category_id FROM categories WHERE name='$old_sql' LIMIT 1"));
        if (!$old_row) {
            continue;
        }

        $new_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT category_id FROM categories WHERE name='$new_sql' LIMIT 1"));
        mysqli_query($conn, "UPDATE job SET category='$new_sql' WHERE category='$old_sql'");

        if ($new_row) {
            mysqli_query($conn, "DELETE FROM categories WHERE category_id='" . intval($old_row['category_id']) . "'");
        } else {
            mysqli_query($conn, "
                UPDATE categories
                SET name='$new_sql', icon='$icon_sql'
                WHERE category_id='" . intval($old_row['category_id']) . "'
            ");
        }
    }
}

function jobfind_category_sort_expression($conn, $column = 'name')
{
    static $collation = null;

    if ($collation === null) {
        $collation = '';
        $thai_collation = mysqli_query($conn, "SHOW COLLATION LIKE 'utf8mb4_thai_520_w2'");
        if ($thai_collation && mysqli_num_rows($thai_collation) > 0) {
            $collation = 'utf8mb4_thai_520_w2';
        } else {
            $unicode_collation = mysqli_query($conn, "SHOW COLLATION LIKE 'utf8mb4_unicode_ci'");
            if ($unicode_collation && mysqli_num_rows($unicode_collation) > 0) {
                $collation = 'utf8mb4_unicode_ci';
            }
        }
    }

    return $collation !== '' ? "$column COLLATE $collation" : $column;
}

function jobfind_category_order_clause($conn, $name_column = 'name', $id_column = 'category_id')
{
    return jobfind_category_sort_expression($conn, $name_column) . " ASC, $id_column ASC";
}

?>
