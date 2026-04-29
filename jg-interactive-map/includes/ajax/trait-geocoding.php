<?php
/**
 * Trait: geocoding and address search
 * reverse_geocode, search_address, _fetch_photon, _fetch_nominatim, search_pages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

trait JG_Ajax_Geocoding {

    /**
     * Reverse geocode proxy - bypass CSP restrictions
     * Makes server-side request to Nominatim API
     */
    public function reverse_geocode() {
        // Rate limiting: max 30 requests per IP per minute
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        $rate_key = 'jg_geocode_rate_' . md5($ip);
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count >= 30) {
            wp_send_json_error(array('message' => 'Zbyt wiele zapytań. Spróbuj ponownie za chwilę.'));
            return;
        }
        set_transient($rate_key, $rate_count + 1, 60);

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

        // Cache: round to 4 decimal places (~11m precision) to increase cache hit rate
        $cache_key = 'jg_rgeocode_' . md5(round($lat, 4) . '|' . round($lng, 4));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            wp_send_json_success($cached);
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

        // Cache result for 24 hours — addresses don't change often
        set_transient($cache_key, $data, DAY_IN_SECONDS);

        // Return the data
        wp_send_json_success($data);
    }

    /**
     * Search address (autocomplete for FAB)
     * Returns multiple results for autocomplete suggestions
     */
    public function search_address() {
        $this->verify_nonce();
        // Rate limiting: max 30 requests per IP per minute
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        $rate_key = 'jg_geocode_rate_' . md5($ip);
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count >= 30) {
            wp_send_json_error(array('message' => 'Zbyt wiele zapytań. Spróbuj ponownie za chwilę.'));
            return;
        }
        set_transient($rate_key, $rate_count + 1, 60);

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (empty($query) || strlen($query) < 3) {
            wp_send_json_error(array('message' => 'Zapytanie za krótkie (min. 3 znaki)'));
            return;
        }

        // Strip Polish street type prefixes for cleaner queries
        $street_prefixes = array('ul.', 'al.', 'pl.', 'os.', 'rondo ', 'aleja ', 'ulica ', 'plac ', 'osiedle ');
        $queryForSearch = trim($query);
        foreach ($street_prefixes as $prefix) {
            if (mb_stripos($queryForSearch, $prefix) === 0) {
                $queryForSearch = trim(mb_substr($queryForSearch, mb_strlen($prefix)));
                break;
            }
        }

        // Cache: use normalized query as key
        $cache_key = 'jg_addr_' . md5(strtolower(trim($queryForSearch)));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            wp_send_json_success($cached);
            return;
        }

        // --- PRIMARY: Photon (photon.komoot.io) ---
        // Supports partial/autocomplete queries unlike Nominatim.
        // bbox: min_lon,min_lat,max_lon,max_lat
        $data = $this->_fetch_photon($queryForSearch);

        // --- FALLBACK: Nominatim ---
        // Used when Photon is unreachable. Requires complete words,
        // so we try the full query and — if no results — each word separately.
        if ($data === null) {
            $data = $this->_fetch_nominatim($queryForSearch);
        }

        if ($data === null) {
            wp_send_json_error(array('message' => 'Błąd połączenia z serwerem geokodowania'));
            return;
        }

        // Cache results for 1 hour
        set_transient($cache_key, $data, HOUR_IN_SECONDS);

        wp_send_json_success($data);
    }

    /**
     * Fetch address suggestions from Photon (supports partial/autocomplete queries).
     * Returns flat array of {display_name, lat, lon} or null on failure.
     */
    private function _fetch_photon(string $query): ?array {
        $url = sprintf(
            'https://photon.komoot.io/api/?q=%s&limit=5&bbox=15.58,50.75,15.85,50.98',
            urlencode($query)
        );

        $response = wp_remote_get($url, array(
            'timeout' => 8,
            'headers' => array('User-Agent' => 'JG-Interactive-Map/1.0 (WordPress)'),
        ));

        if (is_wp_error($response)) {
            return null;
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $geojson = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($geojson['features']) || !is_array($geojson['features'])) {
            return null;
        }

        $data = array();
        foreach ($geojson['features'] as $feature) {
            $props  = $feature['properties'] ?? array();
            $coords = $feature['geometry']['coordinates'] ?? array(0, 0);

            $parts = array();
            if (!empty($props['name'])) {
                $parts[] = $props['name'];
            } elseif (!empty($props['street'])) {
                $parts[] = $props['street'];
            }
            if (!empty($props['housenumber'])) {
                $last = count($parts) - 1;
                if ($last >= 0) {
                    $parts[$last] .= ' ' . $props['housenumber'];
                } else {
                    $parts[] = $props['housenumber'];
                }
            }
            if (!empty($props['district']) && $props['district'] !== ($props['city'] ?? '')) {
                $parts[] = $props['district'];
            }
            if (!empty($props['city'])) {
                $parts[] = $props['city'];
            } elseif (!empty($props['county'])) {
                $parts[] = $props['county'];
            }

            $data[] = array(
                'display_name' => implode(', ', array_filter($parts)) ?: ($props['name'] ?? 'Nieznana lokalizacja'),
                'lat'          => (string) $coords[1],
                'lon'          => (string) $coords[0],
            );
        }

        return $data; // may be empty array — that's valid
    }

    /**
     * Fetch address suggestions from Nominatim (fallback when Photon unavailable).
     * Nominatim needs complete words; tries full query then individual words to improve coverage.
     * Returns flat array of {display_name, lat, lon} or null on failure.
     */
    private function _fetch_nominatim(string $query): ?array {
        // viewbox: left,top,right,bottom (min_lon,max_lat,max_lon,min_lat)
        $viewbox  = '15.58,50.98,15.85,50.75';
        $base_url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&bounded=1&countrycodes=pl&viewbox=' . $viewbox . '&q=';

        $attempts = array($query);

        // If multi-word query, also try each word separately and merge unique results
        $words = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) > 1) {
            foreach ($words as $word) {
                if (mb_strlen($word) >= 3) {
                    $attempts[] = $word;
                }
            }
        }

        $seen = array();
        $data = array();

        foreach ($attempts as $attempt) {
            $response = wp_remote_get($base_url . urlencode($attempt), array(
                'timeout' => 8,
                'headers' => array('User-Agent' => 'JG-Interactive-Map/1.0 (WordPress)'),
            ));

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                // If even the first attempt (full query) fails at network level, signal failure
                if ($attempt === $query) {
                    return null;
                }
                continue;
            }

            $results = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($results)) {
                continue;
            }

            foreach ($results as $r) {
                $key = $r['osm_id'] ?? $r['display_name'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $data[] = array(
                    'display_name' => $r['display_name'],
                    'lat'          => (string) $r['lat'],
                    'lon'          => (string) $r['lon'],
                );
                if (count($data) >= 5) {
                    break 2;
                }
            }
        }

        return $data;
    }

    /**
     * Search WordPress pages/posts for admin autocomplete
     */
    public function search_pages() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            return;
        }

        check_ajax_referer('jg_map_search_pages');

        $search = sanitize_text_field($_POST['search'] ?? '');
        if (strlen($search) < 2) {
            wp_send_json_success(array());
            return;
        }

        $pages = get_posts(array(
            'post_type' => array('page', 'post'),
            'post_status' => 'publish',
            's' => $search,
            'posts_per_page' => 10,
            'orderby' => 'relevance',
        ));

        $results = array();
        foreach ($pages as $page) {
            $results[] = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
            );
        }

        wp_send_json_success($results);
    }

}
