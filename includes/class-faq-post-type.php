<?php
/**
 * FAQ Post Type Handler
 * 
 * @package FAQ_Post_Create
 * @subpackage Post_Type
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles custom post type registration for FAQs
 */
class FAQ_Post_Type {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Register post type directly on init
        self::register_post_type();
        
        // Handle single template
        add_filter('single_template', array(__CLASS__, 'faq_single_template'));
        
        // Redirect archive requests to 404
        add_action('template_redirect', array(__CLASS__, 'redirect_archive_to_404'));
    }
    
    /**
     * Get post type registration arguments
     */
    public static function get_post_type_args() {
        return array(
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
            'supports' => array('title', 'custom-fields'), // Removed editor to use our custom meta box
            'menu_position' => 5,
            'menu_icon' => 'dashicons-editor-help',
            'can_export' => true,
            'show_in_rest' => true, // Enable Gutenberg support
        );
    }
    
    /**
     * Register the custom post type for Questions Answered
     */
    public static function register_post_type() {
        $args = self::get_post_type_args();
        register_post_type('questions-answered', $args);
    }
    
    /**
     * Load custom template for single Questions Answered posts
     */
    public static function faq_single_template($template) {
        if (is_singular('questions-answered')) {
            // Include the template file
            $template_path = plugin_dir_path(__FILE__) . '../templates/single-faq.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        return $template;
    }
    
    /**
     * Redirect archive requests to 404
     */
    public static function redirect_archive_to_404() {
        if (is_post_type_archive('questions-answered')) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            exit;
        }
    }

    /**
     * Plugin activation tasks
     */
    public static function activate() {
        // Register the post type to ensure rewrite rules are properly set
        self::register_post_type();

        // Set version option
        update_option('faq_post_creator_version', '1.0.4');
    }
}
