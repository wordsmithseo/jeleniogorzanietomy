<?php
/**
 * Trait JG_Ajax_AdminUsers
 * User management, bans, limits, trash management, point ownership.
 */
trait JG_Ajax_AdminUsers {

    /**
     * Delete point (admin only) — soft delete (move to trash)
     */
    public function admin_delete_point() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Revoke XP for a published point being moved to trash
        if ($point['status'] === 'publish') {
            $point_author_id = intval($point['author_id']);
            JG_Map_Levels_Achievements::revoke_xp($point_author_id, 'submit_point', $point_id);
            JG_Map_Levels_Achievements::revoke_xp($point_author_id, 'point_approved', $point_id);
            $imgs = json_decode($point['images'] ?? '[]', true);
            if (is_array($imgs)) {
                foreach ($imgs as $_img) {
                    JG_Map_Levels_Achievements::revoke_xp($point_author_id, 'add_photo', $point_id);
                }
            }
            global $wpdb;
            $votes_table = JG_Map_Database::get_votes_table();
            $ext_vote_count = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $votes_table WHERE point_id = %d AND user_id != %d",
                $point_id, $point_author_id
            )));
            for ($i = 0; $i < $ext_vote_count; $i++) {
                JG_Map_Levels_Achievements::revoke_xp($point_author_id, 'receive_upvote', $point_id);
            }
        }

        // Soft delete (move to trash)
        $deleted = JG_Map_Database::soft_delete_point($point_id);

        if ($deleted === false) {
            wp_send_json_error(array('message' => 'Błąd usuwania'));
            exit;
        }

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_point_deleted($point_id, array(
            'admin_deleted' => true,
            'point_title' => $point['title']
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'delete_point',
            'point',
            $point_id,
            sprintf('Przeniesiono do kosza miejsce: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Miejsce przeniesione do kosza'));
    }

    /**
     * Ban user (admin only)
     */
    public function admin_ban_user() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);
        $ban_type = sanitize_text_field($_POST['ban_type'] ?? '');

        if (!$user_id || !in_array($ban_type, array('permanent', 'temporary'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Prevent banning admins and moderators
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'jg_map_moderate')) {
            wp_send_json_error(array('message' => 'Nie można zbanować administratora ani moderatora'));
            exit;
        }

        if ($ban_type === 'permanent') {
            update_user_meta($user_id, 'jg_map_banned', 'permanent');
            delete_user_meta($user_id, 'jg_map_ban_until');
            $ban_details = 'trwale';
        } else {
            // Temporary ban
            $ban_days = intval($_POST['ban_days'] ?? 7);
            $ban_until = date('Y-m-d H:i:s', strtotime('+' . $ban_days . ' days'));

            update_user_meta($user_id, 'jg_map_banned', 'temporary');
            update_user_meta($user_id, 'jg_map_ban_until', $ban_until);
            $ban_details = sprintf('tymczasowo na %d dni', $ban_days);
        }

        // Log action
        JG_Map_Activity_Log::log(
            'ban_user',
            'user',
            $user_id,
            sprintf('Zbanowano użytkownika %s (%s)', $user->display_name, $ban_details)
        );

        wp_send_json_success(array(
            'message' => 'Użytkownik zbanowany',
            'ban_type' => $ban_type
        ));
    }

    /**
     * Unban user (admin only)
     */
    public function admin_unban_user() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        delete_user_meta($user_id, 'jg_map_banned');
        delete_user_meta($user_id, 'jg_map_ban_until');

        // Log action
        JG_Map_Activity_Log::log(
            'unban_user',
            'user',
            $user_id,
            sprintf('Odbanowano użytkownika %s', $user->display_name)
        );

        wp_send_json_success(array('message' => 'Ban usunięty'));
    }

    /**
     * Toggle user restriction (admin only)
     */
    public function admin_toggle_user_restriction() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);
        $restriction_type = sanitize_text_field($_POST['restriction_type'] ?? '');

        $allowed_restrictions = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places', 'photo_upload');
        if (!$user_id || !in_array($restriction_type, $allowed_restrictions)) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Prevent restricting admins and moderators
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'jg_map_moderate')) {
            wp_send_json_error(array('message' => 'Nie można blokować akcji administratora ani moderatora'));
            exit;
        }

        $meta_key = 'jg_map_ban_' . $restriction_type;
        $current_value = get_user_meta($user_id, $meta_key, true);

        if ($current_value) {
            // Remove restriction
            delete_user_meta($user_id, $meta_key);
            $is_restricted = false;
            $message = 'Blokada usunięta';
            $action = 'usunięto';
        } else {
            // Add restriction
            update_user_meta($user_id, $meta_key, '1');
            $is_restricted = true;
            $message = 'Blokada dodana';
            $action = 'dodano';
        }

        // Log action
        JG_Map_Activity_Log::log(
            'toggle_user_restriction',
            'user',
            $user_id,
            sprintf('%s blokadę %s dla użytkownika %s', ucfirst($action), $restriction_type, $user->display_name)
        );

        wp_send_json_success(array(
            'message' => $message,
            'is_restricted' => $is_restricted
        ));
    }

    /**
     * Get user restrictions and ban status (admin only)
     */
    public function get_user_restrictions() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $ban_status = get_user_meta($user_id, 'jg_map_banned', true);
        $ban_until = get_user_meta($user_id, 'jg_map_ban_until', true);
        $is_banned = self::is_user_banned($user_id);

        $restrictions = array();
        $restriction_types = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places', 'photo_upload');
        foreach ($restriction_types as $type) {
            if (get_user_meta($user_id, 'jg_map_ban_' . $type, true)) {
                $restrictions[] = $type;
            }
        }

        wp_send_json_success(array(
            'is_banned' => $is_banned,
            'ban_status' => $ban_status,
            'ban_until' => $ban_until,
            'restrictions' => $restrictions
        ));
    }

    /**
     * Check if user is banned - helper function
     */
    public static function is_user_banned($user_id) {
        if (!$user_id) {
            return false;
        }

        $ban_status = get_user_meta($user_id, 'jg_map_banned', true);

        if ($ban_status === 'permanent') {
            return true;
        }

        if ($ban_status === 'temporary') {
            $ban_until = get_user_meta($user_id, 'jg_map_ban_until', true);
            if ($ban_until && strtotime($ban_until) > time()) {
                return true;
            } else {
                // Ban expired, remove it
                delete_user_meta($user_id, 'jg_map_banned');
                delete_user_meta($user_id, 'jg_map_ban_until');
                return false;
            }
        }

        return false;
    }

    /**
     * Check if user has specific restriction
     */
    public static function has_user_restriction($user_id, $restriction_type) {
        if (!$user_id) {
            return false;
        }

        $meta_key = 'jg_map_ban_' . $restriction_type;
        return (bool)get_user_meta($user_id, $meta_key, true);
    }

    /**
     * Get current user's restrictions (for displaying ban banner)
     */
    public function get_my_restrictions() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_success(array(
                'is_banned' => false,
                'restrictions' => array()
            ));
            return;
        }

        $ban_status = get_user_meta($user_id, 'jg_map_banned', true);
        $ban_until = get_user_meta($user_id, 'jg_map_ban_until', true);
        $is_banned = self::is_user_banned($user_id);

        $restrictions = array();
        $restriction_types = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places', 'photo_upload');
        foreach ($restriction_types as $type) {
            if (get_user_meta($user_id, 'jg_map_ban_' . $type, true)) {
                $restrictions[] = $type;
            }
        }

        wp_send_json_success(array(
            'is_banned' => $is_banned,
            'ban_status' => $ban_status,
            'ban_until' => $ban_until,
            'restrictions' => $restrictions
        ));
    }

    /**
     * Get user's daily limits (admin only)
     */
    public function admin_get_user_limits() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        // Check if user is admin
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        if (user_can($user_id, 'manage_options') || user_can($user_id, 'jg_map_moderate')) {
            wp_send_json_success(array(
                'places_remaining' => 999,
                'reports_remaining' => 999,
                'places_limit' => 999,
                'reports_limit' => 999,
                'is_unlimited' => true
            ));
            exit;
        }

        $today = date('Y-m-d');
        $last_reset = get_user_meta($user_id, 'jg_map_daily_reset', true);

        // Reset if needed
        if ($last_reset !== $today) {
            update_user_meta($user_id, 'jg_map_daily_places', 0);
            update_user_meta($user_id, 'jg_map_daily_reports', 0);
            update_user_meta($user_id, 'jg_map_daily_reset', $today);
        }

        $places_used = intval(get_user_meta($user_id, 'jg_map_daily_places', true));
        $reports_used = intval(get_user_meta($user_id, 'jg_map_daily_reports', true));

        // Get custom limits or use defaults
        $custom_places_limit = get_user_meta($user_id, 'jg_map_daily_places_limit', true);
        $custom_reports_limit = get_user_meta($user_id, 'jg_map_daily_reports_limit', true);
        $places_limit = ($custom_places_limit !== '' && $custom_places_limit !== false) ? intval($custom_places_limit) : 5;
        $reports_limit = ($custom_reports_limit !== '' && $custom_reports_limit !== false) ? intval($custom_reports_limit) : 5;

        wp_send_json_success(array(
            'places_remaining' => max(0, $places_limit - $places_used),
            'reports_remaining' => max(0, $reports_limit - $reports_used),
            'places_limit' => $places_limit,
            'reports_limit' => $reports_limit,
            'is_admin' => false
        ));
    }

    /**
     * Set user's daily limits (admin only)
     */
    public function admin_set_user_limits() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);
        $places_limit = intval($_POST['places_limit'] ?? 5);
        $reports_limit = intval($_POST['reports_limit'] ?? 5);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        // Validate limits
        if ($places_limit < 0 || $reports_limit < 0) {
            wp_send_json_error(array('message' => 'Limity nie mogą być ujemne'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Store the actual custom limits
        update_user_meta($user_id, 'jg_map_daily_places_limit', $places_limit);
        update_user_meta($user_id, 'jg_map_daily_reports_limit', $reports_limit);

        // Get current usage
        $today = date('Y-m-d');
        $last_reset = get_user_meta($user_id, 'jg_map_daily_reset', true);

        // Reset if needed
        if ($last_reset !== $today) {
            update_user_meta($user_id, 'jg_map_daily_places', 0);
            update_user_meta($user_id, 'jg_map_daily_reports', 0);
            update_user_meta($user_id, 'jg_map_daily_reset', $today);
        }

        $places_used = intval(get_user_meta($user_id, 'jg_map_daily_places', true));
        $reports_used = intval(get_user_meta($user_id, 'jg_map_daily_reports', true));

        // Log action
        JG_Map_Activity_Log::log(
            'set_user_limits',
            'user',
            $user_id,
            sprintf('Ustawiono limity dla %s (miejsca: %d, zgłoszenia: %d)', $user->display_name, $places_limit, $reports_limit)
        );

        wp_send_json_success(array(
            'message' => 'Limity ustawione',
            'places_remaining' => max(0, $places_limit - $places_used),
            'reports_remaining' => max(0, $reports_limit - $reports_used),
            'places_limit' => $places_limit,
            'reports_limit' => $reports_limit
        ));
    }

    /**
     * Get user's monthly photo upload limit and usage (admin only)
     */
    public function admin_get_user_photo_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        if (user_can($user_id, 'manage_options') || user_can($user_id, 'jg_map_moderate')) {
            wp_send_json_success(array(
                'used_mb' => 0,
                'limit_mb' => 999,
                'used_bytes' => 0,
                'is_unlimited' => true
            ));
            exit;
        }

        // Use existing get_monthly_photo_usage method
        $monthly_data = $this->get_monthly_photo_usage($user_id);

        wp_send_json_success(array(
            'used_mb' => $monthly_data['used_mb'],
            'limit_mb' => $monthly_data['limit_mb'],
            'used_bytes' => $monthly_data['used_bytes']
        ));
    }

    /**
     * Set user's monthly photo upload limit (admin only)
     */
    public function admin_set_user_photo_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);
        $limit_mb = intval($_POST['limit_mb'] ?? 100);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        if ($limit_mb < 1) {
            wp_send_json_error(array('message' => 'Limit musi być większy niż 0'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Set custom limit
        update_user_meta($user_id, 'jg_map_photo_custom_limit', $limit_mb);

        // Log action
        JG_Map_Activity_Log::log(
            'set_user_photo_limit',
            'user',
            $user_id,
            sprintf('Ustawiono limit zdjęć dla %s: %d MB', $user->display_name, $limit_mb)
        );

        wp_send_json_success(array(
            'message' => 'Limit zdjęć ustawiony',
            'limit_mb' => $limit_mb
        ));
    }

    /**
     * Reset user's monthly photo upload limit to default 100MB (admin only)
     */
    public function admin_reset_user_photo_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Remove custom limit, falling back to default 100MB
        delete_user_meta($user_id, 'jg_map_photo_custom_limit');

        // Log action
        JG_Map_Activity_Log::log(
            'reset_user_photo_limit',
            'user',
            $user_id,
            sprintf('Zresetowano limit zdjęć dla %s do domyślnego (100MB)', $user->display_name)
        );

        // Get current usage
        $monthly_data = $this->get_monthly_photo_usage($user_id);

        wp_send_json_success(array(
            'message' => 'Limit zresetowany do domyślnego (100MB)',
            'used_mb' => $monthly_data['used_mb'],
            'limit_mb' => $monthly_data['limit_mb']
        ));
    }

    /**
     * Get user's daily edit limit (admin only)
     */
    public function admin_get_user_edit_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        if (user_can($user_id, 'manage_options') || user_can($user_id, 'jg_map_moderate')) {
            wp_send_json_success(array(
                'edit_count' => 0,
                'is_unlimited' => true
            ));
            exit;
        }

        // Get current edit count and date
        $edit_count = intval(get_user_meta($user_id, 'jg_map_edits_count', true));
        $edit_date = get_user_meta($user_id, 'jg_map_edits_date', true);
        $today = current_time('Y-m-d');

        // Reset counter if it's a new day
        if ($edit_date !== $today) {
            $edit_count = 0;
        }

        wp_send_json_success(array(
            'edit_count' => $edit_count,
            'is_unlimited' => false
        ));
    }

    /**
     * Reset user's daily edit limit (admin only)
     */
    public function admin_reset_user_edit_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Reset edit counter
        update_user_meta($user_id, 'jg_map_edits_count', 0);
        update_user_meta($user_id, 'jg_map_edits_date', current_time('Y-m-d'));

        // Log action
        JG_Map_Activity_Log::log(
            'reset_user_edit_limit',
            'user',
            $user_id,
            sprintf('Zresetowano licznik edycji dla %s', $user->display_name)
        );

        wp_send_json_success(array(
            'message' => 'Licznik edycji zresetowany',
            'edit_count' => 0
        ));
    }

    /**
     * Unblock IP address from rate limiting (admin only)
     * Supports both 'login' and 'register' types
     */
    public function admin_unblock_ip() {
        $this->verify_nonce();
        $this->check_admin();

        $ip_hash = sanitize_text_field($_POST['ip_hash'] ?? '');
        $ip_type = sanitize_text_field($_POST['ip_type'] ?? 'login');

        if (empty($ip_hash)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy hash IP'));
            exit;
        }

        // Validate ip_type
        if (!in_array($ip_type, array('login', 'register'))) {
            $ip_type = 'login';
        }

        // Delete all three transients (attempts count, time, and user data)
        $transient_key = 'jg_rate_limit_' . $ip_type . '_' . $ip_hash;
        $transient_time_key = 'jg_rate_limit_time_' . $ip_type . '_' . $ip_hash;
        $transient_userdata_key = 'jg_rate_limit_userdata_' . $ip_type . '_' . $ip_hash;

        delete_transient($transient_key);
        delete_transient($transient_time_key);
        delete_transient($transient_userdata_key);

        // Log action
        JG_Map_Activity_Log::log(
            'unblock_ip',
            'system',
            null,
            sprintf('Odblokowano adres IP (typ: %s, hash: %s)', $ip_type, $ip_hash)
        );

        wp_send_json_success(array('message' => 'Adres IP odblokowany pomyślnie'));
    }

    /**
     * Admin delete user - removes all user data including pins, photos, and account
     */
    public function admin_delete_user() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        // Check if user exists
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Prevent deleting admins and moderators
        $is_admin = user_can($user_id, 'manage_options');
        $is_moderator = user_can($user_id, 'jg_map_moderate');

        if ($is_admin || $is_moderator) {
            wp_send_json_error(array('message' => 'Nie można usunąć administratorów ani moderatorów'));
            exit;
        }

        // Get all user's points (pins)
        $user_places = JG_Map_Database::get_all_places_with_status('', '', $user_id);

        // Delete all user's points with their images
        if (!empty($user_places)) {
            foreach ($user_places as $place) {
                JG_Map_Database::delete_point($place['id']);
            }
        }

        // Delete all user meta data related to the plugin
        $meta_keys = array(
            'jg_map_ban_until',
            'jg_map_restrict_edit',
            'jg_map_restrict_delete',
            'jg_map_restrict_add',
            'jg_map_restrict_voting',
            'jg_map_restrict_add_events',
            'jg_map_restrict_add_trivia',
            'jg_map_restrict_photo_upload',
            'jg_map_daily_reset',
            'jg_map_daily_places',
            'jg_map_daily_reports',
            'jg_map_edits_count',
            'jg_map_edits_date',
            'jg_map_photo_month',
            'jg_map_photo_used_bytes',
            'jg_map_photo_custom_limit',
            'jg_map_activation_key',
            'jg_map_activation_key_time',
            'jg_map_account_status',
            'jg_map_reset_key',
            'jg_map_reset_key_time'
        );

        foreach ($meta_keys as $meta_key) {
            delete_user_meta($user_id, $meta_key);
        }

        // Delete the user account
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $deleted = wp_delete_user($user_id);

        if (!$deleted) {
            wp_send_json_error(array('message' => 'Wystąpił błąd podczas usuwania użytkownika'));
            exit;
        }

        // Log action
        JG_Map_Activity_Log::log(
            'delete_user',
            'user',
            $user_id,
            sprintf('Trwale usunięto konto użytkownika %s (wraz z %d miejscami)', $user->display_name, count($user_places))
        );

        wp_send_json_success(array('message' => 'Użytkownik został pomyślnie usunięty'));
    }

    /**
     * Restore point from trash (admin only)
     */
    public function admin_restore_point() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID miejsca'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Miejsce nie istnieje'));
            exit;
        }

        if ($point['status'] !== 'trash') {
            wp_send_json_error(array('message' => 'Miejsce nie znajduje się w koszu'));
            exit;
        }

        // Restore point to publish status
        JG_Map_Database::update_point($point_id, array('status' => 'publish'));

        // Re-award XP that was revoked when the point was trashed
        $restore_author_id = intval($point['author_id']);
        JG_Map_Levels_Achievements::award_xp($restore_author_id, 'submit_point', $point_id);
        JG_Map_Levels_Achievements::award_xp($restore_author_id, 'point_approved', $point_id);
        $restore_imgs = json_decode($point['images'] ?? '[]', true);
        if (is_array($restore_imgs)) {
            foreach ($restore_imgs as $_img) {
                JG_Map_Levels_Achievements::award_xp($restore_author_id, 'add_photo', $point_id);
            }
        }
        global $wpdb;
        $votes_table = JG_Map_Database::get_votes_table();
        $ext_vote_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $votes_table WHERE point_id = %d AND user_id != %d",
            $point_id, $restore_author_id
        )));
        for ($i = 0; $i < $ext_vote_count; $i++) {
            JG_Map_Levels_Achievements::award_xp($restore_author_id, 'receive_upvote', $point_id);
        }

        // Log action
        JG_Map_Activity_Log::log(
            'restore_point',
            'point',
            $point_id,
            sprintf('Przywrócono miejsce z kosza: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Miejsce przywrócone z kosza'));
    }

    /**
     * Permanently delete a single point that is already in trash (admin only)
     */
    public function admin_delete_permanent() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        if ($point['status'] !== 'trash') {
            wp_send_json_error(array('message' => 'Miejsce nie znajduje się w koszu'));
            exit;
        }

        $deleted = JG_Map_Database::delete_point($point_id);

        if ($deleted === false) {
            wp_send_json_error(array('message' => 'Błąd usuwania'));
            exit;
        }

        // Queue sync event
        JG_Map_Sync_Manager::get_instance()->queue_point_deleted($point_id, array(
            'admin_deleted' => true,
            'point_title' => $point['title'],
            'from_trash' => true
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'delete_point',
            'point',
            $point_id,
            sprintf('Trwale usunięto miejsce z kosza: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Miejsce zostało trwale usunięte'));
    }

    /**
     * Empty trash - permanently delete all trashed points (admin only)
     */
    public function admin_empty_trash() {
        $this->verify_nonce();
        $this->check_admin();

        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get all trashed points for logging
        $trashed_points = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title FROM $points_table WHERE status = %s",
            'trash'
        ), ARRAY_A);

        if (empty($trashed_points)) {
            wp_send_json_error(array('message' => 'Kosz jest pusty'));
            exit;
        }

        $deleted_count = 0;

        // Delete each trashed point
        foreach ($trashed_points as $point) {
            $point_id = $point['id'];

            // Delete point using the same method as admin_delete_point
            $deleted = JG_Map_Database::delete_point($point_id);

            if ($deleted !== false) {
                $deleted_count++;

                // Queue sync event
                JG_Map_Sync_Manager::get_instance()->queue_point_deleted($point_id, array(
                    'admin_deleted' => true,
                    'point_title' => $point['title'],
                    'from_trash' => true
                ));

                // Log individual deletion
                JG_Map_Activity_Log::log(
                    'delete_point',
                    'point',
                    $point_id,
                    sprintf('Trwale usunięto miejsce z kosza: %s', $point['title'])
                );
            }
        }

        // Log bulk action
        JG_Map_Activity_Log::log(
            'empty_trash',
            'system',
            0,
            sprintf('Opróżniono kosz - usunięto %d miejsc', $deleted_count)
        );

        wp_send_json_success(array(
            'message' => sprintf('Kosz został opróżniony. Usunięto %d miejsc.', $deleted_count),
            'deleted_count' => $deleted_count
        ));
    }

    /**
     * Toggle edit lock on a point (admin/moderator only)
     */
    public function admin_toggle_edit_lock() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['point_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID punktu'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // First check if point exists
        $point = $wpdb->get_row($wpdb->prepare("SELECT id, title FROM $table WHERE id = %d", $point_id), ARRAY_A);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje (ID: ' . $point_id . ')'));
            exit;
        }

        // Ensure edit_locked column exists
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'edit_locked'));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        // Get current lock status
        $current_status = intval($wpdb->get_var($wpdb->prepare("SELECT edit_locked FROM $table WHERE id = %d", $point_id)));

        // Toggle the lock
        $new_status = $current_status ? 0 : 1;
        $result = $wpdb->update(
            $table,
            array('edit_locked' => $new_status),
            array('id' => $point_id)
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Błąd zapisu do bazy danych'));
            exit;
        }

        // Log the action (with error handling)
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                $new_status ? 'lock_edit' : 'unlock_edit',
                'point',
                $point_id,
                sprintf('%s blokadę edycji miejsca: %s', $new_status ? 'Włączono' : 'Wyłączono', $point['title'])
            );
        }

        // Queue sync (with error handling)
        if (class_exists('JG_Map_Sync_Manager')) {
            JG_Map_Sync_Manager::get_instance()->queue_point_updated($point_id);
        }

        wp_send_json_success(array(
            'message' => $new_status ? 'Blokada edycji włączona' : 'Blokada edycji wyłączona',
            'edit_locked' => (bool)$new_status
        ));
    }

    /**
     * Change point owner (admin/moderator only)
     */
    public function admin_change_owner() {
        $this->verify_nonce();

        try {
            $this->check_admin();
        } catch (Exception $e) {
            throw $e;
        }

        $point_id = intval($_POST['point_id'] ?? 0);
        $new_owner_id = intval($_POST['new_owner_id'] ?? 0);

        if (!$point_id || !$new_owner_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Check if new owner exists
        $new_owner = get_userdata($new_owner_id);
        if (!$new_owner) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Get current point data
        $point = $wpdb->get_row($wpdb->prepare("SELECT id, title, author_id FROM $table WHERE id = %d", $point_id), ARRAY_A);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        $old_owner_id = $point['author_id'];
        $old_owner = get_userdata($old_owner_id);
        $old_owner_name = $old_owner ? $old_owner->display_name : 'Nieznany';

        // Update owner
        $wpdb->update(
            $table,
            array('author_id' => $new_owner_id),
            array('id' => $point_id)
        );

        // Log the action
        JG_Map_Activity_Log::log(
            'change_owner',
            'point',
            $point_id,
            sprintf('Zmieniono właściciela miejsca "%s" z %s na %s', $point['title'], $old_owner_name, $new_owner->display_name)
        );

        // Queue sync
        JG_Map_Sync_Manager::get_instance()->queue_point_updated($point_id);

        wp_send_json_success(array(
            'message' => 'Właściciel został zmieniony na: ' . $new_owner->display_name,
            'new_owner_id' => $new_owner_id,
            'new_owner_name' => $new_owner->display_name
        ));
    }

    /**
     * Search users for owner change modal (admin/moderator only)
     */
    public function admin_search_users() {
        $this->verify_nonce();

        try {
            $this->check_admin();
        } catch (Exception $e) {
            throw $e;
        }

        $search = sanitize_text_field($_POST['search'] ?? '');
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        $args = array(
            'number' => $per_page,
            'offset' => $offset,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email', 'user_registered')
        );

        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total = $user_query->get_total();

        $users_data = array();
        foreach ($users as $user) {
            $users_data[] = array(
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'registered' => date('Y-m-d', strtotime($user->user_registered))
            );
        }

        wp_send_json_success(array(
            'users' => $users_data,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ));
    }

    /**
     * Reset filter preferences for all users (sets a timestamp; JS discards older saved prefs)
     */
    public function admin_reset_filter_prefs() {
        if (!current_user_can('manage_options') && !current_user_can('jg_map_moderate')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_admin_reset_filter_prefs_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }
        $ts = time();
        update_option('jg_map_filter_reset_at', $ts);
        wp_send_json_success(array('reset_at' => $ts));
    }

}
