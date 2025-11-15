<?php
/**
 * Main Plugin Class
 * 
 * @package FAQ_Post_Create
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main FAQ Post Creator Plugin Class
 */
class FAQ_Post_Create {
    
    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Constructor
     */
    public function __construct() {
        $this->version = $this->get_plugin_version();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load all required class files
     */
    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-faq-post-type.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-faq-form-handler.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-faq-template-handler.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-faq-admin.php';
    }
    
    /**
     * Initialize all WordPress hooks
     */
    private function init_hooks() {
        // Initialize the post type
        add_action('init', array('FAQ_Post_Type', 'init'));
        
        // Initialize form handling
        add_action('init', array('FAQ_Form_Handler', 'init'));
        
        // Initialize template handling
        add_action('init', array('FAQ_Template_Handler', 'init'));
        
        // Initialize admin functionality
        add_action('init', array('FAQ_Admin', 'init'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
        register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'faq-submission-script',
            plugin_dir_url(__FILE__) . 'assets/faq-submission.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_enqueue_style(
            'faq-styles',
            plugin_dir_url(__FILE__) . 'assets/faq-styles.css',
            array(),
            $this->version
        );

        // Localize script for AJAX
        wp_localize_script('faq-submission-script', 'faq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('faq_nonce')
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts() {
        // Add admin-specific scripts and styles if needed
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Register the post type during activation to ensure rewrite rules are properly set
        $args = FAQ_Post_Type::get_post_type_args();
        register_post_type('faq', $args);
        
        // Set version option
        update_option('faq_post_creator_version', FAQ_Post_Create::get_plugin_version_static());
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Get the plugin version dynamically from the plugin header.
     *
     * @return string The plugin version.
     */
    private function get_plugin_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . 'faq-post-create.php');
        return $plugin_data['Version'];
    }

    /**
     * Static method to get the plugin version dynamically from the plugin header.
     * Used for static contexts like activation hooks.
     *
     * @return string The plugin version.
     */
    public static function get_plugin_version_static() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . 'faq-post-create.php');
        return $plugin_data['Version'];
    }
}
