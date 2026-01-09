<?php
/**
 * Manual Migration Script for JG Interactive Map v3.4.0
 *
 * This script forcefully runs the database migration to add:
 * - case_id column for report tracking
 * - resolved_delete_at column for auto-deletion
 *
 * USAGE:
 * 1. Upload this file to: wp-content/plugins/jg-interactive-map/
 * 2. Visit: https://yoursite.com/wp-content/plugins/jg-interactive-map/force-migration.php
 * 3. Delete this file after migration is complete (for security)
 */

// Load WordPress
// Try different possible paths to wp-load.php
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',           // Standard: plugins/jg-interactive-map -> wp-load.php
    __DIR__ . '/../../../../wp-load.php',        // Alternative structure
    __DIR__ . '/../../../../../wp-load.php',     // Subdomain structure
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Error: Could not find wp-load.php. Please check the installation path.');
}

// Security check - only admins can run this
if (!current_user_can('manage_options')) {
    die('Access denied. Only administrators can run this script.');
}

echo '<html><head><title>JG Map Migration</title><style>
body { font-family: monospace; background: #1e1e1e; color: #00ff00; padding: 20px; }
h1 { color: #00ff00; }
.success { color: #00ff00; }
.error { color: #ff0000; }
.info { color: #ffff00; }
pre { background: #000; padding: 10px; border: 1px solid #00ff00; }
</style></head><body>';

echo '<h1>🔧 JG Interactive Map - Manual Migration v3.4.0</h1>';
echo '<p class="info">Starting migration process...</p>';

global $wpdb;
$table = $wpdb->prefix . 'jg_map_points';

// Step 1: Delete cached schema version to force migration
echo '<p class="info">Step 1: Resetting schema version cache...</p>';
delete_option('jg_map_schema_version');
echo '<p class="success">✓ Schema version cache cleared</p>';

// Step 2: Check if columns exist
echo '<p class="info">Step 2: Checking database structure...</p>';

$case_id_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'case_id'");
$resolved_delete_at_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'resolved_delete_at'");

echo '<pre>';
echo 'case_id column exists: ' . (empty($case_id_exists) ? 'NO' : 'YES') . "\n";
echo 'resolved_delete_at column exists: ' . (empty($resolved_delete_at_exists) ? 'NO' : 'YES') . "\n";
echo '</pre>';

// Step 3: Add case_id column if missing
if (empty($case_id_exists)) {
    echo '<p class="info">Step 3a: Adding case_id column...</p>';
    $result = $wpdb->query("ALTER TABLE `$table` ADD COLUMN case_id varchar(20) DEFAULT NULL AFTER id");

    if ($result === false) {
        echo '<p class="error">✗ Error adding case_id column: ' . $wpdb->last_error . '</p>';
    } else {
        echo '<p class="success">✓ case_id column added successfully</p>';

        // Generate case IDs for existing reports
        echo '<p class="info">Generating case IDs for existing reports...</p>';
        $updated = $wpdb->query("
            UPDATE `$table`
            SET case_id = CONCAT('ZGL-', LPAD(id, 6, '0'))
            WHERE type = 'zgloszenie' AND (case_id IS NULL OR case_id = '')
        ");
        echo '<p class="success">✓ Generated case IDs for ' . $updated . ' reports</p>';
    }
} else {
    echo '<p class="success">✓ case_id column already exists</p>';
}

// Step 4: Add resolved_delete_at column if missing
if (empty($resolved_delete_at_exists)) {
    echo '<p class="info">Step 3b: Adding resolved_delete_at column...</p>';
    $result = $wpdb->query("ALTER TABLE `$table` ADD COLUMN resolved_delete_at datetime DEFAULT NULL AFTER report_status");

    if ($result === false) {
        echo '<p class="error">✗ Error adding resolved_delete_at column: ' . $wpdb->last_error . '</p>';
    } else {
        echo '<p class="success">✓ resolved_delete_at column added successfully</p>';
    }
} else {
    echo '<p class="success">✓ resolved_delete_at column already exists</p>';
}

// Step 5: Add index on case_id if missing
echo '<p class="info">Step 4: Adding index on case_id...</p>';
$index_exists = $wpdb->get_results("SHOW INDEX FROM `$table` WHERE Key_name = 'case_id'");
if (empty($index_exists)) {
    $result = $wpdb->query("ALTER TABLE `$table` ADD KEY case_id (case_id)");
    if ($result === false) {
        echo '<p class="error">✗ Error adding index: ' . $wpdb->last_error . '</p>';
    } else {
        echo '<p class="success">✓ Index added successfully</p>';
    }
} else {
    echo '<p class="success">✓ Index already exists</p>';
}

// Step 6: Run WordPress schema check
echo '<p class="info">Step 5: Running WordPress schema update...</p>';
if (class_exists('JG_Map_Database')) {
    JG_Map_Database::check_and_update_schema();
    echo '<p class="success">✓ Schema update completed</p>';
} else {
    echo '<p class="error">✗ JG_Map_Database class not found. Make sure the plugin is activated.</p>';
}

// Step 7: Verify results
echo '<p class="info">Step 6: Verifying migration...</p>';
$stats = $wpdb->get_row("
    SELECT
        COUNT(*) AS total_reports,
        SUM(CASE WHEN case_id IS NOT NULL THEN 1 ELSE 0 END) AS reports_with_case_id,
        SUM(CASE WHEN resolved_delete_at IS NOT NULL THEN 1 ELSE 0 END) AS reports_scheduled_for_deletion
    FROM `$table`
    WHERE type = 'zgloszenie'
");

echo '<pre>';
echo 'Total reports (zgłoszenia): ' . $stats->total_reports . "\n";
echo 'Reports with case_id: ' . $stats->reports_with_case_id . "\n";
echo 'Reports scheduled for deletion: ' . $stats->reports_scheduled_for_deletion . "\n";
echo '</pre>';

// Step 8: Show sample data
echo '<p class="info">Sample reports with case IDs:</p>';
$samples = $wpdb->get_results("
    SELECT id, case_id, title, report_status, resolved_delete_at
    FROM `$table`
    WHERE type = 'zgloszenie'
    ORDER BY id DESC
    LIMIT 5
", ARRAY_A);

if (!empty($samples)) {
    echo '<pre>';
    foreach ($samples as $sample) {
        echo sprintf(
            "ID: %d | Case ID: %s | Status: %s | Title: %s\n",
            $sample['id'],
            $sample['case_id'] ?: 'NULL',
            $sample['report_status'],
            substr($sample['title'], 0, 50)
        );
    }
    echo '</pre>';
}

// Final status
echo '<hr>';
if ($stats->total_reports > 0 && $stats->reports_with_case_id == $stats->total_reports) {
    echo '<h2 class="success">✓ MIGRATION COMPLETED SUCCESSFULLY!</h2>';
    echo '<p>All features should now work:</p>';
    echo '<ul>';
    echo '<li>✓ Case ID tracking (ZGL-XXXXXX)</li>';
    echo '<li>✓ Auto-delete timer for resolved reports</li>';
    echo '<li>✓ Post-submission modal</li>';
    echo '<li>✓ Badge displays in UI</li>';
    echo '</ul>';
    echo '<p class="info">⚠️ IMPORTANT: Delete this file (force-migration.php) for security!</p>';
    echo '<p>Clear your browser cache (Ctrl+Shift+R) and test the features.</p>';
} else {
    echo '<h2 class="error">⚠ MIGRATION MAY HAVE ISSUES</h2>';
    echo '<p>Please check the error messages above.</p>';
}

echo '</body></html>';
