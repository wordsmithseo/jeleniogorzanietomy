<?php
/**
 * Direct database query to check category column value
 * Run this by visiting: https://jeleniogorzanietomy.pl/wp-content/plugins/jg-interactive-map/check-category-in-db.php
 */

// Load WordPress
require_once('../../../wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'jg_map_points';

// Query for all zgłoszenia with their category
$results = $wpdb->get_results(
    "SELECT id, title, type, category, created_at FROM $table WHERE type = 'zgloszenie' ORDER BY id DESC LIMIT 10",
    ARRAY_A
);

echo "<h2>Direct Database Query - Last 10 Zgłoszenia</h2>";
echo "<pre>";
echo "Table: $table\n\n";

if ($wpdb->last_error) {
    echo "SQL ERROR: " . $wpdb->last_error . "\n";
}

if (empty($results)) {
    echo "No zgłoszenia found in database.\n";
} else {
    foreach ($results as $row) {
        echo "Point #" . $row['id'] . ":\n";
        echo "  Title: " . $row['title'] . "\n";
        echo "  Type: " . $row['type'] . "\n";
        echo "  Category: " . ($row['category'] ?? 'NULL') . "\n";
        echo "  Created: " . $row['created_at'] . "\n";
        echo "\n";
    }
}

// Also check the exact columns that exist in the table
echo "\n=== TABLE STRUCTURE ===\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
foreach ($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . " - " . $col['Null'] . " - " . $col['Default'] . "\n";
}

echo "</pre>";
?>
