<?php
/**
 * Admin panel for JG Map
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Admin {

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
        add_action('admin_bar_menu', array($this, 'add_admin_bar_notifications'), 100);
        add_filter('admin_title', array($this, 'modify_admin_title'), 999, 2);

        // Real-time notifications via Heartbeat API
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_bar_script'));
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
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_content LIKE '%[jg_map%'
             AND post_status = 'publish'
             AND post_type IN ('page', 'post')
             LIMIT 1"
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

        // Debug logging (temporary - can be removed after fixing)
        if (current_user_can('manage_options')) {
            error_log('[JG MAP ADMIN BAR] Pending counts: points=' . $pending_points . ', edits=' . $pending_edits . ', reports=' . $pending_reports . ', deletions=' . $pending_deletions);
        }

        $total_pending = intval($pending_points) + intval($pending_edits) + intval($pending_reports) + intval($pending_deletions);

        if ($total_pending === 0) {
            return;
        }

        // Add parent node
        $wp_admin_bar->add_node(array(
            'id' => 'jg-map-notifications',
            'title' => '<span style="background:#dc2626;color:#fff;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:700;margin-right:4px">' . $total_pending . '</span> JG Map',
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
        if (strpos($screen->id, 'jg-map-moderation') !== false) {
            // Get pending points
            $pending_points = $wpdb->get_results(
                "SELECT title FROM $points_table WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5",
                ARRAY_A
            );
            foreach ($pending_points as $point) {
                $events[] = $point['title'] ?: 'Bez nazwy';
            }

            // Get pending edits (ONLY edits, not deletion requests)
            $pending_edits = $wpdb->get_results(
                "SELECT p.title FROM $history_table h
                 LEFT JOIN $points_table p ON h.point_id = p.id
                 WHERE h.status = 'pending' AND h.action_type = 'edit'
                 ORDER BY h.created_at DESC LIMIT 5",
                ARRAY_A
            );
            foreach ($pending_edits as $edit) {
                $events[] = 'Edycja: ' . ($edit['title'] ?: 'Bez nazwy');
            }

            $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $points_table WHERE status = 'pending'")
                         + $wpdb->get_var("SELECT COUNT(*) FROM $history_table WHERE status = 'pending' AND action_type = 'edit'");
        }
        // Reports page - show report reasons
        elseif (strpos($screen->id, 'jg-map-reports') !== false) {
            $pending_reports = $wpdb->get_results(
                "SELECT r.reason, p.title
                 FROM $reports_table r
                 INNER JOIN $points_table p ON r.point_id = p.id
                 WHERE r.status = 'pending' AND p.status = 'publish'
                 ORDER BY r.created_at DESC LIMIT 5",
                ARRAY_A
            );
            foreach ($pending_reports as $report) {
                $events[] = ($report['title'] ?: 'Bez nazwy') . ': ' . $report['reason'];
            }

            $total_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT r.point_id)
                 FROM $reports_table r
                 INNER JOIN $points_table p ON r.point_id = p.id
                 WHERE r.status = 'pending' AND p.status = 'publish'"
            );
        }
        // Deletions page - show deletion requests
        elseif (strpos($screen->id, 'jg-map-deletions') !== false) {
            $pending_deletions = $wpdb->get_results(
                "SELECT title FROM $points_table
                 WHERE is_deletion_requested = 1 AND status = 'publish'
                 ORDER BY updated_at DESC LIMIT 5",
                ARRAY_A
            );
            foreach ($pending_deletions as $deletion) {
                $events[] = $deletion['title'] ?: 'Bez nazwy';
            }

            $total_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM $points_table WHERE is_deletion_requested = 1 AND status = 'publish'"
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
        add_menu_page(
            'JG Map',
            'JG Map',
            'manage_options',
            'jg-map',
            array($this, 'render_main_page'),
            'dashicons-location-alt',
            30
        );

        add_submenu_page(
            'jg-map',
            'Miejsca',
            'Miejsca',
            'read', // Allow all logged-in users to see their own places
            'jg-map-places',
            array($this, 'render_places_page')
        );

        add_submenu_page(
            'jg-map',
            'Promocje',
            'Promocje',
            'manage_options',
            'jg-map-promos',
            array($this, 'render_promos_page')
        );

        add_submenu_page(
            'jg-map',
            'Galeria zdjęć',
            'Galeria zdjęć',
            'manage_options',
            'jg-map-gallery',
            array($this, 'render_gallery_page')
        );

        add_submenu_page(
            'jg-map',
            'Użytkownicy',
            'Użytkownicy',
            'manage_options',
            'jg-map-users',
            array($this, 'render_users_page')
        );

        add_submenu_page(
            'jg-map',
            'Konserwacja',
            'Konserwacja',
            'manage_options',
            'jg-map-maintenance',
            array($this, 'render_maintenance_page')
        );

        add_submenu_page(
            'jg-map',
            'Role użytkowników',
            'Role użytkowników',
            'manage_options',
            'jg-map-roles',
            array($this, 'render_roles_page')
        );

        add_submenu_page(
            'jg-map',
            'Activity Log',
            'Activity Log',
            'manage_options',
            'jg-map-activity-log',
            array($this, 'render_activity_log_page')
        );

        add_submenu_page(
            'jg-map',
            'Ustawienia',
            'Ustawienia',
            'manage_options',
            'jg-map-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render main page
     */
    public function render_main_page() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'publish'");
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
        $promos = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_promo = 1 AND status = 'publish'");
        $deletions = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_deletion_requested = 1");

        $reports_table = JG_Map_Database::get_reports_table();
        $reports = $wpdb->get_var("SELECT COUNT(DISTINCT r.point_id) FROM $reports_table r INNER JOIN $table p ON r.point_id = p.id WHERE r.status = 'pending' AND p.status = 'publish'");

        // Ensure history table exists
        JG_Map_Database::ensure_history_table();

        $history_table = JG_Map_Database::get_history_table();
        $edits = $wpdb->get_var("SELECT COUNT(*) FROM $history_table WHERE status = 'pending'");

        ?>
        <div class="wrap">
            <h1>JG Interactive Map - Panel Administracyjny</h1>

            <div class="jg-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin:30px 0">
                <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                    <h3 style="margin:0 0 10px">📍 Wszystkie miejsca</h3>
                    <p style="font-size:32px;font-weight:700;margin:0;color:#2271b1"><?php echo $total; ?></p>
                </div>

                <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                    <h3 style="margin:0 0 10px">⏳ Oczekujące</h3>
                    <p style="font-size:32px;font-weight:700;margin:0;color:#d63638"><?php echo $pending; ?></p>
                    <?php if ($pending > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=jg-map-moderation'); ?>" class="button">Moderuj</a>
                    <?php endif; ?>
                </div>

                <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                    <h3 style="margin:0 0 10px">✏️ Edycje do zatwierdzenia</h3>
                    <p style="font-size:32px;font-weight:700;margin:0;color:#9333ea"><?php echo $edits; ?></p>
                    <?php if ($edits > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=jg-map-moderation'); ?>" class="button">Zobacz</a>
                    <?php endif; ?>
                </div>

                <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                    <h3 style="margin:0 0 10px">🚨 Zgłoszenia</h3>
                    <p style="font-size:32px;font-weight:700;margin:0;color:#d63638"><?php echo $reports; ?></p>
                    <?php if ($reports > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=jg-map-reports'); ?>" class="button">Zobacz</a>
                    <?php endif; ?>
                </div>

                <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                    <h3 style="margin:0 0 10px">🗑️ Żądania usunięcia</h3>
                    <p style="font-size:32px;font-weight:700;margin:0;color:#dc2626"><?php echo $deletions; ?></p>
                    <?php if ($deletions > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=jg-map-deletions'); ?>" class="button">Zarządzaj</a>
                    <?php endif; ?>
                </div>

                <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                    <h3 style="margin:0 0 10px">⭐ Promocje</h3>
                    <p style="font-size:32px;font-weight:700;margin:0;color:#f59e0b"><?php echo $promos; ?></p>
                    <a href="<?php echo admin_url('admin.php?page=jg-map-promos'); ?>" class="button">Zarządzaj</a>
                </div>
            </div>

            <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);margin-top:30px">
                <h2>Jak używać pluginu?</h2>
                <p>Wstaw shortcode <code>[jg_map]</code> na dowolnej stronie lub wpisie.</p>

                <h3>Opcje shortcode:</h3>
                <ul>
                    <li><code>[jg_map]</code> - podstawowa mapa</li>
                    <li><code>[jg_map lat="50.904" lng="15.734" zoom="13"]</code> - z niestandardową lokalizacją</li>
                    <li><code>[jg_map height="600px"]</code> - z niestandardową wysokością</li>
                </ul>

                <h3>Funkcje:</h3>
                <ul>
                    <li>✅ Auto-refresh co 30 sekund - zmiany widoczne w czasie rzeczywistym</li>
                    <li>✅ Historia edycji - pełna kontrola nad zmianami</li>
                    <li>✅ System moderacji - wszystko pod kontrolą</li>
                    <li>✅ Promocje z pulsowaniem - zawsze widoczne, nigdy w clusterze</li>
                    <li>✅ Ograniczenie mapy do regionu Jeleniej Góry</li>
                    <li>✅ Upload zdjęć - maksymalnie 6 na miejsce</li>
                    <li>✅ Głosowanie (wyłączone dla promocji)</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render places page - unified moderation interface
     */
    public function render_places_page() {
        // Check if user is admin
        $is_admin = current_user_can('manage_options');

        // Handle search and filters
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // For regular users, always filter by their ID
        // For admins, respect the my_places checkbox
        $my_places_only = !$is_admin || (isset($_GET['my_places']) && $_GET['my_places'] === '1');

        // Get current user ID for filtering
        $current_user_id = $my_places_only ? get_current_user_id() : 0;

        // Get places with status
        $places = JG_Map_Database::get_all_places_with_status($search, $status_filter, $current_user_id);
        $counts = JG_Map_Database::get_places_count_by_status($current_user_id);

        // Group places by display status
        $grouped_places = array(
            'reported' => array(),
            'new_pending' => array(),
            'edit_pending' => array(),
            'deletion_pending' => array(),
            'published' => array()
        );

        foreach ($places as $place) {
            if (isset($grouped_places[$place['display_status']])) {
                $grouped_places[$place['display_status']][] = $place;
            }
        }

        ?>
        <div class="wrap">
            <h1>Zarządzanie miejscami</h1>

            <!-- Search bar -->
            <div style="background:#fff;padding:20px;margin:20px 0;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                <form method="get" action="">
                    <input type="hidden" name="page" value="jg-map-places">
                    <div style="display:flex;gap:10px;align-items:center<?php echo $is_admin ? ';margin-bottom:10px' : ''; ?>">
                        <input type="text" name="search" value="<?php echo esc_attr($search); ?>"
                               placeholder="Szukaj po nazwie, treści, adresie<?php echo $is_admin ? ' lub autorze' : ''; ?>..."
                               style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:4px">
                        <button type="submit" class="button button-primary">🔍 Szukaj</button>
                        <?php if ($search || $status_filter || ($is_admin && $my_places_only)): ?>
                            <a href="?page=jg-map-places" class="button">✕ Wyczyść</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_admin): ?>
                    <div style="display:flex;gap:15px;align-items:center">
                        <label style="display:flex;align-items:center;gap:5px;cursor:pointer">
                            <input type="checkbox" name="my_places" value="1" <?php checked($my_places_only, true); ?>>
                            <span>Tylko moje miejsca</span>
                        </label>
                    </div>
                    <?php else: ?>
                    <p style="margin:10px 0 0 0;color:#666;font-size:13px">
                        ℹ️ Widzisz tylko swoje miejsca
                    </p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Statistics -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0">
                <div style="background:#dc2626;color:#fff;padding:20px;border-radius:8px;text-align:center">
                    <div style="font-size:32px;font-weight:bold"><?php echo $counts['reported']; ?></div>
                    <div>🚨 Zgłoszone</div>
                </div>
                <div style="background:#f59e0b;color:#fff;padding:20px;border-radius:8px;text-align:center">
                    <div style="font-size:32px;font-weight:bold"><?php echo $counts['new_pending']; ?></div>
                    <div>⏳ Nowe czekające</div>
                </div>
                <div style="background:#3b82f6;color:#fff;padding:20px;border-radius:8px;text-align:center">
                    <div style="font-size:32px;font-weight:bold"><?php echo $counts['edit_pending']; ?></div>
                    <div>✏️ Edycje czekające</div>
                </div>
                <div style="background:#8b5cf6;color:#fff;padding:20px;border-radius:8px;text-align:center">
                    <div style="font-size:32px;font-weight:bold"><?php echo $counts['deletion_pending']; ?></div>
                    <div>🗑️ Do usunięcia</div>
                </div>
                <div style="background:#10b981;color:#fff;padding:20px;border-radius:8px;text-align:center">
                    <div style="font-size:32px;font-weight:bold"><?php echo $counts['published']; ?></div>
                    <div>✅ Opublikowane</div>
                </div>
            </div>

            <?php
            // Define sections with their configurations
            $sections = array(
                'reported' => array(
                    'title' => '🚨 Zgłoszone do sprawdzenia przez moderację',
                    'color' => '#dc2626',
                    'actions' => array('details', 'delete', 'keep')
                ),
                'new_pending' => array(
                    'title' => '⏳ Nowe miejsce czekające na zatwierdzenie',
                    'color' => '#f59e0b',
                    'actions' => array('details', 'approve', 'reject')
                ),
                'edit_pending' => array(
                    'title' => '✏️ Oczekuje na zatwierdzenie edycji',
                    'color' => '#3b82f6',
                    'actions' => array('details', 'approve_edit', 'reject_edit')
                ),
                'deletion_pending' => array(
                    'title' => '🗑️ Oczekuje na usunięcie',
                    'color' => '#8b5cf6',
                    'actions' => array('details', 'delete', 'keep_deletion')
                ),
                'published' => array(
                    'title' => '✅ Opublikowane',
                    'color' => '#10b981',
                    'actions' => array('details', 'edit', 'delete_basic')
                )
            );

            // Render each section
            foreach ($sections as $status => $config) {
                $section_places = $grouped_places[$status];
                $section_count = count($section_places);

                if ($section_count > 0 || !$search) { // Show section if has places or no search active
                    ?>
                    <div id="section-<?php echo esc_attr($status); ?>" style="margin:30px 0">
                        <h2 style="color:<?php echo $config['color']; ?>">
                            <?php echo $config['title']; ?>
                            <span style="background:<?php echo $config['color']; ?>;color:#fff;padding:4px 12px;border-radius:12px;font-size:14px">
                                <?php echo $section_count; ?>
                            </span>
                        </h2>

                        <?php if ($section_count > 0): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width:20%">Miejsce</th>
                                        <th style="width:12%">Kto dodał</th>
                                        <th style="width:12%">Data dodania</th>
                                        <th style="width:12%">Data zatwierdzenia</th>
                                        <th style="width:10%">Status</th>
                                        <th style="width:8%">Sponsorowane</th>
                                        <th style="width:26%">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($section_places as $place):
                                        $this->render_place_row($place, $config['actions'], $is_admin);
                                    endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color:#666;font-style:italic">Brak miejsc w tej kategorii</p>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            }
            ?>

            <?php if (empty($places) && $search): ?>
                <div style="text-align:center;padding:40px;background:#fff;border-radius:8px">
                    <h3>Nie znaleziono miejsc</h3>
                    <p>Spróbuj zmienić kryteria wyszukiwania</p>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .jg-action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .jg-action-buttons .button {
            font-size: 12px;
            padding: 4px 8px;
            height: auto;
            line-height: 1.4;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Approve new point
            $('.jg-approve-point').on('click', function() {
                if (!confirm('Czy na pewno chcesz zaakceptować to miejsce?')) return;

                var pointId = $(this).data('point-id');
                var $button = $(this);

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_approve_point',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        post_id: pointId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Miejsce zostało zaakceptowane!');
                            location.reload();
                        } else {
                            alert('Błąd: ' + (response.data?.message || 'Nieznany błąd'));
                        }
                    },
                    error: function() {
                        alert('Błąd połączenia z serwerem');
                    }
                });
            });

            // Reject new point
            $('.jg-reject-point').on('click', function() {
                if (!confirm('Czy na pewno chcesz odrzucić to miejsce?')) return;

                var pointId = $(this).data('point-id');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_reject_point',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        post_id: pointId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Miejsce zostało odrzucone!');
                            location.reload();
                        } else {
                            alert('Błąd: ' + (response.data?.message || 'Nieznany błąd'));
                        }
                    },
                    error: function() {
                        alert('Błąd połączenia z serwerem');
                    }
                });
            });

            // Approve edit
            $('.jg-approve-edit').on('click', function() {
                if (!confirm('Czy na pewno chcesz zaakceptować tę edycję?')) return;

                var historyId = $(this).data('history-id');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_approve_edit',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        history_id: historyId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Edycja została zaakceptowana!');
                            location.reload();
                        } else {
                            alert('Błąd: ' + (response.data?.message || 'Nieznany błąd'));
                        }
                    },
                    error: function() {
                        alert('Błąd połączenia z serwerem');
                    }
                });
            });

            // Reject edit
            $('.jg-reject-edit').on('click', function() {
                if (!confirm('Czy na pewno chcesz odrzucić tę edycję?')) return;

                var historyId = $(this).data('history-id');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_reject_edit',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        history_id: historyId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Edycja została odrzucona!');
                            location.reload();
                        } else {
                            alert('Błąd: ' + (response.data?.message || 'Nieznany błąd'));
                        }
                    },
                    error: function() {
                        alert('Błąd połączenia z serwerem');
                    }
                });
            });

            // Keep reported place (resolve all reports as "kept")
            $('.jg-keep-reported').on('click', function() {
                if (!confirm('Czy na pewno chcesz pozostawić to miejsce (odrzucić zgłoszenia)?')) return;

                var pointId = $(this).data('point-id');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_keep_reported_place',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        point_id: pointId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Miejsce zostało pozostawione, zgłoszenia odrzucone!');
                            location.reload();
                        } else {
                            alert('Błąd: ' + (response.data?.message || 'Nieznany błąd'));
                        }
                    },
                    error: function() {
                        alert('Błąd połączenia z serwerem');
                    }
                });
            });

            // Reject deletion request
            $('.jg-reject-deletion').on('click', function() {
                if (!confirm('Czy na pewno chcesz odrzucić żądanie usunięcia?')) return;

                var pointId = $(this).data('point-id');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_reject_deletion',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        point_id: pointId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Żądanie usunięcia zostało odrzucone!');
                            location.reload();
                        } else {
                            alert('Błąd: ' + (response.data?.message || 'Nieznany błąd'));
                        }
                    },
                    error: function() {
                        alert('Błąd połączenia z serwerem');
                    }
                });
            });

            // Delete point (basic)
            $('.jg-delete-point').on('click', function() {
                if (!confirm('Czy na pewno chcesz PERMANENTNIE usunąć to miejsce? Tej operacji nie można cofnąć!')) return;

                var pointId = $(this).data('point-id');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_delete_point',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        post_id: pointId  // Changed from point_id to post_id
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Miejsce zostało usunięte!');
                            location.reload();
                        } else {
                            alert('Błąd: ' + (response.data?.message || 'Nieznany błąd'));
                        }
                    },
                    error: function() {
                        alert('Błąd połączenia z serwerem');
                    }
                });
            });

            // Scroll to section if URL has hash
            if (window.location.hash) {
                var hash = window.location.hash;
                if ($(hash).length) {
                    $('html, body').animate({
                        scrollTop: $(hash).offset().top - 100
                    }, 500);
                }
            }
        });
        </script>
        <?php
    }

    /**
     * Render a single place row in the table
     */
    private function render_place_row($place, $actions, $is_admin) {
        $author_name = !empty($place['author_name']) ? $place['author_name'] : 'Nieznany';
        $created_date = date('Y-m-d H:i', strtotime($place['created_at']));
        $approved_date = !empty($place['approved_at']) ? date('Y-m-d H:i', strtotime($place['approved_at'])) : '-';
        $is_sponsored = $place['is_promo'] == 1 ? '⭐ Tak' : 'Nie';

        // For regular users, only show 'details' action
        if (!$is_admin) {
            $actions = array('details');
        }

        ?>
        <tr>
            <td><strong><?php echo esc_html($place['title']); ?></strong></td>
            <td><?php echo esc_html($author_name); ?></td>
            <td><?php echo esc_html($created_date); ?></td>
            <td><?php echo esc_html($approved_date); ?></td>
            <td><span style="font-size:11px;padding:3px 6px;background:#f3f4f6;border-radius:3px">
                <?php echo esc_html($place['display_status_label']); ?>
            </span></td>
            <td><?php echo $is_sponsored; ?></td>
            <td>
                <div class="jg-action-buttons">
                    <?php echo $this->render_place_actions($place, $actions); ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Render action buttons for a place based on its status
     */
    private function render_place_actions($place, $allowed_actions) {
        $buttons = '';
        $point_id = $place['id'];
        $map_url = $this->get_map_page_url();

        foreach ($allowed_actions as $action) {
            switch ($action) {
                case 'details':
                    // Link that zooms to place on map and opens modal
                    $details_url = add_query_arg('jg_view_point', $point_id, $map_url);
                    $buttons .= sprintf(
                        '<a href="%s" class="button" target="_blank">🔍 Szczegóły</a>',
                        esc_url($details_url)
                    );
                    break;

                case 'approve':
                    $buttons .= sprintf(
                        '<button class="button button-primary jg-approve-point" data-point-id="%d">✓ Zaakceptuj</button>',
                        $point_id
                    );
                    break;

                case 'reject':
                    $buttons .= sprintf(
                        '<button class="button jg-reject-point" data-point-id="%d">✗ Odrzuć</button>',
                        $point_id
                    );
                    break;

                case 'approve_edit':
                    // Get pending edit history ID
                    $history = JG_Map_Database::get_pending_history($point_id);
                    if ($history) {
                        $buttons .= sprintf(
                            '<button class="button button-primary jg-approve-edit" data-history-id="%d">✓ Zaakceptuj</button>',
                            $history['id']
                        );
                    }
                    break;

                case 'reject_edit':
                    $history = JG_Map_Database::get_pending_history($point_id);
                    if ($history) {
                        $buttons .= sprintf(
                            '<button class="button jg-reject-edit" data-history-id="%d">✗ Odrzuć</button>',
                            $history['id']
                        );
                    }
                    break;

                case 'delete':
                    // For reported places - handle reports
                    $reports_url = add_query_arg('jg_view_reports', $point_id, $map_url);
                    $buttons .= sprintf(
                        '<a href="%s" class="button" target="_blank">🗑️ Usuń</a>',
                        esc_url($reports_url)
                    );
                    break;

                case 'keep':
                    // For reported places - keep the place
                    $buttons .= sprintf(
                        '<button class="button jg-keep-reported" data-point-id="%d">✓ Pozostaw</button>',
                        $point_id
                    );
                    break;

                case 'keep_deletion':
                    // For deletion requests - reject deletion
                    $buttons .= sprintf(
                        '<button class="button jg-reject-deletion" data-point-id="%d">✓ Pozostaw</button>',
                        $point_id
                    );
                    break;

                case 'edit':
                    $edit_url = add_query_arg(array(
                        'jg_edit' => $point_id
                    ), $map_url);
                    $buttons .= sprintf(
                        '<a href="%s" class="button" target="_blank">✏️ Edytuj</a>',
                        esc_url($edit_url)
                    );
                    break;

                case 'delete_basic':
                    $buttons .= sprintf(
                        '<button class="button jg-delete-point" data-point-id="%d">🗑️ Usuń</button>',
                        $point_id
                    );
                    break;
            }
        }

        return $buttons;
    }

    /**
     * Render moderation page
     */
    public function render_moderation_page() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();
        $history_table = JG_Map_Database::get_history_table();
        $reports_table = JG_Map_Database::get_reports_table();

        // Ensure history table exists
        JG_Map_Database::ensure_history_table();

        // Get pending points with priority calculation
        $pending = $wpdb->get_results(
            "SELECT p.*,
            COUNT(r.id) as report_count,
            TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_old
            FROM $table p
            LEFT JOIN $reports_table r ON p.id = r.point_id AND r.status = 'pending'
            WHERE p.status = 'pending'
            GROUP BY p.id
            ORDER BY report_count DESC, hours_old DESC",
            ARRAY_A
        );

        // Get edits with priority calculation (based on how old they are and number of reports on the point)
        // ONLY get edits, not deletion requests (filter by action_type)
        $edits = $wpdb->get_results(
            "SELECT h.*, p.title as point_title,
            COUNT(r.id) as report_count,
            TIMESTAMPDIFF(HOUR, h.created_at, NOW()) as hours_old
            FROM $history_table h
            LEFT JOIN $table p ON h.point_id = p.id
            LEFT JOIN $reports_table r ON h.point_id = r.point_id AND r.status = 'pending'
            WHERE h.status = 'pending' AND h.action_type = 'edit'
            GROUP BY h.id
            ORDER BY report_count DESC, hours_old DESC",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <h1>Dodane miejsca</h1>

            <?php if (!empty($edits)): ?>
            <h2>Edycje do zatwierdzenia (<?php echo count($edits); ?>)</h2>

            <!-- Bulk actions -->
            <div style="margin-bottom:10px;display:flex;gap:10px;align-items:center;">
                <select id="bulk-action-edits" style="padding:5px;">
                    <option value="">Akcje zbiorcze</option>
                    <option value="approve">Zatwierdź zaznaczone</option>
                    <option value="reject">Odrzuć zaznaczone</option>
                </select>
                <button id="apply-bulk-action-edits" class="button">Zastosuj</button>
                <span id="bulk-selected-count-edits" style="margin-left:10px;color:#666;"></span>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px"><input type="checkbox" id="select-all-edits" /></th>
                        <th>Miejsce</th>
                        <th>Użytkownik</th>
                        <th>Zmiany</th>
                        <th>Data</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($edits as $edit):
                        $user = get_userdata($edit['user_id']);
                        $old_values = json_decode($edit['old_values'], true);
                        $new_values = json_decode($edit['new_values'], true);

                        $changes = array();
                        if ($old_values['title'] !== $new_values['title']) {
                            $changes[] = 'Tytuł';
                        }
                        if ($old_values['type'] !== $new_values['type']) {
                            $changes[] = 'Typ';
                        }
                        if ($old_values['content'] !== $new_values['content']) {
                            $changes[] = 'Opis';
                        }
                        if (isset($old_values['website']) && isset($new_values['website']) && $old_values['website'] !== $new_values['website']) {
                            $changes[] = 'Strona internetowa';
                        }
                        if (isset($old_values['phone']) && isset($new_values['phone']) && $old_values['phone'] !== $new_values['phone']) {
                            $changes[] = 'Telefon';
                        }
                        if (isset($old_values['cta_enabled']) && isset($new_values['cta_enabled']) && $old_values['cta_enabled'] !== $new_values['cta_enabled']) {
                            $changes[] = 'CTA włączone/wyłączone';
                        }
                        if (isset($old_values['cta_type']) && isset($new_values['cta_type']) && $old_values['cta_type'] !== $new_values['cta_type']) {
                            $changes[] = 'Typ CTA';
                        }
                        if (isset($old_values['facebook_url']) && isset($new_values['facebook_url']) && $old_values['facebook_url'] !== $new_values['facebook_url']) {
                            $changes[] = 'Facebook';
                        }
                        if (isset($old_values['instagram_url']) && isset($new_values['instagram_url']) && $old_values['instagram_url'] !== $new_values['instagram_url']) {
                            $changes[] = 'Instagram';
                        }
                        if (isset($old_values['linkedin_url']) && isset($new_values['linkedin_url']) && $old_values['linkedin_url'] !== $new_values['linkedin_url']) {
                            $changes[] = 'LinkedIn';
                        }
                        if (isset($old_values['tiktok_url']) && isset($new_values['tiktok_url']) && $old_values['tiktok_url'] !== $new_values['tiktok_url']) {
                            $changes[] = 'TikTok';
                        }

                        // Calculate priority badge
                        $report_count = intval($edit['report_count']);
                        $hours_old = intval($edit['hours_old']);
                        $priority = '';
                        $priority_style = '';

                        if ($report_count > 0) {
                            $priority = '🔴 PILNE';
                            $priority_style = 'background:#dc2626;color:#fff;padding:4px 8px;border-radius:4px;font-weight:700;margin-left:8px';
                        } elseif ($hours_old > 48) {
                            $priority = '⚠️ Stare';
                            $priority_style = 'background:#f59e0b;color:#fff;padding:4px 8px;border-radius:4px;font-weight:700;margin-left:8px';
                        }
                        ?>
                        <tr>
                            <td><input type="checkbox" class="edit-checkbox" value="<?php echo $edit['id']; ?>" /></td>
                            <td>
                                <strong><?php echo esc_html($edit['point_title']); ?></strong>
                                <?php if ($priority): ?>
                                    <span style="<?php echo $priority_style; ?>"><?php echo $priority; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user ? esc_html($user->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo implode(', ', $changes); ?></td>
                            <td><?php echo human_time_diff(strtotime($edit['created_at'] . ' UTC'), time()); ?> temu</td>
                            <td>
                                <button class="button jg-view-edit-details" data-edit='<?php echo esc_attr(json_encode($edit)); ?>'>Szczegóły</button>
                                <button class="button button-primary jg-approve-edit" data-id="<?php echo $edit['id']; ?>">Zatwierdź</button>
                                <button class="button jg-reject-edit" data-id="<?php echo $edit['id']; ?>">Odrzuć</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal for edit details -->
            <div id="jg-edit-details-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;padding:20px;border-radius:8px;max-width:900px;width:90%;max-height:80vh;overflow:auto;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h2 id="jg-edit-modal-title" style="margin:0">Szczegóły edycji</h2>
                        <button id="jg-edit-modal-close" style="background:#dc2626;color:#fff;border:none;border-radius:4px;padding:8px 16px;cursor:pointer;font-weight:700;">✕ Zamknij</button>
                    </div>
                    <div id="jg-edit-modal-content"></div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                var modal = $('#jg-edit-details-modal');
                var modalContent = $('#jg-edit-modal-content');
                var modalTitle = $('#jg-edit-modal-title');

                // View edit details
                $('.jg-view-edit-details').on('click', function() {
                    var edit = $(this).data('edit');
                    var old_values = JSON.parse(edit.old_values);
                    var new_values = JSON.parse(edit.new_values);

                    modalTitle.text('Szczegóły edycji: ' + edit.point_title);

                    var html = '<table style="width:100%;border-collapse:collapse">';
                    html += '<tr><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f5f5f5">Pole</th><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f5f5f5">Poprzednia wartość</th><th style="text-align:left;padding:8px;border:1px solid #ddd;background:#f5f5f5">Nowa wartość</th></tr>';

                    if (old_values.title !== new_values.title) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Tytuł</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + old_values.title + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + new_values.title + '</td></tr>';
                    }
                    if (old_values.type !== new_values.type) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Typ</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + old_values.type + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + new_values.type + '</td></tr>';
                    }
                    if (old_values.category !== undefined && new_values.category !== undefined && old_values.category !== new_values.category) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Kategoria</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.category || '(brak)') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.category || '(brak)') + '</td></tr>';
                    }
                    if (old_values.content !== new_values.content) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Opis</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee;max-width:300px;word-wrap:break-word">' + old_values.content + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5;max-width:300px;word-wrap:break-word">' + new_values.content + '</td></tr>';
                    }
                    if (old_values.website !== undefined && new_values.website !== undefined && old_values.website !== new_values.website) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Strona internetowa</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.website || '(brak)') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.website || '(brak)') + '</td></tr>';
                    }
                    if (old_values.phone !== undefined && new_values.phone !== undefined && old_values.phone !== new_values.phone) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Telefon</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.phone || '(brak)') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.phone || '(brak)') + '</td></tr>';
                    }
                    if (old_values.cta_enabled !== undefined && new_values.cta_enabled !== undefined && old_values.cta_enabled !== new_values.cta_enabled) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>CTA włączone</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.cta_enabled ? 'Tak' : 'Nie') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.cta_enabled ? 'Tak' : 'Nie') + '</td></tr>';
                    }
                    if (old_values.cta_type !== undefined && new_values.cta_type !== undefined && old_values.cta_type !== new_values.cta_type) {
                        var ctaTypeLabels = {
                            'call': 'Zadzwoń teraz',
                            'website': 'Wejdź na stronę',
                            'facebook': 'Odwiedź nas na Facebooku',
                            'instagram': 'Sprawdź nas na Instagramie',
                            'linkedin': 'Zobacz nas na LinkedIn',
                            'tiktok': 'Obserwuj nas na TikToku'
                        };
                        var ctaTypeOld = ctaTypeLabels[old_values.cta_type] || '(brak)';
                        var ctaTypeNew = ctaTypeLabels[new_values.cta_type] || '(brak)';
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Typ CTA</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + ctaTypeOld + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + ctaTypeNew + '</td></tr>';
                    }
                    if (old_values.facebook_url !== undefined && new_values.facebook_url !== undefined && old_values.facebook_url !== new_values.facebook_url) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Facebook</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.facebook_url || '(brak)') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.facebook_url || '(brak)') + '</td></tr>';
                    }
                    if (old_values.instagram_url !== undefined && new_values.instagram_url !== undefined && old_values.instagram_url !== new_values.instagram_url) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Instagram</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.instagram_url || '(brak)') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.instagram_url || '(brak)') + '</td></tr>';
                    }
                    if (old_values.linkedin_url !== undefined && new_values.linkedin_url !== undefined && old_values.linkedin_url !== new_values.linkedin_url) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>LinkedIn</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.linkedin_url || '(brak)') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.linkedin_url || '(brak)') + '</td></tr>';
                    }
                    if (old_values.tiktok_url !== undefined && new_values.tiktok_url !== undefined && old_values.tiktok_url !== new_values.tiktok_url) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>TikTok</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.tiktok_url || '(brak)') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.tiktok_url || '(brak)') + '</td></tr>';
                    }
                    html += '</table>';

                    modalContent.html(html);
                    modal.css('display', 'flex');
                });

                $('#jg-edit-modal-close, #jg-edit-details-modal').on('click', function(e) {
                    if (e.target === this) {
                        modal.hide();
                    }
                });

                // Approve edit
                $('.jg-approve-edit').on('click', function() {
                    if (!confirm('Zatwierdzić tę edycję?')) return;

                    var btn = $(this);
                    var editId = btn.data('id');
                    btn.prop('disabled', true).text('Zatwierdzam...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_approve_edit',
                            history_id: editId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Edycja zatwierdzona!');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nieznany błąd'));
                                btn.prop('disabled', false).text('Zatwierdź');
                            }
                        },
                        error: function() {
                            alert('Błąd połączenia');
                            btn.prop('disabled', false).text('Zatwierdź');
                        }
                    });
                });

                // Reject edit
                $('.jg-reject-edit').on('click', function() {
                    var reason = prompt('Powód odrzucenia (zostanie wysłany do użytkownika):');
                    if (reason === null) return;

                    var btn = $(this);
                    var editId = btn.data('id');
                    btn.prop('disabled', true).text('Odrzucam...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_reject_edit',
                            history_id: editId,
                            reason: reason,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Edycja odrzucona!');
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

                // Bulk actions for edits
                var updateSelectedCount = function() {
                    var count = $('.edit-checkbox:checked').length;
                    $('#bulk-selected-count-edits').text(count > 0 ? '(' + count + ' zaznaczonych)' : '');
                };

                // Select all checkboxes
                $('#select-all-edits').on('change', function() {
                    $('.edit-checkbox').prop('checked', $(this).is(':checked'));
                    updateSelectedCount();
                });

                // Update count when individual checkbox changes
                $('.edit-checkbox').on('change', function() {
                    updateSelectedCount();
                    // Update "select all" checkbox state
                    var total = $('.edit-checkbox').length;
                    var checked = $('.edit-checkbox:checked').length;
                    $('#select-all-edits').prop('checked', total > 0 && total === checked);
                });

                // Apply bulk action
                $('#apply-bulk-action-edits').on('click', function() {
                    var action = $('#bulk-action-edits').val();
                    if (!action) {
                        alert('Wybierz akcję');
                        return;
                    }

                    var selectedIds = $('.edit-checkbox:checked').map(function() {
                        return $(this).val();
                    }).get();

                    if (selectedIds.length === 0) {
                        alert('Zaznacz przynajmniej jeden element');
                        return;
                    }

                    var confirmMsg = action === 'approve'
                        ? 'Czy na pewno chcesz zatwierdzić ' + selectedIds.length + ' edycji?'
                        : 'Czy na pewno chcesz odrzucić ' + selectedIds.length + ' edycji?';

                    if (!confirm(confirmMsg)) return;

                    var reason = '';
                    if (action === 'reject') {
                        reason = prompt('Powód odrzucenia (zostanie wysłany do użytkownika):');
                        if (reason === null) return;
                    }

                    var btn = $(this);
                    btn.prop('disabled', true).text('Przetwarzam...');

                    var processNext = function(index) {
                        if (index >= selectedIds.length) {
                            alert('Zakończono przetwarzanie!');
                            location.reload();
                            return;
                        }

                        var editId = selectedIds[index];
                        var ajaxAction = action === 'approve' ? 'jg_admin_approve_edit' : 'jg_admin_reject_edit';
                        var data = {
                            action: ajaxAction,
                            history_id: editId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        };

                        if (action === 'reject') {
                            data.reason = reason;
                        }

                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: data,
                            success: function(response) {
                                if (response.success) {
                                    processNext(index + 1);
                                } else {
                                    alert('Błąd przy przetwarzaniu ID ' + editId + ': ' + (response.data?.message || 'Nieznany błąd'));
                                    btn.prop('disabled', false).text('Zastosuj');
                                }
                            },
                            error: function() {
                                alert('Błąd połączenia przy przetwarzaniu ID ' + editId);
                                btn.prop('disabled', false).text('Zastosuj');
                            }
                        });
                    };

                    processNext(0);
                });
            });
            </script>
            <?php endif; ?>

            <?php if (!empty($pending)): ?>
            <h2 style="margin-top:40px">Nowe miejsca do zatwierdzenia (<?php echo count($pending); ?>)</h2>

            <!-- Bulk actions -->
            <div style="margin-bottom:10px;display:flex;gap:10px;align-items:center;">
                <select id="bulk-action-pending" style="padding:5px;">
                    <option value="">Akcje zbiorcze</option>
                    <option value="approve">Zatwierdź zaznaczone</option>
                    <option value="reject">Odrzuć zaznaczone</option>
                </select>
                <button id="apply-bulk-action-pending" class="button">Zastosuj</button>
                <span id="bulk-selected-count-pending" style="margin-left:10px;color:#666;"></span>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px"><input type="checkbox" id="select-all-pending" /></th>
                        <th>Tytuł</th>
                        <th>Typ</th>
                        <th>Autor</th>
                        <th>Data</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $point):
                        $author = get_userdata($point['author_id']);

                        // Calculate priority badge
                        $report_count = intval($point['report_count']);
                        $hours_old = intval($point['hours_old']);
                        $priority = '';
                        $priority_style = '';

                        if ($report_count > 0) {
                            $priority = '🔴 PILNE';
                            $priority_style = 'background:#dc2626;color:#fff;padding:4px 8px;border-radius:4px;font-weight:700;margin-left:8px';
                        } elseif ($hours_old > 48) {
                            $priority = '⚠️ Stare';
                            $priority_style = 'background:#f59e0b;color:#fff;padding:4px 8px;border-radius:4px;font-weight:700;margin-left:8px';
                        }
                        ?>
                        <tr>
                            <td><input type="checkbox" class="pending-checkbox" value="<?php echo $point['id']; ?>" /></td>
                            <td>
                                <strong><?php echo esc_html($point['title']); ?></strong>
                                <?php if ($priority): ?>
                                    <span style="<?php echo $priority_style; ?>"><?php echo $priority; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($point['type']); ?></td>
                            <td><?php echo $author ? esc_html($author->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo human_time_diff(strtotime($point['created_at'] . ' UTC'), time()); ?> temu</td>
                            <td>
                                <button class="button jg-view-pending-details" data-point='<?php echo esc_attr(json_encode($point)); ?>'>Zobacz szczegóły</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal for pending point details -->
            <div id="jg-pending-details-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;padding:20px;border-radius:8px;max-width:800px;width:90%;max-height:80vh;overflow:auto;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h2 id="jg-pending-modal-title" style="margin:0">Szczegóły miejsca</h2>
                        <button id="jg-pending-modal-close" style="background:#dc2626;color:#fff;border:none;border-radius:4px;padding:8px 16px;cursor:pointer;font-weight:700;">✕ Zamknij</button>
                    </div>
                    <div id="jg-pending-modal-content"></div>
                    <div style="margin-top:20px;padding-top:20px;border-top:2px solid #e5e7eb;display:flex;gap:12px;justify-content:flex-end;">
                        <button class="button button-large jg-reject-point" id="jg-pending-reject" style="background:#dc2626;color:#fff;border-color:#dc2626">Odrzuć</button>
                        <button class="button button-primary button-large jg-approve-point" id="jg-pending-approve">Zatwierdź</button>
                    </div>
                    <div id="jg-pending-msg" style="margin-top:12px;padding:12px;border-radius:8px;display:none;"></div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                var modal = $('#jg-pending-details-modal');
                var modalContent = $('#jg-pending-modal-content');
                var modalTitle = $('#jg-pending-modal-title');
                var currentPointId = null;

                // View pending point details
                $('.jg-view-pending-details').on('click', function() {
                    var point = $(this).data('point');
                    currentPointId = point.id;

                    modalTitle.text('Szczegóły: ' + point.title);

                    // Parse images
                    var images = [];
                    if (point.images) {
                        try {
                            images = JSON.parse(point.images);
                        } catch (e) {}
                    }

                    var imagesHtml = '';
                    if (images.length > 0) {
                        imagesHtml = '<div style="margin:16px 0"><strong>Zdjęcia:</strong><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;margin-top:8px">';
                        images.forEach(function(img) {
                            var thumbUrl = typeof img === 'object' ? (img.thumb || img.full) : img;
                            var fullUrl = typeof img === 'object' ? (img.full || img.thumb) : img;
                            imagesHtml += '<a href="' + fullUrl + '" target="_blank"><img src="' + thumbUrl + '" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;border:2px solid #e5e7eb"></a>';
                        });
                        imagesHtml += '</div></div>';
                    }

                    var html = '<div style="display:grid;gap:12px">' +
                        '<div><strong>Typ:</strong> ' + point.type + '</div>' +
                        '<div><strong>Lokalizacja:</strong> ' + point.lat + ', ' + point.lng + '</div>' +
                        '<div><strong>Opis:</strong><div style="margin-top:8px;padding:12px;background:#f9fafb;border-radius:8px;white-space:pre-wrap">' + (point.content || point.excerpt || '<em>Brak opisu</em>') + '</div></div>' +
                        imagesHtml +
                        '<div><strong>IP:</strong> ' + (point.ip_address || '<em>brak</em>') + '</div>' +
                        '</div>';

                    modalContent.html(html);
                    modal.css('display', 'flex');
                });

                $('#jg-pending-modal-close, #jg-pending-details-modal').on('click', function(e) {
                    if (e.target === this) {
                        modal.hide();
                    }
                });

                // Approve point
                $('#jg-pending-approve').on('click', function() {
                    if (!confirm('Zatwierdzić to miejsce?')) return;

                    var btn = $(this);
                    btn.prop('disabled', true).text('Zatwierdzam...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_approve_point',
                            post_id: currentPointId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Miejsce zatwierdzone!');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nieznany błąd'));
                                btn.prop('disabled', false).text('Zatwierdź');
                            }
                        },
                        error: function() {
                            alert('Błąd połączenia');
                            btn.prop('disabled', false).text('Zatwierdź');
                        }
                    });
                });

                // Reject point
                $('#jg-pending-reject').on('click', function() {
                    var reason = prompt('Powód odrzucenia (zostanie wysłany do użytkownika):');
                    if (reason === null) return;

                    var btn = $(this);
                    btn.prop('disabled', true).text('Odrzucam...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_reject_point',
                            post_id: currentPointId,
                            reason: reason,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Miejsce odrzucone!');
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

                // Bulk actions for pending places
                var updateSelectedCountPending = function() {
                    var count = $('.pending-checkbox:checked').length;
                    $('#bulk-selected-count-pending').text(count > 0 ? '(' + count + ' zaznaczonych)' : '');
                };

                // Select all checkboxes
                $('#select-all-pending').on('change', function() {
                    $('.pending-checkbox').prop('checked', $(this).is(':checked'));
                    updateSelectedCountPending();
                });

                // Update count when individual checkbox changes
                $('.pending-checkbox').on('change', function() {
                    updateSelectedCountPending();
                    var total = $('.pending-checkbox').length;
                    var checked = $('.pending-checkbox:checked').length;
                    $('#select-all-pending').prop('checked', total > 0 && total === checked);
                });

                // Apply bulk action
                $('#apply-bulk-action-pending').on('click', function() {
                    var action = $('#bulk-action-pending').val();
                    if (!action) {
                        alert('Wybierz akcję');
                        return;
                    }

                    var selectedIds = $('.pending-checkbox:checked').map(function() {
                        return $(this).val();
                    }).get();

                    if (selectedIds.length === 0) {
                        alert('Zaznacz przynajmniej jeden element');
                        return;
                    }

                    var confirmMsg = action === 'approve'
                        ? 'Czy na pewno chcesz zatwierdzić ' + selectedIds.length + ' miejsc?'
                        : 'Czy na pewno chcesz odrzucić ' + selectedIds.length + ' miejsc?';

                    if (!confirm(confirmMsg)) return;

                    var reason = '';
                    if (action === 'reject') {
                        reason = prompt('Powód odrzucenia (zostanie wysłany do użytkownika):');
                        if (reason === null) return;
                    }

                    var btn = $(this);
                    btn.prop('disabled', true).text('Przetwarzam...');

                    var processNext = function(index) {
                        if (index >= selectedIds.length) {
                            alert('Zakończono przetwarzanie!');
                            location.reload();
                            return;
                        }

                        var pointId = selectedIds[index];
                        var ajaxAction = action === 'approve' ? 'jg_admin_approve_point' : 'jg_admin_reject_point';
                        var data = {
                            action: ajaxAction,
                            post_id: pointId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        };

                        if (action === 'reject') {
                            data.reason = reason;
                        }

                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: data,
                            success: function(response) {
                                if (response.success) {
                                    processNext(index + 1);
                                } else {
                                    alert('Błąd przy przetwarzaniu ID ' + pointId + ': ' + (response.data?.message || 'Nieznany błąd'));
                                    btn.prop('disabled', false).text('Zastosuj');
                                }
                            },
                            error: function() {
                                alert('Błąd połączenia przy przetwarzaniu ID ' + pointId);
                                btn.prop('disabled', false).text('Zastosuj');
                            }
                        });
                    };

                    processNext(0);
                });
            });
            </script>
            <?php endif; ?>

            <?php if (empty($pending) && empty($edits)): ?>
            <p>Brak miejsc do moderacji! 🎉</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render reports page
     */
    public function render_reports_page() {
        global $wpdb;
        $reports_table = JG_Map_Database::get_reports_table();
        $points_table = JG_Map_Database::get_points_table();

        $reports = $wpdb->get_results(
            "SELECT r.*, p.title as point_title, p.status as point_status, COUNT(r2.id) as report_count
            FROM $reports_table r
            LEFT JOIN $points_table p ON r.point_id = p.id
            LEFT JOIN $reports_table r2 ON r.point_id = r2.point_id AND r2.status = 'pending'
            WHERE r.status = 'pending' AND p.status = 'publish'
            GROUP BY r.point_id
            ORDER BY report_count DESC, r.created_at DESC",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <h1>Zgłoszenia miejsc</h1>

            <?php if (!empty($reports)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Miejsce</th>
                        <th>Liczba zgłoszeń</th>
                        <th>Ostatnie zgłoszenie</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><strong><?php echo esc_html($report['point_title']); ?></strong></td>
                            <td><span style="background:#dc2626;color:#fff;padding:4px 8px;border-radius:4px"><?php echo $report['report_count']; ?></span></td>
                            <td><?php echo human_time_diff(strtotime($report['created_at'] . ' UTC'), time()); ?> temu</td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('jg_view_reports', $report['point_id'], $this->get_map_page_url())); ?>" class="button">Zobacz szczegóły</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>Brak zgłoszeń! 🎉</p>
            <?php endif; ?>
        </div>
        <?php
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
            "SELECT * FROM $table WHERE is_promo = 1 AND status = 'publish' ORDER BY created_at DESC",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <h1>Zarządzanie promocjami</h1>

            <div style="background:#fff7e6;border:2px solid #f59e0b;padding:15px;border-radius:8px;margin:20px 0">
                <h3 style="margin-top:0">ℹ️ O promocjach:</h3>
                <ul>
                    <li>Miejsca z promocją mają większy, złoty pin z pulsowaniem</li>
                    <li>Nigdy nie są grupowane w klaster - zawsze widoczne</li>
                    <li>Zawsze na szczycie (z-index 10000)</li>
                    <li>Brak możliwości głosowania</li>
                    <li>Można ustawić datę wygaśnięcia promocji</li>
                </ul>
            </div>

            <?php if (!empty($promos)): ?>
            <table class="wp-list-table widefat fixed striped">
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
                            <td><strong><?php echo esc_html($promo['title']); ?></strong></td>
                            <td><?php echo esc_html($promo['type']); ?></td>
                            <td>
                                <?php if ($promo['promo_until']): ?>
                                    <?php echo date('Y-m-d H:i', strtotime($promo['promo_until'])); ?>
                                    <?php if ($expired): ?>
                                        <span style="color:#dc2626;font-weight:700">(Wygasła)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Bez limitu
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($expired): ?>
                                    <span style="background:#dc2626;color:#fff;padding:4px 8px;border-radius:4px">Nieaktywna</span>
                                <?php else: ?>
                                    <span style="background:#16a34a;color:#fff;padding:4px 8px;border-radius:4px">Aktywna</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button jg-edit-promo-date" data-id="<?php echo $promo['id']; ?>" data-current="<?php echo $promo['promo_until'] ? date('Y-m-d\TH:i', strtotime($promo['promo_until'])) : ''; ?>">Edytuj datę</button>
                                <form method="post" style="display:inline" onsubmit="return confirm('Na pewno usunąć promocję?');">
                                    <?php wp_nonce_field('jg_promo_action', 'jg_promo_nonce'); ?>
                                    <input type="hidden" name="jg_promo_action" value="1">
                                    <input type="hidden" name="point_id" value="<?php echo $promo['id']; ?>">
                                    <input type="hidden" name="action_type" value="remove">
                                    <button type="submit" class="button">Usuń promocję</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

            if ($user_id && in_array($action, array('add', 'remove')) && in_array($role_type, array('moderator', 'test_user'))) {
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

        // Get all users
        $users = get_users(array('orderby' => 'registered', 'order' => 'DESC'));

        ?>
        <div class="wrap">
            <h1>Zarządzanie rolami użytkowników</h1>

            <div style="background:#fff7e6;border:2px solid #f59e0b;padding:15px;border-radius:8px;margin:20px 0">
                <h3 style="margin-top:0">ℹ️ O rolach:</h3>
                <ul>
                    <li><strong>Administrator</strong> - pełny dostęp do wszystkich funkcji pluginu</li>
                    <li><strong>Moderator JG Map</strong> - może moderować miejsca, zgłoszenia i edycje</li>
                    <li><strong>Użytkownik testowy</strong> - może logować się pomimo trybu konserwacji w Elementorze</li>
                    <li><strong>Użytkownik</strong> - może dodawać i edytować swoje miejsca</li>
                </ul>
                <p><strong>Uwaga:</strong> Uprawnienia można nadać dowolnemu użytkownikowi. Administratorzy WordPress mają automatycznie wszystkie uprawnienia.</p>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa użytkownika</th>
                        <th>Email</th>
                        <th>Rola WordPress</th>
                        <th>Moderator</th>
                        <th>Użytkownik testowy</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $is_admin = user_can($user->ID, 'manage_options');
                        $is_moderator = user_can($user->ID, 'jg_map_moderate');
                        $is_test_user = user_can($user->ID, 'jg_map_bypass_maintenance');
                        $roles = implode(', ', $user->roles);
                        ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td><strong><?php echo esc_html($user->display_name); ?></strong> (<?php echo esc_html($user->user_login); ?>)</td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(ucfirst($roles)); ?></td>
                            <td>
                                <?php if ($is_admin): ?>
                                    <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px;font-size:12px">✓ Admin</span>
                                <?php elseif ($is_moderator): ?>
                                    <span style="background:#3b82f6;color:#fff;padding:4px 8px;border-radius:4px;font-size:12px">✓ Tak</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px;font-size:12px">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_admin): ?>
                                    <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px;font-size:12px">✓ Admin</span>
                                <?php elseif ($is_test_user): ?>
                                    <span style="background:#f59e0b;color:#fff;padding:4px 8px;border-radius:4px;font-size:12px">✓ Tak</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px;font-size:12px">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$is_admin): ?>
                                    <!-- Moderator buttons -->
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
                                <?php else: ?>
                                    <em style="color:#6b7280">Administrator (automatycznie)</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
            <h1>Galeria wszystkich zdjęć</h1>

            <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);margin:20px 0">
                <p><strong>Łącznie miejsc ze zdjęciami:</strong> <?php echo count($points); ?></p>
            </div>

            <?php if (!empty($points)): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;margin-top:30px">
                    <?php foreach ($points as $point):
                        $images = json_decode($point['images'], true);
                        if (empty($images)) continue;

                        $author = get_userdata($point['author_id']);
                        ?>
                        <div style="background:#fff;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);overflow:hidden">
                            <div style="position:relative;height:200px;background:#f5f5f5">
                                <img src="<?php echo esc_url($images[0]['thumb'] ?? $images[0]['full']); ?>"
                                     style="width:100%;height:100%;object-fit:cover"
                                     alt="<?php echo esc_attr($point['title']); ?>">
                                <?php if (count($images) > 1): ?>
                                    <span style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);color:#fff;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:700">
                                        +<?php echo count($images) - 1; ?> zdjęć
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="padding:12px">
                                <h3 style="margin:0 0 8px;font-size:16px">
                                    <?php echo esc_html($point['title']); ?>
                                </h3>
                                <p style="margin:0 0 8px;font-size:12px;color:#666">
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
     * Render users management page
     */
    public function render_users_page() {
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get all users with their statistics
        $users = get_users(array('orderby' => 'registered', 'order' => 'DESC'));

        // Build stats for each user
        $user_stats = array();
        foreach ($users as $user) {
            $points_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $points_table WHERE author_id = %d AND status = 'publish'",
                $user->ID
            ));

            $pending_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $points_table WHERE author_id = %d AND status = 'pending'",
                $user->ID
            ));

            // Get ban status
            $ban_status = get_user_meta($user->ID, 'jg_map_banned', true);
            $ban_until = get_user_meta($user->ID, 'jg_map_ban_until', true);

            // Get restrictions
            $restrictions = array();
            $restriction_types = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places', 'photo_upload');
            foreach ($restriction_types as $type) {
                if (get_user_meta($user->ID, 'jg_map_ban_' . $type, true)) {
                    $restrictions[] = $type;
                }
            }

            $user_stats[$user->ID] = array(
                'points' => $points_count,
                'pending' => $pending_count,
                'ban_status' => $ban_status,
                'ban_until' => $ban_until,
                'restrictions' => $restrictions
            );
        }

        ?>
        <div class="wrap">
            <h1>Zarządzanie użytkownikami</h1>

            <div style="background:#fff7e6;border:2px solid #f59e0b;padding:15px;border-radius:8px;margin:20px 0">
                <h3 style="margin-top:0">ℹ️ Zarządzanie użytkownikami:</h3>
                <ul>
                    <li>Zobacz statystyki aktywności użytkowników</li>
                    <li>Zarządzaj banami i blokadami</li>
                    <li>Przypisuj role moderatorów</li>
                </ul>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Użytkownik</th>
                        <th>Miejsca</th>
                        <th>Status</th>
                        <th>Blokady</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $stats = $user_stats[$user->ID];
                        $is_banned = JG_Map_Ajax_Handlers::is_user_banned($user->ID);
                        ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <br><small style="color:#666"><?php echo esc_html($user->user_email); ?></small>
                            </td>
                            <td>
                                <span style="background:#e5e7eb;padding:4px 8px;border-radius:4px"><?php echo $stats['points']; ?> opubl.</span>
                                <?php if ($stats['pending'] > 0): ?>
                                    <span style="background:#fbbf24;padding:4px 8px;border-radius:4px;margin-left:4px"><?php echo $stats['pending']; ?> oczek.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_banned): ?>
                                    <?php if ($stats['ban_status'] === 'permanent'): ?>
                                        <span style="background:#dc2626;color:#fff;padding:4px 8px;border-radius:4px;font-weight:700">🚫 Ban permanentny</span>
                                    <?php else: ?>
                                        <span style="background:#dc2626;color:#fff;padding:4px 8px;border-radius:4px;font-weight:700">🚫 Ban do <?php echo date('Y-m-d', strtotime($stats['ban_until'])); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px">✓ Aktywny</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($stats['restrictions'])): ?>
                                    <?php
                                    $labels = array(
                                        'voting' => 'głosowanie',
                                        'add_places' => 'dodawanie miejsc',
                                        'add_events' => 'wydarzenia',
                                        'add_trivia' => 'ciekawostki',
                                        'edit_places' => 'edycja'
                                    );
                                    foreach ($stats['restrictions'] as $r): ?>
                                        <span style="background:#f59e0b;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;margin:2px;display:inline-block">⚠️ <?php echo $labels[$r] ?? $r; ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color:#999">Brak</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-small jg-manage-user"
                                        data-user-id="<?php echo $user->ID; ?>"
                                        data-user-name="<?php echo esc_attr($user->display_name); ?>"
                                        data-ban-status="<?php echo esc_attr($stats['ban_status']); ?>"
                                        data-restrictions='<?php echo esc_attr(json_encode($stats['restrictions'])); ?>'>
                                    Zarządzaj
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal for user management -->
            <div id="jg-user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;padding:20px;border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow:auto;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h2 id="jg-user-modal-title" style="margin:0">Zarządzanie użytkownikiem</h2>
                        <button id="jg-user-modal-close" style="background:#dc2626;color:#fff;border:none;border-radius:4px;padding:8px 16px;cursor:pointer;font-weight:700;">✕</button>
                    </div>

                    <div id="jg-user-current-status" style="margin-bottom:20px;padding:12px;background:#f5f5f5;border-radius:8px;"></div>

                    <div style="margin-bottom:20px;">
                        <h3>Bany</h3>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button class="button jg-ban-permanent">Ban permanentny</button>
                            <button class="button jg-ban-temporary">Ban czasowy</button>
                            <button class="button jg-unban" style="background:#10b981;color:#fff;border-color:#10b981;">Usuń ban</button>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h3>Blokady</h3>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <button class="button jg-toggle-restriction" data-type="voting">Głosowanie</button>
                            <button class="button jg-toggle-restriction" data-type="add_places">Dodawanie miejsc</button>
                            <button class="button jg-toggle-restriction" data-type="add_events">Wydarzenia</button>
                            <button class="button jg-toggle-restriction" data-type="add_trivia">Ciekawostki</button>
                            <button class="button jg-toggle-restriction" data-type="edit_places">Edycja miejsc</button>
                            <button class="button jg-toggle-restriction" data-type="photo_upload">Przesyłanie zdjęć</button>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h3>Limity dzienne (tymczasowe)</h3>
                        <p style="font-size:12px;color:#666;margin:8px 0">Zmiany obowiązują tylko do północy. O północy limity są automatycznie resetowane do domyślnych wartości (5/5).</p>
                        <div id="jg-current-limits" style="background:#f0f9ff;padding:12px;border-radius:8px;margin-bottom:12px;border:2px solid #3b82f6;">
                            <strong>Aktualne limity:</strong>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px;">
                                <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                    <div style="font-size:24px;font-weight:700;color:#3b82f6;" id="limit-places-display">-</div>
                                    <div style="font-size:11px;color:#666;">miejsc/ciekawostek</div>
                                </div>
                                <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                    <div style="font-size:24px;font-weight:700;color:#3b82f6;" id="limit-reports-display">-</div>
                                    <div style="font-size:11px;color:#666;">zgłoszeń</div>
                                </div>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Miejsca/Ciekawostki:</label>
                                <input type="number" id="limit-places-input" min="0" max="999" value="5" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;">
                            </div>
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Zgłoszenia:</label>
                                <input type="number" id="limit-reports-input" min="0" max="999" value="5" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;">
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="button button-primary jg-set-limits">Ustaw limity</button>
                            <button class="button jg-reset-limits">Reset do domyślnych (5/5)</button>
                        </div>
                    </div>

                    <!-- Monthly Photo Upload Limit -->
                    <div style="background:#f8fafc;padding:16px;border-radius:8px;margin-top:16px;">
                        <h3 style="margin:0 0 12px 0;font-size:14px;color:#334155;">📸 Miesięczny limit przesyłania zdjęć</h3>
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:16px;">
                            <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                <div style="font-size:24px;font-weight:700;color:#8b5cf6;" id="photo-used-display">-</div>
                                <div style="font-size:11px;color:#666;">wykorzystano (MB)</div>
                            </div>
                            <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                <div style="font-size:24px;font-weight:700;color:#3b82f6;" id="photo-limit-display">-</div>
                                <div style="font-size:11px;color:#666;">limit (MB)</div>
                            </div>
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="display:block;font-weight:600;margin-bottom:4px;">Ustaw nowy limit (MB):</label>
                            <input type="number" id="photo-limit-input" min="1" max="10000" value="100" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="button button-primary jg-set-photo-limit">Ustaw limit</button>
                            <button class="button jg-reset-photo-limit">Reset do domyślnych (100MB)</button>
                        </div>
                    </div>

                    <div id="jg-user-message" style="margin-top:16px;padding:12px;border-radius:8px;display:none;"></div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                var modal = $('#jg-user-modal');
                var modalTitle = $('#jg-user-modal-title');
                var currentStatus = $('#jg-user-current-status');
                var message = $('#jg-user-message');
                var currentUserId = null;
                var currentRestrictions = [];

                $('.jg-manage-user').on('click', function() {
                    currentUserId = $(this).data('user-id');
                    var userName = $(this).data('user-name');
                    var banStatus = $(this).data('ban-status');
                    currentRestrictions = $(this).data('restrictions') || [];

                    modalTitle.text('Zarządzanie: ' + userName);

                    // Update current status display
                    var statusHtml = '<strong>Aktualny status:</strong><br>';
                    if (banStatus === 'permanent') {
                        statusHtml += '<span style="color:#dc2626">🚫 Ban permanentny</span>';
                    } else if (banStatus === 'temporary') {
                        statusHtml += '<span style="color:#dc2626">🚫 Ban czasowy</span>';
                    } else {
                        statusHtml += '<span style="color:#10b981">✓ Aktywny</span>';
                    }

                    if (currentRestrictions.length > 0) {
                        statusHtml += '<br><strong>Aktywne blokady:</strong> ' + currentRestrictions.join(', ');
                    }

                    currentStatus.html(statusHtml);

                    // Update restriction button states
                    $('.jg-toggle-restriction').each(function() {
                        var type = $(this).data('type');
                        if (currentRestrictions.indexOf(type) !== -1) {
                            $(this).css({
                                'background': '#dc2626',
                                'color': '#fff',
                                'border-color': '#dc2626'
                            }).text($(this).text() + ' ✓');
                        } else {
                            $(this).css({
                                'background': '',
                                'color': '',
                                'border-color': ''
                            });
                        }
                    });

                    // Fetch current daily limits
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_get_user_limits',
                            user_id: currentUserId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                $('#limit-places-display').text(data.places_remaining);
                                $('#limit-reports-display').text(data.reports_remaining);
                                $('#limit-places-input').val(data.places_remaining);
                                $('#limit-reports-input').val(data.reports_remaining);
                            }
                        }
                    });

                    // Fetch monthly photo limits
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_get_user_photo_limit',
                            user_id: currentUserId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                $('#photo-used-display').text(data.used_mb);
                                $('#photo-limit-display').text(data.limit_mb);
                                $('#photo-limit-input').val(data.limit_mb);
                            }
                        }
                    });

                    modal.css('display', 'flex');
                });

                $('#jg-user-modal-close, #jg-user-modal').on('click', function(e) {
                    if (e.target === this) {
                        modal.hide();
                        message.hide();
                    }
                });

                function showMessage(text, isError) {
                    message.text(text)
                        .css('background', isError ? '#fee' : '#d1fae5')
                        .css('color', isError ? '#dc2626' : '#10b981')
                        .show();
                    setTimeout(function() {
                        if (!isError) {
                            location.reload();
                        }
                    }, 1500);
                }

                $('.jg-ban-permanent').on('click', function() {
                    if (!confirm('Czy na pewno zbanować użytkownika permanentnie?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_ban_user',
                            user_id: currentUserId,
                            ban_type: 'permanent',
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            showMessage(response.success ? 'Użytkownik zbanowany permanentnie!' : response.data.message, !response.success);
                        }
                    });
                });

                $('.jg-ban-temporary').on('click', function() {
                    var days = prompt('Na ile dni zbanować użytkownika?', '7');
                    if (!days) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_ban_user',
                            user_id: currentUserId,
                            ban_type: 'temporary',
                            ban_days: parseInt(days),
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            showMessage(response.success ? 'Użytkownik zbanowany na ' + days + ' dni!' : response.data.message, !response.success);
                        }
                    });
                });

                $('.jg-unban').on('click', function() {
                    if (!confirm('Czy na pewno usunąć ban?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_unban_user',
                            user_id: currentUserId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            showMessage(response.success ? 'Ban usunięty!' : response.data.message, !response.success);
                        }
                    });
                });

                $('.jg-toggle-restriction').on('click', function() {
                    var type = $(this).data('type');
                    var btn = $(this);

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_toggle_user_restriction',
                            user_id: currentUserId,
                            restriction_type: type,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                showMessage(response.data.message, false);
                            } else {
                                showMessage(response.data.message, true);
                            }
                        }
                    });
                });

                // Set custom limits
                $('.jg-set-limits').on('click', function() {
                    var placesLimit = parseInt($('#limit-places-input').val());
                    var reportsLimit = parseInt($('#limit-reports-input').val());

                    if (isNaN(placesLimit) || isNaN(reportsLimit) || placesLimit < 0 || reportsLimit < 0) {
                        showMessage('Nieprawidłowe wartości limitów', true);
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_set_user_limits',
                            user_id: currentUserId,
                            places_limit: placesLimit,
                            reports_limit: reportsLimit,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#limit-places-display').text(response.data.places_remaining);
                                $('#limit-reports-display').text(response.data.reports_remaining);
                                showMessage('Limity ustawione pomyślnie!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });

                // Reset limits to default
                $('.jg-reset-limits').on('click', function() {
                    if (!confirm('Zresetować limity do domyślnych wartości (5/5)?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_set_user_limits',
                            user_id: currentUserId,
                            places_limit: 5,
                            reports_limit: 5,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#limit-places-display').text('5');
                                $('#limit-reports-display').text('5');
                                $('#limit-places-input').val(5);
                                $('#limit-reports-input').val(5);
                                showMessage('Limity zresetowane do domyślnych!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });

                // Set custom photo limit
                $('.jg-set-photo-limit').on('click', function() {
                    var photoLimit = parseInt($('#photo-limit-input').val());

                    if (isNaN(photoLimit) || photoLimit < 1) {
                        showMessage('Nieprawidłowa wartość limitu zdjęć', true);
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_set_user_photo_limit',
                            user_id: currentUserId,
                            limit_mb: photoLimit,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#photo-limit-display').text(response.data.limit_mb);
                                showMessage('Limit zdjęć ustawiony pomyślnie!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });

                // Reset photo limit to default
                $('.jg-reset-photo-limit').on('click', function() {
                    if (!confirm('Zresetować limit zdjęć do domyślnej wartości (100MB)?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_reset_user_photo_limit',
                            user_id: currentUserId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#photo-used-display').text(response.data.used_mb);
                                $('#photo-limit-display').text(response.data.limit_mb);
                                $('#photo-limit-input').val(response.data.limit_mb);
                                showMessage('Limit zdjęć zresetowany do domyślnego (100MB)!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });
            });
            </script>
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

        // Build query
        $where = array('1=1');
        if ($action_filter) {
            $where[] = $wpdb->prepare('action = %s', $action_filter);
        }
        if ($user_filter) {
            $where[] = $wpdb->prepare('user_id = %d', $user_filter);
        }
        $where_clause = implode(' AND ', $where);

        // Get logs
        $logs = $wpdb->get_results(
            "SELECT * FROM $log_table
             WHERE $where_clause
             ORDER BY created_at DESC
             LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );

        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE $where_clause");
        $total_pages = ceil($total / $per_page);

        // Get unique actions for filter
        $actions = $wpdb->get_col("SELECT DISTINCT action FROM $log_table ORDER BY action");

        // Get users who have logged actions
        $users_with_logs = $wpdb->get_results(
            "SELECT DISTINCT user_id FROM $log_table ORDER BY user_id"
        );

        ?>
        <div class="wrap">
            <h1>Activity Log</h1>

            <div style="background:#fff;padding:15px;border:1px solid #ddd;border-radius:4px;margin:20px 0">
                <form method="get" style="display:flex;gap:15px;align-items:flex-end">
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
                    <?php if ($action_filter || $user_filter): ?>
                        <a href="<?php echo admin_url('admin.php?page=jg-map-activity-log'); ?>" class="button">Wyczyść filtry</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($logs)): ?>
            <table class="wp-list-table widefat fixed striped">
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
                    ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?></td>
                            <td><?php echo esc_html($user_name); ?></td>
                            <td><strong><?php echo esc_html($log['action']); ?></strong></td>
                            <td><?php echo esc_html($log['object_type']); ?></td>
                            <td><?php echo esc_html($log['object_id'] ?: '-'); ?></td>
                            <td><?php echo esc_html($log['description']); ?></td>
                            <td><code><?php echo esc_html($log['ip_address']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

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
                                    'user_filter' => $user_filter
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
            $registration_enabled = isset($_POST['jg_map_registration_enabled']) ? 1 : 0;
            $registration_disabled_message = sanitize_textarea_field($_POST['jg_map_registration_disabled_message'] ?? '');

            update_option('jg_map_registration_enabled', $registration_enabled);
            update_option('jg_map_registration_disabled_message', $registration_disabled_message);

            echo '<div class="notice notice-success is-dismissible"><p>Ustawienia zostały zapisane.</p></div>';
        }

        $registration_enabled = get_option('jg_map_registration_enabled', 1); // Enabled by default
        $registration_disabled_message = get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.');
        ?>
        <div class="wrap">
            <h1>Ustawienia JG Map</h1>

            <form method="post" action="">
                <?php wp_nonce_field('jg_map_settings_nonce'); ?>

                <div style="background:#fff;padding:20px;margin:20px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);max-width:800px">
                    <h2 style="margin-top:0">Rejestracja użytkowników</h2>

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
                                    Gdy wyłączone, przycisk "Zarejestruj" pokaże komunikat zamiast formularza rejestracji.
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

                <p class="submit">
                    <input type="submit"
                           name="jg_map_save_settings"
                           id="submit"
                           class="button button-primary"
                           value="Zapisz ustawienia">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Get pending counts for real-time updates
     */
    private function get_pending_counts() {
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();
        $reports_table = JG_Map_Database::get_reports_table();
        $history_table = JG_Map_Database::get_history_table();

        // Ensure history table exists
        JG_Map_Database::ensure_history_table();

        // Disable caching
        $wpdb->query('SET SESSION query_cache_type = OFF');

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

        return array(
            'points' => intval($pending_points),
            'edits' => intval($pending_edits),
            'reports' => intval($pending_reports),
            'deletions' => intval($pending_deletions),
            'total' => intval($pending_points) + intval($pending_edits) + intval($pending_reports) + intval($pending_deletions)
        );
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
    public function enqueue_admin_bar_script() {
        // Only for admins and moderators
        if (!current_user_can('manage_options') && !current_user_can('jg_map_moderate')) {
            return;
        }

        // Enqueue WordPress Heartbeat API
        wp_enqueue_script('heartbeat');

        // Add inline script for real-time notifications
        $script = "
        (function($) {
            var lastTotal = 0;

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

                console.log('[JG MAP] Heartbeat notification update:', counts);

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
                            .attr('href', '<?php echo admin_url('admin.php?page=jg-map-places#section-new_pending'); ?>');

                        $('#wp-admin-bar-jg-map-pending-edits').toggle(counts.edits > 0);
                        $('#wp-admin-bar-jg-map-pending-edits a').html('✏️ ' + counts.edits + ' edycji do zatwierdzenia')
                            .attr('href', '<?php echo admin_url('admin.php?page=jg-map-places#section-edit_pending'); ?>');

                        $('#wp-admin-bar-jg-map-pending-reports').toggle(counts.reports > 0);
                        $('#wp-admin-bar-jg-map-pending-reports a').html('🚨 ' + counts.reports + ' zgłoszeń')
                            .attr('href', '<?php echo admin_url('admin.php?page=jg-map-places#section-reported'); ?>');

                        $('#wp-admin-bar-jg-map-pending-deletions').toggle(counts.deletions > 0);
                        $('#wp-admin-bar-jg-map-pending-deletions a').html('🗑️ ' + counts.deletions + ' żądań usunięcia')
                            .attr('href', '<?php echo admin_url('admin.php?page=jg-map-places#section-deletion_pending'); ?>');

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
        ";

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
            <h1>🔧 Konserwacja bazy danych</h1>

            <div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-top:20px;">
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
        </div>
        <?php
    }
}
