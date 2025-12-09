<?php
/**
 * Plugin Name: JG Interactive Map
 * Plugin URI: https://jeleniogorzanietomy.pl
 * Description: Interaktywna mapa Jeleniej Góry z możliwością dodawania zgłoszeń, ciekawostek i miejsc
 * Version: 2.9.7
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
define('JG_MAP_VERSION', '2.9.7');
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

        // Set email sender name
        add_filter('wp_mail_from_name', array($this, 'set_email_from_name'));
    }

    /**
     * Set email sender name for all emails from this plugin
     */
    public function set_email_from_name($from_name) {
        // Only modify emails from this plugin (check if we're in a plugin context)
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && strpos($trace['file'], 'jg-interactive-map') !== false) {
                return 'Jeleniogorzanie to my';
            }
        }
        return $from_name;
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Check and update database schema on every load (only runs if needed)
        JG_Map_Database::check_and_update_schema();

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
}

/**
 * Initialize the plugin
 */
function jg_interactive_map() {
    return JG_Interactive_Map::get_instance();
}

// Start the plugin
jg_interactive_map();
