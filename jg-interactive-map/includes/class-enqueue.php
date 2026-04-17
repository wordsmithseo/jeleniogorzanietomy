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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_topbar_css'), 5); // Earlier priority

        // Hide admin bar for non-admins
        add_action('after_setup_theme', array($this, 'hide_admin_bar_for_users'));

        // Add mobile nav bar (logo + hamburger) – renders before the top bar
        add_action('wp_body_open', array($this, 'render_nav_bar'), 5);

        // Add custom top bar to the page
        add_action('wp_body_open', array($this, 'render_top_bar'), 10);

        // Add contact footer bar
        add_action('wp_footer', array($this, 'render_footer_bar'), 20);

        // Add jg-has-map body class early (before body content) so CSS can hide Elementor header
        // without FOUC — body_class fires when WP outputs <body class="...">, before any body content
        add_filter('body_class', array($this, 'add_map_body_class'));

        // Immediately hide Elementor site header when map is present (prevents FOUC flash)
        add_action('wp_head', array($this, 'hide_elementor_header_early'), 0);

        // Hide register button on Elementor maintenance screen
        add_action('wp_head', array($this, 'hide_register_on_maintenance'));

        // Disable pinch-to-zoom on mobile (except for map)
        add_action('wp_head', array($this, 'disable_mobile_zoom'), 1);

        // Preconnect to map tile providers for faster first tile load
        add_action('wp_head', array($this, 'add_tile_preconnect'), 2);

        // Handle email activation
        add_action('template_redirect', array($this, 'handle_email_activation'));
        add_action('template_redirect', array($this, 'handle_password_reset'));

        // Redirect unauthenticated users away from /wp-admin/ before WordPress
        // tries to show a login form or 404 — fires early on init so it catches
        // non-logged-in requests before auth_redirect() or admin_init run.
        add_action('init', array($this, 'redirect_unauthenticated_wp_admin'), 1);

        // Block non-admin users from accessing /wp-admin/
        add_action('admin_init', array($this, 'block_non_admin_access'));

        // Dynamic capability: jg_map_manage = manage_options OR jg_map_admin (plugin admin)
        add_filter('user_has_cap', array($this, 'grant_plugin_caps'), 10, 3);
    }

    /**
     * Grant the jg_map_manage capability to full WP admins and plugin admins (jg_map_admin).
     * All plugin pages that require full admin access use jg_map_manage so that
     * plugin admins work without touching every manage_options check.
     */
    public function grant_plugin_caps($allcaps, $caps, $args) {
        if (!empty($allcaps['manage_options']) || !empty($allcaps['jg_map_admin'])) {
            // Full plugin management access
            $allcaps['jg_map_manage'] = true;
            // Also grant moderator access so the plugin menu (using jg_map_moderate) is visible
            $allcaps['jg_map_moderate'] = true;
        }
        return $allcaps;
    }

    /**
     * Hide WordPress admin bar for ALL users (including admins)
     * Admins can access wp-admin via custom top bar button
     */
    public function hide_admin_bar_for_users() {
        show_admin_bar(false);
    }

    /**
     * Redirect unauthenticated visitors away from /wp-admin/ to the home page.
     * Fires at init (priority 1) so it catches the request before WordPress
     * issues its own wp-login.php redirect or any 404 handling kicks in.
     * AJAX and Cron requests are excluded so they can proceed normally.
     */
    public function redirect_unauthenticated_wp_admin() {
        if (!is_admin()) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        if (is_user_logged_in()) {
            return;
        }

        wp_safe_redirect(home_url('/'));
        exit;
    }

    /**
     * Block non-admin users from /wp-admin/ and restrict moderators/plugin-admins
     * to plugin pages only.
     */
    public function block_non_admin_access() {
        // Allow AJAX requests (admin-ajax.php is used by frontend)
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Allow admin-post.php for form submissions
        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'admin-post.php') {
            return;
        }

        // Full WP admin: unrestricted access
        if (current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        // Plugin admin (jg_map_manage via jg_map_admin cap): allow any jg-map-* page
        if (current_user_can('jg_map_manage')) {
            if (strpos($page, 'jg-map-') === 0) {
                return;
            }
            wp_safe_redirect(admin_url('admin.php?page=jg-map-dashboard'));
            exit;
        }

        // Moderator: allow only specific plugin pages
        if (current_user_can('jg_map_moderate')) {
            $allowed = array('jg-map-dashboard', 'jg-map-places', 'jg-map-users', 'jg-map-roles');
            if (in_array($page, $allowed, true)) {
                return;
            }
            wp_safe_redirect(admin_url('admin.php?page=jg-map-dashboard'));
            exit;
        }

        // No relevant permissions: redirect to home page
        wp_safe_redirect(home_url());
        exit;
    }

    /**
     * Enqueue top bar CSS and minimal JS on ALL pages
     */
    public function enqueue_topbar_css() {
        // Load plugin CSS on all pages for top bar styling
        wp_enqueue_style(
            'jg-map-topbar',
            JG_MAP_PLUGIN_URL . 'assets/css/jg-map.css',
            array(),
            JG_MAP_VERSION . '.' . filemtime(JG_MAP_PLUGIN_DIR . 'assets/css/jg-map.css')
        );

        // Inject per-request random CSS for promotional slot elements.
        // Class names change on every page load — no static selector to target.
        wp_add_inline_style('jg-map-topbar', JG_Slot_Keys::get_css());

        // On non-plugin pages (posts, archives, tag/category pages) load the
        // 247 KB stylesheet asynchronously so it does not block rendering.
        // On plugin pages (map, catalog) it loads normally — styles are critical there.
        add_filter('style_loader_tag', array($this, 'defer_css_on_non_plugin_pages'), 10, 4);

        // Load Heartbeat and notifications script for admins/moderators only
        if (is_user_logged_in() && (current_user_can('manage_options') || current_user_can('jg_map_moderate'))) {
            wp_enqueue_script('heartbeat');

            // Load notifications script with jQuery dependency - this loads BEFORE jg-map.js
            wp_enqueue_script(
                'jg-map-notifications',
                JG_MAP_PLUGIN_URL . 'assets/js/jg-notifications.js',
                array('jquery', 'heartbeat'),
                JG_MAP_VERSION,
                false // Load in header to ensure it's available before jg-map.js
            );

            // Localize script with config data
            wp_localize_script('jg-map-notifications', 'jgNotificationsConfig', array(
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('jg_map_nonce'),
                'moderationUrl' => admin_url('admin.php?page=jg-map-places#section-new_pending'),
                'pointsUrl'     => admin_url('admin.php?page=jg-map-places#section-new_pending'),
                'editsUrl'      => admin_url('admin.php?page=jg-map-places#section-edit_pending'),
                'reportsUrl'    => admin_url('admin.php?page=jg-map-places#section-reported'),
                'deletionsUrl'  => admin_url('admin.php?page=jg-map-places#section-deletion_pending'),
            ));
        }

        // Load auth script on ALL pages for login/register buttons
        wp_enqueue_script(
            'jg-map-auth',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-auth.js',
            array('jquery'),
            JG_MAP_VERSION,
            true // Load in footer
        );

        // Localize auth script with config
        wp_localize_script('jg-map-auth', 'JG_AUTH_CFG', array(
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jg_map_nonce'),
            'isAdmin' => (bool) (current_user_can('manage_options') || current_user_can('jg_map_moderate')),
            'registrationEnabled' => (bool) get_option('jg_map_registration_enabled', 1),
            'registrationDisabledMessage' => get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.'),
            'termsUrl' => get_option('jg_map_terms_url', ''),
            'termsContent' => get_option('jg_map_terms_content', ''),
            'privacyUrl' => get_option('jg_map_privacy_url', ''),
            'privacyContent' => get_option('jg_map_privacy_content', ''),
        ));

        // Load session monitor script on ALL pages (for logged in users)
        wp_enqueue_script(
            'jg-map-session-monitor',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-session-monitor.js',
            array('jquery'),
            JG_MAP_VERSION,
            true // Load in footer
        );

        // Localize session monitor script
        wp_localize_script('jg-map-session-monitor', 'JG_SESSION_CFG', array(
            'ajax' => admin_url('admin-ajax.php'),
            'isLoggedIn' => is_user_logged_in()
        ));


        // Twemoji – cross-platform emoji consistency, loaded on ALL pages
        // (top bar contains emoji icons that must look the same everywhere)
        wp_enqueue_script(
            'twemoji',
            'https://cdn.jsdelivr.net/npm/twemoji@14.0.2/dist/twemoji.min.js',
            array(),
            '14.0.2',
            true
        );
        wp_add_inline_script('twemoji', '(function(){function t(){if(window.twemoji)twemoji.parse(document.body,{folder:"svg",ext:".svg"});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",t);}else{t();}})();');
    }

    /**
     * On non-plugin pages (posts, archives, tag/category archives) convert the
     * jg-map.css <link> tag from render-blocking to asynchronous using the
     * preload + onload pattern. On plugin pages (map shortcode, catalog) the
     * stylesheet is critical and loads normally.
     *
     * Called via 'style_loader_tag' filter (registered in enqueue_topbar_css).
     *
     * @param string $html   Full <link> tag generated by WordPress.
     * @param string $handle Stylesheet handle.
     * @param string $href   Stylesheet URL.
     * @param string $media  Media attribute value.
     * @return string Modified (or original) tag HTML.
     */
    public function defer_css_on_non_plugin_pages( $html, $handle, $href, $media ) {
        if ( $handle !== 'jg-map-topbar' ) {
            return $html;
        }

        global $post;
        $is_plugin_page = (
            ( is_a( $post, 'WP_Post' ) && (
                has_shortcode( $post->post_content, 'jg_map' ) ||
                has_shortcode( $post->post_content, 'jg_map_directory' )
            ) ) ||
            get_query_var( 'jg_catalog_category', '' ) !== '' ||
            get_query_var( 'jg_catalog_tag', '' ) !== ''
        );

        if ( $is_plugin_page ) {
            return $html; // critical — keep render-blocking
        }

        // Non-plugin page: load asynchronously.
        // <noscript> fallback ensures browsers with JS disabled still receive the sheet.
        $href_escaped  = esc_url( $href );
        $media_escaped = esc_attr( $media ?: 'all' );
        return sprintf(
            '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n" .
            '<noscript><link rel="stylesheet" href="%s" media="%s"></noscript>' . "\n",
            $href_escaped,
            $href_escaped,
            $media_escaped
        );
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_assets() {
        global $post;

        // Enqueue top-slot script
        wp_enqueue_script(
            'jg-map-ext',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-map-ext.js',
            array('jquery'),
            JG_MAP_VERSION . '-' . time(),
            true
        );

        $k = JG_Slot_Keys::get();
        wp_localize_script('jg-map-ext', 'JG_EXT_CFG', array(
            'ajax' => admin_url('admin-ajax.php'),
            'act'  => array(
                'fetch'  => 'jg_map_ext_fetch',
                'view'   => 'jg_map_ext_ping',
                'engage' => 'jg_map_ext_tap',
            ),
            'cls'  => array(
                'wrap'  => $k['cls_wrap'],
                'box'   => $k['cls_box'],
                'tag'   => $k['cls_tag'],
                'fs'    => $k['cls_fs'],
                'fsIn'  => $k['cls_fs_in'],
                'fsTag' => $k['cls_fs_tag'],
            ),
        ));

        // Only load map assets on pages with map shortcode
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

        // Plugin CSS is already loaded globally via enqueue_topbar_css()

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

        // WordPress Heartbeat for real-time sync (ALL users need this for map updates)
        wp_enqueue_script('heartbeat');

        // Plugin JS - CRITICAL: Add 'heartbeat' as dependency for real-time sync
        // 'twemoji' is already enqueued globally via enqueue_topbar_css()
        $dependencies = array('jquery', 'leaflet', 'leaflet-markercluster', 'heartbeat', 'twemoji');

        // Add notifications script as dependency if user is admin/moderator
        if (is_user_logged_in() && (current_user_can('manage_options') || current_user_can('jg_map_moderate'))) {
            $dependencies[] = 'jg-map-notifications';
        }

        wp_enqueue_script(
            'jg-map-script',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-map.js',
            $dependencies,
            JG_MAP_VERSION,
            true
        );

        // Sidebar script (depends on main map script)
        wp_enqueue_script(
            'jg-map-sidebar',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-sidebar.js',
            array('jquery', 'jg-map-script'),
            JG_MAP_VERSION,
            true
        );

        // Onboarding & help system (depends on main map script)
        wp_enqueue_script(
            'jg-map-onboarding',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-onboarding.js',
            array('jg-map-script'),
            JG_MAP_VERSION,
            true
        );

        // Localize script with config
        $has_sponsored_point = false;
        $ghost_pin = null;
        {
            global $wpdb;
            $pts = JG_Map_Database::get_points_table();

            if (is_user_logged_in()) {
                $has_sponsored_point = (bool) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $pts WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
                    get_current_user_id()
                ));
            }

            // Ghost pin: shown only to users without a sponsored point
            if (!$has_sponsored_point) {
                // ~50 commercial/service locations in Jelenia Góra (non-residential)
                $ghost_candidates = array(
                    array(50.9068, 15.7441), // Galeria Nowy Rynek
                    array(50.9063, 15.7381), // Centrum handlowe
                    array(50.9095, 15.7399), // Plac Ratuszowy
                    array(50.9101, 15.7427), // Ulica Długa (usługi)
                    array(50.9072, 15.7460), // Ulica Bankowa
                    array(50.9055, 15.7410), // Ulica 1 Maja
                    array(50.9087, 15.7356), // Ulica Grodzka
                    array(50.9049, 15.7389), // Parking Śródmiejski
                    array(50.9114, 15.7451), // Ulica Wolności (usługi)
                    array(50.9040, 15.7420), // Rynek Podrynkowy
                    array(50.9031, 15.7374), // Ulica Różana
                    array(50.9120, 15.7395), // Ulica Fryderyka Chopina
                    array(50.9079, 15.7330), // Ulica Widok
                    array(50.9060, 15.7490), // Ulica Piłsudskiego
                    array(50.9035, 15.7445), // Park Norweski
                    array(50.9108, 15.7480), // Ulica Konstytucji 3 Maja
                    array(50.9025, 15.7355), // Ulica Cervi (restauracje)
                    array(50.9145, 15.7418), // Cieplice - centrum
                    array(50.9155, 15.7435), // Cieplice - plac zdrojowy
                    array(50.9132, 15.7402), // Cieplice - ul. Cieplicka
                    array(50.9162, 15.7460), // Cieplice - Park Zdrojowy
                    array(50.9143, 15.7380), // Cieplice - usługi
                    array(50.9078, 15.7520), // Zabobrze - centrum handlowe
                    array(50.9065, 15.7555), // Zabobrze - usługi
                    array(50.9090, 15.7540), // Zabobrze - ul. Gagarina
                    array(50.9021, 15.7480), // Śródmieście południe
                    array(50.9015, 15.7440), // ul. Sudecka (restauracje/usługi)
                    array(50.9098, 15.7302), // Zachodnia strefa usługowa
                    array(50.9082, 15.7280), // ul. Podchorążych
                    array(50.9118, 15.7340), // ul. Kochanowskiego (kawiarnie)
                    array(50.9044, 15.7510), // Centrum Handlowe wschód
                    array(50.9070, 15.7580), // ul. Zgorzelecka (usługi)
                    array(50.9035, 15.7320), // ul. Podwale (restauracje)
                    array(50.9133, 15.7460), // Biura usługowe Cieplice
                    array(50.9058, 15.7350), // ul. Różyckiego
                    array(50.9112, 15.7510), // ul. Jana Pawła II
                    array(50.9089, 15.7410), // Pasaż Świdnicki
                    array(50.9077, 15.7448), // ul. Ogińskiego
                    array(50.9047, 15.7465), // ul. Podgórna
                    array(50.9102, 15.7368), // ul. Nowowiejska
                );

                // Deterministyczny wybór pozycji co 5 minut — JS używa tej samej formuły
                $current_period = (int) floor(time() / 300);
                $idx = $current_period % count($ghost_candidates);

                $chosen = $ghost_candidates[$idx];

                // Verify no existing published pin is within ~50m of this coordinate
                $existing = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT lat, lng FROM $pts WHERE status = 'publish'
                         AND ABS(lat - %f) < 0.0005 AND ABS(lng - %f) < 0.0007",
                        $chosen[0], $chosen[1]
                    )
                );

                if (empty($existing)) {
                    $ghost_pin = array('lat' => $chosen[0], 'lng' => $chosen[1]);
                } else {
                    // Pick a fallback at a random offset far from all existing pins
                    foreach ($ghost_candidates as $candidate) {
                        $nearby = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(*) FROM $pts WHERE status = 'publish'
                                 AND ABS(lat - %f) < 0.0005 AND ABS(lng - %f) < 0.0007",
                                $candidate[0], $candidate[1]
                            )
                        );
                        if (!$nearby) {
                            $ghost_pin = array('lat' => $candidate[0], 'lng' => $candidate[1]);
                            break;
                        }
                    }
                }
            }
        }

        wp_localize_script(
            'jg-map-script',
            'JG_MAP_CFG',
            array(
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('jg_map_nonce'),
                'isLoggedIn' => is_user_logged_in(),
                'onboardingEnabled' => (bool) get_option('jg_map_onboarding_enabled', 1),
                'hasSponsoredPoint' => $has_sponsored_point,
                'ghostPin' => $ghost_pin,
                'isAdmin' => current_user_can('manage_options') || current_user_can('jg_map_moderate'),
                'usersUrl' => (current_user_can('manage_options') || current_user_can('jg_map_moderate'))
                    ? admin_url('admin.php?page=jg-map-users')
                    : '',
                'currentUserId' => get_current_user_id(),
                'currentUserDisplayName' => is_user_logged_in() ? wp_get_current_user()->display_name : '',
                'loginUrl' => wp_login_url(get_permalink()),
                'registrationEnabled' => (bool) get_option('jg_map_registration_enabled', 1),
                'registrationDisabledMessage' => get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
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
                ),
                'reportCategories' => JG_Map_Ajax_Handlers::get_category_groups(),
                'reportReasons' => JG_Map_Ajax_Handlers::get_report_categories(),
                'placeCategories' => JG_Map_Ajax_Handlers::get_place_categories(),
                'curiosityCategories' => JG_Map_Ajax_Handlers::get_curiosity_categories(),
                'menuCategories' => JG_Map_Ajax_Handlers::get_menu_categories(),
                'priceRangeCategories' => JG_Map_Ajax_Handlers::get_price_range_categories(),
                'servesCuisineCategories' => JG_Map_Ajax_Handlers::get_serves_cuisine_categories(),
                'promoCategories' => JG_Map_Ajax_Handlers::get_promo_categories(),
                'offeringsCategories' => JG_Map_Ajax_Handlers::get_offerings_categories(),
                'noPhotoSidebar' => home_url('/wp-content/uploads/2026/02/no_photo_sidebar.jpg'),
                'termsUrl' => get_option('jg_map_terms_url', ''),
                'termsContent' => get_option('jg_map_terms_content', ''),
                'privacyUrl' => get_option('jg_map_privacy_url', ''),
                'privacyContent' => get_option('jg_map_privacy_content', ''),
                'catalogUrl' => self::get_catalog_page_url(),
                'tagBaseUrl' => home_url('/katalog/tag/'),
                'homeUrl'         => home_url('/'),
                'activeChallenge' => JG_Map_Challenges::get_active_with_progress(),
            )
        );

        // Real-time updates now handled directly in jg-map.js via WordPress Heartbeat API
        // and JG_Map_Sync_Manager class. No inline script needed.
        // Heartbeat is enqueued as a dependency of jg-map-script (see above)
    }

    /**
     * Find the URL of the page containing [jg_map_directory] shortcode
     */
    private static function get_catalog_page_url() {
        $cached = get_transient('jg_map_catalog_url');
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $page = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ('page','post') AND post_content LIKE %s LIMIT 1",
                'publish',
                '%' . $wpdb->esc_like('[jg_map_directory') . '%'
            )
        );

        $url = $page ? get_permalink($page) : '';
        set_transient('jg_map_catalog_url', $url, HOUR_IN_SECONDS);

        return $url;
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

    /**
     * Render mobile nav bar (logo + hamburger menu) above the top bar.
     * Visible only on mobile (<= 768 px). Independent of Elementor styling.
     */
    public function render_nav_bar() {
        $logo_url   = 'https://jeleniogorzanietomy.pl/wp-content/uploads/2025/10/jg-logo-1.svg';
        $home_url   = home_url('/');
        $menu_items = get_option('jg_map_nav_menu', array());

        // Resolve "Kontakt" URL from nav menu or fall back to /kontakt
        $contact_url = home_url('/kontakt');
        foreach ($menu_items as $_nav_item) {
            if (!empty($_nav_item['label']) && mb_stripos($_nav_item['label'], 'kontakt') !== false) {
                $contact_url = $_nav_item['url'];
                break;
            }
        }
        ?>
        <!-- JG nav color override: injected in <body> so it loads after Elementor's <head> CSS -->
        <style id="jg-nav-color-fix">
        html body #jg-nav-menu .jg-nav-menu-link { color: #8d2324 !important; }
        html body #jg-nav-menu a.jg-nav-menu-link { color: #8d2324 !important; }
        html body #jg-nav-menu .jg-nav-menu-link:hover,
        html body #jg-nav-menu .jg-nav-menu-link:focus { color: #8d2324 !important; }
        html body .jg-nav-sub-toggle { color: #8d2324 !important; }
        html body a.jg-top-bar-menu-item { color: #ffffff !important; }
        html body a.jg-top-bar-menu-item:hover,
        html body a.jg-top-bar-menu-item:focus { color: #ffffff !important; }
        </style>
        <!-- JG Mobile Nav Bar -->
        <div id="jg-nav-bar" class="jg-nav-bar">
            <a href="<?php echo esc_url($home_url); ?>" class="jg-nav-logo-link" aria-label="Strona główna">
                <img src="<?php echo esc_url($logo_url); ?>" alt="Jelenia Góra to my" class="jg-nav-logo-img" loading="eager">
                <span class="jg-nav-site-title">Jeleniogórzanie to my - Interaktywna mapa Jeleniej Góry</span>
            </a>
            <a href="<?php echo esc_url($contact_url); ?>" class="jg-nav-contact-btn" aria-label="Kontakt z redakcją" title="Kontakt z redakcją">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            </a>
            <button id="jg-hamburger-btn" class="jg-hamburger-btn" aria-label="Otwórz menu" aria-expanded="false" aria-controls="jg-nav-menu" type="button">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
        <!-- Dropdown menu (appended outside nav-bar to allow full-width overlay) -->
        <nav id="jg-nav-menu" class="jg-nav-menu" aria-hidden="true" role="navigation" aria-label="Menu główne">
            <?php if (!empty($menu_items)) : ?>
                <?php foreach ($menu_items as $item) : ?>
                    <?php
                    $label    = isset($item['label'])    ? $item['label']    : '';
                    $url      = isset($item['url'])      ? $item['url']      : '#';
                    $target   = !empty($item['new_tab']) ? '_blank'          : '_self';
                    $rel      = $target === '_blank'     ? 'noopener noreferrer' : '';
                    $children = !empty($item['children']) && is_array($item['children']) ? $item['children'] : array();
                    ?>
                    <?php if (!empty($children)) : ?>
                    <div class="jg-nav-menu-item jg-has-sub">
                        <div class="jg-nav-menu-link-wrap">
                            <a href="<?php echo esc_url($url); ?>"
                               class="jg-nav-menu-link"
                               target="<?php echo esc_attr($target); ?>"
                               <?php echo $rel ? 'rel="' . esc_attr($rel) . '"' : ''; ?>>
                                <?php echo esc_html($label); ?>
                            </a>
                            <button type="button" class="jg-nav-sub-toggle" aria-label="Rozwiń podmenu">
                                <span class="jg-nav-arrow">&#9660;</span>
                            </button>
                        </div>
                        <div class="jg-nav-submenu" aria-hidden="true">
                            <?php foreach ($children as $child) : ?>
                                <?php
                                $clabel  = isset($child['label'])    ? $child['label']    : '';
                                $curl    = isset($child['url'])      ? $child['url']      : '#';
                                $ctarget = !empty($child['new_tab']) ? '_blank'           : '_self';
                                $crel    = $ctarget === '_blank'     ? 'noopener noreferrer' : '';
                                ?>
                                <a href="<?php echo esc_url($curl); ?>"
                                   class="jg-nav-menu-link jg-nav-sub-link"
                                   target="<?php echo esc_attr($ctarget); ?>"
                                   <?php echo $crel ? 'rel="' . esc_attr($crel) . '"' : ''; ?>>
                                    <?php echo esc_html($clabel); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else : ?>
                    <a href="<?php echo esc_url($url); ?>"
                       class="jg-nav-menu-link"
                       target="<?php echo esc_attr($target); ?>"
                       <?php echo $rel ? 'rel="' . esc_attr($rel) . '"' : ''; ?>>
                        <?php echo esc_html($label); ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else : ?>
                <span class="jg-nav-menu-link" style="color:#9ca3af;cursor:default">Brak pozycji menu — skonfiguruj w panelu JG Map → Menu nawigacyjne</span>
            <?php endif; ?>
        </nav>
        <div id="jg-nav-overlay" class="jg-nav-overlay" aria-hidden="true"></div>

        <!-- Small window / landscape orientation overlay -->
        <div id="jg-landscape-overlay" role="alert" aria-live="polite">
            <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="18" rx="2" ry="2"/>
                <polyline points="8 10 12 6 16 10"/>
                <line x1="12" y1="6" x2="12" y2="18"/>
            </svg>
            <p>Rozmiar okna przeglądarki lub ekranu jest zbyt mały, aby wyświetlić mapę. Powiększ okno przeglądarki lub, jeśli korzystasz z telefonu, obróć go do widoku pionowego.</p>
        </div>

        <script>
        (function () {
            var btn     = document.getElementById('jg-hamburger-btn');
            var menu    = document.getElementById('jg-nav-menu');
            var overlay = document.getElementById('jg-nav-overlay');

            if (!btn || !menu) return;

            function openMenu() {
                /* Position dropdown flush below the nav bar's actual bottom edge,
                   accounting for the info bar height when it is visible. */
                var navBar = document.getElementById('jg-nav-bar');
                if (navBar) {
                    var navBottom = Math.round(navBar.getBoundingClientRect().bottom);
                    menu.style.setProperty('top', navBottom + 'px', 'important');
                    menu.style.setProperty('max-height', 'calc(100dvh - ' + navBottom + 'px)', 'important');
                }
                btn.classList.add('jg-nav-open');
                menu.classList.add('jg-nav-open');
                overlay.classList.add('jg-nav-open');
                btn.setAttribute('aria-expanded', 'true');
                menu.setAttribute('aria-hidden', 'false');
            }

            function closeMenu() {
                btn.classList.remove('jg-nav-open');
                menu.classList.remove('jg-nav-open');
                overlay.classList.remove('jg-nav-open');
                btn.setAttribute('aria-expanded', 'false');
                menu.setAttribute('aria-hidden', 'true');
            }

            btn.addEventListener('click', function () {
                btn.classList.contains('jg-nav-open') ? closeMenu() : openMenu();
            });

            overlay.addEventListener('click', closeMenu);

            /* ── Keep --jg-nav-bottom / --jg-footer-top CSS variables in sync ─────
               --jg-nav-bottom : bottom edge of all visible top-nav elements
               --jg-footer-top : top edge of any visible footer (viewport coords).
                                 Equals window.innerHeight when no footer visible.
               Both are used by .jg-modal-bg so modals are symmetrically contained
               between the nav bar and the footer on every page and screen size.

               IMPORTANT: #jg-nav-bar is mobile-only (display:none on desktop).
               On desktop the visible navigation is #jg-custom-top-bar.
               jgGetNavBottom() takes the max bottom among all VISIBLE nav
               elements so it works correctly on every viewport width.
            ────────────────────────────────────────────────────────────────────── */
            var navBarEl  = document.getElementById('jg-nav-bar');
            var infoBarEl = document.getElementById('jg-info-bar');
            /* Shared helpers — exposed globally so jg-map.js can reuse them */
            function jgGetNavBottom() {
                var bottom = 0;
                ['jg-nav-bar', 'jg-info-bar', 'jg-custom-top-bar'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    if (window.getComputedStyle(el).display === 'none') return;
                    var b = Math.round(el.getBoundingClientRect().bottom);
                    if (b > bottom) bottom = b;
                });
                return bottom || 52;
            }
            window.jgGetNavBottom = jgGetNavBottom;
            /* Returns the top edge of any visible footer within the viewport.
               Falls back to window.innerHeight (no footer constraint) when no
               footer element is visible (e.g. on the map page where footer is hidden).
               Uses querySelectorAll per selector so a hidden first match (e.g. the
               Elementor location-footer hidden by plugin CSS) does not prevent a
               second visible element from being found.
               #jg-footer-bar is the plugin's own fixed bottom bar (copyright + CTA).
               Remaining selectors mirror dwDetectHeaderFooter() in jg-map.js. */
            function jgGetFooterTop() {
                var vh = window.innerHeight;
                var footerTop = vh;
                var footerSelectors = [
                    '#jg-footer-bar',
                    '#site-footer',
                    '.elementor-location-footer',
                    '.site-footer',
                    'footer.elementor-section',
                    '#colophon',
                    'footer'
                ];
                footerSelectors.forEach(function (sel) {
                    document.querySelectorAll(sel).forEach(function (el) {
                        var s = window.getComputedStyle(el);
                        if (s.display === 'none' || s.visibility === 'hidden') return;
                        var rect = el.getBoundingClientRect();
                        if (rect.height === 0) return;
                        /* Only constrain when the footer top is inside the viewport */
                        if (rect.top > 0 && rect.top < vh && rect.top < footerTop) {
                            footerTop = Math.round(rect.top);
                        }
                    });
                });
                return footerTop;
            }
            window.jgGetFooterTop = jgGetFooterTop;
            function jgUpdateNavBottom() {
                /* --jg-info-bar-h: height of the info bar (0 when hidden/dismissed) — all widths */
                var infoBarH = 0;
                if (infoBarEl) {
                    var ibStyle = window.getComputedStyle(infoBarEl);
                    infoBarH = ibStyle.display === 'none' ? 0 : Math.round(infoBarEl.offsetHeight);
                }
                document.documentElement.style.setProperty('--jg-info-bar-h', infoBarH + 'px');
                /* --jg-nav-bottom: max bottom-edge among all visible nav elements */
                document.documentElement.style.setProperty('--jg-nav-bottom', jgGetNavBottom() + 'px');
                /* --jg-footer-top: top edge of visible footer (or 100vh when none) */
                document.documentElement.style.setProperty('--jg-footer-top', jgGetFooterTop() + 'px');
            }
            /* Also re-run when the info bar is dismissed */
            window.addEventListener('jg-info-bar-changed', jgUpdateNavBottom);
            /* Throttled scroll handler — fires at most once per animation frame */
            var jgNavBottomTicking = false;
            window.addEventListener('scroll', function () {
                if (jgNavBottomTicking) return;
                jgNavBottomTicking = true;
                requestAnimationFrame(function () {
                    jgUpdateNavBottom();
                    jgNavBottomTicking = false;
                });
            }, { passive: true });
            window.addEventListener('resize', jgUpdateNavBottom);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', jgUpdateNavBottom);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', jgUpdateNavBottom);
            } else {
                jgUpdateNavBottom();
            }
            window.addEventListener('load', jgUpdateNavBottom);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeMenu();
            });

            /* ── Submenu toggles ── */
            document.querySelectorAll('.jg-nav-sub-toggle').forEach(function (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    var item    = toggleBtn.closest('.jg-has-sub');
                    var submenu = item ? item.querySelector('.jg-nav-submenu') : null;
                    if (!submenu) return;
                    var isOpen = submenu.classList.contains('jg-sub-open');
                    submenu.classList.toggle('jg-sub-open', !isOpen);
                    toggleBtn.classList.toggle('jg-sub-open', !isOpen);
                    submenu.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
                });
            });

            /* ── Force brand colors on nav/top-bar links (beats Elementor inline JS styles) ── */
            function jgForceNavColors() {
                document.querySelectorAll('#jg-nav-menu .jg-nav-menu-link, #jg-nav-menu a').forEach(function (el) {
                    el.style.setProperty('color', '#8d2324', 'important');
                });
                document.querySelectorAll('.jg-top-bar-menu-item').forEach(function (el) {
                    el.style.setProperty('color', '#ffffff', 'important');
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', jgForceNavColors);
            } else {
                jgForceNavColors();
            }
            /* Drugi pass po załadowaniu wszystkich skryptów Elementora */
            window.addEventListener('load', jgForceNavColors);

            /* ── Mobile viewport fitting ──────────────────────────────────────
               Root cause: CSS had `height: 100% !important` on #jg-map-wrap
               which beats regular inline styles.  Fix: use
               style.setProperty(prop, val, 'important') — inline !important
               always wins over any stylesheet !important.

               Map height = visualViewport.height − map's actual top position
               (getBoundingClientRect().top), so it reaches the exact bottom
               of the visible screen regardless of Elementor padding or bar
               heights.
            ──────────────────────────────────────────────────────────────────── */
            var jgFitting = false;

            function jgFitMobileViewport() {
                if (window.innerWidth > 768) {
                    /* Returning to desktop: remove any mobile-only inline constraints
                       that would conflict with the desktop-wide fixed layout. */
                    var _mwEl = document.getElementById('jg-map-wrap');
                    if (_mwEl) {
                        _mwEl.style.removeProperty('max-height');
                        _mwEl.style.removeProperty('height');
                    }
                    return;
                }
                if (jgFitting) return;
                jgFitting = true;

                var navBarEl    = document.getElementById('jg-nav-bar');
                var topBarEl    = document.getElementById('jg-custom-top-bar');
                var mapWrapEl   = document.getElementById('jg-map-wrap');
                var footerBarEl = document.getElementById('jg-footer-bar');
                var _slotWrap   = document.querySelector('[data-cid]');
                var bannerEl    = _slotWrap ? document.getElementById(_slotWrap.dataset.cid) : null;

                if (!mapWrapEl) { jgFitting = false; return; }

                var navH    = navBarEl ? navBarEl.offsetHeight : 0;
                var topH    = topBarEl ? topBarEl.offsetHeight : 0;
                var footerH = (footerBarEl && window.getComputedStyle(footerBarEl).display !== 'none') ? footerBarEl.offsetHeight : 0;
                /* visualViewport.height shrinks when browser chrome appears
                   (address bar, bottom nav bar, on-screen keyboard) */
                var vpH = window.visualViewport
                    ? window.visualViewport.height
                    : window.innerHeight;
                var avail = vpH - navH - topH;

                /* 1. Cap banner to 22 % of available vertical space */
                if (bannerEl) {
                    bannerEl.style.setProperty('max-height', Math.round(avail * 0.22) + 'px', 'important');
                    bannerEl.style.setProperty('overflow',   'hidden', 'important');
                    void bannerEl.offsetHeight; /* force reflow before measuring map */
                }

                /* 2. Fill map from its real top edge to viewport bottom.
                   The fixed footer overlays the bottom of the map — no gap needed.
                   setProperty with 'important' beats CSS height:100%!important */
                var mapTop = mapWrapEl.getBoundingClientRect().top;
                var mapH   = Math.max(vpH - mapTop, 200);
                mapWrapEl.style.setProperty('height',     mapH + 'px', 'important');
                mapWrapEl.style.setProperty('max-height', mapH + 'px', 'important');

                /* 3. Notify Leaflet to redraw; clear guard first so Leaflet's
                   own resize handling doesn't get blocked */
                setTimeout(function () {
                    jgFitting = false;
                    window.dispatchEvent(new Event('resize'));
                }, 0);
            }

            /* DOM ready */
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    requestAnimationFrame(jgFitMobileViewport);
                });
            } else {
                requestAnimationFrame(jgFitMobileViewport);
            }

            /* After ALL resources (images, banner) are loaded */
            window.addEventListener('load', jgFitMobileViewport);

            /* Browser chrome resize (address bar hide/show) */
            window.addEventListener('resize', jgFitMobileViewport);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', jgFitMobileViewport);
            }

            /* Safety net for late Elementor rendering */
            setTimeout(jgFitMobileViewport, 300);
            setTimeout(jgFitMobileViewport, 800);
        })();
        </script>
        <?php
    }

    /**
     * Render custom top bar at the top of the page
     */
    public function render_top_bar() {
        // Render on all pages
        ?>
        <!-- Custom Top Bar -->
        <div id="jg-custom-top-bar" class="jg-custom-top-bar">
            <div class="jg-top-bar-left">
                <?php
                $top_bar_menu_items_left = get_option('jg_map_nav_menu', array());
                ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="jg-top-bar-logo-link" aria-label="Strona główna">
                    <img src="https://jeleniogorzanietomy.pl/wp-content/uploads/2025/10/jg-logo-1.svg" alt="Jelenia Góra to my" class="jg-top-bar-logo-img">
                    <span class="jg-top-bar-site-title"><span class="jg-title-main">Jeleniogórzanie to my</span><span class="jg-title-sub"> &ndash; Interaktywna mapa Jeleniej Góry</span></span>
                </a>
                <div class="jg-top-bar-menu-wrap">
                    <button class="jg-top-bar-menu-btn" id="jg-top-bar-menu-btn" aria-haspopup="true" aria-expanded="false" type="button">
                        Menu <span class="jg-top-bar-menu-chevron">&#9660;</span>
                    </button>
                    <div class="jg-top-bar-menu-dropdown" id="jg-top-bar-menu-dropdown" aria-hidden="true">
                        <?php if (!empty($top_bar_menu_items_left)) : ?>
                            <?php foreach ($top_bar_menu_items_left as $item) :
                                $mi_label  = isset($item['label']) ? $item['label'] : '';
                                $mi_url    = isset($item['url'])   ? $item['url']   : '#';
                                $mi_target = !empty($item['new_tab']) ? '_blank' : '_self';
                                $mi_rel    = $mi_target === '_blank' ? 'noopener noreferrer' : '';
                            ?>
                                <a href="<?php echo esc_url($mi_url); ?>"
                                   class="jg-top-bar-menu-item"
                                   target="<?php echo esc_attr($mi_target); ?>"
                                   <?php echo $mi_rel ? 'rel="' . esc_attr($mi_rel) . '"' : ''; ?>>
                                    <?php echo esc_html($mi_label); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <span class="jg-top-bar-menu-item jg-top-bar-menu-empty">Brak pozycji menu</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                $tb_contact_url = home_url('/kontakt');
                foreach (get_option('jg_map_nav_menu', array()) as $_tb_item) {
                    if (!empty($_tb_item['label']) && mb_stripos($_tb_item['label'], 'kontakt') !== false) {
                        $tb_contact_url = $_tb_item['url'];
                        break;
                    }
                }
                ?>
                <a href="<?php echo esc_url($tb_contact_url); ?>" class="jg-top-bar-btn jg-top-bar-contact-btn" title="Kontakt z redakcją" aria-label="Kontakt z redakcją">
                    <svg class="jg-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </a>
            </div>
            <div class="jg-top-bar-right">
                <?php if (is_user_logged_in()) : ?>
                    <?php
                    $current_user = wp_get_current_user();
                    $is_admin     = current_user_can('manage_options') || current_user_can('jg_map_admin');
                    $is_moderator = !$is_admin && current_user_can('jg_map_moderate');

                    // Check if user has sponsored places
                    global $wpdb;
                    $points_table = JG_Map_Database::get_points_table();
                    $has_sponsored = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $points_table WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
                        $current_user->ID
                    )) > 0;

                    $role_icon = '';
                    if ($is_admin) {
                        $role_icon = '<span style="color:#fbbf24;font-size:16px;margin-left:4px" title="Administrator">⭐</span>';
                    } elseif ($is_moderator) {
                        $role_icon = '<span style="color:#60a5fa;font-size:16px;margin-left:4px" title="Moderator">🛡️</span>';
                    }
                    if ($has_sponsored) {
                        $role_icon .= '<span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:#f59e0b;border-radius:50%;color:#fff;font-size:12px;margin-left:4px;font-weight:bold" title="Użytkownik sponsorowany">$</span>';
                    }

                    // Get moderation notifications count for admins/moderators
                    $mod_notifications = array();
                    if ($is_admin || $is_moderator) {
                        $history_table = JG_Map_Database::get_history_table();
                        $reports_table = JG_Map_Database::get_reports_table();

                        // Disable caching
                        $wpdb->query('SET SESSION query_cache_type = OFF');

                        $pending_points = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $points_table WHERE status = %s",
                            'pending'
                        ));
                        $pending_edits = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $history_table WHERE status = %s AND action_type = %s",
                            'pending',
                            'edit'
                        ));
                        // FIX: This query was counting reports for deleted/trashed points!
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

                        if ($pending_points > 0) {
                            $mod_notifications[] = array(
                                'icon' => '➕',
                                'label' => 'Nowe miejsca',
                                'count' => $pending_points,
                                'url' => admin_url('admin.php?page=jg-map-places#section-new_pending')
                            );
                        }
                        if ($pending_edits > 0) {
                            $mod_notifications[] = array(
                                'icon' => '📝',
                                'label' => 'Edycje',
                                'count' => $pending_edits,
                                'url' => admin_url('admin.php?page=jg-map-places#section-edit_pending')
                            );
                        }
                        if ($pending_reports > 0) {
                            $mod_notifications[] = array(
                                'icon' => '🚨',
                                'label' => 'Zgłoszenia',
                                'count' => $pending_reports,
                                'url' => admin_url('admin.php?page=jg-map-places#section-reported')
                            );
                        }
                        if ($pending_deletions > 0) {
                            $mod_notifications[] = array(
                                'icon' => '🗑️',
                                'label' => 'Usunięcia',
                                'count' => $pending_deletions,
                                'url' => admin_url('admin.php?page=jg-map-places#section-deletion_pending')
                            );
                        }
                    }
                    ?>
                    <?php
                    // Get user level & XP data for top bar
                    $user_xp_data = JG_Map_Levels_Achievements::get_user_xp_data($current_user->ID);
                    $user_level = $user_xp_data['level'];
                    $user_xp = $user_xp_data['xp'];
                    $current_level_xp = JG_Map_Levels_Achievements::xp_for_level($user_level);
                    $next_level_xp = JG_Map_Levels_Achievements::xp_for_level($user_level + 1);
                    $xp_in_level = $user_xp - $current_level_xp;
                    $xp_needed = $next_level_xp - $current_level_xp;
                    $xp_progress = $xp_needed > 0 ? min(100, round(($xp_in_level / $xp_needed) * 100)) : 100;
                    ?>
                    <span class="jg-top-bar-user">
                        <strong><a href="#" id="jg-my-profile-link" style="color:inherit;text-decoration:none;cursor:pointer" data-user-id="<?php echo esc_attr($current_user->ID); ?>"><?php echo esc_html($current_user->display_name); ?></a></strong><?php echo $role_icon; ?>
                    </span>
                    <?php
                    // Level color tiers (Forza Horizon style prestige colors)
                    if ($user_level >= 50) $level_tier = 'prestige-legend';
                    elseif ($user_level >= 40) $level_tier = 'prestige-ruby';
                    elseif ($user_level >= 30) $level_tier = 'prestige-diamond';
                    elseif ($user_level >= 20) $level_tier = 'prestige-purple';
                    elseif ($user_level >= 15) $level_tier = 'prestige-emerald';
                    elseif ($user_level >= 10) $level_tier = 'prestige-gold';
                    elseif ($user_level >= 5) $level_tier = 'prestige-silver';
                    else $level_tier = 'prestige-bronze';
                    ?>
                    <span class="jg-top-bar-level jg-level-<?php echo $level_tier; ?>" title="Poziom <?php echo $user_level; ?> — <?php echo $xp_in_level; ?>/<?php echo $xp_needed; ?> XP do następnego poziomu">
                        <span class="jg-top-bar-level-num"><?php echo $user_level; ?></span>
                        <span class="jg-top-bar-xp-bar"><span class="jg-top-bar-xp-fill" style="width:<?php echo $xp_progress; ?>%"></span></span>
                    </span>
                    <button id="jg-ranking-btn" class="jg-top-bar-btn" title="Ranking">
                        <svg class="jg-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>
                        <span class="jg-btn-text">Ranking</span>
                    </button>
                    <button id="jg-edit-profile-btn" class="jg-top-bar-btn" title="Edytuj profil">
                        <svg class="jg-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="jg-btn-text">Edytuj profil</span>
                    </button>

                    <!-- Notifications container for real-time updates -->
                    <div id="jg-top-bar-notifications"<?php echo empty($mod_notifications) ? ' class="jg-notifications-empty"' : ''; ?>>
                        <?php foreach ($mod_notifications as $notif) : ?>
                            <a href="<?php echo esc_url($notif['url']); ?>" class="jg-top-bar-btn jg-top-bar-notif" data-type="<?php echo esc_attr(strtolower($notif['label'])); ?>">
                                <span><?php echo $notif['icon']; ?> <?php echo esc_html($notif['label']); ?></span>
                                <span class="jg-notif-badge"><?php echo $notif['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($is_admin || $is_moderator) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=jg-map-dashboard')); ?>" class="jg-top-bar-btn jg-top-bar-btn-admin" title="Panel administratora">
                            <svg class="jg-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span class="jg-btn-text">Admin</span>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="jg-top-bar-btn" title="Wyloguj">
                        <svg class="jg-btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <span class="jg-btn-text">Wyloguj</span>
                    </a>
                <?php else : ?>
                    <button id="jg-auth-btn" class="jg-top-bar-btn">Zarejestruj / Zaloguj</button>
                <?php endif; ?>
            </div>
        </div>
        <script>
        (function () {
            var menuBtn      = document.getElementById('jg-top-bar-menu-btn');
            var menuDropdown = document.getElementById('jg-top-bar-menu-dropdown');
            if (!menuBtn || !menuDropdown) return;

            function openTopMenu() {
                menuDropdown.classList.add('jg-top-bar-menu-open');
                menuBtn.setAttribute('aria-expanded', 'true');
                menuDropdown.setAttribute('aria-hidden', 'false');
            }

            function closeTopMenu() {
                menuDropdown.classList.remove('jg-top-bar-menu-open');
                menuBtn.setAttribute('aria-expanded', 'false');
                menuDropdown.setAttribute('aria-hidden', 'true');
            }

            menuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                menuDropdown.classList.contains('jg-top-bar-menu-open') ? closeTopMenu() : openTopMenu();
            });

            document.addEventListener('click', function (e) {
                if (!menuBtn.contains(e.target) && !menuDropdown.contains(e.target)) {
                    closeTopMenu();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeTopMenu();
            });

            /* ── Modal positioning ─────────────────────────────────────────────
               Applies backdrop top-offset and modal max-height every time any
               .jg-modal-bg becomes visible (display:flex). This runs from the
               inline script so it is never affected by JS file caching.
               The MutationObserver watches style attribute changes on every
               .jg-modal-bg in the document.
            ───────────────────────────────────────────────────────────────────── */
            (function () {
                function jgPositionModalBg(bgEl) {
                    /* Use global helpers — jgGetNavBottom picks the correct nav
                       element per viewport; jgGetFooterTop finds any visible
                       footer and uses its top as the bottom boundary. */
                    var navBottom  = window.jgGetNavBottom  ? window.jgGetNavBottom()  : 52;
                    var footerTop  = window.jgGetFooterTop  ? window.jgGetFooterTop()  : window.innerHeight;
                    var gap = window.innerWidth <= 768 ? 14 : 18;
                    bgEl.style.paddingTop    = (navBottom + gap) + 'px';
                    bgEl.style.paddingBottom = (window.innerHeight - footerTop + gap) + 'px';
                    bgEl.style.paddingLeft   = '10px';
                    bgEl.style.paddingRight  = '10px';
                    /* size the .jg-modal inner element — skip the lightbox */
                    var c = bgEl.querySelector('.jg-modal');
                    if (c) {
                        var available = footerTop - navBottom - gap * 2;
                        c.style.maxHeight = Math.max(available, 100) + 'px';
                    }
                }

                var modalObs = new MutationObserver(function (mutations) {
                    mutations.forEach(function (m) {
                        var el = m.target;
                        if (el.classList && el.classList.contains('jg-modal-bg') &&
                            el.style.display === 'flex') {
                            jgPositionModalBg(el);
                        }
                    });
                });

                function jgObserveModalBgs() {
                    document.querySelectorAll('.jg-modal-bg').forEach(function (el) {
                        modalObs.observe(el, { attributes: true, attributeFilter: ['style'] });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', jgObserveModalBgs);
                } else {
                    jgObserveModalBgs();
                }
            })();
        })();
        </script>
        <?php
    }

    /**
     * Hide register button on Elementor maintenance screen
     * Registration is blocked during maintenance, so no need to show the button
     */
    public function hide_register_on_maintenance() {
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');

        if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
            ?>
            <style>
                /* Hide register button on Elementor maintenance/coming soon screen */
                body.elementor-maintenance-mode a[href*="wp-login.php?action=register"],
                body.elementor-maintenance-mode .elementor-button-link[href*="register"],
                body.elementor-maintenance-mode a[href*="register"],
                .elementor-maintenance-mode-register,
                a[href*="wp-login.php?action=register"] {
                    display: none !important;
                }
            </style>
            <?php
        }
    }

    /**
     * Disable pinch-to-zoom on mobile devices (except for the map)
     * The Leaflet map has its own zoom controls that work independently
     */
    /**
     * Preconnect to map tile CDNs so the TCP/TLS handshake is done
     * before Leaflet requests the first tile (saves ~100ms per provider).
     */
    /**
     * Return true if the current page contains the map shortcode.
     * Checks both standard post_content and Elementor's _elementor_data meta.
     */
    private function is_map_page() {
        global $post;
        if (!$post) return false;
        if (has_shortcode($post->post_content, 'jg_map') || has_shortcode($post->post_content, 'jg_map_advanced')) {
            return true;
        }
        // Elementor stores widget content in _elementor_data (JSON), not post_content
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        return $elementor_data && strpos($elementor_data, 'jg_map') !== false;
    }

    public function add_map_body_class($classes) {
        if ($this->is_map_page()) {
            $classes[] = 'jg-has-map';
        }
        return $classes;
    }

    public function hide_elementor_header_early() {
        // Header hidden globally (plugin nav bar replaces it on every page).
        // Footer and adminbar hidden only on the map page (posts/pages keep their footer).
        $map_only = $this->is_map_page()
            ? '#site-footer{display:none!important}' .
              '.site-footer{display:none!important}' .
              '.elementor-location-footer{display:none!important}' .
              'footer.elementor-section[class*="elementor-location"]{display:none!important}' .
              '#wpadminbar{display:none!important}' .
              'html{margin-top:0!important}'
            : '';
        echo '<style>' .
            '#site-header{display:none!important}' .
            '.site-header{display:none!important}' .
            '.elementor-location-header{display:none!important}' .
            'header.elementor-section[class*="elementor-location"]{display:none!important}' .
            $map_only .
            '</style>' . "\n";
    }

    public function add_tile_preconnect() {
        echo '<link rel="preconnect" href="https://basemaps.cartocdn.com" crossorigin>' . "\n";
        echo '<link rel="dns-prefetch" href="https://basemaps.cartocdn.com">' . "\n";
        echo '<link rel="preconnect" href="https://server.arcgisonline.com" crossorigin>' . "\n";
        echo '<link rel="dns-prefetch" href="https://server.arcgisonline.com">' . "\n";
    }

    public function disable_mobile_zoom() {
        ?>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <style>
            @media (max-width: 768px) {
                /* Disable double-tap zoom on all elements except map */
                body, body * {
                    touch-action: manipulation;
                }

                /* Let Leaflet control all touch interactions on the map:
                   one finger = pan, two fingers = pinch-zoom */
                .leaflet-container {
                    touch-action: none;
                }
                .leaflet-container * {
                    touch-action: none;
                }
            }
        </style>
        <?php
    }

    /**
     * Handle email activation from link
     */
    public function handle_email_activation() {
        if (!isset($_GET['jg_activate'])) {
            return;
        }

        $activation_key = sanitize_text_field($_GET['jg_activate']);

        // Find user with this activation key
        $users = get_users(array(
            'meta_key' => 'jg_map_activation_key',
            'meta_value' => $activation_key,
            'number' => 1
        ));

        if (empty($users)) {
            wp_die('Nieprawidłowy link aktywacyjny. Konto mogło już zostać aktywowane lub link wygasł.', 'Błąd aktywacji', array('response' => 400));
        }

        $user = $users[0];

        // Check if already activated
        $status = get_user_meta($user->ID, 'jg_map_account_status', true);
        if ($status === 'active') {
            wp_redirect(add_query_arg('activation', 'already', home_url()));
            exit;
        }

        // Check if activation key expired (48 hours)
        $key_time = get_user_meta($user->ID, 'jg_map_activation_key_time', true);
        if (empty($key_time) || (time() - $key_time) > 172800) {
            delete_user_meta($user->ID, 'jg_map_activation_key');
            delete_user_meta($user->ID, 'jg_map_activation_key_time');
            delete_user_meta($user->ID, 'jg_map_activation_session');
            wp_die('Link aktywacyjny wygasł. Linki są ważne przez 48 godzin. Skontaktuj się z administratorem aby ponownie aktywować konto.', 'Link wygasł', array('response' => 400));
        }

        // Activate account
        update_user_meta($user->ID, 'jg_map_account_status', 'active');
        update_user_meta($user->ID, 'jg_map_activated_at', time());
        delete_user_meta($user->ID, 'jg_map_activation_key');
        delete_user_meta($user->ID, 'jg_map_activation_key_time');

        // Send activation success email
        $subject = 'Konto aktywowane - ' . get_bloginfo('name');
        $message  = "Witaj " . $user->user_login . "!\n\n";
        $message .= "Twoje konto w serwisie " . get_bloginfo('name') . " zostało pomyślnie aktywowane.\n\n";
        $message .= "Możesz teraz zalogować się na stronie:\n";
        $message .= home_url() . "\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół " . get_bloginfo('name');
        JG_Map_Ajax_Handlers::get_instance()->send_plugin_email($user->user_email, $subject, $message);

        // DO NOT auto login - require manual login for security
        // Redirect to home with success message (will show modal)
        wp_redirect(add_query_arg('activation', 'success', home_url()));
        exit;
    }

    public function handle_password_reset() {
        if (!isset($_GET['jg_reset'])) {
            return;
        }

        $reset_key = sanitize_text_field($_GET['jg_reset']);

        // Find user with this reset key
        $users = get_users(array(
            'meta_key' => 'jg_map_reset_key',
            'meta_value' => $reset_key,
            'number' => 1
        ));

        if (empty($users)) {
            wp_die('Nieprawidłowy lub wygasły link resetowania hasła.', 'Błąd resetowania hasła', array('response' => 400));
        }

        $user = $users[0];

        // Check if key is still valid (24 hours)
        $key_time = get_user_meta($user->ID, 'jg_map_reset_key_time', true);
        if (empty($key_time) || (time() - $key_time) > 86400) {
            delete_user_meta($user->ID, 'jg_map_reset_key');
            delete_user_meta($user->ID, 'jg_map_reset_key_time');
            wp_die('Link resetowania hasła wygasł. Linki są ważne przez 24 godziny.', 'Link wygasł', array('response' => 400));
        }

        // Handle password reset form submission
        if (isset($_POST['new_password']) && isset($_POST['reset_key'])) {
            // Verify nonce
            if (!isset($_POST['reset_nonce']) || !wp_verify_nonce($_POST['reset_nonce'], 'jg_reset_password_' . $reset_key)) {
                wp_die('Token bezpieczeństwa CSRF nieprawidłowy lub wygasł.', 'Błąd bezpieczeństwa', array('response' => 403));
            }

            $new_password = $_POST['new_password'];
            $posted_key = sanitize_text_field($_POST['reset_key']);

            if ($posted_key !== $reset_key) {
                wp_die('Nieprawidłowy klucz resetowania.', 'Błąd', array('response' => 400));
            }

            if (strlen($new_password) < 12) {
                $error = 'Hasło musi mieć co najmniej 12 znaków.';
            } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $error = 'Hasło musi zawierać co najmniej jedną wielką literę, małą literę i cyfrę.';
            } else {
                // Update password
                wp_set_password($new_password, $user->ID);

                // Remove reset key
                delete_user_meta($user->ID, 'jg_map_reset_key');
                delete_user_meta($user->ID, 'jg_map_reset_key_time');

                // Auto login user
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true);

                // Redirect to home with success message
                wp_redirect(add_query_arg('password_reset', 'success', home_url()));
                exit;
            }
        }

        // Show password reset form
        $this->show_reset_password_form($reset_key, isset($error) ? $error : '');
        exit;
    }

    private function show_reset_password_form($reset_key, $error = '') {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Resetowanie hasła - <?php bloginfo('name'); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .reset-container {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 480px;
                    width: 100%;
                    overflow: hidden;
                }
                .reset-header {
                    background: #8d2324;
                    color: white;
                    padding: 30px 24px;
                    text-align: center;
                }
                .reset-header h1 {
                    font-size: 24px;
                    font-weight: 600;
                    margin-bottom: 8px;
                }
                .reset-header p {
                    font-size: 14px;
                    opacity: 0.9;
                }
                .reset-body {
                    padding: 32px 24px;
                }
                .form-group {
                    margin-bottom: 24px;
                }
                label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 600;
                    color: #333;
                    font-size: 14px;
                }
                input[type="password"] {
                    width: 100%;
                    padding: 14px;
                    border: 2px solid #ddd;
                    border-radius: 8px;
                    font-size: 15px;
                    transition: border-color 0.2s;
                }
                input[type="password"]:focus {
                    outline: none;
                    border-color: #8d2324;
                }
                .error-message {
                    background: #fee;
                    border: 2px solid #fcc;
                    color: #c33;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    font-size: 14px;
                }
                .submit-btn {
                    width: 100%;
                    padding: 14px;
                    background: #8d2324;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .submit-btn:hover {
                    background: #a02829;
                }
                .info-box {
                    background: #f0f9ff;
                    border: 2px solid #bae6fd;
                    border-radius: 8px;
                    padding: 12px;
                    margin-top: 20px;
                    font-size: 13px;
                    color: #0c4a6e;
                }
            </style>
        </head>
        <body>
            <div class="reset-container">
                <div class="reset-header">
                    <h1>🔑 Ustaw nowe hasło</h1>
                    <p><?php bloginfo('name'); ?></p>
                </div>
                <div class="reset-body">
                    <?php if (!empty($error)) : ?>
                        <div class="error-message"><?php echo esc_html($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="reset_key" value="<?php echo esc_attr($reset_key); ?>">
                        <?php wp_nonce_field('jg_reset_password_' . $reset_key, 'reset_nonce'); ?>

                        <div class="form-group">
                            <label for="new_password">Nowe hasło</label>
                            <input type="password" id="new_password" name="new_password" required minlength="12" placeholder="Wprowadź nowe hasło (min. 12 znaków)">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Potwierdź nowe hasło</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="12" placeholder="Wprowadź ponownie nowe hasło">
                        </div>

                        <button type="submit" class="submit-btn" onclick="return validatePasswords()">Ustaw nowe hasło</button>

                        <div class="info-box">
                            💡 Hasło musi mieć co najmniej 12 znaków, zawierać wielką literę, małą literę i cyfrę
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function validatePasswords() {
                    var password = document.getElementById('new_password').value;
                    var confirm = document.getElementById('confirm_password').value;

                    if (password.length < 12) {
                        alert('Hasło musi mieć co najmniej 12 znaków');
                        return false;
                    }

                    if (!/[A-Z]/.test(password)) {
                        alert('Hasło musi zawierać co najmniej jedną wielką literę');
                        return false;
                    }

                    if (!/[a-z]/.test(password)) {
                        alert('Hasło musi zawierać co najmniej jedną małą literę');
                        return false;
                    }

                    if (!/[0-9]/.test(password)) {
                        alert('Hasło musi zawierać co najmniej jedną cyfrę');
                        return false;
                    }

                    if (password !== confirm) {
                        alert('Hasła nie są identyczne');
                        return false;
                    }

                    return true;
                }
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Render minimal contact footer bar (fixed bottom, portal-red + white text).
     * Hooked to wp_footer so it renders after all page content.
     * Sets --jg-footer-h CSS variable so JS can subtract it from map height.
     */
    public function render_footer_bar() {
        // Resolve "Kontakt" URL from nav menu
        $footer_contact_url = home_url('/kontakt');
        foreach (get_option('jg_map_nav_menu', array()) as $_fi) {
            if (!empty($_fi['label']) && mb_stripos($_fi['label'], 'kontakt') !== false) {
                $footer_contact_url = $_fi['url'];
                break;
            }
        }
        ?>
        <div id="jg-footer-bar" role="contentinfo" aria-label="Stopka portalu">
            <span class="jg-footer-bar-copy">&copy; <?php echo esc_html(date('Y')); ?> Jeleniogórzanie to my</span>
            <a href="<?php echo esc_url($footer_contact_url); ?>" class="jg-footer-bar-link">Napisz do redakcji</a>
        </div>
        <script>
        (function () {
            var bar = document.getElementById('jg-footer-bar');
            if (!bar) return;
            function jgSetFooterH() {
                document.documentElement.style.setProperty('--jg-footer-h', bar.offsetHeight + 'px');
            }
            jgSetFooterH();
            window.addEventListener('resize', jgSetFooterH);
            window.addEventListener('load',   jgSetFooterH);
        })();
        </script>
        <?php
    }

}
