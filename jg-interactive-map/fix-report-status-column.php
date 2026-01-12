<?php
/**
 * FIX: Increase report_status column size from varchar(20) to varchar(50)
 * Run this once: https://jeleniogorzanietomy.pl/wp-content/plugins/jg-interactive-map/fix-report-status-column.php
 */

require_once('../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== FIXING REPORT_STATUS COLUMN SIZE ===\n\n";

global $wpdb;
$table = $wpdb->prefix . 'jg_map_points';

// 1. Check current column size
echo "1. Checking current column size...\n";
$column_info = $wpdb->get_row("SHOW COLUMNS FROM `$table` LIKE 'report_status'");
echo "   Current type: " . $column_info->Type . "\n";

if ($column_info->Type === 'varchar(50)') {
    echo "   ✅ Column is already correct size!\n";
    echo "   Nothing to do.\n\n";
    exit;
}

// 2. Execute ALTER TABLE
echo "\n2. Executing ALTER TABLE...\n";
$sql = "ALTER TABLE `$table` MODIFY COLUMN report_status varchar(50) DEFAULT 'added'";
echo "   SQL: $sql\n";

$result = $wpdb->query($sql);

if ($result === false) {
    echo "   ❌ FAILED!\n";
    echo "   Error: " . $wpdb->last_error . "\n\n";
    exit;
}

echo "   ✅ Success!\n";

// 3. Verify the change
echo "\n3. Verifying change...\n";
$column_info_after = $wpdb->get_row("SHOW COLUMNS FROM `$table` LIKE 'report_status'");
echo "   New type: " . $column_info_after->Type . "\n";

if ($column_info_after->Type === 'varchar(50)') {
    echo "   ✅ Column successfully updated!\n\n";
    echo "=== FIX COMPLETED ===\n";
    echo "\nYou can now use status 'needs_better_documentation' (27 characters).\n";
    echo "Clear browser cache (Ctrl+Shift+R) and test changing status on map.\n";
} else {
    echo "   ❌ Verification failed!\n";
    echo "   Something went wrong.\n";
}
