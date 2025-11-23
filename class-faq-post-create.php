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
        require_once plugin_dir_path(__FILE__) . 'includes/class-faq-settings.php';
        require_once plugin_dir_path(__FILE__) . 'import-csv.php';
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

        // Initialize settings functionality
        add_action('init', array('FAQ_Settings', 'init'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
        register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

        // Hook to flush rewrite rules when plugin is initialized
        add_action('init', array($this, 'maybe_flush_rewrite_rules'));
    }

    /**
     * Maybe flush rewrite rules on init
     */
    public function maybe_flush_rewrite_rules() {
        // Only flush rewrite rules once during plugin initialization
        if (!get_option('faq_post_creator_rewrite_rules_flushed')) {
            flush_rewrite_rules();
            update_option('faq_post_creator_rewrite_rules_flushed', true);
        }
    }

    /**
     * Function to manually flush rewrite rules when needed
     */
    public static function flush_faq_rewrite_rules() {
        flush_rewrite_rules(false);
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

        // Enqueue Font Awesome CDN
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
            array(),
            '6.5.2'
        );

        // Localize script for AJAX
        wp_localize_script('faq-submission-script', 'faq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('faq_nonce')
        ));

        // Check if reCAPTCHA is enabled and load the API if needed
        if (class_exists('FAQ_Settings')) {
            $settings = FAQ_Settings::get_settings();
            $recaptcha_enabled = !empty($settings['recaptcha_enabled']);
            $recaptcha_site_key = $settings['recaptcha_site_key'];

            if ($recaptcha_enabled && !empty($recaptcha_site_key)) {
                // Load reCAPTCHA API for auto-rendering
                wp_enqueue_script(
                    'google-recaptcha',
                    'https://www.google.com/recaptcha/api.js',
                    array(),
                    null,
                    false // Load in header for proper initialization
                );
            }
        }
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
        // Use static labels during activation to avoid translation issues
        $args = array(
            'labels' => array(
                'name' => 'Questions',
                'singular_name' => 'Question Answered',
                'add_new' => null, // Disable "Add New" from admin
                'add_new_item' => null, // Disable "Add New Question Answered"
                'edit_item' => 'Edit Question Answered',
                'new_item' => 'New Question Answered',
                'view_item' => 'View Question Answered',
                'search_items' => 'Search Questions Answered',
                'not_found' => 'No questions answered found',
                'not_found_in_trash' => 'No questions answered found in Trash',
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'has_archive' => false, // Disable archive index
            'rewrite' => array('slug' => 'questions-answered', 'with_front' => false),
            'capability_type' => 'post',
            'supports' => array('title', 'custom-fields'),
            'menu_position' => 5,
            'menu_icon' => 'dashicons-editor-help',
            'can_export' => true,
            'show_in_rest' => true,
        );

        register_post_type('questions-answered', $args);

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
        // Return static version to avoid text domain loading issues
        return '1.0.6';
    }

    /**
     * Static method to get the plugin version dynamically from the plugin header.
     * Used for static contexts like activation hooks.
     *
     * @return string The plugin version.
     */
    public static function get_plugin_version_static() {
        // Return static version to avoid text domain loading issues
        return '1.0.6';
    }
}
