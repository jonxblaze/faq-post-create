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
    }
    
    /**
     * Get post type registration arguments
     */
    public static function get_post_type_args() {
        return array(
            'labels' => array(
                'name' => __('FAQs', 'faq-post-create'),
                'singular_name' => __('FAQ', 'faq-post-create'),
                'add_new' => __('Add New', 'faq-post-create'),
                'add_new_item' => __('Add New FAQ', 'faq-post-create'),
                'edit_item' => __('Edit FAQ', 'faq-post-create'),
                'new_item' => __('New FAQ', 'faq-post-create'),
                'view_item' => __('View FAQ', 'faq-post-create'),
                'search_items' => __('Search FAQs', 'faq-post-create'),
                'not_found' => __('No FAQs found', 'faq-post-create'),
                'not_found_in_trash' => __('No FAQs found in Trash', 'faq-post-create'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'faqs', 'with_front' => false),
            'capability_type' => 'post',
            'has_archive' => true,
            'supports' => array('title', 'custom-fields'), // Removed editor to use our custom meta box
            'menu_position' => 5,
            'menu_icon' => 'dashicons-editor-help',
            'can_export' => true,
            'show_in_rest' => true, // Enable Gutenberg support
        );
    }
    
    /**
     * Register the custom post type for FAQs
     */
    public static function register_post_type() {
        $args = self::get_post_type_args();
        register_post_type('faq', $args);
    }
    
    /**
     * Load custom template for single FAQ posts
     */
    public static function faq_single_template($template) {
        if (is_singular('faq')) {
            // Include the template file
            $template_path = plugin_dir_path(__FILE__) . '../templates/single-faq.php';
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        return $template;
    }
    
    /**
     * Plugin activation tasks
     */
    public static function activate() {
        // Register the post type to ensure rewrite rules are properly set
        self::register_post_type();
        
        // Set version option
        update_option('faq_post_creator_version', FAQ_Post_Create::VERSION);
    }
}
