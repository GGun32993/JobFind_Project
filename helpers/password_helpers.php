<?php

if (!function_exists('jobfind_hash_password')) {
    function jobfind_hash_password($password)
    {
        return password_hash((string)$password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('jobfind_verify_password')) {
    function jobfind_verify_password($password, $stored_password, &$needs_rehash = false)
    {
        $password = (string)$password;
        $stored_password = (string)$stored_password;
        $needs_rehash = false;

        if ($stored_password === '') {
            return false;
        }

        if (password_verify($password, $stored_password)) {
            $needs_rehash = password_needs_rehash($stored_password, PASSWORD_DEFAULT);
            return true;
        }

        if (hash_equals($stored_password, $password)) {
            $needs_rehash = true;
            return true;
        }

        return false;
    }
}

