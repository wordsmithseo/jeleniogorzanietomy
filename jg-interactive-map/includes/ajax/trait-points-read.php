<?php
/**
 * Trait: read-only point queries
 * check_point_exists, check_updates, get_points, get_tags, get_point_stats,
 * get_point_visitors, get_user_info, get_user_activity, get_ranking,
 * check_daily_limit, decrement_daily_limit, get_daily_limits,
 * get_author_points, get_sidebar_points, get_featured_image_url,
 * point_has_tags, get_images_count, point_has_internal_links,
 * point_has_external_links, track_stat
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

trait JG_Ajax_PointsRead {

    /**
     * Check if point exists (prevent operations on deleted points)
     */
    public function check_point_exists() {
        $point_id = intval($_POST['point_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Check if point exists in database
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d",
            $point_id
        ));

        if ($exists) {
            wp_send_json_success(array('exists' => true));
        } else {
            wp_send_json_success(array('exists' => false));
        }
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
            $pending_points = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", 'pending'));
            // ONLY count edits, not deletion requests
            $pending_edits = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $history_table WHERE status = %s AND action_type IN ('edit', 'edit_menu')", 'pending'));
            $pending_reports = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT r.point_id)
                     FROM $reports_table r
                     INNER JOIN $table p ON r.point_id = p.id
                     WHERE r.status = %s AND p.status = %s",
                    'pending',
                    'publish'
                )
            );
            $pending_deletions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE is_deletion_requested = %d AND status = %s", 1, 'publish'));
            $pending_count = intval($pending_points) + intval($pending_edits) + intval($pending_reports) + intval($pending_deletions);
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
        global $wpdb;

        // CRITICAL: Prevent external caching (CDN, server cache, browser cache)
        // Response varies per user, so it MUST NOT be cached externally
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        header('Vary: Cookie');

        // Force schema check and slug generation on first request (ensures backward compatibility)
        static $schema_checked = false;
        if (!$schema_checked) {
            JG_Map_Database::check_and_update_schema();
            $schema_checked = true;
        }

        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        $current_user_id = get_current_user_id();

        // For admins: get all points (published + pending)
        // For regular users: get published points + their own pending points
        $points = JG_Map_Database::get_published_points($is_admin);

        // If regular user is logged in, add their pending points
        if (!$is_admin && $current_user_id > 0) {
            $user_pending_points = JG_Map_Database::get_user_pending_points($current_user_id);
            $points = array_merge($points, $user_pending_points);
        }

        // PERFORMANCE OPTIMIZATION: Batch load all related data to avoid N+1 queries
        $point_ids = array();
        $owner_point_ids = array(); // Points owned by current user (need rejected histories)

        if (is_array($points) && !empty($points)) {
            // Collect all point IDs
            $point_ids = array_column($points, 'id');

            // Pre-load user data
            if (function_exists('wp_prime_user_cache')) {
                $author_ids = array_unique(array_column($points, 'author_id'));
                $author_ids = array_filter($author_ids); // Remove nulls/zeros
                if (!empty($author_ids)) {
                    wp_prime_user_cache($author_ids); // Load all users at once
                }
            }

            // Identify owner points for rejected histories
            foreach ($points as $point) {
                if ($current_user_id > 0 && $current_user_id == $point['author_id']) {
                    $owner_point_ids[] = $point['id'];
                }
            }
        }

        // BATCH LOAD: Load all votes, reports, and histories at once (prevents N+1 queries)
        $votes_counts_map = !empty($point_ids) ? JG_Map_Database::get_votes_counts_batch($point_ids) : array();
        $user_votes_map = ($current_user_id > 0 && !empty($point_ids)) ? JG_Map_Database::get_user_votes_batch($point_ids, $current_user_id) : array();
        $reports_counts_map = ($is_admin && !empty($point_ids)) ? JG_Map_Database::get_reports_counts_batch($point_ids) : array();
        $user_reported_map = ($current_user_id > 0 && !empty($point_ids)) ? JG_Map_Database::has_user_reported_batch($point_ids, $current_user_id) : array();
        $pending_histories_map = (($is_admin || $current_user_id > 0) && !empty($point_ids)) ? JG_Map_Database::get_pending_histories_batch($point_ids) : array();
        $rejected_histories_map = (!empty($owner_point_ids)) ? JG_Map_Database::get_rejected_histories_batch($owner_point_ids, 30) : array();

        $result = array();

        foreach ($points as $point) {
            $point_id = intval($point['id']);

            $author = get_userdata($point['author_id']); // Now from cache
            $author_name = '';
            $author_email = '';

            if ($author) {
                // Show author name if not hidden OR if current user is the author
                if (!$point['author_hidden'] || $current_user_id == $point['author_id']) {
                    $author_name = $author->display_name;
                }
                $author_email = $author->user_email;
            }

            // Get star rating from batch-loaded data
            $rating_batch = isset($votes_counts_map[$point_id]) ? $votes_counts_map[$point_id] : array('avg' => 0.0, 'count' => 0);
            $rating       = $rating_batch['avg'];
            $ratings_count = $rating_batch['count'];
            $my_rating    = isset($user_votes_map[$point_id]) ? $user_votes_map[$point_id] : '';

            // Get reports count from batch-loaded data - for admins or place owner
            $reports_count = 0;
            $is_own_place_temp = ($current_user_id > 0 && $current_user_id == $point['author_id']);
            if ($is_admin && isset($reports_counts_map[$point_id])) {
                $reports_count = $reports_counts_map[$point_id];
            } elseif ($is_own_place_temp && !$is_admin) {
                // For non-admin owners, load individually (rare case)
                $reports_count = JG_Map_Database::get_reports_count($point_id);
            }

            // Check if current user has reported this point from batch-loaded data
            $user_has_reported = isset($user_reported_map[$point_id]) ? $user_reported_map[$point_id] : false;

            // If user has reported, get report details (time when reported and who reported)
            $reporter_info = null;
            if ($user_has_reported) {
                $reports_table = JG_Map_Database::get_reports_table();
                $report = $wpdb->get_row($wpdb->prepare(
                    "SELECT created_at FROM $reports_table
                     WHERE point_id = %d AND user_id = %d AND status = 'pending'
                     ORDER BY created_at DESC LIMIT 1",
                    $point_id,
                    $current_user_id
                ), ARRAY_A);

                if ($report) {
                    // Get current user's display name
                    $current_user = wp_get_current_user();
                    $reporter_name = $current_user ? $current_user->display_name : 'Ty';

                    $reporter_info = array(
                        'reported_at' => human_time_diff(strtotime($report['created_at'] . ' UTC'), time()) . ' temu',
                        'reporter_name' => $reporter_name
                    );
                }
            }


            // Check if sponsored expired
            $is_sponsored = (bool)$point['is_promo'];
            $sponsored_until = $point['promo_until'] ?? null;
            if ($is_sponsored && $sponsored_until) {
                if (strtotime($sponsored_until) < time()) {
                    // Sponsored expired, update DB
                    JG_Map_Database::update_point($point['id'], array('is_promo' => 0));
                    $is_sponsored = false;
                }
            }

            // Parse images and limit based on sponsored status
            $images = array();
            if (!empty($point['images'])) {
                $images_data = json_decode($point['images'], true);
                if (is_array($images_data)) {
                    // Show only first 6 images for regular places, 12 for sponsored
                    // All images are kept in database, but only visible number is returned
                    $max_visible_images = $is_sponsored ? 12 : 6;
                    $images = array_slice($images_data, 0, $max_visible_images);
                }
            }

            // Status labels
            $status_label = $this->get_status_label($point['status']);
            $report_status_label = $this->get_report_status_label($point['report_status']);

            // Check if pending or edit - for admins/moderators, place owner, OR the editor who submitted the edit
            $is_pending = false;
            $edit_info = null;
            $deletion_info = null;
            $is_edit = false;
            $is_deletion_requested = false;
            $is_own_place = ($current_user_id > 0 && $current_user_id == $point['author_id']);

            // Get ALL pending history entries from batch-loaded data
            $pending_histories = isset($pending_histories_map[$point_id]) ? $pending_histories_map[$point_id] : array();

            // Check if current user submitted any pending edit for this point
            $user_pending_edit = null;
            if ($current_user_id > 0 && !empty($pending_histories)) {
                foreach ($pending_histories as $ph) {
                    if (in_array($ph['action_type'], array('edit', 'edit_menu'), true) && intval($ph['user_id']) === $current_user_id) {
                        $user_pending_edit = $ph;
                        break;
                    }
                }
            }

            // Show edit info if: admin, owner, or the editor who submitted it
            if ($is_admin || $is_own_place || $user_pending_edit !== null) {
                $is_pending = ($point['status'] === 'pending');

                // Loop through all pending changes and populate edit_info and/or deletion_info
                if (!empty($pending_histories)) {
                    foreach ($pending_histories as $pending_history) {
                        $old_values = json_decode($pending_history['old_values'], true);
                        $new_values = json_decode($pending_history['new_values'], true);

                        if ($pending_history['action_type'] === 'edit') {
                            // Parse new images if present
                            $new_images = array();
                            if (isset($new_values['new_images'])) {
                                $new_images_data = json_decode($new_values['new_images'], true);
                                if (is_array($new_images_data)) {
                                    $new_images = $new_images_data;
                                }
                            }

                            // Get editor info (the person who submitted the edit)
                            $editor_id = intval($pending_history['user_id']);
                            $editor = get_userdata($editor_id);
                            $editor_name = $editor ? $editor->display_name : 'Nieznany użytkownik';

                            // Check if current user is the editor
                            $is_my_edit = ($editor_id === $current_user_id);

                            // Check if this is an edit by someone other than the owner
                            $point_owner_id = intval($point['author_id']);
                            $is_external_edit = ($editor_id !== $point_owner_id);

                            // Check if owner approval is required and its status
                            $requires_owner_approval = !empty($pending_history['point_owner_id']);
                            $owner_approval_status = $pending_history['owner_approval_status'] ?? 'pending';

                            $edit_info = array(
                                'history_id' => intval($pending_history['id']),
                                'editor_id' => $editor_id,
                                'editor_name' => $editor_name,
                                'is_my_edit' => $is_my_edit,
                                'is_external_edit' => $is_external_edit,
                                'requires_owner_approval' => $requires_owner_approval,
                                'owner_approval_status' => $owner_approval_status,
                                'prev_title' => $old_values['title'] ?? '',
                                'prev_type' => $old_values['type'] ?? '',
                                'prev_category' => $old_values['category'] ?? null,
                                'prev_content' => $old_values['content'] ?? '',
                                'prev_tags' => $old_values['tags'] ?? '[]',
                                'new_title' => $new_values['title'] ?? '',
                                'new_type' => $new_values['type'] ?? '',
                                'new_category' => $new_values['category'] ?? null,
                                'new_content' => $new_values['content'] ?? '',
                                'new_tags' => $new_values['tags'] ?? '[]',
                                'prev_website' => $old_values['website'] ?? null,
                                'new_website' => $new_values['website'] ?? null,
                                'prev_phone' => $old_values['phone'] ?? null,
                                'new_phone' => $new_values['phone'] ?? null,
                                'prev_email' => $old_values['email'] ?? null,
                                'new_email' => $new_values['email'] ?? null,
                                'prev_facebook_url' => $old_values['facebook_url'] ?? null,
                                'new_facebook_url' => $new_values['facebook_url'] ?? null,
                                'prev_instagram_url' => $old_values['instagram_url'] ?? null,
                                'new_instagram_url' => $new_values['instagram_url'] ?? null,
                                'prev_linkedin_url' => $old_values['linkedin_url'] ?? null,
                                'new_linkedin_url' => $new_values['linkedin_url'] ?? null,
                                'prev_tiktok_url' => $old_values['tiktok_url'] ?? null,
                                'new_tiktok_url' => $new_values['tiktok_url'] ?? null,
                                'prev_cta_enabled' => $old_values['cta_enabled'] ?? null,
                                'new_cta_enabled' => $new_values['cta_enabled'] ?? null,
                                'prev_cta_type' => $old_values['cta_type'] ?? null,
                                'new_cta_type' => $new_values['cta_type'] ?? null,
                                'prev_address' => $old_values['address'] ?? null,
                                'new_address' => $new_values['address'] ?? null,
                                'prev_lat' => $old_values['lat'] ?? null,
                                'new_lat' => $new_values['lat'] ?? null,
                                'prev_lng' => $old_values['lng'] ?? null,
                                'new_lng' => $new_values['lng'] ?? null,
                                'prev_opening_hours' => $old_values['opening_hours'] ?? null,
                                'new_opening_hours' => $new_values['opening_hours'] ?? null,
                                'new_images' => $new_images,
                                'edited_at' => human_time_diff(strtotime($pending_history['created_at'] . ' UTC'), time()) . ' temu'
                            );
                        } else if ($pending_history['action_type'] === 'edit_menu') {
                            $editor_id   = intval($pending_history['user_id']);
                            $editor      = get_userdata($editor_id);
                            $editor_name = $editor ? $editor->display_name : 'Nieznany użytkownik';
                            $is_my_edit  = ($editor_id === $current_user_id);
                            $edit_info   = array(
                                'history_id'  => intval($pending_history['id']),
                                'editor_id'   => $editor_id,
                                'editor_name' => $editor_name,
                                'is_my_edit'  => $is_my_edit,
                                'is_menu_edit' => true,
                                'edited_at'   => human_time_diff(strtotime($pending_history['created_at'] . ' UTC'), time()) . ' temu'
                            );
                        } else if ($pending_history['action_type'] === 'delete_request') {
                            $deletion_info = array(
                                'history_id' => intval($pending_history['id']),
                                'reason' => $new_values['reason'] ?? '',
                                'requested_at' => human_time_diff(strtotime($pending_history['created_at'] . ' UTC'), time()) . ' temu'
                            );
                        }
                    }
                }

                // For place owners, also get recently rejected history from batch-loaded data to show rejection reasons
                if ($is_own_place) {
                    $rejected_histories = isset($rejected_histories_map[$point_id]) ? $rejected_histories_map[$point_id] : array();
                    if (!empty($rejected_histories)) {
                        foreach ($rejected_histories as $rejected_history) {
                            $rejection_reason = $rejected_history['rejection_reason'] ?? '';
                            if (empty($rejection_reason)) continue; // Skip if no reason provided

                            if ($rejected_history['action_type'] === 'edit' && $edit_info === null) {
                                $edit_info = array(
                                    'status' => 'rejected',
                                    'rejection_reason' => $rejection_reason,
                                    'rejected_at' => human_time_diff(strtotime($rejected_history['resolved_at'] . ' UTC'), time()) . ' temu'
                                );
                            } else if ($rejected_history['action_type'] === 'delete_request' && $deletion_info === null) {
                                $deletion_info = array(
                                    'status' => 'rejected',
                                    'rejection_reason' => $rejection_reason,
                                    'rejected_at' => human_time_diff(strtotime($rejected_history['resolved_at'] . ' UTC'), time()) . ' temu'
                                );
                            }
                        }
                    }
                }

                $is_edit = ($edit_info !== null && (!isset($edit_info['status']) || $edit_info['status'] !== 'rejected'));
                $is_deletion_requested = ($deletion_info !== null && (!isset($deletion_info['status']) || $deletion_info['status'] !== 'rejected'));
            }


            $result[] = array(
                'id' => intval($point['id']),
                'case_id' => $point['case_id'] ?? null,
                'title' => $point['title'],
                'slug' => $point['slug'] ?? '',
                'excerpt' => $point['excerpt'],
                'content' => $point['content'],
                'lat' => floatval($point['lat']),
                'lng' => floatval($point['lng']),
                'address' => $point['address'] ?? '',
                'type' => $point['type'],
                'category' => $point['category'] ?? null,
                'sponsored' => $is_sponsored,
                'sponsored_until' => $sponsored_until,
                'website' => $point['website'] ?? null,
                'phone' => $point['phone'] ?? null,
                'email' => $point['email'] ?? null,
                'facebook_url' => $point['facebook_url'] ?? null,
                'instagram_url' => $point['instagram_url'] ?? null,
                'linkedin_url' => $point['linkedin_url'] ?? null,
                'tiktok_url' => $point['tiktok_url'] ?? null,
                'cta_enabled' => (bool)($point['cta_enabled'] ?? 0),
                'cta_type' => $point['cta_type'] ?? null,
                'tags' => !empty($point['tags']) ? json_decode($point['tags'], true) : array(),
                'status' => $point['status'],
                'status_label' => $status_label,
                'report_status' => $point['report_status'],
                'report_status_label' => $report_status_label,
                'resolved_delete_at' => $point['resolved_delete_at'] ?? null,
                'resolved_summary' => $point['resolved_summary'] ?? null,
                'rejected_reason' => $point['rejected_reason'] ?? null,
                'rejected_delete_at' => $point['rejected_delete_at'] ?? null,
                'author_id' => intval($point['author_id']),
                'author_name' => $author_name,
                'author_hidden' => (bool)$point['author_hidden'],
                'images' => $images,
                'featured_image_index' => intval($point['featured_image_index'] ?? 0),
                'rating'        => $rating,
                'ratings_count' => $ratings_count,
                'my_rating'     => $my_rating,
                'date' => array(
                    'raw' => $point['created_at'],
                    'human' => human_time_diff(strtotime($point['created_at'] . ' UTC'), time()) . ' temu',
                    'full' => get_date_from_gmt($point['created_at'], 'd.m.Y, H:i')
                ),
                'admin' => $is_admin ? array(
                    'author_name_real' => $author ? $author->display_name : '',
                    'author_email' => $author_email,
                    'ip' => $point['ip_address'] ?: '(brak)'
                ) : null,
                'last_modifier' => $is_admin ? self::get_last_modifier_info($point['id']) : null,
                // SECURITY: For unauthenticated users, always hide moderation data
                'admin_note' => ($current_user_id > 0) ? $point['admin_note'] : null,
                'opening_hours' => $point['opening_hours'] ?? null,
                'price_range'   => $point['price_range'] ?? null,
                'serves_cuisine' => $point['serves_cuisine'] ?? null,
                'pending_edit' => (bool)($point['pending_edit'] ?? 0),
                'is_pending' => ($current_user_id > 0) ? $is_pending : false,
                'is_edit' => ($current_user_id > 0) ? $is_edit : false,
                'edit_info' => ($current_user_id > 0) ? $edit_info : null,
                'is_deletion_requested' => ($current_user_id > 0) ? $is_deletion_requested : false,
                'deletion_info' => ($current_user_id > 0) ? $deletion_info : null,
                'is_own_place' => ($current_user_id > 0) ? $is_own_place : false,
                'edit_locked' => (bool)($point['edit_locked'] ?? 0),
                'reports_count' => ($current_user_id > 0) ? $reports_count : 0,
                'user_has_reported' => ($current_user_id > 0) ? $user_has_reported : false,
                'reporter_info' => ($current_user_id > 0) ? $reporter_info : null,
                'stats' => ($is_admin || $is_own_place) ? array(
                    'views' => intval($point['stats_views'] ?? 0),
                    'phone_clicks' => intval($point['stats_phone_clicks'] ?? 0),
                    'website_clicks' => intval($point['stats_website_clicks'] ?? 0),
                    'social_clicks' => json_decode($point['stats_social_clicks'] ?? '{}', true) ?: array(),
                    'cta_clicks' => intval($point['stats_cta_clicks'] ?? 0),
                    'gallery_clicks' => json_decode($point['stats_gallery_clicks'] ?? '{}', true) ?: array(),
                    'first_viewed' => $point['stats_first_viewed'] ? $point['stats_first_viewed'] . ' UTC' : null,
                    'last_viewed' => $point['stats_last_viewed'] ? $point['stats_last_viewed'] . ' UTC' : null,
                    'unique_visitors' => intval($point['stats_unique_visitors'] ?? 0),
                    'avg_time_spent' => intval($point['stats_avg_time_spent'] ?? 0)
                ) : null
            );
        }

        // DEBUG: Log the actual $result array before sending to JavaScript
        foreach (array_slice($result, 0, 3) as $item) {
            if ($item['type'] === 'zgloszenie') {
            }
        }

        wp_send_json_success($result);
    }

    /**
     * Get all existing tags for autocomplete suggestions
     */
    public function get_tags() {
        $tags = JG_Map_Database::get_all_tags();
        wp_send_json_success($tags);
    }

    /**
     * Get stats for a single point (for live updates)
     */
    public function get_point_stats() {
        $this->verify_nonce();
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_points';

        $point_id = isset($_POST['point_id']) ? intval($_POST['point_id']) : 0;

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Missing point_id'));
            return;
        }

        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        // Get point with all data
        $point = $wpdb->get_row($wpdb->prepare(
            "SELECT id, author_id, is_promo, images, facebook_url, instagram_url, linkedin_url, tiktok_url,
                    website, phone, cta_enabled, cta_type,
                    stats_views, stats_phone_clicks, stats_website_clicks, stats_social_clicks,
                    stats_cta_clicks, stats_gallery_clicks, stats_first_viewed, stats_last_viewed,
                    stats_unique_visitors, stats_avg_time_spent
             FROM $table WHERE id = %d",
            $point_id
        ), ARRAY_A);

        if (!$point) {
            wp_send_json_error(array('message' => 'Point not found'));
            return;
        }

        $is_own_place = ($current_user_id > 0 && $current_user_id == $point['author_id']);

        // Only return stats if user is admin or owner
        if (!$is_admin && !$is_own_place) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        // Parse images
        $images = array();
        if (!empty($point['images'])) {
            $images_data = json_decode($point['images'], true);
            if (is_array($images_data)) {
                $is_sponsored = (bool)$point['is_promo'];
                $max_visible_images = $is_sponsored ? 12 : 6;
                $images = array_slice($images_data, 0, $max_visible_images);
            }
        }

        $result = array(
            'id' => intval($point['id']),
            'images' => $images,
            'facebook_url' => $point['facebook_url'],
            'instagram_url' => $point['instagram_url'],
            'linkedin_url' => $point['linkedin_url'],
            'tiktok_url' => $point['tiktok_url'],
            'website' => $point['website'],
            'phone' => $point['phone'],
            'email' => $point['email'] ?? null,
            'cta_enabled' => $point['cta_enabled'],
            'cta_type' => $point['cta_type'],
            'stats' => array(
                'views' => intval($point['stats_views'] ?? 0),
                'phone_clicks' => intval($point['stats_phone_clicks'] ?? 0),
                'website_clicks' => intval($point['stats_website_clicks'] ?? 0),
                'social_clicks' => json_decode($point['stats_social_clicks'] ?? '{}', true) ?: array(),
                'cta_clicks' => intval($point['stats_cta_clicks'] ?? 0),
                'gallery_clicks' => json_decode($point['stats_gallery_clicks'] ?? '{}', true) ?: array(),
                'first_viewed' => $point['stats_first_viewed'] ? $point['stats_first_viewed'] . ' UTC' : null,
                'last_viewed' => $point['stats_last_viewed'] ? $point['stats_last_viewed'] . ' UTC' : null,
                'unique_visitors' => intval($point['stats_unique_visitors'] ?? 0),
                'avg_time_spent' => intval($point['stats_avg_time_spent'] ?? 0)
            )
        );

        wp_send_json_success($result);
    }

    /**
     * Get visitors list for a point (for stats modal)
     */
    public function get_point_visitors() {
        $this->verify_nonce();
        global $wpdb;
        $table_points = $wpdb->prefix . 'jg_map_points';
        $table_visits = $wpdb->prefix . 'jg_map_point_visits';

        $point_id = isset($_POST['point_id']) ? intval($_POST['point_id']) : 0;

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Missing point_id'));
            return;
        }

        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        // Check if point exists
        $point = $wpdb->get_row($wpdb->prepare(
            "SELECT id, author_id FROM $table_points WHERE id = %d",
            $point_id
        ), ARRAY_A);

        if (!$point) {
            wp_send_json_error(array('message' => 'Point not found'));
            return;
        }

        $is_own_place = ($current_user_id > 0 && $current_user_id == $point['author_id']);

        // Only return visitors if user is admin or owner
        if (!$is_admin && !$is_own_place) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        // Get all visitors for this point (both logged in and anonymous)
        $visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT v.user_id, v.visitor_fingerprint, v.visit_count, v.first_visited, v.last_visited
             FROM $table_visits v
             WHERE v.point_id = %d
             ORDER BY v.visit_count DESC, v.last_visited DESC",
            $point_id
        ), ARRAY_A);

        // Check for SQL errors (log internally, never expose to client)
        if ($wpdb->last_error) {
            error_log('[JG Map] get_point_visitors DB error for point ' . $point_id . ': ' . $wpdb->last_error);
            wp_send_json_error(array('message' => 'Błąd pobierania danych'));
            return;
        }

        // PERFORMANCE OPTIMIZATION: Prime user cache to avoid N+1 queries
        if (!empty($visitors) && function_exists('wp_prime_user_cache')) {
            $user_ids = array_filter(array_column($visitors, 'user_id'));
            if (!empty($user_ids)) {
                wp_prime_user_cache($user_ids);
            }
        }

        $result = array();
        foreach ($visitors as $visitor) {
            if ($visitor['user_id']) {
                // Logged in user
                $user = get_userdata($visitor['user_id']); // Now from cache
                if ($user) {
                    $result[] = array(
                        'user_id' => intval($visitor['user_id']),
                        'username' => $user->display_name,
                        'visit_count' => intval($visitor['visit_count']),
                        'first_visited' => $visitor['first_visited'] ? $visitor['first_visited'] . ' UTC' : null,
                        'last_visited' => $visitor['last_visited'] ? $visitor['last_visited'] . ' UTC' : null,
                        'is_anonymous' => false
                    );
                }
            } else {
                // Anonymous visitor
                $result[] = array(
                    'user_id' => 0,
                    'username' => 'Użytkownik niezalogowany',
                    'visit_count' => intval($visitor['visit_count']),
                    'first_visited' => $visitor['first_visited'] ? $visitor['first_visited'] . ' UTC' : null,
                    'last_visited' => $visitor['last_visited'] ? $visitor['last_visited'] . ' UTC' : null,
                    'is_anonymous' => true
                );
            }
        }

        wp_send_json_success($result);
    }

    /**
     * Get user information (for user profile modal)
     */
    public function get_user_info() {
        $this->verify_nonce();
        global $wpdb;
        $table_points = $wpdb->prefix . 'jg_map_points';

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Missing user_id'));
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found'));
            return;
        }

        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        // Pagination params
        $points_page = isset($_POST['points_page']) ? max(1, intval($_POST['points_page'])) : 1;
        $photos_page = isset($_POST['photos_page']) ? max(1, intval($_POST['photos_page'])) : 1;
        $edited_points_page = isset($_POST['edited_points_page']) ? max(1, intval($_POST['edited_points_page'])) : 1;
        $points_per_page = 10;
        $photos_per_page = 12;
        $edited_points_per_page = 10;

        // Get user's points count
        $points_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'publish'",
            $user_id
        ));

        // Get pin type counts
        $type_counts_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT type, COUNT(*) as cnt FROM $table_points WHERE author_id = %d AND status = 'publish' GROUP BY type",
            $user_id
        ), ARRAY_A);

        $type_counts = array(
            'miejsce' => 0,
            'ciekawostka' => 0,
            'zgloszenie' => 0
        );
        foreach ($type_counts_raw as $row) {
            if (isset($type_counts[$row['type']])) {
                $type_counts[$row['type']] = intval($row['cnt']);
            }
        }

        // Count total votes cast by this user (both up and down)
        $table_votes = JG_Map_Database::get_votes_table();
        $type_counts['votes'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_votes WHERE user_id = %d",
            $user_id
        )));

        // Count approved edits made by this user
        $table_history = JG_Map_Database::get_history_table();
        $type_counts['edits'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_history WHERE user_id = %d AND status = 'approved'",
            $user_id
        )));

        // Get user's last activity (most recent action across all pin-related tables)
        $table_reports = JG_Map_Database::get_reports_table();
        $last_actions = array();

        $last_point = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table_points WHERE author_id = %d
             ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        if ($last_point && $last_point !== '1970-01-01') $last_actions[] = $last_point;

        $last_edit = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table_history WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        if ($last_edit) $last_actions[] = $last_edit;

        $last_vote = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table_votes WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        if ($last_vote) $last_actions[] = $last_vote;

        $last_report = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table_reports WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
        if ($last_report) $last_actions[] = $last_report;

        $last_activity = null;
        $last_activity_type = null;
        if (!empty($last_actions)) {
            $last_activity = max($last_actions);
            if ($last_report && $last_report === $last_activity) {
                $last_activity_type = 'Zgłoszenie miejsca';
            } elseif ($last_vote && $last_vote === $last_activity) {
                $last_activity_type = 'Głosowanie';
            } elseif ($last_edit && $last_edit === $last_activity) {
                $last_activity_type = 'Edycja miejsca';
            } elseif ($last_point && $last_point === $last_activity) {
                $last_activity_type = 'Dodano pinezkę';
            }
        }

        // Get user's points with pagination
        $points_offset = ($points_page - 1) * $points_per_page;
        $user_points = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, type, created_at FROM $table_points
             WHERE author_id = %d AND status = 'publish'
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $user_id, $points_per_page, $points_offset
        ), ARRAY_A);

        $points_list = array();
        foreach ($user_points as $point) {
            $points_list[] = array(
                'id' => intval($point['id']),
                'title' => $point['title'],
                'type' => $point['type'],
                'created_at' => $point['created_at'] ? $point['created_at'] . ' UTC' : null
            );
        }

        // Get distinct points edited by this user (approved edits only, excluding own points)
        $edited_points_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT h.point_id) FROM $table_history h
             INNER JOIN $table_points p ON p.id = h.point_id
             WHERE h.user_id = %d AND h.status = 'approved' AND p.status = 'publish' AND p.author_id != %d",
            $user_id, $user_id
        )));

        $edited_points_offset = ($edited_points_page - 1) * $edited_points_per_page;
        $edited_points_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.title, p.type, MAX(h.created_at) as last_edited_at
             FROM $table_history h
             INNER JOIN $table_points p ON p.id = h.point_id
             WHERE h.user_id = %d AND h.status = 'approved' AND p.status = 'publish' AND p.author_id != %d
             GROUP BY p.id, p.title, p.type
             ORDER BY last_edited_at DESC
             LIMIT %d OFFSET %d",
            $user_id, $user_id, $edited_points_per_page, $edited_points_offset
        ), ARRAY_A);

        $edited_points_list = array();
        foreach ($edited_points_raw as $ep) {
            $edited_points_list[] = array(
                'id' => intval($ep['id']),
                'title' => $ep['title'],
                'type' => $ep['type'],
                'last_edited_at' => $ep['last_edited_at'] ? $ep['last_edited_at'] . ' UTC' : null
            );
        }

        // Get all user's photos from all their points
        $user_photos_data = $wpdb->get_results($wpdb->prepare(
            "SELECT images FROM $table_points
             WHERE author_id = %d AND status = 'publish' AND images IS NOT NULL AND images != ''
             ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);

        $all_photos = array();
        foreach ($user_photos_data as $point_data) {
            if (!empty($point_data['images'])) {
                $images = json_decode($point_data['images'], true);
                if (is_array($images)) {
                    foreach ($images as $image) {
                        $all_photos[] = $image;
                    }
                }
            }
        }

        $photos_total = count($all_photos);
        $photos_offset = ($photos_page - 1) * $photos_per_page;
        $photos_paged = array_slice($all_photos, $photos_offset, $photos_per_page);

        // Get restrictions (if admin or own profile)
        $restrictions = null;
        if ($is_admin || $current_user_id == $user_id) {
            $restrictions = array(
                'banned_until' => get_user_meta($user_id, 'jg_map_ban_until', true),
                'can_edit' => !get_user_meta($user_id, 'jg_map_restrict_edit', true),
                'can_delete' => !get_user_meta($user_id, 'jg_map_restrict_delete', true),
                'can_add' => !get_user_meta($user_id, 'jg_map_restrict_add', true)
            );
        }

        $result = array(
            'user_id' => $user_id,
            'username' => $user->display_name,
            'member_since' => $user->user_registered . ' UTC',
            'last_activity' => $last_activity ? $last_activity . ' UTC' : null,
            'last_activity_type' => $last_activity_type,
            'points_count' => intval($points_count),
            'type_counts' => $type_counts,
            'points' => $points_list,
            'points_total' => intval($points_count),
            'points_page' => $points_page,
            'points_pages' => max(1, ceil(intval($points_count) / $points_per_page)),
            'edited_points' => $edited_points_list,
            'edited_points_total' => $edited_points_count,
            'edited_points_page' => $edited_points_page,
            'edited_points_pages' => max(1, ceil($edited_points_count / $edited_points_per_page)),
            'photos' => $photos_paged,
            'photos_total' => $photos_total,
            'photos_page' => $photos_page,
            'photos_pages' => max(1, ceil($photos_total / $photos_per_page)),
            'restrictions' => $restrictions,
            'is_admin' => $is_admin
        );

        wp_send_json_success($result);
    }

    /**
     * Get last 10 pin-related actions for a user
     */
    public function get_user_activity() {
        $this->verify_nonce();
        global $wpdb;

        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy użytkownik'));
            return;
        }

        $table_points  = $wpdb->prefix . 'jg_map_points';
        $table_votes   = JG_Map_Database::get_votes_table();
        $table_history = JG_Map_Database::get_history_table();
        $table_reports = JG_Map_Database::get_reports_table();

        $actions = array();

        // Points added
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT 'point_added' as action_type, id as point_id, title as point_title, '' as detail, created_at as ts
             FROM $table_points WHERE author_id = %d ORDER BY created_at DESC LIMIT 10",
            $user_id
        ), ARRAY_A);
        foreach ($rows as $r) $actions[] = $r;

        // Edits submitted
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT 'edit' as action_type, h.point_id as point_id, p.title as point_title, h.status as detail, h.created_at as ts
             FROM $table_history h LEFT JOIN $table_points p ON p.id = h.point_id
             WHERE h.user_id = %d ORDER BY h.created_at DESC LIMIT 10",
            $user_id
        ), ARRAY_A);
        foreach ($rows as $r) $actions[] = $r;

        // Votes
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT 'vote' as action_type, v.point_id as point_id, p.title as point_title, v.vote_type as detail, v.created_at as ts
             FROM $table_votes v LEFT JOIN $table_points p ON p.id = v.point_id
             WHERE v.user_id = %d ORDER BY v.created_at DESC LIMIT 10",
            $user_id
        ), ARRAY_A);
        foreach ($rows as $r) $actions[] = $r;

        // Reports submitted
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT 'report' as action_type, r.point_id as point_id, p.title as point_title, '' as detail, r.created_at as ts
             FROM $table_reports r LEFT JOIN $table_points p ON p.id = r.point_id
             WHERE r.user_id = %d ORDER BY r.created_at DESC LIMIT 10",
            $user_id
        ), ARRAY_A);
        foreach ($rows as $r) $actions[] = $r;

        // Sort descending by timestamp, take top 10
        usort($actions, function($a, $b) {
            return strcmp($b['ts'], $a['ts']);
        });
        $actions = array_slice($actions, 0, 10);

        $status_labels = array(
            'pending'  => 'oczekuje',
            'approved' => 'zatwierdzona',
            'rejected' => 'odrzucona',
        );

        $result = array();
        foreach ($actions as $a) {
            switch ($a['action_type']) {
                case 'point_added':
                    $label = 'Dodano pinezkę';
                    $icon  = '📍';
                    break;
                case 'point_updated':
                    $label = 'Zaktualizowano pinezkę';
                    $icon  = '✏️';
                    break;
                case 'edit':
                    $status = $status_labels[$a['detail']] ?? $a['detail'];
                    $label  = 'Edycja miejsca (' . $status . ')';
                    $icon   = '✏️';
                    break;
                case 'vote':
                    $stars = intval($a['detail']);
                    $star_str = str_repeat('★', $stars) . str_repeat('☆', 5 - $stars);
                    $label = 'Oceniono: ' . $star_str . ' (' . $stars . '/5)';
                    $icon  = '⭐';
                    break;
                case 'report':
                    $label = 'Zgłoszono miejsce';
                    $icon  = '🚩';
                    break;
                default:
                    $label = $a['action_type'];
                    $icon  = '•';
            }

            $result[] = array(
                'action_type' => $a['action_type'],
                'label'       => $label,
                'icon'        => $icon,
                'point_id'    => intval($a['point_id']),
                'point_title' => $a['point_title'] ?: '#' . intval($a['point_id']),
                'ts'          => $a['ts'] ? $a['ts'] . ' UTC' : null,
            );
        }

        wp_send_json_success($result);
    }

    /**
     * Get top 10 users ranking by number of published places
     */
    public function get_ranking() {
        $this->verify_nonce();
        global $wpdb;
        $table_points = $wpdb->prefix . 'jg_map_points';
        $table_xp = $wpdb->prefix . 'jg_map_user_xp';

        $results = $wpdb->get_results(
            "SELECT u.ID as user_id, u.display_name,
                    COUNT(p.id) as places_count,
                    COALESCE(MAX(xp.level), 1) as user_level,
                    COALESCE(MAX(xp.xp), 0) as total_xp
             FROM {$wpdb->users} u
             LEFT JOIN $table_points p ON p.author_id = u.ID AND p.status = 'publish'
             LEFT JOIN $table_xp xp ON xp.user_id = u.ID
             GROUP BY u.ID
             HAVING places_count > 0 OR total_xp > 0
             ORDER BY places_count DESC, user_level DESC, u.user_registered ASC
             LIMIT 10",
            ARRAY_A
        );

        $ranking = array();
        foreach ($results as $row) {
            $ranking[] = array(
                'user_id' => intval($row['user_id']),
                'display_name' => $row['display_name'],
                'places_count' => intval($row['places_count']),
                'level' => intval($row['user_level'])
            );
        }

        wp_send_json_success($ranking);
    }

    /**
     * Check daily limits for user
     */
    private function check_daily_limit($user_id, $limit_type) {
        // Admins and moderators have no limits
        if (current_user_can('manage_options') || current_user_can('jg_map_moderate')) {
            return true;
        }

        $today = date('Y-m-d');
        $last_reset = get_user_meta($user_id, 'jg_map_daily_reset', true);

        // Reset counters if it's a new day
        if ($last_reset !== $today) {
            update_user_meta($user_id, 'jg_map_daily_places', 0);
            update_user_meta($user_id, 'jg_map_daily_reports', 0);
            update_user_meta($user_id, 'jg_map_daily_reset', $today);
        }

        // Get custom limits or use defaults
        $default_limits = array(
            'places' => 5,  // Places + Curiosities combined
            'reports' => 5  // Reports
        );

        $custom_limit = get_user_meta($user_id, 'jg_map_daily_' . $limit_type . '_limit', true);
        $limit = ($custom_limit !== '' && $custom_limit !== false) ? intval($custom_limit) : $default_limits[$limit_type];

        $meta_key = 'jg_map_daily_' . $limit_type;
        $current_count = intval(get_user_meta($user_id, $meta_key, true));

        if ($current_count >= $limit) {
            return false;
        }

        // Increment counter
        update_user_meta($user_id, $meta_key, $current_count + 1);
        return true;
    }

    /**
     * Decrement daily limit (called when point is rejected)
     */
    private function decrement_daily_limit($user_id, $limit_type) {
        $meta_key = 'jg_map_daily_' . $limit_type;
        $current_count = intval(get_user_meta($user_id, $meta_key, true));

        if ($current_count > 0) {
            update_user_meta($user_id, $meta_key, $current_count - 1);
        }
    }

    /**
     * Get remaining daily limits for current user
     */
    public function get_daily_limits() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nie jesteś zalogowany'));
            exit;
        }

        // Admins and moderators have no limits
        if (current_user_can('manage_options') || current_user_can('jg_map_moderate')) {
            wp_send_json_success(array(
                'places_remaining' => 999,
                'reports_remaining' => 999,
                'photo_used_mb' => 0,
                'photo_limit_mb' => 999,
                'is_admin' => true
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

        // Get monthly photo usage
        $photo_data = $this->get_monthly_photo_usage($user_id);

        wp_send_json_success(array(
            'places_remaining' => max(0, $places_limit - $places_used),
            'reports_remaining' => max(0, $reports_limit - $reports_used),
            'photo_used_mb' => $photo_data['used_mb'],
            'photo_limit_mb' => $photo_data['limit_mb'],
            'is_admin' => false
        ));
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
     * Get points for sidebar widget with sorting and filtering
     */
    public function get_sidebar_points() {
        $this->verify_nonce();
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        $current_user_id = get_current_user_id();

        // Get filter and sort parameters
        $type_filters = isset($_POST['type_filters']) ? (array)$_POST['type_filters'] : array();
        // FIX: Use filter_var to properly convert boolean from POST (string "false" was being cast to true)
        $my_places = isset($_POST['my_places']) ? filter_var($_POST['my_places'], FILTER_VALIDATE_BOOLEAN) : false;
        $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'date_desc';

        // Category filters
        $place_categories = isset($_POST['place_categories']) ? array_map('sanitize_text_field', (array)$_POST['place_categories']) : array();
        $curiosity_categories = isset($_POST['curiosity_categories']) ? array_map('sanitize_text_field', (array)$_POST['curiosity_categories']) : array();

        // SIDEBAR SHOWS ONLY PUBLISHED POINTS (not pending)
        // Pending points are visible only on the map for moderation
        $points = JG_Map_Database::get_published_points(false);

        // PERFORMANCE OPTIMIZATION: Batch load votes for all points to avoid N+1 queries
        $point_ids = array_column($points, 'id');
        $votes_counts_map = !empty($point_ids) ? JG_Map_Database::get_votes_counts_batch($point_ids) : array();

        $points_with_votes = array();
        foreach ($points as $point) {
            $point_id = intval($point['id']);
            $rating_batch = isset($votes_counts_map[$point_id]) ? $votes_counts_map[$point_id] : array('avg' => 0.0, 'count' => 0);
            $point['votes_count']    = $rating_batch['avg'];
            $point['ratings_count']  = $rating_batch['count'];
            $points_with_votes[] = $point;
        }

        // Apply filters
        $filtered_points = array();

        foreach ($points_with_votes as $point) {
            $is_sponsored = (bool)$point['is_promo'];
            $is_my_place = ($current_user_id > 0 && $point['author_id'] == $current_user_id);

            // "Moje miejsca" filter
            if ($my_places) {
                if (!$is_sponsored && !$is_my_place) {
                    continue;
                }
            }

            // Type filters (miejsca, ciekawostki, zgloszenia)
            if (!empty($type_filters)) {
                $matches_type = in_array($point['type'], $type_filters);
                if (!$matches_type && !$is_sponsored) {
                    continue;
                }
            }

            // Category filters for places
            if ($point['type'] === 'miejsce' && !empty($place_categories) && !$is_sponsored) {
                $point_category = isset($point['category']) ? $point['category'] : '';
                // If point has no category, show it only if no category filter is selected
                // If point has category, show it only if it matches selected categories
                if (!empty($point_category) && !in_array($point_category, $place_categories)) {
                    continue;
                }
            }

            // Category filters for curiosities
            if ($point['type'] === 'ciekawostka' && !empty($curiosity_categories) && !$is_sponsored) {
                $point_category = isset($point['category']) ? $point['category'] : '';
                if (!empty($point_category) && !in_array($point_category, $curiosity_categories)) {
                    continue;
                }
            }

            $filtered_points[] = $point;
        }

        // Sort points
        $sponsored_points = array();
        $regular_points = array();

        // Separate sponsored from regular
        foreach ($filtered_points as $point) {
            if ((bool)$point['is_promo']) {
                $sponsored_points[] = $point;
            } else {
                $regular_points[] = $point;
            }
        }

        // Randomize sponsored order on each page load
        shuffle($sponsored_points);

        // Sort regular points based on sort_by parameter
        switch ($sort_by) {
            case 'date_asc':
                usort($regular_points, function($a, $b) {
                    return strtotime($a['created_at']) - strtotime($b['created_at']);
                });
                break;

            case 'date_desc':
            default:
                usort($regular_points, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                break;

            case 'alpha_asc':
                usort($regular_points, function($a, $b) {
                    return strcasecmp($a['title'], $b['title']);
                });
                break;

            case 'alpha_desc':
                usort($regular_points, function($a, $b) {
                    return strcasecmp($b['title'], $a['title']);
                });
                break;

            case 'votes_desc':
                usort($regular_points, function($a, $b) {
                    $a_cnt = isset($a['ratings_count']) ? (int)$a['ratings_count'] : 0;
                    $b_cnt = isset($b['ratings_count']) ? (int)$b['ratings_count'] : 0;
                    // Unrated items (no votes) always go to end
                    if (($a_cnt > 0) !== ($b_cnt > 0)) {
                        return ($b_cnt > 0) <=> ($a_cnt > 0);
                    }
                    return $b['votes_count'] <=> $a['votes_count'];
                });
                break;

            case 'votes_asc':
                usort($regular_points, function($a, $b) {
                    $a_cnt = isset($a['ratings_count']) ? (int)$a['ratings_count'] : 0;
                    $b_cnt = isset($b['ratings_count']) ? (int)$b['ratings_count'] : 0;
                    // Unrated items (no votes) always go to end
                    if (($a_cnt > 0) !== ($b_cnt > 0)) {
                        return ($b_cnt > 0) <=> ($a_cnt > 0);
                    }
                    return $a['votes_count'] <=> $b['votes_count'];
                });
                break;

            case 'modified_desc':
                // Use last APPROVED edit timestamp from history table so that
                // rejected edits (which bump updated_at via ON UPDATE CURRENT_TIMESTAMP)
                // do not cause places to appear at the top of "last edited" sort.
                $approved_edit_map = array();
                if (!empty($regular_points)) {
                    global $wpdb;
                    $history_table = $wpdb->prefix . 'jg_map_history';
                    $reg_ids = array_map('intval', array_column($regular_points, 'id'));
                    $id_placeholders = implode(',', array_fill(0, count($reg_ids), '%d'));
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $approved_rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT point_id, MAX(resolved_at) AS last_approved FROM {$history_table} WHERE status = 'approved' AND action_type IN ('edit', 'edit_menu') AND point_id IN ($id_placeholders) GROUP BY point_id",
                            $reg_ids
                        ),
                        ARRAY_A
                    );
                    if (is_array($approved_rows)) {
                        foreach ($approved_rows as $row) {
                            $approved_edit_map[intval($row['point_id'])] = $row['last_approved'];
                        }
                    }
                }
                usort($regular_points, function($a, $b) use ($approved_edit_map) {
                    $pid_a = intval($a['id']);
                    $pid_b = intval($b['id']);
                    // Prefer last approved edit timestamp; fall back to created_at
                    $ta = isset($approved_edit_map[$pid_a]) && $approved_edit_map[$pid_a]
                        ? strtotime($approved_edit_map[$pid_a])
                        : strtotime($a['created_at']);
                    $tb = isset($approved_edit_map[$pid_b]) && $approved_edit_map[$pid_b]
                        ? strtotime($approved_edit_map[$pid_b])
                        : strtotime($b['created_at']);
                    return $tb - $ta;
                });
                break;
        }

        // Merge sponsored at the top
        $sorted_points = array_merge($sponsored_points, $regular_points);

        // Build simplified result for sidebar
        $is_admin_or_mod = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        // Batch-load menu presence for menu-capable places (avoids N+1 queries)
        $menu_categories   = self::get_menu_categories();
        $menu_capable_ids  = array();
        foreach ($sorted_points as $point) {
            if ($point['type'] === 'miejsce' && in_array($point['category'] ?? '', $menu_categories, true)) {
                $menu_capable_ids[] = intval($point['id']);
            }
        }
        $has_menu_ids = !empty($menu_capable_ids)
            ? JG_Map_Database::get_menu_point_ids_batch($menu_capable_ids)
            : array();
        $has_menu_set = array_flip($has_menu_ids);

        $result = array();
        foreach ($sorted_points as $point) {
            $item = array(
                'id' => $point['id'],
                'title' => $point['title'],
                'slug' => $point['slug'],
                'type' => $point['type'],
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'is_promo' => (bool)$point['is_promo'],
                'votes_count' => $point['votes_count'],
                'ratings_count' => $point['ratings_count'],
                'created_at' => $point['created_at'],
                'updated_at' => $point['updated_at'],
                'date' => array(
                    'raw' => $point['created_at'],
                    'human' => human_time_diff(strtotime($point['created_at'] . ' UTC'), time()) . ' temu',
                    'full' => get_date_from_gmt($point['created_at'], 'd.m.Y, H:i')
                ),
                'featured_image'   => $this->get_featured_image_url($point),
                'has_description'  => !empty($point['content']) || !empty($point['excerpt']),
                'has_incomplete_sections' => strpos($point['content'] ?? '', 'jg-section-incomplete') !== false,
                'has_tags'         => $this->point_has_tags($point),
                'category'         => !empty($point['category']) ? sanitize_text_field($point['category']) : '',
                'images_count'     => $this->get_images_count($point),
                'opening_hours'    => ($point['type'] === 'miejsce') ? ($point['opening_hours'] ?? null) : null,
                'can_have_menu'    => in_array(intval($point['id']), $menu_capable_ids, true),
                'has_menu'         => isset($has_menu_set[intval($point['id'])])
            );

            // Admin/moderator-only fields
            if ($is_admin_or_mod) {
                $content = $point['content'] ?? '';
                $item['has_internal_links'] = $this->point_has_internal_links($content);
                $item['has_external_links'] = $this->point_has_external_links($content);
            }

            $result[] = $item;
        }

        // Calculate statistics
        $stats = array(
            'total' => count($points_with_votes),
            'miejsce' => 0,
            'ciekawostka' => 0,
            'zgloszenie' => 0
        );

        foreach ($points_with_votes as $point) {
            if (isset($stats[$point['type']])) {
                $stats[$point['type']]++;
            }
        }

        wp_send_json_success(array(
            'points' => $result,
            'stats' => $stats
        ));
    }

    /**
     * Get featured image URL for a point
     */
    private function get_featured_image_url($point) {
        if (empty($point['images'])) {
            return '';
        }

        $images = json_decode($point['images'], true);
        if (!is_array($images) || empty($images)) {
            return '';
        }

        $featured_index = isset($point['featured_image_index']) ? intval($point['featured_image_index']) : 0;

        $featured_image = null;
        if (isset($images[$featured_index])) {
            $featured_image = $images[$featured_index];
        } else {
            $featured_image = $images[0];
        }

        // Support both old format (string URL) and new format (object with thumb/full)
        if (is_array($featured_image)) {
            // New format: return thumb for sidebar list
            return $featured_image['thumb'] ?? $featured_image['full'] ?? '';
        }

        // Old format: return string URL
        return $featured_image;
    }

    /**
     * Check whether a point has at least one non-empty tag
     */
    private function point_has_tags($point) {
        if (empty($point['tags'])) {
            return false;
        }
        $tags = json_decode($point['tags'], true);
        return is_array($tags) && count($tags) > 0;
    }

    /**
     * Get count of images for a point
     */
    private function get_images_count($point) {
        if (empty($point['images'])) {
            return 0;
        }
        $images = json_decode($point['images'], true);
        return is_array($images) ? count($images) : 0;
    }

    /**
     * Check whether point content contains links to other pins (internal links).
     * Matches URLs like /miejsce/slug, /ciekawostka/slug, /zgloszenie/slug
     * or full URLs pointing to the same site with those patterns.
     */
    private function point_has_internal_links($content) {
        if (empty($content)) {
            return false;
        }
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $escaped_host = preg_quote($site_host, '/');
        // Match href attributes containing internal pin URLs
        $pattern = '/href=["\'](?:https?:\/\/' . $escaped_host . ')?\/(miejsce|ciekawostka|zgloszenie)\/[^"\']+["\']/i';
        return (bool) preg_match($pattern, $content);
    }

    /**
     * Check whether point content contains external links (links to other domains).
     */
    private function point_has_external_links($content) {
        if (empty($content)) {
            return false;
        }
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        // Find all href URLs in content
        if (!preg_match_all('/href=["\'](?:https?:\/\/)([^"\'\/]+)/i', $content, $matches)) {
            return false;
        }
        foreach ($matches[1] as $host) {
            $host = strtolower($host);
            if ($host !== strtolower($site_host) && $host !== 'www.' . strtolower($site_host)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Track statistics for sponsored pins
     * Tracks: views, phone_clicks, website_clicks, social_clicks, cta_clicks, gallery_clicks
     */
    public function track_stat() {
        $this->verify_nonce();
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_points';

        $point_id = isset($_POST['point_id']) ? intval($_POST['point_id']) : 0;
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        $platform = isset($_POST['platform']) ? sanitize_text_field($_POST['platform']) : '';
        $image_index = isset($_POST['image_index']) ? intval($_POST['image_index']) : -1;
        $time_spent = isset($_POST['time_spent']) ? intval($_POST['time_spent']) : 0;
        // FIX: Properly convert string "true"/"false" to boolean
        // URLSearchParams sends booleans as strings, and (bool)"false" = true in PHP
        $is_unique = isset($_POST['is_unique']) && filter_var($_POST['is_unique'], FILTER_VALIDATE_BOOLEAN);

        // Whitelist allowed action types
        $allowed_action_types = array('view', 'time_spent', 'phone_click', 'website_click', 'social_click', 'cta_click', 'gallery_click');
        if ($action_type && !in_array($action_type, $allowed_action_types, true)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy typ akcji'));
            return;
        }

        // Whitelist allowed social platforms
        $allowed_platforms = array('facebook', 'instagram', 'linkedin', 'tiktok');
        if ($platform && !in_array($platform, $allowed_platforms, true)) {
            wp_send_json_error(array('message' => 'Nieprawidłowa platforma'));
            return;
        }

        // Bound image_index to reasonable range (max 12 images for sponsored)
        if ($image_index > 11) {
            wp_send_json_error(array('message' => 'Nieprawidłowy indeks zdjęcia'));
            return;
        }

        if (!$point_id || !$action_type) {
            wp_send_json_error(array('message' => 'Brak wymaganych parametrów'));
            return;
        }

        // Check if point exists and is sponsored
        $point = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_promo, stats_first_viewed, stats_social_clicks, stats_gallery_clicks, stats_views, stats_unique_visitors, stats_avg_time_spent FROM $table WHERE id = %d",
            $point_id
        ), ARRAY_A);

        if (!$point) {
            wp_send_json_error(array('message' => 'Nie znaleziono pinezki'));
            return;
        }

        // Only track stats for sponsored/promo places
        if (!$point['is_promo']) {
            wp_send_json_success(array('message' => 'Tracking disabled for non-sponsored places'));
            return;
        }

        // Rate limit: max 60 stat events per minute per IP per point (prevents stat inflation bots)
        $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_identifier = $visitor_ip . '_point_' . $point_id;
        $rate_check = $this->check_rate_limit('track_stat', $rate_identifier, 60, 60);
        if (!$rate_check['allowed']) {
            wp_send_json_success(array('message' => 'Rate limit reached'));
            return;
        }
        $this->check_rate_limit('track_stat', $rate_identifier, 60, 60, array(), true);

        $current_time = current_time('mysql', true); // GMT time for consistency with other timestamps
        $result = false;

        switch ($action_type) {
            case 'view':
                // Track individual visitor (for stats dashboard)
                $current_user_id = get_current_user_id();
                $visitor_table = $wpdb->prefix . 'jg_map_point_visits';

                if ($current_user_id > 0) {
                    // Logged in user - track by user_id
                    $existing_visit = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, visit_count FROM $visitor_table WHERE point_id = %d AND user_id = %d",
                        $point_id,
                        $current_user_id
                    ), ARRAY_A);

                    if ($existing_visit) {
                        // Update visit count
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $visitor_table SET visit_count = visit_count + 1, last_visited = %s WHERE id = %d",
                            $current_time,
                            $existing_visit['id']
                        ));
                        $server_is_unique = false; // Returning visitor
                    } else {
                        // First visit - insert
                        $wpdb->insert($visitor_table, array(
                            'point_id' => $point_id,
                            'user_id' => $current_user_id,
                            'visit_count' => 1,
                            'first_visited' => $current_time,
                            'last_visited' => $current_time
                        ));
                        $server_is_unique = true; // New visitor
                    }
                } else {
                    // Not logged in - track by fingerprint (IP + User Agent hash)
                    $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $visitor_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $fingerprint = md5($visitor_ip . '|' . $visitor_ua);

                    $existing_visit = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, visit_count FROM $visitor_table WHERE point_id = %d AND visitor_fingerprint = %s",
                        $point_id,
                        $fingerprint
                    ), ARRAY_A);

                    if ($existing_visit) {
                        // Update visit count
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $visitor_table SET visit_count = visit_count + 1, last_visited = %s WHERE id = %d",
                            $current_time,
                            $existing_visit['id']
                        ));
                        $server_is_unique = false; // Returning visitor
                    } else {
                        // First visit - insert
                        $wpdb->insert($visitor_table, array(
                            'point_id' => $point_id,
                            'visitor_fingerprint' => $fingerprint,
                            'visit_count' => 1,
                            'first_visited' => $current_time,
                            'last_visited' => $current_time
                        ));
                        $server_is_unique = true; // New visitor
                    }
                }

                // Increment view counter and update last viewed
                $updates = array(
                    'stats_views' => 'COALESCE(stats_views, 0) + 1',
                    'stats_last_viewed' => $current_time
                );

                // Track unique visitor based on server-side check (not client-supplied flag)
                if ($server_is_unique) {
                    $updates['stats_unique_visitors'] = 'COALESCE(stats_unique_visitors, 0) + 1';
                }

                // Build UPDATE query
                $update_parts = array();
                foreach ($updates as $col => $val) {
                    if ($col === 'stats_last_viewed') {
                        $update_parts[] = "$col = '" . esc_sql($val) . "'";
                    } else {
                        $update_parts[] = "$col = $val";
                    }
                }

                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET " . implode(', ', $update_parts) . " WHERE id = %d",
                    $point_id
                ));

                // Set first_viewed if not set
                if ($result !== false && empty($point['stats_first_viewed'])) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $table SET stats_first_viewed = %s WHERE id = %d",
                        $current_time,
                        $point_id
                    ));
                }
                break;

            case 'time_spent':
                // Update average time spent
                if ($time_spent > 0) {
                    $current_views = intval($point['stats_views']) ?: 1;
                    $current_avg = intval($point['stats_avg_time_spent']) ?: 0;

                    // Calculate new average: (current_avg * (views - 1) + time_spent) / views
                    // Use ceil() instead of round() to always round up, ensuring changes are saved
                    $new_avg = ceil(($current_avg * ($current_views - 1) + $time_spent) / $current_views);

                    // Only UPDATE if value actually changed (avoid unnecessary writes)
                    if ($new_avg != $current_avg) {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $table SET stats_avg_time_spent = %d WHERE id = %d",
                            $new_avg,
                            $point_id
                        ));
                    }
                }
                break;

            case 'phone_click':
                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET stats_phone_clicks = COALESCE(stats_phone_clicks, 0) + 1 WHERE id = %d",
                    $point_id
                ));
                break;

            case 'website_click':
                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET stats_website_clicks = COALESCE(stats_website_clicks, 0) + 1 WHERE id = %d",
                    $point_id
                ));
                break;

            case 'social_click':
                if (!$platform) {
                    wp_send_json_error(array('message' => 'Brak platformy dla social_click'));
                    return;
                }

                $social_clicks = json_decode($point['stats_social_clicks'] ?: '{}', true);
                if (!is_array($social_clicks)) {
                    $social_clicks = array();
                }

                $social_clicks[$platform] = isset($social_clicks[$platform]) ? $social_clicks[$platform] + 1 : 1;

                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET stats_social_clicks = %s WHERE id = %d",
                    json_encode($social_clicks),
                    $point_id
                ));
                break;

            case 'cta_click':
                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET stats_cta_clicks = COALESCE(stats_cta_clicks, 0) + 1 WHERE id = %d",
                    $point_id
                ));
                break;

            case 'gallery_click':
                if ($image_index < 0) {
                    wp_send_json_error(array('message' => 'Brak indeksu zdjęcia'));
                    return;
                }

                $gallery_clicks = json_decode($point['stats_gallery_clicks'] ?: '{}', true);
                if (!is_array($gallery_clicks)) {
                    $gallery_clicks = array();
                }

                $gallery_clicks[$image_index] = isset($gallery_clicks[$image_index]) ? $gallery_clicks[$image_index] + 1 : 1;

                $result = $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET stats_gallery_clicks = %s WHERE id = %d",
                    json_encode($gallery_clicks),
                    $point_id
                ));
                break;

            default:
                wp_send_json_error(array('message' => 'Nieznany typ akcji: ' . $action_type));
                return;
        }

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Statystyka zapisana'));
        } else {
            wp_send_json_error(array('message' => 'Błąd zapisu: ' . $wpdb->last_error));
        }
    }

}
