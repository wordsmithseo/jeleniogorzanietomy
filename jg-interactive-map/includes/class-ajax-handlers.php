<?php
/**
 * AJAX Handlers for map operations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load traits
require_once __DIR__ . '/ajax/trait-categories.php';
require_once __DIR__ . '/ajax/trait-points-read.php';
require_once __DIR__ . '/ajax/trait-points-write.php';
require_once __DIR__ . '/ajax/trait-images.php';
require_once __DIR__ . '/ajax/trait-notifications.php';
require_once __DIR__ . '/ajax/trait-auth.php';
require_once __DIR__ . '/ajax/trait-geocoding.php';
require_once __DIR__ . '/ajax/trait-admin-moderation.php';
require_once __DIR__ . '/ajax/trait-admin-edits.php';
require_once __DIR__ . '/ajax/trait-admin-users.php';
require_once __DIR__ . '/ajax/trait-admin-categories.php';
require_once __DIR__ . '/ajax/trait-place-features.php';

class JG_Map_Ajax_Handlers {

    use JG_Ajax_Categories;
    use JG_Ajax_PointsRead;
    use JG_Ajax_PointsWrite;
    use JG_Ajax_Images;
    use JG_Ajax_Notifications;
    use JG_Ajax_Auth;
    use JG_Ajax_Geocoding;
    use JG_Ajax_AdminModeration;
    use JG_Ajax_AdminEdits;
    use JG_Ajax_AdminUsers;
    use JG_Ajax_AdminCategories;
    use JG_Ajax_PlaceFeatures;

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
        // Public AJAX actions (logged in and not logged in)
        add_action('wp_ajax_jg_points', array($this, 'get_points'));
        add_action('wp_ajax_nopriv_jg_points', array($this, 'get_points'));
        add_action('wp_ajax_jg_check_point_exists', array($this, 'check_point_exists'));
        add_action('wp_ajax_nopriv_jg_check_point_exists', array($this, 'check_point_exists'));
        add_action('wp_ajax_jg_check_updates', array($this, 'check_updates'));
        add_action('wp_ajax_nopriv_jg_check_updates', array($this, 'check_updates'));
        add_action('wp_ajax_jg_reverse_geocode', array($this, 'reverse_geocode'));
        add_action('wp_ajax_nopriv_jg_reverse_geocode', array($this, 'reverse_geocode'));
        add_action('wp_ajax_jg_search_address', array($this, 'search_address'));
        add_action('wp_ajax_nopriv_jg_search_address', array($this, 'search_address'));
        add_action('wp_ajax_nopriv_jg_map_login', array($this, 'login_user'));
        add_action('wp_ajax_nopriv_jg_map_register', array($this, 'register_user'));
        add_action('wp_ajax_nopriv_jg_map_forgot_password', array($this, 'forgot_password'));
        add_action('wp_ajax_nopriv_jg_google_oauth_callback', array($this, 'google_oauth_callback'));
        add_action('wp_ajax_nopriv_jg_facebook_oauth_callback', array($this, 'facebook_oauth_callback'));
        add_action('wp_ajax_nopriv_jg_map_resend_activation', array($this, 'resend_activation_email'));
        add_action('wp_ajax_jg_map_resend_activation', array($this, 'resend_activation_email'));
        add_action('wp_ajax_jg_check_registration_status', array($this, 'check_registration_status'));
        add_action('wp_ajax_nopriv_jg_check_registration_status', array($this, 'check_registration_status'));
        add_action('wp_ajax_jg_check_user_session_status', array($this, 'check_user_session_status'));
        add_action('wp_ajax_nopriv_jg_check_user_session_status', array($this, 'check_user_session_status'));
        add_action('wp_ajax_jg_logout_user', array($this, 'logout_user'));
        add_action('wp_ajax_nopriv_jg_logout_user', array($this, 'logout_user'));
        add_action('wp_ajax_jg_track_stat', array($this, 'track_stat'));
        add_action('wp_ajax_nopriv_jg_track_stat', array($this, 'track_stat'));
        add_action('wp_ajax_jg_get_point_stats', array($this, 'get_point_stats'));
        add_action('wp_ajax_nopriv_jg_get_point_stats', array($this, 'get_point_stats'));
        add_action('wp_ajax_jg_get_point_visitors', array($this, 'get_point_visitors'));
        add_action('wp_ajax_nopriv_jg_get_point_visitors', array($this, 'get_point_visitors'));
        add_action('wp_ajax_jg_get_user_info', array($this, 'get_user_info'));
        add_action('wp_ajax_nopriv_jg_get_user_info', array($this, 'get_user_info'));
        add_action('wp_ajax_jg_get_user_activity', array($this, 'get_user_activity'));
        add_action('wp_ajax_nopriv_jg_get_user_activity', array($this, 'get_user_activity'));
        add_action('wp_ajax_jg_get_ranking', array($this, 'get_ranking'));
        add_action('wp_ajax_nopriv_jg_get_ranking', array($this, 'get_ranking'));
        add_action('wp_ajax_jg_get_sidebar_points', array($this, 'get_sidebar_points'));
        add_action('wp_ajax_nopriv_jg_get_sidebar_points', array($this, 'get_sidebar_points'));
        add_action('wp_ajax_jg_map_ext_ping', array($this, 'track_banner_impression'));
        add_action('wp_ajax_nopriv_jg_map_ext_ping', array($this, 'track_banner_impression'));
        add_action('wp_ajax_jg_map_ext_tap', array($this, 'track_banner_click'));
        add_action('wp_ajax_nopriv_jg_map_ext_tap', array($this, 'track_banner_click'));
        add_action('wp_ajax_jg_map_ext_fetch', array($this, 'get_banner'));
        add_action('wp_ajax_nopriv_jg_map_ext_fetch', array($this, 'get_banner'));
        add_action('wp_ajax_jg_get_tags', array($this, 'get_tags'));
        add_action('wp_ajax_nopriv_jg_get_tags', array($this, 'get_tags'));

        // Contact place (send message to place email — available to everyone)
        add_action('wp_ajax_jg_contact_place',        array($this, 'contact_place'));
        add_action('wp_ajax_nopriv_jg_contact_place', array($this, 'contact_place'));

        // Menu actions (public read, auth write)
        add_action('wp_ajax_jg_get_menu', array($this, 'get_menu'));
        add_action('wp_ajax_nopriv_jg_get_menu', array($this, 'get_menu'));
        add_action('wp_ajax_jg_save_menu', array($this, 'save_menu'));
        add_action('wp_ajax_jg_upload_menu_photo', array($this, 'upload_menu_photo'));
        add_action('wp_ajax_jg_delete_menu_photo', array($this, 'delete_menu_photo'));

        // Offerings actions (public read, auth write)
        add_action('wp_ajax_jg_get_offerings', array($this, 'get_offerings'));
        add_action('wp_ajax_nopriv_jg_get_offerings', array($this, 'get_offerings'));
        add_action('wp_ajax_jg_save_offerings', array($this, 'save_offerings'));

        // Logged in user actions
        add_action('wp_ajax_jg_submit_point', array($this, 'submit_point'));
        add_action('wp_ajax_jg_update_point', array($this, 'update_point'));
        add_action('wp_ajax_jg_vote', array($this, 'vote'));
        add_action('wp_ajax_jg_report_point', array($this, 'report_point'));
        add_action('wp_ajax_jg_author_points', array($this, 'get_author_points'));
        add_action('wp_ajax_jg_request_deletion', array($this, 'request_deletion'));
        add_action('wp_ajax_jg_get_daily_limits', array($this, 'get_daily_limits'));
        add_action('wp_ajax_jg_map_get_current_user', array($this, 'get_current_user'));
        add_action('wp_ajax_jg_map_get_my_stats', array($this, 'get_my_stats'));
        add_action('wp_ajax_jg_map_update_profile', array($this, 'update_profile'));
        add_action('wp_ajax_jg_map_delete_profile', array($this, 'delete_profile'));

        // Admin actions
        add_action('wp_ajax_jg_get_reports', array($this, 'get_reports'));
        add_action('wp_ajax_jg_handle_reports', array($this, 'handle_reports'));
        add_action('wp_ajax_jg_admin_edit_and_resolve_reports', array($this, 'admin_edit_and_resolve_reports'));
        add_action('wp_ajax_jg_admin_toggle_promo', array($this, 'admin_toggle_promo'));
        add_action('wp_ajax_jg_admin_toggle_author', array($this, 'admin_toggle_author'));
        add_action('wp_ajax_jg_admin_update_note', array($this, 'admin_update_note'));
        add_action('wp_ajax_jg_admin_change_status', array($this, 'admin_change_status'));
        add_action('wp_ajax_jg_admin_approve_point', array($this, 'admin_approve_point'));
        add_action('wp_ajax_jg_admin_reject_point', array($this, 'admin_reject_point'));
        add_action('wp_ajax_jg_get_point_history', array($this, 'get_point_history'));
        add_action('wp_ajax_jg_admin_approve_edit', array($this, 'admin_approve_edit'), 1, 0);
        add_action('wp_ajax_jg_admin_reject_edit', array($this, 'admin_reject_edit'), 1);
        add_action('wp_ajax_jg_owner_approve_edit', array($this, 'owner_approve_edit'), 1);
        add_action('wp_ajax_jg_owner_reject_edit', array($this, 'owner_reject_edit'), 1);
        add_action('wp_ajax_jg_user_revert_edit', array($this, 'user_revert_edit'), 1);
        add_action('wp_ajax_jg_admin_update_promo_date', array($this, 'admin_update_promo_date'), 1);
        add_action('wp_ajax_jg_admin_update_promo', array($this, 'admin_update_promo'), 1);
        add_action('wp_ajax_jg_admin_update_sponsored', array($this, 'admin_update_sponsored'), 1);
        add_action('wp_ajax_jg_admin_delete_point', array($this, 'admin_delete_point'), 1);
        add_action('wp_ajax_jg_admin_ban_user', array($this, 'admin_ban_user'), 1);
        add_action('wp_ajax_jg_admin_unban_user', array($this, 'admin_unban_user'), 1);
        add_action('wp_ajax_jg_admin_toggle_user_restriction', array($this, 'admin_toggle_user_restriction'), 1);
        add_action('wp_ajax_jg_get_user_restrictions', array($this, 'get_user_restrictions'), 1);
        add_action('wp_ajax_jg_get_my_restrictions', array($this, 'get_my_restrictions'), 1);
        add_action('wp_ajax_jg_admin_approve_deletion', array($this, 'admin_approve_deletion'), 1);
        add_action('wp_ajax_jg_admin_reject_deletion', array($this, 'admin_reject_deletion'), 1);
        add_action('wp_ajax_jg_admin_get_user_limits', array($this, 'admin_get_user_limits'), 1);
        add_action('wp_ajax_jg_admin_set_user_limits', array($this, 'admin_set_user_limits'), 1);
        add_action('wp_ajax_jg_admin_get_user_photo_limit', array($this, 'admin_get_user_photo_limit'), 1);
        add_action('wp_ajax_jg_admin_set_user_photo_limit', array($this, 'admin_set_user_photo_limit'), 1);
        add_action('wp_ajax_jg_admin_reset_user_photo_limit', array($this, 'admin_reset_user_photo_limit'), 1);
        add_action('wp_ajax_jg_admin_get_user_edit_limit', array($this, 'admin_get_user_edit_limit'), 1);
        add_action('wp_ajax_jg_admin_reset_user_edit_limit', array($this, 'admin_reset_user_edit_limit'), 1);
        add_action('wp_ajax_jg_admin_unblock_ip', array($this, 'admin_unblock_ip'), 1);
        add_action('wp_ajax_jg_delete_image', array($this, 'delete_image'), 1);
        add_action('wp_ajax_jg_set_featured_image', array($this, 'set_featured_image'), 1);
        add_action('wp_ajax_jg_get_notification_counts', array($this, 'get_notification_counts'), 1);
        add_action('wp_ajax_jg_keep_reported_place', array($this, 'keep_reported_place'), 1);
        add_action('wp_ajax_jg_admin_delete_user', array($this, 'admin_delete_user'), 1);
        add_action('wp_ajax_jg_admin_restore_point', array($this, 'admin_restore_point'), 1);
        add_action('wp_ajax_jg_admin_delete_permanent', array($this, 'admin_delete_permanent'), 1);
        add_action('wp_ajax_jg_admin_empty_trash', array($this, 'admin_empty_trash'), 1);
        add_action('wp_ajax_jg_admin_toggle_edit_lock', array($this, 'admin_toggle_edit_lock'), 1);
        add_action('wp_ajax_jg_admin_change_owner', array($this, 'admin_change_owner'), 1);
        add_action('wp_ajax_jg_admin_search_users', array($this, 'admin_search_users'), 1);
        add_action('wp_ajax_jg_get_full_point_history', array($this, 'get_full_point_history'), 1);
        add_action('wp_ajax_jg_admin_revert_to_history', array($this, 'admin_revert_to_history'), 1);

        // Tag management (admin)
        add_action('wp_ajax_jg_admin_get_tags_paginated', array($this, 'admin_get_tags_paginated'), 1);
        add_action('wp_ajax_jg_admin_rename_tag', array($this, 'admin_rename_tag'), 1);
        add_action('wp_ajax_jg_admin_delete_tag', array($this, 'admin_delete_tag'), 1);
        add_action('wp_ajax_jg_admin_get_tag_suggestions', array($this, 'admin_get_tag_suggestions'), 1);
        add_action('wp_ajax_jg_admin_delete_history_entry', array($this, 'admin_delete_history_entry'), 1);

        // Report reasons management (admin only)
        add_action('wp_ajax_jg_save_report_category', array($this, 'save_report_category'), 1);
        add_action('wp_ajax_jg_update_report_category', array($this, 'update_report_category'), 1);
        add_action('wp_ajax_jg_delete_report_category', array($this, 'delete_report_category'), 1);
        add_action('wp_ajax_jg_save_report_reason', array($this, 'save_report_reason'), 1);
        add_action('wp_ajax_jg_update_report_reason', array($this, 'update_report_reason'), 1);
        add_action('wp_ajax_jg_delete_report_reason', array($this, 'delete_report_reason'), 1);
        add_action('wp_ajax_jg_suggest_reason_icon', array($this, 'suggest_reason_icon'), 1);

        // Place categories management (admin only)
        add_action('wp_ajax_jg_save_place_category', array($this, 'save_place_category'), 1);
        add_action('wp_ajax_jg_update_place_category', array($this, 'update_place_category'), 1);
        add_action('wp_ajax_jg_delete_place_category', array($this, 'delete_place_category'), 1);

        // Curiosity categories management (admin only)
        add_action('wp_ajax_jg_save_curiosity_category', array($this, 'save_curiosity_category'), 1);
        add_action('wp_ajax_jg_update_curiosity_category', array($this, 'update_curiosity_category'), 1);
        add_action('wp_ajax_jg_delete_curiosity_category', array($this, 'delete_curiosity_category'), 1);

        // Filter prefs reset (admin only)
        add_action('wp_ajax_jg_admin_reset_filter_prefs', array($this, 'admin_reset_filter_prefs'), 1);

        // Business promotion request (logged in users)
        add_action('wp_ajax_jg_request_promotion', array($this, 'request_promotion'));

        // Page search for admin autocomplete
        add_action('wp_ajax_jg_map_search_pages', array($this, 'search_pages'));

        // SEO settings for pins (admin only)
        add_action('wp_ajax_jg_admin_save_seo', array($this, 'admin_save_seo'), 1);

        // Track last login time
        add_action('wp_login', array($this, 'track_last_login'), 10, 2);
    }

    /**
     * Track last login time
     */
    public function track_last_login($user_login, $user) {
        update_user_meta($user->ID, 'jg_map_last_login', current_time('mysql', true));
    }

    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!isset($_POST['_ajax_nonce'])) {
            wp_send_json_error(array('message' => 'Błąd bezpieczeństwa - brak nonce'));
            exit;
        }

        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'jg_map_nonce')) {
            wp_send_json_error(array('message' => 'Błąd bezpieczeństwa - nieprawidłowy nonce'));
            exit;
        }
    }

    /**
     * Check if user is admin or moderator
     */
    private function check_admin() {
        // jg_map_manage is dynamically granted to manage_options and jg_map_admin users
        if (!current_user_can('jg_map_manage') && !current_user_can('jg_map_moderate')) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }
    }

    /**
     * Get user IP address
     */
    private function get_user_ip() {
        // Always use REMOTE_ADDR as the authoritative source — it cannot be spoofed
        // by the connecting client. Trusting HTTP_X_FORWARDED_FOR or
        // HTTP_CF_CONNECTING_IP without verifying the source of the request allows
        // an attacker to bypass rate limiting by sending arbitrary header values.
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '0.0.0.0';
        }

        return $ip;
    }

    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = array(
            'pending' => 'Oczekujące',
            'publish' => 'Opublikowane',
            'edit' => 'Edycja w moderacji',
            'trash' => 'Usunięte'
        );

        return $labels[$status] ?? $status;
    }

    /**
     * Get report status label
     */
    private function get_report_status_label($status) {
        $labels = array(
            'added' => 'Dodane',
            'needs_better_documentation' => 'Wymaga lepszego udokumentowania',
            'reported' => 'Zgłoszone do instytucji',
            'processing' => 'Procesowanie',
            'resolved' => 'Rozwiązane',
            'rejected' => 'Odrzucono'
        );

        return $labels[$status] ?? $status;
    }

}
