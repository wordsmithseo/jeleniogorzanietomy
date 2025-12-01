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
            'Moderacja',
            'Moderacja',
            'manage_options',
            'jg-map-moderation',
            array($this, 'render_moderation_page')
        );

        add_submenu_page(
            'jg-map',
            'Zgłoszenia',
            'Zgłoszenia',
            'manage_options',
            'jg-map-reports',
            array($this, 'render_reports_page')
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
            'Wszystkie miejsca',
            'Wszystkie miejsca',
            'manage_options',
            'jg-map-all',
            array($this, 'render_all_points_page')
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

        $reports_table = JG_Map_Database::get_reports_table();
        $reports = $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'");

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
     * Render moderation page
     */
    public function render_moderation_page() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $pending = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at DESC",
            ARRAY_A
        );

        $history_table = JG_Map_Database::get_history_table();
        $edits = $wpdb->get_results(
            "SELECT h.*, p.title as point_title FROM $history_table h
            LEFT JOIN $table p ON h.point_id = p.id
            WHERE h.status = 'pending' ORDER BY h.created_at DESC",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <h1>Moderacja miejsc</h1>

            <?php if (!empty($edits)): ?>
            <h2>Edycje do zatwierdzenia (<?php echo count($edits); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Miejsce</th>
                        <th>Użytkownik</th>
                        <th>Data</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($edits as $edit):
                        $user = get_userdata($edit['user_id']);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($edit['point_title']); ?></strong></td>
                            <td><?php echo $user ? esc_html($user->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo human_time_diff(strtotime($edit['created_at']), current_time('timestamp')); ?> temu</td>
                            <td>
                                <a href="<?php echo get_site_url(); ?>?jg_preview_edit=<?php echo $edit['id']; ?>" class="button">Zobacz zmiany</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (!empty($pending)): ?>
            <h2 style="margin-top:40px">Nowe miejsca do zatwierdzenia (<?php echo count($pending); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
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
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($point['title']); ?></strong></td>
                            <td><?php echo esc_html($point['type']); ?></td>
                            <td><?php echo $author ? esc_html($author->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo human_time_diff(strtotime($point['created_at']), current_time('timestamp')); ?> temu</td>
                            <td>
                                <a href="<?php echo get_site_url(); ?>?jg_preview_point=<?php echo $point['id']; ?>" class="button">Zobacz szczegóły</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
            "SELECT r.*, p.title as point_title, COUNT(r2.id) as report_count
            FROM $reports_table r
            LEFT JOIN $points_table p ON r.point_id = p.id
            LEFT JOIN $reports_table r2 ON r.point_id = r2.point_id AND r2.status = 'pending'
            WHERE r.status = 'pending'
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
                            <td><?php echo human_time_diff(strtotime($report['created_at']), current_time('timestamp')); ?> temu</td>
                            <td>
                                <a href="<?php echo get_site_url(); ?>?jg_view_reports=<?php echo $report['point_id']; ?>" class="button">Zobacz szczegóły</a>
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
                            $expired = strtotime($promo['promo_until']) < current_time('timestamp');
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
                                <a href="<?php echo get_site_url(); ?>?jg_edit_promo=<?php echo $promo['id']; ?>" class="button">Edytuj datę</a>
                                <a href="<?php echo get_site_url(); ?>?jg_remove_promo=<?php echo $promo['id']; ?>" class="button">Usuń promocję</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>Brak aktywnych promocji.</p>
            <?php endif; ?>
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
                            <td><?php echo human_time_diff(strtotime($point['created_at']), current_time('timestamp')); ?> temu</td>
                            <td>
                                <a href="<?php echo get_site_url(); ?>?jg_view_point=<?php echo $point['id']; ?>" class="button button-small">Zobacz</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
