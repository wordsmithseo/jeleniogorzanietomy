<?php
/**
 * Database management class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Database {

    /**
     * Create database tables on plugin activation
     */
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for map points
        $table_points = $wpdb->prefix . 'jg_map_points';

        // Table for votes
        $table_votes = $wpdb->prefix . 'jg_map_votes';

        // Table for reports
        $table_reports = $wpdb->prefix . 'jg_map_reports';

        // Table for edit history
        $table_history = $wpdb->prefix . 'jg_map_history';

        // Points table SQL
        $sql_points = "CREATE TABLE IF NOT EXISTS $table_points (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext,
            excerpt text,
            lat decimal(10, 6) NOT NULL,
            lng decimal(10, 6) NOT NULL,
            address varchar(500) DEFAULT NULL,
            type varchar(50) NOT NULL DEFAULT 'zgloszenie',
            status varchar(20) NOT NULL DEFAULT 'pending',
            report_status varchar(20) DEFAULT 'added',
            author_id bigint(20) UNSIGNED NOT NULL,
            author_hidden tinyint(1) DEFAULT 0,
            is_promo tinyint(1) DEFAULT 0,
            promo_until datetime DEFAULT NULL,
            admin_note text,
            images longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address varchar(100),
            PRIMARY KEY (id),
            KEY author_id (author_id),
            KEY status (status),
            KEY type (type),
            KEY lat_lng (lat, lng)
        ) $charset_collate;";

        // Votes table SQL
        $sql_votes = "CREATE TABLE IF NOT EXISTS $table_votes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            vote_type varchar(10) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_point (user_id, point_id),
            KEY point_id (point_id)
        ) $charset_collate;";

        // Reports table SQL
        $sql_reports = "CREATE TABLE IF NOT EXISTS $table_reports (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            email varchar(255),
            reason text,
            status varchar(20) DEFAULT 'pending',
            admin_decision text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY point_id (point_id),
            KEY status (status)
        ) $charset_collate;";

        // History table SQL
        $sql_history = "CREATE TABLE IF NOT EXISTS $table_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            action_type varchar(50) NOT NULL,
            old_values longtext,
            new_values longtext,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime DEFAULT NULL,
            resolved_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY point_id (point_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_points);
        dbDelta($sql_votes);
        dbDelta($sql_reports);
        dbDelta($sql_history);

        // Set plugin version
        update_option('jg_map_db_version', JG_MAP_VERSION);

        // Check and update schema for existing installations
        self::check_and_update_schema();

        // Create upload directory for map images
        self::create_upload_directory();

        // Create activity log table
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-activity-log.php';
        JG_Map_Activity_Log::create_table();
    }

    /**
     * Check and update database schema for missing columns
     */
    public static function check_and_update_schema() {
        global $wpdb;
        $table = self::get_points_table();

        // Ensure activity log table exists (for existing installations)
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-activity-log.php';
        JG_Map_Activity_Log::create_table();

        // Check if promo_until column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'promo_until'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN promo_until datetime DEFAULT NULL AFTER is_promo");
        }

        // Check if deletion request columns exist
        $deletion_requested = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'is_deletion_requested'");
        if (empty($deletion_requested)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN is_deletion_requested tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        $deletion_reason = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'deletion_reason'");
        if (empty($deletion_reason)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN deletion_reason text DEFAULT NULL AFTER is_deletion_requested");
        }

        $deletion_requested_at = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'deletion_requested_at'");
        if (empty($deletion_requested_at)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN deletion_requested_at datetime DEFAULT NULL AFTER deletion_reason");
        }

        // Check if website column exists (for sponsored points)
        $website = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'website'");
        if (empty($website)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN website varchar(255) DEFAULT NULL AFTER promo_until");
        }

        // Check if phone column exists (for sponsored points)
        $phone = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'phone'");
        if (empty($phone)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN phone varchar(50) DEFAULT NULL AFTER website");
        }

        // Check if cta_enabled column exists (for sponsored points CTA)
        $cta_enabled = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'cta_enabled'");
        if (empty($cta_enabled)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN cta_enabled tinyint(1) DEFAULT 0 AFTER phone");
        }

        // Check if cta_type column exists (for sponsored points CTA - 'call' or 'website')
        $cta_type = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'cta_type'");
        if (empty($cta_type)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN cta_type varchar(20) DEFAULT NULL AFTER cta_enabled");
        }

        // Check if address column exists (for geocoding)
        $address = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'address'");
        if (empty($address)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN address varchar(500) DEFAULT NULL AFTER lng");
        }
    }

    /**
     * Create upload directory for map images
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $jg_map_dir = $upload_dir['basedir'] . '/jg-map';

        if (!file_exists($jg_map_dir)) {
            wp_mkdir_p($jg_map_dir);

            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n<Files *.php>\ndeny from all\n</Files>";
            file_put_contents($jg_map_dir . '/.htaccess', $htaccess_content);
        }
    }

    /**
     * Cleanup on plugin deactivation (optional)
     */
    public static function deactivate() {
        // Optional: Clear scheduled tasks if any
        wp_clear_scheduled_hook('jg_map_cleanup');
    }

    /**
     * Get points table name
     */
    public static function get_points_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_points';
    }

    /**
     * Get votes table name
     */
    public static function get_votes_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_votes';
    }

    /**
     * Get reports table name
     */
    public static function get_reports_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_reports';
    }

    /**
     * Get all published points
     */
    public static function get_published_points($include_pending = false) {
        global $wpdb;
        $table = self::get_points_table();

        // Flush WordPress object cache to ensure fresh data
        wp_cache_flush();

        // Disable MySQL query cache for this session
        $wpdb->query('SET SESSION query_cache_type = OFF');

        $status_condition = $include_pending
            ? "status IN ('publish', 'pending', 'edit')"
            : "status = 'publish'";

        $sql = "SELECT id, title, content, excerpt, lat, lng, type, status, report_status,
                       author_id, author_hidden, is_deletion_requested, deletion_reason,
                       deletion_requested_at, is_promo, promo_until, website, phone,
                       cta_enabled, cta_type, admin_note, images, address, created_at, updated_at, ip_address
                FROM $table WHERE $status_condition ORDER BY created_at DESC";

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get point by ID
     */
    public static function get_point($point_id) {
        global $wpdb;
        $table = self::get_points_table();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, content, excerpt, lat, lng, type, status, report_status,
                        author_id, author_hidden, is_deletion_requested, deletion_reason,
                        deletion_requested_at, is_promo, promo_until, website, phone,
                        cta_enabled, cta_type, admin_note, images, address, created_at, updated_at, ip_address
                 FROM $table WHERE id = %d",
                $point_id
            ),
            ARRAY_A
        );
    }

    /**
     * Insert new point
     */
    public static function insert_point($data) {
        global $wpdb;
        $table = self::get_points_table();

        $wpdb->insert($table, $data);

        return $wpdb->insert_id;
    }

    /**
     * Update point
     */
    public static function update_point($point_id, $data) {
        global $wpdb;
        $table = self::get_points_table();

        return $wpdb->update(
            $table,
            $data,
            array('id' => $point_id)
        );
    }

    /**
     * Delete point
     */
    public static function delete_point($point_id) {
        global $wpdb;
        $table = self::get_points_table();

        // Move to trash instead of permanently deleting
        return $wpdb->update(
            $table,
            array('status' => 'trash'),
            array('id' => $point_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Get votes count for a point
     */
    public static function get_votes_count($point_id) {
        global $wpdb;
        $table = self::get_votes_table();

        $up = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE point_id = %d AND vote_type = 'up'",
                $point_id
            )
        );

        $down = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE point_id = %d AND vote_type = 'down'",
                $point_id
            )
        );

        return intval($up) - intval($down);
    }

    /**
     * Get user's vote for a point
     */
    public static function get_user_vote($point_id, $user_id) {
        global $wpdb;
        $table = self::get_votes_table();

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT vote_type FROM $table WHERE point_id = %d AND user_id = %d",
                $point_id,
                $user_id
            )
        );
    }

    /**
     * Set user vote
     */
    public static function set_vote($point_id, $user_id, $vote_type) {
        global $wpdb;
        $table = self::get_votes_table();

        // Delete existing vote first
        $wpdb->delete(
            $table,
            array('point_id' => $point_id, 'user_id' => $user_id)
        );

        // Insert new vote if not removing
        if (!empty($vote_type)) {
            $wpdb->insert(
                $table,
                array(
                    'point_id' => $point_id,
                    'user_id' => $user_id,
                    'vote_type' => $vote_type
                )
            );
        }

        return true;
    }

    /**
     * Get reports for a point
     */
    public static function get_reports($point_id) {
        global $wpdb;
        $table = self::get_reports_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE point_id = %d AND status = 'pending' ORDER BY created_at DESC",
                $point_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get reports count for a point
     */
    public static function get_reports_count($point_id) {
        global $wpdb;
        $table = self::get_reports_table();

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE point_id = %d AND status = 'pending'",
                $point_id
            )
        );
    }

    /**
     * Check if user already reported a point
     */
    public static function has_user_reported($point_id, $user_id) {
        global $wpdb;
        $table = self::get_reports_table();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE point_id = %d AND user_id = %d AND status = 'pending'",
                $point_id,
                $user_id
            )
        );

        return $count > 0;
    }

    /**
     * Add report
     */
    public static function add_report($point_id, $user_id, $email, $reason) {
        global $wpdb;
        $table = self::get_reports_table();

        return $wpdb->insert(
            $table,
            array(
                'point_id' => $point_id,
                'user_id' => $user_id,
                'email' => $email,
                'reason' => $reason,
                'status' => 'pending'
            )
        );
    }

    /**
     * Resolve reports
     */
    public static function resolve_reports($point_id, $decision) {
        global $wpdb;
        $table = self::get_reports_table();

        return $wpdb->update(
            $table,
            array(
                'status' => 'resolved',
                'admin_decision' => $decision,
                'resolved_at' => current_time('mysql')
            ),
            array('point_id' => $point_id, 'status' => 'pending')
        );
    }

    /**
     * Get history table name
     */
    public static function get_history_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_history';
    }

    /**
     * Ensure history table exists
     */
    public static function ensure_history_table() {
        global $wpdb;
        $table_history = self::get_history_table();

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_history'");

        if ($table_exists != $table_history) {
            // Create table
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $table_history (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                point_id bigint(20) UNSIGNED NOT NULL,
                user_id bigint(20) UNSIGNED NOT NULL,
                action_type varchar(50) NOT NULL,
                old_values longtext,
                new_values longtext,
                status varchar(20) DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                resolved_at datetime DEFAULT NULL,
                resolved_by bigint(20) UNSIGNED DEFAULT NULL,
                PRIMARY KEY (id),
                KEY point_id (point_id),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // Also check and update main table schema
        self::check_and_update_schema();
    }

    /**
     * Add history entry
     */
    public static function add_history($point_id, $user_id, $action_type, $old_values, $new_values) {
        global $wpdb;

        // Ensure history table exists
        self::ensure_history_table();

        $table = self::get_history_table();

        return $wpdb->insert(
            $table,
            array(
                'point_id' => $point_id,
                'user_id' => $user_id,
                'action_type' => $action_type,
                'old_values' => is_array($old_values) ? json_encode($old_values) : $old_values,
                'new_values' => is_array($new_values) ? json_encode($new_values) : $new_values,
                'status' => 'pending'
            )
        );
    }

    /**
     * Get pending history for a point
     */
    public static function get_pending_history($point_id) {
        global $wpdb;

        // Ensure history table exists
        self::ensure_history_table();

        $table = self::get_history_table();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, point_id, user_id, action_type, old_values, new_values,
                        status, created_at, resolved_at, resolved_by
                 FROM $table WHERE point_id = %d AND status = 'pending'
                 ORDER BY created_at DESC LIMIT 1",
                $point_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get all history for a point
     */
    public static function get_point_history($point_id) {
        global $wpdb;
        $table = self::get_history_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, point_id, user_id, action_type, old_values, new_values,
                        status, created_at, resolved_at, resolved_by
                 FROM $table WHERE point_id = %d ORDER BY created_at DESC",
                $point_id
            ),
            ARRAY_A
        );
    }

    /**
     * Approve history entry
     */
    public static function approve_history($history_id, $admin_id) {
        global $wpdb;
        $table = self::get_history_table();

        return $wpdb->update(
            $table,
            array(
                'status' => 'approved',
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $admin_id
            ),
            array('id' => $history_id)
        );
    }

    /**
     * Reject history entry
     */
    public static function reject_history($history_id, $admin_id) {
        global $wpdb;
        $table = self::get_history_table();

        return $wpdb->update(
            $table,
            array(
                'status' => 'rejected',
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $admin_id
            ),
            array('id' => $history_id)
        );
    }
}
