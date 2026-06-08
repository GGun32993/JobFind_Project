-- JobFind table-name repair for case-sensitive MySQL/MariaDB hosts.
-- Run this in phpMyAdmin on the target database after taking a backup.
--
-- This script renames an old lowercase table only when the Title_Case table
-- is missing. If both old and new names exist, it leaves both untouched and
-- shows them in the final report so you can compare row counts before cleanup.

SET SESSION group_concat_max_len = 65535;

CREATE TEMPORARY TABLE IF NOT EXISTS jobfind_table_name_map (
    old_name VARCHAR(64) NOT NULL,
    new_name VARCHAR(64) NOT NULL,
    PRIMARY KEY (old_name, new_name)
);

TRUNCATE TABLE jobfind_table_name_map;

INSERT INTO jobfind_table_name_map (old_name, new_name) VALUES
('categories', 'Categories'),
('category_seed_runs', 'Category_Seed_Runs'),
('chat_messages', 'Chat_Messages'),
('employer_profile', 'Employer_Profile'),
('employer_rating', 'Employer_Rating'),
('employer_review', 'Employer_Review'),
('freelancer_profile', 'Freelancer_Profile'),
('freelancer_rating', 'Freelancer_Rating'),
('freelancer_review', 'Freelancer_Review'),
('job', 'Job'),
('job_application', 'Job_Application'),
('job_images', 'Job_Images'),
('job_subcategories', 'Job_Subcategories'),
('like_employer', 'Like_Employer'),
('resume', 'Resume'),
('saved_freelancers', 'Saved_Freelancers'),
('users', 'Users');

SELECT GROUP_CONCAT(
    CONCAT(
        '`', REPLACE(DATABASE(), '`', '``'), '`.`', REPLACE(m.old_name, '`', '``'),
        '` TO `', REPLACE(DATABASE(), '`', '``'), '`.`', REPLACE(m.new_name, '`', '``'), '`'
    )
    ORDER BY m.new_name
    SEPARATOR ', '
) INTO @jobfind_rename_pairs
FROM jobfind_table_name_map m
JOIN information_schema.TABLES old_obj
  ON old_obj.TABLE_SCHEMA = DATABASE()
 AND old_obj.TABLE_NAME = m.old_name
 AND old_obj.TABLE_TYPE = 'BASE TABLE'
LEFT JOIN information_schema.TABLES new_obj
  ON new_obj.TABLE_SCHEMA = DATABASE()
 AND new_obj.TABLE_NAME = m.new_name
 AND new_obj.TABLE_TYPE = 'BASE TABLE'
WHERE new_obj.TABLE_NAME IS NULL;

SET @jobfind_rename_sql = IF(
    @jobfind_rename_pairs IS NULL OR @jobfind_rename_pairs = '',
    'SELECT ''No table renames needed'' AS message',
    CONCAT('RENAME TABLE ', @jobfind_rename_pairs)
);

PREPARE jobfind_rename_stmt FROM @jobfind_rename_sql;
EXECUTE jobfind_rename_stmt;
DEALLOCATE PREPARE jobfind_rename_stmt;

-- Final report. Expected state is that every new_object_type is BASE TABLE.
-- If old_object_type is also BASE TABLE, you still have a duplicate old table.
SELECT
    m.old_name,
    m.new_name,
    old_obj.TABLE_TYPE AS old_object_type,
    new_obj.TABLE_TYPE AS new_object_type,
    COALESCE(old_obj.TABLE_ROWS, 0) AS old_rows_estimate,
    COALESCE(new_obj.TABLE_ROWS, 0) AS new_rows_estimate
FROM jobfind_table_name_map m
LEFT JOIN information_schema.TABLES old_obj
  ON old_obj.TABLE_SCHEMA = DATABASE()
 AND old_obj.TABLE_NAME = m.old_name
LEFT JOIN information_schema.TABLES new_obj
  ON new_obj.TABLE_SCHEMA = DATABASE()
 AND new_obj.TABLE_NAME = m.new_name
ORDER BY m.new_name;

DROP TEMPORARY TABLE IF EXISTS jobfind_table_name_map;
