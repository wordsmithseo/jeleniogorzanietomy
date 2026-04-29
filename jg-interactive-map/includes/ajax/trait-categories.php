<?php
/**
 * Trait: category definitions (report categories, place categories, curiosity categories)
 * and helper static methods used by both frontend and admin.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

trait JG_Ajax_Categories {

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
            'gastronomia' => array('label' => 'Gastronomia', 'icon' => '🍽️', 'schema_type' => 'FoodEstablishment', 'has_menu' => true, 'has_price_range' => true, 'serves_cuisine' => true),
            'kultura' => array('label' => 'Kultura', 'icon' => '🏛️', 'schema_type' => 'Museum'),
            'uslugi' => array('label' => 'Usługi', 'icon' => '🏢', 'schema_type' => 'LocalBusiness'),
            'sport' => array('label' => 'Sport i rekreacja', 'icon' => '⚽', 'schema_type' => 'SportsActivityLocation'),
            'historia' => array('label' => 'Historia i zabytki', 'icon' => '🏰', 'schema_type' => 'LandmarksOrHistoricalBuildings'),
            'zielen' => array('label' => 'Zieleń', 'icon' => '🌲', 'schema_type' => 'Park')
        );
    }

    /**
     * Default curiosity categories configuration
     */
    private static function get_default_curiosity_categories() {
        return array(
            'historyczne' => array('label' => 'Historyczne', 'icon' => '📜', 'schema_type' => 'LandmarksOrHistoricalBuildings'),
            'przyrodnicze' => array('label' => 'Przyrodnicze', 'icon' => '🦋', 'schema_type' => 'TouristAttraction'),
            'architektoniczne' => array('label' => 'Architektoniczne', 'icon' => '🏰', 'schema_type' => 'LandmarksOrHistoricalBuildings'),
            'legendy' => array('label' => 'Legendy i historie', 'icon' => '📖', 'schema_type' => 'TouristAttraction')
        );
    }

    /**
     * Get keys of categories that support menu (has_menu => true)
     */
    public static function get_menu_categories() {
        $cats = self::get_place_categories();
        $keys = array();
        foreach ($cats as $key => $cat) {
            if (!empty($cat['has_menu'])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Get keys of categories that support price range (has_price_range => true)
     */
    public static function get_price_range_categories() {
        $cats = self::get_place_categories();
        $keys = array();
        foreach ($cats as $key => $cat) {
            if (!empty($cat['has_price_range'])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Get keys of categories that serve cuisine (serves_cuisine => true)
     */
    public static function get_serves_cuisine_categories() {
        $cats = self::get_place_categories();
        $keys = array();
        foreach ($cats as $key => $cat) {
            if (!empty($cat['serves_cuisine'])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Get keys of categories that show the business promo box (show_promo => true)
     */
    public static function get_promo_categories() {
        $cats = self::get_place_categories();
        $keys = array();
        foreach ($cats as $key => $cat) {
            if (!empty($cat['show_promo'])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Get map of category_key => offerings_label for categories that support offerings.
     * offerings_label is a non-empty string like "Usługi" or "Produkty".
     */
    public static function get_offerings_categories() {
        $cats   = self::get_place_categories();
        $result = array();
        foreach ($cats as $key => $cat) {
            if (!empty($cat['offerings_label'])) {
                $result[$key] = $cat['offerings_label'];
            }
        }
        return $result;
    }

    /**
     * Get place categories (sorted alphabetically by label)
     */
    public static function get_place_categories() {
        $custom_categories = get_option('jg_map_place_categories', null);
        if ($custom_categories !== null && is_array($custom_categories)) {
            // Merge default metadata (e.g. has_menu, schema_type) into custom categories
            // so flags added after initial setup are not lost.
            $defaults = self::get_default_place_categories();
            $categories = array();
            foreach ($custom_categories as $key => $cat) {
                $categories[$key] = isset($defaults[$key])
                    ? array_merge($defaults[$key], $cat)
                    : $cat;
            }
        } else {
            $categories = self::get_default_place_categories();
        }
        uasort($categories, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        return $categories;
    }

    /**
     * Get curiosity categories (sorted alphabetically by label)
     */
    public static function get_curiosity_categories() {
        $custom_categories = get_option('jg_map_curiosity_categories', null);
        if ($custom_categories !== null && is_array($custom_categories)) {
            $categories = $custom_categories;
        } else {
            $categories = self::get_default_curiosity_categories();
        }
        uasort($categories, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        return $categories;
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
     * Report categories configuration (sorted by group label, then by reason label)
     * Maps category keys to their display labels and group
     * Reads from WordPress options, falls back to defaults
     */
    public static function get_report_categories() {
        $custom_categories = get_option('jg_map_report_reasons', null);
        if ($custom_categories !== null && is_array($custom_categories)) {
            $reasons = $custom_categories;
        } else {
            $reasons = self::get_default_report_categories();
        }
        $groups = self::get_category_groups();
        uasort($reasons, function($a, $b) use ($groups) {
            $groupA = $groups[$a['group'] ?? ''] ?? ($a['group'] ?? '');
            $groupB = $groups[$b['group'] ?? ''] ?? ($b['group'] ?? '');
            $groupCmp = strcmp($groupA, $groupB);
            if ($groupCmp !== 0) return $groupCmp;
            return strcmp($a['label'], $b['label']);
        });
        return $reasons;
    }

    /**
     * Get category groups for display (sorted alphabetically)
     * Reads from WordPress options, falls back to defaults
     */
    public static function get_category_groups() {
        $custom_groups = get_option('jg_map_report_categories', null);
        if ($custom_groups !== null && is_array($custom_groups)) {
            $groups = $custom_groups;
        } else {
            $groups = self::get_default_category_groups();
        }
        asort($groups);
        return $groups;
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

}
