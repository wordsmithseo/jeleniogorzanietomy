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
        // NOTE: Table names cannot be parameterized via $wpdb->prepare() — use esc_sql() + interpolation instead
        $safe_table = esc_sql($table_points);
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$safe_table` LIKE %s", 'category'));
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

        // Table for slug redirects (when a point's title/slug changes)
        $table_slug_redirects = $wpdb->prefix . 'jg_map_slug_redirects';

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
            email varchar(255) DEFAULT NULL,
            cta_enabled tinyint(1) DEFAULT 0,
            cta_type varchar(20) DEFAULT NULL,
            is_deletion_requested tinyint(1) DEFAULT 0,
            deletion_reason text DEFAULT NULL,
            deletion_requested_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            approved_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            tags varchar(500) DEFAULT NULL,
            opening_hours text DEFAULT NULL,
            pending_edit tinyint(1) DEFAULT 0,
            price_range varchar(10) DEFAULT NULL,
            serves_cuisine varchar(255) DEFAULT NULL,
            ip_address varchar(100),
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY author_id (author_id),
            KEY status (status),
            KEY type (type),
            KEY status_type (status, type),
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

        // Slug redirects table SQL (stores old slugs for 301 redirects after title changes)
        $sql_slug_redirects = "CREATE TABLE IF NOT EXISTS $table_slug_redirects (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            old_slug varchar(255) NOT NULL,
            point_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY old_slug (old_slug),
            KEY point_id (point_id)
        ) $charset_collate;";

        // Table for menu sections
        $table_menu_sections = $wpdb->prefix . 'jg_map_menu_sections';
        $sql_menu_sections = "CREATE TABLE IF NOT EXISTS $table_menu_sections (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY point_id (point_id)
        ) $charset_collate;";

        // Table for menu items
        $table_menu_items = $wpdb->prefix . 'jg_map_menu_items';
        $sql_menu_items = "CREATE TABLE IF NOT EXISTS $table_menu_items (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            section_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            price decimal(8,2) DEFAULT NULL,
            variants text DEFAULT NULL,
            dietary_tags varchar(255) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            is_available tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY point_id (point_id),
            KEY section_id (section_id)
        ) $charset_collate;";

        // Table for menu card photos (Type A - scans of physical menu)
        $table_menu_photos = $wpdb->prefix . 'jg_map_menu_photos';
        $sql_menu_photos = "CREATE TABLE IF NOT EXISTS $table_menu_photos (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            url varchar(500) NOT NULL,
            thumb_url varchar(500) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY point_id (point_id)
        ) $charset_collate;";

        // Table for offerings (services / products list per place)
        $table_offerings = $wpdb->prefix . 'jg_map_offerings';
        $sql_offerings = "CREATE TABLE IF NOT EXISTS $table_offerings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            price decimal(8,2) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            is_available tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY point_id (point_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_points);
        dbDelta($sql_votes);
        dbDelta($sql_reports);
        dbDelta($sql_history);
        dbDelta($sql_relevance_votes);
        dbDelta($sql_point_visits);
        dbDelta($sql_slug_redirects);
        dbDelta($sql_menu_sections);
        dbDelta($sql_menu_items);
        dbDelta($sql_menu_photos);
        dbDelta($sql_offerings);

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

        // Create challenges table
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-challenges.php';
        JG_Map_Challenges::create_table();

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
        $current_schema_version = '3.28.1'; // Add composite index (status, type) for performance
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

        // Ensure challenges table exists (for existing installations)
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-challenges.php';
        JG_Map_Challenges::create_table();

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

        // Check if email column exists (contact email for all points)
        if (!$column_exists('email')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN email varchar(255) DEFAULT NULL AFTER phone");
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

        // Check if opening_hours column exists (for displaying business hours)
        if (!$column_exists('opening_hours')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN opening_hours text DEFAULT NULL AFTER tags");
        }

        // Check if pending_edit column exists (flags points with pending moderation edits)
        if (!$column_exists('pending_edit')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN pending_edit tinyint(1) DEFAULT 0 AFTER opening_hours");
        }

        // Check if price_range column exists (Google priceRange schema field)
        if (!$column_exists('price_range')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN price_range varchar(10) DEFAULT NULL AFTER pending_edit");
        }

        // Check if serves_cuisine column exists (Google servesCuisine schema field)
        if (!$column_exists('serves_cuisine')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN serves_cuisine varchar(255) DEFAULT NULL AFTER price_range");
        }

        // Fix tags stored with unicode escapes (e.g. "G\u00f3ry" -> "Góry")
        self::migrate_fix_unicode_tags();

        // Run migration to strip slashes from existing data (one-time)
        self::migrate_strip_slashes();

        // Fix slugs for points with special European characters in titles (e.g. Ä→a, Ö→o, Ü→u)
        self::migrate_fix_special_char_slugs();

        // Ensure slug_redirects table exists (for 301 redirects after title/slug changes)
        $table_slug_redirects = $wpdb->prefix . 'jg_map_slug_redirects';
        $sql_slug_redirects = "CREATE TABLE IF NOT EXISTS $table_slug_redirects (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            old_slug varchar(255) NOT NULL,
            point_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY old_slug (old_slug),
            KEY point_id (point_id)
        ) $charset_collate;";
        dbDelta($sql_slug_redirects);

        // Ensure menu tables exist
        $table_menu_sections = $wpdb->prefix . 'jg_map_menu_sections';
        $sql_menu_sections = "CREATE TABLE IF NOT EXISTS $table_menu_sections (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY point_id (point_id)
        ) $charset_collate;";
        dbDelta($sql_menu_sections);

        $table_menu_items = $wpdb->prefix . 'jg_map_menu_items';
        $sql_menu_items = "CREATE TABLE IF NOT EXISTS $table_menu_items (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            section_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            price decimal(8,2) DEFAULT NULL,
            variants text DEFAULT NULL,
            dietary_tags varchar(255) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            is_available tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY point_id (point_id),
            KEY section_id (section_id)
        ) $charset_collate;";
        dbDelta($sql_menu_items);

        // Add variants column to existing installations
        $safe_items_table = esc_sql($table_menu_items);
        $col_check = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$safe_items_table` LIKE %s", 'variants'));
        if (empty($col_check)) {
            $wpdb->query("ALTER TABLE `$safe_items_table` ADD COLUMN variants text DEFAULT NULL AFTER price");
        }

        // Add menu_size_labels column to points table (predefined size labels per place)
        if (!$column_exists('menu_size_labels')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN menu_size_labels text DEFAULT NULL");
        }

        // Add SEO columns: custom canonical URL and noindex flag (admin-only, not editable by users)
        if (!$column_exists('seo_canonical')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN seo_canonical varchar(500) DEFAULT NULL");
        }
        if (!$column_exists('seo_noindex')) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD COLUMN seo_noindex tinyint(1) DEFAULT 0");
        }

        $table_menu_photos = $wpdb->prefix . 'jg_map_menu_photos';
        $sql_menu_photos = "CREATE TABLE IF NOT EXISTS $table_menu_photos (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            url varchar(500) NOT NULL,
            thumb_url varchar(500) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY point_id (point_id)
        ) $charset_collate;";
        dbDelta($sql_menu_photos);

        // Ensure offerings table exists (for existing installations)
        $table_offerings = $wpdb->prefix . 'jg_map_offerings';
        $sql_offerings = "CREATE TABLE IF NOT EXISTS $table_offerings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            point_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            price decimal(8,2) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            is_available tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY point_id (point_id)
        ) $charset_collate;";
        dbDelta($sql_offerings);

        // Add composite index (status, type) if not present (speeds up the most common WHERE clauses)
        $index_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW INDEX FROM `$safe_table` WHERE Key_name = %s",
            'status_type'
        ));
        if (empty($index_exists)) {
            $wpdb->query("ALTER TABLE `$safe_table` ADD KEY status_type (status, type)");
        }

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
     * Migration: Fix tags stored with JSON unicode escapes (\u00f3 -> ó)
     * Re-encode tags with JSON_UNESCAPED_UNICODE so LIKE queries work with Polish characters
     */
    public static function migrate_fix_unicode_tags() {
        if (get_option('jg_map_tags_unicode_fixed', false)) {
            return;
        }

        global $wpdb;
        $table = self::get_points_table();

        // Find rows with unicode escapes in tags
        $rows = $wpdb->get_results(
            "SELECT id, tags FROM $table WHERE tags IS NOT NULL AND tags LIKE '%\\\\u0%'",
            ARRAY_A
        );

        foreach ($rows as $row) {
            $decoded = json_decode($row['tags'], true);
            if (is_array($decoded)) {
                $fixed = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                $wpdb->update($table, array('tags' => $fixed), array('id' => $row['id']));
            }
        }

        // Also fix tags in history table (old_values/new_values JSON)
        $history_table = self::get_history_table();
        $history_rows = $wpdb->get_results(
            "SELECT id, old_values, new_values FROM $history_table WHERE (old_values LIKE '%\\\\u0%' AND old_values LIKE '%tags%') OR (new_values LIKE '%\\\\u0%' AND new_values LIKE '%tags%')",
            ARRAY_A
        );

        foreach ($history_rows as $row) {
            $updates = array();
            foreach (array('old_values', 'new_values') as $field) {
                if (!empty($row[$field]) && strpos($row[$field], '\\u0') !== false) {
                    $data = json_decode($row[$field], true);
                    if (is_array($data) && isset($data['tags'])) {
                        $tags_val = is_string($data['tags']) ? json_decode($data['tags'], true) : $data['tags'];
                        if (is_array($tags_val)) {
                            $data['tags'] = json_encode($tags_val, JSON_UNESCAPED_UNICODE);
                        }
                        $updates[$field] = json_encode($data, JSON_UNESCAPED_UNICODE);
                    }
                }
            }
            if (!empty($updates)) {
                $wpdb->update($history_table, $updates, array('id' => $row['id']));
            }
        }

        update_option('jg_map_tags_unicode_fixed', true);
    }

    /**
     * Migration: Regenerate slugs for points whose titles contain special European characters
     * that were previously stripped (e.g. Ä, Ö, Ü) but should now be transliterated (ä→a, ö→o, ü→u)
     */
    public static function migrate_fix_special_char_slugs() {
        if (get_option('jg_map_special_char_slugs_fixed', false)) {
            return;
        }

        global $wpdb;
        $table = self::get_points_table();

        // Characters that are now transliterated but were previously stripped
        $special_chars = array('ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü',
            'à', 'â', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ù', 'û', 'ÿ', 'ç',
            'À', 'Â', 'É', 'È', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Ù', 'Û', 'Ÿ', 'Ç',
            'á', 'í', 'ú', 'ñ', 'ã', 'õ', 'Á', 'Í', 'Ú', 'Ñ', 'Ã', 'Õ',
            'č', 'š', 'ž', 'ř', 'ě', 'ý', 'ů', 'ď', 'ť',
            'Č', 'Š', 'Ž', 'Ř', 'Ě', 'Ý', 'Ů', 'Ď', 'Ť');

        // Build WHERE clause to find points with any of these characters in title
        $like_conditions = array();
        foreach ($special_chars as $char) {
            $like_conditions[] = $wpdb->prepare("title LIKE %s", '%' . $wpdb->esc_like($char) . '%');
        }
        $where = implode(' OR ', $like_conditions);

        $points = $wpdb->get_results(
            "SELECT id, title, slug FROM $table WHERE $where ORDER BY id ASC",
            ARRAY_A
        );

        foreach ($points as $point) {
            if (empty($point['title'])) {
                continue;
            }

            $new_slug = self::generate_unique_slug($point['title'], $point['id']);

            if ($new_slug !== $point['slug']) {
                // Save old slug as redirect before updating
                if (!empty($point['slug'])) {
                    self::save_slug_redirect($point['slug'], $point['id']);
                }

                $wpdb->update(
                    $table,
                    array('slug' => $new_slug),
                    array('id' => $point['id']),
                    array('%s'),
                    array('%d')
                );
            }
        }

        update_option('jg_map_special_char_slugs_fixed', true);
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

        // Polish and other European characters transliteration
        $special = array(
            // Polish
            'ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż',
            'Ą', 'Ć', 'Ę', 'Ł', 'Ń', 'Ó', 'Ś', 'Ź', 'Ż',
            // German
            'ä', 'ö', 'ü', 'ß',
            'Ä', 'Ö', 'Ü',
            // French
            'à', 'â', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ù', 'û', 'ü', 'ÿ', 'ç',
            'À', 'Â', 'É', 'È', 'Ê', 'Ë', 'Î', 'Ï', 'Ô', 'Ù', 'Û', 'Ü', 'Ÿ', 'Ç',
            // Spanish / Portuguese
            'á', 'í', 'ú', 'ñ', 'ã', 'õ',
            'Á', 'Í', 'Ú', 'Ñ', 'Ã', 'Õ',
            // Czech / Slovak / other Slavic
            'č', 'š', 'ž', 'ř', 'ě', 'ý', 'ů', 'ď', 'ť',
            'Č', 'Š', 'Ž', 'Ř', 'Ě', 'Ý', 'Ů', 'Ď', 'Ť',
        );
        $latin = array(
            // Polish
            'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z',
            'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z',
            // German
            'a', 'o', 'u', 's',
            'a', 'o', 'u',
            // French
            'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'u', 'u', 'u', 'y', 'c',
            'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'u', 'u', 'u', 'y', 'c',
            // Spanish / Portuguese
            'a', 'i', 'u', 'n', 'a', 'o',
            'a', 'i', 'u', 'n', 'a', 'o',
            // Czech / Slovak / other Slavic
            'c', 's', 'z', 'r', 'e', 'y', 'u', 'd', 't',
            'c', 's', 'z', 'r', 'e', 'y', 'u', 'd', 't',
        );
        $slug = str_replace($special, $latin, $slug);

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
     * One-time migration: convert legacy 'up'/'down' vote_type values to star ratings (5/1).
     * Runs once per site, tracked by option 'jg_map_votes_migrated_to_stars'.
     */
    public static function maybe_migrate_votes_to_stars() {
        if (get_option('jg_map_votes_migrated_to_stars')) {
            return;
        }
        global $wpdb;
        $table = self::get_votes_table();
        $wpdb->query("UPDATE $table SET vote_type = '5' WHERE vote_type = 'up'");
        $wpdb->query("UPDATE $table SET vote_type = '1' WHERE vote_type = 'down'");
        update_option('jg_map_votes_migrated_to_stars', 1);
    }

    /**
     * Get slug redirects table name
     */
    public static function get_slug_redirects_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_slug_redirects';
    }

    /**
     * Get all published points
     */
    public static function get_published_points($include_pending = false) {
        global $wpdb;
        $table = self::get_points_table();

        // PERFORMANCE OPTIMIZATION: Use transient cache (30 seconds)
        // Cache key includes $include_pending to avoid conflicts
        $cache_key = $include_pending ? 'jg_map_points_with_pending' : 'jg_map_points_published';
        $cached_results = get_transient($cache_key);

        if ($cached_results !== false) {
            return $cached_results;
        }

        // Ensure edit_locked column exists (only checked on cache miss, ~every 30s)
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'edit_locked'));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
            self::invalidate_points_cache();
        }

        // Exclude trashed points from all queries
        $status_condition = $include_pending
            ? "status IN ('publish', 'pending', 'edit') AND status != 'trash'"
            : "status = 'publish' AND status != 'trash'";

        $sql = "SELECT * FROM $table WHERE $status_condition ORDER BY created_at DESC";

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Cache results for 30 seconds
        set_transient($cache_key, $results, 30);

        return $results;
    }

    /**
     * Get all unique tags from published and pending points (for autocomplete)
     */
    public static function get_all_tags() {
        global $wpdb;
        $table = self::get_points_table();

        $cached = get_transient('jg_map_all_tags');
        if ($cached !== false) {
            return $cached;
        }

        $rows = $wpdb->get_col(
            "SELECT DISTINCT tags FROM $table WHERE status IN ('publish', 'pending') AND tags IS NOT NULL AND tags != ''"
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
     * Get all distinct place categories (from the `category` column), sorted alphabetically.
     * Returns an array of category name strings.
     */
    public static function get_all_place_categories() {
        global $wpdb;
        $table = self::get_points_table();

        $cached = get_transient('jg_map_all_place_categories');
        if ($cached !== false) {
            return $cached;
        }

        $rows = $wpdb->get_col(
            "SELECT DISTINCT category FROM $table WHERE status = 'publish' AND type = 'miejsce' AND category IS NOT NULL AND category != '' ORDER BY category ASC"
        );

        $result = array_values(array_filter(array_map('trim', $rows)));
        set_transient('jg_map_all_place_categories', $result, 300);

        return $result;
    }

    /**
     * Invalidate points cache - call this whenever point data changes
     */
    public static function invalidate_points_cache() {
        delete_transient('jg_map_points_published');
        delete_transient('jg_map_points_with_pending');
        delete_transient('jg_map_all_tags');
        delete_transient('jg_map_all_place_categories');

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
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'edit_locked'));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        $sql = $wpdb->prepare(
            "SELECT id, case_id, title, slug, content, excerpt, lat, lng, type, category, status, report_status,
                    resolved_delete_at, rejected_reason, rejected_delete_at, author_id, author_hidden, edit_locked, is_deletion_requested, deletion_reason,
                    deletion_requested_at, is_promo, promo_until, website, phone, email,
                    cta_enabled, cta_type, admin_note, images, featured_image_index,
                    facebook_url, instagram_url, linkedin_url, tiktok_url,
                    stats_views, stats_phone_clicks, stats_website_clicks, stats_social_clicks,
                    stats_cta_clicks, stats_gallery_clicks, stats_first_viewed, stats_last_viewed,
                    stats_unique_visitors, stats_avg_time_spent,
                    address, created_at, updated_at, ip_address, tags, opening_hours, pending_edit
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
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'edit_locked'));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
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
            // Fetch current slug before overwriting so we can store a redirect
            $current_slug = $wpdb->get_var(
                $wpdb->prepare("SELECT slug FROM $table WHERE id = %d", $point_id)
            );

            $data['slug'] = self::generate_unique_slug($data['title'], $point_id);

            // Save old slug as a redirect if it actually changed
            if (!empty($current_slug) && $current_slug !== $data['slug']) {
                self::save_slug_redirect($current_slug, $point_id);
            }
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
     * Save old slug to the redirects table so old URLs continue to work via 301 redirect.
     */
    public static function save_slug_redirect($old_slug, $point_id) {
        global $wpdb;
        $table = self::get_slug_redirects_table();

        // Check if the old_slug row already exists to avoid duplicate-key errors
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE old_slug = %s", $old_slug)
        );

        if ($existing) {
            // Update the target point in case the same old slug now points elsewhere
            $wpdb->update(
                $table,
                array('point_id' => $point_id),
                array('old_slug' => $old_slug),
                array('%d'),
                array('%s')
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'old_slug' => $old_slug,
                    'point_id' => $point_id,
                ),
                array('%s', '%d')
            );
        }
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

        // Wrap all DB deletes in a transaction so related rows are never
        // left orphaned if one statement fails mid-way
        $wpdb->query('START TRANSACTION');

        // Delete related data first
        $wpdb->delete($votes_table,   array('point_id' => $point_id), array('%d'));
        $wpdb->delete($reports_table, array('point_id' => $point_id), array('%d'));
        $wpdb->delete($history_table, array('point_id' => $point_id), array('%d'));

        // Delete the point itself
        $result = $wpdb->delete(
            $points_table,
            array('id' => $point_id),
            array('%d')
        );

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');

        // Delete physical image files from filesystem (after successful DB commit)
        if ($point && !empty($point['images'])) {
            self::delete_point_images($point['images']);
        }

        // Invalidate points cache after deletion
        self::invalidate_points_cache();

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
     * Get star rating data for a point.
     * Returns ['avg' => float 0-5, 'count' => int].
     */
    public static function get_rating_data($point_id) {
        global $wpdb;
        $table = self::get_votes_table();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT AVG(CAST(vote_type AS DECIMAL(3,1))) as avg_rating, COUNT(*) as total_count
                 FROM $table WHERE point_id = %d",
                $point_id
            ),
            ARRAY_A
        );

        if (!$row || $row['total_count'] == 0) {
            return array('avg' => 0.0, 'count' => 0);
        }

        return array(
            'avg'   => round(floatval($row['avg_rating']), 1),
            'count' => intval($row['total_count']),
        );
    }

    /**
     * @deprecated Use get_rating_data() instead.
     * Kept for backward compatibility - returns net score (no longer meaningful).
     */
    public static function get_votes_count($point_id) {
        $data = self::get_rating_data($point_id);
        return $data['count'];
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
     * Get star rating data for multiple points at once (batch loading).
     *
     * @param array $point_ids Array of point IDs
     * @return array Associative array [point_id => ['avg' => float, 'count' => int]]
     */
    public static function get_votes_counts_batch($point_ids) {
        if (empty($point_ids)) {
            return array();
        }

        global $wpdb;
        $table = self::get_votes_table();

        $point_ids = array_map('intval', $point_ids);
        $ids_placeholder = implode(',', array_fill(0, count($point_ids), '%d'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT point_id,
                    AVG(CAST(vote_type AS DECIMAL(3,1))) as avg_rating,
                    COUNT(*) as total_count
             FROM $table
             WHERE point_id IN ($ids_placeholder)
             GROUP BY point_id",
            ...$point_ids
        ), ARRAY_A);

        $votes_map = array();
        foreach ($results as $row) {
            $votes_map[intval($row['point_id'])] = array(
                'avg'   => round(floatval($row['avg_rating']), 1),
                'count' => intval($row['total_count']),
            );
        }

        foreach ($point_ids as $point_id) {
            if (!isset($votes_map[$point_id])) {
                $votes_map[$point_id] = array('avg' => 0.0, 'count' => 0);
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
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_history));

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

        $points_table  = self::get_points_table();
        $reports_table = self::get_reports_table();
        $history_table = self::get_history_table();

        // ── Query 1: all places + author name (simple JOIN, no subqueries) ─────
        $where_conditions = array('1=1');

        if (!empty($user_id)) {
            $where_conditions[] = $wpdb->prepare('p.author_id = %d', $user_id);
        }

        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = $wpdb->prepare(
                '(p.title LIKE %s OR p.content LIKE %s OR p.address LIKE %s OR u.display_name LIKE %s)',
                $search_term, $search_term, $search_term, $search_term
            );
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        $places_raw = $wpdb->get_results(
            "SELECT p.*, u.display_name AS author_name, u.user_email AS author_email
             FROM $points_table p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.author_id
             $where_clause",
            ARRAY_A
        );

        if (!is_array($places_raw) || empty($places_raw)) {
            return array();
        }

        // Index places by id for O(1) enrichment below
        $places = array();
        foreach ($places_raw as $row) {
            $row['pending_reports_count'] = 0;
            $row['pending_edits_count']   = 0;
            $row['latest_edit_date']      = null;
            $row['reporter_name']         = null;
            $row['reporter_id']           = null;
            $row['last_modifier_name']    = null;
            $row['last_modified_at']      = null;
            $places[$row['id']] = $row;
        }

        // ── Query 2: pending report counts per point ──────────────────────────
        $report_counts = $wpdb->get_results(
            "SELECT point_id, COUNT(*) AS cnt FROM $reports_table WHERE status = 'pending' GROUP BY point_id",
            ARRAY_A
        );
        if (is_array($report_counts)) {
            foreach ($report_counts as $row) {
                if (isset($places[$row['point_id']])) {
                    $places[$row['point_id']]['pending_reports_count'] = (int) $row['cnt'];
                }
            }
        }

        // ── Query 3: pending edit counts + latest edit date per point ─────────
        $history_counts = $wpdb->get_results(
            "SELECT point_id, COUNT(*) AS cnt, MAX(created_at) AS latest_edit_date
             FROM $history_table
             WHERE status = 'pending' AND action_type = 'edit'
             GROUP BY point_id",
            ARRAY_A
        );
        if (is_array($history_counts)) {
            foreach ($history_counts as $row) {
                if (isset($places[$row['point_id']])) {
                    $places[$row['point_id']]['pending_edits_count'] = (int) $row['cnt'];
                    $places[$row['point_id']]['latest_edit_date']    = $row['latest_edit_date'];
                }
            }
        }

        // ── Query 4: latest pending reporter per reported point ───────────────
        // Only fetch for points that actually have pending reports (small subset)
        $reported_ids = array();
        foreach ($places as $p) {
            if ($p['pending_reports_count'] > 0) {
                $reported_ids[] = (int) $p['id'];
            }
        }
        if (!empty($reported_ids)) {
            $ids_in = implode(',', $reported_ids);
            $reporters = $wpdb->get_results(
                "SELECT r.point_id, r.user_id, u2.display_name AS reporter_name
                 FROM $reports_table r
                 LEFT JOIN {$wpdb->users} u2 ON u2.ID = r.user_id
                 WHERE r.status = 'pending' AND r.point_id IN ($ids_in)
                 ORDER BY r.created_at DESC",
                ARRAY_A
            );
            if (is_array($reporters)) {
                $seen = array();
                foreach ($reporters as $row) {
                    $pid = $row['point_id'];
                    if (isset($places[$pid]) && !isset($seen[$pid])) {
                        $places[$pid]['reporter_id']   = $row['user_id'];
                        $places[$pid]['reporter_name'] = $row['reporter_name'];
                        $seen[$pid] = true;
                    }
                }
            }
        }

        // ── Query 5: latest approved editor per point ─────────────────────────
        // Only for points that have approved edits
        $edited_ids = array();
        foreach ($places as $p) {
            if ($p['pending_edits_count'] > 0 || $p['status'] === 'publish') {
                $edited_ids[] = (int) $p['id'];
            }
        }
        if (!empty($edited_ids)) {
            $ids_in = implode(',', $edited_ids);
            $modifiers = $wpdb->get_results(
                "SELECT h.point_id, h.user_id, h.resolved_at, u3.display_name AS modifier_name
                 FROM $history_table h
                 LEFT JOIN {$wpdb->users} u3 ON u3.ID = h.user_id
                 WHERE h.status = 'approved' AND h.action_type = 'edit'
                   AND h.point_id IN ($ids_in)
                 ORDER BY h.resolved_at DESC",
                ARRAY_A
            );
            if (is_array($modifiers)) {
                $seen = array();
                foreach ($modifiers as $row) {
                    $pid = $row['point_id'];
                    if (isset($places[$pid]) && !isset($seen[$pid])) {
                        $places[$pid]['last_modifier_name'] = $row['modifier_name'];
                        $places[$pid]['last_modified_at']   = $row['resolved_at'];
                        $seen[$pid] = true;
                    }
                }
            }
        }

        // Re-index as plain array
        $places = array_values($places);

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

    /**
     * Get tags with usage counts, search, and pagination for admin management
     *
     * @param string $search Search query (optional)
     * @param int    $page   Page number (1-based)
     * @param int    $per_page Items per page
     * @return array ['tags' => [...], 'total' => int, 'pages' => int]
     */
    public static function get_tags_paginated($search = '', $page = 1, $per_page = 20) {
        global $wpdb;
        $table = self::get_points_table();

        // Get all tags JSON from published points
        $rows = $wpdb->get_col(
            "SELECT tags FROM $table WHERE status = 'publish' AND tags IS NOT NULL AND tags != ''"
        );

        // Parse and count tags
        $tag_counts = array();
        foreach ($rows as $tags_json) {
            $tags = json_decode($tags_json, true);
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if ($tag !== '') {
                        $lower = mb_strtolower($tag);
                        if (!isset($tag_counts[$lower])) {
                            $tag_counts[$lower] = array('name' => $tag, 'count' => 0);
                        }
                        $tag_counts[$lower]['count']++;
                    }
                }
            }
        }

        // Sort alphabetically
        ksort($tag_counts);

        // Filter by search (diacritics-insensitive for Polish characters)
        if (!empty($search)) {
            $search_normalized = self::remove_diacritics(mb_strtolower($search));
            $tag_counts = array_filter($tag_counts, function($item) use ($search_normalized) {
                $tag_normalized = self::remove_diacritics(mb_strtolower($item['name']));
                return mb_strpos($tag_normalized, $search_normalized) !== false;
            });
        }

        $total = count($tag_counts);
        $pages = max(1, (int) ceil($total / $per_page));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $per_page;

        $tags = array_values(array_slice($tag_counts, $offset, $per_page));

        return array(
            'tags' => $tags,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
        );
    }

    /**
     * Rename a tag across all points
     *
     * @param string $old_name Current tag name
     * @param string $new_name New tag name
     * @return int Number of updated points
     */
    public static function rename_tag($old_name, $new_name) {
        global $wpdb;
        $table = self::get_points_table();

        $like_pattern = '%' . $wpdb->esc_like('"' . $old_name . '"') . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tags FROM $table WHERE tags LIKE %s",
            $like_pattern
        ), ARRAY_A);

        $updated = 0;
        foreach ($rows as $row) {
            $tags = json_decode($row['tags'], true);
            if (!is_array($tags)) continue;

            $changed = false;
            foreach ($tags as &$tag) {
                if (mb_strtolower(trim($tag)) === mb_strtolower($old_name)) {
                    $tag = $new_name;
                    $changed = true;
                }
            }
            unset($tag);

            if ($changed) {
                // Deduplicate after rename (case-insensitive)
                $seen = array();
                $unique_tags = array();
                foreach ($tags as $t) {
                    $lower = mb_strtolower($t);
                    if (!isset($seen[$lower])) {
                        $seen[$lower] = true;
                        $unique_tags[] = $t;
                    }
                }

                $wpdb->update(
                    $table,
                    array('tags' => json_encode(array_values($unique_tags), JSON_UNESCAPED_UNICODE)),
                    array('id' => $row['id']),
                    array('%s'),
                    array('%d')
                );
                $updated++;
            }
        }

        if ($updated > 0) {
            self::invalidate_points_cache();
        }

        return $updated;
    }

    /**
     * Delete a tag from all points
     *
     * @param string $tag_name Tag to delete
     * @return int Number of updated points
     */
    public static function delete_tag($tag_name) {
        global $wpdb;
        $table = self::get_points_table();

        $like_pattern = '%' . $wpdb->esc_like('"' . $tag_name . '"') . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, tags FROM $table WHERE tags LIKE %s",
            $like_pattern
        ), ARRAY_A);

        $updated = 0;
        foreach ($rows as $row) {
            $tags = json_decode($row['tags'], true);
            if (!is_array($tags)) continue;

            $new_tags = array_values(array_filter($tags, function($tag) use ($tag_name) {
                return mb_strtolower(trim($tag)) !== mb_strtolower($tag_name);
            }));

            $tags_json = !empty($new_tags) ? json_encode($new_tags, JSON_UNESCAPED_UNICODE) : null;
            $wpdb->update(
                $table,
                array('tags' => $tags_json),
                array('id' => $row['id']),
                array('%s'),
                array('%d')
            );
            $updated++;
        }

        if ($updated > 0) {
            self::invalidate_points_cache();
        }

        return $updated;
    }

    /**
     * Get all unique tag names for search suggestions
     *
     * @return array List of tag names
     */
    public static function get_all_tag_names() {
        global $wpdb;
        $table = self::get_points_table();

        $rows = $wpdb->get_col(
            "SELECT DISTINCT tags FROM $table WHERE tags IS NOT NULL AND tags != ''"
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
        return $result;
    }

    /**
     * Remove Polish diacritics from a string for search comparison
     *
     * @param string $str Input string
     * @return string String without diacritics
     */
    public static function remove_diacritics($str) {
        return strtr($str, array(
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N',
            'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
        ));
    }

    // -----------------------------------------------------------------------
    // Menu helpers
    // -----------------------------------------------------------------------

    public static function get_menu_sections_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_menu_sections';
    }

    public static function get_menu_items_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_menu_items';
    }

    public static function get_menu_photos_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_menu_photos';
    }

    /**
     * Return full menu for a point: array of sections each with ->items array.
     */
    public static function get_menu($point_id) {
        global $wpdb;
        $point_id = intval($point_id);

        $st = self::get_menu_sections_table();
        $it = self::get_menu_items_table();

        $sections = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, sort_order FROM $st WHERE point_id = %d ORDER BY sort_order ASC, id ASC",
                $point_id
            ),
            ARRAY_A
        );

        if (empty($sections)) {
            return array();
        }

        $section_ids = array_map('intval', array_column($sections, 'id'));
        $placeholders = implode(',', array_fill(0, count($section_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, section_id, name, description, price, variants, dietary_tags, sort_order, is_available
                 FROM $it
                 WHERE section_id IN ($placeholders)
                 ORDER BY sort_order ASC, id ASC",
                ...$section_ids
            ),
            ARRAY_A
        );

        // Group items by section
        $items_by_section = array();
        foreach ($items as $item) {
            $items_by_section[intval($item['section_id'])][] = $item;
        }

        foreach ($sections as &$section) {
            $sid = intval($section['id']);
            $section['items'] = isset($items_by_section[$sid]) ? $items_by_section[$sid] : array();
        }
        unset($section);

        return $sections;
    }

    /**
     * Replace all sections and items for a point.
     * $sections = [['name'=>'...', 'items'=>[['name'=>'...','price'=>0.0,'description'=>'...','dietary_tags'=>'...'],...]],...]
     */
    public static function save_menu($point_id, $sections) {
        global $wpdb;
        $point_id = intval($point_id);

        $st = self::get_menu_sections_table();
        $it = self::get_menu_items_table();

        // Delete existing data
        $section_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT id FROM $st WHERE point_id = %d", $point_id)
        );
        if (!empty($section_ids)) {
            $placeholders = implode(',', array_fill(0, count($section_ids), '%d'));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($wpdb->prepare("DELETE FROM $it WHERE section_id IN ($placeholders)", ...$section_ids));
        }
        $wpdb->delete($st, array('point_id' => $point_id), array('%d'));

        // Insert new data
        foreach ($sections as $sort_s => $sec) {
            $sec_name = sanitize_text_field(substr($sec['name'] ?? '', 0, 255));
            if ($sec_name === '') continue;

            $wpdb->insert(
                $st,
                array('point_id' => $point_id, 'name' => $sec_name, 'sort_order' => $sort_s),
                array('%d', '%s', '%d')
            );
            $section_id = $wpdb->insert_id;

            foreach (($sec['items'] ?? array()) as $sort_i => $item) {
                $item_name = sanitize_text_field(substr($item['name'] ?? '', 0, 255));
                if ($item_name === '') continue;

                $price = isset($item['price']) && $item['price'] !== '' ? round(floatval($item['price']), 2) : null;
                $desc  = sanitize_textarea_field($item['description'] ?? '');
                $dtags = sanitize_text_field(substr($item['dietary_tags'] ?? '', 0, 255));
                $avail = isset($item['is_available']) ? (intval($item['is_available']) ? 1 : 0) : 1;

                // Sanitize variants (array of {label, price})
                $variants_json = null;
                if (!empty($item['variants']) && is_array($item['variants'])) {
                    $clean_variants = array();
                    foreach ($item['variants'] as $v) {
                        $vlabel = sanitize_text_field(substr($v['label'] ?? '', 0, 100));
                        $vprice = isset($v['price']) && $v['price'] !== '' ? round(floatval($v['price']), 2) : null;
                        if ($vlabel !== '') {
                            $clean_variants[] = array('label' => $vlabel, 'price' => $vprice);
                        }
                    }
                    if (!empty($clean_variants)) {
                        $variants_json = wp_json_encode($clean_variants);
                    }
                }

                $wpdb->insert(
                    $it,
                    array(
                        'point_id'     => $point_id,
                        'section_id'   => $section_id,
                        'name'         => $item_name,
                        'description'  => $desc,
                        'price'        => $price,
                        'variants'     => $variants_json,
                        'dietary_tags' => $dtags,
                        'sort_order'   => $sort_i,
                        'is_available' => $avail,
                    ),
                    array('%d', '%d', '%s', '%s', $price !== null ? '%f' : 'null', $variants_json !== null ? '%s' : 'null', '%s', '%d', '%d')
                );
            }
        }

        return true;
    }

    /**
     * Return menu card photos for a point.
     */
    public static function get_menu_photos($point_id) {
        global $wpdb;
        $pt = self::get_menu_photos_table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, url, thumb_url, sort_order FROM $pt WHERE point_id = %d ORDER BY sort_order ASC, id ASC",
                intval($point_id)
            ),
            ARRAY_A
        );
    }

    /**
     * Add a menu card photo.
     */
    public static function add_menu_photo($point_id, $url, $thumb_url) {
        global $wpdb;
        $pt = self::get_menu_photos_table();
        $next_order = (int)$wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM $pt WHERE point_id = %d", intval($point_id))
        );
        $wpdb->insert(
            $pt,
            array('point_id' => intval($point_id), 'url' => esc_url_raw($url), 'thumb_url' => esc_url_raw($thumb_url), 'sort_order' => $next_order),
            array('%d', '%s', '%s', '%d')
        );
        return $wpdb->insert_id;
    }

    /**
     * Delete a menu card photo. Returns true if deleted.
     */
    public static function delete_menu_photo($photo_id, $point_id) {
        global $wpdb;
        $pt = self::get_menu_photos_table();
        $deleted = $wpdb->delete(
            $pt,
            array('id' => intval($photo_id), 'point_id' => intval($point_id)),
            array('%d', '%d')
        );
        return $deleted !== false && $deleted > 0;
    }

    /**
     * Get predefined size labels for a point's menu.
     * Returns array of strings, e.g. ['Mała', 'Duża'].
     */
    public static function get_menu_size_labels($point_id) {
        global $wpdb;
        $table = self::get_points_table();
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT menu_size_labels FROM $table WHERE id = %d",
            intval($point_id)
        ));
        if (!$raw) return array();
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : array();
    }

    /**
     * Save predefined size labels for a point's menu.
     * $labels = ['Mała', 'Duża', ...]
     */
    public static function save_menu_size_labels($point_id, $labels) {
        global $wpdb;
        $table = self::get_points_table();
        $clean = array();
        if (is_array($labels)) {
            foreach ($labels as $l) {
                $s = sanitize_text_field(substr((string)$l, 0, 50));
                if ($s !== '') $clean[] = $s;
            }
        }
        $wpdb->update(
            $table,
            array('menu_size_labels' => !empty($clean) ? wp_json_encode($clean) : null),
            array('id' => intval($point_id)),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Check if a point has any menu data (sections or photos).
     */
    public static function point_has_menu($point_id) {
        global $wpdb;
        $st = self::get_menu_sections_table();
        $pt = self::get_menu_photos_table();
        $sections = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $st WHERE point_id = %d", intval($point_id)));
        $photos   = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $pt WHERE point_id = %d", intval($point_id)));
        return ($sections + $photos) > 0;
    }

    /**
     * Batch-check which of the given point IDs have menu content.
     * Returns a flat array of point IDs that have at least one section or photo.
     */
    public static function get_menu_point_ids_batch(array $point_ids) {
        if (empty($point_ids)) return array();
        global $wpdb;
        $st   = self::get_menu_sections_table();
        $pt   = self::get_menu_photos_table();
        $ids  = implode(',', array_map('intval', $point_ids));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_col(
            "SELECT DISTINCT point_id FROM $st WHERE point_id IN ($ids)
             UNION
             SELECT DISTINCT point_id FROM $pt WHERE point_id IN ($ids)"
        );
        return array_map('intval', $rows ?: array());
    }

    // -----------------------------------------------------------------------
    // Offerings helpers (services / products)
    // -----------------------------------------------------------------------

    public static function get_offerings_table() {
        global $wpdb;
        return $wpdb->prefix . 'jg_map_offerings';
    }

    /**
     * Return all offerings for a point, ordered by sort_order ASC.
     */
    public static function get_offerings($point_id) {
        global $wpdb;
        $ot = self::get_offerings_table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, description, price, sort_order, is_available FROM $ot WHERE point_id = %d ORDER BY sort_order ASC, id ASC",
                intval($point_id)
            ),
            ARRAY_A
        );
    }

    /**
     * Replace all offerings for a point.
     * $items = [['name'=>'...', 'description'=>'...', 'price'=>0.0, 'is_available'=>1], ...]
     */
    public static function save_offerings($point_id, $items) {
        global $wpdb;
        $point_id = intval($point_id);
        $ot = self::get_offerings_table();

        $wpdb->delete($ot, array('point_id' => $point_id), array('%d'));

        foreach ($items as $sort => $item) {
            $name = sanitize_text_field(substr($item['name'] ?? '', 0, 255));
            if ($name === '') continue;

            $price = isset($item['price']) && $item['price'] !== '' ? round(floatval($item['price']), 2) : null;
            $desc  = sanitize_textarea_field($item['description'] ?? '');
            $avail = isset($item['is_available']) ? (intval($item['is_available']) ? 1 : 0) : 1;

            $wpdb->insert(
                $ot,
                array(
                    'point_id'     => $point_id,
                    'name'         => $name,
                    'description'  => $desc,
                    'price'        => $price,
                    'sort_order'   => $sort,
                    'is_available' => $avail,
                ),
                array('%d', '%s', '%s', $price !== null ? '%f' : 'null', '%d', '%d')
            );
        }

        return true;
    }

    /**
     * Check if a point has any offerings.
     */
    public static function point_has_offerings($point_id) {
        global $wpdb;
        $ot = self::get_offerings_table();
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ot WHERE point_id = %d", intval($point_id))) > 0;
    }

    /**
     * Batch-check which of the given point IDs have offerings.
     * Returns a flat array of point IDs that have at least one offering.
     */
    public static function get_offerings_point_ids_batch(array $point_ids) {
        if (empty($point_ids)) return array();
        global $wpdb;
        $ot  = self::get_offerings_table();
        $ids = implode(',', array_map('intval', $point_ids));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_col("SELECT DISTINCT point_id FROM $ot WHERE point_id IN ($ids)");
        return array_map('intval', $rows ?: array());
    }
}
