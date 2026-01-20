<?php
/**
 * JG Map - Maintenance & Cleanup
 * Handles scheduled database cleanup and optimization tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Maintenance {

    /**
     * Cron hook name
     */
    const CRON_HOOK = 'jg_map_daily_maintenance';

    /**
     * Initialize maintenance tasks
     */
    public static function init() {
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }

        // Hook the maintenance function
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_maintenance'));

        // Add manual trigger for admins (for testing)
        add_action('admin_init', array(__CLASS__, 'handle_manual_trigger'));
    }

    /**
     * Handle manual maintenance trigger from admin
     */
    public static function handle_manual_trigger() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['jg_run_maintenance']) && $_GET['jg_run_maintenance'] === '1') {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'jg_maintenance')) {
                wp_die('Security check failed');
            }

            self::run_maintenance();
            wp_redirect(admin_url('admin.php?page=jg-map&maintenance_done=1'));
            exit;
        }
    }

    /**
     * Main maintenance function - runs all cleanup tasks
     */
    public static function run_maintenance() {
        $start_time = microtime(true);
        $results = array();


        // 1. Clean orphaned data
        $results['orphaned_votes'] = self::clean_orphaned_votes();
        $results['orphaned_reports'] = self::clean_orphaned_reports();
        $results['orphaned_history'] = self::clean_orphaned_history();
        $results['orphaned_images'] = self::clean_orphaned_images();

        // 2. Validate data integrity
        $results['invalid_coords'] = self::validate_coordinates();
        $results['empty_content'] = self::validate_content();

        // 3. Clean outdated data
        $results['expired_sponsors'] = self::disable_expired_sponsorships();
        $results['expired_banners'] = self::deactivate_expired_banners();
        $results['old_pending'] = self::clean_old_pending_points();
        $results['old_deleted'] = self::clean_old_deleted_points();
        $results['expired_resolved_reports'] = self::clean_expired_resolved_reports();
        $results['expired_rejected_reports'] = self::clean_expired_rejected_reports();

        // 4. Optimize database
        $results['cache_cleared'] = self::clear_caches();
        $results['tables_optimized'] = self::optimize_tables();

        $execution_time = round(microtime(true) - $start_time, 2);


        // Store last run time
        update_option('jg_map_last_maintenance', array(
            'time' => current_time('mysql'),
            'results' => $results,
            'execution_time' => $execution_time
        ));

        return $results;
    }

    /**
     * Clean orphaned votes (votes for deleted points)
     */
    private static function clean_orphaned_votes() {
        global $wpdb;
        $votes_table = JG_Map_Database::get_votes_table();
        $points_table = JG_Map_Database::get_points_table();

        $deleted = $wpdb->query("
            DELETE v FROM $votes_table v
            LEFT JOIN $points_table p ON v.point_id = p.id
            WHERE p.id IS NULL
        ");

        return $deleted;
    }

    /**
     * Clean orphaned reports
     */
    private static function clean_orphaned_reports() {
        global $wpdb;
        $reports_table = JG_Map_Database::get_reports_table();
        $points_table = JG_Map_Database::get_points_table();

        $deleted = $wpdb->query("
            DELETE r FROM $reports_table r
            LEFT JOIN $points_table p ON r.point_id = p.id
            WHERE p.id IS NULL
        ");

        return $deleted;
    }

    /**
     * Clean orphaned history entries
     */
    private static function clean_orphaned_history() {
        global $wpdb;
        $history_table = JG_Map_Database::get_history_table();
        $points_table = JG_Map_Database::get_points_table();

        $deleted = $wpdb->query("
            DELETE h FROM $history_table h
            LEFT JOIN $points_table p ON h.point_id = p.id
            WHERE p.id IS NULL
        ");

        return $deleted;
    }

    /**
     * Validate coordinates - mark or delete points with invalid coordinates
     * Poland bounds: lat 49-55, lng 14-24
     */
    private static function validate_coordinates() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Find points with invalid coordinates
        $invalid_points = $wpdb->get_results("
            SELECT id, title, lat, lng
            FROM $table
            WHERE lat IS NULL OR lng IS NULL
               OR lat = 0 OR lng = 0
               OR lat < 49 OR lat > 55
               OR lng < 14 OR lng > 24
        ");

        $count = count($invalid_points);

        if ($count > 0) {
            foreach ($invalid_points as $point) {
                error_log('[JG MAP MAINTENANCE] Invalid coordinates for point #' . $point->id . ': lat=' . $point->lat . ', lng=' . $point->lng);
            }
        }

        return $count;
    }

    /**
     * Validate content - mark points without title or content
     */
    private static function validate_content() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Find points with empty title or content
        $empty_points = $wpdb->get_results("
            SELECT id, title, content, excerpt
            FROM $table
            WHERE (title IS NULL OR title = '')
               OR ((content IS NULL OR content = '') AND (excerpt IS NULL OR excerpt = ''))
        ");

        $count = count($empty_points);

        if ($count > 0) {
            error_log('[JG MAP MAINTENANCE] Found ' . $count . ' points with empty content');
            foreach ($empty_points as $point) {
            }
        }

        return $count;
    }

    /**
     * Disable expired sponsorships
     */
    private static function disable_expired_sponsorships() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $updated = $wpdb->query($wpdb->prepare("
            UPDATE $table
            SET is_promo = 0
            WHERE is_promo = 1
              AND promo_until IS NOT NULL
              AND promo_until < %s
        ", current_time('mysql')));

        if ($updated > 0) {
        }

        return $updated;
    }

    /**
     * Deactivate expired banners and clean old impression records
     */
    private static function deactivate_expired_banners() {
        JG_Map_Banner_Manager::deactivate_expired_banners();
        JG_Map_Banner_Manager::clean_old_impressions();
        return true;
    }

    /**
     * Clean old pending points (older than 30 days)
     */
    private static function clean_old_pending_points() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Get points older than 30 days
        $old_points = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, author_id, created_at
            FROM $table
            WHERE status = 'pending'
              AND created_at < %s
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));

        $count = count($old_points);

        if ($count > 0) {

            foreach ($old_points as $point) {
                // Log before deletion

                // Send notification to author
                $user = get_userdata($point->author_id);
                if ($user) {
                    $subject = 'Twoje miejsce wygasło z powodu braku moderacji';
                    $message = "Witaj " . $user->display_name . ",\n\n";
                    $message .= "Twoje miejsce \"" . $point->title . "\" zostało automatycznie usunięte, ponieważ czekało na moderację dłużej niż 30 dni.\n\n";
                    $message .= "Możesz dodać je ponownie na mapie.\n\n";
                    $message .= "Pozdrawiamy,\nZespół Jeleniórzanie to my";

                    // Temporarily override email sender for this email
                    $custom_from_name = function($from_name) { return 'Jeleniogorzanie to my'; };
                    $custom_from_email = function($from_email) { return 'powiadomienia@jeleniogorzanietomy.pl'; };
                    add_filter('wp_mail_from_name', $custom_from_name, 99);
                    add_filter('wp_mail_from', $custom_from_email, 99);

                    $headers = array(
                        'Content-Type: text/plain; charset=UTF-8',
                        'Reply-To: powiadomienia@jeleniogorzanietomy.pl'
                    );

                    wp_mail($user->user_email, $subject, $message, $headers);

                    // Remove temporary filters
                    remove_filter('wp_mail_from_name', $custom_from_name, 99);
                    remove_filter('wp_mail_from', $custom_from_email, 99);
                }

                // Delete the point (will also delete related data thanks to our delete_point() function)
                JG_Map_Database::delete_point($point->id);
            }
        }

        return $count;
    }

    /**
     * Clean old deleted/trash points (older than 90 days)
     */
    private static function clean_old_deleted_points() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Permanently delete points marked as deleted/trash older than 90 days
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM $table
            WHERE status IN ('trash', 'deleted', 'draft')
              AND updated_at < %s
        ", date('Y-m-d H:i:s', strtotime('-90 days'))));

        if ($deleted > 0) {
        }

        return $deleted;
    }

    /**
     * Clean expired resolved reports (auto-delete after 7 days)
     */
    private static function clean_expired_resolved_reports() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Get expired resolved reports
        $expired_reports = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, case_id, report_status, resolved_delete_at
            FROM $table
            WHERE type = 'zgloszenie'
              AND report_status = 'resolved'
              AND resolved_delete_at IS NOT NULL
              AND resolved_delete_at <= %s
        ", current_time('mysql')));

        $count = count($expired_reports);

        if ($count > 0) {
            error_log('[JG MAP MAINTENANCE] Found ' . $count . ' expired resolved reports to delete');

            foreach ($expired_reports as $report) {
                error_log('[JG MAP MAINTENANCE] Auto-deleting resolved report: ' . $report->case_id . ' - ' . $report->title);

                // Delete the report completely (will also delete related data)
                JG_Map_Database::delete_point($report->id);
            }

            error_log('[JG MAP MAINTENANCE] Successfully deleted ' . $count . ' expired resolved reports');
        }

        return $count;
    }

    /**
     * Clean expired rejected reports (after 7 days)
     */
    private static function clean_expired_rejected_reports() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Get expired rejected reports
        $expired_reports = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, case_id, report_status, rejected_delete_at
            FROM $table
            WHERE type = 'zgloszenie'
              AND report_status = 'rejected'
              AND rejected_delete_at IS NOT NULL
              AND rejected_delete_at <= %s
        ", current_time('mysql')));

        $count = count($expired_reports);

        if ($count > 0) {
            error_log('[JG MAP MAINTENANCE] Found ' . $count . ' expired rejected reports to delete');

            foreach ($expired_reports as $report) {
                error_log('[JG MAP MAINTENANCE] Auto-deleting rejected report: ' . $report->case_id . ' - ' . $report->title);

                // Delete the report completely (will also delete related data)
                JG_Map_Database::delete_point($report->id);
            }

            error_log('[JG MAP MAINTENANCE] Successfully deleted ' . $count . ' expired rejected reports');
        }

        return $count;
    }

    /**
     * Clean orphaned image files (images not referenced in any point)
     */
    private static function clean_orphaned_images() {
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get all image URLs from database
        $all_points = $wpdb->get_results("SELECT id, images FROM $points_table WHERE images IS NOT NULL AND images != ''");

        $used_files = array();
        foreach ($all_points as $point) {
            $images = json_decode($point->images, true);
            if (is_array($images)) {
                foreach ($images as $image) {
                    if (!empty($image['full'])) {
                        $used_files[] = basename($image['full']);
                    }
                    if (!empty($image['thumb']) && $image['thumb'] !== $image['full']) {
                        $used_files[] = basename($image['thumb']);
                    }
                }
            }
        }

        // Get upload directory
        $upload_dir = wp_upload_dir();
        $jg_map_dir = $upload_dir['basedir'] . '/jg-map-images';

        // Check if directory exists
        if (!is_dir($jg_map_dir)) {
            return 0;
        }

        // Scan directory for all image files
        $all_files = array();
        $extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');

        foreach ($extensions as $ext) {
            $files = glob($jg_map_dir . '/*.' . $ext);
            if ($files) {
                $all_files = array_merge($all_files, $files);
            }
            // Check uppercase extensions too
            $files = glob($jg_map_dir . '/*.' . strtoupper($ext));
            if ($files) {
                $all_files = array_merge($all_files, $files);
            }
        }

        // Delete orphaned files
        $deleted_count = 0;
        foreach ($all_files as $file_path) {
            $filename = basename($file_path);

            // If file is not in used_files list, delete it
            if (!in_array($filename, $used_files)) {
                if (@unlink($file_path)) {
                    $deleted_count++;
                    error_log('[JG MAP MAINTENANCE] Deleted orphaned image: ' . $filename);
                }
            }
        }

        if ($deleted_count > 0) {
            error_log('[JG MAP MAINTENANCE] Deleted ' . $deleted_count . ' orphaned images');
        }

        return $deleted_count;
    }

    /**
     * Clear WordPress caches
     */
    private static function clear_caches() {
        wp_cache_flush();
        return true;
    }

    /**
     * Optimize database tables
     */
    private static function optimize_tables() {
        global $wpdb;

        $tables = array(
            JG_Map_Database::get_points_table(),
            JG_Map_Database::get_votes_table(),
            JG_Map_Database::get_reports_table(),
            JG_Map_Database::get_history_table(),
            JG_Map_Database::get_relevance_votes_table()
        );

        $optimized = 0;
        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE $table");
            if ($result !== false) {
                $optimized++;
            }
        }

        return $optimized;
    }

    /**
     * Unschedule cron on plugin deactivation
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
}
