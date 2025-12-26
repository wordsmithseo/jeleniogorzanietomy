<?php
/**
 * Timezone Migration Script
 *
 * This script fixes old database entries that were saved with local WordPress time
 * instead of GMT/UTC. Run this ONCE after deploying the timezone fixes.
 *
 * IMPORTANT: This script should be run via WordPress admin or WP-CLI, NOT directly.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('Direct access not permitted');
}

function jg_map_migrate_timezone_fix() {
    global $wpdb;

    // Get WordPress timezone offset in seconds
    $timezone_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

    if ($timezone_offset == 0) {
        return array('success' => true, 'message' => 'No timezone offset - database already in UTC');
    }

    error_log('[JG MAP MIGRATION] Starting timezone migration. Offset: ' . $timezone_offset . ' seconds');

    $results = array(
        'points' => 0,
        'history' => 0,
        'reports' => 0,
        'votes' => 0,
        'activity_log' => 0
    );

    // Get table names
    $points_table = $wpdb->prefix . 'jg_map_points';
    $history_table = $wpdb->prefix . 'jg_map_history';
    $reports_table = $wpdb->prefix . 'jg_map_reports';
    $votes_table = $wpdb->prefix . 'jg_map_votes';
    $activity_table = $wpdb->prefix . 'jg_map_activity_log';

    // Fix points table
    $results['points'] = $wpdb->query($wpdb->prepare("
        UPDATE $points_table
        SET
            created_at = DATE_SUB(created_at, INTERVAL %d SECOND),
            updated_at = DATE_SUB(updated_at, INTERVAL %d SECOND),
            approved_at = IF(approved_at IS NOT NULL, DATE_SUB(approved_at, INTERVAL %d SECOND), NULL),
            deletion_requested_at = IF(deletion_requested_at IS NOT NULL, DATE_SUB(deletion_requested_at, INTERVAL %d SECOND), NULL)
        WHERE created_at IS NOT NULL
    ", $timezone_offset, $timezone_offset, $timezone_offset, $timezone_offset));

    // Fix history table
    $results['history'] = $wpdb->query($wpdb->prepare("
        UPDATE $history_table
        SET
            created_at = DATE_SUB(created_at, INTERVAL %d SECOND),
            resolved_at = IF(resolved_at IS NOT NULL, DATE_SUB(resolved_at, INTERVAL %d SECOND), NULL)
        WHERE created_at IS NOT NULL
    ", $timezone_offset, $timezone_offset));

    // Fix reports table
    $results['reports'] = $wpdb->query($wpdb->prepare("
        UPDATE $reports_table
        SET
            created_at = DATE_SUB(created_at, INTERVAL %d SECOND),
            resolved_at = IF(resolved_at IS NOT NULL, DATE_SUB(resolved_at, INTERVAL %d SECOND), NULL)
        WHERE created_at IS NOT NULL
    ", $timezone_offset, $timezone_offset));

    // Fix votes table
    $results['votes'] = $wpdb->query($wpdb->prepare("
        UPDATE $votes_table
        SET created_at = DATE_SUB(created_at, INTERVAL %d SECOND)
        WHERE created_at IS NOT NULL
    ", $timezone_offset));

    // Fix activity log table
    $results['activity_log'] = $wpdb->query($wpdb->prepare("
        UPDATE $activity_table
        SET created_at = DATE_SUB(created_at, INTERVAL %d SECOND)
        WHERE created_at IS NOT NULL
    ", $timezone_offset));

    error_log('[JG MAP MIGRATION] Completed timezone migration: ' . print_r($results, true));

    // Store migration flag so we don't run it again
    update_option('jg_map_timezone_migrated', array(
        'timestamp' => current_time('mysql', true),
        'offset' => $timezone_offset,
        'results' => $results
    ));

    return array(
        'success' => true,
        'message' => 'Migration completed successfully',
        'results' => $results
    );
}

// Check if we should run migration
$migrated = get_option('jg_map_timezone_migrated');
if (!$migrated) {
    echo '<div style="padding: 20px; background: #fff3cd; border: 2px solid #ffc107; margin: 20px; border-radius: 8px;">';
    echo '<h2>⚠️ Timezone Migration Required</h2>';
    echo '<p>Old database entries need to be updated to use GMT/UTC timezone.</p>';
    echo '<p><strong>WordPress Timezone:</strong> ' . get_option('timezone_string') . ' (GMT' . (get_option('gmt_offset') >= 0 ? '+' : '') . get_option('gmt_offset') . ')</p>';
    echo '<form method="post">';
    echo '<input type="hidden" name="jg_run_migration" value="1">';
    echo '<button type="submit" style="background: #dc3545; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold;">Run Migration Now</button>';
    echo '</form>';
    echo '<p style="margin-top: 20px; color: #856404;"><strong>Note:</strong> This is a one-time operation and cannot be undone. Make sure you have a database backup!</p>';
    echo '</div>';

    if (isset($_POST['jg_run_migration'])) {
        $result = jg_map_migrate_timezone_fix();
        echo '<div style="padding: 20px; background: ' . ($result['success'] ? '#d4edda' : '#f8d7da') . '; border: 2px solid ' . ($result['success'] ? '#28a745' : '#dc3545') . '; margin: 20px; border-radius: 8px;">';
        echo '<h3>' . ($result['success'] ? '✅ ' : '❌ ') . $result['message'] . '</h3>';
        if (isset($result['results'])) {
            echo '<ul>';
            foreach ($result['results'] as $table => $count) {
                echo '<li><strong>' . ucfirst($table) . ':</strong> ' . $count . ' rows updated</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
} else {
    echo '<div style="padding: 20px; background: #d4edda; border: 2px solid #28a745; margin: 20px; border-radius: 8px;">';
    echo '<h2>✅ Timezone Migration Already Completed</h2>';
    echo '<p><strong>Migrated at:</strong> ' . $migrated['timestamp'] . '</p>';
    echo '<p><strong>Offset:</strong> ' . $migrated['offset'] . ' seconds</p>';
    echo '<p><strong>Results:</strong></p>';
    echo '<ul>';
    foreach ($migrated['results'] as $table => $count) {
        echo '<li><strong>' . ucfirst($table) . ':</strong> ' . $count . ' rows updated</li>';
    }
    echo '</ul>';
    echo '</div>';
}
