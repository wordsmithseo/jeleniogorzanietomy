<?php
/**
 * User Levels & Achievements System
 *
 * Handles XP, levels, achievements, and related admin editors.
 */

if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Levels_Achievements {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // AJAX handlers - public (for user modal)
        add_action('wp_ajax_jg_get_user_level_info', array($this, 'ajax_get_user_level_info'));
        add_action('wp_ajax_nopriv_jg_get_user_level_info', array($this, 'ajax_get_user_level_info'));
        add_action('wp_ajax_jg_get_user_achievements', array($this, 'ajax_get_user_achievements'));
        add_action('wp_ajax_nopriv_jg_get_user_achievements', array($this, 'ajax_get_user_achievements'));

        // AJAX handlers - admin
        add_action('wp_ajax_jg_admin_save_xp_sources', array($this, 'ajax_save_xp_sources'));
        add_action('wp_ajax_jg_admin_get_xp_sources', array($this, 'ajax_get_xp_sources'));
        add_action('wp_ajax_jg_admin_save_achievements', array($this, 'ajax_save_achievements'));
        add_action('wp_ajax_jg_admin_get_achievements', array($this, 'ajax_get_achievements'));
        add_action('wp_ajax_jg_admin_delete_achievement', array($this, 'ajax_delete_achievement'));

        // AJAX handler - check for pending notifications
        add_action('wp_ajax_jg_check_level_notifications', array($this, 'ajax_check_notifications'));
        add_action('wp_ajax_jg_dismiss_level_notification', array($this, 'ajax_dismiss_notification'));
    }

    // =========================================================================
    // DATABASE
    // =========================================================================

    /**
     * Create database tables for levels & achievements
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // User XP table
        $table_user_xp = $wpdb->prefix . 'jg_map_user_xp';
        $sql_user_xp = "CREATE TABLE IF NOT EXISTS $table_user_xp (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            xp int(11) NOT NULL DEFAULT 0,
            level int(11) NOT NULL DEFAULT 1,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        // XP log table (track each XP gain)
        $table_xp_log = $wpdb->prefix . 'jg_map_xp_log';
        $sql_xp_log = "CREATE TABLE IF NOT EXISTS $table_xp_log (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            amount int(11) NOT NULL,
            source varchar(100) NOT NULL,
            reference_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY source (source)
        ) $charset_collate;";

        // Achievements definitions table (admin-configurable)
        $table_achievements = $wpdb->prefix . 'jg_map_achievements';
        $sql_achievements = "CREATE TABLE IF NOT EXISTS $table_achievements (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            description text NOT NULL,
            icon varchar(50) DEFAULT '🏆',
            rarity varchar(20) NOT NULL DEFAULT 'common',
            condition_type varchar(100) NOT NULL,
            condition_value int(11) NOT NULL DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        // User achievements (earned)
        $table_user_achievements = $wpdb->prefix . 'jg_map_user_achievements';
        $sql_user_achievements = "CREATE TABLE IF NOT EXISTS $table_user_achievements (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            achievement_id bigint(20) UNSIGNED NOT NULL,
            earned_at datetime DEFAULT CURRENT_TIMESTAMP,
            notified tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY user_achievement (user_id, achievement_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Pending notifications table (for level-up and achievement popups)
        $table_notifications = $wpdb->prefix . 'jg_map_level_notifications';
        $sql_notifications = "CREATE TABLE IF NOT EXISTS $table_notifications (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            type varchar(20) NOT NULL,
            data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_user_xp);
        dbDelta($sql_xp_log);
        dbDelta($sql_achievements);
        dbDelta($sql_user_achievements);
        dbDelta($sql_notifications);

        // Seed default XP sources if not set
        if (!get_option('jg_map_xp_sources')) {
            $default_sources = array(
                array('key' => 'submit_point', 'label' => 'Dodanie nowego miejsca', 'xp' => 50),
                array('key' => 'point_approved', 'label' => 'Zatwierdzenie miejsca przez moderację', 'xp' => 30),
                array('key' => 'receive_upvote', 'label' => 'Otrzymanie głosu w górę', 'xp' => 5),
                array('key' => 'vote_on_point', 'label' => 'Zagłosowanie na punkt', 'xp' => 2),
                array('key' => 'add_photo', 'label' => 'Dodanie zdjęcia', 'xp' => 10),
                array('key' => 'edit_point', 'label' => 'Edycja punktu', 'xp' => 15),
                array('key' => 'daily_login', 'label' => 'Codzienny login', 'xp' => 5),
                array('key' => 'report_point', 'label' => 'Zgłoszenie problemu', 'xp' => 10),
            );
            update_option('jg_map_xp_sources', json_encode($default_sources));
        }

        // Seed default achievements if table is empty
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_achievements");
        if ($count == 0) {
            $defaults = array(
                array('slug' => 'first_point', 'name' => 'Pierwszy krok', 'description' => 'Dodaj swój pierwszy punkt na mapie', 'icon' => '📍', 'rarity' => 'common', 'condition_type' => 'points_count', 'condition_value' => 1, 'sort_order' => 1),
                array('slug' => 'five_points', 'name' => 'Aktywny mieszkaniec', 'description' => 'Dodaj 5 punktów na mapie', 'icon' => '🏘️', 'rarity' => 'uncommon', 'condition_type' => 'points_count', 'condition_value' => 5, 'sort_order' => 2),
                array('slug' => 'ten_points', 'name' => 'Lokalny ekspert', 'description' => 'Dodaj 10 punktów na mapie', 'icon' => '🗺️', 'rarity' => 'rare', 'condition_type' => 'points_count', 'condition_value' => 10, 'sort_order' => 3),
                array('slug' => 'twentyfive_points', 'name' => 'Kartograf', 'description' => 'Dodaj 25 punktów na mapie', 'icon' => '🧭', 'rarity' => 'epic', 'condition_type' => 'points_count', 'condition_value' => 25, 'sort_order' => 4),
                array('slug' => 'fifty_points', 'name' => 'Legenda Jeleniej Góry', 'description' => 'Dodaj 50 punktów na mapie', 'icon' => '👑', 'rarity' => 'legendary', 'condition_type' => 'points_count', 'condition_value' => 50, 'sort_order' => 5),
                array('slug' => 'first_vote', 'name' => 'Głos obywatelski', 'description' => 'Zagłosuj na dowolny punkt', 'icon' => '👍', 'rarity' => 'common', 'condition_type' => 'votes_count', 'condition_value' => 1, 'sort_order' => 6),
                array('slug' => 'ten_votes', 'name' => 'Aktywny głosujący', 'description' => 'Zagłosuj 10 razy', 'icon' => '🗳️', 'rarity' => 'uncommon', 'condition_type' => 'votes_count', 'condition_value' => 10, 'sort_order' => 7),
                array('slug' => 'first_photo', 'name' => 'Fotoreporter', 'description' => 'Dodaj pierwsze zdjęcie', 'icon' => '📸', 'rarity' => 'common', 'condition_type' => 'photos_count', 'condition_value' => 1, 'sort_order' => 8),
                array('slug' => 'level_5', 'name' => 'Doświadczony', 'description' => 'Osiągnij poziom 5', 'icon' => '⭐', 'rarity' => 'uncommon', 'condition_type' => 'level', 'condition_value' => 5, 'sort_order' => 9),
                array('slug' => 'level_10', 'name' => 'Weteran', 'description' => 'Osiągnij poziom 10', 'icon' => '🌟', 'rarity' => 'rare', 'condition_type' => 'level', 'condition_value' => 10, 'sort_order' => 10),
                array('slug' => 'level_20', 'name' => 'Mistrz mapy', 'description' => 'Osiągnij poziom 20', 'icon' => '💎', 'rarity' => 'epic', 'condition_type' => 'level', 'condition_value' => 20, 'sort_order' => 11),
                array('slug' => 'all_types', 'name' => 'Wszechstronny', 'description' => 'Dodaj punkt każdego typu (miejsce, ciekawostka, zgłoszenie)', 'icon' => '🎯', 'rarity' => 'rare', 'condition_type' => 'all_types', 'condition_value' => 1, 'sort_order' => 12),
            );

            foreach ($defaults as $ach) {
                $wpdb->insert($table_achievements, $ach);
            }
        }
    }

    // =========================================================================
    // XP & LEVEL CALCULATIONS
    // =========================================================================

    /**
     * XP required to reach a given level (cumulative).
     * Level 1 starts at 0 XP. Level 2 = 400, level 3 = 900, etc.
     * Formula: level^2 * 100 (but level 1 = 0)
     */
    public static function xp_for_level($level) {
        if ($level <= 1) return 0;
        return ($level * $level) * 100;
    }

    /**
     * Calculate level from total XP
     */
    public static function calculate_level($total_xp) {
        $level = 1;
        while (self::xp_for_level($level + 1) <= $total_xp) {
            $level++;
        }
        return $level;
    }

    /**
     * Get user XP data
     */
    public static function get_user_xp_data($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_user_xp';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT xp, level FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$row) {
            return array('xp' => 0, 'level' => 1);
        }

        return array(
            'xp' => intval($row['xp']),
            'level' => intval($row['level'])
        );
    }

    /**
     * Award XP to a user
     * Returns array with level_up info if level changed
     */
    public static function award_xp($user_id, $source, $reference_id = null) {
        if (!$user_id) return null;

        global $wpdb;
        $table_xp = $wpdb->prefix . 'jg_map_user_xp';
        $table_log = $wpdb->prefix . 'jg_map_xp_log';
        $table_notifications = $wpdb->prefix . 'jg_map_level_notifications';

        // Get XP amount for this source
        $sources = self::get_xp_sources();
        $amount = 0;
        foreach ($sources as $s) {
            if ($s['key'] === $source) {
                $amount = intval($s['xp']);
                break;
            }
        }

        if ($amount <= 0) return null;

        // Log the XP gain
        $wpdb->insert($table_log, array(
            'user_id' => $user_id,
            'amount' => $amount,
            'source' => $source,
            'reference_id' => $reference_id
        ));

        // Get current XP
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT xp, level FROM $table_xp WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $old_xp = $current ? intval($current['xp']) : 0;
        $old_level = $current ? intval($current['level']) : 1;
        $new_xp = $old_xp + $amount;
        $new_level = self::calculate_level($new_xp);

        // Upsert user XP
        if ($current) {
            $wpdb->update($table_xp,
                array('xp' => $new_xp, 'level' => $new_level),
                array('user_id' => $user_id)
            );
        } else {
            $wpdb->insert($table_xp, array(
                'user_id' => $user_id,
                'xp' => $new_xp,
                'level' => $new_level
            ));
        }

        $result = array(
            'xp_gained' => $amount,
            'old_xp' => $old_xp,
            'new_xp' => $new_xp,
            'old_level' => $old_level,
            'new_level' => $new_level,
            'level_up' => false
        );

        // Check for level up
        if ($new_level > $old_level) {
            $result['level_up'] = true;

            // Store notification for level up
            $wpdb->insert($table_notifications, array(
                'user_id' => $user_id,
                'type' => 'level_up',
                'data' => json_encode(array(
                    'old_level' => $old_level,
                    'new_level' => $new_level,
                    'xp' => $new_xp
                ))
            ));
        }

        // Check achievements after XP award
        self::check_achievements($user_id);

        return $result;
    }

    /**
     * Get XP sources configuration
     */
    public static function get_xp_sources() {
        $json = get_option('jg_map_xp_sources', '[]');
        $sources = json_decode($json, true);
        return is_array($sources) ? $sources : array();
    }

    // =========================================================================
    // ACHIEVEMENTS
    // =========================================================================

    /**
     * Check and award achievements for a user
     */
    public static function check_achievements($user_id) {
        global $wpdb;
        $table_achievements = $wpdb->prefix . 'jg_map_achievements';
        $table_user_achievements = $wpdb->prefix . 'jg_map_user_achievements';
        $table_notifications = $wpdb->prefix . 'jg_map_level_notifications';
        $table_points = $wpdb->prefix . 'jg_map_points';
        $table_votes = $wpdb->prefix . 'jg_map_votes';

        // Get all achievements
        $achievements = $wpdb->get_results("SELECT * FROM $table_achievements ORDER BY sort_order ASC", ARRAY_A);

        // Get already earned achievement IDs
        $earned_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT achievement_id FROM $table_user_achievements WHERE user_id = %d",
            $user_id
        ));

        // Get user stats for condition checking
        $user_xp = self::get_user_xp_data($user_id);

        $points_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'publish'",
            $user_id
        ));

        $votes_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_votes WHERE user_id = %d",
            $user_id
        ));

        // Photo count
        $photos_data = $wpdb->get_results($wpdb->prepare(
            "SELECT images FROM $table_points WHERE author_id = %d AND status = 'publish' AND images IS NOT NULL AND images != ''",
            $user_id
        ), ARRAY_A);
        $photos_count = 0;
        foreach ($photos_data as $pd) {
            $imgs = json_decode($pd['images'], true);
            if (is_array($imgs)) {
                $photos_count += count($imgs);
            }
        }

        // All types check
        $types = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT type FROM $table_points WHERE author_id = %d AND status = 'publish'",
            $user_id
        ));
        $has_all_types = (in_array('miejsce', $types) && in_array('ciekawostka', $types) && in_array('zgloszenie', $types));

        // Received upvotes count
        $received_upvotes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_votes v
             INNER JOIN $table_points p ON v.point_id = p.id
             WHERE p.author_id = %d AND v.vote_type = 'up'",
            $user_id
        ));

        foreach ($achievements as $ach) {
            if (in_array($ach['id'], $earned_ids)) {
                continue; // Already earned
            }

            $earned = false;

            switch ($ach['condition_type']) {
                case 'points_count':
                    $earned = ($points_count >= intval($ach['condition_value']));
                    break;
                case 'votes_count':
                    $earned = ($votes_count >= intval($ach['condition_value']));
                    break;
                case 'photos_count':
                    $earned = ($photos_count >= intval($ach['condition_value']));
                    break;
                case 'level':
                    $earned = ($user_xp['level'] >= intval($ach['condition_value']));
                    break;
                case 'all_types':
                    $earned = $has_all_types;
                    break;
                case 'received_upvotes':
                    $earned = ($received_upvotes >= intval($ach['condition_value']));
                    break;
            }

            if ($earned) {
                // Award achievement
                $wpdb->insert($table_user_achievements, array(
                    'user_id' => $user_id,
                    'achievement_id' => intval($ach['id']),
                    'notified' => 0
                ));

                // Store notification
                $wpdb->insert($table_notifications, array(
                    'user_id' => $user_id,
                    'type' => 'achievement',
                    'data' => json_encode(array(
                        'achievement_id' => $ach['id'],
                        'name' => $ach['name'],
                        'description' => $ach['description'],
                        'icon' => $ach['icon'],
                        'rarity' => $ach['rarity']
                    ))
                ));
            }
        }
    }

    /**
     * Get user's earned achievements
     */
    public static function get_user_achievements($user_id) {
        global $wpdb;
        $table_achievements = $wpdb->prefix . 'jg_map_achievements';
        $table_user_achievements = $wpdb->prefix . 'jg_map_user_achievements';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, ua.earned_at
             FROM $table_user_achievements ua
             INNER JOIN $table_achievements a ON ua.achievement_id = a.id
             WHERE ua.user_id = %d
             ORDER BY ua.earned_at DESC",
            $user_id
        ), ARRAY_A);
    }

    // =========================================================================
    // AJAX HANDLERS - PUBLIC
    // =========================================================================

    /**
     * Get user level info for the user modal
     */
    public function ajax_get_user_level_info() {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error('Missing user_id');
            return;
        }

        $xp_data = self::get_user_xp_data($user_id);
        $level = $xp_data['level'];
        $xp = $xp_data['xp'];

        $current_level_xp = self::xp_for_level($level);
        $next_level_xp = self::xp_for_level($level + 1);
        $xp_in_level = $xp - $current_level_xp;
        $xp_needed = $next_level_xp - $current_level_xp;
        $progress = $xp_needed > 0 ? min(100, round(($xp_in_level / $xp_needed) * 100)) : 100;

        // Get last 4 achievements
        $achievements = self::get_user_achievements($user_id);
        $recent_achievements = array_slice($achievements, 0, 4);

        wp_send_json_success(array(
            'level' => $level,
            'xp' => $xp,
            'xp_in_level' => $xp_in_level,
            'xp_needed' => $xp_needed,
            'progress' => $progress,
            'next_level_xp' => $next_level_xp,
            'current_level_xp' => $current_level_xp,
            'recent_achievements' => $recent_achievements,
            'total_achievements' => count($achievements)
        ));
    }

    /**
     * Get all user achievements (for full modal)
     */
    public function ajax_get_user_achievements() {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error('Missing user_id');
            return;
        }

        global $wpdb;
        $table_achievements = $wpdb->prefix . 'jg_map_achievements';

        // All available achievements
        $all_achievements = $wpdb->get_results(
            "SELECT * FROM $table_achievements ORDER BY sort_order ASC",
            ARRAY_A
        );

        // User's earned achievements
        $earned = self::get_user_achievements($user_id);
        $earned_ids = array();
        $earned_map = array();
        foreach ($earned as $e) {
            $earned_ids[] = $e['id'];
            $earned_map[$e['id']] = $e['earned_at'];
        }

        $result = array();
        foreach ($all_achievements as $ach) {
            $ach['earned'] = in_array($ach['id'], $earned_ids);
            $ach['earned_at'] = isset($earned_map[$ach['id']]) ? $earned_map[$ach['id']] : null;
            $result[] = $ach;
        }

        wp_send_json_success($result);
    }

    /**
     * Check for pending notifications (level-up, achievements)
     */
    public function ajax_check_notifications() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_success(array('notifications' => array()));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_level_notifications';

        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT id, type, data FROM $table WHERE user_id = %d ORDER BY created_at ASC LIMIT 5",
            $user_id
        ), ARRAY_A);

        $result = array();
        foreach ($notifications as $n) {
            $result[] = array(
                'id' => intval($n['id']),
                'type' => $n['type'],
                'data' => json_decode($n['data'], true)
            );
        }

        wp_send_json_success(array('notifications' => $result));
    }

    /**
     * Dismiss a notification
     */
    public function ajax_dismiss_notification() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
            return;
        }

        $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
        if (!$notification_id) {
            wp_send_json_error('Missing notification_id');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_level_notifications';

        $wpdb->delete($table, array(
            'id' => $notification_id,
            'user_id' => $user_id
        ));

        wp_send_json_success();
    }

    // =========================================================================
    // AJAX HANDLERS - ADMIN
    // =========================================================================

    public function ajax_get_xp_sources() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        wp_send_json_success(self::get_xp_sources());
    }

    public function ajax_save_xp_sources() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $sources_json = isset($_POST['sources']) ? wp_unslash($_POST['sources']) : '[]';
        $sources = json_decode($sources_json, true);

        if (!is_array($sources)) {
            wp_send_json_error('Invalid data');
            return;
        }

        // Sanitize
        $clean = array();
        foreach ($sources as $s) {
            $clean[] = array(
                'key' => sanitize_key($s['key']),
                'label' => sanitize_text_field($s['label']),
                'xp' => intval($s['xp'])
            );
        }

        update_option('jg_map_xp_sources', json_encode($clean));
        wp_send_json_success('Saved');
    }

    public function ajax_get_achievements() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_achievements';
        $achievements = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC", ARRAY_A);

        wp_send_json_success($achievements);
    }

    public function ajax_save_achievements() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $achievements_json = isset($_POST['achievements']) ? wp_unslash($_POST['achievements']) : '[]';
        $achievements = json_decode($achievements_json, true);

        if (!is_array($achievements)) {
            wp_send_json_error('Invalid data');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_achievements';
        $valid_rarities = array('common', 'uncommon', 'rare', 'epic', 'legendary');
        $valid_conditions = array('points_count', 'votes_count', 'photos_count', 'level', 'all_types', 'received_upvotes');

        foreach ($achievements as $ach) {
            $data = array(
                'slug' => sanitize_key($ach['slug']),
                'name' => sanitize_text_field($ach['name']),
                'description' => sanitize_text_field($ach['description']),
                'icon' => mb_substr(sanitize_text_field($ach['icon']), 0, 50),
                'rarity' => in_array($ach['rarity'], $valid_rarities) ? $ach['rarity'] : 'common',
                'condition_type' => in_array($ach['condition_type'], $valid_conditions) ? $ach['condition_type'] : 'points_count',
                'condition_value' => intval($ach['condition_value']),
                'sort_order' => intval($ach['sort_order'])
            );

            if (!empty($ach['id'])) {
                // Update existing
                $wpdb->update($table, $data, array('id' => intval($ach['id'])));
            } else {
                // Insert new
                $wpdb->insert($table, $data);
            }
        }

        wp_send_json_success('Saved');
    }

    // =========================================================================
    // RECALCULATION / SYNC
    // =========================================================================

    /**
     * Recalculate XP for all users based on actual actions in the database.
     * Clears xp_log and rebuilds XP from scratch by counting real data.
     * Returns summary array.
     */
    public static function recalculate_all_xp() {
        global $wpdb;
        $table_points = $wpdb->prefix . 'jg_map_points';
        $table_votes  = $wpdb->prefix . 'jg_map_votes';
        $table_reports = $wpdb->prefix . 'jg_map_reports';
        $table_history = $wpdb->prefix . 'jg_map_history';
        $table_user_xp = $wpdb->prefix . 'jg_map_user_xp';
        $table_xp_log  = $wpdb->prefix . 'jg_map_xp_log';

        // Get XP amounts from config
        $sources = self::get_xp_sources();
        $xp_map = array();
        foreach ($sources as $s) {
            $xp_map[$s['key']] = intval($s['xp']);
        }

        // Collect all user IDs that have any activity
        $user_ids = array();

        // Users who submitted points
        $point_authors = $wpdb->get_col("SELECT DISTINCT author_id FROM $table_points WHERE author_id > 0");
        $user_ids = array_merge($user_ids, $point_authors);

        // Users who voted
        $voters = $wpdb->get_col("SELECT DISTINCT user_id FROM $table_votes WHERE user_id > 0");
        $user_ids = array_merge($user_ids, $voters);

        // Users who reported
        $reporters = $wpdb->get_col("SELECT DISTINCT user_id FROM $table_reports WHERE user_id > 0");
        $user_ids = array_merge($user_ids, $reporters);

        $user_ids = array_unique(array_map('intval', $user_ids));

        // Clear existing XP log and user XP
        $wpdb->query("TRUNCATE TABLE $table_xp_log");
        $wpdb->query("TRUNCATE TABLE $table_user_xp");

        $users_updated = 0;
        $total_xp_awarded = 0;

        foreach ($user_ids as $uid) {
            $xp = 0;

            // submit_point: count published points by this user
            if (!empty($xp_map['submit_point'])) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'publish'",
                    $uid
                ));
                $xp += intval($count) * $xp_map['submit_point'];
            }

            // point_approved: same as published points (approved = published)
            if (!empty($xp_map['point_approved'])) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'publish'",
                    $uid
                ));
                $xp += intval($count) * $xp_map['point_approved'];
            }

            // add_photo: count photos in user's published points
            if (!empty($xp_map['add_photo'])) {
                $photos_data = $wpdb->get_results($wpdb->prepare(
                    "SELECT images FROM $table_points WHERE author_id = %d AND status = 'publish' AND images IS NOT NULL AND images != ''",
                    $uid
                ), ARRAY_A);
                $photo_count = 0;
                foreach ($photos_data as $pd) {
                    $imgs = json_decode($pd['images'], true);
                    if (is_array($imgs)) {
                        $photo_count += count($imgs);
                    }
                }
                $xp += $photo_count * $xp_map['add_photo'];
            }

            // vote_on_point: count votes cast by this user
            if (!empty($xp_map['vote_on_point'])) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_votes WHERE user_id = %d",
                    $uid
                ));
                $xp += intval($count) * $xp_map['vote_on_point'];
            }

            // receive_upvote: count upvotes received on user's points (excluding self-votes)
            if (!empty($xp_map['receive_upvote'])) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_votes v
                     INNER JOIN $table_points p ON v.point_id = p.id
                     WHERE p.author_id = %d AND v.vote_type = 'up' AND v.user_id != %d",
                    $uid, $uid
                ));
                $xp += intval($count) * $xp_map['receive_upvote'];
            }

            // edit_point: count edit history entries
            if (!empty($xp_map['edit_point'])) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_history WHERE user_id = %d AND action_type = 'edit'",
                    $uid
                ));
                $xp += intval($count) * $xp_map['edit_point'];
            }

            // report_point: count reports submitted
            if (!empty($xp_map['report_point'])) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_reports WHERE user_id = %d",
                    $uid
                ));
                $xp += intval($count) * $xp_map['report_point'];
            }

            // daily_login: not retroactively countable, skip

            if ($xp > 0) {
                $level = self::calculate_level($xp);
                $wpdb->insert($table_user_xp, array(
                    'user_id' => $uid,
                    'xp' => $xp,
                    'level' => $level
                ));
                $users_updated++;
                $total_xp_awarded += $xp;
            }
        }

        return array(
            'users_processed' => count($user_ids),
            'users_updated' => $users_updated,
            'total_xp_awarded' => $total_xp_awarded
        );
    }

    /**
     * Re-check achievements for all users who have XP records.
     * Does NOT create notifications (to avoid spam on mass-sync).
     * Returns summary array.
     */
    public static function recheck_all_achievements() {
        global $wpdb;
        $table_user_xp = $wpdb->prefix . 'jg_map_user_xp';
        $table_achievements = $wpdb->prefix . 'jg_map_achievements';
        $table_user_achievements = $wpdb->prefix . 'jg_map_user_achievements';
        $table_points = $wpdb->prefix . 'jg_map_points';
        $table_votes  = $wpdb->prefix . 'jg_map_votes';

        $user_ids = $wpdb->get_col("SELECT user_id FROM $table_user_xp");
        $achievements = $wpdb->get_results("SELECT * FROM $table_achievements ORDER BY sort_order ASC", ARRAY_A);

        $new_achievements = 0;

        foreach ($user_ids as $uid) {
            $uid = intval($uid);

            // Get already earned
            $earned_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT achievement_id FROM $table_user_achievements WHERE user_id = %d",
                $uid
            ));

            // Get user stats
            $user_xp = self::get_user_xp_data($uid);

            $points_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'publish'",
                $uid
            ));

            $votes_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_votes WHERE user_id = %d",
                $uid
            ));

            $photos_data = $wpdb->get_results($wpdb->prepare(
                "SELECT images FROM $table_points WHERE author_id = %d AND status = 'publish' AND images IS NOT NULL AND images != ''",
                $uid
            ), ARRAY_A);
            $photos_count = 0;
            foreach ($photos_data as $pd) {
                $imgs = json_decode($pd['images'], true);
                if (is_array($imgs)) {
                    $photos_count += count($imgs);
                }
            }

            $types = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT type FROM $table_points WHERE author_id = %d AND status = 'publish'",
                $uid
            ));
            $has_all_types = (in_array('miejsce', $types) && in_array('ciekawostka', $types) && in_array('zgloszenie', $types));

            $received_upvotes = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_votes v
                 INNER JOIN $table_points p ON v.point_id = p.id
                 WHERE p.author_id = %d AND v.vote_type = 'up'",
                $uid
            ));

            foreach ($achievements as $ach) {
                if (in_array($ach['id'], $earned_ids)) {
                    continue;
                }

                $earned = false;
                switch ($ach['condition_type']) {
                    case 'points_count':
                        $earned = ($points_count >= intval($ach['condition_value']));
                        break;
                    case 'votes_count':
                        $earned = ($votes_count >= intval($ach['condition_value']));
                        break;
                    case 'photos_count':
                        $earned = ($photos_count >= intval($ach['condition_value']));
                        break;
                    case 'level':
                        $earned = ($user_xp['level'] >= intval($ach['condition_value']));
                        break;
                    case 'all_types':
                        $earned = $has_all_types;
                        break;
                    case 'received_upvotes':
                        $earned = ($received_upvotes >= intval($ach['condition_value']));
                        break;
                }

                if ($earned) {
                    $wpdb->insert($table_user_achievements, array(
                        'user_id' => $uid,
                        'achievement_id' => intval($ach['id']),
                        'notified' => 1 // Mark as already notified (no spam)
                    ));
                    $new_achievements++;
                }
            }
        }

        return array(
            'users_checked' => count($user_ids),
            'new_achievements_awarded' => $new_achievements
        );
    }

    public function ajax_delete_achievement() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $id = isset($_POST['achievement_id']) ? intval($_POST['achievement_id']) : 0;
        if (!$id) {
            wp_send_json_error('Missing ID');
            return;
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'jg_map_achievements', array('id' => $id));
        $wpdb->delete($wpdb->prefix . 'jg_map_user_achievements', array('achievement_id' => $id));

        wp_send_json_success('Deleted');
    }
}
