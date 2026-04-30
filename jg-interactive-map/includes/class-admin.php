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
require_once __DIR__ . '/admin/trait-admin-promos.php';
require_once __DIR__ . '/admin/trait-admin-gallery.php';
require_once __DIR__ . '/admin/trait-admin-activity.php';
require_once __DIR__ . '/admin/trait-admin-settings.php';
require_once __DIR__ . '/admin/trait-admin-categories.php';
require_once __DIR__ . '/admin/trait-admin-gamification.php';
require_once __DIR__ . '/admin/trait-admin-other.php';

class JG_Map_Admin {
    use JG_Map_Admin_Helpers;
    use JG_Map_Admin_Dashboard;
    use JG_Map_Admin_Moderation;
    use JG_Map_Admin_Places;
    use JG_Map_Admin_Users;
    use JG_Map_Admin_Reports;
    use JG_Map_Admin_Promos;
    use JG_Map_Admin_Gallery;
    use JG_Map_Admin_Activity;
    use JG_Map_Admin_Settings;
    use JG_Map_Admin_Categories;
    use JG_Map_Admin_Gamification;
    use JG_Map_Admin_Other;

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


}
