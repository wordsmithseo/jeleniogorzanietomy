<?php
/**
 * Plugin Name: JG Interactive Map
 * Plugin URI: https://jeleniogorzanietomy.pl
 * Description: Interaktywna mapa Jeleniej Góry z możliwością dodawania zgłoszeń, ciekawostek i miejsc
 * Version: 3.19.0
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
define('JG_MAP_VERSION', '3.19.0');
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
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-banner-manager.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-banner-admin.php';
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
        add_action('wp_head', array($this, 'add_point_meta_tags'));
        add_action('wp_head', array($this, 'add_tag_page_meta_tags'));

        // Override document title for tag pages
        add_filter('document_title_parts', array($this, 'filter_tag_page_title'));
        add_filter('wpseo_title', array($this, 'filter_tag_page_yoast_title'));

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
        JG_Map_Levels_Achievements::get_instance();
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
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://www.google-analytics.com",
            "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self' https://www.google-analytics.com https://analytics.google.com https://*.google-analytics.com https://*.analytics.google.com https://stats.g.doubleclick.net https://www.googletagmanager.com",
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

        // Sitemap for places
        add_rewrite_rule(
            '^jg-map-sitemap\.xml$',
            'index.php?jg_map_sitemap=1',
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

        // Legacy flush check
        if (get_option('jg_map_needs_rewrite_flush', false)) {
            flush_rewrite_rules(false);
            delete_option('jg_map_needs_rewrite_flush');
        }
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
            if (preg_match('#^/(miejsce|ciekawostka|zgloszenie)/([^/]+)/?$#', $request_uri, $matches)) {
                $point_slug = sanitize_title($matches[2]);
            }
        }

        if (empty($point_slug)) {
            return;
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
                "SELECT id, title, slug, content, excerpt, lat, lng, address, type, status,
                        author_id, is_promo, website, phone, images, featured_image_index,
                        facebook_url, instagram_url, linkedin_url, tiktok_url, tags, created_at, updated_at
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
            get_template_part(404);
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

        try {
            $this->render_point_page($point, $request_id, $user_agent_short);
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

        // Page title
        $page_title = esc_html($point['title']) . ' - ' . esc_html($type_label) . ' w Jeleniej Górze';

        // Site logo URL
        $logo_url = '';
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        }

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
    <style>
        /* Standalone point page styles - no Elementor dependency */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #111; background: #f9fafb; line-height: 1.5; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; height: auto; display: block; }

        /* Minimal site header */
        .jg-sp-site-header {
            background: #fff; border-bottom: 1px solid #e5e7eb; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .jg-sp-site-header a { display: flex; align-items: center; gap: 10px; }
        .jg-sp-site-logo { height: 40px; width: auto; }
        .jg-sp-site-name { font-size: 16px; font-weight: 700; color: #111; }
        .jg-sp-site-nav a {
            font-size: 14px; font-weight: 600; color: #fff; background: <?php echo $type_color; ?>;
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
        .jg-sp-map-cta-text { font-size: 18px; font-weight: 700; color: #fff; line-height: 1.3; }
        .jg-sp-map-cta-sub { font-size: 13px; opacity: 0.9; margin-top: 4px; color: #fff; }
        .jg-sp-map-cta-arrow { font-size: 28px; flex-shrink: 0; color: #fff; animation: jg-cta-arrow-nudge 1s ease-in-out infinite; }
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
        .jg-sp-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.5; }

        /* Title */
        .jg-sp-title { font-size: 2rem; font-weight: 800; color: #111; margin: 0 0 8px 0; line-height: 1.2; }
        .jg-sp-date { font-size: 13px; color: #9ca3af; margin-bottom: 18px; }

        /* Content */
        .jg-sp-content { font-size: 16px; line-height: 1.75; color: #374151; margin-bottom: 24px; word-wrap: break-word; }
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
        .jg-sp-contact-link { color: #2563eb; font-size: 15px; display: inline-flex; align-items: center; gap: 6px; }
        .jg-sp-contact-link:hover { text-decoration: underline; }
        .jg-sp-social {
            display: inline-flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; border-radius: 50%;
            color: #fff; transition: opacity 0.15s;
        }
        .jg-sp-social:hover { opacity: 0.8; }
        .jg-sp-social svg { width: 20px; height: 20px; fill: #fff; }

        /* Address */
        .jg-sp-address { font-size: 15px; color: #6b7280; margin-bottom: 24px; }

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
            padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
            color: #fff; border: none; cursor: pointer; transition: opacity 0.15s; line-height: 1.4;
        }
        .jg-sp-share-btn:hover { opacity: 0.85; }
        .jg-sp-share-btn--fb { background: #1877f2; }
        .jg-sp-share-btn--wa { background: #25d366; }
        .jg-sp-share-btn--copy { background: #6b7280; }

        /* Footer */
        .jg-sp-site-footer {
            background: #1f2937; color: #9ca3af; text-align: center;
            padding: 24px 20px; font-size: 13px; line-height: 1.6;
        }
        .jg-sp-site-footer a { color: #d1d5db; text-decoration: underline; }
        .jg-sp-site-footer a:hover { color: #fff; }

        @media (max-width: 640px) {
            .jg-sp { padding: 16px 12px 32px; }
            .jg-sp-title { font-size: 1.5rem; }
            .jg-sp-map-cta { padding: 14px 16px; }
            .jg-sp-map-cta-text { font-size: 16px; }
            .jg-sp-gallery { grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); }
            .jg-sp-hero-img { max-height: 300px; }
            .jg-sp-site-name { display: none; }
        }
    </style>
</head>
<body>
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
            <?php if (!empty($point['website']) || !empty($point['phone']) || !empty($point['facebook_url']) || !empty($point['instagram_url']) || !empty($point['linkedin_url']) || !empty($point['tiktok_url'])): ?>
                <div class="jg-sp-contact">
                    <?php if (!empty($point['website'])): ?>
                        <a href="<?php echo esc_url($point['website']); ?>" target="_blank" rel="noopener" class="jg-sp-contact-link">&#127760; <?php echo esc_html(parse_url($point['website'], PHP_URL_HOST) ?: $point['website']); ?></a>
                    <?php endif; ?>
                    <?php if (!empty($point['phone'])): ?>
                        <a href="tel:<?php echo esc_attr($point['phone']); ?>" class="jg-sp-contact-link">&#128222; <?php echo esc_html($point['phone']); ?></a>
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

            <!-- Address -->
            <?php if (!empty($point['address'])): ?>
                <div class="jg-sp-address">&#128205; <?php echo esc_html($point['address']); ?></div>
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
            <h2 style="font-size:18px;font-weight:700;color:#374151;margin-bottom:16px;">Inne miejsca w Jeleniej Górze</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
                <?php foreach ($related as $r):
                    $path = isset($type_paths[$r['type']]) ? $type_paths[$r['type']] : 'miejsce';
                    $url = home_url('/' . $path . '/' . $r['slug'] . '/');
                    $label = isset($type_labels[$r['type']]) ? $type_labels[$r['type']] : '';
                ?>
                <a href="<?php echo esc_url($url); ?>" style="display:block;padding:14px 16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;transition:border-color 0.15s;">
                    <div style="font-size:14px;font-weight:600;color:#111;line-height:1.4;"><?php echo esc_html($r['title']); ?></div>
                    <?php if (!empty($r['address'])): ?>
                    <div style="font-size:12px;color:#9ca3af;margin-top:4px;"><?php echo esc_html($r['address']); ?></div>
                    <?php endif; ?>
                    <div style="font-size:11px;color:#6b7280;margin-top:4px;"><?php echo esc_html($label); ?></div>
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
            $description = wp_trim_words(strip_tags($point['excerpt']), 25);
        } elseif (!empty($point['content'])) {
            $description = wp_trim_words(strip_tags($point['content']), 25);
        } else {
            $description = 'Interaktywna mapa miasta Jelenia Góra. Dodaj miejsca, ciekawostki, zgłoś sprawę.';
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

        ?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($point['title']); ?> - Jelenia Góra</title>
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta name="robots" content="<?php echo esc_attr($robots_content); ?>">
    <link rel="canonical" href="<?php echo esc_url($url); ?>">
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
    ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "<?php echo $point['type'] === 'miejsce' ? 'LocalBusiness' : 'Place'; ?>",
                "@id": <?php echo json_encode($url . '#place'); ?>,
                "name": <?php echo json_encode($point['title']); ?>,
                "description": <?php echo json_encode($description); ?>,
                "url": <?php echo json_encode($url); ?>,
                "image": <?php echo json_encode($first_image); ?>,
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
                <?php if (!empty($point['website'])): ?>
                ,"url": <?php echo json_encode($point['website']); ?>
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
        <?php if (!empty($point['website'])): ?>
        <a href="<?php echo esc_url($point['website']); ?>" target="_blank" rel="noopener" class="jg-fb-site">Odwiedź stronę</a>
        <?php endif; ?>
        <?php if (!empty($point['phone'])): ?>
        <a href="tel:<?php echo esc_attr($point['phone']); ?>"><?php echo esc_html($point['phone']); ?></a>
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
            $description = wp_trim_words(strip_tags($point['excerpt']), 25);
        } elseif (!empty($point['content'])) {
            $description = wp_trim_words(strip_tags($point['content']), 25);
        } else {
            $description = 'Interaktywna mapa miasta Jelenia Góra. Dodaj miejsca, ciekawostki, zgłoś sprawę.';
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
        <meta property="og:title" content="<?php echo esc_attr($point['title']); ?>">
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
        <meta name="twitter:title" content="<?php echo esc_attr($point['title']); ?>">
        <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
        <meta name="twitter:image" content="<?php echo esc_url($first_image); ?>">
        <meta name="twitter:image:alt" content="<?php echo esc_attr($point['title']); ?>">

        <!-- Geo tags -->
        <meta name="geo.position" content="<?php echo esc_attr($point['lat'] . ';' . $point['lng']); ?>">
        <meta name="ICBM" content="<?php echo esc_attr($point['lat'] . ', ' . $point['lng']); ?>">

        <!-- Canonical URL -->
        <link rel="canonical" href="<?php echo esc_url($url); ?>">

        <!-- Schema.org JSON-LD structured data -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@graph": [
                {
                    "@type": "<?php echo $point['type'] === 'miejsce' ? 'LocalBusiness' : 'Place'; ?>",
                    "@id": <?php echo json_encode($url . '#place'); ?>,
                    "name": <?php echo json_encode($point['title']); ?>,
                    "description": <?php echo json_encode($description); ?>,
                    "url": <?php echo json_encode($url); ?>,
                    "image": <?php echo json_encode($first_image); ?>,
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
                    <?php if (!empty($point['website'])): ?>
                    ,"url": <?php echo json_encode($point['website']); ?>
                    <?php endif; ?>
                    <?php
                    // sameAs should reference social media profiles, not the business website
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

        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
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

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="<?php echo esc_attr('#' . $tag . ' - Miejsca w Jeleniej Górze'); ?>">
        <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">

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
