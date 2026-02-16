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

        // Check if category column exists, add it if it doesn't
        $safe_table = esc_sql($table_points);
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `%s` LIKE %s", $safe_table, 'category'));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN category varchar(100) DEFAULT NULL AFTER type");
        }

        // Table for votes
        $table_votes = $wpdb->prefix . 'jg_map_votes';

        // Table for reports
        $table_reports = $wpdb->prefix . 'jg_map_reports';

        // Table for edit history
        $table_history = $wpdb->prefix . 'jg_map_history';

        // Table for relevance votes
        $table_relevance_votes = $wpdb->prefix . 'jg_map_relevance_votes';

        // Table for point visits (tracking who visited which point)
        $table_point_visits = $wpdb->prefix . 'jg_map_point_visits';

        // Points table SQL
        $sql_points = "CREATE TABLE IF NOT EXISTS $table_points (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            case_id varchar(20) DEFAULT NULL,
            title varchar(255) NOT NULL,
            slug varchar(255) DEFAULT NULL,
            content longtext,
            excerpt text,
            lat decimal(10, 6) NOT NULL,
            lng decimal(10, 6) NOT NULL,
            address varchar(500) DEFAULT NULL,
            type varchar(50) NOT NULL DEFAULT 'zgloszenie',
            category varchar(100) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            report_status varchar(50) DEFAULT 'added',
            resolved_delete_at datetime DEFAULT NULL,
            resolved_summary text DEFAULT NULL,
            rejected_reason text DEFAULT NULL,
            rejected_delete_at datetime DEFAULT NULL,
            author_id bigint(20) UNSIGNED NOT NULL,
            author_hidden tinyint(1) DEFAULT 0,
            edit_locked tinyint(1) DEFAULT 0,
            is_promo tinyint(1) DEFAULT 0,
            promo_until datetime DEFAULT NULL,
            admin_note text,
            images longtext,
            website varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            cta_enabled tinyint(1) DEFAULT 0,
            cta_type varchar(20) DEFAULT NULL,
            is_deletion_requested tinyint(1) DEFAULT 0,
            deletion_reason text DEFAULT NULL,
            deletion_requested_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            approved_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            tags varchar(500) DEFAULT NULL,
            ip_address varchar(100),
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY author_id (author_id),
            KEY status (status),
            KEY type (type),
            KEY lat_lng (lat, lng),
            KEY case_id (case_id)
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
            point_owner_id bigint(20) UNSIGNED DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            old_values longtext,
            new_values longtext,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime DEFAULT NULL,
            resolved_by bigint(20) UNSIGNED DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            owner_approval_status varchar(20) DEFAULT 'pending',
            owner_approval_at datetime DEFAULT NULL,
            owner_approval_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY point_id (point_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY owner_approval_status (owner_approval_status)
        ) $charset_collate;";

        // Relevance votes table SQL (for "Nadal aktualne?" voting)
        $sql_relevance_votes = "CREATE TABLE IF NOT EXISTS $table_relevance_votes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            vote_type varchar(10) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_point (user_id, point_id),
            KEY point_id (point_id)
        ) $charset_collate;";

        // Point visits table SQL (for tracking unique visitors)
        $sql_point_visits = "CREATE TABLE IF NOT EXISTS $table_point_visits (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            visitor_fingerprint varchar(64) DEFAULT NULL,
            visit_count int(11) DEFAULT 1,
            first_visited datetime DEFAULT CURRENT_TIMESTAMP,
            last_visited datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_point (user_id, point_id),
            UNIQUE KEY fingerprint_point (visitor_fingerprint, point_id),
            KEY point_id (point_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_points);
        dbDelta($sql_votes);
        dbDelta($sql_reports);
        dbDelta($sql_history);
        dbDelta($sql_relevance_votes);
        dbDelta($sql_point_visits);

        // Set plugin version
        update_option('jg_map_db_version', JG_MAP_VERSION);

        // Check and update schema for existing installations
        self::check_and_update_schema();

        // Create upload directory for map images
        self::create_upload_directory();

        // Create activity log table
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-activity-log.php';
        JG_Map_Activity_Log::create_table();

        // Create sync queue table
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-sync-manager.php';
        JG_Map_Sync_Manager::create_table();

        // Create banners table
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-banner-manager.php';
        JG_Map_Banner_Manager::create_table();

        // Create levels & achievements tables
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-levels-achievements.php';
        JG_Map_Levels_Achievements::create_tables();

        // Add custom capabilities to administrator role
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('jg_map_moderate');
            $admin->add_cap('jg_map_bypass_maintenance');
        }
    }

    /**
     * Check and update database schema for missing columns
     */
    public static function check_and_update_schema() {
        global $wpdb;
        $table = self::get_points_table();
        $safe_table = esc_sql($table);

        // Performance optimization: Cache schema check to avoid 17 SHOW COLUMNS queries on every page load
        // Schema version tracks which columns have been added
        $current_schema_version = '3.16.0'; // Added tags for points
        $cached_schema_version = get_option('jg_map_schema_version', '0');

        // Only run schema check if version has changed
        if ($cached_schema_version === $current_schema_version) {
            return; // Schema is up to date, skip all checks
        }

        // Ensure activity log table exists (for existing installations)
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-activity-log.php';
        JG_Map_Activity_Log::create_table();

        // Ensure sync queue table exists (for existing installations)
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-sync-manager.php';
        JG_Map_Sync_Manager::create_table();

        // Ensure point visits table exists (for visitor tracking)
        $table_point_visits = $wpdb->prefix . 'jg_map_point_visits';
        $charset_collate = $wpdb->get_charset_collate();
        $sql_point_visits = "CREATE TABLE IF NOT EXISTS $table_point_visits (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            visitor_fingerprint varchar(64) DEFAULT NULL,
            visit_count int(11) DEFAULT 1,
            first_visited datetime DEFAULT CURRENT_TIMESTAMP,
            last_visited datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_point (user_id, point_id),
            UNIQUE KEY fingerprint_point (visitor_fingerprint, point_id),
            KEY point_id (point_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_point_visits);

        // Ensure banners table exists (for existing installations)
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-banner-manager.php';
        JG_Map_Banner_Manager::create_table();

        // Ensure levels & achievements tables exist (for existing installations)
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-levels-achievements.php';
        JG_Map_Levels_Achievements::create_tables();

        // Helper function to check if column exists
        $column_exists = function($column_name) use ($wpdb, $safe_table) {
            $result = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM `$safe_table` LIKE %s",
                $column_name
            ));
            return !empty($result);
        };

        // Check if category column exists (added in v3.2.x for report categorization)
        if (!$column_exists('category')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN category varchar(100) DEFAULT NULL AFTER type");
        }

        // Check if promo_until column exists
        if (!$column_exists('promo_until')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN promo_until datetime DEFAULT NULL AFTER is_promo");
        }

        // Check if deletion request columns exist
        if (!$column_exists('is_deletion_requested')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN is_deletion_requested tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        if (!$column_exists('deletion_reason')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN deletion_reason text DEFAULT NULL AFTER is_deletion_requested");
        }

        if (!$column_exists('deletion_requested_at')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN deletion_requested_at datetime DEFAULT NULL AFTER deletion_reason");
        }

        // Check if website column exists (for sponsored points)
        if (!$column_exists('website')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN website varchar(255) DEFAULT NULL AFTER promo_until");
        }

        // Check if phone column exists (for sponsored points)
        if (!$column_exists('phone')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN phone varchar(50) DEFAULT NULL AFTER website");
        }

        // Check if cta_enabled column exists (for sponsored points CTA)
        if (!$column_exists('cta_enabled')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN cta_enabled tinyint(1) DEFAULT 0 AFTER phone");
        }

        // Check if cta_type column exists (for sponsored points CTA - 'call' or 'website')
        if (!$column_exists('cta_type')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN cta_type varchar(20) DEFAULT NULL AFTER cta_enabled");
        }

        // Check if address column exists (for geocoding)
        if (!$column_exists('address')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN address varchar(500) DEFAULT NULL AFTER lng");
        }

        // Check if approved_at column exists (for tracking approval date)
        if (!$column_exists('approved_at')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN approved_at datetime DEFAULT NULL AFTER created_at");
        }

        // Check if slug column exists (for SEO-friendly URLs)
        $slug_was_added = false;
        if (!$column_exists('slug')) {
            $result = $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN slug varchar(255) DEFAULT NULL AFTER title");
            if ($result !== false) {
                // Add unique index
                $wpdb->query("ALTER TABLE `$safe_table` ADD UNIQUE KEY slug (slug)");

                // Generate slugs for existing points
                self::migrate_generate_slugs();

                // Mark that slug was added so we can flush rewrite rules
                $slug_was_added = true;
            }
        } else {
            // Check if there are any points without slugs and generate them
            $points_without_slugs = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `$safe_table` WHERE slug IS NULL OR slug = %s",
                ''
            ));
            if ($points_without_slugs > 0) {
                self::migrate_generate_slugs();
            }
        }

        // Flush rewrite rules if slug column was just added
        if ($slug_was_added) {
            flush_rewrite_rules();
            // Set option to flush again on next page load (ensures it works)
            update_option('jg_map_needs_rewrite_flush', true);
        }

        // Check if featured_image_index column exists (for OG/social media featured image)
        if (!$column_exists('featured_image_index')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN featured_image_index int DEFAULT 0 AFTER images");
        }

        // Check if social media columns exist (for sponsored places)
        $social_columns = array('facebook_url', 'instagram_url', 'linkedin_url', 'tiktok_url');
        $social_definitions = array(
            'facebook_url' => "ALTER TABLE `$safe_table` ADD COLUMN facebook_url varchar(255) DEFAULT NULL AFTER phone",
            'instagram_url' => "ALTER TABLE `$safe_table` ADD COLUMN instagram_url varchar(255) DEFAULT NULL AFTER facebook_url",
            'linkedin_url' => "ALTER TABLE `$safe_table` ADD COLUMN linkedin_url varchar(255) DEFAULT NULL AFTER instagram_url",
            'tiktok_url' => "ALTER TABLE `$safe_table` ADD COLUMN tiktok_url varchar(255) DEFAULT NULL AFTER linkedin_url"
        );

        foreach ($social_columns as $column_name) {
            if (!$column_exists($column_name)) {
                $wpdb->query($social_definitions[$column_name]);
            }
        }

        // Check if statistics columns exist (for analytics)
        $stats_columns = array('stats_views', 'stats_phone_clicks', 'stats_website_clicks', 'stats_social_clicks',
                              'stats_cta_clicks', 'stats_gallery_clicks', 'stats_first_viewed', 'stats_last_viewed',
                              'stats_unique_visitors', 'stats_avg_time_spent');
        $stats_definitions = array(
            'stats_views' => "ALTER TABLE `$safe_table` ADD COLUMN stats_views int DEFAULT 0 AFTER tiktok_url",
            'stats_phone_clicks' => "ALTER TABLE `$safe_table` ADD COLUMN stats_phone_clicks int DEFAULT 0 AFTER stats_views",
            'stats_website_clicks' => "ALTER TABLE `$safe_table` ADD COLUMN stats_website_clicks int DEFAULT 0 AFTER stats_phone_clicks",
            'stats_social_clicks' => "ALTER TABLE `$safe_table` ADD COLUMN stats_social_clicks longtext DEFAULT NULL AFTER stats_website_clicks",
            'stats_cta_clicks' => "ALTER TABLE `$safe_table` ADD COLUMN stats_cta_clicks int DEFAULT 0 AFTER stats_social_clicks",
            'stats_gallery_clicks' => "ALTER TABLE `$safe_table` ADD COLUMN stats_gallery_clicks longtext DEFAULT NULL AFTER stats_cta_clicks",
            'stats_first_viewed' => "ALTER TABLE `$safe_table` ADD COLUMN stats_first_viewed datetime DEFAULT NULL AFTER stats_gallery_clicks",
            'stats_last_viewed' => "ALTER TABLE `$safe_table` ADD COLUMN stats_last_viewed datetime DEFAULT NULL AFTER stats_first_viewed",
            'stats_unique_visitors' => "ALTER TABLE `$safe_table` ADD COLUMN stats_unique_visitors int DEFAULT 0 AFTER stats_last_viewed",
            'stats_avg_time_spent' => "ALTER TABLE `$safe_table` ADD COLUMN stats_avg_time_spent int DEFAULT 0 AFTER stats_unique_visitors"
        );

        foreach ($stats_columns as $column_name) {
            if (!$column_exists($column_name)) {
                $wpdb->query($stats_definitions[$column_name]);
            }
        }

        // Check if rejection_reason column exists in history table (for moderation transparency)
        $table_history = self::get_history_table();
        $safe_history = esc_sql($table_history);
        $rejection_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$safe_history` LIKE %s",
            'rejection_reason'
        ));
        if (empty($rejection_exists)) {
            $wpdb->query("ALTER TABLE `$safe_history` ADD COLUMN rejection_reason text DEFAULT NULL AFTER resolved_by");
        }

        // Check if owner approval columns exist in history table (for two-stage approval)
        $owner_approval_status_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$safe_history` LIKE %s",
            'owner_approval_status'
        ));
        if (empty($owner_approval_status_exists)) {
            $wpdb->query("ALTER TABLE `$safe_history` ADD COLUMN owner_approval_status varchar(20) DEFAULT 'pending' AFTER rejection_reason");
        }

        $owner_approval_at_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$safe_history` LIKE %s",
            'owner_approval_at'
        ));
        if (empty($owner_approval_at_exists)) {
            $wpdb->query("ALTER TABLE `$safe_history` ADD COLUMN owner_approval_at datetime DEFAULT NULL AFTER owner_approval_status");
        }

        $owner_approval_by_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$safe_history` LIKE %s",
            'owner_approval_by'
        ));
        if (empty($owner_approval_by_exists)) {
            $wpdb->query("ALTER TABLE `$safe_history` ADD COLUMN owner_approval_by bigint(20) UNSIGNED DEFAULT NULL AFTER owner_approval_at");
        }

        // Check if point_owner_id column exists in history table (to track who needs to approve)
        $point_owner_id_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `$safe_history` LIKE %s",
            'point_owner_id'
        ));
        if (empty($point_owner_id_exists)) {
            $wpdb->query("ALTER TABLE `$safe_history` ADD COLUMN point_owner_id bigint(20) UNSIGNED DEFAULT NULL AFTER user_id");
        }

        // Check if case_id column exists (for unique report case numbers)
        if (!$column_exists('case_id')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN case_id varchar(20) DEFAULT NULL AFTER id");

            // Generate case IDs for existing zgłoszenie type points
            $wpdb->query("
                UPDATE `$safe_table`
                SET case_id = CONCAT('ZGL-', LPAD(id, 6, '0'))
                WHERE type = 'zgloszenie' AND case_id IS NULL
            ");
        }

        // Check if resolved_delete_at column exists (for auto-deletion of resolved reports after 7 days)
        if (!$column_exists('resolved_delete_at')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN resolved_delete_at datetime DEFAULT NULL AFTER report_status");
        }

        // Check if resolved_summary column exists (for resolved summary)
        if (!$column_exists('resolved_summary')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN resolved_summary text DEFAULT NULL AFTER resolved_delete_at");
        }

        // Check if rejected_reason column exists (for rejection explanation)
        if (!$column_exists('rejected_reason')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN rejected_reason text DEFAULT NULL AFTER resolved_summary");
        }

        // Check if rejected_delete_at column exists (for auto-deletion of rejected reports after 7 days)
        if (!$column_exists('rejected_delete_at')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN rejected_delete_at datetime DEFAULT NULL AFTER rejected_reason");
        }

        // Check if edit_locked column exists (for locking pins from being edited)
        if (!$column_exists('edit_locked')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        // Modify report_status column to support longer status names (needs_better_documentation = 27 chars)
        $report_status_size = $wpdb->get_row($wpdb->prepare(
            "SHOW COLUMNS FROM `$safe_table` LIKE %s",
            'report_status'
        ));
        if ($report_status_size && strpos($report_status_size->Type, 'varchar(20)') !== false) {
            $wpdb->query("ALTER TABLE `$safe_table` MODIFY COLUMN report_status varchar(50) DEFAULT 'added'");
        }

        // Check if tags column exists (for point tagging)
        if (!$column_exists('tags')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN tags varchar(500) DEFAULT NULL AFTER ip_address");
        }

        // Run migration to strip slashes from existing data (one-time)
        self::migrate_strip_slashes();

        // Cache the schema version to avoid running these checks on every page load
        update_option('jg_map_schema_version', $current_schema_version);
    }

    /**
     * Migration: Generate slugs for all existing points that don't have them
     */
    public static function migrate_generate_slugs() {
        global $wpdb;
        $table = self::get_points_table();


        // Get all points without slugs
        $points = $wpdb->get_results(
            "SELECT id, title FROM $table WHERE slug IS NULL OR slug = '' ORDER BY id ASC",
            ARRAY_A
        );

        $updated = 0;
        $errors = 0;

        foreach ($points as $point) {
            if (empty($point['title'])) {
                continue;
            }

            $slug = self::generate_unique_slug($point['title'], $point['id']);

            $result = $wpdb->update(
                $table,
                array('slug' => $slug),
                array('id' => $point['id']),
                array('%s'),
                array('%d')
            );

            if ($result !== false) {
                $updated++;
            } else {
                $errors++;
            }
        }

    }

    /**
     * Migration: Strip slashes from existing data that was saved with WordPress magic quotes
     * This fixes the issue where quotes appear as \" in the database
     */
    public static function migrate_strip_slashes() {
        // Check if migration already ran
        if (get_option('jg_map_slashes_migrated', false)) {
            return;
        }

        global $wpdb;
        $table = self::get_points_table();

        // Update title, content, address fields that contain escaped quotes
        $wpdb->query("UPDATE $table SET title = REPLACE(title, '\\\\\"', '\"') WHERE title LIKE '%\\\\\"%'");
        $wpdb->query("UPDATE $table SET title = REPLACE(title, \"\\\\\'\", \"'\") WHERE title LIKE '%\\\\\\'%'");
        $wpdb->query("UPDATE $table SET content = REPLACE(content, '\\\\\"', '\"') WHERE content LIKE '%\\\\\"%'");
        $wpdb->query("UPDATE $table SET content = REPLACE(content, \"\\\\\'\", \"'\") WHERE content LIKE '%\\\\\\'%'");
        $wpdb->query("UPDATE $table SET address = REPLACE(address, '\\\\\"', '\"') WHERE address LIKE '%\\\\\"%'");
        $wpdb->query("UPDATE $table SET address = REPLACE(address, \"\\\\\'\", \"'\") WHERE address LIKE '%\\\\\\'%'");

        // Mark migration as complete
        update_option('jg_map_slashes_migrated', true);
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
     * Generate SEO-friendly slug from title
     */
    public static function generate_slug($title) {
        $slug = strtolower($title);

        // Polish characters transliteration
        $polish = array('ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż', 'Ą', 'Ć', 'Ę', 'Ł', 'Ń', 'Ó', 'Ś', 'Ź', 'Ż');
        $latin = array('a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z');
        $slug = str_replace($polish, $latin, $slug);

        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Limit length
        $slug = substr($slug, 0, 200);

        return $slug;
    }

    /**
     * Generate unique slug for a point
     */
    public static function generate_unique_slug($title, $point_id = null) {
        global $wpdb;
        $table = self::get_points_table();

        $base_slug = self::generate_slug($title);
        $slug = $base_slug;
        $counter = 2;

        // Keep trying until we find a unique slug
        while (true) {
            // Check if slug exists (excluding current point if updating)
            if ($point_id) {
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $table WHERE slug = %s AND id != %d",
                        $slug,
                        $point_id
                    )
                );
            } else {
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $table WHERE slug = %s",
                        $slug
                    )
                );
            }

            if ($exists == 0) {
                return $slug;
            }

            // Slug exists, try with number suffix
            $slug = $base_slug . '-' . $counter;
            $counter++;

            // Safety limit to prevent infinite loop
            if ($counter > 1000) {
                $slug = $base_slug . '-' . uniqid();
                break;
            }
        }

        return $slug;
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
     * Get relevance votes table name
     */
    public static function get_relevance_votes_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_relevance_votes';
    }

    /**
     * Get all published points
     */
    public static function get_published_points($include_pending = false) {
        global $wpdb;
        $table = self::get_points_table();

        // Ensure edit_locked column exists (in case migration hasn't run yet)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'edit_locked'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
            // Clear cache after adding column to ensure fresh data
            self::invalidate_points_cache();
        }

        // PERFORMANCE OPTIMIZATION: Use transient cache (30 seconds)
        // Cache key includes $include_pending to avoid conflicts
        $cache_key = $include_pending ? 'jg_map_points_with_pending' : 'jg_map_points_published';
        $cached_results = get_transient($cache_key);

        if ($cached_results !== false) {
            return $cached_results;
        }

        // Exclude trashed points from all queries
        $status_condition = $include_pending
            ? "status IN ('publish', 'pending', 'edit') AND status != 'trash'"
            : "status = 'publish' AND status != 'trash'";

        $sql = "SELECT id, case_id, title, slug, content, excerpt, lat, lng, type, category, status, report_status,
                       resolved_delete_at, resolved_summary, rejected_reason, rejected_delete_at, author_id, author_hidden, edit_locked, is_deletion_requested, deletion_reason,
                       deletion_requested_at, is_promo, promo_until, website, phone,
                       cta_enabled, cta_type, admin_note, images, featured_image_index,
                       facebook_url, instagram_url, linkedin_url, tiktok_url,
                       stats_views, stats_phone_clicks, stats_website_clicks, stats_social_clicks,
                       stats_cta_clicks, stats_gallery_clicks, stats_first_viewed, stats_last_viewed,
                       stats_unique_visitors, stats_avg_time_spent,
                       address, created_at, updated_at, ip_address, tags
                FROM $table WHERE $status_condition ORDER BY created_at DESC";

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Cache results for 30 seconds
        set_transient($cache_key, $results, 30);

        return $results;
    }

    /**
     * Get all unique tags from published points (for autocomplete)
     */
    public static function get_all_tags() {
        global $wpdb;
        $table = self::get_points_table();

        $cached = get_transient('jg_map_all_tags');
        if ($cached !== false) {
            return $cached;
        }

        $rows = $wpdb->get_col(
            "SELECT DISTINCT tags FROM $table WHERE status = 'publish' AND tags IS NOT NULL AND tags != ''"
        );

        $all_tags = array();
        foreach ($rows as $tags_json) {
            $tags = json_decode($tags_json, true);
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if ($tag !== '') {
                        $lower = mb_strtolower($tag);
                        if (!isset($all_tags[$lower])) {
                            $all_tags[$lower] = $tag;
                        }
                    }
                }
            }
        }

        $result = array_values($all_tags);
        sort($result);

        set_transient('jg_map_all_tags', $result, 300);

        return $result;
    }

    /**
     * Invalidate points cache - call this whenever point data changes
     */
    public static function invalidate_points_cache() {
        delete_transient('jg_map_points_published');
        delete_transient('jg_map_points_with_pending');
        delete_transient('jg_map_all_tags');

        // Regenerate sitemap cache so Google always gets a fresh static file
        $plugin = JG_Interactive_Map::get_instance();
        $plugin->regenerate_sitemap_cache();
    }

    /**
     * Get user's pending points (for regular users to see their own pending places)
     */
    public static function get_user_pending_points($user_id) {
        global $wpdb;
        $table = self::get_points_table();

        // Ensure edit_locked column exists (in case migration hasn't run yet)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'edit_locked'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        $sql = $wpdb->prepare(
            "SELECT id, case_id, title, slug, content, excerpt, lat, lng, type, category, status, report_status,
                    resolved_delete_at, rejected_reason, rejected_delete_at, author_id, author_hidden, edit_locked, is_deletion_requested, deletion_reason,
                    deletion_requested_at, is_promo, promo_until, website, phone,
                    cta_enabled, cta_type, admin_note, images, featured_image_index,
                    facebook_url, instagram_url, linkedin_url, tiktok_url,
                    stats_views, stats_phone_clicks, stats_website_clicks, stats_social_clicks,
                    stats_cta_clicks, stats_gallery_clicks, stats_first_viewed, stats_last_viewed,
                    stats_unique_visitors, stats_avg_time_spent,
                    address, created_at, updated_at, ip_address, tags
             FROM $table
             WHERE author_id = %d AND status = 'pending'
             ORDER BY created_at DESC",
            $user_id
        );

        $results = $wpdb->get_results($sql, ARRAY_A);


        return $results;
    }

    /**
     * Get point by ID
     */
    public static function get_point($point_id) {
        global $wpdb;
        $table = self::get_points_table();

        // Ensure edit_locked column exists (in case migration hasn't run yet)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'edit_locked'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, case_id, title, slug, content, excerpt, lat, lng, type, category, status, report_status,
                        resolved_delete_at, resolved_summary, rejected_reason, rejected_delete_at, author_id, author_hidden, edit_locked, is_deletion_requested, deletion_reason,
                        deletion_requested_at, is_promo, promo_until, website, phone,
                        cta_enabled, cta_type, admin_note, images, featured_image_index,
                        facebook_url, instagram_url, linkedin_url, tiktok_url,
                        stats_views, stats_phone_clicks, stats_website_clicks, stats_social_clicks,
                        stats_cta_clicks, stats_gallery_clicks, stats_first_viewed, stats_last_viewed,
                        stats_unique_visitors, stats_avg_time_spent,
                        address, created_at, updated_at, ip_address, tags
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

        // Auto-generate slug if not provided
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = self::generate_unique_slug($data['title']);
        }

        $result = $wpdb->insert($table, $data);
        if ($result === false) {
            return false;
        }

        $insert_id = $wpdb->insert_id;

        // Auto-generate case_id for zgłoszenie type if not provided
        if (!empty($data['type']) && $data['type'] === 'zgloszenie' && empty($data['case_id'])) {
            $case_id = 'ZGL-' . str_pad($insert_id, 6, '0', STR_PAD_LEFT);
            $wpdb->update(
                $table,
                array('case_id' => $case_id),
                array('id' => $insert_id),
                array('%s'),
                array('%d')
            );
        }

        // Invalidate points cache after insert
        self::invalidate_points_cache();

        return $insert_id;
    }

    /**
     * Update point
     */
    public static function update_point($point_id, $data) {
        global $wpdb;
        $table = self::get_points_table();

        // If title is being updated, regenerate slug if not explicitly provided
        if (isset($data['title']) && empty($data['slug'])) {
            $data['slug'] = self::generate_unique_slug($data['title'], $point_id);
        }

        $result = $wpdb->update(
            $table,
            $data,
            array('id' => $point_id)
        );

        // Invalidate points cache after update
        if ($result !== false) {
            self::invalidate_points_cache();
        }

        return $result;
    }

    /**
     * Soft delete point (move to trash)
     */
    public static function soft_delete_point($point_id) {
        global $wpdb;
        $points_table = self::get_points_table();

        $result = $wpdb->update(
            $points_table,
            array('status' => 'trash'),
            array('id' => $point_id),
            array('%s'),
            array('%d')
        );

        // Invalidate points cache after soft deletion
        if ($result !== false) {
            self::invalidate_points_cache();
        }

        return $result;
    }

    /**
     * Restore point from trash
     */
    public static function restore_point($point_id) {
        global $wpdb;
        $points_table = self::get_points_table();

        $result = $wpdb->update(
            $points_table,
            array('status' => 'publish'),
            array('id' => $point_id),
            array('%s'),
            array('%d')
        );

        // Invalidate points cache after restore
        if ($result !== false) {
            self::invalidate_points_cache();
        }

        return $result;
    }

    /**
     * Delete point permanently (with related data)
     */
    public static function delete_point($point_id) {
        global $wpdb;
        $points_table = self::get_points_table();
        $votes_table = self::get_votes_table();
        $reports_table = self::get_reports_table();
        $history_table = self::get_history_table();

        // Get point data before deletion to clean up images
        $point = self::get_point($point_id);

        // Delete physical image files from filesystem
        if ($point && !empty($point['images'])) {
            self::delete_point_images($point['images']);
        }

        // Delete related data first
        $wpdb->delete($votes_table, array('point_id' => $point_id), array('%d'));
        $wpdb->delete($reports_table, array('point_id' => $point_id), array('%d'));
        $wpdb->delete($history_table, array('point_id' => $point_id), array('%d'));

        // Delete the point itself
        $result = $wpdb->delete(
            $points_table,
            array('id' => $point_id),
            array('%d')
        );

        // Invalidate points cache after deletion
        if ($result !== false) {
            self::invalidate_points_cache();
        }

        return $result;
    }

    /**
     * Delete physical image files from filesystem
     *
     * @param string $images_json JSON string containing image URLs
     */
    private static function delete_point_images($images_json) {
        if (empty($images_json)) {
            return;
        }

        $images = json_decode($images_json, true);
        if (!is_array($images) || empty($images)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $upload_base_url = $upload_dir['baseurl'];
        $upload_base_path = $upload_dir['basedir'];

        foreach ($images as $image) {
            // Delete full size image
            if (!empty($image['full'])) {
                $file_path = str_replace($upload_base_url, $upload_base_path, $image['full']);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }

            // Delete thumbnail
            if (!empty($image['thumb']) && $image['thumb'] !== $image['full']) {
                $thumb_path = str_replace($upload_base_url, $upload_base_path, $image['thumb']);
                if (file_exists($thumb_path)) {
                    @unlink($thumb_path);
                }
            }
        }
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
     * BATCH LOADING METHODS - Performance optimization to prevent N+1 queries
     * These methods load data for multiple points at once using IN clauses
     */

    /**
     * Get votes counts for multiple points at once (batch loading)
     *
     * @param array $point_ids Array of point IDs
     * @return array Associative array [point_id => vote_count]
     */
    public static function get_votes_counts_batch($point_ids) {
        if (empty($point_ids)) {
            return array();
        }

        global $wpdb;
        $table = self::get_votes_table();

        // Sanitize point IDs
        $point_ids = array_map('intval', $point_ids);
        $ids_placeholder = implode(',', array_fill(0, count($point_ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT point_id,
                    SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) as up_votes,
                    SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) as down_votes
             FROM $table
             WHERE point_id IN ($ids_placeholder)
             GROUP BY point_id",
            ...$point_ids
        ), ARRAY_A);

        // Return as associative array indexed by point_id
        $votes_map = array();
        foreach ($results as $row) {
            $votes_map[intval($row['point_id'])] = intval($row['up_votes']) - intval($row['down_votes']);
        }

        // Fill in zeros for points with no votes
        foreach ($point_ids as $point_id) {
            if (!isset($votes_map[$point_id])) {
                $votes_map[$point_id] = 0;
            }
        }

        return $votes_map;
    }

    /**
     * Get user votes for multiple points at once (batch loading)
     *
     * @param array $point_ids Array of point IDs
     * @param int $user_id User ID
     * @return array Associative array [point_id => vote_type]
     */
    public static function get_user_votes_batch($point_ids, $user_id) {
        if (empty($point_ids) || !$user_id) {
            return array();
        }

        global $wpdb;
        $table = self::get_votes_table();

        // Sanitize point IDs
        $point_ids = array_map('intval', $point_ids);
        $ids_placeholder = implode(',', array_fill(0, count($point_ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT point_id, vote_type
             FROM $table
             WHERE point_id IN ($ids_placeholder)
             AND user_id = %d",
            ...array_merge($point_ids, array($user_id))
        ), ARRAY_A);

        // Return as associative array indexed by point_id
        $votes_map = array();
        foreach ($results as $row) {
            $votes_map[intval($row['point_id'])] = $row['vote_type'];
        }

        return $votes_map;
    }

    /**
     * Get reports counts for multiple points at once (batch loading)
     *
     * @param array $point_ids Array of point IDs
     * @return array Associative array [point_id => reports_count]
     */
    public static function get_reports_counts_batch($point_ids) {
        if (empty($point_ids)) {
            return array();
        }

        global $wpdb;
        $table = self::get_reports_table();

        // Sanitize point IDs
        $point_ids = array_map('intval', $point_ids);
        $ids_placeholder = implode(',', array_fill(0, count($point_ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT point_id, COUNT(*) as reports_count
             FROM $table
             WHERE point_id IN ($ids_placeholder)
             AND status = 'pending'
             GROUP BY point_id",
            ...$point_ids
        ), ARRAY_A);

        // Return as associative array indexed by point_id
        $reports_map = array();
        foreach ($results as $row) {
            $reports_map[intval($row['point_id'])] = intval($row['reports_count']);
        }

        // Fill in zeros for points with no reports
        foreach ($point_ids as $point_id) {
            if (!isset($reports_map[$point_id])) {
                $reports_map[$point_id] = 0;
            }
        }

        return $reports_map;
    }

    /**
     * Check if user reported multiple points (batch loading)
     *
     * @param array $point_ids Array of point IDs
     * @param int $user_id User ID
     * @return array Associative array [point_id => has_reported (bool)]
     */
    public static function has_user_reported_batch($point_ids, $user_id) {
        if (empty($point_ids) || !$user_id) {
            return array();
        }

        global $wpdb;
        $table = self::get_reports_table();

        // Sanitize point IDs
        $point_ids = array_map('intval', $point_ids);
        $ids_placeholder = implode(',', array_fill(0, count($point_ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT point_id
             FROM $table
             WHERE point_id IN ($ids_placeholder)
             AND user_id = %d
             AND status = 'pending'",
            ...array_merge($point_ids, array($user_id))
        ), ARRAY_A);

        // Return as associative array indexed by point_id
        $reported_map = array();

        // Initialize all as false
        foreach ($point_ids as $point_id) {
            $reported_map[$point_id] = false;
        }

        // Set true for reported points
        foreach ($results as $row) {
            $reported_map[intval($row['point_id'])] = true;
        }

        return $reported_map;
    }

    /**
     * Get pending histories for multiple points at once (batch loading)
     *
     * @param array $point_ids Array of point IDs
     * @return array Associative array [point_id => [history_records]]
     */
    public static function get_pending_histories_batch($point_ids) {
        if (empty($point_ids)) {
            return array();
        }

        global $wpdb;
        $table = self::get_history_table();

        // Sanitize point IDs
        $point_ids = array_map('intval', $point_ids);
        $ids_placeholder = implode(',', array_fill(0, count($point_ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM $table
             WHERE point_id IN ($ids_placeholder)
             AND status = 'pending'
             ORDER BY created_at DESC",
            ...$point_ids
        ), ARRAY_A);

        // Group by point_id
        $histories_map = array();
        foreach ($results as $row) {
            $point_id = intval($row['point_id']);
            if (!isset($histories_map[$point_id])) {
                $histories_map[$point_id] = array();
            }
            $histories_map[$point_id][] = $row;
        }

        return $histories_map;
    }

    /**
     * Get rejected histories for multiple points at once (batch loading)
     *
     * @param array $point_ids Array of point IDs
     * @param int $days_ago Number of days to look back (default 30)
     * @return array Associative array [point_id => [history_records]]
     */
    public static function get_rejected_histories_batch($point_ids, $days_ago = 30) {
        if (empty($point_ids)) {
            return array();
        }

        global $wpdb;
        $table = self::get_history_table();

        // Sanitize point IDs
        $point_ids = array_map('intval', $point_ids);
        $ids_placeholder = implode(',', array_fill(0, count($point_ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM $table
             WHERE point_id IN ($ids_placeholder)
             AND status = 'rejected'
             AND resolved_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             ORDER BY resolved_at DESC",
            ...array_merge($point_ids, array($days_ago))
        ), ARRAY_A);

        // Group by point_id
        $histories_map = array();
        foreach ($results as $row) {
            $point_id = intval($row['point_id']);
            if (!isset($histories_map[$point_id])) {
                $histories_map[$point_id] = array();
            }
            $histories_map[$point_id][] = $row;
        }

        return $histories_map;
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
                'status' => 'pending',
                'created_at' => current_time('mysql', true) // ALWAYS save as GMT
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
                'resolved_at' => current_time('mysql', true)  // GMT time
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
    public static function add_history($point_id, $user_id, $action_type, $old_values, $new_values, $point_owner_id = null) {
        global $wpdb;

        // Ensure history table exists
        self::ensure_history_table();

        $table = self::get_history_table();

        $data = array(
            'point_id' => $point_id,
            'user_id' => $user_id,
            'action_type' => $action_type,
            'old_values' => is_array($old_values) ? json_encode($old_values) : $old_values,
            'new_values' => is_array($new_values) ? json_encode($new_values) : $new_values,
            'status' => 'pending'
        );

        // If point_owner_id is provided, this requires owner approval first
        if ($point_owner_id !== null) {
            $data['point_owner_id'] = $point_owner_id;
            $data['owner_approval_status'] = 'pending';
        }

        return $wpdb->insert($table, $data);
    }

    /**
     * Add history entry for direct admin/mod edits (auto-approved).
     */
    public static function add_admin_edit_history($point_id, $admin_user_id, $old_values, $new_values) {
        global $wpdb;

        self::ensure_history_table();

        $table = self::get_history_table();

        return $wpdb->insert($table, array(
            'point_id'    => $point_id,
            'user_id'     => $admin_user_id,
            'action_type' => 'edit',
            'old_values'  => is_array($old_values) ? json_encode($old_values) : $old_values,
            'new_values'  => is_array($new_values) ? json_encode($new_values) : $new_values,
            'status'      => 'approved',
            'resolved_at' => current_time('mysql', true),
            'resolved_by' => $admin_user_id,
        ));
    }

    /**
     * Get ALL pending history entries for a point (can be multiple: edit + deletion)
     */
    public static function get_pending_history($point_id) {
        global $wpdb;

        // Ensure history table exists
        self::ensure_history_table();

        $table = self::get_history_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, point_id, user_id, action_type, old_values, new_values,
                        status, created_at, resolved_at, resolved_by, rejection_reason
                 FROM $table WHERE point_id = %d AND status = 'pending'
                 ORDER BY created_at ASC",
                $point_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get recently rejected history for a point (for showing rejection reasons to authors)
     */
    public static function get_rejected_history($point_id, $days = 30) {
        global $wpdb;
        $table = self::get_history_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, point_id, user_id, action_type, old_values, new_values,
                        status, created_at, resolved_at, resolved_by, rejection_reason
                 FROM $table
                 WHERE point_id = %d
                 AND status = 'rejected'
                 AND resolved_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                 ORDER BY resolved_at DESC",
                $point_id,
                $days
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
                        status, created_at, resolved_at, resolved_by, rejection_reason
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
                'resolved_at' => current_time('mysql', true),  // GMT time
                'resolved_by' => $admin_id
            ),
            array('id' => $history_id)
        );
    }

    /**
     * Reject history entry
     */
    public static function reject_history($history_id, $admin_id, $rejection_reason = '') {
        global $wpdb;
        $table = self::get_history_table();

        $update_data = array(
            'status' => 'rejected',
            'resolved_at' => current_time('mysql', true),  // GMT time
            'resolved_by' => $admin_id
        );

        // Add rejection reason if provided
        if (!empty($rejection_reason)) {
            $update_data['rejection_reason'] = $rejection_reason;
        }

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $history_id)
        );
    }

    /**
     * Get all places with their extended status and priority
     * Returns places grouped by their moderation status with priority levels
     */
    public static function get_all_places_with_status($search = '', $status_filter = '', $user_id = 0) {
        global $wpdb;

        $points_table = self::get_points_table();
        $reports_table = self::get_reports_table();
        $history_table = self::get_history_table();

        // Base query - get all places (including trash)
        $where_conditions = array("1=1");

        // Add user filter if provided
        if (!empty($user_id)) {
            $where_conditions[] = $wpdb->prepare("p.author_id = %d", $user_id);
        }

        // Add search filter if provided
        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = $wpdb->prepare(
                "(p.title LIKE %s OR p.content LIKE %s OR p.address LIKE %s OR u.display_name LIKE %s)",
                $search_term, $search_term, $search_term, $search_term
            );
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        $query = "
            SELECT
                p.*,
                u.display_name as author_name,
                u.user_email as author_email,
                (SELECT COUNT(*) FROM $reports_table r
                 WHERE r.point_id = p.id AND r.status = 'pending') as pending_reports_count,
                (SELECT COUNT(*) FROM $history_table h
                 WHERE h.point_id = p.id AND h.status = 'pending' AND h.action_type = 'edit') as pending_edits_count,
                (SELECT created_at FROM $history_table h
                 WHERE h.point_id = p.id AND h.status = 'pending' AND h.action_type = 'edit'
                 ORDER BY created_at DESC LIMIT 1) as latest_edit_date,
                (SELECT u2.display_name FROM $reports_table r2
                 LEFT JOIN {$wpdb->users} u2 ON r2.user_id = u2.ID
                 WHERE r2.point_id = p.id AND r2.status = 'pending'
                 ORDER BY r2.created_at DESC LIMIT 1) as reporter_name,
                (SELECT r2.user_id FROM $reports_table r2
                 WHERE r2.point_id = p.id AND r2.status = 'pending'
                 ORDER BY r2.created_at DESC LIMIT 1) as reporter_id,
                (SELECT u3.display_name FROM $history_table h3
                 LEFT JOIN {$wpdb->users} u3 ON h3.user_id = u3.ID
                 WHERE h3.point_id = p.id AND h3.status = 'approved' AND h3.action_type = 'edit'
                 ORDER BY h3.resolved_at DESC LIMIT 1) as last_modifier_name,
                (SELECT h3.resolved_at FROM $history_table h3
                 WHERE h3.point_id = p.id AND h3.status = 'approved' AND h3.action_type = 'edit'
                 ORDER BY h3.resolved_at DESC LIMIT 1) as last_modified_at
            FROM $points_table p
            LEFT JOIN {$wpdb->users} u ON p.author_id = u.ID
            $where_clause
        ";

        $places = $wpdb->get_results($query, ARRAY_A);

        // Process each place to determine its display status and priority
        $processed_places = array();
        foreach ($places as $place) {
            // Determine display status and priority
            if ($place['pending_reports_count'] > 0 && $place['status'] === 'publish') {
                $place['display_status'] = 'reported';
                $place['display_status_label'] = 'Zgłoszone do sprawdzenia przez moderację';
                $place['priority'] = 3;
            } elseif ($place['status'] === 'pending') {
                $place['display_status'] = 'new_pending';
                $place['display_status_label'] = 'Nowe miejsce czekające na zatwierdzenie';
                $place['priority'] = 2;
            } elseif ($place['pending_edits_count'] > 0 && $place['status'] === 'publish') {
                $place['display_status'] = 'edit_pending';
                $place['display_status_label'] = 'Oczekuje na zatwierdzenie edycji';
                $place['priority'] = 2;
            } elseif ($place['is_deletion_requested'] == 1 && $place['status'] === 'publish') {
                $place['display_status'] = 'deletion_pending';
                $place['display_status_label'] = 'Oczekuje na usunięcie';
                $place['priority'] = 1;
            } elseif ($place['status'] === 'publish') {
                $place['display_status'] = 'published';
                $place['display_status_label'] = 'Opublikowane';
                $place['priority'] = 1;
            } elseif ($place['status'] === 'trash') {
                $place['display_status'] = 'trash';
                $place['display_status_label'] = 'W koszu';
                $place['priority'] = 0;
            } else {
                // Fallback for any other status
                $place['display_status'] = 'other';
                $place['display_status_label'] = 'Inny status';
                $place['priority'] = 0;
            }

            // Filter by status if specified
            if (!empty($status_filter) && $place['display_status'] !== $status_filter) {
                continue;
            }

            $processed_places[] = $place;
        }

        // Sort by priority (descending) and then by created_at (descending)
        usort($processed_places, function($a, $b) {
            if ($a['priority'] != $b['priority']) {
                return $b['priority'] - $a['priority']; // Higher priority first
            }
            // Within same priority, newer first
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $processed_places;
    }

    /**
     * Get count of places by display status
     */
    public static function get_places_count_by_status($user_id = 0) {
        $places = self::get_all_places_with_status('', '', $user_id);

        $counts = array(
            'reported' => 0,
            'new_pending' => 0,
            'edit_pending' => 0,
            'deletion_pending' => 0,
            'published' => 0,
            'trash' => 0,
            'total' => count($places)
        );

        foreach ($places as $place) {
            if (isset($counts[$place['display_status']])) {
                $counts[$place['display_status']]++;
            }
        }

        return $counts;
    }
}
