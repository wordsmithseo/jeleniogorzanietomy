<?php
/**
 * Diagnostic Script for JG Interactive Map v3.4.0
 * Checks if migration was successful and all features are working
 *
 * USAGE:
 * 1. Upload to: wp-content/plugins/jg-interactive-map/
 * 2. Visit: https://yoursite.com/wp-content/plugins/jg-interactive-map/check-status.php
 * 3. Delete after checking (for security)
 */

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
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
    die('Error: Could not find wp-load.php');
}

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. Only administrators can access this diagnostic.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>JG Map - Diagnostic Check</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #00ff00; padding: 20px; line-height: 1.6; }
        h1 { color: #00ff00; border-bottom: 2px solid #00ff00; padding-bottom: 10px; }
        h2 { color: #ffff00; margin-top: 30px; }
        .success { color: #00ff00; }
        .error { color: #ff0000; }
        .warning { color: #ffff00; }
        .info { color: #00ffff; }
        pre { background: #000; padding: 15px; border: 1px solid #00ff00; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        th, td { border: 1px solid #00ff00; padding: 8px; text-align: left; }
        th { background: #003300; color: #00ff00; font-weight: bold; }
        .status-ok { color: #00ff00; font-weight: bold; }
        .status-fail { color: #ff0000; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 JG Interactive Map - Diagnostic Check</h1>
    <p class="info">Running comprehensive system check...</p>

<?php

global $wpdb;
$table = $wpdb->prefix . 'jg_map_points';
$errors = [];
$warnings = [];

// Check 1: Plugin Version
echo '<h2>1. Plugin Version</h2>';
$plugin_version = defined('JG_MAP_VERSION') ? JG_MAP_VERSION : 'UNDEFINED';
echo '<p>Current plugin version: <strong>' . esc_html($plugin_version) . '</strong></p>';
if ($plugin_version === '3.4.0') {
    echo '<p class="success">✓ Version is correct (3.4.0)</p>';
} else {
    echo '<p class="error">✗ Expected version 3.4.0, got: ' . esc_html($plugin_version) . '</p>';
    $errors[] = 'Plugin version mismatch';
}

// Check 2: Schema Version
echo '<h2>2. Database Schema Version</h2>';
$schema_version = get_option('jg_map_schema_version', 'NOT SET');
echo '<p>Cached schema version: <strong>' . esc_html($schema_version) . '</strong></p>';
if ($schema_version === '3.5.0') {
    echo '<p class="success">✓ Schema version is up to date (3.5.0)</p>';
} else {
    echo '<p class="error">✗ Expected schema version 3.5.0, got: ' . esc_html($schema_version) . '</p>';
    $errors[] = 'Schema version outdated';
}

// Check 3: Database Columns
echo '<h2>3. Database Structure</h2>';
$columns = $wpdb->get_results("SHOW COLUMNS FROM `$table`");
$column_names = array_column($columns, 'Field');

$required_columns = ['case_id', 'resolved_delete_at'];
echo '<table>';
echo '<tr><th>Column</th><th>Status</th><th>Type</th></tr>';

foreach ($required_columns as $col) {
    $exists = in_array($col, $column_names);
    $col_info = null;
    if ($exists) {
        foreach ($columns as $c) {
            if ($c->Field === $col) {
                $col_info = $c;
                break;
            }
        }
    }

    echo '<tr>';
    echo '<td>' . esc_html($col) . '</td>';
    if ($exists) {
        echo '<td class="status-ok">✓ EXISTS</td>';
        echo '<td>' . esc_html($col_info->Type) . '</td>';
    } else {
        echo '<td class="status-fail">✗ MISSING</td>';
        echo '<td>-</td>';
        $errors[] = "Column '$col' is missing";
    }
    echo '</tr>';
}
echo '</table>';

// Check 4: Sample Data
echo '<h2>4. Sample Report Data</h2>';
$sample_reports = $wpdb->get_results("
    SELECT id, case_id, title, report_status, resolved_delete_at, created_at
    FROM `$table`
    WHERE type = 'zgloszenie'
    ORDER BY id DESC
    LIMIT 5
");

if (empty($sample_reports)) {
    echo '<p class="warning">⚠ No reports found in database</p>';
    $warnings[] = 'No reports to test with';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Case ID</th><th>Title</th><th>Status</th><th>Delete At</th></tr>';
    foreach ($sample_reports as $report) {
        echo '<tr>';
        echo '<td>' . esc_html($report->id) . '</td>';
        echo '<td>' . ($report->case_id ? '<span class="status-ok">' . esc_html($report->case_id) . '</span>' : '<span class="status-fail">NULL</span>') . '</td>';
        echo '<td>' . esc_html(substr($report->title, 0, 40)) . '</td>';
        echo '<td>' . esc_html($report->report_status) . '</td>';
        echo '<td>' . ($report->resolved_delete_at ? esc_html($report->resolved_delete_at) : '-') . '</td>';
        echo '</tr>';

        if (empty($report->case_id)) {
            $errors[] = "Report ID {$report->id} has no case_id";
        }
    }
    echo '</table>';
}

// Check 5: Backend Code (AJAX Handler)
echo '<h2>5. Backend Code Check</h2>';
if (class_exists('JG_Map_Ajax_Handlers')) {
    echo '<p class="success">✓ JG_Map_Ajax_Handlers class exists</p>';

    // Check if the submit_point method exists
    if (method_exists('JG_Map_Ajax_Handlers', 'submit_point')) {
        echo '<p class="success">✓ submit_point method exists</p>';
    } else {
        echo '<p class="error">✗ submit_point method not found</p>';
        $errors[] = 'AJAX handler method missing';
    }
} else {
    echo '<p class="error">✗ JG_Map_Ajax_Handlers class not found</p>';
    $errors[] = 'AJAX handler class missing';
}

// Check 6: File Versions
echo '<h2>6. Asset File Check</h2>';
$files_to_check = [
    'assets/js/jg-map.js' => ['show_report_info_modal', 'jg-case-id-badge'],
    'assets/css/jg-map.css' => ['jg-case-id-badge', 'needs_better_documentation'],
    'includes/class-ajax-handlers.php' => ['show_report_info_modal', 'needs_better_documentation'],
    'includes/class-maintenance.php' => ['clean_expired_resolved_reports'],
];

echo '<table>';
echo '<tr><th>File</th><th>Keywords Found</th><th>Status</th></tr>';

foreach ($files_to_check as $file => $keywords) {
    $filepath = __DIR__ . '/' . $file;
    echo '<tr>';
    echo '<td>' . esc_html($file) . '</td>';

    if (file_exists($filepath)) {
        $content = file_get_contents($filepath);
        $found = [];
        $missing = [];

        foreach ($keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $found[] = $keyword;
            } else {
                $missing[] = $keyword;
            }
        }

        echo '<td>';
        if (!empty($found)) {
            echo '<span class="success">Found: ' . implode(', ', $found) . '</span>';
        }
        if (!empty($missing)) {
            echo '<br><span class="error">Missing: ' . implode(', ', $missing) . '</span>';
        }
        echo '</td>';

        if (empty($missing)) {
            echo '<td class="status-ok">✓ OK</td>';
        } else {
            echo '<td class="status-fail">✗ OUTDATED</td>';
            $errors[] = "File '$file' is missing keywords: " . implode(', ', $missing);
        }
    } else {
        echo '<td>-</td>';
        echo '<td class="status-fail">✗ NOT FOUND</td>';
        $errors[] = "File '$file' not found";
    }
    echo '</tr>';
}
echo '</table>';

// Check 7: WordPress Cache
echo '<h2>7. Cache Check</h2>';
echo '<p>WordPress object cache: ' . (wp_using_ext_object_cache() ? '<span class="warning">ACTIVE (may cause issues)</span>' : '<span class="success">Standard</span>') . '</p>';

// Final Summary
echo '<hr>';
echo '<h2>📊 Summary</h2>';

if (empty($errors) && empty($warnings)) {
    echo '<h3 class="success">✓ ALL CHECKS PASSED!</h3>';
    echo '<p class="success">The migration was successful and all features should work.</p>';
    echo '<p>If you still don\'t see the features:</p>';
    echo '<ul>';
    echo '<li>Clear your browser cache completely (Ctrl+Shift+Delete)</li>';
    echo '<li>Try incognito/private browsing mode</li>';
    echo '<li>Check browser console for JavaScript errors (F12)</li>';
    echo '<li>If using CDN/caching plugin, purge all caches</li>';
    echo '</ul>';
} else {
    if (!empty($errors)) {
        echo '<h3 class="error">✗ ' . count($errors) . ' ERROR(S) FOUND:</h3>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li class="error">' . esc_html($error) . '</li>';
        }
        echo '</ul>';
    }

    if (!empty($warnings)) {
        echo '<h3 class="warning">⚠ ' . count($warnings) . ' WARNING(S):</h3>';
        echo '<ul>';
        foreach ($warnings as $warning) {
            echo '<li class="warning">' . esc_html($warning) . '</li>';
        }
        echo '</ul>';
    }

    echo '<p class="info">Please fix the errors above and run the diagnostic again.</p>';
}

echo '<hr>';
echo '<p class="warning">⚠️ IMPORTANT: Delete this file (check-status.php) after diagnosis for security!</p>';

?>
</body>
</html>
