<?php
/**
 * Weekly Challenges System
 */

if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Challenges {

    private static $instance = null;

    /**
     * All supported condition types: key => [label, description, needs_category]
     */
    public static function get_condition_types() {
        return array(
            // --- DODAWANIE PUNKTÓW ---
            'place_any'          => array('label' => '📍 Dodaj dowolne Miejsce',                  'desc' => 'Zatwierdzone punkty typu Miejsce',            'needs_cat' => false),
            'place_category'     => array('label' => '📍 Dodaj Miejsce z konkretnej kategorii',   'desc' => 'Miejsce o wybranej kategorii',                 'needs_cat' => true),
            'place_photo'        => array('label' => '📸 Dodaj Miejsce ze zdjęciem',              'desc' => 'Miejsce z min. 1 zdjęciem',                   'needs_cat' => false),
            'place_hours'        => array('label' => '🕐 Dodaj Miejsce z godzinami otwarcia',     'desc' => 'Miejsce z uzupełnionymi godzinami',           'needs_cat' => false),
            'place_menu'         => array('label' => '🍽️ Dodaj Miejsce z kartą menu',             'desc' => 'Miejsce z co najmniej jedną pozycją w menu',  'needs_cat' => false),
            'place_desc'         => array('label' => '📝 Dodaj Miejsce z opisem',                 'desc' => 'Miejsce z niepustym opisem',                  'needs_cat' => false),
            'place_phone'        => array('label' => '📞 Dodaj Miejsce z numerem telefonu',       'desc' => 'Miejsce z uzupełnionym numerem kontaktowym',  'needs_cat' => false),
            'place_website'      => array('label' => '🌐 Dodaj Miejsce ze stroną WWW',            'desc' => 'Miejsce z podanym adresem strony',            'needs_cat' => false),
            'place_full'         => array('label' => '⭐ Dodaj kompletne Miejsce',               'desc' => 'Miejsce z opisem, zdjęciem i godzinami',      'needs_cat' => false),
            // --- CIEKAWOSTKI ---
            'curiosity_any'      => array('label' => '💡 Dodaj dowolną Ciekawostkę',             'desc' => 'Zatwierdzone punkty typu Ciekawostka',         'needs_cat' => false),
            'curiosity_category' => array('label' => '💡 Dodaj Ciekawostkę z kategorii',         'desc' => 'Ciekawostka z wybraną kategorią',              'needs_cat' => true),
            'curiosity_photo'    => array('label' => '📸 Dodaj Ciekawostkę ze zdjęciem',         'desc' => 'Ciekawostka z min. 1 zdjęciem',               'needs_cat' => false),
            // --- ZGŁOSZENIA ---
            'issue_any'          => array('label' => '⚠️ Dodaj dowolne Zgłoszenie',              'desc' => 'Zatwierdzone punkty typu Zgłoszenie',          'needs_cat' => false),
            'issue_category'     => array('label' => '⚠️ Dodaj Zgłoszenie z kategorii',         'desc' => 'Zgłoszenie o wybranej kategorii',              'needs_cat' => true),
            // --- WSZYSTKIE TYPY ---
            'any_point'          => array('label' => '🗺️ Dodaj dowolny punkt (wszystkie typy)',  'desc' => 'Miejsce, Ciekawostka lub Zgłoszenie',          'needs_cat' => false),
            'any_with_photo'     => array('label' => '📸 Dodaj dowolny punkt ze zdjęciem',       'desc' => 'Dowolny typ punktu z min. 1 zdjęciem',        'needs_cat' => false),
            // --- INTERAKCJE ---
            'cast_vote'          => array('label' => '⭐ Zagłosuj na punkty',                    'desc' => 'Oddane głosy w wybranym przedziale czasu',    'needs_cat' => false),
            'cast_report'        => array('label' => '🚩 Zgłoś problem z punktem',               'desc' => 'Wysłane raporty o problemach',                 'needs_cat' => false),
            'edit_approved'      => array('label' => '✏️ Edytuj i zatwierdź punkt',              'desc' => 'Zatwierdzone edycje istniejących punktów',    'needs_cat' => false),
        );
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_jg_get_active_challenge',        array($this, 'ajax_get_active_challenge'));
        add_action('wp_ajax_nopriv_jg_get_active_challenge', array($this, 'ajax_get_active_challenge'));

        add_action('wp_ajax_jg_admin_get_challenges',   array($this, 'ajax_get_all'));
        add_action('wp_ajax_jg_admin_save_challenge',   array($this, 'ajax_save'));
        add_action('wp_ajax_jg_admin_delete_challenge', array($this, 'ajax_delete'));
    }

    // =========================================================================
    // DATABASE
    // =========================================================================

    public static function create_table() {
        global $wpdb;
        $table           = $wpdb->prefix . 'jg_map_challenges';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            condition_type varchar(50) NOT NULL DEFAULT 'any_point',
            category varchar(100) DEFAULT NULL,
            target_count int(11) NOT NULL DEFAULT 10,
            xp_reward int(11) NOT NULL DEFAULT 100,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY end_date (end_date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // =========================================================================
    // DATA ACCESS
    // =========================================================================

    public static function get_active_with_progress() {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';
        $now   = current_time('mysql');

        $challenge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$table` WHERE is_active = 1 AND start_date <= %s AND end_date >= %s ORDER BY start_date DESC LIMIT 1",
            $now, $now
        ));

        if (!$challenge) {
            return null;
        }

        $progress = self::calculate_progress($challenge);

        return array(
            'id'             => (int) $challenge->id,
            'title'          => $challenge->title,
            'description'    => $challenge->description,
            'condition_type' => $challenge->condition_type,
            'category'       => $challenge->category,
            'target_count'   => (int) $challenge->target_count,
            'xp_reward'      => (int) $challenge->xp_reward,
            'progress'       => $progress,
            'end_date'       => $challenge->end_date,
        );
    }

    /**
     * Calculate current progress for a challenge based on its condition_type.
     */
    private static function calculate_progress($challenge) {
        global $wpdb;

        $pts    = $wpdb->prefix . 'jg_map_points';
        $votes  = $wpdb->prefix . 'jg_map_votes';
        $rpts   = $wpdb->prefix . 'jg_map_reports';
        $hist   = $wpdb->prefix . 'jg_map_history';
        $menu   = $wpdb->prefix . 'jg_map_menu_items';

        $start = $challenge->start_date;
        $end   = $challenge->end_date;
        $cat   = $challenge->category;
        $type  = $challenge->condition_type;

        switch ($type) {
            // ── Point-adding conditions ───────────────────────────────────────
            case 'place_any':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'place_category':
                if (empty($cat)) return 0;
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND category=%s AND approved_at BETWEEN %s AND %s",
                    $cat, $start, $end
                ));

            case 'place_photo':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND images IS NOT NULL AND images NOT IN ('','[]') AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'place_hours':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND opening_hours IS NOT NULL AND opening_hours != '' AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'place_menu':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT p.id) FROM `$pts` p INNER JOIN `$menu` m ON m.point_id = p.id WHERE p.status='approved' AND p.type='miejsce' AND p.approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'place_desc':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND content IS NOT NULL AND content != '' AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'place_phone':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND phone IS NOT NULL AND phone != '' AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'place_website':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND website IS NOT NULL AND website != '' AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'place_full':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND content IS NOT NULL AND content != '' AND images IS NOT NULL AND images NOT IN ('','[]') AND opening_hours IS NOT NULL AND opening_hours != '' AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'curiosity_any':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='ciekawostka' AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'curiosity_category':
                if (empty($cat)) return 0;
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='ciekawostka' AND category=%s AND approved_at BETWEEN %s AND %s",
                    $cat, $start, $end
                ));

            case 'curiosity_photo':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='ciekawostka' AND images IS NOT NULL AND images NOT IN ('','[]') AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'issue_any':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='zgloszenie' AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'issue_category':
                if (empty($cat)) return 0;
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='zgloszenie' AND category=%s AND approved_at BETWEEN %s AND %s",
                    $cat, $start, $end
                ));

            case 'any_point':
                $sql    = "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND approved_at BETWEEN %s AND %s";
                $params = array($start, $end);
                if (!empty($cat)) { $sql .= " AND category = %s"; $params[] = $cat; }
                return (int) $wpdb->get_var($wpdb->prepare($sql, $params));

            case 'any_with_photo':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND images IS NOT NULL AND images NOT IN ('','[]') AND approved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            // ── Interaction conditions ────────────────────────────────────────
            case 'cast_vote':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$votes` WHERE created_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'cast_report':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$rpts` WHERE created_at BETWEEN %s AND %s",
                    $start, $end
                ));

            case 'edit_approved':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$hist` WHERE status='approved' AND resolved_at BETWEEN %s AND %s",
                    $start, $end
                ));

            default:
                return 0;
        }
    }

    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';
        return $wpdb->get_results("SELECT * FROM `$table` ORDER BY created_at DESC");
    }

    public static function save($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';

        // Validate required fields
        if (empty($data['title']) || empty($data['start_date']) || empty($data['end_date'])) {
            return false;
        }

        $ctype = sanitize_text_field($data['condition_type'] ?? 'any_point');
        $valid_types = array_keys(self::get_condition_types());
        if (!in_array($ctype, $valid_types, true)) {
            $ctype = 'any_point';
        }

        $row = array(
            'title'          => sanitize_text_field($data['title']),
            'description'    => sanitize_textarea_field($data['description'] ?? ''),
            'condition_type' => $ctype,
            'category'       => !empty($data['category']) ? sanitize_text_field($data['category']) : null,
            'target_count'   => max(1, intval($data['target_count'])),
            'xp_reward'      => max(0, intval($data['xp_reward'])),
            'start_date'     => sanitize_text_field($data['start_date']),
            'end_date'       => sanitize_text_field($data['end_date']),
            'is_active'      => isset($data['is_active']) ? intval($data['is_active']) : 1,
        );

        if (!empty($data['id'])) {
            $wpdb->update($table, $row, array('id' => intval($data['id'])));
            return intval($data['id']);
        }

        $wpdb->insert($table, $row);
        return $wpdb->insert_id;
    }

    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';
        $wpdb->delete($table, array('id' => intval($id)));
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    public function ajax_get_active_challenge() {
        check_ajax_referer('jg_map_nonce', '_ajax_nonce');
        wp_send_json_success(self::get_active_with_progress());
    }

    public function ajax_get_all() {
        check_ajax_referer('jg_map_admin_nonce', '_ajax_nonce');
        if (!current_user_can('jg_map_manage')) {
            wp_send_json_error('Access denied', 403);
        }
        wp_send_json_success(self::get_all());
    }

    public function ajax_save() {
        check_ajax_referer('jg_map_admin_nonce', '_ajax_nonce');
        if (!current_user_can('jg_map_manage')) {
            wp_send_json_error('Access denied', 403);
        }
        $id = self::save($_POST);
        if ($id === false) {
            wp_send_json_error('Brakuje wymaganych pól (tytuł, daty).');
        }
        wp_send_json_success(array('id' => $id));
    }

    public function ajax_delete() {
        check_ajax_referer('jg_map_admin_nonce', '_ajax_nonce');
        if (!current_user_can('jg_map_manage')) {
            wp_send_json_error('Access denied', 403);
        }
        self::delete(intval($_POST['challenge_id'] ?? 0));
        wp_send_json_success();
    }
}
