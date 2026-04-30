<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait JG_Map_Admin_Dashboard {

    public function render_main_page() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", 'publish'));
        $pending = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", 'pending'));
        $promos = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE is_promo = %d AND status = %s", 1, 'publish'));
        $deletions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE is_deletion_requested = %d", 1));

        $reports_table = JG_Map_Database::get_reports_table();
        $reports = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT r.point_id) FROM $reports_table r INNER JOIN $table p ON r.point_id = p.id WHERE r.status = %s AND p.status = %s", 'pending', 'publish'));

        // Ensure history table exists
        JG_Map_Database::ensure_history_table();

        $history_table = JG_Map_Database::get_history_table();
        $edits = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $history_table WHERE status = %s", 'pending'));

        // Liczba użytkowników pending
        $pending_users = count(get_users(array(
            'meta_key'   => 'jg_map_account_status',
            'meta_value' => 'pending',
            'number'     => -1,
            'fields'     => 'ids',
        )));

        // Liczba aktywnych kont (wszyscy zarejestrowani minus oczekujący na aktywację)
        $all_users_count = count(get_users(array(
            'number' => -1,
            'fields' => 'ids',
        )));
        $active_accounts = $all_users_count - $pending_users;
        ?>
        <style>
        /* ===== JG Admin — Dashboard ===== */
        .jg-dash-header{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:20px}
        .jg-dash-header h1{margin:0;flex:1 1 auto;font-size:22px}

        /* Stat cards */
        .jg-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px}
        .jg-stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;flex-direction:column;gap:6px;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .15s}
        .jg-stat-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-2px);color:inherit;text-decoration:none}
        .jg-stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af}
        .jg-stat-value{font-size:32px;font-weight:800;line-height:1;color:#111827}
        .jg-stat-sub{font-size:12px;color:#6b7280;margin-top:2px}
        .jg-stat-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;margin-top:4px;width:fit-content}
        .jg-stat-card.has-action .jg-stat-sub{color:#2563eb;font-weight:600}

        /* Nav sections */
        .jg-nav-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
        .jg-nav-section-title{padding:12px 18px;background:#f8fafc;border-bottom:1px solid #e5e7eb;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin:0}
        .jg-nav-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0}
        .jg-nav-item{display:flex;align-items:center;gap:10px;padding:13px 18px;border-right:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9;text-decoration:none;color:#374151;font-size:13px;font-weight:500;transition:background .12s}
        .jg-nav-item:hover{background:#f0f7ff;color:#1d4ed8;text-decoration:none}
        .jg-nav-item .jg-nav-icon{font-size:16px;flex-shrink:0;width:20px;text-align:center}

        /* Shortcode box */
        .jg-shortcode-box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px 22px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
        .jg-shortcode-box h3{margin:0 0 10px;font-size:13px;color:#374151}
        .jg-shortcode-box code{background:#f1f5f9;padding:3px 8px;border-radius:5px;font-size:12px}

        @media(max-width:782px){
            .jg-stat-grid{grid-template-columns:repeat(2,1fr)}
            .jg-stat-value{font-size:26px}
            .jg-nav-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:480px){
            .jg-stat-grid{grid-template-columns:repeat(2,1fr)}
            .jg-nav-grid{grid-template-columns:1fr}
        }
        </style>
        <div class="wrap">

            <div class="jg-dash-header">
                <h1>JG Interactive Map</h1>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="jg-back-btn" target="_blank">← Otwórz mapę</a>
            </div>

            <!-- STAT CARDS -->
            <div class="jg-stat-grid">

                <a href="<?php echo admin_url('admin.php?page=jg-map-places'); ?>" class="jg-stat-card">
                    <span class="jg-stat-label">📍 Wszystkie miejsca</span>
                    <span class="jg-stat-value"><?php echo (int)$total; ?></span>
                    <span class="jg-stat-sub">opublikowane</span>
                </a>

                <a href="<?php echo admin_url('admin.php?page=jg-map-places'); ?>" class="jg-stat-card<?php echo $pending > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">⏳ Oczekujące</span>
                    <span class="jg-stat-value" style="color:<?php echo $pending > 0 ? '#dc2626' : '#111827'; ?>"><?php echo (int)$pending; ?></span>
                    <span class="jg-stat-sub"><?php echo $pending > 0 ? '→ kliknij, aby moderować' : 'brak nowych'; ?></span>
                </a>

                <a href="<?php echo admin_url('admin.php?page=jg-map-places'); ?>" class="jg-stat-card<?php echo $edits > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">✏️ Edycje</span>
                    <span class="jg-stat-value" style="color:<?php echo $edits > 0 ? '#7c3aed' : '#111827'; ?>"><?php echo (int)$edits; ?></span>
                    <span class="jg-stat-sub"><?php echo $edits > 0 ? '→ do zatwierdzenia' : 'brak nowych'; ?></span>
                </a>

                <a href="<?php echo admin_url('admin.php?page=jg-map-places'); ?>" class="jg-stat-card<?php echo $reports > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">🚨 Zgłoszenia</span>
                    <span class="jg-stat-value" style="color:<?php echo $reports > 0 ? '#dc2626' : '#111827'; ?>"><?php echo (int)$reports; ?></span>
                    <span class="jg-stat-sub"><?php echo $reports > 0 ? '→ wymagają uwagi' : 'brak nowych'; ?></span>
                </a>

                <a href="<?php echo admin_url('admin.php?page=jg-map-places'); ?>" class="jg-stat-card<?php echo $deletions > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">🗑️ Usunięcia</span>
                    <span class="jg-stat-value" style="color:<?php echo $deletions > 0 ? '#dc2626' : '#111827'; ?>"><?php echo (int)$deletions; ?></span>
                    <span class="jg-stat-sub"><?php echo $deletions > 0 ? '→ żądania usunięcia' : 'brak żądań'; ?></span>
                </a>

                <a href="<?php echo admin_url('admin.php?page=jg-map-users'); ?>" class="jg-stat-card<?php echo $pending_users > 0 ? ' has-action' : ''; ?>">
                    <span class="jg-stat-label">👤 Aktywne konta</span>
                    <span class="jg-stat-value" style="color:#16a34a"><?php echo (int)$active_accounts; ?></span>
                    <span class="jg-stat-sub"><?php echo $pending_users > 0 ? '→ ' . (int)$pending_users . ' oczekuje na aktywację' : 'brak oczekujących'; ?></span>
                </a>

                <a href="<?php echo admin_url('admin.php?page=jg-map-promos'); ?>" class="jg-stat-card">
                    <span class="jg-stat-label">⭐ Promocje</span>
                    <span class="jg-stat-value" style="color:#f59e0b"><?php echo (int)$promos; ?></span>
                    <span class="jg-stat-sub">aktywne miejsca promo</span>
                </a>

            </div>

            <!-- NAV GRID: Moderacja -->
            <div class="jg-nav-section">
                <p class="jg-nav-section-title">Moderacja</p>
                <div class="jg-nav-grid">
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-places'); ?>">
                        <span class="jg-nav-icon">📍</span> Miejsca
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-promos'); ?>">
                        <span class="jg-nav-icon">⭐</span> Promocje
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-gallery'); ?>">
                        <span class="jg-nav-icon">🖼️</span> Galeria zdjęć
                    </a>
                </div>
            </div>

            <!-- NAV GRID: Użytkownicy -->
            <div class="jg-nav-section">
                <p class="jg-nav-section-title">Użytkownicy</p>
                <div class="jg-nav-grid">
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-users'); ?>">
                        <span class="jg-nav-icon">👥</span> Zarządzanie
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-roles'); ?>">
                        <span class="jg-nav-icon">🛡️</span> Role
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-activity-log'); ?>">
                        <span class="jg-nav-icon">📋</span> Activity Log
                    </a>
                </div>
            </div>

            <!-- NAV GRID: Treści -->
            <div class="jg-nav-section">
                <p class="jg-nav-section-title">Treści</p>
                <div class="jg-nav-grid">
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-place-categories'); ?>">
                        <span class="jg-nav-icon">🏷️</span> Kat. miejsc
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-curiosity-categories'); ?>">
                        <span class="jg-nav-icon">💡</span> Kat. ciekawostek
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-tags'); ?>">
                        <span class="jg-nav-icon">🔖</span> Tagi
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-report-reasons'); ?>">
                        <span class="jg-nav-icon">🚩</span> Powody zgłoszeń
                    </a>
                </div>
            </div>

            <!-- NAV GRID: Gamifikacja -->
            <div class="jg-nav-section">
                <p class="jg-nav-section-title">Gamifikacja</p>
                <div class="jg-nav-grid">
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-xp-editor'); ?>">
                        <span class="jg-nav-icon">⚡</span> Doświadczenie (XP)
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-achievements-editor'); ?>">
                        <span class="jg-nav-icon">🏆</span> Osiągnięcia
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-challenges'); ?>">
                        <span class="jg-nav-icon">🎯</span> Wyzwania
                    </a>
                </div>
            </div>

            <!-- NAV GRID: Konfiguracja -->
            <div class="jg-nav-section">
                <p class="jg-nav-section-title">Konfiguracja</p>
                <div class="jg-nav-grid">
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-settings'); ?>">
                        <span class="jg-nav-icon">⚙️</span> Ustawienia
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-nav-menu'); ?>">
                        <span class="jg-nav-icon">🧭</span> Menu nawigacyjne
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-maintenance'); ?>">
                        <span class="jg-nav-icon">🔧</span> Konserwacja
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-map-banners'); ?>">
                        <span class="jg-nav-icon">📢</span> Banery reklamowe
                    </a>
                    <a class="jg-nav-item" href="<?php echo admin_url('admin.php?page=jg-info-bar'); ?>">
                        <span class="jg-nav-icon">📣</span> Pasek informacyjny
                    </a>
                </div>
            </div>

            <!-- Shortcode info -->
            <div class="jg-shortcode-box">
                <h3>Shortcode</h3>
                <p style="margin:0;font-size:13px;color:#6b7280">
                    Wstaw mapę na stronie: <code>[jg_map]</code>&nbsp;&nbsp;
                    z parametrami: <code>[jg_map lat="50.904" lng="15.734" zoom="13"]</code>&nbsp;&nbsp;
                    z wysokością: <code>[jg_map height="600px"]</code>
                </p>
            </div>

        </div>
        <?php
    }

}
