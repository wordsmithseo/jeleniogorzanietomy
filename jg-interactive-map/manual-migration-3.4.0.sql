-- Manual migration script for JG Interactive Map v3.4.0
-- This adds case_id and resolved_delete_at columns for report tracking features
-- Run this SQL in your WordPress database (phpMyAdmin or similar)

-- IMPORTANT: Replace 'wp_' with your actual WordPress table prefix if different

-- Step 1: Add case_id column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'wp_jg_map_points';
SET @columnname = 'case_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column case_id already exists' AS message;",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN case_id varchar(20) DEFAULT NULL AFTER id;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 2: Add resolved_delete_at column if it doesn't exist
SET @columnname = 'resolved_delete_at';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column resolved_delete_at already exists' AS message;",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN resolved_delete_at datetime DEFAULT NULL AFTER report_status;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 3: Add index on case_id if it doesn't exist
SET @indexname = 'case_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  "SELECT 'Index case_id already exists' AS message;",
  CONCAT("ALTER TABLE ", @tablename, " ADD KEY case_id (case_id);")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Step 4: Generate case_id for all existing reports (zgłoszenie) that don't have one
UPDATE wp_jg_map_points
SET case_id = CONCAT('ZGL-', LPAD(id, 6, '0'))
WHERE type = 'zgloszenie' AND (case_id IS NULL OR case_id = '');

-- Step 5: Verify the changes
SELECT
    'Migration completed!' AS status,
    COUNT(*) AS total_reports,
    SUM(CASE WHEN case_id IS NOT NULL THEN 1 ELSE 0 END) AS reports_with_case_id,
    SUM(CASE WHEN resolved_delete_at IS NOT NULL THEN 1 ELSE 0 END) AS reports_scheduled_for_deletion
FROM wp_jg_map_points
WHERE type = 'zgloszenie';

-- If you see this result, the migration was successful!
