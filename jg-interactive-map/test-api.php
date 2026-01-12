<?php
/**
 * API Test Script - Tests if backend sends case_id and resolved_delete_at
 *
 * USAGE:
 * 1. Upload to: wp-content/plugins/jg-interactive-map/
 * 2. Visit: https://yoursite.com/wp-content/plugins/jg-interactive-map/test-api.php
 * 3. Delete after testing (for security)
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
    die('Access denied. Only administrators can access this test.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>JG Map - API Test</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #00ff00; padding: 20px; line-height: 1.6; }
        h1 { color: #00ff00; border-bottom: 2px solid #00ff00; padding-bottom: 10px; }
        h2 { color: #ffff00; margin-top: 30px; }
        .success { color: #00ff00; font-weight: bold; }
        .error { color: #ff0000; font-weight: bold; }
        .warning { color: #ffff00; }
        .info { color: #00ffff; }
        pre { background: #000; padding: 15px; border: 1px solid #00ff00; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        th, td { border: 1px solid #00ff00; padding: 8px; text-align: left; }
        th { background: #003300; color: #00ff00; font-weight: bold; }
        .highlight { background: #ffff00; color: #000; padding: 2px 4px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🧪 JG Interactive Map - API Test</h1>
    <p class="info">Testing if backend API returns case_id and resolved_delete_at for reports...</p>

<?php

// Simulate the AJAX handler
require_once(__DIR__ . '/includes/class-ajax-handlers.php');
require_once(__DIR__ . '/includes/class-database.php');

$ajax_handler = JG_Map_Ajax_Handlers::get_instance();

// Call the get_points method directly
ob_start();
$ajax_handler->get_points();
$response = ob_get_clean();

$data = json_decode($response, true);

echo '<h2>1. API Response Status</h2>';
if ($data && isset($data['success']) && $data['success']) {
    echo '<p class="success">✓ API call successful</p>';
    echo '<p>Total points returned: <strong>' . count($data['data']) . '</strong></p>';
} else {
    echo '<p class="error">✗ API call failed</p>';
    echo '<pre>' . htmlspecialchars($response) . '</pre>';
    die('</body></html>');
}

// Find reports (zgłoszenie type)
$reports = array_filter($data['data'], function($point) {
    return $point['type'] === 'zgloszenie';
});

echo '<h2>2. Reports Found</h2>';
echo '<p>Found <strong>' . count($reports) . '</strong> reports (type: zgloszenie)</p>';

if (empty($reports)) {
    echo '<p class="warning">⚠ No reports found in database. Add a test report first.</p>';
    die('</body></html>');
}

// Show first 3 reports with detailed data
echo '<h2>3. Sample Report Data (First 3)</h2>';

$sample_reports = array_slice($reports, 0, 3);
$report_num = 1;

foreach ($sample_reports as $report) {
    echo '<h3 style="color: #00ffff;">Report #' . $report_num . ' (ID: ' . $report['id'] . ')</h3>';

    echo '<table>';
    echo '<tr><th>Field</th><th>Value</th><th>Status</th></tr>';

    // Check case_id
    echo '<tr>';
    echo '<td><span class="highlight">case_id</span></td>';
    if (isset($report['case_id']) && !empty($report['case_id'])) {
        echo '<td class="success">' . htmlspecialchars($report['case_id']) . '</td>';
        echo '<td class="success">✓ PRESENT</td>';
    } else {
        echo '<td class="error">NULL or MISSING</td>';
        echo '<td class="error">✗ MISSING</td>';
    }
    echo '</tr>';

    // Check resolved_delete_at
    echo '<tr>';
    echo '<td><span class="highlight">resolved_delete_at</span></td>';
    if (isset($report['resolved_delete_at'])) {
        if (!empty($report['resolved_delete_at'])) {
            echo '<td class="success">' . htmlspecialchars($report['resolved_delete_at']) . '</td>';
            echo '<td class="success">✓ SET</td>';
        } else {
            echo '<td>NULL (expected for non-resolved)</td>';
            echo '<td class="info">✓ OK (not resolved)</td>';
        }
    } else {
        echo '<td class="error">MISSING FROM API</td>';
        echo '<td class="error">✗ MISSING</td>';
    }
    echo '</tr>';

    // Other relevant fields
    echo '<tr><td>title</td><td>' . htmlspecialchars(substr($report['title'], 0, 50)) . '</td><td>-</td></tr>';
    echo '<tr><td>type</td><td>' . htmlspecialchars($report['type']) . '</td><td>-</td></tr>';
    echo '<tr><td>report_status</td><td>' . htmlspecialchars($report['report_status']) . '</td><td>-</td></tr>';
    echo '<tr><td>report_status_label</td><td>' . htmlspecialchars($report['report_status_label']) . '</td><td>-</td></tr>';

    echo '</table>';

    $report_num++;
}

// Check if ANY report has case_id
$has_case_id = false;
$has_resolved_delete_at_field = false;

foreach ($reports as $report) {
    if (isset($report['case_id']) && !empty($report['case_id'])) {
        $has_case_id = true;
    }
    if (isset($report['resolved_delete_at'])) {
        $has_resolved_delete_at_field = true;
    }
}

echo '<h2>4. Overall Results</h2>';

if ($has_case_id && $has_resolved_delete_at_field) {
    echo '<p class="success">✓✓✓ SUCCESS! Backend API is sending both fields correctly!</p>';
    echo '<p>If you still don\'t see the features on the map:</p>';
    echo '<ul>';
    echo '<li>Check browser console for JavaScript errors (press F12)</li>';
    echo '<li>Verify JavaScript file version: <code>assets/js/jg-map.js</code></li>';
    echo '<li>Verify CSS file version: <code>assets/css/jg-map.css</code></li>';
    echo '<li>Clear all caches (browser + WordPress + CDN if using)</li>';
    echo '<li>Try hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)</li>';
    echo '</ul>';
} else {
    if (!$has_case_id) {
        echo '<p class="error">✗ case_id is NOT being sent by backend API</p>';
        echo '<p>Possible causes:</p>';
        echo '<ul>';
        echo '<li>SQL query in class-database.php not updated</li>';
        echo '<li>Old PHP files cached (check opcache, server cache)</li>';
        echo '<li>Database migration didn\'t run (case_id column missing)</li>';
        echo '</ul>';
    }

    if (!$has_resolved_delete_at_field) {
        echo '<p class="error">✗ resolved_delete_at is NOT in API response</p>';
        echo '<p>Same possible causes as above.</p>';
    }
}

echo '<h2>5. Raw JSON (First Report)</h2>';
echo '<pre>' . htmlspecialchars(json_encode($sample_reports[0], JSON_PRETTY_PRINT)) . '</pre>';

echo '<hr>';
echo '<p class="warning">⚠️ Delete this file (test-api.php) after testing for security!</p>';

?>
</body>
</html>
