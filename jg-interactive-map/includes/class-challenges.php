<?php
/**
 * Weekly Challenges System
 *
 * Manages community challenges with progress tracking and admin CRUD.
 */

if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Challenges {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_jg_get_active_challenge',        array($this, 'ajax_get_active_challenge'));
        add_action('wp_ajax_nopriv_jg_get_active_challenge', array($this, 'ajax_get_active_challenge'));

        add_action('wp_ajax_jg_admin_get_challenges',    array($this, 'ajax_get_all'));
        add_action('wp_ajax_jg_admin_save_challenge',    array($this, 'ajax_save'));
        add_action('wp_ajax_jg_admin_delete_challenge',  array($this, 'ajax_delete'));
    }

    // =========================================================================
    // DATABASE
    // =========================================================================

    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            point_type varchar(50) DEFAULT NULL,
            category varchar(100) DEFAULT NULL,
            target_count int(11) NOT NULL DEFAULT 10,
            xp_reward int(11) NOT NULL DEFAULT 100,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY end_date (end_date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // =========================================================================
    // DATA ACCESS
    // =========================================================================

    public static function get_active_with_progress() {
        global $wpdb;
        $table  = $wpdb->prefix . 'jg_map_challenges';
        $now    = current_time('mysql');

        $challenge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$table` WHERE is_active = 1 AND start_date <= %s AND end_date >= %s ORDER BY start_date DESC LIMIT 1",
            $now,
            $now
        ));

        if (!$challenge) {
            return null;
        }

        $points_table = $wpdb->prefix . 'jg_map_points';
        $sql    = "SELECT COUNT(*) FROM `$points_table` WHERE status = 'approved' AND approved_at >= %s AND approved_at <= %s";
        $params = array($challenge->start_date, $challenge->end_date);

        if (!empty($challenge->point_type)) {
            $sql     .= " AND type = %s";
            $params[] = $challenge->point_type;
        }
        if (!empty($challenge->category)) {
            $sql     .= " AND category = %s";
            $params[] = $challenge->category;
        }

        $progress = (int) $wpdb->get_var($wpdb->prepare($sql, $params));

        return array(
            'id'           => (int) $challenge->id,
            'title'        => $challenge->title,
            'description'  => $challenge->description,
            'target_count' => (int) $challenge->target_count,
            'xp_reward'    => (int) $challenge->xp_reward,
            'progress'     => $progress,
            'end_date'     => $challenge->end_date,
            'point_type'   => $challenge->point_type,
            'category'     => $challenge->category,
        );
    }

    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';
        return $wpdb->get_results("SELECT * FROM `$table` ORDER BY created_at DESC");
    }

    public static function save($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';

        $row = array(
            'title'        => sanitize_text_field($data['title']),
            'description'  => sanitize_textarea_field($data['description'] ?? ''),
            'point_type'   => !empty($data['point_type']) ? sanitize_text_field($data['point_type']) : null,
            'category'     => !empty($data['category'])   ? sanitize_text_field($data['category'])   : null,
            'target_count' => max(1, intval($data['target_count'])),
            'xp_reward'    => max(0, intval($data['xp_reward'])),
            'start_date'   => sanitize_text_field($data['start_date']),
            'end_date'     => sanitize_text_field($data['end_date']),
            'is_active'    => isset($data['is_active']) ? intval($data['is_active']) : 1,
        );

        if (!empty($data['id'])) {
            $wpdb->update($table, $row, array('id' => intval($data['id'])));
            return intval($data['id']);
        }

        $wpdb->insert($table, $row);
        return $wpdb->insert_id;
    }

    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';
        $wpdb->delete($table, array('id' => intval($id)));
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    public function ajax_get_active_challenge() {
        check_ajax_referer('jg_map_nonce', '_ajax_nonce');
        $data = self::get_active_with_progress();
        wp_send_json_success($data);
    }

    public function ajax_get_all() {
        check_ajax_referer('jg_map_admin_nonce', '_ajax_nonce');
        if (!current_user_can('jg_map_manage')) {
            wp_send_json_error('Access denied', 403);
        }
        wp_send_json_success(self::get_all());
    }

    public function ajax_save() {
        check_ajax_referer('jg_map_admin_nonce', '_ajax_nonce');
        if (!current_user_can('jg_map_manage')) {
            wp_send_json_error('Access denied', 403);
        }
        $id = self::save($_POST);
        wp_send_json_success(array('id' => $id));
    }

    public function ajax_delete() {
        check_ajax_referer('jg_map_admin_nonce', '_ajax_nonce');
        if (!current_user_can('jg_map_manage')) {
            wp_send_json_error('Access denied', 403);
        }
        self::delete(intval($_POST['challenge_id'] ?? 0));
        wp_send_json_success();
    }
}
