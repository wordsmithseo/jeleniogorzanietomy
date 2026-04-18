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
            'upload_photo_existing' => array('label' => '📸 Dodaj zdjęcie do istniejącego miejsca', 'desc' => 'Zatwierdzone edycje z nowymi zdjęciami do cudzych miejsc', 'needs_cat' => false),
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

        // Run DB migration immediately (plugins_loaded has already fired when
        // get_instance() is called via init_components, so we can't hook it).
        $this->maybe_upgrade_db();

        // Auto-deactivate expired challenges on every init
        add_action('init', array($this, 'auto_deactivate_expired'));
    }

    /**
     * Deactivate challenges whose end_date has passed. Called on 'init'.
     */
    public function auto_deactivate_expired() {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';
        $wpdb->query($wpdb->prepare(
            "UPDATE `$table` SET is_active = 0 WHERE is_active = 1 AND end_date < %s",
            current_time('mysql')
        ));
    }

    /**
     * Add ach_* columns to the challenges table if they don't exist yet.
     * Uses explicit ALTER TABLE — more reliable than dbDelta for existing tables.
     * NOTE: TEXT columns cannot have DEFAULT values in MySQL < 8.0, so ach_desc
     *       is defined without a DEFAULT clause.
     */
    public function maybe_upgrade_db() {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';

        $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        if (empty($columns)) return; // table doesn't exist yet; create_table() will handle it

        $to_add = array(
            'ach_name'   => "varchar(255) DEFAULT NULL",
            'ach_icon'   => "varchar(50) DEFAULT NULL",
            'ach_rarity' => "varchar(20) NOT NULL DEFAULT 'rare'",
            'ach_desc'   => "text",   // no DEFAULT — TEXT can't have one in MySQL < 8.0
        );
        foreach ($to_add as $col => $def) {
            if (!in_array($col, $columns, true)) {
                $wpdb->query("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        }
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
            ach_name varchar(255) DEFAULT NULL,
            ach_desc text DEFAULT NULL,
            ach_icon varchar(50) DEFAULT NULL,
            ach_rarity varchar(20) DEFAULT 'rare',
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
        if (!is_user_logged_in()) return null;

        global $wpdb;
        $table   = $wpdb->prefix . 'jg_map_challenges';
        $now     = current_time('mysql');
        $user_id = get_current_user_id();

        $challenge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$table` WHERE is_active = 1 AND start_date <= %s AND end_date >= %s ORDER BY start_date DESC LIMIT 1",
            $now, $now
        ));

        if (!$challenge) {
            return null;
        }

        $progress = self::calculate_progress($challenge, $user_id);

        if ($progress >= intval($challenge->target_count) && !empty($challenge->ach_name ?? '')) {
            self::maybe_award_challenge_achievement($challenge, $user_id);
        }

        $valid_rarities = array('common', 'uncommon', 'rare', 'epic', 'legendary');
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
            'ach_name'       => ($challenge->ach_name ?? '') ?: null,
            'ach_icon'       => ($challenge->ach_icon ?? '') ?: null,
            'ach_rarity'     => in_array($challenge->ach_rarity ?? '', $valid_rarities) ? $challenge->ach_rarity : 'rare',
        );
    }

    /**
     * Calculate current progress for a challenge based on its condition_type.
     */
    private static function calculate_progress($challenge, $user_id = 0) {
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
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND author_id=%d AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'place_category':
                if (empty($cat)) return 0;
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND author_id=%d AND category=%s AND approved_at BETWEEN %s AND %s",
                    $user_id, $cat, $start, $end
                ));

            case 'place_photo':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND author_id=%d AND images IS NOT NULL AND images NOT IN ('','[]') AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'place_hours':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND author_id=%d AND opening_hours IS NOT NULL AND opening_hours != '' AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'place_menu':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT p.id) FROM `$pts` p INNER JOIN `$menu` m ON m.point_id = p.id WHERE p.status='approved' AND p.type='miejsce' AND p.author_id=%d AND p.approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'place_desc':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND author_id=%d AND content IS NOT NULL AND content != '' AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'place_phone':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND author_id=%d AND phone IS NOT NULL AND phone != '' AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'place_website':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND author_id=%d AND website IS NOT NULL AND website != '' AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'place_full':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='miejsce' AND author_id=%d AND content IS NOT NULL AND content != '' AND images IS NOT NULL AND images NOT IN ('','[]') AND opening_hours IS NOT NULL AND opening_hours != '' AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'curiosity_any':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='ciekawostka' AND author_id=%d AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'curiosity_category':
                if (empty($cat)) return 0;
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='ciekawostka' AND author_id=%d AND category=%s AND approved_at BETWEEN %s AND %s",
                    $user_id, $cat, $start, $end
                ));

            case 'curiosity_photo':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='ciekawostka' AND author_id=%d AND images IS NOT NULL AND images NOT IN ('','[]') AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'issue_any':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='zgloszenie' AND author_id=%d AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'issue_category':
                if (empty($cat)) return 0;
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND type='zgloszenie' AND author_id=%d AND category=%s AND approved_at BETWEEN %s AND %s",
                    $user_id, $cat, $start, $end
                ));

            case 'any_point':
                $sql    = "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND author_id=%d AND approved_at BETWEEN %s AND %s";
                $params = array($user_id, $start, $end);
                if (!empty($cat)) { $sql .= " AND category = %s"; $params[] = $cat; }
                return (int) $wpdb->get_var($wpdb->prepare($sql, $params));

            case 'any_with_photo':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$pts` WHERE status='approved' AND author_id=%d AND images IS NOT NULL AND images NOT IN ('','[]') AND approved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            // ── Interaction conditions ────────────────────────────────────────
            case 'cast_vote':
                // COUNT(DISTINCT point_id) prevents vote→un-vote→re-vote on the
                // same point from inflating the counter.
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT point_id) FROM `$votes` WHERE user_id=%d AND created_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'cast_report':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT point_id) FROM `$rpts` WHERE user_id=%d AND created_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'edit_approved':
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT point_id) FROM `$hist` WHERE user_id=%d AND status='approved' AND resolved_at BETWEEN %s AND %s",
                    $user_id, $start, $end
                ));

            case 'upload_photo_existing': {
                // Only count places that CURRENTLY have photos — joining with the
                // points table ensures upload→delete→re-upload stays at 1 and a
                // final delete drops the count back to 0.
                $like = '%' . $wpdb->esc_like('"images":') . '%';
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT h.point_id) FROM `$hist` h
                     INNER JOIN `$pts` p ON p.id = h.point_id
                     WHERE h.user_id = %d
                     AND h.status = 'approved'
                     AND h.resolved_at BETWEEN %s AND %s
                     AND h.new_values LIKE %s
                     AND p.images IS NOT NULL AND p.images NOT IN ('', '[]')",
                    $user_id, $start, $end, $like
                ));
            }

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

        $is_active  = isset($data['is_active']) ? intval($data['is_active']) : 1;
        $start_date = sanitize_text_field($data['start_date']);
        $end_date   = sanitize_text_field($data['end_date']);

        // Trying to activate a challenge with a past end_date — require new dates
        if ($is_active === 1 && strtotime($end_date) <= strtotime(current_time('mysql'))) {
            return 'Nie można aktywować wyzwania z datą zakończenia w przeszłości. Ustaw nowe ramy czasowe.';
        }

        // Base columns always available
        $row = array(
            'title'          => sanitize_text_field($data['title']),
            'description'    => sanitize_textarea_field($data['description'] ?? ''),
            'condition_type' => $ctype,
            'category'       => !empty($data['category']) ? sanitize_text_field($data['category']) : null,
            'target_count'   => max(1, intval($data['target_count'])),
            'xp_reward'      => max(0, intval($data['xp_reward'])),
            'start_date'     => $start_date,
            'end_date'       => $end_date,
            'is_active'      => $is_active,
        );

        // Only include ach_* columns if they actually exist in the DB (migration guard)
        $existing_cols = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
        if (in_array('ach_name', $existing_cols, true)) {
            $valid_rarities = array('common', 'uncommon', 'rare', 'epic', 'legendary');
            $row['ach_name']   = !empty($data['ach_name']) ? sanitize_text_field($data['ach_name']) : null;
            $row['ach_desc']   = !empty($data['ach_desc']) ? sanitize_textarea_field($data['ach_desc']) : null;
            $row['ach_icon']   = !empty($data['ach_icon']) ? sanitize_text_field($data['ach_icon']) : null;
            $row['ach_rarity'] = isset($data['ach_rarity']) && in_array($data['ach_rarity'], $valid_rarities)
                ? $data['ach_rarity'] : 'rare';
        }

        if (!empty($data['id'])) {
            $wpdb->update($table, $row, array('id' => intval($data['id'])));
            $id = intval($data['id']);
        } else {
            $wpdb->insert($table, $row);
            $id = $wpdb->insert_id;
        }

        if (!$id) {
            return false;
        }

        self::upsert_challenge_achievement($id, $data);
        return $id;
    }

    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_challenges';
        $wpdb->delete($table, array('id' => intval($id)));
        // NOTE: the achievement definition in wp_jg_map_achievements is intentionally
        // kept — users who already earned it must continue to see it in their profile.
    }

    /**
     * Create or update the achievement record for a challenge.
     * Called every time a challenge is saved.
     */
    private static function upsert_challenge_achievement($challenge_id, $data) {
        global $wpdb;
        $ach_table = $wpdb->prefix . 'jg_map_achievements';
        $slug      = 'challenge_' . intval($challenge_id);

        if (empty($data['ach_name'])) {
            // Achievement removed from challenge — only delete the definition if
            // no user has earned it yet; otherwise keep it so earners retain it.
            $ach_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `$ach_table` WHERE slug = %s", $slug
            ));
            if ($ach_id) {
                $ua_table  = $wpdb->prefix . 'jg_map_user_achievements';
                $has_earners = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `$ua_table` WHERE achievement_id = %d", intval($ach_id)
                ));
                if (!$has_earners) {
                    $wpdb->delete($ach_table, array('id' => intval($ach_id)));
                }
            }
            return;
        }

        $valid_rarities = array('common', 'uncommon', 'rare', 'epic', 'legendary');
        $rarity = isset($data['ach_rarity']) && in_array($data['ach_rarity'], $valid_rarities)
            ? $data['ach_rarity'] : 'rare';

        $ach_row = array(
            'slug'            => $slug,
            'name'            => sanitize_text_field($data['ach_name']),
            'description'     => sanitize_textarea_field($data['ach_desc'] ?? ''),
            'icon'            => sanitize_text_field($data['ach_icon'] ?? '🏆'),
            'rarity'          => $rarity,
            'condition_type'  => 'challenge_completed',
            'condition_value' => intval($challenge_id),
            'sort_order'      => 1000,
        );

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `$ach_table` WHERE slug = %s", $slug
        ));

        if ($existing_id) {
            $wpdb->update($ach_table, $ach_row, array('id' => intval($existing_id)));
        } else {
            $wpdb->insert($ach_table, $ach_row);
        }
    }

    /**
     * Award the challenge-specific achievement to a user if not already held.
     * Called when progress reaches the target.
     */
    private static function maybe_award_challenge_achievement($challenge, $user_id) {
        if (empty($challenge->ach_name ?? '')) return;

        global $wpdb;
        $ach_table   = $wpdb->prefix . 'jg_map_achievements';
        $ua_table    = $wpdb->prefix . 'jg_map_user_achievements';
        $notif_table = $wpdb->prefix . 'jg_map_level_notifications';
        $slug        = 'challenge_' . intval($challenge->id);

        $ach_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `$ach_table` WHERE slug = %s", $slug
        ));

        // Record missing — challenge was saved before upsert logic existed; create it now
        if (!$ach_id) {
            self::upsert_challenge_achievement(intval($challenge->id), array(
                'ach_name'   => $challenge->ach_name ?? '',
                'ach_desc'   => $challenge->ach_desc ?? '',
                'ach_icon'   => $challenge->ach_icon ?? '🏆',
                'ach_rarity' => $challenge->ach_rarity ?? 'rare',
            ));
            $ach_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `$ach_table` WHERE slug = %s", $slug
            ));
            if (!$ach_id) return;
        }

        $already = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `$ua_table` WHERE user_id = %d AND achievement_id = %d",
            $user_id, $ach_id
        ));
        if ($already) return;

        $inserted = $wpdb->insert($ua_table, array(
            'user_id'        => $user_id,
            'achievement_id' => $ach_id,
            'notified'       => 0,
        ));

        if ($inserted) {
            $wpdb->insert($notif_table, array(
                'user_id' => $user_id,
                'type'    => 'achievement',
                'data'    => wp_json_encode(array(
                    'achievement_id' => $ach_id,
                    'name'           => $challenge->ach_name ?? '',
                    'description'    => ($challenge->ach_desc ?? '') ?: '',
                    'icon'           => ($challenge->ach_icon ?? '') ?: '🏆',
                    'rarity'         => ($challenge->ach_rarity ?? '') ?: 'rare',
                )),
            ));
        }
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
        $result = self::save($_POST);
        if ($result === false) {
            wp_send_json_error('Brakuje wymaganych pól (tytuł, daty).');
        }
        if (is_string($result)) {
            // Validation error message returned by save()
            wp_send_json_error($result);
        }
        wp_send_json_success(array('id' => $result));
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
