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

        // Hide admin bar for non-admins
        add_action('after_setup_theme', array($this, 'hide_admin_bar_for_users'));

        // Add custom top bar to the page
        add_action('wp_body_open', array($this, 'render_top_bar'));

        // Block wp-admin and wp-login access for non-admins
        add_action('admin_init', array($this, 'block_admin_access'));
        add_action('login_init', array($this, 'block_login_page'));

        // Hide register button on Elementor maintenance screen
        add_action('wp_head', array($this, 'hide_register_on_maintenance'));

        // Handle email activation
        add_action('template_redirect', array($this, 'handle_email_activation'));
    }

    /**
     * Hide WordPress admin bar for non-admin users
     */
    public function hide_admin_bar_for_users() {
        if (!current_user_can('manage_options')) {
            show_admin_bar(false);
        }
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
                'isAdmin' => current_user_can('manage_options') || current_user_can('jg_map_moderate'),
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

    /**
     * Render custom top bar at the top of the page
     */
    public function render_top_bar() {
        // Only render on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'jg_map')) {
            return;
        }
        ?>
        <!-- Custom Top Bar -->
        <div id="jg-custom-top-bar" class="jg-custom-top-bar">
            <div class="jg-top-bar-left">
                <span id="jg-top-bar-datetime"></span>
            </div>
            <div class="jg-top-bar-right">
                <?php if (is_user_logged_in()) : ?>
                    <?php
                    $current_user = wp_get_current_user();
                    $is_admin = current_user_can('manage_options');
                    $is_moderator = current_user_can('jg_map_moderate');
                    $role_icon = '';
                    if ($is_admin) {
                        $role_icon = '<span style="color:#fbbf24;font-size:16px;margin-left:4px" title="Administrator">⭐</span>';
                    } elseif ($is_moderator) {
                        $role_icon = '<span style="color:#60a5fa;font-size:16px;margin-left:4px" title="Moderator">🛡️</span>';
                    }
                    ?>
                    <span class="jg-top-bar-user">
                        Zalogowano jako: <strong><?php echo esc_html($current_user->display_name); ?></strong><?php echo $role_icon; ?>
                    </span>
                    <button id="jg-edit-profile-btn" class="jg-top-bar-btn">Edytuj profil</button>
                    <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="jg-top-bar-btn">Wyloguj</a>
                <?php else : ?>
                    <button id="jg-login-btn" class="jg-top-bar-btn">Zaloguj</button>
                    <button id="jg-register-btn" class="jg-top-bar-btn">Zarejestruj</button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Block wp-admin access for non-admin users
     * Allow access for users with manage_options capability (administrators)
     */
    public function block_admin_access() {
        // Explicitly allow admins and during AJAX
        if (current_user_can('manage_options') || wp_doing_ajax()) {
            return; // Admin or AJAX - allow access
        }

        // Block all other users from wp-admin
        if (is_admin()) {
            wp_redirect(home_url());
            exit;
        }
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
     * Block wp-login.php access (redirect to home with modal trigger)
     * BUT allow logout, lostpassword, and rp (reset password) actions
     * ALSO don't block during Elementor maintenance mode (admins need to login)
     */
    public function block_login_page() {
        // Don't block wp-login.php during Elementor maintenance mode
        // Admins and moderators need to be able to login via standard WP login page
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');
        if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
            return; // Allow standard WP login during maintenance
        }

        // Allow logout, password reset actions
        if (isset($_GET['action']) && in_array($_GET['action'], array('logout', 'lostpassword', 'rp', 'resetpass'))) {
            return; // Don't block these actions
        }

        // Redirect to home page - modal will open via JavaScript
        wp_redirect(home_url());
        exit;
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

        // Activate account
        update_user_meta($user->ID, 'jg_map_account_status', 'active');
        delete_user_meta($user->ID, 'jg_map_activation_key');

        // Auto login user
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        // Redirect to home with success message
        wp_redirect(add_query_arg('activation', 'success', home_url()));
        exit;
    }
}
