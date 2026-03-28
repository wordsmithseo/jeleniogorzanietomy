<?php
/**
 * PHPUnit bootstrap file for JG Interactive Map plugin tests.
 *
 * Uses WordPress test suite (wp-phpunit) when available,
 * falls back to a lightweight stub environment so tests
 * can always run without a full WordPress installation.
 */

// Composer autoloader
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Try loading WordPress test suite
$wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

if (file_exists($wp_tests_dir . '/includes/functions.php')) {
    // WordPress test environment available
    require_once $wp_tests_dir . '/includes/functions.php';

    tests_add_filter('muplugins_loaded', function () {
        require dirname(__DIR__) . '/jg-interactive-map.php';
    });

    require_once $wp_tests_dir . '/includes/bootstrap.php';

    define('JG_MAP_TESTS_WP', true);
} else {
    // Lightweight stub environment – no WordPress installation needed
    define('JG_MAP_TESTS_WP', false);
    define('ABSPATH', '/tmp/fake-wp/');

    if (!defined('JG_MAP_VERSION')) {
        define('JG_MAP_VERSION', '3.25.4');
    }
    if (!defined('JG_MAP_PLUGIN_DIR')) {
        define('JG_MAP_PLUGIN_DIR', dirname(__DIR__) . '/');
    }
    if (!defined('JG_MAP_PLUGIN_URL')) {
        define('JG_MAP_PLUGIN_URL', 'https://example.com/wp-content/plugins/jg-interactive-map/');
    }

    // ── WordPress function stubs ──────────────────────────────────────
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) { return trim(strip_tags($str)); }
    }
    if (!function_exists('esc_html')) {
        function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('esc_attr')) {
        function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    }
    if (!function_exists('esc_url')) {
        function esc_url($url) { return filter_var($url, FILTER_SANITIZE_URL) ?: ''; }
    }
    if (!function_exists('esc_url_raw')) {
        function esc_url_raw($url) { return filter_var($url, FILTER_SANITIZE_URL) ?: ''; }
    }
    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($data) { return strip_tags($data, '<p><br><a><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><img>'); }
    }
    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags($string) { return strip_tags($string); }
    }
    if (!function_exists('absint')) {
        function absint($val) { return abs(intval($val)); }
    }
    if (!function_exists('sanitize_email')) {
        function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL) ?: ''; }
    }
    if (!function_exists('is_email')) {
        function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
    }
    if (!function_exists('wp_generate_password')) {
        function wp_generate_password($length = 12, $special = true) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            if ($special) $chars .= '!@#$%^&*()';
            $pass = '';
            for ($i = 0; $i < $length; $i++) $pass .= $chars[random_int(0, strlen($chars) - 1)];
            return $pass;
        }
    }
    if (!function_exists('__')) {
        function __($text, $domain = 'default') { return $text; }
    }
    if (!function_exists('esc_sql')) {
        function esc_sql($data) { return addslashes($data); }
    }
    if (!function_exists('plugin_basename')) {
        function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
    }

    // Transient stubs
    $GLOBALS['_jg_test_transients'] = [];
    if (!function_exists('get_transient')) {
        function get_transient($key) { return $GLOBALS['_jg_test_transients'][$key] ?? false; }
    }
    if (!function_exists('set_transient')) {
        function set_transient($key, $value, $expiration = 0) { $GLOBALS['_jg_test_transients'][$key] = $value; return true; }
    }
    if (!function_exists('delete_transient')) {
        function delete_transient($key) { unset($GLOBALS['_jg_test_transients'][$key]); return true; }
    }

    // Option stubs
    $GLOBALS['_jg_test_options'] = [];
    if (!function_exists('get_option')) {
        function get_option($key, $default = false) { return $GLOBALS['_jg_test_options'][$key] ?? $default; }
    }
    if (!function_exists('update_option')) {
        function update_option($key, $value) { $GLOBALS['_jg_test_options'][$key] = $value; return true; }
    }
    if (!function_exists('delete_option')) {
        function delete_option($key) { unset($GLOBALS['_jg_test_options'][$key]); return true; }
    }

    // User stubs
    if (!function_exists('get_current_user_id')) {
        function get_current_user_id() { return $GLOBALS['_jg_test_current_user_id'] ?? 0; }
    }
    if (!function_exists('current_user_can')) {
        function current_user_can($cap) { return $GLOBALS['_jg_test_user_caps'][$cap] ?? false; }
    }
    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in() { return get_current_user_id() > 0; }
    }
    if (!function_exists('user_can')) {
        function user_can($user_id, $cap) { return $GLOBALS['_jg_test_user_caps'][$cap] ?? false; }
    }
    if (!function_exists('get_userdata')) {
        function get_userdata($user_id) {
            if (isset($GLOBALS['_jg_test_users'][$user_id])) {
                return (object) $GLOBALS['_jg_test_users'][$user_id];
            }
            return false;
        }
    }
    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action = -1) { return $nonce === 'valid_test_nonce' ? 1 : false; }
    }

    // Hook stubs
    if (!function_exists('add_action')) {
        function add_action() {}
    }
    if (!function_exists('add_filter')) {
        function add_filter() {}
    }
    if (!function_exists('do_action')) {
        function do_action() {}
    }
    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value) { return $value; }
    }
    if (!function_exists('register_activation_hook')) {
        function register_activation_hook() {}
    }
    if (!function_exists('register_deactivation_hook')) {
        function register_deactivation_hook() {}
    }
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null) { throw new \RuntimeException(json_encode(['success' => false, 'data' => $data])); }
    }
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data = null) { throw new \RuntimeException(json_encode(['success' => true, 'data' => $data])); }
    }
}
