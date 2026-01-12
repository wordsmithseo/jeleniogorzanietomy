<?php
/**
 * Quick diagnostic: Test status save for needs_better_documentation
 */

// Load WordPress
require_once('../../../wp-load.php');

header('Content-Type: text/plain; charset=utf-8');

echo "=== STATUS SAVE DIAGNOSTIC ===\n\n";

// 1. Check database column size
global $wpdb;
$table = $wpdb->prefix . 'jg_map_points';
$column_info = $wpdb->get_row("SHOW COLUMNS FROM `$table` LIKE 'report_status'");

echo "1. Database column info:\n";
echo "   Type: " . $column_info->Type . "\n";
echo "   Expected: varchar(50)\n";
echo "   Status: " . ($column_info->Type === 'varchar(50)' ? '✅ CORRECT' : '❌ WRONG') . "\n\n";

// 2. Get a test zgłoszenie point
$test_point = $wpdb->get_row("SELECT id, title, report_status FROM `$table` WHERE type = 'zgloszenie' LIMIT 1");

if (!$test_point) {
    echo "2. ❌ No zgłoszenie points found in database\n";
    exit;
}

echo "2. Test point found:\n";
echo "   ID: " . $test_point->id . "\n";
echo "   Title: " . $test_point->title . "\n";
echo "   Current status: " . $test_point->report_status . "\n\n";

// 3. Test direct SQL UPDATE
$old_status = $test_point->report_status;
$test_status = 'needs_better_documentation';

echo "3. Testing direct SQL UPDATE:\n";
$result = $wpdb->update(
    $table,
    array('report_status' => $test_status),
    array('id' => $test_point->id),
    array('%s'),
    array('%d')
);

if ($result === false) {
    echo "   ❌ SQL UPDATE failed\n";
    echo "   Error: " . $wpdb->last_error . "\n\n";
} else {
    echo "   ✅ SQL UPDATE successful (affected rows: $result)\n";

    // Verify the update
    $verify = $wpdb->get_var($wpdb->prepare("SELECT report_status FROM `$table` WHERE id = %d", $test_point->id));
    echo "   Verified status in DB: " . $verify . "\n";
    echo "   Length: " . strlen($verify) . " characters\n";
    echo "   Status: " . ($verify === $test_status ? '✅ CORRECT' : '❌ TRUNCATED') . "\n\n";

    // Restore original status
    $wpdb->update($table, array('report_status' => $old_status), array('id' => $test_point->id));
    echo "   (Restored original status: $old_status)\n\n";
}

// 4. Check if admin_change_status handler exists and is registered
echo "4. Check AJAX handler registration:\n";
$ajax_action = 'wp_ajax_jg_admin_change_status';
echo "   Action hook: $ajax_action\n";
echo "   Has handler: " . (has_action($ajax_action) ? '✅ YES' : '❌ NO') . "\n\n";

// 5. Check validation array in handler
echo "5. Check status validation:\n";
$valid_statuses = array('added', 'needs_better_documentation', 'reported', 'resolved');
echo "   Valid statuses: " . implode(', ', $valid_statuses) . "\n";
echo "   'needs_better_documentation' in array: " . (in_array('needs_better_documentation', $valid_statuses) ? '✅ YES' : '❌ NO') . "\n\n";

echo "=== END DIAGNOSTIC ===\n";
