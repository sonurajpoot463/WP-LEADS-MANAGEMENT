<?php
/**
 * Plugin Name: WP Leads Management
 * Plugin URI: https://example.com/wp-leads-management
 * Description: A comprehensive lead management system for WordPress
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wp-leads-management
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPLM_VERSION', '1.0.0');
define('WPLM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPLM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPLM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once WPLM_PLUGIN_DIR . 'includes/class-wplm-installer.php';
require_once WPLM_PLUGIN_DIR . 'includes/class-wplm-lead.php';
require_once WPLM_PLUGIN_DIR . 'admin/class-wplm-leads-list-table.php';
require_once WPLM_PLUGIN_DIR . 'includes/class-wplm-form-handler.php';
require_once WPLM_PLUGIN_DIR . 'includes/class-wplm-shortcodes.php';
require_once WPLM_PLUGIN_DIR . 'includes/class-wplm-notifications.php';
require_once WPLM_PLUGIN_DIR . 'admin/class-wplm-admin.php';
require_once WPLM_PLUGIN_DIR . 'includes/class-wplm-api.php';

/**
 * Main plugin class
 */
class WP_Leads_Management {
    /**
     * Instance of this class
     *
     * @var WP_Leads_Management
     */
    protected static $instance = null;

    /**
     * Admin class instance
     *
     * @var WPLM_Admin
     */
    public $admin;

    /**
     * Lead class instance
     *
     * @var WPLM_Lead
     */
    public $lead;

    /**
     * Form handler class instance
     *
     * @var WPLM_Form_Handler
     */
    public $form_handler;

    /**
     * Shortcodes class instance
     *
     * @var WPLM_Shortcodes
     */
    public $shortcodes;

    /**
     * Notifications class instance
     *
     * @var WPLM_Notifications
     */
    public $notifications;

    /**
     * API class instance
     *
     * @var WPLM_API
     */
    public $api;

    /**
     * Get instance of this class
     *
     * @return WP_Leads_Management
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
    public function __construct() {
        // Initialize classes first
        $this->init_classes();
        
        // Then initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array('WPLM_Installer', 'install'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add admin menu
        if (is_admin()) {
            add_action('admin_menu', array($this->admin, 'register_admin_menu'));
        }
    }

    /**
     * Initialize classes
     */
    private function init_classes() {
        // Initialize lead class first as other classes depend on it
        $this->lead = new WPLM_Lead();
        
        // Then initialize other classes
        $this->admin = new WPLM_Admin();
        $this->form_handler = new WPLM_Form_Handler();
        $this->shortcodes = new WPLM_Shortcodes();
        $this->notifications = new WPLM_Notifications();
        $this->api = new WPLM_API();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-leads-management', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Bootstrap
        wp_enqueue_style('bootstrap', WPLM_PLUGIN_URL . 'assets/css/bootstrap.min.css', array(), '5.3.0');
        wp_enqueue_script('bootstrap', WPLM_PLUGIN_URL . 'assets/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
        
        // Plugin styles
        wp_enqueue_style('wplm-styles', WPLM_PLUGIN_URL . 'assets/css/wplm-styles.css', array(), WPLM_VERSION);
        
        // Plugin scripts
        wp_enqueue_script('wplm-scripts', WPLM_PLUGIN_URL . 'assets/js/wplm-scripts.js', array('jquery'), WPLM_VERSION, true);
        
        // Localize script
        wp_localize_script('wplm-scripts', 'wplm_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wplm-nonce'),
            'i18n' => array(
                'submit_success' => __('Thank you! Your information has been submitted successfully.', 'wp-leads-management'),
                'submit_error' => __('Error submitting form. Please try again.', 'wp-leads-management'),
                'required_field' => __('This field is required.', 'wp-leads-management'),
                'invalid_email' => __('Please enter a valid email address.', 'wp-leads-management'),
                'invalid_phone' => __('Please enter a valid phone number.', 'wp-leads-management')
            )
        ));
    }
}

// Initialize the plugin
function WPLM() {
    return WP_Leads_Management::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'WPLM');