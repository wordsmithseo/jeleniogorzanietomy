<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait JG_Map_Admin_Helpers {

    private function render_filter_reset_card() {
        $nonce = wp_create_nonce('jg_admin_reset_filter_prefs_nonce');
        $reset_at = (int) get_option('jg_map_filter_reset_at', 0);
        ?>
        <div class="card" style="max-width:800px;margin-top:20px;border-left:4px solid #f59e0b;background:#fffbeb;padding:18px 20px;border-radius:12px;border:1px solid #fde68a;box-shadow:0 1px 4px rgba(0,0,0,.06)">
            <h2 style="margin-top:0;padding-bottom:12px;border-bottom:1px solid #fde68a;margin-bottom:14px;font-size:15px;font-weight:700;color:#92400e">🔄 Resetowanie filtrów użytkowników</h2>
            <p style="margin:0 0 10px;color:#78350f">Zresetuj preferencje filtrów i sortowania dla <strong>wszystkich użytkowników</strong>. Po kliknięciu przycisku, przy następnym wejściu na stronę każdy użytkownik zobaczy domyślne ustawienia:</p>
            <ul style="margin:0 0 14px 20px;color:#78350f;font-size:13px">
                <li>Wszystkie typy zaznaczone (miejsca, ciekawostki, zgłoszenia)</li>
                <li>Wszystkie kategorie zaznaczone</li>
                <li>Sortowanie: <em>Ostatnio edytowane</em></li>
            </ul>
            <?php if ($reset_at > 0): ?>
            <p style="margin:0 0 12px;font-size:12px;color:#92400e">Ostatni reset: <strong><?php echo date_i18n('j F Y, G:i', $reset_at); ?></strong></p>
            <?php endif; ?>
            <button type="button" id="jg-btn-reset-filter-prefs" class="button button-secondary"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    style="background:#f59e0b;border-color:#d97706;color:#fff;font-weight:600">
                🔄 Resetuj filtry u wszystkich użytkowników
            </button>
            <span id="jg-reset-filter-prefs-msg" style="margin-left:12px;font-size:13px;display:none"></span>
        </div>
        <script>
        (function() {
            var btn = document.getElementById('jg-btn-reset-filter-prefs');
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (!confirm('Czy na pewno chcesz zresetować preferencje filtrów wszystkich użytkowników?\n\nPrzy następnym wejściu na stronę zobaczą domyślne ustawienia (wszystkie filtry zaznaczone, sortowanie po ostatnio edytowanych).')) return;
                btn.disabled = true;
                var msg = document.getElementById('jg-reset-filter-prefs-msg');
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'jg_admin_reset_filter_prefs', nonce: btn.dataset.nonce })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        msg.style.color = '#16a34a';
                        msg.textContent = '✓ Zresetowano pomyślnie. Użytkownicy zobaczą domyślne filtry przy następnym wejściu.';
                    } else {
                        msg.style.color = '#dc2626';
                        msg.textContent = '✗ Błąd: ' + (data.data || 'Nieznany błąd');
                        btn.disabled = false;
                    }
                    msg.style.display = '';
                })
                .catch(function() {
                    msg.style.color = '#dc2626';
                    msg.textContent = '✗ Błąd połączenia';
                    msg.style.display = '';
                    btn.disabled = false;
                });
            });
        })();
        </script>
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

    private function render_page_header($title) {
        $dashboard_url = admin_url('admin.php?page=jg-map-dashboard');
        ?>
        <div class="jg-page-header">
            <h1><?php echo esc_html($title); ?></h1>
            <a href="<?php echo esc_url($dashboard_url); ?>" class="jg-back-btn">&#8592; Dashboard</a>
        </div>
        <?php
    }

}
