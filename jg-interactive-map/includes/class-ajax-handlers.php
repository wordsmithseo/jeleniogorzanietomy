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
     * Default report categories configuration
     * Used as fallback if no custom categories are defined
     */
    private static function get_default_report_categories() {
        return array(
            // Zgłoszenie usterek infrastruktury
            'dziura_w_jezdni' => array('label' => 'Dziura w jezdni', 'group' => 'infrastructure', 'icon' => '🕳️'),
            'uszkodzone_chodniki' => array('label' => 'Uszkodzone chodniki', 'group' => 'infrastructure', 'icon' => '🚶'),
            'znaki_drogowe' => array('label' => 'Brakujące lub zniszczone znaki drogowe', 'group' => 'infrastructure', 'icon' => '🚸'),
            'oswietlenie' => array('label' => 'Awarie oświetlenia ulicznego', 'group' => 'infrastructure', 'icon' => '💡'),

            // Porządek i bezpieczeństwo
            'dzikie_wysypisko' => array('label' => 'Dzikie wysypisko śmieci', 'group' => 'safety', 'icon' => '🗑️'),
            'przepelniony_kosz' => array('label' => 'Przepełniony kosz na śmieci', 'group' => 'safety', 'icon' => '♻️'),
            'graffiti' => array('label' => 'Graffiti', 'group' => 'safety', 'icon' => '🎨'),
            'sliski_chodnik' => array('label' => 'Śliski chodnik', 'group' => 'safety', 'icon' => '⚠️'),

            // Zieleń i estetyka miasta
            'nasadzenie_drzew' => array('label' => 'Potrzeba nasadzenia drzew', 'group' => 'greenery', 'icon' => '🌳'),
            'nieprzycięta_gałąź' => array('label' => 'Nieprzycięta gałąź zagrażająca niebezpieczeństwu', 'group' => 'greenery', 'icon' => '🌿'),

            // Transport i komunikacja
            'brak_przejscia' => array('label' => 'Brak przejścia dla pieszych', 'group' => 'transport', 'icon' => '🚦'),
            'przystanek_autobusowy' => array('label' => 'Potrzeba przystanku autobusowego', 'group' => 'transport', 'icon' => '🚏'),
            'organizacja_ruchu' => array('label' => 'Problem z organizacją ruchu', 'group' => 'transport', 'icon' => '🚗'),
            'korki' => array('label' => 'Powtarzające się korki', 'group' => 'transport', 'icon' => '🚙'),

            // Inicjatywy społeczne i rozwojowe
            'mala_infrastruktura' => array('label' => 'Propozycja nowych obiektów małej infrastruktury (ławki, place zabaw, stojaki rowerowe)', 'group' => 'initiatives', 'icon' => '🎪')
        );
    }

    /**
     * Default place categories configuration
     */
    private static function get_default_place_categories() {
        return array(
            'gastronomia' => array('label' => 'Gastronomia', 'icon' => '🍽️'),
            'kultura' => array('label' => 'Kultura', 'icon' => '🏛️'),
            'uslugi' => array('label' => 'Usługi', 'icon' => '🏢'),
            'sport' => array('label' => 'Sport i rekreacja', 'icon' => '⚽'),
            'historia' => array('label' => 'Historia i zabytki', 'icon' => '🏰'),
            'zielen' => array('label' => 'Zieleń', 'icon' => '🌲')
        );
    }

    /**
     * Default curiosity categories configuration
     */
    private static function get_default_curiosity_categories() {
        return array(
            'historyczne' => array('label' => 'Historyczne', 'icon' => '📜'),
            'przyrodnicze' => array('label' => 'Przyrodnicze', 'icon' => '🦋'),
            'architektoniczne' => array('label' => 'Architektoniczne', 'icon' => '🏰'),
            'legendy' => array('label' => 'Legendy i historie', 'icon' => '📖')
        );
    }

    /**
     * Get place categories
     */
    public static function get_place_categories() {
        $custom_categories = get_option('jg_map_place_categories', null);
        if ($custom_categories !== null && is_array($custom_categories)) {
            return $custom_categories;
        }
        return self::get_default_place_categories();
    }

    /**
     * Get curiosity categories
     */
    public static function get_curiosity_categories() {
        $custom_categories = get_option('jg_map_curiosity_categories', null);
        if ($custom_categories !== null && is_array($custom_categories)) {
            return $custom_categories;
        }
        return self::get_default_curiosity_categories();
    }

    /**
     * Default category groups
     * Used as fallback if no custom groups are defined
     */
    private static function get_default_category_groups() {
        return array(
            'infrastructure' => 'Zgłoszenie usterek infrastruktury',
            'safety' => 'Porządek i bezpieczeństwo',
            'greenery' => 'Zieleń i estetyka miasta',
            'transport' => 'Transport i komunikacja',
            'initiatives' => 'Inicjatywy społeczne i rozwojowe'
        );
    }

    /**
     * Report categories configuration
     * Maps category keys to their display labels and group
     * Reads from WordPress options, falls back to defaults
     */
    public static function get_report_categories() {
        $custom_categories = get_option('jg_map_report_reasons', null);
        if ($custom_categories !== null && is_array($custom_categories)) {
            return $custom_categories;
        }
        return self::get_default_report_categories();
    }

    /**
     * Get category groups for display
     * Reads from WordPress options, falls back to defaults
     */
    public static function get_category_groups() {
        $custom_groups = get_option('jg_map_report_categories', null);
        if ($custom_groups !== null && is_array($custom_groups)) {
            return $custom_groups;
        }
        return self::get_default_category_groups();
    }

    /**
     * Initialize default report settings if not already set
     */
    public static function init_default_report_settings() {
        if (get_option('jg_map_report_reasons', null) === null) {
            update_option('jg_map_report_reasons', self::get_default_report_categories());
        }
        if (get_option('jg_map_report_categories', null) === null) {
            update_option('jg_map_report_categories', self::get_default_category_groups());
        }
    }

    /**
     * Auto-suggest icon based on label name
     * Returns an emoji that best matches the text content
     */
    public static function suggest_icon_for_label($label) {
        $label_lower = mb_strtolower($label, 'UTF-8');

        // Icon mapping based on keywords (more specific first)
        $icon_mappings = array(
            // Infrastructure - specific
            'dziura w jezdni' => '🕳️',
            'dziura' => '🕳️',
            'wyrwa' => '🕳️',
            'nierówność' => '🛣️',
            'jezdnia' => '🛣️',
            'asfalt' => '🛣️',
            'nawierzchnia' => '🛣️',
            'droga' => '🛣️',
            'ulica' => '🛣️',
            'chodnik' => '🚶',
            'krawężnik' => '🚶',
            'płyta chodnikowa' => '🚶',
            'znak drogowy' => '🚸',
            'znak' => '🚸',
            'sygnalizacja' => '🚦',
            'oświetlenie' => '💡',
            'światło' => '💡',
            'lampa' => '💡',
            'latarnia' => '💡',
            'żarówka' => '💡',
            'ciemno' => '💡',
            'most' => '🌉',
            'kładka' => '🌉',
            'wiadukt' => '🌉',
            'tunel' => '🚇',
            'budynek' => '🏢',
            'dom' => '🏠',
            'mieszkanie' => '🏢',
            'parking' => '🅿️',
            'garaż' => '🅿️',
            'schody' => '🪜',
            'dach' => '🏠',
            'elewacja' => '🏢',
            'fasada' => '🏢',
            'brama' => '🚪',
            'drzwi' => '🚪',
            'okno' => '🪟',
            'płot' => '🏗️',
            'ogrodzenie' => '🏗️',
            'barierka' => '🏗️',
            'poręcz' => '🏗️',
            'studzienka' => '🕳️',
            'kanał' => '💧',
            'rura' => '🔧',
            'hydrant' => '🧯',

            // Safety & order
            'wysypisko' => '🗑️',
            'śmieci' => '🗑️',
            'odpady' => '🗑️',
            'śmietnik' => '🗑️',
            'kosz' => '♻️',
            'pojemnik' => '♻️',
            'recykling' => '♻️',
            'segregacja' => '♻️',
            'graffiti' => '🎨',
            'napis' => '🎨',
            'wandalizm' => '🎨',
            'zniszcz' => '🔧',
            'dewastacja' => '⚠️',
            'śliski' => '⚠️',
            'oblodzon' => '❄️',
            'lód' => '❄️',
            'śnieg' => '❄️',
            'zaśnieżon' => '❄️',
            'liście' => '🍂',
            'niebezpiecz' => '⚠️',
            'zagrożenie' => '⚠️',
            'ostrzeżenie' => '⚠️',
            'wypadek' => '🚨',
            'alarm' => '🚨',
            'awaria' => '🔧',
            'uszkodz' => '🔧',
            'zepsut' => '🔧',
            'naprawa' => '🔧',
            'remont' => '🏗️',
            'budowa' => '🏗️',
            'roboty' => '🏗️',

            // Greenery & Nature
            'drzewo' => '🌳',
            'drzewa' => '🌳',
            'nasadzenie' => '🌳',
            'wycinka' => '🌳',
            'las' => '🌲',
            'sosna' => '🌲',
            'świerk' => '🌲',
            'zieleń' => '🌿',
            'gałąź' => '🌿',
            'gałęzie' => '🌿',
            'krzew' => '🌿',
            'krzak' => '🌿',
            'żywopłot' => '🌿',
            'park' => '🏞️',
            'skwer' => '🏞️',
            'trawnik' => '🌱',
            'trawa' => '🌱',
            'koszenie' => '🌱',
            'kwiat' => '🌸',
            'kwiaty' => '🌸',
            'róża' => '🌹',
            'klomb' => '🌷',
            'rabata' => '🌷',
            'ogród' => '🌻',
            'roślina' => '🪴',
            'sadzonka' => '🌱',

            // Transport
            'przejście dla pieszych' => '🚦',
            'przejście' => '🚦',
            'zebra' => '🚦',
            'pieszy' => '🚦',
            'piesi' => '🚦',
            'przystanek' => '🚏',
            'wiata' => '🚏',
            'rozkład' => '🚏',
            'autobus' => '🚌',
            'komunikacja' => '🚌',
            'mpk' => '🚌',
            'tramwaj' => '🚋',
            'pociąg' => '🚆',
            'kolej' => '🚆',
            'dworzec' => '🚉',
            'metro' => '🚇',
            'ruch' => '🚗',
            'samochód' => '🚗',
            'auto' => '🚗',
            'pojazd' => '🚗',
            'korek' => '🚙',
            'zator' => '🚙',
            'rower' => '🚲',
            'ścieżka rowerowa' => '🚲',
            'hulajnoga' => '🛴',
            'motocykl' => '🏍️',
            'ciężarówka' => '🚛',
            'tir' => '🚛',
            'taxi' => '🚕',
            'taksówka' => '🚕',
            'helikopter' => '🚁',
            'samolot' => '✈️',
            'lotnisko' => '🛫',
            'łódź' => '⛵',
            'statek' => '🚢',
            'port' => '⚓',

            // Urban furniture & Amenities
            'ławka' => '🪑',
            'siedzenie' => '🪑',
            'plac zabaw' => '🎠',
            'zabaw' => '🎠',
            'huśtawka' => '🎠',
            'zjeżdżalnia' => '🎠',
            'piaskownica' => '🎠',
            'siłownia' => '🏋️',
            'fitness' => '🏋️',
            'stojak' => '🚲',
            'wieszak' => '🚲',
            'infrastruktura' => '🎪',
            'kosze do koszykówki' => '🏀',
            'boisko' => '⚽',
            'stadion' => '🏟️',
            'basen' => '🏊',
            'kąpielisko' => '🏊',
            'fontanna' => '⛲',
            'pomnik' => '🗽',
            'rzeźba' => '🗿',
            'mural' => '🎨',

            // Water & Weather issues
            'woda' => '💧',
            'zalanie' => '💧',
            'powódź' => '🌊',
            'deszcz' => '🌧️',
            'burza' => '⛈️',
            'wiatr' => '💨',
            'huragan' => '🌪️',
            'gradobicie' => '🌨️',

            // Noise & Pollution
            'hałas' => '🔊',
            'głośno' => '🔊',
            'muzyka' => '🎵',
            'impreza' => '🎉',
            'zapach' => '👃',
            'smród' => '👃',
            'dym' => '🌫️',
            'zanieczyszczenie' => '☣️',
            'smog' => '🌫️',
            'pył' => '🌫️',
            'kurz' => '🌫️',

            // Animals
            'zwierzę' => '🐕',
            'zwierzęta' => '🐕',
            'pies' => '🐕',
            'psy' => '🐕',
            'szczekanie' => '🐕',
            'kot' => '🐈',
            'koty' => '🐈',
            'ptak' => '🐦',
            'ptaki' => '🐦',
            'gołębie' => '🐦',
            'wrona' => '🐦',
            'szczur' => '🐀',
            'mysz' => '🐁',
            'owad' => '🐝',
            'komary' => '🦟',
            'muchy' => '🪰',
            'osa' => '🐝',
            'pszczoła' => '🐝',
            'gniazdo' => '🪹',
            'mrowisko' => '🐜',

            // Public services
            'szkoła' => '🏫',
            'przedszkole' => '🏫',
            'uniwersytet' => '🏛️',
            'szpital' => '🏥',
            'przychodnia' => '🏥',
            'apteka' => '💊',
            'policja' => '🚔',
            'straż' => '🚒',
            'urząd' => '🏛️',
            'poczta' => '📮',
            'biblioteka' => '📚',
            'kościół' => '⛪',
            'cmentarz' => '⚰️',
            'sklep' => '🏪',
            'market' => '🏪',
            'restauracja' => '🍽️',
            'kawiarnia' => '☕',
            'bar' => '🍺',
            'hotel' => '🏨',
            'bank' => '🏦',

            // Miscellaneous
            'propozycja' => '💡',
            'pomysł' => '💡',
            'inicjatywa' => '💡',
            'prośba' => '📝',
            'wniosek' => '📋',
            'skarga' => '📢',
            'petycja' => '📜',
            'wydarzenie' => '📅',
            'festyn' => '🎪',
            'koncert' => '🎤',
            'wystawa' => '🖼️',
            'wifi' => '📶',
            'internet' => '🌐',
            'kamera' => '📹',
            'monitoring' => '📹',
            'defibrylator' => '💓',
            'aed' => '💓',
        );

        // Search for matching keywords (longer phrases first for better matching)
        // Sort by key length descending
        uksort($icon_mappings, function($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        foreach ($icon_mappings as $keyword => $icon) {
            if (mb_strpos($label_lower, $keyword) !== false) {
                return $icon;
            }
        }

        // Default icon
        return '📌';
    }

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
        add_action('wp_ajax_jg_check_point_exists', array($this, 'check_point_exists'));
        add_action('wp_ajax_nopriv_jg_check_point_exists', array($this, 'check_point_exists'));
        add_action('wp_ajax_jg_check_updates', array($this, 'check_updates'));
        add_action('wp_ajax_nopriv_jg_check_updates', array($this, 'check_updates'));
        add_action('wp_ajax_jg_reverse_geocode', array($this, 'reverse_geocode'));
        add_action('wp_ajax_nopriv_jg_reverse_geocode', array($this, 'reverse_geocode'));
        add_action('wp_ajax_jg_search_address', array($this, 'search_address'));
        add_action('wp_ajax_nopriv_jg_search_address', array($this, 'search_address'));
        add_action('wp_ajax_nopriv_jg_map_login', array($this, 'login_user'));
        add_action('wp_ajax_nopriv_jg_map_register', array($this, 'register_user'));
        add_action('wp_ajax_nopriv_jg_map_forgot_password', array($this, 'forgot_password'));
        add_action('wp_ajax_nopriv_jg_map_resend_activation', array($this, 'resend_activation_email'));
        add_action('wp_ajax_jg_map_resend_activation', array($this, 'resend_activation_email'));
        add_action('wp_ajax_jg_check_registration_status', array($this, 'check_registration_status'));
        add_action('wp_ajax_nopriv_jg_check_registration_status', array($this, 'check_registration_status'));
        add_action('wp_ajax_jg_check_user_session_status', array($this, 'check_user_session_status'));
        add_action('wp_ajax_nopriv_jg_check_user_session_status', array($this, 'check_user_session_status'));
        add_action('wp_ajax_jg_logout_user', array($this, 'logout_user'));
        add_action('wp_ajax_nopriv_jg_logout_user', array($this, 'logout_user'));
        add_action('wp_ajax_jg_track_stat', array($this, 'track_stat'));
        add_action('wp_ajax_nopriv_jg_track_stat', array($this, 'track_stat'));
        add_action('wp_ajax_jg_get_point_stats', array($this, 'get_point_stats'));
        add_action('wp_ajax_nopriv_jg_get_point_stats', array($this, 'get_point_stats'));
        add_action('wp_ajax_jg_get_point_visitors', array($this, 'get_point_visitors'));
        add_action('wp_ajax_nopriv_jg_get_point_visitors', array($this, 'get_point_visitors'));
        add_action('wp_ajax_jg_get_user_info', array($this, 'get_user_info'));
        add_action('wp_ajax_nopriv_jg_get_user_info', array($this, 'get_user_info'));
        add_action('wp_ajax_jg_get_ranking', array($this, 'get_ranking'));
        add_action('wp_ajax_nopriv_jg_get_ranking', array($this, 'get_ranking'));
        add_action('wp_ajax_jg_get_sidebar_points', array($this, 'get_sidebar_points'));
        add_action('wp_ajax_nopriv_jg_get_sidebar_points', array($this, 'get_sidebar_points'));
        add_action('wp_ajax_jg_banner_impression', array($this, 'track_banner_impression'));
        add_action('wp_ajax_nopriv_jg_banner_impression', array($this, 'track_banner_impression'));
        add_action('wp_ajax_jg_banner_click', array($this, 'track_banner_click'));
        add_action('wp_ajax_nopriv_jg_banner_click', array($this, 'track_banner_click'));
        add_action('wp_ajax_jg_get_banner', array($this, 'get_banner'));
        add_action('wp_ajax_nopriv_jg_get_banner', array($this, 'get_banner'));

        // Logged in user actions
        add_action('wp_ajax_jg_submit_point', array($this, 'submit_point'));
        add_action('wp_ajax_jg_update_point', array($this, 'update_point'));
        add_action('wp_ajax_jg_vote', array($this, 'vote'));
        add_action('wp_ajax_jg_report_point', array($this, 'report_point'));
        add_action('wp_ajax_jg_author_points', array($this, 'get_author_points'));
        add_action('wp_ajax_jg_request_deletion', array($this, 'request_deletion'));
        add_action('wp_ajax_jg_get_daily_limits', array($this, 'get_daily_limits'));
        add_action('wp_ajax_jg_map_get_current_user', array($this, 'get_current_user'));
        add_action('wp_ajax_jg_map_get_my_stats', array($this, 'get_my_stats'));
        add_action('wp_ajax_jg_map_update_profile', array($this, 'update_profile'));
        add_action('wp_ajax_jg_map_delete_profile', array($this, 'delete_profile'));

        // Admin actions
        add_action('wp_ajax_jg_get_reports', array($this, 'get_reports'));
        add_action('wp_ajax_jg_handle_reports', array($this, 'handle_reports'));
        add_action('wp_ajax_jg_admin_edit_and_resolve_reports', array($this, 'admin_edit_and_resolve_reports'));
        add_action('wp_ajax_jg_admin_toggle_promo', array($this, 'admin_toggle_promo'));
        add_action('wp_ajax_jg_admin_toggle_author', array($this, 'admin_toggle_author'));
        add_action('wp_ajax_jg_admin_update_note', array($this, 'admin_update_note'));
        add_action('wp_ajax_jg_admin_change_status', array($this, 'admin_change_status'));
        add_action('wp_ajax_jg_admin_approve_point', array($this, 'admin_approve_point'));
        add_action('wp_ajax_jg_admin_reject_point', array($this, 'admin_reject_point'));
        add_action('wp_ajax_jg_get_point_history', array($this, 'get_point_history'));
        add_action('wp_ajax_jg_admin_approve_edit', array($this, 'admin_approve_edit'), 1, 0);
        add_action('wp_ajax_jg_admin_reject_edit', array($this, 'admin_reject_edit'), 1);
        add_action('wp_ajax_jg_owner_approve_edit', array($this, 'owner_approve_edit'), 1);
        add_action('wp_ajax_jg_owner_reject_edit', array($this, 'owner_reject_edit'), 1);
        add_action('wp_ajax_jg_admin_update_promo_date', array($this, 'admin_update_promo_date'), 1);
        add_action('wp_ajax_jg_admin_update_promo', array($this, 'admin_update_promo'), 1);
        add_action('wp_ajax_jg_admin_update_sponsored', array($this, 'admin_update_sponsored'), 1);
        add_action('wp_ajax_jg_admin_delete_point', array($this, 'admin_delete_point'), 1);
        add_action('wp_ajax_jg_admin_ban_user', array($this, 'admin_ban_user'), 1);
        add_action('wp_ajax_jg_admin_unban_user', array($this, 'admin_unban_user'), 1);
        add_action('wp_ajax_jg_admin_toggle_user_restriction', array($this, 'admin_toggle_user_restriction'), 1);
        add_action('wp_ajax_jg_get_user_restrictions', array($this, 'get_user_restrictions'), 1);
        add_action('wp_ajax_jg_get_my_restrictions', array($this, 'get_my_restrictions'), 1);
        add_action('wp_ajax_jg_admin_approve_deletion', array($this, 'admin_approve_deletion'), 1);
        add_action('wp_ajax_jg_admin_reject_deletion', array($this, 'admin_reject_deletion'), 1);
        add_action('wp_ajax_jg_admin_get_user_limits', array($this, 'admin_get_user_limits'), 1);
        add_action('wp_ajax_jg_admin_set_user_limits', array($this, 'admin_set_user_limits'), 1);
        add_action('wp_ajax_jg_admin_get_user_photo_limit', array($this, 'admin_get_user_photo_limit'), 1);
        add_action('wp_ajax_jg_admin_set_user_photo_limit', array($this, 'admin_set_user_photo_limit'), 1);
        add_action('wp_ajax_jg_admin_reset_user_photo_limit', array($this, 'admin_reset_user_photo_limit'), 1);
        add_action('wp_ajax_jg_admin_get_user_edit_limit', array($this, 'admin_get_user_edit_limit'), 1);
        add_action('wp_ajax_jg_admin_reset_user_edit_limit', array($this, 'admin_reset_user_edit_limit'), 1);
        add_action('wp_ajax_jg_admin_unblock_ip', array($this, 'admin_unblock_ip'), 1);
        add_action('wp_ajax_jg_delete_image', array($this, 'delete_image'), 1);
        add_action('wp_ajax_jg_set_featured_image', array($this, 'set_featured_image'), 1);
        add_action('wp_ajax_jg_get_notification_counts', array($this, 'get_notification_counts'), 1);
        add_action('wp_ajax_jg_keep_reported_place', array($this, 'keep_reported_place'), 1);
        add_action('wp_ajax_jg_admin_delete_user', array($this, 'admin_delete_user'), 1);
        add_action('wp_ajax_jg_admin_restore_point', array($this, 'admin_restore_point'), 1);
        add_action('wp_ajax_jg_admin_empty_trash', array($this, 'admin_empty_trash'), 1);
        add_action('wp_ajax_jg_admin_toggle_edit_lock', array($this, 'admin_toggle_edit_lock'), 1);
        add_action('wp_ajax_jg_admin_change_owner', array($this, 'admin_change_owner'), 1);
        add_action('wp_ajax_jg_admin_search_users', array($this, 'admin_search_users'), 1);

        // Report reasons management (admin only)
        add_action('wp_ajax_jg_save_report_category', array($this, 'save_report_category'), 1);
        add_action('wp_ajax_jg_update_report_category', array($this, 'update_report_category'), 1);
        add_action('wp_ajax_jg_delete_report_category', array($this, 'delete_report_category'), 1);
        add_action('wp_ajax_jg_save_report_reason', array($this, 'save_report_reason'), 1);
        add_action('wp_ajax_jg_update_report_reason', array($this, 'update_report_reason'), 1);
        add_action('wp_ajax_jg_delete_report_reason', array($this, 'delete_report_reason'), 1);
        add_action('wp_ajax_jg_suggest_reason_icon', array($this, 'suggest_reason_icon'), 1);

        // Place categories management (admin only)
        add_action('wp_ajax_jg_save_place_category', array($this, 'save_place_category'), 1);
        add_action('wp_ajax_jg_update_place_category', array($this, 'update_place_category'), 1);
        add_action('wp_ajax_jg_delete_place_category', array($this, 'delete_place_category'), 1);

        // Curiosity categories management (admin only)
        add_action('wp_ajax_jg_save_curiosity_category', array($this, 'save_curiosity_category'), 1);
        add_action('wp_ajax_jg_update_curiosity_category', array($this, 'update_curiosity_category'), 1);
        add_action('wp_ajax_jg_delete_curiosity_category', array($this, 'delete_curiosity_category'), 1);

        // Track last login time
        add_action('wp_login', array($this, 'track_last_login'), 10, 2);
    }

    /**
     * Track last login time
     */
    public function track_last_login($user_login, $user) {
        update_user_meta($user->ID, 'jg_map_last_login', current_time('mysql', true));
    }

    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!isset($_POST['_ajax_nonce'])) {
            wp_send_json_error(array('message' => 'Błąd bezpieczeństwa - brak nonce'));
            exit;
        }

        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'jg_map_nonce')) {
            wp_send_json_error(array('message' => 'Błąd bezpieczeństwa - nieprawidłowy nonce'));
            exit;
        }

    }

    /**
     * Check if user is admin or moderator
     */
    private function check_admin() {
        $user_id = get_current_user_id();
        $can_manage = current_user_can('manage_options');
        $can_moderate = current_user_can('jg_map_moderate');


        if (!$can_manage && !$can_moderate) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

    }

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
            $pending_points = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
            // ONLY count edits, not deletion requests
            $pending_edits = $wpdb->get_var("SELECT COUNT(*) FROM $history_table WHERE status = 'pending' AND action_type = 'edit'");
            $pending_reports = $wpdb->get_var(
                "SELECT COUNT(DISTINCT r.point_id)
                 FROM $reports_table r
                 INNER JOIN $table p ON r.point_id = p.id
                 WHERE r.status = 'pending' AND p.status = 'publish'"
            );
            $pending_deletions = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_deletion_requested = 1 AND status = 'publish'");
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

            // Get votes from batch-loaded data
            $votes_count = isset($votes_counts_map[$point_id]) ? $votes_counts_map[$point_id] : 0;
            $my_vote = isset($user_votes_map[$point_id]) ? $user_votes_map[$point_id] : '';

            // Get relevance votes (not yet implemented)
            $relevance_votes_count = 0;
            $my_relevance_vote = '';
            if ($current_user_id > 0) {
            }

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

                    // Convert GMT time from DB to local WordPress time for display
                    $local_time = get_date_from_gmt($report['created_at']);

                    $reporter_info = array(
                        'reported_at' => human_time_diff(strtotime($local_time), current_time('timestamp')) . ' temu',
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
                    if ($ph['action_type'] === 'edit' && intval($ph['user_id']) === $current_user_id) {
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
                                'new_title' => $new_values['title'] ?? '',
                                'new_type' => $new_values['type'] ?? '',
                                'new_category' => $new_values['category'] ?? null,
                                'new_content' => $new_values['content'] ?? '',
                                'prev_website' => $old_values['website'] ?? null,
                                'new_website' => $new_values['website'] ?? null,
                                'prev_phone' => $old_values['phone'] ?? null,
                                'new_phone' => $new_values['phone'] ?? null,
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
                                'new_images' => $new_images,
                                'edited_at' => human_time_diff(strtotime(get_date_from_gmt($pending_history['created_at'])), current_time('timestamp')) . ' temu'
                            );
                        } else if ($pending_history['action_type'] === 'delete_request') {
                            $deletion_info = array(
                                'history_id' => intval($pending_history['id']),
                                'reason' => $new_values['reason'] ?? '',
                                'requested_at' => human_time_diff(strtotime(get_date_from_gmt($pending_history['created_at'])), current_time('timestamp')) . ' temu'
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
                                    'rejected_at' => human_time_diff(strtotime(get_date_from_gmt($rejected_history['resolved_at'])), current_time('timestamp')) . ' temu'
                                );
                            } else if ($rejected_history['action_type'] === 'delete_request' && $deletion_info === null) {
                                $deletion_info = array(
                                    'status' => 'rejected',
                                    'rejection_reason' => $rejection_reason,
                                    'rejected_at' => human_time_diff(strtotime(get_date_from_gmt($rejected_history['resolved_at'])), current_time('timestamp')) . ' temu'
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
                'facebook_url' => $point['facebook_url'] ?? null,
                'instagram_url' => $point['instagram_url'] ?? null,
                'linkedin_url' => $point['linkedin_url'] ?? null,
                'tiktok_url' => $point['tiktok_url'] ?? null,
                'cta_enabled' => (bool)($point['cta_enabled'] ?? 0),
                'cta_type' => $point['cta_type'] ?? null,
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
                'votes' => $votes_count,
                'my_vote' => $my_vote,
                'relevance_votes' => $relevance_votes_count,
                'my_relevance_vote' => $my_relevance_vote,
                'date' => array(
                    'raw' => $point['created_at'],
                    'human' => human_time_diff(strtotime(get_date_from_gmt($point['created_at'])), current_time('timestamp')) . ' temu'
                ),
                'admin' => $is_admin ? array(
                    'author_name_real' => $author ? $author->display_name : '',
                    'author_email' => $author_email,
                    'ip' => $point['ip_address'] ?: '(brak)'
                ) : null,
                // SECURITY: For unauthenticated users, always hide moderation data
                'admin_note' => ($current_user_id > 0) ? $point['admin_note'] : null,
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
     * Get stats for a single point (for live updates)
     */
    public function get_point_stats() {
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

        // Check for SQL errors
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
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
        $points_per_page = 10;
        $photos_per_page = 12;

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

        // Get user's last activity (last point created)
        $last_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table_points WHERE author_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));

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
            'points_count' => intval($points_count),
            'type_counts' => $type_counts,
            'points' => $points_list,
            'points_total' => intval($points_count),
            'points_page' => $points_page,
            'points_pages' => max(1, ceil(intval($points_count) / $points_per_page)),
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
     * Get top 10 users ranking by number of published places
     */
    public function get_ranking() {
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
        // Admins have no limits
        if (current_user_can('manage_options')) {
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

        // Admins have no limits
        if (current_user_can('manage_options')) {
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

            // Send email notification to admin
            $this->notify_admin_new_point($point_id);

            // Queue sync event for real-time updates
            JG_Map_Sync_Manager::get_instance()->queue_point_created($point_id, array(
                'point_title' => $saved_point['title'],
                'point_type' => $type,
                'status' => $saved_point['status']
            ));

            // Award XP for submitting a point
            JG_Map_Levels_Achievements::award_xp($user_id, 'submit_point', $point_id);

            // Award XP for photos if images were uploaded
            if (!empty($_FILES['images'])) {
                $photo_count = is_array($_FILES['images']['name']) ? count(array_filter($_FILES['images']['name'])) : 1;
                for ($i = 0; $i < $photo_count; $i++) {
                    JG_Map_Levels_Achievements::award_xp($user_id, 'add_photo', $point_id);
                }
            }

            $response = array(
                'message' => 'Punkt dodany do moderacji',
                'point_id' => $point_id,
                'type' => $type
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

        // Normalize social media URLs - accept full URLs, domain URLs, or profile names
        $facebook_url = !empty($_POST['facebook_url']) ? $this->normalize_social_url($_POST['facebook_url'], 'facebook') : '';
        $instagram_url = !empty($_POST['instagram_url']) ? $this->normalize_social_url($_POST['instagram_url'], 'instagram') : '';
        $linkedin_url = !empty($_POST['linkedin_url']) ? $this->normalize_social_url($_POST['linkedin_url'], 'linkedin') : '';
        $tiktok_url = !empty($_POST['tiktok_url']) ? $this->normalize_social_url($_POST['tiktok_url'], 'tiktok') : '';

        $cta_enabled = isset($_POST['cta_enabled']) ? 1 : 0;
        $cta_type = sanitize_text_field($_POST['cta_type'] ?? '');

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

            // Update lat/lng if provided (from geocoding)
            if ($lat !== null && $lng !== null) {
                $update_data['lat'] = $lat;
                $update_data['lng'] = $lng;
            }

            // Update address if provided
            if (!empty($address)) {
                $update_data['address'] = $address;
            }

            // Add website, phone, social media, and CTA if point is sponsored
            $is_sponsored = (bool)$point['is_promo'];
            if ($is_sponsored) {
                $update_data['website'] = !empty($website) ? $website : null;
                $update_data['phone'] = !empty($phone) ? $phone : null;
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
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'address' => $point['address'] ?? '',
                'images' => $point['images'] ?? '[]'
            );

            $new_values = array(
                'title' => $title,
                'type' => $type,
                'category' => $category,
                'content' => $content,
                'new_images' => json_encode($new_images) // Store new images separately for moderation
            );

            // Always include lat/lng/address in new_values for proper comparison in admin panel
            // Use new values if provided (from geocoding), otherwise use current point values
            $new_values['lat'] = ($lat !== null) ? $lat : $point['lat'];
            $new_values['lng'] = ($lng !== null) ? $lng : $point['lng'];
            $new_values['address'] = !empty($address) ? $address : ($point['address'] ?? '');

            // Add website, phone, social media, and CTA if point is sponsored
            $is_sponsored = (bool)$point['is_promo'];
            if ($is_sponsored) {
                $old_values['website'] = $point['website'] ?? null;
                $old_values['phone'] = $point['phone'] ?? null;
                $old_values['facebook_url'] = $point['facebook_url'] ?? null;
                $old_values['instagram_url'] = $point['instagram_url'] ?? null;
                $old_values['linkedin_url'] = $point['linkedin_url'] ?? null;
                $old_values['tiktok_url'] = $point['tiktok_url'] ?? null;
                $old_values['cta_enabled'] = $point['cta_enabled'] ?? 0;
                $old_values['cta_type'] = $point['cta_type'] ?? null;
                $new_values['website'] = !empty($website) ? $website : null;
                $new_values['phone'] = !empty($phone) ? $phone : null;
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

            // Award XP for editing
            JG_Map_Levels_Achievements::award_xp($user_id, 'edit_point', $point_id);

            $success_msg = !$is_owner
                ? 'Edycja wysłana do zatwierdzenia przez właściciela miejsca'
                : 'Edycja wysłana do moderacji';

            wp_send_json_success(array('message' => $success_msg));
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

        if (!$point_id || !in_array($direction, array('up', 'down'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Check if user is the author of the point - can't vote on own places
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }
        if (intval($point['author_id']) === $user_id) {
            wp_send_json_error(array('message' => 'Nie możesz głosować na własne miejsca'));
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

        // Award XP for voting (only when casting a new vote, not removing)
        if (!empty($new_vote) && empty($current_vote)) {
            JG_Map_Levels_Achievements::award_xp($user_id, 'vote_on_point', $point_id);
            // Award XP to the point author for receiving an upvote
            if ($new_vote === 'up') {
                $author_id = intval($point['author_id']);
                if ($author_id && $author_id !== $user_id) {
                    JG_Map_Levels_Achievements::award_xp($author_id, 'receive_upvote', $point_id);
                }
            }
        }

        $votes_count = JG_Map_Database::get_votes_count($point_id);

        // Check if votes dropped to -100 or below - auto-report to moderation
        if ($votes_count <= -100) {
            // Check if already reported for this reason
            $user_email = wp_get_current_user()->user_email;
            $reason_text = 'Zgłoszenie z dużą dezaprobatą społeczności (automatyczne zgłoszenie: głosowanie wynosi ' . $votes_count . ')';

            // Check if not already reported with this reason
            global $wpdb;
            $reports_table = JG_Map_Database::get_reports_table();
            $existing_auto_report = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $reports_table WHERE point_id = %d AND reason LIKE %s AND status = 'pending'",
                    $point_id,
                    '%Zgłoszenie z dużą dezaprobatą społeczności%'
                )
            );

            if ($existing_auto_report == 0) {
                // Auto-report to moderation
                JG_Map_Database::add_report($point_id, $user_id, $user_email, $reason_text);

                // Notify admin
                $this->notify_admin_auto_negative_report($point_id, $votes_count);
            }
        }

        wp_send_json_success(array(
            'votes' => $votes_count,
            'my_vote' => $new_vote
        ));
    }

    /**

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
        JG_Map_Levels_Achievements::award_xp($user_id, 'report_point', $point_id);

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

        // Notify admin
        $this->notify_admin_new_report($point_id, $user_id);

        // Notify reporter (confirmation email)
        $this->notify_reporter_confirmation($point_id, $email);

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
                'date' => human_time_diff(strtotime(get_date_from_gmt($report['created_at'])), current_time('timestamp')) . ' temu'
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

        if ($action_type === 'remove') {
            // Delete permanently
            JG_Map_Database::delete_point($point_id);
            $message = 'Miejsce usunięte';
            $decision_text = 'usunięte';
        } else if ($action_type === 'edit') {
            // Place was edited
            $message = 'Miejsce edytowane';
            $decision_text = 'edytowane i pozostawione';
        } else {
            // Keep the point
            $message = 'Miejsce pozostawione';
            $decision_text = 'pozostawione bez zmian';
        }

        // Notify reporters about decision
        $this->notify_reporters_decision($point_id, $decision_text, $reason);

        // Resolve reports
        JG_Map_Database::resolve_reports($point_id, $reason);

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
        $point = JG_Map_Database::get_point($point_id);
        JG_Map_Activity_Log::log(
            'handle_reports',
            'point',
            $point_id,
            sprintf('Rozpatrzono zgłoszenia dla: %s. Decyzja: %s', $point['title'], $decision_text)
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
        $website = sanitize_text_field($_POST['website'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');

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
            'excerpt' => wp_trim_words($content, 20)
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

        // Add website, phone, social media, and CTA if point is sponsored
        $is_sponsored = (bool)$point['is_promo'];
        if ($is_sponsored) {
            $update_data['website'] = !empty($website) ? $website : null;
            $update_data['phone'] = !empty($phone) ? $phone : null;
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

        if (!$point_id || !in_array($new_status, array('added', 'needs_better_documentation', 'reported', 'resolved', 'rejected'))) {
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
            'author_id' => intval($point['author_id'])
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

        JG_Map_Database::delete_point($point_id);

        // Resolve any pending reports for this point
        JG_Map_Database::resolve_reports($point_id, 'Punkt został odrzucony przez moderatora: ' . $reason);

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

        // Notify author
        $this->notify_author_rejected($point_id, $reason);

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

    /**
     * Handle image upload with size/dimension validation and monthly limit tracking
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

                        // Resize to 800x800 if needed
                        $resized_file = $this->resize_image_if_needed($movefile['file'], $MAX_DIMENSION);

                        // Create thumbnail
                        $thumbnail_url = $this->create_thumbnail($resized_file, $movefile['url']);

                        // Get actual file size after resize
                        $actual_size = file_exists($resized_file) ? filesize($resized_file) : 0;
                        $total_size_uploaded += $actual_size;

                        $images[] = array(
                            'full' => $movefile['url'],
                            'thumb' => $thumbnail_url ?: $movefile['url']
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

                    // Resize to 800x800 if needed
                    $resized_file = $this->resize_image_if_needed($movefile['file'], $MAX_DIMENSION);

                    // Create thumbnail
                    $thumbnail_url = $this->create_thumbnail($resized_file, $movefile['url']);

                    // Get actual file size after resize
                    $actual_size = file_exists($resized_file) ? filesize($resized_file) : 0;
                    $total_size_uploaded += $actual_size;

                    $images[] = array(
                        'full' => $movefile['url'],
                        'thumb' => $thumbnail_url ?: $movefile['url']
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
     * Check rate limiting to prevent abuse
     */
    private function check_rate_limit($action, $identifier, $max_attempts = 5, $timeframe = 900, $user_data = array(), $increment = false) {
        $transient_key = 'jg_rate_limit_' . $action . '_' . md5($identifier);
        $transient_time_key = 'jg_rate_limit_time_' . $action . '_' . md5($identifier);
        $transient_userdata_key = 'jg_rate_limit_userdata_' . $action . '_' . md5($identifier);

        $attempts = get_transient($transient_key);
        $first_attempt_time = get_transient($transient_time_key);

        if ($attempts !== false && $attempts >= $max_attempts) {
            // Calculate actual time remaining
            $elapsed_time = time() - $first_attempt_time;
            $time_remaining = max(0, $timeframe - $elapsed_time);

            // If time has expired, clear the rate limit and allow the attempt
            if ($time_remaining <= 0) {
                delete_transient($transient_key);
                delete_transient($transient_time_key);
                delete_transient($transient_userdata_key);
                return array(
                    'allowed' => true,
                    'attempts_used' => 0,
                    'attempts_remaining' => $max_attempts
                );
            }

            $minutes_remaining = max(1, ceil($time_remaining / 60));

            return array(
                'allowed' => false,
                'minutes_remaining' => $minutes_remaining,
                'attempts_used' => $attempts,
                'attempts_remaining' => 0
            );
        }

        // Calculate current attempts
        $current_attempts = ($attempts !== false) ? $attempts : 0;

        // Only increment if requested (for failed attempts)
        if ($increment) {
            if ($attempts === false) {
                set_transient($transient_key, 1, $timeframe);
                set_transient($transient_time_key, time(), $timeframe);
                $current_attempts = 1;

                // Store user data for admin viewing (IP, username, email)
                if (!empty($user_data)) {
                    set_transient($transient_userdata_key, $user_data, $timeframe);
                }
            } else {
                $current_attempts = $attempts + 1;
                set_transient($transient_key, $current_attempts, $timeframe);

                // Update user data if provided
                if (!empty($user_data)) {
                    set_transient($transient_userdata_key, $user_data, $timeframe);
                }
            }
        }

        return array(
            'allowed' => true,
            'attempts_used' => $current_attempts,
            'attempts_remaining' => max(0, $max_attempts - $current_attempts)
        );
    }

    /**
     * Clear rate limit for successful attempts
     */
    private function clear_rate_limit($action, $identifier) {
        $transient_key = 'jg_rate_limit_' . $action . '_' . md5($identifier);
        $transient_time_key = 'jg_rate_limit_time_' . $action . '_' . md5($identifier);
        $transient_userdata_key = 'jg_rate_limit_userdata_' . $action . '_' . md5($identifier);

        delete_transient($transient_key);
        delete_transient($transient_time_key);
        delete_transient($transient_userdata_key);
    }

    /**
     * Send email with proper headers for spam prevention
     */
    private function send_plugin_email($to, $subject, $message) {
        // Temporarily override email sender for this email
        add_filter('wp_mail_from_name', array($this, 'get_plugin_email_from_name'), 99);
        add_filter('wp_mail_from', array($this, 'get_plugin_email_from'), 99);

        // Set up headers for better deliverability and spam prevention
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: powiadomienia@jeleniogorzanietomy.pl',
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 3',
            'Importance: Normal'
        );

        // Send email
        $result = wp_mail($to, $subject, $message, $headers);

        // Remove temporary filters
        remove_filter('wp_mail_from_name', array($this, 'get_plugin_email_from_name'), 99);
        remove_filter('wp_mail_from', array($this, 'get_plugin_email_from'), 99);

        return $result;
    }

    /**
     * Get plugin email sender name
     */
    public function get_plugin_email_from_name($from_name) {
        return 'Jeleniogorzanie to my';
    }

    /**
     * Get plugin email sender address
     */
    public function get_plugin_email_from($from_email) {
        return 'powiadomienia@jeleniogorzanietomy.pl';
    }

    /**
     * Validate password strength
     */
    private function validate_password_strength($password) {
        // Minimum 12 characters
        if (strlen($password) < 12) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi mieć co najmniej 12 znaków'
            );
        }

        // Must contain uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi zawierać co najmniej jedną wielką literę'
            );
        }

        // Must contain lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi zawierać co najmniej jedną małą literę'
            );
        }

        // Must contain digit
        if (!preg_match('/[0-9]/', $password)) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi zawierać co najmniej jedną cyfrę'
            );
        }

        return array('valid' => true);
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
     * Resize image if it exceeds max dimension
     */
    private function resize_image_if_needed($file_path, $max_dimension) {
        $image_editor = wp_get_image_editor($file_path);

        if (is_wp_error($image_editor)) {
            return $file_path; // Return original if can't edit
        }

        $size = $image_editor->get_size();
        $width = $size['width'];
        $height = $size['height'];

        // Only resize if larger than max
        if ($width > $max_dimension || $height > $max_dimension) {
            $image_editor->resize($max_dimension, $max_dimension, false);
            $image_editor->save($file_path);
        }

        return $file_path;
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
     * Create thumbnail for uploaded image
     */
    private function create_thumbnail($file_path, $original_url) {
        $image_editor = wp_get_image_editor($file_path);

        if (is_wp_error($image_editor)) {
            return false;
        }

        // Resize to 300x300 thumbnail
        $image_editor->resize(300, 300, false);

        $file_info = pathinfo($file_path);
        $thumbnail_path = $file_info['dirname'] . '/' . $file_info['filename'] . '-thumb.' . $file_info['extension'];

        $saved = $image_editor->save($thumbnail_path);

        if (is_wp_error($saved)) {
            return false;
        }

        // Convert file path to URL
        $upload_dir = wp_upload_dir();
        $thumbnail_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $thumbnail_path);

        return $thumbnail_url;
    }

    /**
     * Get user IP address (with proper validation to prevent spoofing)
     */
    private function get_user_ip() {
        $ip = '';

        // Check CloudFlare (if using CF)
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // Check X-Forwarded-For (take first IP in chain)
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        // Fallback to REMOTE_ADDR
        else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        // Sanitize
        $ip = sanitize_text_field($ip);

        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            // If invalid, use REMOTE_ADDR as fallback
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        }

        return $ip;
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
            'needs_better_documentation' => 'Wymaga lepszego udokumentowania',
            'reported' => 'Zgłoszone do instytucji',
            'resolved' => 'Rozwiązane',
            'rejected' => 'Odrzucono'
        );

        return $labels[$status] ?? $status;
    }

    /**
     * Notify admin about new point
     */
    private function notify_admin_new_point($point_id) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);

        $subject = 'Portal Jeleniogórzanie to my - Nowy punkt do moderacji';
        $message = "Nowy punkt został dodany i czeka na moderację:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Typ: {$point['type']}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-places') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify admin about new report
     */
    private function notify_admin_new_report($point_id, $reporter_user_id = 0) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);

        // Get reporter name
        $reporter_name = 'Nieznany';
        if ($reporter_user_id > 0) {
            $reporter = get_userdata($reporter_user_id);
            $reporter_name = $reporter ? $reporter->display_name : 'Nieznany';
        }

        $subject = 'Portal Jeleniogórzanie to my - Nowe zgłoszenie miejsca';
        $message = "Miejsce zostało zgłoszone:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Zgłoszone przez: {$reporter_name}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-places') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify admin about auto-report due to low relevance votes
     */
    private function notify_admin_auto_report($point_id, $relevance_votes_count) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);

        $subject = 'Portal Jeleniogórzanie to my - Automatyczne zgłoszenie miejsca (nieaktualne)';
        $message = "Miejsce zostało automatycznie zgłoszone do moderacji z powodu niskich głosów na aktualność:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Głosy \"Nadal aktualne?\": {$relevance_votes_count}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-places') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify admin about auto-report due to negative votes
     */
    private function notify_admin_auto_negative_report($point_id, $votes_count) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);

        $subject = 'Portal Jeleniogórzanie to my - Automatyczne zgłoszenie miejsca (duża dezaprobata)';
        $message = "Miejsce zostało automatycznie zgłoszone do moderacji z powodu dużej dezaprobaty społeczności:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Liczba głosów: {$votes_count}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-places') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify reporter about confirmation of report
     */
    private function notify_reporter_confirmation($point_id, $email) {
        if (empty($email)) {
            return;
        }

        $point = JG_Map_Database::get_point($point_id);

        $subject = 'Portal Jeleniogórzanie to my - Potwierdzenie zgłoszenia miejsca';
        $message = "Dziękujemy za zgłoszenie miejsca \"{$point['title']}\".\n\n";
        $message .= "Twoje zgłoszenie zostało przyjęte i zostanie rozpatrzone przez moderatorów.\n";
        $message .= "Otrzymasz powiadomienie email o decyzji moderatora.\n\n";
        $message .= "Dziękujemy za pomoc w utrzymaniu jakości naszej mapy!\n";

        wp_mail($email, $subject, $message);
    }

    /**
     * Notify reporters about decision
     */
    private function notify_reporters_decision($point_id, $decision, $admin_reason) {
        $point = JG_Map_Database::get_point($point_id);
        $reports = JG_Map_Database::get_reports($point_id);

        if (empty($reports)) {
            return;
        }

        $subject = 'Portal Jeleniogórzanie to my - Decyzja dotycząca zgłoszonego miejsca';
        $message = "Zgłoszone przez Ciebie miejsce \"{$point['title']}\" zostało {$decision}.\n\n";

        if ($admin_reason) {
            $message .= "Uzasadnienie moderatora: {$admin_reason}\n\n";
        }

        $message .= "Dziękujemy za zgłoszenie!\n";

        // Send email to all unique reporters
        $sent_emails = array();
        foreach ($reports as $report) {
            // Get email from user account if user_id exists, otherwise use email field
            $email = null;
            if (!empty($report['user_id'])) {
                $user = get_userdata($report['user_id']);
                if ($user && $user->user_email) {
                    $email = $user->user_email;
                }
            } elseif (!empty($report['email'])) {
                $email = $report['email'];
            }

            if ($email && !in_array($email, $sent_emails)) {
                wp_mail($email, $subject, $message);
                $sent_emails[] = $email;
            }
        }
    }

    /**
     * Notify author about approved point
     */
    private function notify_author_approved($point_id) {
        $point = JG_Map_Database::get_point($point_id);
        $author = get_userdata($point['author_id']);

        if ($author && $author->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Twój punkt został zaakceptowany';
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
            $subject = 'Portal Jeleniogórzanie to my - Twój punkt został odrzucony';
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

        $subject = 'Portal Jeleniogórzanie to my - Edycja miejsca do zatwierdzenia';
        $message = "Użytkownik zaktualizował miejsce:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-places') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify owner of edit suggestion
     */
    private function notify_owner_edit($point_id, $owner_id) {
        $owner = get_userdata($owner_id);
        if (!$owner || empty($owner->user_email)) {
            return;
        }

        $point = JG_Map_Database::get_point($point_id);
        $editor = wp_get_current_user();

        $subject = 'Portal Jeleniogórzanie to my - Propozycja edycji twojego miejsca';
        $message = "Użytkownik {$editor->display_name} zaproponował zmiany w twoim miejscu:\n\n";
        $message .= "Tytuł miejsca: {$point['title']}\n";
        $message .= "Link do strony: " . home_url('/mapa/?point=' . $point_id) . "\n\n";
        $message .= "Zaloguj się, aby przejrzeć i zatwierdzić lub odrzucić proponowane zmiany.";

        wp_mail($owner->user_email, $subject, $message);
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
                'created_at' => human_time_diff(strtotime(get_date_from_gmt($entry['created_at'])), current_time('timestamp')) . ' temu',
                'resolved_at' => $entry['resolved_at'] ? human_time_diff(strtotime(get_date_from_gmt($entry['resolved_at'])), current_time('timestamp')) . ' temu' : null
            );
        }

        wp_send_json_success($formatted_history);
    }

    /**
     * Approve edit (admin only)
     */
    public function admin_approve_edit() {
        try {
            $this->verify_nonce();
        } catch (Exception $e) {
            throw $e;
        }

        try {
            $this->check_admin();
        } catch (Exception $e) {
            throw $e;
        }

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

        // Check if this edit requires owner approval
        $current_user_id = get_current_user_id();
        if ($history['point_owner_id'] !== null) {
            if ($history['owner_approval_status'] !== 'approved') {
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
                            'owner_approval_at' => current_time('mysql'),
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

        $new_values = json_decode($history['new_values'], true);


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

        // Add lat/lng if present (from address geocoding)
        if (isset($new_values['lat']) && isset($new_values['lng'])) {
            $update_data['lat'] = floatval($new_values['lat']);
            $update_data['lng'] = floatval($new_values['lng']);
        }

        // Add address if present
        if (isset($new_values['address'])) {
            $update_data['address'] = $new_values['address'];
        }

        // Add website, phone, social media, and CTA if point is sponsored and they are in new_values
        $is_sponsored = (bool)$point['is_promo'];
        if ($is_sponsored) {
            if (isset($new_values['website'])) {
                $update_data['website'] = $new_values['website'];
            }
            if (isset($new_values['phone'])) {
                $update_data['phone'] = $new_values['phone'];
            }
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

        // Update point with new values
        JG_Map_Database::update_point($history['point_id'], $update_data);

        // Approve history
        JG_Map_Database::approve_history($history_id, get_current_user_id());

        // Notify editor (the person who submitted the edit)
        $point = JG_Map_Database::get_point($history['point_id']);
        $editor = get_userdata($history['user_id']);
        if ($editor && $editor->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Twoja edycja została zaakceptowana';
            $message = "Twoja edycja miejsca \"{$point['title']}\" została zaakceptowana przez moderatora.";
            wp_mail($editor->user_email, $subject, $message);
        }

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_edit_approved($history['point_id'], array(
            'history_id' => $history_id,
            'point_title' => $point['title'],
            'point_type' => $point['type'],
            'editor_id' => intval($history['user_id'])
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'approve_edit',
            'history',
            $history_id,
            sprintf('Zaakceptowano edycję miejsca: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Edycja zaakceptowana'));
    }

    /**
     * Reject edit (admin only)
     */
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

        // Reject history with reason
        JG_Map_Database::reject_history($history_id, get_current_user_id(), $reason);

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_edit_rejected($history['point_id'], array(
            'history_id' => $history_id,
            'reason' => $reason
        ));

        // Notify editor (the person who submitted the edit)
        $point = JG_Map_Database::get_point($history['point_id']);
        $editor = get_userdata($history['user_id']);
        if ($editor && $editor->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Twoja edycja została odrzucona';
            $message = "Twoja edycja miejsca \"{$point['title']}\" została odrzucona przez moderatora.\n\n";
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
            sprintf('Odrzucono edycję miejsca: %s. Powód: %s', $point['title'], $reason)
        );

        wp_send_json_success(array('message' => 'Edycja odrzucona'));
    }

    /**
     * Owner approves edit suggestion
     */
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
                $update_data['lat'] = $new_values['lat'];
                $update_data['lng'] = $new_values['lng'];
            }
            if (isset($new_values['address'])) {
                $update_data['address'] = $new_values['address'];
            }

            // Add website, phone, social media, and CTA if point is sponsored
            $is_sponsored = (bool)$point['is_promo'];
            if ($is_sponsored) {
                if (isset($new_values['website'])) {
                    $update_data['website'] = $new_values['website'];
                }
                if (isset($new_values['phone'])) {
                    $update_data['phone'] = $new_values['phone'];
                }
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
                    'owner_approval_at' => current_time('mysql'),
                    'owner_approval_by' => $user_id,
                    'status' => 'approved',
                    'resolved_at' => current_time('mysql'),
                    'resolved_by' => $user_id
                ),
                array('id' => $history_id)
            );

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
                'editor_id' => intval($history['user_id'])
            ));

            // Log action
            JG_Map_Activity_Log::log(
                'owner_approve_edit',
                'history',
                $history_id,
                sprintf('Właściciel (admin/mod) zaakceptował i zatwierdził edycję miejsca: %s', $point['title'])
            );

            wp_send_json_success(array('message' => 'Edycja zaakceptowana i zatwierdzona. Zmiany są już widoczne.'));
        } else {
            // Owner is regular user - only owner approval, still needs admin approval
            $wpdb->update(
                $table,
                array(
                    'owner_approval_status' => 'approved',
                    'owner_approval_at' => current_time('mysql'),
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

    /**
     * Owner rejects edit suggestion
     */
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
                'owner_approval_at' => current_time('mysql'),
                'owner_approval_by' => $user_id,
                'status' => 'rejected',
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $user_id,
                'rejection_reason' => $reason
            ),
            array('id' => $history_id)
        );

        // Get point info for notification
        $point = JG_Map_Database::get_point($history['point_id']);
        $editor = get_userdata($history['user_id']);

        // Notify editor that owner rejected
        if ($editor && $editor->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Właściciel odrzucił twoją edycję';
            $message = "Właściciel miejsca \"{$point['title']}\" odrzucił twoją propozycję zmian.\n\n";
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
            sprintf('Właściciel odrzucił propozycję edycji miejsca: %s. Powód: %s', $point['title'], $reason)
        );

        wp_send_json_success(array('message' => 'Edycja odrzucona'));
    }

    /**
     * Approve deletion request (admin only)
     */
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

    /**
     * Reject deletion request (admin only)
     */
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

    /**
     * Update sponsored status and date (admin only) - NEW API with sponsored naming
     */
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

    /**
     * Move point to trash (admin only)
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

        // Soft delete (move to trash)
        $deleted = JG_Map_Database::soft_delete_point($point_id);

        if ($deleted === false) {
            wp_send_json_error(array('message' => 'Błąd usuwania'));
            exit;
        }

        // Queue sync event via dedicated sync manager
        JG_Map_Sync_Manager::get_instance()->queue_point_deleted($point_id, array(
            'admin_deleted' => true,
            'point_title' => $point['title']
        ));

        // Log action
        JG_Map_Activity_Log::log(
            'delete_point',
            'point',
            $point_id,
            sprintf('Przeniesiono do kosza miejsce: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Miejsce przeniesione do kosza'));
    }

    /**
     * Ban user (admin only)
     */
    public function admin_ban_user() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);
        $ban_type = sanitize_text_field($_POST['ban_type'] ?? '');

        if (!$user_id || !in_array($ban_type, array('permanent', 'temporary'))) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        if ($ban_type === 'permanent') {
            update_user_meta($user_id, 'jg_map_banned', 'permanent');
            delete_user_meta($user_id, 'jg_map_ban_until');
            $ban_details = 'trwale';
        } else {
            // Temporary ban
            $ban_days = intval($_POST['ban_days'] ?? 7);
            $ban_until = date('Y-m-d H:i:s', strtotime('+' . $ban_days . ' days'));

            update_user_meta($user_id, 'jg_map_banned', 'temporary');
            update_user_meta($user_id, 'jg_map_ban_until', $ban_until);
            $ban_details = sprintf('tymczasowo na %d dni', $ban_days);
        }

        // Log action
        JG_Map_Activity_Log::log(
            'ban_user',
            'user',
            $user_id,
            sprintf('Zbanowano użytkownika %s (%s)', $user->display_name, $ban_details)
        );

        wp_send_json_success(array(
            'message' => 'Użytkownik zbanowany',
            'ban_type' => $ban_type
        ));
    }

    /**
     * Unban user (admin only)
     */
    public function admin_unban_user() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        delete_user_meta($user_id, 'jg_map_banned');
        delete_user_meta($user_id, 'jg_map_ban_until');

        // Log action
        JG_Map_Activity_Log::log(
            'unban_user',
            'user',
            $user_id,
            sprintf('Odbanowano użytkownika %s', $user->display_name)
        );

        wp_send_json_success(array('message' => 'Ban usunięty'));
    }

    /**
     * Toggle user restriction (admin only)
     */
    public function admin_toggle_user_restriction() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);
        $restriction_type = sanitize_text_field($_POST['restriction_type'] ?? '');

        $allowed_restrictions = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places', 'photo_upload');
        if (!$user_id || !in_array($restriction_type, $allowed_restrictions)) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        $meta_key = 'jg_map_ban_' . $restriction_type;
        $current_value = get_user_meta($user_id, $meta_key, true);

        if ($current_value) {
            // Remove restriction
            delete_user_meta($user_id, $meta_key);
            $is_restricted = false;
            $message = 'Blokada usunięta';
            $action = 'usunięto';
        } else {
            // Add restriction
            update_user_meta($user_id, $meta_key, '1');
            $is_restricted = true;
            $message = 'Blokada dodana';
            $action = 'dodano';
        }

        // Log action
        JG_Map_Activity_Log::log(
            'toggle_user_restriction',
            'user',
            $user_id,
            sprintf('%s blokadę %s dla użytkownika %s', ucfirst($action), $restriction_type, $user->display_name)
        );

        wp_send_json_success(array(
            'message' => $message,
            'is_restricted' => $is_restricted
        ));
    }

    /**
     * Get user restrictions and ban status (admin only)
     */
    public function get_user_restrictions() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        $ban_status = get_user_meta($user_id, 'jg_map_banned', true);
        $ban_until = get_user_meta($user_id, 'jg_map_ban_until', true);
        $is_banned = self::is_user_banned($user_id);

        $restrictions = array();
        $restriction_types = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places', 'photo_upload');
        foreach ($restriction_types as $type) {
            if (get_user_meta($user_id, 'jg_map_ban_' . $type, true)) {
                $restrictions[] = $type;
            }
        }

        wp_send_json_success(array(
            'is_banned' => $is_banned,
            'ban_status' => $ban_status,
            'ban_until' => $ban_until,
            'restrictions' => $restrictions
        ));
    }

    /**
     * Check if user is banned - helper function
     */
    public static function is_user_banned($user_id) {
        if (!$user_id) {
            return false;
        }

        $ban_status = get_user_meta($user_id, 'jg_map_banned', true);

        if ($ban_status === 'permanent') {
            return true;
        }

        if ($ban_status === 'temporary') {
            $ban_until = get_user_meta($user_id, 'jg_map_ban_until', true);
            if ($ban_until && strtotime($ban_until) > time()) {
                return true;
            } else {
                // Ban expired, remove it
                delete_user_meta($user_id, 'jg_map_banned');
                delete_user_meta($user_id, 'jg_map_ban_until');
                return false;
            }
        }

        return false;
    }

    /**
     * Check if user has specific restriction
     */
    public static function has_user_restriction($user_id, $restriction_type) {
        if (!$user_id) {
            return false;
        }

        $meta_key = 'jg_map_ban_' . $restriction_type;
        return (bool)get_user_meta($user_id, $meta_key, true);
    }

    /**
     * Get current user's restrictions (for displaying ban banner)
     */
    public function get_my_restrictions() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_success(array(
                'is_banned' => false,
                'restrictions' => array()
            ));
            return;
        }

        $ban_status = get_user_meta($user_id, 'jg_map_banned', true);
        $ban_until = get_user_meta($user_id, 'jg_map_ban_until', true);
        $is_banned = self::is_user_banned($user_id);

        $restrictions = array();
        $restriction_types = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places', 'photo_upload');
        foreach ($restriction_types as $type) {
            if (get_user_meta($user_id, 'jg_map_ban_' . $type, true)) {
                $restrictions[] = $type;
            }
        }

        wp_send_json_success(array(
            'is_banned' => $is_banned,
            'ban_status' => $ban_status,
            'ban_until' => $ban_until,
            'restrictions' => $restrictions
        ));
    }

    /**
     * Get user's daily limits (admin only)
     */
    public function admin_get_user_limits() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        // Check if user is admin
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        if (user_can($user_id, 'manage_options')) {
            wp_send_json_success(array(
                'places_remaining' => 999,
                'reports_remaining' => 999,
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

        wp_send_json_success(array(
            'places_remaining' => max(0, $places_limit - $places_used),
            'reports_remaining' => max(0, $reports_limit - $reports_used),
            'places_limit' => $places_limit,
            'reports_limit' => $reports_limit,
            'is_admin' => false
        ));
    }

    /**
     * Set user's daily limits (admin only)
     */
    public function admin_set_user_limits() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);
        $places_limit = intval($_POST['places_limit'] ?? 5);
        $reports_limit = intval($_POST['reports_limit'] ?? 5);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        // Validate limits
        if ($places_limit < 0 || $reports_limit < 0) {
            wp_send_json_error(array('message' => 'Limity nie mogą być ujemne'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Store the actual custom limits
        update_user_meta($user_id, 'jg_map_daily_places_limit', $places_limit);
        update_user_meta($user_id, 'jg_map_daily_reports_limit', $reports_limit);

        // Get current usage
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

        // Log action
        JG_Map_Activity_Log::log(
            'set_user_limits',
            'user',
            $user_id,
            sprintf('Ustawiono limity dla %s (miejsca: %d, zgłoszenia: %d)', $user->display_name, $places_limit, $reports_limit)
        );

        wp_send_json_success(array(
            'message' => 'Limity ustawione',
            'places_remaining' => max(0, $places_limit - $places_used),
            'reports_remaining' => max(0, $reports_limit - $reports_used),
            'places_limit' => $places_limit,
            'reports_limit' => $reports_limit
        ));
    }

    /**
     * Get user's monthly photo upload limit and usage (admin only)
     */
    public function admin_get_user_photo_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Use existing get_monthly_photo_usage method
        $monthly_data = $this->get_monthly_photo_usage($user_id);

        wp_send_json_success(array(
            'used_mb' => $monthly_data['used_mb'],
            'limit_mb' => $monthly_data['limit_mb'],
            'used_bytes' => $monthly_data['used_bytes']
        ));
    }

    /**
     * Set user's monthly photo upload limit (admin only)
     */
    public function admin_set_user_photo_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);
        $limit_mb = intval($_POST['limit_mb'] ?? 100);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        if ($limit_mb < 1) {
            wp_send_json_error(array('message' => 'Limit musi być większy niż 0'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Set custom limit
        update_user_meta($user_id, 'jg_map_photo_custom_limit', $limit_mb);

        // Log action
        JG_Map_Activity_Log::log(
            'set_user_photo_limit',
            'user',
            $user_id,
            sprintf('Ustawiono limit zdjęć dla %s: %d MB', $user->display_name, $limit_mb)
        );

        wp_send_json_success(array(
            'message' => 'Limit zdjęć ustawiony',
            'limit_mb' => $limit_mb
        ));
    }

    /**
     * Reset user's monthly photo upload limit to default 100MB (admin only)
     */
    public function admin_reset_user_photo_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Remove custom limit, falling back to default 100MB
        delete_user_meta($user_id, 'jg_map_photo_custom_limit');

        // Log action
        JG_Map_Activity_Log::log(
            'reset_user_photo_limit',
            'user',
            $user_id,
            sprintf('Zresetowano limit zdjęć dla %s do domyślnego (100MB)', $user->display_name)
        );

        // Get current usage
        $monthly_data = $this->get_monthly_photo_usage($user_id);

        wp_send_json_success(array(
            'message' => 'Limit zresetowany do domyślnego (100MB)',
            'used_mb' => $monthly_data['used_mb'],
            'limit_mb' => $monthly_data['limit_mb']
        ));
    }

    /**
     * Get user's daily edit limit (admin only)
     */
    public function admin_get_user_edit_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Get current edit count and date
        $edit_count = intval(get_user_meta($user_id, 'jg_map_edits_count', true));
        $edit_date = get_user_meta($user_id, 'jg_map_edits_date', true);
        $today = current_time('Y-m-d');

        // Reset counter if it's a new day
        if ($edit_date !== $today) {
            $edit_count = 0;
        }

        wp_send_json_success(array(
            'edit_count' => $edit_count
        ));
    }

    /**
     * Reset user's daily edit limit (admin only)
     */
    public function admin_reset_user_edit_limit() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Reset edit counter
        update_user_meta($user_id, 'jg_map_edits_count', 0);
        update_user_meta($user_id, 'jg_map_edits_date', current_time('Y-m-d'));

        // Log action
        JG_Map_Activity_Log::log(
            'reset_user_edit_limit',
            'user',
            $user_id,
            sprintf('Zresetowano licznik edycji dla %s', $user->display_name)
        );

        wp_send_json_success(array(
            'message' => 'Licznik edycji zresetowany',
            'edit_count' => 0
        ));
    }

    /**
     * Unblock IP address from rate limiting (admin only)
     * Supports both 'login' and 'register' types
     */
    public function admin_unblock_ip() {
        $this->verify_nonce();
        $this->check_admin();

        $ip_hash = sanitize_text_field($_POST['ip_hash'] ?? '');
        $ip_type = sanitize_text_field($_POST['ip_type'] ?? 'login');

        if (empty($ip_hash)) {
            wp_send_json_error(array('message' => 'Nieprawidłowy hash IP'));
            exit;
        }

        // Validate ip_type
        if (!in_array($ip_type, array('login', 'register'))) {
            $ip_type = 'login';
        }

        // Delete all three transients (attempts count, time, and user data)
        $transient_key = 'jg_rate_limit_' . $ip_type . '_' . $ip_hash;
        $transient_time_key = 'jg_rate_limit_time_' . $ip_type . '_' . $ip_hash;
        $transient_userdata_key = 'jg_rate_limit_userdata_' . $ip_type . '_' . $ip_hash;

        delete_transient($transient_key);
        delete_transient($transient_time_key);
        delete_transient($transient_userdata_key);

        // Log action
        JG_Map_Activity_Log::log(
            'unblock_ip',
            'system',
            null,
            sprintf('Odblokowano adres IP (typ: %s, hash: %s)', $ip_type, $ip_hash)
        );

        wp_send_json_success(array('message' => 'Adres IP odblokowany pomyślnie'));
    }

    /**
     * Admin delete user - removes all user data including pins, photos, and account
     */
    public function admin_delete_user() {
        $this->verify_nonce();
        $this->check_admin();

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID użytkownika'));
            exit;
        }

        // Check if user exists
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        // Prevent deleting admins and moderators
        $is_admin = user_can($user_id, 'manage_options');
        $is_moderator = user_can($user_id, 'jg_map_moderate');

        if ($is_admin || $is_moderator) {
            wp_send_json_error(array('message' => 'Nie można usunąć administratorów ani moderatorów'));
            exit;
        }

        // Get all user's points (pins)
        $user_places = JG_Map_Database::get_all_places_with_status('', '', $user_id);

        // Delete all user's points with their images
        if (!empty($user_places)) {
            foreach ($user_places as $place) {
                JG_Map_Database::delete_point($place['id']);
            }
        }

        // Delete all user meta data related to the plugin
        $meta_keys = array(
            'jg_map_ban_until',
            'jg_map_restrict_edit',
            'jg_map_restrict_delete',
            'jg_map_restrict_add',
            'jg_map_restrict_voting',
            'jg_map_restrict_add_events',
            'jg_map_restrict_add_trivia',
            'jg_map_restrict_photo_upload',
            'jg_map_daily_reset',
            'jg_map_daily_places',
            'jg_map_daily_reports',
            'jg_map_edits_count',
            'jg_map_edits_date',
            'jg_map_photo_month',
            'jg_map_photo_used_bytes',
            'jg_map_photo_custom_limit',
            'jg_map_activation_key',
            'jg_map_activation_key_time',
            'jg_map_account_status',
            'jg_map_reset_key',
            'jg_map_reset_key_time'
        );

        foreach ($meta_keys as $meta_key) {
            delete_user_meta($user_id, $meta_key);
        }

        // Delete the user account
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $deleted = wp_delete_user($user_id);

        if (!$deleted) {
            wp_send_json_error(array('message' => 'Wystąpił błąd podczas usuwania użytkownika'));
            exit;
        }

        // Log action
        JG_Map_Activity_Log::log(
            'delete_user',
            'user',
            $user_id,
            sprintf('Trwale usunięto konto użytkownika %s (wraz z %d miejscami)', $user->display_name, count($user_places))
        );

        wp_send_json_success(array('message' => 'Użytkownik został pomyślnie usunięty'));
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

        wp_send_json_success(array(
            'message' => 'Wyróżniony obraz ustawiony',
            'featured_image_index' => $image_index
        ));
    }

    /**
     * Get current user data
     */
    public function get_current_user() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
            exit;
        }

        $current_user = wp_get_current_user();
        $is_admin = user_can($current_user->ID, 'manage_options');
        $is_moderator = user_can($current_user->ID, 'jg_map_moderate');

        wp_send_json_success(array(
            'display_name' => $current_user->display_name,
            'email' => $current_user->user_email,
            'is_admin' => $is_admin,
            'is_moderator' => $is_moderator,
            'can_delete_profile' => !$is_admin && !$is_moderator
        ));
    }

    /**
     * Get current user statistics for profile modal
     */
    public function get_my_stats() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
            exit;
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();

        $table_points = $wpdb->prefix . 'jg_map_points';
        $table_votes = $wpdb->prefix . 'jg_map_votes';
        $table_reports = $wpdb->prefix . 'jg_map_reports';
        $table_history = $wpdb->prefix . 'jg_map_history';
        $table_point_visits = $wpdb->prefix . 'jg_map_point_visits';

        // Count added places (published)
        $places_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'publish'",
            $user_id
        ));

        // Count pending places
        $pending_places_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'pending'",
            $user_id
        ));

        // Count edits submitted
        $edits_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_history WHERE user_id = %d AND action_type = 'edit'",
            $user_id
        ));

        // Count approved edits
        $approved_edits_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_history WHERE user_id = %d AND action_type = 'edit' AND status = 'approved'",
            $user_id
        ));

        // Count photos added
        $photos_data = $wpdb->get_results($wpdb->prepare(
            "SELECT images FROM $table_points WHERE author_id = %d AND status = 'publish' AND images IS NOT NULL AND images != ''",
            $user_id
        ), ARRAY_A);

        $photos_count = 0;
        foreach ($photos_data as $point_data) {
            if (!empty($point_data['images'])) {
                $images = json_decode($point_data['images'], true);
                if (is_array($images)) {
                    $photos_count += count($images);
                }
            }
        }

        // Count upvotes given
        $upvotes_given = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_votes WHERE user_id = %d AND vote_type = 'up'",
            $user_id
        ));

        // Count downvotes given
        $downvotes_given = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_votes WHERE user_id = %d AND vote_type = 'down'",
            $user_id
        ));

        // Count reports submitted
        $reports_submitted = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_reports WHERE user_id = %d",
            $user_id
        ));

        // Count visits to places
        $places_visited = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT point_id) FROM $table_point_visits WHERE user_id = %d",
            $user_id
        ));

        // Get votes received on user's places
        $upvotes_received = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_votes v
             INNER JOIN $table_points p ON v.point_id = p.id
             WHERE p.author_id = %d AND v.vote_type = 'up'",
            $user_id
        ));

        $downvotes_received = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_votes v
             INNER JOIN $table_points p ON v.point_id = p.id
             WHERE p.author_id = %d AND v.vote_type = 'down'",
            $user_id
        ));

        // User metadata
        $is_admin = current_user_can('manage_options');
        $is_moderator = current_user_can('jg_map_moderate');

        // Check if user has sponsored places
        $has_sponsored = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
            $user_id
        )) > 0;

        $role = 'Użytkownik';
        if ($is_admin) {
            $role = 'Administrator';
        } elseif ($is_moderator) {
            $role = 'Moderator';
        }

        wp_send_json_success(array(
            'user_id' => $user_id,
            'display_name' => $current_user->display_name,
            'member_since' => $current_user->user_registered . ' UTC',
            'role' => $role,
            'is_admin' => $is_admin,
            'is_moderator' => $is_moderator,
            'has_sponsored' => $has_sponsored,
            'stats' => array(
                'places_added' => intval($places_count),
                'places_pending' => intval($pending_places_count),
                'edits_submitted' => intval($edits_count),
                'edits_approved' => intval($approved_edits_count),
                'photos_added' => intval($photos_count),
                'upvotes_given' => intval($upvotes_given),
                'downvotes_given' => intval($downvotes_given),
                'upvotes_received' => intval($upvotes_received),
                'downvotes_received' => intval($downvotes_received),
                'reports_submitted' => intval($reports_submitted),
                'places_visited' => intval($places_visited)
            )
        ));
    }

    /**
     * Update user profile (password only)
     */
    public function update_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
            exit;
        }

        $user_id = get_current_user_id();
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($password)) {
            wp_send_json_error('Proszę podać nowe hasło');
            exit;
        }

        // Update user data (only password)
        $user_data = array(
            'ID' => $user_id,
            'user_pass' => $password
        );

        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            exit;
        }

        wp_send_json_success('Hasło zostało zmienione');
    }

    /**
     * Delete user profile - removes all user data including pins, photos, and account
     */
    public function delete_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
            exit;
        }

        $user_id = get_current_user_id();
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Check if user is admin or moderator - they cannot delete their own profiles this way
        $is_admin = user_can($user_id, 'manage_options');
        $is_moderator = user_can($user_id, 'jg_map_moderate');

        if ($is_admin || $is_moderator) {
            wp_send_json_error('Administratorzy i moderatorzy nie mogą usunąć swoich profili przez tę opcję');
            exit;
        }

        if (empty($password)) {
            wp_send_json_error('Proszę podać hasło w celu potwierdzenia');
            exit;
        }

        // Verify password
        $user = wp_get_current_user();
        if (!wp_check_password($password, $user->user_pass, $user_id)) {
            wp_send_json_error('Nieprawidłowe hasło');
            exit;
        }

        // Get all user's points (pins)
        $user_places = JG_Map_Database::get_all_places_with_status('', '', $user_id);

        // Delete all user's points with their images
        if (!empty($user_places)) {
            foreach ($user_places as $place) {
                JG_Map_Database::delete_point($place['id']);
            }
        }

        // Delete all user meta data related to the plugin
        $meta_keys = array(
            'jg_map_ban_until',
            'jg_map_restrict_edit',
            'jg_map_restrict_delete',
            'jg_map_restrict_add',
            'jg_map_restrict_voting',
            'jg_map_restrict_add_events',
            'jg_map_restrict_add_trivia',
            'jg_map_restrict_photo_upload',
            'jg_map_daily_reset',
            'jg_map_daily_places',
            'jg_map_daily_reports',
            'jg_map_edits_count',
            'jg_map_edits_date',
            'jg_map_photo_month',
            'jg_map_photo_used_bytes',
            'jg_map_photo_custom_limit',
            'jg_map_activation_key',
            'jg_map_activation_key_time',
            'jg_map_account_status',
            'jg_map_reset_key',
            'jg_map_reset_key_time'
        );

        foreach ($meta_keys as $meta_key) {
            delete_user_meta($user_id, $meta_key);
        }

        // Log user out before deletion
        wp_logout();

        // Delete the user account
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $deleted = wp_delete_user($user_id);

        if (!$deleted) {
            wp_send_json_error('Wystąpił błąd podczas usuwania profilu');
            exit;
        }

        wp_send_json_success('Profil został pomyślnie usunięty');
    }

    /**
     * Resend activation email
     */
    public function resend_activation_email() {
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($username) && empty($email)) {
            wp_send_json_error('Proszę podać nazwę użytkownika lub email');
            exit;
        }

        // Find user by username or email
        $user = null;
        if (!empty($username)) {
            $user = get_user_by('login', $username);
            if (!$user) {
                $user = get_user_by('email', $username);
            }
        } elseif (!empty($email)) {
            $user = get_user_by('email', $email);
        }

        if (!$user) {
            wp_send_json_error('Nie znaleziono użytkownika');
            exit;
        }

        // Check if account is already activated
        $account_status = get_user_meta($user->ID, 'jg_map_account_status', true);
        if ($account_status === 'active') {
            wp_send_json_error('To konto jest już aktywowane. Możesz się zalogować.');
            exit;
        }

        // Rate limiting for resend attempts (max 3 per hour)
        $ip = $this->get_user_ip();
        $user_data = array(
            'ip' => $ip,
            'username' => $user->user_login,
            'email' => $user->user_email
        );

        // Check rate limit
        $rate_check = $this->check_rate_limit('resend_activation', $ip, 3, 3600, $user_data, false);
        if (!$rate_check['allowed']) {
            $minutes = isset($rate_check['minutes_remaining']) ? $rate_check['minutes_remaining'] : 60;
            wp_send_json_error('Zbyt wiele prób wysłania linku aktywacyjnego. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.');
            exit;
        }

        // Generate new activation key
        $activation_key = wp_generate_password(32, false);
        update_user_meta($user->ID, 'jg_map_activation_key', $activation_key);
        update_user_meta($user->ID, 'jg_map_activation_key_time', time());

        // Send activation email
        $activation_link = add_query_arg(
            array(
                'action' => 'activate',
                'key' => $activation_key,
                'email' => rawurlencode($user->user_email)
            ),
            home_url()
        );

        $subject = 'Aktywacja konta - Jeleniogórzanie to my';
        $message = "Witaj " . $user->user_login . ",\n\n";
        $message .= "Aby aktywować swoje konto w serwisie Jeleniogórzanie to my, kliknij w poniższy link:\n\n";
        $message .= $activation_link . "\n\n";
        $message .= "Link aktywacyjny jest ważny przez 24 godziny.\n\n";
        $message .= "UWAGA: Link musi zostać otwarty w tej samej przeglądarce, w której dokonałeś rejestracji.\n\n";
        $message .= "Jeśli nie rejestrowałeś się w naszym serwisie, zignoruj tę wiadomość.\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół Jeleniogórzanie to my";

        $email_sent = $this->send_plugin_email($user->user_email, $subject, $message);

        if (!$email_sent) {
            wp_send_json_error('Wystąpił błąd podczas wysyłania emaila');
            exit;
        }

        // Increment rate limit after successful send
        $this->check_rate_limit('resend_activation', $ip, 3, 3600, $user_data, true);

        wp_send_json_success('Link aktywacyjny został wysłany ponownie. Sprawdź swoją skrzynkę email.');
    }

    /**
     * Login user via AJAX
     */
    public function login_user() {
        // Honeypot check - if filled, it's a bot
        $honeypot = isset($_POST['honeypot']) ? $_POST['honeypot'] : '';
        if (!empty($honeypot)) {
            // Bot detected - silently fail
            wp_send_json_error('Nieprawidłowa nazwa użytkownika lub hasło');
            exit;
        }

        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Check if user exists and is admin/moderator - they bypass rate limiting
        $user_check = get_user_by('login', $username);
        if (!$user_check) {
            $user_check = get_user_by('email', $username);
        }

        $bypass_rate_limit = false;
        if ($user_check) {
            $is_admin = user_can($user_check->ID, 'manage_options');
            $is_moderator = user_can($user_check->ID, 'jg_map_moderate');
            $bypass_rate_limit = $is_admin || $is_moderator;
        }

        // Rate limiting check (skip for admins and moderators)
        $ip = $this->get_user_ip();
        if (!$bypass_rate_limit) {
            // Prepare user data for rate limiting tracking
            $user_data = array(
                'ip' => $ip,
                'username' => $username,
                'email' => $user_check ? $user_check->user_email : ''
            );

            // Check rate limit without incrementing (3 max attempts for login)
            $rate_check = $this->check_rate_limit('login', $ip, 3, 900, $user_data, false);
            if (!$rate_check['allowed']) {
                $minutes = isset($rate_check['minutes_remaining']) ? $rate_check['minutes_remaining'] : 15;
                $seconds = $minutes * 60;
                wp_send_json_error(array(
                    'message' => 'Zbyt wiele prób logowania. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.',
                    'type' => 'rate_limit',
                    'seconds_remaining' => $seconds,
                    'action' => 'login'
                ));
                exit;
            }
        }

        if (empty($username) || empty($password)) {
            wp_send_json_error('Proszę wypełnić wszystkie pola');
            exit;
        }

        $credentials = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true
        );

        $user = wp_signon($credentials, false);

        if (is_wp_error($user)) {
            // Increment rate limit only on failed login (skip for admins/moderators)
            if (!$bypass_rate_limit) {
                $user_data = array(
                    'ip' => $ip,
                    'username' => $username,
                    'email' => $user_check ? $user_check->user_email : ''
                );
                $this->check_rate_limit('login', $ip, 3, 900, $user_data, true);

                // Check if we just hit the rate limit
                $rate_check_after = $this->check_rate_limit('login', $ip, 3, 900, $user_data, false);
                if (!$rate_check_after['allowed']) {
                    // Now blocked - return rate limit error with countdown
                    $minutes = isset($rate_check_after['minutes_remaining']) ? $rate_check_after['minutes_remaining'] : 15;
                    $seconds = $minutes * 60;
                    wp_send_json_error(array(
                        'message' => 'Wykorzystałeś wszystkie próby logowania. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.',
                        'type' => 'rate_limit',
                        'seconds_remaining' => $seconds,
                        'action' => 'login'
                    ));
                    exit;
                } else {
                    // Still have attempts remaining - show warning
                    $attempts_remaining = isset($rate_check_after['attempts_remaining']) ? $rate_check_after['attempts_remaining'] : 0;
                    $attempts_used = isset($rate_check_after['attempts_used']) ? $rate_check_after['attempts_used'] : 0;

                    if ($attempts_remaining === 1) {
                        // Last attempt warning
                        wp_send_json_error(array(
                            'message' => 'Nieprawidłowa nazwa użytkownika lub hasło.',
                            'type' => 'attempts_warning',
                            'attempts_remaining' => $attempts_remaining,
                            'attempts_used' => $attempts_used,
                            'is_last_attempt' => true,
                            'warning' => 'UWAGA: To była Twoja przedostatnia próba! Jeśli kolejna próba się nie powiedzie, logowanie zostanie zablokowane na 15 minut.',
                            'action' => 'login'
                        ));
                        exit;
                    } else if ($attempts_remaining > 0) {
                        // Regular warning
                        wp_send_json_error(array(
                            'message' => 'Nieprawidłowa nazwa użytkownika lub hasło.',
                            'type' => 'attempts_warning',
                            'attempts_remaining' => $attempts_remaining,
                            'attempts_used' => $attempts_used,
                            'is_last_attempt' => false,
                            'action' => 'login'
                        ));
                        exit;
                    }
                }
            }
            wp_send_json_error('Nieprawidłowa nazwa użytkownika lub hasło');
            exit;
        }

        // Clear rate limit on successful login
        if (!$bypass_rate_limit) {
            $this->clear_rate_limit('login', $ip);
        }

        // Set authentication cookie for wp-admin access
        wp_set_auth_cookie($user->ID, true, is_ssl());

        // Set current user
        wp_set_current_user($user->ID);
        do_action('wp_login', $user->user_login, $user);

        // Check if email is verified
        $account_status = get_user_meta($user->ID, 'jg_map_account_status', true);
        if ($account_status === 'pending') {
            wp_logout(); // Logout the user
            wp_send_json_error(array(
                'message' => 'Twoje konto nie zostało jeszcze aktywowane. Sprawdź swoją skrzynkę email i kliknij w link aktywacyjny.',
                'type' => 'pending_activation',
                'username' => $user->user_login,
                'email' => $user->user_email
            ));
            exit;
        }

        // Check Elementor maintenance mode for users without bypass permission
        $is_admin = user_can($user->ID, 'manage_options');
        $is_moderator = user_can($user->ID, 'jg_map_moderate');
        $can_bypass_maintenance = user_can($user->ID, 'jg_map_bypass_maintenance');

        if (!$is_admin && !$is_moderator && !$can_bypass_maintenance) {
            $maintenance_mode = get_option('elementor_maintenance_mode_mode');

            if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
                // Log user out
                wp_logout();

                wp_send_json_error('Trwa konserwacja serwisu. Zapraszamy później. Przepraszamy za utrudnienia.');
                exit;
            }
        }

        wp_send_json_success('Zalogowano pomyślnie');
    }

    /**
     * Register user via AJAX
     */
    public function register_user() {
        // Check if registration is enabled - server-side validation
        $registration_enabled = get_option('jg_map_registration_enabled', 1);
        if (!$registration_enabled || $registration_enabled === '0' || $registration_enabled === 0) {
            $message = get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.');
            wp_send_json_error($message);
            exit;
        }

        // Get form data first for rate limiting tracking
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Rate limiting check with user data for admin panel display (check only, don't increment yet)
        // 5 max attempts for registration
        $ip = $this->get_user_ip();
        $user_data = array(
            'ip' => $ip,
            'username' => $username,
            'email' => $email
        );
        $rate_check = $this->check_rate_limit('register', $ip, 5, 3600, $user_data, false);
        if (!$rate_check['allowed']) {
            $minutes = isset($rate_check['minutes_remaining']) ? $rate_check['minutes_remaining'] : 60;
            $seconds = $minutes * 60;
            $hours = ceil($minutes / 60);
            $message = '';
            if ($hours >= 1) {
                $message = 'Wykorzystałeś wszystkie próby rejestracji. Spróbuj ponownie za ' . $hours . ' ' . ($hours === 1 ? 'godzinę' : 'godzin') . '.';
            } else {
                $message = 'Wykorzystałeś wszystkie próby rejestracji. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.';
            }
            wp_send_json_error(array(
                'message' => $message,
                'type' => 'rate_limit',
                'seconds_remaining' => $seconds,
                'action' => 'register'
            ));
            exit;
        }

        // Honeypot check - if filled, it's a bot
        $honeypot = isset($_POST['honeypot']) ? $_POST['honeypot'] : '';
        if (!empty($honeypot)) {
            // Bot detected - silently fail with generic error
            wp_send_json_error('Wystąpił błąd podczas rejestracji. Spróbuj ponownie.');
            exit;
        }

        // Check Elementor maintenance mode - block registration completely
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');

        if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
            wp_send_json_error('Trwają prace konserwacyjne. Rejestracja nowych kont została tymczasowo wstrzymana. Zapraszamy później.');
            exit;
        }

        // Increment rate limit counter HERE - counts all non-bot registration attempts
        // This protects against spam/flood even with invalid data (5 max attempts)
        $this->check_rate_limit('register', $ip, 5, 3600, $user_data, true);

        // Check if we just hit the rate limit after incrementing
        $rate_check_after = $this->check_rate_limit('register', $ip, 5, 3600, $user_data, false);
        $attempts_remaining = isset($rate_check_after['attempts_remaining']) ? $rate_check_after['attempts_remaining'] : 0;
        $attempts_used = isset($rate_check_after['attempts_used']) ? $rate_check_after['attempts_used'] : 0;

        if (!$rate_check_after['allowed']) {
            $minutes = isset($rate_check_after['minutes_remaining']) ? $rate_check_after['minutes_remaining'] : 60;
            $seconds = $minutes * 60;
            $hours = ceil($minutes / 60);
            $message = '';
            if ($hours >= 1) {
                $message = 'Wykorzystałeś wszystkie próby rejestracji. Spróbuj ponownie za ' . $hours . ' ' . ($hours === 1 ? 'godzinę' : 'godzin') . '.';
            } else {
                $message = 'Wykorzystałeś wszystkie próby rejestracji. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.';
            }
            wp_send_json_error(array(
                'message' => $message,
                'type' => 'rate_limit',
                'seconds_remaining' => $seconds,
                'action' => 'register'
            ));
            exit;
        }

        // Helper function for error response with attempts remaining
        $send_validation_error = function($message) use ($attempts_remaining, $attempts_used) {
            if ($attempts_remaining === 1) {
                // Last attempt warning
                wp_send_json_error(array(
                    'message' => $message,
                    'type' => 'attempts_warning',
                    'attempts_remaining' => $attempts_remaining,
                    'attempts_used' => $attempts_used,
                    'is_last_attempt' => true,
                    'warning' => 'UWAGA: To była Twoja przedostatnia próba! Jeśli kolejna próba się nie powiedzie, rejestracja zostanie zablokowana na 1 godzinę.',
                    'action' => 'register'
                ));
            } else if ($attempts_remaining > 0) {
                // Regular warning
                wp_send_json_error(array(
                    'message' => $message,
                    'type' => 'attempts_warning',
                    'attempts_remaining' => $attempts_remaining,
                    'attempts_used' => $attempts_used,
                    'is_last_attempt' => false,
                    'action' => 'register'
                ));
            } else {
                // No attempts info
                wp_send_json_error($message);
            }
        };

        if (empty($username) || empty($email) || empty($password)) {
            $send_validation_error('Proszę wypełnić wszystkie pola');
            exit;
        }

        // Validate email
        if (!is_email($email)) {
            $send_validation_error('Nieprawidłowy adres email');
            exit;
        }

        // Validate password strength
        $password_check = $this->validate_password_strength($password);
        if (!$password_check['valid']) {
            $send_validation_error($password_check['error']);
            exit;
        }

        // Check if username exists
        if (username_exists($username)) {
            $send_validation_error('Ta nazwa użytkownika jest już zajęta');
            exit;
        }

        // Check if email exists
        if (email_exists($email)) {
            $send_validation_error('Ten adres email jest już zarejestrowany');
            exit;
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            $send_validation_error($user_id->get_error_message());
            exit;
        }

        // Generate activation key
        $activation_key = wp_generate_password(32, false);
        update_user_meta($user_id, 'jg_map_activation_key', $activation_key);
        update_user_meta($user_id, 'jg_map_activation_key_time', time());
        update_user_meta($user_id, 'jg_map_account_status', 'pending');

        // Store session ID for security - link should be activated from same session
        $session_id = session_id();
        if (empty($session_id)) {
            // Start session if not started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
                $session_id = session_id();
            }
        }
        if (!empty($session_id)) {
            update_user_meta($user_id, 'jg_map_activation_session', $session_id);
        }

        // Send activation email
        $activation_link = home_url('/?jg_activate=' . $activation_key);
        $subject = 'Aktywacja konta - ' . get_bloginfo('name');
        $message = "Witaj {$username}!\n\n";
        $message .= "Dziękujemy za rejestrację na " . get_bloginfo('name') . ".\n\n";
        $message .= "Aby aktywować swoje konto, kliknij w poniższy link:\n";
        $message .= $activation_link . "\n\n";
        $message .= "Link jest ważny przez 48 godzin.\n\n";
        $message .= "Jeśli to nie Ty zarejestrowałeś to konto, zignoruj tę wiadomość.\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół Jeleniórzanie to my";

        $this->send_plugin_email($email, $subject, $message);

        // Don't auto login - user must verify email first
        wp_send_json_success('Rejestracja zakończona pomyślnie! Sprawdź swoją skrzynkę email i kliknij w link aktywacyjny.');
    }

    public function forgot_password() {
        // Rate limiting check (check only, don't increment yet)
        $ip = $this->get_user_ip();
        $rate_check = $this->check_rate_limit('forgot_password', $ip, 3, 1800, array(), false);
        if (!$rate_check['allowed']) {
            $minutes = isset($rate_check['minutes_remaining']) ? $rate_check['minutes_remaining'] : 30;
            wp_send_json_error('Zbyt wiele prób resetowania hasła. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.');
            exit;
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email)) {
            wp_send_json_error('Proszę podać adres email');
            exit;
        }

        // Validate email format
        if (!is_email($email)) {
            wp_send_json_error('Nieprawidłowy adres email');
            exit;
        }

        // Check if user exists with this email
        $user = get_user_by('email', $email);

        if (!$user) {
            // Don't reveal if email exists or not for security
            // Send success message anyway
            // Increment rate limit counter even if user doesn't exist
            $this->check_rate_limit('forgot_password', $ip, 3, 1800, array(), true);
            wp_send_json_success('Jeśli konto z tym adresem email istnieje, wysłaliśmy link do resetowania hasła.');
            exit;
        }

        // Generate reset key
        $reset_key = wp_generate_password(32, false);
        update_user_meta($user->ID, 'jg_map_reset_key', $reset_key);
        update_user_meta($user->ID, 'jg_map_reset_key_time', time());

        // Send reset email
        $reset_link = home_url('/?jg_reset=' . $reset_key);
        $subject = 'Resetowanie hasła - ' . get_bloginfo('name');
        $message = "Witaj {$user->user_login}!\n\n";
        $message .= "Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta na " . get_bloginfo('name') . ".\n\n";
        $message .= "Aby ustawić nowe hasło, kliknij w poniższy link:\n";
        $message .= $reset_link . "\n\n";
        $message .= "Link jest ważny przez 24 godziny.\n\n";
        $message .= "Jeśli to nie Ty zleciłeś resetowanie hasła, zignoruj tę wiadomość.\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół Jeleniórzanie to my";

        $this->send_plugin_email($email, $subject, $message);

        // Increment rate limit counter after successful password reset request
        $this->check_rate_limit('forgot_password', $ip, 3, 1800, array(), true);

        wp_send_json_success('Link do resetowania hasła został wysłany na Twój adres email.');
    }

    /**
     * Check if current user should be logged out due to maintenance mode or permission changes
     */
    public function check_user_session_status() {
        // Get user ID from session (even if user doesn't exist anymore)
        $user_id = get_current_user_id();

        // If no user ID in session, not logged in
        if (!$user_id) {
            wp_send_json_success(array(
                'should_logout' => false,
                'reason' => 'not_logged_in'
            ));
            return;
        }

        // Check if user still exists (might have been deleted by admin)
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_success(array(
                'should_logout' => true,
                'reason' => 'user_deleted',
                'message' => 'Twoje konto zostało usunięte przez administratora.',
                'requires_confirmation' => true
            ));
            return;
        }

        // Get current permissions
        $is_admin = user_can($user_id, 'manage_options');
        $is_moderator = user_can($user_id, 'jg_map_moderate');
        $can_bypass_maintenance = user_can($user_id, 'jg_map_bypass_maintenance');

        // Get sponsored places count for premium status
        global $wpdb;
        $points_table = $wpdb->prefix . 'jg_map_points';
        $sponsored_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $points_table WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
            $user_id
        ));

        // Check if maintenance mode is active
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');
        $is_maintenance = ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon');

        // Users who can bypass don't need to be logged out during maintenance
        if ($is_admin || $is_moderator || $can_bypass_maintenance) {
            wp_send_json_success(array(
                'should_logout' => false,
                'reason' => 'has_permissions',
                'user_data' => array(
                    'is_admin' => $is_admin,
                    'is_moderator' => $is_moderator,
                    'sponsored_count' => (int)$sponsored_count
                )
            ));
            return;
        }

        // Regular user during maintenance = should be logged out
        if ($is_maintenance) {
            wp_send_json_success(array(
                'should_logout' => true,
                'reason' => 'maintenance_mode',
                'message' => 'Strona przechodzi w tryb konserwacji. Zapraszamy później. Przepraszamy za utrudnienia.'
            ));
            return;
        }

        // All good - return user data for change detection
        wp_send_json_success(array(
            'should_logout' => false,
            'reason' => 'ok',
            'user_data' => array(
                'is_admin' => $is_admin,
                'is_moderator' => $is_moderator,
                'sponsored_count' => (int)$sponsored_count
            )
        ));
    }

    /**
     * Logout current user via AJAX
     */
    public function logout_user() {
        wp_logout();
        wp_send_json_success('Wylogowano pomyślnie');
    }

    /**
     * Check registration status - returns current registration availability
     */
    public function check_registration_status() {
        $enabled = get_option('jg_map_registration_enabled', 1);
        $message = get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.');

        wp_send_json_success(array(
            'enabled' => (bool) $enabled,
            'message' => $message
        ));
    }

    /**
     * Get notification counts for admins/moderators (real-time updates)
     */
    public function get_notification_counts() {
        $this->verify_nonce();

        // Only for admins and moderators
        if (!current_user_can('manage_options') && !current_user_can('jg_map_moderate')) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();
        $reports_table = JG_Map_Database::get_reports_table();
        $history_table = JG_Map_Database::get_history_table();

        // Ensure history table exists
        JG_Map_Database::ensure_history_table();

        $pending_points = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $points_table WHERE status = %s",
            'pending'
        ));
        $pending_edits = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $history_table WHERE status = %s AND action_type = %s",
            'pending',
            'edit'
        ));
        $pending_reports = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT r.point_id)
             FROM $reports_table r
             INNER JOIN $points_table p ON r.point_id = p.id
             WHERE r.status = %s AND p.status = %s",
            'pending',
            'publish'
        ));
        $pending_deletions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $points_table WHERE is_deletion_requested = %d AND status = %s",
            1,
            'publish'
        ));

        wp_send_json_success(array(
            'points' => intval($pending_points),
            'edits' => intval($pending_edits),
            'reports' => intval($pending_reports),
            'deletions' => intval($pending_deletions),
            'total' => intval($pending_points) + intval($pending_edits) + intval($pending_reports) + intval($pending_deletions)
        ));
    }

    /**
     * Reverse geocode proxy - bypass CSP restrictions
     * Makes server-side request to Nominatim API
     */
    public function reverse_geocode() {
        // Get lat/lng from request
        $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
        $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;

        if (!$lat || !$lng) {
            wp_send_json_error(array('message' => 'Brak współrzędnych'));
            return;
        }

        // Validate coordinates
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            wp_send_json_error(array('message' => 'Nieprawidłowe współrzędne'));
            return;
        }

        // Build Nominatim API URL
        $url = sprintf(
            'https://nominatim.openstreetmap.org/reverse?format=json&lat=%s&lon=%s&addressdetails=1',
            $lat,
            $lng
        );

        // Make server-side request (not subject to browser CSP)
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'JG-Map-Plugin/1.0 (WordPress)',
            ),
        ));

        // Check for errors
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Błąd połączenia z serwerem geokodowania',
                'error' => $response->get_error_message()
            ));
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            wp_send_json_error(array(
                'message' => 'Błąd serwera geokodowania',
                'status' => $status_code
            ));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            wp_send_json_error(array('message' => 'Błąd przetwarzania odpowiedzi'));
            return;
        }

        // Return the data
        wp_send_json_success($data);
    }

    /**
     * Search address (autocomplete for FAB)
     * Returns multiple results for autocomplete suggestions
     */
    public function search_address() {
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (empty($query) || strlen($query) < 3) {
            wp_send_json_error(array('message' => 'Zapytanie za krótkie (min. 3 znaki)'));
            return;
        }

        // Add context of Jelenia Góra if not already in query
        $searchQuery = $query;
        if (stripos($query, 'jelenia') === false && stripos($query, 'góra') === false) {
            $searchQuery = $query . ', Jelenia Góra, Poland';
        }

        // Build Nominatim API URL
        $url = sprintf(
            'https://nominatim.openstreetmap.org/search?format=json&q=%s&limit=5&addressdetails=1',
            urlencode($searchQuery)
        );

        // Make server-side request with proper headers
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'JG-Interactive-Map/1.0 (WordPress)',
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Błąd połączenia z serwerem geokodowania',
                'error' => $response->get_error_message()
            ));
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            wp_send_json_error(array(
                'message' => 'Błąd serwera geokodowania',
                'status' => $status_code
            ));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data === null) {
            wp_send_json_error(array('message' => 'Błąd parsowania odpowiedzi'));
            return;
        }

        // Return results directly - JavaScript will handle display
        wp_send_json_success($data);
    }

    /**
     * Track statistics for sponsored pins
     * Tracks: views, phone_clicks, website_clicks, social_clicks, cta_clicks, gallery_clicks
     */
    public function track_stat() {
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
                    } else {
                        // First visit - insert
                        $wpdb->insert($visitor_table, array(
                            'point_id' => $point_id,
                            'user_id' => $current_user_id,
                            'visit_count' => 1,
                            'first_visited' => $current_time,
                            'last_visited' => $current_time
                        ));
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
                    } else {
                        // First visit - insert
                        $wpdb->insert($visitor_table, array(
                            'point_id' => $point_id,
                            'visitor_fingerprint' => $fingerprint,
                            'visit_count' => 1,
                            'first_visited' => $current_time,
                            'last_visited' => $current_time
                        ));
                    }
                }

                // Increment view counter and update last viewed
                $updates = array(
                    'stats_views' => 'COALESCE(stats_views, 0) + 1',
                    'stats_last_viewed' => $current_time
                );

                // Track unique visitor if flagged
                if ($is_unique) {
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

    /**
     * Normalize social media URLs
     * Accepts: full URL, domain URL, or profile name
     * Returns: full valid URL or empty string
     */
    private function normalize_social_url($input, $platform) {
        if (empty($input)) {
            return '';
        }

        // Sanitize input
        $input = sanitize_text_field(trim($input));

        // Platform base URLs
        $base_urls = array(
            'facebook' => 'https://facebook.com/',
            'instagram' => 'https://instagram.com/',
            'linkedin' => 'https://linkedin.com/in/',
            'tiktok' => 'https://tiktok.com/@'
        );

        // Already a full URL starting with http(s)
        if (preg_match('/^https?:\/\//i', $input)) {
            return esc_url_raw($input);
        }

        // Remove @ if present (common for TikTok/Instagram)
        $input = ltrim($input, '@');

        // Remove domain if user pasted it
        $patterns = array(
            'facebook' => array('facebook.com/', 'fb.com/', 'fb.me/', 'm.facebook.com/'),
            'instagram' => array('instagram.com/', 'instagr.am/', 'm.instagram.com/'),
            'linkedin' => array('linkedin.com/in/', 'linkedin.com/company/', 'lnkd.in/'),
            'tiktok' => array('tiktok.com/@', 'tiktok.com/', 'vm.tiktok.com/')
        );

        if (isset($patterns[$platform])) {
            foreach ($patterns[$platform] as $pattern) {
                $input = preg_replace('/^' . preg_quote($pattern, '/') . '/i', '', $input);
            }
        }

        // LinkedIn company pages need different base
        if ($platform === 'linkedin' && stripos($input, 'company/') === 0) {
            $input = preg_replace('/^company\//i', '', $input);
            return 'https://linkedin.com/company/' . urlencode($input);
        }

        // Build full URL - don't use esc_url_raw as it may strip valid social media URLs
        return $base_urls[$platform] . urlencode($input);
    }

    /**
     * Get points for sidebar widget with sorting and filtering
     */
    public function get_sidebar_points() {
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
            $votes_count = isset($votes_counts_map[$point_id]) ? $votes_counts_map[$point_id] : 0;
            $point['votes_count'] = $votes_count;
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
                    return $b['votes_count'] - $a['votes_count'];
                });
                break;

            case 'votes_asc':
                usort($regular_points, function($a, $b) {
                    return $a['votes_count'] - $b['votes_count'];
                });
                break;
        }

        // Merge sponsored at the top
        $sorted_points = array_merge($sponsored_points, $regular_points);

        // Build simplified result for sidebar
        $result = array();
        foreach ($sorted_points as $point) {
            $result[] = array(
                'id' => $point['id'],
                'title' => $point['title'],
                'slug' => $point['slug'],
                'type' => $point['type'],
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'is_promo' => (bool)$point['is_promo'],
                'votes_count' => $point['votes_count'],
                'created_at' => $point['created_at'],
                'date' => array(
                    'raw' => $point['created_at'],
                    'human' => human_time_diff(strtotime(get_date_from_gmt($point['created_at'])), current_time('timestamp')) . ' temu'
                ),
                'featured_image' => $this->get_featured_image_url($point)
            );
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
     * Restore point from trash (admin only)
     */
    public function admin_restore_point() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['post_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID miejsca'));
            exit;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) {
            wp_send_json_error(array('message' => 'Miejsce nie istnieje'));
            exit;
        }

        if ($point['status'] !== 'trash') {
            wp_send_json_error(array('message' => 'Miejsce nie znajduje się w koszu'));
            exit;
        }

        // Restore point to publish status
        JG_Map_Database::update_point($point_id, array('status' => 'publish'));

        // Log action
        JG_Map_Activity_Log::log(
            'restore_point',
            'point',
            $point_id,
            sprintf('Przywrócono miejsce z kosza: %s', $point['title'])
        );

        wp_send_json_success(array('message' => 'Miejsce przywrócone z kosza'));
    }

    /**
     * Empty trash - permanently delete all trashed points (admin only)
     */
    public function admin_empty_trash() {
        $this->verify_nonce();
        $this->check_admin();

        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get all trashed points for logging
        $trashed_points = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title FROM $points_table WHERE status = %s",
            'trash'
        ), ARRAY_A);

        if (empty($trashed_points)) {
            wp_send_json_error(array('message' => 'Kosz jest pusty'));
            exit;
        }

        $deleted_count = 0;

        // Delete each trashed point
        foreach ($trashed_points as $point) {
            $point_id = $point['id'];

            // Delete point using the same method as admin_delete_point
            $deleted = JG_Map_Database::delete_point($point_id);

            if ($deleted !== false) {
                $deleted_count++;

                // Queue sync event
                JG_Map_Sync_Manager::get_instance()->queue_point_deleted($point_id, array(
                    'admin_deleted' => true,
                    'point_title' => $point['title'],
                    'from_trash' => true
                ));

                // Log individual deletion
                JG_Map_Activity_Log::log(
                    'delete_point',
                    'point',
                    $point_id,
                    sprintf('Trwale usunięto miejsce z kosza: %s', $point['title'])
                );
            }
        }

        // Log bulk action
        JG_Map_Activity_Log::log(
            'empty_trash',
            'system',
            0,
            sprintf('Opróżniono kosz - usunięto %d miejsc', $deleted_count)
        );

        wp_send_json_success(array(
            'message' => sprintf('Kosz został opróżniony. Usunięto %d miejsc.', $deleted_count),
            'deleted_count' => $deleted_count
        ));
    }

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
     * Toggle edit lock on a point (admin/moderator only)
     */
    public function admin_toggle_edit_lock() {
        $this->verify_nonce();
        $this->check_admin();

        $point_id = intval($_POST['point_id'] ?? 0);

        if (!$point_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe ID punktu'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // First check if point exists
        $point = $wpdb->get_row($wpdb->prepare("SELECT id, title FROM $table WHERE id = %d", $point_id), ARRAY_A);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje (ID: ' . $point_id . ')'));
            exit;
        }

        // Ensure edit_locked column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'edit_locked'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN edit_locked tinyint(1) DEFAULT 0 AFTER author_hidden");
        }

        // Get current lock status
        $current_status = intval($wpdb->get_var($wpdb->prepare("SELECT edit_locked FROM $table WHERE id = %d", $point_id)));

        // Toggle the lock
        $new_status = $current_status ? 0 : 1;
        $result = $wpdb->update(
            $table,
            array('edit_locked' => $new_status),
            array('id' => $point_id)
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Błąd zapisu do bazy danych'));
            exit;
        }

        // Log the action (with error handling)
        if (class_exists('JG_Map_Activity_Log')) {
            JG_Map_Activity_Log::log(
                $new_status ? 'lock_edit' : 'unlock_edit',
                'point',
                $point_id,
                sprintf('%s blokadę edycji miejsca: %s', $new_status ? 'Włączono' : 'Wyłączono', $point['title'])
            );
        }

        // Queue sync (with error handling)
        if (class_exists('JG_Map_Sync_Manager')) {
            JG_Map_Sync_Manager::get_instance()->queue_point_updated($point_id);
        }

        wp_send_json_success(array(
            'message' => $new_status ? 'Blokada edycji włączona' : 'Blokada edycji wyłączona',
            'edit_locked' => (bool)$new_status
        ));
    }

    /**
     * Change point owner (admin/moderator only)
     */
    public function admin_change_owner() {
        $this->verify_nonce();

        try {
            $this->check_admin();
        } catch (Exception $e) {
            throw $e;
        }

        $point_id = intval($_POST['point_id'] ?? 0);
        $new_owner_id = intval($_POST['new_owner_id'] ?? 0);

        if (!$point_id || !$new_owner_id) {
            wp_send_json_error(array('message' => 'Nieprawidłowe dane'));
            exit;
        }

        // Check if new owner exists
        $new_owner = get_userdata($new_owner_id);
        if (!$new_owner) {
            wp_send_json_error(array('message' => 'Użytkownik nie istnieje'));
            exit;
        }

        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Get current point data
        $point = $wpdb->get_row($wpdb->prepare("SELECT id, title, author_id FROM $table WHERE id = %d", $point_id), ARRAY_A);

        if (!$point) {
            wp_send_json_error(array('message' => 'Punkt nie istnieje'));
            exit;
        }

        $old_owner_id = $point['author_id'];
        $old_owner = get_userdata($old_owner_id);
        $old_owner_name = $old_owner ? $old_owner->display_name : 'Nieznany';

        // Update owner
        $wpdb->update(
            $table,
            array('author_id' => $new_owner_id),
            array('id' => $point_id)
        );

        // Log the action
        JG_Map_Activity_Log::log(
            'change_owner',
            'point',
            $point_id,
            sprintf('Zmieniono właściciela miejsca "%s" z %s na %s', $point['title'], $old_owner_name, $new_owner->display_name)
        );

        // Queue sync
        JG_Map_Sync_Manager::get_instance()->queue_point_updated($point_id);

        wp_send_json_success(array(
            'message' => 'Właściciel został zmieniony na: ' . $new_owner->display_name,
            'new_owner_id' => $new_owner_id,
            'new_owner_name' => $new_owner->display_name
        ));
    }

    /**
     * Search users for owner change modal (admin/moderator only)
     */
    public function admin_search_users() {
        $this->verify_nonce();

        try {
            $this->check_admin();
        } catch (Exception $e) {
            throw $e;
        }

        $search = sanitize_text_field($_POST['search'] ?? '');
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        $args = array(
            'number' => $per_page,
            'offset' => $offset,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email', 'user_registered')
        );

        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total = $user_query->get_total();

        $users_data = array();
        foreach ($users as $user) {
            $users_data[] = array(
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'registered' => date('Y-m-d', strtotime($user->user_registered))
            );
        }

        wp_send_json_success(array(
            'users' => $users_data,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ));
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

        $key = sanitize_key($_POST['key'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');
        $icon = sanitize_text_field($_POST['icon'] ?? '📍');

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
            'label' => $label,
            'icon' => $icon
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

        $key = sanitize_key($_POST['key'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');
        $icon = sanitize_text_field($_POST['icon'] ?? '📍');

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
        $categories[$key] = array(
            'label' => $label,
            'icon' => $icon
        );
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
}
