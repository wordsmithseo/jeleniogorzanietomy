<?php
/**
 * AJAX Handlers for map operations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Ajax_Handlers {

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
        add_action('wp_ajax_jg_check_updates', array($this, 'check_updates'));
        add_action('wp_ajax_nopriv_jg_check_updates', array($this, 'check_updates'));

        // Logged in user actions
        add_action('wp_ajax_jg_submit_point', array($this, 'submit_point'));
        add_action('wp_ajax_jg_update_point', array($this, 'update_point'));
        add_action('wp_ajax_jg_vote', array($this, 'vote'));
        add_action('wp_ajax_jg_report_point', array($this, 'report_point'));
        add_action('wp_ajax_jg_author_points', array($this, 'get_author_points'));
        add_action('wp_ajax_jg_request_deletion', array($this, 'request_deletion'));

        // Admin actions
        add_action('wp_ajax_jg_get_reports', array($this, 'get_reports'));
        add_action('wp_ajax_jg_handle_reports', array($this, 'handle_reports'));
        add_action('wp_ajax_jg_admin_toggle_promo', array($this, 'admin_toggle_promo'));
        add_action('wp_ajax_jg_admin_toggle_author', array($this, 'admin_toggle_author'));
        add_action('wp_ajax_jg_admin_update_note', array($this, 'admin_update_note'));
        add_action('wp_ajax_jg_admin_change_status', array($this, 'admin_change_status'));
        add_action('wp_ajax_jg_admin_approve_point', array($this, 'admin_approve_point'));
        add_action('wp_ajax_jg_admin_reject_point', array($this, 'admin_reject_point'));
        add_action('wp_ajax_jg_get_point_history', array($this, 'get_point_history'));
        add_action('wp_ajax_jg_admin_approve_edit', array($this, 'admin_approve_edit'));
        add_action('wp_ajax_jg_admin_reject_edit', array($this, 'admin_reject_edit'));
        add_action('wp_ajax_jg_admin_update_promo_date', array($this, 'admin_update_promo_date'));
        add_action('wp_ajax_jg_admin_update_promo', array($this, 'admin_update_promo'));
        add_action('wp_ajax_jg_admin_update_sponsored', array($this, 'admin_update_sponsored'));
        add_action('wp_ajax_jg_admin_delete_point', array($this, 'admin_delete_point'));
        add_action('wp_ajax_jg_admin_ban_user', array($this, 'admin_ban_user'));
        add_action('wp_ajax_jg_admin_unban_user', array($this, 'admin_unban_user'));
        add_action('wp_ajax_jg_admin_toggle_user_restriction', array($this, 'admin_toggle_user_restriction'));
        add_action('wp_ajax_jg_admin_approve_deletion', array($this, 'admin_approve_deletion'));
        add_action('wp_ajax_jg_admin_reject_deletion', array($this, 'admin_reject_deletion'));
    }

    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!isset($_POST['_ajax_nonce'])) {
            error_log('JG MAP NONCE: Nonce not set in POST data');
            wp_send_json_error(array('message' => 'Błąd bezpieczeństwa - brak nonce'));
            exit;
        }

        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'jg_map_nonce')) {
            error_log('JG MAP NONCE: Nonce verification failed - ' . $_POST['_ajax_nonce']);
            wp_send_json_error(array('message' => 'Błąd bezpieczeństwa - nieprawidłowy nonce'));
            exit;
        }

        error_log('JG MAP NONCE: Verification passed');
    }

    /**
     * Check if user is admin or moderator
     */
    private function check_admin() {
        $user_id = get_current_user_id();
        $can_manage = current_user_can('manage_options');
        $can_moderate = current_user_can('jg_map_moderate');

        error_log('JG MAP ADMIN CHECK: user_id=' . $user_id . ', can_manage=' . ($can_manage ? 'yes' : 'no') . ', can_moderate=' . ($can_moderate ? 'yes' : 'no'));

        if (!$can_manage && !$can_moderate) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        error_log('JG MAP ADMIN CHECK: Access granted');
    }

    /**
     * Check for updates - returns last modified timestamp
     */
    public function check_updates() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();
        $reports_table = JG_Map_Database::get_reports_table();
        $history_table = JG_Map_Database::get_history_table();

        // Get latest timestamp from all relevant tables
        $points_time = $wpdb->get_var("SELECT MAX(updated_at) FROM $table");
        $reports_time = $wpdb->get_var("SELECT MAX(created_at) FROM $reports_table");
        $history_time = $wpdb->get_var("SELECT MAX(created_at) FROM $history_table");

        $timestamps = array_filter(array($points_time, $reports_time, $history_time));
        $last_modified = empty($timestamps) ? current_time('mysql') : max($timestamps);

        // Get counts for moderators
        $pending_count = 0;
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        if ($is_admin) {
            $pending_points = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
            $pending_edits = $wpdb->get_var("SELECT COUNT(*) FROM $history_table WHERE status = 'pending'");
            $pending_reports = $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'");
            $pending_count = intval($pending_points) + intval($pending_edits) + intval($pending_reports);
        }

        wp_send_json_success(array(
            'last_modified' => strtotime($last_modified),
            'pending_count' => $pending_count
        ));
    }

    /**
     * Get all points
     */
    public function get_points() {
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        $current_user_id = get_current_user_id();

        $points = JG_Map_Database::get_published_points($is_admin);
        $result = array();

        foreach ($points as $point) {
            $author = get_userdata($point['author_id']);
            $author_name = '';
            $author_email = '';

            if ($author) {
                if (!$point['author_hidden']) {
                    $author_name = $author->display_name;
                }
                $author_email = $author->user_email;
            }

            // Get votes
            $votes_count = JG_Map_Database::get_votes_count($point['id']);
            $my_vote = '';
            if ($current_user_id > 0) {
                $my_vote = JG_Map_Database::get_user_vote($point['id'], $current_user_id) ?: '';
            }

            // Get reports count
            $reports_count = 0;
            if ($is_admin) {
                $reports_count = JG_Map_Database::get_reports_count($point['id']);
            }

            // Parse images
            $images = array();
            if (!empty($point['images'])) {
                $images_data = json_decode($point['images'], true);
                if (is_array($images_data)) {
                    $images = $images_data;
                }
            }

            // Check if sponsored expired
            $is_sponsored = (bool)$point['is_promo'];
            $sponsored_until = $point['promo_until'] ?? null;
            if ($is_sponsored && $sponsored_until) {
                if (strtotime($sponsored_until) < current_time('timestamp', true)) {
                    // Sponsored expired, update DB
                    JG_Map_Database::update_point($point['id'], array('is_promo' => 0));
                    $is_sponsored = false;
                }
            }

            // Status labels
            $status_label = $this->get_status_label($point['status']);
            $report_status_label = $this->get_report_status_label($point['report_status']);

            // Check if pending or edit
            $is_pending = ($point['status'] === 'pending');

            // Get pending edit or deletion history
            $edit_info = null;
            $deletion_info = null;
            $pending_history = JG_Map_Database::get_pending_history($point['id']);

            if ($pending_history) {
                $old_values = json_decode($pending_history['old_values'], true);
                $new_values = json_decode($pending_history['new_values'], true);

                if ($pending_history['action_type'] === 'edit') {
                    $edit_info = array(
                        'history_id' => intval($pending_history['id']),
                        'prev_title' => $old_values['title'] ?? '',
                        'prev_type' => $old_values['type'] ?? '',
                        'prev_content' => $old_values['content'] ?? '',
                        'new_title' => $new_values['title'] ?? '',
                        'new_type' => $new_values['type'] ?? '',
                        'new_content' => $new_values['content'] ?? '',
                        'edited_at' => human_time_diff(strtotime($pending_history['created_at']), current_time('timestamp', true)) . ' temu'
                    );
                } else if ($pending_history['action_type'] === 'delete_request') {
                    $deletion_info = array(
                        'history_id' => intval($pending_history['id']),
                        'reason' => $new_values['reason'] ?? '',
                        'requested_at' => human_time_diff(strtotime($pending_history['created_at']), current_time('timestamp', true)) . ' temu'
                    );
                }
            }
            $is_edit = ($edit_info !== null);
            $is_deletion_requested = ($deletion_info !== null);

            $result[] = array(
                'id' => intval($point['id']),
                'title' => $point['title'],
                'excerpt' => $point['excerpt'],
                'content' => $point['content'],
                'lat' => floatval($point['lat']),
                'lng' => floatval($point['lng']),
                'type' => $point['type'],
                'sponsored' => $is_sponsored,
                'sponsored_until' => $sponsored_until,
                'status' => $point['status'],
                'status_label' => $status_label,
                'report_status' => $point['report_status'],
                'report_status_label' => $report_status_label,
                'author_id' => intval($point['author_id']),
                'author_name' => $author_name,
                'author_hidden' => (bool)$point['author_hidden'],
                'images' => $images,
                'votes' => $votes_count,
                'my_vote' => $my_vote,
                'date' => array(
                    'raw' => $point['created_at'],
                    'human' => human_time_diff(strtotime($point['created_at']), current_time('timestamp', true)) . ' temu'
                ),
                'admin' => $is_admin ? array(
                    'author_name_real' => $author ? $author->display_name : '',
                    'author_email' => $author_email,
                    'ip' => $point['ip_address'] ?: '(brak)'
                ) : null,
                'admin_note' => $point['admin_note'],
                'is_pending' => $is_pending,
                'is_edit' => $is_edit,
                'edit_info' => $edit_info,
                'is_deletion_requested' => $is_deletion_requested,
                'deletion_info' => $deletion_info,
                'reports_count' => $reports_count
            );
        }

        wp_send_json_success($result);
    }

    /**
     * Submit new point
     */
    public function submit_point() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();

        // Check if user is banned
        if (self::is_user_banned($user_id)) {
            wp_send_json_error(array('message' => 'Twoje konto zostało zbanowane'));
            exit;
        }

        // Check if user has restriction for adding places
        if (self::has_user_restriction($user_id, 'add_places')) {
            wp_send_json_error(array('message' => 'Masz zablokowaną możliwość dodawania miejsc'));
            exit;
        }

        // Validate required fields
        $title = sanitize_text_field($_POST['title'] ?? '');
        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? 'zgloszenie');
        $content = wp_kses_post($_POST['content'] ?? '');
        $public_name = isset($_POST['public_name']);

        if (empty($title) || $lat === 0.0 || $lng === 0.0) {
            wp_send_json_error(array('message' => 'Wypełnij wszystkie wymagane pola'));
            exit;
        }

        // Handle image uploads
        $images = array();
        if (!empty($_FILES['images'])) {
            $images = $this->handle_image_upload($_FILES['images']);
        }

        // Get user IP
        $ip_address = $this->get_user_ip();

        // Insert point
        $point_id = JG_Map_Database::insert_point(array(
            'title' => $title,
            'content' => $content,
            'excerpt' => wp_trim_words($content, 20),
            'lat' => $lat,
            'lng' => $lng,
            'type' => $type,
            'status' => 'pending',
            'report_status' => 'added',
            'author_id' => $user_id,
            'author_hidden' => !$public_name,
            'images' => json_encode($images),
            'ip_address' => $ip_address
        ));

        if ($point_id) {
            // Send email notification to admin
            $this->notify_admin_new_point($point_id);

            wp_send_json_success(array(
                'message' => 'Punkt dodany do moderacji',
                'point_id' => $point_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Błąd zapisu'));
        }
    }

    /**
     * Update existing point
     */
    public function update_point() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();
        $point_id = intval($_POST['post_id'] ?? 0);

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Check permissions
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        if (!$is_admin && intval($point['author_id']) !== $user_id) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        // Check if user is banned (skip for admins)
        if (!$is_admin && self::is_user_banned($user_id)) {
            wp_send_json_error(array('message' => 'Twoje konto zostało zbanowane'));
            exit;
        }

        // Check if user has restriction for editing places (skip for admins)
        if (!$is_admin && self::has_user_restriction($user_id, 'edit_places')) {
            wp_send_json_error(array('message' => 'Masz zablokowaną możliwość edycji miejsc'));
            exit;
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');

        if (empty($title)) {
            wp_send_json_error(array('message' => 'Tytuł jest wymagany'));
            exit;
        }

        // Handle image uploads
        $new_images = array();
        if (!empty($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $new_images = $this->handle_image_upload($_FILES['images']);
        }

        // Check if there's already pending edit for this point
        $pending_edit = JG_Map_Database::get_pending_history($point_id);
        if ($pending_edit && !$is_admin) {
            wp_send_json_error(array('message' => 'Ta lokalizacja ma już oczekującą edycję'));
            exit;
        }

        // Admins and moderators can edit directly ONLY if they use admin panel
        // Regular edits from map always go through moderation
        $direct_edit = $is_admin && isset($_POST['admin_edit']);

        if ($direct_edit) {
            $update_data = array(
                'title' => $title,
                'type' => $type,
                'content' => $content,
                'excerpt' => wp_trim_words($content, 20)
            );

            // Add new images to existing images
            if (!empty($new_images)) {
                $existing_images = json_decode($point['images'] ?? '[]', true) ?: array();
                $all_images = array_merge($existing_images, $new_images);
                // Limit to 6 images
                $all_images = array_slice($all_images, 0, 6);
                $update_data['images'] = json_encode($all_images);
            }

            JG_Map_Database::update_point($point_id, $update_data);

            wp_send_json_success(array('message' => 'Zaktualizowano'));
        } else {
            // All edits from map go through moderation system
            $old_values = array(
                'title' => $point['title'],
                'type' => $point['type'],
                'content' => $point['content'],
                'images' => $point['images'] ?? '[]'
            );

            $new_values = array(
                'title' => $title,
                'type' => $type,
                'content' => $content,
                'new_images' => json_encode($new_images) // Store new images separately for moderation
            );

            JG_Map_Database::add_history($point_id, $user_id, 'edit', $old_values, $new_values);

            // Notify admin
            $this->notify_admin_edit($point_id);

            wp_send_json_success(array('message' => 'Edycja wysłana do moderacji'));
        }
    }

    /**
     * Request deletion of own point
     */
    public function request_deletion() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();
        $point_id = intval($_POST['post_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Check permissions - only author or admin can request deletion
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        if (!$is_admin && intval($point['author_id']) !== $user_id) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        // Check if user is banned (skip for admins)
        if (!$is_admin && self::is_user_banned($user_id)) {
            wp_send_json_error(array('message' => 'Twoje konto zostało zbanowane'));
            exit;
        }

        // Check if there's already pending deletion request for this point
        global $wpdb;
        $table = JG_Map_Database::get_history_table();
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE point_id = %d AND action_type = 'delete_request' AND status = 'pending'",
            $point_id
        ), ARRAY_A);

        if ($existing) {
            wp_send_json_error(array('message' => 'To miejsce ma już oczekujące zgłoszenie usunięcia'));
            exit;
        }

        // Admins can delete directly
        if ($is_admin && isset($_POST['admin_delete'])) {
            JG_Map_Database::delete_point($point_id);
            wp_send_json_success(array('message' => 'Miejsce usunięte'));
            exit;
        }

        // Create deletion request in history
        $old_values = array(
            'title' => $point['title'],
            'type' => $point['type'],
            'content' => $point['content']
        );

        $new_values = array(
            'reason' => $reason
        );

        JG_Map_Database::add_history($point_id, $user_id, 'delete_request', $old_values, $new_values);

        // Notify admin
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $subject = '[JG Map] Nowe zgłoszenie usunięcia miejsca';
            $message = "Użytkownik zgłosił chęć usunięcia miejsca:\n\n";
            $message .= "Tytuł: {$point['title']}\n";
            $message .= "Powód: {$reason}\n\n";
            $message .= "Sprawdź w panelu administratora.";
            wp_mail($admin_email, $subject, $message);
        }

        wp_send_json_success(array('message' => 'Zgłoszenie usunięcia wysłane do moderacji'));
    }

    /**
     * Vote on point
     */
    public function vote() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();

        // Check if user is banned
        if (self::is_user_banned($user_id)) {
            wp_send_json_error(array('message' => 'Twoje konto zostało zbanowane'));
            exit;
        }

        // Check if user has restriction for voting
        if (self::has_user_restriction($user_id, 'voting')) {
            wp_send_json_error(array('message' => 'Masz zablokowaną możliwość głosowania'));
            exit;
        }

        $point_id = intval($_POST['post_id'] ?? 0);
        $direction = sanitize_text_field($_POST['dir'] ?? '');

        if (!$point_id || !in_array($direction, array('up', 'down'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Get current vote
        $current_vote = JG_Map_Database::get_user_vote($point_id, $user_id);

        // Toggle vote
        $new_vote = '';
        if ($current_vote === $direction) {
            $new_vote = ''; // Remove vote
        } else {
            $new_vote = $direction;
        }

        JG_Map_Database::set_vote($point_id, $user_id, $new_vote);

        $votes_count = JG_Map_Database::get_votes_count($point_id);

        wp_send_json_success(array(
            'votes' => $votes_count,
            'my_vote' => $new_vote
        ));
    }

    /**
     * Report point
     */
    public function report_point() {
        $this->verify_nonce();

        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        $point_id = intval($_POST['post_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        JG_Map_Database::add_report($point_id, $user_id, $email, $reason);

        // Notify admin
        $this->notify_admin_new_report($point_id);

        wp_send_json_success(array('message' => 'Zgłoszenie wysłane'));
    }

    /**
     * Get author's points
     */
    public function get_author_points() {
        $this->verify_nonce();

        $author_id = intval($_POST['author_id'] ?? 0);

        if (!$author_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $points = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title FROM $table WHERE author_id = %d AND status = 'publish' ORDER BY created_at DESC",
                $author_id
            ),
            ARRAY_A
        );

        wp_send_json_success($points);
    }

    /**
     * Get reports for a point (admin only)
     */
    public function get_reports() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $reports = JG_Map_Database::get_reports($point_id);
        $formatted_reports = array();

        foreach ($reports as $report) {
            $user_name = 'Anonim';
            if ($report['user_id']) {
                $user = get_userdata($report['user_id']);
                if ($user) {
                    $user_name = $user->display_name;
                }
            } elseif ($report['email']) {
                $user_name = $report['email'];
            }

            $formatted_reports[] = array(
                'user_name' => $user_name,
                'reason' => $report['reason'] ?: 'Brak powodu',
                'date' => human_time_diff(strtotime($report['created_at']), current_time('timestamp')) . ' temu'
            );
        }

        wp_send_json_success(array(
            'count' => count($formatted_reports),
            'reports' => $formatted_reports
        ));
    }

    /**
     * Handle reports (admin only)
     */
    public function handle_reports() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$point_id || !in_array($action_type, array('keep', 'remove'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        if ($action_type === 'remove') {
            // Move to trash
            JG_Map_Database::update_point($point_id, array('status' => 'trash'));
            $message = 'Miejsce usunięte';
        } else {
            // Keep the point
            $message = 'Miejsce pozostawione';
        }

        // Resolve reports
        JG_Map_Database::resolve_reports($point_id, $reason);

        wp_send_json_success(array('message' => $message));
    }

    /**
     * Toggle promo status (admin only)
     */
    public function admin_toggle_promo() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $point = JG_Map_Database::get_point($point_id);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        $new_promo = !$point['is_promo'];
        JG_Map_Database::update_point($point_id, array('is_promo' => $new_promo));

        wp_send_json_success(array('promo' => $new_promo));
    }

    /**
     * Toggle author visibility (admin only)
     */
    public function admin_toggle_author() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $point = JG_Map_Database::get_point($point_id);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        $new_hidden = !$point['author_hidden'];
        JG_Map_Database::update_point($point_id, array('author_hidden' => $new_hidden));

        wp_send_json_success(array('author_hidden' => $new_hidden));
    }

    /**
     * Update admin note (admin only)
     */
    public function admin_update_note() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        JG_Map_Database::update_point($point_id, array('admin_note' => $note));

        wp_send_json_success(array('message' => 'Notatka zaktualizowana'));
    }

    /**
     * Change report status (admin only)
     */
    public function admin_change_status() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['new_status'] ?? '');

        if (!$point_id || !in_array($new_status, array('added', 'reported', 'resolved'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        JG_Map_Database::update_point($point_id, array('report_status' => $new_status));

        wp_send_json_success(array(
            'report_status' => $new_status,
            'report_status_label' => $this->get_report_status_label($new_status)
        ));
    }

    /**
     * Approve pending point (admin only)
     */
    public function admin_approve_point() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $point = JG_Map_Database::get_point($point_id);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        JG_Map_Database::update_point($point_id, array('status' => 'publish'));

        // Notify author
        $this->notify_author_approved($point_id);

        wp_send_json_success(array('message' => 'Punkt zaakceptowany'));
    }

    /**
     * Reject pending point (admin only)
     */
    public function admin_reject_point() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        $point = JG_Map_Database::get_point($point_id);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        JG_Map_Database::update_point($point_id, array('status' => 'trash'));

        // Notify author
        $this->notify_author_rejected($point_id, $reason);

        wp_send_json_success(array('message' => 'Punkt odrzucony'));
    }

    /**
     * Handle image upload
     */
    private function handle_image_upload($files) {
        $images = array();

        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        if (!function_exists('wp_get_image_editor')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        $upload_overrides = array('test_form' => false);

        // Handle multiple files
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($i >= 6) break; // Max 6 images

                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    );

                    $movefile = wp_handle_upload($file, $upload_overrides);

                    if ($movefile && !isset($movefile['error'])) {
                        // Create thumbnail
                        $thumbnail_url = $this->create_thumbnail($movefile['file'], $movefile['url']);

                        $images[] = array(
                            'full' => $movefile['url'],
                            'thumb' => $thumbnail_url ?: $movefile['url']
                        );
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Create thumbnail for uploaded image
     */
    private function create_thumbnail($file_path, $original_url) {
        $image_editor = wp_get_image_editor($file_path);

        if (is_wp_error($image_editor)) {
            return false;
        }

        // Resize to 300x300 thumbnail
        $image_editor->resize(300, 300, false);

        $file_info = pathinfo($file_path);
        $thumbnail_path = $file_info['dirname'] . '/' . $file_info['filename'] . '-thumb.' . $file_info['extension'];

        $saved = $image_editor->save($thumbnail_path);

        if (is_wp_error($saved)) {
            return false;
        }

        // Convert file path to URL
        $upload_dir = wp_upload_dir();
        $thumbnail_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $thumbnail_path);

        return $thumbnail_url;
    }

    /**
     * Get user IP address
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        }
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
            'reported' => 'Zgłoszone do instytucji',
            'resolved' => 'Rozwiązane'
        );

        return $labels[$status] ?? $status;
    }

    /**
     * Notify admin about new point
     */
    private function notify_admin_new_point($point_id) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);

        $subject = '[JG Map] Nowy punkt do moderacji';
        $message = "Nowy punkt został dodany i czeka na moderację:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Typ: {$point['type']}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-moderation') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify admin about new report
     */
    private function notify_admin_new_report($point_id) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);

        $subject = '[JG Map] Nowe zgłoszenie miejsca';
        $message = "Miejsce zostało zgłoszone:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-moderation') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify author about approved point
     */
    private function notify_author_approved($point_id) {
        $point = JG_Map_Database::get_point($point_id);
        $author = get_userdata($point['author_id']);

        if ($author && $author->user_email) {
            $subject = '[JG Map] Twój punkt został zaakceptowany';
            $message = "Twój punkt \"{$point['title']}\" został zaakceptowany i jest teraz widoczny na mapie.";

            wp_mail($author->user_email, $subject, $message);
        }
    }

    /**
     * Notify author about rejected point
     */
    private function notify_author_rejected($point_id, $reason) {
        $point = JG_Map_Database::get_point($point_id);
        $author = get_userdata($point['author_id']);

        if ($author && $author->user_email) {
            $subject = '[JG Map] Twój punkt został odrzucony';
            $message = "Twój punkt \"{$point['title']}\" został odrzucony.\n\n";
            if ($reason) {
                $message .= "Powód: $reason\n";
            }

            wp_mail($author->user_email, $subject, $message);
        }
    }

    /**
     * Notify admin about edit
     */
    private function notify_admin_edit($point_id) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);

        $subject = '[JG Map] Edycja miejsca do zatwierdzenia';
        $message = "Użytkownik zaktualizował miejsce:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-moderation') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Get point history (admin only)
     */
    public function get_point_history() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $history = JG_Map_Database::get_point_history($point_id);
        $formatted_history = array();

        foreach ($history as $entry) {
            $user = get_userdata($entry['user_id']);
            $old_values = json_decode($entry['old_values'], true);
            $new_values = json_decode($entry['new_values'], true);

            $formatted_history[] = array(
                'id' => intval($entry['id']),
                'user_name' => $user ? $user->display_name : 'Nieznany',
                'action_type' => $entry['action_type'],
                'old_values' => $old_values,
                'new_values' => $new_values,
                'status' => $entry['status'],
                'created_at' => human_time_diff(strtotime($entry['created_at']), current_time('timestamp')) . ' temu',
                'resolved_at' => $entry['resolved_at'] ? human_time_diff(strtotime($entry['resolved_at']), current_time('timestamp')) . ' temu' : null
            );
        }

        wp_send_json_success($formatted_history);
    }

    /**
     * Approve edit (admin only)
     */
    public function admin_approve_edit() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);

        error_log('JG MAP EDIT APPROVE: Received request - history_id=' . $history_id);

        if (!$history_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_history_table();
        $history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $history_id), ARRAY_A);

        if (!$history) {
            error_log('JG MAP EDIT APPROVE: History not found - history_id=' . $history_id);
            wp_send_json_error(array('message' => 'Historia nie istnieje'));
            exit;
        }

        error_log('JG MAP EDIT APPROVE: History found - point_id=' . $history['point_id'] . ', action_type=' . $history['action_type'] . ', status=' . $history['status']);

        $new_values = json_decode($history['new_values'], true);

        error_log('JG MAP EDIT APPROVE: New values - ' . json_encode($new_values));

        if (!$new_values || !isset($new_values['title'])) {
            error_log('JG MAP EDIT APPROVE: Invalid new_values');
            wp_send_json_error(array('message' => 'Nieprawidłowe dane edycji'));
            exit;
        }

        // Verify point exists
        $points_table = JG_Map_Database::get_points_table();

        // First check directly in database
        $point_check = $wpdb->get_row($wpdb->prepare("SELECT * FROM $points_table WHERE id = %d", $history['point_id']), ARRAY_A);
        error_log('JG MAP EDIT APPROVE: Direct DB check - ' . ($point_check ? 'exists (id=' . $point_check['id'] . ', status=' . $point_check['status'] . ')' : 'NOT FOUND'));

        // Then use helper function
        $point = JG_Map_Database::get_point($history['point_id']);
        error_log('JG MAP EDIT APPROVE: get_point() result - ' . ($point ? 'found (status=' . $point['status'] . ')' : 'NULL'));

        if (!$point && !$point_check) {
            error_log('JG MAP EDIT APPROVE: FATAL - Point does not exist in database at all!');
            wp_send_json_error(array(
                'message' => 'Punkt nie istnieje',
                'debug' => array(
                    'point_id' => $history['point_id'],
                    'history_id' => $history_id,
                    'action_type' => $history['action_type'],
                    'db_query_executed' => true
                )
            ));
            exit;
        }

        // Use point_check if get_point failed
        if (!$point) {
            $point = $point_check;
            error_log('JG MAP EDIT APPROVE: Using direct DB result instead of get_point()');
        }

        // Prepare update data
        $update_data = array(
            'title' => $new_values['title'],
            'type' => $new_values['type'],
            'content' => $new_values['content'],
            'excerpt' => wp_trim_words($new_values['content'], 20)
        );

        // Handle new images if present
        if (isset($new_values['new_images'])) {
            $new_images = json_decode($new_values['new_images'], true) ?: array();
            if (!empty($new_images)) {
                // Get existing images
                $existing_images = json_decode($point['images'] ?? '[]', true) ?: array();
                // Merge old and new images
                $all_images = array_merge($existing_images, $new_images);
                // Limit to 6 images
                $all_images = array_slice($all_images, 0, 6);
                $update_data['images'] = json_encode($all_images);
            }
        }

        // Update point with new values
        JG_Map_Database::update_point($history['point_id'], $update_data);

        // Approve history
        JG_Map_Database::approve_history($history_id, get_current_user_id());

        // Notify author
        $point = JG_Map_Database::get_point($history['point_id']);
        $author = get_userdata($point['author_id']);
        if ($author && $author->user_email) {
            $subject = '[JG Map] Twoja edycja została zaakceptowana';
            $message = "Twoja edycja miejsca \"{$point['title']}\" została zaakceptowana.";
            wp_mail($author->user_email, $subject, $message);
        }

        wp_send_json_success(array('message' => 'Edycja zaakceptowana'));
    }

    /**
     * Reject edit (admin only)
     */
    public function admin_reject_edit() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$history_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_history_table();
        $history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $history_id), ARRAY_A);

        if (!$history) {
            wp_send_json_error(array('message' => 'Historia nie istnieje'));
            exit;
        }

        // Reject history
        JG_Map_Database::reject_history($history_id, get_current_user_id());

        // Notify author
        $point = JG_Map_Database::get_point($history['point_id']);
        $author = get_userdata($point['author_id']);
        if ($author && $author->user_email) {
            $subject = '[JG Map] Twoja edycja została odrzucona';
            $message = "Twoja edycja miejsca \"{$point['title']}\" została odrzucona.\n\n";
            if ($reason) {
                $message .= "Powód: $reason\n";
            }
            wp_mail($author->user_email, $subject, $message);
        }

        wp_send_json_success(array('message' => 'Edycja odrzucona'));
    }

    /**
     * Approve deletion request (admin only)
     */
    public function admin_approve_deletion() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);

        if (!$history_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_history_table();
        $history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $history_id), ARRAY_A);

        if (!$history) {
            wp_send_json_error(array('message' => 'Historia nie istnieje'));
            exit;
        }

        if ($history['action_type'] !== 'delete_request') {
            wp_send_json_error(array('message' => 'Nieprawidłowy typ akcji'));
            exit;
        }

        $point = JG_Map_Database::get_point($history['point_id']);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Delete the point
        JG_Map_Database::delete_point($history['point_id']);

        // Approve history
        JG_Map_Database::approve_history($history_id, get_current_user_id());

        // Notify author
        $author = get_userdata($point['author_id']);
        if ($author && $author->user_email) {
            $subject = '[JG Map] Twoje zgłoszenie usunięcia zostało zaakceptowane';
            $message = "Miejsce \"{$point['title']}\" zostało usunięte zgodnie z Twoim zgłoszeniem.";
            wp_mail($author->user_email, $subject, $message);
        }

        wp_send_json_success(array('message' => 'Miejsce usunięte'));
    }

    /**
     * Reject deletion request (admin only)
     */
    public function admin_reject_deletion() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');

        if (!$history_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_history_table();
        $history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $history_id), ARRAY_A);

        if (!$history) {
            wp_send_json_error(array('message' => 'Historia nie istnieje'));
            exit;
        }

        if ($history['action_type'] !== 'delete_request') {
            wp_send_json_error(array('message' => 'Nieprawidłowy typ akcji'));
            exit;
        }

        // Reject history
        JG_Map_Database::reject_history($history_id, get_current_user_id());

        // Notify author
        $point = JG_Map_Database::get_point($history['point_id']);
        if ($point) {
            $author = get_userdata($point['author_id']);
            if ($author && $author->user_email) {
                $subject = '[JG Map] Twoje zgłoszenie usunięcia zostało odrzucone';
                $message = "Twoje zgłoszenie usunięcia miejsca \"{$point['title']}\" zostało odrzucone.\n\n";
                if ($reason) {
                    $message .= "Powód: $reason\n";
                }
                wp_mail($author->user_email, $subject, $message);
            }
        }

        wp_send_json_success(array('message' => 'Zgłoszenie usunięcia odrzucone'));
    }

    /**
     * Update promo date (admin only)
     */
    public function admin_update_promo_date() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $promo_until = sanitize_text_field($_POST['promo_until'] ?? '');

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // If promo_until is provided, set is_promo to 1
        $is_promo = !empty($promo_until) ? 1 : $point['is_promo'];

        JG_Map_Database::update_point($point_id, array(
            'is_promo' => $is_promo,
            'promo_until' => $promo_until ? $promo_until : null
        ));

        wp_send_json_success(array(
            'message' => 'Data promocji zaktualizowana',
            'promo_until' => $promo_until
        ));
    }

    /**
     * Update promo status and date (admin only)
     */
    public function admin_update_promo() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $is_promo = intval($_POST['is_promo'] ?? 0);
        $promo_until = sanitize_text_field($_POST['promo_until'] ?? '');

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // If promo_until is provided, ensure it's a valid date
        $promo_until_value = null;
        if (!empty($promo_until)) {
            $promo_until_value = $promo_until;
        }

        JG_Map_Database::update_point($point_id, array(
            'is_promo' => $is_promo,
            'promo_until' => $promo_until_value
        ));

        wp_send_json_success(array(
            'message' => 'Promocja zaktualizowana',
            'is_promo' => $is_promo,
            'promo_until' => $promo_until_value
        ));
    }

    /**
     * Update sponsored status and date (admin only) - NEW API with sponsored naming
     */
    public function admin_update_sponsored() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $is_sponsored = intval($_POST['is_sponsored'] ?? 0);
        $sponsored_until = sanitize_text_field($_POST['sponsored_until'] ?? '');

        error_log('JG MAP SPONSORED: Received request - point_id=' . $point_id . ', is_sponsored=' . $is_sponsored . ', sponsored_until=' . $sponsored_until);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Only allow sponsoring places and curiosities, not reports
        if ($is_sponsored && !in_array($point['type'], array('miejsce', 'ciekawostka'))) {
            wp_send_json_error(array('message' => 'Tylko miejsca i ciekawostki mogą być sponsorowane'));
            exit;
        }

        error_log('JG MAP SPONSORED: Point before update - is_promo=' . $point['is_promo'] . ', promo_until=' . ($point['promo_until'] ?? 'null'));

        // Map sponsored naming to promo in database
        $sponsored_until_value = null;
        if (!empty($sponsored_until)) {
            $sponsored_until_value = $sponsored_until;
        }

        // Use direct wpdb update with format specification to ensure proper types
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $update_result = $wpdb->update(
            $table,
            array(
                'is_promo' => $is_sponsored,
                'promo_until' => $sponsored_until_value
            ),
            array('id' => $point_id),
            array('%d', '%s'),  // format for data
            array('%d')         // format for where
        );

        error_log('JG MAP SPONSORED: Update result - ' . ($update_result !== false ? 'success (rows=' . $update_result . ')' : 'failed'));
        error_log('JG MAP SPONSORED: Last query - ' . $wpdb->last_query);
        if ($wpdb->last_error) {
            error_log('JG MAP SPONSORED: DB Error - ' . $wpdb->last_error);
        }

        // Get updated point to return current state
        $updated_point = JG_Map_Database::get_point($point_id);

        error_log('JG MAP SPONSORED: Point after update - is_promo=' . var_export($updated_point['is_promo'], true) . ' (type: ' . gettype($updated_point['is_promo']) . '), promo_until=' . var_export($updated_point['promo_until'] ?? null, true));

        wp_send_json_success(array(
            'message' => 'Sponsorowanie zaktualizowane',
            'is_sponsored' => (bool)$updated_point['is_promo'],
            'sponsored_until' => $updated_point['promo_until'] ?? null
        ));
    }

    /**
     * Delete point permanently (admin only)
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

        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();
        $votes_table = JG_Map_Database::get_votes_table();
        $reports_table = JG_Map_Database::get_reports_table();
        $history_table = JG_Map_Database::get_history_table();

        // Delete related data
        $wpdb->delete($votes_table, array('point_id' => $point_id), array('%d'));
        $wpdb->delete($reports_table, array('point_id' => $point_id), array('%d'));
        $wpdb->delete($history_table, array('point_id' => $point_id), array('%d'));

        // Delete the point itself
        $deleted = $wpdb->delete($points_table, array('id' => $point_id), array('%d'));

        if ($deleted === false) {
            wp_send_json_error(array('message' => 'Błąd usuwania'));
            exit;
        }

        wp_send_json_success(array('message' => 'Miejsce usunięte'));
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

        if ($ban_type === 'permanent') {
            update_user_meta($user_id, 'jg_map_banned', 'permanent');
            delete_user_meta($user_id, 'jg_map_ban_until');
        } else {
            // Temporary ban
            $ban_days = intval($_POST['ban_days'] ?? 7);
            $ban_until = date('Y-m-d H:i:s', strtotime('+' . $ban_days . ' days'));

            update_user_meta($user_id, 'jg_map_banned', 'temporary');
            update_user_meta($user_id, 'jg_map_ban_until', $ban_until);
        }

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

        $allowed_restrictions = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places');
        if (!$user_id || !in_array($restriction_type, $allowed_restrictions)) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        $meta_key = 'jg_map_ban_' . $restriction_type;
        $current_value = get_user_meta($user_id, $meta_key, true);

        if ($current_value) {
            // Remove restriction
            delete_user_meta($user_id, $meta_key);
            $is_restricted = false;
            $message = 'Blokada usunięta';
        } else {
            // Add restriction
            update_user_meta($user_id, $meta_key, '1');
            $is_restricted = true;
            $message = 'Blokada dodana';
        }

        wp_send_json_success(array(
            'message' => $message,
            'is_restricted' => $is_restricted
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
            if ($ban_until && strtotime($ban_until) > current_time('timestamp')) {
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
}
