<?php
/**
 * Plugin Name: JG Interactive Map
 * Plugin URI: https://jeleniogorzanietomy.pl
 * Description: Interaktywna mapa Jeleniej Góry z możliwością dodawania zgłoszeń, ciekawostek i miejsc
 * Version: 3.2.4
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
define('JG_MAP_VERSION', '3.2.4');
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
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-enqueue.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-shortcode.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-admin.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array('JG_Map_Database', 'activate'));
        register_deactivation_hook(__FILE__, array('JG_Map_Database', 'deactivate'));

        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));

        // Load text domain
        add_action('init', array($this, 'load_textdomain'));

        // Set email sender name and address
        add_filter('wp_mail_from_name', array($this, 'set_email_from_name'));
        add_filter('wp_mail_from', array($this, 'set_email_from'));

        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));

        // SEO-friendly URLs for points
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_point_page'));
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
            $domain = wp_parse_url(home_url(), PHP_URL_HOST);
            return 'noreply@' . $domain;
        }
        return $from_email;
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Check and update database schema on every load (only runs if needed)
        JG_Map_Database::check_and_update_schema();

        JG_Map_Activity_Log::get_instance();
        JG_Map_Enqueue::get_instance();
        JG_Map_Shortcode::get_instance();
        JG_Map_Ajax_Handlers::get_instance();
        JG_Map_Admin::get_instance();
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
     * Add security headers including CSP
     */
    public function add_security_headers() {
        // Only add headers for frontend (not admin)
        if (is_admin()) {
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
        add_rewrite_rule(
            '^miejsce/([^/]+)/?$',
            'index.php?jg_map_point=$matches[1]',
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'jg_map_point';
        return $vars;
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
            'googlebot',           // Google
            'bingbot',            // Bing
            'slurp',              // Yahoo
            'duckduckbot',        // DuckDuckGo
            'baiduspider',        // Baidu
            'yandexbot',          // Yandex
            'facebookexternalhit', // Facebook
            'twitterbot',         // Twitter
            'whatsapp',           // WhatsApp
            'telegram',           // Telegram
            'linkedinbot',        // LinkedIn
            'pinterestbot',       // Pinterest
            'slackbot',           // Slack
            'discordbot',         // Discord
            'applebot',           // Apple
            'ia_archiver',        // Alexa
            'semrushbot',         // SEMrush
            'ahrefsbot',          // Ahrefs
            'mj12bot',            // Majestic
            'dotbot',             // Moz
            'rogerbot',           // Moz
            'petalbot',           // Huawei
            'seznambot',          // Seznam
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

        // Get point by slug
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $point = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, content, excerpt, lat, lng, type, status,
                        author_id, is_promo, website, phone, images, created_at
                 FROM $table
                 WHERE LOWER(REPLACE(REPLACE(REPLACE(title, ' ', '-'), 'ł', 'l'), 'ą', 'a')) = %s
                 AND status = 'publish'
                 LIMIT 1",
                strtolower($point_slug)
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

        // Check if visitor is a bot
        if ($this->is_bot()) {
            // Bots get full HTML page with meta tags for SEO
            global $jg_current_point;
            $jg_current_point = $point;

            $this->render_point_page($point);
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
    private function render_point_page($point) {
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

        $images = json_decode($point['images'], true) ?: array();
        $first_image = !empty($images) ? $images[0] : '';

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
                    · 📅 <?php echo date('d.m.Y', strtotime($point['created_at'])); ?>
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
        // Get site footer
        get_footer();
    }

    /**
     * Add SEO meta tags for point pages
     */
    public function add_point_meta_tags() {
        global $jg_current_point;

        if (empty($jg_current_point)) {
            return;
        }

        // Check if search engines are discouraged
        if (get_option('blog_public') == '0') {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
            return;
        }

        $point = $jg_current_point;
        $images = json_decode($point['images'], true) ?: array();
        $first_image = !empty($images) ? $images[0] : '';
        $description = !empty($point['excerpt']) ? $point['excerpt'] : wp_trim_words(strip_tags($point['content']), 30);
        $url = home_url('/miejsce/' . $this->generate_slug($point['title']) . '/');

        ?>
        <meta name="description" content="<?php echo esc_attr($description); ?>">
        <meta name="robots" content="index, follow">

        <!-- Open Graph -->
        <meta property="og:type" content="article">
        <meta property="og:title" content="<?php echo esc_attr($point['title']); ?>">
        <meta property="og:description" content="<?php echo esc_attr($description); ?>">
        <meta property="og:url" content="<?php echo esc_url($url); ?>">
        <?php if ($first_image): ?>
        <meta property="og:image" content="<?php echo esc_url($first_image); ?>">
        <?php endif; ?>
        <meta property="og:site_name" content="<?php bloginfo('name'); ?>">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo esc_attr($point['title']); ?>">
        <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
        <?php if ($first_image): ?>
        <meta name="twitter:image" content="<?php echo esc_url($first_image); ?>">
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
     * Generate slug from title
     */
    private function generate_slug($title) {
        $slug = strtolower($title);

        // Polish characters transliteration
        $polish = array('ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż', 'Ą', 'Ć', 'Ę', 'Ł', 'Ń', 'Ó', 'Ś', 'Ź', 'Ż');
        $latin = array('a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z');
        $slug = str_replace($polish, $latin, $slug);

        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
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
