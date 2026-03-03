<?php
/**
 * JG Map - Google Search Console Index Checker
 *
 * Checks URL indexing status via the GSC URL Inspection API.
 * Uses a service account with manual JWT generation (no Composer dependencies).
 * Runs checks asynchronously via WP-Cron to avoid slowing down page loads.
 */

if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_GSC_Index_Checker {

    const CRON_HOOK = 'jg_map_gsc_index_check';
    const BATCH_SIZE = 50;
    const CACHE_TTL = 7 * DAY_IN_SECONDS;      // 7 days
    const ERROR_CACHE_TTL = 6 * HOUR_IN_SECONDS; // 6 hours on error
    const OPTION_KEY = 'jg_map_gsc_service_account';
    const TOKEN_TRANSIENT = 'jg_map_gsc_access_token';

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Schedule hourly cron if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }

        add_action(self::CRON_HOOK, array($this, 'run_batch_check'));

        // AJAX handler for test/manual trigger
        add_action('wp_ajax_jg_gsc_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_jg_gsc_flush_cache', array($this, 'ajax_flush_cache'));
    }

    /**
     * Check if GSC integration is configured and active.
     */
    public static function is_configured() {
        $sa = get_option(self::OPTION_KEY, '');
        if (empty($sa)) {
            return false;
        }
        $data = json_decode($sa, true);
        return !empty($data['client_email']) && !empty($data['private_key']);
    }

    /**
     * Get the cached index status for a point.
     * Returns 'yes', 'no', or 'unknown'.
     * Does NOT make API calls — only reads cache.
     */
    public function get_cached_status($point) {
        if (!self::is_configured()) {
            return 'unknown';
        }

        if (empty($point['slug']) || empty($point['type'])) {
            return 'unknown';
        }

        $url = home_url('/' . $point['type'] . '/' . $point['slug'] . '/');
        $transient_key = 'jg_gsc_idx_' . md5($url);

        $cached = get_transient($transient_key);
        if ($cached !== false) {
            return $cached; // 'yes', 'no', or 'unknown'
        }

        // No cache yet — will be checked by cron
        return 'unknown';
    }

    /**
     * WP-Cron: check a batch of URLs that need (re-)checking.
     * Picks the oldest/missing cached entries first.
     */
    public function run_batch_check() {
        if (!self::is_configured()) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jg_map_points';

        // Get all published points
        $points = $wpdb->get_results(
            "SELECT id, title, slug, type, content, status FROM {$table} WHERE status IN ('approved', 'published') AND slug != '' ORDER BY id ASC",
            ARRAY_A
        );

        if (empty($points)) {
            return;
        }

        // Filter to those that need checking (no cache or expired cache)
        $to_check = array();
        foreach ($points as $point) {
            if (empty($point['slug']) || empty($point['type'])) {
                continue;
            }
            $url = home_url('/' . $point['type'] . '/' . $point['slug'] . '/');
            $transient_key = 'jg_gsc_idx_' . md5($url);
            $cached = get_transient($transient_key);
            if ($cached === false) {
                $to_check[] = $point;
            }
            if (count($to_check) >= self::BATCH_SIZE) {
                break;
            }
        }

        if (empty($to_check)) {
            return;
        }

        // Get access token
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return;
        }

        $site_url = home_url('/');

        foreach ($to_check as $point) {
            $url = home_url('/' . $point['type'] . '/' . $point['slug'] . '/');
            $status = $this->check_url($access_token, $url, $site_url);
            $transient_key = 'jg_gsc_idx_' . md5($url);

            if ($status === 'yes' || $status === 'no') {
                set_transient($transient_key, $status, self::CACHE_TTL);
            } else {
                set_transient($transient_key, 'unknown', self::ERROR_CACHE_TTL);
            }

            // Small delay to respect rate limits (600 QPM = 10/sec)
            usleep(150000); // 150ms
        }
    }

    /**
     * Check a single URL via the GSC URL Inspection API.
     * Returns 'yes', 'no', or 'unknown'.
     */
    private function check_url($access_token, $inspection_url, $site_url) {
        $response = wp_remote_post(
            'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'inspectionUrl' => $inspection_url,
                    'siteUrl'       => $site_url,
                    'languageCode'  => 'pl',
                )),
            )
        );

        if (is_wp_error($response)) {
            return 'unknown';
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 429) {
            // Rate limited — stop this batch
            return 'rate_limited';
        }

        if ($code !== 200 || empty($body['inspectionResult']['indexStatusResult'])) {
            return 'unknown';
        }

        $result = $body['inspectionResult']['indexStatusResult'];
        $verdict = $result['verdict'] ?? '';
        $coverage = $result['coverageState'] ?? '';

        // PASS = indexed
        if ($verdict === 'PASS') {
            return 'yes';
        }

        // FAIL or NEUTRAL with explicit coverage states = not indexed
        if ($verdict === 'FAIL' || $verdict === 'NEUTRAL') {
            return 'no';
        }

        return 'unknown';
    }

    /**
     * Get a valid OAuth2 access token.
     * Caches the token in a transient (tokens last ~1 hour).
     */
    private function get_access_token() {
        $cached = get_transient(self::TOKEN_TRANSIENT);
        if ($cached) {
            return $cached;
        }

        $sa_json = get_option(self::OPTION_KEY, '');
        if (empty($sa_json)) {
            return false;
        }

        $sa = json_decode($sa_json, true);
        if (empty($sa['client_email']) || empty($sa['private_key'])) {
            return false;
        }

        $jwt = $this->create_jwt($sa['client_email'], $sa['private_key']);
        if (!$jwt) {
            return false;
        }

        // Exchange JWT for access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 10,
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            return false;
        }

        $expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] : 3600;
        // Cache with 5 min buffer
        set_transient(self::TOKEN_TRANSIENT, $body['access_token'], $expires_in - 300);

        return $body['access_token'];
    }

    /**
     * Create a signed JWT for Google OAuth2 service account authentication.
     * No external libraries needed — uses PHP's built-in openssl.
     */
    private function create_jwt($client_email, $private_key) {
        $now = time();

        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT',
        );

        $payload = array(
            'iss'   => $client_email,
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        );

        $header_b64 = $this->base64url_encode(wp_json_encode($header));
        $payload_b64 = $this->base64url_encode(wp_json_encode($payload));

        $signing_input = $header_b64 . '.' . $payload_b64;

        $private_key_resource = openssl_pkey_get_private($private_key);
        if (!$private_key_resource) {
            return false;
        }

        $signature = '';
        $success = openssl_sign($signing_input, $signature, $private_key_resource, OPENSSL_ALGO_SHA256);

        if (!$success) {
            return false;
        }

        return $signing_input . '.' . $this->base64url_encode($signature);
    }

    /**
     * Base64url encode (RFC 7515).
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * AJAX: Test GSC connection with the configured service account.
     */
    public function ajax_test_connection() {
        check_ajax_referer('jg_gsc_test', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        if (!self::is_configured()) {
            wp_send_json_error('Brak konfiguracji konta serwisowego');
            return;
        }

        // Delete cached token to force re-auth
        delete_transient(self::TOKEN_TRANSIENT);

        $access_token = $this->get_access_token();
        if (!$access_token) {
            wp_send_json_error('Nie udało się uzyskać tokenu dostępu. Sprawdź klucz konta serwisowego.');
            return;
        }

        // Try inspecting the home page as a test
        $site_url = home_url('/');
        $status = $this->check_url($access_token, $site_url, $site_url);

        if ($status === 'unknown') {
            wp_send_json_error('Token OK, ale API zwróciło błąd. Sprawdź czy konto serwisowe ma dostęp do usługi Search Console dla tej witryny.');
            return;
        }

        wp_send_json_success(array(
            'message' => 'Połączenie działa! Strona główna: ' . ($status === 'yes' ? 'zaindeksowana ✅' : 'niezaindeksowana ❌'),
            'status'  => $status,
        ));
    }

    /**
     * AJAX: Flush all GSC index cache to force re-check.
     */
    public function ajax_flush_cache() {
        check_ajax_referer('jg_gsc_flush', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
            return;
        }

        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jg_gsc_idx_%' OR option_name LIKE '_transient_timeout_jg_gsc_idx_%'"
        );

        wp_send_json_success(array(
            'message' => 'Wyczyszczono cache indeksowania. Ponowne sprawdzanie rozpocznie się przy następnym uruchomieniu crona.',
            'deleted' => $deleted,
        ));
    }

    /**
     * Deactivation: clear cron.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
