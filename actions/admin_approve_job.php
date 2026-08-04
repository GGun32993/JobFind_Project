<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../helpers/auth_helpers.php";
require_once __DIR__ . "/../services/admin_job_service.php";

jobfind_require_role('admin');

$id = intval($_GET['id'] ?? 0);
admin_approve_job($conn, $id);

header("Location: ../admin/manage_jobs.php");
exit();
