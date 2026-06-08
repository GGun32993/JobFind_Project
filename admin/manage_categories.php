<?php
session_start();
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../helpers/support_helpers.php";
require_once __DIR__ . "/../helpers/category_helpers.php";

$admin_id_for_badge = jobfind_require_role('admin');
$admin_unread_support = admin_unread_support_count($conn, $admin_id_for_badge);

ensure_category_schema($conn);
ensure_default_job_categories($conn);

$toast = '';

// ── ADD ──
if(isset($_POST['add'])){
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $icon = mysqli_real_escape_string($conn, trim($_POST['icon']));
    if($name !== ''){
        $exists = mysqli_fetch_assoc(mysqli_query($conn,"SELECT category_id FROM Categories WHERE name='$name' LIMIT 1"));
        if($exists){
            $toast = 'dup';
        } else {
            $r = mysqli_query($conn,"INSERT INTO Categories (name,icon) VALUES ('$name','$icon')");
            $toast = $r ? 'added' : 'dup';
        }
    }
    header("Location: manage_categories.php?toast=$toast"); exit();
}

// ── EDIT ──
if(isset($_POST['edit'])){
    $id   = intval($_POST['category_id']);
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $icon = mysqli_real_escape_string($conn, trim($_POST['icon']));
    $old_cat = mysqli_fetch_assoc(mysqli_query($conn,"SELECT name FROM Categories WHERE category_id='$id' LIMIT 1"));
    $dup = $name !== ''
        ? mysqli_fetch_assoc(mysqli_query($conn,"SELECT category_id FROM Categories WHERE name='$name' AND category_id!='$id' LIMIT 1"))
        : true;

    if($dup){
        header("Location: manage_categories.php?toast=dup"); exit();
    }

    mysqli_query($conn,"UPDATE Categories SET name='$name', icon='$icon' WHERE category_id='$id'");
    if($old_cat && ($old_cat['name'] ?? '') !== trim($_POST['name'])){
        $old_name = mysqli_real_escape_string($conn, $old_cat['name']);
        mysqli_query($conn,"UPDATE Job SET category='$name' WHERE category='$old_name'");
    }
    header("Location: manage_categories.php?toast=edited"); exit();
}

// ── ADD SUBCATEGORY ──
if(isset($_POST['add_subcategory'])){
    $category_id = intval($_POST['subcategory_category_id'] ?? 0);
    $name = mysqli_real_escape_string($conn, trim($_POST['subcategory_name'] ?? ''));

    if($category_id > 0 && $name !== ''){
        $exists = mysqli_fetch_assoc(mysqli_query($conn,"
            SELECT subcategory_id
            FROM Job_Subcategories
            WHERE category_id='$category_id' AND name='$name'
            LIMIT 1
        "));
        if($exists){
            $toast = 'sub_dup';
        } else {
            $r = mysqli_query($conn,"
                INSERT INTO Job_Subcategories (category_id, name)
                VALUES ('$category_id', '$name')
            ");
            $toast = $r ? 'sub_added' : 'sub_dup';
        }
    } else {
        $toast = 'sub_dup';
    }
    header("Location: manage_categories.php?toast=$toast"); exit();
}

// ── EDIT SUBCATEGORY ──
if(isset($_POST['edit_subcategory'])){
    $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
    $category_id = intval($_POST['subcategory_category_id'] ?? 0);
    $name = mysqli_real_escape_string($conn, trim($_POST['subcategory_name'] ?? ''));

    $old_sub = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT js.name, js.category_id, c.name AS category_name
        FROM Job_Subcategories js
        JOIN Categories c ON c.category_id=js.category_id
        WHERE js.subcategory_id='$subcategory_id'
        LIMIT 1
    "));

    $dup = ($category_id <= 0 || $name === '')
        ? true
        : mysqli_fetch_assoc(mysqli_query($conn,"
            SELECT subcategory_id
            FROM Job_Subcategories
            WHERE category_id='$category_id' AND name='$name' AND subcategory_id!='$subcategory_id'
            LIMIT 1
        "));

    if($dup){
        header("Location: manage_categories.php?toast=sub_dup"); exit();
    }

    $new_cat = mysqli_fetch_assoc(mysqli_query($conn,"SELECT name FROM Categories WHERE category_id='$category_id' LIMIT 1"));
    mysqli_query($conn,"
        UPDATE Job_Subcategories
        SET category_id='$category_id', name='$name'
        WHERE subcategory_id='$subcategory_id'
    ");

    if($old_sub && $new_cat){
        $old_category_name = mysqli_real_escape_string($conn, $old_sub['category_name']);
        $old_sub_name = mysqli_real_escape_string($conn, $old_sub['name']);
        $new_category_name = mysqli_real_escape_string($conn, $new_cat['name']);
        mysqli_query($conn,"
            UPDATE Job
            SET category='$new_category_name', job_subcategory='$name'
            WHERE category='$old_category_name' AND job_subcategory='$old_sub_name'
        ");
    }

    header("Location: manage_categories.php?toast=sub_edited"); exit();
}

// ── DELETE ──
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    mysqli_query($conn,"DELETE FROM Job_Subcategories WHERE category_id='$id'");
    mysqli_query($conn,"DELETE FROM Categories WHERE category_id='$id'");
    header("Location: manage_categories.php?toast=deleted"); exit();
}

// ── DELETE SUBCATEGORY ──
if(isset($_GET['delete_subcategory'])){
    $id = intval($_GET['delete_subcategory']);
    $old_sub = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT js.name, c.name AS category_name
        FROM Job_Subcategories js
        JOIN Categories c ON c.category_id=js.category_id
        WHERE js.subcategory_id='$id'
        LIMIT 1
    "));
    if($old_sub){
        $old_category_name = mysqli_real_escape_string($conn, $old_sub['category_name']);
        $old_sub_name = mysqli_real_escape_string($conn, $old_sub['name']);
        mysqli_query($conn,"
            UPDATE Job
            SET job_subcategory=NULL
            WHERE category='$old_category_name' AND job_subcategory='$old_sub_name'
        ");
    }
    mysqli_query($conn,"DELETE FROM Job_Subcategories WHERE subcategory_id='$id'");
    header("Location: manage_categories.php?toast=sub_deleted"); exit();
}

// ── GET ALL ──
$cats = [];
$res  = mysqli_query($conn,"SELECT * FROM Categories ORDER BY " . jobfind_category_order_clause($conn));
while($r = mysqli_fetch_assoc($res)) $cats[] = $r;

$subcats_by_category = [];
$subcat_res = mysqli_query($conn,"
    SELECT js.*, c.name AS category_name
    FROM Job_Subcategories js
    JOIN Categories c ON c.category_id=js.category_id
    ORDER BY " . jobfind_category_sort_expression($conn, 'c.name') . " ASC,
             " . jobfind_category_sort_expression($conn, 'js.name') . " ASC,
             js.subcategory_id ASC
");
if($subcat_res){
    while($sr = mysqli_fetch_assoc($subcat_res)){
        $subcats_by_category[(int)$sr['category_id']][] = $sr;
    }
}

// edit mode
$edit_cat = null;
if(isset($_GET['edit'])){
    $eid = intval($_GET['edit']);
    foreach($cats as $c){ if($c['category_id']==$eid){ $edit_cat=$c; break; } }
}

$edit_subcat = null;
if(isset($_GET['edit_subcategory'])){
    $sid = intval($_GET['edit_subcategory']);
    foreach($subcats_by_category as $items){
        foreach($items as $subcat){
            if((int)$subcat['subcategory_id'] === $sid){
                $edit_subcat = $subcat;
                break 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<link rel="icon" type="image/png" href="../assets/images/jobfind-logo-icon.png?v=13">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Categories</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600&display=swap');

  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

  :root {
    --navy:#0f172a; --navy2:#1e293b; --navy3:#334155;
    --accent:#6366f1; --light:#f1f5f9; --white:#ffffff;
    --text:#0f172a; --muted:#64748b; --border:#e2e8f0;
    --green:#10b981; --red:#ef4444; --yellow:#f59e0b; --radius:14px;
  }

  body { font-family:'Sora',sans-serif; background:var(--light); color:var(--text); display:flex; min-height:100vh; }

  /* ── Sidebar ── */
  .sidebar { width:240px; min-height:100vh; background:var(--navy); display:flex; flex-direction:column; padding:28px 0; position:fixed; top:0; left:0; z-index:100; }
  .sidebar-brand { padding:0 24px 28px; border-bottom:1px solid var(--navy3); }
  .logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
  .logo-icon { width:36px; height:36px; background:var(--accent); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; }
  .logo-text { font-size:15px; font-weight:600; color:#fff; line-height:1.2; }
  .logo-sub  { font-size:11px; color:var(--navy3); }
  .sidebar-nav { padding:20px 12px; flex:1; display:flex; flex-direction:column; gap:4px; }
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#94a3b8; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s,color .15s; }
  .nav-item:hover  { background:var(--navy2); color:#e2e8f0; }
  .nav-item.active { background:var(--accent); color:#fff; }
  .nav-item i { font-size:17px; width:20px; text-align:center; }
  .nav-divider { height:1px; background:var(--navy3); margin:10px 14px; }
  .sidebar-footer { padding:16px 12px 0; }
  .nav-logout { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:#f87171; text-decoration:none; font-size:13.5px; font-weight:500; transition:background .15s; }
  .nav-logout:hover { background:rgba(239,68,68,.12); }
  .nav-logout i { font-size:17px; }

  /* ── Main ── */
  .main { margin-left:240px; flex:1; padding:36px 40px; }

  /* ── Topbar ── */
  .topbar { margin-bottom:28px; }
  .topbar h2 { font-size:22px; font-weight:600; }
  .topbar p  { font-size:13px; color:var(--muted); margin-top:2px; }

  /* ── Toast ── */
  .toast-bar { position:fixed; top:24px; right:24px; z-index:999; background:var(--navy); color:#fff; padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; box-shadow:0 8px 24px rgba(0,0,0,.18); animation:slideIn .3s ease; transition:opacity .4s; }
  .toast-bar i { font-size:18px; }
  .toast-ok  { background:var(--navy); }
  .toast-err { background:#7f1d1d; }
  @keyframes slideIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }

  /* ── Layout 2 col ── */
  .layout { display:grid; grid-template-columns:320px 1fr; gap:24px; align-items:start; }

  /* ── Form card ── */
  .form-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:24px; position:sticky; top:36px; }
  .form-card h4 { font-size:15px; font-weight:600; margin-bottom:18px; display:flex; align-items:center; gap:8px; }

  .field-group { margin-bottom:16px; }
  .field-group label { display:block; font-size:13px; font-weight:500; margin-bottom:6px; }
  .field-group label span { color:var(--muted); font-weight:400; font-size:12px; }
  .form-input { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--text); outline:none; transition:border-color .15s,box-shadow .15s; }
  .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(99,102,241,.12); }

  /* emoji picker row */
  .emoji-row { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
  .emoji-btn { width:36px; height:36px; border-radius:8px; border:1px solid var(--border); background:var(--white); font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .15s,border-color .15s; }
  .emoji-btn:hover, .emoji-btn.selected { background:#eef2ff; border-color:var(--accent); }

  .form-actions { display:flex; gap:8px; }
  .btn-save { flex:1; padding:11px; background:var(--accent); color:#fff; border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:background .15s; }
  .btn-save:hover { background:#4f46e5; }
  .btn-cancel-edit { padding:11px 16px; border:1px solid var(--border); border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; color:var(--muted); text-decoration:none; display:flex; align-items:center; gap:5px; transition:background .15s; }
  .btn-cancel-edit:hover { background:var(--light); color:var(--text); }

  /* ── Category list card ── */
  .list-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
  .list-head { padding:18px 22px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
  .list-head h4 { font-size:15px; font-weight:600; }
  .count-badge { background:#eef2ff; color:var(--accent); font-size:12px; font-weight:600; padding:3px 10px; border-radius:20px; }

  .cat-item { display:block; padding:14px 22px; border-bottom:1px solid var(--border); transition:background .15s; }
  .cat-item:last-child { border-bottom:none; }
  .cat-item:hover { background:#f8f9ff; }
  .cat-item.editing { background:#eef2ff; }

  .cat-row { display:flex; align-items:center; gap:14px; }
  .cat-icon-box { width:42px; height:42px; border-radius:11px; background:var(--light); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
  .cat-name { font-size:14px; font-weight:600; flex:1; }
  .cat-date { font-size:12px; color:var(--muted); margin-right:10px; }
  .cat-actions { display:flex; gap:6px; flex-shrink:0; }
  .act-btn { display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border-radius:8px; font-size:12.5px; font-weight:500; text-decoration:none; border:none; cursor:pointer; font-family:'Sora',sans-serif; transition:opacity .15s; }
  .act-btn:hover { opacity:.85; }
  .ab-edit   { background:#fef9c3; color:#854d0e; }
  .ab-delete { background:#fee2e2; color:#991b1b; }

  .sub-form-divider { height:1px; background:var(--border); margin:22px 0; }
  .subcat-list { display:flex; flex-wrap:wrap; gap:8px; margin:12px 0 0 56px; }
  .subcat-chip { display:inline-flex; align-items:center; gap:7px; padding:6px 9px; border-radius:999px; background:#f8fafc; border:1px solid var(--border); font-size:12.5px; color:var(--muted); }
  .subcat-chip.editing { background:#eef2ff; border-color:#c7d2fe; color:var(--accent); }
  .subcat-chip a,
  .subcat-chip button { width:22px; height:22px; border-radius:50%; border:none; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; color:inherit; background:transparent; cursor:pointer; }
  .subcat-chip a:hover,
  .subcat-chip button:hover { background:#e2e8f0; color:var(--text); }
  .subcat-empty { font-size:12.5px; color:var(--muted); }

  .empty-list { text-align:center; padding:40px; color:var(--muted); }
  .empty-list i { font-size:36px; color:#c7d2fe; margin-bottom:10px; display:block; }

  /* ── Delete modal ── */
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; align-items:center; justify-content:center; }
  .modal-overlay.show { display:flex; }
  .modal-box { background:var(--white); border-radius:var(--radius); padding:30px; max-width:380px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.22); }
  .modal-icon { width:54px; height:54px; border-radius:50%; background:#fee2e2; color:var(--red); font-size:26px; display:flex; align-items:center; justify-content:center; margin-bottom:16px; }
  .modal-title { font-size:17px; font-weight:600; margin-bottom:6px; }
  .modal-sub   { font-size:13px; color:var(--muted); line-height:1.7; margin-bottom:24px; }
  .modal-actions { display:flex; gap:10px; }
  .btn-mc { flex:1; padding:11px; border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; font-weight:500; cursor:pointer; border:1px solid var(--border); background:var(--white); color:var(--text); }
  .btn-mc:hover { background:var(--light); }
  .btn-md { flex:1; padding:11px; border:none; border-radius:10px; font-family:'Sora',sans-serif; font-size:14px; font-weight:600; cursor:pointer; background:var(--red); color:#fff; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center; gap:6px; }
  .btn-md:hover { opacity:.88; color:#fff; }

  @media(max-width:900px){ .layout { grid-template-columns:1fr; } .form-card { position:static; } }
  @media(max-width:768px){ .sidebar { display:none; } .main { margin-left:0; padding:20px 16px; } }
</style>
<link rel="stylesheet" href="../assets/css/freelancehub-theme.css">

</head>
<body>

<!-- ── Toast ── -->
<?php
$toasts = [
    'added'  => ['ok',  'bi-check-circle-fill', 'เพิ่มหมวดหมู่เรียบร้อยแล้ว'],
    'edited' => ['ok',  'bi-check-circle-fill', 'แก้ไขหมวดหมู่เรียบร้อยแล้ว'],
    'deleted'=> ['ok',  'bi-check-circle-fill', 'ลบหมวดหมู่เรียบร้อยแล้ว'],
    'sub_added'  => ['ok',  'bi-check-circle-fill', 'เพิ่มงานย่อยเรียบร้อยแล้ว'],
    'sub_edited' => ['ok',  'bi-check-circle-fill', 'แก้ไขงานย่อยเรียบร้อยแล้ว'],
    'sub_deleted'=> ['ok',  'bi-check-circle-fill', 'ลบงานย่อยเรียบร้อยแล้ว'],
    'dup'    => ['err', 'bi-exclamation-triangle-fill', 'ชื่อหมวดหมู่นี้มีอยู่แล้ว'],
    'sub_dup'=> ['err', 'bi-exclamation-triangle-fill', 'ชื่องานย่อยนี้มีอยู่แล้ว หรือข้อมูลไม่ครบ'],
];
if(isset($_GET['toast']) && isset($toasts[$_GET['toast']])):
    [$type,$icon,$msg] = $toasts[$_GET['toast']];
?>
<div class="toast-bar toast-<?php echo $type; ?>" id="toast">
  <i class="bi <?php echo $icon; ?>" style="color:<?php echo $type==='ok'?'var(--green)':'#fca5a5'; ?>;"></i>
  <?php echo $msg; ?>
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.style.opacity='0'; },3000);</script>
<?php endif; ?>

<!-- ── Delete modal ── -->
<div class="modal-overlay" id="del-modal">
  <div class="modal-box">
    <div class="modal-icon"><i class="bi bi-trash3"></i></div>
    <div class="modal-title" id="del-title">ยืนยันการลบหมวดหมู่</div>
    <div class="modal-sub" id="del-sub">ลบ <strong id="del-name"></strong> ออกจากระบบ?</div>
    <div class="modal-actions">
      <button class="btn-mc" onclick="closeModal()">ยกเลิก</button>
      <a href="#" id="del-link" class="btn-md"><i class="bi bi-trash3"></i> ลบเลย</a>
    </div>
  </div>
</div>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <a href="../index.php" class="logo">
      <div class="logo-icon" style="width:132px!important;height:120px!important;min-width:132px!important;max-width:132px!important;max-height:120px!important;flex:0 0 120px!important;border-radius:0!important;background:transparent!important;padding:0!important;overflow:hidden!important;box-shadow:none!important;margin:0 auto!important;align-self:center!important;"><img class="brand-logo-img" src="../assets/images/jobfind-logo.png?v=13" alt="Job_Find logo" style="width:100%;height:100%;object-fit:contain;display:block;"></div>
      <div>
        <div class="logo-text" style="display:none!important;">Job_Find</div>
        <div class="logo-sub" style="display:none!important;">Admin Panel</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"          class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="manage_users.php"       class="nav-item"><i class="bi bi-people"></i> Manage Users</a>
    <a href="manage_jobs.php"        class="nav-item"><i class="bi bi-briefcase"></i> Manage Jobs</a>
    <a href="manage_categories.php"  class="nav-item active"><i class="bi bi-tag"></i> Categories</a>
    <div class="nav-divider"></div>
    <a href="support.php"            class="nav-item">
      <i class="bi bi-chat-dots"></i> Support Chat
      <?php if($admin_unread_support > 0): ?><span class="nav-badge"><?php echo $admin_unread_support; ?></span><?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="nav-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main">

  <div class="topbar">
    <h2>Manage Categories</h2>
    <p>จัดการหมวดหมู่งานที่ Employer เลือกตอนโพสต์และ Freelancer ใช้กรอง</p>
  </div>

  <div class="layout">

    <!-- Form (Add / Edit) -->
    <div class="form-card">
      <?php if($edit_cat): ?>
      <h4><i class="bi bi-pencil" style="color:var(--accent);"></i> แก้ไขหมวดหมู่</h4>
      <form method="POST">
        <input type="hidden" name="category_id" value="<?php echo $edit_cat['category_id']; ?>">
      <?php else: ?>
      <h4><i class="bi bi-plus-circle" style="color:var(--accent);"></i> เพิ่มหมวดหมู่ใหม่</h4>
      <form method="POST">
      <?php endif; ?>

        <div class="field-group">
          <label>ชื่อหมวดหมู่</label>
          <input type="text" name="name" class="form-input"
                 placeholder="เช่น IT & Software"
                 value="<?php echo htmlspecialchars($edit_cat['name'] ?? ''); ?>"
                 required maxlength="100">
        </div>

        <div class="field-group">
          <label>Icon <span>(เลือก emoji)</span></label>
          <input type="text" name="icon" id="icon-input" class="form-input"
                 placeholder="📦"
                 value="<?php echo htmlspecialchars($edit_cat['icon'] ?? '📦'); ?>"
                 maxlength="5" style="font-size:20px;text-align:center;width:70px;">
          <div class="emoji-row" id="emoji-row">
            <?php
            $emojis = ['💻','🎨','📢','✍️','💰','🎓','📦','⚙️','📱','🚀','🔬','🏥','🏗️','📊','🎵','📸','🛒','✈️','🎮','🌐'];
            foreach($emojis as $e):
              $sel = ($edit_cat['icon'] ?? '📦') === $e ? 'selected' : '';
            ?>
            <button type="button" class="emoji-btn <?php echo $sel; ?>"
                    onclick="pickEmoji('<?php echo $e; ?>')"><?php echo $e; ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-actions">
          <?php if($edit_cat): ?>
          <button type="submit" name="edit" class="btn-save">
            <i class="bi bi-check-lg"></i> บันทึก
          </button>
          <a href="manage_categories.php" class="btn-cancel-edit">
            <i class="bi bi-x-lg"></i> ยกเลิก
          </a>
          <?php else: ?>
          <button type="submit" name="add" class="btn-save">
            <i class="bi bi-plus-lg"></i> เพิ่มหมวดหมู่
          </button>
          <?php endif; ?>
        </div>
      </form>

      <div class="sub-form-divider"></div>

      <?php if($edit_subcat): ?>
      <h4><i class="bi bi-pencil-square" style="color:var(--accent);"></i> แก้ไขงานย่อย</h4>
      <form method="POST">
        <input type="hidden" name="subcategory_id" value="<?php echo (int)$edit_subcat['subcategory_id']; ?>">
      <?php else: ?>
      <h4><i class="bi bi-diagram-3" style="color:var(--accent);"></i> เพิ่มงานย่อย</h4>
      <form method="POST">
      <?php endif; ?>

        <div class="field-group">
          <label>หมวดหมู่หลัก</label>
          <select name="subcategory_category_id" class="form-input" required>
            <option value="">เลือกหมวดหมู่หลัก</option>
            <?php foreach($cats as $cat_option): ?>
              <?php
                $selected_subcat_category = (int)($edit_subcat['category_id'] ?? 0) === (int)$cat_option['category_id'] ? 'selected' : '';
              ?>
              <option value="<?php echo (int)$cat_option['category_id']; ?>" <?php echo $selected_subcat_category; ?>>
                <?php echo htmlspecialchars($cat_option['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field-group">
          <label>ชื่องานย่อย</label>
          <input type="text" name="subcategory_name" class="form-input"
                 placeholder="เช่น Website Development"
                 value="<?php echo htmlspecialchars($edit_subcat['name'] ?? ''); ?>"
                 required maxlength="120">
        </div>

        <div class="form-actions">
          <?php if($edit_subcat): ?>
          <button type="submit" name="edit_subcategory" class="btn-save">
            <i class="bi bi-check-lg"></i> บันทึก
          </button>
          <a href="manage_categories.php" class="btn-cancel-edit">
            <i class="bi bi-x-lg"></i> ยกเลิก
          </a>
          <?php else: ?>
          <button type="submit" name="add_subcategory" class="btn-save">
            <i class="bi bi-plus-lg"></i> เพิ่มงานย่อย
          </button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Category list -->
    <div class="list-card">
      <div class="list-head">
        <h4>หมวดหมู่ทั้งหมด</h4>
        <span class="count-badge"><?php echo count($cats); ?> รายการ</span>
      </div>

      <?php if(empty($cats)): ?>
      <div class="empty-list">
        <i class="bi bi-tag"></i>
        <p>ยังไม่มีหมวดหมู่</p>
      </div>
      <?php else: ?>
      <?php foreach($cats as $c):
        $is_editing = ($edit_cat && $edit_cat['category_id']==$c['category_id']);
        $date_str   = !empty($c['created_at']) ? date('d M Y', strtotime($c['created_at'])) : '';
        $subcats = $subcats_by_category[(int)$c['category_id']] ?? [];
      ?>
      <div class="cat-item <?php echo $is_editing?'editing':''; ?>">
        <div class="cat-row">
          <div class="cat-icon-box"><?php echo $c['icon']; ?></div>
          <div class="cat-name"><?php echo htmlspecialchars($c['name']); ?></div>
          <?php if($date_str): ?>
          <div class="cat-date"><?php echo $date_str; ?></div>
          <?php endif; ?>
          <div class="cat-actions">
            <a href="manage_categories.php?edit=<?php echo $c['category_id']; ?>"
               class="act-btn ab-edit">
              <i class="bi bi-pencil"></i> แก้ไข
            </a>
            <button class="act-btn ab-delete"
              onclick='confirmDelete(<?php echo (int)$c['category_id']; ?>, <?php echo json_encode($c['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
              <i class="bi bi-trash3"></i> ลบ
            </button>
          </div>
        </div>
        <div class="subcat-list">
          <?php if(empty($subcats)): ?>
            <span class="subcat-empty">ยังไม่มีงานย่อย</span>
          <?php else: ?>
            <?php foreach($subcats as $subcat):
              $is_sub_editing = ($edit_subcat && (int)$edit_subcat['subcategory_id'] === (int)$subcat['subcategory_id']);
            ?>
            <span class="subcat-chip <?php echo $is_sub_editing ? 'editing' : ''; ?>">
              <i class="bi bi-diagram-3"></i>
              <?php echo htmlspecialchars($subcat['name']); ?>
              <a href="manage_categories.php?edit_subcategory=<?php echo (int)$subcat['subcategory_id']; ?>" title="แก้ไขงานย่อย">
                <i class="bi bi-pencil"></i>
              </a>
              <button type="button" title="ลบงานย่อย"
                onclick='confirmDeleteSubcategory(<?php echo (int)$subcat['subcategory_id']; ?>, <?php echo json_encode($subcat['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($c['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                <i class="bi bi-trash3"></i>
              </button>
            </span>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</main>

<script>
  function pickEmoji(e){
    document.getElementById('icon-input').value = e;
    document.querySelectorAll('.emoji-btn').forEach(b => {
      b.classList.toggle('selected', b.textContent.trim() === e);
    });
  }

  // sync selected state on load
  const cur = document.getElementById('icon-input').value.trim();
  document.querySelectorAll('.emoji-btn').forEach(b => {
    b.classList.toggle('selected', b.textContent.trim() === cur);
  });

  function confirmDelete(id, name){
    document.getElementById('del-title').textContent = 'ยืนยันการลบหมวดหมู่';
    document.getElementById('del-name').textContent = name;
    document.getElementById('del-sub').innerHTML = 'ลบ <strong id="del-name">' + escapeHtml(name) + '</strong> ออกจากระบบ?<br>งานที่ใช้หมวดหมู่นี้อยู่จะยังคงอยู่ แต่รายการงานย่อยของหมวดนี้จะถูกลบ';
    document.getElementById('del-link').href = 'manage_categories.php?delete=' + id;
    document.getElementById('del-modal').classList.add('show');
  }
  function confirmDeleteSubcategory(id, name, categoryName){
    document.getElementById('del-title').textContent = 'ยืนยันการลบงานย่อย';
    document.getElementById('del-sub').innerHTML = 'ลบ <strong id="del-name">' + escapeHtml(name) + '</strong> ในหมวด ' + escapeHtml(categoryName) + '?<br>งานเดิมที่เลือกงานย่อยนี้จะถูกล้างเฉพาะค่างานย่อย';
    document.getElementById('del-link').href = 'manage_categories.php?delete_subcategory=' + id;
    document.getElementById('del-modal').classList.add('show');
  }
  function escapeHtml(value){
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }
  function closeModal(){
    document.getElementById('del-modal').classList.remove('show');
  }
  document.getElementById('del-modal').addEventListener('click',function(e){
    if(e.target===this) closeModal();
  });
</script>
</body>
</html>
