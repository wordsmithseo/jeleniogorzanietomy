<?php
/**
 * Admin panel for JG Map
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/admin/trait-admin-helpers.php';
require_once __DIR__ . '/admin/trait-admin-dashboard.php';
require_once __DIR__ . '/admin/trait-admin-moderation.php';
require_once __DIR__ . '/admin/trait-admin-places.php';
require_once __DIR__ . '/admin/trait-admin-users.php';
require_once __DIR__ . '/admin/trait-admin-reports.php';

class JG_Map_Admin {
    use JG_Map_Admin_Helpers;
    use JG_Map_Admin_Dashboard;
    use JG_Map_Admin_Moderation;
    use JG_Map_Admin_Places;
    use JG_Map_Admin_Users;
    use JG_Map_Admin_Reports;

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // Remove all non-plugin WP menus for moderators and plugin admins
        add_action('admin_menu', array($this, 'restrict_sidebar_for_non_wp_admins'), 9999);
        add_action('admin_bar_menu', array($this, 'add_admin_bar_notifications'), 100);
        add_filter('admin_title', array($this, 'modify_admin_title'), 999, 2);

        // Real-time notifications via Heartbeat API
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_bar_script'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        // Handle manual activation from plugin users page
        add_action('admin_post_jg_map_activate_user', array($this, 'handle_manual_activate_user'));
    }

    /**
     * Get map page URL
     * Finds the page/post that contains the [jg_map] shortcode
     */
    private function get_map_page_url() {
        // Check transient cache first
        $cached_url = get_transient('jg_map_page_url');
        if ($cached_url) {
            return $cached_url;
        }

        global $wpdb;

        // Search for page or post with [jg_map] shortcode
        $page = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_content LIKE %s
                 AND post_status = %s
                 AND post_type IN ('page', 'post')
                 LIMIT 1",
                '%' . $wpdb->esc_like('[jg_map') . '%',
                'publish'
            )
        );

        if ($page) {
            $url = get_permalink($page);
            // Cache for 1 hour
            set_transient('jg_map_page_url', $url, HOUR_IN_SECONDS);
            return $url;
        }

        // Fallback to home URL
        return home_url('/');
    }

    /**
     * Add admin bar notifications
     */
    public function add_admin_bar_notifications($wp_admin_bar) {
        // Only for admins and moderators
        if (!current_user_can('manage_options') && !current_user_can('jg_map_moderate')) {
            return;
        }

        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();
        $reports_table = JG_Map_Database::get_reports_table();
        $history_table = JG_Map_Database::get_history_table();

        // Ensure history table exists
        JG_Map_Database::ensure_history_table();

        // Disable caching for these queries to ensure fresh data
        $wpdb->query('SET SESSION query_cache_type = OFF');

        // Count pending items with WPDB suppress filter to bypass cache
        $pending_points = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $points_table WHERE status = %s",
            'pending'
        ));
        // ONLY count edits, not deletion requests
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

        $total_pending = intval($pending_points) + intval($pending_edits) + intval($pending_reports) + intval($pending_deletions);

        if ($total_pending === 0) {
            return;
        }

        // Add parent node
        $wp_admin_bar->add_node(array(
            'id' => 'jg-map-notifications',
            'title' => '<span style="background:#dc2626;color:#fff;padding:2px 6px;border-radius:10px;font-size:calc(11 * var(--jg));font-weight:700;margin-right:4px">' . $total_pending . '</span> JG Map',
            'href' => admin_url('admin.php?page=jg-map-places'),
            'meta' => array(
                'title' => 'JG Map - Oczekujące moderacje'
            )
        ));

        // Add child nodes with links to specific sections
        if ($pending_points > 0) {
            $wp_admin_bar->add_node(array(
                'parent' => 'jg-map-notifications',
                'id' => 'jg-map-pending-points',
                'title' => '📍 ' . $pending_points . ' nowych miejsc',
                'href' => admin_url('admin.php?page=jg-map-places#section-new_pending')
            ));
        }

        if ($pending_edits > 0) {
            $wp_admin_bar->add_node(array(
                'parent' => 'jg-map-notifications',
                'id' => 'jg-map-pending-edits',
                'title' => '✏️ ' . $pending_edits . ' edycji do zatwierdzenia',
                'href' => admin_url('admin.php?page=jg-map-places#section-edit_pending')
            ));
        }

        if ($pending_reports > 0) {
            $wp_admin_bar->add_node(array(
                'parent' => 'jg-map-notifications',
                'id' => 'jg-map-pending-reports',
                'title' => '🚨 ' . $pending_reports . ' zgłoszeń',
                'href' => admin_url('admin.php?page=jg-map-places#section-reported')
            ));
        }

        if ($pending_deletions > 0) {
            $wp_admin_bar->add_node(array(
                'parent' => 'jg-map-notifications',
                'id' => 'jg-map-pending-deletions',
                'title' => '🗑️ ' . $pending_deletions . ' żądań usunięcia',
                'href' => admin_url('admin.php?page=jg-map-places#section-deletion_pending')
            ));
        }
    }

    /**
     * Handle manual account activation from plugin users page
     */
    public function handle_manual_activate_user() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.', 403);
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_die('Nieprawidłowy użytkownik.');
        }

        check_admin_referer('jg_map_activate_user_' . $user_id, 'jg_map_activate_nonce');

        $user = get_userdata($user_id);
        if (!$user) {
            wp_die('Użytkownik nie istnieje.');
        }

        update_user_meta($user_id, 'jg_map_account_status', 'active');
        update_user_meta($user_id, 'jg_map_activated_at', time());
        delete_user_meta($user_id, 'jg_map_activation_key');
        delete_user_meta($user_id, 'jg_map_activation_key_time');

        // Send activation success email
        $subject  = 'Konto aktywowane - ' . get_bloginfo('name');
        $message  = "Witaj " . $user->user_login . "!\n\n";
        $message .= "Twoje konto w serwisie " . get_bloginfo('name') . " zostało aktywowane przez administratora.\n\n";
        $message .= "Możesz teraz zalogować się na stronie:\n";
        $message .= home_url() . "\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół " . get_bloginfo('name');
        JG_Map_Ajax_Handlers::get_instance()->send_plugin_email($user->user_email, $subject, $message);

        wp_redirect(add_query_arg(
            array('page' => 'jg-map-users', 'jg_activated' => $user_id),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Modify admin title to show event names
     */
    public function modify_admin_title($admin_title, $title) {
        global $wpdb;

        // Only for admins and moderators
        if (!current_user_can('manage_options') && !current_user_can('moderate_comments')) {
            return $admin_title;
        }

        // Get current page
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'jg-map') === false) {
            return $admin_title;
        }

        $points_table = JG_Map_Database::get_points_table();
        $history_table = JG_Map_Database::get_history_table();
        $reports_table = JG_Map_Database::get_reports_table();

        // Ensure history table exists
        JG_Map_Database::ensure_history_table();

        $events = array();
        $total_count = 0;

        // Moderation page - show pending points and edits
        if (strpos($screen->id, 'jg-map-places') !== false) {
            // Get pending points
            $pending_points = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT title FROM $points_table WHERE status = %s ORDER BY created_at DESC LIMIT 5",
                    'pending'
                ),
                ARRAY_A
            );
            foreach ($pending_points as $point) {
                $events[] = $point['title'] ?: 'Bez nazwy';
            }

            // Get pending edits (ONLY edits, not deletion requests)
            $pending_edits = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.title FROM $history_table h
                     LEFT JOIN $points_table p ON h.point_id = p.id
                     WHERE h.status = %s AND h.action_type = %s
                     ORDER BY h.created_at DESC LIMIT 5",
                    'pending',
                    'edit'
                ),
                ARRAY_A
            );
            foreach ($pending_edits as $edit) {
                $events[] = 'Edycja: ' . ($edit['title'] ?: 'Bez nazwy');
            }

            $total_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $points_table WHERE status = %s", 'pending'))
                         + $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $history_table WHERE status = %s AND action_type = %s", 'pending', 'edit'));
        }
        // Reports page - show report reasons
        elseif (strpos($screen->id, 'jg-map-reports') !== false) {
            $pending_reports = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.reason, p.title
                     FROM $reports_table r
                     INNER JOIN $points_table p ON r.point_id = p.id
                     WHERE r.status = %s AND p.status = %s
                     ORDER BY r.created_at DESC LIMIT 5",
                    'pending',
                    'publish'
                ),
                ARRAY_A
            );
            foreach ($pending_reports as $report) {
                $events[] = ($report['title'] ?: 'Bez nazwy') . ': ' . $report['reason'];
            }

            $total_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT r.point_id)
                     FROM $reports_table r
                     INNER JOIN $points_table p ON r.point_id = p.id
                     WHERE r.status = %s AND p.status = %s",
                    'pending',
                    'publish'
                )
            );
        }
        // Deletions page - show deletion requests
        elseif (strpos($screen->id, 'jg-map-deletions') !== false) {
            $pending_deletions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT title FROM $points_table
                     WHERE is_deletion_requested = %d AND status = %s
                     ORDER BY updated_at DESC LIMIT 5",
                    1,
                    'publish'
                ),
                ARRAY_A
            );
            foreach ($pending_deletions as $deletion) {
                $events[] = $deletion['title'] ?: 'Bez nazwy';
            }

            $total_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $points_table WHERE is_deletion_requested = %d AND status = %s",
                    1,
                    'publish'
                )
            );
        }

        // Get the site name (appears after &lsaquo; in the title)
        $site_name = get_bloginfo('name');

        // Build the new title from scratch
        $new_title = $title;

        // If we have events to show, add them to the title
        if (!empty($events) && $total_count > 0) {
            // Limit to first 3 events for title
            $event_names = array_slice($events, 0, 3);
            $event_text = implode(', ', $event_names);

            // If there are more events, add ellipsis
            if ($total_count > 3) {
                $event_text .= '...';
            }

            // Build title: "Page Title: Event1, Event2..." (without count)
            $new_title = $title . ': ' . $event_text;
        }

        // Rebuild the complete admin title
        return $new_title . ' &lsaquo; ' . $site_name;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu: visible to moderators, plugin admins, and WP admins
        add_menu_page(
            'JG Map',
            'JG Map',
            'jg_map_moderate',
            'jg-map-dashboard',
            array($this, 'render_main_page'),
            'dashicons-location-alt',
            30
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Dashboard',
            'Dashboard',
            'jg_map_moderate',
            'jg-map-dashboard',
            array($this, 'render_main_page')
        );

        // --- Moderacja ---
        add_submenu_page(
            'jg-map-dashboard',
            'Miejsca',
            'Miejsca',
            'jg_map_moderate',
            'jg-map-places',
            array($this, 'render_places_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Promocje',
            'Promocje',
            'jg_map_manage',
            'jg-map-promos',
            array($this, 'render_promos_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Galeria zdjęć',
            'Galeria zdjęć',
            'jg_map_manage',
            'jg-map-gallery',
            array($this, 'render_gallery_page')
        );

        // --- Użytkownicy ---
        add_submenu_page(
            'jg-map-dashboard',
            'Użytkownicy',
            'Użytkownicy',
            'jg_map_moderate',
            'jg-map-users',
            array($this, 'render_users_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Role użytkowników',
            'Role użytkowników',
            'jg_map_moderate',
            'jg-map-roles',
            array($this, 'render_roles_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Activity Log',
            'Activity Log',
            'jg_map_manage',
            'jg-map-activity-log',
            array($this, 'render_activity_log_page')
        );

        // --- Treści ---
        add_submenu_page(
            'jg-map-dashboard',
            'Kategorie miejsc',
            'Kat. miejsc',
            'jg_map_manage',
            'jg-map-place-categories',
            array($this, 'render_place_categories_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Kategorie ciekawostek',
            'Kat. ciekawostek',
            'jg_map_manage',
            'jg-map-curiosity-categories',
            array($this, 'render_curiosity_categories_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Zarządzanie tagami',
            'Tagi',
            'jg_map_manage',
            'jg-map-tags',
            array($this, 'render_tags_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Powody zgłoszeń',
            'Powody zgłoszeń',
            'jg_map_manage',
            'jg-map-report-reasons',
            array($this, 'render_report_reasons_page')
        );

        // --- Gamifikacja ---
        add_submenu_page(
            'jg-map-dashboard',
            'Doświadczenie (XP)',
            'Doświadczenie (XP)',
            'jg_map_manage',
            'jg-map-xp-editor',
            array($this, 'render_xp_editor_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Osiągnięcia',
            'Osiągnięcia',
            'jg_map_manage',
            'jg-map-achievements-editor',
            array($this, 'render_achievements_editor_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Wyzwania',
            'Wyzwania',
            'jg_map_manage',
            'jg-map-challenges',
            array($this, 'render_challenges_page')
        );

        // --- Konfiguracja ---
        add_submenu_page(
            'jg-map-dashboard',
            'Ustawienia',
            'Ustawienia',
            'jg_map_manage',
            'jg-map-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Menu nawigacyjne',
            'Menu nawigacyjne',
            'jg_map_manage',
            'jg-map-nav-menu',
            array($this, 'render_nav_menu_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'Konserwacja',
            'Konserwacja',
            'jg_map_manage',
            'jg-map-maintenance',
            array($this, 'render_maintenance_page')
        );

        add_submenu_page(
            'jg-map-dashboard',
            'SEO Pinezek',
            'SEO Pinezek',
            'manage_options',
            'jg-map-seo',
            array($this, 'render_seo_page')
        );

    }

    /**
     * Remove all standard WordPress admin menus for moderators and plugin admins
     * (non-WP-admins). They should see only the JG Map plugin menu.
     */
    public function restrict_sidebar_for_non_wp_admins() {
        // Full WP admins keep the full sidebar
        if (current_user_can('manage_options')) {
            return;
        }

        // Only restrict users who have some plugin access
        if (!current_user_can('jg_map_manage') && !current_user_can('jg_map_moderate')) {
            return;
        }

        // Remove every standard WP core menu
        $core_menus = array(
            'index.php',                    // Dashboard
            'edit.php',                     // Posts
            'upload.php',                   // Media
            'edit.php?post_type=page',      // Pages
            'edit-comments.php',            // Comments
            'themes.php',                   // Appearance
            'plugins.php',                  // Plugins
            'users.php',                    // Users
            'tools.php',                    // Tools
            'options-general.php',          // Settings
            'profile.php',                  // Profile
        );
        foreach ($core_menus as $menu) {
            remove_menu_page($menu);
        }
    }

    /**
     * Render promos page
     */
    public function render_promos_page() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Handle promo actions
        if (isset($_POST['jg_promo_action']) && check_admin_referer('jg_promo_action', 'jg_promo_nonce')) {
            $point_id = intval($_POST['point_id'] ?? 0);
            $action = sanitize_text_field($_POST['action_type'] ?? '');

            if ($point_id && $action) {
                if ($action === 'update_date') {
                    $promo_until = sanitize_text_field($_POST['promo_until'] ?? '');
                    JG_Map_Database::update_point($point_id, array(
                        'promo_until' => $promo_until ? $promo_until : null
                    ));
                    echo '<div class="notice notice-success"><p>Data promocji zaktualizowana!</p></div>';
                } elseif ($action === 'remove') {
                    JG_Map_Database::update_point($point_id, array('is_promo' => 0, 'promo_until' => null));
                    echo '<div class="notice notice-success"><p>Promocja usunięta!</p></div>';
                }
            }
        }

        $promos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE is_promo = %d AND status = %s ORDER BY created_at DESC",
                1,
                'publish'
            ),
            ARRAY_A
        );

        ?>
        <style>
        .jg-admin-table{width:100%;border-collapse:collapse;font-size:13px}
        .jg-admin-table th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#374151;border-bottom:2px solid #e5e7eb;white-space:nowrap;text-transform:uppercase;letter-spacing:.4px}
        .jg-admin-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .jg-admin-table tbody tr:last-child td{border-bottom:none}
        .jg-admin-table tbody tr:hover{background:#f8fafc}
        .jg-admin-table-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
        .jg-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
        .jg-action-btns{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
        .jg-action-btns form{margin:0}
        .jg-info-box{background:#fff7e6;border:2px solid #f59e0b;padding:15px 18px;border-radius:10px;margin:16px 0}
        .jg-info-box h3{margin:0 0 8px}
        @media(max-width:782px){
            .jg-admin-table thead{display:none}
            .jg-admin-table tbody{display:flex;flex-direction:column;gap:10px;padding:10px}
            .jg-admin-table tbody tr{display:grid;grid-template-columns:1fr 1fr;gap:0;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
            .jg-admin-table-wrap{border-radius:12px;overflow:visible;box-shadow:none;border:none;background:transparent}
            .jg-admin-table td{display:flex;flex-direction:column;padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:13px;line-height:1.4}
            .jg-admin-table td::before{content:attr(data-label);font-weight:700;font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
            .jg-td-actions{grid-column:1 / -1;background:#f8fafc;border-top:2px solid #e5e7eb;border-bottom:none}
            .jg-td-main{grid-column:1 / -1;background:#f8fafc;border-bottom:2px solid #e5e7eb}
            .jg-action-btns{flex-direction:column;width:100%}
            .jg-action-btns .button,.jg-action-btns form,.jg-action-btns form button{width:100%;box-sizing:border-box;text-align:center}
        }
        </style>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie promocjami'); ?>

            <div class="jg-info-box">
                <h3>ℹ️ O promocjach:</h3>
                <ul>
                    <li>Miejsca z promocją mają większy, złoty pin z pulsowaniem</li>
                    <li>Nigdy nie są grupowane w klaster - zawsze widoczne</li>
                    <li>Zawsze na szczycie (z-index 10000)</li>
                    <li>Brak możliwości głosowania</li>
                    <li>Można ustawić datę wygaśnięcia promocji</li>
                </ul>
            </div>

            <?php if (!empty($promos)): ?>
            <div class="jg-admin-table-wrap">
              <div class="jg-table-scroll">
                <table class="jg-admin-table">
                    <thead>
                        <tr>
                            <th>Tytuł</th>
                            <th>Typ</th>
                            <th>Data wygaśnięcia</th>
                            <th>Status</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promos as $promo):
                            $expired = false;
                            if ($promo['promo_until']) {
                                $expired = strtotime($promo['promo_until']) < time();
                            }
                            ?>
                            <tr <?php echo $expired ? 'style="opacity:0.6"' : ''; ?>>
                                <td data-label="Tytuł" class="jg-td-main"><strong><?php echo esc_html($promo['title']); ?></strong></td>
                                <td data-label="Typ"><?php echo esc_html($promo['type']); ?></td>
                                <td data-label="Data wygaśnięcia">
                                    <?php if ($promo['promo_until']): ?>
                                        <?php echo get_date_from_gmt($promo['promo_until'], 'Y-m-d H:i'); ?>
                                        <?php if ($expired): ?>
                                            <span style="color:#dc2626;font-weight:700">(Wygasła)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Bez limitu
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <?php if ($expired): ?>
                                        <span style="background:#dc2626;color:#fff;padding:4px 8px;border-radius:4px">Nieaktywna</span>
                                    <?php else: ?>
                                        <span style="background:#16a34a;color:#fff;padding:4px 8px;border-radius:4px">Aktywna</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Akcje" class="jg-td-actions">
                                    <div class="jg-action-btns">
                                        <button type="button" class="button jg-edit-promo-date" data-id="<?php echo $promo['id']; ?>" data-current="<?php echo $promo['promo_until'] ? get_date_from_gmt($promo['promo_until'], 'Y-m-d\TH:i') : ''; ?>">Edytuj datę</button>
                                        <form method="post" onsubmit="return confirm('Na pewno usunąć promocję?');">
                                            <?php wp_nonce_field('jg_promo_action', 'jg_promo_nonce'); ?>
                                            <input type="hidden" name="jg_promo_action" value="1">
                                            <input type="hidden" name="point_id" value="<?php echo $promo['id']; ?>">
                                            <input type="hidden" name="action_type" value="remove">
                                            <button type="submit" class="button">Usuń promocję</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
              </div>
            </div>
            <?php else: ?>
            <p>Brak aktywnych promocji.</p>
            <?php endif; ?>

            <!-- Modal for editing promo date -->
            <div id="jg-promo-date-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;padding:20px;border-radius:8px;max-width:400px;width:90%;">
                    <h2>Edytuj datę wygaśnięcia</h2>
                    <form method="post" id="jg-promo-date-form">
                        <?php wp_nonce_field('jg_promo_action', 'jg_promo_nonce'); ?>
                        <input type="hidden" name="jg_promo_action" value="1">
                        <input type="hidden" name="point_id" id="jg-promo-point-id">
                        <input type="hidden" name="action_type" value="update_date">
                        <p>
                            <label style="display:block;margin-bottom:8px"><strong>Data wygaśnięcia:</strong></label>
                            <input type="datetime-local" name="promo_until" id="jg-promo-until" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px">
                            <small style="display:block;margin-top:4px;color:#666">Pozostaw puste dla promocji bez limitu czasowego</small>
                        </p>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
                            <button type="button" class="button" id="jg-promo-cancel">Anuluj</button>
                            <button type="submit" class="button button-primary">Zapisz</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                var modal = $('#jg-promo-date-modal');

                $('.jg-edit-promo-date').on('click', function() {
                    var pointId = $(this).data('id');
                    var currentDate = $(this).data('current');

                    $('#jg-promo-point-id').val(pointId);
                    $('#jg-promo-until').val(currentDate);
                    modal.css('display', 'flex');
                });

                $('#jg-promo-cancel, #jg-promo-date-modal').on('click', function(e) {
                    if (e.target === this) {
                        modal.hide();
                    }
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render all points page
     */
    public function render_all_points_page() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $points = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'publish' ORDER BY created_at DESC LIMIT 100",
            ARRAY_A
        );

        // PERFORMANCE OPTIMIZATION: Prime user cache to avoid N+1 queries
        if (!empty($points) && function_exists('wp_prime_user_cache')) {
            $author_ids = array_unique(array_filter(array_column($points, 'author_id')));
            if (!empty($author_ids)) {
                wp_prime_user_cache($author_ids);
            }
        }

        ?>
        <div class="wrap">
            <h1>Wszystkie miejsca (ostatnie 100)</h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tytuł</th>
                        <th>Typ</th>
                        <th>Autor</th>
                        <th>Promocja</th>
                        <th>Data utworzenia</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($points as $point):
                        $author = get_userdata($point['author_id']);
                        ?>
                        <tr>
                            <td><?php echo $point['id']; ?></td>
                            <td><strong><?php echo esc_html($point['title']); ?></strong></td>
                            <td><?php echo esc_html($point['type']); ?></td>
                            <td><?php echo $author ? esc_html($author->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo $point['is_promo'] ? '⭐' : '-'; ?></td>
                            <td><?php echo human_time_diff(strtotime($point['created_at'] . ' UTC'), time()); ?> temu</td>
                            <td>
                                <a href="<?php echo get_site_url(); ?>?jg_view_point=<?php echo $point['id']; ?>" class="button button-small">Zobacz</a>
                                <button class="button button-small jg-delete-point" data-id="<?php echo $point['id']; ?>" style="color:#b32d2e">Usuń</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <script>
            jQuery(document).ready(function($) {
                $('.jg-delete-point').on('click', function() {
                    var pointId = $(this).data('id');
                    var btn = $(this);

                    if (!confirm('NA PEWNO usunąć to miejsce? Tej operacji nie można cofnąć!')) {
                        return;
                    }

                    btn.prop('disabled', true).text('Usuwanie...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_delete_point',
                            post_id: pointId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Miejsce usunięte!');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nieznany błąd'));
                                btn.prop('disabled', false).text('Usuń');
                            }
                        },
                        error: function() {
                            alert('Błąd połączenia');
                            btn.prop('disabled', false).text('Usuń');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render roles page
     */
    public function render_roles_page() {
        // Handle role update
        if (isset($_POST['jg_update_roles']) && check_admin_referer('jg_roles_update', 'jg_roles_nonce')) {
            $user_id = intval($_POST['user_id'] ?? 0);
            $action = sanitize_text_field($_POST['role_action'] ?? '');
            $role_type = sanitize_text_field($_POST['role_type'] ?? 'moderator');

            if ($user_id && in_array($action, array('add', 'remove')) && in_array($role_type, array('moderator', 'test_user', 'plugin_admin'))) {
                // Only full WP admins can assign the plugin_admin role
                if ($role_type === 'plugin_admin' && !current_user_can('manage_options')) {
                    echo '<div class="notice notice-error"><p>Tylko administratorzy WordPress mogą nadawać uprawnienia administratora pluginu.</p></div>';
                } else {
                    $user = get_userdata($user_id);
                    if ($user) {
                        if ($role_type === 'moderator') {
                            if ($action === 'add') {
                                $user->add_cap('jg_map_moderate');
                                echo '<div class="notice notice-success"><p>Uprawnienia moderatora dodane!</p></div>';
                            } else {
                                $user->remove_cap('jg_map_moderate');
                                echo '<div class="notice notice-success"><p>Uprawnienia moderatora usunięte!</p></div>';
                            }
                        } elseif ($role_type === 'plugin_admin') {
                            if ($action === 'add') {
                                $user->add_cap('jg_map_admin');
                                // Plugin admin also gets moderator capability
                                $user->add_cap('jg_map_moderate');
                                echo '<div class="notice notice-success"><p>Uprawnienia administratora pluginu dodane!</p></div>';
                            } else {
                                $user->remove_cap('jg_map_admin');
                                $user->remove_cap('jg_map_moderate');
                                echo '<div class="notice notice-success"><p>Uprawnienia administratora pluginu usunięte!</p></div>';
                            }
                        } else { // test_user
                            if ($action === 'add') {
                                $user->add_cap('jg_map_bypass_maintenance');
                                echo '<div class="notice notice-success"><p>Użytkownik oznaczony jako testowy!</p></div>';
                            } else {
                                $user->remove_cap('jg_map_bypass_maintenance');
                                echo '<div class="notice notice-success"><p>Użytkownik przestał być testowym!</p></div>';
                            }
                        }
                    }
                }
            }
        }

        // Get all users
        $users = get_users(array('orderby' => 'registered', 'order' => 'DESC'));

        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie rolami użytkowników'); ?>

            <div class="jg-info-box">
                <h3>ℹ️ O rolach:</h3>
                <ul>
                    <li><strong>WP Admin</strong> - pełny dostęp do WordPressa i wszystkich funkcji pluginu (nadawany przez WP, nie można tu zmienić)</li>
                    <li><strong>Admin pluginu ⭐</strong> - pełny dostęp do wszystkich funkcji pluginu JG Map; nie ma dostępu do rdzennego WP admina</li>
                    <li><strong>Moderator JG Map 🛡️</strong> - może moderować miejsca, zgłoszenia i edycje; widzi tylko Dashboard i zarządzanie użytkownikami</li>
                    <li><strong>Użytkownik testowy</strong> - może logować się pomimo trybu konserwacji w Elementorze</li>
                    <li><strong>Użytkownik</strong> - może dodawać i edytować swoje miejsca</li>
                </ul>
                <p><strong>Uwaga:</strong> Tytuł "Admin pluginu" może nadawać tylko administrator WordPress.</p>
            </div>

            <div class="jg-admin-table-wrap"><div class="jg-table-scroll">
            <table class="jg-admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa użytkownika</th>
                        <th>Email</th>
                        <th>Rola WordPress</th>
                        <th>Admin pluginu</th>
                        <th>Moderator</th>
                        <th>Użytkownik testowy</th>
                        <th>Poziom użytkownika</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $is_wp_admin    = user_can($user->ID, 'manage_options');
                        $is_plugin_admin = !$is_wp_admin && user_can($user->ID, 'jg_map_admin');
                        $is_moderator   = user_can($user->ID, 'jg_map_moderate') && !$is_wp_admin && !$is_plugin_admin;
                        $is_test_user   = user_can($user->ID, 'jg_map_bypass_maintenance');
                        $roles = implode(', ', $user->roles);
                        $user_xp_data = JG_Map_Levels_Achievements::get_user_xp_data($user->ID);
                        $user_level = $user_xp_data['level'];
                        ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td><strong><?php echo esc_html($user->display_name); ?></strong> (<?php echo esc_html($user->user_login); ?>)</td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(ucfirst($roles)); ?></td>
                            <td>
                                <?php if ($is_wp_admin): ?>
                                    <span style="background:#6b7280;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">WP Admin</span>
                                <?php elseif ($is_plugin_admin): ?>
                                    <span style="background:#7c3aed;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Tak</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_wp_admin || $is_plugin_admin): ?>
                                    <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Auto</span>
                                <?php elseif ($is_moderator): ?>
                                    <span style="background:#3b82f6;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Tak</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_wp_admin || $is_plugin_admin): ?>
                                    <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Auto</span>
                                <?php elseif ($is_test_user): ?>
                                    <span style="background:#f59e0b;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Tak</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background:#fbbf24;color:#78350f;padding:4px 10px;border-radius:4px;font-size:calc(12 * var(--jg));font-weight:700">Poz. <?php echo $user_level; ?></span>
                            </td>
                            <td>
                                <?php if ($is_wp_admin): ?>
                                    <em style="color:#6b7280">WP Admin (automatycznie)</em>
                                <?php else: ?>
                                    <?php if (current_user_can('manage_options')): ?>
                                    <!-- Plugin admin buttons (only full WP admins can assign) -->
                                    <form method="post" style="display:inline;margin-right:5px">
                                        <?php wp_nonce_field('jg_roles_update', 'jg_roles_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="jg_update_roles" value="1">
                                        <input type="hidden" name="role_type" value="plugin_admin">
                                        <?php if ($is_plugin_admin): ?>
                                            <input type="hidden" name="role_action" value="remove">
                                            <button type="submit" class="button button-small" style="border-color:#7c3aed;color:#7c3aed" title="Usuń uprawnienia admina pluginu">❌ Admin pluginu</button>
                                        <?php else: ?>
                                            <input type="hidden" name="role_action" value="add">
                                            <button type="submit" class="button button-small" style="background:#7c3aed;border-color:#7c3aed;color:#fff" title="Nadaj uprawnienia admina pluginu">⭐ Admin pluginu</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Moderator buttons (hidden when user is plugin admin) -->
                                    <?php if (!$is_plugin_admin): ?>
                                    <form method="post" style="display:inline;margin-right:5px">
                                        <?php wp_nonce_field('jg_roles_update', 'jg_roles_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="jg_update_roles" value="1">
                                        <input type="hidden" name="role_type" value="moderator">
                                        <?php if ($is_moderator): ?>
                                            <input type="hidden" name="role_action" value="remove">
                                            <button type="submit" class="button button-small" title="Usuń uprawnienia moderatora">❌ Moderator</button>
                                        <?php else: ?>
                                            <input type="hidden" name="role_action" value="add">
                                            <button type="submit" class="button button-small button-primary" title="Dodaj uprawnienia moderatora">➕ Moderator</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Test user buttons -->
                                    <form method="post" style="display:inline">
                                        <?php wp_nonce_field('jg_roles_update', 'jg_roles_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="jg_update_roles" value="1">
                                        <input type="hidden" name="role_type" value="test_user">
                                        <?php if ($is_test_user): ?>
                                            <input type="hidden" name="role_action" value="remove">
                                            <button type="submit" class="button button-small" title="Usuń status testowy">❌ Testowy</button>
                                        <?php else: ?>
                                            <input type="hidden" name="role_action" value="add">
                                            <button type="submit" class="button button-small button-primary" title="Oznacz jako użytkownika testowego">➕ Testowy</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div></div>
        </div>
        <?php
    }

    /**
     * Render deletions page
     */
    public function render_deletions_page() {
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get all deletion requests
        $deletions = $wpdb->get_results(
            "SELECT * FROM $points_table WHERE is_deletion_requested = 1 ORDER BY deletion_requested_at DESC",
            ARRAY_A
        );

        // PERFORMANCE OPTIMIZATION: Prime user cache to avoid N+1 queries
        if (!empty($deletions) && function_exists('wp_prime_user_cache')) {
            $author_ids = array_unique(array_filter(array_column($deletions, 'author_id')));
            if (!empty($author_ids)) {
                wp_prime_user_cache($author_ids);
            }
        }

        ?>
        <div class="wrap">
            <h1>Żądania usunięcia miejsc</h1>

            <?php if (!empty($deletions)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Tytuł</th>
                        <th>Typ</th>
                        <th>Autor</th>
                        <th>Powód</th>
                        <th>Data żądania</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deletions as $point):
                        $author = get_userdata($point['author_id']);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($point['title']); ?></strong></td>
                            <td><?php echo esc_html($point['type']); ?></td>
                            <td><?php echo $author ? esc_html($author->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo $point['deletion_reason'] ? esc_html($point['deletion_reason']) : '<em>Brak powodu</em>'; ?></td>
                            <td><?php echo human_time_diff(strtotime($point['deletion_requested_at'] . ' UTC'), time()); ?> temu</td>
                            <td>
                                <a href="<?php echo get_site_url(); ?>?jg_view_point=<?php echo $point['id']; ?>" class="button" target="_blank">Zobacz miejsce</a>
                                <button class="button button-primary jg-approve-deletion" data-id="<?php echo $point['id']; ?>">Zatwierdź usunięcie</button>
                                <button class="button jg-reject-deletion" data-id="<?php echo $point['id']; ?>">Odrzuć</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <script>
            jQuery(document).ready(function($) {
                // Approve deletion
                $('.jg-approve-deletion').on('click', function() {
                    if (!confirm('Na pewno usunąć to miejsce? Tej operacji nie można cofnąć!')) return;

                    var btn = $(this);
                    var pointId = btn.data('id');
                    btn.prop('disabled', true).text('Usuwanie...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_approve_deletion',
                            post_id: pointId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Miejsce zostało usunięte!');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nieznany błąd'));
                                btn.prop('disabled', false).text('Zatwierdź usunięcie');
                            }
                        },
                        error: function() {
                            alert('Błąd połączenia');
                            btn.prop('disabled', false).text('Zatwierdź usunięcie');
                        }
                    });
                });

                // Reject deletion
                $('.jg-reject-deletion').on('click', function() {
                    if (!confirm('Odrzucić żądanie usunięcia?')) return;

                    var btn = $(this);
                    var pointId = btn.data('id');
                    btn.prop('disabled', true).text('Odrzucanie...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_reject_deletion',
                            post_id: pointId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Żądanie usunięcia zostało odrzucone!');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nieznany błąd'));
                                btn.prop('disabled', false).text('Odrzuć');
                            }
                        },
                        error: function() {
                            alert('Błąd połączenia');
                            btn.prop('disabled', false).text('Odrzuć');
                        }
                    });
                });
            });
            </script>
            <?php else: ?>
            <p>Brak żądań usunięcia! 🎉</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render gallery page
     */
    public function render_gallery_page() {
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get all points with images
        $points = $wpdb->get_results(
            "SELECT id, title, images, type, author_id, created_at FROM $points_table
            WHERE status = 'publish' AND images IS NOT NULL AND images != '[]'
            ORDER BY created_at DESC LIMIT 200",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <?php $this->render_page_header('Galeria wszystkich zdjęć'); ?>

            <div class="jg-card jg-card-body" style="margin-bottom:20px">
                <p style="margin:0"><strong>Łącznie miejsc ze zdjęciami:</strong> <?php echo count($points); ?></p>
            </div>

            <?php if (!empty($points)): ?>
                <div class="jg-card">
                <div class="jg-gallery-grid">
                    <?php foreach ($points as $point):
                        $images = json_decode($point['images'], true);
                        if (empty($images)) continue;

                        $author = get_userdata($point['author_id']);
                        ?>
                        <div class="jg-gallery-card">
                            <div style="position:relative;height:200px;background:#f5f5f5">
                                <img src="<?php echo esc_url($images[0]['thumb'] ?? $images[0]['full']); ?>"
                                     style="width:100%;height:100%;object-fit:cover"
                                     alt="<?php echo esc_attr($point['title']); ?>">
                                <?php if (count($images) > 1): ?>
                                    <span style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg));font-weight:700">
                                        +<?php echo count($images) - 1; ?> zdjęć
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="jg-gallery-card-body">
                                <h3><?php echo esc_html($point['title']); ?></h3>
                                <p>
                                    <strong><?php echo esc_html($point['type']); ?></strong> •
                                    <?php echo $author ? esc_html($author->display_name) : 'Nieznany'; ?> •
                                    <?php echo human_time_diff(strtotime($point['created_at'] . ' UTC'), time()); ?> temu
                                </p>
                                <div style="display:flex;gap:8px;flex-wrap:wrap">
                                    <a href="<?php echo get_site_url(); ?>?jg_view_point=<?php echo $point['id']; ?>"
                                       class="button button-small" target="_blank">Zobacz miejsce</a>
                                    <button class="button button-small jg-view-all-images"
                                            data-images='<?php echo esc_attr(json_encode($images)); ?>'
                                            data-title="<?php echo esc_attr($point['title']); ?>"
                                            data-point-id="<?php echo $point['id']; ?>">
                                        Wszystkie zdjęcia
                                    </button>
                                    <button class="button button-small button-link-delete jg-delete-all-images"
                                            data-point-id="<?php echo $point['id']; ?>"
                                            style="color:#dc2626">
                                        Usuń wszystkie
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>

                <!-- Lightbox modal -->
                <div id="jg-gallery-lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;padding:20px">
                    <div style="position:relative;max-width:1200px;width:100%">
                        <button id="jg-gallery-close" style="position:absolute;top:-40px;right:0;background:#fff;border:none;border-radius:4px;padding:8px 16px;cursor:pointer;font-weight:700">✕ Zamknij</button>
                        <h2 id="jg-gallery-title" style="color:#fff;margin-bottom:20px"></h2>
                        <div id="jg-gallery-images" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px"></div>
                    </div>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    var lightbox = $('#jg-gallery-lightbox');
                    var imagesContainer = $('#jg-gallery-images');
                    var titleEl = $('#jg-gallery-title');
                    var currentPointId = null;

                    $('.jg-view-all-images').on('click', function() {
                        var images = $(this).data('images');
                        var title = $(this).data('title');
                        currentPointId = $(this).data('point-id');

                        titleEl.text(title);
                        imagesContainer.empty();

                        images.forEach(function(img, idx) {
                            var container = $('<div>').css({
                                position: 'relative',
                                borderRadius: '8px',
                                overflow: 'hidden'
                            });

                            var deleteBtn = $('<button>')
                                .text('×')
                                .addClass('jg-delete-single-image')
                                .attr('data-point-id', currentPointId)
                                .attr('data-image-index', idx)
                                .css({
                                    position: 'absolute',
                                    top: '8px',
                                    right: '8px',
                                    background: 'rgba(220,38,38,0.9)',
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: '4px',
                                    width: '32px',
                                    height: '32px',
                                    cursor: 'pointer',
                                    fontWeight: '700',
                                    fontSize: '20px',
                                    zIndex: 10
                                })
                                .attr('title', 'Usuń zdjęcie');

                            container.append(deleteBtn);
                            container.append(
                                $('<a>').attr({
                                    href: img.full,
                                    target: '_blank'
                                }).css({
                                    display: 'block'
                                }).append(
                                    $('<img>').attr('src', img.thumb || img.full).css({
                                        width: '100%',
                                        height: '250px',
                                        objectFit: 'cover',
                                        display: 'block'
                                    })
                                )
                            );

                            imagesContainer.append(container);
                        });

                        lightbox.css('display', 'flex');
                    });

                    // Delete single image
                    $(document).on('click', '.jg-delete-single-image', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        if (!confirm('Czy na pewno chcesz usunąć to zdjęcie?')) {
                            return;
                        }

                        var btn = $(this);
                        var pointId = btn.data('point-id');
                        var imageIndex = btn.data('image-index');

                        btn.prop('disabled', true).text('...');

                        $.post(ajaxurl, {
                            action: 'jg_delete_image',
                            point_id: pointId,
                            image_index: imageIndex,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        }, function(response) {
                            if (response.success) {
                                alert('Zdjęcie usunięte');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nie udało się usunąć'));
                                btn.prop('disabled', false).text('×');
                            }
                        });
                    });

                    // Delete all images
                    $('.jg-delete-all-images').on('click', function(e) {
                        e.preventDefault();

                        if (!confirm('Czy na pewno chcesz usunąć WSZYSTKIE zdjęcia z tego miejsca? Tej operacji nie można cofnąć!')) {
                            return;
                        }

                        var btn = $(this);
                        var pointId = btn.data('point-id');

                        btn.prop('disabled', true).text('Usuwanie...');

                        // Delete images one by one from the end
                        function deleteNextImage(index) {
                            if (index < 0) {
                                alert('Wszystkie zdjęcia zostały usunięte');
                                location.reload();
                                return;
                            }

                            $.post(ajaxurl, {
                                action: 'jg_delete_image',
                                point_id: pointId,
                                image_index: index,
                                _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                            }, function(response) {
                                if (response.success) {
                                    // Continue with next image (always delete index 0 since array shrinks)
                                    deleteNextImage(index - 1);
                                } else {
                                    alert('Błąd: ' + (response.data.message || 'Nie udało się usunąć'));
                                    btn.prop('disabled', false).text('Usuń wszystkie');
                                }
                            });
                        }

                        // Start from the last image
                        $.get(ajaxurl, {
                            action: 'jg_get_points'
                        }, function(response) {
                            if (response.success && response.data) {
                                var point = response.data.find(function(p) { return p.id == pointId; });
                                if (point && point.images) {
                                    deleteNextImage(point.images.length - 1);
                                }
                            }
                        });
                    });

                    $('#jg-gallery-close, #jg-gallery-lightbox').on('click', function(e) {
                        if (e.target === this) {
                            lightbox.hide();
                        }
                    });
                });
                </script>
            <?php else: ?>
                <p>Brak miejsc ze zdjęciami.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Activity Log page
     */
    public function render_activity_log_page() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'jg_map_activity_log';

        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Filters
        $action_filter = isset($_GET['action_filter']) ? sanitize_text_field($_GET['action_filter']) : '';
        $user_filter = isset($_GET['user_filter']) ? intval($_GET['user_filter']) : 0;
        $role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : '';

        // Build query
        $where = array('1=1');
        if ($action_filter) {
            $where[] = $wpdb->prepare('action = %s', $action_filter);
        }
        if ($user_filter) {
            $where[] = $wpdb->prepare('user_id = %d', $user_filter);
        }

        // Role filter - get user IDs by role (queries run once and shared between both branches)
        if ($role_filter === 'admin' || $role_filter === 'user') {
            $admin_users   = get_users(array('role__in' => array('administrator'), 'fields' => 'ID'));
            $mod_users     = get_users(array('capability' => 'jg_map_moderate', 'fields' => 'ID'));
            $admin_mod_ids = array_unique(array_merge($admin_users, $mod_users));
            if ($role_filter === 'admin') {
                if (!empty($admin_mod_ids)) {
                    $placeholders = implode(',', array_fill(0, count($admin_mod_ids), '%d'));
                    $where[] = $wpdb->prepare("user_id IN ($placeholders)", $admin_mod_ids);
                } else {
                    $where[] = '1=0';
                }
            } else { // 'user'
                if (!empty($admin_mod_ids)) {
                    $placeholders = implode(',', array_fill(0, count($admin_mod_ids), '%d'));
                    $where[] = $wpdb->prepare("user_id NOT IN ($placeholders)", $admin_mod_ids);
                }
            }
        }

        $where_clause = implode(' AND ', $where);

        // Get logs (LIMIT/OFFSET are intval-sanitized above, safe to interpolate)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $logs = $wpdb->get_results(
            "SELECT * FROM $log_table WHERE $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );

        // PERFORMANCE OPTIMIZATION: Prime user cache for log authors to avoid N+1 queries
        if (!empty($logs) && function_exists('wp_prime_user_cache')) {
            $log_user_ids = array_unique(array_filter(array_column($logs, 'user_id')));
            if (!empty($log_user_ids)) {
                wp_prime_user_cache($log_user_ids);
            }
        }

        // Get total count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE $where_clause");
        $total_pages = ceil($total / $per_page);

        // Get unique actions for filter dropdown (capped at 200 — more than enough)
        $actions = $wpdb->get_col("SELECT DISTINCT action FROM $log_table ORDER BY action LIMIT 200");

        // Get users who have logged actions (capped at 500)
        $users_with_logs = $wpdb->get_results(
            "SELECT DISTINCT user_id FROM $log_table ORDER BY user_id LIMIT 500"
        );

        // PERFORMANCE OPTIMIZATION: Prime user cache for filter dropdown to avoid N+1 queries
        if (!empty($users_with_logs) && function_exists('wp_prime_user_cache')) {
            $filter_user_ids = array_unique(array_filter(array_column($users_with_logs, 'user_id')));
            if (!empty($filter_user_ids)) {
                wp_prime_user_cache($filter_user_ids);
            }
        }

        ?>
        <div class="wrap">
            <?php $this->render_page_header('Activity Log'); ?>

            <div class="jg-card jg-card-body" style="margin-bottom:20px">
                <form method="get" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap">
                    <input type="hidden" name="page" value="jg-map-activity-log">

                    <div>
                        <label style="display:block;margin-bottom:5px;font-weight:600">Filtruj po akcji:</label>
                        <select name="action_filter" style="padding:5px">
                            <option value="">Wszystkie akcje</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo esc_attr($action); ?>" <?php selected($action_filter, $action); ?>>
                                    <?php echo esc_html($action); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display:block;margin-bottom:5px;font-weight:600">Filtruj po roli:</label>
                        <select name="role_filter" style="padding:5px">
                            <option value="">Wszystkie role</option>
                            <option value="admin" <?php selected($role_filter, 'admin'); ?>>Admin / Moderator</option>
                            <option value="user" <?php selected($role_filter, 'user'); ?>>Zwykli użytkownicy</option>
                        </select>
                    </div>

                    <div>
                        <label style="display:block;margin-bottom:5px;font-weight:600">Filtruj po użytkowniku:</label>
                        <select name="user_filter" style="padding:5px">
                            <option value="0">Wszyscy użytkownicy</option>
                            <?php foreach ($users_with_logs as $u):
                                $user = get_userdata($u->user_id);
                                if ($user):
                            ?>
                                <option value="<?php echo $u->user_id; ?>" <?php selected($user_filter, $u->user_id); ?>>
                                    <?php echo esc_html($user->display_name); ?> (ID: <?php echo $u->user_id; ?>)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="button">Filtruj</button>
                    <?php if ($action_filter || $user_filter || $role_filter): ?>
                        <a href="<?php echo admin_url('admin.php?page=jg-map-activity-log'); ?>" class="button">Wyczyść filtry</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($logs)): ?>
            <div class="jg-admin-table-wrap">
            <div class="jg-table-scroll">
            <table class="jg-admin-table">
                <thead>
                    <tr>
                        <th style="width:150px">Data</th>
                        <th style="width:120px">Użytkownik</th>
                        <th style="width:150px">Akcja</th>
                        <th style="width:100px">Typ obiektu</th>
                        <th style="width:80px">ID obiektu</th>
                        <th>Opis</th>
                        <th style="width:120px">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $user = get_userdata($log['user_id']);
                        $user_name = $user ? $user->display_name : 'Użytkownik #' . $log['user_id'];
                        $is_admin_user = $user && (user_can($user->ID, 'manage_options') || user_can($user->ID, 'jg_map_moderate'));
                        $role_badge = $is_admin_user
                            ? '<span style="background:#d63638;color:#fff;padding:1px 6px;border-radius:3px;font-size:calc(11 * var(--jg));margin-left:4px">Admin</span>'
                            : '<span style="background:#2271b1;color:#fff;padding:1px 6px;border-radius:3px;font-size:calc(11 * var(--jg));margin-left:4px">User</span>';
                    ?>
                        <tr>
                            <td><?php echo esc_html(get_date_from_gmt($log['created_at'], 'Y-m-d H:i:s')); ?></td>
                            <td><?php echo esc_html($user_name); ?> <?php echo $role_badge; ?></td>
                            <td><strong><?php echo esc_html($log['action']); ?></strong></td>
                            <td><?php echo esc_html($log['object_type']); ?></td>
                            <td><?php echo esc_html($log['object_id'] ?: '-'); ?></td>
                            <td><?php echo esc_html($log['description']); ?></td>
                            <td><code><?php echo esc_html($log['ip_address']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            </div>

            <div class="tablenav bottom" style="padding-top:10px">
                <div class="tablenav-pages">
                    <?php if ($total_pages > 1): ?>
                        <span class="displaying-num"><?php echo number_format($total); ?> wpisów</span>
                        <span class="pagination-links">
                            <?php for ($i = 1; $i <= $total_pages; $i++):
                                $url = add_query_arg(array(
                                    'page' => 'jg-map-activity-log',
                                    'paged' => $i,
                                    'action_filter' => $action_filter,
                                    'user_filter' => $user_filter,
                                    'role_filter' => $role_filter
                                ), admin_url('admin.php'));
                            ?>
                                <?php if ($i === $current_page): ?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a class="button" href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <p>Brak wpisów w activity log.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Handle form submission
        if (isset($_POST['jg_map_save_settings']) && check_admin_referer('jg_map_settings_nonce')) {
            $onboarding_enabled = isset($_POST['jg_map_onboarding_enabled']) ? 1 : 0;
            update_option('jg_map_onboarding_enabled', $onboarding_enabled);

            $registration_enabled = isset($_POST['jg_map_registration_enabled']) ? 1 : 0;
            $registration_disabled_message = sanitize_textarea_field($_POST['jg_map_registration_disabled_message'] ?? '');

            update_option('jg_map_registration_enabled', $registration_enabled);
            update_option('jg_map_registration_disabled_message', $registration_disabled_message);

            // Terms of service settings
            $terms_type = sanitize_text_field($_POST['jg_map_terms_type'] ?? 'url');
            if ($terms_type === 'url') {
                update_option('jg_map_terms_url', esc_url_raw($_POST['jg_map_terms_url'] ?? ''));
                update_option('jg_map_terms_content', '');
            } else {
                update_option('jg_map_terms_url', '');
                update_option('jg_map_terms_content', wp_kses_post($_POST['jg_map_terms_content'] ?? ''));
            }

            // Privacy policy settings
            $privacy_type = sanitize_text_field($_POST['jg_map_privacy_type'] ?? 'url');
            if ($privacy_type === 'url') {
                update_option('jg_map_privacy_url', esc_url_raw($_POST['jg_map_privacy_url'] ?? ''));
                update_option('jg_map_privacy_content', '');
            } else {
                update_option('jg_map_privacy_url', '');
                update_option('jg_map_privacy_content', wp_kses_post($_POST['jg_map_privacy_content'] ?? ''));
            }

            // Social OAuth credentials
            update_option('jg_map_google_client_id', sanitize_text_field($_POST['jg_map_google_client_id'] ?? ''));
            update_option('jg_map_google_client_secret', sanitize_text_field($_POST['jg_map_google_client_secret'] ?? ''));
            update_option('jg_map_facebook_app_id', sanitize_text_field($_POST['jg_map_facebook_app_id'] ?? ''));
            update_option('jg_map_facebook_app_secret', sanitize_text_field($_POST['jg_map_facebook_app_secret'] ?? ''));

            // IndexNow: regenerate key if requested
            if (!empty($_POST['jg_map_indexnow_regenerate'])) {
                update_option('jg_map_indexnow_key', wp_generate_uuid4());
            }

            echo '<div class="notice notice-success is-dismissible"><p>Ustawienia zostały zapisane.</p></div>';
        }

        // Auto-generate IndexNow key on first use (no manual setup needed)
        $indexnow_key = get_option('jg_map_indexnow_key', '');
        if ($indexnow_key === '') {
            $indexnow_key = wp_generate_uuid4();
            update_option('jg_map_indexnow_key', $indexnow_key);
        }

        $onboarding_enabled = get_option('jg_map_onboarding_enabled', 1); // Enabled by default
        $registration_enabled = get_option('jg_map_registration_enabled', 1); // Enabled by default
        $registration_disabled_message = get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.');
        $terms_url = get_option('jg_map_terms_url', '');
        $terms_content = get_option('jg_map_terms_content', '');
        $terms_type = $terms_url ? 'url' : ($terms_content ? 'content' : 'url');
        $privacy_url = get_option('jg_map_privacy_url', '');
        $privacy_content = get_option('jg_map_privacy_content', '');
        $privacy_type = $privacy_url ? 'url' : ($privacy_content ? 'content' : 'url');
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Ustawienia JG Map'); ?>

            <form method="post" action="">
                <?php wp_nonce_field('jg_map_settings_nonce'); ?>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Onboarding i samouczek</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="jg_map_onboarding_enabled">Samouczek</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="jg_map_onboarding_enabled"
                                           id="jg_map_onboarding_enabled"
                                           value="1"
                                           <?php checked($onboarding_enabled, 1); ?>>
                                    <strong>Włącz onboarding dla użytkowników</strong>
                                </label>
                                <p class="description">
                                    Gdy włączone: nowym użytkownikom wyświetla się modal powitalny, wskazówki kontekstowe (tipy) oraz tooltopy na elementach UI.
                                    Gdy wyłączone: żadna z warstw samouczka nie jest inicjalizowana — przycisk pomocy (?) i panel pomocy również nie pojawią się na mapie.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Rejestracja użytkowników</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="jg_map_registration_enabled">Rejestracja</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="jg_map_registration_enabled"
                                           id="jg_map_registration_enabled"
                                           value="1"
                                           <?php checked($registration_enabled, 1); ?>>
                                    <strong>Włącz rejestrację nowych użytkowników</strong>
                                </label>
                                <p class="description">
                                    Gdy wyłączone, zakładka rejestracji w modalu pokaże komunikat zamiast formularza.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="jg_map_registration_disabled_message">Komunikat gdy wyłączona</label>
                            </th>
                            <td>
                                <textarea name="jg_map_registration_disabled_message"
                                          id="jg_map_registration_disabled_message"
                                          rows="3"
                                          class="large-text"
                                          placeholder="Rejestracja jest obecnie wyłączona. Spróbuj ponownie później."><?php echo esc_textarea($registration_disabled_message); ?></textarea>
                                <p class="description">
                                    Ten komunikat zostanie wyświetlony użytkownikom gdy rejestracja jest wyłączona.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Regulamin serwisu</h2>
                    <p class="description" style="margin-bottom:16px">Dokument regulaminu wyświetlany w formularzu rejestracji. Możesz podać URL istniejącej podstrony WordPress lub wpisać treść bezpośrednio.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Źródło regulaminu</th>
                            <td>
                                <fieldset>
                                    <label style="display:block;margin-bottom:8px">
                                        <input type="radio" name="jg_map_terms_type" value="url" <?php checked($terms_type, 'url'); ?> class="jg-terms-type-radio">
                                        <strong>URL podstrony WordPress</strong>
                                    </label>
                                    <label style="display:block">
                                        <input type="radio" name="jg_map_terms_type" value="content" <?php checked($terms_type, 'content'); ?> class="jg-terms-type-radio">
                                        <strong>Wpisz treść</strong>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr class="jg-terms-url-row" <?php echo $terms_type === 'content' ? 'style="display:none"' : ''; ?>>
                            <th scope="row">
                                <label for="jg_map_terms_url">URL regulaminu</label>
                            </th>
                            <td>
                                <div style="position:relative">
                                    <input type="text"
                                           name="jg_map_terms_url"
                                           id="jg_map_terms_url"
                                           value="<?php echo esc_attr($terms_url); ?>"
                                           class="large-text jg-page-autocomplete"
                                           placeholder="Zacznij pisać nazwę strony..."
                                           autocomplete="off">
                                    <div id="jg_map_terms_url_suggestions" class="jg-autocomplete-suggestions" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;border-radius:0 0 4px 4px;max-height:200px;overflow:auto;z-index:100;box-shadow:0 4px 6px rgba(0,0,0,0.1)"></div>
                                </div>
                                <p class="description">Podaj URL lub zacznij wpisywać nazwę strony WordPress, aby zobaczyć podpowiedzi.</p>
                            </td>
                        </tr>
                        <tr class="jg-terms-content-row" <?php echo $terms_type === 'url' ? 'style="display:none"' : ''; ?>>
                            <th scope="row">
                                <label for="jg_map_terms_content">Treść regulaminu</label>
                            </th>
                            <td>
                                <textarea name="jg_map_terms_content"
                                          id="jg_map_terms_content"
                                          rows="10"
                                          class="large-text"
                                          placeholder="Wpisz treść regulaminu..."><?php echo esc_textarea($terms_content); ?></textarea>
                                <p class="description">Treść regulaminu zostanie wyświetlona użytkownikom w okienku modalnym. Dozwolony HTML.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Polityka prywatności</h2>
                    <p class="description" style="margin-bottom:16px">Dokument polityki prywatności wyświetlany w formularzu rejestracji. Możesz podać URL istniejącej podstrony WordPress lub wpisać treść bezpośrednio.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Źródło polityki prywatności</th>
                            <td>
                                <fieldset>
                                    <label style="display:block;margin-bottom:8px">
                                        <input type="radio" name="jg_map_privacy_type" value="url" <?php checked($privacy_type, 'url'); ?> class="jg-privacy-type-radio">
                                        <strong>URL podstrony WordPress</strong>
                                    </label>
                                    <label style="display:block">
                                        <input type="radio" name="jg_map_privacy_type" value="content" <?php checked($privacy_type, 'content'); ?> class="jg-privacy-type-radio">
                                        <strong>Wpisz treść</strong>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr class="jg-privacy-url-row" <?php echo $privacy_type === 'content' ? 'style="display:none"' : ''; ?>>
                            <th scope="row">
                                <label for="jg_map_privacy_url">URL polityki prywatności</label>
                            </th>
                            <td>
                                <div style="position:relative">
                                    <input type="text"
                                           name="jg_map_privacy_url"
                                           id="jg_map_privacy_url"
                                           value="<?php echo esc_attr($privacy_url); ?>"
                                           class="large-text jg-page-autocomplete"
                                           placeholder="Zacznij pisać nazwę strony..."
                                           autocomplete="off">
                                    <div id="jg_map_privacy_url_suggestions" class="jg-autocomplete-suggestions" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-top:none;border-radius:0 0 4px 4px;max-height:200px;overflow:auto;z-index:100;box-shadow:0 4px 6px rgba(0,0,0,0.1)"></div>
                                </div>
                                <p class="description">Podaj URL lub zacznij wpisywać nazwę strony WordPress, aby zobaczyć podpowiedzi.</p>
                            </td>
                        </tr>
                        <tr class="jg-privacy-content-row" <?php echo $privacy_type === 'url' ? 'style="display:none"' : ''; ?>>
                            <th scope="row">
                                <label for="jg_map_privacy_content">Treść polityki prywatności</label>
                            </th>
                            <td>
                                <textarea name="jg_map_privacy_content"
                                          id="jg_map_privacy_content"
                                          rows="10"
                                          class="large-text"
                                          placeholder="Wpisz treść polityki prywatności..."><?php echo esc_textarea($privacy_content); ?></textarea>
                                <p class="description">Treść polityki prywatności zostanie wyświetlona użytkownikom w okienku modalnym. Dozwolony HTML.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>Logowanie przez media społecznościowe (OAuth)</h2>
                    <p class="description" style="margin-bottom:16px">
                        Aby umożliwić logowanie przez Google i Facebook, utwórz aplikacje OAuth w odpowiednich konsolach
                        deweloperskich i wpisz poniżej dane uwierzytelniające. Pola zostaw puste, aby wyłączyć dany provider.
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="jg_map_google_client_id">Google Client ID</label></th>
                            <td>
                                <input type="text" name="jg_map_google_client_id" id="jg_map_google_client_id"
                                       value="<?php echo esc_attr(get_option('jg_map_google_client_id', '')); ?>"
                                       class="regular-text" placeholder="xxxxxxxxxxxx-xxxxxxxx.apps.googleusercontent.com">
                                <p class="description">Redirect URI do ustawienia w Google Cloud Console:<br>
                                    <code><?php echo esc_html(admin_url('admin-ajax.php') . '?action=jg_google_oauth_callback'); ?></code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="jg_map_google_client_secret">Google Client Secret</label></th>
                            <td>
                                <input type="password" name="jg_map_google_client_secret" id="jg_map_google_client_secret"
                                       value="<?php echo esc_attr(get_option('jg_map_google_client_secret', '')); ?>"
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="jg_map_facebook_app_id">Facebook App ID</label></th>
                            <td>
                                <input type="text" name="jg_map_facebook_app_id" id="jg_map_facebook_app_id"
                                       value="<?php echo esc_attr(get_option('jg_map_facebook_app_id', '')); ?>"
                                       class="regular-text" placeholder="123456789012345">
                                <p class="description">Redirect URI do ustawienia w Facebook Developer Console:<br>
                                    <code><?php echo esc_html(admin_url('admin-ajax.php') . '?action=jg_facebook_oauth_callback'); ?></code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="jg_map_facebook_app_secret">Facebook App Secret</label></th>
                            <td>
                                <input type="password" name="jg_map_facebook_app_secret" id="jg_map_facebook_app_secret"
                                       value="<?php echo esc_attr(get_option('jg_map_facebook_app_secret', '')); ?>"
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="jg-card jg-card-body" style="max-width:800px">
                    <h2>IndexNow – automatyczne powiadamianie wyszukiwarek</h2>
                    <p class="description" style="margin-bottom:16px">
                        IndexNow to protokół obsługiwany przez Bing i Yandex. Plugin automatycznie pinguje wyszukiwarki
                        za każdym razem gdy zatwierdzisz nowe miejsce lub edycję. Klucz poniżej jest generowany
                        automatycznie i hostowany przez plugin pod adresem
                        <code><?php echo esc_html(home_url('/' . $indexnow_key . '.txt')); ?></code> –
                        <strong>nie musisz nic konfigurować ani rejestrować się nigdzie</strong>.
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Klucz IndexNow</th>
                            <td>
                                <code style="font-size:14px;background:#f6f7f7;padding:6px 10px;border-radius:4px;display:inline-block;letter-spacing:.5px"><?php echo esc_html($indexnow_key); ?></code>
                                <p class="description" style="margin-top:8px">
                                    Plik weryfikacyjny dostępny pod:
                                    <a href="<?php echo esc_url(home_url('/' . $indexnow_key . '.txt')); ?>" target="_blank"><?php echo esc_html(home_url('/' . $indexnow_key . '.txt')); ?></a>
                                </p>
                                <label style="display:block;margin-top:12px">
                                    <input type="checkbox" name="jg_map_indexnow_regenerate" value="1">
                                    Wygeneruj nowy klucz (użyj tylko gdy coś nie działa)
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit"
                           name="jg_map_save_settings"
                           id="submit"
                           class="button button-primary"
                           value="Zapisz ustawienia">
                </p>
            </form>
        </div>

        <script>
        jQuery(function($) {
            // Toggle terms URL/content fields based on radio selection
            $('.jg-terms-type-radio').on('change', function() {
                if ($(this).val() === 'url') {
                    $('.jg-terms-url-row').show();
                    $('.jg-terms-content-row').hide();
                } else {
                    $('.jg-terms-url-row').hide();
                    $('.jg-terms-content-row').show();
                }
            });

            // Toggle privacy URL/content fields based on radio selection
            $('.jg-privacy-type-radio').on('change', function() {
                if ($(this).val() === 'url') {
                    $('.jg-privacy-url-row').show();
                    $('.jg-privacy-content-row').hide();
                } else {
                    $('.jg-privacy-url-row').hide();
                    $('.jg-privacy-content-row').show();
                }
            });

            // Page URL autocomplete for both terms and privacy URL fields
            var autocompleteTimer = null;
            $('.jg-page-autocomplete').on('input', function() {
                var input = $(this);
                var suggestionsDiv = input.parent().find('.jg-autocomplete-suggestions');
                var query = input.val().trim();

                clearTimeout(autocompleteTimer);

                if (query.length < 2) {
                    suggestionsDiv.hide().empty();
                    return;
                }

                // If it looks like a URL already, don't search
                if (query.indexOf('http') === 0 || query.indexOf('/') === 0) {
                    suggestionsDiv.hide().empty();
                    return;
                }

                autocompleteTimer = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'jg_map_search_pages',
                            search: query,
                            _wpnonce: '<?php echo wp_create_nonce('jg_map_search_pages'); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data && response.data.length > 0) {
                                var html = '';
                                $.each(response.data, function(i, page) {
                                    html += '<div class="jg-autocomplete-item" data-url="' + page.url + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:calc(13 * var(--jg))">' +
                                        '<strong>' + page.title + '</strong>' +
                                        '<div style="color:#999;font-size:calc(11 * var(--jg));margin-top:2px">' + page.url + '</div>' +
                                    '</div>';
                                });
                                suggestionsDiv.html(html).show();

                                suggestionsDiv.find('.jg-autocomplete-item').on('mouseenter', function() {
                                    $(this).css('background', '#f0f0f0');
                                }).on('mouseleave', function() {
                                    $(this).css('background', '#fff');
                                }).on('click', function() {
                                    input.val($(this).data('url'));
                                    suggestionsDiv.hide().empty();
                                });
                            } else {
                                suggestionsDiv.hide().empty();
                            }
                        }
                    });
                }, 300);
            });

            // Hide suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.jg-page-autocomplete, .jg-autocomplete-suggestions').length) {
                    $('.jg-autocomplete-suggestions').hide().empty();
                }
            });

        });
        </script>
        <?php
    }

    /**
     * Heartbeat API handler for real-time notifications
     */
    public function heartbeat_received($response, $data) {
        // Check if our heartbeat request is present (only for admins/moderators)
        if (!empty($data['jg_map_check_notifications'])) {
            // Only for admins and moderators
            if (current_user_can('manage_options') || current_user_can('jg_map_moderate')) {
                $response['jg_map_notifications'] = $this->get_pending_counts();
            }
        }

        // Check for map updates (for ALL users - admins, moderators, and regular users)
        if (!empty($data['jg_map_check_updates'])) {
            global $wpdb;
            $points_table = JG_Map_Database::get_points_table();
            $last_check = !empty($data['jg_map_last_check']) ? intval($data['jg_map_last_check']) : 0;
            $last_check_date = date('Y-m-d H:i:s', $last_check / 1000); // Convert JS timestamp to MySQL datetime

            // Count new points since last check
            // Include both newly created published points AND newly approved points
            $new_points = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $points_table
                 WHERE status = 'publish'
                 AND (
                    created_at > %s
                    OR approved_at > %s
                    OR updated_at > %s
                 )",
                $last_check_date,
                $last_check_date,
                $last_check_date
            ));

            // Get rejected points since last check
            $rejected_points = get_transient('jg_map_rejected_points');
            $rejected_ids = array();
            if (is_array($rejected_points)) {
                foreach ($rejected_points as $rejected) {
                    if ($rejected['timestamp'] >= ($last_check / 1000)) {
                        $rejected_ids[] = intval($rejected['id']);
                    }
                }
            }

            // Get deleted points since last check
            $deleted_points = get_transient('jg_map_deleted_points');
            $deleted_ids = array();
            if (is_array($deleted_points)) {
                foreach ($deleted_points as $deleted) {
                    if ($deleted['timestamp'] >= ($last_check / 1000)) {
                        $deleted_ids[] = intval($deleted['id']);
                    }
                }
            }

            // Get updated points since last check
            $updated_points = get_transient('jg_map_updated_points');
            $updated_ids = array();
            if (is_array($updated_points)) {
                foreach ($updated_points as $updated) {
                    if ($updated['timestamp'] >= ($last_check / 1000)) {
                        $updated_ids[] = intval($updated['id']);
                    }
                }
            }

            $response['jg_map_updates'] = array(
                'has_new_points' => intval($new_points) > 0,
                'new_count' => intval($new_points),
                'last_check' => $last_check_date,
                'rejected_points' => $rejected_ids,
                'deleted_points' => $deleted_ids,
                'updated_points' => $updated_ids
            );
        }

        return $response;
    }

    /**
     * Enqueue admin bar script for real-time updates
     */
    /**
     * Shared CSS for all JG Map admin pages
     */
    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'jg-map') === false) {
            return;
        }
        $css = '
        /* ===== JG Admin — shared page chrome ===== */
        .jg-page-header{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:20px}
        .jg-page-header h1{margin:0;flex:1 1 auto;font-size:22px}
        .jg-back-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1d4ed8;color:#fff!important;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;border:none;cursor:pointer;transition:background .15s}
        .jg-back-btn:hover{background:#1e40af;color:#fff!important;text-decoration:none}

        /* ===== JG Admin — card ===== */
        .jg-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px;overflow:hidden}
        .jg-card-body{padding:18px 20px}
        .jg-card-body > h2:first-child,.jg-card-body > h3:first-child{margin-top:0;padding-bottom:12px;border-bottom:1px solid #e5e7eb;margin-bottom:16px;font-size:15px;font-weight:700;color:#111827}

        /* ===== JG Admin — table ===== */
        .jg-admin-table-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
        .jg-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
        .jg-admin-table{width:100%;border-collapse:collapse;font-size:13px}
        .jg-admin-table th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#374151;border-bottom:2px solid #e5e7eb;white-space:nowrap;text-transform:uppercase;letter-spacing:.4px}
        .jg-admin-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .jg-admin-table tbody tr:last-child td{border-bottom:none}
        .jg-admin-table tbody tr:hover{background:#f8fafc}

        /* ===== JG Admin — info box ===== */
        .jg-info-box{background:#fff7e6;border:2px solid #f59e0b;padding:15px 18px;border-radius:10px;margin:16px 0}
        .jg-info-box h3{margin:0 0 8px}

        /* ===== JG Admin — action buttons ===== */
        .jg-action-btns{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
        .jg-action-btns form{margin:0}

        /* ===== JG Admin — stat cards (shared with dashboard & places) ===== */
        .jg-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px}
        .jg-stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;flex-direction:column;gap:6px;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .15s}
        .jg-stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-2px);color:inherit;text-decoration:none}
        .jg-stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af}
        .jg-stat-value{font-size:32px;font-weight:800;line-height:1;color:#111827}
        .jg-stat-sub{font-size:12px;color:#6b7280;margin-top:2px}
        .jg-stat-card.has-action .jg-stat-sub{color:#2563eb;font-weight:600}

        /* ===== JG Admin — gallery ===== */
        .jg-gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;padding:18px 20px}
        .jg-gallery-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
        .jg-gallery-card-body{padding:12px}
        .jg-gallery-card-body h3{margin:0 0 8px;font-size:15px}
        .jg-gallery-card-body p{margin:0 0 8px;font-size:12px;color:#6b7280}

        /* ===== JG Admin — nav sections (shared with main dashboard) ===== */
        .jg-nav-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
        .jg-nav-section-title{padding:12px 18px;background:#f8fafc;border-bottom:1px solid #e5e7eb;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin:0}
        .jg-nav-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0}
        .jg-nav-item{display:flex;align-items:center;gap:10px;padding:13px 18px;border-right:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;text-decoration:none;color:#374151;font-size:13px;font-weight:500;transition:background .12s}
        .jg-nav-item:hover{background:#f0f7ff;color:#1d4ed8;text-decoration:none}
        .jg-nav-item .jg-nav-icon{font-size:16px;flex-shrink:0;width:20px;text-align:center}

        /* ===== JG Admin — rarity badges (achievements) ===== */
        .jg-rarity-badges{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
        .jg-rarity-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:13px}
        .jg-rarity-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}

        @media(max-width:782px){
            .jg-stat-grid{grid-template-columns:repeat(2,1fr)}
            .jg-stat-value{font-size:26px}
            .jg-gallery-grid{grid-template-columns:repeat(2,1fr)}
            .jg-nav-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:480px){
            .jg-stat-grid{grid-template-columns:1fr}
            .jg-gallery-grid{grid-template-columns:1fr}
            .jg-nav-grid{grid-template-columns:1fr}
        }
        ';
        wp_add_inline_style('wp-admin', $css);
    }

    /**
     * Heartbeat real-time notifications for admin bar
     */
    public function enqueue_admin_bar_script() {
        // Only for admins and moderators
        if (!current_user_can('manage_options') && !current_user_can('jg_map_moderate')) {
            return;
        }

        // Enqueue WordPress Heartbeat API
        wp_enqueue_script('heartbeat');

        // Localize script with URLs (avoids PHP quote escaping issues)
        wp_localize_script('heartbeat', 'JG_MAP_ADMIN_BAR_URLS', array(
            'pointsUrl' => admin_url('admin.php?page=jg-map-places#section-new_pending'),
            'editsUrl' => admin_url('admin.php?page=jg-map-places#section-edit_pending'),
            'reportsUrl' => admin_url('admin.php?page=jg-map-places#section-reported'),
            'deletionsUrl' => admin_url('admin.php?page=jg-map-places#section-deletion_pending')
        ));

        // Add inline script for real-time notifications
        $script = <<<'JAVASCRIPT'
        (function($) {
            var lastTotal = 0;
            var urls = window.JG_MAP_ADMIN_BAR_URLS || {};

            // Set Heartbeat interval to 15 seconds (faster updates)
            if (typeof wp !== 'undefined' && wp.heartbeat) {
                wp.heartbeat.interval(15);
            }

            // Send data with each heartbeat
            $(document).on('heartbeat-send', function(e, data) {
                data.jg_map_check_notifications = true;
            });

            // Process heartbeat response
            $(document).on('heartbeat-tick', function(e, data) {
                if (!data.jg_map_notifications) return;

                var counts = data.jg_map_notifications;
                var total = counts.total;

                // Update admin bar
                var adminBarItem = $('#wp-admin-bar-jg-map-notifications');

                if (total === 0) {
                    // Remove notification if no pending items
                    if (adminBarItem.length) {
                        adminBarItem.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                } else {
                    if (adminBarItem.length) {
                        // Update existing notification
                        var badge = adminBarItem.find('> a > span').first();
                        if (badge.length) {
                            badge.text(total);
                        }

                        // Update child items - preserve hrefs
                        $('#wp-admin-bar-jg-map-pending-points').toggle(counts.points > 0);
                        $('#wp-admin-bar-jg-map-pending-points a').html('📍 ' + counts.points + ' nowych miejsc')
                            .attr('href', urls.pointsUrl);

                        $('#wp-admin-bar-jg-map-pending-edits').toggle(counts.edits > 0);
                        $('#wp-admin-bar-jg-map-pending-edits a').html('✏️ ' + counts.edits + ' edycji do zatwierdzenia')
                            .attr('href', urls.editsUrl);

                        $('#wp-admin-bar-jg-map-pending-reports').toggle(counts.reports > 0);
                        $('#wp-admin-bar-jg-map-pending-reports a').html('🚨 ' + counts.reports + ' zgłoszeń')
                            .attr('href', urls.reportsUrl);

                        $('#wp-admin-bar-jg-map-pending-deletions').toggle(counts.deletions > 0);
                        $('#wp-admin-bar-jg-map-pending-deletions a').html('🗑️ ' + counts.deletions + ' żądań usunięcia')
                            .attr('href', urls.deletionsUrl);

                    } else {
                        // Reload page to show notification
                        if (lastTotal === 0) {
                            location.reload();
                        }
                    }
                }

                // Show toast notification if count increased
                if (total > lastTotal && lastTotal > 0) {
                    if (typeof adminNotice !== 'undefined') {
                        var increase = total - lastTotal;
                        adminNotice('info', 'Nowe zadania do moderacji: +' + increase);
                    }
                }

                lastTotal = total;
            });
        })(jQuery);
JAVASCRIPT;

        wp_add_inline_script('heartbeat', $script);
    }

    /**
     * Render maintenance page
     */
    public function render_maintenance_page() {
        // Check if manual run was successful
        if (isset($_GET['maintenance_done'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Konserwacja bazy danych została uruchomiona pomyślnie!</p></div>';
        }

        // Get last maintenance info
        $last_maintenance = get_option('jg_map_last_maintenance', null);
        $next_scheduled = wp_next_scheduled(JG_Map_Maintenance::CRON_HOOK);

        ?>
        <div class="wrap">
            <?php $this->render_page_header('Konserwacja bazy danych'); ?>

            <div class="jg-card jg-card-body">
                <h2>Status automatycznej konserwacji</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">Status crona:</th>
                        <td>
                            <?php if ($next_scheduled): ?>
                                <span style="color:#15803d;font-weight:700;">✓ Aktywny</span>
                            <?php else: ?>
                                <span style="color:#dc2626;font-weight:700;">✗ Nieaktywny</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Następne uruchomienie:</th>
                        <td>
                            <?php if ($next_scheduled): ?>
                                <?php echo date('Y-m-d H:i:s', $next_scheduled); ?> (za <?php echo human_time_diff($next_scheduled); ?>)
                            <?php else: ?>
                                Brak zaplanowanego uruchomienia
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Częstotliwość:</th>
                        <td>Raz dziennie (codziennie o tej samej porze)</td>
                    </tr>
                </table>

                <h3>Ostatnie uruchomienie</h3>
                <?php if ($last_maintenance): ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Data:</th>
                            <td><?php echo $last_maintenance['time']; ?> (<?php echo human_time_diff(strtotime($last_maintenance['time']), time()); ?> temu)</td>
                        </tr>
                        <tr>
                            <th scope="row">Czas wykonania:</th>
                            <td><?php echo $last_maintenance['execution_time']; ?> sekund</td>
                        </tr>
                        <tr>
                            <th scope="row">Wyniki:</th>
                            <td>
                                <ul style="margin:0;padding-left:20px;">
                                    <li>Usunięto <strong><?php echo $last_maintenance['results']['orphaned_votes']; ?></strong> osieroconych głosów</li>
                                    <li>Usunięto <strong><?php echo $last_maintenance['results']['orphaned_reports']; ?></strong> osieroconych raportów</li>
                                    <li>Usunięto <strong><?php echo $last_maintenance['results']['orphaned_history']; ?></strong> osieroconych wpisów historii</li>
                                    <li>Znaleziono <strong><?php echo $last_maintenance['results']['invalid_coords']; ?></strong> miejsc z nieprawidłowymi współrzędnymi</li>
                                    <li>Znaleziono <strong><?php echo $last_maintenance['results']['empty_content']; ?></strong> miejsc bez treści</li>
                                    <li>Wyłączono <strong><?php echo $last_maintenance['results']['expired_sponsors']; ?></strong> wygasłych sponsorowanych miejsc</li>
                                    <li>Usunięto <strong><?php echo $last_maintenance['results']['old_pending']; ?></strong> starych miejsc oczekujących (>30 dni)</li>
                                    <li>Zoptymalizowano <strong><?php echo $last_maintenance['results']['tables_optimized']; ?></strong> tabel bazy danych</li>
                                </ul>
                            </td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p style="color:#666;">Konserwacja nie była jeszcze uruchamiana.</p>
                <?php endif; ?>

                <h3>Zadania konserwacyjne</h3>
                <p>Automatyczna konserwacja wykonuje następujące zadania:</p>
                <ul style="padding-left:20px;">
                    <li><strong>Czyszczenie osieroconych danych:</strong> Usuwanie głosów, raportów i historii dla usuniętych miejsc</li>
                    <li><strong>Walidacja współrzędnych:</strong> Sprawdzanie miejsc z nieprawidłowymi współrzędnymi (poza Polską: lat 49-55, lng 14-24)</li>
                    <li><strong>Walidacja treści:</strong> Oznaczanie miejsc bez tytułu lub opisu</li>
                    <li><strong>Wyłączanie wygasłych sponsorowań:</strong> Automatyczne wyłączanie miejsc sponsorowanych po terminie</li>
                    <li><strong>Czyszczenie starych pending:</strong> Usuwanie miejsc oczekujących dłużej niż 30 dni (z powiadomieniem autora)</li>
                    <li><strong>Optymalizacja bazy:</strong> Czyszczenie cache i optymalizacja tabel MySQL</li>
                </ul>

                <h3>Ręczne uruchomienie</h3>
                <p>Możesz ręcznie uruchomić konserwację w dowolnym momencie:</p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=jg-map-maintenance&jg_run_maintenance=1'), 'jg_maintenance'); ?>"
                   class="button button-primary"
                   onclick="return confirm('Czy na pewno chcesz uruchomić konserwację? Operacja może potrwać kilka sekund.');">
                    🔧 Uruchom konserwację teraz
                </a>

                <p style="margin-top:20px;padding:15px;background:#fef3c7;border-left:4px solid #f59e0b;color:#92400e;">
                    <strong>Uwaga:</strong> Ręczne uruchomienie konserwacji może chwilę potrwać. Strona zostanie automatycznie przeładowana po zakończeniu.
                </p>
            </div>

            <?php
            // XP sync success notice
            if (isset($_GET['xp_sync_done'])) {
                echo '<div class="notice notice-success is-dismissible" style="margin-top:20px"><p>Synchronizacja doświadczenia i osiągnięć zakończona pomyślnie!</p></div>';
            }
            $last_sync = get_option('jg_map_last_xp_sync', null);
            ?>

            <div class="jg-card jg-card-body">
                <h2>Synchronizacja doświadczenia i osiągnięć</h2>
                <p>Przelicz XP i odblokuj osiągnięcia na podstawie rzeczywistych akcji użytkowników w bazie danych.
                Używaj tej opcji gdy:</p>
                <ul style="padding-left:20px;">
                    <li>System poziomów został dodany do istniejącej instalacji (użytkownicy mieli konta przed wprowadzeniem poziomów)</li>
                    <li>Zmieniono ilość XP przyznawanych za poszczególne akcje i chcesz przeliczyć</li>
                    <li>Dodano nowe osiągnięcia i chcesz sprawdzić, kto już je spełnia</li>
                    <li>Dane XP wyglądają na niespójne z rzeczywistą aktywnością użytkowników</li>
                </ul>

                <?php if ($last_sync): ?>
                <h3>Ostatnia synchronizacja</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Data:</th>
                        <td><?php echo $last_sync['time']; ?> (<?php echo human_time_diff(strtotime($last_sync['time']), time()); ?> temu)</td>
                    </tr>
                    <tr>
                        <th scope="row">Przeliczenie XP:</th>
                        <td>
                            Przetworzono <strong><?php echo $last_sync['xp']['users_processed']; ?></strong> użytkowników,
                            zaktualizowano <strong><?php echo $last_sync['xp']['users_updated']; ?></strong>,
                            przyznano łącznie <strong><?php echo number_format($last_sync['xp']['total_xp_awarded']); ?></strong> XP
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Osiągnięcia:</th>
                        <td>
                            Sprawdzono <strong><?php echo $last_sync['achievements']['users_checked']; ?></strong> użytkowników,
                            odblokowano <strong><?php echo $last_sync['achievements']['new_achievements_awarded']; ?></strong> nowych osiągnięć
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

                <h3>Uruchom synchronizację</h3>
                <p>Przelicza XP od nowa na podstawie rzeczywistych danych (punkty, głosy, zdjęcia, raporty, edycje), a następnie odblokuje wszystkie osiągnięcia, których warunki użytkownicy już spełniają.</p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=jg-map-maintenance&jg_sync_xp=1'), 'jg_sync_xp'); ?>"
                   class="button button-primary"
                   onclick="return confirm('Czy na pewno chcesz przeliczyć XP i osiągnięcia dla wszystkich użytkowników? Istniejące dane XP zostaną nadpisane obliczeniami na podstawie rzeczywistych akcji.');">
                    Przelicz XP i osiągnięcia
                </a>

                <p style="margin-top:20px;padding:15px;background:#eff6ff;border-left:4px solid #3b82f6;color:#1e40af;">
                    <strong>Info:</strong> Przeliczenie nadpisze obecne XP użytkowników wartościami obliczonymi z ich rzeczywistych akcji.
                    Osiągnięcia odblokowane retroaktywnie nie wyświetlą powiadomień (aby nie spamować użytkowników).
                    XP za „codzienny login" nie jest możliwe do odtworzenia retroaktywnie.
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render place categories editor page
     */
    public function render_place_categories_page() {
        // Get current data
        $categories = JG_Map_Ajax_Handlers::get_place_categories();

        // Extended emoji list for picker - organized by category
        $common_emojis = array(
            // Food & Dining
            '🍽️', '🍴', '🥄', '🍕', '🍔', '🌭', '🥪', '🌮', '🌯', '🥙', '🧆', '🥚', '🍳', '🥘', '🍲', '🥣', '🥗', '🍿', '🧈', '🧂', '🥫',
            '🍱', '🍘', '🍙', '🍚', '🍛', '🍜', '🍝', '🍠', '🍢', '🍣', '🍤', '🍥', '🥮', '🍡', '🥟', '🥠', '🥡',
            '🍦', '🍧', '🍨', '🍩', '🍪', '🎂', '🍰', '🧁', '🥧', '🍫', '🍬', '🍭', '🍮', '🍯',
            '🍼', '🥛', '☕', '🍵', '🧃', '🥤', '🧋', '🍶', '🍺', '🍻', '🥂', '🍷', '🥃', '🍸', '🍹', '🍾',
            // Buildings & Places
            '🏛️', '🏢', '🏠', '🏡', '🏘️', '🏚️', '🏭', '🏬', '🏣', '🏤', '🏥', '🏦', '🏨', '🏩', '🏪', '🏫', '⛪', '🕌', '🕍', '🛕', '⛩️', '🏰', '🏯', '🗼', '🗽', '⛲', '🎡', '🎢', '🎠', '🎪',
            // Nature & Parks
            '🌲', '🌳', '🌴', '🌿', '🍀', '🍃', '🍂', '🍁', '🌾', '🌻', '🌺', '🌸', '🌷', '🌹', '💐', '🏞️', '🌱', '🪴', '🪻', '🪷', '🏕️', '⛺', '🏖️', '🏜️', '🏔️', '⛰️', '🌄', '🌅',
            // Sports & Recreation
            '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🥅', '⛳', '🏒', '🥊', '🎣', '🤿', '🎿', '⛷️', '🏂', '🛷', '⛸️', '🏋️', '🤸', '🧘', '🏃', '🚴', '🏊', '🎮', '🎳', '🧗',
            // Culture & Entertainment
            '🎭', '🎨', '🖼️', '🎬', '📽️', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🪕', '🎻', '🎪', '🎟️', '🏟️', '📚', '📖', '📕', '📗', '📘', '📙',
            // History & Heritage
            '🏰', '🏯', '⛪', '🕌', '🏛️', '🗿', '🏺', '⚱️', '🗽', '🗼', '⚔️', '🛡️', '👑', '📜', '🗺️',
            // Services & Commerce
            '🏢', '🏪', '🏬', '🏦', '🏨', '🏥', '💈', '🛒', '🛍️', '💇', '💆', '🧖', '🛁', '🚿', '✂️', '💊', '💉', '🏧',
            // Transport
            '🚗', '🚌', '🚎', '🚐', '🚕', '🚖', '🛻', '🚚', '🚛', '🚜', '🏎️', '🏍️', '🛵', '🚲', '🛴', '🚋', '🚃', '🚈', '🚇', '🚊', '🚝', '🚆', '🚂', '✈️', '🛫', '🛬', '🛩️', '🚁', '🚀', '🛶', '⛵', '🚤', '🛥️', '⛴️', '🚢', '🅿️',
            // Other useful
            '✨', '⭐', '🌟', '💫', '🔥', '💎', '🔑', '🗝️', '📍', '🎯', '❤️', '💙', '💚', '💛', '🧡', '💜', '🖤', '🤍', '🤎', 'ℹ️', '🆕', '🆓', '🆙', '🆗', '🆒'
        );
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie kategoriami miejsc'); ?>

            <style>
                .jg-category-editor { max-width: 800px; margin-top: 20px; }
                .jg-category-editor .card { background: #fff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 20px; }
                .jg-category-editor h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; font-size: 15px; font-weight: 700; color: #111827; }
                .jg-category-list { list-style: none; padding: 0; margin: 0; }
                .jg-category-item {
                    display: flex; align-items: center; gap: 10px; padding: 12px;
                    border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px;
                    background: #f8fafc; transition: all 0.15s;
                }
                .jg-category-item:hover { background: #f0f7ff; border-color: #93c5fd; }
                .jg-category-item .cat-icon { font-size: calc(20 * var(--jg)); width: 30px; text-align: center; }
                .jg-category-item .cat-name { flex: 1; font-weight: 500; }
                .jg-action-btn { background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; transition: background 0.2s; }
                .jg-action-btn:hover { background: #e0e0e0; }
                .jg-action-btn.delete:hover { background: #ffebee; color: #c62828; }
                .jg-add-form { display: none; padding: 15px; background: #f5f5f5; border-radius: 6px; margin-top: 15px; }
                .jg-add-form.visible { display: block; }
                .jg-add-form label { display: block; margin-bottom: 5px; font-weight: 500; }
                .jg-add-form input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
                .jg-emoji-picker { display: flex; flex-wrap: wrap; gap: 4px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; max-height: 200px; overflow-y: auto; }
                .jg-emoji-btn { padding: 4px 6px; border: 1px solid transparent; border-radius: 4px; cursor: pointer; font-size: calc(16 * var(--jg)); background: none; transition: all 0.2s; line-height: 1; }
                .jg-emoji-btn:hover { background: #e3f2fd; }
                .jg-emoji-btn.selected { background: #2196f3; border-color: #1976d2; }
                .jg-icon-preview { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
                .jg-icon-preview .preview { font-size: calc(32 * var(--jg)); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #fff; border: 2px solid #ddd; border-radius: 8px; }
                .jg-manual-emoji { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
                .jg-manual-emoji input { width: 60px; font-size: calc(24 * var(--jg)); text-align: center; padding: 4px; border: 1px solid #ddd; border-radius: 4px; }
                .jg-manual-emoji .hint { font-size: calc(11 * var(--jg)); color: #666; }
                .jg-manual-emoji input.invalid { border-color: #c62828; }
                .jg-btn-row { display: flex; gap: 10px; margin-top: 15px; }
                .jg-edit-inline { display: none; padding: 15px; background: #fff3e0; border-radius: 6px; margin-top: 15px; }
                .jg-edit-inline.visible { display: block; }
                .jg-edit-inline input { padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
            </style>

            <div class="jg-category-editor">
                <div class="card">
                    <h2>Kategorie miejsc</h2>
                    <p class="description">Kategorie pomagają użytkownikom filtrować i organizować miejsca na mapie.</p>

                    <ul class="jg-category-list" id="jg-place-category-list">
                        <?php foreach ($categories as $key => $category): ?>
                        <li class="jg-category-item" data-key="<?php echo esc_attr($key); ?>">
                            <span class="cat-icon"><?php echo esc_html($category['icon'] ?? '📍'); ?></span>
                            <span class="cat-name"><?php echo esc_html($category['label']); ?></span>
                            <code class="cat-slug"><?php echo esc_html($key); ?></code>
                            <?php if (!empty($category['has_menu'])): ?>
                            <span title="Posiada menu" style="font-size:14px;opacity:0.7">🍽️</span>
                            <?php endif; ?>
                            <?php if (!empty($category['serves_cuisine'])): ?>
                            <span title="Miejsce serwujące jedzenie" style="font-size:14px;opacity:0.7">🥗</span>
                            <?php endif; ?>
                            <?php if (!empty($category['has_price_range'])): ?>
                            <span title="Zakres cenowy" style="font-size:14px;opacity:0.7">💰</span>
                            <?php endif; ?>
                            <?php if (!empty($category['show_promo'])): ?>
                            <span title="Ramka promocyjna" style="font-size:14px;opacity:0.7">💼</span>
                            <?php endif; ?>
                            <?php if (!empty($category['offerings_label'])): ?>
                            <span title="Lista ofert: <?php echo esc_attr($category['offerings_label']); ?>" style="font-size:14px;opacity:0.7">📋</span>
                            <?php endif; ?>
                            <button class="jg-action-btn" onclick="jgEditPlaceCategory('<?php echo esc_js($key); ?>', '<?php echo esc_js($category['label']); ?>', '<?php echo esc_js($category['icon'] ?? '📍'); ?>', <?php echo !empty($category['has_menu']) ? 'true' : 'false'; ?>, <?php echo !empty($category['serves_cuisine']) ? 'true' : 'false'; ?>, <?php echo !empty($category['has_price_range']) ? 'true' : 'false'; ?>, <?php echo !empty($category['show_promo']) ? 'true' : 'false'; ?>, '<?php echo esc_js($category['offerings_label'] ?? ''); ?>')" title="Edytuj">✏️</button>
                            <button class="jg-action-btn delete" onclick="jgDeletePlaceCategory('<?php echo esc_js($key); ?>')" title="Usuń">🗑️</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <button class="button" onclick="jgToggleAddPlaceCategory()">+ Dodaj kategorię</button>

                    <div class="jg-add-form" id="jg-add-place-category-form">
                        <label for="new-place-cat-key">Klucz kategorii (bez spacji, małe litery)</label>
                        <input type="text" id="new-place-cat-key" placeholder="np. gastronomia">

                        <label for="new-place-cat-label">Nazwa wyświetlana</label>
                        <input type="text" id="new-place-cat-label" placeholder="np. Gastronomia">

                        <label>Ikona</label>
                        <div class="jg-icon-preview">
                            <div class="preview" id="place-icon-preview">📍</div>
                            <span>Wybierz ikonę z listy lub wklej własne emoji</span>
                        </div>

                        <div class="jg-manual-emoji">
                            <input type="text" id="new-place-cat-icon-manual" maxlength="4" placeholder="📍" oninput="jgManualPlaceEmojiInput(this)">
                            <span class="hint">Wklej własne emoji</span>
                        </div>

                        <div class="jg-emoji-picker" id="place-emoji-picker">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectPlaceEmoji('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="new-place-cat-icon" value="📍">

                        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer">
                            <input type="checkbox" id="new-place-cat-has-menu" value="1">
                            🍽️ Kategoria posiada menu (włącz opcję dodawania menu dla miejsc)
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="new-place-cat-serves-cuisine" value="1">
                            🥗 Miejsce serwujące jedzenie (dodaje pole rodzaju kuchni i servesCuisine do schematu)
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="new-place-cat-has-price-range" value="1">
                            💰 Zakres cenowy (dodaje pole zakresu cenowego i priceRange do schematu)
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="new-place-cat-show-promo" value="1">
                            💼 Wyświetlaj ramkę promocyjną „Jesteś właścicielem?" (mapa i strona pineski)
                        </label>
                        <label style="display:block;margin-top:10px;font-weight:500">📋 Etykieta listy ofert (zostaw puste, aby wyłączyć)</label>
                        <input type="text" id="new-place-cat-offerings-label" placeholder='np. "Usługi" lub "Produkty"' maxlength="50" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;margin-top:4px">
                        <p class="description" style="margin:4px 0 0">Pojawi się jako przycisk obok Menu w oknie pineski. Właściciel może dodać listę pozycji z cenami.</p>

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgSavePlaceCategory()">Zapisz</button>
                            <button class="button" onclick="jgToggleAddPlaceCategory()">Anuluj</button>
                        </div>
                    </div>

                    <!-- Edit category inline -->
                    <div class="jg-edit-inline" id="jg-edit-place-category-form">
                        <label>Edytuj kategorię</label>
                        <input type="hidden" id="edit-place-cat-key">

                        <label for="edit-place-cat-label" style="margin-top:10px">Nazwa</label>
                        <input type="text" id="edit-place-cat-label" style="width: 100%; margin-bottom: 10px;">

                        <label>Ikona</label>
                        <div class="jg-icon-preview">
                            <div class="preview" id="edit-place-icon-preview">📍</div>
                            <span>Wybierz ikonę z listy lub wklej własne emoji</span>
                        </div>

                        <div class="jg-manual-emoji">
                            <input type="text" id="edit-place-cat-icon-manual" maxlength="4" placeholder="📍" oninput="jgManualPlaceEmojiInputEdit(this)">
                            <span class="hint">Wklej własne emoji</span>
                        </div>

                        <div class="jg-emoji-picker" id="edit-place-emoji-picker">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectPlaceEmojiEdit('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="edit-place-cat-icon" value="">

                        <label style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer">
                            <input type="checkbox" id="edit-place-cat-has-menu" value="1">
                            🍽️ Kategoria posiada menu
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="edit-place-cat-serves-cuisine" value="1">
                            🥗 Miejsce serwujące jedzenie
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="edit-place-cat-has-price-range" value="1">
                            💰 Zakres cenowy
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer">
                            <input type="checkbox" id="edit-place-cat-show-promo" value="1">
                            💼 Wyświetlaj ramkę promocyjną „Jesteś właścicielem?"
                        </label>
                        <label style="display:block;margin-top:10px;font-weight:500">📋 Etykieta listy ofert (zostaw puste, aby wyłączyć)</label>
                        <input type="text" id="edit-place-cat-offerings-label" placeholder='np. "Usługi" lub "Produkty"' maxlength="50" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;margin-top:4px">
                        <p class="description" style="margin:4px 0 0">Pojawi się jako przycisk obok Menu w oknie pineski.</p>

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgUpdatePlaceCategory()">Zapisz zmiany</button>
                            <button class="button" onclick="jgCancelEditPlaceCategory()">Anuluj</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const nonce = '<?php echo wp_create_nonce('jg_map_place_categories_nonce'); ?>';

                // Store current data
                let categories = <?php echo json_encode($categories); ?>;

                // Emoji validation function
                function jgIsValidEmoji(str) {
                    str = str.trim();
                    if (!str) return false;
                    if (/[a-zA-Z0-9ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/.test(str)) return false;
                    return /\p{Extended_Pictographic}/u.test(str);
                }

                function jgGetFirstPlaceEmoji() {
                    const btn = document.querySelector('#place-emoji-picker .jg-emoji-btn');
                    return btn ? btn.textContent.trim() : '📍';
                }

                // Toggle add form
                window.jgToggleAddPlaceCategory = function() {
                    const form = document.getElementById('jg-add-place-category-form');
                    form.classList.toggle('visible');
                    document.getElementById('jg-edit-place-category-form').classList.remove('visible');
                };

                // Manual emoji input for new category
                window.jgManualPlaceEmojiInput = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('place-icon-preview').textContent = emoji;
                            document.getElementById('new-place-cat-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#place-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                // Select emoji for new category
                window.jgSelectPlaceEmoji = function(emoji) {
                    document.getElementById('place-icon-preview').textContent = emoji;
                    document.getElementById('new-place-cat-icon').value = emoji;
                    document.getElementById('new-place-cat-icon-manual').value = emoji;
                    document.getElementById('new-place-cat-icon-manual').classList.remove('invalid');
                    document.querySelectorAll('#place-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                // Save new category
                window.jgSavePlaceCategory = function() {
                    const key = document.getElementById('new-place-cat-key').value.trim().toLowerCase().replace(/\s+/g, '_');
                    const label = document.getElementById('new-place-cat-label').value.trim();
                    let icon = document.getElementById('new-place-cat-icon').value || '📍';
                    const hasMenu = document.getElementById('new-place-cat-has-menu').checked ? '1' : '0';
                    const servesCuisine = document.getElementById('new-place-cat-serves-cuisine').checked ? '1' : '0';
                    const hasPriceRange = document.getElementById('new-place-cat-has-price-range').checked ? '1' : '0';
                    const showPromo = document.getElementById('new-place-cat-show-promo').checked ? '1' : '0';
                    const offeringsLabel = document.getElementById('new-place-cat-offerings-label').value.trim();
                    if (!jgIsValidEmoji(icon)) {
                        icon = jgGetFirstPlaceEmoji();
                    }

                    if (!key || !label) {
                        alert('Wypełnij wszystkie pola');
                        return;
                    }

                    if (categories[key]) {
                        alert('Kategoria o tym kluczu już istnieje');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_save_place_category',
                            nonce: nonce,
                            key: key,
                            label: label,
                            icon: icon,
                            has_menu: hasMenu,
                            serves_cuisine: servesCuisine,
                            has_price_range: hasPriceRange,
                            show_promo: showPromo,
                            offerings_label: offeringsLabel
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd zapisu');
                        }
                    });
                };

                // Edit category
                window.jgEditPlaceCategory = function(key, label, icon, hasMenu, servesCuisine, hasPriceRange, showPromo, offeringsLabel) {
                    document.getElementById('jg-add-place-category-form').classList.remove('visible');
                    const form = document.getElementById('jg-edit-place-category-form');
                    form.classList.add('visible');
                    document.getElementById('edit-place-cat-key').value = key;
                    document.getElementById('edit-place-cat-label').value = label;
                    document.getElementById('edit-place-cat-icon').value = icon;
                    document.getElementById('edit-place-icon-preview').textContent = icon;
                    document.getElementById('edit-place-cat-icon-manual').value = icon;
                    document.getElementById('edit-place-cat-icon-manual').classList.remove('invalid');
                    document.getElementById('edit-place-cat-has-menu').checked = !!hasMenu;
                    document.getElementById('edit-place-cat-serves-cuisine').checked = !!servesCuisine;
                    document.getElementById('edit-place-cat-has-price-range').checked = !!hasPriceRange;
                    document.getElementById('edit-place-cat-show-promo').checked = !!showPromo;
                    document.getElementById('edit-place-cat-offerings-label').value = offeringsLabel || '';

                    // Highlight current emoji
                    document.querySelectorAll('#edit-place-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === icon);
                    });

                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };

                // Cancel edit
                window.jgCancelEditPlaceCategory = function() {
                    document.getElementById('jg-edit-place-category-form').classList.remove('visible');
                };

                // Manual emoji input for edit
                window.jgManualPlaceEmojiInputEdit = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('edit-place-icon-preview').textContent = emoji;
                            document.getElementById('edit-place-cat-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#edit-place-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                // Select emoji for edit
                window.jgSelectPlaceEmojiEdit = function(emoji) {
                    document.getElementById('edit-place-icon-preview').textContent = emoji;
                    document.getElementById('edit-place-cat-icon').value = emoji;
                    document.getElementById('edit-place-cat-icon-manual').value = emoji;
                    document.getElementById('edit-place-cat-icon-manual').classList.remove('invalid');
                    document.querySelectorAll('#edit-place-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                // Update category
                window.jgUpdatePlaceCategory = function() {
                    const key = document.getElementById('edit-place-cat-key').value;
                    const label = document.getElementById('edit-place-cat-label').value.trim();
                    let icon = document.getElementById('edit-place-cat-icon').value || '📍';
                    const hasMenu = document.getElementById('edit-place-cat-has-menu').checked ? '1' : '0';
                    const servesCuisine = document.getElementById('edit-place-cat-serves-cuisine').checked ? '1' : '0';
                    const hasPriceRange = document.getElementById('edit-place-cat-has-price-range').checked ? '1' : '0';
                    const showPromo = document.getElementById('edit-place-cat-show-promo').checked ? '1' : '0';
                    const offeringsLabel = document.getElementById('edit-place-cat-offerings-label').value.trim();
                    if (!jgIsValidEmoji(icon)) {
                        icon = jgGetFirstPlaceEmoji();
                    }

                    if (!label) {
                        alert('Nazwa nie może być pusta');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_update_place_category',
                            nonce: nonce,
                            key: key,
                            label: label,
                            icon: icon,
                            has_menu: hasMenu,
                            serves_cuisine: servesCuisine,
                            has_price_range: hasPriceRange,
                            show_promo: showPromo,
                            offerings_label: offeringsLabel
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd zapisu');
                        }
                    });
                };

                // Delete category
                window.jgDeletePlaceCategory = function(key) {
                    if (!confirm('Czy na pewno chcesz usunąć tę kategorię?')) {
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_delete_place_category',
                            nonce: nonce,
                            key: key
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd usuwania');
                        }
                    });
                };
            })();
            </script>
        </div>
        <?php $this->render_filter_reset_card(); ?>
        <?php
    }

    /**
     * Render curiosity categories editor page
     */
    public function render_curiosity_categories_page() {
        // Get current data
        $categories = JG_Map_Ajax_Handlers::get_curiosity_categories();

        // Extended emoji list for picker - organized by category
        $common_emojis = array(
            // History & Heritage
            '📜', '📖', '📚', '🏰', '🏯', '⛪', '🕌', '🏛️', '🗿', '🏺', '⚱️', '🗽', '🗼', '⚔️', '🛡️', '👑', '🗺️', '🧭', '📯', '🎺',
            // Nature & Wildlife
            '🦋', '🐦', '🦅', '🦉', '🐝', '🐛', '🐜', '🐞', '🦗', '🕷️', '🐀', '🐁', '🐿️', '🦔', '🦇', '🐺', '🦊', '🦝', '🐻', '🐨', '🐼', '🦁', '🐯', '🐸', '🦎', '🐍', '🐢', '🦕', '🦖',
            '🌲', '🌳', '🌴', '🌿', '🍀', '🍃', '🍂', '🍁', '🌾', '🌻', '🌺', '🌸', '🌷', '🌹', '💐', '🏞️', '🌱', '🪴', '🪻', '🪷', '🍄', '🪨', '💎', '🌋', '⛰️', '🏔️',
            // Architecture
            '🏰', '🏯', '🗼', '🏛️', '⛪', '🕌', '🕍', '🛕', '⛩️', '🏚️', '🏗️', '🧱', '🪵', '🪟', '🚪', '🏠', '🏡', '🏢', '🏬', '🏭', '🌉', '🗿',
            // Stories & Legends
            '📖', '📕', '📗', '📘', '📙', '📓', '📔', '📒', '📃', '📜', '📰', '🗞️', '✒️', '🖋️', '🖊️', '📝', '💭', '💬', '🗯️', '👻', '🧙', '🧚', '🧛', '🧜', '🧝', '🧞', '🧟', '🐉', '🐲', '🦄', '🔮', '🪄', '✨',
            // Mystery & Discovery
            '🔍', '🔎', '🧩', '🗝️', '🔑', '🗃️', '🗄️', '📦', '🎁', '💡', '🔦', '🕯️', '🪔', '⚗️', '🔬', '🔭', '📡', '🧲', '⚙️', '🛠️',
            // Culture & Art
            '🎭', '🎨', '🖼️', '🎬', '📽️', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🪕', '🎻', '🎪', '🎟️',
            // Water & Geography
            '💧', '💦', '🌊', '🏝️', '🏖️', '⛵', '🚣', '🌅', '🌄', '🏕️', '⛺', '🌈', '☀️', '🌙', '⭐', '🌟', '💫',
            // Other useful
            '❤️', '💙', '💚', '💛', '🧡', '💜', '🖤', '🤍', '🤎', '❓', '❗', '💯', '🎯', '📍', 'ℹ️', '🆕', '🏆', '🥇', '🎖️', '🏅'
        );
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie kategoriami ciekawostek'); ?>

            <style>
                .jg-category-editor { max-width: 800px; margin-top: 20px; }
                .jg-category-editor .card { background: #fff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 20px; }
                .jg-category-editor h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; font-size: 15px; font-weight: 700; color: #111827; }
                .jg-category-list { list-style: none; padding: 0; margin: 0; }
                .jg-category-item {
                    display: flex; align-items: center; gap: 10px; padding: 12px;
                    border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px;
                    background: #f8fafc; transition: all 0.15s;
                }
                .jg-category-item:hover { background: #f0f7ff; border-color: #93c5fd; }
                .jg-category-item .cat-icon { font-size: calc(20 * var(--jg)); width: 30px; text-align: center; }
                .jg-category-item .cat-name { flex: 1; font-weight: 500; }
                .jg-action-btn { background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; transition: background 0.2s; }
                .jg-action-btn:hover { background: #e0e0e0; }
                .jg-action-btn.delete:hover { background: #ffebee; color: #c62828; }
                .jg-add-form { display: none; padding: 15px; background: #f5f5f5; border-radius: 6px; margin-top: 15px; }
                .jg-add-form.visible { display: block; }
                .jg-add-form label { display: block; margin-bottom: 5px; font-weight: 500; }
                .jg-add-form input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; }
                .jg-emoji-picker { display: flex; flex-wrap: wrap; gap: 4px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; max-height: 200px; overflow-y: auto; }
                .jg-emoji-btn { padding: 4px 6px; border: 1px solid transparent; border-radius: 4px; cursor: pointer; font-size: calc(16 * var(--jg)); background: none; transition: all 0.2s; line-height: 1; }
                .jg-emoji-btn:hover { background: #e3f2fd; }
                .jg-emoji-btn.selected { background: #2196f3; border-color: #1976d2; }
                .jg-icon-preview { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
                .jg-icon-preview .preview { font-size: calc(32 * var(--jg)); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #fff; border: 2px solid #ddd; border-radius: 8px; }
                .jg-manual-emoji { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
                .jg-manual-emoji input { width: 60px; font-size: calc(24 * var(--jg)); text-align: center; padding: 4px; border: 1px solid #ddd; border-radius: 4px; }
                .jg-manual-emoji .hint { font-size: calc(11 * var(--jg)); color: #666; }
                .jg-manual-emoji input.invalid { border-color: #c62828; }
                .jg-btn-row { display: flex; gap: 10px; margin-top: 15px; }
                .jg-edit-inline { display: none; padding: 15px; background: #fff3e0; border-radius: 6px; margin-top: 15px; }
                .jg-edit-inline.visible { display: block; }
                .jg-edit-inline input { padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
            </style>

            <div class="jg-category-editor">
                <div class="card">
                    <h2>Kategorie ciekawostek</h2>
                    <p class="description">Kategorie pomagają użytkownikom filtrować i organizować ciekawostki na mapie.</p>

                    <ul class="jg-category-list" id="jg-curiosity-category-list">
                        <?php foreach ($categories as $key => $category): ?>
                        <li class="jg-category-item" data-key="<?php echo esc_attr($key); ?>">
                            <span class="cat-icon"><?php echo esc_html($category['icon'] ?? '📖'); ?></span>
                            <span class="cat-name"><?php echo esc_html($category['label']); ?></span>
                            <code class="cat-slug"><?php echo esc_html($key); ?></code>
                            <button class="jg-action-btn" onclick="jgEditCuriosityCategory('<?php echo esc_js($key); ?>', '<?php echo esc_js($category['label']); ?>', '<?php echo esc_js($category['icon'] ?? '📖'); ?>')" title="Edytuj">✏️</button>
                            <button class="jg-action-btn delete" onclick="jgDeleteCuriosityCategory('<?php echo esc_js($key); ?>')" title="Usuń">🗑️</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <button class="button" onclick="jgToggleAddCuriosityCategory()">+ Dodaj kategorię</button>

                    <div class="jg-add-form" id="jg-add-curiosity-category-form">
                        <label for="new-curiosity-cat-key">Klucz kategorii (bez spacji, małe litery)</label>
                        <input type="text" id="new-curiosity-cat-key" placeholder="np. historyczne">

                        <label for="new-curiosity-cat-label">Nazwa wyświetlana</label>
                        <input type="text" id="new-curiosity-cat-label" placeholder="np. Historyczne">

                        <label>Ikona</label>
                        <div class="jg-icon-preview">
                            <div class="preview" id="curiosity-icon-preview">📖</div>
                            <span>Wybierz ikonę z listy lub wklej własne emoji</span>
                        </div>

                        <div class="jg-manual-emoji">
                            <input type="text" id="new-curiosity-cat-icon-manual" maxlength="4" placeholder="📖" oninput="jgManualCuriosityEmojiInput(this)">
                            <span class="hint">Wklej własne emoji</span>
                        </div>

                        <div class="jg-emoji-picker" id="curiosity-emoji-picker">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectCuriosityEmoji('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="new-curiosity-cat-icon" value="📖">

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgSaveCuriosityCategory()">Zapisz</button>
                            <button class="button" onclick="jgToggleAddCuriosityCategory()">Anuluj</button>
                        </div>
                    </div>

                    <!-- Edit category inline -->
                    <div class="jg-edit-inline" id="jg-edit-curiosity-category-form">
                        <label>Edytuj kategorię</label>
                        <input type="hidden" id="edit-curiosity-cat-key">

                        <label for="edit-curiosity-cat-label" style="margin-top:10px">Nazwa</label>
                        <input type="text" id="edit-curiosity-cat-label" style="width: 100%; margin-bottom: 10px;">

                        <label>Ikona</label>
                        <div class="jg-icon-preview">
                            <div class="preview" id="edit-curiosity-icon-preview">📖</div>
                            <span>Wybierz ikonę z listy lub wklej własne emoji</span>
                        </div>

                        <div class="jg-manual-emoji">
                            <input type="text" id="edit-curiosity-cat-icon-manual" maxlength="4" placeholder="📖" oninput="jgManualCuriosityEmojiInputEdit(this)">
                            <span class="hint">Wklej własne emoji</span>
                        </div>

                        <div class="jg-emoji-picker" id="edit-curiosity-emoji-picker">
                            <?php foreach ($common_emojis as $emoji): ?>
                            <button type="button" class="jg-emoji-btn" onclick="jgSelectCuriosityEmojiEdit('<?php echo $emoji; ?>')"><?php echo $emoji; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="edit-curiosity-cat-icon" value="">

                        <div class="jg-btn-row">
                            <button class="button button-primary" onclick="jgUpdateCuriosityCategory()">Zapisz zmiany</button>
                            <button class="button" onclick="jgCancelEditCuriosityCategory()">Anuluj</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const nonce = '<?php echo wp_create_nonce('jg_map_curiosity_categories_nonce'); ?>';

                // Store current data
                let categories = <?php echo json_encode($categories); ?>;

                // Emoji validation function
                function jgIsValidEmoji(str) {
                    str = str.trim();
                    if (!str) return false;
                    if (/[a-zA-Z0-9ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/.test(str)) return false;
                    return /\p{Extended_Pictographic}/u.test(str);
                }

                function jgGetFirstCuriosityEmoji() {
                    const btn = document.querySelector('#curiosity-emoji-picker .jg-emoji-btn');
                    return btn ? btn.textContent.trim() : '📖';
                }

                // Toggle add form
                window.jgToggleAddCuriosityCategory = function() {
                    const form = document.getElementById('jg-add-curiosity-category-form');
                    form.classList.toggle('visible');
                    document.getElementById('jg-edit-curiosity-category-form').classList.remove('visible');
                };

                // Manual emoji input for new category
                window.jgManualCuriosityEmojiInput = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('curiosity-icon-preview').textContent = emoji;
                            document.getElementById('new-curiosity-cat-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                // Select emoji for new category
                window.jgSelectCuriosityEmoji = function(emoji) {
                    document.getElementById('curiosity-icon-preview').textContent = emoji;
                    document.getElementById('new-curiosity-cat-icon').value = emoji;
                    document.getElementById('new-curiosity-cat-icon-manual').value = emoji;
                    document.getElementById('new-curiosity-cat-icon-manual').classList.remove('invalid');
                    document.querySelectorAll('#curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                // Save new category
                window.jgSaveCuriosityCategory = function() {
                    const key = document.getElementById('new-curiosity-cat-key').value.trim().toLowerCase().replace(/\s+/g, '_');
                    const label = document.getElementById('new-curiosity-cat-label').value.trim();
                    let icon = document.getElementById('new-curiosity-cat-icon').value || '📖';
                    if (!jgIsValidEmoji(icon)) {
                        icon = jgGetFirstCuriosityEmoji();
                    }

                    if (!key || !label) {
                        alert('Wypełnij wszystkie pola');
                        return;
                    }

                    if (categories[key]) {
                        alert('Kategoria o tym kluczu już istnieje');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_save_curiosity_category',
                            nonce: nonce,
                            key: key,
                            label: label,
                            icon: icon
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd zapisu');
                        }
                    });
                };

                // Edit category
                window.jgEditCuriosityCategory = function(key, label, icon) {
                    document.getElementById('jg-add-curiosity-category-form').classList.remove('visible');
                    const form = document.getElementById('jg-edit-curiosity-category-form');
                    form.classList.add('visible');
                    document.getElementById('edit-curiosity-cat-key').value = key;
                    document.getElementById('edit-curiosity-cat-label').value = label;
                    document.getElementById('edit-curiosity-cat-icon').value = icon;
                    document.getElementById('edit-curiosity-icon-preview').textContent = icon;
                    document.getElementById('edit-curiosity-cat-icon-manual').value = icon;
                    document.getElementById('edit-curiosity-cat-icon-manual').classList.remove('invalid');

                    // Highlight current emoji
                    document.querySelectorAll('#edit-curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === icon);
                    });

                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };

                // Cancel edit
                window.jgCancelEditCuriosityCategory = function() {
                    document.getElementById('jg-edit-curiosity-category-form').classList.remove('visible');
                };

                // Manual emoji input for edit
                window.jgManualCuriosityEmojiInputEdit = function(input) {
                    const emoji = input.value.trim();
                    if (emoji) {
                        if (jgIsValidEmoji(emoji)) {
                            input.classList.remove('invalid');
                            document.getElementById('edit-curiosity-icon-preview').textContent = emoji;
                            document.getElementById('edit-curiosity-cat-icon').value = emoji;
                        } else {
                            input.classList.add('invalid');
                        }
                        document.querySelectorAll('#edit-curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });
                    } else {
                        input.classList.remove('invalid');
                    }
                };

                // Select emoji for edit
                window.jgSelectCuriosityEmojiEdit = function(emoji) {
                    document.getElementById('edit-curiosity-icon-preview').textContent = emoji;
                    document.getElementById('edit-curiosity-cat-icon').value = emoji;
                    document.getElementById('edit-curiosity-cat-icon-manual').value = emoji;
                    document.getElementById('edit-curiosity-cat-icon-manual').classList.remove('invalid');
                    document.querySelectorAll('#edit-curiosity-emoji-picker .jg-emoji-btn').forEach(btn => {
                        btn.classList.toggle('selected', btn.textContent === emoji);
                    });
                };

                // Update category
                window.jgUpdateCuriosityCategory = function() {
                    const key = document.getElementById('edit-curiosity-cat-key').value;
                    const label = document.getElementById('edit-curiosity-cat-label').value.trim();
                    let icon = document.getElementById('edit-curiosity-cat-icon').value || '📖';
                    if (!jgIsValidEmoji(icon)) {
                        icon = jgGetFirstCuriosityEmoji();
                    }

                    if (!label) {
                        alert('Nazwa nie może być pusta');
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_update_curiosity_category',
                            nonce: nonce,
                            key: key,
                            label: label,
                            icon: icon
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd zapisu');
                        }
                    });
                };

                // Delete category
                window.jgDeleteCuriosityCategory = function(key) {
                    if (!confirm('Czy na pewno chcesz usunąć tę kategorię?')) {
                        return;
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_delete_curiosity_category',
                            nonce: nonce,
                            key: key
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data || 'Błąd usuwania');
                        }
                    });
                };
            })();
            </script>
        </div>
        <?php $this->render_filter_reset_card(); ?>
        <?php
    }

    /**
     * Render XP Editor page
     */
    public function render_xp_editor_page() {
        $nonce = wp_create_nonce('jg_map_admin_nonce');
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Edytor doświadczenia (XP)'); ?>
            <p style="margin-top:0;color:#6b7280">Konfiguruj za jakie akcje użytkownicy otrzymują doświadczenie (XP) i ile punktów przyznawać.</p>
            <p><strong>Formuła poziomów:</strong> Poziom N wymaga N&sup2; &times; 100 XP (np. poziom 2 = 400 XP, poziom 5 = 2500 XP, poziom 10 = 10000 XP)</p>

            <div id="jg-xp-editor" style="max-width:800px;margin-top:20px">
                <div class="jg-admin-table-wrap"><div class="jg-table-scroll">
                <table class="jg-admin-table" id="jg-xp-table">
                    <thead>
                        <tr>
                            <th style="width:240px">Akcja</th>
                            <th>Opis (opcjonalny)</th>
                            <th style="width:100px">XP</th>
                            <th style="width:80px">Aktywna</th>
                        </tr>
                    </thead>
                    <tbody id="jg-xp-tbody"></tbody>
                </table>
                </div></div>
                <p style="margin-top:12px">
                    <button class="button button-primary" id="jg-xp-save">Zapisz zmiany</button>
                    <span id="jg-xp-status" style="margin-left:12px;color:#059669;font-weight:600;display:none">Zapisano!</span>
                </p>
            </div>

            <script>
            (function() {
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                var nonce = '<?php echo $nonce; ?>';
                var tbody = document.getElementById('jg-xp-tbody');

                var availableActions = [
                    { key: 'submit_point', name: 'Dodanie punktu', defaultXp: 50 },
                    { key: 'point_approved', name: 'Zatwierdzenie punktu przez admina', defaultXp: 30 },
                    { key: 'receive_upvote', name: 'Otrzymanie głosu w górę', defaultXp: 5 },
                    { key: 'vote_on_point', name: 'Oddanie głosu na punkt', defaultXp: 2 },
                    { key: 'add_photo', name: 'Dodanie zdjęcia do punktu', defaultXp: 10 },
                    { key: 'edit_point', name: 'Edycja punktu', defaultXp: 15 },
                    { key: 'daily_login', name: 'Dzienny login', defaultXp: 5 },
                    { key: 'report_point', name: 'Zgłoszenie punktu', defaultXp: 10 }
                ];

                function renderRow(action, savedData) {
                    var tr = document.createElement('tr');
                    var isActive = savedData !== null;
                    var xpVal = isActive ? (savedData.xp || 0) : action.defaultXp;
                    var labelVal = isActive && savedData.label ? savedData.label : '';
                    tr.setAttribute('data-key', action.key);
                    tr.innerHTML = '<td><strong>' + esc(action.key) + '</strong><br><span style="color:#6b7280;font-size:calc(12 * var(--jg))">' + esc(action.name) + '</span></td>' +
                        '<td><input type="text" value="' + esc(labelVal) + '" class="xp-label regular-text" style="width:100%" placeholder="' + esc(action.name) + '"></td>' +
                        '<td><input type="number" value="' + xpVal + '" class="xp-amount" style="width:80px" min="0"></td>' +
                        '<td style="text-align:center"><input type="checkbox" class="xp-active"' + (isActive ? ' checked' : '') + '></td>';
                    tbody.appendChild(tr);
                }

                function esc(s) {
                    var d = document.createElement('div');
                    d.textContent = s || '';
                    return d.innerHTML.replace(/"/g, '&quot;');
                }

                // Load saved sources, then render all available actions
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'jg_admin_get_xp_sources', _ajax_nonce: nonce })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var saved = {};
                    if (data.success && Array.isArray(data.data)) {
                        data.data.forEach(function(s) { saved[s.key] = s; });
                    }
                    availableActions.forEach(function(action) {
                        renderRow(action, saved[action.key] || null);
                    });
                });

                document.getElementById('jg-xp-save').onclick = function() {
                    var rows = tbody.querySelectorAll('tr');
                    var sources = [];
                    rows.forEach(function(tr) {
                        if (!tr.querySelector('.xp-active').checked) return;
                        var key = tr.getAttribute('data-key');
                        var action = availableActions.find(function(a) { return a.key === key; });
                        sources.push({
                            key: key,
                            label: tr.querySelector('.xp-label').value || (action ? action.name : key),
                            xp: parseInt(tr.querySelector('.xp-amount').value) || 0
                        });
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_admin_save_xp_sources',
                            _ajax_nonce: nonce,
                            sources: JSON.stringify(sources)
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var status = document.getElementById('jg-xp-status');
                        if (data.success) {
                            status.textContent = 'Zapisano!';
                            status.style.color = '#059669';
                        } else {
                            status.textContent = 'Błąd: ' + (data.data || 'nieznany');
                            status.style.color = '#dc2626';
                        }
                        status.style.display = 'inline';
                        setTimeout(function() { status.style.display = 'none'; }, 3000);
                    });
                };
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Render Achievements Editor page
     */
    public function render_achievements_editor_page() {
        $nonce = wp_create_nonce('jg_map_admin_nonce');
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Edytor osiągnięć'); ?>
            <p style="margin-top:0;color:#6b7280">Konfiguruj osiągnięcia dostępne dla użytkowników. Rzadkość determinuje kolor poświaty wokół osiągnięcia.</p>

            <div class="jg-rarity-badges">
                <span class="jg-rarity-badge" style="background:#f3f4f6;border:2px solid #d1d5db">
                    <span class="jg-rarity-dot" style="background:#d1d5db;box-shadow:0 0 6px #d1d5db"></span> Zwykłe (common)
                </span>
                <span class="jg-rarity-badge" style="background:#ecfdf5;border:2px solid #10b981">
                    <span class="jg-rarity-dot" style="background:#10b981;box-shadow:0 0 6px #10b981"></span> Niepospolite (uncommon)
                </span>
                <span class="jg-rarity-badge" style="background:#eff6ff;border:2px solid #3b82f6">
                    <span class="jg-rarity-dot" style="background:#3b82f6;box-shadow:0 0 6px #3b82f6"></span> Rzadkie (rare)
                </span>
                <span class="jg-rarity-badge" style="background:#faf5ff;border:2px solid #8b5cf6">
                    <span class="jg-rarity-dot" style="background:#8b5cf6;box-shadow:0 0 6px #8b5cf6"></span> Epickie (epic)
                </span>
                <span class="jg-rarity-badge" style="background:#fffbeb;border:2px solid #f59e0b">
                    <span class="jg-rarity-dot" style="background:#f59e0b;box-shadow:0 0 6px #f59e0b"></span> Legendarne (legendary)
                </span>
            </div>

            <div id="jg-ach-editor" style="max-width:1100px;margin-top:12px">
                <div class="jg-admin-table-wrap"><div class="jg-table-scroll">
                <table class="jg-admin-table" id="jg-ach-table">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th style="width:120px">Slug</th>
                            <th style="width:160px">Nazwa</th>
                            <th>Opis</th>
                            <th style="width:50px">Ikona</th>
                            <th style="width:120px">Rzadkość</th>
                            <th style="width:140px">Warunek</th>
                            <th style="width:70px">Wartość</th>
                            <th style="width:60px">Kolejn.</th>
                            <th style="width:80px">Akcje</th>
                        </tr>
                    </thead>
                    <tbody id="jg-ach-tbody"></tbody>
                </table>
                </div></div>
                <p style="margin-top:12px">
                    <button class="button" id="jg-ach-add-row">+ Dodaj osiągnięcie</button>
                    <button class="button button-primary" id="jg-ach-save" style="margin-left:8px">Zapisz zmiany</button>
                    <span id="jg-ach-status" style="margin-left:12px;color:#059669;font-weight:600;display:none">Zapisano!</span>
                </p>
            </div>

            <script>
            (function() {
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                var nonce = '<?php echo $nonce; ?>';
                var tbody = document.getElementById('jg-ach-tbody');

                var rarityOptions = '<option value="common">Zwykłe (biała)</option><option value="uncommon">Niepospolite (zielona)</option><option value="rare">Rzadkie (niebieska)</option><option value="epic">Epickie (fioletowa)</option><option value="legendary">Legendarne (złota)</option>';
                var conditionOptions = '<option value="points_count">Liczba punktów</option><option value="votes_count">Liczba głosów</option><option value="photos_count">Liczba zdjęć</option><option value="level">Poziom</option><option value="all_types">Wszystkie typy</option><option value="received_upvotes">Otrzymane upvote\'y</option>';

                function esc(s) {
                    var d = document.createElement('div');
                    d.textContent = s || '';
                    return d.innerHTML.replace(/"/g, '&quot;');
                }

                function renderRow(ach) {
                    var tr = document.createElement('tr');
                    tr.dataset.id = ach.id || '';
                    tr.innerHTML =
                        '<td>' + (ach.id || '<em>nowe</em>') + '<input type="hidden" class="ach-id" value="' + (ach.id || '') + '"></td>' +
                        '<td><input type="text" value="' + esc(ach.slug) + '" class="ach-slug" style="width:100%"></td>' +
                        '<td><input type="text" value="' + esc(ach.name) + '" class="ach-name" style="width:100%"></td>' +
                        '<td><input type="text" value="' + esc(ach.description) + '" class="ach-desc" style="width:100%"></td>' +
                        '<td><input type="text" value="' + esc(ach.icon) + '" class="ach-icon" style="width:40px;text-align:center;font-size:calc(18 * var(--jg))"></td>' +
                        '<td><select class="ach-rarity">' + rarityOptions + '</select></td>' +
                        '<td><select class="ach-condition">' + conditionOptions + '</select></td>' +
                        '<td><input type="number" value="' + (ach.condition_value || 1) + '" class="ach-value" style="width:60px" min="1"></td>' +
                        '<td><input type="number" value="' + (ach.sort_order || 0) + '" class="ach-sort" style="width:50px"></td>' +
                        '<td><button class="button ach-remove" style="color:#dc2626">Usuń</button></td>';

                    // Set select values
                    if (ach.rarity) tr.querySelector('.ach-rarity').value = ach.rarity;
                    if (ach.condition_type) tr.querySelector('.ach-condition').value = ach.condition_type;

                    tr.querySelector('.ach-remove').onclick = function() {
                        var id = tr.dataset.id;
                        if (id) {
                            if (!confirm('Usunąć osiągnięcie?')) return;
                            fetch(ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'jg_admin_delete_achievement', _ajax_nonce: nonce, achievement_id: id })
                            }).then(function() { tr.remove(); });
                        } else {
                            tr.remove();
                        }
                    };

                    tbody.appendChild(tr);
                }

                // Load
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'jg_admin_get_achievements', _ajax_nonce: nonce })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && Array.isArray(data.data)) {
                        data.data.forEach(renderRow);
                    }
                });

                document.getElementById('jg-ach-add-row').onclick = function() {
                    renderRow({ id: '', slug: '', name: '', description: '', icon: '🏆', rarity: 'common', condition_type: 'points_count', condition_value: 1, sort_order: 0 });
                };

                document.getElementById('jg-ach-save').onclick = function() {
                    var rows = tbody.querySelectorAll('tr');
                    var achievements = [];
                    rows.forEach(function(tr) {
                        achievements.push({
                            id: tr.querySelector('.ach-id').value || '',
                            slug: tr.querySelector('.ach-slug').value,
                            name: tr.querySelector('.ach-name').value,
                            description: tr.querySelector('.ach-desc').value,
                            icon: tr.querySelector('.ach-icon').value,
                            rarity: tr.querySelector('.ach-rarity').value,
                            condition_type: tr.querySelector('.ach-condition').value,
                            condition_value: parseInt(tr.querySelector('.ach-value').value) || 1,
                            sort_order: parseInt(tr.querySelector('.ach-sort').value) || 0
                        });
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'jg_admin_save_achievements',
                            _ajax_nonce: nonce,
                            achievements: JSON.stringify(achievements)
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var status = document.getElementById('jg-ach-status');
                        if (data.success) {
                            status.textContent = 'Zapisano!';
                            status.style.color = '#059669';
                            // Reload to get proper IDs
                            setTimeout(function() { location.reload(); }, 1000);
                        } else {
                            status.textContent = 'Błąd: ' + (data.data || 'nieznany');
                            status.style.color = '#dc2626';
                        }
                        status.style.display = 'inline';
                    });
                };
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Render Challenges editor page
     */
    public function render_challenges_page() {
        $nonce      = wp_create_nonce('jg_map_admin_nonce');
        $conditions = JG_Map_Challenges::get_condition_types();
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Wyzwania społecznościowe'); ?>

            <style>
            .jg-ch-page { max-width: 1200px; }
            .jg-ch-info { background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:#1e40af; line-height:1.6; }
            .jg-ch-info strong { display:block; margin-bottom:4px; font-size:14px; }

            .jg-ch-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:20px; overflow:hidden; }
            .jg-ch-card-head { padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
            .jg-ch-card-title { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#374151; margin:0; }
            .jg-ch-card-body { padding:20px; }

            .jg-ch-list { display:flex; flex-direction:column; gap:16px; }

            .jg-ch-row { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; }
            .jg-ch-row-head { background:#f9fafb; padding:10px 16px; display:flex; align-items:center; gap:10px; border-bottom:1px solid #e5e7eb; }
            .jg-ch-row-id { font-size:11px; color:#9ca3af; min-width:50px; }
            .jg-ch-row-status { margin-left:auto; display:flex; align-items:center; gap:8px; }
            .jg-ch-row-body { padding:16px; display:grid; grid-template-columns:1fr 1fr; gap:14px 20px; }
            .jg-ch-row-body .jg-ch-full { grid-column:1/-1; }

            .jg-ch-field { display:flex; flex-direction:column; gap:5px; }
            .jg-ch-field label { font-size:12px; font-weight:600; color:#374151; }
            .jg-ch-field input[type=text],
            .jg-ch-field input[type=number],
            .jg-ch-field input[type=datetime-local],
            .jg-ch-field select,
            .jg-ch-field textarea {
                padding:8px 10px; border:1px solid #d1d5db; border-radius:6px;
                font-size:13px; width:100%; box-sizing:border-box;
                background:#fff; color:#111827; transition:border-color .15s;
                font-family:inherit;
            }
            .jg-ch-field input:focus,
            .jg-ch-field select:focus,
            .jg-ch-field textarea:focus { outline:none; border-color:#8d2324; box-shadow:0 0 0 2px rgba(141,35,36,.1); }
            .jg-ch-field textarea { resize:vertical; min-height:60px; }
            .jg-ch-field .jg-ch-hint { font-size:11px; color:#6b7280; margin-top:2px; }
            .jg-ch-field .jg-ch-error { font-size:11px; color:#dc2626; display:none; }

            .jg-ch-row-foot { padding:10px 16px; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:12px; }
            .jg-ch-row-foot .jg-ch-row-save { background:#8d2324; color:#fff; border:none; padding:8px 18px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; }
            .jg-ch-row-foot .jg-ch-row-delete { background:#fff; color:#dc2626; border:1px solid #dc2626; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
            .jg-ch-row-foot .jg-ch-row-msg { font-size:12px; font-weight:600; display:none; }

            .jg-ch-add-btn { background:#8d2324; color:#fff; border:none; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; }
            .jg-ch-add-btn:hover { background:#a02829; }

            .jg-ch-toggle-wrap { display:flex; align-items:center; gap:8px; }
            .jg-ch-toggle { position:relative; width:40px; height:22px; cursor:pointer; flex-shrink:0; }
            .jg-ch-toggle input { opacity:0; width:0; height:0; position:absolute; }
            .jg-ch-toggle-slider { position:absolute; inset:0; background:#d1d5db; border-radius:11px; transition:.2s; }
            .jg-ch-toggle input:checked + .jg-ch-toggle-slider { background:#059669; }
            .jg-ch-toggle-slider:before { content:''; position:absolute; left:3px; top:3px; width:16px; height:16px; border-radius:50%; background:#fff; transition:.2s; }
            .jg-ch-toggle input:checked + .jg-ch-toggle-slider:before { transform:translateX(18px); }

            @media(max-width:700px) {
                .jg-ch-row-body { grid-template-columns:1fr; }
                .jg-ch-row-body .jg-ch-full { grid-column:1; }
            }
            </style>

            <div class="jg-ch-page">
                <div class="jg-ch-info">
                    <strong>Jak działają wyzwania?</strong>
                    Wyzwanie jest widoczne na mapie dla wszystkich użytkowników — na desktopie jako widget na mapie, na telefonie między przyciskami. Postęp jest liczony automatycznie na podstawie aktywności w portalu w wybranym przedziale czasowym. Tylko jedno wyzwanie może być aktywne jednocześnie (pierwsze aktywne w bieżącym czasie).
                </div>

                <div class="jg-ch-card">
                    <div class="jg-ch-card-head">
                        <p class="jg-ch-card-title">🏆 Lista wyzwań</p>
                        <button class="jg-ch-add-btn" id="jg-ch-add">+ Dodaj nowe wyzwanie</button>
                    </div>
                    <div class="jg-ch-card-body">
                        <div class="jg-ch-list" id="jg-ch-list">
                            <p id="jg-ch-loading" style="color:#9ca3af;font-size:13px">Ładowanie...</p>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (function() {
                var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                var nonce   = '<?php echo esc_js($nonce); ?>';
                var list    = document.getElementById('jg-ch-list');

                var conditionTypes = <?php
                    $ct = array();
                    foreach ($conditions as $k => $v) {
                        $ct[] = array('key' => $k, 'label' => $v['label'], 'needs_cat' => (bool)$v['needs_cat'], 'group' => $v['group'] ?? '');
                    }
                    echo json_encode($ct);
                ?>;

                function esc(s) {
                    var d = document.createElement('div');
                    d.textContent = s || '';
                    return d.innerHTML.replace(/"/g, '&quot;');
                }

                function toDatetimeLocal(dt) {
                    if (!dt) return '';
                    return dt.replace(' ', 'T').substring(0, 16);
                }

                function buildConditionOptions(selected) {
                    var html = '';
                    var curGroup = null;
                    conditionTypes.forEach(function(ct) {
                        if (ct.group && ct.group !== curGroup) {
                            if (curGroup !== null) html += '</optgroup>';
                            html += '<optgroup label="' + ct.group + '">';
                            curGroup = ct.group;
                        }
                        html += '<option value="' + ct.key + '"' + (ct.key === selected ? ' selected' : '') + '>' + ct.label + '</option>';
                    });
                    if (curGroup !== null) html += '</optgroup>';
                    return html;
                }

                function needsCat(ctKey) {
                    for (var i = 0; i < conditionTypes.length; i++) {
                        if (conditionTypes[i].key === ctKey) return conditionTypes[i].needs_cat;
                    }
                    return false;
                }

                function buildRow(ch) {
                    var isNew = !ch.id;
                    var div = document.createElement('div');
                    div.className = 'jg-ch-row';
                    div.dataset.id = ch.id || '';

                    var now  = new Date();
                    var week = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
                    var fmt  = function(d) { return d.toISOString().substring(0, 16); };

                    var catVal = ch.category || ch.cat || '';
                    var ctVal  = ch.condition_type || ch.point_type || 'any_point';
                    var showCat = needsCat(ctVal) ? '' : 'display:none';

                    div.innerHTML =
                        '<div class="jg-ch-row-head">' +
                            '<span class="jg-ch-row-id">' + (ch.id ? '#' + ch.id : '<em>nowe</em>') + '</span>' +
                            '<strong style="font-size:13px;color:#111827;flex:1">' + esc(ch.title || '—') + '</strong>' +
                            '<div class="jg-ch-row-status">' +
                                '<label class="jg-ch-toggle" title="Aktywne">' +
                                    '<input type="checkbox" class="ch-active"' + (ch.is_active == 1 ? ' checked' : '') + '>' +
                                    '<span class="jg-ch-toggle-slider"></span>' +
                                '</label>' +
                                '<span style="font-size:12px;color:#6b7280">Aktywne</span>' +
                            '</div>' +
                        '</div>' +

                        '<div class="jg-ch-row-body">' +
                            '<div class="jg-ch-field jg-ch-full">' +
                                '<label>Tytuł wyzwania <span style="color:#dc2626">*</span></label>' +
                                '<input type="text" class="ch-title" value="' + esc(ch.title) + '" placeholder="np. Odkryj restauracje Jeleniej Góry!" maxlength="255">' +
                                '<span class="jg-ch-error" id="err-title-' + (ch.id||'new') + '">Tytuł jest wymagany</span>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full">' +
                                '<label>Opis (opcjonalny)</label>' +
                                '<textarea class="ch-desc" placeholder="Dodatkowe wyjaśnienie czego dotyczy wyzwanie…" maxlength="500">' + esc(ch.description) + '</textarea>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full">' +
                                '<label>Warunek — co trzeba zrobić <span style="color:#dc2626">*</span></label>' +
                                '<select class="ch-condition-type">' + buildConditionOptions(ctVal) + '</select>' +
                                '<span class="jg-ch-hint">Postęp wyzwania jest liczony automatycznie na podstawie wybranego warunku w przedziale czasowym wyzwania.</span>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full jg-ch-cat-wrap" style="' + showCat + '">' +
                                '<label>Konkretna kategoria (slug)</label>' +
                                '<input type="text" class="ch-category" value="' + esc(catVal) + '" placeholder="np. restauracja, historyczne (slug z ustawień kategorii)">' +
                                '<span class="jg-ch-hint">Wpisz slug kategorii z edytora kategorii. Pozostaw puste by liczyć wszystkie kategorie wybranego warunku.</span>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Cel (liczba akcji) <span style="color:#dc2626">*</span></label>' +
                                '<input type="number" class="ch-target" value="' + (ch.target_count || 10) + '" min="1" max="9999" style="max-width:120px">' +
                                '<span class="jg-ch-hint">Ile akcji trzeba wykonać, żeby ukończyć wyzwanie</span>' +
                                '<span class="jg-ch-error" id="err-target-' + (ch.id||'new') + '">Cel musi być liczbą ≥ 1</span>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Nagroda XP za ukończenie</label>' +
                                '<input type="number" class="ch-xp" value="' + (ch.xp_reward || 0) + '" min="0" max="99999" style="max-width:120px">' +
                                '<span class="jg-ch-hint">0 = brak nagrody XP (wyzwanie jest nadal widoczne)</span>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full" style="border-top:1px solid #e5e7eb;padding-top:14px;margin-top:2px">' +
                                '<label style="font-size:13px;font-weight:700;color:#374151">🏅 Unikalne osiągnięcie za ukończenie (opcjonalne)</label>' +
                                '<span class="jg-ch-hint" style="display:block;margin-top:3px">Użytkownicy, którzy ukończą wyzwanie, otrzymają poniższe osiągnięcie. Zostaw nazwę pustą, by nie przyznawać osiągnięcia.</span>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Nazwa osiągnięcia</label>' +
                                '<input type="text" class="ch-ach-name" value="' + esc(ch.ach_name || '') + '" placeholder="np. Odkrywca Jeleniej Góry" maxlength="255">' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Ikona (emoji) i rzadkość</label>' +
                                '<div style="display:flex;gap:8px;align-items:center">' +
                                    '<input type="text" class="ch-ach-icon" value="' + esc(ch.ach_icon || '🏆') + '" placeholder="🏆" style="max-width:64px;font-size:20px;text-align:center">' +
                                    '<select class="ch-ach-rarity" style="flex:1">' +
                                        '<option value="common"'    + ((!ch.ach_rarity||ch.ach_rarity==='common')?' selected':'')    + '>Zwykłe</option>' +
                                        '<option value="uncommon"'  + (ch.ach_rarity==='uncommon'?' selected':'')  + '>Niepospolite</option>' +
                                        '<option value="rare"'      + (ch.ach_rarity==='rare'?' selected':'')      + '>Rzadkie</option>' +
                                        '<option value="epic"'      + (ch.ach_rarity==='epic'?' selected':'')      + '>Epickie</option>' +
                                        '<option value="legendary"' + (ch.ach_rarity==='legendary'?' selected':'') + '>Legendarne</option>' +
                                    '</select>' +
                                '</div>' +
                            '</div>' +

                            '<div class="jg-ch-field jg-ch-full">' +
                                '<label>Opis osiągnięcia</label>' +
                                '<textarea class="ch-ach-desc" placeholder="np. Odkryłeś restauracje i kawiarnie Jeleniej Góry!" maxlength="500" style="min-height:48px">' + esc(ch.ach_desc || '') + '</textarea>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Data i godzina startu <span style="color:#dc2626">*</span></label>' +
                                '<input type="datetime-local" class="ch-start" value="' + toDatetimeLocal(ch.start_date || fmt(now).replace('T',' ')) + '">' +
                                '<span class="jg-ch-error" id="err-start-' + (ch.id||'new') + '">Data startu jest wymagana</span>' +
                            '</div>' +

                            '<div class="jg-ch-field">' +
                                '<label>Data i godzina zakończenia <span style="color:#dc2626">*</span></label>' +
                                '<input type="datetime-local" class="ch-end" value="' + toDatetimeLocal(ch.end_date || fmt(week).replace('T',' ')) + '">' +
                                '<span class="jg-ch-error" id="err-end-' + (ch.id||'new') + '">Data zakończenia musi być po starcie</span>' +
                            '</div>' +

                            '<input type="hidden" class="ch-id" value="' + (ch.id || '') + '">' +
                        '</div>' +

                        '<div class="jg-ch-row-foot">' +
                            '<button class="jg-ch-row-delete">🗑 Usuń wyzwanie</button>' +
                            '<div style="display:flex;align-items:center;gap:12px">' +
                                '<span class="jg-ch-row-msg"></span>' +
                                '<button class="jg-ch-row-save">💾 Zapisz</button>' +
                            '</div>' +
                        '</div>';

                    // Show/hide category field on condition type change
                    var ctSel  = div.querySelector('.ch-condition-type');
                    var catWrap = div.querySelector('.jg-ch-cat-wrap');
                    ctSel.addEventListener('change', function() {
                        catWrap.style.display = needsCat(ctSel.value) ? '' : 'none';
                    });

                    // Update header title on input
                    var titleInput = div.querySelector('.ch-title');
                    var headTitle  = div.querySelector('.jg-ch-row-head strong');
                    titleInput.addEventListener('input', function() {
                        headTitle.textContent = titleInput.value || '—';
                    });

                    // Validate and save row
                    div.querySelector('.jg-ch-row-save').addEventListener('click', function() {
                        var title   = titleInput.value.trim();
                        var target  = parseInt(div.querySelector('.ch-target').value, 10);
                        var startV  = div.querySelector('.ch-start').value;
                        var endV    = div.querySelector('.ch-end').value;
                        var msg     = div.querySelector('.jg-ch-row-msg');
                        var valid   = true;

                        if (!title) {
                            valid = false;
                            msg.textContent = '⚠ Tytuł jest wymagany.';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        }
                        if (!target || target < 1) {
                            valid = false;
                            msg.textContent = '⚠ Cel musi być liczbą ≥ 1.';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        }
                        if (!startV || !endV) {
                            valid = false;
                            msg.textContent = '⚠ Daty startu i zakończenia są wymagane.';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        }
                        if (startV && endV && startV >= endV) {
                            valid = false;
                            msg.textContent = '⚠ Data zakończenia musi być późniejsza niż start.';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        }
                        if (!valid) return;

                        msg.style.display = 'none';
                        var btn = div.querySelector('.jg-ch-row-save');
                        btn.disabled = true;
                        btn.textContent = 'Zapisywanie…';

                        var data = {
                            action:         'jg_admin_save_challenge',
                            _ajax_nonce:    nonce,
                            id:             div.querySelector('.ch-id').value,
                            title:          title,
                            description:    div.querySelector('.ch-desc').value,
                            condition_type: div.querySelector('.ch-condition-type').value,
                            category:       div.querySelector('.ch-category').value,
                            target_count:   target,
                            xp_reward:      parseInt(div.querySelector('.ch-xp').value, 10) || 0,
                            start_date:     startV.replace('T', ' ') + ':00',
                            end_date:       endV.replace('T', ' ')   + ':00',
                            is_active:      div.querySelector('.ch-active').checked ? 1 : 0,
                            ach_name:       div.querySelector('.ch-ach-name').value.trim(),
                            ach_desc:       div.querySelector('.ch-ach-desc').value.trim(),
                            ach_icon:       div.querySelector('.ch-ach-icon').value.trim() || '🏆',
                            ach_rarity:     div.querySelector('.ch-ach-rarity').value
                        };

                        fetch(ajaxUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams(data)
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(resp) {
                            btn.disabled = false;
                            btn.textContent = '💾 Zapisz';
                            if (resp.success) {
                                msg.textContent = '✓ Zapisano!';
                                msg.style.color = '#059669';
                                msg.style.display = 'inline';
                                if (!div.dataset.id && resp.data && resp.data.id) {
                                    div.dataset.id = resp.data.id;
                                    div.querySelector('.ch-id').value = resp.data.id;
                                    div.querySelector('.jg-ch-row-id').textContent = '#' + resp.data.id;
                                }
                                setTimeout(function() { msg.style.display = 'none'; }, 3000);
                            } else {
                                msg.textContent = '✗ ' + (resp.data || 'Błąd zapisu');
                                msg.style.color = '#dc2626';
                                msg.style.display = 'inline';
                            }
                        })
                        .catch(function() {
                            btn.disabled = false;
                            btn.textContent = '💾 Zapisz';
                            msg.textContent = '✗ Błąd połączenia';
                            msg.style.color = '#dc2626';
                            msg.style.display = 'inline';
                        });
                    });

                    // Delete row
                    div.querySelector('.jg-ch-row-delete').addEventListener('click', function() {
                        var id = div.dataset.id;
                        if (id) {
                            if (!confirm('Usunąć to wyzwanie trwale?')) return;
                            fetch(ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'jg_admin_delete_challenge', _ajax_nonce: nonce, challenge_id: id })
                            }).then(function() { div.remove(); });
                        } else {
                            div.remove();
                        }
                    });

                    return div;
                }

                // Load existing challenges
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'jg_admin_get_challenges', _ajax_nonce: nonce })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    document.getElementById('jg-ch-loading').remove();
                    if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                        data.data.forEach(function(ch) { list.appendChild(buildRow(ch)); });
                    } else {
                        var empty = document.createElement('p');
                        empty.style.cssText = 'color:#9ca3af;font-size:13px;margin:0';
                        empty.textContent = 'Brak wyzwań. Kliknij „+ Dodaj nowe wyzwanie" by utworzyć pierwsze.';
                        list.appendChild(empty);
                    }
                });

                document.getElementById('jg-ch-add').addEventListener('click', function() {
                    var emptyMsg = list.querySelector('p');
                    if (emptyMsg) emptyMsg.remove();
                    var row = buildRow({ id:'', title:'', description:'', condition_type:'any_point', category:'', target_count:10, xp_reward:50, start_date:'', end_date:'', is_active:1 });
                    list.prepend(row);
                    row.querySelector('.ch-title').focus();
                });
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Render tag management page
     */
    public function render_tags_page() {
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie tagami'); ?>

            <style>
                .jg-tags-manager { max-width: 900px; margin-top: 20px; }
                .jg-tags-manager .card { background: #fff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 20px; }
                .jg-tags-manager h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; font-size: 15px; font-weight: 700; color: #111827; }
                .jg-tags-search-wrap { position: relative; margin-bottom: 20px; }
                .jg-tags-search-input {
                    width: 100%; padding: 10px 14px; font-size: calc(14 * var(--jg));
                    border: 1px solid #ddd; border-radius: 6px;
                    box-sizing: border-box; transition: border-color 0.2s;
                }
                .jg-tags-search-input:focus { outline: none; border-color: #8d2324; box-shadow: 0 0 0 2px rgba(141,35,36,0.1); }
                .jg-tags-suggestions {
                    position: absolute; top: 100%; left: 0; right: 0; z-index: 100;
                    background: #fff; border: 1px solid #ddd; border-top: none;
                    border-radius: 0 0 6px 6px; max-height: 200px; overflow-y: auto;
                    display: none; box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                }
                .jg-tags-suggestions.visible { display: block; }
                .jg-tags-suggestion-item {
                    padding: 8px 14px; cursor: pointer; font-size: calc(13 * var(--jg));
                    border-bottom: 1px solid #f3f4f6; transition: background 0.15s;
                }
                .jg-tags-suggestion-item:last-child { border-bottom: none; }
                .jg-tags-suggestion-item:hover, .jg-tags-suggestion-item.active { background: #f3f4f6; }
                .jg-tags-suggestion-item mark { background: #fef3c7; padding: 0; border-radius: 2px; }
                .jg-tags-stats { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
                .jg-tags-stat {
                    background: #f9fafb; padding: 10px 16px; border-radius: 6px;
                    border: 1px solid #e5e7eb; font-size: calc(13 * var(--jg)); color: #666;
                }
                .jg-tags-stat strong { color: #333; font-size: calc(16 * var(--jg)); display: block; margin-bottom: 2px; }
                .jg-tags-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
                .jg-tags-table th {
                    text-align: left; padding: 10px 12px; background: #f9fafb;
                    border-bottom: 2px solid #e5e7eb; font-size: calc(13 * var(--jg)); color: #666;
                    font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
                }
                .jg-tags-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; font-size: calc(14 * var(--jg)); }
                .jg-tags-table tr:hover td { background: #f9fafb; }
                .jg-tag-name { font-weight: 500; color: #333; }
                .jg-tag-count {
                    display: inline-flex; align-items: center; justify-content: center;
                    background: #e5e7eb; color: #374151; padding: 2px 10px;
                    border-radius: 12px; font-size: calc(12 * var(--jg)); font-weight: 600; min-width: 24px;
                }
                .jg-tag-actions { display: flex; gap: 6px; }
                .jg-tag-btn {
                    background: none; border: 1px solid #ddd; cursor: pointer;
                    padding: 5px 10px; border-radius: 4px; font-size: calc(12 * var(--jg));
                    transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px;
                }
                .jg-tag-btn:hover { background: #f3f4f6; border-color: #999; }
                .jg-tag-btn.edit:hover { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
                .jg-tag-btn.delete:hover { background: #fef2f2; border-color: #ef4444; color: #dc2626; }
                .jg-tags-pagination {
                    display: flex; align-items: center; justify-content: center;
                    gap: 4px; margin-top: 16px;
                }
                .jg-tags-pagination button {
                    padding: 6px 12px; border: 1px solid #ddd; background: #fff;
                    border-radius: 4px; cursor: pointer; font-size: calc(13 * var(--jg)); transition: all 0.2s;
                    min-width: 36px;
                }
                .jg-tags-pagination button:hover:not(:disabled) { background: #f3f4f6; border-color: #999; }
                .jg-tags-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
                .jg-tags-pagination button.current { background: #8d2324; color: #fff; border-color: #8d2324; }
                .jg-tags-pagination .page-info { font-size: calc(13 * var(--jg)); color: #666; margin: 0 8px; }
                .jg-tags-empty { text-align: center; padding: 40px 20px; color: #999; font-size: calc(14 * var(--jg)); }
                .jg-tags-loading { text-align: center; padding: 30px; color: #666; }
                .jg-tag-edit-row td { background: #fffbeb !important; }
                .jg-tag-edit-input {
                    padding: 6px 10px; border: 1px solid #f59e0b; border-radius: 4px;
                    font-size: calc(14 * var(--jg)); width: 250px; outline: none;
                }
                .jg-tag-edit-input:focus { border-color: #d97706; box-shadow: 0 0 0 2px rgba(245,158,11,0.2); }
                .jg-tag-btn.save { background: #059669; color: #fff; border-color: #059669; }
                .jg-tag-btn.save:hover { background: #047857; }
                .jg-tag-btn.cancel { background: #6b7280; color: #fff; border-color: #6b7280; }
                .jg-tag-btn.cancel:hover { background: #4b5563; }
                .jg-tags-toast {
                    position: fixed; bottom: 30px; right: 30px; z-index: 9999;
                    padding: 12px 20px; border-radius: 8px; color: #fff; font-size: calc(14 * var(--jg));
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15); opacity: 0;
                    transform: translateY(10px); transition: all 0.3s;
                }
                .jg-tags-toast.visible { opacity: 1; transform: translateY(0); }
                .jg-tags-toast.success { background: #059669; }
                .jg-tags-toast.error { background: #dc2626; }
                .jg-confirm-overlay {
                    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.5); z-index: 9998;
                    display: flex; align-items: center; justify-content: center;
                }
                .jg-confirm-box {
                    background: #fff; border-radius: 12px; padding: 24px; max-width: 420px;
                    width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                }
                .jg-confirm-box h3 { margin: 0 0 8px; font-size: calc(16 * var(--jg)); color: #333; }
                .jg-confirm-box p { margin: 0 0 20px; font-size: calc(14 * var(--jg)); color: #666; line-height: 1.5; }
                .jg-confirm-box .jg-btn-row { display: flex; gap: 10px; justify-content: flex-end; }
                .jg-confirm-box button {
                    padding: 8px 18px; border-radius: 6px; font-size: calc(13 * var(--jg));
                    cursor: pointer; border: 1px solid #ddd; transition: all 0.2s;
                }
                .jg-confirm-box .btn-cancel { background: #f3f4f6; color: #333; }
                .jg-confirm-box .btn-cancel:hover { background: #e5e7eb; }
                .jg-confirm-box .btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; }
                .jg-confirm-box .btn-danger:hover { background: #b91c1c; }
            </style>

            <div class="jg-tags-manager">
                <div class="card">
                    <h2>Tagi</h2>
                    <p class="description">Zarządzaj tagami przypisanymi do miejsc na mapie. Możesz wyszukiwać, edytować nazwy i usuwać tagi.</p>

                    <div class="jg-tags-stats" id="jg-tags-stats"></div>

                    <div class="jg-tags-search-wrap">
                        <input type="text" class="jg-tags-search-input" id="jg-tags-search"
                               placeholder="Szukaj tagów..." autocomplete="off">
                        <div class="jg-tags-suggestions" id="jg-tags-suggestions"></div>
                    </div>

                    <div id="jg-tags-content">
                        <div class="jg-tags-loading">Ładowanie tagów...</div>
                    </div>

                    <div class="jg-tags-pagination" id="jg-tags-pagination"></div>
                </div>
            </div>

            <div id="jg-tags-toast" class="jg-tags-toast"></div>

            <script>
            (function() {
                const ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                const nonce = '<?php echo wp_create_nonce('jg_map_nonce'); ?>';

                let currentPage = 1;
                let currentSearch = '';
                let allTagNames = [];
                let editingTag = null;
                let suggestionsActive = -1;
                let debounceTimer = null;

                // Initialize
                loadTags();
                loadSuggestions();

                // Search input
                const searchInput = document.getElementById('jg-tags-search');
                const suggestionsEl = document.getElementById('jg-tags-suggestions');

                searchInput.addEventListener('input', function() {
                    const val = this.value.trim();
                    showSuggestions(val);

                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() {
                        currentSearch = val;
                        currentPage = 1;
                        loadTags();
                    }, 300);
                });

                searchInput.addEventListener('keydown', function(e) {
                    const items = suggestionsEl.querySelectorAll('.jg-tags-suggestion-item');
                    if (!items.length) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        suggestionsActive = Math.min(suggestionsActive + 1, items.length - 1);
                        updateActiveSuggestion(items);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        suggestionsActive = Math.max(suggestionsActive - 1, 0);
                        updateActiveSuggestion(items);
                    } else if (e.key === 'Enter' && suggestionsActive >= 0) {
                        e.preventDefault();
                        items[suggestionsActive].click();
                    } else if (e.key === 'Escape') {
                        hideSuggestions();
                    }
                });

                searchInput.addEventListener('focus', function() {
                    if (this.value.trim()) showSuggestions(this.value.trim());
                });

                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.jg-tags-search-wrap')) {
                        hideSuggestions();
                    }
                });

                function updateActiveSuggestion(items) {
                    items.forEach(function(item, i) {
                        item.classList.toggle('active', i === suggestionsActive);
                    });
                    if (suggestionsActive >= 0 && items[suggestionsActive]) {
                        items[suggestionsActive].scrollIntoView({ block: 'nearest' });
                    }
                }

                function removeDiacritics(str) {
                    return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\u0142/g, 'l').replace(/\u0141/g, 'L');
                }

                function showSuggestions(query) {
                    if (!query || query.length < 1) {
                        hideSuggestions();
                        return;
                    }
                    const normalizedQuery = removeDiacritics(query.toLowerCase());
                    const matches = allTagNames.filter(function(t) {
                        return removeDiacritics(t.toLowerCase()).indexOf(normalizedQuery) !== -1;
                    }).slice(0, 10);

                    if (!matches.length) {
                        hideSuggestions();
                        return;
                    }

                    suggestionsEl.innerHTML = matches.map(function(tag) {
                        var normalizedTag = removeDiacritics(tag.toLowerCase());
                        var idx = normalizedTag.indexOf(normalizedQuery);
                        let html = '';
                        if (idx >= 0) {
                            html = escHtml(tag.substring(0, idx)) +
                                   '<mark>' + escHtml(tag.substring(idx, idx + query.length)) + '</mark>' +
                                   escHtml(tag.substring(idx + query.length));
                        } else {
                            html = escHtml(tag);
                        }
                        return '<div class="jg-tags-suggestion-item" data-tag="' + escAttr(tag) + '">' + html + '</div>';
                    }).join('');

                    suggestionsEl.classList.add('visible');
                    suggestionsActive = -1;

                    suggestionsEl.querySelectorAll('.jg-tags-suggestion-item').forEach(function(item) {
                        item.addEventListener('click', function() {
                            searchInput.value = this.dataset.tag;
                            currentSearch = this.dataset.tag;
                            currentPage = 1;
                            hideSuggestions();
                            loadTags();
                        });
                    });
                }

                function hideSuggestions() {
                    suggestionsEl.classList.remove('visible');
                    suggestionsActive = -1;
                }

                function loadSuggestions() {
                    jQuery.post(ajaxUrl, {
                        action: 'jg_admin_get_tag_suggestions',
                        _ajax_nonce: nonce
                    }, function(res) {
                        if (res.success) {
                            allTagNames = res.data;
                        }
                    });
                }

                function loadTags() {
                    const content = document.getElementById('jg-tags-content');
                    content.innerHTML = '<div class="jg-tags-loading">Ładowanie tagów...</div>';

                    jQuery.post(ajaxUrl, {
                        action: 'jg_admin_get_tags_paginated',
                        _ajax_nonce: nonce,
                        search: currentSearch,
                        page: currentPage,
                        per_page: 20
                    }, function(res) {
                        if (!res.success) {
                            content.innerHTML = '<div class="jg-tags-empty">Błąd ładowania tagów</div>';
                            return;
                        }

                        const data = res.data;
                        renderStats(data.total);
                        renderTable(data.tags);
                        renderPagination(data.page, data.pages, data.total);
                    });
                }

                function renderStats(total) {
                    document.getElementById('jg-tags-stats').innerHTML =
                        '<div class="jg-tags-stat"><strong>' + total + '</strong>' +
                        (currentSearch ? 'znalezionych tagów' : 'tagów łącznie') + '</div>';
                }

                function renderTable(tags) {
                    const content = document.getElementById('jg-tags-content');

                    if (!tags.length) {
                        content.innerHTML = '<div class="jg-tags-empty">' +
                            (currentSearch ? 'Nie znaleziono tagów pasujących do "' + escHtml(currentSearch) + '"' : 'Brak tagów') +
                            '</div>';
                        return;
                    }

                    let html = '<table class="jg-tags-table"><thead><tr>' +
                        '<th>Tag</th><th>Liczba miejsc</th><th>Akcje</th>' +
                        '</tr></thead><tbody>';

                    tags.forEach(function(tag) {
                        html += '<tr data-tag="' + escAttr(tag.name) + '">' +
                            '<td><span class="jg-tag-name">#' + escHtml(tag.name) + '</span></td>' +
                            '<td><span class="jg-tag-count">' + tag.count + '</span></td>' +
                            '<td class="jg-tag-actions">' +
                                '<button class="jg-tag-btn edit" onclick="jgEditTag(\'' + escJs(tag.name) + '\')" title="Edytuj">' +
                                    '✏️ Edytuj</button>' +
                                '<button class="jg-tag-btn delete" onclick="jgDeleteTag(\'' + escJs(tag.name) + '\', ' + tag.count + ')" title="Usuń">' +
                                    '🗑️ Usuń</button>' +
                            '</td></tr>';
                    });

                    html += '</tbody></table>';
                    content.innerHTML = html;
                }

                function renderPagination(page, pages, total) {
                    const pag = document.getElementById('jg-tags-pagination');
                    if (pages <= 1) {
                        pag.innerHTML = '';
                        return;
                    }

                    let html = '<button ' + (page <= 1 ? 'disabled' : 'onclick="jgTagsPage(1)"') + ' title="Pierwsza strona">&laquo;</button>';
                    html += '<button ' + (page <= 1 ? 'disabled' : 'onclick="jgTagsPage(' + (page - 1) + ')"') + '>&lsaquo;</button>';

                    // Page numbers
                    let startPage = Math.max(1, page - 2);
                    let endPage = Math.min(pages, page + 2);

                    if (startPage > 1) {
                        html += '<button onclick="jgTagsPage(1)">1</button>';
                        if (startPage > 2) html += '<span class="page-info">...</span>';
                    }

                    for (let i = startPage; i <= endPage; i++) {
                        html += '<button class="' + (i === page ? 'current' : '') + '" onclick="jgTagsPage(' + i + ')">' + i + '</button>';
                    }

                    if (endPage < pages) {
                        if (endPage < pages - 1) html += '<span class="page-info">...</span>';
                        html += '<button onclick="jgTagsPage(' + pages + ')">' + pages + '</button>';
                    }

                    html += '<button ' + (page >= pages ? 'disabled' : 'onclick="jgTagsPage(' + (page + 1) + ')"') + '>&rsaquo;</button>';
                    html += '<button ' + (page >= pages ? 'disabled' : 'onclick="jgTagsPage(' + pages + ')"') + ' title="Ostatnia strona">&raquo;</button>';

                    pag.innerHTML = html;
                }

                // Global functions
                window.jgTagsPage = function(page) {
                    currentPage = page;
                    loadTags();
                    document.querySelector('.jg-tags-manager .card').scrollIntoView({ behavior: 'smooth' });
                };

                window.jgEditTag = function(tagName) {
                    // Switch the row to edit mode
                    const row = document.querySelector('tr[data-tag="' + CSS.escape(tagName) + '"]');
                    if (!row) return;

                    editingTag = tagName;
                    row.classList.add('jg-tag-edit-row');
                    const nameCell = row.querySelector('td:first-child');
                    const actionsCell = row.querySelector('.jg-tag-actions');

                    nameCell.innerHTML = '<input type="text" class="jg-tag-edit-input" id="jg-tag-edit-input" value="' + escAttr(tagName) + '" maxlength="50">';
                    actionsCell.innerHTML =
                        '<button class="jg-tag-btn save" onclick="jgSaveTag()">Zapisz</button>' +
                        '<button class="jg-tag-btn cancel" onclick="jgCancelEdit()">Anuluj</button>';

                    const input = document.getElementById('jg-tag-edit-input');
                    input.focus();
                    input.select();

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') jgSaveTag();
                        if (e.key === 'Escape') jgCancelEdit();
                    });
                };

                window.jgSaveTag = function() {
                    const input = document.getElementById('jg-tag-edit-input');
                    if (!input || !editingTag) return;

                    const newName = input.value.trim();
                    if (!newName) {
                        showToast('Nazwa tagu nie może być pusta', 'error');
                        return;
                    }
                    if (newName === editingTag) {
                        jgCancelEdit();
                        return;
                    }

                    input.disabled = true;

                    jQuery.post(ajaxUrl, {
                        action: 'jg_admin_rename_tag',
                        _ajax_nonce: nonce,
                        old_name: editingTag,
                        new_name: newName
                    }, function(res) {
                        if (res.success) {
                            showToast(res.data.message, 'success');
                            editingTag = null;
                            loadTags();
                            loadSuggestions();
                        } else {
                            showToast(res.data.message || 'Błąd', 'error');
                            input.disabled = false;
                        }
                    }).fail(function() {
                        showToast('Błąd połączenia', 'error');
                        input.disabled = false;
                    });
                };

                window.jgCancelEdit = function() {
                    editingTag = null;
                    loadTags();
                };

                window.jgDeleteTag = function(tagName, count) {
                    const overlay = document.createElement('div');
                    overlay.className = 'jg-confirm-overlay';
                    overlay.innerHTML =
                        '<div class="jg-confirm-box">' +
                            '<h3>Usuń tag</h3>' +
                            '<p>Czy na pewno chcesz usunąć tag <strong>#' + escHtml(tagName) + '</strong>?<br>' +
                            'Tag zostanie usunięty z <strong>' + count + '</strong> ' + pluralize(count) + '.</p>' +
                            '<div class="jg-btn-row">' +
                                '<button class="btn-cancel" id="jg-confirm-cancel">Anuluj</button>' +
                                '<button class="btn-danger" id="jg-confirm-delete">Usuń tag</button>' +
                            '</div>' +
                        '</div>';

                    document.body.appendChild(overlay);

                    document.getElementById('jg-confirm-cancel').addEventListener('click', function() {
                        overlay.remove();
                    });
                    overlay.addEventListener('click', function(e) {
                        if (e.target === overlay) overlay.remove();
                    });

                    document.getElementById('jg-confirm-delete').addEventListener('click', function() {
                        this.disabled = true;
                        this.textContent = 'Usuwanie...';

                        jQuery.post(ajaxUrl, {
                            action: 'jg_admin_delete_tag',
                            _ajax_nonce: nonce,
                            tag_name: tagName
                        }, function(res) {
                            overlay.remove();
                            if (res.success) {
                                showToast(res.data.message, 'success');
                                loadTags();
                                loadSuggestions();
                            } else {
                                showToast(res.data.message || 'Błąd', 'error');
                            }
                        }).fail(function() {
                            overlay.remove();
                            showToast('Błąd połączenia', 'error');
                        });
                    });
                };

                function pluralize(count) {
                    if (count === 1) return 'miejsca';
                    return 'miejsc';
                }

                function showToast(message, type) {
                    const toast = document.getElementById('jg-tags-toast');
                    toast.textContent = message;
                    toast.className = 'jg-tags-toast ' + type + ' visible';
                    setTimeout(function() {
                        toast.classList.remove('visible');
                    }, 3000);
                }

                function escHtml(str) {
                    var div = document.createElement('div');
                    div.appendChild(document.createTextNode(str));
                    return div.innerHTML;
                }

                function escAttr(str) {
                    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                }

                function escJs(str) {
                    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
                }
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Render admin page for configuring the mobile hamburger-menu navigation items.
     * Items are stored as JSON in wp_options under 'jg_map_nav_menu'.
     */
    public function render_nav_menu_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.');
        }

        /* ── Handle form save ── */
        if (isset($_POST['jg_nav_menu_save']) && check_admin_referer('jg_nav_menu_nonce')) {
            $json = isset($_POST['jg_nav_menu_json']) ? wp_unslash($_POST['jg_nav_menu_json']) : '[]';
            $raw  = json_decode($json, true);
            if (!is_array($raw)) $raw = array();

            $items = array();
            foreach ($raw as $item) {
                if (!is_array($item)) continue;
                $label = sanitize_text_field(isset($item['label']) ? $item['label'] : '');
                $url   = esc_url_raw(isset($item['url']) ? $item['url'] : '');
                if ($label === '' || $url === '') continue;

                $children = array();
                if (!empty($item['children']) && is_array($item['children'])) {
                    foreach ($item['children'] as $child) {
                        if (!is_array($child)) continue;
                        $clabel = sanitize_text_field(isset($child['label']) ? $child['label'] : '');
                        $curl   = esc_url_raw(isset($child['url']) ? $child['url'] : '');
                        if ($clabel === '' || $curl === '') continue;
                        $children[] = array(
                            'label'   => $clabel,
                            'url'     => $curl,
                            'new_tab' => !empty($child['new_tab']),
                        );
                    }
                }

                $items[] = array(
                    'label'    => $label,
                    'url'      => $url,
                    'new_tab'  => !empty($item['new_tab']),
                    'children' => $children,
                );
            }

            update_option('jg_map_nav_menu', $items);
            echo '<div class="notice notice-success is-dismissible"><p>Menu zostało zapisane.</p></div>';
        }

        $items = get_option('jg_map_nav_menu', array());
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Menu nawigacyjne (mobilny pasek)'); ?>
            <p class="description" style="margin-bottom:16px;color:#6b7280">
                Pozycje wyświetlane w rozwijanym menu hamburgerowym na pasku z logo portalu (widocznym na urządzeniach mobilnych).
                Przeciągaj wiersze za uchwyt <strong>⠿</strong> aby zmieniać kolejność.
            </p>

            <style>
            .jg-nav-row.jg-drag-over { outline: 2px dashed #2271b1 !important; background: #f0f7ff !important; }
            .jg-nav-row .jg-drag-handle { cursor: grab !important; }
            .jg-nav-row .jg-drag-handle:active { cursor: grabbing !important; }
            .jg-nav-subrow td { background: #fafafa; }
            .jg-nav-subrow td:first-child { border-left: 3px solid #e5e7eb; }
            </style>

            <form method="post" action="" id="jg-nav-menu-form">
                <?php wp_nonce_field('jg_nav_menu_nonce'); ?>
                <input type="hidden" name="jg_nav_menu_json" id="jg-nav-menu-json" value="<?php echo esc_attr(wp_json_encode($items)); ?>">

                <div class="jg-card jg-card-body" style="max-width:900px">
                    <h2>Pozycje menu</h2>

                    <div class="jg-admin-table-wrap"><div class="jg-table-scroll">
                    <table class="jg-admin-table" id="jg-nav-menu-table">
                        <thead>
                            <tr>
                                <th style="width:30px"></th>
                                <th style="width:36px">#</th>
                                <th>Etykieta</th>
                                <th>URL</th>
                                <th style="width:110px;text-align:center">Nowa karta</th>
                                <th style="width:190px"></th>
                            </tr>
                        </thead>
                        <tbody id="jg-nav-menu-body">
                            <?php foreach ($items as $idx => $item) : ?>
                            <tr class="jg-nav-row" data-idx="<?php echo esc_attr($idx); ?>" draggable="true">
                                <td class="jg-drag-handle" title="Przeciągnij aby zmienić kolejność" style="text-align:center;color:#9ca3af;font-size:18px;user-select:none;padding:4px 8px">⠿</td>
                                <td class="jg-row-num" style="color:#9ca3af;font-size:calc(13 * var(--jg))"><?php echo $idx + 1; ?></td>
                                <td>
                                    <input type="text" class="jg-nav-label regular-text"
                                           value="<?php echo esc_attr($item['label']); ?>"
                                           placeholder="np. Aktualności">
                                </td>
                                <td>
                                    <input type="url" class="jg-nav-url regular-text"
                                           value="<?php echo esc_attr($item['url']); ?>"
                                           placeholder="https://...">
                                </td>
                                <td style="text-align:center">
                                    <input type="checkbox" class="jg-nav-new-tab" value="1"
                                           <?php checked(!empty($item['new_tab'])); ?>>
                                </td>
                                <td>
                                    <button type="button" class="button jg-nav-add-sub" style="margin-right:4px">+ Podmenu</button>
                                    <button type="button" class="button jg-nav-remove-row" style="color:#dc2626;border-color:#dc2626">Usuń</button>
                                </td>
                            </tr>
                            <?php if (!empty($item['children'])) : ?>
                                <?php foreach ($item['children'] as $child) : ?>
                                <tr class="jg-nav-subrow" data-parent="<?php echo esc_attr($idx); ?>">
                                    <td></td>
                                    <td style="color:#9ca3af;font-size:12px;text-align:center">↳</td>
                                    <td>
                                        <input type="text" class="jg-nav-sub-label regular-text"
                                               value="<?php echo esc_attr($child['label']); ?>"
                                               placeholder="np. Podstrona"
                                               style="margin-left:8px">
                                    </td>
                                    <td>
                                        <input type="url" class="jg-nav-sub-url regular-text"
                                               value="<?php echo esc_attr($child['url']); ?>"
                                               placeholder="https://...">
                                    </td>
                                    <td style="text-align:center">
                                        <input type="checkbox" class="jg-nav-sub-new-tab" value="1"
                                               <?php checked(!empty($child['new_tab'])); ?>>
                                    </td>
                                    <td>
                                        <button type="button" class="button jg-nav-remove-subrow" style="color:#dc2626;border-color:#dc2626">Usuń</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div></div>

                    <p style="margin-top:12px">
                        <button type="button" id="jg-nav-add-row" class="button">+ Dodaj pozycję</button>
                    </p>
                </div>

                <p>
                    <input type="submit" name="jg_nav_menu_save" class="button button-primary button-large" value="Zapisz menu">
                </p>
            </form>

            <script>
            (function () {
                var tbody   = document.getElementById('jg-nav-menu-body');
                var addBtn  = document.getElementById('jg-nav-add-row');
                var form    = document.getElementById('jg-nav-menu-form');
                var jsonFld = document.getElementById('jg-nav-menu-json');

                if (!tbody || !addBtn || !form || !jsonFld) return;

                /* ── Refresh row numbers for parent rows ── */
                function refreshNumbers() {
                    var n = 0;
                    tbody.querySelectorAll('.jg-nav-row').forEach(function (row) {
                        row.querySelector('.jg-row-num').textContent = ++n;
                    });
                }

                /* ── Get subrows belonging to a parent row ── */
                function getSubrows(parentRow) {
                    var idx  = parentRow.dataset.idx;
                    var subs = [];
                    var next = parentRow.nextElementSibling;
                    while (next && next.classList.contains('jg-nav-subrow') && next.dataset.parent === idx) {
                        subs.push(next);
                        next = next.nextElementSibling;
                    }
                    return subs;
                }

                /* ── Build JSON from current DOM state ── */
                function collectJSON() {
                    var items = [];
                    tbody.querySelectorAll('.jg-nav-row').forEach(function (row) {
                        var label  = row.querySelector('.jg-nav-label').value.trim();
                        var url    = row.querySelector('.jg-nav-url').value.trim();
                        var newTab = row.querySelector('.jg-nav-new-tab').checked;
                        var children = [];
                        getSubrows(row).forEach(function (sr) {
                            var sl = sr.querySelector('.jg-nav-sub-label').value.trim();
                            var su = sr.querySelector('.jg-nav-sub-url').value.trim();
                            var sn = sr.querySelector('.jg-nav-sub-new-tab').checked;
                            if (sl && su) children.push({ label: sl, url: su, new_tab: sn });
                        });
                        if (label && url) items.push({ label: label, url: url, new_tab: newTab, children: children });
                    });
                    return JSON.stringify(items);
                }

                /* ── Keep hidden field in sync with DOM at all times ── */
                function syncJSON() {
                    jsonFld.value = collectJSON();
                }

                /* ── Add parent row ── */
                addBtn.addEventListener('click', function () {
                    var uid = String(Date.now());
                    var tr  = document.createElement('tr');
                    tr.className   = 'jg-nav-row';
                    tr.dataset.idx = uid;
                    tr.draggable   = true;
                    tr.innerHTML =
                        '<td class="jg-drag-handle" title="Przeciągnij aby zmienić kolejność" style="text-align:center;color:#9ca3af;font-size:18px;user-select:none;padding:4px 8px">⠿</td>' +
                        '<td class="jg-row-num" style="color:#9ca3af;font-size:calc(13 * var(--jg))"></td>' +
                        '<td><input type="text" class="jg-nav-label regular-text" placeholder="np. Aktualności"></td>' +
                        '<td><input type="url"  class="jg-nav-url regular-text"   placeholder="https://..."></td>' +
                        '<td style="text-align:center"><input type="checkbox" class="jg-nav-new-tab" value="1"></td>' +
                        '<td>' +
                            '<button type="button" class="button jg-nav-add-sub" style="margin-right:4px">+ Podmenu</button>' +
                            '<button type="button" class="button jg-nav-remove-row" style="color:#dc2626;border-color:#dc2626">Usuń</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                    bindDrag(tr);
                    bindRemove(tr.querySelector('.jg-nav-remove-row'));
                    bindAddSub(tr.querySelector('.jg-nav-add-sub'));
                    tr.querySelector('.jg-nav-label').focus();
                    refreshNumbers();
                    syncJSON();
                });

                /* ── Add subrow under a parent ── */
                function addSubrow(parentRow) {
                    var idx    = parentRow.dataset.idx;
                    var subs   = getSubrows(parentRow);
                    var anchor = subs.length ? subs[subs.length - 1] : parentRow;
                    var tr     = document.createElement('tr');
                    tr.className      = 'jg-nav-subrow';
                    tr.dataset.parent = idx;
                    tr.innerHTML =
                        '<td></td>' +
                        '<td style="color:#9ca3af;font-size:12px;text-align:center">↳</td>' +
                        '<td><input type="text" class="jg-nav-sub-label regular-text" placeholder="np. Podstrona" style="margin-left:8px"></td>' +
                        '<td><input type="url"  class="jg-nav-sub-url regular-text"   placeholder="https://..."></td>' +
                        '<td style="text-align:center"><input type="checkbox" class="jg-nav-sub-new-tab" value="1"></td>' +
                        '<td><button type="button" class="button jg-nav-remove-subrow" style="color:#dc2626;border-color:#dc2626">Usuń</button></td>';
                    anchor.insertAdjacentElement('afterend', tr);
                    bindRemoveSub(tr.querySelector('.jg-nav-remove-subrow'));
                    tr.querySelector('.jg-nav-sub-label').focus();
                    syncJSON();
                }

                function bindAddSub(btn) {
                    btn.addEventListener('click', function () {
                        addSubrow(btn.closest('.jg-nav-row'));
                    });
                }

                function bindRemoveSub(btn) {
                    btn.addEventListener('click', function () {
                        btn.closest('.jg-nav-subrow').remove();
                        syncJSON();
                    });
                }

                function bindRemove(btn) {
                    btn.addEventListener('click', function () {
                        var row = btn.closest('.jg-nav-row');
                        getSubrows(row).forEach(function (sr) { sr.remove(); });
                        row.remove();
                        refreshNumbers();
                        syncJSON();
                    });
                }

                /* ── Drag & drop reorder ── */
                var dragSrc = null;

                function bindDrag(row) {
                    row.addEventListener('dragstart', function (e) {
                        dragSrc = row;
                        e.dataTransfer.effectAllowed = 'move';
                        setTimeout(function () { row.style.opacity = '0.5'; }, 0);
                    });
                    row.addEventListener('dragend', function () {
                        row.style.opacity = '';
                        dragSrc = null;
                        tbody.querySelectorAll('.jg-nav-row').forEach(function (r) {
                            r.classList.remove('jg-drag-over');
                        });
                    });
                    row.addEventListener('dragover', function (e) {
                        if (!dragSrc || dragSrc === row) return;
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        tbody.querySelectorAll('.jg-nav-row').forEach(function (r) {
                            r.classList.remove('jg-drag-over');
                        });
                        row.classList.add('jg-drag-over');
                    });
                    row.addEventListener('drop', function (e) {
                        e.preventDefault();
                        if (!dragSrc || dragSrc === row) return;

                        var srcSubs    = getSubrows(dragSrc);
                        var targetSubs = getSubrows(row);
                        var rect       = row.getBoundingClientRect();
                        var before     = e.clientY < rect.top + rect.height / 2;

                        if (before) {
                            tbody.insertBefore(dragSrc, row);
                        } else {
                            var anchor = targetSubs.length ? targetSubs[targetSubs.length - 1] : row;
                            anchor.insertAdjacentElement('afterend', dragSrc);
                        }

                        /* Re-attach dragSrc's subrows immediately after it */
                        var after = dragSrc;
                        srcSubs.forEach(function (sr) {
                            after.insertAdjacentElement('afterend', sr);
                            after = sr;
                        });

                        row.classList.remove('jg-drag-over');
                        refreshNumbers();
                        syncJSON();
                    });
                }

                tbody.querySelectorAll('.jg-nav-row').forEach(bindDrag);
                tbody.querySelectorAll('.jg-nav-remove-row').forEach(bindRemove);
                tbody.querySelectorAll('.jg-nav-add-sub').forEach(bindAddSub);
                tbody.querySelectorAll('.jg-nav-remove-subrow').forEach(bindRemoveSub);

                /* ── Sync on any value change in the table ── */
                tbody.addEventListener('input', syncJSON);
                tbody.addEventListener('change', syncJSON);

                /* ── Final sync before submit (safety net) ── */
                form.addEventListener('submit', function () {
                    jsonFld.value = collectJSON();
                });
            })();
            </script>
        </div>
        <?php
    }

    /**
     * SEO Pinezek — admin page for setting canonical URL and noindex on map pins
     */
    public function render_seo_page() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $per_page     = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset       = ($current_page - 1) * $per_page;
        $search       = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

        $where = "status = 'publish' AND type IN ('miejsce','ciekawostka')";
        if ($search) {
            $where .= $wpdb->prepare(' AND title LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total       = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
        $total_pages = max(1, ceil($total / $per_page));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $points = $wpdb->get_results(
            "SELECT id, title, slug, type, seo_canonical, seo_noindex FROM $table WHERE $where ORDER BY title ASC LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );

        $type_labels = array('miejsce' => 'Miejsce', 'ciekawostka' => 'Ciekawostka');
        ?>
        <style>
        .jg-seo-canonical-input{width:100%;max-width:360px;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px}
        .jg-seo-canonical-input.has-value{border-color:#2563eb;background:#eff6ff}
        .jg-seo-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase}
        .jg-seo-badge-noindex{background:#fee2e2;color:#b91c1c}
        .jg-seo-badge-canonical{background:#dbeafe;color:#1e40af}
        .jg-seo-saved{color:#16a34a;font-weight:700;font-size:11px;display:none;margin-left:6px}
        </style>
        <div class="wrap">
            <?php $this->render_page_header('SEO Pinezek'); ?>

            <p style="color:#6b7280;margin-bottom:20px">Ustaw niestandardowy canonical URL lub flagę noindex dla pinezek mapy. Przydatne przy cannibalizacji między pinezką a artykułem.</p>

            <!-- Search -->
            <div class="jg-admin-table-wrap" style="padding:16px;margin-bottom:16px">
                <form method="get" action="">
                    <input type="hidden" name="page" value="jg-map-seo">
                    <div style="display:flex;gap:10px;align-items:center">
                        <input type="text" name="search" value="<?php echo esc_attr($search); ?>"
                               placeholder="Szukaj po nazwie..."
                               style="flex:1;max-width:340px;padding:8px 12px;border:1px solid #ddd;border-radius:4px">
                        <button type="submit" class="button button-primary">🔍 Szukaj</button>
                        <?php if ($search): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jg-map-seo')); ?>" class="button">✕ Wyczyść</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (!empty($points)): ?>
            <div class="jg-admin-table-wrap">
              <div class="jg-table-scroll">
                <table class="jg-admin-table">
                    <thead>
                        <tr>
                            <th>Nazwa</th>
                            <th>Typ</th>
                            <th>Status SEO</th>
                            <th>Canonical URL <span style="font-weight:400;text-transform:none">(puste = auto)</span></th>
                            <th style="text-align:center">Noindex</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($points as $p):
                        $pin_url      = home_url('/' . ($p['type'] === 'ciekawostka' ? 'ciekawostka' : 'miejsce') . '/' . $p['slug'] . '/');
                        $has_canonical = !empty($p['seo_canonical']);
                        $has_noindex   = !empty($p['seo_noindex']);
                    ?>
                        <tr data-id="<?php echo intval($p['id']); ?>">
                            <td data-label="Nazwa">
                                <a href="<?php echo esc_url($pin_url); ?>" target="_blank"><?php echo esc_html($p['title']); ?></a>
                            </td>
                            <td data-label="Typ"><?php echo esc_html($type_labels[$p['type']] ?? $p['type']); ?></td>
                            <td data-label="Status SEO">
                                <?php if ($has_noindex): ?>
                                    <span class="jg-seo-badge jg-seo-badge-noindex">noindex</span>
                                <?php elseif ($has_canonical): ?>
                                    <span class="jg-seo-badge jg-seo-badge-canonical">canonical</span>
                                <?php else: ?>
                                    <span style="color:#9ca3af;font-size:11px">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Canonical URL">
                                <input type="text"
                                       class="jg-seo-canonical-input<?php echo $has_canonical ? ' has-value' : ''; ?>"
                                       value="<?php echo esc_attr($p['seo_canonical'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($pin_url); ?>">
                            </td>
                            <td data-label="Noindex" style="text-align:center">
                                <input type="checkbox" class="jg-seo-noindex" <?php checked($has_noindex); ?>>
                            </td>
                            <td data-label="" class="jg-td-actions">
                                <div class="jg-action-btns">
                                    <button class="button jg-seo-save-btn">Zapisz</button>
                                    <span class="jg-seo-saved">✓ Zapisano</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
              </div>
            </div>

            <div class="tablenav bottom" style="padding-top:10px">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total); ?> pinezek</span>
                    <?php if ($total_pages > 1): ?>
                    <span class="pagination-links">
                        <?php for ($i = 1; $i <= $total_pages; $i++):
                            $page_url = add_query_arg(array('page' => 'jg-map-seo', 'paged' => $i, 'search' => $search), admin_url('admin.php'));
                        ?>
                            <?php if ($i === $current_page): ?>
                                <span class="tablenav-pages-navspan button disabled"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a class="button" href="<?php echo esc_url($page_url); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <p style="color:#9ca3af;padding:20px 0"><?php echo $search ? 'Brak wyników dla podanej frazy.' : 'Brak opublikowanych pinezek.'; ?></p>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            var nonce = '<?php echo wp_create_nonce('jg_admin_save_seo'); ?>';
            document.querySelectorAll('.jg-seo-save-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var row       = btn.closest('tr');
                    var id        = row.dataset.id;
                    var canonical = row.querySelector('.jg-seo-canonical-input').value.trim();
                    var noindex   = row.querySelector('.jg-seo-noindex').checked ? 1 : 0;
                    btn.disabled  = true;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({action:'jg_admin_save_seo', nonce:nonce, point_id:id, seo_canonical:canonical, seo_noindex:noindex})
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        btn.disabled = false;
                        if (res.success) {
                            var saved = row.querySelector('.jg-seo-saved');
                            saved.style.display = 'inline';
                            setTimeout(function(){ saved.style.display = 'none'; }, 2000);
                            row.querySelector('.jg-seo-canonical-input').classList.toggle('has-value', canonical !== '');
                        }
                    });
                });
            });
        })();
        </script>
        <?php
    }

}
