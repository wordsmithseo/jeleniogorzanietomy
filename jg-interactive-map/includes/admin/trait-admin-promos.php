<?php

defined( 'ABSPATH' ) || exit;

trait JG_Map_Admin_Promos {

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
}
