<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait JG_Map_Admin_Places {

    public function render_places_page() {
        // Check if user is admin
        $is_admin = current_user_can('manage_options');

        // Ensure history table exists before querying (safe no-op if already up to date)
        JG_Map_Database::ensure_history_table();

        // Handle search and filters
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // For regular users, always filter by their ID
        // For admins, show all places by default
        $current_user_id = $is_admin ? 0 : get_current_user_id();

        // Get places with status
        $places = JG_Map_Database::get_all_places_with_status($search, $status_filter, $current_user_id);

        // Compute counts — when no search/filter is active the $places array already contains all
        // records, so we can count in-memory and avoid a second heavy DB query.
        if (empty($search) && empty($status_filter)) {
            $counts = array(
                'reported' => 0, 'new_pending' => 0, 'edit_pending' => 0,
                'deletion_pending' => 0, 'published' => 0, 'trash' => 0, 'total' => 0
            );
            foreach ($places as $_p) {
                $counts['total']++;
                if (isset($counts[$_p['display_status']])) {
                    $counts[$_p['display_status']]++;
                }
            }
        } else {
            $counts = JG_Map_Database::get_places_count_by_status($current_user_id);
        }

        // Group places by display status
        $grouped_places = array(
            'reported' => array(),
            'new_pending' => array(),
            'edit_pending' => array(),
            'deletion_pending' => array(),
            'published' => array(),
            'trash' => array()
        );

        foreach ($places as $place) {
            if (isset($grouped_places[$place['display_status']])) {
                $grouped_places[$place['display_status']][] = $place;
            }
        }

        ?>
        <style>
        /* ===== Miejsca — stat cards & section headers ===== */
        .jg-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px}
        .jg-stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;flex-direction:column;gap:6px;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .15s}
        .jg-stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-2px);color:inherit;text-decoration:none}
        .jg-stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af}
        .jg-stat-value{font-size:32px;font-weight:800;line-height:1;color:#111827}
        .jg-stat-sub{font-size:12px;color:#6b7280;margin-top:2px}
        .jg-stat-card.has-action .jg-stat-sub{color:#2563eb;font-weight:600}
        .jg-places-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
        .jg-places-section-title{display:flex;align-items:center;gap:10px;padding:12px 18px;background:#f8fafc;border-bottom:2px solid;font-size:13px;font-weight:700;color:#374151;margin:0}
        .jg-places-section-count{padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;color:#fff}
        .jg-places-empty{padding:16px 18px;color:#9ca3af;font-style:italic;font-size:13px}
        .jg-action-buttons{display:flex;gap:5px;flex-wrap:wrap}
        .jg-action-buttons .button{font-size:calc(12 * var(--jg));padding:4px 8px;height:auto;line-height:1.4}
        @media(max-width:782px){.jg-stat-grid{grid-template-columns:repeat(2,1fr)}.jg-stat-value{font-size:26px}}
        @media(max-width:480px){.jg-stat-grid{grid-template-columns:1fr}}
        </style>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie miejscami'); ?>

            <!-- Search bar -->
            <div class="jg-places-section" style="padding:20px;margin-bottom:20px">
                <form method="get" action="">
                    <input type="hidden" name="page" value="jg-map-places">
                    <div style="display:flex;gap:10px;align-items:center">
                        <input type="text" name="search" value="<?php echo esc_attr($search); ?>"
                               placeholder="Szukaj po nazwie, treści, adresie<?php echo $is_admin ? ' lub autorze' : ''; ?>..."
                               style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:4px">
                        <button type="submit" class="button button-primary">🔍 Szukaj</button>
                        <?php if ($search || $status_filter): ?>
                            <a href="?page=jg-map-places" class="button">✕ Wyczyść</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_admin): ?>
                    <p style="margin:10px 0 0 0;color:#666;font-size:calc(13 * var(--jg))">
                        ℹ️ Widzisz tylko swoje miejsca
                    </p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Statistics -->
            <div class="jg-stat-grid">
                <a href="#section-reported" class="jg-stat-card<?php echo $counts['reported'] > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">🚨 Zgłoszone</span>
                    <span class="jg-stat-value" style="color:<?php echo $counts['reported'] > 0 ? '#dc2626' : '#111827'; ?>"><?php echo (int)$counts['reported']; ?></span>
                    <span class="jg-stat-sub"><?php echo $counts['reported'] > 0 ? '→ wymagają uwagi' : 'brak zgłoszeń'; ?></span>
                </a>
                <a href="#section-new_pending" class="jg-stat-card<?php echo $counts['new_pending'] > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">⏳ Nowe czekające</span>
                    <span class="jg-stat-value" style="color:<?php echo $counts['new_pending'] > 0 ? '#f59e0b' : '#111827'; ?>"><?php echo (int)$counts['new_pending']; ?></span>
                    <span class="jg-stat-sub"><?php echo $counts['new_pending'] > 0 ? '→ do zatwierdzenia' : 'brak nowych'; ?></span>
                </a>
                <a href="#section-edit_pending" class="jg-stat-card<?php echo $counts['edit_pending'] > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">✏️ Edycje czekające</span>
                    <span class="jg-stat-value" style="color:<?php echo $counts['edit_pending'] > 0 ? '#3b82f6' : '#111827'; ?>"><?php echo (int)$counts['edit_pending']; ?></span>
                    <span class="jg-stat-sub"><?php echo $counts['edit_pending'] > 0 ? '→ do zatwierdzenia' : 'brak edycji'; ?></span>
                </a>
                <a href="#section-deletion_pending" class="jg-stat-card<?php echo $counts['deletion_pending'] > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">🗑️ Do usunięcia</span>
                    <span class="jg-stat-value" style="color:<?php echo $counts['deletion_pending'] > 0 ? '#8b5cf6' : '#111827'; ?>"><?php echo (int)$counts['deletion_pending']; ?></span>
                    <span class="jg-stat-sub"><?php echo $counts['deletion_pending'] > 0 ? '→ żądania usunięcia' : 'brak żądań'; ?></span>
                </a>
                <a href="#section-published" class="jg-stat-card">
                    <span class="jg-stat-label">✅ Opublikowane</span>
                    <span class="jg-stat-value" style="color:#10b981"><?php echo (int)$counts['published']; ?></span>
                    <span class="jg-stat-sub">aktywne miejsca</span>
                </a>
                <a href="#section-trash" class="jg-stat-card<?php echo $counts['trash'] > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">🗑️ Kosz</span>
                    <span class="jg-stat-value" style="color:<?php echo $counts['trash'] > 0 ? '#6b7280' : '#111827'; ?>"><?php echo (int)$counts['trash']; ?></span>
                    <span class="jg-stat-sub"><?php echo $counts['trash'] > 0 ? '→ usunięte miejsca' : 'pusty'; ?></span>
                </a>
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
                ),
                'trash' => array(
                    'title' => '🗑️ Kosz',
                    'color' => '#6b7280',
                    'actions' => array('details', 'restore', 'delete_permanent')
                )
            );

            // Render each section
            foreach ($sections as $status => $config) {
                $section_places = $grouped_places[$status];
                $section_count = count($section_places);

                if ($section_count > 0 || !$search) { // Show section if has places or no search active
                    ?>
                    <div id="section-<?php echo esc_attr($status); ?>" class="jg-places-section">
                        <p class="jg-places-section-title" style="border-left:4px solid <?php echo esc_attr($config['color']); ?>">
                            <span style="color:<?php echo esc_attr($config['color']); ?>"><?php echo $config['title']; ?></span>
                            <span class="jg-places-section-count" style="background:<?php echo esc_attr($config['color']); ?>"><?php echo $section_count; ?></span>
                            <?php if ($status === 'trash' && $section_count > 0): ?>
                                <button class="button jg-empty-trash" style="margin-left:auto;background:#dc2626;color:#fff;border-color:#dc2626">
                                    🗑️ Opróżnij kosz
                                </button>
                            <?php endif; ?>
                        </p>

                        <?php if ($section_count > 0): ?>
                            <div style="overflow-x:auto">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width:<?php echo $status === 'reported' ? '16%' : '18%'; ?>">Miejsce</th>
                                        <th style="width:10%">Kto dodał</th>
                                        <th style="width:10%">Ostatni modyfikujący</th>
                                        <?php if ($status === 'reported'): ?>
                                        <th style="width:10%">Kto zgłosił</th>
                                        <?php endif; ?>
                                        <th style="width:10%">Data dodania</th>
                                        <th style="width:10%">Data zatwierdzenia</th>
                                        <th style="width:8%">Status</th>
                                        <th style="width:7%">Sponsorowane</th>
                                        <th style="width:<?php echo $status === 'reported' ? '14%' : '22%'; ?>">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody class="jg-paginated-tbody" data-section="<?php echo esc_attr($status); ?>" data-total="<?php echo $section_count; ?>">
                                    <?php foreach ($section_places as $place):
                                        $this->render_place_row($place, $config['actions'], $is_admin, $status);
                                    endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                            <div class="jg-pagination-bar" data-section="<?php echo esc_attr($status); ?>" style="padding:10px 18px;align-items:center;gap:10px;border-top:1px solid #e5e7eb;background:#f9fafb;display:none"></div>
                        <?php else: ?>
                            <p class="jg-places-empty">Brak miejsc w tej kategorii</p>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            }
            ?>

            <?php if (empty($places) && $search): ?>
                <div class="jg-places-section" style="text-align:center;padding:40px">
                    <h3>Nie znaleziono miejsc</h3>
                    <p>Spróbuj zmienić kryteria wyszukiwania</p>
                </div>
            <?php endif; ?>
        </div>

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

            // Delete point (basic) — moves to trash, not permanent
            $('.jg-delete-point').on('click', function() {
                if (!confirm('Czy na pewno chcesz przenieść to miejsce do kosza?')) return;

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
                            alert('Miejsce zostało przeniesione do kosza!');
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

            // Restore point from trash
            $('.jg-restore-point').on('click', function() {
                if (!confirm('Czy na pewno chcesz przywrócić to miejsce z kosza?')) return;

                var pointId = $(this).data('point-id');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_restore_point',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        post_id: pointId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Miejsce zostało przywrócone!');
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

            // Delete point permanently from trash
            $('.jg-delete-permanent').on('click', function() {
                if (!confirm('Czy na pewno chcesz TRWALE usunąć to miejsce z kosza? Tej operacji nie można cofnąć!')) return;

                var pointId = $(this).data('point-id');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_delete_permanent',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>',
                        post_id: pointId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Miejsce zostało trwale usunięte!');
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

            // Empty trash - delete all trashed points
            $('.jg-empty-trash').on('click', function() {
                if (!confirm('Czy na pewno chcesz TRWALE OPRÓŻNIĆ KOSZ? Wszystkie miejsca w koszu zostaną usunięte. Tej operacji nie można cofnąć!')) return;

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'jg_admin_empty_trash',
                        _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || 'Kosz został opróżniony!');
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

            // ── Pagination (50 per page per section) ──────────────────────────
            var PER_PAGE = 50;
            var sectionPages = {};

            function initPagination() {
                $('.jg-paginated-tbody').each(function() {
                    var section = $(this).data('section');
                    var $rows = $(this).children('tr');
                    var total = $rows.length;

                    if (total <= PER_PAGE) return; // no pagination needed

                    sectionPages[section] = 1;
                    renderPage(section);
                });
            }

            function renderPage(section) {
                var page = sectionPages[section] || 1;
                var $tbody = $('.jg-paginated-tbody[data-section="' + section + '"]');
                var $rows = $tbody.children('tr');
                var total = $rows.length;
                var totalPages = Math.ceil(total / PER_PAGE);
                var start = (page - 1) * PER_PAGE;
                var end = start + PER_PAGE;

                $rows.each(function(i) {
                    $(this).toggle(i >= start && i < end);
                });

                var $bar = $('.jg-pagination-bar[data-section="' + section + '"]');
                $bar.css('display', 'flex').html(
                    '<button class="button jg-pg-prev" data-section="' + section + '" ' + (page <= 1 ? 'disabled' : '') + '>&#8592; Poprzednia</button>' +
                    '<span style="font-size:13px;color:#374151">Strona <strong>' + page + '</strong> z <strong>' + totalPages + '</strong> (' + total + ' pozycji)</span>' +
                    '<button class="button jg-pg-next" data-section="' + section + '" ' + (page >= totalPages ? 'disabled' : '') + '>Następna &#8594;</button>'
                );
            }

            $(document).on('click', '.jg-pg-prev', function() {
                var section = $(this).data('section');
                if (sectionPages[section] > 1) {
                    sectionPages[section]--;
                    renderPage(section);
                    $('html, body').animate({ scrollTop: $('#section-' + section).offset().top - 80 }, 200);
                }
            });

            $(document).on('click', '.jg-pg-next', function() {
                var section = $(this).data('section');
                var total = $('.jg-paginated-tbody[data-section="' + section + '"]').children('tr').length;
                var totalPages = Math.ceil(total / PER_PAGE);
                if (sectionPages[section] < totalPages) {
                    sectionPages[section]++;
                    renderPage(section);
                    $('html, body').animate({ scrollTop: $('#section-' + section).offset().top - 80 }, 200);
                }
            });

            initPagination();
            // ─────────────────────────────────────────────────────────────────
        });
        </script>
        <?php
    }

    private function render_place_row($place, $actions, $is_admin, $status = '') {
        $author_name = !empty($place['author_name']) ? $place['author_name'] : 'Nieznany';
        $reporter_name = !empty($place['reporter_name']) ? $place['reporter_name'] : 'Nieznany';
        // Convert GMT timestamps to WordPress local timezone
        $created_date = get_date_from_gmt($place['created_at'], 'Y-m-d H:i');
        $approved_date = !empty($place['approved_at']) ? get_date_from_gmt($place['approved_at'], 'Y-m-d H:i') : '-';
        $is_sponsored = $place['is_promo'] == 1 ? '⭐ Tak' : 'Nie';

        // For regular users, only show 'details' action
        if (!$is_admin) {
            $actions = array('details');
        }

        $last_modifier = !empty($place['last_modifier_name']) ? $place['last_modifier_name'] : '-';
        $last_modified_date = !empty($place['last_modified_at']) ? get_date_from_gmt($place['last_modified_at'], 'Y-m-d H:i') : '';

        ?>
        <tr>
            <td><strong><?php echo esc_html($place['title']); ?></strong></td>
            <td><?php echo esc_html($author_name); ?></td>
            <td>
                <?php echo esc_html($last_modifier); ?>
                <?php if ($last_modified_date): ?>
                    <br><span style="font-size:calc(11 * var(--jg));color:#6b7280"><?php echo esc_html($last_modified_date); ?></span>
                <?php endif; ?>
            </td>
            <?php if ($status === 'reported'): ?>
            <td><strong style="color:#dc2626"><?php echo esc_html($reporter_name); ?></strong></td>
            <?php endif; ?>
            <td><?php echo esc_html($created_date); ?></td>
            <td><?php echo esc_html($approved_date); ?></td>
            <td><span style="font-size:calc(11 * var(--jg));padding:3px 6px;background:#f3f4f6;border-radius:3px">
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
                    $histories = JG_Map_Database::get_pending_history($point_id);
                    if (!empty($histories)) {
                        // Find the edit entry
                        foreach ($histories as $h) {
                            if ($h['action_type'] === 'edit') {
                                $buttons .= sprintf(
                                    '<button class="button button-primary jg-approve-edit" data-history-id="%d">✓ Zaakceptuj</button>',
                                    $h['id']
                                );
                                break;
                            }
                        }
                    }
                    break;

                case 'reject_edit':
                    $histories = JG_Map_Database::get_pending_history($point_id);
                    if (!empty($histories)) {
                        // Find the edit entry
                        foreach ($histories as $h) {
                            if ($h['action_type'] === 'edit') {
                                $buttons .= sprintf(
                                    '<button class="button jg-reject-edit" data-history-id="%d">✗ Odrzuć</button>',
                                    $h['id']
                                );
                                break;
                            }
                        }
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

                case 'restore':
                    $buttons .= sprintf(
                        '<button class="button button-primary jg-restore-point" data-point-id="%d">↩️ Przywróć</button>',
                        $point_id
                    );
                    break;

                case 'delete_permanent':
                    $buttons .= sprintf(
                        '<button class="button jg-delete-permanent" data-point-id="%d">🗑️ Usuń trwale</button>',
                        $point_id
                    );
                    break;
            }
        }

        return $buttons;
    }

}
