<?php
/**
 * Trait: admin moderation of points and reports
 * get_reports, handle_reports, admin_edit_and_resolve_reports, keep_reported_place,
 * admin_toggle_promo, admin_toggle_author, admin_update_note, admin_change_status,
 * admin_approve_point, admin_reject_point
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

trait JG_Ajax_AdminModeration {

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
                'date' => human_time_diff(strtotime($report['created_at'] . ' UTC'), time()) . ' temu'
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
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

        if (!$point_id || !in_array($action_type, array('keep', 'remove', 'edit'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Load point before potential deletion — needed for notifications and activity log
        $point = JG_Map_Database::get_point($point_id);
        $point_title = $point ? $point['title'] : "ID:{$point_id}";

        if ($action_type === 'remove') {
            $message = 'Miejsce usunięte';
            $decision_text = 'usunięte';
        } else if ($action_type === 'edit') {
            $message = 'Miejsce edytowane';
            $decision_text = 'edytowane i pozostawione';
        } else {
            $message = 'Miejsce pozostawione';
            $decision_text = 'pozostawione bez zmian';
        }

        // Notify reporters before deletion — delete_point removes reports from DB
        $this->notify_reporters_decision($point_id, $decision_text, $reason);

        if ($action_type === 'remove') {
            JG_Map_Database::delete_point($point_id);
        } else {
            // Resolve reports (keep/edit path — point stays in DB)
            JG_Map_Database::resolve_reports($point_id, $reason);
        }

        // Queue sync event via dedicated sync manager
        if ($action_type === 'remove') {
            JG_Map_Sync_Manager::get_instance()->queue_point_deleted($point_id, array(
                'reason' => $reason,
                'via_reports' => true
            ));
        } else {
            JG_Map_Sync_Manager::get_instance()->queue_report_resolved($point_id, array(
                'action_type' => $action_type,
                'reason' => $reason
            ));
        }

        // Log action
        JG_Map_Activity_Log::log(
            'handle_reports',
            'point',
            $point_id,
            sprintf('Rozpatrzono zgłoszenia dla: %s. Decyzja: %s', $point_title, $decision_text)
        );

        wp_send_json_success(array('message' => $message));
    }

    /**
     * Edit place and resolve reports (admin only)
     * This is used when editing a reported place - edits are applied immediately and reports are closed
     */
    public function admin_edit_and_resolve_reports() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        // Use wp_unslash() to remove WordPress magic quotes before sanitizing
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $type = sanitize_text_field($_POST['type'] ?? '');
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $website = !empty($_POST['website']) ? esc_url_raw($_POST['website']) : '';
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $email = !empty($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';

        // Social media URLs
        $facebook_url = !empty($_POST['facebook_url']) ? $this->normalize_social_url($_POST['facebook_url'], 'facebook') : '';
        $instagram_url = !empty($_POST['instagram_url']) ? $this->normalize_social_url($_POST['instagram_url'], 'instagram') : '';
        $linkedin_url = !empty($_POST['linkedin_url']) ? $this->normalize_social_url($_POST['linkedin_url'], 'linkedin') : '';
        $tiktok_url = !empty($_POST['tiktok_url']) ? $this->normalize_social_url($_POST['tiktok_url'], 'tiktok') : '';

        $cta_enabled = isset($_POST['cta_enabled']) ? 1 : 0;
        $cta_type = sanitize_text_field($_POST['cta_type'] ?? '');

        // Address/location data
        $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
        $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
        $address = sanitize_text_field(wp_unslash($_POST['address'] ?? ''));

        // Process tags (max 5)
        $tags_raw = isset($_POST['tags']) ? wp_unslash($_POST['tags']) : '';
        $tags = array();
        if (!empty($tags_raw)) {
            $tags_array = is_array($tags_raw) ? $tags_raw : explode(',', $tags_raw);
            foreach ($tags_array as $tag) {
                $tag = sanitize_text_field(trim($tag));
                if ($tag !== '' && count($tags) < 5) {
                    $tags[] = $tag;
                }
            }
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        if (empty($title)) {
            wp_send_json_error(array('message' => 'Tytuł jest wymagany'));
            exit;
        }

        // Handle image uploads
        $new_images = array();
        $has_files = !empty($_FILES['images']) && (
            (is_array($_FILES['images']['name']) && !empty($_FILES['images']['name'][0])) ||
            (!is_array($_FILES['images']['name']) && !empty($_FILES['images']['name']))
        );

        if ($has_files) {
            $user_id = get_current_user_id();
            $existing_images = json_decode($point['images'] ?? '[]', true) ?: array();
            $existing_count = count($existing_images);
            $is_sponsored = (bool)$point['is_promo'];
            $max_total_images = $is_sponsored ? 12 : 6;
            $max_new_images = max(0, $max_total_images - $existing_count);

            if ($max_new_images > 0) {
                $upload_result = $this->handle_image_upload($_FILES['images'], $max_new_images, $user_id);

                if (isset($upload_result['error'])) {
                    wp_send_json_error(array('message' => $upload_result['error']));
                    exit;
                }

                $new_images = $upload_result['images'];
            }
        }

        // Update point directly (no moderation needed)
        $update_data = array(
            'title' => $title,
            'type' => $type,
            'content' => $content,
            'excerpt' => wp_trim_words($content, 20),
            'tags' => !empty($tags) ? json_encode($tags, JSON_UNESCAPED_UNICODE) : null
        );

        // Add lat/lng if provided (from address geocoding)
        if ($lat !== null && $lng !== null) {
            $update_data['lat'] = $lat;
            $update_data['lng'] = $lng;
        }

        // Add address if provided
        if (!empty($address)) {
            $update_data['address'] = $address;
        }

        // Add website, phone, email for all points; social media and CTA for sponsored only
        $update_data['website'] = !empty($website) ? $website : null;
        $update_data['phone'] = !empty($phone) ? $phone : null;
        $update_data['email'] = !empty($email) ? $email : null;
        $is_sponsored = (bool)$point['is_promo'];
        if ($is_sponsored) {
            $update_data['facebook_url'] = !empty($facebook_url) ? $facebook_url : null;
            $update_data['instagram_url'] = !empty($instagram_url) ? $instagram_url : null;
            $update_data['linkedin_url'] = !empty($linkedin_url) ? $linkedin_url : null;
            $update_data['tiktok_url'] = !empty($tiktok_url) ? $tiktok_url : null;
            $update_data['cta_enabled'] = $cta_enabled;
            $update_data['cta_type'] = !empty($cta_type) ? $cta_type : null;
        }

        // Add new images to existing images
        if (!empty($new_images)) {
            $existing_images = json_decode($point['images'] ?? '[]', true) ?: array();
            $all_images = array_merge($existing_images, $new_images);

            // Limit based on sponsored status
            $max_images = $is_sponsored ? 12 : 6;
            $all_images = array_slice($all_images, 0, $max_images);

            $update_data['images'] = json_encode($all_images);
        }

        JG_Map_Database::update_point($point_id, $update_data);

        // Notify reporters that place was edited
        $this->notify_reporters_decision($point_id, 'edytowane i pozostawione', '');

        // Resolve reports
        JG_Map_Database::resolve_reports($point_id, 'Miejsce zostało edytowane przez moderatora');

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_report_resolved($point_id, array(
            'admin_edited' => true,
            'title' => $title
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'edit_and_resolve_reports',
            'point',
            $point_id,
            sprintf('Edytowano miejsce i rozwiązano zgłoszenia: %s', $title)
        );

        wp_send_json_success(array('message' => 'Miejsce edytowane i zgłoszenia zamknięte'));
    }

    /**
     * Keep reported place - resolve all reports as "kept" (admin only)
     */
    public function keep_reported_place() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['point_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Miejsce nie istnieje'));
            exit;
        }

        // Notify reporters that place was kept
        $this->notify_reporters_decision($point_id, 'pozostawione bez zmian', 'Moderator zdecydował o pozostawieniu miejsca');

        // Resolve all pending reports
        JG_Map_Database::resolve_reports($point_id, 'Miejsce pozostawione przez moderatora');

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_report_resolved($point_id, array(
            'action' => 'kept',
            'point_title' => $point['title']
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'keep_reported_place',
            'point',
            $point_id,
            sprintf('Pozostawiono zgłoszone miejsce: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Miejsce zostało pozostawione, zgłoszenia odrzucone'));
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

        // Log action
        JG_Map_Activity_Log::log(
            'toggle_promo',
            'point',
            $point_id,
            sprintf('%s status promo dla: %s', $new_promo ? 'Włączono' : 'Wyłączono', $point['title'])
        );

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

        // Log action
        JG_Map_Activity_Log::log(
            'toggle_author',
            'point',
            $point_id,
            sprintf('%s autora dla: %s', $new_hidden ? 'Ukryto' : 'Pokazano', $point['title'])
        );

        wp_send_json_success(array('author_hidden' => $new_hidden));
    }

    /**
     * Update admin note (admin only)
     */
    public function admin_update_note() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        JG_Map_Database::update_point($point_id, array('admin_note' => $note));

        // Log action
        JG_Map_Activity_Log::log(
            'update_note',
            'point',
            $point_id,
            sprintf('Zaktualizowano notatkę dla: %s', $point ? $point['title'] : 'ID:' . $point_id)
        );

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
        $resolved_summary = isset($_POST['resolved_summary']) ? sanitize_textarea_field(wp_unslash($_POST['resolved_summary'])) : '';
        $rejection_reason = isset($_POST['rejection_reason']) ? sanitize_textarea_field(wp_unslash($_POST['rejection_reason'])) : '';

        if (!$point_id || !in_array($new_status, array('added', 'needs_better_documentation', 'reported', 'processing', 'resolved', 'rejected'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Resolved summary is required when changing to 'resolved' status
        if ($new_status === 'resolved' && empty($resolved_summary)) {
            wp_send_json_error(array('message' => 'Podsumowanie rozwiązania jest wymagane'));
            exit;
        }

        // Rejection reason is required when changing to 'rejected' status
        if ($new_status === 'rejected' && empty($rejection_reason)) {
            wp_send_json_error(array('message' => 'Powód odrzucenia jest wymagany'));
            exit;
        }

        // Get current point to check old status
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie znaleziony'));
            exit;
        }

        $update_data = array('report_status' => $new_status);

        // Set auto-delete date and summary when changing to 'resolved' status (7 days from now)
        if ($new_status === 'resolved') {
            $update_data['resolved_delete_at'] = date('Y-m-d H:i:s', strtotime('+7 days'));
            $update_data['resolved_summary'] = $resolved_summary;
        }
        // Clear resolved data when changing away from 'resolved' status
        elseif ($point['report_status'] === 'resolved' && $new_status !== 'resolved') {
            $update_data['resolved_delete_at'] = null;
            $update_data['resolved_summary'] = null;
        }

        // Set auto-delete date and reason when changing to 'rejected' status (7 days from now)
        if ($new_status === 'rejected') {
            $update_data['rejected_delete_at'] = date('Y-m-d H:i:s', strtotime('+7 days'));
            $update_data['rejected_reason'] = $rejection_reason;
        }
        // Clear rejected data when changing away from 'rejected' status
        elseif ($point['report_status'] === 'rejected' && $new_status !== 'rejected') {
            $update_data['rejected_delete_at'] = null;
            $update_data['rejected_reason'] = null;
        }

        JG_Map_Database::update_point($point_id, $update_data);

        // Log action
        $status_labels = array(
            'added' => 'dodane',
            'needs_better_documentation' => 'wymaga lepszej dokumentacji',
            'reported' => 'zgłoszone',
            'resolved' => 'rozwiązane',
            'rejected' => 'odrzucone'
        );
        JG_Map_Activity_Log::log(
            'change_report_status',
            'point',
            $point_id,
            sprintf('Zmieniono status zgłoszenia na "%s" dla: %s', $status_labels[$new_status] ?? $new_status, $point['title'])
        );

        // Get updated point to return delete date and rejection reason
        $updated_point = JG_Map_Database::get_point($point_id);

        wp_send_json_success(array(
            'report_status' => $new_status,
            'report_status_label' => $this->get_report_status_label($new_status),
            'resolved_delete_at' => $updated_point['resolved_delete_at'] ?? null,
            'resolved_summary' => $updated_point['resolved_summary'] ?? null,
            'rejected_delete_at' => $updated_point['rejected_delete_at'] ?? null,
            'rejected_reason' => $updated_point['rejected_reason'] ?? null
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

        // Update status to publish and set approved_at if this is first approval
        $update_data = array('status' => 'publish');
        if (empty($point['approved_at'])) {
            $update_data['approved_at'] = current_time('mysql', true);  // GMT time
        }
        JG_Map_Database::update_point($point_id, $update_data);

        // Resolve any pending reports for this point
        JG_Map_Database::resolve_reports($point_id, 'Punkt został zaakceptowany przez moderatora');

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_point_approved($point_id, array(
            'point_title' => $point['title'],
            'point_type' => $point['type'],
            'author_id' => intval($point['author_id']),
            'lat' => floatval($point['lat']),
            'lng' => floatval($point['lng'])
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'approve_point',
            'point',
            $point_id,
            sprintf('Zaakceptowano punkt: %s', $point['title'])
        );

        // Notify author
        $this->notify_author_approved($point_id);

        // Award XP for point approval to the author
        $author_id = intval($point['author_id']);
        if ($author_id) {
            JG_Map_Levels_Achievements::award_xp($author_id, 'point_approved', $point_id);
        }

        // Notify IndexNow: new point is now publicly visible
        if (!empty($point['slug']) && !empty($point['type'])) {
            JG_Interactive_Map::ping_indexnow_url(home_url('/' . $point['type'] . '/' . $point['slug'] . '/'));
        }

        wp_send_json_success(array('message' => 'Punkt zaakceptowany'));
    }

    /**
     * Reject pending point (admin only)
     */
    public function admin_reject_point() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

        $point = JG_Map_Database::get_point($point_id);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Return daily limit to user before deleting
        $author_id = intval($point['author_id']);
        $point_type = $point['type'];

        // Determine limit category and decrement
        if ($point_type === 'miejsce' || $point_type === 'ciekawostka') {
            $this->decrement_daily_limit($author_id, 'places');
        } elseif ($point_type === 'zgloszenie') {
            $this->decrement_daily_limit($author_id, 'reports');
        }

        // Notify author before deletion — notify_author_rejected re-fetches point internally
        $this->notify_author_rejected($point_id, $reason);

        // Revoke XP that was awarded on submission (pending points were never approved)
        if ($point['status'] === 'pending') {
            JG_Map_Levels_Achievements::revoke_xp($author_id, 'submit_point', $point_id);
        }

        JG_Map_Database::delete_point($point_id);

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_point_deleted($point_id, array(
            'reason' => $reason,
            'point_title' => $point['title'],
            'rejected' => true
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'reject_point',
            'point',
            $point_id,
            sprintf('Odrzucono punkt: %s. Powód: %s', $point['title'], $reason)
        );

        // Store rejected point ID for real-time broadcast via Heartbeat
        $rejected_points = get_transient('jg_map_rejected_points');
        if (!is_array($rejected_points)) {
            $rejected_points = array();
        }
        $rejected_points[] = array(
            'id' => $point_id,
            'timestamp' => time()
        );
        // Keep only last 100 rejections
        $rejected_points = array_slice($rejected_points, -100);
        set_transient('jg_map_rejected_points', $rejected_points, 300); // 5 minutes

        wp_send_json_success(array('message' => 'Punkt odrzucony'));
    }

}
