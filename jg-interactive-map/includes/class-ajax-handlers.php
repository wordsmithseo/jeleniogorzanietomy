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

        // Logged in user actions
        add_action('wp_ajax_jg_submit_point', array($this, 'submit_point'));
        add_action('wp_ajax_jg_update_point', array($this, 'update_point'));
        add_action('wp_ajax_jg_vote', array($this, 'vote'));
        add_action('wp_ajax_jg_report_point', array($this, 'report_point'));
        add_action('wp_ajax_jg_author_points', array($this, 'get_author_points'));

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
        add_action('wp_ajax_jg_admin_delete_point', array($this, 'admin_delete_point'));
    }

    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'jg_map_nonce')) {
            wp_send_json_error(array('message' => 'Błąd bezpieczeństwa'));
            exit;
        }
    }

    /**
     * Check if user is admin or moderator
     */
    private function check_admin() {
        if (!current_user_can('manage_options') && !current_user_can('jg_map_moderate')) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }
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

            // Check if promo expired
            $is_promo = (bool)$point['is_promo'];
            $promo_until = $point['promo_until'];
            if ($is_promo && $promo_until) {
                if (strtotime($promo_until) < current_time('timestamp')) {
                    // Promo expired, update DB
                    JG_Map_Database::update_point($point['id'], array('is_promo' => 0));
                    $is_promo = false;
                }
            }

            // Status labels
            $status_label = $this->get_status_label($point['status']);
            $report_status_label = $this->get_report_status_label($point['report_status']);

            // Check if pending or edit
            $is_pending = ($point['status'] === 'pending');

            // Get pending edit history
            $edit_info = null;
            $pending_history = JG_Map_Database::get_pending_history($point['id']);
            if ($pending_history) {
                $old_values = json_decode($pending_history['old_values'], true);
                $new_values = json_decode($pending_history['new_values'], true);

                $edit_info = array(
                    'history_id' => intval($pending_history['id']),
                    'prev_title' => $old_values['title'] ?? '',
                    'prev_type' => $old_values['type'] ?? '',
                    'prev_content' => $old_values['content'] ?? '',
                    'edited_at' => human_time_diff(strtotime($pending_history['created_at']), current_time('timestamp')) . ' temu'
                );
            }
            $is_edit = ($pending_history !== null);

            $result[] = array(
                'id' => intval($point['id']),
                'title' => $point['title'],
                'excerpt' => $point['excerpt'],
                'content' => $point['content'],
                'lat' => floatval($point['lat']),
                'lng' => floatval($point['lng']),
                'type' => $point['type'],
                'promo' => $is_promo,
                'promo_until' => $promo_until,
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
                    'human' => human_time_diff(strtotime($point['created_at']), current_time('timestamp')) . ' temu'
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

        $title = sanitize_text_field($_POST['title'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');

        if (empty($title)) {
            wp_send_json_error(array('message' => 'Tytuł jest wymagany'));
            exit;
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
            JG_Map_Database::update_point($point_id, array(
                'title' => $title,
                'type' => $type,
                'content' => $content,
                'excerpt' => wp_trim_words($content, 20)
            ));

            wp_send_json_success(array('message' => 'Zaktualizowano'));
        } else {
            // All edits from map go through moderation system
            $old_values = array(
                'title' => $point['title'],
                'type' => $point['type'],
                'content' => $point['content']
            );

            $new_values = array(
                'title' => $title,
                'type' => $type,
                'content' => $content
            );

            JG_Map_Database::add_history($point_id, $user_id, 'edit', $old_values, $new_values);

            // Notify admin
            $this->notify_admin_edit($point_id);

            wp_send_json_success(array('message' => 'Edycja wysłana do moderacji'));
        }
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
                        $images[] = $movefile['url'];
                    }
                }
            }
        }

        return $images;
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

        if (!$history_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $history = JG_Map_Database::get_pending_history(0); // Get by ID instead
        global $wpdb;
        $table = JG_Map_Database::get_history_table();
        $history = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $history_id), ARRAY_A);

        if (!$history) {
            wp_send_json_error(array('message' => 'Historia nie istnieje'));
            exit;
        }

        $new_values = json_decode($history['new_values'], true);

        // Update point with new values
        JG_Map_Database::update_point($history['point_id'], array(
            'title' => $new_values['title'],
            'type' => $new_values['type'],
            'content' => $new_values['content'],
            'excerpt' => wp_trim_words($new_values['content'], 20)
        ));

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
}
