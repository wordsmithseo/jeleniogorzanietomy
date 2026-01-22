<?php
/**
 * Plugin Name: JG Interactive Map
 * Plugin URI: https://jeleniogorzanietomy.pl
 * Description: Interaktywna mapa Jeleniej Góry z możliwością dodawania zgłoszeń, ciekawostek i miejsc
 * Version: 3.7.5
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
define('JG_MAP_VERSION', '3.7.5');
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

        // SEO-friendly URLs for points
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('init', array($this, 'check_rewrite_flush'), 999); // Run late to ensure rewrite rules are added first
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_point_page'));
        add_action('template_redirect', array($this, 'handle_sitemap'));
        add_action('wp_head', array($this, 'add_point_meta_tags'));
    }

    /**
     * Set email sender name for emails from this plugin
     */
    public function set_email_from_name($from_name) {
        // Check if this is called from plugin context using a more efficient method
        if (doing_filter('jg_map_email')) {
            return 'Jeleniogorzanie to my';
        }
        return $from_name;
    }

    /**
     * Set email sender address for emails from this plugin
     */
    public function set_email_from($from_email) {
        // Check if this is called from plugin context
        if (doing_filter('jg_map_email')) {
            return 'powiadomienia@jeleniogorzanietomy.pl';
        }
        return $from_email;
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
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self'",
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
        return $vars;
    }

    /**
     * Check and flush rewrite rules if needed
     * Runs on 'init' hook when $wp_rewrite is available
     */
    public function check_rewrite_flush() {
        // TEMPORARY: Aggressive flush until sitemap works
        $flush_count = get_option('jg_map_flush_count', 0);

        if ($flush_count < 3) {
            flush_rewrite_rules(false);
            update_option('jg_map_flush_count', $flush_count + 1);
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
                "SELECT id, title, slug, content, excerpt, lat, lng, type, status,
                        author_id, is_promo, website, phone, images, featured_image_index,
                        facebook_url, instagram_url, linkedin_url, tiktok_url, created_at
                 FROM $table
                 WHERE slug = %s AND status = 'publish'
                 LIMIT 1",
                $point_slug
            ),
            ARRAY_A
        );

        if (!$point) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            exit;
        }

        $db_time = microtime(true) - $start_time;

        // Check if visitor is a bot
        $is_bot = $this->is_bot();

        if ($is_bot) {
            // Bots get full HTML page with meta tags for SEO

            // Check if headers already sent
            if (headers_sent($file, $line)) {
            }

            // Ensure HTTP 200 status
            status_header(200);

            // Prevent WordPress from doing redirects or 404 handling
            remove_action('template_redirect', 'redirect_canonical');

            global $jg_current_point;
            $jg_current_point = $point;

            // Start output buffering to capture entire page
            $render_start = microtime(true);
            ob_start();

            try {
                $this->render_point_page($point, $request_id, $user_agent_short);

                // Get the rendered content
                $html_output = ob_get_clean();
                $html_size = strlen($html_output);
                $render_time = microtime(true) - $render_start;
                $total_time = microtime(true) - $start_time;


                // Check for PHP errors in output
                if (stripos($html_output, 'fatal error') !== false ||
                    stripos($html_output, 'parse error') !== false ||
                    stripos($html_output, 'warning:') !== false) {
                }

                // Check if HTML is reasonable size (not empty, not too small)
                if ($html_size < 100) {
                }

                // Output the captured HTML
                echo $html_output;

            } catch (Exception $e) {
                ob_end_clean();

                // Fallback: render minimal HTML
                $this->render_fallback_page($point, $request_id, $user_agent_short);
            }
            exit;
        } else {
            // Humans get redirected to map with modal
            wp_redirect(home_url('/#point-' . $point['id']));
            exit;
        }
    }

    /**
     * Render single point page
     */
    private function render_point_page($point, $request_id = 'unknown', $user_agent_short = '') {
        $header_start = microtime(true);

        // Set page title for SEO
        add_filter('pre_get_document_title', function() use ($point) {
            $type_labels = array(
                'miejsce' => 'Miejsce',
                'ciekawostka' => 'Ciekawostka',
                'zgloszenie' => 'Zgłoszenie'
            );
            $type_label = isset($type_labels[$point['type']]) ? $type_labels[$point['type']] : 'Punkt';
            return $point['title'] . ' - ' . $type_label . ' w Jeleniej Górze';
        }, 999);

        // Get site header
        get_header();
        $header_time = microtime(true) - $header_start;

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

        // Type labels
        $type_labels = array(
            'miejsce' => 'Miejsce',
            'ciekawostka' => 'Ciekawostka',
            'zgloszenie' => 'Zgłoszenie'
        );
        $type_label = isset($type_labels[$point['type']]) ? $type_labels[$point['type']] : 'Punkt';

        ?>
        <style>
            .jg-single-point {
                max-width: 1200px;
                margin: 40px auto;
                padding: 0 20px;
            }
            .jg-point-header {
                margin-bottom: 30px;
            }
            .jg-point-type {
                display: inline-block;
                padding: 6px 12px;
                background: <?php echo $point['is_promo'] ? '#fbbf24' : ($point['type'] === 'miejsce' ? '#8d2324' : ($point['type'] === 'ciekawostka' ? '#3b82f6' : '#ef4444')); ?>;
                color: <?php echo $point['is_promo'] ? '#111' : '#fff'; ?>;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 10px;
            }
            .jg-point-title {
                font-size: 42px;
                font-weight: 700;
                margin: 0 0 15px 0;
                color: #111;
            }
            .jg-point-meta {
                color: #666;
                font-size: 14px;
                margin-bottom: 20px;
            }
            .jg-point-content {
                font-size: 18px;
                line-height: 1.7;
                color: #333;
                margin-bottom: 30px;
            }
            .jg-point-image {
                width: 100%;
                max-width: 800px;
                height: auto;
                border-radius: 8px;
                margin-bottom: 30px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .jg-point-map {
                width: 100%;
                height: 400px;
                border-radius: 8px;
                margin-bottom: 30px;
            }
            .jg-point-cta {
                margin-top: 30px;
            }
            .jg-point-cta a {
                display: inline-block;
                padding: 12px 24px;
                background: #8d2324;
                color: #fff;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                transition: background 0.2s;
            }
            .jg-point-cta a:hover {
                background: #a02829;
            }
        </style>

        <div class="jg-single-point">
            <div class="jg-point-header">
                <span class="jg-point-type"><?php echo esc_html($type_label); ?></span>
                <h1 class="jg-point-title"><?php echo esc_html($point['title']); ?></h1>
                <div class="jg-point-meta">
                    📍 <?php echo esc_html($point['lat']); ?>, <?php echo esc_html($point['lng']); ?>
                    · 📅 <?php echo get_date_from_gmt($point['created_at'], 'd.m.Y'); ?>
                </div>
            </div>

            <?php if ($first_image): ?>
                <img src="<?php echo esc_url($first_image); ?>" alt="<?php echo esc_attr($point['title']); ?>" class="jg-point-image">
            <?php endif; ?>

            <div class="jg-point-content">
                <?php echo wp_kses_post($point['content']); ?>
            </div>

            <?php if (!empty($point['website']) || !empty($point['phone'])): ?>
                <div class="jg-point-cta">
                    <?php if (!empty($point['website'])): ?>
                        <a href="<?php echo esc_url($point['website']); ?>" target="_blank" rel="noopener">
                            🌐 Odwiedź stronę
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($point['phone'])): ?>
                        <a href="tel:<?php echo esc_attr($point['phone']); ?>">
                            📞 <?php echo esc_html($point['phone']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div id="jg-point-map-<?php echo $point['id']; ?>" class="jg-point-map"></div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof L !== 'undefined') {
                        var map = L.map('jg-point-map-<?php echo $point['id']; ?>').setView([<?php echo $point['lat']; ?>, <?php echo $point['lng']; ?>], 16);
                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap'
                        }).addTo(map);
                        L.marker([<?php echo $point['lat']; ?>, <?php echo $point['lng']; ?>]).addTo(map);
                    }
                });
            </script>
        </div>

        <?php
        $footer_start = microtime(true);
        // Get site footer
        get_footer();
        $footer_time = microtime(true) - $footer_start;
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
    <?php if ($first_image): ?>
    <meta property="og:image" content="<?php echo esc_url($first_image); ?>">
    <?php endif; ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "<?php echo $point['type'] === 'miejsce' ? 'LocalBusiness' : 'Place'; ?>",
        "name": <?php echo json_encode($point['title']); ?>,
        "description": <?php echo json_encode($description); ?>,
        "url": <?php echo json_encode($url); ?>,
        <?php if ($first_image): ?>"image": <?php echo json_encode($first_image); ?>,<?php endif; ?>
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": <?php echo json_encode($point['lat']); ?>,
            "longitude": <?php echo json_encode($point['lng']); ?>
        },
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Jelenia Góra",
            "addressCountry": "PL"
        }
    }
    </script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.6; }
        h1 { color: #8d2324; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <h1><?php echo esc_html($point['title']); ?></h1>
    <?php if ($first_image): ?>
    <img src="<?php echo esc_url($first_image); ?>" alt="<?php echo esc_attr($point['title']); ?>">
    <?php endif; ?>
    <div><?php echo wp_kses_post($point['content']); ?></div>
    <p><strong>Lokalizacja:</strong> <?php echo esc_html($point['lat']); ?>, <?php echo esc_html($point['lng']); ?></p>
    <?php if (!empty($point['website'])): ?>
    <p><a href="<?php echo esc_url($point['website']); ?>" target="_blank" rel="noopener">Odwiedź stronę</a></p>
    <?php endif; ?>
    <?php if (!empty($point['phone'])): ?>
    <p><a href="tel:<?php echo esc_attr($point['phone']); ?>">Telefon: <?php echo esc_html($point['phone']); ?></a></p>
    <?php endif; ?>
</body>
</html><?php
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
        <?php if ($first_image): ?>
        <meta property="og:image" content="<?php echo esc_url($first_image); ?>">
        <meta property="og:image:secure_url" content="<?php echo esc_url($first_image); ?>">
        <meta property="og:image:alt" content="<?php echo esc_attr($point['title']); ?>">
        <meta property="og:image:type" content="image/jpeg">
        <?php endif; ?>

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo esc_attr($point['title']); ?>">
        <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
        <?php if ($first_image): ?>
        <meta name="twitter:image" content="<?php echo esc_url($first_image); ?>">
        <meta name="twitter:image:alt" content="<?php echo esc_attr($point['title']); ?>">
        <?php endif; ?>

        <!-- Geo tags -->
        <meta name="geo.position" content="<?php echo esc_attr($point['lat'] . ';' . $point['lng']); ?>">
        <meta name="ICBM" content="<?php echo esc_attr($point['lat'] . ', ' . $point['lng']); ?>">

        <!-- Canonical URL -->
        <link rel="canonical" href="<?php echo esc_url($url); ?>">

        <!-- Schema.org JSON-LD structured data -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "<?php echo $point['type'] === 'miejsce' ? 'LocalBusiness' : 'Place'; ?>",
            "name": <?php echo json_encode($point['title']); ?>,
            "description": <?php echo json_encode($description); ?>,
            "url": <?php echo json_encode($url); ?>,
            <?php if ($first_image): ?>
            "image": <?php echo json_encode($first_image); ?>,
            <?php endif; ?>
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": <?php echo json_encode($point['lat']); ?>,
                "longitude": <?php echo json_encode($point['lng']); ?>
            },
            "address": {
                "@type": "PostalAddress",
                "addressLocality": "Jelenia Góra",
                "addressCountry": "PL"
            }
            <?php if (!empty($point['phone'])): ?>
            ,"telephone": <?php echo json_encode($point['phone']); ?>
            <?php endif; ?>
            <?php if (!empty($point['website'])): ?>
            ,"sameAs": <?php echo json_encode($point['website']); ?>
            <?php endif; ?>
        }
        </script>
        <?php
    }

    /**
     * Handle sitemap.xml generation
     */
    public function handle_sitemap() {
        // ALTERNATIVE APPROACH: Check URL directly instead of relying on query_var
        // This bypasses rewrite rule issues
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'];

        // Check if this is a sitemap request (direct URL check)
        if (strpos($request_uri, 'jg-map-sitemap.xml') === false) {
            return;
        }


        // Clean all output buffers to prevent any content before headers
        while (ob_get_level()) {
            ob_end_clean();
        }

        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        // Get all published points with slug - with error handling
        $points = $wpdb->get_results(
            "SELECT id, title, slug, type, updated_at
             FROM $table
             WHERE status = 'publish' AND slug IS NOT NULL AND slug != ''
             ORDER BY updated_at DESC",
            ARRAY_A
        );

        // Check for database errors
        if ($wpdb->last_error) {
            status_header(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Sitemap generation error. Please contact administrator.';
            exit;
        }

        // Ensure we have results (could be empty array)
        if (!is_array($points)) {
            $points = array();
        }


        // Set HTTP status code explicitly
        status_header(200);

        // Set headers for XML sitemap - no cache for debugging
        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: index, follow');

        // Start output buffering to capture the entire XML
        ob_start();

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo "\n";
        ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach ($points as $point):
    // Determine URL path based on point type
    $type_path = 'miejsce'; // default
    if ($point['type'] === 'ciekawostka') {
        $type_path = 'ciekawostka';
    } elseif ($point['type'] === 'zgloszenie') {
        $type_path = 'zgloszenie';
    }

    $url = home_url('/' . $type_path . '/' . $point['slug'] . '/');
    $lastmod = get_date_from_gmt($point['updated_at'], 'Y-m-d');
?>
    <url>
        <loc><?php echo esc_url($url); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority><?php echo $point['type'] === 'miejsce' ? '0.8' : '0.6'; ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
        <?php
        $xml_content = ob_get_clean();

        // Log the sitemap size for debugging

        // Set Content-Length header for better compatibility
        header('Content-Length: ' . strlen($xml_content));

        echo $xml_content;
        exit;
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
