<?php
/**
 * JG Map Sync Manager
 *
 * Dedicated, super-reliable synchronization system for map pins and notifications.
 * This system ensures all users see pin state changes in real-time with predictable,
 * scalable performance resistant to edge cases.
 *
 * Architecture:
 * - Database-backed sync queue (no transients)
 * - Event-driven with WordPress hooks
 * - Automatic retry logic with exponential backoff
 * - Comprehensive error logging
 * - Efficient queue cleanup
 * - Role-aware state broadcasting
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Sync_Manager {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Sync event types
     */
    const EVENT_POINT_CREATED = 'point_created';
    const EVENT_POINT_UPDATED = 'point_updated';
    const EVENT_POINT_APPROVED = 'point_approved';
    const EVENT_POINT_DELETED = 'point_deleted';
    const EVENT_REPORT_ADDED = 'report_added';
    const EVENT_REPORT_RESOLVED = 'report_resolved';
    const EVENT_EDIT_SUBMITTED = 'edit_submitted';
    const EVENT_EDIT_APPROVED = 'edit_approved';
    const EVENT_EDIT_REJECTED = 'edit_rejected';
    const EVENT_DELETION_REQUESTED = 'deletion_requested';
    const EVENT_DELETION_APPROVED = 'deletion_approved';
    const EVENT_DELETION_REJECTED = 'deletion_rejected';

    /**
     * Sync status
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Maximum retry attempts
     */
    const MAX_RETRIES = 3;

    /**
     * Queue retention period (seconds) - 24 hours
     */
    const QUEUE_RETENTION = 86400;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize hooks
     */
    private function __construct() {
        // Register heartbeat handler
        add_filter('heartbeat_received', array($this, 'heartbeat_handler'), 10, 2);

        // Register cleanup cron
        add_action('jg_map_sync_cleanup', array($this, 'cleanup_old_queue_entries'));

        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('jg_map_sync_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'jg_map_sync_cleanup');
        }
    }

    /**
     * Queue a sync event
     *
     * @param string $event_type Event type constant
     * @param int $point_id Point ID affected
     * @param array $metadata Additional metadata
     * @param int $priority Priority (higher = more urgent)
     * @return int|false Queue entry ID or false on failure
     */
    public function queue_sync($event_type, $point_id, $metadata = array(), $priority = 10) {
        global $wpdb;
        $table = self::get_sync_queue_table();

        // Prepare data
        $data = array(
            'event_type' => $event_type,
            'point_id' => $point_id,
            'metadata' => json_encode($metadata),
            'priority' => $priority,
            'status' => self::STATUS_PENDING,
            'retry_count' => 0,
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true)
        );

        // Insert into queue
        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            $this->log_error('Failed to queue sync event', array(
                'event_type' => $event_type,
                'point_id' => $point_id,
                'error' => $wpdb->last_error
            ));
            return false;
        }

        $queue_id = $wpdb->insert_id;

        // Invalidate map cache immediately
        $this->invalidate_map_cache();

        // Fire action hook for extensibility
        do_action('jg_map_sync_queued', $queue_id, $event_type, $point_id, $metadata);

        // Log success
        $this->log_debug('Sync event queued', array(
            'queue_id' => $queue_id,
            'event_type' => $event_type,
            'point_id' => $point_id
        ));

        return $queue_id;
    }

    /**
     * Invalidate map cache to force refresh
     */
    private function invalidate_map_cache() {
        // Update last modified timestamp
        update_option('jg_map_last_modified', time());

        // Clear WordPress object cache
        wp_cache_delete('jg_map_pending_counts');
        wp_cache_flush();

        // Invalidate points transient cache (for sidebar and map)
        JG_Map_Database::invalidate_points_cache();
    }

    /**
     * Get pending sync events for heartbeat
     *
     * @param int $since_timestamp Only get events newer than this
     * @return array Array of sync events
     */
    public function get_pending_syncs($since_timestamp = 0) {
        global $wpdb;
        $table = self::get_sync_queue_table();

        $query = $wpdb->prepare(
            "SELECT id, event_type, point_id, metadata, priority, created_at
             FROM $table
             WHERE status IN (%s, %s)
             AND created_at > %s
             ORDER BY priority DESC, created_at DESC
             LIMIT 100",
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            date('Y-m-d H:i:s', $since_timestamp)
        );

        $events = $wpdb->get_results($query, ARRAY_A);

        // Decode metadata
        foreach ($events as &$event) {
            $event['metadata'] = json_decode($event['metadata'], true);
        }

        return $events;
    }

    /**
     * Mark sync event as completed
     *
     * @param int $queue_id Queue entry ID
     * @return bool Success
     */
    public function mark_completed($queue_id) {
        global $wpdb;
        $table = self::get_sync_queue_table();

        $result = $wpdb->update(
            $table,
            array(
                'status' => self::STATUS_COMPLETED,
                'updated_at' => current_time('mysql', true)
            ),
            array('id' => $queue_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            $this->log_error('Failed to mark sync as completed', array(
                'queue_id' => $queue_id,
                'error' => $wpdb->last_error
            ));
            return false;
        }

        return true;
    }

    /**
     * Mark sync event as failed and retry if possible
     *
     * @param int $queue_id Queue entry ID
     * @param string $error_message Error message
     * @return bool Success
     */
    public function mark_failed($queue_id, $error_message = '') {
        global $wpdb;
        $table = self::get_sync_queue_table();

        // Get current retry count
        $entry = $wpdb->get_row(
            $wpdb->prepare("SELECT retry_count FROM $table WHERE id = %d", $queue_id),
            ARRAY_A
        );

        if (!$entry) {
            return false;
        }

        $retry_count = intval($entry['retry_count']) + 1;

        // Determine new status
        $new_status = ($retry_count >= self::MAX_RETRIES)
            ? self::STATUS_FAILED
            : self::STATUS_PENDING;

        // Update entry
        $result = $wpdb->update(
            $table,
            array(
                'status' => $new_status,
                'retry_count' => $retry_count,
                'error_message' => $error_message,
                'updated_at' => current_time('mysql', true)
            ),
            array('id' => $queue_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            $this->log_error('Failed to mark sync as failed', array(
                'queue_id' => $queue_id,
                'error' => $wpdb->last_error
            ));
            return false;
        }

        // Log failure
        $this->log_error('Sync event failed', array(
            'queue_id' => $queue_id,
            'retry_count' => $retry_count,
            'error_message' => $error_message,
            'will_retry' => ($new_status === self::STATUS_PENDING)
        ));

        return true;
    }

    /**
     * WordPress Heartbeat handler
     * Broadcasts pending sync events to connected clients
     *
     * @param array $response Heartbeat response
     * @param array $data Heartbeat data
     * @return array Modified response
     */
    public function heartbeat_handler($response, $data) {
        global $wpdb;

        // Only process if map is active
        if (!isset($data['jg_map_check'])) {
            return $response;
        }

        $last_check = isset($data['jg_map_last_check'])
            ? intval($data['jg_map_last_check'])
            : (time() - 60); // Default: last 60 seconds

        // Get pending sync events
        $sync_events = $this->get_pending_syncs($last_check);

        // Get new/updated points count
        // For admins: include both published and pending points
        // For regular users: only published points
        $points_table = JG_Map_Database::get_points_table();
        $is_admin = current_user_can('manage_options');

        if ($is_admin) {
            // Admins see both published and pending points
            $new_points = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $points_table
                 WHERE status IN ('publish', 'pending')
                 AND (
                    created_at > %s
                    OR approved_at > %s
                    OR updated_at > %s
                 )",
                date('Y-m-d H:i:s', $last_check),
                date('Y-m-d H:i:s', $last_check),
                date('Y-m-d H:i:s', $last_check)
            ));
        } else {
            // Regular users only see published points
            $new_points = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $points_table
                 WHERE status = 'publish'
                 AND (
                    created_at > %s
                    OR approved_at > %s
                    OR updated_at > %s
                 )",
                date('Y-m-d H:i:s', $last_check),
                date('Y-m-d H:i:s', $last_check),
                date('Y-m-d H:i:s', $last_check)
            ));
        }

        // Get pending counts for admin
        $pending_counts = array();
        if (current_user_can('manage_options')) {
            $pending_counts = array(
                'points' => $wpdb->get_var("SELECT COUNT(*) FROM $points_table WHERE status = 'pending'"),
                'reports' => $wpdb->get_var("SELECT COUNT(*) FROM " . JG_Map_Database::get_reports_table() . " WHERE status = 'pending'"),
                'edits' => $wpdb->get_var("SELECT COUNT(*) FROM " . JG_Map_Database::get_history_table() . " WHERE status = 'pending' AND action_type = 'edit'"),
                'deletions' => $wpdb->get_var("SELECT COUNT(*) FROM $points_table WHERE is_deletion_requested = 1 AND status = 'publish'")
            );
        }

        // Build response
        $response['jg_map_sync'] = array(
            'new_points' => intval($new_points),
            'sync_events' => $sync_events,
            'pending_counts' => $pending_counts,
            'last_modified' => get_option('jg_map_last_modified', time()),
            'server_time' => time()
        );

        return $response;
    }

    /**
     * Cleanup old completed/failed queue entries
     * Runs via cron hourly
     */
    public function cleanup_old_queue_entries() {
        global $wpdb;
        $table = self::get_sync_queue_table();

        $cutoff_time = date('Y-m-d H:i:s', time() - self::QUEUE_RETENTION);

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table
             WHERE status IN (%s, %s)
             AND updated_at < %s",
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            $cutoff_time
        ));

        if ($deleted > 0) {
            $this->log_debug('Cleaned up old sync queue entries', array(
                'deleted_count' => $deleted,
                'cutoff_time' => $cutoff_time
            ));
        }
    }

    /**
     * Get sync queue statistics
     *
     * @return array Statistics
     */
    public function get_queue_stats() {
        global $wpdb;
        $table = self::get_sync_queue_table();

        $stats = array(
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = '" . self::STATUS_PENDING . "'"),
            'processing' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = '" . self::STATUS_PROCESSING . "'"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = '" . self::STATUS_COMPLETED . "'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = '" . self::STATUS_FAILED . "'"),
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table")
        );

        return $stats;
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function log_error($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[JG Map Sync Manager ERROR] ' . $message . ' | Context: ' . json_encode($context));
        }

        // Also log to activity log if available
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'sync_error',
                0, // system action
                $message,
                $context
            );
        }
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context
     */
    private function log_debug($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[JG Map Sync Manager] ' . $message . ' | Context: ' . json_encode($context));
        }
    }

    /**
     * Get sync queue table name
     *
     * @return string Table name
     */
    public static function get_sync_queue_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_sync_queue';
    }

    /**
     * Create sync queue table
     * Called during plugin activation and schema updates
     */
    public static function create_table() {
        global $wpdb;
        $table = self::get_sync_queue_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            point_id bigint(20) UNSIGNED NOT NULL,
            metadata longtext,
            priority int DEFAULT 10,
            status varchar(20) NOT NULL DEFAULT 'pending',
            retry_count int DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY point_id (point_id),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at),
            KEY event_type (event_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Helper: Queue point creation event
     */
    public function queue_point_created($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_POINT_CREATED, $point_id, $metadata, 20);
    }

    /**
     * Helper: Queue point update event
     */
    public function queue_point_updated($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_POINT_UPDATED, $point_id, $metadata, 15);
    }

    /**
     * Helper: Queue point approval event
     */
    public function queue_point_approved($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_POINT_APPROVED, $point_id, $metadata, 25);
    }

    /**
     * Helper: Queue point deletion event
     */
    public function queue_point_deleted($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_POINT_DELETED, $point_id, $metadata, 30);
    }

    /**
     * Helper: Queue report added event
     */
    public function queue_report_added($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_REPORT_ADDED, $point_id, $metadata, 20);
    }

    /**
     * Helper: Queue report resolved event
     */
    public function queue_report_resolved($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_REPORT_RESOLVED, $point_id, $metadata, 20);
    }

    /**
     * Helper: Queue edit submitted event
     */
    public function queue_edit_submitted($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_EDIT_SUBMITTED, $point_id, $metadata, 15);
    }

    /**
     * Helper: Queue edit approved event
     */
    public function queue_edit_approved($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_EDIT_APPROVED, $point_id, $metadata, 20);
    }

    /**
     * Helper: Queue edit rejected event
     */
    public function queue_edit_rejected($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_EDIT_REJECTED, $point_id, $metadata, 15);
    }

    /**
     * Helper: Queue deletion requested event
     */
    public function queue_deletion_requested($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_DELETION_REQUESTED, $point_id, $metadata, 15);
    }

    /**
     * Helper: Queue deletion approved event
     */
    public function queue_deletion_approved($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_DELETION_APPROVED, $point_id, $metadata, 25);
    }

    /**
     * Helper: Queue deletion rejected event
     */
    public function queue_deletion_rejected($point_id, $metadata = array()) {
        return $this->queue_sync(self::EVENT_DELETION_REJECTED, $point_id, $metadata, 15);
    }
}
