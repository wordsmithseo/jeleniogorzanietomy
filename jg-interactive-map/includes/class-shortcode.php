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
        <div id="jg-map-wrap" class="jg-wrap" style="position:relative">
            <div id="jg-map-filters" class="jg-filters">
                <label><input type="checkbox" data-type="zgloszenie" checked> <?php _e('Zgłoszenia', 'jg-map'); ?></label>
                <label><input type="checkbox" data-type="ciekawostka" checked> <?php _e('Ciekawostki', 'jg-map'); ?></label>
                <label><input type="checkbox" data-type="miejsce" checked> <?php _e('Miejsca', 'jg-map'); ?></label>
                <label><input type="checkbox" data-my-places> <?php _e('Moje miejsca', 'jg-map'); ?></label>
                <label style="margin-left:auto"><input type="checkbox" data-promo> <?php _e('Tylko promocje', 'jg-map'); ?></label>
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

            <!-- Loader OUTSIDE map div so it's always visible -->
            <div id="jg-map-loading" style="position:absolute;top:calc(50% + 40px);left:50%;transform:translate(-50%,-50%);z-index:10000;background:#fff;padding:30px 40px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.15);pointer-events:none;">
                <div class="jg-spinner"></div>
                <div style="margin-top:16px;font-size:16px;color:#333;font-weight:600"><?php _e('Ładowanie mapy...', 'jg-map'); ?></div>
            </div>

            <div id="jg-map" class="jg-map" style="opacity: 0; transition: opacity 0.3s; height: <?php echo esc_attr($atts['height']); ?>;"
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
            <div id="jg-map-lightbox" class="jg-modal-bg"><div class="jg-lightbox"></div></div>

            <!-- Message Modals (for alert/confirm replacements) -->
            <div id="jg-modal-alert" class="jg-modal-bg" style="z-index:99999">
                <div class="jg-modal jg-modal-message" style="max-width:500px">
                    <div class="jg-modal-message-content"></div>
                    <div class="jg-modal-message-buttons"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
