<?php
/**
 * Debug script to check category column and data
 */

// Load WordPress
require_once('../../../wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'jg_map_points';

echo "<h2>Checking category column...</h2>";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
echo "<p><strong>Table exists:</strong> " . ($table_exists ? "YES" : "NO") . "</p>";

if ($table_exists) {
    // Check column schema
    echo "<h3>Column schema:</h3>";
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
    echo "<pre>";
    foreach ($columns as $col) {
        if ($col->Field === 'category' || $col->Field === 'type') {
            print_r($col);
        }
    }
    echo "</pre>";

    // Check if category column exists
    $category_col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'category'");
    echo "<p><strong>Category column exists:</strong> " . (!empty($category_col) ? "YES" : "NO") . "</p>";

    // Get recent reports with their categories
    echo "<h3>Recent reports (last 5):</h3>";
    $reports = $wpdb->get_results(
        "SELECT id, title, type, category, status, created_at
         FROM $table
         WHERE type = 'zgloszenie'
         ORDER BY created_at DESC
         LIMIT 5",
        ARRAY_A
    );

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Category</th><th>Status</th><th>Created</th></tr>";
    foreach ($reports as $report) {
        echo "<tr>";
        echo "<td>" . $report['id'] . "</td>";
        echo "<td>" . htmlspecialchars($report['title']) . "</td>";
        echo "<td>" . $report['type'] . "</td>";
        echo "<td>" . ($report['category'] ?: '<em>NULL</em>') . "</td>";
        echo "<td>" . $report['status'] . "</td>";
        echo "<td>" . $report['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Try to manually update schema (only if column doesn't exist)
    echo "<h3>Manual schema update:</h3>";
    if (empty($category_col)) {
        $update_result = $wpdb->query("ALTER TABLE $table ADD COLUMN category varchar(100) DEFAULT NULL AFTER type");
        if ($update_result === false) {
            echo "<p style='color:red'><strong>Update failed:</strong> " . $wpdb->last_error . "</p>";
        } else {
            echo "<p style='color:green'><strong>Update successful!</strong> Column added.</p>";
        }
    } else {
        echo "<p><strong>Column already exists, no update needed.</strong></p>";
    }
}
