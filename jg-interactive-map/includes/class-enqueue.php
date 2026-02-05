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

        // Add custom top bar to the page
        add_action('wp_body_open', array($this, 'render_top_bar'));

        // Hide register button on Elementor maintenance screen
        add_action('wp_head', array($this, 'hide_register_on_maintenance'));

        // Disable pinch-to-zoom on mobile (except for map)
        add_action('wp_head', array($this, 'disable_mobile_zoom'), 1);

        // Handle email activation
        add_action('template_redirect', array($this, 'handle_email_activation'));
        add_action('template_redirect', array($this, 'handle_password_reset'));

        // Block non-admin users from accessing /wp-admin/
        add_action('admin_init', array($this, 'block_non_admin_access'));
    }

    /**
     * Hide WordPress admin bar for ALL users (including admins)
     * Admins can access wp-admin via custom top bar button
     */
    public function hide_admin_bar_for_users() {
        show_admin_bar(false);
    }

    /**
     * Block non-admin users from accessing /wp-admin/
     * Only users with manage_options or jg_map_moderate capability can access
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

        // Check if user has admin or moderator permissions
        if (current_user_can('manage_options') || current_user_can('jg_map_moderate')) {
            return;
        }

        // Redirect non-admin users to home page
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
            JG_MAP_VERSION
        );

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
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('jg_map_nonce'),
                'moderationUrl' => admin_url('admin.php?page=jg-map-places&status=new_pending'),
                'reportsUrl' => admin_url('admin.php?page=jg-map-places&status=reported'),
                'deletionsUrl' => admin_url('admin.php?page=jg-map-places&status=deletion_pending')
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
            'registrationEnabled' => (bool) get_option('jg_map_registration_enabled', 1),
            'registrationDisabledMessage' => get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.')
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

        // Inline script for clock - loads on ALL pages
        $inline_script = "
        (function() {
            function updateDateTime() {
                var el = document.getElementById('jg-top-bar-datetime');
                if (!el) return;

                var now = new Date();
                var days = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
                var dayName = days[now.getDay()];

                var day = String(now.getDate()).padStart(2, '0');
                var month = String(now.getMonth() + 1).padStart(2, '0');
                var year = now.getFullYear();

                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var seconds = String(now.getSeconds()).padStart(2, '0');

                el.textContent = dayName + ', ' + day + '.' + month + '.' + year + ' • ' + hours + ':' + minutes + ':' + seconds;
            }

            function initClock() {
                updateDateTime();
                setInterval(updateDateTime, 1000);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initClock);
            } else {
                initClock();
            }
        })();
        ";
        wp_add_inline_script('jquery', $inline_script);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_assets() {
        global $post;

        // Always enqueue banner script (it's small and will only init if container exists)
        // This avoids issues with caching and post detection
        wp_enqueue_script(
            'jg-map-banner',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-banner.js',
            array('jquery'),
            JG_MAP_VERSION . '-' . time(),
            true
        );

        // Localize banner script
        wp_localize_script('jg-map-banner', 'JG_BANNER_CFG', array(
            'ajax' => admin_url('admin-ajax.php')
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
        $dependencies = array('jquery', 'leaflet', 'leaflet-markercluster', 'heartbeat');

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
                'curiosityCategories' => JG_Map_Ajax_Handlers::get_curiosity_categories()
            )
        );

        // Real-time updates now handled directly in jg-map.js via WordPress Heartbeat API
        // and JG_Map_Sync_Manager class. No inline script needed.
        // Heartbeat is enqueued as a dependency of jg-map-script (see above)
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
        // Render on all pages
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
                                'url' => admin_url('admin.php?page=jg-map-places&status=new_pending')
                            );
                        }
                        if ($pending_edits > 0) {
                            $mod_notifications[] = array(
                                'icon' => '📝',
                                'label' => 'Edycje',
                                'count' => $pending_edits,
                                'url' => admin_url('admin.php?page=jg-map-places&status=edit_pending')
                            );
                        }
                        if ($pending_reports > 0) {
                            $mod_notifications[] = array(
                                'icon' => '🚨',
                                'label' => 'Zgłoszenia',
                                'count' => $pending_reports,
                                'url' => admin_url('admin.php?page=jg-map-places&status=reported')
                            );
                        }
                        if ($pending_deletions > 0) {
                            $mod_notifications[] = array(
                                'icon' => '🗑️',
                                'label' => 'Usunięcia',
                                'count' => $pending_deletions,
                                'url' => admin_url('admin.php?page=jg-map-places&status=deletion_pending')
                            );
                        }
                    }
                    ?>
                    <span class="jg-top-bar-user">
                        Zalogowano jako:&nbsp;<strong><a href="#" id="jg-my-profile-link" style="color:inherit;text-decoration:none;cursor:pointer" data-user-id="<?php echo esc_attr($current_user->ID); ?>"><?php echo esc_html($current_user->display_name); ?></a></strong><?php echo $role_icon; ?>
                    </span>
                    <button id="jg-edit-profile-btn" class="jg-top-bar-btn">Edytuj profil</button>

                    <!-- Notifications container for real-time updates -->
                    <div id="jg-top-bar-notifications"<?php echo empty($mod_notifications) ? ' class="jg-notifications-empty"' : ''; ?>>
                        <?php foreach ($mod_notifications as $notif) : ?>
                            <a href="<?php echo esc_url($notif['url']); ?>" class="jg-top-bar-btn jg-top-bar-notif" data-type="<?php echo esc_attr(strtolower($notif['label'])); ?>">
                                <span><?php echo $notif['icon']; ?> <?php echo esc_html($notif['label']); ?></span>
                                <span class="jg-notif-badge"><?php echo $notif['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($is_admin) : ?>
                        <a href="<?php echo admin_url(); ?>" class="jg-top-bar-btn jg-top-bar-btn-admin">Panel administratora</a>
                    <?php endif; ?>
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
    public function disable_mobile_zoom() {
        ?>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <style>
            @media (max-width: 768px) {
                /* Disable double-tap zoom on all elements except map */
                body, body * {
                    touch-action: manipulation;
                }

                /* Allow scroll and pinch-zoom on the map container */
                #jg-map-container,
                #jg-map-container *,
                .leaflet-container,
                .leaflet-container * {
                    touch-action: pan-x pan-y pinch-zoom !important;
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

        // Check if activation is from the same session as registration (security)
        $expected_session = get_user_meta($user->ID, 'jg_map_activation_session', true);
        if (!empty($expected_session)) {
            // Get current session ID
            $current_session = session_id();
            if (empty($current_session)) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                    $current_session = session_id();
                }
            }

            // If session doesn't match, deny activation for security
            if ($current_session !== $expected_session) {
                wp_die('Link aktywacyjny musi być otwarty w tej samej przeglądarce i sesji, w której dokonano rejestracji. Proszę skopiować link i otworzyć go w tej samej przeglądarce, w której rejestrowałeś się.', 'Błąd bezpieczeństwa', array('response' => 403));
            }
        }

        // Activate account
        update_user_meta($user->ID, 'jg_map_account_status', 'active');
        delete_user_meta($user->ID, 'jg_map_activation_key');
        delete_user_meta($user->ID, 'jg_map_activation_key_time');
        delete_user_meta($user->ID, 'jg_map_activation_session');

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

}
