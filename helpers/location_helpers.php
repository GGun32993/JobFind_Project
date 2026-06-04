<?php

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/location_schema.php";
require_once __DIR__ . "/review_schema.php";

function jobfind_coordinate_value($value)
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (float)$value;
}

function jobfind_has_coordinates($lat, $lon)
{
    $lat = jobfind_coordinate_value($lat);
    $lon = jobfind_coordinate_value($lon);

    if ($lat === null || $lon === null) {
        return false;
    }

    return $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180;
}

function jobfind_clamp_radius($radius)
{
    $radius = is_numeric($radius) ? (float)$radius : 30;
    return max(1, min(300, $radius));
}

function haversineDistance($lat1, $lon1, $lat2, $lon2)
{
    $earth_radius_km = 6371;

    $lat1_rad = deg2rad((float)$lat1);
    $lon1_rad = deg2rad((float)$lon1);
    $lat2_rad = deg2rad((float)$lat2);
    $lon2_rad = deg2rad((float)$lon2);

    $dlat = $lat2_rad - $lat1_rad;
    $dlon = $lon2_rad - $lon1_rad;

    $a = sin($dlat / 2) * sin($dlat / 2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($dlon / 2) * sin($dlon / 2);

    return $earth_radius_km * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function locationMatch($freelancer_location, $job_location)
{
    if (empty($freelancer_location) || empty($job_location)) {
        return 0;
    }

    $lower = function ($value) {
        $value = trim((string)$value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };
    $contains = function ($haystack, $needle) {
        return function_exists('mb_strpos')
            ? mb_strpos($haystack, $needle, 0, 'UTF-8') !== false
            : strpos($haystack, $needle) !== false;
    };

    $freelancer_location = $lower($freelancer_location);
    $job_location = $lower($job_location);

    if ($freelancer_location === $job_location) {
        return 100;
    }

    if ($contains($job_location, $freelancer_location) || $contains($freelancer_location, $job_location)) {
        return 80;
    }

    $freelancer_parts = preg_split('/[\s,\/-]+/u', $freelancer_location, -1, PREG_SPLIT_NO_EMPTY);
    $job_parts = preg_split('/[\s,\/-]+/u', $job_location, -1, PREG_SPLIT_NO_EMPTY);

    if (!$freelancer_parts || !$job_parts) {
        return 0;
    }

    $common_parts = array_intersect($freelancer_parts, $job_parts);
    if (empty($common_parts)) {
        return 0;
    }

    return (int)round((count($common_parts) / max(count($freelancer_parts), count($job_parts))) * 100);
}

function jobfind_distance_score($distance_km, $radius_km)
{
    if ($distance_km <= 1) {
        return 100;
    }

    $ratio = $radius_km > 0 ? ($distance_km / $radius_km) : 1;

    if ($ratio <= 0.25) {
        return 95;
    }
    if ($ratio <= 0.50) {
        return 88;
    }
    if ($ratio <= 0.75) {
        return 78;
    }

    return 65;
}

function getFreelancerLocationSummary($conn, $user_id)
{
    ensure_location_schema($conn);
    $user_id = (int)$user_id;

    $result = mysqli_query($conn, "
        SELECT fp.location, fp.address, fp.province, fp.district,
               fp.latitude, fp.longitude, fp.preferred_radius_km,
               u.latitude AS user_lat, u.longitude AS user_lon
        FROM freelancer_profile fp
        JOIN users u ON u.user_id = fp.user_id
        WHERE fp.user_id = $user_id
        LIMIT 1
    ");

    if (!$result || mysqli_num_rows($result) === 0) {
        return [
            'label' => '',
            'radius_km' => 30,
            'latitude' => null,
            'longitude' => null,
            'has_pin' => false,
        ];
    }

    $row = mysqli_fetch_assoc($result);
    $lat = jobfind_coordinate_value($row['latitude'] ?? null);
    $lon = jobfind_coordinate_value($row['longitude'] ?? null);

    if (!jobfind_has_coordinates($lat, $lon)) {
        $lat = jobfind_coordinate_value($row['user_lat'] ?? null);
        $lon = jobfind_coordinate_value($row['user_lon'] ?? null);
    }

    $parts = array_filter([
        $row['district'] ?? '',
        $row['province'] ?? '',
        $row['location'] ?? '',
    ], fn($value) => trim((string)$value) !== '');

    return [
        'label' => $parts ? implode(', ', array_unique($parts)) : ($row['address'] ?? ''),
        'radius_km' => jobfind_clamp_radius($row['preferred_radius_km'] ?? 30),
        'latitude' => $lat,
        'longitude' => $lon,
        'has_pin' => jobfind_has_coordinates($lat, $lon),
    ];
}

function getRecommendedJobs($conn, $user_id, $limit = 10, $min_match_score = 40)
{
    ensure_location_schema($conn);
    $user_id = (int)$user_id;
    $limit = max(1, (int)$limit);
    $min_match_score = max(0, min(100, (int)$min_match_score));

    $profile_query = mysqli_query($conn, "
        SELECT fp.location, fp.address, fp.province, fp.district,
               fp.latitude, fp.longitude, fp.preferred_radius_km,
               u.latitude AS user_lat, u.longitude AS user_lon
        FROM freelancer_profile fp
        JOIN users u ON u.user_id = fp.user_id
        WHERE fp.user_id = $user_id
        LIMIT 1
    ");

    if (!$profile_query || mysqli_num_rows($profile_query) === 0) {
        return [];
    }

    $freelancer = mysqli_fetch_assoc($profile_query);
    $radius_km = jobfind_clamp_radius($freelancer['preferred_radius_km'] ?? 30);
    $freelancer_lat = jobfind_coordinate_value($freelancer['latitude'] ?? null);
    $freelancer_lon = jobfind_coordinate_value($freelancer['longitude'] ?? null);

    if (!jobfind_has_coordinates($freelancer_lat, $freelancer_lon)) {
        $freelancer_lat = jobfind_coordinate_value($freelancer['user_lat'] ?? null);
        $freelancer_lon = jobfind_coordinate_value($freelancer['user_lon'] ?? null);
    }

    $freelancer_has_pin = jobfind_has_coordinates($freelancer_lat, $freelancer_lon);

    $jobs_query = mysqli_query($conn, "
        SELECT
            j.job_id,
            j.employer_id,
            j.title,
            j.description,
            j.location,
            j.status,
            j.latitude AS job_lat,
            j.longitude AS job_lon,
            j.salary,
            j.deadline,
            j.category,
            j.image_path,
            j.created_at,
            COALESCE(er.avg_rating, 0) AS avg_rating,
            COALESCE(er.total_reviews, 0) AS total_reviews,
            COALESCE(NULLIF(ep.employer_name,''), NULLIF(u.fullname,''), u.username) AS company_name,
            ep.address AS employer_address,
            ep.province AS employer_province,
            ep.district AS employer_district,
            ep.latitude AS employer_lat,
            ep.longitude AS employer_lon,
            u.latitude AS employer_user_lat,
            u.longitude AS employer_user_lon
        FROM job j
        JOIN users u ON u.user_id = j.employer_id
        LEFT JOIN employer_profile ep ON ep.user_id = j.employer_id
        LEFT JOIN (
            SELECT employer_id, AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
            FROM employer_review
            GROUP BY employer_id
        ) er ON er.employer_id = j.employer_id
        WHERE j.admin_status = 'approved'
        AND (j.status IN ('open', 'in_progress') OR j.status IS NULL OR j.status = '')
        ORDER BY j.created_at DESC
        LIMIT 150
    ");

    $recommended = [];
    if (!$jobs_query) {
        error_log('Job_Find recommendations query failed: ' . mysqli_error($conn));
        return [];
    }

    while ($job = mysqli_fetch_assoc($jobs_query)) {
        $job_lat = jobfind_coordinate_value($job['job_lat'] ?? null);
        $job_lon = jobfind_coordinate_value($job['job_lon'] ?? null);

        if (!jobfind_has_coordinates($job_lat, $job_lon)) {
            $job_lat = jobfind_coordinate_value($job['employer_lat'] ?? null);
            $job_lon = jobfind_coordinate_value($job['employer_lon'] ?? null);
        }

        if (!jobfind_has_coordinates($job_lat, $job_lon)) {
            $job_lat = jobfind_coordinate_value($job['employer_user_lat'] ?? null);
            $job_lon = jobfind_coordinate_value($job['employer_user_lon'] ?? null);
        }

        $text_score = max(
            locationMatch($freelancer['location'] ?? '', $job['location'] ?? ''),
            locationMatch($freelancer['province'] ?? '', $job['location'] ?? ''),
            locationMatch($freelancer['province'] ?? '', $job['employer_province'] ?? ''),
            locationMatch($freelancer['district'] ?? '', $job['employer_district'] ?? ''),
            locationMatch($freelancer['address'] ?? '', $job['employer_address'] ?? '')
        );

        $distance_km = null;
        $match_score = $text_score;
        $matched_by = 'text';

        if ($freelancer_has_pin && jobfind_has_coordinates($job_lat, $job_lon)) {
            $distance_km = haversineDistance($freelancer_lat, $freelancer_lon, $job_lat, $job_lon);

            if ($distance_km > $radius_km) {
                continue;
            }

            $match_score = max($text_score, jobfind_distance_score($distance_km, $radius_km));
            $matched_by = 'distance';
        }

        if (!$freelancer_has_pin && $match_score < $min_match_score) {
            continue;
        }

        if ($freelancer_has_pin && $distance_km === null && $match_score < $min_match_score) {
            continue;
        }

        $job['match_score'] = $match_score;
        $job['distance_km'] = $distance_km;
        $job['radius_km'] = $radius_km;
        $job['matched_by'] = $matched_by;
        $recommended[] = $job;
    }

    usort($recommended, function ($a, $b) {
        if ((int)$a['match_score'] !== (int)$b['match_score']) {
            return (int)$b['match_score'] - (int)$a['match_score'];
        }

        $a_distance = $a['distance_km'] ?? PHP_FLOAT_MAX;
        $b_distance = $b['distance_km'] ?? PHP_FLOAT_MAX;
        if ($a_distance != $b_distance) {
            return $a_distance <=> $b_distance;
        }

        return strtotime($b['created_at'] ?? 'now') <=> strtotime($a['created_at'] ?? 'now');
    });

    return array_slice($recommended, 0, $limit);
}

function getNearbyFreelancers($conn, $employer_user_id, $limit = 10, $min_match_score = 40, $radius_km = 30)
{
    ensure_location_schema($conn);
    $employer_user_id = (int)$employer_user_id;
    $limit = max(1, (int)$limit);
    $radius_km = jobfind_clamp_radius($radius_km);

    $employer_query = mysqli_query($conn, "
        SELECT ep.address, ep.province, ep.district, ep.latitude, ep.longitude,
               u.latitude AS user_lat, u.longitude AS user_lon
        FROM employer_profile ep
        JOIN users u ON u.user_id = ep.user_id
        WHERE ep.user_id = $employer_user_id
        LIMIT 1
    ");

    if (!$employer_query || mysqli_num_rows($employer_query) === 0) {
        return [];
    }

    $employer = mysqli_fetch_assoc($employer_query);
    $employer_lat = jobfind_coordinate_value($employer['latitude'] ?? null);
    $employer_lon = jobfind_coordinate_value($employer['longitude'] ?? null);

    if (!jobfind_has_coordinates($employer_lat, $employer_lon)) {
        $employer_lat = jobfind_coordinate_value($employer['user_lat'] ?? null);
        $employer_lon = jobfind_coordinate_value($employer['user_lon'] ?? null);
    }

    $employer_has_pin = jobfind_has_coordinates($employer_lat, $employer_lon);

    ensure_freelancer_review_schema($conn);

    $freelancers_query = mysqli_query($conn, "
        SELECT
            u.user_id,
            u.username,
            u.fullname,
            u.email,
            u.phone,
            fp.skill,
            fp.experience,
            fp.location,
            fp.address,
            fp.province,
            fp.district,
            fp.latitude,
            fp.longitude,
            fp.preferred_radius_km,
            (SELECT COUNT(*) FROM freelancer_review WHERE freelancer_id = u.user_id) AS review_count,
            (SELECT AVG(rating) FROM freelancer_review WHERE freelancer_id = u.user_id) AS avg_rating
        FROM users u
        JOIN freelancer_profile fp ON fp.user_id = u.user_id
        WHERE u.role = 'freelancer'
        ORDER BY fp.created_at DESC
        LIMIT 150
    ");

    $nearby = [];
    if (!$freelancers_query) {
        error_log('Job_Find nearby freelancers query failed: ' . mysqli_error($conn));
        return [];
    }

    while ($freelancer = mysqli_fetch_assoc($freelancers_query)) {
        $text_score = max(
            locationMatch($employer['province'] ?? '', $freelancer['province'] ?? ''),
            locationMatch($employer['district'] ?? '', $freelancer['district'] ?? ''),
            locationMatch($employer['address'] ?? '', $freelancer['location'] ?? '')
        );

        $distance_km = null;
        $match_score = $text_score;

        if ($employer_has_pin && jobfind_has_coordinates($freelancer['latitude'] ?? null, $freelancer['longitude'] ?? null)) {
            $distance_km = haversineDistance(
                $employer_lat,
                $employer_lon,
                $freelancer['latitude'],
                $freelancer['longitude']
            );

            if ($distance_km > $radius_km) {
                continue;
            }

            $match_score = max($text_score, jobfind_distance_score($distance_km, $radius_km));
        }

        if ($match_score < $min_match_score) {
            continue;
        }

        $freelancer['match_score'] = $match_score;
        $freelancer['distance_km'] = $distance_km;
        $nearby[] = $freelancer;
    }

    usort($nearby, function ($a, $b) {
        if ((int)$a['match_score'] !== (int)$b['match_score']) {
            return (int)$b['match_score'] - (int)$a['match_score'];
        }

        return (float)($b['avg_rating'] ?? 0) <=> (float)($a['avg_rating'] ?? 0);
    });

    return array_slice($nearby, 0, $limit);
}

?>
