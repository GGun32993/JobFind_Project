-- Add address fields for location-based job recommendations

-- Add address field to employer_profile table
ALTER TABLE `employer_profile` 
ADD COLUMN `address` VARCHAR(255) DEFAULT NULL AFTER `employer_description`,
ADD COLUMN `province` VARCHAR(100) DEFAULT NULL AFTER `address`,
ADD COLUMN `district` VARCHAR(100) DEFAULT NULL AFTER `province`,
ADD COLUMN `postal_code` VARCHAR(10) DEFAULT NULL AFTER `district`,
ADD COLUMN `latitude` DOUBLE DEFAULT NULL AFTER `postal_code`,
ADD COLUMN `longitude` DOUBLE DEFAULT NULL AFTER `latitude`,
ADD COLUMN `preferred_radius_km` DOUBLE NOT NULL DEFAULT 30 AFTER `longitude`;

-- Add more detailed address fields to users table (if not already present)
-- ALTER TABLE `users` 
-- ADD COLUMN `address` VARCHAR(255) DEFAULT NULL AFTER `phone`,
-- ADD COLUMN `province` VARCHAR(100) DEFAULT NULL AFTER `address`,
-- ADD COLUMN `district` VARCHAR(100) DEFAULT NULL AFTER `province`,
-- ADD COLUMN `postal_code` VARCHAR(10) DEFAULT NULL AFTER `district`;

-- Add more detailed location fields to freelancer_profile table  
ALTER TABLE `freelancer_profile`
ADD COLUMN `address` VARCHAR(255) DEFAULT NULL AFTER `location`,
ADD COLUMN `province` VARCHAR(100) DEFAULT NULL AFTER `address`,
ADD COLUMN `district` VARCHAR(100) DEFAULT NULL AFTER `province`,
ADD COLUMN `postal_code` VARCHAR(10) DEFAULT NULL AFTER `district`,
ADD COLUMN `latitude` DOUBLE DEFAULT NULL AFTER `postal_code`,
ADD COLUMN `longitude` DOUBLE DEFAULT NULL AFTER `latitude`;

-- Add location-related fields to job table if not already present
-- ALTER TABLE `job`
-- ADD COLUMN `province` VARCHAR(100) DEFAULT NULL AFTER `location`,
-- ADD COLUMN `district` VARCHAR(100) DEFAULT NULL AFTER `province`;

-- Create index for faster location-based searches
ALTER TABLE `job`
ADD INDEX `idx_location` (`location`),
ADD INDEX `idx_status` (`status`, `admin_status`),
ADD INDEX `idx_created` (`created_at`);

ALTER TABLE `freelancer_profile`
ADD INDEX `idx_province` (`province`),
ADD INDEX `idx_location_field` (`location`),
ADD INDEX `idx_user` (`user_id`);

ALTER TABLE `employer_profile`
ADD INDEX `idx_province_emp` (`province`),
ADD INDEX `idx_user_emp` (`user_id`);
