<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait JG_Map_Admin_Moderation {

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

        // PERFORMANCE OPTIMIZATION: Prime user cache to avoid N+1 queries
        if (!empty($pending) && function_exists('wp_prime_user_cache')) {
            $author_ids = array_unique(array_filter(array_column($pending, 'author_id')));
            if (!empty($author_ids)) {
                wp_prime_user_cache($author_ids);
            }
        }

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

        // PERFORMANCE OPTIMIZATION: Prime user cache for edit authors
        if (!empty($edits) && function_exists('wp_prime_user_cache')) {
            $edit_author_ids = array_unique(array_filter(array_column($edits, 'user_id')));
            if (!empty($edit_author_ids)) {
                wp_prime_user_cache($edit_author_ids);
            }
        }

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
                        if (isset($old_values['tags']) && isset($new_values['tags']) && $old_values['tags'] !== $new_values['tags']) {
                            $changes[] = 'Tagi';
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
                        if (isset($old_values['address']) && isset($new_values['address']) && $old_values['address'] !== $new_values['address']) {
                            $changes[] = 'Adres';
                        }
                        if ((isset($old_values['lat']) && isset($new_values['lat']) && floatval($old_values['lat']) !== floatval($new_values['lat'])) ||
                            (isset($old_values['lng']) && isset($new_values['lng']) && floatval($old_values['lng']) !== floatval($new_values['lng']))) {
                            $changes[] = 'Pozycja na mapie';
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
                                <?php if (!empty($edit['point_owner_id']) && $edit['owner_approval_status'] !== 'approved'): ?>
                                <button class="button jg-override-owner-approval" data-id="<?php echo $edit['id']; ?>" style="background:#7c3aed;color:#fff;border-color:#7c3aed" title="Zatwierdź bez akceptacji właściciela">⚡ Obejdź właściciela</button>
                                <?php endif; ?>
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
                    if (old_values.tags !== undefined && new_values.tags !== undefined && old_values.tags !== new_values.tags) {
                        var formatTags = function(tagsVal) {
                            try {
                                var arr = typeof tagsVal === 'string' ? JSON.parse(tagsVal) : tagsVal;
                                if (Array.isArray(arr) && arr.length > 0) {
                                    return arr.map(function(t) { return '#' + t; }).join(' ');
                                }
                            } catch(e) {}
                            return '(brak)';
                        };
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Tagi</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + formatTags(old_values.tags) + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + formatTags(new_values.tags) + '</td></tr>';
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
                    if (old_values.address !== undefined && new_values.address !== undefined && old_values.address !== new_values.address) {
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>📍 Adres</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + (old_values.address || '(brak)') + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + (new_values.address || '(brak)') + '</td></tr>';
                    }
                    if ((old_values.lat !== undefined && new_values.lat !== undefined && parseFloat(old_values.lat) !== parseFloat(new_values.lat)) ||
                        (old_values.lng !== undefined && new_values.lng !== undefined && parseFloat(old_values.lng) !== parseFloat(new_values.lng))) {
                        var oldPos = (old_values.lat || '?') + ', ' + (old_values.lng || '?');
                        var newPos = (new_values.lat || '?') + ', ' + (new_values.lng || '?');
                        html += '<tr><td style="padding:8px;border:1px solid #ddd"><strong>🗺️ Pozycja na mapie</strong></td><td style="padding:8px;border:1px solid #ddd;background:#fee">' + oldPos + '</td><td style="padding:8px;border:1px solid #ddd;background:#d1fae5">' + newPos + '</td></tr>';
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

                // Override owner approval
                $('.jg-override-owner-approval').on('click', function() {
                    if (!confirm('Zatwierdź edycję bez akceptacji właściciela?\n\nWłaściciel miejsca nie zostanie zapytany o zgodę — edycja zostanie natychmiast zatwierdzona.')) return;

                    var btn = $(this);
                    var editId = btn.data('id');
                    btn.prop('disabled', true).text('Zatwierdzam...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_approve_edit',
                            history_id: editId,
                            override_owner: 1,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Edycja zatwierdzona (obejście akceptacji właściciela)!');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nieznany błąd'));
                                btn.prop('disabled', false).text('⚡ Obejdź właściciela');
                            }
                        },
                        error: function() {
                            alert('Błąd połączenia');
                            btn.prop('disabled', false).text('⚡ Obejdź właściciela');
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
                        (function() {
                            try {
                                var t = point.tags ? (typeof point.tags === 'string' ? JSON.parse(point.tags) : point.tags) : [];
                                if (Array.isArray(t) && t.length > 0) {
                                    return '<div><strong>Tagi:</strong> ' + t.map(function(tag) { return '<span style="display:inline-block;padding:2px 8px;margin:2px;border-radius:12px;background:#f3f4f6;border:1px solid #e5e7eb;font-size:calc(12 * var(--jg))">#' + tag + '</span>'; }).join('') + '</div>';
                                }
                            } catch(e) {}
                            return '';
                        })() +
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

}
