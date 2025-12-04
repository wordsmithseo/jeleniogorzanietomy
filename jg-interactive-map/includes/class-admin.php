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

        // Count pending items
        $pending_points = $wpdb->get_var("SELECT COUNT(*) FROM $points_table WHERE status = 'pending'");
        $pending_edits = $wpdb->get_var("SELECT COUNT(*) FROM $history_table WHERE status = 'pending'");
        $pending_reports = $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'");
        $pending_deletions = $wpdb->get_var("SELECT COUNT(*) FROM $points_table WHERE is_deletion_requested = 1");

        $total_pending = intval($pending_points) + intval($pending_edits) + intval($pending_reports) + intval($pending_deletions);

        if ($total_pending === 0) {
            return;
        }

        // Add parent node
        $wp_admin_bar->add_node(array(
            'id' => 'jg-map-notifications',
            'title' => '<span style="background:#dc2626;color:#fff;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:700;margin-right:4px">' . $total_pending . '</span> JG Map',
            'href' => admin_url('admin.php?page=jg-map-moderation'),
            'meta' => array(
                'title' => 'JG Map - Oczekujące moderacje'
            )
        ));

        // Add child nodes
        if ($pending_points > 0) {
            $wp_admin_bar->add_node(array(
                'parent' => 'jg-map-notifications',
                'id' => 'jg-map-pending-points',
                'title' => '📍 ' . $pending_points . ' nowych miejsc',
                'href' => admin_url('admin.php?page=jg-map-moderation')
            ));
        }

        if ($pending_edits > 0) {
            $wp_admin_bar->add_node(array(
                'parent' => 'jg-map-notifications',
                'id' => 'jg-map-pending-edits',
                'title' => '✏️ ' . $pending_edits . ' edycji do zatwierdzenia',
                'href' => admin_url('admin.php?page=jg-map-moderation')
            ));
        }

        if ($pending_reports > 0) {
            $wp_admin_bar->add_node(array(
                'parent' => 'jg-map-notifications',
                'id' => 'jg-map-pending-reports',
                'title' => '🚨 ' . $pending_reports . ' zgłoszeń',
                'href' => admin_url('admin.php?page=jg-map-reports')
            ));
        }

        if ($pending_deletions > 0) {
            $wp_admin_bar->add_node(array(
                'parent' => 'jg-map-notifications',
                'id' => 'jg-map-pending-deletions',
                'title' => '🗑️ ' . $pending_deletions . ' żądań usunięcia',
                'href' => admin_url('admin.php?page=jg-map-deletions')
            ));
        }
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
            'Dodane miejsca',
            'Dodane miejsca',
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
            'Żądania usunięcia',
            'Żądania usunięcia',
            'manage_options',
            'jg-map-deletions',
            array($this, 'render_deletions_page')
        );

        add_submenu_page(
            'jg-map',
            'Wszystkie miejsca',
            'Wszystkie miejsca',
            'manage_options',
            'jg-map-all',
            array($this, 'render_all_points_page')
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
            'Role użytkowników',
            'Role użytkowników',
            'manage_options',
            'jg-map-roles',
            array($this, 'render_roles_page')
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
        $reports = $wpdb->get_var("SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'");

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
        $edits = $wpdb->get_results(
            "SELECT h.*, p.title as point_title,
            COUNT(r.id) as report_count,
            TIMESTAMPDIFF(HOUR, h.created_at, NOW()) as hours_old
            FROM $history_table h
            LEFT JOIN $table p ON h.point_id = p.id
            LEFT JOIN $reports_table r ON h.point_id = r.point_id AND r.status = 'pending'
            WHERE h.status = 'pending'
            GROUP BY h.id
            ORDER BY report_count DESC, hours_old DESC",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <h1>Dodane miejsca</h1>

            <?php if (!empty($edits)): ?>
            <h2>Edycje do zatwierdzenia (<?php echo count($edits); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
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
                            <td>
                                <strong><?php echo esc_html($edit['point_title']); ?></strong>
                                <?php if ($priority): ?>
                                    <span style="<?php echo $priority_style; ?>"><?php echo $priority; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user ? esc_html($user->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo implode(', ', $changes); ?></td>
                            <td><?php echo human_time_diff(strtotime(get_date_from_gmt($edit['created_at'])), current_time('timestamp')); ?> temu</td>
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
                    if (old_values.content !== new_values.content) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Opis</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee;max-width:300px;word-wrap:break-word">' + old_values.content + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5;max-width:300px;word-wrap:break-word">' + new_values.content + '</td></tr>';
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
            });
            </script>
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
                            <td>
                                <strong><?php echo esc_html($point['title']); ?></strong>
                                <?php if ($priority): ?>
                                    <span style="<?php echo $priority_style; ?>"><?php echo $priority; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($point['type']); ?></td>
                            <td><?php echo $author ? esc_html($author->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo human_time_diff(strtotime(get_date_from_gmt($point['created_at'])), current_time('timestamp')); ?> temu</td>
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
                            <td><?php echo human_time_diff(strtotime(get_date_from_gmt($report['created_at'])), current_time('timestamp')); ?> temu</td>
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
                            <td><?php echo human_time_diff(strtotime(get_date_from_gmt($point['created_at'])), current_time('timestamp')); ?> temu</td>
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

            if ($user_id && in_array($action, array('add', 'remove'))) {
                $user = get_userdata($user_id);
                if ($user) {
                    if ($action === 'add') {
                        $user->add_cap('jg_map_moderate');
                        echo '<div class="notice notice-success"><p>Uprawnienia moderatora dodane!</p></div>';
                    } else {
                        $user->remove_cap('jg_map_moderate');
                        echo '<div class="notice notice-success"><p>Uprawnienia moderatora usunięte!</p></div>';
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
                    <li><strong>Użytkownik</strong> - może dodawać i edytować swoje miejsca</li>
                </ul>
                <p><strong>Uwaga:</strong> Uprawnienia moderatora można nadać dowolnemu użytkownikowi. Administratorzy WordPress mają automatycznie wszystkie uprawnienia.</p>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa użytkownika</th>
                        <th>Email</th>
                        <th>Rola WordPress</th>
                        <th>Moderator JG Map</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $is_admin = user_can($user->ID, 'manage_options');
                        $is_moderator = user_can($user->ID, 'jg_map_moderate');
                        $roles = implode(', ', $user->roles);
                        ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td><strong><?php echo esc_html($user->display_name); ?></strong> (<?php echo esc_html($user->user_login); ?>)</td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(ucfirst($roles)); ?></td>
                            <td>
                                <?php if ($is_admin): ?>
                                    <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px">✓ Administrator</span>
                                <?php elseif ($is_moderator): ?>
                                    <span style="background:#3b82f6;color:#fff;padding:4px 8px;border-radius:4px">✓ Moderator</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px">Brak</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$is_admin): ?>
                                    <form method="post" style="display:inline">
                                        <?php wp_nonce_field('jg_roles_update', 'jg_roles_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="jg_update_roles" value="1">
                                        <?php if ($is_moderator): ?>
                                            <input type="hidden" name="role_action" value="remove">
                                            <button type="submit" class="button button-small">Usuń moderatora</button>
                                        <?php else: ?>
                                            <input type="hidden" name="role_action" value="add">
                                            <button type="submit" class="button button-small button-primary">Dodaj moderatora</button>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <em style="color:#6b7280">Admin (nie można zmienić)</em>
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
                            <td><?php echo human_time_diff(strtotime($point['deletion_requested_at']), current_time('timestamp')); ?> temu</td>
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
                                    <?php echo human_time_diff(strtotime(get_date_from_gmt($point['created_at'])), current_time('timestamp')); ?> temu
                                </p>
                                <div style="display:flex;gap:8px">
                                    <a href="<?php echo get_site_url(); ?>?jg_view_point=<?php echo $point['id']; ?>"
                                       class="button button-small" target="_blank">Zobacz miejsce</a>
                                    <button class="button button-small jg-view-all-images"
                                            data-images='<?php echo esc_attr(json_encode($images)); ?>'
                                            data-title="<?php echo esc_attr($point['title']); ?>">
                                        Wszystkie zdjęcia
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

                    $('.jg-view-all-images').on('click', function() {
                        var images = $(this).data('images');
                        var title = $(this).data('title');

                        titleEl.text(title);
                        imagesContainer.empty();

                        images.forEach(function(img) {
                            imagesContainer.append(
                                $('<a>').attr({
                                    href: img.full,
                                    target: '_blank'
                                }).css({
                                    display: 'block',
                                    borderRadius: '8px',
                                    overflow: 'hidden'
                                }).append(
                                    $('<img>').attr('src', img.thumb || img.full).css({
                                        width: '100%',
                                        height: '250px',
                                        objectFit: 'cover',
                                        display: 'block'
                                    })
                                )
                            );
                        });

                        lightbox.css('display', 'flex');
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
            $restriction_types = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places');
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
            });
            </script>
        </div>
        <?php
    }
}
