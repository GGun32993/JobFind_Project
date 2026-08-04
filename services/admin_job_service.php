<?php

function admin_approve_job($conn, $job_id)
{
    $job_id = (int)$job_id;
    if (!$conn || $job_id <= 0) {
        return false;
    }

    return (bool)mysqli_query($conn, "
        UPDATE Job
        SET admin_status='approved', status='open'
        WHERE job_id='$job_id'
    ");
}

function admin_reject_job($conn, $job_id)
{
    $job_id = (int)$job_id;
    if (!$conn || $job_id <= 0) {
        return false;
    }

    return (bool)mysqli_query($conn, "
        UPDATE Job
        SET admin_status='rejected'
        WHERE job_id='$job_id'
    ");
}

