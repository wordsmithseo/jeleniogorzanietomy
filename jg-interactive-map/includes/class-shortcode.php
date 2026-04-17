<?php
/**
 * Shortcode handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Shortcode {

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
        add_shortcode('jg_map', array($this, 'render_map'));
        add_shortcode('jg_map_sidebar', array($this, 'render_sidebar'));
        add_shortcode('jg_map_directory', array($this, 'render_directory'));
        add_shortcode('jg_banner', array($this, 'render_banner'));
    }

    /**
     * Render map shortcode
     *
     * Usage: [jg_map] or [jg_map lat="50.904" lng="15.734" zoom="13"]
     */
    public function render_map($atts) {
        $atts = shortcode_atts(
            array(
                'lat' => '50.904',
                'lng' => '15.734',
                'zoom' => '13',
                'height' => '1100px'
            ),
            $atts,
            'jg_map'
        );

        $show_advertise_link = true;
        if (is_user_logged_in()) {
            global $wpdb;
            $pts = JG_Map_Database::get_points_table();
            $show_advertise_link = !(bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $pts WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
                get_current_user_id()
            ));
        }

        ob_start();
        ?>
        <div id="jg-map-wrap" class="jg-wrap" style="position:relative; height: <?php echo esc_attr($atts['height']); ?> !important; display: grid; grid-template-rows: auto 1fr;">
            <div id="jg-map-filters-wrapper">
                <div id="jg-map-filters" class="jg-filters">
                    <label class="jg-filter-label" data-filter-type="zgloszenie"><input type="checkbox" data-type="zgloszenie" checked><span class="jg-filter-icon">⚠️</span><span class="jg-filter-text"><?php _e('Zgłoszenia', 'jg-map'); ?></span></label>
                    <label class="jg-filter-label jg-filter-label--expandable" data-filter-type="ciekawostka"><input type="checkbox" data-type="ciekawostka" checked><span class="jg-filter-icon">💡</span><span class="jg-filter-text"><?php _e('Ciekawostki', 'jg-map'); ?></span><span class="jg-filter-expand-btn" data-expand-target="curiosity-categories">▼</span></label>
                    <label class="jg-filter-label jg-filter-label--expandable" data-filter-type="miejsce"><input type="checkbox" data-type="miejsce" checked><span class="jg-filter-icon">📍</span><span class="jg-filter-text"><?php _e('Miejsca', 'jg-map'); ?></span><span class="jg-filter-expand-btn" data-expand-target="place-categories">▼</span></label>
                    <?php if ($show_advertise_link) : ?><a href="/reklama/" class="jg-partner-link" title="Dowiedz się jak promować firmę na mapie">📣 <?php _e('Reklamuj swoją firmę na mapie →', 'jg-map'); ?></a><?php endif; ?>
                    <div class="jg-search">
                        <input type="text" id="jg-search-input" placeholder="🔍 <?php _e('Szukaj po nazwie, adresie, tagach...', 'jg-map'); ?>" />
                        <button id="jg-search-btn" class="jg-search-btn" title="Szukaj">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Category filter dropdowns -->
                <div id="jg-category-filters" class="jg-category-filters" style="display:none;">
                    <div id="jg-place-categories" class="jg-category-dropdown" data-category-type="miejsce" style="display:none;">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    <div id="jg-curiosity-categories" class="jg-category-dropdown" data-category-type="ciekawostka" style="display:none;">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Search Results Side Panel -->
            <div id="jg-search-panel" class="jg-search-panel">
                <div class="jg-search-panel-header">
                    <div class="jg-search-panel-header-top">
                        <h3 id="jg-search-panel-title">Wyniki wyszukiwania</h3>
                        <span id="jg-search-panel-count" class="jg-search-count"></span>
                    </div>
                    <button class="jg-search-panel-header-close jg-btn jg-btn--secondary" type="button" onclick="document.getElementById('jg-search-close-btn').click()"><?php _e('Zakończ wyszukiwanie', 'jg-map'); ?></button>
                </div>
                <div id="jg-search-results" class="jg-search-results"></div>
                <div class="jg-search-panel-footer">
                    <button id="jg-search-close-btn" class="jg-btn jg-btn--secondary">Zakończ wyszukiwanie</button>
                </div>
            </div>

            <!-- Mobile floating overlays: user panel + controls row + banner (JS-injected) -->
            <div id="jg-mobile-overlays" class="jg-mobile-overlays">
                <?php
                /* ── Mobile user panel ── */
                if (is_user_logged_in()) :
                    $mup_user        = wp_get_current_user();
                    $mup_is_admin    = current_user_can('manage_options') || current_user_can('jg_map_admin');
                    $mup_is_mod      = !$mup_is_admin && current_user_can('jg_map_moderate');
                    global $wpdb;
                    $mup_pts_tbl     = JG_Map_Database::get_points_table();
                    $mup_has_spon    = (bool) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $mup_pts_tbl WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
                        $mup_user->ID
                    ));

                    /* Role badge */
                    $mup_role_badge  = '';
                    if ($mup_is_admin) {
                        $mup_role_badge = '<span class="jg-mup-role jg-mup-role--admin" title="Administrator"><svg width="12" height="12" viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>';
                    } elseif ($mup_is_mod) {
                        $mup_role_badge = '<span class="jg-mup-role jg-mup-role--mod" title="Moderator"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#93c5fd" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>';
                    }
                    if ($mup_has_spon) {
                        $mup_role_badge .= '<span class="jg-mup-role jg-mup-role--spon" title="Użytkownik sponsorowany">$</span>';
                    }

                    /* Level & XP */
                    $mup_xp_data     = JG_Map_Levels_Achievements::get_user_xp_data($mup_user->ID);
                    $mup_level       = $mup_xp_data['level'];
                    $mup_xp          = $mup_xp_data['xp'];
                    $mup_cur_xp      = JG_Map_Levels_Achievements::xp_for_level($mup_level);
                    $mup_next_xp     = JG_Map_Levels_Achievements::xp_for_level($mup_level + 1);
                    $mup_xp_in_lvl   = $mup_xp - $mup_cur_xp;
                    $mup_xp_needed   = $mup_next_xp - $mup_cur_xp;
                    $mup_progress    = $mup_xp_needed > 0 ? min(100, round(($mup_xp_in_lvl / $mup_xp_needed) * 100)) : 100;

                    if ($mup_level >= 50)      $mup_tier = 'prestige-legend';
                    elseif ($mup_level >= 40)  $mup_tier = 'prestige-ruby';
                    elseif ($mup_level >= 30)  $mup_tier = 'prestige-diamond';
                    elseif ($mup_level >= 20)  $mup_tier = 'prestige-purple';
                    elseif ($mup_level >= 15)  $mup_tier = 'prestige-emerald';
                    elseif ($mup_level >= 10)  $mup_tier = 'prestige-gold';
                    elseif ($mup_level >= 5)   $mup_tier = 'prestige-silver';
                    else                       $mup_tier = 'prestige-bronze';
                ?>
                <?php
                    /* ── Moderation notifications for mobile ── */
                    $mup_mod_notifs = array();
                    if ($mup_is_admin || $mup_is_mod) {
                        $mup_history_tbl = JG_Map_Database::get_history_table();
                        $mup_reports_tbl = JG_Map_Database::get_reports_table();
                        $mup_pending_points = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $mup_pts_tbl WHERE status = %s", 'pending'
                        ));
                        $mup_pending_edits = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $mup_history_tbl WHERE status = %s AND action_type = %s", 'pending', 'edit'
                        ));
                        $mup_pending_reports = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(DISTINCT r.point_id) FROM $mup_reports_tbl r
                             INNER JOIN $mup_pts_tbl p ON r.point_id = p.id
                             WHERE r.status = %s AND p.status = %s", 'pending', 'publish'
                        ));
                        $mup_pending_deletions = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $mup_pts_tbl WHERE is_deletion_requested = %d AND status = %s", 1, 'publish'
                        ));
                        if ($mup_pending_points > 0) {
                            $mup_mod_notifs[] = array(
                                'type'  => 'points',
                                'title' => 'Nowe miejsca (' . $mup_pending_points . ')',
                                'count' => $mup_pending_points,
                                'url'   => admin_url('admin.php?page=jg-map-places#section-new_pending'),
                                'icon'  => '<path d="M12 5v14M5 12h14"/>',
                            );
                        }
                        if ($mup_pending_edits > 0) {
                            $mup_mod_notifs[] = array(
                                'type'  => 'edits',
                                'title' => 'Edycje (' . $mup_pending_edits . ')',
                                'count' => $mup_pending_edits,
                                'url'   => admin_url('admin.php?page=jg-map-places#section-edit_pending'),
                                'icon'  => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
                            );
                        }
                        if ($mup_pending_reports > 0) {
                            $mup_mod_notifs[] = array(
                                'type'  => 'reports',
                                'title' => 'Zgłoszenia (' . $mup_pending_reports . ')',
                                'count' => $mup_pending_reports,
                                'url'   => admin_url('admin.php?page=jg-map-places#section-reported'),
                                'icon'  => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                            );
                        }
                        if ($mup_pending_deletions > 0) {
                            $mup_mod_notifs[] = array(
                                'type'  => 'deletions',
                                'title' => 'Usunięcia (' . $mup_pending_deletions . ')',
                                'count' => $mup_pending_deletions,
                                'url'   => admin_url('admin.php?page=jg-map-places#section-deletion_pending'),
                                'icon'  => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>',
                            );
                        }
                    }
                ?>
                <div id="jg-mobile-user-panel" class="jg-mobile-user-panel">
                    <div class="jg-mup-main-row">
                        <div class="jg-mup-info">
                            <a href="#" id="jg-mup-username-link" class="jg-mup-username" data-user-id="<?php echo esc_attr($mup_user->ID); ?>"><?php echo esc_html($mup_user->display_name); ?></a>
                            <?php echo $mup_role_badge; ?>
                            <div class="jg-mup-level jg-level-<?php echo $mup_tier; ?>" title="Poziom <?php echo $mup_level; ?> — <?php echo $mup_xp_in_lvl; ?>/<?php echo $mup_xp_needed; ?> XP">
                                <span class="jg-mup-level-num">Poz.&nbsp;<?php echo $mup_level; ?></span>
                                <span class="jg-mup-xp-bar"><span class="jg-mup-xp-fill" style="width:<?php echo $mup_progress; ?>%"></span></span>
                            </div>
                        </div>
                        <div class="jg-mup-actions">
                            <button id="jg-mup-ranking-btn" class="jg-mup-btn" title="Ranking">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                            </button>
                            <button id="jg-mup-profile-btn" class="jg-mup-btn" title="Edytuj profil">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </button>
                            <?php if ($mup_is_admin || $mup_is_mod) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jg-map-dashboard')); ?>" class="jg-mup-btn jg-mup-btn--admin" title="Panel administratora">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="jg-mup-btn" title="Wyloguj">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            </a>
                        </div>
                    </div>
                    <!-- Moderation notifications row (hidden when empty, updated by JS) -->
                    <div id="jg-mup-notifications" class="jg-mup-notifications<?php echo empty($mup_mod_notifs) ? ' jg-mup-notifications--empty' : ''; ?>">
                        <?php foreach ($mup_mod_notifs as $n) : ?>
                        <a href="<?php echo esc_url($n['url']); ?>" class="jg-mup-notif-btn" data-type="<?php echo esc_attr($n['type']); ?>" title="<?php echo esc_attr($n['title']); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $n['icon']; ?></svg>
                            <span class="jg-mup-notif-badge"><?php echo $n['count']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else : ?>
                <div id="jg-mobile-user-panel" class="jg-mobile-user-panel jg-mobile-user-panel--guest">
                    <button id="jg-mup-auth-btn" class="jg-mup-login-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Zaloguj się
                    </button>
                </div>
                <?php endif; ?>

                <!-- Banner slot: JS moves this into .jg-mobile-banner-slot above controls row (mobile)
                     and into deskPromoWrap next to map/satellite selector (desktop wide mode).
                     Rendered here so it exists on every map page without a separate [jg_banner] shortcode. -->
                <?php echo $this->render_banner([]); ?>
            </div>

            <!-- Full-screen loader covering map + sidebar until everything is ready -->
            <div id="jg-map-loading" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:999999;background:#fff;pointer-events:all;transition:opacity 0.3s;overflow:hidden;">
                <div class="jg-loader-pins">
                    <!-- Blue pin (ciekawostka) -->
                    <svg class="jg-loader-pin" width="32" height="40" viewBox="0 0 32 40" xmlns="http://www.w3.org/2000/svg" style="animation-delay:0s">
                        <defs><linearGradient id="jg-ldr-blue" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#1e40af"/><stop offset="50%" style="stop-color:#3b82f6"/><stop offset="100%" style="stop-color:#1e40af"/></linearGradient></defs>
                        <path d="M16 0 C7.163 0 0 7.163 0 16 C0 19 1 22 4 26 L16 40 L28 26 C31 22 32 19 32 16 C32 7.163 24.837 0 16 0 Z" fill="url(#jg-ldr-blue)"/>
                        <circle cx="16" cy="16" r="5.5" fill="#1e3a8a"/>
                    </svg>
                    <!-- Green pin (miejsce) -->
                    <svg class="jg-loader-pin" width="32" height="40" viewBox="0 0 32 40" xmlns="http://www.w3.org/2000/svg" style="animation-delay:0.2s">
                        <defs><linearGradient id="jg-ldr-green" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#15803d"/><stop offset="50%" style="stop-color:#22c55e"/><stop offset="100%" style="stop-color:#15803d"/></linearGradient></defs>
                        <path d="M16 0 C7.163 0 0 7.163 0 16 C0 19 1 22 4 26 L16 40 L28 26 C31 22 32 19 32 16 C32 7.163 24.837 0 16 0 Z" fill="url(#jg-ldr-green)"/>
                        <circle cx="16" cy="16" r="5.5" fill="#0a5a28"/>
                    </svg>
                    <!-- Black pin (zgłoszenie) -->
                    <svg class="jg-loader-pin" width="32" height="40" viewBox="0 0 32 40" xmlns="http://www.w3.org/2000/svg" style="animation-delay:0.4s">
                        <defs><linearGradient id="jg-ldr-black" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#000"/><stop offset="50%" style="stop-color:#1f1f1f"/><stop offset="100%" style="stop-color:#000"/></linearGradient></defs>
                        <path d="M16 0 C7.163 0 0 7.163 0 16 C0 19 1 22 4 26 L16 40 L28 26 C31 22 32 19 32 16 C32 7.163 24.837 0 16 0 Z" fill="url(#jg-ldr-black)"/>
                        <circle cx="16" cy="16" r="5.5" fill="#888"/>
                    </svg>
                </div>
                <div id="jg-map-loading-text" style="margin-top:24px;font-size:clamp(18px,4vw,26px);color:#111;font-weight:700;letter-spacing:0.01em;text-align:center;padding:0 16px;line-height:1.4"><?php _e('Ładowanie mapy...', 'jg-map'); ?></div>
            </div>
            <script>
            // Move loader to <body> immediately so parent overflow:hidden/clip doesn't clip it
            (function(){
              var e=document.getElementById('jg-map-loading');
              if(e)document.body.appendChild(e);
              // Block page scroll while loader is visible (prevents scrollbar showing behind fixed overlay)
              document.documentElement.style.setProperty('overflow','hidden','important');
              // If coming from a pin page, show a more relevant loading message
              try {
                if(new URLSearchParams(window.location.search).get('from')==='point'){
                  var t=document.getElementById('jg-map-loading-text');
                  if(t)t.textContent='Trwa ładowanie pineski, prosimy czekać\u2026';
                }
              } catch(ex){}
            })();
            </script>

            <div id="jg-map" class="jg-map" style="opacity: 0; transition: opacity 0.3s;"
                 data-lat="<?php echo esc_attr($atts['lat']); ?>"
                 data-lng="<?php echo esc_attr($atts['lng']); ?>"
                 data-zoom="<?php echo esc_attr($atts['zoom']); ?>">
                <div id="jg-map-error" style="display:none;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1000;background:#fee;padding:20px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.15);color:#c00;max-width:400px">
                    <strong><?php _e('Błąd ładowania mapy', 'jg-map'); ?></strong><br>
                    <span id="error-msg"></span>
                </div>
            </div>

            <!-- Modals -->
            <div id="jg-map-modal-add" class="jg-modal-bg"><div class="jg-modal"></div></div>
            <div id="jg-map-modal-view" class="jg-modal-bg"><div class="jg-modal"></div></div>
            <div id="jg-map-modal-report" class="jg-modal-bg"><div class="jg-modal"></div></div>
            <div id="jg-map-modal-reports-list" class="jg-modal-bg"><div class="jg-modal"></div></div>
            <div id="jg-map-modal-edit" class="jg-modal-bg"><div class="jg-modal"></div></div>
            <div id="jg-map-modal-author" class="jg-modal-bg"><div class="jg-modal"></div></div>
            <div id="jg-map-modal-status" class="jg-modal-bg"><div class="jg-modal"></div></div>
            <div id="jg-map-modal-ranking" class="jg-modal-bg"><div class="jg-modal"></div></div>
            <div id="jg-map-lightbox" class="jg-modal-bg"><div class="jg-lightbox"></div></div>
            <div id="jg-place-contact-modal" class="jg-modal-bg"><div class="jg-modal"></div></div>

            <!-- Message Modals (for alert/confirm replacements) -->
            <div id="jg-modal-alert" class="jg-modal-bg" style="z-index:99999">
                <div class="jg-modal jg-modal-message" style="max-width:500px">
                    <div class="jg-modal-message-content"></div>
                    <div class="jg-modal-message-buttons"></div>
                </div>
            </div>

            <!-- Help Panel (moved into #jg-map by JS) -->
            <div id="jg-help-panel" class="jg-help-panel" style="display:none">
                <div class="jg-help-panel-header">
                    <h3><?php _e('Jak korzystać z mapy?', 'jg-map'); ?></h3>
                    <button id="jg-help-panel-close" class="jg-close">&times;</button>
                </div>
                <div class="jg-help-panel-body">
                    <div class="jg-help-section">
                        <h4><?php _e('Typy punktów', 'jg-map'); ?></h4>
                        <div class="jg-help-types">
                            <div class="jg-help-type">
                                <span class="jg-help-type-icon"><span class="jg-pin-dot jg-pin-dot--zgloszenie"></span></span>
                                <div>
                                    <strong><?php _e('Zgłoszenie', 'jg-map'); ?></strong>
                                    <p><?php _e('Problemy infrastrukturalne, bezpieczeństwo, dziury w drogach, uszkodzone chodniki, nielegalne wysypiska.', 'jg-map'); ?></p>
                                </div>
                            </div>
                            <div class="jg-help-type">
                                <span class="jg-help-type-icon"><span class="jg-pin-dot jg-pin-dot--ciekawostka"></span></span>
                                <div>
                                    <strong><?php _e('Ciekawostka', 'jg-map'); ?></strong>
                                    <p><?php _e('Ciekawe miejsca, historia, architektura, legendy i opowieści z okolicy.', 'jg-map'); ?></p>
                                </div>
                            </div>
                            <div class="jg-help-type">
                                <span class="jg-help-type-icon"><span class="jg-pin-dot jg-pin-dot--miejsce"></span></span>
                                <div>
                                    <strong><?php _e('Miejsce', 'jg-map'); ?></strong>
                                    <p><?php _e('Gastronomia, kultura, usługi, sport, zabytki, przyroda i inne ważne lokalizacje.', 'jg-map'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="jg-help-section">
                        <h4><?php _e('Dodawanie punktów', 'jg-map'); ?></h4>
                        <ol class="jg-help-steps">
                            <li><?php _e('Zaloguj się na swoje konto', 'jg-map'); ?></li>
                            <li><?php _e('Przybliż mapę do maksymalnego poziomu (zoom 17+)', 'jg-map'); ?></li>
                            <li><?php _e('Kliknij na mapę w wybranym miejscu', 'jg-map'); ?></li>
                            <li><?php _e('Wypełnij formularz i dodaj zdjęcia', 'jg-map'); ?></li>
                            <li><?php _e('Punkt trafi do moderacji i pojawi się po zatwierdzeniu', 'jg-map'); ?></li>
                        </ol>
                        <p class="jg-help-tip"><?php _e('Możesz też użyć przycisku + w prawym dolnym rogu mapy, aby szybko dodać punkt po adresie.', 'jg-map'); ?></p>
                    </div>
                    <div class="jg-help-section">
                        <h4><?php _e('Inne funkcje', 'jg-map'); ?></h4>
                        <ul class="jg-help-features">
                            <li><strong><?php _e('Ocenianie', 'jg-map'); ?></strong> <?php _e('przyznaj 1–5 gwiazdek każdemu miejscu lub ciekawostce — otwórz szczegóły punktu i wybierz ocenę', 'jg-map'); ?></li>
                            <li><strong><?php _e('Zdjęcia', 'jg-map'); ?></strong> <?php _e('dodaj własne zdjęcie do istniejącego punktu — otwórz szczegóły i kliknij przycisk aparatu', 'jg-map'); ?></li>
                            <li><strong><?php _e('Filtrowanie', 'jg-map'); ?></strong> <?php _e('użyj checkboxów nad mapą, aby pokazać/ukryć typy punktów i kategorie', 'jg-map'); ?></li>
                            <li><strong><?php _e('Wyszukiwanie', 'jg-map'); ?></strong> <?php _e('wpisz nazwę w pole wyszukiwania, aby szybko znaleźć punkt na mapie', 'jg-map'); ?></li>
                            <li><strong><?php _e('Zgłaszanie', 'jg-map'); ?></strong> <?php _e('zgłoś nieodpowiednią treść przyciskiem w szczegółach punktu', 'jg-map'); ?></li>
                            <li><strong><?php _e('Edycja', 'jg-map'); ?></strong> <?php _e('edytuj własne punkty (zmiany wymagają ponownej moderacji)', 'jg-map'); ?></li>
                        </ul>
                    </div>
                    <div class="jg-help-section">
                        <h4><?php _e('XP i poziomy', 'jg-map'); ?></h4>
                        <ul class="jg-help-xp-list">
                            <li><span><?php _e('Dodaj punkt', 'jg-map'); ?></span><strong>+50 XP</strong></li>
                            <li><span><?php _e('Punkt zatwierdzony', 'jg-map'); ?></span><strong>+30 XP</strong></li>
                            <li><span><?php _e('Dodaj zdjęcie', 'jg-map'); ?></span><strong>+10 XP</strong></li>
                            <li><span><?php _e('Edytuj punkt', 'jg-map'); ?></span><strong>+15 XP</strong></li>
                            <li><span><?php _e('Oceń punkt', 'jg-map'); ?></span><strong>+2 XP</strong></li>
                        </ul>
                        <p class="jg-help-tip"><?php _e('Awansujesz przez 8 poziomów: Brąz → Srebro → Złoto → Szmaragd → Fiolet → Diament → Rubin → Legenda. Twój poziom i pasek XP widoczne są na pasku u góry strony.', 'jg-map'); ?></p>
                    </div>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render directory shortcode - crawlable HTML listing of all published points.
     * Provides internal links that Google needs to discover and index pin pages.
     *
     * Usage: [jg_map_directory] or [jg_map_directory per_page="50"]
     */
    public function render_directory($atts) {
        $atts = shortcode_atts(
            array(
                'per_page' => 50,
            ),
            $atts,
            'jg_map_directory'
        );

        $per_page = max(10, min(100, (int) $atts['per_page']));
        $current_page = max(1, (int) (isset($_GET['katalog-strona']) ? $_GET['katalog-strona'] : 1));

        // Tag filter - prefer clean URL query var, fall back to ?tag= for compat
        $active_tag = JG_Interactive_Map::resolve_catalog_tag();
        if ($active_tag === '' && isset($_GET['tag'])) {
            $active_tag = sanitize_text_field(wp_unslash($_GET['tag']));
        }

        // Category filter from clean URL query var
        $active_category = JG_Interactive_Map::resolve_catalog_category();

        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Build WHERE clause
        $where = "status = 'publish' AND slug IS NOT NULL AND slug != ''";
        $where_args = array();

        if ($active_tag !== '') {
            // Filter by tag - search inside JSON array
            // Match tag as exact element: ["..","tag",".."] or ["tag"] or ["tag",..] or [..,"tag"]
            $like_pattern = '%' . $wpdb->esc_like('"' . $active_tag . '"') . '%';
            $where .= " AND tags LIKE %s";
            $where_args[] = $like_pattern;
        }

        if ($active_category !== '') {
            $where .= " AND category = %s";
            $where_args[] = $active_category;
        }

        // Get total count
        if (!empty($where_args)) {
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE $where",
                ...$where_args
            ));
        } else {
            $total = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM $table WHERE $where"
            );
        }

        if ($total === 0 && $active_category !== '') {
            $catalog_base = home_url('/katalog/');
            ob_start();
            echo '<div class="jg-directory">';
            $cat_title = JG_Interactive_Map::get_instance()->get_category_seo_title_public($active_category);
            echo '<h1 class="jg-dir-h1">' . esc_html($cat_title) . '</h1>';
            $this->render_category_cloud($table, $catalog_base, $active_category);
            echo '<p style="color:#6b7280;margin-top:16px">Brak miejsc w kategorii <strong>' . esc_html($active_category) . '</strong>.</p>';
            echo '<p><a href="' . esc_url($catalog_base) . '" style="color:#2563eb">Pokaż wszystkie miejsca</a></p>';
            echo '</div>';
            return ob_get_clean();
        }

        if ($total === 0 && $active_tag !== '') {
            // No results for tag filter - show message + tag cloud
            $catalog_base = home_url('/katalog/');
            ob_start();
            echo '<div class="jg-directory">';
            echo '<h1 class="jg-dir-h1">#' . esc_html($active_tag) . ' – Miejsca w Jeleniej Górze</h1>';
            $this->render_tag_cloud($table, $catalog_base, $active_tag);
            echo '<p style="color:#6b7280;margin-top:16px">Brak miejsc z tagiem <strong>#' . esc_html($active_tag) . '</strong>.</p>';
            echo '<p><a href="' . esc_url($catalog_base) . '" style="color:#2563eb">Pokaż wszystkie miejsca</a></p>';
            echo '</div>';
            return ob_get_clean();
        }

        if ($total === 0) {
            return '<p>Brak miejsc w katalogu.</p>';
        }

        $total_pages = (int) ceil($total / $per_page);
        $current_page = min($current_page, $total_pages);
        $offset = ($current_page - 1) * $per_page;

        // Get paginated points
        $query_args = array_merge($where_args, array($per_page, $offset));
        $points = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT title, slug, type, address, tags
                 FROM $table
                 WHERE $where
                 ORDER BY type ASC, title ASC
                 LIMIT %d OFFSET %d",
                ...$query_args
            ),
            ARRAY_A
        );

        $type_labels = array(
            'miejsce' => 'Miejsca',
            'ciekawostka' => 'Ciekawostki',
            'zgloszenie' => 'Zgłoszenia'
        );
        $type_paths = array(
            'miejsce' => 'miejsce',
            'ciekawostka' => 'ciekawostka',
            'zgloszenie' => 'zgloszenie'
        );

        // Group by type
        $grouped = array();
        foreach ($points as $p) {
            $grouped[$p['type']][] = $p;
        }

        // Base URL for pagination: category > tag > plain catalog
        if ($active_category !== '') {
            $base_url = JG_Interactive_Map::get_category_url($active_category);
        } elseif ($active_tag !== '') {
            $base_url = JG_Interactive_Map::get_tag_url($active_tag);
        } else {
            $base_url = home_url('/katalog/');
        }
        // Base URL without tag/category (for cloud links and "remove filter")
        $base_url_no_tag = home_url('/katalog/');

        ob_start();
        ?>
        <div class="jg-directory">
            <?php if ($active_category !== ''): ?>
                <h1 class="jg-dir-h1"><?php echo esc_html(JG_Interactive_Map::get_instance()->get_category_seo_title_public($active_category)); ?></h1>
                <?php
                $cat_intro = JG_Interactive_Map::get_instance()->get_category_intro_public($active_category, $total);
                if ($cat_intro !== ''):
                ?>
                <p class="jg-dir-intro"><?php echo wp_kses_post($cat_intro); ?></p>
                <?php endif; ?>
            <?php elseif ($active_tag !== ''): ?>
                <h1 class="jg-dir-h1">#<?php echo esc_html($active_tag); ?> – Miejsca w Jeleniej Górze</h1>
            <?php else: ?>
                <h1 class="jg-dir-h1">Katalog miejsc w Jeleniej Górze</h1>
            <?php endif; ?>
            <style>
                .jg-directory { padding: 4px 0; }
                .jg-dir-h1 { font-size: calc(22 * var(--jg)); font-weight: 700; color: #111827; margin: 0 0 12px; line-height: 1.3; }
                .jg-dir-intro { font-size: calc(14.5 * var(--jg)); color: #374151; line-height: 1.65; margin: 0 0 20px; max-width: 680px; }
                .jg-dir-section { margin-bottom: 24px; }
                .jg-dir-section h3 { font-size: calc(12.8 * var(--jg)); font-weight: 600; color: #6b7280; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.5px; }
                .jg-dir-list { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 0; }
                .jg-dir-item { font-size: calc(14 * var(--jg)); line-height: 1.6; padding: 4px 0; }
                .jg-dir-item:not(:last-child)::after { content: "·"; margin: 0 10px; color: #d1d5db; }
                .jg-dir-item a { color: #2563eb; text-decoration: none; }
                .jg-dir-item a:hover { text-decoration: underline; }
                .jg-dir-addr { color: #9ca3af; font-size: calc(12 * var(--jg)); }
                .jg-dir-tag-cloud { margin-bottom: 24px; }
                .jg-dir-tag-cloud h3 { font-size: calc(12.8 * var(--jg)); font-weight: 600; color: #6b7280; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.5px; }
                .jg-dir-tag-list { display: flex; flex-wrap: wrap; gap: 6px; list-style: none; margin: 0; padding: 0; }
                .jg-dir-tag-item { display: inline-block; }
                .jg-dir-tag-item a {
                    display: inline-block; padding: 4px 12px; border-radius: 16px;
                    font-size: calc(13 * var(--jg)); text-decoration: none; color: #374151;
                    background: #f3f4f6; border: 1px solid #e5e7eb; transition: all 0.15s;
                }
                .jg-dir-tag-item a:hover { background: #8d2324; color: #fff; border-color: #8d2324; }
                .jg-dir-tag-item a.jg-dir-tag-active { background: #8d2324; color: #fff; border-color: #8d2324; }
                .jg-dir-tag-count { font-size: calc(11 * var(--jg)); color: #9ca3af; margin-left: 2px; }
                .jg-dir-tag-active .jg-dir-tag-count { color: rgba(255,255,255,0.7); }
                .jg-dir-active-filter { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; padding: 10px 16px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; font-size: calc(14 * var(--jg)); color: #78350f; }
                .jg-dir-active-filter a { color: #8d2324; font-weight: 600; text-decoration: none; }
                .jg-dir-active-filter a:hover { text-decoration: underline; }
                .jg-dir-pagination { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
                .jg-dir-pagination a, .jg-dir-pagination span {
                    display: inline-flex; align-items: center; justify-content: center;
                    min-width: 36px; height: 36px; padding: 0 10px;
                    border-radius: 8px; font-size: calc(14 * var(--jg)); font-weight: 500; text-decoration: none;
                }
                .jg-dir-pagination a { color: #2563eb; background: #f3f4f6; }
                .jg-dir-pagination a:hover { background: #e5e7eb; }
                .jg-dir-pagination .current { color: #fff; background: #2563eb; font-weight: 700; }
                .jg-dir-pagination .dots { color: #9ca3af; background: none; }
                .jg-dir-info { font-size: calc(13 * var(--jg)); color: #9ca3af; margin-top: 8px; }
            </style>

            <?php $this->render_category_cloud($table, $base_url_no_tag, $active_category); ?>
            <?php $this->render_tag_cloud($table, $base_url_no_tag, $active_tag); ?>

            <?php if ($active_category !== ''): ?>
                <div class="jg-dir-active-filter">
                    Kategoria: <strong><?php echo esc_html($active_category); ?></strong>
                    <a href="<?php echo esc_url($base_url_no_tag); ?>">Usuń filtr &times;</a>
                    <span style="color:#9ca3af;margin-left:auto;font-size:calc(12 * var(--jg))"><?php echo $total; ?> <?php echo $total === 1 ? 'wynik' : ($total < 5 ? 'wyniki' : 'wyników'); ?></span>
                </div>
            <?php elseif ($active_tag !== ''): ?>
                <div class="jg-dir-active-filter">
                    Filtrowanie po tagu: <strong>#<?php echo esc_html($active_tag); ?></strong>
                    <a href="<?php echo esc_url($base_url_no_tag); ?>">Usuń filtr &times;</a>
                    <span style="color:#9ca3af;margin-left:auto;font-size:calc(12 * var(--jg))"><?php echo $total; ?> <?php echo $total === 1 ? 'wynik' : ($total < 5 ? 'wyniki' : 'wyników'); ?></span>
                </div>
            <?php endif; ?>

            <?php foreach ($type_labels as $type => $label): ?>
                <?php if (!empty($grouped[$type])): ?>
                    <div class="jg-dir-section">
                        <h3><?php echo esc_html($label); ?></h3>
                        <ul class="jg-dir-list">
                            <?php foreach ($grouped[$type] as $p):
                                $path = isset($type_paths[$p['type']]) ? $type_paths[$p['type']] : 'miejsce';
                                $url = home_url('/' . $path . '/' . $p['slug'] . '/');
                            ?>
                                <li class="jg-dir-item">
                                    <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($p['title']); ?></a><?php
                                    if (!empty($p['address'])): ?> <span class="jg-dir-addr"><?php echo esc_html($p['address']); ?></span><?php endif;
                                    $p_tags = !empty($p['tags']) ? json_decode($p['tags'], true) : array();
                                    if (!empty($p_tags)): ?> <span class="jg-dir-tags"><?php foreach ($p_tags as $pt): ?><a href="<?php echo esc_url(JG_Interactive_Map::get_tag_url($pt)); ?>" class="jg-dir-tag-inline" rel="tag">#<?php echo esc_html($pt); ?></a> <?php endforeach; ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
                <nav class="jg-dir-pagination" aria-label="Paginacja katalogu">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo esc_url(add_query_arg('katalog-strona', $current_page - 1, $base_url)); ?>">&larr;</a>
                    <?php endif; ?>

                    <?php
                    // Show pagination with ellipsis for large page counts
                    $range = 2; // pages around current
                    for ($i = 1; $i <= $total_pages; $i++):
                        if ($i === 1 || $i === $total_pages || abs($i - $current_page) <= $range):
                            if ($i === $current_page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo esc_url(add_query_arg('katalog-strona', $i, $base_url)); ?>"><?php echo $i; ?></a>
                            <?php endif;
                        elseif ($i === 2 || $i === $total_pages - 1): ?>
                            <span class="dots">&hellip;</span>
                        <?php endif;
                    endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo esc_url(add_query_arg('katalog-strona', $current_page + 1, $base_url)); ?>">&rarr;</a>
                    <?php endif; ?>
                </nav>
                <div class="jg-dir-info">Strona <?php echo $current_page; ?> z <?php echo $total_pages; ?> &middot; <?php echo $total; ?> miejsc</div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render tag cloud for directory
     */
    private function render_tag_cloud($table, $base_url, $active_tag = '') {
        global $wpdb;

        $all_tag_counts = array();
        $tag_rows = $wpdb->get_col(
            "SELECT tags FROM $table WHERE status = 'publish' AND tags IS NOT NULL AND tags != ''"
        );
        foreach ($tag_rows as $tags_json) {
            $tag_list = json_decode($tags_json, true);
            if (is_array($tag_list)) {
                foreach ($tag_list as $tag) {
                    $tag = trim($tag);
                    if ($tag !== '') {
                        $lower = mb_strtolower($tag);
                        if (!isset($all_tag_counts[$lower])) {
                            $all_tag_counts[$lower] = array('label' => $tag, 'count' => 0);
                        }
                        $all_tag_counts[$lower]['count']++;
                    }
                }
            }
        }

        if (empty($all_tag_counts)) {
            return;
        }

        uasort($all_tag_counts, function($a, $b) { return $b['count'] - $a['count']; });
        ?>
        <nav class="jg-dir-tag-cloud" aria-label="Chmurka tagów">
            <h3>Tagi</h3>
            <ul class="jg-dir-tag-list">
                <?php foreach ($all_tag_counts as $tag_data):
                    $is_active = ($active_tag !== '' && mb_strtolower($active_tag) === mb_strtolower($tag_data['label']));
                    $tag_url = $is_active ? $base_url : JG_Interactive_Map::get_tag_url($tag_data['label']);
                ?>
                    <li class="jg-dir-tag-item">
                        <a href="<?php echo esc_url($tag_url); ?>" rel="tag"<?php echo $is_active ? ' class="jg-dir-tag-active"' : ''; ?>>#<?php echo esc_html($tag_data['label']); ?><span class="jg-dir-tag-count">(<?php echo intval($tag_data['count']); ?>)</span></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
    }

    /**
     * Render category cloud for the directory.
     */
    private function render_category_cloud($table, $base_url, $active_category = '') {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT category, COUNT(*) as cnt FROM $table WHERE status = 'publish' AND type = 'miejsce' AND category IS NOT NULL AND category != '' GROUP BY category ORDER BY cnt DESC, category ASC",
            ARRAY_A
        );

        if (empty($rows)) {
            return;
        }
        ?>
        <nav class="jg-dir-tag-cloud" aria-label="Kategorie miejsc">
            <h3>Kategorie</h3>
            <ul class="jg-dir-tag-list">
                <?php foreach ($rows as $row):
                    $cat   = $row['category'];
                    $cnt   = (int) $row['cnt'];
                    $is_active = ($active_category !== '' && $active_category === $cat);
                    $cat_url   = $is_active ? $base_url : JG_Interactive_Map::get_category_url($cat);
                ?>
                    <li class="jg-dir-tag-item">
                        <a href="<?php echo esc_url($cat_url); ?>"<?php echo $is_active ? ' class="jg-dir-tag-active"' : ''; ?>><?php echo esc_html($cat); ?><span class="jg-dir-tag-count">(<?php echo $cnt; ?>)</span></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
    }

    /**
     * Render sidebar shortcode
     *
     * Usage: [jg_map_sidebar] or [jg_map_sidebar height="800px"]
     */
    public function render_sidebar($atts) {
        $atts = shortcode_atts(
            array(
                'title' => 'Lista miejsc',
                'height' => '80dvh'
            ),
            $atts,
            'jg_map_sidebar'
        );

        ob_start();
        ?>
        <div id="jg-map-sidebar" class="jg-map-sidebar" style="height: <?php echo esc_attr($atts['height']); ?> !important; opacity: 0; transition: opacity 0.3s ease;">

            <!-- Challenge Widget (desktop) - populated by JS from CFG.activeChallenge -->
            <div id="jg-challenge-widget-desktop" class="jg-challenge-widget-desktop" style="display:none"></div>

            <!-- Statistics Summary -->
            <div class="jg-sidebar-stats">
                <div class="jg-sidebar-stat">
                    <span class="jg-sidebar-stat-icon">📊</span>
                    <span class="jg-sidebar-stat-value" id="jg-sidebar-stat-total">0</span>
                </div>
                <div class="jg-sidebar-stat">
                    <span class="jg-sidebar-stat-icon">📍</span>
                    <span class="jg-sidebar-stat-value" id="jg-sidebar-stat-miejsce">0</span>
                </div>
                <div class="jg-sidebar-stat">
                    <span class="jg-sidebar-stat-icon">💡</span>
                    <span class="jg-sidebar-stat-value" id="jg-sidebar-stat-ciekawostka">0</span>
                </div>
                <div class="jg-sidebar-stat">
                    <span class="jg-sidebar-stat-icon">⚠️</span>
                    <span class="jg-sidebar-stat-value" id="jg-sidebar-stat-zgloszenie">0</span>
                </div>
            </div>

            <!-- Filters and Sorting - Single Collapsible Section -->
            <div class="jg-sidebar-filters-sort">
                <div class="jg-sidebar-collapsible-header">
                    <span>Filtry i sortowanie</span>
                    <span class="jg-sidebar-toggle-icon">▼</span>
                </div>
                <div class="jg-sidebar-collapsible-content" style="display:none;">
                    <!-- Sorting (above filters) -->
                    <div class="jg-sidebar-sort-section">
                        <h4>Sortowanie</h4>
                        <div class="jg-sidebar-sort-controls">
                            <label for="jg-sidebar-sort-select">Sortuj:</label>
                            <select id="jg-sidebar-sort-select">
                                <option value="date_desc">Najnowsze</option>
                                <option value="date_asc">Najstarsze</option>
                                <option value="modified_desc">Ostatnio edytowane</option>
                                <option value="alpha_asc">Alfabetycznie A-Z</option>
                                <option value="alpha_desc">Alfabetycznie Z-A</option>
                                <option value="votes_desc">Najlepiej oceniane</option>
                                <option value="votes_asc">Najgorzej oceniane</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="jg-sidebar-filter-section">
                        <h4>Filtry typów</h4>
                        <div class="jg-sidebar-filter-group">
                            <label class="jg-sidebar-filter-label" data-sidebar-filter="miejsce"><input type="checkbox" data-sidebar-type="miejsce" checked><span class="jg-sidebar-filter-icon">📍</span><span class="jg-sidebar-filter-text"><?php _e('Miejsca', 'jg-map'); ?></span></label>
                            <label class="jg-sidebar-filter-label" data-sidebar-filter="ciekawostka"><input type="checkbox" data-sidebar-type="ciekawostka" checked><span class="jg-sidebar-filter-icon">💡</span><span class="jg-sidebar-filter-text"><?php _e('Ciekawostki', 'jg-map'); ?></span></label>
                            <label class="jg-sidebar-filter-label" data-sidebar-filter="zgloszenie"><input type="checkbox" data-sidebar-type="zgloszenie" checked><span class="jg-sidebar-filter-icon">⚠️</span><span class="jg-sidebar-filter-text"><?php _e('Zgłoszenia', 'jg-map'); ?></span></label>
                            <label class="jg-sidebar-filter-label" data-sidebar-filter="my-places"><input type="checkbox" data-sidebar-my-places><span class="jg-sidebar-filter-icon">👤</span><span class="jg-sidebar-filter-text"><?php _e('Moje miejsca', 'jg-map'); ?></span></label>
                        </div>
                    </div>

                    <!-- Category Filters - Place categories (collapsible, default collapsed) -->
                    <div class="jg-sidebar-filter-section" id="jg-sidebar-place-categories" style="display:none;">
                        <div class="jg-sidebar-collapsible-header jg-sidebar-category-header">
                            <span>Kategorie miejsc</span>
                            <span class="jg-sidebar-toggle-icon">▼</span>
                        </div>
                        <div class="jg-sidebar-collapsible-content" style="display:none;">
                            <div class="jg-sidebar-filter-group jg-sidebar-category-filters" data-category-type="miejsce">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Category Filters - Curiosity categories (collapsible, default collapsed) -->
                    <div class="jg-sidebar-filter-section" id="jg-sidebar-curiosity-categories" style="display:none;">
                        <div class="jg-sidebar-collapsible-header jg-sidebar-category-header">
                            <span>Kategorie ciekawostek</span>
                            <span class="jg-sidebar-toggle-icon">▼</span>
                        </div>
                        <div class="jg-sidebar-collapsible-content" style="display:none;">
                            <div class="jg-sidebar-filter-group jg-sidebar-category-filters" data-category-type="ciekawostka">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loader -->
            <div id="jg-sidebar-loading" class="jg-sidebar-loading" style="display:none;">
                <div class="jg-spinner"></div>
                <div>Ładowanie...</div>
            </div>

            <!-- Points List -->
            <div id="jg-sidebar-list" class="jg-sidebar-list">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render top slot shortcode
     *
     * Usage: [jg_banner]
     */
    public function render_banner($atts) {
        $atts = shortcode_atts(
            array(
                'width' => '728px',
                'height' => '90px'
            ),
            $atts,
            'jg_banner'
        );

        // Get per-request random keys (IDs and CSS class names)
        $k = JG_Slot_Keys::get();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($k['cls_wrap']); ?>"
             data-cid="<?php echo esc_attr($k['id_cid']); ?>"
             data-lid="<?php echo esc_attr($k['id_lid']); ?>"
             data-iid="<?php echo esc_attr($k['id_iid']); ?>"
             data-spin="<?php echo esc_attr($k['id_spin']); ?>"
             data-tag="<?php echo esc_attr($k['id_tag']); ?>">
            <div class="<?php echo esc_attr($k['cls_tag']); ?>" id="<?php echo esc_attr($k['id_tag']); ?>">Sponsorowane</div>
            <div id="<?php echo esc_attr($k['id_cid']); ?>" class="<?php echo esc_attr($k['cls_box']); ?>" style="max-width:<?php echo esc_attr($atts['width']); ?>;width:100%;margin:0 auto;box-sizing:border-box;">
                <div id="<?php echo esc_attr($k['id_spin']); ?>" style="display:flex;align-items:center;justify-content:center;aspect-ratio:<?php echo intval($atts['width']) . '/' . intval($atts['height']); ?>;background:#f5f5f5;color:#999;font-size:calc(14 * var(--jg));">
                    Ładowanie...
                </div>
                <a id="<?php echo esc_attr($k['id_lid']); ?>" href="#" target="_blank" style="display:none;">
                    <img id="<?php echo esc_attr($k['id_iid']); ?>" src="" alt="" style="width:100%;height:auto;display:block;">
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
