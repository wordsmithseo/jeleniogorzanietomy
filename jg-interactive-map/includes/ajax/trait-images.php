<?php
/**
 * Trait: image handling
 * handle_image_upload, verify_image_mime_type, process_uploaded_image,
 * get_monthly_photo_usage, update_monthly_photo_usage,
 * delete_image, delete_image_files, set_featured_image
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

trait JG_Ajax_Images {

    /**
     * Handle image upload (private helper)
     */
    private function handle_image_upload($files, $max_images = 6, $user_id = 0) {
        $images = array();
        $total_size_uploaded = 0;

        // Const limits
        $MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
        $MAX_DIMENSION = 800; // 800x800
        $MONTHLY_LIMIT_MB = 100; // 100MB per month for regular users

        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        if (!function_exists('wp_get_image_editor')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        // Check monthly limit for non-admins
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        if (!$is_admin && $user_id > 0) {
            $monthly_data = $this->get_monthly_photo_usage($user_id);
            $used_mb = $monthly_data['used_mb'];
            $limit_mb = $monthly_data['limit_mb'];

            // If already at limit, reject
            if ($used_mb >= $limit_mb) {
                return array('error' => 'Osiągnięto miesięczny limit przesyłania zdjęć (' . $limit_mb . 'MB)');
            }
        }

        $upload_overrides = array('test_form' => false);

        // Check if files are in array format (multiple files) or single file format
        if (is_array($files['name'])) {
            // Multiple files format
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($i >= $max_images) {
                    break;
                }

                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    // Check file size (2MB limit)
                    if ($files['size'][$i] > $MAX_FILE_SIZE) {
                        return array('error' => 'Plik ' . $files['name'][$i] . ' jest za duży. Maksymalny rozmiar to 2MB');
                    }

                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    );

                    $movefile = wp_handle_upload($file, $upload_overrides);

                    if ($movefile && !isset($movefile['error'])) {
                        // Verify MIME type for security
                        $mime_check = $this->verify_image_mime_type($movefile['file']);
                        if (!$mime_check['valid']) {
                            @unlink($movefile['file']);
                            return array('error' => $mime_check['error']);
                        }

                        // Resize + thumbnail in one editor pass
                        $processed = $this->process_uploaded_image($movefile['file'], $movefile['url'], $MAX_DIMENSION);

                        // Get actual file size after resize
                        $actual_size = file_exists($movefile['file']) ? filesize($movefile['file']) : 0;
                        $total_size_uploaded += $actual_size;

                        $images[] = array(
                            'full' => $processed['full'],
                            'thumb' => $processed['thumb']
                        );
                    } else {
                        return array('error' => 'Błąd uploadu: ' . ($movefile['error'] ?? 'Nieznany błąd'));
                    }
                }
            }
        } else {
            // Single file format
            if ($files['error'] === UPLOAD_ERR_OK) {
                // Check file size (2MB limit)
                if ($files['size'] > $MAX_FILE_SIZE) {
                    return array('error' => 'Plik jest za duży. Maksymalny rozmiar to 2MB');
                }

                $movefile = wp_handle_upload($files, $upload_overrides);

                if ($movefile && !isset($movefile['error'])) {
                    // Verify MIME type for security
                    $mime_check = $this->verify_image_mime_type($movefile['file']);
                    if (!$mime_check['valid']) {
                        @unlink($movefile['file']);
                        return array('error' => $mime_check['error']);
                    }

                    // Resize + thumbnail in one editor pass
                    $processed = $this->process_uploaded_image($movefile['file'], $movefile['url'], $MAX_DIMENSION);

                    // Get actual file size after resize
                    $actual_size = file_exists($movefile['file']) ? filesize($movefile['file']) : 0;
                    $total_size_uploaded += $actual_size;

                    $images[] = array(
                        'full' => $processed['full'],
                        'thumb' => $processed['thumb']
                    );
                } else {
                    return array('error' => 'Błąd uploadu: ' . ($movefile['error'] ?? 'Nieznany błąd'));
                }
            }
        }

        // Update monthly usage for non-admins
        if (!$is_admin && $user_id > 0 && $total_size_uploaded > 0) {
            $this->update_monthly_photo_usage($user_id, $total_size_uploaded);
        }

        return array('images' => $images);
    }

    /**
     * Verify image MIME type for security
     */
    private function verify_image_mime_type($file_path) {
        $allowed_mimes = array(
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp'
        );

        // Check with finfo (most reliable)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_path);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_mimes, true)) {
                return array(
                    'valid' => false,
                    'error' => 'Nieprawidłowy typ pliku. Dozwolone są tylko obrazy (JPG, PNG, GIF, WebP)'
                );
            }
        }

        // Additional check with getimagesize
        $image_info = @getimagesize($file_path);
        if ($image_info === false) {
            return array(
                'valid' => false,
                'error' => 'Plik nie jest prawidłowym obrazem'
            );
        }

        // Verify MIME from getimagesize matches allowed types
        if (!in_array($image_info['mime'], $allowed_mimes, true)) {
            return array(
                'valid' => false,
                'error' => 'Nieprawidłowy typ obrazu'
            );
        }

        return array('valid' => true);
    }

    /**
     * Resize to max_dimension and create 300px thumbnail in a single editor load.
     * Returns array with 'full' (original URL) and 'thumb' (thumbnail URL).
     */
    private function process_uploaded_image($file_path, $original_url, $max_dimension) {
        $image_editor = wp_get_image_editor($file_path);

        if (is_wp_error($image_editor)) {
            return array('full' => $original_url, 'thumb' => $original_url);
        }

        $size = $image_editor->get_size();

        if ($size['width'] > $max_dimension || $size['height'] > $max_dimension) {
            $image_editor->resize($max_dimension, $max_dimension, false);
            $image_editor->save($file_path);
        }

        $image_editor->resize(300, 300, false);
        $file_info     = pathinfo($file_path);
        $thumbnail_path = $file_info['dirname'] . '/' . $file_info['filename'] . '-thumb.' . $file_info['extension'];
        $saved          = $image_editor->save($thumbnail_path);

        $thumbnail_url = null;
        if (!is_wp_error($saved)) {
            $upload_dir    = wp_upload_dir();
            $thumbnail_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $thumbnail_path);
        }

        return array(
            'full'  => $original_url,
            'thumb' => $thumbnail_url ?: $original_url,
        );
    }

    /**
     * Get monthly photo usage for user
     */
    private function get_monthly_photo_usage($user_id) {
        $current_month = date('Y-m');
        $last_reset_month = get_user_meta($user_id, 'jg_map_photo_month', true);

        // Reset if new month
        if ($last_reset_month !== $current_month) {
            update_user_meta($user_id, 'jg_map_photo_month', $current_month);
            update_user_meta($user_id, 'jg_map_photo_used_bytes', 0);
            delete_user_meta($user_id, 'jg_map_photo_custom_limit'); // Reset custom limit
        }

        $used_bytes = intval(get_user_meta($user_id, 'jg_map_photo_used_bytes', true));
        $custom_limit_mb = get_user_meta($user_id, 'jg_map_photo_custom_limit', true);
        $limit_mb = $custom_limit_mb ? intval($custom_limit_mb) : 100; // Default 100MB

        return array(
            'used_mb' => round($used_bytes / (1024 * 1024), 2),
            'limit_mb' => $limit_mb,
            'used_bytes' => $used_bytes
        );
    }

    /**
     * Update monthly photo usage
     */
    private function update_monthly_photo_usage($user_id, $bytes_to_add) {
        $current_usage = intval(get_user_meta($user_id, 'jg_map_photo_used_bytes', true));
        $new_usage = $current_usage + $bytes_to_add;
        update_user_meta($user_id, 'jg_map_photo_used_bytes', $new_usage);
    }

    /**
     * Delete image from point
     * Admins/moderators can delete from any point, users can only delete from their own points
     */
    public function delete_image() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();
        $point_id = intval($_POST['point_id'] ?? 0);
        $image_index = intval($_POST['image_index'] ?? -1);

        if (!$point_id || $image_index < 0) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Check permissions
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        $is_author = (intval($point['author_id']) === $user_id);

        if (!$is_admin && !$is_author) {
            wp_send_json_error(array('message' => 'Brak uprawnień do usuwania zdjęć z tego miejsca'));
            exit;
        }

        // Get existing images
        $images = json_decode($point['images'] ?? '[]', true) ?: array();

        if (!isset($images[$image_index])) {
            wp_send_json_error(array('message' => 'Zdjęcie nie istnieje'));
            exit;
        }

        // Delete physical image files before removing from array
        $image_to_delete = $images[$image_index];
        $this->delete_image_files($image_to_delete);

        // Get current featured image index
        $current_featured = isset($point['featured_image_index']) ? (int)$point['featured_image_index'] : 0;

        // Remove image from array
        array_splice($images, $image_index, 1);

        // Update featured_image_index based on deletion
        $new_featured_index = $current_featured;

        if ($image_index === $current_featured) {
            // Deleted image was featured - set first image as new featured
            $new_featured_index = 0;
        } elseif ($image_index < $current_featured) {
            // Deleted image was before featured - shift featured index down
            $new_featured_index = $current_featured - 1;
        }
        // else: deleted image was after featured - no change needed

        // Ensure featured index is within bounds (shouldn't happen, but safety check)
        if ($new_featured_index >= count($images)) {
            $new_featured_index = max(0, count($images) - 1);
        }

        // Update point with new images array and adjusted featured index
        $update_data = array(
            'images' => json_encode($images)
        );

        if (!empty($images)) {
            $update_data['featured_image_index'] = $new_featured_index;
        } else {
            // No images left - clear featured index
            $update_data['featured_image_index'] = null;
        }

        JG_Map_Database::update_point($point_id, $update_data);

        // Log user action
        JG_Map_Activity_Log::log_user_action(
            'delete_image',
            'point',
            $point_id,
            sprintf('Usunięto zdjęcie #%d z miejsca: %s', $image_index + 1, $point['title'])
        );

        wp_send_json_success(array(
            'message' => 'Zdjęcie usunięte',
            'remaining_count' => count($images),
            'new_featured_index' => $update_data['featured_image_index']
        ));
    }

    /**
     * Delete physical image files from filesystem
     *
     * @param array $image Image array with 'full' and 'thumb' URLs
     */
    private function delete_image_files($image) {
        if (empty($image) || !is_array($image)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $upload_base_url = $upload_dir['baseurl'];
        $upload_base_path = $upload_dir['basedir'];

        // Delete full size image
        if (!empty($image['full'])) {
            $file_path = str_replace($upload_base_url, $upload_base_path, $image['full']);
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }

        // Delete thumbnail (only if different from full image)
        if (!empty($image['thumb']) && $image['thumb'] !== $image['full']) {
            $thumb_path = str_replace($upload_base_url, $upload_base_path, $image['thumb']);
            if (file_exists($thumb_path)) {
                @unlink($thumb_path);
            }
        }
    }

    /**
     * Set featured image for point
     * Admins/moderators can set for any point, users can only set for their own points
     */
    public function set_featured_image() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $user_id = get_current_user_id();
        $point_id = intval($_POST['point_id'] ?? 0);
        $image_index = intval($_POST['image_index'] ?? 0);

        if (!$point_id || $image_index < 0) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        // Check permissions
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');
        $is_author = (intval($point['author_id']) === $user_id);

        if (!$is_admin && !$is_author) {
            wp_send_json_error(array('message' => 'Brak uprawnień do edycji tego miejsca'));
            exit;
        }

        // Verify image exists
        $images = json_decode($point['images'] ?? '[]', true) ?: array();
        if (!isset($images[$image_index])) {
            wp_send_json_error(array('message' => 'Zdjęcie nie istnieje'));
            exit;
        }

        // Update featured_image_index
        JG_Map_Database::update_point($point_id, array(
            'featured_image_index' => $image_index
        ));

        // Log user action
        JG_Map_Activity_Log::log_user_action(
            'set_featured_image',
            'point',
            $point_id,
            sprintf('Ustawiono zdjęcie #%d jako wyróżnione dla: %s', $image_index + 1, $point['title'])
        );

        wp_send_json_success(array(
            'message' => 'Wyróżniony obraz ustawiony',
            'featured_image_index' => $image_index
        ));
    }

}
