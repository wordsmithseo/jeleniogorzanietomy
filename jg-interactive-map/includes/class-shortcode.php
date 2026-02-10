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

        ob_start();
        ?>
        <div id="jg-map-wrap" class="jg-wrap" style="position:relative; height: <?php echo esc_attr($atts['height']); ?> !important; display: grid; grid-template-rows: auto 1fr;">
            <div id="jg-map-filters-wrapper">
                <div id="jg-map-filters" class="jg-filters">
                    <label class="jg-filter-label" data-filter-type="zgloszenie"><input type="checkbox" data-type="zgloszenie" checked><span class="jg-filter-icon">⚠️</span><span class="jg-filter-text"><?php _e('Zgłoszenia', 'jg-map'); ?></span></label>
                    <label class="jg-filter-label jg-filter-label--expandable" data-filter-type="ciekawostka"><input type="checkbox" data-type="ciekawostka" checked><span class="jg-filter-icon">💡</span><span class="jg-filter-text"><?php _e('Ciekawostki', 'jg-map'); ?></span><span class="jg-filter-expand-btn" data-expand-target="curiosity-categories">▼</span></label>
                    <label class="jg-filter-label jg-filter-label--expandable" data-filter-type="miejsce"><input type="checkbox" data-type="miejsce" checked><span class="jg-filter-icon">📍</span><span class="jg-filter-text"><?php _e('Miejsca', 'jg-map'); ?></span><span class="jg-filter-expand-btn" data-expand-target="place-categories">▼</span></label>
                    <label class="jg-filter-label" data-filter-type="my-places"><input type="checkbox" data-my-places><span class="jg-filter-icon">👤</span><span class="jg-filter-text"><?php _e('Moje miejsca', 'jg-map'); ?></span></label>
                    <label class="jg-filter-label" data-filter-type="promo" style="margin-left:auto"><input type="checkbox" data-promo><span class="jg-filter-icon">⭐</span><span class="jg-filter-text"><?php _e('Tylko miejsca sponsorowane', 'jg-map'); ?></span></label>
                    <div class="jg-search">
                        <input type="text" id="jg-search-input" placeholder="🔍 <?php _e('Szukaj miejsca...', 'jg-map'); ?>" />
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
                    <h3 id="jg-search-panel-title">Wyniki wyszukiwania</h3>
                    <span id="jg-search-panel-count" class="jg-search-count"></span>
                </div>
                <div id="jg-search-results" class="jg-search-results"></div>
                <div class="jg-search-panel-footer">
                    <button id="jg-search-close-btn" class="jg-btn jg-btn--secondary">Zakończ wyszukiwanie</button>
                </div>
            </div>

            <!-- Loader positioned relative to map container -->
            <div id="jg-map-loading" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:400;background:#fff;padding:30px 40px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.15);pointer-events:none;">
                <div class="jg-spinner"></div>
                <div style="margin-top:16px;font-size:16px;color:#333;font-weight:600"><?php _e('Ładowanie mapy...', 'jg-map'); ?></div>
            </div>

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

            <!-- Message Modals (for alert/confirm replacements) -->
            <div id="jg-modal-alert" class="jg-modal-bg" style="z-index:99999">
                <div class="jg-modal jg-modal-message" style="max-width:500px">
                    <div class="jg-modal-message-content"></div>
                    <div class="jg-modal-message-buttons"></div>
                </div>
            </div>

            <!-- Onboarding: Welcome Modal -->
            <div id="jg-onboarding-modal" class="jg-modal-bg" style="z-index:99998">
                <div class="jg-modal jg-onboarding-modal">
                    <div id="jg-onboarding-content"></div>
                </div>
            </div>

            <!-- Onboarding: Help Panel (will be moved into #jg-map by JS) -->
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
                            <li><strong><?php _e('Głosowanie', 'jg-map'); ?></strong> <?php _e('oceniaj punkty kciukiem w górę lub w dół', 'jg-map'); ?></li>
                            <li><strong><?php _e('Filtrowanie', 'jg-map'); ?></strong> <?php _e('użyj checkboxów nad mapą, aby pokazać/ukryć typy punktów', 'jg-map'); ?></li>
                            <li><strong><?php _e('Wyszukiwanie', 'jg-map'); ?></strong> <?php _e('wpisz nazwę w pole wyszukiwania, aby znaleźć punkt', 'jg-map'); ?></li>
                            <li><strong><?php _e('Zgłaszanie', 'jg-map'); ?></strong> <?php _e('zgłoś nieodpowiednią treść przyciskiem w szczegółach punktu', 'jg-map'); ?></li>
                            <li><strong><?php _e('Edycja', 'jg-map'); ?></strong> <?php _e('edytuj własne punkty (zmiany wymagają ponownej moderacji)', 'jg-map'); ?></li>
                        </ul>
                    </div>
                    <div class="jg-help-section jg-help-section--footer">
                        <button id="jg-help-restart-onboarding" class="jg-btn jg-btn--ghost"><?php _e('Pokaż powitanie ponownie', 'jg-map'); ?></button>
                    </div>
                </div>
            </div>

            <!-- Onboarding: Contextual Tips (will be moved into #jg-map by JS) -->
            <div id="jg-tip-container" class="jg-tip-container" style="display:none">
                <div class="jg-tip-content">
                    <span id="jg-tip-text"></span>
                    <button id="jg-tip-dismiss" class="jg-tip-dismiss">&times;</button>
                </div>
            </div>
        </div>
        <?php
        // Add crawlable HTML directory of all published points for SEO internal linking
        // Google discovers pages primarily through <a href> links, not just sitemaps
        echo $this->render_points_directory();

        return ob_get_clean();
    }

    /**
     * Render HTML directory of all published points for SEO internal linking.
     * Without this, Google only knows about pin pages from the sitemap (weak signal)
     * and won't crawl/index them automatically.
     */
    private function render_points_directory() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $points = $wpdb->get_results(
            "SELECT title, slug, type, address, created_at
             FROM $table
             WHERE status = 'publish' AND slug IS NOT NULL AND slug != ''
             ORDER BY type ASC, title ASC",
            ARRAY_A
        );

        if (empty($points)) {
            return '';
        }

        // Group by type
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

        $grouped = array();
        foreach ($points as $p) {
            $grouped[$p['type']][] = $p;
        }

        ob_start();
        ?>
        <div class="jg-directory" style="margin-top:32px; padding:24px 20px; background:#fff; border-radius:12px; border:1px solid #e5e7eb;">
            <h2 style="font-size:1.25rem; font-weight:700; color:#111; margin:0 0 16px;">Katalog miejsc w Jeleniej Górze</h2>
            <?php foreach ($type_labels as $type => $label): ?>
                <?php if (!empty($grouped[$type])): ?>
                    <div style="margin-bottom:20px;">
                        <h3 style="font-size:1rem; font-weight:600; color:#6b7280; margin:0 0 8px; text-transform:uppercase; letter-spacing:0.5px; font-size:0.8rem;"><?php echo esc_html($label); ?></h3>
                        <ul style="list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:6px 16px;">
                            <?php foreach ($grouped[$type] as $p):
                                $path = isset($type_paths[$p['type']]) ? $type_paths[$p['type']] : 'miejsce';
                                $url = home_url('/' . $path . '/' . $p['slug'] . '/');
                            ?>
                                <li style="font-size:14px; line-height:1.8;">
                                    <a href="<?php echo esc_url($url); ?>" style="color:#2563eb; text-decoration:none;"><?php echo esc_html($p['title']); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
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
        <div id="jg-map-sidebar" class="jg-map-sidebar" style="height: <?php echo esc_attr($atts['height']); ?> !important;">

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

                    <!-- Category Filters - Place categories -->
                    <div class="jg-sidebar-filter-section" id="jg-sidebar-place-categories" style="display:none;">
                        <h4>Kategorie miejsc</h4>
                        <div class="jg-sidebar-filter-group jg-sidebar-category-filters" data-category-type="miejsce">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Category Filters - Curiosity categories -->
                    <div class="jg-sidebar-filter-section" id="jg-sidebar-curiosity-categories" style="display:none;">
                        <h4>Kategorie ciekawostek</h4>
                        <div class="jg-sidebar-filter-group jg-sidebar-category-filters" data-category-type="ciekawostka">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Sorting -->
                    <div class="jg-sidebar-sort-section">
                        <h4>Sortowanie</h4>
                        <div class="jg-sidebar-sort-controls">
                            <label for="jg-sidebar-sort-select">Sortuj:</label>
                            <select id="jg-sidebar-sort-select">
                                <option value="date_desc">Najnowsze</option>
                                <option value="date_asc">Najstarsze</option>
                                <option value="alpha_asc">Alfabetycznie A-Z</option>
                                <option value="alpha_desc">Alfabetycznie Z-A</option>
                                <option value="votes_desc">Najlepiej oceniane</option>
                                <option value="votes_asc">Najgorzej oceniane</option>
                            </select>
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
     * Render banner shortcode
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

        ob_start();
        ?>
        <div id="jg-banner-container" class="jg-banner-container" style="width:<?php echo esc_attr($atts['width']); ?>;height:<?php echo esc_attr($atts['height']); ?>;margin:20px auto;position:relative;overflow:hidden;">
            <div id="jg-banner-loading" style="display:flex;align-items:center;justify-content:center;height:100%;background:#f5f5f5;color:#999;font-size:14px;">
                Ładowanie banneru...
            </div>
            <a id="jg-banner-link" href="#" target="_blank" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;">
                <img id="jg-banner-image" src="" alt="Banner" style="width:100%;height:100%;object-fit:contain;background:#f5f5f5;">
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}
