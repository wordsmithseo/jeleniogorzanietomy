<?php
if (!defined('ABSPATH')) { exit; }

trait JG_Ajax_AdminEdits {

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
                'created_at' => human_time_diff(strtotime($entry['created_at'] . ' UTC'), time()) . ' temu',
                'resolved_at' => $entry['resolved_at'] ? human_time_diff(strtotime($entry['resolved_at'] . ' UTC'), time()) . ' temu' : null
            );
        }

        wp_send_json_success($formatted_history);
    }

    private static function get_last_modifier_info($point_id) {
        global $wpdb;
        $table = JG_Map_Database::get_history_table();

        $last = $wpdb->get_row($wpdb->prepare(
            "SELECT h.user_id, h.resolved_by, h.resolved_at, h.created_at
             FROM $table h
             WHERE h.point_id = %d AND h.status = 'approved' AND h.action_type = 'edit'
             ORDER BY h.resolved_at DESC LIMIT 1",
            $point_id
        ), ARRAY_A);

        if (!$last) {
            return null;
        }

        $editor = get_userdata($last['user_id']);
        $approver = $last['resolved_by'] ? get_userdata($last['resolved_by']) : null;

        return array(
            'user_id'       => intval($last['user_id']),
            'user_name'     => $editor ? $editor->display_name : 'Nieznany',
            'approved_by'   => $approver ? $approver->display_name : null,
            'date'          => $last['resolved_at']
                ? human_time_diff(strtotime($last['resolved_at'] . ' UTC'), time()) . ' temu'
                : human_time_diff(strtotime($last['created_at'] . ' UTC'), time()) . ' temu',
            'date_raw'      => $last['resolved_at'] ?: $last['created_at'],
        );
    }

    public function get_full_point_history() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $history = JG_Map_Database::get_point_history($point_id);
        $result = array();

        foreach ($history as $entry) {
            $user = get_userdata($entry['user_id']);
            $resolved_by_user = $entry['resolved_by'] ? get_userdata($entry['resolved_by']) : null;
            $old_values = json_decode($entry['old_values'], true) ?: array();
            $new_values = json_decode($entry['new_values'], true) ?: array();

            // Build list of changed fields
            $changes = array();
            $fields_to_compare = array(
                'title' => 'Tytuł',
                'type' => 'Typ',
                'category' => 'Kategoria',
                'content' => 'Opis',
                'address' => 'Adres',
                'lat' => 'Szerokość geo.',
                'lng' => 'Długość geo.',
                'website' => 'Strona WWW',
                'phone' => 'Telefon',
                'facebook_url' => 'Facebook',
                'instagram_url' => 'Instagram',
                'linkedin_url' => 'LinkedIn',
                'tiktok_url' => 'TikTok',
                'cta_enabled' => 'CTA',
                'cta_type' => 'Typ CTA',
                'menu_sections' => 'Menu',
                'menu_size_labels' => 'Rozmiary dań',
            );

            foreach ($fields_to_compare as $field => $label) {
                $old_val = isset($old_values[$field]) ? (string)$old_values[$field] : '';
                $new_val = isset($new_values[$field]) ? (string)$new_values[$field] : '';
                // Normalize empty-ish values to avoid false diffs (e.g. cta_enabled "0" vs "")
                $boolean_fields = array('cta_enabled');
                if (in_array($field, $boolean_fields)) {
                    $old_val = $old_val === '' ? '0' : $old_val;
                    $new_val = $new_val === '' ? '0' : $new_val;
                }
                if ($old_val !== $new_val) {
                    $changes[] = array(
                        'field' => $field,
                        'label' => $label,
                        'old'   => $old_val,
                        'new'   => $new_val,
                    );
                }
            }

            $result[] = array(
                'id'            => intval($entry['id']),
                'user_id'       => intval($entry['user_id']),
                'user_name'     => $user ? $user->display_name : 'Nieznany',
                'action_type'   => $entry['action_type'],
                'status'        => $entry['status'],
                'changes'       => $changes,
                'old_values'    => $old_values,
                'new_values'    => $new_values,
                'created_at'    => get_date_from_gmt($entry['created_at'], 'Y-m-d H:i'),
                'created_ago'   => human_time_diff(strtotime($entry['created_at'] . ' UTC'), time()) . ' temu',
                'resolved_at'   => $entry['resolved_at'] ? get_date_from_gmt($entry['resolved_at'], 'Y-m-d H:i') : null,
                'resolved_by'   => $resolved_by_user ? $resolved_by_user->display_name : null,
                'rejection_reason' => $entry['rejection_reason'] ?? null,
            );
        }

        wp_send_json_success($result);
    }

    public function admin_revert_to_history() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);
        if (!$history_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_history_table();

        $history = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d", $history_id
        ), ARRAY_A);

        if (!$history) {
            wp_send_json_error(array('message' => 'Wpis historii nie istnieje'));
            exit;
        }

        $point_id = intval($history['point_id']);
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Apply new_values = the result/outcome of this history entry
        // "Restore to this state" means restoring to what the point looked like AFTER this edit
        $target_values = json_decode($history['new_values'], true);
        if (!$target_values || !isset($target_values['title'])) {
            wp_send_json_error(array('message' => 'Brak danych do przywrócenia'));
            exit;
        }

        // Save current state as a new history entry before reverting
        $current_state = array(
            'title'   => $point['title'],
            'type'    => $point['type'],
            'category' => $point['category'] ?? null,
            'content' => $point['content'],
            'address' => $point['address'] ?? '',
            'lat'     => $point['lat'],
            'lng'     => $point['lng'],
            'website' => $point['website'] ?? null,
            'phone'   => $point['phone'] ?? null,
            'facebook_url'  => $point['facebook_url'] ?? null,
            'instagram_url' => $point['instagram_url'] ?? null,
            'linkedin_url'  => $point['linkedin_url'] ?? null,
            'tiktok_url'    => $point['tiktok_url'] ?? null,
            'cta_enabled'   => $point['cta_enabled'] ?? 0,
            'cta_type'      => $point['cta_type'] ?? null,
        );

        $admin_id = get_current_user_id();

        // Fill in missing fields in target_values with current state values
        // This prevents false diffs (e.g. cta_enabled "0" vs "" when CTA wasn't part of original edit)
        $target_state = array();
        foreach ($current_state as $key => $val) {
            $target_state[$key] = isset($target_values[$key]) ? $target_values[$key] : $val;
        }

        // Create a history entry documenting the revert
        $wpdb->insert($table, array(
            'point_id'    => $point_id,
            'user_id'     => $admin_id,
            'action_type' => 'edit',
            'old_values'  => wp_json_encode($current_state),
            'new_values'  => wp_json_encode($target_state),
            'status'      => 'approved',
            'created_at'  => current_time('mysql', true),
            'resolved_at' => current_time('mysql', true),
            'resolved_by' => $admin_id,
        ));

        // Apply target_state to the point
        $update_data = array(
            'title'   => $target_state['title'],
            'type'    => $target_state['type'],
            'content' => $target_state['content'],
            'excerpt' => wp_trim_words($target_state['content'], 20),
        );

        if (isset($target_state['category'])) {
            $update_data['category'] = $target_state['category'];
        }
        if (isset($target_state['lat']) && isset($target_state['lng'])) {
            $update_data['lat'] = floatval($target_state['lat']);
            $update_data['lng'] = floatval($target_state['lng']);
        }
        if (isset($target_state['address'])) {
            $update_data['address'] = $target_state['address'];
        }
        if (isset($target_state['website'])) {
            $update_data['website'] = $target_state['website'];
        }
        if (isset($target_state['phone'])) {
            $update_data['phone'] = $target_state['phone'];
        }
        if (isset($target_state['email'])) {
            $update_data['email'] = $target_state['email'];
        }
        if (isset($target_state['facebook_url'])) {
            $update_data['facebook_url'] = $target_state['facebook_url'];
        }
        if (isset($target_state['instagram_url'])) {
            $update_data['instagram_url'] = $target_state['instagram_url'];
        }
        if (isset($target_state['linkedin_url'])) {
            $update_data['linkedin_url'] = $target_state['linkedin_url'];
        }
        if (isset($target_state['tiktok_url'])) {
            $update_data['tiktok_url'] = $target_state['tiktok_url'];
        }
        if (isset($target_state['cta_enabled'])) {
            $update_data['cta_enabled'] = $target_state['cta_enabled'];
        }
        if (isset($target_state['cta_type'])) {
            $update_data['cta_type'] = $target_state['cta_type'];
        }

        JG_Map_Database::update_point($point_id, $update_data);

        // Log activity
        JG_Map_Activity_Log::log(
            'revert_point',
            'point',
            $point_id,
            sprintf('Przywrócono punkt "%s" do stanu z historii #%d', $point['title'], $history_id)
        );

        wp_send_json_success(array('message' => 'Punkt przywrócony do wybranego stanu'));
    }

    public function admin_delete_history_entry() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);
        if (!$history_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_history_table();

        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT id, point_id FROM $table WHERE id = %d", $history_id
        ), ARRAY_A);

        if (!$entry) {
            wp_send_json_error(array('message' => 'Wpis nie istnieje'));
            exit;
        }

        $wpdb->delete($table, array('id' => $history_id), array('%d'));

        JG_Map_Activity_Log::log(
            'delete_history',
            'point',
            $entry['point_id'],
            sprintf('Usunięto wpis historii #%d dla punktu #%d', $history_id, $entry['point_id'])
        );

        wp_send_json_success(array('message' => 'Wpis historii usunięty'));
    }

    public function admin_approve_edit() {
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

        if ($history['status'] !== 'pending') {
            wp_send_json_error(array('message' => 'Ta edycja została już rozpatrzona'));
            exit;
        }

        // Check if admin is requesting to override owner approval
        $override_owner = !empty($_POST['override_owner']) && intval($_POST['override_owner']) === 1;

        // Check if this edit requires owner approval
        $current_user_id = get_current_user_id();
        if ($history['point_owner_id'] !== null) {
            if ($history['owner_approval_status'] !== 'approved') {
                if ($override_owner) {
                    // Admin/mod is forcing override of owner approval
                    $wpdb->update(
                        $table,
                        array(
                            'owner_approval_status' => 'approved',
                            'owner_approval_at' => current_time('mysql', true),
                            'owner_approval_by' => $current_user_id
                        ),
                        array('id' => $history_id)
                    );
                    $history['owner_approval_status'] = 'approved';
                } else {
                    // Owner hasn't approved yet - check if owner is admin/mod
                    $owner_id = intval($history['point_owner_id']);
                    $owner_user = get_userdata($owner_id);
                    $owner_is_admin_or_mod = false;

                    if ($owner_user) {
                        $owner_is_admin_or_mod = in_array('administrator', $owner_user->roles) ||
                                                 in_array('jg_moderator', $owner_user->roles);
                    }

                    if ($owner_is_admin_or_mod) {
                        // Owner is admin/mod - admin approval can bypass owner approval
                        $wpdb->update(
                            $table,
                            array(
                                'owner_approval_status' => 'approved',
                                'owner_approval_at' => current_time('mysql', true),
                                'owner_approval_by' => $current_user_id
                            ),
                            array('id' => $history_id)
                        );
                        $history['owner_approval_status'] = 'approved';
                    } else {
                        // Owner is regular user - owner approval is required first
                        wp_send_json_error(array(
                            'message' => 'Ta edycja wymaga najpierw zatwierdzenia przez właściciela miejsca'
                        ));
                        exit;
                    }
                }
            }
        }

        $new_values = json_decode($history['new_values'], true);

        // Handle menu edit approval separately
        if ($history['action_type'] === 'edit_menu') {
            $menu_sections    = $new_values['menu_sections']    ?? array();
            $menu_size_labels = $new_values['menu_size_labels'] ?? array();
            JG_Map_Database::save_menu($history['point_id'], $menu_sections);
            JG_Map_Database::save_menu_size_labels($history['point_id'], $menu_size_labels);
            JG_Map_Database::approve_history($history_id, $current_user_id);
            $history_table_local = JG_Map_Database::get_history_table();
            $remaining = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $history_table_local WHERE point_id = %d AND status = 'pending' AND action_type IN ('edit','edit_menu','edit_offerings')",
                $history['point_id']
            ));
            if (!$remaining) {
                JG_Map_Database::update_point($history['point_id'], array('pending_edit' => 0));
            }
            wp_send_json_success(array('message' => 'Menu zatwierdzone'));
            exit;
        }

        if (!$new_values || !isset($new_values['title'])) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane edycji'));
            exit;
        }

        // Verify point exists
        $points_table = JG_Map_Database::get_points_table();

        // First check directly in database
        $point_check = $wpdb->get_row($wpdb->prepare("SELECT * FROM $points_table WHERE id = %d", $history['point_id']), ARRAY_A);

        // Then use helper function
        $point = JG_Map_Database::get_point($history['point_id']);

        if (!$point && !$point_check) {
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
        }

        // Prepare update data
        $update_data = array(
            'title' => $new_values['title'],
            'type' => $new_values['type'],
            'content' => $new_values['content'],
            'excerpt' => wp_trim_words($new_values['content'], 20)
        );

        // Add category if present (for reports)
        if (isset($new_values['category'])) {
            if ($new_values['type'] === 'zgloszenie' && !empty($new_values['category'])) {
                $update_data['category'] = $new_values['category'];
            } else {
                // Clear category if changing from report to other type
                $update_data['category'] = null;
            }
        }

        // Add tags if present
        if (isset($new_values['tags'])) {
            $tags_data = is_string($new_values['tags']) ? json_decode($new_values['tags'], true) : $new_values['tags'];
            $update_data['tags'] = is_array($tags_data) && !empty($tags_data) ? json_encode($tags_data, JSON_UNESCAPED_UNICODE) : null;
        }

        // Add lat/lng if present (from address geocoding)
        if (isset($new_values['lat']) && isset($new_values['lng'])) {
            $update_data['lat'] = floatval($new_values['lat']);
            $update_data['lng'] = floatval($new_values['lng']);
        }

        // Add address if present
        if (isset($new_values['address'])) {
            $update_data['address'] = $new_values['address'];
        }

        // Add website, phone, email for all points; social media and CTA for sponsored only
        if (isset($new_values['website'])) {
            $update_data['website'] = $new_values['website'];
        }
        if (isset($new_values['phone'])) {
            $update_data['phone'] = $new_values['phone'];
        }
        if (isset($new_values['email'])) {
            $update_data['email'] = $new_values['email'];
        }
        $is_sponsored = (bool)$point['is_promo'];
        if ($is_sponsored) {
            if (isset($new_values['facebook_url'])) {
                $update_data['facebook_url'] = $new_values['facebook_url'];
            }
            if (isset($new_values['instagram_url'])) {
                $update_data['instagram_url'] = $new_values['instagram_url'];
            }
            if (isset($new_values['linkedin_url'])) {
                $update_data['linkedin_url'] = $new_values['linkedin_url'];
            }
            if (isset($new_values['tiktok_url'])) {
                $update_data['tiktok_url'] = $new_values['tiktok_url'];
            }
            if (isset($new_values['cta_enabled'])) {
                $update_data['cta_enabled'] = $new_values['cta_enabled'];
            }
            if (isset($new_values['cta_type'])) {
                $update_data['cta_type'] = $new_values['cta_type'];
            }
        }

        // Handle new images if present
        if (isset($new_values['new_images'])) {
            $new_images = json_decode($new_values['new_images'], true) ?: array();
            if (!empty($new_images)) {
                // Get existing images
                $existing_images = json_decode($point['images'] ?? '[]', true) ?: array();
                // Merge old and new images
                $all_images = array_merge($existing_images, $new_images);

                // Limit based on sponsored status - 12 for sponsored, 6 for regular
                $is_sponsored = (bool)$point['is_promo'];
                $max_images = $is_sponsored ? 12 : 6;
                $all_images = array_slice($all_images, 0, $max_images);

                $update_data['images'] = json_encode($all_images);
            }
        }

        // Apply opening_hours if present in approved edit
        if (array_key_exists('opening_hours', $new_values)) {
            $update_data['opening_hours'] = !empty($new_values['opening_hours']) ? $new_values['opening_hours'] : null;
        }

        // Apply price_range and serves_cuisine if present in approved edit
        if (array_key_exists('price_range', $new_values)) {
            $update_data['price_range'] = !empty($new_values['price_range']) ? $new_values['price_range'] : null;
        }
        if (array_key_exists('serves_cuisine', $new_values)) {
            $update_data['serves_cuisine'] = !empty($new_values['serves_cuisine']) ? $new_values['serves_cuisine'] : null;
        }

        // Update point with new values
        JG_Map_Database::update_point($history['point_id'], $update_data);

        // Approve history
        JG_Map_Database::approve_history($history_id, $current_user_id);

        // Clear pending_edit flag only if no other pending edits remain
        $remaining_pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE point_id = %d AND status = 'pending' AND action_type IN ('edit','edit_menu','edit_offerings')",
            $history['point_id']
        ));
        if (!$remaining_pending) {
            JG_Map_Database::update_point($history['point_id'], array('pending_edit' => 0));
        }

        // Notify editor (the person who submitted the edit)
        $point = JG_Map_Database::get_point($history['point_id']);
        $point_title = $point ? $point['title'] : "ID:{$history['point_id']}";
        $editor = get_userdata($history['user_id']);
        if ($editor && $editor->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Twoja edycja została zaakceptowana';
            $message = "Twoja edycja miejsca \"{$point_title}\" została zaakceptowana przez moderatora.";
            wp_mail($editor->user_email, $subject, $message);
        }

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_edit_approved($history['point_id'], array(
            'history_id' => $history_id,
            'point_title' => $point_title,
            'point_type' => $point ? $point['type'] : '',
            'editor_id' => intval($history['user_id']),
            'lat' => $point ? floatval($point['lat']) : 0.0,
            'lng' => $point ? floatval($point['lng']) : 0.0
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'approve_edit',
            'history',
            $history_id,
            sprintf('Zaakceptowano edycję miejsca: %s', $point['title'])
        );

        // Notify IndexNow: point content has been updated
        if (!empty($point['slug']) && !empty($point['type'])) {
            JG_Interactive_Map::ping_indexnow_url(home_url('/' . $point['type'] . '/' . $point['slug'] . '/'));
        }

        wp_send_json_success(array('message' => 'Edycja zaakceptowana'));
    }

    public function admin_reject_edit() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

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

        if ($history['status'] !== 'pending') {
            wp_send_json_error(array('message' => 'Ta edycja została już rozpatrzona'));
            exit;
        }

        // Reject history with reason
        JG_Map_Database::reject_history($history_id, get_current_user_id(), $reason);

        // Clear pending_edit flag only if no other pending edits remain
        $history_table = JG_Map_Database::get_history_table();
        $remaining_pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $history_table WHERE point_id = %d AND status = 'pending' AND action_type IN ('edit','edit_menu','edit_offerings')",
            $history['point_id']
        ));
        if (!$remaining_pending) {
            JG_Map_Database::update_point($history['point_id'], array('pending_edit' => 0));
        }

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_edit_rejected($history['point_id'], array(
            'history_id' => $history_id,
            'reason' => $reason
        ));

        // Notify editor (the person who submitted the edit)
        $point = JG_Map_Database::get_point($history['point_id']);
        $point_title = $point ? $point['title'] : "ID:{$history['point_id']}";
        $editor = get_userdata($history['user_id']);
        if ($editor && $editor->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Twoja edycja została odrzucona';
            $message = "Twoja edycja miejsca \"{$point_title}\" została odrzucona przez moderatora.\n\n";
            if ($reason) {
                $message .= "Powód: $reason\n";
            }
            wp_mail($editor->user_email, $subject, $message);
        }

        // Log action
        JG_Map_Activity_Log::log(
            'reject_edit',
            'history',
            $history_id,
            sprintf('Odrzucono edycję miejsca: %s. Powód: %s', $point_title, $reason)
        );

        wp_send_json_success(array('message' => 'Edycja odrzucona'));
    }

    public function user_revert_edit() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();
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

        // Only the editor who submitted the edit can cancel it
        if (intval($history['user_id']) !== $user_id) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        // Can only cancel pending edits
        if ($history['status'] !== 'pending') {
            wp_send_json_error(array('message' => 'Ta edycja nie jest już oczekująca'));
            exit;
        }

        // Cancel the pending edit
        $wpdb->update(
            $table,
            array(
                'status' => 'cancelled',
                'rejection_reason' => 'Cofnięte przez użytkownika',
                'resolved_at' => current_time('mysql', true),
                'resolved_by' => $user_id
            ),
            array('id' => $history_id)
        );

        // Clear pending_edit flag if no other pending edits remain for this point
        $remaining_pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE point_id = %d AND status = 'pending' AND action_type IN ('edit','edit_menu','edit_offerings')",
            $history['point_id']
        ));
        if (!$remaining_pending) {
            JG_Map_Database::update_point($history['point_id'], array('pending_edit' => 0));
        }

        // Log action
        JG_Map_Activity_Log::log(
            'revert_edit',
            'history',
            $history_id,
            sprintf('Użytkownik cofnął swoją edycję miejsca ID: %d', $history['point_id'])
        );

        wp_send_json_success(array('message' => 'Zmiany zostały cofnięte'));
    }

    public function owner_approve_edit() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();
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

        // Check if user is the point owner or admin
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        if (!$is_admin && intval($history['point_owner_id']) !== $user_id) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        // Check if owner is also admin/moderator - if so, fully approve the edit
        $owner_id = intval($history['point_owner_id']);
        $owner_is_admin = user_can($owner_id, 'manage_options') || user_can($owner_id, 'jg_map_moderate');

        // Get point info
        $point = JG_Map_Database::get_point($history['point_id']);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }
        $editor = get_userdata($history['user_id']);

        if ($owner_is_admin) {
            // Owner is admin/mod - fully approve the edit (owner + admin approval in one step)
            $new_values = json_decode($history['new_values'], true);

            if (!$new_values || !isset($new_values['title'])) {
                wp_send_json_error(array('message' => 'Nieprawidłowe dane edycji'));
                exit;
            }

            // Prepare update data
            $update_data = array(
                'title' => $new_values['title'],
                'type' => $new_values['type'],
                'content' => $new_values['content'],
                'excerpt' => wp_trim_words($new_values['content'], 20)
            );

            // Add category if present (for reports)
            if (isset($new_values['category'])) {
                if ($new_values['type'] === 'zgloszenie' && !empty($new_values['category'])) {
                    $update_data['category'] = $new_values['category'];
                } else {
                    $update_data['category'] = null;
                }
            }

            // Add lat/lng if changed
            if (isset($new_values['lat']) && isset($new_values['lng'])) {
                $update_data['lat'] = floatval($new_values['lat']);
                $update_data['lng'] = floatval($new_values['lng']);
            }
            if (isset($new_values['address'])) {
                $update_data['address'] = $new_values['address'];
            }

            // Add website, phone, email for all points; social media and CTA for sponsored only
            if (isset($new_values['website'])) {
                $update_data['website'] = $new_values['website'];
            }
            if (isset($new_values['phone'])) {
                $update_data['phone'] = $new_values['phone'];
            }
            if (isset($new_values['email'])) {
                $update_data['email'] = $new_values['email'];
            }
            $is_sponsored = (bool)$point['is_promo'];
            if ($is_sponsored) {
                if (isset($new_values['facebook_url'])) {
                    $update_data['facebook_url'] = $new_values['facebook_url'];
                }
                if (isset($new_values['instagram_url'])) {
                    $update_data['instagram_url'] = $new_values['instagram_url'];
                }
                if (isset($new_values['linkedin_url'])) {
                    $update_data['linkedin_url'] = $new_values['linkedin_url'];
                }
                if (isset($new_values['tiktok_url'])) {
                    $update_data['tiktok_url'] = $new_values['tiktok_url'];
                }
                if (isset($new_values['cta_enabled'])) {
                    $update_data['cta_enabled'] = $new_values['cta_enabled'];
                }
                if (isset($new_values['cta_type'])) {
                    $update_data['cta_type'] = $new_values['cta_type'];
                }
            }

            // Add tags if present
            if (isset($new_values['tags'])) {
                $tags_data = is_string($new_values['tags']) ? json_decode($new_values['tags'], true) : $new_values['tags'];
                $update_data['tags'] = is_array($tags_data) && !empty($tags_data) ? json_encode($tags_data, JSON_UNESCAPED_UNICODE) : null;
            }

            // Apply opening_hours if present
            if (array_key_exists('opening_hours', $new_values)) {
                $update_data['opening_hours'] = !empty($new_values['opening_hours']) ? $new_values['opening_hours'] : null;
            }

            // Apply price_range and serves_cuisine if present
            if (array_key_exists('price_range', $new_values)) {
                $update_data['price_range'] = !empty($new_values['price_range']) ? $new_values['price_range'] : null;
            }
            if (array_key_exists('serves_cuisine', $new_values)) {
                $update_data['serves_cuisine'] = !empty($new_values['serves_cuisine']) ? $new_values['serves_cuisine'] : null;
            }

            // Handle new images if present
            if (isset($new_values['new_images'])) {
                $new_images = json_decode($new_values['new_images'], true) ?: array();
                if (!empty($new_images)) {
                    $existing_images = json_decode($point['images'] ?? '[]', true) ?: array();
                    $all_images = array_merge($existing_images, $new_images);
                    $max_images = $is_sponsored ? 12 : 6;
                    $all_images = array_slice($all_images, 0, $max_images);
                    $update_data['images'] = json_encode($all_images);
                }
            }

            // Update point with new values
            JG_Map_Database::update_point($history['point_id'], $update_data);

            // Update history - set both owner approval and full approval
            $wpdb->update(
                $table,
                array(
                    'owner_approval_status' => 'approved',
                    'owner_approval_at' => current_time('mysql', true),
                    'owner_approval_by' => $user_id,
                    'status' => 'approved',
                    'resolved_at' => current_time('mysql', true),
                    'resolved_by' => $user_id
                ),
                array('id' => $history_id)
            );

            // Clear pending_edit flag only if no other pending edits remain
            $remaining_pending_owner = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE point_id = %d AND status = 'pending' AND action_type IN ('edit','edit_menu','edit_offerings')",
                $history['point_id']
            ));
            if (!$remaining_pending_owner) {
                JG_Map_Database::update_point($history['point_id'], array('pending_edit' => 0));
            }

            // Notify editor that edit was fully approved
            if ($editor && $editor->user_email) {
                $subject = 'Portal Jeleniogórzanie to my - Twoja edycja została zaakceptowana';
                $message = "Twoja edycja miejsca \"{$point['title']}\" została zaakceptowana przez właściciela.\n\n";
                $message .= "Zmiany są już widoczne na mapie.";
                wp_mail($editor->user_email, $subject, $message);
            }

            // Queue sync event
            JG_Map_Sync_Manager::get_instance()->queue_edit_approved($history['point_id'], array(
                'history_id' => $history_id,
                'point_title' => $point['title'],
                'point_type' => $point['type'],
                'editor_id' => intval($history['user_id']),
                'lat' => floatval($point['lat']),
                'lng' => floatval($point['lng'])
            ));

            // Log action
            JG_Map_Activity_Log::log(
                'owner_approve_edit',
                'history',
                $history_id,
                sprintf('Właściciel (admin/mod) zaakceptował i zatwierdził edycję miejsca: %s', $point['title'])
            );

            // Notify IndexNow: point content has been updated (owner+mod fast-path)
            if (!empty($point['slug']) && !empty($point['type'])) {
                JG_Interactive_Map::ping_indexnow_url(home_url('/' . $point['type'] . '/' . $point['slug'] . '/'));
            }

            wp_send_json_success(array('message' => 'Edycja zaakceptowana i zatwierdzona. Zmiany są już widoczne.'));
        } else {
            // Owner is regular user - only owner approval, still needs admin approval
            $wpdb->update(
                $table,
                array(
                    'owner_approval_status' => 'approved',
                    'owner_approval_at' => current_time('mysql', true),
                    'owner_approval_by' => $user_id
                ),
                array('id' => $history_id)
            );

            // Notify editor that owner approved, now waiting for moderator
            if ($editor && $editor->user_email) {
                $subject = 'Portal Jeleniogórzanie to my - Właściciel zaakceptował twoją edycję';
                $message = "Właściciel miejsca \"{$point['title']}\" zaakceptował twoją propozycję zmian.\n\n";
                $message .= "Twoja edycja oczekuje teraz na zatwierdzenie przez moderatora.";
                wp_mail($editor->user_email, $subject, $message);
            }

            // Notify admin that edit is ready for final approval
            $this->notify_admin_edit($history['point_id']);

            // Log action
            JG_Map_Activity_Log::log(
                'owner_approve_edit',
                'history',
                $history_id,
                sprintf('Właściciel zaakceptował propozycję edycji miejsca: %s', $point['title'])
            );

            wp_send_json_success(array('message' => 'Edycja zaakceptowana. Oczekuje teraz na zatwierdzenie moderatora.'));
        }
    }

    public function owner_reject_edit() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();
        $history_id = intval($_POST['history_id'] ?? 0);
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

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

        // Check if user is the point owner or admin
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        if (!$is_admin && intval($history['point_owner_id']) !== $user_id) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        // Update owner approval status and mark history as rejected
        $wpdb->update(
            $table,
            array(
                'owner_approval_status' => 'rejected',
                'owner_approval_at' => current_time('mysql', true),
                'owner_approval_by' => $user_id,
                'status' => 'rejected',
                'resolved_at' => current_time('mysql', true),
                'resolved_by' => $user_id,
                'rejection_reason' => $reason
            ),
            array('id' => $history_id)
        );

        // Get point info for notification
        $point = JG_Map_Database::get_point($history['point_id']);
        $point_title = $point ? $point['title'] : "ID:{$history['point_id']}";
        $editor = get_userdata($history['user_id']);

        // Notify editor that owner rejected
        if ($editor && $editor->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Właściciel odrzucił twoją edycję';
            $message = "Właściciel miejsca \"{$point_title}\" odrzucił twoją propozycję zmian.\n\n";
            if ($reason) {
                $message .= "Powód: $reason\n";
            }
            wp_mail($editor->user_email, $subject, $message);
        }

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_edit_rejected($history['point_id'], array(
            'history_id' => $history_id,
            'reason' => $reason,
            'rejected_by' => 'owner'
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'owner_reject_edit',
            'history',
            $history_id,
            sprintf('Właściciel odrzucił propozycję edycji miejsca: %s. Powód: %s', $point_title, $reason)
        );

        wp_send_json_success(array('message' => 'Edycja odrzucona'));
    }

    public function admin_approve_deletion() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);
        $point_id = intval($_POST['post_id'] ?? 0);

        global $wpdb;

        // Support both history_id (from modal) and post_id (from dashboard)
        if ($history_id) {
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

            $point_id = $history['point_id'];
        } else if ($point_id) {
            // Find history entry for this point
            $table = JG_Map_Database::get_history_table();
            $history = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE point_id = %d AND action_type = 'delete_request' AND status = 'pending' ORDER BY id DESC LIMIT 1",
                $point_id
            ), ARRAY_A);

            if ($history) {
                $history_id = $history['id'];
            }
        } else {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

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

        // Approve history before deletion (if exists)
        if ($history_id) {
            JG_Map_Database::approve_history($history_id, get_current_user_id());
        }

        // Delete the point permanently
        JG_Map_Database::delete_point($point_id);

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_deletion_approved($point_id, array(
            'point_title' => $point['title'],
            'user_requested' => true
        ));

        // Notify author
        $author = get_userdata($point['author_id']);
        if ($author && $author->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Twoje zgłoszenie usunięcia zostało zaakceptowane';
            $message = "Miejsce \"{$point['title']}\" zostało usunięte zgodnie z Twoim zgłoszeniem.";
            wp_mail($author->user_email, $subject, $message);
        }

        // Log action
        JG_Map_Activity_Log::log(
            'approve_deletion',
            'point',
            $point_id,
            sprintf('Zaakceptowano żądanie usunięcia: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Miejsce usunięte'));
    }

    public function admin_reject_deletion() {
        $this->verify_nonce();
        $this->check_admin();

        $history_id = intval($_POST['history_id'] ?? 0);
        $point_id = intval($_POST['post_id'] ?? 0);
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

        global $wpdb;

        // Support both history_id (from modal) and post_id (from dashboard)
        if ($history_id) {
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

            $point_id = $history['point_id'];
        } else if ($point_id) {
            // Find history entry for this point
            $table = JG_Map_Database::get_history_table();
            $history = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE point_id = %d AND action_type = 'delete_request' AND status = 'pending' ORDER BY id DESC LIMIT 1",
                $point_id
            ), ARRAY_A);

            if ($history) {
                $history_id = $history['id'];
            }
        } else {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Clear deletion flags from point
        $points_table = JG_Map_Database::get_points_table();
        $wpdb->update(
            $points_table,
            array(
                'is_deletion_requested' => 0,
                'deletion_reason' => null,
                'deletion_requested_at' => null
            ),
            array('id' => $point_id)
        );

        // Reject history if exists with reason
        if ($history_id) {
            JG_Map_Database::reject_history($history_id, get_current_user_id(), $reason);
        }

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_deletion_rejected($point_id, array(
            'reason' => $reason,
            'history_id' => $history_id
        ));

        // Notify author
        $point = JG_Map_Database::get_point($point_id);
        if ($point) {
            $author = get_userdata($point['author_id']);
            if ($author && $author->user_email) {
                $subject = 'Portal Jeleniogórzanie to my - Twoje zgłoszenie usunięcia zostało odrzucone';
                $message = "Twoje zgłoszenie usunięcia miejsca \"{$point['title']}\" zostało odrzucone.\n\n";
                if ($reason) {
                    $message .= "Powód: $reason\n";
                }
                wp_mail($author->user_email, $subject, $message);
            }

            // Log action
            JG_Map_Activity_Log::log(
                'reject_deletion',
                'point',
                $point_id,
                sprintf('Odrzucono żądanie usunięcia: %s. Powód: %s', $point['title'], $reason)
            );
        }

        wp_send_json_success(array('message' => 'Zgłoszenie usunięcia odrzucone'));
    }

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

        // Log action
        JG_Map_Activity_Log::log(
            'update_promo_date',
            'point',
            $point_id,
            sprintf('Zaktualizowano datę promocji do %s dla: %s', $promo_until ? $promo_until : 'brak', $point['title'])
        );

        wp_send_json_success(array(
            'message' => 'Data promocji zaktualizowana',
            'promo_until' => $promo_until
        ));
    }

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

        // Log action
        JG_Map_Activity_Log::log(
            'update_promo',
            'point',
            $point_id,
            sprintf('Zaktualizowano promocję (status: %s, data: %s) dla: %s', $is_promo ? 'włączona' : 'wyłączona', $promo_until_value ?? 'brak', $point['title'])
        );

        wp_send_json_success(array(
            'message' => 'Promocja zaktualizowana',
            'is_promo' => $is_promo,
            'promo_until' => $promo_until_value
        ));
    }

    public function admin_update_sponsored() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);
        $is_sponsored = intval($_POST['is_sponsored'] ?? 0);
        $sponsored_until = sanitize_text_field($_POST['sponsored_until'] ?? '');
        $website = sanitize_text_field($_POST['website'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $cta_enabled = intval($_POST['cta_enabled'] ?? 0);
        $cta_type = sanitize_text_field($_POST['cta_type'] ?? '');


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
                'promo_until' => $sponsored_until_value,
                'website' => !empty($website) ? $website : null,
                'phone' => !empty($phone) ? $phone : null,
                'cta_enabled' => $cta_enabled,
                'cta_type' => !empty($cta_type) ? $cta_type : null
            ),
            array('id' => $point_id),
            array('%d', '%s', '%s', '%s', '%d', '%s'),  // format for data
            array('%d')         // format for where
        );

        if ($wpdb->last_error) {
        }

        // Get updated point to return current state
        $updated_point = JG_Map_Database::get_point($point_id);

        // Log action
        JG_Map_Activity_Log::log(
            'update_sponsored',
            'point',
            $point_id,
            sprintf('Zaktualizowano sponsorowanie (status: %s, data: %s) dla: %s', $is_sponsored ? 'włączone' : 'wyłączone', $sponsored_until_value ?? 'brak', $point['title'])
        );

        wp_send_json_success(array(
            'message' => 'Sponsorowanie zaktualizowane',
            'is_sponsored' => (bool)$updated_point['is_promo'],
            'sponsored_until' => $updated_point['promo_until'] ?? null,
            'website' => $updated_point['website'] ?? null,
            'phone' => $updated_point['phone'] ?? null,
            'cta_enabled' => (bool)($updated_point['cta_enabled'] ?? 0),
            'cta_type' => $updated_point['cta_type'] ?? null
        ));
    }
}
