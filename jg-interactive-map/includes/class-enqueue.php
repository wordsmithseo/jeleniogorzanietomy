<?php
/**
 * Enqueue scripts and styles
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Enqueue {

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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'jg_map')) {
            return;
        }

        // Leaflet CSS
        wp_enqueue_style(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            array(),
            '1.9.4'
        );

        // Leaflet MarkerCluster CSS
        wp_enqueue_style(
            'leaflet-markercluster',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
            array(),
            '1.5.3'
        );

        wp_enqueue_style(
            'leaflet-markercluster-default',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
            array(),
            '1.5.3'
        );

        // Plugin CSS
        wp_enqueue_style(
            'jg-map-style',
            JG_MAP_PLUGIN_URL . 'assets/css/jg-map.css',
            array(),
            JG_MAP_VERSION
        );

        // Leaflet JS
        wp_enqueue_script(
            'leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            array(),
            '1.9.4',
            true
        );

        // Leaflet MarkerCluster JS
        wp_enqueue_script(
            'leaflet-markercluster',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
            array('leaflet'),
            '1.5.3',
            true
        );

        // Plugin JS
        wp_enqueue_script(
            'jg-map-script',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-map.js',
            array('jquery', 'leaflet', 'leaflet-markercluster'),
            JG_MAP_VERSION,
            true
        );

        // Localize script with config
        wp_localize_script(
            'jg-map-script',
            'JG_MAP_CFG',
            array(
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('jg_map_nonce'),
                'isLoggedIn' => is_user_logged_in(),
                'isAdmin' => current_user_can('manage_options'),
                'currentUserId' => get_current_user_id(),
                'loginUrl' => wp_login_url(get_permalink()),
                'defaults' => array(
                    'lat' => 50.904,
                    'lng' => 15.734,
                    'zoom' => 13
                ),
                'strings' => array(
                    'loading' => __('Ładowanie mapy...', 'jg-map'),
                    'error' => __('Błąd ładowania mapy', 'jg-map'),
                    'loginRequired' => __('Musisz być zalogowany', 'jg-map'),
                    'confirmReport' => __('Czy na pewno zgłosić to miejsce?', 'jg-map'),
                    'confirmDelete' => __('Czy na pewno usunąć?', 'jg-map'),
                )
            )
        );
    }

    /**
     * Enqueue admin scripts and styles (for future admin panel)
     */
    public function enqueue_admin_assets($hook) {
        // Only on plugin admin pages
        if (strpos($hook, 'jg-map') === false) {
            return;
        }

        wp_enqueue_style(
            'jg-map-admin-style',
            JG_MAP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            JG_MAP_VERSION
        );

        wp_enqueue_script(
            'jg-map-admin-script',
            JG_MAP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            JG_MAP_VERSION,
            true
        );
    }
}
