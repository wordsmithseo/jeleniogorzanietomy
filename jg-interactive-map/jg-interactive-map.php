<?php
/**
 * Plugin Name: JG Interactive Map
 * Plugin URI: https://jeleniogorzanietomy.pl
 * Description: Interaktywna mapa Jeleniej Góry z możliwością dodawania zgłoszeń, ciekawostek i miejsc
 * Version: 3.26.13
 * Author: JeleniogorzaNieTomy
 * Author URI: https://jeleniogorzanietomy.pl
 * Text Domain: jg-map
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('JG_MAP_VERSION', '3.26.13');
define('JG_MAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JG_MAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JG_MAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class JG_Interactive_Map {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Flag to prevent double execution of page rendering
     */
    private static $page_rendered = false;

    /**
     * Get single instance
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-activity-log.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-sync-manager.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-slot-keys.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-banner-manager.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-banner-admin.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-info-bar.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-levels-achievements.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-enqueue.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-admin.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-maintenance.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array('JG_Map_Database', 'activate'));
        register_activation_hook(__FILE__, array($this, 'flush_rewrite_rules_on_activation'));
        register_deactivation_hook(__FILE__, array('JG_Map_Database', 'deactivate'));
        register_deactivation_hook(__FILE__, array('JG_Map_Maintenance', 'deactivate'));

        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));

        // Disable WordPress built-in emoji script — we use Twemoji directly
        // to avoid double-parsing and the extra ~10KB wp-emoji-release.min.js request.
        add_action('init', array($this, 'disable_wp_emoji'));

        // One-time migration: convert legacy up/down votes to star ratings
        add_action('init', array('JG_Map_Database', 'maybe_migrate_votes_to_stars'));

        // Initialize maintenance cron
        add_action('init', array('JG_Map_Maintenance', 'init'));

        // Load text domain
        add_action('init', array($this, 'load_textdomain'));

        // Set email sender name and address
        add_filter('wp_mail_from_name', array($this, 'set_email_from_name'));
        add_filter('wp_mail_from', array($this, 'set_email_from'));

        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));

        // SEO-friendly URLs for points and tag pages
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('init', array($this, 'check_rewrite_flush'), 999); // Run late to ensure rewrite rules are added first
        add_filter('query_vars', array($this, 'add_query_vars'));

        // CRITICAL: Use priority 1 for template_redirect to ensure point pages and sitemap
        // are handled BEFORE WordPress redirect_canonical (priority 10) and Yoast SEO redirects
        // can interfere. Without this, redirect_canonical or Yoast may redirect/404 the URLs
        // before the plugin gets a chance to render them.
        add_action('template_redirect', array($this, 'handle_point_page'), 1);
        add_action('template_redirect', array($this, 'handle_sitemap'), 1);
        add_action('template_redirect', array($this, 'redirect_legacy_tag_urls'), 1);
        add_action('template_redirect', array($this, 'handle_tile_sw'), 1);
        add_action('wp_head', array($this, 'add_point_meta_tags'));
        add_action('wp_head', array($this, 'add_tag_page_meta_tags'));
        add_action('wp_head', array($this, 'add_category_page_meta_tags'));

        // Suppress Yoast/RankMath meta description on catalog tag/category pages (plugin outputs its own)
        add_action('wp', array($this, 'suppress_seo_plugin_description_on_tag_pages'));
        add_action('wp', array($this, 'suppress_seo_plugin_description_on_category_pages'));

        // Override document title for tag and category pages
        add_filter('document_title_parts', array($this, 'filter_tag_page_title'));
        add_filter('wpseo_title', array($this, 'filter_tag_page_yoast_title'));
        add_filter('document_title_parts', array($this, 'filter_category_page_title'));
        add_filter('wpseo_title', array($this, 'filter_category_page_yoast_title'));

        // Register map sitemap in Yoast sitemap index for better discoverability
        add_filter('wpseo_sitemap_index', array($this, 'add_map_sitemap_to_yoast_index'));
        // Add map sitemap to robots.txt
        add_filter('robots_txt', array($this, 'add_map_sitemap_to_robots'), 10, 2);
    }

    /**
     * Set email sender name for all emails from this WordPress site
     * Replaces default "WordPress" sender with our brand name
     */
    public function set_email_from_name($from_name) {
        return 'Jeleniogórzanie to my';
    }

    /**
     * Set email sender address for all emails from this WordPress site
     */
    public function set_email_from($from_email) {
        return 'powiadomienia@jeleniogorzanietomy.pl';
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Allow admins to force schema update via URL parameter (for debugging)
        if (isset($_GET['jg_force_schema_update']) && current_user_can('manage_options')) {
            delete_option('jg_map_schema_version');
        }

        // Check and update database schema on every load (only runs if needed)
        JG_Map_Database::check_and_update_schema();

        // Set flag for one-time rewrite flush (will be executed in init hook)
        if (!get_option('jg_map_rewrite_flushed_v7', false)) {
            delete_option('jg_map_flush_count'); // Reset counter
            update_option('jg_map_needs_rewrite_flush', true);
            update_option('jg_map_rewrite_flushed_v7', true);
        }

        JG_Map_Activity_Log::get_instance();
        JG_Map_Sync_Manager::get_instance();
        JG_Map_Enqueue::get_instance();
        JG_Map_Shortcode::get_instance();
        JG_Map_Ajax_Handlers::get_instance();
        JG_Map_Admin::get_instance();
        JG_Map_Banner_Admin::init();
        JG_Map_Info_Bar::init();
        JG_Map_Levels_Achievements::get_instance();
    }

    /**
     * Disable WordPress built-in emoji handling.
     * We load Twemoji ourselves, so WP's emoji script is redundant overhead.
     */
    public function disable_wp_emoji() {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'jg-map',
            false,
            dirname(JG_MAP_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Flush rewrite rules on plugin activation
     */
    public function flush_rewrite_rules_on_activation() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        // Set option to ensure flush on next page load
        update_option('jg_map_needs_rewrite_flush', true);
    }

    /**
     * Add security headers including CSP
     */
    public function add_security_headers() {
        // Only add headers for frontend (not admin)
        if (is_admin()) {
            return;
        }

        // Skip security headers for sitemap XML requests
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'jg-map-sitemap.xml') !== false) {
            return;
        }

        // CRITICAL: Skip security headers for bots (especially Google-InspectionTool)
        // CSP "connect-src 'self'" blocks Google from sending inspection results back
        if ($this->is_bot()) {
            return;
        }

        // Content Security Policy
        // Allow self, inline scripts (needed for map), and specific external sources
        $csp_directives = array(
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://www.googletagmanager.com https://www.google-analytics.com https://www.youtube.com https://analytics.ahrefs.com https://www.clarity.ms https://scripts.clarity.ms",
            "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "worker-src blob: 'self'",
            "connect-src 'self' https://www.google-analytics.com https://analytics.google.com https://*.google-analytics.com https://*.analytics.google.com https://stats.g.doubleclick.net https://www.googletagmanager.com https://basemaps.cartocdn.com https://*.basemaps.cartocdn.com https://server.arcgisonline.com https://analytics.ahrefs.com https://api.mixpanel.com https://api-eu.mixpanel.com https://www.clarity.ms https://*.clarity.ms",
            "frame-src https://www.youtube.com https://www.youtube-nocookie.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'"
        );

        header('Content-Security-Policy: ' . implode('; ', $csp_directives));

        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');

        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');

        // X-XSS-Protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions-Policy (formerly Feature-Policy)
        $permissions = array(
            'geolocation=(self)',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()'
        );
        header('Permissions-Policy: ' . implode(', ', $permissions));
    }

    /**
     * Add rewrite rules for SEO-friendly point URLs
     */
    public function add_rewrite_rules() {
        // Rewrite rules for all point types
        add_rewrite_rule(
            '^miejsce/([^/]+)/menu/?$',
            'index.php?jg_map_point=$matches[1]&jg_map_type=miejsce&jg_map_menu=1',
            'top'
        );
        add_rewrite_rule(
            '^miejsce/([^/]+)/?$',
            'index.php?jg_map_point=$matches[1]&jg_map_type=miejsce',
            'top'
        );
        add_rewrite_rule(
            '^ciekawostka/([^/]+)/?$',
            'index.php?jg_map_point=$matches[1]&jg_map_type=ciekawostka',
            'top'
        );
        add_rewrite_rule(
            '^zgloszenie/([^/]+)/?$',
            'index.php?jg_map_point=$matches[1]&jg_map_type=zgloszenie',
            'top'
        );

        // Clean URL for catalog tag pages: /katalog/tag/{slug}/
        add_rewrite_rule(
            '^katalog/tag/([^/]+)/?$',
            'index.php?pagename=katalog&jg_catalog_tag=$matches[1]',
            'top'
        );

        // Clean URL for catalog category pages: /katalog/kategoria/{slug}/
        add_rewrite_rule(
            '^katalog/kategoria/([^/]+)/?$',
            'index.php?pagename=katalog&jg_catalog_category=$matches[1]',
            'top'
        );

        // Sitemap for places
        add_rewrite_rule(
            '^jg-map-sitemap\.xml$',
            'index.php?jg_map_sitemap=1',
            'top'
        );

        // Tile-caching Service Worker served from root-level URL
        // (admin-ajax.php cannot reliably send Service-Worker-Allowed: / header)
        add_rewrite_rule(
            '^jg-tile-sw\.js$',
            'index.php?jg_tile_sw=1',
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'jg_map_point';
        $vars[] = 'jg_map_type';
        $vars[] = 'jg_map_sitemap';
        $vars[] = 'jg_catalog_tag';
        $vars[] = 'jg_catalog_category';
        $vars[] = 'jg_tile_sw';
        $vars[] = 'jg_map_menu';
        return $vars;
    }

    /**
     * Check and flush rewrite rules if needed
     * Runs on 'init' hook when $wp_rewrite is available
     */
    public function check_rewrite_flush() {
        // Flush rewrite rules a few times after deployment to pick up new rules
        $flush_count = get_option('jg_map_flush_count_v2', 0);

        if ($flush_count < 3) {
            flush_rewrite_rules(false);
            update_option('jg_map_flush_count_v2', $flush_count + 1);
        }

        // v3: flush to register /jg-tile-sw.js rewrite rule
        $flush_count_v3 = get_option('jg_map_flush_count_v3', 0);
        if ($flush_count_v3 < 3) {
            flush_rewrite_rules(false);
            update_option('jg_map_flush_count_v3', $flush_count_v3 + 1);
        }

        // v4: flush to register /miejsce/{slug}/menu/ rewrite rule
        $flush_count_v4 = get_option('jg_map_flush_count_v4', 0);
        if ($flush_count_v4 < 3) {
            flush_rewrite_rules(false);
            update_option('jg_map_flush_count_v4', $flush_count_v4 + 1);
        }

        // v5: flush to register /katalog/kategoria/{slug}/ rewrite rule
        $flush_count_v5 = get_option('jg_map_flush_count_v5', 0);
        if ($flush_count_v5 < 3) {
            flush_rewrite_rules(false);
            update_option('jg_map_flush_count_v5', $flush_count_v5 + 1);
        }

        // Legacy flush check
        if (get_option('jg_map_needs_rewrite_flush', false)) {
            flush_rewrite_rules(false);
            delete_option('jg_map_needs_rewrite_flush');
        }
    }

    /**
     * Serve tile-caching Service Worker from a clean root-level URL (/jg-tile-sw.js).
     * Using a rewrite rule (not admin-ajax.php) guarantees the Service-Worker-Allowed: /
     * header is sent before any WordPress output, so the browser accepts scope '/'.
     */
    public function handle_tile_sw() {
        if (!get_query_var('jg_tile_sw')) {
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $sw_file = JG_MAP_PLUGIN_DIR . 'assets/js/tile-sw.js';
        if (!file_exists($sw_file)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($sw_file);
        exit;
    }

    /**
     * Detect if visitor is a bot/crawler
     */
    private function is_bot() {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

        // Common bot signatures
        $bots = array(
            'googlebot',              // Google crawler
            'google-inspectiontool',  // Google Search Console URL inspection tool (CRITICAL!)
            'adsbot-google',          // Google Ads bot
            'googlebot-image',        // Google Image crawler
            'googlebot-news',         // Google News crawler
            'googlebot-video',        // Google Video crawler
            'chrome-lighthouse',      // Google Lighthouse
            'bingbot',                // Bing
            'slurp',                  // Yahoo
            'duckduckbot',            // DuckDuckGo
            'baiduspider',            // Baidu
            'yandexbot',              // Yandex
            'facebookexternalhit',    // Facebook
            'twitterbot',             // Twitter
            'whatsapp',               // WhatsApp
            'telegram',               // Telegram
            'linkedinbot',            // LinkedIn
            'pinterestbot',           // Pinterest
            'slackbot',               // Slack
            'discordbot',             // Discord
            'applebot',               // Apple
            'ia_archiver',            // Alexa
            'semrushbot',             // SEMrush
            'ahrefsbot',              // Ahrefs
            'mj12bot',                // Majestic
            'dotbot',                 // Moz
            'rogerbot',               // Moz
            'petalbot',               // Huawei
            'seznambot',              // Seznam
        );

        foreach ($bots as $bot) {
            if (strpos($user_agent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle single point page display
     */
    public function handle_point_page() {
        $point_slug = get_query_var('jg_map_point');

        // Fallback: parse URL directly if rewrite rules aren't working
        // This mirrors the approach used in handle_sitemap() and ensures
        // point pages remain accessible even when rewrite rules are flushed/broken
        if (empty($point_slug) && isset($_SERVER['REQUEST_URI'])) {
            $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if (preg_match('#^/(miejsce|ciekawostka|zgloszenie)/([^/]+)/menu/?$#', $request_uri, $matches)) {
                $point_slug = sanitize_title($matches[2]);
                // Override query var so we know it's the menu subpage
                if (!get_query_var('jg_map_menu')) {
                    set_query_var('jg_map_menu', '1');
                }
            } elseif (preg_match('#^/(miejsce|ciekawostka|zgloszenie)/([^/]+)/?$#', $request_uri, $matches)) {
                $point_slug = sanitize_title($matches[2]);
            }
        }

        if (empty($point_slug)) {
            return;
        }

        // 301 redirect to trailing-slash canonical URL to prevent GSC duplicate indexing.
        // Without this, /miejsce/slug and /miejsce/slug/ are treated as separate pages by Google,
        // splitting impressions and link equity across two URLs.
        if (isset($_SERVER['REQUEST_URI'])) {
            $req_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if ($req_path !== null && substr($req_path, -1) !== '/') {
                wp_redirect(home_url($req_path . '/'), 301);
                exit;
            }
        }

        // Generate unique request ID for logging
        $request_id = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $start_time = microtime(true);

        // Prevent double execution within same PHP process
        if (self::$page_rendered) {
            exit;
        }
        self::$page_rendered = true;

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        $user_agent_short = (strpos($user_agent, 'Mobile') !== false) ? 'Mobile' : 'Desktop';

        // Get point by slug from database
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $point = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, slug, content, excerpt, lat, lng, address, type, category, status,
                        author_id, is_promo, website, phone, images, featured_image_index,
                        facebook_url, instagram_url, linkedin_url, tiktok_url, tags, opening_hours, created_at, updated_at,
                        seo_canonical, seo_noindex
                 FROM $table
                 WHERE slug = %s AND status = 'publish'
                 LIMIT 1",
                $point_slug
            ),
            ARRAY_A
        );

        if (!$point) {
            // Check if this is an old slug that was renamed — if so, do a 301 redirect
            $redirects_table = JG_Map_Database::get_slug_redirects_table();
            $redirect_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT sr.point_id, p.slug, p.type
                     FROM $redirects_table sr
                     INNER JOIN $table p ON sr.point_id = p.id
                     WHERE sr.old_slug = %s AND p.status = 'publish'
                     LIMIT 1",
                    $point_slug
                ),
                ARRAY_A
            );

            if ($redirect_row) {
                $type_path = ($redirect_row['type'] === 'ciekawostka') ? 'ciekawostka'
                           : (($redirect_row['type'] === 'zgloszenie') ? 'zgloszenie' : 'miejsce');
                $new_url = home_url('/' . $type_path . '/' . $redirect_row['slug'] . '/');
                wp_redirect($new_url, 301);
                exit;
            }

            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            header('Content-Type: text/html; charset=UTF-8');
            $template_404 = get_404_template();
            if ($template_404 && file_exists($template_404)) {
                include($template_404);
            } else {
                $home_url = esc_url(home_url('/'));
                echo '<!doctype html><html lang="pl-PL"><head>'
                   . '<meta charset="UTF-8">'
                   . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                   . '<meta name="robots" content="noindex, follow">'
                   . '<title>404 – Nie znaleziono | JeleniogorzaNieTomy.pl</title>'
                   . '<style>body{font-family:system-ui,sans-serif;background:#fff;color:#111;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
                   . '.box{text-align:center;padding:40px 24px;max-width:520px}'
                   . 'h1{font-size:28px;font-weight:800;color:#111;margin:0 0 24px;line-height:1.3}'
                   . 'img{max-width:100%;height:auto;margin:0 0 28px;border-radius:12px}'
                   . 'a{display:inline-block;background:#c2410c;color:#fff;font-weight:700;padding:14px 28px;border-radius:8px;text-decoration:none;font-size:16px}'
                   . 'a:hover{background:#9a3412}'
                   . '</style></head><body>'
                   . '<div class="box">'
                   . '<h1>Ups! Chyba się zgubiliśmy!</h1>'
                   . '<img src="https://jeleniogorzanietomy.pl/wp-content/uploads/2026/03/404.png" alt="404 – strona nie istnieje">'
                   . '<br><a href="' . $home_url . '">Wróć do mapy</a>'
                   . '</div></body></html>';
            }
            exit;
        }

        $db_time = microtime(true) - $start_time;

        // Serve the same full HTML page to ALL visitors (bots and humans alike)
        // This avoids cloaking (serving different content to search engines vs users)
        // which can prevent Google from indexing the pages

        // Ensure HTTP 200 status
        status_header(200);

        // Prevent WordPress and Yoast SEO from doing redirects or 404 handling
        // Now runs at priority 1, so redirect_canonical (priority 10) hasn't fired yet
        remove_action('template_redirect', 'redirect_canonical');

        // Disable Yoast SEO output for this request to prevent any X-Robots-Tag headers
        // or other interference with the standalone point page
        if (class_exists('WPSEO_Frontend')) {
            remove_action('wp_head', array(WPSEO_Frontend::get_instance(), 'head'), 1);
        }
        // Yoast 14+ uses a different class
        if (class_exists('Yoast\\WP\\SEO\\Integrations\\Front_End_Integration')) {
            $yoast_front = YoastSEO()->classes->get('Yoast\\WP\\SEO\\Integrations\\Front_End_Integration');
            if ($yoast_front) {
                remove_action('wp_head', array($yoast_front, 'call_wpseo_head'), 1);
            }
        }
        // Remove Yoast's robots header filter
        remove_filter('wp_robots', 'wp_robots_noindex');
        if (function_exists('wp_robots_no_robots')) {
            remove_filter('wp_robots', 'wp_robots_no_robots');
        }

        global $jg_current_point;
        $jg_current_point = $point;

        // Render the full point page (standalone HTML, no wp_head/wp_footer)
        // No Yoast filter overrides needed since we don't call wp_head()
        ob_start();

        $is_menu_page = (get_query_var('jg_map_menu') == '1');

        try {
            if ($is_menu_page) {
                $this->render_menu_page($point, $request_id);
            } else {
                $this->render_point_page($point, $request_id, $user_agent_short);
            }
            $html_output = ob_get_clean();
            echo $html_output;
        } catch (Exception $e) {
            ob_end_clean();
            // Fallback: render minimal HTML
            $this->render_fallback_page($point, $request_id, $user_agent_short);
        }
        exit;
    }

    /**
     * Render the menu subpage for a gastronomic place (/miejsce/{slug}/menu/)
     */
    private function render_menu_page($point, $request_id = 'unknown') {
        $point_url  = home_url('/miejsce/' . $point['slug'] . '/');
        $menu_url   = home_url('/miejsce/' . $point['slug'] . '/menu/');
        $type_color = '#8d2324';

        $site_name  = get_bloginfo('name');
        $logo_url   = '';
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        }

        $site_icon_32  = get_site_icon_url(32);
        $site_icon_192 = get_site_icon_url(192);
        $site_icon_180 = get_site_icon_url(180);

        $sections = JG_Map_Database::get_menu($point['id']);
        $photos   = JG_Map_Database::get_menu_photos($point['id']);

        $page_title = 'Menu – ' . esc_html($point['title']) . ' | ' . esc_html($site_name);
        $description = 'Sprawdź aktualne menu restauracji ' . $point['title'] . ' w Jeleniej Górze.';

        $canonical = esc_url($menu_url);

        $dietary_labels = array(
            'wegetarianskie' => '🌿 wegetariańskie',
            'weganskie'      => '🌱 wegańskie',
            'bezglutenowe'   => '🌾 bezglutenowe',
            'ostre'          => '🌶️ ostre',
            'bez_laktozy'    => '🥛 bez laktozy',
        );

        ?><!doctype html>
<html lang="pl-PL">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo $canonical; ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo esc_attr('Menu – ' . $point['title']); ?>">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:url" content="<?php echo $canonical; ?>">
    <meta property="og:locale" content="pl_PL">
    <?php if ($site_icon_32): ?>
    <link rel="icon" href="<?php echo esc_url($site_icon_32); ?>" sizes="32x32">
    <link rel="icon" href="<?php echo esc_url($site_icon_192); ?>" sizes="192x192">
    <link rel="apple-touch-icon" href="<?php echo esc_url($site_icon_180); ?>">
    <?php endif; ?>
    <?php
    // Schema.org Menu structured data
    if (!empty($sections)):
        $menu_schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Menu',
            '@id'      => $menu_url . '#menu',
            'name'     => 'Menu – ' . $point['title'],
            'url'      => $menu_url,
        );
        $menu_sections_schema = array();
        foreach ($sections as $sec) {
            $items_schema = array();
            foreach ($sec['items'] as $item) {
                $item_schema = array(
                    '@type' => 'MenuItem',
                    'name'  => $item['name'],
                );
                if (!empty($item['description'])) {
                    $item_schema['description'] = $item['description'];
                }
                if ($item['price'] !== null && $item['price'] !== '') {
                    $item_schema['offers'] = array(
                        '@type'         => 'Offer',
                        'price'         => number_format(floatval($item['price']), 2, '.', ''),
                        'priceCurrency' => 'PLN',
                    );
                }
                $items_schema[] = $item_schema;
            }
            if (!empty($items_schema)) {
                $menu_sections_schema[] = array(
                    '@type'       => 'MenuSection',
                    'name'        => $sec['name'],
                    'hasMenuItem' => $items_schema,
                );
            }
        }
        if (!empty($menu_sections_schema)) {
            $menu_schema['hasMenuSection'] = $menu_sections_schema;
        }
        echo '<script type="application/ld+json">' . json_encode($menu_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    endif;
    ?>
    <style>
        :root { --jg: clamp(1px, 0.065vw, 1.1px); }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f9fafb; color: #111; }
        a { color: inherit; text-decoration: none; }
        .jg-sp-site-header { position: sticky; top: 0; z-index: 100; background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .jg-sp-site-header a { font-weight: 700; font-size: calc(16 * var(--jg)); color: <?php echo esc_attr($type_color); ?>; display: flex; align-items: center; gap: 8px; }
        .jg-sp-site-header img { height: 32px; width: auto; }
        .jg-sp-site-nav a { font-size: calc(13 * var(--jg)); color: #6b7280; border: 1px solid #e5e7eb; padding: 6px 12px; border-radius: 20px; }
        .jg-sp { max-width: 800px; margin: 0 auto; padding: 28px 20px 60px; }
        .jg-menu-back { display: inline-flex; align-items: center; gap: 6px; font-size: calc(13 * var(--jg)); color: #6b7280; margin-bottom: 16px; }
        .jg-menu-back:hover { color: <?php echo esc_attr($type_color); ?>; }
        .jg-sp-title { font-size: calc(28 * var(--jg)); font-weight: 800; margin: 0 0 4px 0; color: #111; }
        .jg-sp-date { font-size: calc(12 * var(--jg)); color: #9ca3af; margin-bottom: 24px; }

        /* Menu card photos */
        .jg-menu-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; margin-bottom: 28px; }
        .jg-menu-photo-item { border-radius: 8px; overflow: hidden; aspect-ratio: 3/4; cursor: pointer; }
        .jg-menu-photo-item img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.2s; }
        .jg-menu-photo-item:hover img { transform: scale(1.03); }
        .jg-menu-photos-title { font-size: calc(11 * var(--jg)); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 10px; }

        /* Menu sections */
        .jg-menu-section { margin-bottom: 28px; }
        .jg-menu-section-name { font-size: calc(13 * var(--jg)); font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: <?php echo esc_attr($type_color); ?>; padding-bottom: 8px; border-bottom: 2px solid <?php echo esc_attr($type_color); ?>; margin-bottom: 12px; }
        .jg-menu-item { display: flex; align-items: baseline; gap: 8px; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
        .jg-menu-item:last-child { border-bottom: none; }
        .jg-menu-item__name { font-size: calc(15 * var(--jg)); font-weight: 600; color: #1f2937; flex: 1; min-width: 0; }
        .jg-menu-item__desc { font-size: calc(12 * var(--jg)); color: #6b7280; margin-top: 2px; }
        .jg-menu-item__tags { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px; }
        .jg-menu-item__tag { font-size: calc(10 * var(--jg)); color: #374151; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
        .jg-menu-item__price { font-size: calc(15 * var(--jg)); font-weight: 700; color: <?php echo esc_attr($type_color); ?>; white-space: nowrap; flex-shrink: 0; }
        .jg-menu-item__unavailable .jg-menu-item__name { color: #9ca3af; text-decoration: line-through; }
        .jg-menu-item__unavailable .jg-menu-item__price { color: #9ca3af; }

        /* Lightbox */
        .jg-lightbox-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 200000; align-items: center; justify-content: center; }
        .jg-lightbox-overlay.active { display: flex; }
        .jg-lightbox-overlay img { max-width: 95vw; max-height: 88vh; object-fit: contain; border-radius: 4px; cursor: default; }
        .jg-lightbox-close { position: absolute; top: 16px; right: 20px; background: none; border: none; color: #fff; font-size: 2.4rem; cursor: pointer; line-height: 1; z-index: 1; }
        .jg-menu-item__variants { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px; }
        .jg-menu-item__variant { font-size: calc(12 * var(--jg)); color: #374151; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 2px 8px; white-space: nowrap; }
        .jg-menu-item__variant strong { color: <?php echo esc_attr($type_color); ?>; }

        .jg-sp-site-footer { text-align: center; padding: 24px 20px; font-size: calc(12 * var(--jg)); color: #9ca3af; border-top: 1px solid #e5e7eb; }
        .jg-menu-empty { color: #9ca3af; font-size: calc(14 * var(--jg)); padding: 24px 0; }
        @media (max-width: 480px) {
            .jg-menu-photos { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<header class="jg-sp-site-header">
    <a href="<?php echo esc_url(home_url('/')); ?>">
        <?php if ($logo_url): ?><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>"><?php else: echo esc_html($site_name); endif; ?>
    </a>
    <nav class="jg-sp-site-nav">
        <a href="<?php echo esc_url(home_url('/')); ?>">Otwórz mapę</a>
    </nav>
</header>

<div class="jg-sp">
    <a href="<?php echo esc_url($point_url); ?>" class="jg-menu-back">← Wróć do: <?php echo esc_html($point['title']); ?></a>
    <h1 class="jg-sp-title">Menu – <?php echo esc_html($point['title']); ?></h1>

    <?php if (!empty($photos)): ?>
    <div class="jg-menu-photos-title">Karta menu</div>
    <div class="jg-menu-photos">
        <?php foreach ($photos as $photo): ?>
        <div class="jg-menu-photo-item" data-full="<?php echo esc_attr($photo['url']); ?>">
            <img src="<?php echo esc_url($photo['thumb_url'] ?: $photo['url']); ?>" alt="Karta menu – <?php echo esc_attr($point['title']); ?>" loading="lazy">
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $section): ?>
        <div class="jg-menu-section">
            <div class="jg-menu-section-name"><?php echo esc_html($section['name']); ?></div>
            <?php foreach ($section['items'] as $item): ?>
            <div class="jg-menu-item<?php echo (!$item['is_available']) ? ' jg-menu-item__unavailable' : ''; ?>">
                <div style="flex:1;min-width:0">
                    <div class="jg-menu-item__name"><?php echo esc_html($item['name']); ?></div>
                    <?php if (!empty($item['description'])): ?>
                    <div class="jg-menu-item__desc"><?php echo esc_html($item['description']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['dietary_tags'])): ?>
                    <div class="jg-menu-item__tags">
                        <?php
                        foreach (explode(',', $item['dietary_tags']) as $dtag) {
                            $dtag = trim($dtag);
                            if ($dtag !== '') {
                                $label = isset($dietary_labels[$dtag]) ? $dietary_labels[$dtag] : esc_html($dtag);
                                echo '<span class="jg-menu-item__tag">' . $label . '</span>';
                            }
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php
                $variants = array();
                if (!empty($item['variants'])) {
                    $decoded = json_decode($item['variants'], true);
                    if (is_array($decoded)) $variants = $decoded;
                }
                if (!empty($variants)):
                ?>
                <div style="align-self:flex-start;flex-shrink:0">
                    <?php if (count($variants) > 1): ?>
                    <div class="jg-menu-item__variants">
                        <?php foreach ($variants as $v): ?>
                        <span class="jg-menu-item__variant"><?php echo esc_html($v['label']); ?> <strong><?php echo number_format(floatval($v['price']), 2, ',', ' ') . ' zł'; ?></strong></span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="jg-menu-item__price"><?php
                        if (!empty($variants[0]['label'])) echo esc_html($variants[0]['label']) . ': ';
                        echo number_format(floatval($variants[0]['price']), 2, ',', ' ') . ' zł';
                    ?></div>
                    <?php endif; ?>
                </div>
                <?php elseif ($item['price'] !== null && $item['price'] !== ''): ?>
                <div class="jg-menu-item__price"><?php echo number_format(floatval($item['price']), 2, ',', ' ') . ' zł'; ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php elseif (empty($photos)): ?>
        <p class="jg-menu-empty">Menu nie zostało jeszcze dodane.</p>
    <?php endif; ?>
</div>

<div class="jg-lightbox-overlay" id="jg-lightbox">
    <span class="jg-lightbox-close" id="jg-lightbox-close">&times;</span>
    <img id="jg-lightbox-img" src="" alt="">
</div>

<footer class="jg-sp-site-footer">
    <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($site_name); ?></a> &middot;
    <a href="<?php echo esc_url($point_url); ?>"><?php echo esc_html($point['title']); ?></a>
</footer>

<script>
(function() {
    var overlay = document.getElementById('jg-lightbox');
    var img     = document.getElementById('jg-lightbox-img');
    var closeBtn = document.getElementById('jg-lightbox-close');

    document.querySelectorAll('.jg-menu-photo-item').forEach(function(el) {
        el.addEventListener('click', function() {
            img.src = el.dataset.full || el.querySelector('img').src;
            overlay.classList.add('active');
        });
    });
    closeBtn.addEventListener('click', function() { overlay.classList.remove('active'); });
    overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.classList.remove('active'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') overlay.classList.remove('active'); });
})();
</script>
</body>
</html>
<?php
    }

    /**
     * Render single point page (standalone, no Elementor header/footer)
     */
    private function render_point_page($point, $request_id = 'unknown', $user_agent_short = '') {
        $images = json_decode($point['images'], true) ?: array();
        $featured_index = isset($point['featured_image_index']) ? (int)$point['featured_image_index'] : 0;

        // Get featured image (or first image as fallback) - ensure it's a full URL
        $first_image = '';
        if (!empty($images)) {
            $img_index = isset($images[$featured_index]) ? $featured_index : 0;

            if (isset($images[$img_index])) {
                $img = $images[$img_index];
                if (is_array($img)) {
                    $first_image = isset($img['full']) ? $img['full'] : (isset($img['thumb']) ? $img['thumb'] : '');
                } else {
                    $first_image = $img;
                }
                if ($first_image && strpos($first_image, 'http') !== 0) {
                    $first_image = home_url($first_image);
                }
            }
        }

        // Prepare all images as full URLs
        $all_images = array();
        foreach ($images as $idx => $img) {
            $thumb_url = '';
            $full_url = '';
            if (is_array($img)) {
                $full_url = isset($img['full']) ? $img['full'] : (isset($img['thumb']) ? $img['thumb'] : '');
                $thumb_url = isset($img['thumb']) ? $img['thumb'] : $full_url;
            } else {
                $full_url = $img;
                $thumb_url = $img;
            }
            if ($full_url && strpos($full_url, 'http') !== 0) {
                $full_url = home_url($full_url);
            }
            if ($thumb_url && strpos($thumb_url, 'http') !== 0) {
                $thumb_url = home_url($thumb_url);
            }
            if ($full_url) {
                $all_images[] = array('full' => $full_url, 'thumb' => $thumb_url, 'is_featured' => ($idx === $featured_index));
            }
        }

        // Type labels and colors (matching modal)
        $type_labels = array(
            'miejsce' => 'Miejsce',
            'ciekawostka' => 'Ciekawostka',
            'zgloszenie' => 'Zgłoszenie'
        );
        $type_label = isset($type_labels[$point['type']]) ? $type_labels[$point['type']] : 'Punkt';

        $type_colors = array(
            'miejsce' => '#8d2324',
            'ciekawostka' => '#3b82f6',
            'zgloszenie' => '#ef4444'
        );
        $type_color = isset($type_colors[$point['type']]) ? $type_colors[$point['type']] : '#6b7280';
        $badge_bg = $point['is_promo'] ? '#fbbf24' : $type_color;
        $badge_color = $point['is_promo'] ? '#111' : '#fff';

        // Build share URL
        $type_path = ($point['type'] === 'ciekawostka') ? 'ciekawostka' : (($point['type'] === 'zgloszenie') ? 'zgloszenie' : 'miejsce');
        $share_url = home_url('/' . $type_path . '/' . $point['slug'] . '/');

        // Page title – category-aware suffix for better search CTR
        $point_cat_key = $point['category'] ?? '';
        if ($point['type'] === 'miejsce' && !empty($point_cat_key)) {
            $all_place_cats = JG_Map_Ajax_Handlers::get_place_categories();
            if (isset($all_place_cats[$point_cat_key]['label'])) {
                $cat_label_lower = mb_strtolower($all_place_cats[$point_cat_key]['label']);
                $page_title = esc_html($point['title']) . ' – ' . $cat_label_lower . ' w Jeleniej Górze';
            } else {
                $page_title = esc_html($point['title']) . ' – ' . esc_html($type_label) . ' w Jeleniej Górze';
            }
        } else {
            $page_title = esc_html($point['title']) . ' – ' . esc_html($type_label) . ' w Jeleniej Górze';
        }

        // Site logo URL
        $logo_url = '';
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        }

        // Star rating data — fetched once, used for both HTML display and JSON-LD schema
        $rating_data_schema = JG_Map_Database::get_rating_data($point['id']);
        $avg_rating_schema  = $rating_data_schema['avg'];
        $total_votes        = $rating_data_schema['count'];

        ?><!doctype html>
<html lang="pl-PL">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <?php
    // Output SEO meta tags directly (OG, Twitter, canonical, schema.org)
    // We do NOT call wp_head() because it loads all Elementor/WP CSS and scripts
    $this->add_point_meta_tags();
    ?>
    <link rel="icon" href="<?php echo esc_url(get_site_icon_url(32)); ?>" sizes="32x32">
    <link rel="icon" href="<?php echo esc_url(get_site_icon_url(192)); ?>" sizes="192x192">
    <link rel="apple-touch-icon" href="<?php echo esc_url(get_site_icon_url(180)); ?>">
    <!-- Google Analytics (GA4) – added manually because wp_head() is not called on pin pages -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-B6E2GMXWCL"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-B6E2GMXWCL');
    </script>
    <!-- Microsoft Clarity – added manually because wp_head() is not called on pin pages -->
    <script type="text/javascript">
      (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
      })(window,document,"clarity","script","vrf65qsjp5");
    </script>
    <style>
        /* Standalone point page styles - no Elementor dependency */

        /*
         * --jg: multiplikator 1px identyczny jak w jg-map.css.
         * Ta strona nie ładuje wp_head() więc musi definiować go lokalnie.
         * Wszystkie font-size używają calc(N * var(--jg)) — to samo co reszta wtyczki.
         * Zakres clamp(1px, 0.1vw, 1.4px): 14px staje się 14px→19.6px (+40%).
         */
        :root {
            --jg: clamp(1px, 0.1vw, 1.4px);
        }
        @media (min-width: 1600px) { :root { --jg: 1.3px; } }
        @media (min-width: 1920px) { :root { --jg: 1.15px; } }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #111; background: #f9fafb; line-height: 1.5; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; height: auto; display: block; }
        img.emoji { display: inline; width: 1em; height: 1em; vertical-align: -0.1em; max-width: none; }

        /* Minimal site header */
        .jg-sp-site-header {
            background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .jg-sp-site-header a { display: flex; align-items: center; gap: 10px; }
        .jg-sp-site-logo { height: 40px; width: auto; }
        .jg-sp-site-name { font-size: calc(16 * var(--jg)); font-weight: 700; color: #111; }
        .jg-sp-site-nav a {
            font-size: calc(14 * var(--jg)); font-weight: 600; color: #fff; background: <?php echo $type_color; ?>;
            padding: 8px 16px; border-radius: 8px; transition: opacity 0.15s;
        }
        .jg-sp-site-nav a:hover { opacity: 0.85; }

        /* Main content container */
        .jg-sp { max-width: 800px; margin: 0 auto; padding: 28px 20px 48px; background: #fff; min-height: calc(100vh - 130px); }

        /* Map CTA Banner */
        .jg-sp-map-cta {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            background: linear-gradient(135deg, <?php echo $type_color; ?>, <?php echo $type_color; ?>cc);
            color: #fff; padding: 18px 24px; border-radius: 12px;
            margin-bottom: 28px; transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            animation: jg-cta-pulse 1.8s ease-in-out infinite;
        }
        .jg-sp-map-cta:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.25); animation: none; }
        .jg-sp-map-cta-text { font-size: calc(18 * var(--jg)); font-weight: 700; color: #fff; line-height: 1.3; }
        .jg-sp-map-cta-sub { font-size: calc(13 * var(--jg)); opacity: 0.9; margin-top: 4px; color: #fff; }
        .jg-sp-map-cta-arrow { font-size: calc(28 * var(--jg)); flex-shrink: 0; color: #fff; animation: jg-cta-arrow-nudge 1s ease-in-out infinite; }
        @keyframes jg-cta-pulse {
            0% { transform: scale(1); box-shadow: 0 2px 8px rgba(0,0,0,0.12), 0 0 0 0 <?php echo $type_color; ?>99; }
            50% { transform: scale(1.02); box-shadow: 0 6px 20px rgba(0,0,0,0.2), 0 0 0 14px <?php echo $type_color; ?>00; }
            100% { transform: scale(1); box-shadow: 0 2px 8px rgba(0,0,0,0.12), 0 0 0 0 <?php echo $type_color; ?>99; }
        }
        @keyframes jg-cta-arrow-nudge {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(10px); }
        }

        /* Header with badges */
        .jg-sp-header { display: flex; align-items: center; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid #e5e7eb; margin-bottom: 20px; flex-wrap: wrap; }
        .jg-sp-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: calc(12 * var(--jg)); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.5; }

        /* Title */
        .jg-sp-title { font-size: calc(32 * var(--jg)); font-weight: 800; color: #111; margin: 0 0 8px 0; line-height: 1.2; }
        .jg-sp-date { font-size: calc(13 * var(--jg)); color: #9ca3af; margin-bottom: 18px; }

        /* Content */
        .jg-sp-content { font-size: calc(16 * var(--jg)); line-height: 1.75; color: #374151; margin-bottom: 24px; word-wrap: break-word; }
        .jg-sp-content p { margin: 0 0 12px 0; }
        .jg-sp-content p:last-child { margin-bottom: 0; }
        .jg-sp-content strong, .jg-sp-content b { font-weight: 700; color: #111; }
        .jg-sp-content em, .jg-sp-content i { font-style: italic; }
        .jg-sp-content ul, .jg-sp-content ol { margin: 0 0 12px 24px; padding: 0; }
        .jg-sp-content li { margin-bottom: 4px; }
        .jg-sp-content a { color: #2563eb; text-decoration: underline; }
        .jg-sp-content a:hover { color: #1d4ed8; }
        .jg-sp-content a.jg-pin-link { color: #8d2324; text-decoration: underline; font-weight: 500; }
        .jg-sp-content a.jg-pin-link:hover { color: #b91c1c; }

        /* Tags */
        .jg-place-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; margin-bottom: 18px; padding-top: 8px; }
        .jg-place-tag { display: inline-block; padding: 3px 10px; border-radius: 14px; background: #f3f4f6; border: 1px solid #e5e7eb; font-size: 0.85em; color: #8d2324; font-weight: 500; line-height: 1.5; }

        /* Contact & Social */
        .jg-sp-contact { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 24px; }
        .jg-sp-contact-link { color: #2563eb; font-size: calc(15 * var(--jg)); display: inline-flex; align-items: center; gap: 6px; }
        .jg-sp-contact-link:hover { text-decoration: underline; }
        .jg-sp-social {
            display: inline-flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; border-radius: 50%;
            color: #fff; transition: opacity 0.15s;
        }
        .jg-sp-social:hover { opacity: 0.8; }
        .jg-sp-social svg { width: 20px; height: 20px; fill: #fff; }

        /* Address */
        .jg-sp-address { font-size: calc(15 * var(--jg)); color: #6b7280; margin-bottom: 24px; }
        .jg-sp-oh { margin-bottom: 24px; }
        .jg-sp-oh-title { font-size: calc(13 * var(--jg)); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin: 0 0 8px 0; }
        .jg-sp-oh-table { width: 100%; border-collapse: collapse; font-size: calc(14 * var(--jg)); color: #374151; }
        .jg-sp-oh-table tr { border-bottom: 1px solid #f3f4f6; }
        .jg-sp-oh-table tr:last-child { border-bottom: none; }
        .jg-sp-oh-day { padding: 5px 16px 5px 0; font-weight: 500; width: 55%; }
        .jg-sp-oh-time { padding: 5px 0; }
        .jg-sp-oh-closed { color: #dc2626; }

        /* Menu preview */
        .jg-sp-menu-preview { margin-bottom: 24px; padding: 14px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; }
        .jg-sp-menu-preview__title { font-size: calc(13 * var(--jg)); font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #065f46; margin-bottom: 10px; }
        .jg-sp-menu-preview__sec { font-size: calc(11 * var(--jg)); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin: 8px 0 4px 0; }
        .jg-sp-menu-preview__row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; padding: 4px 0; border-bottom: 1px solid #d1fae5; font-size: calc(14 * var(--jg)); }
        .jg-sp-menu-preview__row:last-of-type { border-bottom: none; }
        .jg-sp-menu-preview__name { color: #111; flex: 1; min-width: 0; }
        .jg-sp-menu-preview__price { color: #065f46; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
        .jg-sp-menu-preview__link { display: inline-block; margin-top: 10px; font-size: calc(13 * var(--jg)); color: #059669; font-weight: 600; }
        .jg-sp-menu-preview__link:hover { text-decoration: underline; }

        /* Gallery grid */
        .jg-sp-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; margin-bottom: 28px; }
        .jg-sp-gallery-item {
            position: relative; width: 100%; padding-bottom: 100%;
            border-radius: 12px; overflow: hidden; border: 2px solid #e5e7eb;
        }
        .jg-sp-gallery-item.is-featured { border-color: #fbbf24; border-width: 3px; }
        .jg-sp-gallery-item a { display: block; position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .jg-sp-gallery-img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* Hero image (single image) */
        .jg-sp-hero-img { width: 100%; max-height: 500px; object-fit: cover; border-radius: 12px; margin-bottom: 24px; }

        /* Share buttons */
        .jg-sp-share { display: flex; align-items: center; gap: 8px; margin-bottom: 28px; flex-wrap: wrap; }
        .jg-sp-share-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 8px; font-size: calc(13 * var(--jg)); font-weight: 600;
            color: #fff; border: none; cursor: pointer; transition: opacity 0.15s; line-height: 1.4;
        }
        .jg-sp-share-btn:hover { opacity: 0.85; }
        .jg-sp-share-btn--fb { background: #1877f2; }
        .jg-sp-share-btn--wa { background: #25d366; }
        .jg-sp-share-btn--copy { background: #6b7280; }

        /* Footer */
        .jg-sp-site-footer {
            background: #1f2937; color: #9ca3af; text-align: center;
            padding: 24px 20px; font-size: calc(13 * var(--jg)); line-height: 1.6;
        }
        .jg-sp-site-footer a { color: #d1d5db; text-decoration: underline; }
        .jg-sp-site-footer a:hover { color: #fff; }

        @media (max-width: 640px) {
            .jg-sp { padding: 16px 12px 32px; }
            .jg-sp-title { font-size: calc(24 * var(--jg)); }
            .jg-sp-map-cta { padding: 14px 16px; }
            .jg-sp-map-cta-text { font-size: calc(16 * var(--jg)); }
            .jg-sp-gallery { grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); }
            .jg-sp-hero-img { max-height: 300px; }
            .jg-sp-site-name { display: none; }
        }

        /* Redirect banner */
        .jg-redirect-notify {
            position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
            background: <?php echo $type_color; ?>; color: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }
        .jg-redirect-notify-inner {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px; flex-wrap: wrap;
        }
        .jg-redirect-icon { font-size: calc(22 * var(--jg)); flex-shrink: 0; }
        .jg-redirect-text { flex: 1; min-width: 0; }
        .jg-redirect-title { display: block; font-size: calc(19 * var(--jg)); font-weight: 700; line-height: 1.3; }
        .jg-redirect-text span { font-size: calc(15 * var(--jg)); opacity: 0.9; }
        .jg-redirect-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .jg-redirect-btn-go {
            background: #fff; color: <?php echo $type_color; ?>;
            padding: 7px 16px; border-radius: 7px; font-size: calc(13 * var(--jg)); font-weight: 700;
            white-space: nowrap; text-decoration: none; border: none; cursor: pointer;
            transition: opacity 0.15s;
        }
        .jg-redirect-btn-go:hover { opacity: 0.88; }
        .jg-redirect-btn-cancel {
            background: rgba(255,255,255,0.18); color: #fff;
            padding: 7px 14px; border-radius: 7px; font-size: calc(13 * var(--jg)); font-weight: 600;
            border: 1px solid rgba(255,255,255,0.4); cursor: pointer; white-space: nowrap;
            transition: background 0.15s;
        }
        .jg-redirect-btn-cancel:hover { background: rgba(255,255,255,0.28); }
        .jg-redirect-progress {
            height: 3px; background: rgba(255,255,255,0.4);
            transform-origin: left; width: 100%;
        }
        .jg-redirect-progress-bar {
            height: 100%; background: #fff; width: 100%;
            transition: width linear;
        }
        /* Address row with directions button */
        .jg-sp-address-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .jg-sp-address-wrap .jg-sp-address { margin-bottom: 0; flex: 1; min-width: 0; }
        .jg-sp-dir-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 18px; background: #eff6ff; color: #1d4ed8;
            border: 1.5px solid #bfdbfe; border-radius: 10px;
            font-size: calc(14 * var(--jg)); font-weight: 700;
            text-decoration: none; white-space: nowrap; flex-shrink: 0;
            transition: background 0.15s, box-shadow 0.15s;
        }
        .jg-sp-dir-btn:hover { background: #dbeafe; box-shadow: 0 2px 10px rgba(37,99,235,0.18); color: #1d4ed8; }
        .jg-sp-dir-btn svg { width: 20px; height: 20px; fill: #1d4ed8; flex-shrink: 0; }

        /* offset body so redirect banner doesn't overlap header — set dynamically by JS */
        body { padding-top: 0; }
        @media (max-width: 480px) {
            .jg-redirect-title { font-size: calc(16 * var(--jg)); }
        }

        /* ── Pulsujący baner "Zostań na tym" ───────────────────────────── */
        /* Pojawia się inline (nie zasłania treści) gdy user kliknie "Zostań" */
        @keyframes jg-stay-pulse {
            0%, 100% {
                box-shadow: 0 2px 8px rgba(0,0,0,0.15), 0 0 0 0 rgba(141,35,36,0.45);
            }
            50% {
                box-shadow: 0 4px 18px rgba(0,0,0,0.2), 0 0 0 10px rgba(141,35,36,0);
            }
        }
        .jg-stay-banner {
            display: none;
            background: linear-gradient(135deg, #8d2324 0%, #6b1a1a 100%);
            color: #fff;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 24px;
            animation: jg-stay-pulse 2s ease-in-out infinite;
        }
        .jg-stay-banner__inner {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .jg-stay-banner__icon {
            font-size: calc(32 * var(--jg));
            flex-shrink: 0;
            line-height: 1;
        }
        .jg-stay-banner__body {
            flex: 1;
            font-size: calc(16 * var(--jg));
            font-weight: 600;
            min-width: 140px;
            line-height: 1.4;
        }
        .jg-stay-banner__timer {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: calc(14 * var(--jg));
            white-space: nowrap;
            opacity: 0.92;
        }
        .jg-stay-countdown {
            font-size: calc(30 * var(--jg));
            font-family: monospace;
            font-variant-numeric: tabular-nums;
            font-weight: 800;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 6px;
            letter-spacing: 0.04em;
        }
        .jg-stay-banner__cta {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: #fff;
            font-size: calc(15 * var(--jg));
            font-weight: 700;
            padding: 9px 20px;
            border-radius: 8px;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s;
        }
        .jg-stay-banner__cta:hover {
            background: rgba(255,255,255,0.35);
            color: #fff;
        }
        @media (max-width: 600px) {
            .jg-stay-banner__timer { flex-basis: 100%; }
            .jg-stay-countdown { font-size: calc(26 * var(--jg)); }
        }
    </style>
</head>
<body>
        <!-- Sticky redirect banner -->
        <div id="jg-redirect-notify" class="jg-redirect-notify">
            <div class="jg-redirect-notify-inner">
                <span class="jg-redirect-icon">🗺️</span>
                <div class="jg-redirect-text">
                    <strong class="jg-redirect-title">Mapa interaktywna Jelenia Góra</strong>
                    <span id="jg-redirect-sub">Odkryj setki miejsc, ciekawostek i zgłoszeń &mdash; przenosisz się za <strong id="jg-countdown">10</strong>s</span>
                </div>
                <div class="jg-redirect-actions">
                    <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>" id="jg-redirect-now" class="jg-redirect-btn-go">Otwórz mapę &rarr;</a>
                    <button onclick="jgCancelRedirect()" class="jg-redirect-btn-cancel">Zostań</button>
                </div>
            </div>
            <div class="jg-redirect-progress"><div id="jg-progress-bar" class="jg-redirect-progress-bar"></div></div>
        </div>

        <!-- Minimal site header -->
        <header class="jg-sp-site-header">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php bloginfo('name'); ?>" class="jg-sp-site-logo">
                <?php endif; ?>
                <span class="jg-sp-site-name"><?php bloginfo('name'); ?></span>
            </a>
            <nav class="jg-sp-site-nav">
                <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>">Otwórz na mapie</a>
            </nav>
        </header>

        <div class="jg-sp">
            <!-- Prominent "View on Map" CTA (manual click only, no auto-redirect) -->
            <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>" class="jg-sp-map-cta">
                <div>
                    <div class="jg-sp-map-cta-text">Zobacz na mapie interaktywnej</div>
                    <div class="jg-sp-map-cta-sub"><?php echo esc_html($point['title']); ?> &mdash; <?php echo esc_html($type_label); ?> w Jeleniej Górze</div>
                </div>
                <span class="jg-sp-map-cta-arrow">&rarr;</span>
            </a>

            <!-- Pulsujący baner "Zostań na tym" — ukryty, pojawia się po kliknięciu "Zostań" -->
            <div id="jg-stay-banner" class="jg-stay-banner" role="status" aria-live="polite">
                <div class="jg-stay-banner__inner">
                    <span class="jg-stay-banner__icon">🎯</span>
                    <div class="jg-stay-banner__body">
                        Świetnie! Sprawdź to miejsce i wróć do mapy kiedy chcesz.
                    </div>
                    <div class="jg-stay-banner__timer">
                        Odkryj więcej za: <strong id="jg-stay-countdown" class="jg-stay-countdown">05:00</strong>
                    </div>
                    <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>" class="jg-stay-banner__cta">
                        Otwórz mapę &rarr;
                    </a>
                </div>
            </div>

            <!-- Header with badges -->
            <div class="jg-sp-header">
                <?php if ($point['is_promo']): ?>
                    <span class="jg-sp-badge" style="background:#fbbf24;color:#111">Miejsce sponsorowane</span>
                <?php endif; ?>
                <span class="jg-sp-badge" style="background:<?php echo $badge_bg; ?>;color:<?php echo $badge_color; ?>"><?php echo esc_html($type_label); ?></span>
            </div>

            <!-- Title -->
            <h1 class="jg-sp-title"><?php echo esc_html($point['title']); ?></h1>

            <!-- Date -->
            <div class="jg-sp-date"><?php echo get_date_from_gmt($point['created_at'], 'd.m.Y'); ?></div>

            <!-- Star rating (read-only; voting requires map + login) -->
            <?php if ($total_votes > 0):
                $sp_stars_full  = floor($avg_rating_schema);
                $sp_stars_empty = 5 - $sp_stars_full;
            ?>
            <div class="jg-sp-rating" style="display:flex;align-items:center;gap:8px;margin:10px 0 16px;flex-wrap:wrap">
                <span class="jg-sp-rating-stars" style="font-size:22px;color:#f59e0b;letter-spacing:1px" aria-label="Ocena <?php echo esc_attr(number_format($avg_rating_schema, 1)); ?> na 5 gwiazdek">
                    <?php echo str_repeat('★', $sp_stars_full) . str_repeat('☆', $sp_stars_empty); ?>
                </span>
                <strong style="font-size:16px"><?php echo esc_html(number_format($avg_rating_schema, 1)); ?></strong>
                <span style="color:#6b7280;font-size:13px">(<?php echo esc_html($total_votes); ?> <?php echo esc_html($total_votes === 1 ? 'ocena' : ($total_votes >= 2 && $total_votes <= 4 ? 'oceny' : 'ocen')); ?>)</span>
                <span style="font-size:12px;color:#92400e">— <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>" style="color:#b45309">oceń na mapie</a> (wymagane logowanie)</span>
            </div>
            <?php else: ?>
            <div style="font-size:13px;color:#9ca3af;margin:8px 0 16px">
                Brak ocen &mdash; <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>" style="color:#2563eb">przejdź na mapę i zaloguj się</a>, aby ocenić.
            </div>
            <?php endif; ?>

            <!-- Content -->
            <div class="jg-sp-content">
                <?php echo wp_kses_post($point['content']); ?>
            </div>

            <?php
            $sp_tags = !empty($point['tags']) ? json_decode($point['tags'], true) : array();
            if (!empty($sp_tags)):
            ?>
            <div class="jg-place-tags">
                <?php foreach ($sp_tags as $sp_tag): ?>
                    <a href="<?php echo esc_url(self::get_tag_url($sp_tag)); ?>" class="jg-place-tag" rel="tag">#<?php echo esc_html($sp_tag); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Contact info & social links -->
            <?php if (!empty($point['website']) || !empty($point['phone']) || !empty($point['email']) || !empty($point['facebook_url']) || !empty($point['instagram_url']) || !empty($point['linkedin_url']) || !empty($point['tiktok_url'])): ?>
                <div class="jg-sp-contact">
                    <?php if (!empty($point['phone'])): ?>
                        <a href="tel:<?php echo esc_attr($point['phone']); ?>" class="jg-sp-contact-link">&#128222; <?php echo esc_html($point['phone']); ?></a>
                    <?php endif; ?>
                    <?php if (!empty($point['email'])): ?>
                        <a href="mailto:<?php echo esc_attr($point['email']); ?>" class="jg-sp-contact-link">&#9993; <?php echo esc_html($point['email']); ?></a>
                    <?php endif; ?>
                    <?php if (!empty($point['website'])): ?>
                        <a href="<?php echo esc_url($point['website']); ?>" target="_blank" rel="noopener" class="jg-sp-contact-link">&#127760; <?php echo esc_html(parse_url($point['website'], PHP_URL_HOST) ?: $point['website']); ?></a>
                    <?php endif; ?>
                    <?php if (!empty($point['facebook_url'])): ?>
                        <a href="<?php echo esc_url($point['facebook_url']); ?>" target="_blank" rel="noopener" class="jg-sp-social" style="background:#1877f2" title="Facebook"><svg viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"/></svg></a>
                    <?php endif; ?>
                    <?php if (!empty($point['instagram_url'])): ?>
                        <a href="<?php echo esc_url($point['instagram_url']); ?>" target="_blank" rel="noopener" class="jg-sp-social" style="background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)" title="Instagram"><svg viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/></svg></a>
                    <?php endif; ?>
                    <?php if (!empty($point['linkedin_url'])): ?>
                        <a href="<?php echo esc_url($point['linkedin_url']); ?>" target="_blank" rel="noopener" class="jg-sp-social" style="background:#0077b5" title="LinkedIn"><svg viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M100.28 448H7.4V148.9h92.88zM53.79 108.1C24.09 108.1 0 83.5 0 53.8a53.79 53.79 0 0 1 107.58 0c0 29.7-24.1 54.3-53.79 54.3zM447.9 448h-92.68V302.4c0-34.7-.7-79.2-48.29-79.2-48.29 0-55.69 37.7-55.69 76.7V448h-92.78V148.9h89.08v40.8h1.3c12.4-23.5 42.69-48.3 87.83-48.3 93.97 0 111.28 61.9 111.28 142.3V448z"/></svg></a>
                    <?php endif; ?>
                    <?php if (!empty($point['tiktok_url'])): ?>
                        <a href="<?php echo esc_url($point['tiktok_url']); ?>" target="_blank" rel="noopener" class="jg-sp-social" style="background:#000" title="TikTok"><svg viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z"/></svg></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Address + Directions button -->
            <?php if (!empty($point['address']) || (!empty($point['lat']) && !empty($point['lng']))): ?>
                <div class="jg-sp-address-wrap">
                    <?php if (!empty($point['address'])): ?>
                        <div class="jg-sp-address">&#128205; <?php echo esc_html($point['address']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($point['lat']) && !empty($point['lng'])): ?>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo urlencode($point['lat'] . ',' . $point['lng']); ?>" target="_blank" rel="noopener" class="jg-sp-dir-btn">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21.71 11.29l-9-9a1 1 0 0 0-1.42 0l-9 9a1 1 0 0 0 0 1.42l9 9a1 1 0 0 0 1.42 0l9-9a1 1 0 0 0 0-1.42zM14 14.5V12h-4v3H8v-4a1 1 0 0 1 1-1h5V7.5l3.5 3.5-3.5 3.5z"/></svg>
                            <span>Dojazd</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Menu preview (gastronomy places only) -->
            <?php
            if ($point['type'] === 'miejsce' && in_array($point['category'] ?? '', JG_Map_Ajax_Handlers::get_menu_categories(), true) && JG_Map_Database::point_has_menu($point['id'])):
                $sp_menu_sections = JG_Map_Database::get_menu($point['id']);
                $sp_menu_url = home_url('/miejsce/' . $point['slug'] . '/menu/');
                if (!empty($sp_menu_sections)):
                    $sp_items_shown = 0;
                    $sp_max_items = 5;
            ?>
            <div class="jg-sp-menu-preview">
                <div class="jg-sp-menu-preview__title">🍽️ Menu</div>
                <?php foreach ($sp_menu_sections as $sp_sec): ?>
                    <?php if ($sp_items_shown >= $sp_max_items) break; ?>
                    <?php if (!empty($sp_sec['items'])): ?>
                    <div class="jg-sp-menu-preview__sec"><?php echo esc_html($sp_sec['name']); ?></div>
                    <?php foreach ($sp_sec['items'] as $sp_item): ?>
                        <?php if ($sp_items_shown >= $sp_max_items) break; $sp_items_shown++; ?>
                        <?php
                        $sp_variants = array();
                        if (!empty($sp_item['variants'])) {
                            $sp_dec = json_decode($sp_item['variants'], true);
                            if (is_array($sp_dec)) $sp_variants = $sp_dec;
                        }
                        $sp_price_str = '';
                        if (!empty($sp_variants)) {
                            if (count($sp_variants) > 1) {
                                $sp_min = null;
                                foreach ($sp_variants as $sv) { $svp = floatval($sv['price']); if ($sp_min === null || $svp < $sp_min) $sp_min = $svp; }
                                if ($sp_min !== null) $sp_price_str = 'od ' . number_format($sp_min, 2, ',', '') . '&nbsp;zł';
                            } else {
                                $sv = $sp_variants[0];
                                $sp_price_str = (!empty($sv['label']) ? esc_html($sv['label']) . ': ' : '') . number_format(floatval($sv['price']), 2, ',', '') . '&nbsp;zł';
                            }
                        } elseif ($sp_item['price'] !== null && $sp_item['price'] !== '') {
                            $sp_price_str = number_format(floatval($sp_item['price']), 2, ',', '') . '&nbsp;zł';
                        }
                        ?>
                        <div class="jg-sp-menu-preview__row">
                            <span class="jg-sp-menu-preview__name"><?php echo esc_html($sp_item['name']); ?></span>
                            <?php if ($sp_price_str): ?><span class="jg-sp-menu-preview__price"><?php echo $sp_price_str; ?></span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="<?php echo esc_url($sp_menu_url); ?>" class="jg-sp-menu-preview__link">Zobacz pełne menu →</a>
            </div>
            <?php endif; endif; ?>

            <!-- Opening hours -->
            <?php
            $sp_oh_days = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
            $sp_oh_labels = ['Mo' => 'Poniedziałek', 'Tu' => 'Wtorek', 'We' => 'Środa', 'Th' => 'Czwartek', 'Fr' => 'Piątek', 'Sa' => 'Sobota', 'Su' => 'Niedziela'];
            $sp_oh_parsed = [];
            if (!empty($point['opening_hours'])) {
                foreach (explode("\n", $point['opening_hours']) as $sp_oh_line) {
                    $sp_oh_line = trim($sp_oh_line);
                    if (preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)\s+(\d{2}:\d{2})-(\d{2}:\d{2})$/', $sp_oh_line, $sp_oh_m)) {
                        $sp_oh_parsed[$sp_oh_m[1]] = ['open' => $sp_oh_m[2], 'close' => $sp_oh_m[3]];
                    }
                }
            }
            if (!empty($sp_oh_parsed)):
            ?>
            <div class="jg-sp-oh">
                <h2 class="jg-sp-oh-title">Godziny otwarcia</h2>
                <table class="jg-sp-oh-table">
                    <?php foreach ($sp_oh_days as $sp_oh_key): ?>
                    <tr>
                        <td class="jg-sp-oh-day"><?php echo esc_html($sp_oh_labels[$sp_oh_key]); ?></td>
                        <td class="jg-sp-oh-time">
                            <?php if (isset($sp_oh_parsed[$sp_oh_key])): ?>
                                <?php echo esc_html($sp_oh_parsed[$sp_oh_key]['open'] . ' – ' . ($sp_oh_parsed[$sp_oh_key]['close'] === '24:00' ? '00:00' : $sp_oh_parsed[$sp_oh_key]['close'])); ?>
                            <?php else: ?>
                                <span class="jg-sp-oh-closed">Nieczynne</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>

            <!-- Image: hero if 1 image, gallery grid if multiple -->
            <?php if (count($all_images) === 1): ?>
                <img src="<?php echo esc_url($all_images[0]['full']); ?>" alt="<?php echo esc_attr($point['title']); ?>" class="jg-sp-hero-img">
            <?php elseif (count($all_images) > 1): ?>
                <div class="jg-sp-gallery">
                    <?php foreach ($all_images as $img_data): ?>
                        <div class="jg-sp-gallery-item<?php echo $img_data['is_featured'] ? ' is-featured' : ''; ?>">
                            <a href="<?php echo esc_url($img_data['full']); ?>" target="_blank">
                                <img src="<?php echo esc_url($img_data['thumb']); ?>" alt="<?php echo esc_attr($point['title']); ?>" class="jg-sp-gallery-img" loading="lazy">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Share bar -->
            <div class="jg-sp-share">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($share_url); ?>" target="_blank" rel="noopener" class="jg-sp-share-btn jg-sp-share-btn--fb">Facebook</a>
                <a href="https://wa.me/?text=<?php echo urlencode($point['title'] . ' - ' . $share_url); ?>" target="_blank" rel="noopener" class="jg-sp-share-btn jg-sp-share-btn--wa">WhatsApp</a>
                <button class="jg-sp-share-btn jg-sp-share-btn--copy" onclick="navigator.clipboard.writeText('<?php echo esc_js($share_url); ?>').then(function(){var b=event.target;b.textContent='Skopiowano!';setTimeout(function(){b.textContent='Kopiuj link'},2000)})">Kopiuj link</button>
            </div>
        </div>

        <?php
        // Internal links: show other published points for Google to crawl
        $this->render_related_points($point);
        ?>

        <!-- Minimal footer -->
        <footer class="jg-sp-site-footer">
            &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?> &mdash;
            <a href="<?php echo esc_url(home_url('/')); ?>">Wróć do mapy</a>
        </footer>

        <script>
        (function() {
            var DELAY = 10;
            var mapUrl = <?php echo json_encode(home_url('/?from=point#point-' . $point['id'])); ?>;
            var countdown = DELAY;
            var timer = null;
            var cancelled = false;

            var bar = document.getElementById('jg-progress-bar');
            var countEl = document.getElementById('jg-countdown');
            var notify = document.getElementById('jg-redirect-notify');

            // Dopasuj padding-top body do rzeczywistej wysokości bannera (czcionki dynamiczne = różna wysokość)
            function syncBodyPadding() {
                if (notify && notify.style.display !== 'none') {
                    document.body.style.paddingTop = notify.offsetHeight + 'px';
                }
            }
            syncBodyPadding();
            window.addEventListener('resize', syncBodyPadding);

            // Animate the progress bar over DELAY seconds
            if (bar) {
                bar.style.transition = 'width ' + DELAY + 's linear';
                // Trigger in next frame so transition fires
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        bar.style.width = '0%';
                    });
                });
            }

            timer = setInterval(function() {
                if (cancelled) return;
                countdown--;
                if (countEl) countEl.textContent = countdown;
                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.href = mapUrl;
                }
            }, 1000);

            window.jgCancelRedirect = function() {
                cancelled = true;
                clearInterval(timer);
                var banner = document.getElementById('jg-redirect-notify');
                if (banner) {
                    window.removeEventListener('resize', syncBodyPadding);
                    banner.style.transition = 'opacity 0.3s';
                    banner.style.opacity = '0';
                    setTimeout(function() {
                        banner.style.display = 'none';
                        document.body.style.paddingTop = '0';
                    }, 300);
                }

                // Pokaż pulsujący baner z odliczaniem po kliknięciu "Zostań"
                var stayBanner = document.getElementById('jg-stay-banner');
                if (stayBanner && !stayBanner._stayShown) {
                    if (stayBanner) {
                        stayBanner._stayShown = true;
                        stayBanner.style.display = 'block';
                        var staySeconds = 5 * 60; // 5 minut
                        var stayEl = document.getElementById('jg-stay-countdown');
                        var stayTimer = setInterval(function() {
                            staySeconds--;
                            if (stayEl) {
                                var m = Math.floor(staySeconds / 60);
                                var s = staySeconds % 60;
                                stayEl.textContent = ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
                            }
                            if (staySeconds <= 0) {
                                clearInterval(stayTimer);
                                stayBanner.style.transition = 'opacity 0.5s';
                                stayBanner.style.opacity = '0';
                                setTimeout(function() { stayBanner.style.display = 'none'; }, 500);
                            }
                        }, 1000);
                    }
                }
            };
        })();
        </script>
</body>
</html>
        <?php
    }

    /**
     * Render related points section with internal links for SEO crawling.
     * Shows nearby points of the same type + recent points of other types.
     */
    private function render_related_points($current_point) {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Get up to 6 other published points (3 same type, 3 other types)
        $same_type = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT title, slug, type, address FROM $table
                 WHERE status = 'publish' AND slug IS NOT NULL AND slug != '' AND id != %d AND type = %s
                 ORDER BY updated_at DESC LIMIT 3",
                (int) $current_point['id'],
                $current_point['type']
            ),
            ARRAY_A
        );

        $other_type = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT title, slug, type, address FROM $table
                 WHERE status = 'publish' AND slug IS NOT NULL AND slug != '' AND id != %d AND type != %s
                 ORDER BY updated_at DESC LIMIT 3",
                (int) $current_point['id'],
                $current_point['type']
            ),
            ARRAY_A
        );

        $related = array_merge($same_type ?: array(), $other_type ?: array());

        if (empty($related)) {
            return;
        }

        $type_paths = array(
            'miejsce' => 'miejsce',
            'ciekawostka' => 'ciekawostka',
            'zgloszenie' => 'zgloszenie'
        );
        $type_labels = array(
            'miejsce' => 'Miejsce',
            'ciekawostka' => 'Ciekawostka',
            'zgloszenie' => 'Zgłoszenie'
        );

        ?>
        <div style="max-width:800px;margin:0 auto;padding:24px 20px 0;">
            <h2 style="font-size:calc(18 * var(--jg));font-weight:700;color:#374151;margin-bottom:16px;">Inne miejsca w Jeleniej Górze</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
                <?php foreach ($related as $r):
                    $path = isset($type_paths[$r['type']]) ? $type_paths[$r['type']] : 'miejsce';
                    $url = home_url('/' . $path . '/' . $r['slug'] . '/');
                    $label = isset($type_labels[$r['type']]) ? $type_labels[$r['type']] : '';
                ?>
                <a href="<?php echo esc_url($url); ?>" style="display:block;padding:14px 16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;transition:border-color 0.15s;">
                    <div style="font-size:calc(14 * var(--jg));font-weight:600;color:#111;line-height:1.4;"><?php echo esc_html($r['title']); ?></div>
                    <?php if (!empty($r['address'])): ?>
                    <div style="font-size:calc(12 * var(--jg));color:#9ca3af;margin-top:4px;"><?php echo esc_html($r['address']); ?></div>
                    <?php endif; ?>
                    <div style="font-size:calc(11 * var(--jg));color:#6b7280;margin-top:4px;"><?php echo esc_html($label); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render fallback minimal HTML page (no theme)
     */
    private function render_fallback_page($point, $request_id = 'unknown', $user_agent_short = '') {

        $images = json_decode($point['images'], true) ?: array();
        $first_image = '';
        if (!empty($images)) {
            $featured_index = isset($point['featured_image_index']) ? (int)$point['featured_image_index'] : 0;
            $img_index = isset($images[$featured_index]) ? $featured_index : 0;
            if (isset($images[$img_index])) {
                $img = $images[$img_index];
                if (is_array($img)) {
                    $first_image = isset($img['full']) ? $img['full'] : (isset($img['thumb']) ? $img['thumb'] : '');
                } else {
                    $first_image = $img;
                }
                if ($first_image && strpos($first_image, 'http') !== 0) {
                    $first_image = home_url($first_image);
                }
            }
        }

        // Fallback image for og:image when point has no images
        if (empty($first_image)) {
            $site_icon_id = get_option('site_icon');
            if ($site_icon_id) {
                $first_image = wp_get_attachment_image_url($site_icon_id, 'full');
            }
            if (empty($first_image)) {
                $custom_logo_id = get_theme_mod('custom_logo');
                if ($custom_logo_id) {
                    $first_image = wp_get_attachment_image_url($custom_logo_id, 'full');
                }
            }
            if (empty($first_image)) {
                $first_image = home_url('/wp-content/uploads/jg-og-default.png');
            }
        }

        // Meta description for Google search results (max 160 chars recommended)
        if (!empty($point['excerpt'])) {
            $description = wp_trim_words(strip_tags($point['excerpt']), 30);
        } elseif (!empty($point['content'])) {
            $description = wp_trim_words(strip_tags($point['content']), 30);
        } else {
            // Category-aware fallback description for better CTR when no excerpt/content
            $desc_cat_key  = $point['category'] ?? '';
            $desc_type_key = $point['type'] ?? 'miejsce';
            if ($desc_type_key === 'ciekawostka') {
                $description = 'Ciekawostka: ' . $point['title'] . ' – odkryj historię i szczegóły na jeleniogorzanietomy.pl, interaktywna mapa Jeleniej Góry.';
            } elseif ($desc_type_key === 'miejsce' && !empty($desc_cat_key)) {
                $desc_place_cats = JG_Map_Ajax_Handlers::get_place_categories();
                if (!empty($desc_place_cats[$desc_cat_key]['has_menu'])) {
                    $description = $point['title'] . ' w Jeleniej Górze – godziny otwarcia, menu, adres i mapa dojazdu na jeleniogorzanietomy.pl';
                } elseif (isset($desc_place_cats[$desc_cat_key]['label'])) {
                    $desc_cat_label = mb_strtolower($desc_place_cats[$desc_cat_key]['label']);
                    $description = $point['title'] . ' w Jeleniej Górze – ' . $desc_cat_label . '. Zdjęcia, mapa dojazdu i szczegółowe informacje na jeleniogorzanietomy.pl';
                } else {
                    $description = $point['title'] . ' w Jeleniej Górze – szczegółowe informacje, zdjęcia i mapa dojazdu na jeleniogorzanietomy.pl';
                }
            } else {
                $description = $point['title'] . ' w Jeleniej Górze – szczegółowe informacje, zdjęcia i mapa dojazdu na jeleniogorzanietomy.pl';
            }
        }

        $type_path = ($point['type'] === 'ciekawostka') ? 'ciekawostka' : (($point['type'] === 'zgloszenie') ? 'zgloszenie' : 'miejsce');
        $url = home_url('/' . $type_path . '/' . $point['slug'] . '/');

        // Singular type label (for data-pin-description and article:section)
        $type_labels_singular = array(
            'miejsce' => 'Miejsce',
            'ciekawostka' => 'Ciekawostka',
            'zgloszenie' => 'Zgłoszenie'
        );
        $type_label_singular = isset($type_labels_singular[$point['type']]) ? $type_labels_singular[$point['type']] : 'Punkt';

        // Determine robots directive (same logic as add_point_meta_tags)
        $robots_content = 'index, follow'; // Default
        if (get_option('blog_public') == '0') {
            $robots_content = 'noindex, nofollow';
        }
        // Check Elementor maintenance mode
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');
        if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
            $robots_content = 'noindex, nofollow';
        }
        // Admin-set noindex override
        if (!empty($point['seo_noindex'])) {
            $robots_content = 'noindex, follow';
        }

        ?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($point['title']); ?> – Jelenia Góra | JeleniogorzaNieTomy</title>
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta name="robots" content="<?php echo esc_attr($robots_content); ?>">
    <link rel="canonical" href="<?php echo esc_url(!empty($point['seo_canonical']) ? $point['seo_canonical'] : $url); ?>">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo esc_attr($point['title']); ?>">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:url" content="<?php echo esc_url($url); ?>">
    <meta property="og:site_name" content="<?php bloginfo('name'); ?>">
    <meta property="og:locale" content="pl_PL">
    <meta property="og:image" content="<?php echo esc_url($first_image); ?>">
    <meta property="og:image:secure_url" content="<?php echo esc_url($first_image); ?>">
    <meta property="og:image:alt" content="<?php echo esc_attr($point['title']); ?>">
    <meta property="og:image:type" content="<?php echo esc_attr($this->detect_image_type($first_image)); ?>">
    <meta property="article:published_time" content="<?php echo esc_attr(get_date_from_gmt($point['created_at'], 'c')); ?>">
    <?php if (!empty($point['updated_at'])): ?>
    <meta property="article:modified_time" content="<?php echo esc_attr(get_date_from_gmt($point['updated_at'], 'c')); ?>">
    <meta property="og:updated_time" content="<?php echo esc_attr(get_date_from_gmt($point['updated_at'], 'c')); ?>">
    <?php endif; ?>
    <meta property="article:section" content="<?php echo esc_attr($type_label_singular); ?>">
    <meta property="article:tag" content="Jelenia Góra">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr($point['title']); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
    <meta name="twitter:image" content="<?php echo esc_url($first_image); ?>">
    <meta name="twitter:image:alt" content="<?php echo esc_attr($point['title']); ?>">
    <meta name="geo.position" content="<?php echo esc_attr($point['lat'] . ';' . $point['lng']); ?>">
    <meta name="ICBM" content="<?php echo esc_attr($point['lat'] . ', ' . $point['lng']); ?>">
    <?php
    // Plural type label (for breadcrumbs)
    $type_labels = array(
        'miejsce' => 'Miejsca',
        'ciekawostka' => 'Ciekawostki',
        'zgloszenie' => 'Zgłoszenia'
    );
    $type_label = isset($type_labels[$point['type']]) ? $type_labels[$point['type']] : 'Mapa';
    // Resolve schema.org @type from category (fallback page)
    $fb_schema_type = 'Place';
    $fb_point_category = $point['category'] ?? '';
    if ($point['type'] === 'miejsce') {
        $fb_place_cats = JG_Map_Ajax_Handlers::get_place_categories();
        $fb_schema_type = isset($fb_place_cats[$fb_point_category]['schema_type']) ? $fb_place_cats[$fb_point_category]['schema_type'] : 'LocalBusiness';
    } elseif ($point['type'] === 'ciekawostka') {
        $fb_cur_cats = JG_Map_Ajax_Handlers::get_curiosity_categories();
        $fb_schema_type = isset($fb_cur_cats[$fb_point_category]['schema_type']) ? $fb_cur_cats[$fb_point_category]['schema_type'] : 'TouristAttraction';
    }
    global $wpdb;
    $fb_rating_data = JG_Map_Database::get_rating_data($point['id']);
    $fb_avg_rating  = $fb_rating_data['avg'];
    $fb_total_votes = $fb_rating_data['count'];
    $fb_date_created = !empty($point['created_at']) ? get_date_from_gmt($point['created_at'], 'c') : null;
    $fb_date_modified = !empty($point['updated_at']) ? get_date_from_gmt($point['updated_at'], 'c') : null;
    ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "<?php echo esc_html($fb_schema_type); ?>",
                "@id": <?php echo json_encode($url . '#place'); ?>,
                "name": <?php echo json_encode($point['title']); ?>,
                "description": <?php echo json_encode($description); ?>,
                "url": <?php echo json_encode($url); ?>,
                <?php if (!empty($first_image)): ?>
                "image": {
                    "@type": "ImageObject",
                    "url": <?php echo json_encode($first_image); ?>,
                    "caption": <?php echo json_encode($point['title']); ?>
                },
                <?php endif; ?>
                "geo": {
                    "@type": "GeoCoordinates",
                    "latitude": <?php echo json_encode($point['lat']); ?>,
                    "longitude": <?php echo json_encode($point['lng']); ?>
                },
                "address": {
                    "@type": "PostalAddress",
                    <?php if (!empty($point['address'])): ?>"streetAddress": <?php echo json_encode($point['address']); ?>,<?php endif; ?>
                    "addressLocality": "Jelenia Góra",
                    "addressRegion": "Dolnośląskie",
                    "addressCountry": "PL"
                }
                <?php if (!empty($point['phone'])): ?>
                ,"telephone": <?php echo json_encode($point['phone']); ?>
                <?php endif; ?>
                <?php if (!empty($point['email'])): ?>
                ,"email": <?php echo json_encode($point['email']); ?>
                <?php endif; ?>
                <?php if ($point['type'] === 'miejsce' && in_array($point['category'] ?? '', JG_Map_Ajax_Handlers::get_menu_categories(), true) && JG_Map_Database::point_has_menu($point['id'])): ?>
                ,"hasMenu": <?php echo json_encode(home_url('/miejsce/' . $point['slug'] . '/menu/')); ?>
                <?php endif; ?>
                <?php if (!empty($point['opening_hours'])): ?>
                <?php
                $fb_oh_days = ['Mo'=>'https://schema.org/Monday','Tu'=>'https://schema.org/Tuesday','We'=>'https://schema.org/Wednesday','Th'=>'https://schema.org/Thursday','Fr'=>'https://schema.org/Friday','Sa'=>'https://schema.org/Saturday','Su'=>'https://schema.org/Sunday'];
                $fb_oh_spec = [];
                foreach (explode("\n", $point['opening_hours']) as $fb_oh_line) {
                    $fb_oh_line = trim($fb_oh_line);
                    if (preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)\s+(\d{2}:\d{2})-(\d{2}:\d{2})$/', $fb_oh_line, $fb_oh_m)) {
                        $fb_oh_spec[] = ['@type'=>'OpeningHoursSpecification','dayOfWeek'=>$fb_oh_days[$fb_oh_m[1]],'opens'=>$fb_oh_m[2],'closes'=>$fb_oh_m[3]];
                    }
                }
                ?>
                <?php if (!empty($fb_oh_spec)): ?>
                ,"openingHoursSpecification": <?php echo json_encode($fb_oh_spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                <?php endif; ?>
                <?php endif; ?>
                <?php
                $fb_rating_supported_types = ['LocalBusiness','FoodEstablishment','Museum','SportsActivityLocation','Hotel','Restaurant','Store','HealthAndBeautyBusiness','EntertainmentBusiness','LodgingBusiness','AutoDealer','FinancialService','ProfessionalService'];
                if ($fb_total_votes > 0 && in_array($fb_schema_type, $fb_rating_supported_types, true)): ?>
                ,"aggregateRating": {
                    "@type": "AggregateRating",
                    "ratingValue": <?php echo json_encode($fb_avg_rating); ?>,
                    "ratingCount": <?php echo json_encode($fb_total_votes); ?>,
                    "bestRating": 5,
                    "worstRating": 1
                }
                <?php endif; ?>
                <?php
                $same_as_fb = array();
                if (!empty($point['facebook_url'])) $same_as_fb[] = $point['facebook_url'];
                if (!empty($point['instagram_url'])) $same_as_fb[] = $point['instagram_url'];
                if (!empty($point['linkedin_url'])) $same_as_fb[] = $point['linkedin_url'];
                if (!empty($point['tiktok_url'])) $same_as_fb[] = $point['tiktok_url'];
                if (!empty($point['website'])) $same_as_fb[] = $point['website'];
                if (!empty($same_as_fb)): ?>
                ,"sameAs": <?php echo json_encode($same_as_fb); ?>
                <?php endif; ?>
            },
            {
                "@type": "BreadcrumbList",
                "@id": <?php echo json_encode($url . '#breadcrumb'); ?>,
                "itemListElement": [
                    {
                        "@type": "ListItem",
                        "position": 1,
                        "name": "Strona główna",
                        "item": <?php echo json_encode(home_url('/')); ?>
                    },
                    {
                        "@type": "ListItem",
                        "position": 2,
                        "name": <?php echo json_encode($type_label); ?>,
                        "item": <?php echo json_encode(home_url('/mapa/')); ?>
                    },
                    {
                        "@type": "ListItem",
                        "position": 3,
                        "name": <?php echo json_encode($point['title']); ?>
                    }
                ]
            },
            {
                "@type": "WebPage",
                "@id": <?php echo json_encode($url . '#webpage'); ?>,
                "url": <?php echo json_encode($url); ?>,
                "name": <?php echo json_encode($point['title'] . ' - Jelenia Góra'); ?>,
                "isPartOf": {"@id": <?php echo json_encode(home_url('/#website')); ?>},
                "breadcrumb": {"@id": <?php echo json_encode($url . '#breadcrumb'); ?>},
                "inLanguage": "pl-PL"
                <?php if ($fb_date_created): ?>
                ,"datePublished": <?php echo json_encode($fb_date_created); ?>
                <?php endif; ?>
                <?php if ($fb_date_modified): ?>
                ,"dateModified": <?php echo json_encode($fb_date_modified); ?>
                <?php endif; ?>
            }
        ]
    }
    </script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.6; }
        h1 { color: #8d2324; }
        img { max-width: 100%; height: auto; }
        .jg-fb-cta { margin: 20px 0; }
        .jg-fb-cta a { display: inline-block; padding: 10px 20px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; margin-right: 10px; margin-bottom: 8px; }
        .jg-fb-cta a:hover { background: #1d4ed8; }
        .jg-fb-cta a.jg-fb-site { background: #8d2324; }
        .jg-fb-cta a.jg-fb-site:hover { background: #a02829; }
    </style>
</head>
<body>
    <h1><?php echo esc_html($point['title']); ?></h1>
    <?php if ($first_image): ?>
    <img src="<?php echo esc_url($first_image); ?>" alt="<?php echo esc_attr($point['title']); ?>" data-pin-description="<?php echo esc_attr($point['title'] . ' - ' . $type_label_singular . ' w Jeleniej Górze'); ?>">
    <?php endif; ?>
    <?php if ($fb_total_votes > 0):
        $fb_stars_full  = floor($fb_avg_rating);
        $fb_stars_empty = 5 - $fb_stars_full;
        $fb_stars_html  = str_repeat('★', $fb_stars_full) . str_repeat('☆', $fb_stars_empty);
    ?>
    <div style="margin:12px 0;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;display:inline-block">
        <span style="font-size:20px;color:#f59e0b;letter-spacing:2px"><?php echo esc_html($fb_stars_html); ?></span>
        <strong style="margin-left:8px;font-size:16px"><?php echo esc_html(number_format($fb_avg_rating, 1)); ?></strong>
        <span style="color:#6b7280;font-size:13px;margin-left:4px">(<?php echo esc_html($fb_total_votes); ?> <?php echo esc_html($fb_total_votes === 1 ? 'ocena' : ($fb_total_votes >= 2 && $fb_total_votes <= 4 ? 'oceny' : 'ocen')); ?>)</span>
        <div style="font-size:12px;color:#92400e;margin-top:4px">Aby ocenić to miejsce, <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>" style="color:#b45309">przejdź na mapę i zaloguj się</a>.</div>
    </div>
    <?php else: ?>
    <div style="margin:12px 0;padding:10px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;display:inline-block;font-size:13px;color:#6b7280">
        Brak ocen. Aby ocenić to miejsce, <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>" style="color:#2563eb">przejdź na mapę i zaloguj się</a>.
    </div>
    <?php endif; ?>
    <div><?php echo wp_kses_post($point['content']); ?></div>
    <?php
    $fb_tags = !empty($point['tags']) ? json_decode($point['tags'], true) : array();
    if (!empty($fb_tags)):
    ?>
    <div class="jg-place-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0">
        <?php foreach ($fb_tags as $fb_tag): ?>
            <a href="<?php echo esc_url(self::get_tag_url($fb_tag)); ?>" rel="tag" style="display:inline-block;padding:3px 10px;border-radius:14px;background:#f3f4f6;border:1px solid #e5e7eb;font-size:0.85em;color:#8d2324;font-weight:500;text-decoration:none">#<?php echo esc_html($fb_tag); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="jg-fb-cta">
        <a href="<?php echo esc_url(home_url('/?from=point#point-' . $point['id'])); ?>">Zobacz na mapie</a>
        <?php if (!empty($point['phone'])): ?>
        <a href="tel:<?php echo esc_attr($point['phone']); ?>"><?php echo esc_html($point['phone']); ?></a>
        <?php endif; ?>
        <?php if (!empty($point['email'])): ?>
        <a href="mailto:<?php echo esc_attr($point['email']); ?>"><?php echo esc_html($point['email']); ?></a>
        <?php endif; ?>
        <?php if (!empty($point['website'])): ?>
        <a href="<?php echo esc_url($point['website']); ?>" target="_blank" rel="noopener" class="jg-fb-site">Odwiedź stronę</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($point['address'])): ?>
    <p><strong>Adres:</strong> <?php echo esc_html($point['address']); ?></p>
    <?php endif; ?>
    <p><strong>Lokalizacja:</strong> <?php echo esc_html($point['lat']); ?>, <?php echo esc_html($point['lng']); ?></p>
    <?php $this->render_related_points($point); ?>
</body>
</html><?php
    }

    /**
     * Detect image MIME type from URL extension
     */
    private function detect_image_type($url) {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $types = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
        );
        return isset($types[$extension]) ? $types[$extension] : 'image/jpeg';
    }

    /**
     * Get the URL of the page containing [jg_map_directory] shortcode
     */
    private function get_catalog_page_url() {
        $cached = get_transient('jg_map_catalog_url');
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $page_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ('page','post') AND post_content LIKE %s LIMIT 1",
                'publish',
                '%' . $wpdb->esc_like('[jg_map_directory') . '%'
            )
        );

        $url = $page_id ? get_permalink($page_id) : '';
        set_transient('jg_map_catalog_url', $url, HOUR_IN_SECONDS);

        return $url;
    }

    /**
     * Add SEO meta tags for point pages
     */
    public function add_point_meta_tags() {
        global $jg_current_point;

        if (empty($jg_current_point)) {
            return;
        }

        $point = $jg_current_point;

        // Determine robots directive based on WordPress settings and Elementor maintenance mode
        // Note: We still generate all meta tags even if indexing is discouraged,
        // as they're useful for social sharing (Open Graph, Twitter Cards)
        $robots_content = 'index, follow'; // Default

        // Check WordPress search engine visibility setting
        if (get_option('blog_public') == '0') {
            $robots_content = 'noindex, nofollow';
        }

        // Check Elementor maintenance mode - should block indexing during maintenance
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');
        if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
            $robots_content = 'noindex, nofollow';
        }

        // Admin-set noindex override (e.g. for cannibalizing pins)
        if (!empty($point['seo_noindex'])) {
            $robots_content = 'noindex, follow';
        }

        $images = json_decode($point['images'], true) ?: array();

        // Get featured image (or first image as fallback) - ensure it's a full URL
        $first_image = '';
        if (!empty($images)) {
            // Use featured image index if set, otherwise use first image (index 0)
            $featured_index = isset($point['featured_image_index']) ? (int)$point['featured_image_index'] : 0;
            $img_index = isset($images[$featured_index]) ? $featured_index : 0;

            if (isset($images[$img_index])) {
                $img = $images[$img_index];
                // Support both old format (string URL) and new format (object with thumb/full)
                if (is_array($img)) {
                    $first_image = isset($img['full']) ? $img['full'] : (isset($img['thumb']) ? $img['thumb'] : '');
                } else {
                    $first_image = $img;
                }

                // Ensure absolute URL
                if ($first_image && strpos($first_image, 'http') !== 0) {
                    $first_image = home_url($first_image);
                }
            }
        }

        // Fallback image for og:image when point has no images
        // Facebook requires og:image to be explicitly set
        if (empty($first_image)) {
            $first_image = home_url('/wp-content/uploads/2026/02/no_photo.jpg');
        }

        // Try to get image dimensions for og:image:width/height (cached)
        $img_width = 0;
        $img_height = 0;
        if (!empty($first_image)) {
            $cache_key = 'jg_img_dim_' . md5($first_image);
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                $img_width = $cached['w'];
                $img_height = $cached['h'];
            } else {
                // Convert URL to local path for faster access
                $upload_dir = wp_upload_dir();
                $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $first_image);
                if (file_exists($local_path)) {
                    $size = @getimagesize($local_path);
                    if ($size) {
                        $img_width = $size[0];
                        $img_height = $size[1];
                    }
                }
                set_transient($cache_key, array('w' => $img_width, 'h' => $img_height), DAY_IN_SECONDS);
            }
        }

        // Meta description for Google search results (max 160 chars recommended)
        if (!empty($point['excerpt'])) {
            $description = wp_trim_words(strip_tags($point['excerpt']), 30);
        } elseif (!empty($point['content'])) {
            $description = wp_trim_words(strip_tags($point['content']), 30);
        } else {
            // Category-aware fallback description for better CTR when no excerpt/content
            $desc_cat_key  = $point['category'] ?? '';
            $desc_type_key = $point['type'] ?? 'miejsce';
            if ($desc_type_key === 'ciekawostka') {
                $description = 'Ciekawostka: ' . $point['title'] . ' – odkryj historię i szczegóły na jeleniogorzanietomy.pl, interaktywna mapa Jeleniej Góry.';
            } elseif ($desc_type_key === 'miejsce' && !empty($desc_cat_key)) {
                $desc_place_cats = JG_Map_Ajax_Handlers::get_place_categories();
                if (!empty($desc_place_cats[$desc_cat_key]['has_menu'])) {
                    $description = $point['title'] . ' w Jeleniej Górze – godziny otwarcia, menu, adres i mapa dojazdu na jeleniogorzanietomy.pl';
                } elseif (isset($desc_place_cats[$desc_cat_key]['label'])) {
                    $desc_cat_label = mb_strtolower($desc_place_cats[$desc_cat_key]['label']);
                    $description = $point['title'] . ' w Jeleniej Górze – ' . $desc_cat_label . '. Zdjęcia, mapa dojazdu i szczegółowe informacje na jeleniogorzanietomy.pl';
                } else {
                    $description = $point['title'] . ' w Jeleniej Górze – szczegółowe informacje, zdjęcia i mapa dojazdu na jeleniogorzanietomy.pl';
                }
            } else {
                $description = $point['title'] . ' w Jeleniej Górze – szczegółowe informacje, zdjęcia i mapa dojazdu na jeleniogorzanietomy.pl';
            }
        }

        // Determine URL path based on point type
        $type_path = 'miejsce'; // default
        if ($point['type'] === 'ciekawostka') {
            $type_path = 'ciekawostka';
        } elseif ($point['type'] === 'zgloszenie') {
            $type_path = 'zgloszenie';
        }

        $url = home_url('/' . $type_path . '/' . $point['slug'] . '/');

        // Determine type labels (used in OG article:section and JSON-LD breadcrumbs)
        $type_labels = array(
            'miejsce' => 'Miejsca',
            'ciekawostka' => 'Ciekawostki',
            'zgloszenie' => 'Zgłoszenia'
        );
        $type_label = isset($type_labels[$point['type']]) ? $type_labels[$point['type']] : 'Mapa';

        ?>
        <meta name="description" content="<?php echo esc_attr($description); ?>">
        <meta name="robots" content="<?php echo esc_attr($robots_content); ?>">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="article">
        <meta property="og:title" content="<?php echo esc_attr($point['title'] . ' – Jelenia Góra'); ?>">
        <meta property="og:description" content="<?php echo esc_attr($description); ?>">
        <meta property="og:url" content="<?php echo esc_url($url); ?>">
        <meta property="og:site_name" content="<?php bloginfo('name'); ?>">
        <meta property="og:locale" content="pl_PL">
        <meta property="og:image" content="<?php echo esc_url($first_image); ?>">
        <meta property="og:image:secure_url" content="<?php echo esc_url($first_image); ?>">
        <meta property="og:image:alt" content="<?php echo esc_attr($point['title']); ?>">
        <meta property="og:image:type" content="<?php echo esc_attr($this->detect_image_type($first_image)); ?>">
        <?php if ($img_width > 0 && $img_height > 0): ?>
        <meta property="og:image:width" content="<?php echo (int)$img_width; ?>">
        <meta property="og:image:height" content="<?php echo (int)$img_height; ?>">
        <?php endif; ?>

        <!-- Article metadata for Rich Pins -->
        <meta property="article:published_time" content="<?php echo esc_attr(get_date_from_gmt($point['created_at'], 'c')); ?>">
        <?php if (!empty($point['updated_at'])): ?>
        <meta property="article:modified_time" content="<?php echo esc_attr(get_date_from_gmt($point['updated_at'], 'c')); ?>">
        <meta property="og:updated_time" content="<?php echo esc_attr(get_date_from_gmt($point['updated_at'], 'c')); ?>">
        <?php endif; ?>
        <meta property="article:section" content="<?php echo esc_attr($type_label); ?>">
        <meta property="article:tag" content="Jelenia Góra">
        <?php
        $point_tags = !empty($point['tags']) ? json_decode($point['tags'], true) : array();
        if (is_array($point_tags) && !empty($point_tags)):
            foreach ($point_tags as $ptag): ?>
        <meta property="article:tag" content="<?php echo esc_attr($ptag); ?>">
            <?php endforeach; ?>
        <meta name="keywords" content="<?php echo esc_attr(implode(', ', array_merge(array('Jelenia Góra', $point['title']), $point_tags))); ?>">
        <?php else: ?>
        <meta name="keywords" content="<?php echo esc_attr('Jelenia Góra, ' . $point['title']); ?>">
        <?php endif; ?>

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo esc_attr($point['title'] . ' – Jelenia Góra'); ?>">
        <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
        <meta name="twitter:image" content="<?php echo esc_url($first_image); ?>">
        <meta name="twitter:image:alt" content="<?php echo esc_attr($point['title']); ?>">

        <!-- Geo tags -->
        <meta name="geo.position" content="<?php echo esc_attr($point['lat'] . ';' . $point['lng']); ?>">
        <meta name="ICBM" content="<?php echo esc_attr($point['lat'] . ', ' . $point['lng']); ?>">

        <!-- Canonical URL -->
        <link rel="canonical" href="<?php echo esc_url(!empty($point['seo_canonical']) ? $point['seo_canonical'] : $url); ?>">

        <!-- Schema.org JSON-LD structured data -->
        <?php
        // Resolve schema.org @type from category
        $schema_type = 'Place';
        $point_category = $point['category'] ?? '';
        if ($point['type'] === 'miejsce') {
            $place_cats = JG_Map_Ajax_Handlers::get_place_categories();
            $schema_type = isset($place_cats[$point_category]['schema_type']) ? $place_cats[$point_category]['schema_type'] : 'LocalBusiness';
        } elseif ($point['type'] === 'ciekawostka') {
            $cur_cats = JG_Map_Ajax_Handlers::get_curiosity_categories();
            $schema_type = isset($cur_cats[$point_category]['schema_type']) ? $cur_cats[$point_category]['schema_type'] : 'TouristAttraction';
        }
        $rating_data_schema  = JG_Map_Database::get_rating_data($point['id']);
        $avg_rating_schema   = $rating_data_schema['avg'];
        $total_votes         = $rating_data_schema['count'];
        $date_created_schema = !empty($point['created_at']) ? get_date_from_gmt($point['created_at'], 'c') : null;
        $date_modified_schema = !empty($point['updated_at']) ? get_date_from_gmt($point['updated_at'], 'c') : null;
        ?>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@graph": [
                {
                    "@type": "<?php echo esc_html($schema_type); ?>",
                    "@id": <?php echo json_encode($url . '#place'); ?>,
                    "name": <?php echo json_encode($point['title']); ?>,
                    "description": <?php echo json_encode($description); ?>,
                    "url": <?php echo json_encode($url); ?>,
                    <?php if (!empty($first_image)): ?>
                    "image": {
                        "@type": "ImageObject",
                        "url": <?php echo json_encode($first_image); ?>,
                        "caption": <?php echo json_encode($point['title']); ?>
                    },
                    <?php endif; ?>
                    "geo": {
                        "@type": "GeoCoordinates",
                        "latitude": <?php echo json_encode($point['lat']); ?>,
                        "longitude": <?php echo json_encode($point['lng']); ?>
                    },
                    "address": {
                        "@type": "PostalAddress",
                        <?php if (!empty($point['address'])): ?>
                        "streetAddress": <?php echo json_encode($point['address']); ?>,
                        <?php endif; ?>
                        "addressLocality": "Jelenia Góra",
                        "addressRegion": "Dolnośląskie",
                        "addressCountry": "PL"
                    }
                    <?php if (!empty($point['phone'])): ?>
                    ,"telephone": <?php echo json_encode($point['phone']); ?>
                    <?php endif; ?>
                    <?php if (!empty($point['email'])): ?>
                    ,"email": <?php echo json_encode($point['email']); ?>
                    <?php endif; ?>
                    <?php if ($point['type'] === 'miejsce' && in_array($point['category'] ?? '', JG_Map_Ajax_Handlers::get_menu_categories(), true) && JG_Map_Database::point_has_menu($point['id'])): ?>
                    <?php
                    $ld_menu_url      = home_url('/miejsce/' . $point['slug'] . '/menu/');
                    $ld_menu_sections = JG_Map_Database::get_menu($point['id']);
                    $ld_menu_obj      = array(
                        '@type' => 'Menu',
                        '@id'   => $ld_menu_url . '#menu',
                        'name'  => 'Menu – ' . $point['title'],
                        'url'   => $ld_menu_url,
                    );
                    $ld_sections_out = array();
                    foreach ($ld_menu_sections as $ld_sec) {
                        $ld_items_out = array();
                        foreach ($ld_sec['items'] as $ld_item) {
                            $ld_item_schema = array('@type' => 'MenuItem', 'name' => $ld_item['name']);
                            if (!empty($ld_item['description'])) {
                                $ld_item_schema['description'] = $ld_item['description'];
                            }
                            $ld_variants = array();
                            if (!empty($ld_item['variants'])) {
                                $ld_dec = json_decode($ld_item['variants'], true);
                                if (is_array($ld_dec)) $ld_variants = $ld_dec;
                            }
                            $ld_price = null;
                            if (!empty($ld_variants)) {
                                $ld_min = null;
                                foreach ($ld_variants as $lv) { $lvp = floatval($lv['price']); if ($ld_min === null || $lvp < $ld_min) $ld_min = $lvp; }
                                $ld_price = $ld_min;
                            } elseif ($ld_item['price'] !== null && $ld_item['price'] !== '') {
                                $ld_price = floatval($ld_item['price']);
                            }
                            if ($ld_price !== null) {
                                $ld_item_schema['offers'] = array('@type' => 'Offer', 'price' => number_format($ld_price, 2, '.', ''), 'priceCurrency' => 'PLN');
                            }
                            $ld_items_out[] = $ld_item_schema;
                        }
                        if (!empty($ld_items_out)) {
                            $ld_sections_out[] = array('@type' => 'MenuSection', 'name' => $ld_sec['name'], 'hasMenuItem' => $ld_items_out);
                        }
                    }
                    if (!empty($ld_sections_out)) {
                        $ld_menu_obj['hasMenuSection'] = $ld_sections_out;
                    }
                    ?>
                    ,"hasMenu": <?php echo json_encode($ld_menu_obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                    <?php endif; ?>
                    <?php if (!empty($point['opening_hours'])): ?>
                    <?php
                    $schema_oh_days = ['Mo'=>'https://schema.org/Monday','Tu'=>'https://schema.org/Tuesday','We'=>'https://schema.org/Wednesday','Th'=>'https://schema.org/Thursday','Fr'=>'https://schema.org/Friday','Sa'=>'https://schema.org/Saturday','Su'=>'https://schema.org/Sunday'];
                    $schema_oh_spec = [];
                    foreach (explode("\n", $point['opening_hours']) as $schema_oh_line) {
                        $schema_oh_line = trim($schema_oh_line);
                        if (preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)\s+(\d{2}:\d{2})-(\d{2}:\d{2})$/', $schema_oh_line, $schema_oh_m)) {
                            $schema_oh_spec[] = ['@type'=>'OpeningHoursSpecification','dayOfWeek'=>$schema_oh_days[$schema_oh_m[1]],'opens'=>$schema_oh_m[2],'closes'=>$schema_oh_m[3]];
                        }
                    }
                    ?>
                    <?php if (!empty($schema_oh_spec)): ?>
                    ,"openingHoursSpecification": <?php echo json_encode($schema_oh_spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php
                    $rating_supported_types = ['LocalBusiness','FoodEstablishment','Museum','SportsActivityLocation','Hotel','Restaurant','Store','HealthAndBeautyBusiness','EntertainmentBusiness','LodgingBusiness','AutoDealer','FinancialService','ProfessionalService'];
                    if ($total_votes > 0 && in_array($schema_type, $rating_supported_types, true)): ?>
                    ,"aggregateRating": {
                        "@type": "AggregateRating",
                        "ratingValue": <?php echo json_encode($avg_rating_schema); ?>,
                        "ratingCount": <?php echo json_encode($total_votes); ?>,
                        "bestRating": 5,
                        "worstRating": 1
                    }
                    <?php endif; ?>
                    <?php
                    $same_as = array();
                    if (!empty($point['facebook_url'])) $same_as[] = $point['facebook_url'];
                    if (!empty($point['instagram_url'])) $same_as[] = $point['instagram_url'];
                    if (!empty($point['linkedin_url'])) $same_as[] = $point['linkedin_url'];
                    if (!empty($point['tiktok_url'])) $same_as[] = $point['tiktok_url'];
                    if (!empty($point['website'])) $same_as[] = $point['website'];
                    if (!empty($same_as)): ?>
                    ,"sameAs": <?php echo json_encode($same_as); ?>
                    <?php endif; ?>
                    <?php if (!empty($point_tags)): ?>
                    ,"keywords": <?php echo json_encode(implode(', ', $point_tags)); ?>
                    <?php endif; ?>
                },
                {
                    "@type": "BreadcrumbList",
                    "@id": <?php echo json_encode($url . '#breadcrumb'); ?>,
                    "itemListElement": [
                        {
                            "@type": "ListItem",
                            "position": 1,
                            "name": "Strona główna",
                            "item": <?php echo json_encode(home_url('/')); ?>
                        },
                        {
                            "@type": "ListItem",
                            "position": 2,
                            "name": <?php echo json_encode($type_label); ?>,
                            "item": <?php echo json_encode(home_url('/mapa/')); ?>
                        },
                        {
                            "@type": "ListItem",
                            "position": 3,
                            "name": <?php echo json_encode($point['title']); ?>
                        }
                    ]
                },
                {
                    "@type": "WebPage",
                    "@id": <?php echo json_encode($url . '#webpage'); ?>,
                    "url": <?php echo json_encode($url); ?>,
                    "name": <?php echo json_encode($point['title'] . ' - Jelenia Góra'); ?>,
                    "isPartOf": {"@id": <?php echo json_encode(home_url('/#website')); ?>},
                    "breadcrumb": {"@id": <?php echo json_encode($url . '#breadcrumb'); ?>},
                    "inLanguage": "pl-PL"
                    <?php if ($date_created_schema): ?>
                    ,"datePublished": <?php echo json_encode($date_created_schema); ?>
                    <?php endif; ?>
                    <?php if ($date_modified_schema): ?>
                    ,"dateModified": <?php echo json_encode($date_modified_schema); ?>
                    <?php endif; ?>
                }
            ]
        }
        </script>
        <?php
    }

    /**
     * Add map sitemap to Yoast sitemap index
     * This helps Google discover the map sitemap through Yoast's main sitemap
     */
    public function add_map_sitemap_to_yoast_index($sitemap_index) {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Get the most recent update date from published points
        $last_modified = $wpdb->get_var(
            "SELECT MAX(updated_at) FROM $table WHERE status = 'publish' AND slug IS NOT NULL AND slug != ''"
        );

        if ($last_modified) {
            $lastmod = get_date_from_gmt($last_modified, 'c');
        } else {
            $lastmod = current_time('c');
        }

        $sitemap_url = home_url('/jg-map-sitemap.xml');
        $sitemap_index .= '<sitemap>' . "\n";
        $sitemap_index .= "\t" . '<loc>' . esc_url($sitemap_url) . '</loc>' . "\n";
        $sitemap_index .= "\t" . '<lastmod>' . esc_html($lastmod) . '</lastmod>' . "\n";
        $sitemap_index .= '</sitemap>' . "\n";

        return $sitemap_index;
    }

    /**
     * Add map sitemap reference to robots.txt
     * This provides an additional way for crawlers to discover the sitemap
     */
    public function add_map_sitemap_to_robots($output, $public) {
        if ($public) {
            $sitemap_url = home_url('/jg-map-sitemap.xml');
            $output .= "\n# JG Interactive Map Sitemap\n";
            $output .= "Sitemap: " . esc_url($sitemap_url) . "\n";
        }
        return $output;
    }

    /**
     * Handle sitemap.xml generation
     */
    /**
     * Escape string for use in XML content (removes invalid XML characters)
     */
    private function xml_escape($string) {
        // Remove XML-invalid control characters (0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F)
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $string);
        // Escape XML entities
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the cached sitemap file path
     */
    private function get_sitemap_cache_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/jg-map-sitemap-cache.xml';
    }

    /**
     * Regenerate and cache the sitemap XML file
     * Called when points are created, updated, or deleted
     */
    public function regenerate_sitemap_cache() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $points = $wpdb->get_results(
            "SELECT id, title, slug, type, images, featured_image_index, updated_at
             FROM $table
             WHERE status = 'publish' AND slug IS NOT NULL AND slug != ''
             ORDER BY updated_at DESC",
            ARRAY_A
        );

        if ($wpdb->last_error || !is_array($points)) {
            return false;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($points as $point) {
            $type_path = 'miejsce';
            if ($point['type'] === 'ciekawostka') {
                $type_path = 'ciekawostka';
            } elseif ($point['type'] === 'zgloszenie') {
                $type_path = 'zgloszenie';
            }

            $url = home_url('/' . $type_path . '/' . $point['slug'] . '/');
            $lastmod = get_date_from_gmt($point['updated_at'], 'c');

            $point_images = json_decode($point['images'], true) ?: array();
            $sitemap_images = array();
            foreach ($point_images as $img) {
                $img_url = '';
                if (is_array($img)) {
                    $img_url = isset($img['full']) ? $img['full'] : (isset($img['thumb']) ? $img['thumb'] : '');
                } else {
                    $img_url = $img;
                }
                if ($img_url && strpos($img_url, 'http') !== 0) {
                    $img_url = home_url($img_url);
                }
                if ($img_url) {
                    $sitemap_images[] = $img_url;
                }
            }

            $xml .= '    <url>' . "\n";
            $xml .= '        <loc>' . esc_url($url) . '</loc>' . "\n";
            $xml .= '        <lastmod>' . $lastmod . '</lastmod>' . "\n";
            $xml .= '        <changefreq>weekly</changefreq>' . "\n";
            $xml .= '        <priority>' . ($point['type'] === 'miejsce' ? '0.8' : '0.6') . '</priority>' . "\n";

            foreach ($sitemap_images as $sitemap_img) {
                $xml .= '        <image:image>' . "\n";
                $xml .= '            <image:loc>' . esc_url($sitemap_img) . '</image:loc>' . "\n";
                $xml .= '            <image:title>' . $this->xml_escape($point['title']) . '</image:title>' . "\n";
                $xml .= '        </image:image>' . "\n";
            }

            $xml .= '    </url>' . "\n";
        }

        // Add menu subpages for gastronomic places that have menu data
        $sitemap_menu_cats = JG_Map_Ajax_Handlers::get_menu_categories();
        $sitemap_menu_cats_sql = implode(',', array_map(function($c) use ($wpdb) { return $wpdb->prepare('%s', $c); }, $sitemap_menu_cats));
        $gastronomic_points = empty($sitemap_menu_cats) ? array() : $wpdb->get_results(
            "SELECT id, slug, updated_at FROM $table WHERE type = 'miejsce' AND category IN ($sitemap_menu_cats_sql) AND status = 'publish' AND slug IS NOT NULL ORDER BY id ASC",
            ARRAY_A
        );
        foreach ($gastronomic_points as $gp) {
            if (JG_Map_Database::point_has_menu($gp['id'])) {
                $menu_url     = home_url('/miejsce/' . $gp['slug'] . '/menu/');
                $menu_lastmod = get_date_from_gmt($gp['updated_at'], 'c');
                $xml .= '    <url>' . "\n";
                $xml .= '        <loc>' . esc_url($menu_url) . '</loc>' . "\n";
                $xml .= '        <lastmod>' . $menu_lastmod . '</lastmod>' . "\n";
                $xml .= '        <changefreq>weekly</changefreq>' . "\n";
                $xml .= '        <priority>0.7</priority>' . "\n";
                $xml .= '    </url>' . "\n";
            }
        }

        // Add category pages to sitemap (higher priority - SEO landing pages)
        $all_place_cats = JG_Map_Database::get_all_place_categories();
        foreach ($all_place_cats as $cat) {
            $cat_url = self::get_category_url($cat);
            $xml .= '    <url>' . "\n";
            $xml .= '        <loc>' . esc_url($cat_url) . '</loc>' . "\n";
            $xml .= '        <changefreq>weekly</changefreq>' . "\n";
            $xml .= '        <priority>0.7</priority>' . "\n";
            $xml .= '    </url>' . "\n";
        }

        // Add tag filter pages to sitemap with clean URLs
        $all_tags = JG_Map_Database::get_all_tags();
        foreach ($all_tags as $tag) {
            $tag_url = self::get_tag_url($tag);
            $xml .= '    <url>' . "\n";
            $xml .= '        <loc>' . esc_url($tag_url) . '</loc>' . "\n";
            $xml .= '        <changefreq>weekly</changefreq>' . "\n";
            $xml .= '        <priority>0.5</priority>' . "\n";
            $xml .= '    </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";

        $cache_path = $this->get_sitemap_cache_path();
        $written = file_put_contents($cache_path, $xml, LOCK_EX);

        return $written !== false;
    }

    public function handle_sitemap() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'];

        if (strpos($request_uri, 'jg-map-sitemap.xml') === false) {
            return;
        }

        // Clean all output buffers to prevent any content before headers
        while (ob_get_level()) {
            ob_end_clean();
        }

        $cache_path = $this->get_sitemap_cache_path();

        // Serve cached file if it exists and is less than 1 hour old
        if (file_exists($cache_path) && (time() - filemtime($cache_path)) < 3600) {
            $xml_content = file_get_contents($cache_path);
        } else {
            // Regenerate cache
            $this->regenerate_sitemap_cache();

            if (file_exists($cache_path)) {
                $xml_content = file_get_contents($cache_path);
            } else {
                // Fallback: generate directly if cache write failed
                $xml_content = $this->generate_sitemap_xml_string();
            }
        }

        if (empty($xml_content)) {
            status_header(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Sitemap generation error.';
            exit;
        }

        $last_modified = file_exists($cache_path) ? filemtime($cache_path) : time();
        $last_modified_str = gmdate('D, d M Y H:i:s', $last_modified) . ' GMT';

        // Support conditional GET - return 304 if client already has current version
        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
            ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])
            : false;
        if ($if_modified_since !== false && $if_modified_since >= $last_modified) {
            status_header(304);
            exit;
        }

        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        header('Last-Modified: ' . $last_modified_str);
        header('Cache-Control: public, max-age=3600, s-maxage=3600');
        header('Pragma: public');
        header('Content-Length: ' . strlen($xml_content));

        echo $xml_content;
        exit;
    }

    /**
     * Fallback: generate sitemap XML string directly without caching
     */
    private function generate_sitemap_xml_string() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $points = $wpdb->get_results(
            "SELECT id, title, slug, type, images, featured_image_index, updated_at
             FROM $table
             WHERE status = 'publish' AND slug IS NOT NULL AND slug != ''
             ORDER BY updated_at DESC",
            ARRAY_A
        );

        if ($wpdb->last_error || !is_array($points)) {
            return '';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($points as $point) {
            $type_path = 'miejsce';
            if ($point['type'] === 'ciekawostka') {
                $type_path = 'ciekawostka';
            } elseif ($point['type'] === 'zgloszenie') {
                $type_path = 'zgloszenie';
            }

            $url = home_url('/' . $type_path . '/' . $point['slug'] . '/');
            $lastmod = get_date_from_gmt($point['updated_at'], 'c');

            $point_images = json_decode($point['images'], true) ?: array();
            $sitemap_images = array();
            foreach ($point_images as $img) {
                $img_url = '';
                if (is_array($img)) {
                    $img_url = isset($img['full']) ? $img['full'] : (isset($img['thumb']) ? $img['thumb'] : '');
                } else {
                    $img_url = $img;
                }
                if ($img_url && strpos($img_url, 'http') !== 0) {
                    $img_url = home_url($img_url);
                }
                if ($img_url) {
                    $sitemap_images[] = $img_url;
                }
            }

            $xml .= '    <url>' . "\n";
            $xml .= '        <loc>' . esc_url($url) . '</loc>' . "\n";
            $xml .= '        <lastmod>' . $lastmod . '</lastmod>' . "\n";
            $xml .= '        <changefreq>weekly</changefreq>' . "\n";
            $xml .= '        <priority>' . ($point['type'] === 'miejsce' ? '0.8' : '0.6') . '</priority>' . "\n";

            foreach ($sitemap_images as $sitemap_img) {
                $xml .= '        <image:image>' . "\n";
                $xml .= '            <image:loc>' . esc_url($sitemap_img) . '</image:loc>' . "\n";
                $xml .= '            <image:title>' . $this->xml_escape($point['title']) . '</image:title>' . "\n";
                $xml .= '        </image:image>' . "\n";
            }

            $xml .= '    </url>' . "\n";
        }

        // Add menu subpages for gastronomic places
        $sitemap_menu_cats2 = JG_Map_Ajax_Handlers::get_menu_categories();
        $sitemap_menu_cats_sql2 = implode(',', array_map(function($c) use ($wpdb) { return $wpdb->prepare('%s', $c); }, $sitemap_menu_cats2));
        $gastronomic_points2 = empty($sitemap_menu_cats2) ? array() : $wpdb->get_results(
            "SELECT id, slug, updated_at FROM $table WHERE type = 'miejsce' AND category IN ($sitemap_menu_cats_sql2) AND status = 'publish' AND slug IS NOT NULL ORDER BY id ASC",
            ARRAY_A
        );
        foreach ($gastronomic_points2 as $gp) {
            if (JG_Map_Database::point_has_menu($gp['id'])) {
                $menu_url     = home_url('/miejsce/' . $gp['slug'] . '/menu/');
                $menu_lastmod = get_date_from_gmt($gp['updated_at'], 'c');
                $xml .= '    <url>' . "\n";
                $xml .= '        <loc>' . esc_url($menu_url) . '</loc>' . "\n";
                $xml .= '        <lastmod>' . $menu_lastmod . '</lastmod>' . "\n";
                $xml .= '        <changefreq>weekly</changefreq>' . "\n";
                $xml .= '        <priority>0.7</priority>' . "\n";
                $xml .= '    </url>' . "\n";
            }
        }

        // Add category pages to sitemap (higher priority - SEO landing pages)
        $all_place_cats2 = JG_Map_Database::get_all_place_categories();
        foreach ($all_place_cats2 as $cat) {
            $cat_url = self::get_category_url($cat);
            $xml .= '    <url>' . "\n";
            $xml .= '        <loc>' . esc_url($cat_url) . '</loc>' . "\n";
            $xml .= '        <changefreq>weekly</changefreq>' . "\n";
            $xml .= '        <priority>0.7</priority>' . "\n";
            $xml .= '    </url>' . "\n";
        }

        // Add tag filter pages to sitemap with clean URLs
        $all_tags = JG_Map_Database::get_all_tags();
        foreach ($all_tags as $tag) {
            $tag_url = self::get_tag_url($tag);
            $xml .= '    <url>' . "\n";
            $xml .= '        <loc>' . esc_url($tag_url) . '</loc>' . "\n";
            $xml .= '        <changefreq>weekly</changefreq>' . "\n";
            $xml .= '        <priority>0.5</priority>' . "\n";
            $xml .= '    </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    /**
     * Filter document title parts for category pages (WordPress native title)
     */
    public function filter_category_page_title($title_parts) {
        $category = self::resolve_catalog_category();
        if ($category !== '') {
            $title_parts['title'] = $this->get_category_seo_title($category);
        }
        return $title_parts;
    }

    /**
     * Filter Yoast SEO title for category pages
     */
    public function filter_category_page_yoast_title($title) {
        $category = self::resolve_catalog_category();
        if ($category !== '') {
            return $this->get_category_seo_title($category) . ' | ' . get_bloginfo('name');
        }
        return $title;
    }

    /**
     * Suppress Yoast SEO / RankMath meta description on catalog category pages.
     */
    public function suppress_seo_plugin_description_on_category_pages() {
        if (get_query_var('jg_catalog_category', '') === '') {
            return;
        }
        $this->remove_yoast_head_from_wp_head();
    }

    /**
     * Remove Yoast SEO head output from wp_head, regardless of hook priority or
     * object instance. Scans $wp_filter directly so it works across all Yoast
     * versions. Also restores _wp_render_title_tag (which Yoast removes) and adds
     * a wpseo_metadesc fallback filter for older Yoast builds.
     */
    private function remove_yoast_head_from_wp_head() {
        global $wp_filter;

        // Scan all wp_head callbacks and remove anything belonging to Yoast's
        // front-end integration, regardless of priority or object instance.
        if (isset($wp_filter['wp_head'])) {
            foreach ($wp_filter['wp_head']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (!is_array($callback['function']) || !is_object($callback['function'][0])) {
                        continue;
                    }
                    $obj = $callback['function'][0];
                    $method = $callback['function'][1];
                    if (
                        ($obj instanceof \WPSEO_Frontend && $method === 'head') ||
                        (class_exists('Yoast\\WP\\SEO\\Integrations\\Front_End_Integration') &&
                         $obj instanceof \Yoast\WP\SEO\Integrations\Front_End_Integration &&
                         $method === 'call_wpseo_head')
                    ) {
                        remove_action('wp_head', $callback['function'], $priority);
                    }
                }
            }
        }

        // Yoast removes _wp_render_title_tag from wp_head and replaces it with its
        // own output. Since we just removed Yoast, restore the native title tag so
        // the page still gets a <title> element (content is set via document_title_parts).
        if (!has_action('wp_head', '_wp_render_title_tag')) {
            add_action('wp_head', '_wp_render_title_tag', 1);
        }

        // Fallback filter for older Yoast versions that use wpseo_metadesc
        add_filter('wpseo_metadesc', '__return_empty_string', PHP_INT_MAX);
        // RankMath equivalent
        add_filter('rank_math/frontend/description', '__return_empty_string', PHP_INT_MAX);

        // Last-resort: buffer the entire wp_head output and strip any duplicate
        // <meta name="description"> tags, keeping only the first one (from this
        // plugin). This catches descriptions output by Elementor Pro, theme
        // functions, or any other source that bypasses the filters above.
        add_action('wp_head', static function() { ob_start(); }, 1);
        add_action('wp_head', array($this, 'dedupe_meta_description_in_head'), PHP_INT_MAX);
    }

    /**
     * Remove all but the first <meta name="description"> from buffered wp_head output.
     */
    public function dedupe_meta_description_in_head() {
        $html  = ob_get_clean();
        $found = false;
        $html  = preg_replace_callback(
            '/<meta\s[^>]*name=["\']description["\'][^>]*\/?>/i',
            function ( $match ) use ( &$found ) {
                if ( $found ) {
                    return '';
                }
                $found = true;
                return $match[0];
            },
            $html
        );
        echo $html;
    }

    /**
     * Public wrapper for shortcode access.
     */
    public function get_category_seo_title_public($category) {
        return $this->get_category_seo_title($category);
    }

    /**
     * Public wrapper for shortcode access.
     */
    public function get_category_intro_public($category, $count) {
        return $this->get_category_intro($category, $count);
    }

    /**
     * Return a short SEO intro paragraph for a category page.
     * Shown below the H1. Targets the main keyword for each category.
     */
    private function get_category_intro($category, $count) {
        $map = array(
            'Gastronomia' =>
                'Szukasz dobrego miejsca na obiad, kolację lub szybki lunch w Jeleniej Górze? Zebraliśmy ' . $count . ' lokali gastronomicznych – restauracje, bary, burgerownie i kuchnie świata. Każde miejsce znajdziesz na interaktywnej mapie z adresem i godzinami otwarcia.',
            'Atrakcja turystyczna' =>
                'Jelenia Góra i okolice oferują wyjątkowe atrakcje turystyczne – od ruin zamku Chojnik po Park Zdrojowy w Cieplicach. Odkryj ' . $count . ' miejsc wartych odwiedzenia: zabytki, punkty widokowe, muzea i obiekty rekreacyjne w Kotlinie Jeleniogórskiej.',
            'Hotele i schroniska' =>
                'Planujesz nocleg w Jeleniej Górze lub Sudetach? Na mapie znajdziesz ' . $count . ' obiektów noclegowych – hotele, pensjonaty i schroniska z adresami i lokalizacją.',
            'Historia i zabytki' =>
                'Jelenia Góra to miasto z bogatą historią sięgającą średniowiecza. Odkryj ' . $count . ' historycznych miejsc i zabytków – zamki, kościoły, kamienice i ślady dawnej świetności Kotliny Jeleniogórskiej.',
            'Sport i rekreacja' =>
                'Aktywny wypoczynek w Jeleniej Górze? Mamy dla Ciebie ' . $count . ' obiektów sportowych i rekreacyjnych – siłownie, baseny, parki trampolin, tory rowerowe i wiele więcej.',
            'Kawiarnia' =>
                'Najlepsze kawiarnie w Jeleniej Górze zebrane w jednym miejscu. ' . $count . ' klimatycznych miejsc na kawę, herbatę i domowe ciasto – w centrum miasta i dzielnicach.',
            'Zakupy' =>
                'Sklepy, galerie handlowe i rynki w Jeleniej Górze. ' . $count . ' miejsc zakupów – od galerii Sudeckiej po lokalne sklepy i targowiska.',
            'Zdrowie' =>
                'Przychodnie, gabinety specjalistyczne i apteki w Jeleniej Górze. ' . $count . ' placówek zdrowotnych z adresami i lokalizacją na mapie.',
            'Kultura' =>
                'Teatry, kina, galerie i miejsca kulturalne w Jeleniej Górze. Odkryj ' . $count . ' miejsc kultury i rozrywki w mieście i okolicach.',
            'Beauty i uroda' =>
                'Salony fryzjerskie, kosmetyczne, barberzy i SPA w Jeleniej Górze. ' . $count . ' miejsc, w których zadbasz o siebie.',
            'Cukiernia' =>
                'Cukiernie, lodziarnie i pączkarnie w Jeleniej Górze. ' . $count . ' słodkich miejsc na deserowy przystanek.',
            'Masaż i SPA' =>
                'Salony masażu, spa i miejsca relaksu w Jeleniej Górze i Cieplicach. ' . $count . ' obiektów dla tych, którzy szukają chwili wytchnienia.',
            'Fryzjer' =>
                'Fryzjerzy i barberzy w Jeleniej Górze. ' . $count . ' salonów fryzjerskich z adresami na mapie.',
            'Apteka' =>
                'Apteki w Jeleniej Górze. ' . $count . ' aptek – znajdź najbliższą na interaktywnej mapie.',
            'Edukacja' =>
                'Szkoły, przedszkola i placówki edukacyjne w Jeleniej Górze. ' . $count . ' miejsc nauki i wychowania z adresami.',
            'Transport publiczny' =>
                'Dworce, przystanki i punkty transportu publicznego w Jeleniej Górze. ' . $count . ' miejsc na mapie komunikacji miejskiej.',
            'Miejsce kultu' =>
                'Kościoły, kaplice i inne miejsca kultu w Jeleniej Górze. ' . $count . ' obiektów sakralnych na mapie.',
            'Parking bezpłatny' =>
                'Darmowe parkingi w Jeleniej Górze. ' . $count . ' bezpłatnych miejsc parkingowych zaznaczonych na mapie.',
            'Parking płatny' =>
                'Parkingi płatne w centrum Jeleniej Góry i okolicach. ' . $count . ' obiektów z lokalizacją na mapie.',
            'Zieleń' =>
                'Parki, skwery i tereny zielone w Jeleniej Górze. ' . $count . ' miejsc do spacerów i odpoczynku na świeżym powietrzu.',
            'Usługi' =>
                'Usługi dla mieszkańców Jeleniej Góry. ' . $count . ' firm i punktów usługowych z adresami na mapie.',
        );

        return isset($map[$category]) ? $map[$category] : '';
    }

    /**
     * Return SEO-optimised H1/title string for a given category name.
     */
    private function get_category_seo_title($category) {
        $map = array(
            'Gastronomia'                       => 'Restauracje i gastronomia w Jeleniej Górze',
            'Atrakcja turystyczna'               => 'Atrakcje turystyczne Jelenia Góra',
            'Hotele i schroniska'                => 'Hotele i noclegi w Jeleniej Górze',
            'Historia i zabytki'                 => 'Zabytki i historia Jelenia Góra',
            'Sport i rekreacja'                  => 'Sport i rekreacja w Jeleniej Górze',
            'Kawiarnia'                          => 'Kawiarnie w Jeleniej Górze',
            'Zakupy'                             => 'Sklepy i zakupy w Jeleniej Górze',
            'Zdrowie'                            => 'Zdrowie i medycyna w Jeleniej Górze',
            'Kultura'                            => 'Kultura i sztuka w Jeleniej Górze',
            'Beauty i uroda'                     => 'Salony urody i beauty w Jeleniej Górze',
            'Cukiernia'                          => 'Cukiernie w Jeleniej Górze',
            'Fryzjer'                            => 'Fryzjerzy i barberzy w Jeleniej Górze',
            'Masaż i SPA'                        => 'Masaż i SPA w Jeleniej Górze',
            'Edukacja'                           => 'Edukacja i szkoły w Jeleniej Górze',
            'Apteka'                             => 'Apteki w Jeleniej Górze',
            'Transport publiczny'                => 'Transport publiczny w Jeleniej Górze',
            'Miejsce kultu'                      => 'Kościoły i miejsca kultu w Jeleniej Górze',
            'Parking bezpłatny'                  => 'Darmowe parkingi w Jeleniej Górze',
            'Parking płatny'                     => 'Parkingi płatne w Jeleniej Górze',
            'Zieleń'                             => 'Parki i tereny zielone w Jeleniej Górze',
            'Usługi'                             => 'Usługi w Jeleniej Górze',
            'Media'                              => 'Media i prasa w Jeleniej Górze',
            'Branża IT'                          => 'Firmy IT w Jeleniej Górze',
            'Produkcja'                          => 'Firmy produkcyjne w Jeleniej Górze',
            'Służby publiczne oraz administracja' => 'Urzędy i administracja w Jeleniej Górze',
            'Infrastruktura energetyczno-komunalna' => 'Infrastruktura komunalna Jelenia Góra',
        );
        return isset($map[$category]) ? $map[$category] : ($category . ' – Miejsca w Jeleniej Górze');
    }

    /**
     * Return SEO meta description for a given category name and place count.
     */
    private function get_category_seo_description($category, $count) {
        $map = array(
            'Gastronomia'          => 'Odkryj restauracje, bary i lokale gastronomiczne w Jeleniej Górze. ' . $count . ' miejsc z adresami, godzinami otwarcia i ocenami.',
            'Atrakcja turystyczna'  => 'Najlepsze atrakcje turystyczne w Jeleniej Górze i Sudetach. ' . $count . ' miejsc wartych odwiedzenia – zabytki, punkty widokowe, muzea.',
            'Hotele i schroniska'   => 'Hotele, pensjonaty i schroniska w Jeleniej Górze. ' . $count . ' miejsc noclegowych w sercu Sudetów.',
            'Historia i zabytki'    => 'Historyczne miejsca i zabytki w Jeleniej Górze. Poznaj ' . $count . ' miejsc z bogatą historią Kotliny Jeleniogórskiej.',
            'Sport i rekreacja'     => 'Obiekty sportowe i miejsca rekreacji w Jeleniej Górze. ' . $count . ' miejsc – siłownie, baseny, boiska i aktywności na świeżym powietrzu.',
            'Kawiarnia'             => 'Kawiarnie i herbaciarnie w Jeleniej Górze. ' . $count . ' miejsc na dobrą kawę i spotkanie ze znajomymi.',
            'Zakupy'                => 'Sklepy, galerie handlowe i targowiska w Jeleniej Górze. ' . $count . ' miejsc zakupów w mieście i okolicach.',
            'Zdrowie'               => 'Przychodnie, gabinety lekarskie i placówki zdrowia w Jeleniej Górze. ' . $count . ' miejsc.',
            'Kultura'               => 'Teatry, galerie, kina i miejsca kulturalne w Jeleniej Górze. ' . $count . ' miejsc kultury i rozrywki.',
            'Beauty i uroda'        => 'Salony fryzjerskie, kosmetyczne i SPA w Jeleniej Górze. ' . $count . ' miejsc beauty i urody.',
            'Cukiernia'             => 'Cukiernie i lodziarnie w Jeleniej Górze. ' . $count . ' miejsc na słodką chwilę.',
            'Masaż i SPA'           => 'Salony masażu i SPA w Jeleniej Górze. ' . $count . ' miejsc relaksu i odnowy.',
        );
        if (isset($map[$category])) {
            return $map[$category];
        }
        return 'Przeglądaj ' . $count . ' miejsc w kategorii ' . $category . ' na interaktywnej mapie Jeleniej Góry. Odkryj lokalne miejsca z adresami i zdjęciami.';
    }

    /**
     * Get the OG image URL for a catalog page (tag or category).
     *
     * For sponsored points (is_promo = 1) uses the image at featured_image_index.
     * For regular points uses the first image (index 0).
     * Returns empty string if no image is found.
     *
     * @param string $where      SQL WHERE clause (without leading AND)
     * @param array  $where_args Values for $wpdb->prepare placeholders in $where
     * @return string Full image URL or ''
     */
    private function get_og_image_for_points( $where, $where_args ) {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT images, is_promo, featured_image_index
                 FROM $table
                 WHERE $where
                 ORDER BY type ASC, title ASC
                 LIMIT 1",
                ...$where_args
            ),
            ARRAY_A
        );

        if ( ! $row || empty( $row['images'] ) ) {
            return '';
        }

        $images         = json_decode( $row['images'], true );
        if ( ! is_array( $images ) || empty( $images ) ) {
            return '';
        }

        $featured_index = (int) ( $row['featured_image_index'] ?? 0 );
        $is_promo       = (bool) $row['is_promo'];

        // Sponsored pins: use featured_image_index; regular pins: use index 0.
        $img_index = ( $is_promo && isset( $images[ $featured_index ] ) )
            ? $featured_index
            : 0;

        if ( ! isset( $images[ $img_index ] ) ) {
            return '';
        }

        $img = $images[ $img_index ];
        $url = is_array( $img )
            ? ( $img['full'] ?? $img['thumb'] ?? '' )
            : (string) $img;

        if ( $url && strpos( $url, 'http' ) !== 0 ) {
            $url = home_url( $url );
        }

        return $url;
    }

    /**
     * Add SEO meta tags for catalog category pages
     */
    public function add_category_page_meta_tags() {
        $category = self::resolve_catalog_category();
        if ($category === '') {
            return;
        }

        $category_url = self::get_category_url($category);

        global $wpdb;
        $table = JG_Map_Database::get_points_table();
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'publish' AND slug IS NOT NULL AND slug != '' AND category = %s",
            $category
        ));

        $h1_title    = $this->get_category_seo_title($category);
        $description = $this->get_category_seo_description($category, $count);
        $site_name   = get_bloginfo('name');
        $og_image    = $this->get_og_image_for_points(
            "status = 'publish' AND slug IS NOT NULL AND slug != '' AND category = %s",
            array( $category )
        );

        $robots = 'index, follow';
        if (get_option('blog_public') == '0') {
            $robots = 'noindex, nofollow';
        }
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');
        if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
            $robots = 'noindex, nofollow';
        }
        ?>
        <meta name="robots" content="<?php echo esc_attr($robots); ?>">
        <link rel="canonical" href="<?php echo esc_url($category_url); ?>">
        <meta name="description" content="<?php echo esc_attr($description); ?>">

        <!-- Open Graph -->
        <meta property="og:title" content="<?php echo esc_attr($h1_title); ?>">
        <meta property="og:description" content="<?php echo esc_attr($description); ?>">
        <meta property="og:url" content="<?php echo esc_url($category_url); ?>">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="pl_PL">
        <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>">
        <?php if ($og_image): ?>
        <meta property="og:image" content="<?php echo esc_url($og_image); ?>">
        <?php endif; ?>

        <!-- Twitter Card -->
        <meta name="twitter:card" content="<?php echo $og_image ? 'summary_large_image' : 'summary'; ?>">
        <meta name="twitter:title" content="<?php echo esc_attr($h1_title); ?>">
        <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
        <?php if ($og_image): ?>
        <meta name="twitter:image" content="<?php echo esc_url($og_image); ?>">
        <?php endif; ?>

        <!-- JSON-LD: CollectionPage -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@graph": [
                {
                    "@type": "CollectionPage",
                    "@id": <?php echo json_encode($category_url . '#webpage'); ?>,
                    "url": <?php echo json_encode($category_url); ?>,
                    "name": <?php echo json_encode($h1_title); ?>,
                    "description": <?php echo json_encode($description); ?>,
                    "isPartOf": {"@id": <?php echo json_encode(home_url('/#website')); ?>},
                    "inLanguage": "pl-PL",
                    "breadcrumb": {"@id": <?php echo json_encode($category_url . '#breadcrumb'); ?>},
                    "numberOfItems": <?php echo $count; ?>
                },
                {
                    "@type": "BreadcrumbList",
                    "@id": <?php echo json_encode($category_url . '#breadcrumb'); ?>,
                    "itemListElement": [
                        {
                            "@type": "ListItem",
                            "position": 1,
                            "name": "Strona główna",
                            "item": <?php echo json_encode(home_url('/')); ?>
                        },
                        {
                            "@type": "ListItem",
                            "position": 2,
                            "name": "Katalog",
                            "item": <?php echo json_encode(home_url('/katalog/')); ?>
                        },
                        {
                            "@type": "ListItem",
                            "position": 3,
                            "name": <?php echo json_encode($category); ?>
                        }
                    ]
                }
            ]
        }
        </script>
        <?php
    }

    /**
     * Filter document title parts for tag pages (WordPress native title)
     */
    public function filter_tag_page_title($title_parts) {
        $tag = self::resolve_catalog_tag();
        if ($tag !== '') {
            $title_parts['title'] = '#' . $tag . ' - Miejsca w Jeleniej Górze';
        }
        return $title_parts;
    }

    /**
     * Filter Yoast SEO title for tag pages
     */
    public function filter_tag_page_yoast_title($title) {
        $tag = self::resolve_catalog_tag();
        if ($tag !== '') {
            return '#' . $tag . ' - Miejsca w Jeleniej Górze | ' . get_bloginfo('name');
        }
        return $title;
    }

    /**
     * Generate a clean tag URL: /katalog/tag/{slug}/
     */
    public static function get_tag_url($tag) {
        $slug = sanitize_title($tag);
        return home_url('/katalog/tag/' . $slug . '/');
    }

    /**
     * Generate a clean category URL: /katalog/kategoria/{slug}/
     */
    public static function get_category_url($category) {
        $slug = sanitize_title($category);
        return home_url('/katalog/kategoria/' . $slug . '/');
    }

    /**
     * Resolve the active category from the clean URL query var.
     * The URL slug is matched against known categories to restore the original label.
     */
    public static function resolve_catalog_category() {
        $slug = get_query_var('jg_catalog_category', '');
        if ($slug === '') {
            return '';
        }

        $slug = sanitize_title($slug);
        $all_categories = JG_Map_Database::get_all_place_categories();

        foreach ($all_categories as $cat) {
            if (sanitize_title($cat) === $slug) {
                return $cat;
            }
        }

        return '';
    }

    /**
     * Resolve the active tag from the clean URL query var.
     * The URL slug is matched against known tags (case-insensitive) to restore
     * the original tag label (preserving casing / diacritics).
     */
    public static function resolve_catalog_tag() {
        $slug = get_query_var('jg_catalog_tag', '');
        if ($slug === '') {
            return '';
        }

        $slug = sanitize_title($slug);
        $all_tags = JG_Map_Database::get_all_tags();

        foreach ($all_tags as $tag) {
            if (sanitize_title($tag) === $slug) {
                return $tag;
            }
        }

        // No matching tag found
        return '';
    }

    /**
     * 301 redirect legacy ?tag= query parameter URLs to clean /katalog/tag/{slug}/ URLs
     */
    public function redirect_legacy_tag_urls() {
        if (!isset($_GET['tag']) || empty($_GET['tag'])) {
            return;
        }

        // Only redirect on the catalog page
        if (!is_page()) {
            return;
        }

        global $post;
        if (!$post || strpos($post->post_content, '[jg_map_directory') === false) {
            return;
        }

        $tag = sanitize_text_field(wp_unslash($_GET['tag']));
        $clean_url = self::get_tag_url($tag);

        wp_redirect($clean_url, 301);
        exit;
    }

    /**
     * Suppress Yoast SEO / RankMath meta description on catalog tag pages.
     * Called on 'wp' action (after query vars are parsed, before wp_head fires).
     * The plugin outputs its own <meta name="description"> via add_tag_page_meta_tags(),
     * so the SEO plugin must not output a second one.
     */
    public function suppress_seo_plugin_description_on_tag_pages() {
        if (get_query_var('jg_catalog_tag', '') === '') {
            return;
        }
        $this->remove_yoast_head_from_wp_head();
    }

    /**
     * Add SEO meta tags for catalog tag pages
     */
    public function add_tag_page_meta_tags() {
        $tag = self::resolve_catalog_tag();
        if ($tag === '') {
            return;
        }

        $tag_url = self::get_tag_url($tag);

        // Count points for this tag
        global $wpdb;
        $table = JG_Map_Database::get_points_table();
        $like_pattern = '%' . $wpdb->esc_like('"' . $tag . '"') . '%';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'publish' AND slug IS NOT NULL AND slug != '' AND tags LIKE %s",
            $like_pattern
        ));

        $title = '#' . $tag . ' - Miejsca w Jeleniej Górze | Jeleniogórzanie to my';
        $description = 'Przeglądaj ' . $count . ' miejsc oznaczonych tagiem #' . $tag . ' na interaktywnej mapie Jeleniej Góry. Odkryj lokalne miejsca, ciekawostki i atrakcje.';
        $site_name = get_bloginfo('name');
        $og_image  = $this->get_og_image_for_points(
            "status = 'publish' AND slug IS NOT NULL AND slug != '' AND tags LIKE %s",
            array( $like_pattern )
        );

        // Robots
        $robots = 'index, follow';
        if (get_option('blog_public') == '0') {
            $robots = 'noindex, nofollow';
        }
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');
        if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
            $robots = 'noindex, nofollow';
        }

        ?>
        <meta name="robots" content="<?php echo esc_attr($robots); ?>">
        <link rel="canonical" href="<?php echo esc_url($tag_url); ?>">
        <meta name="description" content="<?php echo esc_attr($description); ?>">

        <!-- Open Graph -->
        <meta property="og:title" content="<?php echo esc_attr('#' . $tag . ' - Miejsca w Jeleniej Górze'); ?>">
        <meta property="og:description" content="<?php echo esc_attr($description); ?>">
        <meta property="og:url" content="<?php echo esc_url($tag_url); ?>">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="pl_PL">
        <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>">
        <?php if ($og_image): ?>
        <meta property="og:image" content="<?php echo esc_url($og_image); ?>">
        <?php endif; ?>

        <!-- Twitter Card -->
        <meta name="twitter:card" content="<?php echo $og_image ? 'summary_large_image' : 'summary'; ?>">
        <meta name="twitter:title" content="<?php echo esc_attr('#' . $tag . ' - Miejsca w Jeleniej Górze'); ?>">
        <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
        <?php if ($og_image): ?>
        <meta name="twitter:image" content="<?php echo esc_url($og_image); ?>">
        <?php endif; ?>

        <!-- JSON-LD: CollectionPage -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@graph": [
                {
                    "@type": "CollectionPage",
                    "@id": <?php echo json_encode($tag_url . '#webpage'); ?>,
                    "url": <?php echo json_encode($tag_url); ?>,
                    "name": <?php echo json_encode('#' . $tag . ' - Miejsca w Jeleniej Górze'); ?>,
                    "description": <?php echo json_encode($description); ?>,
                    "isPartOf": {"@id": <?php echo json_encode(home_url('/#website')); ?>},
                    "inLanguage": "pl-PL",
                    "breadcrumb": {"@id": <?php echo json_encode($tag_url . '#breadcrumb'); ?>},
                    "numberOfItems": <?php echo $count; ?>
                },
                {
                    "@type": "BreadcrumbList",
                    "@id": <?php echo json_encode($tag_url . '#breadcrumb'); ?>,
                    "itemListElement": [
                        {
                            "@type": "ListItem",
                            "position": 1,
                            "name": "Strona główna",
                            "item": <?php echo json_encode(home_url('/')); ?>
                        },
                        {
                            "@type": "ListItem",
                            "position": 2,
                            "name": "Katalog",
                            "item": <?php echo json_encode(home_url('/katalog/')); ?>
                        },
                        {
                            "@type": "ListItem",
                            "position": 3,
                            "name": <?php echo json_encode('#' . $tag); ?>
                        }
                    ]
                }
            ]
        }
        </script>
        <?php
    }

}

/**
 * Initialize the plugin
 */
function jg_interactive_map() {
    return JG_Interactive_Map::get_instance();
}

// Start the plugin
jg_interactive_map();
