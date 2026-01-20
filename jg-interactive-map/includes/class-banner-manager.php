<?php
/**
 * Banner Manager Class
 * Manages 728x90 leaderboard banners with fair rotation and tracking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Banner_Manager {

    /**
     * Create banners table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            image_url varchar(500) NOT NULL,
            link_url varchar(500) NOT NULL,
            impressions_bought int(11) DEFAULT 0,
            impressions_used int(11) DEFAULT 0,
            clicks int(11) DEFAULT 0,
            active tinyint(1) DEFAULT 1,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY active (active),
            KEY display_order (display_order),
            KEY impressions_used (impressions_used)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_banners';
    }

    /**
     * Get all active banners (with available impressions)
     */
    public static function get_active_banners() {
        global $wpdb;
        $table = self::get_table_name();
        $now = current_time('mysql'); // Use local WordPress time, not UTC

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                 WHERE active = 1
                 AND (impressions_bought = 0 OR impressions_used < impressions_bought)
                 AND (start_date IS NULL OR start_date <= %s)
                 AND (end_date IS NULL OR end_date >= %s)
                 ORDER BY display_order ASC, id ASC",
                $now,
                $now
            ),
            ARRAY_A
        );
    }

    /**
     * Get next banner to display (cyclic rotation based on session)
     */
    public static function get_next_banner_for_rotation() {
        $active_banners = self::get_active_banners();

        if (empty($active_banners)) {
            return null;
        }

        // Return the first active banner
        // The actual rotation will be handled in JavaScript using session storage
        return $active_banners[0];
    }

    /**
     * Get banner by ID
     */
    public static function get_banner($banner_id) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $banner_id
            ),
            ARRAY_A
        );
    }

    /**
     * Insert new banner
     */
    public static function insert_banner($data) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = array(
            'impressions_bought' => 0,
            'impressions_used' => 0,
            'clicks' => 0,
            'active' => 1,
            'display_order' => 0,
            'start_date' => null,
            'end_date' => null
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update banner
     */
    public static function update_banner($banner_id, $data) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->update(
            $table,
            $data,
            array('id' => $banner_id),
            null,
            array('%d')
        );
    }

    /**
     * Delete banner
     */
    public static function delete_banner($banner_id) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->delete(
            $table,
            array('id' => $banner_id),
            array('%d')
        );
    }

    /**
     * Track impression (increment impressions_used)
     */
    public static function track_impression($banner_id) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET impressions_used = impressions_used + 1 WHERE id = %d",
                $banner_id
            )
        );
    }

    /**
     * Track click (increment clicks)
     */
    public static function track_click($banner_id) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET clicks = clicks + 1 WHERE id = %d",
                $banner_id
            )
        );
    }

    /**
     * Get banner statistics
     */
    public static function get_banner_stats($banner_id) {
        global $wpdb;
        $table = self::get_table_name();

        $banner = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT impressions_bought, impressions_used, clicks FROM $table WHERE id = %d",
                $banner_id
            ),
            ARRAY_A
        );

        if (!$banner) {
            return null;
        }

        $impressions_remaining = $banner['impressions_bought'] > 0
            ? max(0, $banner['impressions_bought'] - $banner['impressions_used'])
            : 'unlimited';

        $ctr = $banner['impressions_used'] > 0
            ? round(($banner['clicks'] / $banner['impressions_used']) * 100, 2)
            : 0;

        return array(
            'impressions_bought' => $banner['impressions_bought'],
            'impressions_used' => $banner['impressions_used'],
            'impressions_remaining' => $impressions_remaining,
            'clicks' => $banner['clicks'],
            'ctr' => $ctr . '%'
        );
    }

    /**
     * Get all banners for admin (including inactive)
     */
    public static function get_all_banners() {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_results(
            "SELECT * FROM $table ORDER BY display_order ASC, id DESC",
            ARRAY_A
        );
    }

    /**
     * Deactivate expired banners (called by maintenance cron)
     */
    public static function deactivate_expired_banners() {
        global $wpdb;
        $table = self::get_table_name();
        $now = current_time('mysql'); // Use local WordPress time, not UTC

        // Deactivate banners past their end_date
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET active = 0 WHERE active = 1 AND end_date IS NOT NULL AND end_date < %s",
                $now
            )
        );

        // Deactivate banners that exhausted their impressions
        $wpdb->query(
            "UPDATE $table SET active = 0 WHERE active = 1 AND impressions_bought > 0 AND impressions_used >= impressions_bought"
        );
    }
}
