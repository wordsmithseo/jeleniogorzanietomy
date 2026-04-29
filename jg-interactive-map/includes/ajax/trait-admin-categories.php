<?php
/**
 * Trait JG_Ajax_AdminCategories
 * CRUD for report categories/reasons, place categories, curiosity categories, tags, SEO settings.
 */
trait JG_Ajax_AdminCategories {

    /**
     * Save new report category
     */
    public function save_report_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_report_reasons_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');

        if (empty($key) || empty($label)) {
            wp_send_json_error('Klucz i nazwa są wymagane');
            return;
        }

        $categories = self::get_category_groups();

        if (isset($categories[$key])) {
            wp_send_json_error('Kategoria o tym kluczu już istnieje');
            return;
        }

        $categories[$key] = $label;
        update_option('jg_map_report_categories', $categories);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'add_report_category',
                'settings',
                0,
                sprintf('Dodano kategorię zgłoszeń: %s (%s)', $label, $key)
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została dodana'));
    }

    /**
     * Update existing report category
     */
    public function update_report_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_report_reasons_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');

        if (empty($key) || empty($label)) {
            wp_send_json_error('Klucz i nazwa są wymagane');
            return;
        }

        $categories = self::get_category_groups();

        if (!isset($categories[$key])) {
            wp_send_json_error('Kategoria nie istnieje');
            return;
        }

        $old_label = $categories[$key];
        $categories[$key] = $label;
        update_option('jg_map_report_categories', $categories);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'update_report_category',
                'settings',
                0,
                sprintf('Zaktualizowano kategorię zgłoszeń: %s -> %s', $old_label, $label)
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została zaktualizowana'));
    }

    /**
     * Delete report category
     */
    public function delete_report_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_report_reasons_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');

        if (empty($key)) {
            wp_send_json_error('Klucz kategorii jest wymagany');
            return;
        }

        $categories = self::get_category_groups();

        if (!isset($categories[$key])) {
            wp_send_json_error('Kategoria nie istnieje');
            return;
        }

        $deleted_label = $categories[$key];
        unset($categories[$key]);
        update_option('jg_map_report_categories', $categories);

        // Unlink reasons from this category
        $reasons = self::get_report_categories();
        $unlinked = 0;
        foreach ($reasons as $rkey => $reason) {
            if (isset($reason['group']) && $reason['group'] === $key) {
                $reasons[$rkey]['group'] = '';
                $unlinked++;
            }
        }
        if ($unlinked > 0) {
            update_option('jg_map_report_reasons', $reasons);
        }

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'delete_report_category',
                'settings',
                0,
                sprintf('Usunięto kategorię zgłoszeń: %s (odłączono %d powodów)', $deleted_label, $unlinked)
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została usunięta'));
    }

    /**
     * Save new report reason
     */
    public function save_report_reason() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_report_reasons_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');
        $group = sanitize_key($_POST['group'] ?? '');
        $icon = sanitize_text_field($_POST['icon'] ?? '📌');

        if (empty($key) || empty($label)) {
            wp_send_json_error('Klucz i nazwa są wymagane');
            return;
        }

        $reasons = self::get_report_categories();

        if (isset($reasons[$key])) {
            wp_send_json_error('Powód o tym kluczu już istnieje');
            return;
        }

        $reasons[$key] = array(
            'label' => $label,
            'group' => $group,
            'icon' => $icon
        );
        update_option('jg_map_report_reasons', $reasons);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'add_report_reason',
                'settings',
                0,
                sprintf('Dodano powód zgłoszenia: %s %s (%s)', $icon, $label, $key)
            );
        }

        wp_send_json_success(array('message' => 'Powód został dodany'));
    }

    /**
     * Update existing report reason
     */
    public function update_report_reason() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_report_reasons_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');
        $group = sanitize_key($_POST['group'] ?? '');
        $icon = sanitize_text_field($_POST['icon'] ?? '📌');

        if (empty($key) || empty($label)) {
            wp_send_json_error('Klucz i nazwa są wymagane');
            return;
        }

        $reasons = self::get_report_categories();

        if (!isset($reasons[$key])) {
            wp_send_json_error('Powód nie istnieje');
            return;
        }

        $old_label = $reasons[$key]['label'];
        $reasons[$key] = array(
            'label' => $label,
            'group' => $group,
            'icon' => $icon
        );
        update_option('jg_map_report_reasons', $reasons);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'update_report_reason',
                'settings',
                0,
                sprintf('Zaktualizowano powód zgłoszenia: %s -> %s %s', $old_label, $icon, $label)
            );
        }

        wp_send_json_success(array('message' => 'Powód został zaktualizowany'));
    }

    /**
     * Delete report reason
     */
    public function delete_report_reason() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_report_reasons_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');

        if (empty($key)) {
            wp_send_json_error('Klucz powodu jest wymagany');
            return;
        }

        $reasons = self::get_report_categories();

        if (!isset($reasons[$key])) {
            wp_send_json_error('Powód nie istnieje');
            return;
        }

        $deleted_reason = $reasons[$key];
        unset($reasons[$key]);
        update_option('jg_map_report_reasons', $reasons);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'delete_report_reason',
                'settings',
                0,
                sprintf('Usunięto powód zgłoszenia: %s %s', $deleted_reason['icon'] ?? '📌', $deleted_reason['label'])
            );
        }

        wp_send_json_success(array('message' => 'Powód został usunięty'));
    }

    /**
     * Suggest icon for reason label
     */
    public function suggest_reason_icon() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_report_reasons_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $label = sanitize_text_field($_POST['label'] ?? '');

        if (empty($label)) {
            wp_send_json_success(array('icon' => '📌'));
            return;
        }

        $icon = self::suggest_icon_for_label($label);

        wp_send_json_success(array('icon' => $icon));
    }

    /**
     * Save new place category
     */
    public function save_place_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_place_categories_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key              = sanitize_key($_POST['key'] ?? '');
        $label            = sanitize_text_field($_POST['label'] ?? '');
        $icon             = sanitize_text_field($_POST['icon'] ?? '📍');
        $has_menu         = !empty($_POST['has_menu']) && $_POST['has_menu'] === '1';
        $has_price_range  = !empty($_POST['has_price_range']) && $_POST['has_price_range'] === '1';
        $serves_cuisine   = !empty($_POST['serves_cuisine']) && $_POST['serves_cuisine'] === '1';
        $show_promo       = !empty($_POST['show_promo']) && $_POST['show_promo'] === '1';
        $offerings_label  = sanitize_text_field(substr($_POST['offerings_label'] ?? '', 0, 50));

        if (empty($key) || empty($label)) {
            wp_send_json_error('Klucz i nazwa są wymagane');
            return;
        }

        $categories = self::get_place_categories();

        if (isset($categories[$key])) {
            wp_send_json_error('Kategoria o tym kluczu już istnieje');
            return;
        }

        $categories[$key] = array(
            'label'           => $label,
            'icon'            => $icon,
            'has_menu'        => $has_menu,
            'has_price_range' => $has_price_range,
            'serves_cuisine'  => $serves_cuisine,
            'show_promo'      => $show_promo,
            'offerings_label' => $offerings_label,
        );
        update_option('jg_map_place_categories', $categories);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'add_place_category',
                'settings',
                0,
                sprintf('Dodano kategorię miejsc: %s %s (%s)', $icon, $label, $key)
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została dodana'));
    }

    /**
     * Update existing place category
     */
    public function update_place_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_place_categories_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key              = sanitize_key($_POST['key'] ?? '');
        $label            = sanitize_text_field($_POST['label'] ?? '');
        $icon             = sanitize_text_field($_POST['icon'] ?? '📍');
        $has_menu         = !empty($_POST['has_menu']) && $_POST['has_menu'] === '1';
        $has_price_range  = !empty($_POST['has_price_range']) && $_POST['has_price_range'] === '1';
        $serves_cuisine   = !empty($_POST['serves_cuisine']) && $_POST['serves_cuisine'] === '1';
        $show_promo       = !empty($_POST['show_promo']) && $_POST['show_promo'] === '1';
        $offerings_label  = sanitize_text_field(substr($_POST['offerings_label'] ?? '', 0, 50));

        if (empty($key) || empty($label)) {
            wp_send_json_error('Klucz i nazwa są wymagane');
            return;
        }

        $categories = self::get_place_categories();

        if (!isset($categories[$key])) {
            wp_send_json_error('Kategoria nie istnieje');
            return;
        }

        $old_label = $categories[$key]['label'];
        // Preserve existing fields (e.g. schema_type), only update editable ones
        $categories[$key] = array_merge($categories[$key], array(
            'label'           => $label,
            'icon'            => $icon,
            'has_menu'        => $has_menu,
            'has_price_range' => $has_price_range,
            'serves_cuisine'  => $serves_cuisine,
            'show_promo'      => $show_promo,
            'offerings_label' => $offerings_label,
        ));
        update_option('jg_map_place_categories', $categories);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'update_place_category',
                'settings',
                0,
                sprintf('Zaktualizowano kategorię miejsc: %s -> %s %s', $old_label, $icon, $label)
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została zaktualizowana'));
    }

    /**
     * Delete place category
     */
    public function delete_place_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_place_categories_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');

        if (empty($key)) {
            wp_send_json_error('Klucz kategorii jest wymagany');
            return;
        }

        $categories = self::get_place_categories();

        if (!isset($categories[$key])) {
            wp_send_json_error('Kategoria nie istnieje');
            return;
        }

        $deleted_category = $categories[$key];
        unset($categories[$key]);
        update_option('jg_map_place_categories', $categories);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'delete_place_category',
                'settings',
                0,
                sprintf('Usunięto kategorię miejsc: %s %s', $deleted_category['icon'] ?? '📍', $deleted_category['label'])
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została usunięta'));
    }

    /**
     * Save new curiosity category
     */
    public function save_curiosity_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_curiosity_categories_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');
        $icon = sanitize_text_field($_POST['icon'] ?? '📖');

        if (empty($key) || empty($label)) {
            wp_send_json_error('Klucz i nazwa są wymagane');
            return;
        }

        $categories = self::get_curiosity_categories();

        if (isset($categories[$key])) {
            wp_send_json_error('Kategoria o tym kluczu już istnieje');
            return;
        }

        $categories[$key] = array(
            'label' => $label,
            'icon' => $icon
        );
        update_option('jg_map_curiosity_categories', $categories);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'add_curiosity_category',
                'settings',
                0,
                sprintf('Dodano kategorię ciekawostek: %s %s (%s)', $icon, $label, $key)
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została dodana'));
    }

    /**
     * Update existing curiosity category
     */
    public function update_curiosity_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_curiosity_categories_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');
        $icon = sanitize_text_field($_POST['icon'] ?? '📖');

        if (empty($key) || empty($label)) {
            wp_send_json_error('Klucz i nazwa są wymagane');
            return;
        }

        $categories = self::get_curiosity_categories();

        if (!isset($categories[$key])) {
            wp_send_json_error('Kategoria nie istnieje');
            return;
        }

        $old_label = $categories[$key]['label'];
        $categories[$key] = array(
            'label' => $label,
            'icon' => $icon
        );
        update_option('jg_map_curiosity_categories', $categories);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'update_curiosity_category',
                'settings',
                0,
                sprintf('Zaktualizowano kategorię ciekawostek: %s -> %s %s', $old_label, $icon, $label)
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została zaktualizowana'));
    }

    /**
     * Delete curiosity category
     */
    public function delete_curiosity_category() {
        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'jg_map_curiosity_categories_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            return;
        }

        $key = sanitize_key($_POST['key'] ?? '');

        if (empty($key)) {
            wp_send_json_error('Klucz kategorii jest wymagany');
            return;
        }

        $categories = self::get_curiosity_categories();

        if (!isset($categories[$key])) {
            wp_send_json_error('Kategoria nie istnieje');
            return;
        }

        $deleted_category = $categories[$key];
        unset($categories[$key]);
        update_option('jg_map_curiosity_categories', $categories);

        // Log activity
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                'delete_curiosity_category',
                'settings',
                0,
                sprintf('Usunięto kategorię ciekawostek: %s %s', $deleted_category['icon'] ?? '📖', $deleted_category['label'])
            );
        }

        wp_send_json_success(array('message' => 'Kategoria została usunięta'));
    }

    /**
     * Get tags with pagination and search for admin management
     */
    public function admin_get_tags_paginated() {
        $this->verify_nonce();
        $this->check_admin();

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, min(100, intval($_POST['per_page']))) : 20;

        $result = JG_Map_Database::get_tags_paginated($search, $page, $per_page);
        wp_send_json_success($result);
    }

    /**
     * Rename a tag (admin only)
     */
    public function admin_rename_tag() {
        $this->verify_nonce();
        $this->check_admin();

        $old_name = isset($_POST['old_name']) ? sanitize_text_field(wp_unslash($_POST['old_name'])) : '';
        $new_name = isset($_POST['new_name']) ? sanitize_text_field(wp_unslash($_POST['new_name'])) : '';

        if (empty($old_name) || empty($new_name)) {
            wp_send_json_error(array('message' => 'Nazwa tagu nie może być pusta'));
            return;
        }

        if (mb_strlen($new_name) > 50) {
            wp_send_json_error(array('message' => 'Nazwa tagu jest zbyt długa (max 50 znaków)'));
            return;
        }

        $updated = JG_Map_Database::rename_tag($old_name, $new_name);

        JG_Map_Activity_Log::log(
            'rename_tag',
            'tag',
            0,
            sprintf('Zmieniono tag "%s" na "%s" (%d miejsc zaktualizowanych)', $old_name, $new_name, $updated)
        );

        wp_send_json_success(array(
            'message' => sprintf('Tag zmieniony. Zaktualizowano %d miejsc.', $updated),
            'updated' => $updated,
        ));
    }

    /**
     * Delete a tag from all points (admin only)
     */
    public function admin_delete_tag() {
        $this->verify_nonce();
        $this->check_admin();

        $tag_name = isset($_POST['tag_name']) ? sanitize_text_field(wp_unslash($_POST['tag_name'])) : '';

        if (empty($tag_name)) {
            wp_send_json_error(array('message' => 'Nazwa tagu nie może być pusta'));
            return;
        }

        $updated = JG_Map_Database::delete_tag($tag_name);

        JG_Map_Activity_Log::log(
            'delete_tag',
            'tag',
            0,
            sprintf('Usunięto tag "%s" z %d miejsc', $tag_name, $updated)
        );

        wp_send_json_success(array(
            'message' => sprintf('Tag usunięty z %d miejsc.', $updated),
            'updated' => $updated,
        ));
    }

    /**
     * Get all tag names for search suggestions (admin only)
     */
    public function admin_get_tag_suggestions() {
        $this->verify_nonce();
        $this->check_admin();

        $tags = JG_Map_Database::get_all_tag_names();
        wp_send_json_success($tags);
    }

    /**
     * Save SEO settings (canonical URL + noindex) for a map pin — admin only
     */
    public function admin_save_seo() {
        check_ajax_referer('jg_admin_save_seo', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $point_id = intval($_POST['point_id'] ?? 0);
        if (!$point_id) {
            wp_send_json_error('Brak ID');
        }

        $canonical = esc_url_raw(trim($_POST['seo_canonical'] ?? ''));
        $noindex   = intval($_POST['seo_noindex'] ?? 0) ? 1 : 0;

        JG_Map_Database::update_point($point_id, array(
            'seo_canonical' => $canonical ?: null,
            'seo_noindex'   => $noindex,
        ));

        wp_send_json_success();
    }

}
