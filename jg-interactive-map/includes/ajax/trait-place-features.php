<?php
/**
 * Trait JG_Ajax_PlaceFeatures
 * Banner display, menu CRUD, offerings CRUD, contact place, promotion requests.
 */
trait JG_Ajax_PlaceFeatures {

    /**
     * Get banner for display (with rotation logic)
     */
    public function get_banner() {
        $active_banners = JG_Map_Banner_Manager::get_active_banners();

        if (empty($active_banners)) {
            wp_send_json_success(array('banner' => null));
            return;
        }

        // Return all active banners for client-side rotation
        wp_send_json_success(array('banners' => $active_banners));
    }

    /**
     * Track banner impression
     */
    public function track_banner_impression() {
        $banner_id = isset($_POST['banner_id']) ? intval($_POST['banner_id']) : 0;

        if (!$banner_id) {
            wp_send_json_error(array('message' => 'Invalid banner ID'));
            return;
        }

        $result = JG_Map_Banner_Manager::track_impression($banner_id);

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Impression tracked'));
        } else {
            wp_send_json_error(array('message' => 'Failed to track impression'));
        }
    }

    /**
     * Track banner click
     */
    public function track_banner_click() {
        $banner_id = isset($_POST['banner_id']) ? intval($_POST['banner_id']) : 0;

        if (!$banner_id) {
            wp_send_json_error(array('message' => 'Invalid banner ID'));
            return;
        }

        $result = JG_Map_Banner_Manager::track_click($banner_id);

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Click tracked'));
        } else {
            wp_send_json_error(array('message' => 'Failed to track click'));
        }
    }

    /**
     * Handle business promotion request - send inquiry email to oferty@
     */
    public function request_promotion() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany, aby wysłać prośbę o ofertę.'));
            return;
        }

        $current_user = wp_get_current_user();
        $point_id = intval($_POST['point_id'] ?? 0);
        $point_title = sanitize_text_field($_POST['point_title'] ?? '');
        $point_category = sanitize_text_field($_POST['point_category'] ?? '');
        $point_address = sanitize_text_field($_POST['point_address'] ?? '');
        $point_lat = sanitize_text_field($_POST['point_lat'] ?? '');
        $point_lng = sanitize_text_field($_POST['point_lng'] ?? '');

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane pineski.'));
            return;
        }

        // Rate limit: max 3 promotion requests per user per day (across all places)
        $rate_key = 'jg_promo_request_' . $current_user->ID;
        $rate_count = intval(get_transient($rate_key));
        if ($rate_count >= 3) {
            wp_send_json_error(array('message' => 'Wysłano już maksymalną liczbę zapytań na dziś. Spróbuj ponownie jutro.'));
            return;
        }

        // Rate limit: max 1 request per place per user per week
        $place_rate_key = 'jg_promo_place_' . $current_user->ID . '_' . $point_id;
        if (get_transient($place_rate_key)) {
            wp_send_json_error(array('message' => 'Zapytanie o to miejsce zostało już wysłane. Możesz ponowić za tydzień.'));
            return;
        }

        // Get category label
        $place_categories = get_option('jg_map_place_categories', self::get_default_place_categories());
        $category_label = isset($place_categories[$point_category]) ? $place_categories[$point_category]['label'] : $point_category;

        // Build email
        $to = 'oferty@jeleniogorzanietomy.pl';
        $subject = 'Zapytanie o promocję biznesu - ' . $point_title;

        $map_url = '';
        if ($point_lat && $point_lng) {
            $map_url = home_url('/?jg_view_point=' . $point_id);
        }

        $message = "Nowe zapytanie o promocję biznesu na portalu Jeleniogórzanie to my!\n\n";
        $message .= "=== DANE UŻYTKOWNIKA ===\n";
        $message .= "Nazwa użytkownika: " . $current_user->display_name . "\n";
        $message .= "Email: " . $current_user->user_email . "\n";
        $message .= "ID użytkownika: " . $current_user->ID . "\n";
        $message .= "Data rejestracji: " . $current_user->user_registered . "\n\n";

        $message .= "=== DANE PINESKI ===\n";
        $message .= "ID pineski: #" . $point_id . "\n";
        $message .= "Nazwa: " . $point_title . "\n";
        $message .= "Kategoria: " . $category_label . " (" . $point_category . ")\n";
        if ($point_address) {
            $message .= "Adres: " . $point_address . "\n";
        }
        if ($map_url) {
            $message .= "Link do pineski: " . $map_url . "\n";
        }
        $message .= "\n";

        $message .= "=== INFORMACJE ===\n";
        $message .= "Użytkownik jest zainteresowany promocją tego biznesu.\n";
        $message .= "Data zapytania: " . current_time('Y-m-d H:i:s') . "\n";
        $message .= "IP: " . $this->get_user_ip() . "\n";

        $this->send_plugin_email($to, $subject, $message);

        // Update rate limits
        set_transient($rate_key, $rate_count + 1, DAY_IN_SECONDS);
        set_transient($place_rate_key, 1, WEEK_IN_SECONDS);

        // Log user action
        JG_Map_Activity_Log::log_user_action(
            'request_promotion',
            'point',
            $point_id,
            sprintf('Wysłano zapytanie o promocję: %s', $point_title)
        );

        wp_send_json_success(array('message' => 'Prośba o ofertę została wysłana.'));
    }

    /**
     * Public: return menu (sections + items + photos) for a gastronomic place.
     */
    public function get_menu() {
        $point_id = intval($_POST['point_id'] ?? 0);
        if ($point_id <= 0) {
            wp_send_json_error(array('message' => 'Brak point_id'));
            exit;
        }

        $sections    = JG_Map_Database::get_menu($point_id);
        $photos      = JG_Map_Database::get_menu_photos($point_id);
        $size_labels = JG_Map_Database::get_menu_size_labels($point_id);

        wp_send_json_success(array(
            'sections'    => $sections,
            'photos'      => $photos,
            'size_labels' => $size_labels,
        ));
    }

    /**
     * Save full menu (sections + items) for a place.
     * Allowed: owner or admin/moderator.
     */
    public function save_menu() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $point_id = intval($_POST['point_id'] ?? 0);
        if ($point_id <= 0) {
            wp_send_json_error(array('message' => 'Brak point_id'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        $user_id  = get_current_user_id();
        $is_owner = intval($point['author_id']) === $user_id;
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        if (!$is_owner && !$is_admin) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        $raw_sections = isset($_POST['sections']) ? $_POST['sections'] : array();
        if (!is_array($raw_sections)) {
            $decoded = json_decode(wp_unslash($_POST['sections'] ?? '[]'), true);
            $raw_sections = is_array($decoded) ? $decoded : array();
        }

        $raw_size_labels = isset($_POST['size_labels']) ? $_POST['size_labels'] : array();
        if (!is_array($raw_size_labels)) {
            $decoded_labels = json_decode(wp_unslash($_POST['size_labels'] ?? '[]'), true);
            $raw_size_labels = is_array($decoded_labels) ? $decoded_labels : array();
        }

        // Capture old menu state for before/after diff
        $old_sections   = JG_Map_Database::get_menu($point_id);
        $old_size_labels = JG_Map_Database::get_menu_size_labels($point_id);

        JG_Map_Database::save_menu($point_id, $raw_sections);
        JG_Map_Database::save_menu_size_labels($point_id, $raw_size_labels);

        // Build old/new value summaries for history diff
        $old_values = array(
            'menu_sections'   => $old_sections,
            'menu_size_labels' => $old_size_labels,
        );
        $new_values = array(
            'menu_sections'   => $raw_sections,
            'menu_size_labels' => $raw_size_labels,
        );

        // History + moderation + activity log (mirrors update_point logic)
        if ($is_admin) {
            // Admin/mod: auto-approved, no moderation needed
            JG_Map_Database::add_admin_edit_history($point_id, $user_id, $old_values, $new_values);
            JG_Map_Activity_Log::log_user_action(
                'edit_menu',
                'point',
                $point_id,
                sprintf('Zaktualizowano menu miejsca: %s', $point['title'])
            );
        } else {
            // Regular user (owner): pending moderation
            $point_owner_id = ($is_owner) ? null : intval($point['author_id']);
            JG_Map_Database::add_history($point_id, $user_id, 'edit_menu', $old_values, $new_values, $point_owner_id);
            JG_Map_Database::update_point($point_id, array('pending_edit' => 1));
            JG_Map_Activity_Log::log_user_action(
                'suggest_menu_edit',
                'point',
                $point_id,
                sprintf('Zaproponowano zmiany menu: %s', $point['title'])
            );
        }

        wp_send_json_success(array('message' => 'Menu zapisano', 'pending' => !$is_admin));
    }

    /**
     * Upload a menu card photo (Type A).
     */
    public function upload_menu_photo() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $point_id = intval($_POST['point_id'] ?? 0);
        if ($point_id <= 0) {
            wp_send_json_error(array('message' => 'Brak point_id'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        $user_id  = get_current_user_id();
        $is_owner = intval($point['author_id']) === $user_id;
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        if (!$is_owner && !$is_admin) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        // Max 4 menu card photos per place
        $existing_photos = JG_Map_Database::get_menu_photos($point_id);
        if (count($existing_photos) >= 4) {
            wp_send_json_error(array('message' => 'Osiągnięto limit 4 zdjęć karty menu'));
            exit;
        }

        if (empty($_FILES['menu_photo']) || $_FILES['menu_photo']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'Brak pliku lub błąd uploadu'));
            exit;
        }

        $MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
        if ($_FILES['menu_photo']['size'] > $MAX_FILE_SIZE) {
            wp_send_json_error(array('message' => 'Plik jest za duży. Maksymalny rozmiar to 2MB'));
            exit;
        }

        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_get_image_editor')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        $movefile = wp_handle_upload($_FILES['menu_photo'], array('test_form' => false));

        if (!$movefile || isset($movefile['error'])) {
            wp_send_json_error(array('message' => 'Błąd uploadu: ' . ($movefile['error'] ?? 'Nieznany błąd')));
            exit;
        }

        $mime_check = $this->verify_image_mime_type($movefile['file']);
        if (!$mime_check['valid']) {
            @unlink($movefile['file']);
            wp_send_json_error(array('message' => $mime_check['error']));
            exit;
        }

        // Resize + thumbnail in one editor pass (1200px max for menu readability)
        $processed = $this->process_uploaded_image($movefile['file'], $movefile['url'], 1200);
        $thumb      = $processed['thumb'];

        $photo_id = JG_Map_Database::add_menu_photo($point_id, $movefile['url'], $thumb ?: $movefile['url']);

        wp_send_json_success(array(
            'id'        => $photo_id,
            'url'       => $movefile['url'],
            'thumb_url' => $thumb ?: $movefile['url'],
        ));
    }

    /**
     * Delete a menu card photo.
     */
    public function delete_menu_photo() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $point_id = intval($_POST['point_id'] ?? 0);
        $photo_id = intval($_POST['photo_id'] ?? 0);

        if ($point_id <= 0 || $photo_id <= 0) {
            wp_send_json_error(array('message' => 'Nieprawidłowe parametry'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        $user_id  = get_current_user_id();
        $is_owner = intval($point['author_id']) === $user_id;
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        if (!$is_owner && !$is_admin) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        $deleted = JG_Map_Database::delete_menu_photo($photo_id, $point_id);

        if ($deleted) {
            wp_send_json_success(array('message' => 'Zdjęcie usunięte'));
        } else {
            wp_send_json_error(array('message' => 'Nie znaleziono zdjęcia'));
        }
    }

    /**
     * Get offerings (services / products) for a place — public.
     */
    public function get_offerings() {
        $point_id = intval($_POST['point_id'] ?? 0);
        if ($point_id <= 0) {
            wp_send_json_error(array('message' => 'Brak point_id'));
            exit;
        }

        $items = JG_Map_Database::get_offerings($point_id);
        wp_send_json_success(array('items' => $items));
    }

    /**
     * Save offerings (services / products) for a place.
     * Allowed: owner or admin/moderator.
     */
    public function save_offerings() {
        $this->verify_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Musisz być zalogowany'));
            exit;
        }

        $point_id = intval($_POST['point_id'] ?? 0);
        if ($point_id <= 0) {
            wp_send_json_error(array('message' => 'Brak point_id'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        $user_id  = get_current_user_id();
        $is_owner = intval($point['author_id']) === $user_id;
        $is_admin = current_user_can('manage_options') || current_user_can('jg_map_moderate');

        if (!$is_owner && !$is_admin) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        $raw_items = isset($_POST['items']) ? $_POST['items'] : array();
        if (!is_array($raw_items)) {
            $decoded = json_decode(wp_unslash($_POST['items'] ?? '[]'), true);
            $raw_items = is_array($decoded) ? $decoded : array();
        }

        $old_items = JG_Map_Database::get_offerings($point_id);

        JG_Map_Database::save_offerings($point_id, $raw_items);

        $old_values = array('offerings' => $old_items);
        $new_values = array('offerings' => $raw_items);

        // Offerings are always applied immediately — owner edits their own data directly,
        // no moderation gate needed. Both paths use the same audit history type.
        JG_Map_Database::add_admin_edit_history($point_id, $user_id, $old_values, $new_values);
        JG_Map_Activity_Log::log_user_action(
            'edit_offerings',
            'point',
            $point_id,
            sprintf('Zaktualizowano ofertę miejsca: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Oferta zapisana', 'pending' => false));
    }

    /**
     * Send a contact message to a place's email address.
     * The email stored in the DB is never sent to the browser.
     * Rate-limited to 5 messages per IP per hour via transient.
     */
    public function contact_place() {
        if (!check_ajax_referer('jg_map_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy token bezpieczeństwa.'));
        }

        $point_id     = intval($_POST['point_id'] ?? 0);
        $sender_name  = sanitize_text_field(wp_unslash($_POST['sender_name']  ?? ''));
        $sender_email = sanitize_email(wp_unslash($_POST['sender_email'] ?? ''));
        $message      = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        if (!$point_id || !$sender_name || !$sender_email || !$message) {
            wp_send_json_error(array('message' => 'Uzupełnij wszystkie pola.'));
        }

        if (!is_email($sender_email)) {
            wp_send_json_error(array('message' => 'Podaj prawidłowy adres e-mail.'));
        }

        // Rate limit: max 5 messages per IP per hour
        $ip_hash      = 'jg_cp_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
        $sent_count   = (int) get_transient($ip_hash);
        if ($sent_count >= 5) {
            wp_send_json_error(array('message' => 'Zbyt wiele wiadomości. Spróbuj za godzinę.'));
        }

        // Fetch place email from DB — never returned to the client
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT title, email FROM $points_table WHERE id = %d AND status = 'publish' AND email IS NOT NULL AND email != ''",
            $point_id
        ));

        if (!$row || !is_email($row->email)) {
            wp_send_json_error(array('message' => 'To miejsce nie przyjmuje wiadomości e-mail.'));
        }

        $place_title = $row->title;
        $to          = $row->email;
        $subject     = '[Jeleniogórzanie to my] Wiadomość od: ' . $sender_name;
        $body        = "Otrzymałeś wiadomość przez portal Jeleniogórzanie to my.\n\n" .
                       "Nadawca: {$sender_name}\n" .
                       "E-mail nadawcy: {$sender_email}\n\n" .
                       "Treść:\n{$message}\n\n" .
                       "---\n" .
                       "Wiadomość dotyczy miejsca: {$place_title}\n" .
                       "Portal: " . home_url('/');

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $sender_name . ' <' . $sender_email . '>',
        );

        $sent = wp_mail($to, $subject, $body, $headers);

        if (!$sent) {
            wp_send_json_error(array('message' => 'Nie udało się wysłać wiadomości. Spróbuj ponownie.'));
        }

        // Increment rate-limit counter (TTL = 1 hour)
        set_transient($ip_hash, $sent_count + 1, HOUR_IN_SECONDS);

        wp_send_json_success(array('message' => 'Wiadomość wysłana.'));
    }

}
