<?php
/**
 * Activity Log for administrative actions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Activity_Log {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Create activity log table on activation
        add_action('plugins_loaded', array($this, 'ensure_table_exists'));
    }

    /**
     * Ensure activity log table exists
     */
    public function ensure_table_exists() {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_activity_log';

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

        if ($table_exists != $table) {
            $this->create_table();
        }
    }

    /**
     * Create activity log table
     */
    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_activity_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) UNSIGNED DEFAULT NULL,
            description text,
            ip_address varchar(100),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log an administrative action
     */
    public static function log($action, $object_type, $object_id = null, $description = '') {
        global $wpdb;

        // Only log for admins and moderators
        if (!current_user_can('manage_options') && !current_user_can('jg_map_moderate')) {
            return false;
        }

        $table = $wpdb->prefix . 'jg_map_activity_log';
        $user_id = get_current_user_id();

        // Get IP and user agent
        $ip_address = self::get_user_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';

        return $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'action' => sanitize_text_field($action),
                'object_type' => sanitize_text_field($object_type),
                'object_id' => $object_id,
                'description' => sanitize_text_field($description),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Get activity logs with pagination
     */
    public static function get_logs($limit = 50, $offset = 0, $filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_activity_log';

        $where = array('1=1');
        $where_values = array();

        // Filter by user
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $where_values[] = intval($filters['user_id']);
        }

        // Filter by action
        if (!empty($filters['action'])) {
            $where[] = 'action = %s';
            $where_values[] = sanitize_text_field($filters['action']);
        }

        // Filter by object type
        if (!empty($filters['object_type'])) {
            $where[] = 'object_type = %s';
            $where_values[] = sanitize_text_field($filters['object_type']);
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = sanitize_text_field($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = sanitize_text_field($filters['date_to']);
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get total count of logs
     */
    public static function get_logs_count($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_activity_log';

        $where = array('1=1');
        $where_values = array();

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $where_values[] = intval($filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = %s';
            $where_values[] = sanitize_text_field($filters['action']);
        }

        if (!empty($filters['object_type'])) {
            $where[] = 'object_type = %s';
            $where_values[] = sanitize_text_field($filters['object_type']);
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return intval($wpdb->get_var($sql));
    }

    /**
     * Get user IP address
     */
    private static function get_user_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        $ip = sanitize_text_field($ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        }

        return $ip;
    }

    /**
     * Delete old logs (cleanup, keep last 90 days)
     */
    public static function cleanup_old_logs($days = 90) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_activity_log';

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE created_at < %s",
                $date
            )
        );
    }
}
