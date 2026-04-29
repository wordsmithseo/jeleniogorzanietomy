<?php
/**
 * Trait: write operations on points
 * submit_point, update_point, request_deletion, vote, report_point
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

trait JG_Ajax_PointsWrite {

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
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        // Check if user is banned (skip for admins)
        if (!$is_admin && self::is_user_banned($user_id)) {
            wp_send_json_error(array('message' => 'Twoje konto zostało zbanowane'));
            exit;
        }

        // Check if user has restriction for adding places (skip for admins)
        if (!$is_admin && self::has_user_restriction($user_id, 'add_places')) {
            wp_send_json_error(array('message' => 'Masz zablokowaną możliwość dodawania miejsc'));
            exit;
        }

        // Get type to determine limit category
        $type = sanitize_text_field($_POST['type'] ?? 'zgloszenie');

        // Check daily limits - places and curiosities count together, reports separate
        if ($type === 'miejsce' || $type === 'ciekawostka') {
            if (!$this->check_daily_limit($user_id, 'places')) {
                wp_send_json_error(array('message' => 'Osiągnięto dzienny limit dodawania miejsc i ciekawostek (5 na dobę)'));
                exit;
            }
        } elseif ($type === 'zgloszenie') {
            if (!$this->check_daily_limit($user_id, 'reports')) {
                wp_send_json_error(array('message' => 'Osiągnięto dzienny limit zgłoszeń (5 na dobę)'));
                exit;
            }
        }

        // Validate required fields
        // Use wp_unslash() to remove WordPress magic quotes before sanitizing
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);
        // Type already sanitized above for limit check
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $address = sanitize_text_field(wp_unslash($_POST['address'] ?? ''));
        $public_name = isset($_POST['public_name']);
        $category = sanitize_text_field($_POST['category'] ?? '');
        $opening_hours = sanitize_textarea_field(wp_unslash($_POST['opening_hours'] ?? ''));
        $website = !empty($_POST['website']) ? esc_url_raw($_POST['website']) : '';
        $phone = !empty($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = !empty($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
        $price_range_raw = sanitize_text_field($_POST['price_range'] ?? '');
        $valid_price_ranges = array('$', '$$', '$$$', '$$$$');
        $price_range = in_array($price_range_raw, $valid_price_ranges, true) ? $price_range_raw : '';
        $serves_cuisine = sanitize_text_field(wp_unslash($_POST['serves_cuisine'] ?? ''));

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

        if (empty($title) || $lat === 0.0 || $lng === 0.0) {
            wp_send_json_error(array('message' => 'Wypełnij wszystkie wymagane pola'));
            exit;
        }

        if (empty($content)) {
            wp_send_json_error(array('message' => 'Opis miejsca jest wymagany'));
            exit;
        }

        // Validate category for reports (zgłoszenie)
        if ($type === 'zgloszenie') {
            if (empty($category)) {
                wp_send_json_error(array('message' => 'Wybór kategorii zgłoszenia jest wymagany'));
                exit;
            }

            // Validate category exists
            $valid_categories = array_keys(self::get_report_categories());
            if (!in_array($category, $valid_categories)) {
                wp_send_json_error(array('message' => 'Nieprawidłowa kategoria zgłoszenia'));
                exit;
            }

            // Check for duplicate reports in the same location (within 50m radius) with same category
            global $wpdb;
            $table = JG_Map_Database::get_points_table();

            // Haversine formula to find points within 50m
            // Earth radius = 6371000 meters
            $radius = 50; // meters
            $lat_range = $radius / 111000; // 1 degree lat = ~111km
            $lng_range = $radius / (111000 * cos(deg2rad($lat)));

            $nearby_reports = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title, category FROM $table
                     WHERE type = 'zgloszenie'
                     AND category = %s
                     AND status IN ('publish', 'pending')
                     AND lat BETWEEN %f AND %f
                     AND lng BETWEEN %f AND %f
                     AND (
                         6371000 * 2 * ASIN(SQRT(
                             POWER(SIN((%f - lat) * PI() / 180 / 2), 2) +
                             COS(%f * PI() / 180) * COS(lat * PI() / 180) *
                             POWER(SIN((%f - lng) * PI() / 180 / 2), 2)
                         ))
                     ) <= %f
                     LIMIT 1",
                    $category,
                    $lat - $lat_range,
                    $lat + $lat_range,
                    $lng - $lng_range,
                    $lng + $lng_range,
                    $lat,
                    $lat,
                    $lng,
                    $radius
                ),
                ARRAY_A
            );

            if (!empty($nearby_reports)) {
                $categories = self::get_report_categories();
                $category_label = $categories[$category]['label'] ?? $category;
                wp_send_json_error(array(
                    'message' => 'W tej lokalizacji jest już zgłoszone zdarzenie tego samego typu: "' . $category_label . '". Możesz na nie zagłosować zamiast dodawać nowe zgłoszenie.',
                    'duplicate_point_id' => intval($nearby_reports[0]['id'])
                ));
                exit;
            }
        }

        // Validate website URL if provided
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy format adresu strony internetowej'));
            exit;
        }

        // Validate phone format if provided
        if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy format numeru telefonu'));
            exit;
        }

        // Validate contact email if provided
        if (!empty($email) && !is_email($email)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy format adresu email kontaktowego'));
            exit;
        }

        // Handle image uploads
        $images = array();

        // Check if files are present (works for both array and single file format)
        $has_files = !empty($_FILES['images']) && (
            (is_array($_FILES['images']['name']) && !empty($_FILES['images']['name'][0])) ||
            (!is_array($_FILES['images']['name']) && !empty($_FILES['images']['name']))
        );

        if ($has_files) {
            // Check if user has photo upload restriction (skip for admins)
            if (!$is_admin && self::has_user_restriction($user_id, 'photo_upload')) {
                wp_send_json_error(array('message' => 'Nie możesz dodawać zdjęć - masz aktywną blokadę przesyłania zdjęć'));
                exit;
            }

            // For new submissions, always limit to 6 images (sponsoring is set by admin later)
            $upload_result = $this->handle_image_upload($_FILES['images'], 6, $user_id);

            if (isset($upload_result['error'])) {
                wp_send_json_error(array('message' => $upload_result['error']));
                exit;
            }

            $images = $upload_result['images'];
        } else {
        }

        // Get user IP
        $ip_address = $this->get_user_ip();

        // Insert point
        // Admins and moderators don't need approval - publish immediately
        $status = $is_admin ? 'publish' : 'pending';

        $point_data = array(
            'title' => $title,
            'content' => $content,
            'excerpt' => wp_trim_words($content, 20),
            'lat' => $lat,
            'lng' => $lng,
            'address' => $address,
            'type' => $type,
            'status' => $status,
            'report_status' => 'added',
            'author_id' => $user_id,
            'author_hidden' => !$public_name,
            'images' => json_encode($images),
            'featured_image_index' => !empty($images) ? 0 : null, // Auto-set first image as featured
            'tags' => !empty($tags) ? json_encode($tags, JSON_UNESCAPED_UNICODE) : null,
            'opening_hours' => !empty($opening_hours) ? $opening_hours : null,
            'price_range'   => !empty($price_range) ? $price_range : null,
            'serves_cuisine' => !empty($serves_cuisine) ? $serves_cuisine : null,
            'website' => !empty($website) ? $website : null,
            'phone' => !empty($phone) ? $phone : null,
            'email' => !empty($email) ? $email : null,
            'ip_address' => $ip_address,
            'created_at' => current_time('mysql', true),  // GMT time for consistency
            'updated_at' => current_time('mysql', true)   // GMT time for consistency
        );

        // Add category for all types (zgłoszenie, miejsce, ciekawostka)
        // Category is required for zgłoszenie, optional for others
        if (!empty($category)) {
            $point_data['category'] = $category;
        }

        $point_id = JG_Map_Database::insert_point($point_data);

        if ($point_id) {
            // Verify what was actually saved
            $saved_point = JG_Map_Database::get_point($point_id);

            // Log user action
            $type_labels = array('miejsce' => 'miejsce', 'ciekawostka' => 'ciekawostkę', 'zgloszenie' => 'zgłoszenie');
            $type_label = $type_labels[$type] ?? $type;
            JG_Map_Activity_Log::log_user_action(
                'submit_point',
                'point',
                $point_id,
                sprintf('Dodano %s: %s', $type_label, $title)
            );

            // Send email notification to admin
            $this->notify_admin_new_point($point_id);

            // Queue sync event for real-time updates
            JG_Map_Sync_Manager::get_instance()->queue_point_created($point_id, array(
                'point_title' => $saved_point['title'],
                'point_type' => $type,
                'status' => $saved_point['status']
            ));

            // Award XP for submitting a point
            $xp_result = JG_Map_Levels_Achievements::award_xp($user_id, 'submit_point', $point_id);

            // Award XP for photos that were actually saved (not raw $_FILES count)
            if (!empty($images)) {
                foreach ($images as $_img) {
                    $photo_xp = JG_Map_Levels_Achievements::award_xp($user_id, 'add_photo', $point_id);
                    if ($photo_xp && $xp_result) {
                        $xp_result['xp_gained']  += $photo_xp['xp_gained'];
                        $xp_result['new_xp']      = $photo_xp['new_xp'];
                        $xp_result['new_level']   = $photo_xp['new_level'];
                        $xp_result['progress']    = $photo_xp['progress'];
                        $xp_result['xp_in_level'] = $photo_xp['xp_in_level'];
                        $xp_result['xp_needed']   = $photo_xp['xp_needed'];
                        $xp_result['level_tier']  = $photo_xp['level_tier'];
                        if ($photo_xp['level_up']) { $xp_result['level_up'] = true; }
                    } elseif ($photo_xp) {
                        $xp_result = $photo_xp;
                    }
                }
            }

            $response = array(
                'message'   => 'Punkt dodany do moderacji',
                'point_id'  => $point_id,
                'type'      => $type,
                'status'    => $status,
                'lat'       => floatval($lat),
                'lng'       => floatval($lng),
                'xp_result' => $xp_result,
            );

            // Include case_id for reports (zgłoszenie)
            if ($type === 'zgloszenie' && !empty($saved_point['case_id'])) {
                $response['case_id'] = $saved_point['case_id'];
                $response['show_report_info_modal'] = true;
            }

            wp_send_json_success($response);
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
        $is_owner = intval($point['author_id']) === $user_id;

        // Anyone can suggest edits to any place (will require two-stage approval for non-owners)

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

        // Sponsored places can only be edited by owner or admin
        $is_sponsored = (bool)$point['is_promo'];
        if ($is_sponsored && !$is_admin && !$is_owner) {
            wp_send_json_error(array('message' => 'Miejsca sponsorowane mogą być edytowane tylko przez właściciela'));
            exit;
        }

        // Edit-locked places can only be edited by admins
        $is_edit_locked = (bool)($point['edit_locked'] ?? 0);
        if ($is_edit_locked && !$is_admin) {
            wp_send_json_error(array('message' => 'To miejsce ma zablokowaną możliwość edycji'));
            exit;
        }

        // Use wp_unslash() to remove WordPress magic quotes before sanitizing
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $type = sanitize_text_field($_POST['type'] ?? '');
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $category = sanitize_text_field($_POST['category'] ?? '');
        $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
        $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
        $address = sanitize_text_field(wp_unslash($_POST['address'] ?? ''));
        $website = !empty($_POST['website']) ? esc_url_raw($_POST['website']) : '';
        $phone = !empty($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = !empty($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
        $opening_hours = sanitize_textarea_field(wp_unslash($_POST['opening_hours'] ?? ''));
        $price_range_raw = sanitize_text_field($_POST['price_range'] ?? '');
        $valid_price_ranges = array('$', '$$', '$$$', '$$$$');
        $price_range = in_array($price_range_raw, $valid_price_ranges, true) ? $price_range_raw : '';
        $serves_cuisine = sanitize_text_field(wp_unslash($_POST['serves_cuisine'] ?? ''));

        // Normalize social media URLs - accept full URLs, domain URLs, or profile names
        $facebook_url = !empty($_POST['facebook_url']) ? $this->normalize_social_url($_POST['facebook_url'], 'facebook') : '';
        $instagram_url = !empty($_POST['instagram_url']) ? $this->normalize_social_url($_POST['instagram_url'], 'instagram') : '';
        $linkedin_url = !empty($_POST['linkedin_url']) ? $this->normalize_social_url($_POST['linkedin_url'], 'linkedin') : '';
        $tiktok_url = !empty($_POST['tiktok_url']) ? $this->normalize_social_url($_POST['tiktok_url'], 'tiktok') : '';

        $cta_enabled = isset($_POST['cta_enabled']) ? 1 : 0;
        $cta_type = sanitize_text_field($_POST['cta_type'] ?? '');

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

        if (empty($title)) {
            wp_send_json_error(array('message' => 'Tytuł jest wymagany'));
            exit;
        }

        if (empty($content)) {
            wp_send_json_error(array('message' => 'Opis miejsca jest wymagany'));
            exit;
        }

        // Validate category for reports (zgłoszenie)
        if ($type === 'zgloszenie') {
            if (empty($category)) {
                wp_send_json_error(array('message' => 'Wybór kategorii zgłoszenia jest wymagany'));
                exit;
            }

            // Validate category exists
            $valid_categories = array_keys(self::get_report_categories());
            if (!in_array($category, $valid_categories)) {
                wp_send_json_error(array('message' => 'Nieprawidłowa kategoria zgłoszenia'));
                exit;
            }
        }

        // Validate website URL if provided
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy format adresu strony internetowej'));
            exit;
        }

        // Validate phone format if provided
        if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy format numeru telefonu'));
            exit;
        }

        // Validate contact email if provided
        if (!empty($email) && !is_email($email)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy format adresu email kontaktowego'));
            exit;
        }

        // Handle image uploads
        $new_images = array();

        // Check if files are present (works for both array and single file format)
        $has_files = !empty($_FILES['images']) && (
            (is_array($_FILES['images']['name']) && !empty($_FILES['images']['name'][0])) ||
            (!is_array($_FILES['images']['name']) && !empty($_FILES['images']['name']))
        );

        if ($has_files) {
            // Check if user has photo upload restriction (skip for admins)
            if (!$is_admin && self::has_user_restriction($user_id, 'photo_upload')) {
                wp_send_json_error(array('message' => 'Nie możesz dodawać zdjęć - masz aktywną blokadę przesyłania zdjęć'));
                exit;
            }

            // Check existing image count
            $existing_images = json_decode($point['images'] ?? '[]', true) ?: array();
            $existing_count = count($existing_images);

            // Determine max images based on sponsored status
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
            } else {
            }
        } else {
        }

        // Check if there's already pending edit for this point
        $pending_histories = JG_Map_Database::get_pending_history($point_id);
        if (!empty($pending_histories) && !$is_admin) {
            // Check if any of the pending changes is an edit
            foreach ($pending_histories as $ph) {
                if ($ph['action_type'] === 'edit') {
                    wp_send_json_error(array('message' => 'Ta lokalizacja ma już oczekującą edycję'));
                    exit;
                }
            }
        }

        // Admins and moderators can edit directly without approval
        if ($is_admin) {
            $update_data = array(
                'title' => $title,
                'type' => $type,
                'content' => $content,
                'excerpt' => wp_trim_words($content, 20)
            );

            // Add category for all types (zgłoszenie, miejsce, ciekawostka)
            // Category is required for zgłoszenie, optional for others
            if (!empty($category)) {
                $update_data['category'] = $category;
            } else {
                $update_data['category'] = null;
            }

            // Update tags
            $update_data['tags'] = !empty($tags) ? json_encode($tags, JSON_UNESCAPED_UNICODE) : null;

            // Update opening_hours (admin can edit directly for all types)
            $update_data['opening_hours'] = !empty($opening_hours) ? $opening_hours : null;

            // Update price_range and serves_cuisine
            $update_data['price_range']   = !empty($price_range) ? $price_range : null;
            $update_data['serves_cuisine'] = !empty($serves_cuisine) ? $serves_cuisine : null;

            // Update lat/lng if provided (from geocoding)
            if ($lat !== null && $lng !== null) {
                $update_data['lat'] = $lat;
                $update_data['lng'] = $lng;
            }

            // Update address if provided
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
                $had_no_images = empty($existing_images);
                $all_images = array_merge($existing_images, $new_images);

                // Limit based on sponsored status - 12 for sponsored, 6 for regular
                $max_images = $is_sponsored ? 12 : 6;
                $all_images = array_slice($all_images, 0, $max_images);

                $update_data['images'] = json_encode($all_images);

                // If this is first image being added, set it as featured
                if ($had_no_images) {
                    $update_data['featured_image_index'] = 0;
                }
            }

            JG_Map_Database::update_point($point_id, $update_data);

            // Record admin/mod edit in history so it shows in "Ostatni modyfikujący"
            $old_values = array(
                'title' => $point['title'],
                'type' => $point['type'],
                'category' => $point['category'] ?? '',
                'content' => $point['content'],
                'tags' => $point['tags'] ?? '[]',
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'address' => $point['address'] ?? '',
                'images' => $point['images'] ?? '[]',
                'opening_hours' => $point['opening_hours'] ?? '',
                'website' => $point['website'] ?? null,
                'phone' => $point['phone'] ?? null,
                'email' => $point['email'] ?? null
            );
            $new_values = array(
                'title' => $title,
                'type' => $type,
                'category' => $category,
                'content' => $content,
                'tags' => !empty($tags) ? json_encode($tags, JSON_UNESCAPED_UNICODE) : '[]',
                'lat' => ($lat !== null) ? $lat : $point['lat'],
                'lng' => ($lng !== null) ? $lng : $point['lng'],
                'address' => !empty($address) ? $address : ($point['address'] ?? ''),
                'images' => isset($update_data['images']) ? $update_data['images'] : ($point['images'] ?? '[]'),
                'opening_hours' => $opening_hours,
                'website' => !empty($website) ? $website : null,
                'phone' => !empty($phone) ? $phone : null,
                'email' => !empty($email) ? $email : null
            );
            JG_Map_Database::add_admin_edit_history($point_id, $user_id, $old_values, $new_values);

            // Award XP for admin edit and any new photos (mirrors user edit path)
            JG_Map_Levels_Achievements::award_xp($user_id, 'edit_point', $point_id);
            if (!empty($new_images)) {
                foreach ($new_images as $_img) {
                    JG_Map_Levels_Achievements::award_xp($user_id, 'add_photo', $point_id);
                }
            }

            // Log user action (admin direct edit)
            JG_Map_Activity_Log::log_user_action(
                'edit_point',
                'point',
                $point_id,
                sprintf('Bezpośrednio edytowano miejsce: %s', $title)
            );

            wp_send_json_success(array('message' => 'Zaktualizowano'));
        } else {
            // Check if user has sponsored places (users with sponsored places get 2x edit limit)
            global $wpdb;
            $points_table = JG_Map_Database::get_points_table();
            $sponsored_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $points_table
                WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
                $user_id
            ));
            $has_sponsored = $sponsored_count > 0;

            // Calculate daily edit limit (2 for regular users, 4 for users with sponsored places)
            $daily_limit = $has_sponsored ? 4 : 2;

            // Check daily edit limit
            $edit_count = intval(get_user_meta($user_id, 'jg_map_edits_count', true));
            $edit_date = get_user_meta($user_id, 'jg_map_edits_date', true);
            $today = current_time('Y-m-d');

            // Reset counter if it's a new day
            if ($edit_date !== $today) {
                $edit_count = 0;
                update_user_meta($user_id, 'jg_map_edits_date', $today);
                update_user_meta($user_id, 'jg_map_edits_count', 0);
            }

            // Check if limit exceeded
            if ($edit_count >= $daily_limit) {
                $limit_msg = $has_sponsored
                    ? 'Osiągnąłeś dzienny limit edycji (4 na dobę dla użytkowników z miejscami sponsorowanymi). Spróbuj ponownie jutro.'
                    : 'Osiągnąłeś dzienny limit edycji (2 na dobę). Spróbuj ponownie jutro.';
                wp_send_json_error(array('message' => $limit_msg));
                exit;
            }

            // All edits from map go through moderation system
            $old_values = array(
                'title' => $point['title'],
                'type' => $point['type'],
                'category' => $point['category'] ?? '',
                'content' => $point['content'],
                'tags' => $point['tags'] ?? '[]',
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'address' => $point['address'] ?? '',
                'images' => $point['images'] ?? '[]',
                'opening_hours' => $point['opening_hours'] ?? '',
                'price_range'   => $point['price_range'] ?? null,
                'serves_cuisine' => $point['serves_cuisine'] ?? null,
                'website' => $point['website'] ?? null,
                'phone' => $point['phone'] ?? null,
                'email' => $point['email'] ?? null
            );

            $new_values = array(
                'title' => $title,
                'type' => $type,
                'category' => $category,
                'content' => $content,
                'tags' => !empty($tags) ? json_encode($tags, JSON_UNESCAPED_UNICODE) : '[]',
                'opening_hours' => $opening_hours,
                'price_range'   => !empty($price_range) ? $price_range : null,
                'serves_cuisine' => !empty($serves_cuisine) ? $serves_cuisine : null,
                'new_images' => json_encode($new_images), // Store new images separately for moderation
                'website' => !empty($website) ? $website : null,
                'phone' => !empty($phone) ? $phone : null,
                'email' => !empty($email) ? $email : null
            );

            // Always include lat/lng/address in new_values for proper comparison in admin panel
            // Use new values if provided (from geocoding), otherwise use current point values
            $new_values['lat'] = ($lat !== null) ? $lat : $point['lat'];
            $new_values['lng'] = ($lng !== null) ? $lng : $point['lng'];
            $new_values['address'] = !empty($address) ? $address : ($point['address'] ?? '');

            // Add social media and CTA to history if point is sponsored
            $is_sponsored = (bool)$point['is_promo'];
            if ($is_sponsored) {
                $old_values['facebook_url'] = $point['facebook_url'] ?? null;
                $old_values['instagram_url'] = $point['instagram_url'] ?? null;
                $old_values['linkedin_url'] = $point['linkedin_url'] ?? null;
                $old_values['tiktok_url'] = $point['tiktok_url'] ?? null;
                $old_values['cta_enabled'] = $point['cta_enabled'] ?? 0;
                $old_values['cta_type'] = $point['cta_type'] ?? null;
                $new_values['facebook_url'] = !empty($facebook_url) ? $facebook_url : null;
                $new_values['instagram_url'] = !empty($instagram_url) ? $instagram_url : null;
                $new_values['linkedin_url'] = !empty($linkedin_url) ? $linkedin_url : null;
                $new_values['tiktok_url'] = !empty($tiktok_url) ? $tiktok_url : null;
                $new_values['cta_enabled'] = $cta_enabled;
                $new_values['cta_type'] = !empty($cta_type) ? $cta_type : null;
            }

            // Store point owner ID for two-stage approval (if non-owner is editing)
            $point_owner_id = !$is_owner ? intval($point['author_id']) : null;

            JG_Map_Database::add_history($point_id, $user_id, 'edit', $old_values, $new_values, $point_owner_id);

            // Mark point as having a pending edit awaiting moderation
            JG_Map_Database::update_point($point_id, array('pending_edit' => 1));

            // Increment daily edit counter
            update_user_meta($user_id, 'jg_map_edits_count', $edit_count + 1);

            // Queue sync event via dedicated sync manager
            JG_Map_Sync_Manager::get_instance()->queue_edit_submitted($point_id, array(
                'user_id' => $user_id,
                'old_values' => $old_values,
                'new_values' => $new_values,
                'requires_owner_approval' => !$is_owner
            ));

            // Notify owner if non-owner is editing, otherwise notify admin
            if (!$is_owner) {
                $this->notify_owner_edit($point_id, $point_owner_id);
            } else {
                $this->notify_admin_edit($point_id);
            }

            // Award XP for editing — one award per submitted edit, regardless of which fields changed.
            // Matches recalculate_all_xp() which counts all action_type='edit' history entries.
            $xp_results = array();
            $r = JG_Map_Levels_Achievements::award_xp($user_id, 'edit_point', $point_id);
            if ($r) $xp_results[] = $r;

            // --- New photos ---
            if (!empty($new_images)) {
                foreach ($new_images as $_img) {
                    $r = JG_Map_Levels_Achievements::award_xp($user_id, 'add_photo', $point_id);
                    if ($r) $xp_results[] = $r;
                }
            }

            // Merge into a single xp_result for the frontend (summed xp_gained, last state)
            $xp_result = null;
            if (!empty($xp_results)) {
                $xp_result = end($xp_results);
                $total_gained = array_sum(array_map(function($r) { return $r['xp_gained']; }, $xp_results));
                $xp_result['xp_gained'] = $total_gained;
                $xp_result['level_up']  = array_reduce($xp_results, function($carry, $r) {
                    return $carry || $r['level_up'];
                }, false);
            }

            // Log user action
            JG_Map_Activity_Log::log_user_action(
                'suggest_edit',
                'point',
                $point_id,
                sprintf('Zaproponowano edycję miejsca: %s%s', $point['title'], !$is_owner ? ' (cudze miejsce)' : '')
            );

            $success_msg = !$is_owner
                ? 'Edycja wysłana do zatwierdzenia przez właściciela miejsca'
                : 'Edycja wysłana do moderacji';

            wp_send_json_success(array('message' => $success_msg, 'xp_result' => $xp_result));
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
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

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

        // Update point columns for dashboard display
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();
        $wpdb->update(
            $points_table,
            array(
                'is_deletion_requested' => 1,
                'deletion_reason' => $reason,
                'deletion_requested_at' => current_time('mysql', true)  // GMT time
            ),
            array('id' => $point_id)
        );

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_deletion_requested($point_id, array(
            'reason' => $reason,
            'user_id' => $user_id,
            'point_title' => $point['title']
        ));

        // Notify admin
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $subject = 'Portal Jeleniogórzanie to my - Nowe zgłoszenie usunięcia miejsca';
            $message = "Użytkownik zgłosił chęć usunięcia miejsca:\n\n";
            $message .= "Tytuł: {$point['title']}\n";
            $message .= "Powód: {$reason}\n\n";
            $message .= "Sprawdź w panelu administratora.";
            wp_mail($admin_email, $subject, $message);
        }

        // Log user action
        JG_Map_Activity_Log::log_user_action(
            'request_deletion',
            'point',
            $point_id,
            sprintf('Zgłoszono chęć usunięcia: %s. Powód: %s', $point['title'], $reason ?: 'brak')
        );

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

        if (!$point_id || !in_array($direction, array('1', '2', '3', '4', '5'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Check if user is the author of the point - can't rate own places
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }
        if (intval($point['author_id']) === $user_id) {
            wp_send_json_error(array('message' => 'Nie możesz oceniać własnych miejsc'));
            exit;
        }

        // Get current rating
        $current_vote = JG_Map_Database::get_user_vote($point_id, $user_id);

        // Toggle: clicking the same star value removes the rating
        $new_vote = ($current_vote === $direction) ? '' : $direction;

        JG_Map_Database::set_vote($point_id, $user_id, $new_vote);

        $author_id = intval($point['author_id']);
        if (empty($current_vote) && !empty($new_vote)) {
            // New vote: award XP to voter and to point author
            JG_Map_Levels_Achievements::award_xp($user_id, 'vote_on_point', $point_id);
            JG_Map_Levels_Achievements::award_xp($author_id, 'receive_upvote', $point_id);
        } elseif (!empty($current_vote) && empty($new_vote)) {
            // Vote removed: revoke XP from voter and from point author
            JG_Map_Levels_Achievements::revoke_xp($user_id, 'vote_on_point', $point_id);
            JG_Map_Levels_Achievements::revoke_xp($author_id, 'receive_upvote', $point_id);
        }

        $rating_data = JG_Map_Database::get_rating_data($point_id);

        // Log user action
        if (!empty($new_vote)) {
            JG_Map_Activity_Log::log_user_action(
                'vote',
                'point',
                $point_id,
                sprintf('Oceniono %s gwiazdkami: %s', $new_vote, $point['title'])
            );
        } else {
            JG_Map_Activity_Log::log_user_action(
                'vote_removed',
                'point',
                $point_id,
                sprintf('Wycofano ocenę: %s', $point['title'])
            );
        }

        wp_send_json_success(array(
            'rating'        => $rating_data['avg'],
            'ratings_count' => $rating_data['count'],
            'my_rating'     => $new_vote,
        ));
    }

    /**
     * Report point
     */
    public function report_point() {
        $this->verify_nonce();

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany aby zgłosić miejsce'));
            exit;
        }

        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        $point_id = intval($_POST['post_id'] ?? 0);
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Check if reason is provided
        if (empty(trim($reason))) {
            wp_send_json_error(array('message' => 'Powód zgłoszenia jest wymagany'));
            exit;
        }

        // Check if user already reported this point
        if (JG_Map_Database::has_user_reported($point_id, $user_id)) {
            wp_send_json_error(array('message' => 'To miejsce zostało już przez Ciebie zgłoszone'));
            exit;
        }

        // Check daily report limit for regular users (3 reports per day)
        if (!$is_admin) {
            $report_count = intval(get_user_meta($user_id, 'jg_map_daily_reports_count', true));
            $report_date = get_user_meta($user_id, 'jg_map_daily_reports_date', true);
            $today = current_time('Y-m-d');

            // Reset counter if it's a new day
            if ($report_date !== $today) {
                $report_count = 0;
                update_user_meta($user_id, 'jg_map_daily_reports_date', $today);
                update_user_meta($user_id, 'jg_map_daily_reports_count', 0);
            }

            // Check if limit exceeded
            if ($report_count >= 3) {
                wp_send_json_error(array('message' => 'Osiągnąłeś dzienny limit zgłoszeń (3 na dobę). Spróbuj ponownie jutro.'));
                exit;
            }
        }

        // Get email from logged in user
        $user = get_userdata($user_id);
        $email = $user ? $user->user_email : '';

        JG_Map_Database::add_report($point_id, $user_id, $email, $reason);

        // Award XP for reporting
        $xp_result = JG_Map_Levels_Achievements::award_xp($user_id, 'report_point', $point_id);

        // Increment daily report counter for regular users
        if (!$is_admin) {
            $report_count = intval(get_user_meta($user_id, 'jg_map_daily_reports_count', true));
            update_user_meta($user_id, 'jg_map_daily_reports_count', $report_count + 1);
        }

        // Update point's report_status so users can see it's reported
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();
        $wpdb->update(
            $points_table,
            array('report_status' => 'reported'),
            array('id' => $point_id),
            array('%s'),
            array('%d')
        );

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_report_added($point_id, array(
            'user_id' => $user_id,
            'reason' => $reason
        ));

        // Log user action
        $reported_point = JG_Map_Database::get_point($point_id);
        JG_Map_Activity_Log::log_user_action(
            'report_point',
            'point',
            $point_id,
            sprintf('Zgłoszono miejsce: %s. Powód: %s', $reported_point ? $reported_point['title'] : '#' . $point_id, wp_trim_words($reason, 10))
        );

        // Notify admin
        $this->notify_admin_new_report($point_id, $user_id);

        // Notify reporter (confirmation email)
        $this->notify_reporter_confirmation($point_id, $email);

        wp_send_json_success(array('message' => 'Zgłoszenie wysłane', 'xp_result' => $xp_result));
    }

}
