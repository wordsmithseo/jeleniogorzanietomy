<?php
/**
 * Diagnostic script to check and fix category column
 *
 * To run: access via browser at /wp-content/plugins/jg-interactive-map/check-category-column.php
 * OR run via WP-CLI: wp eval-file check-category-column.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Only allow admins to run this
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

echo "<h1>JG Interactive Map - Category Column Diagnostic</h1>\n";
echo "<pre>\n";

global $wpdb;
$table = $wpdb->prefix . 'jg_map_points';

echo "=== CHECKING DATABASE TABLE ===\n";
echo "Table name: $table\n\n";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if (!$table_exists) {
    echo "ERROR: Table $table does not exist!\n";
    exit;
}
echo "✓ Table exists\n\n";

// Show all columns
echo "=== CURRENT COLUMNS ===\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
foreach ($columns as $column) {
    echo "- {$column->Field} ({$column->Type})\n";
}
echo "\n";

// Check specifically for category column
echo "=== CHECKING CATEGORY COLUMN ===\n";
$category_column = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'category'");

if (empty($category_column)) {
    echo "❌ Category column NOT FOUND\n\n";
    echo "=== ATTEMPTING TO ADD CATEGORY COLUMN ===\n";

    $sql = "ALTER TABLE $table ADD COLUMN category varchar(100) DEFAULT NULL AFTER type";
    echo "SQL: $sql\n\n";

    $result = $wpdb->query($sql);

    if ($result === false) {
        echo "❌ FAILED to add column\n";
        echo "MySQL Error: " . $wpdb->last_error . "\n";
    } else {
        echo "✓ Column added successfully\n\n";

        // Verify it was added
        $verify = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'category'");
        if (!empty($verify)) {
            echo "✓ VERIFIED: Category column now exists\n";
            echo "Column details:\n";
            print_r($verify[0]);
        } else {
            echo "❌ VERIFICATION FAILED: Column still not found after adding\n";
        }
    }
} else {
    echo "✓ Category column EXISTS\n";
    echo "Column details:\n";
    print_r($category_column[0]);
    echo "\n";

    // Check sample data
    echo "\n=== CHECKING SAMPLE DATA ===\n";
    $sample = $wpdb->get_results("SELECT id, title, type, category FROM $table WHERE type='zgloszenie' ORDER BY id DESC LIMIT 5");

    if (empty($sample)) {
        echo "No reports (zgloszenie) found in database\n";
    } else {
        echo "Recent reports:\n";
        foreach ($sample as $row) {
            $cat_status = empty($row->category) ? '❌ NULL/EMPTY' : '✓ ' . $row->category;
            echo "ID {$row->id}: {$row->title} - Category: $cat_status\n";
        }
    }
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
echo "</pre>\n";
