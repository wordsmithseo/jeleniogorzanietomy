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

        // 2. Validate data integrity
        $results['invalid_coords'] = self::validate_coordinates();
        $results['empty_content'] = self::validate_content();

        // 3. Clean outdated data
        $results['expired_sponsors'] = self::disable_expired_sponsorships();
        $results['old_pending'] = self::clean_old_pending_points();
        $results['old_deleted'] = self::clean_old_deleted_points();
        $results['expired_resolved_reports'] = self::clean_expired_resolved_reports();

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
            error_log('[JG MAP MAINTENANCE] Found ' . $count . ' points with invalid coordinates');
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
                error_log('[JG MAP MAINTENANCE] Empty content for point #' . $point->id . ': title=' . ($point->title ?: 'EMPTY'));
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
                    $message .= "Pozdrawiamy,\nZespół JeleniogorzaNieTomy";

                    wp_mail($user->user_email, $subject, $message);
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
